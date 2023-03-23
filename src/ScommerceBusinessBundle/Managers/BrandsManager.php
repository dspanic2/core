<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SearchFilterHelper;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\BrandEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;

class BrandsManager extends AbstractScommerceManager
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var EntityType $brandEntityType */
    protected $brandEntityType;
    /** @var ScommerceHelperManager $sCommerceHelperManager */
    protected $sCommerceHelperManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $id
     * @return |null
     */
    public function getBrandById($id)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("brand");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @return mixed
     */
    public function getBrandByName($name)
    {
        $name = str_replace("'", "''", $name);

        $entityType = $this->entityManager->getEntityTypeByCode("brand");

        $session = $this->getContainer()->get("session");
        $storeId = $session->get("current_store_id");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("name", "json_bw", json_encode(array($name, '$."' . $storeId . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @return mixed
     */
    public function getAllBrands()
    {
        $entityType = $this->entityManager->getEntityTypeByCode("brand");

        $session = $this->getContainer()->get("session");
        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter(json_encode(array("name", '$."' . $session->get("current_store_id") . '"')), "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @return mixed
     */
    public function getHomepageBrands()
    {
        $entityType = $this->entityManager->getEntityTypeByCode("brand");

        $session = $this->getContainer()->get("session");
        $storeId = $session->get("current_store_id");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnHomepage", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("ord", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @return bool
     */
    public function syncBrandsWithSProductAttributeConfigurationOptions()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $storeId = $_ENV["DEFAULT_STORE_ID"];

        $q = "SELECT spaco.* FROM s_product_attribute_configuration_options_entity as spaco LEFT JOIN s_product_attribute_configuration_entity AS spac ON spaco.configuration_attribute_id = spac.id WHERE spaco.entity_state_id = 1 and spac.filter_key = 'brand' AND LOWER(spaco.configuration_value) NOT IN (SELECT LOWER(JSON_UNQUOTE(JSON_EXTRACT(name,'$.\"{$storeId}\"'))) FROM brand_entity WHERE entity_state_id=1);";
        $brandOptions = $this->databaseContext->getAll($q);

        $saveArray = array();

        if (!empty($brandOptions)) {
            if (empty($this->sCommerceHelperManager)) {
                $this->sCommerceHelperManager = $this->container->get("scommerce_helper_manager");
            }
            $brandTemplate = $this->sCommerceHelperManager->getStemplateByCode("brand_page");

            foreach ($brandOptions as $brandOption) {

                $name[$storeId] = $brandOption["configuration_value"];
                $showOnStore[$storeId] = 1;

                /** @var BrandEntity $brandEntity */
                $brandEntity = $this->entityManager->getNewEntityByAttributSetName("brand");
                $brandEntity->setName($name);
                $brandEntity->setShowOnStore($showOnStore);
                $brandEntity->setOrd(100);
                $brandEntity->setIsActive(1);
                $brandEntity->setShowOnHomepage(0);
                if (!EntityHelper::checkIfMethodExists($brandEntity, "setTemplateType")) {
                    $brandEntity->setTemplateType($brandTemplate);
                }

                $saveArray[] = $brandEntity;
            }

            $this->entityManager->saveArrayEntities($saveArray, $this->entityManager->getEntityTypeByCode("brand"));
        }

        $q = "SELECT id FROM brand_entity WHERE entity_state_id=1 AND BINARY JSON_UNQUOTE( JSON_EXTRACT( NAME, '$.\"{$storeId}\"' )) NOT IN (SELECT configuration_value FROM s_product_attribute_configuration_options_entity o INNER JOIN s_product_attribute_configuration_entity a ON o.configuration_attribute_id = a.id WHERE a.filter_key = 'brand' AND o.entity_state_id = 1);";
        $deleteBrands = $this->databaseContext->getAll($q);

        if (!empty($deleteBrands)) {
            $deleteBrandIds = array_column($deleteBrands, "id");
            $q = "UPDATE brand_entity set entity_state_id = 2 WHERE id IN (" . implode(",", $deleteBrandIds) . ");";
            $this->databaseContext->executeNonQuery($q);
        }

        if ($_ENV["USE_ELASTIC"] ?? 0) {

            $q = "SELECT id FROM brand_entity WHERE created >= DATE_SUB(NOW(), INTERVAL 1 HOUR);";
            $data = $this->databaseContext->getAll($q);

            $brandIds = array();

            if (!empty($data)) {
                $brandIds = array_column($data, "id");
            }
            if (!empty($deleteBrandIds)) {
                $brandIds = array_merge($brandIds, $deleteBrandIds);
            }

            if (!empty($brandIds)) {
                if (empty($this->elasticSearchManager)) {
                    $this->elasticSearchManager = $this->container->get("elastic_search_manager");
                }

                if (empty($this->routeManager)) {
                    $this->routeManager = $this->container->get("route_manager");
                }

                $stores = $this->routeManager->getStores();
                $additionalFilter = " id IN (" . implode(",", $brandIds) . ") ";

                /** @var SStoreEntity $store */
                foreach ($stores as $store) {
                    $this->elasticSearchManager->reindex("brand", $store->getId(), $additionalFilter);
                }
            }
        }

        return $this->syncBrandsOnProducts();
    }

    /**
     * @return bool
     */
    public function syncBrandsOnProducts()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $storeId = $_ENV["DEFAULT_STORE_ID"];

        $q = "UPDATE product_entity AS p SET brand_id = null WHERE brand_id IN (SELECT id FROM brand_entity WHERE entity_state_id = 2);";
        $this->databaseContext->executeNonQuery($q);

        $q = "SELECT p.id FROM product_entity as p
            LEFT JOIN s_product_attributes_link_entity AS spale ON p.id = spale.product_id AND spale.s_product_attribute_configuration_id = (SELECT id FROM s_product_attribute_configuration_entity WHERE filter_key = 'brand')
            LEFT JOIN brand_entity as b ON p.brand_id = b.id AND b.entity_state_id = 1
                    WHERE LOWER(spale.attribute_value) != LOWER(JSON_UNQUOTE(JSON_EXTRACT(b.name,'$.\"{$storeId}\"'))) OR (b.id is null AND spale.attribute_value is not null);";
        $productIds = $this->databaseContext->getAll($q);

        if (empty($productIds)) {
            return false;
        }

        $productIds = array_column($productIds, "id");

        $q = "UPDATE product_entity as p
        LEFT JOIN s_product_attributes_link_entity AS spale ON p.id = spale.product_id AND spale.s_product_attribute_configuration_id = (SELECT id FROM s_product_attribute_configuration_entity WHERE filter_key = 'brand')
        LEFT JOIN brand_entity as b ON LOWER(spale.attribute_value) = LOWER(JSON_UNQUOTE(JSON_EXTRACT(b.name,'$.\"{$storeId}\"'))) AND b.entity_state_id = 1 SET p.brand_id = b.id WHERE p.id IN (" . implode(",", $productIds) . ");";
        $this->databaseContext->executeNonQuery($q);

        return $productIds;
    }

    /**
     * @param $data
     * @return array
     */
    public function getFilteredBrands($data)
    {
        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");

        $ret = array();

        $ret["total"] = 0;
        $ret["entities"] = array();
        $ret["filter_data"] = array();
        $ret["error"] = false;

        if (empty($this->brandEntityType)) {
            $this->brandEntityType = $this->entityManager->getEntityTypeByCode("brand");
        }

        /** Defaults */
        if (!isset($data["page_number"]) || empty($data["page_number"])) {
            $data["page_number"] = 1;
        }

        /**
         * Start empty queries
         */
        $joinQuery = "";
        $sortQuery = "";

        /**
         * Standard queries
         */
        $whereQuery = "WHERE b.entity_state_id = 1 and JSON_CONTAINS(b.show_on_store, '1', '$.\"{$storeId}\"') = '1'";
        if (isset($data["ids"]) && !empty($data["ids"])) {
            $whereQuery .= " AND b.id in (" . implode(",", $data["ids"]) . ") ";
        }

        if (isset($data["pre_filter"]) && !empty($data["pre_filter"])) {
            if (is_string($data["pre_filter"])) {
                $data["pre_filter"] = json_decode($data["pre_filter"], true);
            }

            /**
             * Prepare prefilter
             */
            $preparedFilter = SearchFilterHelper::parseProductGroupFilter($data["pre_filter"], $joinQuery);
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
                if ($sort["sort_by"] == "custom" && $sort["sort_dir"] == "ids" && isset($data["ids"]) && !empty($data["ids"])) {
                    $sortQuery .= " FIELD(b.id, " . implode(",", $data["ids"]) . " ),";
                } else {
                    $sortQuery .= " b." . EntityHelper::makeAttributeCode($sort["sort_by"]) . " {$sort["sort_dir"]},";
                }

            }
            $sortQuery = substr($sortQuery, 0, -1);
        } else {
            $data["was_sorted"] = 1;
        }

        if (isset($data["was_sorted"]) && $data["was_sorted"]) {
            $sortQuery = " ORDER BY b.ord ASC";
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $entityQuery = "SELECT DISTINCT(b.id) FROM brand_entity b {$joinQuery} {$whereQuery} {$sortQuery};";
        $entities = $this->databaseContext->getAll($entityQuery);

        $ret["total"] = count($entities);

        if ($ret["total"] > 0) {
            if (isset($data["get_all"]) && $data["get_all"]) {
                $from = 0;
                $limit = $data["page_size"] * $data["page_number"];
            } else {
                $from = (($data["page_number"] - 1) * $data["page_size"]);
                $limit = $data["page_size"];
            }

            $ids = array_unique(array_column($entities, 'id'));
            $ret["entities"] = array_slice($ids, $from, $limit);

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

                $ret["entities"] = $this->entityManager->getEntitiesByEntityTypeAndFilter($this->brandEntityType, $compositeFilters, $sortFilters);

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
                        if (isset($sort["offset"]) && is_numeric($sort["offset"]) && $sort["offset"] > 0) {
                            for ($i = 0; $i < $sort["offset"]; $i++) {
                                unset($ret["entities"][$i]);
                            }
                        }
                    }
                }
            }
        }

        return $ret;
    }

    public function getCurrentBrandProductIds()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }
        if (empty($this->twigBase)) {
            $this->twigBase = $this->getContainer()->get('twig');
        }
        $globals = $this->twigBase->getGlobals();
        if (!empty($globals["current_entity"])) {
            /** @var BrandEntity $brand */
            $brand = $globals["current_entity"];
            if ($brand->getEntityType()->getEntityTypeCode() != "brand") {
                return "0";
            }

            $q = "SELECT id FROM product_entity WHERE entity_state_id=1 AND brand_id={$brand->getId()};";
            $products = $this->databaseContext->getAll($q);

            if (!empty($products)) {
                return implode(",", array_column($products, "id"));
            }
        }

        return "0";
    }

    public function getCurrentBrandProductIdsBySattribute()
    {
        if (empty($this->twigBase)) {
            $this->twigBase = $this->getContainer()->get('twig');
        }
        $globals = $this->twigBase->getGlobals();
        if (!empty($globals["current_entity"])) {
            /** @var BrandEntity $brand */
            $brand = $globals["current_entity"];
            if ($brand->getEntityType()->getEntityTypeCode() != "brand") {
                return "0";
            }
            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->container->get("database_context");
            }
            if (empty($this->getPageUrlExtension)) {
                $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
            }

            $session = $this->container->get("session");

            $brandName = str_ireplace("'", "''", $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $brand, "name"));
            $q = "SELECT DISTINCT product_id FROM s_product_attributes_link_entity WHERE s_product_attribute_configuration_id = (SELECT id FROM s_product_attribute_configuration_entity WHERE filter_key = 'brand') AND attribute_value = '{$brandName}';";
            $products = $this->databaseContext->getAll($q);

            if (!empty($products)) {
                return implode(",", array_column($products, "product_id"));
            }
        }

        return "0";
    }
}
