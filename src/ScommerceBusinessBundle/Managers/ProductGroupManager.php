<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Context\AttributeContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\PagingFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SearchFilterHelper;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\ApplicationSettingsManager;
use AppBundle\Managers\CacheManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\RelatedLinksEntity;
use ScommerceBusinessBundle\Entity\SortOptionEntity;
use ScommerceBusinessBundle\Entity\SProductAttributeConfigurationEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use Symfony\Component\Cache\CacheItem;

class ProductGroupManager extends AbstractScommerceManager
{
    /** @var SproductManager $sProductManager */
    protected $sProductManager;
    /** @var EntityType $productEntityType */
    protected $productEntityType;
    /** @var EntityType $productGroupEntityType */
    protected $productGroupEntityType;
    /** @var DefaultScommerceManager $defaultScommerceManager */
    protected $defaultScommerceManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var AttributeContext $attributeContext */
    protected $attributeContext;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;
    /** @var AlgoliaManager $algoliaManager */
    protected $algoliaManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var ApplicationSettingsManager $applicationSettingsManager */
    protected $applicationSettingsManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var FacetManager $facetManager */
    protected $facetManager;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $id
     * @return |null
     */
    public function getProductGroupById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(ProductGroupEntity::class);
        return $repository->find($id);

        /*
        if (empty($this->productGroupEntityType)) {
            $this->productGroupEntityType = $this->entityManager->getEntityTypeByCode("product_group");
        }

        return $this->entityManager->getEntityByEntityTypeAndId($this->productGroupEntityType, $id);*/
    }

    /**
     * @param $url
     * @return mixed
     */
    public function getProductGroupByUrl($url)
    {

        if (empty($this->productGroupEntityType)) {
            $this->productGroupEntityType = $this->entityManager->getEntityTypeByCode("product_group");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("url", "eq", $url));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($this->productGroupEntityType, $compositeFilters);
    }

    /**
     * @param ProductGroupEntity $productGroup
     * @return array
     */
    public function getRelatedLinks(ProductGroupEntity $productGroup)
    {

        $links = array();

        $entityType = $this->entityManager->getEntityTypeByCode("related_links");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("productGroup", "eq", $productGroup->getId()));
        $compositeFilter->addFilter(new SearchFilter("productGroup.isActive", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $data = $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);

        if (empty($data)) {
            return $links;
        }

        /**
         * Get store from session
         */
        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");

        /** @var RelatedLinksEntity $d */
        foreach ($data as $d) {
            if (!empty($d->getRelatedProductGroup())) {
                $links[] = array("name" => $d->getName(), "target" => "", "rel" => "follow", "url" => $d->getRelatedProductGroup()->getUrlPath($storeId), "type" => $d->getRelatedLinkType());
            } elseif (!empty($d->getCustomUrl())) {
                $url = $d->getCustomUrl();
                if ($url[0] == "/") {
                    $url = substr($url, 1);
                }
                $links[] = array("name" => $d->getName(), "target" => "", "rel" => "follow", "url" => $url, "type" => $d->getRelatedLinkType());
            }
            /*else {
                $links[] = Array("name" => $d->getName(), "target" => "", "rel" => "follow", "url" => $d->getRelatedProductGroup()->getUrl());
            }*/
        }

        return $links;
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getHomepageProductGroups($limit = 10)
    {

        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");

        if (empty($this->productGroupEntityType)) {
            $this->productGroupEntityType = $this->entityManager->getEntityTypeByCode("product_group");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnHomepage", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("ord", "asc"));

        $data = $this->entityManager->getEntitiesByEntityTypeAndFilter($this->productGroupEntityType, $compositeFilters, $sortFilters);

        if (!empty($limit) && count($data) > $limit) {
            $data = array_slice($data, 0, $limit);
        }

        return $data;
    }

    /**
     * @param ProductGroupEntity $productGroup
     * @param string $sort
     * @return mixed
     */
    public function getChildProductGroups(ProductGroupEntity $productGroup, $sort = "ord")
    {

        if (empty($this->productGroupEntityType)) {
            $this->productGroupEntityType = $this->entityManager->getEntityTypeByCode("product_group");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("productGroup", "eq", $productGroup->getId()));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter($sort, "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($this->productGroupEntityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param $customCompositeFilter
     * @return mixed
     */
    public function getProductGroupsByFilter($customCompositeFilter = null)
    {

        if (empty($this->productGroupEntityType)) {
            $this->productGroupEntityType = $this->entityManager->getEntityTypeByCode("product_group");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($customCompositeFilter)) {
            $compositeFilters->addCompositeFilter($customCompositeFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("name", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($this->productGroupEntityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param SStoreEntity $storeEntity
     * @return mixed
     */
    public function getProductGroupsByStore(SStoreEntity $storeEntity)
    {
        if (empty($this->productGroupEntityType)) {
            $this->productGroupEntityType = $this->entityManager->getEntityTypeByCode("product_group");
        }

        $storeId = $storeEntity->getId();

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter('["name","$.\"' . $storeId . '\""]', "asc"));
        $groups = $this->entityManager->getEntitiesByEntityTypeAndFilter($this->productGroupEntityType, $compositeFilters, $sortFilters);

        return $groups;
    }

    /**
     * @param ProductGroupEntity $productGroup
     * @return array
     */
    public function getParentsTree(ProductGroupEntity $productGroup)
    {

        $ret = array();

        if (!empty($productGroup->getProductGroup())) {
            $productGroup = $productGroup->getProductGroup();
            $ret[] = $productGroup;
            $retTmp = $this->getParentsTree($productGroup);
            if (!empty($retTmp)) {
                $ret = array_merge($ret, $retTmp);
            }
        }

        return $ret;
    }

    /**
     * @param ProductEntity $productEntity
     * @param int $linkTypeId
     * @return bool
     */
    public function getRelatedProducts(ProductEntity $productEntity, $linkTypeId = 1)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT child_product_id FROM product_product_link_entity WHERE parent_product_id = {$productEntity->getId()} AND entity_state_id = 1 AND relation_type_id = {$linkTypeId} ORDER BY ord DESC;";
        return $this->databaseContext->getAll($q);
    }

    /**
     * @param $data
     * @return array
     * @throws \Exception
     */
    public function getFilteredProducts($data)
    {
        $ret = array();

        /**
         * Get store from session
         */
        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");

        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"] ?? null;
        }

        $ret["total"] = 0;
        $ret["entities"] = array();
        $ret["filter_data"] = array();
        $ret["error"] = false;

        if (empty($this->productEntityType)) {
            $this->productEntityType = $this->entityManager->getEntityTypeByCode("product");
        }

        /**
         * Get calculation type
         */
        if(empty($this->crmProcessManager)){
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        /**
         * If user is logged in, check for account discounts and account_group_discounts
         */

        $account = null;

        /** @var CoreUserEntity $coreUser */
        if (!isset($coreUser)) {
            $coreUser = $this->helperManager->getCurrentCoreUser();
        }

        if (!empty($coreUser)) {
            /** @var AccountEntity $account */
            $account = $coreUser->getDefaultAccount();
        }

        $priceAddon = "retail";
        if($this->crmProcessManager->getCalculationMethod(null,$account) == "Vpc"){
            $priceAddon = "base";
        }

        /**
         * Start empty queries
         */
        $joinQuery = "";
        $sortQuery = "";
        $configurableChildrenProductIds = array();
        $configurableChildrenProducts = array();

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $configurableChildrenVisibility = "";

        $defaultFilters = array();
        $defaultFilters["p.entity_state_id"] = "p.entity_state_id = 1";
        $defaultFilters["p.is_visible"] = "(p.is_visible = 1 ##configurable_children##)";
        $defaultFilters["p.active"] = "p.active = 1";
        $defaultFilters["p.show_on_store"] = "JSON_CONTAINS(p.show_on_store, '1', '$.\"{$storeId}\"') = '1'";
        $defaultFilters["p.url"] = "LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.url,'$.\"{$storeId}\"'))) != 'null' AND LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.url,'$.\"{$storeId}\"'))) is not null";

        $whereQuery = "WHERE ";

        /**
         * Standard queries
         */
        if (isset($data["pre_filter"]) && !empty($data["pre_filter"])) {
            if (is_string($data["pre_filter"])) {
                $data["pre_filter"] = json_decode($data["pre_filter"], true);
            }

            foreach ($data["pre_filter"] as $filters) {
                if (isset($filters["filters"])) {
                    foreach ($filters["filters"] as $d) {
                        if (array_key_exists($d["field"], $defaultFilters)) {
                            unset($defaultFilters[$d["field"]]);
                        }
                    }
                }
            }
        }

        $whereQuery .= implode(" AND ", $defaultFilters);

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->container->get("application_settings_manager");
        }

        $isIntermediary = false;
        if (isset($data["product_group"]) && !empty($data["product_group"])) {
            if (!$this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("filters_categories_show_next_children_only", $storeId)) {
                $q = "SELECT id FROM product_group_entity WHERE product_group_id = {$data["product_group"]}";
                $children = $this->databaseContext->getAll($q);
                if (!empty($children)) {
                    $isIntermediary = true;
                    $ids = [];
                    $ids[] = $data["product_group"];
                    foreach ($children as $child) {
                        $ids[] = (int)$child["id"];
                    }
                    $data["product_group"] = implode(",", $ids);
                }
            } else {
                $isIntermediary = true;
                $data["product_group"] = implode(",", [$data["product_group"]]);
            }
        }
        if (isset($data["product_group"]) && !empty($data["product_group"])) {
            $joinQuery = "JOIN product_product_group_link_entity as pg ON p.id = pg.product_id AND pg.product_group_id in ({$data["product_group"]})";
        }
        if (isset($data["ids"]) && !empty($data["ids"])) {
            $data["ids"] = array_filter($data["ids"]);

            $qSimple = "SELECT DISTINCT product_id FROM product_configuration_product_link_entity WHERE child_product_id IN (" . implode(",", $data["ids"]) . ");";
            $configurableProducts = $this->databaseContext->getAll($qSimple);

            if (!empty($configurableProducts)) {
                $configurableIds = array_column($configurableProducts, "product_id");
                $data["ids"] = array_merge($data["ids"], $configurableIds);
                $data["ids"] = array_unique($data["ids"]);
            }

            $whereQuery .= " AND p.id in (" . implode(",", $data["ids"]) . ") ";
        }

        if (empty($this->defaultScommerceManager)) {
            $this->defaultScommerceManager = $this->container->get("scommerce_manager");
        }
        $customFilter = $this->defaultScommerceManager->getFilteredProductsCustomFilter();
        $customFilterWhereQuery = "";
        $customFilterJoinQuery = "";
        if (!empty($customFilter)) {
            $joinQuery .= $customFilterJoinQuery = $customFilter["join"];
            $whereQuery .= $customFilterWhereQuery = $customFilter["where"];
        }

        /**
         * Prepare prefilter
         */
        if (isset($data["pre_filter"]) && !empty($data["pre_filter"])) {

            $preparedFilter = SearchFilterHelper::parseProductGroupFilter($data["pre_filter"], $joinQuery, $this->container);
            if (isset($preparedFilter["where_query"])) {
                $whereQuery .= " AND ({$preparedFilter["where_query"]}) ";
            }
            if (isset($preparedFilter["join_query"])) {
                $joinQuery = $preparedFilter["join_query"];
            }
        }
        if (isset($data["additional_pre_filter"]) && !empty($data["additional_pre_filter"])) {
            if (is_string($data["additional_pre_filter"])) {
                $data["additional_pre_filter"] = json_decode($data["additional_pre_filter"], true);
            }

            /**
             * Prepare additional_prefilter
             */
            $preparedFilter = SearchFilterHelper::parseProductGroupFilter($data["additional_pre_filter"], $joinQuery);
            if (isset($preparedFilter["where_query"])) {
                $whereQuery .= " AND ({$preparedFilter["where_query"]}) ";
            }
            if (isset($preparedFilter["join_query"])) {
                $joinQuery = $preparedFilter["join_query"];
            }
        }

        /**
         * Sort by
         */
        if (isset($data["sort"]) && !empty($data["sort"])) {
            $sortQuery .= " ORDER BY ";

            $data["sort"] = json_decode($data["sort"], true);
            foreach ($data["sort"] as $sort) {
                if ($sort["sort_by"] == "custom" && $sort["sort_dir"] == "ids") {

                    // Get sort ids from method
                    // Example: [{"sort_by": "custom", "sort_dir": "ids","ids": "method:timnovak_helper_manager:getPermanentLowPriceProducts" }]
                    if (isset($sort["ids"]) && stripos($sort["ids"], "method") !== false) {
                        $d = explode(":", $sort["ids"]);
                        if ($this->container->has($d[1])) {
                            $manager = $this->container->get($d[1]);
                            if (method_exists($manager, $d[2])) {
                                $data["ids"] = $manager->{$d[2]}();
                                if (!is_array($data["ids"])) {
                                    $data["ids"] = explode(",", $data["ids"]);
                                }
                            }
                        }
                    }

                    if (isset($data["ids"]) && !empty($data["ids"])) {
                        $sortQuery .= " FIELD(p.id, " . implode(",", $data["ids"]) . " ),";
                    }
                } elseif ($sort["sort_by"] != "custom") {
                    $sortQuery .= " p." . EntityHelper::makeAttributeCode($sort["sort_by"]) . " {$sort["sort_dir"]},";
                } else {
                    $sortQuery = " ORDER BY p.ord ASC ";
                }
            }
            /**
             * Fallback
             */
            if ($sortQuery == " ORDER BY ") {
                $sortQuery = " ORDER BY p.ord ASC ";
            }
            $sortQuery = substr($sortQuery, 0, -1);
        }
        if (isset($data["was_sorted"]) && $data["was_sorted"]) {
            $sortQuery = " ORDER BY p.ord ASC";
        }

        if (!empty($sortQuery)) {
            $sortQuery .= ",p.id DESC ";
        }

        $entityQuery = "SELECT DISTINCT(p.id), p.product_type_id FROM product_entity p {$joinQuery} {$whereQuery} {$sortQuery};";
        $entityQuery = str_ireplace("##configurable_children##", $configurableChildrenVisibility, $entityQuery);
        /*if(isset($data["product_group"]) && $data["product_group"] == 1){
            dump($entityQuery);
        die;
        }*/
        $unfilteredEntities = $this->databaseContext->getAll($entityQuery);

        $productIdsByProductType = array();
        if (!empty($unfilteredEntities)) {
            foreach ($unfilteredEntities as $unfilteredEntity) {
                $productIdsByProductType[$unfilteredEntity["product_type_id"]][] = $unfilteredEntity["id"];
            }
        }

        /**
         * Prepare filter data
         */
        if (isset($data["get_filter"]) && !empty($data["get_filter"])) {
            if (!empty($unfilteredEntities)) {

                $ids = $idsForFilters = array_column($unfilteredEntities, 'id');

                /**
                 * Get children from configurable products if exist
                 */
                if (isset($productIdsByProductType[2])) {
                    $childProductsFilters = $defaultFilters;
                    unset($childProductsFilters["p.is_visible"]);
                    $q = "SELECT p.id,pcpl.product_id as parent_id FROM product_configuration_product_link_entity as pcpl LEFT JOIN product_entity AS p ON pcpl.child_product_id = p.id {$customFilterJoinQuery} WHERE pcpl.entity_state_id = 1 AND pcpl.product_id IN (" . implode(",", $productIdsByProductType[2]) . ") {$customFilterWhereQuery} AND " . implode(" AND ", $childProductsFilters) . ";";
                    $configurableChildrenProducts = $this->databaseContext->getAll($q);

                    if (!empty($configurableChildrenProducts)) {
                        $configurableChildrenProductIds = array_column($configurableChildrenProducts, 'id');
                        $ids = $idsForFilters = array_merge($ids, $configurableChildrenProductIds);

                        $configurableChildrenVisibility = " OR (p.id IN (" . implode(",", $configurableChildrenProductIds) . "))";
                    }

                    $idsForFilters = array_diff($idsForFilters, $productIdsByProductType[2]);
                }

                $ids = implode(",", $ids);
                $idsForFilters = implode(",", $idsForFilters);
                if(empty($idsForFilters)){
                    $idsForFilters = $ids;
                }
                $additionalAttributeWhere = " AND sale.product_id in ({$ids}) ";

                $q = "SELECT sace.*, GROUP_CONCAT(DISTINCT(CONCAT(sale.attribute_value, '|||||', sale.configuration_option)) SEPARATOR '#####') as attribute_values FROM s_product_attribute_configuration_entity as sace
                JOIN s_product_attributes_link_entity AS sale ON sace.id = sale.s_product_attribute_configuration_id {$additionalAttributeWhere}
                WHERE sace.entity_state_id = 1 AND sace.is_active = 1 and sace.show_in_filter = 1 GROUP BY sace.id";

                $attributes = $this->databaseContext->getAll($q);

                /**
                 * Remove attribute values excluded by user
                 */
                if (empty($this->cacheManager)) {
                    $this->cacheManager = $this->container->get("cache_manager");
                }
                $exculdedOptions = $this->cacheManager->getCacheGetItem("exclude_s_product_attribute_options");
                if (empty($dashboardRoutesCacheItem)) {
                    $q = "SELECT id FROM s_product_attribute_configuration_options_entity WHERE hide_on_frontend = 1;";
                    $exculdedOptions = $this->databaseContext->getAll($q);
                    if (!empty($exculdedOptions)) {
                        $exculdedOptions = array_column($exculdedOptions, "id");
                        $exculdedOptions = array_flip($exculdedOptions);

                        $this->cacheManager->setCacheItem("exclude_s_product_attribute_options", $exculdedOptions);
                    }
                }

                if (!empty($attributes)) {
                    foreach ($attributes as $attribute) {
                        if (!isset($ret["filter_data"]["secondary"][$attribute["filter_key"]])) {
                            $options = explode("#####", $attribute["attribute_values"]);
                            if (!isset($_ENV["FILTERS_SINGLE_VALUED_FILTERS"]) || $_ENV["FILTERS_SINGLE_VALUED_FILTERS"] != 1) {
                                if (count($options) < 2) {
                                    continue;
                                }
                            }

                            $ret["filter_data"]["secondary"][$attribute["filter_key"]]["attribute_configuration"] = $attribute;
                            $ret["filter_data"]["secondary"][$attribute["filter_key"]]["attribute_configuration"]["attribute_id"] = null;
                            $ret["filter_data"]["secondary"][$attribute["filter_key"]]["ord"] = $attribute["ord"];

                            foreach ($options as $option) {
                                $optionValues = explode("|||||", $option);
                                if (isset($optionValues[1]) && !empty($optionValues[1]) && isset($exculdedOptions[$optionValues[1]])) {
                                    continue;
                                }
                                $ret["filter_data"]["secondary"][$attribute["filter_key"]]["values"][strtolower($optionValues[0])] = array(
                                    "name" => $optionValues[0],
                                    "option_id" => $optionValues[1] ?? null,
                                    "selected" => false,
                                    "disabled" => false,
                                    "prefix" => $attribute["prefix"],
                                    "sufix" => $attribute["sufix"]
                                );
                            }

                            if (empty($ret["filter_data"]["secondary"][$attribute["filter_key"]]["values"])) {
                                unset($ret["filter_data"]["secondary"][$attribute["filter_key"]]);
                            }
                        }
                    }
                }

                /**
                 * Is saleable filter
                 */
                if ($this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("filters_saleable", $storeId)) {
                    $q = "SELECT DISTINCT(is_saleable) as is_saleable FROM product_entity as p WHERE p.id IN ({$ids}); ";
                    $res = $this->databaseContext->getAll($q);

                    $opt = array_column($res, "is_saleable");
                    if (!(count($opt) < 2 || !in_array(1, $opt))) {
                        $ret["filter_data"]["additional"]["is_saleable"]["values"][1] = array("name" => "Only saleable", "selected" => false, "disabled" => false, "prefix" => null, "sufix" => null);
                    }
                }

                /**
                 * Only images filter
                 */
                $withoutImagesIds = null;
                if ($this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("filters_only_images", $storeId)) {
                    $q = "SELECT p.id FROM product_entity as p WHERE p.id in ({$ids}) AND p.id NOT IN (SELECT product_id FROM product_images_entity WHERE product_id in ({$ids})); ";
                    $res = $this->databaseContext->getAll($q);

                    if (!empty($res)) {

                        $withoutImagesIds = array_column($res, "id");
                        $ret["filter_data"]["additional"]["only_images"]["values"][1] = array("name" => "Only images", "selected" => false, "disabled" => false, "prefix" => null, "sufix" => null);
                    }
                }

                $configurableBundleIds = null;
                if ($this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("filters_configurable_bundles", $storeId)) {
                    $q = "SELECT p.id FROM product_entity as p WHERE p.id in ({$ids}) AND p.product_type_id = " . CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE . ";";
                    $res = $this->databaseContext->getAll($q);

                    if (!empty($res)) {
                        $configurableBundleIds = array_column($res, "id");
                        $ret["filter_data"]["additional"]["configurable_bundles"]["values"][1] = array("name" => "Only configurable bundles", "selected" => false, "disabled" => false, "prefix" => null, "sufix" => null);
                    }
                }

                /**
                 * Is on discount
                 */
                $discountIds = null;
                if ($this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("filters_is_on_discount", $storeId)) {
                    $discountIds = $this->getProductDiscountIds($idsForFilters);
                    if (!empty($discountIds)) {
                        $ret["filter_data"]["additional"]["is_on_discount"]["values"][1] = array("name" => "Only on discount", "selected" => false, "disabled" => false, "prefix" => null, "sufix" => null);
                    }
                }

                /**
                 * Price min max
                 */
                if ($this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("filters_price", $storeId)) {

                    $accountId = "NULL";
                    $accountGroupId = "NULL";

                    if (!empty($account)) {
                        $accountId = $account->getId();
                        if (!empty($account->getAccountGroupId())) {
                            $accountGroupId = $account->getAccountGroupId();
                        }
                    }

                    $q = "SELECT 
                            MIN(p.sort_price_{$priceAddon}) AS p_min, 
                            MAX(p.sort_price_{$priceAddon}) AS p_max,
                            MIN(pdcp.discount_price_{$priceAddon}) AS pdcp_min,
                            MAX(pdcp.discount_price_{$priceAddon}) AS pdcp_max,
                            MIN(pap.discount_price_{$priceAddon}) AS pap_min,
                            MAX(pap.discount_price_{$priceAddon}) AS pap_max,
                            MIN(pagp.discount_price_{$priceAddon}) AS pagp_min,
                            MAX(pagp.discount_price_{$priceAddon}) AS pagp_max
                        FROM product_entity AS p 
                        LEFT JOIN product_discount_catalog_price_entity AS pdcp ON p.id = pdcp.product_id
                        LEFT JOIN product_account_price_entity AS pap ON p.id = pap.product_id AND {$accountId} = pap.account_id
                        LEFT JOIN product_account_group_price_entity AS pagp ON p.id = pagp.product_id AND {$accountGroupId} = pagp.account_group_id
                        WHERE p.id IN ({$idsForFilters});";
                    $res = $this->databaseContext->getAll($q);

                    if (!empty($res)) {
                        $res = $res[0];

                        if (!isset($res["p_min"]) || empty($res["p_min"])) {
                            $res["p_min"] = 0.01;
                        }

                        $resMin = min(array_filter(array($res["p_min"], $res["pdcp_min"], $res["pap_min"], $res["pagp_min"]), function ($v) {
                            return !empty($v);
                        }));

                        if (!isset($res["p_max"]) || empty($res["p_max"])) {
                            $res["p_max"] = 0.01;
                        }

                        $resMax = max(array_filter(array($res["p_max"], $res["pdcp_max"], $res["pap_max"], $res["pagp_max"]), function ($v) {
                            return !empty($v);
                        }));

                        if (empty($this->cacheManager)) {
                            $this->cacheManager = $this->container->get("cache_manager");
                        }

                        $exchangeRates = array();

                        $cacheItem = $this->cacheManager->getCacheGetItem("exchange_rates");
                        if (!empty($cacheItem)) {
                            $exchangeRates = $cacheItem->get();
                        }
                        if (isset($exchangeRates[$session->get("current_website_id")][$session->get("current_store_id")])) {
                            $currencyCode = $exchangeRates[$session->get("current_website_id")][$session->get("current_store_id")]["currency_sign"];
                            $excahangeRate = $exchangeRates[$session->get("current_website_id")][$session->get("current_store_id")]["exchange_rate"];
                        } else {
                            $currencyCode = $exchangeRates[$_ENV["DEFAULT_WEBSITE_ID"]][$_ENV["DEFAULT_STORE_ID"]]["currency_sign"];
                            $excahangeRate = 1;
                        }

                        $minPrice = number_format(floatval($resMin) / $excahangeRate, "2", ".", "");
                        $maxPrice = number_format(floatval($resMax) / $excahangeRate, "2", ".", "");

                        $ret["filter_data"]["additional"]["price"]["values"]["price"] = array("name" => null, "selected" => false, "disabled" => false, "prefix" => null, "sufix" => $currencyCode, "min_price" => $minPrice, "max_price" => $maxPrice, "selected_min_price" => $minPrice, "selected_max_price" => $maxPrice);
                    }
                }

                /**
                 * Categories filter
                 */
                if (($this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("filters_categories", $storeId)) && (!isset($data["product_group"]) || empty($data["product_group"]) || $isIntermediary)) {
                    $filterCategoriesLevel = $this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("filters_categories_level", $storeId);
                    // Allow override category level for search only.
                    if (isset($data["s"]) && $data["s"] == 1 && !empty($this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("filters_categories_level_search", $storeId))) {
                        $filterCategoriesLevel = $this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("filters_categories_level_search", $storeId);
                    }
                    $q = "SELECT pg.id, pg.name, pg.product_group_id, pg.level, pg.ord, count(*) as product_count FROM product_product_group_link_entity AS ppgl LEFT JOIN product_group_entity AS pg ON ppgl.product_group_id = pg.id LEFT JOIN product_entity AS p ON ppgl.product_id = p.id WHERE pg.level IN ({$filterCategoriesLevel}) AND ppgl.product_id IN ({$ids}) AND pg.entity_state_id = 1 AND pg.is_active = 1 AND JSON_CONTAINS(p.show_on_store, '1', '$.\"{$storeId}\"') = '1'";
                    if ($isIntermediary) {
                        $q .= " AND pg.product_group_id IN ({$data["product_group"]})";
                    }
                    $q .= " GROUP BY pg.id ORDER BY LOWER(JSON_UNQUOTE(JSON_EXTRACT(pg.name,'$.\"{$storeId}\"'))) ASC";
                    $res = $this->databaseContext->getAll($q);
                    if (!empty($res)) {

                        if (empty($this->getPageUrlExtension)) {
                            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
                        }

                        foreach ($res as $r) {
                            $ret["filter_data"]["additional"]["categories"]["values"][$r["id"]] = array(
                                "name" => $this->getPageUrlExtension->getArrayStoreAttribute($storeId, json_decode($r["name"], true)),
                                "parent_group" => $r["product_group_id"],
                                "level" => $r["level"],
                                "ord" => $r["ord"],
                                "selected" => false,
                                "disabled" => false,
                                "prefix" => null,
                                "sufix" => null,
                                "product_count" => $r["product_count"]
                            );
                        }
                    }
                }
            }

            /**
             * Filters sort by weight
             */
            if (isset($ret["filter_data"]["secondary"]) && !empty($ret["filter_data"]["secondary"])) {
                uasort($ret["filter_data"]["secondary"], function ($a, $b) {
                    return $a['ord'] <=> $b['ord'];
                });

                if (!empty($ret["filter_data"]["secondary"])) {
                    foreach ($ret["filter_data"]["secondary"] as $filterKey => $filterData) {
                        $values = $filterData['values'];
                        uasort($values, function ($a, $b) {
                            return $a['name'] <=> $b['name'];
                        });
                        $ret["filter_data"]["secondary"][$filterKey]['values'] = $values;
                    }
                }
            }
        }

        /** Apply filter */
        if (isset($data["filter"]) && !empty($data["filter"]) && isset($ret["filter_data"]) && !empty($ret["filter_data"])) {
            $ret["show_index"] = false;
            $get_facets = false;
            $ret["index"] = 0;
            if (isset($data["product_group"]) && !empty($data["product_group"])) {
                $ret["show_index"] = true;
                $get_facets = true;
            }

            $filterJoins = "";
            $filterWheres = "";

            foreach ($data["filter"] as $filterKey => $values) {
                $filterKey = str_ireplace("-", "_", $filterKey);

                /**
                 * Skip if filter does not exist
                 */
                if (isset($ret["filter_data"]["additional"][$filterKey]) || $filterKey == "min_price") {
                    /** Is saleable */
                    if ($filterKey == "is_saleable") {
                        if (!empty($values)) {
                            $filterWheres .= " AND p.is_saleable = 1 ";

                            $additionalSaleableWhere = "";
                            if ($this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("filters_qty_positive", $storeId)) {
                                $filterWheres .= " AND p.qty > 0";
                            }

                            if (isset($data["get_filter"]) && !empty($data["get_filter"])) {
                                $ret["filter_data"]["additional"][$filterKey]["values"][1]["selected"] = true;
                            }
                        }
                    }
                    /** Only images */
                    if ($filterKey == "only_images") {
                        if (!empty($values) && isset($withoutImagesIds) && !empty($withoutImagesIds)) {
                            if (isset($data["get_filter"]) && !empty($data["get_filter"])) {
                                $filterWheres .= " AND p.id NOT IN (" . implode(",", $withoutImagesIds) . ") ";
                                if (isset($data["get_filter"]) && !empty($data["get_filter"])) {
                                    $ret["filter_data"]["additional"][$filterKey]["values"][1]["selected"] = true;
                                }
                            }
                        }
                    }
                    // Confuigurable bundles
                    elseif ($filterKey == "configurable_bundles") {
                        if (!empty($values) && isset($configurableBundleIds) && !empty($configurableBundleIds)) {
                            if (isset($data["get_filter"]) && !empty($data["get_filter"])) {
                                $filterWheres .= " AND p.id IN (" . implode(",", $configurableBundleIds) . ") ";
                                if (isset($data["get_filter"]) && !empty($data["get_filter"])) {
                                    $ret["filter_data"]["additional"][$filterKey]["values"][1]["selected"] = true;
                                }
                            }
                        }
                    } /** Is on discount */
                    elseif ($filterKey == "is_on_discount") {
                        if (!empty($values) && isset($discountIds) && !empty($discountIds)) {
                            $filterWheres .= " AND p.id IN (" . implode(",", $discountIds) . ") ";
                            if (isset($data["get_filter"]) && !empty($data["get_filter"])) {
                                $ret["filter_data"]["additional"][$filterKey]["values"][1]["selected"] = true;
                            }
                        }
                    } /** Price */
                    elseif ($filterKey == "min_price" && (!empty($data["filter"]["min_price"]) && !empty($data["filter"]["max_price"]))) {

                        $selectedMinPrice = intval($data["filter"]["min_price"][0]);
                        $selectedMaxPrice = intval($data["filter"]["max_price"][0]);

                        if(empty($selectedMinPrice)){
                            $selectedMinPrice = 0;
                        }
                        if(empty($selectedMaxPrice)){
                            $selectedMaxPrice = 99999999;
                        }

                        $ret["filter_data"]["additional"]["price"]["values"]["price"]["selected"] = true;
                        $ret["filter_data"]["additional"]["price"]["values"]["price"]["selected_min_price"] = $selectedMinPrice;
                        $ret["filter_data"]["additional"]["price"]["values"]["price"]["selected_max_price"] = $selectedMaxPrice;

                        $filterWheres .= " AND (p.sort_price_{$priceAddon} >= {$selectedMinPrice} AND p.sort_price_{$priceAddon} <= {$selectedMaxPrice}) ";
                    } /** Categories */
                    elseif ($filterKey == "categories") {

                        $categoryIds = array();

                        foreach ($values as $value) {
                            $categoryIds[] = $value;
                            if (isset($ret["filter_data"]["additional"][$filterKey]["values"][strtolower($value)])) {
                                $ret["filter_data"]["additional"][$filterKey]["values"][strtolower($value)]["selected"] = true;
                            }
                        }

                        $categoryIds = implode(",", $categoryIds);

                        $filterJoins .= " JOIN product_product_group_link_entity as pg2 ON p.id = pg2.product_id AND pg2.product_group_id IN ({$categoryIds}) ";
                    }
                } /** Secondary */
                elseif (isset($ret["filter_data"]["secondary"][$filterKey])) {
                    /** Set selected attributes */
                    if (isset($data["get_filter"]) && !empty($data["get_filter"])) {
                        foreach ($values as $value) {
                            if (count($values) > 1) {
                                $get_facets = false;
                            }
                            if (isset($ret["filter_data"]["secondary"][$filterKey]["values"][strtolower($value)])) {
                                $ret["filter_data"]["secondary"][$filterKey]["values"][strtolower($value)]["selected"] = true;
                            }
                        }
                    }

                    $attributeValues = array_unique($values);
                    foreach ($attributeValues as $key => $value) {
                        $attributeValues[$key] = addslashes($value);
                    }

                    $filterJoins .= " JOIN s_product_attributes_link_entity as spal_{$filterKey} ON p.id = spal_{$filterKey}.product_id AND spal_{$filterKey}.s_product_attribute_configuration_id = {$ret["filter_data"]["secondary"][$filterKey]["attribute_configuration"]["id"]} ";
                    $filterWheres .= " AND spal_{$filterKey}.attribute_value IN ('" . implode("','", $attributeValues) . "')";

                } else {
                    continue;
                }
            }

            /**
             * Facets
             */
            if ($get_facets) {

                if(empty($this->facetManager)){
                    $this->facetManager = $this->getContainer()->get("facet_manager");
                }

                $facetLlinks = $this->facetManager->getFacetsForProductGroupByAttributes($data["product_group"], $data["filter"]);

                if (!empty($facetLlinks)) {
                    $facetData = $this->facetManager->generateFacetData($facetLlinks, $data["filter"],$storeId);
                    $ret["facet_title"] = $facetData["facet_title"] ?? "";
                    $ret["facet_meta_title"] = $facetData["facet_meta_title"] ?? "";
                    $ret["facet_meta_description"] = $facetData["facet_meta_description"] ?? "";
                    $ret["facet_canonical"] = $facetData["facet_canonical"] ?? "";
                    $ret["index"] = 1;
                    //todo provjeriti da li ide index 1 ili ne
                }
            }

            $entityQuery = "SELECT DISTINCT(p.id) FROM product_entity p {$joinQuery} {$filterJoins} {$whereQuery} {$filterWheres} {$sortQuery};";
            $entityQuery = str_ireplace("##configurable_children##", $configurableChildrenVisibility, $entityQuery);
            $entities = $this->databaseContext->getAll($entityQuery);

            /** Set disabled on filter */
            if (isset($data["get_filter"]) && !empty($data["get_filter"]) && !empty($data["filter"])) {

                if (!empty($entities)) {
                    $idsArray = array_column($entities, 'id');
                    $ids = implode(",", $idsArray);

                    $q = "SELECT p.id,pcpl.product_id as parent_id FROM product_configuration_product_link_entity as pcpl JOIN product_entity AS p ON pcpl.child_product_id = p.id WHERE pcpl.entity_state_id = 1 AND (pcpl.product_id IN ({$ids}));";
                    $configurableChildrenProductsTmp = $this->databaseContext->getAll($q);

                    if (!empty($configurableChildrenProductsTmp)) {
                        $configurableChildrenProductIdsTmp = array_column($configurableChildrenProductsTmp, 'id');
                        $ids .= "," . implode(",", $configurableChildrenProductIdsTmp);
                    }
                }

                /**
                 * Set disabled on additional
                 */
                if (isset($ret["filter_data"]["additional"]) && !empty($ret["filter_data"]["additional"])) {
                    foreach ($ret["filter_data"]["additional"] as $filterKey => $filterData) {
                        $state = true;
                        if (isset($data["filter"][strtolower($filterKey)])) {
                            $state = false;
                        }
                        if (!empty($entities) && !isset($data["filter"][strtolower($filterKey)])) {
                            if ($filterKey == "is_saleable") {
                                //todo ovo se isto moze rijesiti bez query
                                $q = "SELECT DISTINCT(is_saleable) as is_saleable FROM product_entity as p WHERE p.id IN ({$ids}); ";
                                $res = $this->databaseContext->getAll($q);

                                $opt = array_column($res, "is_saleable");

                                if (!(count($opt) < 2 || !in_array(1, $opt))) {
                                    $state = false;
                                }
                                $ret["filter_data"]["additional"][$filterKey]["values"][1]["disabled"] = $state;
                            } elseif ($filterKey == "is_on_discount") {
                                if (!empty(array_intersect($idsArray, $discountIds))) {
                                    $state = false;
                                }
                                $ret["filter_data"]["additional"][$filterKey]["values"][1]["disabled"] = $state;
                            } elseif ($filterKey == "only_images") {
                                if (!empty(array_intersect($idsArray, $withoutImagesIds))) {
                                    $state = false;
                                }
                                $ret["filter_data"]["additional"][$filterKey]["values"][1]["disabled"] = $state;
                            } elseif ($filterKey == "configurable_bundles") {
                                if (!empty(array_intersect($idsArray, $configurableBundleIds))) {
                                    $state = false;
                                }
                                $ret["filter_data"]["additional"][$filterKey]["values"][1]["disabled"] = $state;
                            } elseif ($filterKey == "categories") {
                                $q = "SELECT ppgl.product_group_id FROM product_product_group_link_entity AS ppgl WHERE ppgl.product_id IN ({$ids});";
                                $res = $this->databaseContext->getAll($q);

                                $categoryIds = [];

                                if (!empty($res)) {
                                    $categoryIds = array_unique(array_column($res, "product_group_id"));
                                }

                                foreach ($filterData["values"] as $valueKey => $value) {
                                    $state = true;
                                    if (empty($categoryIds) || in_array($valueKey, $categoryIds)) {
                                        $state = false;
                                    }
                                    $ret["filter_data"]["additional"][$filterKey]["values"][$valueKey]["disabled"] = $state;
                                }
                            }
                        } else {
                            foreach ($filterData["values"] as $valueKey => $value) {
                                $ret["filter_data"]["additional"][$filterKey]["values"][$valueKey]["disabled"] = $state;
                            }
                        }
                    }
                }

                /**
                 * Set disabled on secondary
                 */
                if (!empty($ret["filter_data"]["secondary"])) {
                    foreach ($ret["filter_data"]["secondary"] as $filterKey => $filterData) {
                        $state = true;
                        if (isset($data["filter"][strtolower($filterKey)])) {
                            $state = false;
                        }
                        foreach ($filterData["values"] as $valueKey => $value) {
                            $ret["filter_data"]["secondary"][$filterKey]["values"][$valueKey]["disabled"] = $state;
                        }
                    }
                }

                if (!empty($entities)) {

                    $additionalAttributeWhere = " AND sale.product_id in ({$ids}) ";

                    $q = "SELECT sace.*, GROUP_CONCAT(DISTINCT(CONCAT(sale.attribute_value, '|||||', sale.configuration_option)) SEPARATOR '#####') as attribute_values FROM s_product_attribute_configuration_entity as sace
                    JOIN s_product_attributes_link_entity AS sale ON sace.id = sale.s_product_attribute_configuration_id {$additionalAttributeWhere}
                    WHERE sace.entity_state_id = 1 AND sace.is_active = 1 and sace.show_in_filter = 1 GROUP BY sace.id";
                    $attributes = $this->databaseContext->getAll($q);

                    /**
                     * Remove attribute values excluded by user
                     */
                    if (empty($this->cacheManager)) {
                        $this->cacheManager = $this->container->get("cache_manager");
                    }
                    $exculdedOptions = $this->cacheManager->getCacheGetItem("exclude_s_product_attribute_options");
                    if (empty($dashboardRoutesCacheItem)) {
                        $q = "SELECT id FROM s_product_attribute_configuration_options_entity WHERE hide_on_frontend = 1;";
                        $exculdedOptions = $this->databaseContext->getAll($q);
                        if (!empty($exculdedOptions)) {
                            $exculdedOptions = array_column($exculdedOptions, "id");
                            $exculdedOptions = array_flip($exculdedOptions);

                            $this->cacheManager->setCacheItem("exclude_s_product_attribute_options", $exculdedOptions);
                        }
                    }

                    foreach ($attributes as $attribute) {

                        /**
                         * drek
                         */
                        $options = explode("#####", $attribute["attribute_values"]);

                        foreach ($options as $option) {
                            $optionValues = explode("|||||", $option);
                            if (isset($optionValues[1]) && !empty($optionValues[1]) && isset($exculdedOptions[$optionValues[1]])) {
                                continue;
                            }

                            if (isset($ret["filter_data"]["secondary"][$attribute["filter_key"]]) && isset($ret["filter_data"]["secondary"][$attribute["filter_key"]]["values"][strtolower($optionValues[0])])) {
                                $ret["filter_data"]["secondary"][$attribute["filter_key"]]["values"][strtolower($optionValues[0])]["disabled"] = false;
                            }
                        }
                    }
                }
            }
        } else {
            $entities = $unfilteredEntities;
        }


        $finalIds = array();
        if (!empty($entities) && count($entities)) {
            $finalIds = array_unique(array_column($entities, 'id'));
        }

        /**
         * Trasformacija child configurabilnih producta u parente
         */
        if (isset($configurableChildrenProducts) && !empty($configurableChildrenProducts)) {

            foreach ($configurableChildrenProducts as $configurableChildrenProduct) {
                if (in_array($configurableChildrenProduct["id"], $finalIds)) {
                    $finalKey = array_search($configurableChildrenProduct["id"], $finalIds);
                    $finalIds[$finalKey] = $configurableChildrenProduct["parent_id"];
                }
            }
            $finalIds = array_unique($finalIds);
        }

        if (!empty($finalIds) && isset($_ENV["RETURN_PRODUCT_RESULT_CATEGORIES"]) && $_ENV["RETURN_PRODUCT_RESULT_CATEGORIES"] == 1) {
            $ret["product_groups"] = $this->getProductGroupsByProductIds($finalIds);
        }

        $ret["total"] = count($finalIds);

        if ($ret["total"] > 0) {
            if ((isset($data["get_all_products"]) && $data["get_all_products"])) {
                $from = 0;
                $limit = $data["page_size"] * $data["page_number"];
            } else {
                $from = (($data["page_number"] - 1) * $data["page_size"]);
                $limit = $data["page_size"];
            }

            $ret["entities"] = array_slice($finalIds, $from, $limit);

            if (empty($ret["entities"])) {
                $ret["error"] = true;
            } else {
                $compositeFilter = new CompositeFilter();
                $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",", $ret["entities"])));
                $compositeFilters = new CompositeFilterCollection();
                $compositeFilters->addCompositeFilter($compositeFilter);

                $sortFilters = new SortFilterCollection();
                if (isset($data["sort"]) && !empty($data["sort"])) {
                    foreach ($data["sort"] as $sort) {
                        if ($sort["sort_by"] == "custom" && $sort["sort_dir"] == "ids") {

                        } else {
                            $sortFilters->addSortFilter(new SortFilter($sort["sort_by"], $sort["sort_dir"]));
                        }
                    }
                }

                if (isset($data["was_sorted"]) && $data["was_sorted"]) {
                    $sortFilters->addSortFilter(new SortFilter("ord", "asc"));
                }

//                try {
                $ret["entities"] = $this->entityManager->getEntitiesByEntityTypeAndFilter($this->productEntityType, $compositeFilters, $sortFilters);
//                }catch(\Exception $e){
//                    dump(implode(",",$ret["entities"]));
//                    die;

//                }

                /**
                 * Custom sort by
                 */
                if (isset($data["sort"]) && !empty($data["sort"])) {
                    foreach ($data["sort"] as $sort) {
                        if ($sort["sort_by"] == "custom" && $sort["sort_dir"] == "ids" && isset($data["ids"]) && !empty($data["ids"])) {

                            $tmpProducts = $ret["entities"];
                            $orderArray = array_flip($data["ids"]);
                            $tmpEntitiesArray = array();
                            foreach ($tmpProducts as $entity) {
                                if (isset($orderArray[$entity->getId()])) {
                                    $tmpEntitiesArray[$orderArray[$entity->getId()]] = $entity;
                                }
                            }

                            ksort($tmpEntitiesArray);
                            $ret["entities"] = $tmpEntitiesArray;

                            break;
                        }
                    }

                    if (isset($sort["offset"]) && is_numeric($sort["offset"]) && $sort["offset"] > 0) {
                        for ($i = 0; $i < $sort["offset"]; $i++) {
                            unset($ret["entities"][$i]);
                        }
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * @param $params
     * @return array|false|string
     * Ovaj filter se koristi kod napredne pretrage ili da se u url ubaci ovakav string: /[kategorija|search_results]?s=1&brand=stihl;husquarna
     */
    public function prepareAdditionalPrefilter($params)
    {

        /**
         * Get store from session
         */
        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");

        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"] ?? null;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $filters = array();

        $q = "SELECT a.id, a.attribute_code, a.frontend_type, a.lookup_attribute_id FROM attribute as a WHERE backend_table = 'product_entity';";
        $attributes = $this->databaseContext->getAll($q);

        $attributesKey = array();
        foreach ($attributes as $attribute) {
            $attributesKey[$attribute["attribute_code"]] = $attribute;
        }

        foreach ($params as $attributeCode => $param) {

            foreach ($param as $k => $p) {
                $param[$k] = addslashes($p);
            }

            $selectedAttribute = null;
            $filter = array();
            if (array_key_exists($attributeCode, $attributesKey)) {
                $selectedAttribute = $attributesKey[$attributeCode];
            }
            if (array_key_exists($attributeCode . "_id", $attributesKey)) {
                $selectedAttribute = $attributesKey[$attributeCode . "_id"];
            }

            if (!empty($selectedAttribute)) {
                if ($selectedAttribute["frontend_type"] == "autocomplete") {
                    $q = "SELECT a.attribute_code, a.backend_table, a.frontend_type FROM attribute as a WHERE id = {$selectedAttribute["lookup_attribute_id"]};";
                    $lookupAttribute = $this->databaseContext->getAll($q);

                    if (!empty($lookupAttribute)) {
                        $filter["field"] = "{$selectedAttribute["attribute_code"]}_key.{$lookupAttribute[0]["attribute_code"]}";
                        $filter["join"] = "{$lookupAttribute[0]["backend_table"]} AS {$selectedAttribute["attribute_code"]}_key ON p.{$selectedAttribute["attribute_code"]} = {$selectedAttribute["attribute_code"]}_key.id";
                        $values = "'" . implode("','", $param) . "'";

                        //TODO ovo ce trebati doraditi ovisno o frontend_type
                        if ($lookupAttribute[0]["frontend_type"] == "text_store") {
                            $filter["operation"] = "json_in";
                            $values = str_ireplace("\\", "\\\\", $values);
                            $filter["value"] = "[\"{$values}\",\"$.\\\"{$storeId}\\\"\"]";
                        } else {
                            $filter["operation"] = "in";
                            $filter["value"] = $values;
                        }
                    }
                } elseif ($selectedAttribute["frontend_type"] == "multiselect") {
                    $q = "SELECT la.lookup_attribute_code, la.lookup_backend_table, la.lookup_frontend_type, a.attribute_code, a.backend_table, a.frontend_type  FROM attribute as a LEFT JOIN attribute as la ON a.lookup_attribute_id = la.id WHERE id = {$selectedAttribute["lookup_attribute_id"]};";
                    $lookupAttribute = $this->databaseContext->getAll($q);

                    if (!empty($lookupAttribute)) {
                        $filter["field"] = "{$selectedAttribute["attribute_code"]}_key.{$lookupAttribute[0]["lookup_attribute_code"]}";
                        $filter["join"] = "{$lookupAttribute[0]["backend_table"]} AS {$selectedAttribute["attribute_code"]}_key ON p.{$selectedAttribute["attribute_code"]} = {$selectedAttribute["attribute_code"]}_key.id JOIN {$lookupAttribute[0]["lookup_backend_table"]} AS {$lookupAttribute[0]["lookup_attribute_code"]}_key ON {$selectedAttribute["attribute_code"]}_key.{$lookupAttribute[0]["attribute_code"]} = {$lookupAttribute[0]["lookup_attribute_code"]}_key.id";
                        $values = "'" . implode("','", $param) . "'";


                        //TODO ovo ce trebati doraditi ovisno o frontend_type
                        if ($lookupAttribute[0]["lookup_frontend_type"] == "text_store") {
                            $filter["operation"] = "json_in";
                            $values = str_ireplace("\\", "\\\\", $values);
                            $filter["value"] = "[\"{$values}\",\"$.\\\"{$storeId}\\\"\"]";
                        } else {
                            $filter["operation"] = "in";
                            $filter["value"] = $values;
                        }
                    }
                } else {
                    $filter["field"] = "p.{$selectedAttribute["attribute_code"]}";
                    $values = "'" . implode("','", $param) . "'";

                    //TODO ovo ce trebati doraditi ovisno o frontend_type
                    if ($selectedAttribute["frontend_type"] == "text_store") {
                        $filter["operation"] = "json_in";
                        $values = str_ireplace("\\", "\\\\", $values);
                        $filter["value"] = "[\"{$values}\",\"$.\\\"{$storeId}\\\"\"]";
                    } else {
                        $filter["operation"] = "in";
                        $filter["value"] = $values;
                    }
                }

                unset($params[$attributeCode]);

                if (!empty($filter)) {
                    $filters[] = $filter;
                }
            }
        }

        if (!empty($params)) {

            if (empty($this->sProductManager)) {
                $this->sProductManager = $this->container->get("s_product_manager");
            }

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

            $configurations = $this->sProductManager->getSproductAttributeConfigurations($compositeFilter);

            if (!empty($configurations)) {

                $configurationsByKey = array();
                /** @var SProductAttributeConfigurationEntity $configuration */
                foreach ($configurations as $configuration) {
                    $configurationsByKey[$configuration->getFilterKey()] = $configuration;
                }

                foreach ($params as $attributeCode => $param) {
                    if (array_key_exists($attributeCode, $configurationsByKey)) {

                        $filter = array();

                        $filter["field"] = "{$attributeCode}_attribute_config.attribute_value";
                        $filter["join"] = "s_product_attributes_link_entity AS {$attributeCode}_attribute_config ON p.id = {$attributeCode}_attribute_config.product_id AND {$attributeCode}_attribute_config.s_product_attribute_configuration_id = {$configurationsByKey[$attributeCode]->getId()}";
                        $values = "'" . implode("','", $param) . "'";
                        $filter["operation"] = "in";
                        $filter["value"] = $values;

                        if (!empty($filter)) {
                            $filters[] = $filter;
                        }
                    }
                }
            }
        }

        if (!empty($filters)) {
            $filters = json_encode($filters);
            $filters = '{"pre_filter":[{"connector":"and","filters":' . $filters . '}]}';
        }

        return $filters;
    }

    /**
     * @param $query
     * @param $data
     * @return array
     */
    public function searchProducts($query, $data)
    {
        $ids = array();
        $entities = null;

        if (empty($this->productEntityType)) {
            $this->productEntityType = $this->entityManager->getEntityTypeByCode("product");
        }
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $query = strip_tags($query);

        /**
         * Get store from session
         */
        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");

        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"] ?? null;
        }

        $baseQuery = "SELECT DISTINCT(p.id) FROM product_entity as p WHERE p.entity_state_id = 1 AND p.active = 1 AND JSON_CONTAINS(show_on_store, '1', '$.\"{$storeId}\"')";

        if (empty($this->defaultScommerceManager)) {
            $this->defaultScommerceManager = $this->container->get("scommerce_manager");
        }

        $q = $baseQuery;

        /** Search product codes */
        $addonQuery = $this->defaultScommerceManager->getProductSearchCompositeFilterForCode($query, 1);
        if (strlen(trim($addonQuery)) > 1) {
            $q = $q . $addonQuery;
            $entities = $this->databaseContext->getAll($q);
        }

        if (!empty($entities)) {
            $ids = array_column((array)$entities, "id");
            return $ids;
        }

        if ($_ENV["USE_ELASTIC"] ?? 0) {
            $ids = $this->defaultScommerceManager->elasticSearchProducts($query, $storeId);
            if (!empty($ids)) {
                return $ids;
            }
        }

        if ($_ENV["USE_ALGOLIA"] ?? 0) {
            if (empty($this->algoliaManager)) {
                $this->algoliaManager = $this->container->get("algolia_manager");
            }
            $ids = $this->algoliaManager->searchProducts($query);
            if (!empty($ids)) {
                return $ids;
            }
        }

        /** Addon query 2 */
        $addonQuery = $this->defaultScommerceManager->getProductSearchCompositeFilterForCode($query, 2);
        if (!empty($addonQuery)) {
            $q = $baseQuery . $addonQuery;

            $entities = $this->databaseContext->getAll($q);
            if (!empty($entities)) {
                /** @var ProductEntity $entity */
                foreach ($entities as $entity) {
                    $ids[] = $entity["id"];
                }
            }
        }

        /**
         * Ako su samo 2 rijeci, potrebno ih je obrnuti i napraviti obje
         */
        $similarQueryBn = $similarQueryBw = "";
        if (substr_count($query, ' ') == 1) {
            $tmp = explode(" ", $query);
            $similar = $tmp[1] . " " . $tmp[0];
            $similarQueryBn = "OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.name,'$.\"{$storeId}\"'))) LIKE LOWER('{$similar}%') ";
            $similarQueryBw = "OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.name,'$.\"{$storeId}\"'))) LIKE LOWER('%{$similar}%') ";
            //$similarQueryBw = "OR p.name LIKE '%{$similar}%' ";


            //JSON_CONTAINS({$fieldPrefix}{$fieldName}, '{$fieldData[0]}', '{$fieldData[1]}') = '{$fieldData[0]}'
        }

        $addonQuery = "AND (LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.name,'$.\"{$storeId}\"'))) LIKE LOWER('{$query}%') {$similarQueryBn} )";
        //$addonQuery = " AND (p.name LIKE '{$query}%' {$similarQueryBn} )";
        $q = $baseQuery . $addonQuery;

        $entities = $this->databaseContext->getAll($q);
        if (!empty($entities)) {
            /** @var ProductEntity $entity */
            foreach ($entities as $entity) {
                $ids[] = $entity["id"];
            }
        }

        /** Addon query 3 */
        $addonQuery = $this->defaultScommerceManager->getProductSearchCompositeFilterForCode($query, 3);
        if (!empty($addonQuery)) {
            $q = $baseQuery . $addonQuery;

            $entities = $this->databaseContext->getAll($q);
            if (!empty($entities)) {
                /** @var ProductEntity $entity */
                foreach ($entities as $entity) {
                    $ids[] = $entity["id"];
                }
            }
        }


        $addonQuery = " AND (LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.name,'$.\"{$storeId}\"'))) LIKE LOWER('%{$query}%') {$similarQueryBw} )";
        //$addonQuery = " AND (p.name LIKE '%{$query}%' {$similarQueryBw} )";
        $q = $baseQuery . $addonQuery;

        $entities = $this->databaseContext->getAll($q);
        if (!empty($entities)) {
            /** @var ProductEntity $entity */
            foreach ($entities as $entity) {
                $ids[] = $entity["id"];
            }
        }

        /** Addon query 4 */
        $addonQuery = $this->defaultScommerceManager->getProductSearchCompositeFilterForCode($query, 4);
        if (!empty($addonQuery)) {
            $q = $baseQuery . $addonQuery;

            $entities = $this->databaseContext->getAll($q);
            if (!empty($entities)) {
                /** @var ProductEntity $entity */
                foreach ($entities as $entity) {
                    $ids[] = $entity["id"];
                }
            }
        }

        $addonQuery = " AND (LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.description,'$.\"{$storeId}\"'))) LIKE LOWER('%{$query}%') OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.meta_description,'$.\"{$storeId}\"'))) LIKE LOWER('%{$query}%')) ";
        $q = $baseQuery . $addonQuery;

        $entities = $this->databaseContext->getAll($q);
        if (!empty($entities)) {
            /** @var ProductEntity $entity */
            foreach ($entities as $entity) {
                $ids[] = $entity["id"];
            }
        }

        if (!empty($ids)) {
            $ids = array_unique($ids);

            return $ids;
        }

        $wordsToAvoid = array("za", "te", "sa", "bez", "ni", "niti", "ako", "ka", "ili", "uz", "na", "iz", "no", "nad", "iza", "tu", "kao", "ga", "li", "ću", "će", "je", "koji", "ko", "koja", "su", "se", "si", "po");

        $query = explode(" ", $query);
        foreach ($query as $key => $q) {
            if (strlen(trim($q)) < 2) {
                unset($query[$key]);
            } elseif (strlen(trim($q)) == 2 && in_array(trim($q), $wordsToAvoid)) {
                unset($query[$key]);
            }
        }

        if (empty($query)) {
            return $ids;
        }

        $query = array_map('trim', $query);

        /**
         * Find results having all words in name
         */
        $addonQuery = "";
        foreach ($query as $queryPart) {
            $addonQuery .= " AND LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.name,'$.\"{$storeId}\"'))) LIKE LOWER('%{$queryPart}%') ";

        }

        $q = $baseQuery . $addonQuery;
        $entities = $this->databaseContext->getAll($q);
        if (!empty($entities)) {
            /** @var ProductEntity $entity */
            foreach ($entities as $entity) {
                $ids[] = $entity["id"];
            }
        }

        if (!empty($ids)) {
            $ids = array_unique($ids);
            return $ids;
        }

        /**
         * Find results having all words in description
         */
        $addonQuery = "";
        foreach ($query as $queryPart) {
            $addonQuery .= " AND (LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.description,'$.\"{$storeId}\"'))) LIKE LOWER('%{$queryPart}%') OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.meta_description,'$.\"{$storeId}\"'))) LIKE LOWER('%{$queryPart}%')) ";

        }

        $q = $baseQuery . $addonQuery;
        $entities = $this->databaseContext->getAll($q);
        if (!empty($entities)) {
            /** @var ProductEntity $entity */
            foreach ($entities as $entity) {
                $ids[] = $entity["id"];
            }
        }

        if (!empty($ids)) {
            $ids = array_unique($ids);
            return $ids;
        }

        /**
         * Find results having any word in name
         */
        $addonQuery = " AND (";
        foreach ($query as $queryPart) {
            $addonQuery .= " LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.name,'$.\"{$storeId}\"'))) LIKE LOWER('%{$queryPart}%') OR";
        }
        $addonQuery = substr($addonQuery, 0, -2);
        $addonQuery .= ")";

        $q = $baseQuery . $addonQuery;
        $entities = $this->databaseContext->getAll($q);
        if (!empty($entities)) {
            /** @var ProductEntity $entity */
            foreach ($entities as $entity) {
                $ids[] = $entity["id"];
            }
        }

        if (!empty($ids)) {
            $ids = array_unique($ids);
            return $ids;
        }

        /**
         * Find results having any word in description
         */
        $addonQuery = " AND (";
        foreach ($query as $queryPart) {
            $addonQuery .= " LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.description,'$.\"{$storeId}\"'))) LIKE LOWER('%{$queryPart}%') OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.meta_description,'$.\"{$storeId}\"'))) LIKE LOWER('%{$queryPart}%') OR";
        }
        $addonQuery = substr($addonQuery, 0, -2);
        $addonQuery .= ")";

        $q = $baseQuery . $addonQuery;
        $entities = $this->databaseContext->getAll($q);
        if (!empty($entities)) {
            /** @var ProductEntity $entity */
            foreach ($entities as $entity) {
                $ids[] = $entity["id"];
            }
        }

        if (!empty($ids)) {
            $ids = array_unique($ids);
            return $ids;
        }

        /** Search attributes */
        //todo attributes

        return $ids;
    }

    /**
     * @param null $selectedSortId
     * @param ProductGroupEntity|null $productGroup
     * @param array $sortOptionIds
     * @return array
     */
    public function getSortOptions($selectedSortId = null, ProductGroupEntity $productGroup = null, $sortOptionIds = array())
    {
        $sortOptions = array();
        $additionalIds = array();

        if (!is_numeric($selectedSortId)) {
            $selectedSortId = null;
        }

        if (!empty($productGroup)) {
            if (!empty($productGroup->getDefaultSortOrder())) {
                $additionalIds[] = $productGroup->getDefaultSortOrderId();
                if (empty($selectedSortId)) {
                    $selectedSortId = $productGroup->getDefaultSortOrderId();
                }
            }
            $additionalSortOptions = $productGroup->getAdditionalSortOptions();
            if (EntityHelper::isCountable($additionalSortOptions) && count($additionalSortOptions) > 0) {
                /** @var SortOptionEntity $additionalSortOption */
                foreach ($additionalSortOptions as $additionalSortOption) {
                    $additionalIds[] = $additionalSortOption->getId();
                }
            }

            if (!empty($additionalIds)) {
                $additionalIds = array_unique($additionalIds);
            }
        } else if (!empty($sortOptionIds)) {
            $additionalIds = $sortOptionIds;
        }

        $entityType = $this->entityManager->getEntityTypeByCode("sort_option");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("or");

        $compositeFilter->addFilter(new SearchFilter("alwaysShow", "eq", 1));
        if (!empty($additionalIds)) {
            $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",", $additionalIds)));
        }

        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("ord", "asc"));

        $entities = $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);

        if (empty($entities)) {
            return $sortOptions;
        }

        if (empty($selectedSortId)) {
            /** @var SortOptionEntity $entity */
            foreach ($entities as $entity) {
                if ($entity->getIsDefault()) {
                    $selectedSortId = $entity->getId();
                    break;
                }
            }
            if (empty($selectedSortId)) {
                $selectedSortId = $entities[0]->getId();
            }
        }

        /** @var SortOptionEntity $entity */
        foreach ($entities as $entity) {
            $selected = false;
            if ($selectedSortId == $entity->getId()) {
                $selected = true;
            }
            $sortOptions[] = array("value" => $entity->getId(), "sortOption" => $entity, "selected" => $selected, "sort" => $entity->getSortByValue());
        }

        return $sortOptions;
    }

    /**
     * @param $availablePageSizes
     * @param $selectedPageSize
     * @return array
     */
    public function preparePageSizeOptions($availablePageSizes, $selectedPageSize)
    {

        $ret = $availablePageSizes;
        foreach ($availablePageSizes as $availablePageSize) {
            $selected = false;
            if ($availablePageSize == $selectedPageSize) {
                $selected = true;
            }
            $ret[] = array("value" => $availablePageSize, "selected" => $selected);
        }

        return $ret;
    }

    /**
     * @param $data
     * @param $total
     * @return bool
     */
    public function calculateIfNextPageExists($data, $total)
    {

        if ((intval($data["page_number"]) * intval($data["page_size"])) >= intval($total)) {
            return false;
        }

        return true;
    }

    /**
     * @param $query
     * @return array
     */
    public function searchProductGroups($query)
    {
        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");

        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $query = $this->cleanSearchParams($query);

        $ret = array();
        $ret["brands"] = null;
        $ret["product_groups"] = null;
        $ret["suggestions"] = null;

        $terms = explode(" ", $query);

        $pagingFilter = new PagingFilter();
        $pagingFilter->setPageNumber(0);
        $pagingFilter->setPageSize(5);

        /**
         * Search brands
         */
        $entityType = $this->entityManager->getEntityTypeByCode("brand");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter(json_encode(array("name", '$."' . $storeId . '"')), "asc"));

        /**
         * If use elastic search elastic
         */
        if ($_ENV["USE_ELASTIC"] ?? 0) {

            if (empty($this->defaultScommerceManager)) {
                $this->defaultScommerceManager = $this->container->get("scommerce_manager");
            }

            $ids = $this->defaultScommerceManager->elasticSearchBrands($query, $storeId);

            if (!empty($ids)) {
                $compositeFilter = new CompositeFilter();
                $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",", $ids)));
                $compositeFilters->addCompositeFilter($compositeFilter);

                $ret["brands"] = $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters, $pagingFilter);
            }
        } else {
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("or");

            foreach ($terms as $term) {
                $compositeFilter->addFilter(new SearchFilter("name", "json_bw", json_encode(array($term, '$."' . $storeId . '"'))));
            }

            $compositeFilters->addCompositeFilter($compositeFilter);

            $ret["brands"] = $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters, $pagingFilter);
        }

        /**
         * Product Groups
         */
        if (empty($this->productGroupEntityType)) {
            $this->productGroupEntityType = $this->entityManager->getEntityTypeByCode("product_group");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter(json_encode(array("name", '$."' . $storeId . '"')), "asc"));

        /**
         * If use elastic search elastic
         */
        if ($_ENV["USE_ELASTIC"] ?? 0) {

            if (empty($this->defaultScommerceManager)) {
                $this->defaultScommerceManager = $this->container->get("scommerce_manager");
            }

            $ids = $this->defaultScommerceManager->elasticSearchProductGroups($query, $storeId);

            if (!empty($ids)) {
                $compositeFilter = new CompositeFilter();
                $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",", $ids)));
                $compositeFilters->addCompositeFilter($compositeFilter);

                $ret["product_groups"] = $this->entityManager->getEntitiesByEntityTypeAndFilter($this->productGroupEntityType, $compositeFilters, $sortFilters, $pagingFilter);
            }
        }
        /*else{
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("or");

            foreach ($terms as $term) {
                $compositeFilter->addFilter(new SearchFilter("name", "json_bw", json_encode(array($term, '$."' . $storeId . '"'))));
                $compositeFilter->addFilter(new SearchFilter("description", "json_bw", json_encode(array($term, '$."' . $storeId . '"'))));
                $compositeFilter->addFilter(new SearchFilter("metaTitle", "json_bw", json_encode(array($term, '$."' . $storeId . '"'))));
                $compositeFilter->addFilter(new SearchFilter("metaDescription", "json_bw", json_encode(array($term, '$."' . $storeId . '"'))));
            }

            $compositeFilters->addCompositeFilter($compositeFilter);


            $ret["product_groups"] = $this->entityManager->getEntitiesByEntityTypeAndFilter($this->productGroupEntityType, $compositeFilters, $sortFilters, $pagingFilter);
        }*/

        /**
         * Suggestions
         */
        $entityType = $this->entityManager->getEntityTypeByCode("s_product_search_results");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isSuggestion", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("or");

        foreach ($terms as $term) {
            $compositeFilter->addFilter(new SearchFilter("usedQuery", "bw", $term));
        }

        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("numberOfResults", "desc"));
        $sortFilters->addSortFilter(new SortFilter("timesUsed", "desc"));

        $ret["suggestions"] = $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters, $pagingFilter);

        return $ret;
    }

    /**
     * @param $data
     * @param $startKey
     * @param null $endKey
     * @return array
     */
    public function prepareFilterParams($data, $startKey, $endKey = null)
    {

        $avoidArray = array(
            "get_filter",
            "page_size",
            "sort_dir",
            "sort",
            "get_all_products",
            "product_group",
            "ids",
            "store",
            "show_on_homepage",
            "show_on_category",
            "page_number",
            "filter",
            "coupon",
            "index",
            "fbclid",
            "gclid",
            "utm_campaign",
            "utm_content",
            "utm_medium",
            "utm_source",
            "utm_term",
        );

        $params = array();

        $skip = true;
        foreach ($data as $key => $value) {

            if (is_array($value)) {
                continue;
            }

            if (isset($endKey) && $key == $endKey) {
                break;
            }
            if (in_array($key, $avoidArray)) {
                continue;
            }
            if ($key == $startKey) {
                $skip = false;
                continue;
            }
            if ($skip) {
                continue;
            }

            $values = urldecode(trim($value));
            $values = explode(";", $values);
            foreach ($values as $val) {
                $params[$key][] = $val;
            }
        }

        return $params;
    }

    /**
     * @param ProductEntity $product
     * @param $productGroupId
     * @param int $limit
     * @return mixed
     */
    public function getRandomProductsIdsInCategory(ProductEntity $product = null, $productGroupId, $limit = 10)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $additionalWhere = "";
        if (!empty($product)) {
            $additionalWhere = " and p.id != {$product->getId()}";
        }

        $q = "SELECT p.id as product_id FROM product_entity as p
        LEFT JOIN product_product_group_link_entity as pg on p.id = pg.product_id
        WHERE p.entity_state_id = 1 and p.active = 1 and p.qty > 0 and pg.product_group_id = {$productGroupId} {$additionalWhere}
        ORDER BY RAND()
        LIMIT {$limit};";

        return $this->databaseContext->getAll($q);
    }

    /**
     * @param $query
     * @param null $addonPattern
     * @return string|string[]|null
     */
    public function cleanSearchParams($query, $addonPattern = null)
    {

        $query = trim($query);
        $query = preg_replace('/\s+/', ' ', $query);
        $query = preg_replace('/\'|\%/', '', $query);
        $query = StringHelper::removeNonAsciiCharacters($query);

        if (!empty($addonPattern)) {
            $query = preg_replace('/' . $addonPattern . '/i', '', $query);
        }

        return $query;
    }

    /**
     * @param ProductGroupEntity $productGroup
     * @param $storeId
     * @param string $delimiter
     * @return mixed|null
     * @throws \Exception
     */
    public function getProductGroupNameList(ProductGroupEntity $productGroup, $storeId, $delimiter = " > ")
    {
        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }

        $name = $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $productGroup, "name");

        if (!empty($productGroup->getProductGroup())) {
            $name = $this->getProductGroupNameList($productGroup->getProductGroup(), $storeId) . $delimiter . $name;
        }

        return $name;
    }

    public function getBestsellerProductIds($storeId = null, $productGroupId = null, $limit = 30)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $ret = array();

        $filterByProductGroup = "";
        $limitQuery = "";
        $filterWhere = "";

        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");

        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"] ?? null;
        }

        $q = "SELECT oi.product_id, SUM(oi.qty) as total_qty FROM order_item_entity as oi LEFT JOIN product_entity as p ON oi.product_id = p.id %s WHERE p.is_saleable = 1 %s AND JSON_CONTAINS(show_on_store, '1', '$.\"{$storeId}\"') GROUP BY oi.product_id ORDER BY total_qty DESC %s";
        if (!empty($productGroupId)) {
            $filterByProductGroup = " LEFT JOIN product_product_group_link_entity as ppg on oi.product_id = ppg.product_id ";
            $filterWhere = " AND product_group_id = {$productGroupId} ";
        }
        if (!empty($limit)) {
            $limitQuery = " LIMIT {$limit} ";
        }

        $q = sprintf($q, $filterByProductGroup, $filterWhere, $limitQuery);
        $ret = $this->databaseContext->getAll($q);

        return $ret;
    }

    /**
     * @param ProductGroupEntity $productGroup
     * @param int $level
     * @return int
     */
    public function getProductGroupLevel(ProductGroupEntity $productGroup, $level = 1)
    {

        if (!empty($productGroup->getProductGroup())) {
            $level++;
            return $this->getProductGroupLevel($productGroup->getProductGroup(), $level);
        }

        return $level;
    }

    /**
     * @return bool
     * This method is used to set initial product group levels or if product groups are imported, it should be run afterwords
     */
    public function setProductGroupLevels()
    {

        $entityType = $this->entityManager->getEntityTypeByCode("product_group");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("or");

        $compositeFilter->addFilter(new SearchFilter("level", "eq", 0));
        $compositeFilter->addFilter(new SearchFilter("level", "nu", null));

        $compositeFilters->addCompositeFilter($compositeFilter);

        $productGroups = $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);

        $saveArray = array();

        if (!empty($productGroups)) {
            /** @var ProductGroupEntity $productGroup */
            foreach ($productGroups as $productGroup) {
                $level = $this->getProductGroupLevel($productGroup);
                $productGroup->setLevel($level);

                $saveArray[] = $productGroup;
            }

            if (!empty($saveArray)) {
                $this->entityManager->saveArrayEntities($saveArray, $entityType);
            }
        }

        return true;
    }

    /**
     * @param bool $clearCache
     * @param string $additionalFilter
     * @return bool
     */
    public function setNumberOfProductsInProductGroups($clearCache = true, $additionalFilter = "", $additionalFilterOuter = "")
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }
        if (empty($this->defaultScommerceManager)) {
            $this->defaultScommerceManager = $this->container->get("scommerce_manager");
        }
        $customFilter = $this->defaultScommerceManager->getFilteredProductsCustomFilter();
        $whereQuery = "";
        if (!empty($customFilter)) {
            $whereQuery = $customFilter["where"];
        }

        /**
         * Update products
         */
        $q = "UPDATE product_group_entity as pg SET products_in_group = (SELECT count(p.id) as total FROM product_product_group_link_entity as ppg LEFT JOIN product_entity as p ON ppg.product_id = p.id WHERE is_visible = 1 and p.active = 1 and ppg.product_group_id = pg.id {$whereQuery} {$additionalFilter}) {$additionalFilterOuter}";
        $this->databaseContext->executeNonQuery($q);

        if ($clearCache) {
            if (empty($this->cacheManager)) {
                $this->cacheManager = $this->container->get("cache_manager");
            }

            $this->cacheManager->invalidateCacheByTag("s_menu");
        }

        return true;
    }

    /**
     * @param $productGroupId
     * @param $queryParameters
     * @param $storeId
     * @return array
     */
    public function getProductGroupFacetOverrides($productGroupId, $queryParameters, $storeId = null)
    {
        if(empty($storeId)){
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $data = $this->prepareFilterParams($queryParameters, "f");
        $ret = [];
        if (!empty($data)) {
            $get_facets = true;
            $productEntityAttributes = $this->entityManager->getAttributesOfEntityTypeByKey($this->entityManager->getEntityTypeByCode("product_group"));
            foreach ($data as $filterKey => $values) {
                if (array_key_exists(EntityHelper::makeAttributeName($filterKey), $productEntityAttributes)) {
                    /** Primary */
                    if (count($values) > 1) {
                        $get_facets = false;
                    }
                } else {
                    /** Secondary */
                    if (count($values) > 1) {
                        $get_facets = false;
                    }
                }
            }
            if ($get_facets) {

                if(empty($this->facetManager)){
                    $this->facetManager = $this->getContainer()->get("facet_manager");
                }

                $facetLlink = $this->facetManager->getFacetsForProductGroupByAttributes($productGroupId, $data);
                if (!empty($facetLlink)) {
                    $facetData = $this->facetManager->generateFacetData($facetLlink, $data, $storeId);
                    $ret = [
                        "facet_title" => $facetData["facet_title"] ?? "",
                        "facet_meta_title" => $facetData["facet_meta_title"] ?? "",
                        "facet_meta_description" => $facetData["facet_meta_description"] ?? "",
                        "facet_canonical" => $facetData["facet_canonical"] ?? "",
                    ];
                }
            }
        }
        return $ret;
    }

    /**
     * @param array $ids
     * @return array
     */
    public function getProductDiscountIds($ids = [])
    {
        $productIds = [];

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $additionaJoin = "";
        $additionalWhere = "";

        /**
         * If user is logged in, check for account discounts and account_group_discounts
         */


        /**
         * ISLJKUCENO JER SMO ZAKLJUCILI DA SAMO SMETA A NE POMAZE
         */
        /** @var CoreUserEntity $coreUser */
        /*$coreUser = $this->helperManager->getCurrentCoreUser();
        */

        //TODO sredit kada ce izlaziti discounti na product_account_price_entity i product_account_group_price_entity. Ovo ce biti najbolje rijesiti na MM
        /*if (!empty($coreUser) && ) {
            $account = $coreUser->getDefaultAccount();
            if (!empty($account)) {
                $additionaJoin .= " LEFT JOIN product_account_price_entity AS pape ON p.id = pape.product_id AND pape.account_id = {$account->getId()} ";
                $additionalWhere .= " OR (pape.product_id IS NOT NULL) ";

                if (!empty($account->getAccountGroup())) {
                    $additionaJoin .= " LEFT JOIN product_account_group_price_entity AS pagpe ON p.id = pagpe.product_id AND pagpe.account_group_id = {$account->getAccountGroupId()} ";
                    $additionalWhere .= " OR (pagpe.product_id IS NOT NULL) ";
                }
            }
        }*/

        $pids = "";
        if (!empty($ids)) {
            $pids = "p.id IN ({$ids}) AND";
        }
        $q = "SELECT p.id FROM product_entity as p LEFT JOIN product_discount_catalog_price_entity as pdc ON p.id = pdc.product_id {$additionaJoin} WHERE {$pids} ((p.discount_price_retail > 0 AND (p.date_discount_from IS NULL OR p.date_discount_from < NOW()) AND (p.date_discount_to IS NULL OR p.date_discount_to > NOW()) AND p.discount_percentage > 0) OR (pdc.rebate is not null) {$additionalWhere} );";
        $res = $this->databaseContext->getAll($q);

        if (!empty($res)) {
            $productIds = array_column($res, "id");

            $q = "SELECT product_id FROM product_configuration_product_link_entity WHERE configurable_product_attributes is not null AND child_product_id IN (" . implode(",", $productIds) . ");";
            $res2 = $this->databaseContext->getAll($q);

            if (!empty($res2)) {
                $productIds = array_merge($productIds, array_column($res2, "product_id"));
            }

            $productIds = array_unique($productIds);
        }

        return $productIds;
    }

    /**
     * @return array
     */
    public function getProductIsNewIds($ids = [])
    {

        $productIds = [];

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $pids = "";
        if (!empty($ids)) {
            $pids = "p.id IN ({$ids}) AND";
        }

        $q = "SELECT p.id FROM product_entity as p WHERE {$pids} ((p.date_new_from is null OR p.date_new_from < NOW()) AND (p.date_new_to IS NULL OR p.date_new_to > NOW())) AND (p.date_new_from is not null OR p.date_new_to IS not NULL);";
        $res = $this->databaseContext->getAll($q);

        if (!empty($res)) {
            $productIds = array_column($res, "id");

            $q = "SELECT product_id FROM product_configuration_product_link_entity WHERE configurable_product_attributes is not null AND child_product_id IN (" . implode(",", $productIds) . ");";
            $res2 = $this->databaseContext->getAll($q);

            if (!empty($res2)) {
                $productIds = array_merge($productIds, array_column($res2, "product_id"));
            }

            $productIds = array_unique($productIds);
        }

        return $productIds;
    }

    /**
     * @param ProductGroupEntity $productGroup
     * @param $productIds
     * @return bool
     */
    public function createProductProductGroups(ProductGroupEntity $productGroup, $productIds)
    {

        if (empty($productGroup) || empty($productIds)) {
            return false;
        }

        $insertProductProductGroupLinkQuery = "INSERT IGNORE INTO product_product_group_link_entity (entity_type_id, attribute_set_id, created, modified,created_by, modified_by, entity_state_id, product_id, product_group_id) VALUES ";
        $productProductGroupLinkInsertQueryValues = "";

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        /** @var AttributeSet $productProductGroupLinkAttributeSet */
        $productProductGroupLinkAttributeSet = $this->entityManager->getAttributeSetByCode("product_product_group_link");

        foreach ($productIds as $productId) {
            $productProductGroupLinkInsertQueryValues .= "('{$productProductGroupLinkAttributeSet->getEntityTypeId()}','{$productProductGroupLinkAttributeSet->getId()}',NOW(),NOW(),'system','system','1','{$productId}','{$productGroup->getId()}'),";
        }

        if (!empty($productProductGroupLinkInsertQueryValues)) {
            $q = ($insertProductProductGroupLinkQuery . substr($productProductGroupLinkInsertQueryValues, 0, -1) . ";");
            $this->databaseContext->executeNonQuery($q);
            unset($productProductGroupLinkInsertQueryValues);
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        /** Recalculate products */
        $this->crmProcessManager->recalculateProductsPrices(array("product_ids" => $productIds));

        /** @var ScommerceHelperManager $sCommerceHelperManager */
        $sCommerceHelperManager = $this->getContainer()->get("scommerce_helper_manager");
        $sCommerceHelperManager->assignParentProductGroups($productIds);

        $this->setNumberOfProductsInProductGroups();

        return true;
    }

    /**
     * @param $ids
     * @return bool
     */
    public function deleteProductProductGroupByIds($ids)
    {

        if (empty($ids)) {
            return false;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT product_id FROM product_product_group_link_entity WHERE id IN (" . implode(",", $ids) . ");";
        $productIds = $this->databaseContext->getAll($q);

        if (empty($productIds)) {
            return true;
        }

        $productIds = array_column($productIds, "product_id");

        $q = "DELETE FROM product_product_group_link_entity WHERE id IN (" . implode(",", $ids) . ");";
        $this->databaseContext->executeNonQuery($q);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        /** Recalculate products */
        $this->crmProcessManager->recalculateProductsPrices(array("product_ids" => $productIds));

        $this->setNumberOfProductsInProductGroups();

        return true;
    }

    public function getProductIdsFromProductsProductGroup(ProductEntity $productEntity)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (isset($_ENV["RELATED_PRODUCTS_AUTOMATICLY_FROM_LOWEST_PRODUCT_GROUP_ONLY"]) && $_ENV["RELATED_PRODUCTS_AUTOMATICLY_FROM_LOWEST_PRODUCT_GROUP_ONLY"] == 1) {
            $productGroupIds = [];
            /** @var ProductGroupEntity $productGroup */
            foreach ($productEntity->getProductGroups() as $productGroup) {
                /** @var ProductGroupEntity $parentGroup */
                $parentGroup = $productGroup->getProductGroup();
                if (!empty($parentGroup)) {
                    $productGroupIds[$parentGroup->getId()] = $productGroup->getId();
                }
            }

            $lowestGroupId = null;
            if (!empty($productGroupIds)) {
                foreach ($productGroupIds as $parentId => $groupId) {
                    if (!isset($productGroupIds[$groupId])) {
                        $lowestGroupId = $groupId;
                    }
                }
            }

            if (!empty($lowestGroupId)) {
                $q = "SELECT product_id FROM product_product_group_link_entity AS ppgl
                JOIN product_entity as p ON ppgl.product_id = p.id
                WHERE product_group_id = {$lowestGroupId} AND product_id != {$productEntity->getId()} ORDER BY p.is_saleable DESC LIMIT 50";
                return $this->databaseContext->getAll($q);
            }
        }

        $q = "SELECT product_id FROM product_product_group_link_entity AS ppgl
                JOIN product_entity as p ON ppgl.product_id = p.id
                WHERE product_group_id = (SELECT product_group_id FROM product_product_group_link_entity AS ppgl2 WHERE product_id = {$productEntity->getId()} ORDER BY ord DESC LIMIT 1) AND product_id != {$productEntity->getId()} ORDER BY p.is_saleable DESC LIMIT 50";
        return $this->databaseContext->getAll($q);
    }

    /**
     * Adds promotion product group to products on discount
     */
    public function assignProductPromotionGroupToProductsOnDiscount()
    {
        if (isset($_ENV["PROMOTIONS_PRODUCT_GROUP_ID"]) && !empty($_ENV["PROMOTIONS_PRODUCT_GROUP_ID"])) {
            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->container->get("database_context");
            }
            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $productsOnDiscount = $this->getProductDiscountIds();
            $productsOnDiscount = array_merge($productsOnDiscount, $this->crmProcessManager->getAdditionalProductDiscountIds());

            // Get products in promotion group no longer on discount
            $q = "SELECT id FROM product_product_group_link_entity WHERE product_group_id IN (" . $_ENV["PROMOTIONS_PRODUCT_GROUP_ID"] . ")";
            if (!empty($productsOnDiscount)) {
                $q .= " AND product_id NOT IN (" . implode(",", $productsOnDiscount) . ")";
            }
            $result = $this->databaseContext->getAll($q);

            if (!empty($result)) {
                $this->deleteProductProductGroupByIds(array_column($result, "id"));
            }

            // Assing group to products
            $groupIds = explode(",", $_ENV["PROMOTIONS_PRODUCT_GROUP_ID"]);
            foreach ($groupIds as $groupId) {
                $productGroup = $this->getProductGroupById($groupId);
                $this->createProductProductGroups($productGroup, $productsOnDiscount);
            }
        }
    }

    /**
     * @param ProductGroupEntity $productGroup
     * @return bool
     */
    public function insertAllProductsInProductGroup(ProductGroupEntity $productGroup)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $showOnStore = "";
        $tmp = array();
        foreach ($productGroup->getShowOnStore() as $storeId => $show) {
            if ($show) {
                $tmp[] = " JSON_CONTAINS(show_on_store, '1', '$.\"{$storeId}\"') ";
            }
        }
        if (!empty($tmp)) {
            $showOnStore = " AND (" . implode(" OR ", $tmp) . ")";
        }

        $q = "INSERT IGNORE INTO product_product_group_link_entity (entity_type_id, attribute_set_id, created, modified, created_by, modified_by, entity_state_id, product_id, product_group_id) SELECT '{$productGroup->getEntityType()->getId()}','{$productGroup->getAttributeSet()->getId()}',NOW(),NOW(),'system','system','1',id,{$productGroup->getId()} FROM product_entity WHERE entity_state_id = 1 and active = 1 {$showOnStore};";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param $productIds
     * @return void|null
     */
    public function getProductGroupsByProductIds($productIds)
    {

        if (empty($productIds)) {
            return null;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $additionalWhere = "";
        if (isset($_ENV["RETURN_PRODUCT_RESULT_CATEGORIES_LEVEL"]) && !empty($_ENV["RETURN_PRODUCT_RESULT_CATEGORIES_LEVEL"])) {
            $additionalWhere = " AND pg.level IN ({$_ENV["RETURN_PRODUCT_RESULT_CATEGORIES_LEVEL"]}) ";
        }

        $q = "SELECT DISTINCT(pg.id) FROM product_product_group_link_entity AS ppg LEFT JOIN product_group_entity AS pg ON ppg.product_group_id = pg.id WHERE pg.entity_state_id = 1 {$additionalWhere} and pg.is_active = 1 and ppg.product_id IN (" . implode(",", $productIds) . ");";
        $data = $this->databaseContext->getAll($q);

        if (empty($data)) {
            return null;
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",", array_column($data, "id"))));

        return $this->getProductGroupsByFilter($compositeFilter);
    }
}
