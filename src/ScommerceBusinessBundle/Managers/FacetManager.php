<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\Entity\ProductEntity;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\FacetAttributeConfigurationLinkEntity;
use ScommerceBusinessBundle\Entity\FacetsEntity;
use ScommerceBusinessBundle\Entity\SProductAttributeConfigurationEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;

class FacetManager extends AbstractScommerceManager
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;

    protected $session;

    /** @var string[] $excludeFilterKeysFromFacets */
    protected $excludeFilterKeysFromFacets = [
        "min_price",
        "max_price",
        "categories",
    ];


    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getFacetById($id){
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(FacetsEntity::class);
        return $repository->find($id);
    }

    /**
     * @param FacetAttributeConfigurationLinkEntity $facetLlink
     * @param $filters
     * @param $storeId
     * @return void
     */
    public function generateFacetData(FacetAttributeConfigurationLinkEntity $facetLlink, $filters, $storeId = null)
    {
        if(empty($storeId)){
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        /** @var FacetsEntity $facet */
        $facet = $facetLlink->getFacets();
        $facetData = [
            "facet_title" => "",
            "facet_meta_title" => "",
            "facet_meta_description" => "",
            "facet_canonical" => ""
        ];
        $facetTitle = $facet->getFacetTitle();
        $facetMetaTitle = $facet->getFacetMetaTitle();
        $facetMetaDescription = $facet->getFacetMetaDescription();

        if(empty($this->routeManager)){
            $this->routeManager = $this->getContainer()->get("route_manager");
        }

        /** @var SStoreEntity $store */
        $store = $this->routeManager->getStoreById($storeId);

        $facetCanonical = $_ENV["SSL"] . "://" . $store->getWebsite()->getBaseUrl() . $this->routeManager->getLanguageUrl($store) . $_ENV["FRONTEND_URL_PORT"]."/".$this->getFacetBaseUrl($facet,$store);

        if (!empty($facetTitle) || !empty($facetMetaTitle) || !empty($facetMetaDescription)) {
            $alternateAttributeNames = [];
            $alternateAttributeValues = [];

            if (empty($this->applicationSettingsManager)) {
                $this->applicationSettingsManager = $this->container->get("application_settings_manager");
            }
            $facetAttributeConfiguration = $this->applicationSettingsManager->getApplicationSettingByCode("facet_attribute_configuration");

            if (empty($facetAttributeConfiguration)) {
                return $facetData;
            }
            if (!empty($facetAttributeConfiguration)) {
                if(is_array($facetAttributeConfiguration)){
                    $facetAttributeConfiguration = $facetAttributeConfiguration[$storeId] ?? "";
                }
                $facetAttributeConfiguration = json_decode($facetAttributeConfiguration, true);
            }

            $attrConfigs = $facet->getAttributeConfig();
            $attrConfigsIdPerKey = [];
            if (EntityHelper::isCountable($attrConfigs) && count($attrConfigs) > 0) {
                /** @var SProductAttributeConfigurationEntity $attrConfig */
                foreach ($attrConfigs as $attrConfig) {
                    $attrConfigId = $attrConfig->getId();
                    $filterKey = str_ireplace("-", "_", $attrConfig->getFilterKey());

                    $attrConfigsIdPerKey[$filterKey] = $attrConfigId;
                    /**
                     * sascid = s attribut configuration id
                     */
                    if (isset($facetAttributeConfiguration["sacid-" . $attrConfigId]) && !empty($facetAttributeConfiguration["sacid-" . $attrConfigId])) {
                        $alternateAttributeNames[$filterKey] = $facetAttributeConfiguration["sacid-" . $attrConfigId];
                    }
                }
            }

            if (!empty($filters)) {

                foreach ($filters as $filterKey => $value) {
                    if (!empty($this->excludeFilterKeysFromFacets) && in_array($filterKey, $this->excludeFilterKeysFromFacets)) {
                        continue;
                    }
                    $filterKey = str_ireplace("-", "_", $filterKey);
                    if (isset($attrConfigsIdPerKey[$filterKey])) {
                        $attrValueKey = md5($attrConfigsIdPerKey[$filterKey] . $value[0]);
                    } else {
                        continue;
                    }
                    /**
                     * savk = s attribut value key
                     */
                    if (isset($facetAttributeConfiguration["savk-" . $filterKey . "-" . $attrValueKey]) && !empty($facetAttributeConfiguration["savk-" . $filterKey . "-" . $attrValueKey])) {
                        $alternateAttributeValues[$filterKey] = $facetAttributeConfiguration["savk-" . $filterKey . "-" . $attrValueKey];
                    }
                    $facetUrlAttributes[] = $filterKey."=".$filters[$filterKey][0];
                }
            }
            if (empty($alternateAttributeNames) || empty($alternateAttributeValues)) {
                return $facetData;
            }

            $facetCanonical.=$this->prepareFacetAttributesUrl(implode("&",$facetUrlAttributes));

            $isValid = true;
            if (!empty($facetTitle)) {
                foreach ($alternateAttributeNames as $key => $value) {
                    $key = str_replace("-", "_", $key);
                    $facetTitle = str_replace("%{$key}_name%", $value, $facetTitle);
                }
                foreach ($alternateAttributeValues as $key => $value) {
                    $key = str_replace("-", "_", $key);
                    $facetTitle = str_replace("%{$key}_value%", $value, $facetTitle);
                }
                if (stripos($facetTitle, "_name%") !== false || stripos($facetTitle, "_value%") !== false) {
                    $isValid = false;
                }
            }
            if (!empty($facetMetaTitle)) {
                foreach ($alternateAttributeNames as $key => $value) {
                    $key = str_replace("-", "_", $key);
                    $facetMetaTitle = str_replace("%{$key}_name%", $value, $facetMetaTitle);
                }
                foreach ($alternateAttributeValues as $key => $value) {
                    $key = str_replace("-", "_", $key);
                    $facetMetaTitle = str_replace("%{$key}_value%", $value, $facetMetaTitle);
                }
                if (stripos($facetMetaTitle, "_name%") !== false || stripos($facetMetaTitle, "_value%") !== false) {
                    $isValid = false;
                }
            }
            if (!empty($facetMetaDescription)) {
                foreach ($alternateAttributeNames as $key => $value) {
                    $key = str_replace("-", "_", $key);
                    $facetMetaDescription = str_replace("%{$key}_name%", $value, $facetMetaDescription);
                }
                foreach ($alternateAttributeValues as $key => $value) {
                    $key = str_replace("-", "_", $key);
                    $facetMetaDescription = str_replace("%{$key}_value%", $value, $facetMetaDescription);
                }
                if (stripos($facetMetaDescription, "_name%") !== false || stripos($facetMetaDescription, "_value%") !== false) {
                    $isValid = false;
                }
            }
            if ($isValid) {
                $facetData["facet_title"] = $facetTitle;
                $facetData["facet_meta_title"] = $facetMetaTitle;
                $facetData["facet_meta_description"] = $facetMetaDescription;
                $facetData["facet_canonical"] = $facetCanonical;
            }
        }
        return $facetData;
    }

    /**
     * @param $productGroupId
     * @param $filter
     * @return FacetAttributeConfigurationLinkEntity|null
     */
    public function getFacetsForProductGroupByAttributes($productGroupId, $filter)
    {
        if (empty($this->facetsEntityType)) {
//            $this->facetsEntityType = $this->entityManager->getEntityTypeByCode("facets");
            $this->facetsEntityType = $this->entityManager->getEntityTypeByCode("facet_attribute_configuration_link");
        }

        $filterKeys = array_keys($filter);

        if (!empty($this->excludeFilterKeysFromFacets)) {
            foreach ($this->excludeFilterKeysFromFacets as $unsetFilterKey) {
                if (($key = array_search($unsetFilterKey, $filterKeys)) !== false) {
                    unset($filterKeys[$key]);
                }
            }
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");

//        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
//        $compositeFilter->addFilter(new SearchFilter("attributeConfig.filterKey", "in", "'" . implode("','", $filterKeys) . "'"));
//        $compositeFilter->addFilter(new SearchFilter("productGroup", "eq", $productGroupId));

        $compositeFilter->addFilter(new SearchFilter("facets.entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("attributeConfiguration.filterKey", "in", "'" . implode("','", $filterKeys) . "'"));
        $compositeFilter->addFilter(new SearchFilter("facets.productGroup", "eq", $productGroupId));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("facets.priority", "desc"));

        $facetLlinks = $this->entityManager->getEntitiesByEntityTypeAndFilter($this->facetsEntityType, $compositeFilters, $sortFilters);

        if (!empty($facetLlinks)) {
            /** @var FacetAttributeConfigurationLinkEntity $facetLlink */
            foreach ($facetLlinks as $facetLlink) {
                /** @var FacetsEntity $facet */
                $facet = $facetLlink->getFacets();

                if (EntityHelper::isCountable($facet->getAttributeConfig()) && count($facet->getAttributeConfig())) {
                    $found = true;
                    /** @var SProductAttributeConfigurationEntity $attribute */
                    foreach ($facet->getAttributeConfig() as $attribute) {
                        if (!in_array($attribute->getFilterKey(), $filterKeys)) {
                            $found = false;
                            break;
                        }
                    }
                    if ($found) {
                        return $facetLlink;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param $productGroupId
     * @return mixed
     */
    public function getFacetsForProductGroup($productGroupId)
    {

        if (empty($this->facetsEntityType)) {
            $this->facetsEntityType = $this->entityManager->getEntityTypeByCode("facets");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("productGroup", "eq", $productGroupId));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($this->facetsEntityType, $compositeFilters);
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getAllFacets($storeId = null)
    {

        if (empty($this->facetsEntityType)) {
            $this->facetsEntityType = $this->entityManager->getEntityTypeByCode("facets");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("productGroup.entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("productGroup.isActive", "eq", 1));
        //$compositeFilter->addFilter(new SearchFilter("productGroup.active", "eq", 1)); todo store id

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($this->facetsEntityType, $compositeFilters);
    }

    /**
     * @param FacetsEntity $facet
     * @param $store
     * @return array|false
     * @throws \Exception
     */
    public function generateFacetUrls(FacetsEntity $facet, $store)
    {

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }
        if (empty($store)) {
            $store = $this->routeManager->getStoreById($_ENV["DEFAULT_STORE_ID"]);
        }

        $facetAttributes = $facet->getAttributeConfig();

        $attributeIds = array();

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT p.id FROM product_product_group_link_entity as ppgl LEFT JOIN product_entity as p ON ppgl.product_id = p.id WHERE p.active = 1 and p.is_visible = 1 and p.entity_state_id = 1 and ppgl.product_group_id = {$facet->getProductGroupId()}";
        $tmp = $this->databaseContext->getAll($q);

        if (empty($tmp)) {
            return false;
        }

        $productIds = array_column($tmp, "id");

        if(empty($this->productGroupManager)){
            $this->productGroupManager = $this->getContainer()->get("product_group_manager");
        }
        if(empty($this->crmProcessManager)){
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $data = array();
        $data["page_number"] = 1;
        $data["page_size"] = 5000;
        $data["ids"] = $productIds;

        $products = $this->productGroupManager->getFilteredProducts($data);

        if(!EntityHelper::isCountable($products["entities"]) || !count($products["entities"])){
            return false;
        }

        $productIds = Array();
        /** @var ProductEntity $product */
        foreach ($products["entities"] as $product){
            $productIds[] = $product->getId();
        }

        $productIds = implode(",",$productIds);

        if (EntityHelper::isCountable($facetAttributes) && count($facetAttributes) > 0) {
            /** @var SProductAttributeConfigurationEntity $facetAttribute */
            foreach ($facetAttributes as $key => $facetAttribute) {
                $attributeIds[] = $facetAttribute->getId();
            }
        }

        if (empty($attributeIds)) {
            return false;
        }

        $q = "SELECT s.url, GROUP_CONCAT(s.product_id) as product_ids FROM 
                     (SELECT DISTINCT(GROUP_CONCAT(CONCAT(spac.filter_key,'=',spal.attribute_value) SEPARATOR '&')) as url, GROUP_CONCAT(spal.product_id) as product_id FROM s_product_attributes_link_entity as spal
                    LEFT JOIN s_product_attribute_configuration_entity AS spac ON spal.s_product_attribute_configuration_id = spac.id
                    WHERE spal.s_product_attribute_configuration_id in (" . implode(",", $attributeIds) . ") AND spal.product_id IN ({$productIds})
                    GROUP BY spal.product_id HAVING COUNT(*) = " . count($attributeIds) . ") as s GROUP BY s.url;";
        $configurations = $this->databaseContext->getAll($q);

        if (empty($configurations)) {
            return false;
        }

        $baseUrl = $this->getFacetBaseUrl($facet,$store);

        foreach ($configurations as $key => $configuration){

            $tmpBaseUrl = $baseUrl;

            $configurations[$key]["min_product_count"] = intval($facet->getMinProductCount());
            if(empty($configurations[$key]["min_product_count"])){
                $configurations[$key]["min_product_count"] = 10;
            }

            $configurations[$key]["product_count"] = count(explode(",",$configuration["product_ids"]));
            $configurations[$key]["is_valid"] = 1;
            if($configurations[$key]["product_count"] < $configurations[$key]["min_product_count"]){
                $configurations[$key]["is_valid"] = 0;
                $tmpBaseUrl = str_ireplace("&index=1", "&index=0", $tmpBaseUrl);
            }
            $configurations[$key]["url"] = $tmpBaseUrl.$this->prepareFacetAttributesUrl($configuration["url"]);
        }

        return $configurations;
    }

    /**
     * @param $url
     * @return array|string|string[]
     */
    public function prepareFacetAttributesUrl($url){

        $url = str_ireplace("%3D", "=", urlencode($url));
        $url = str_ireplace("+", "%2520", $url);
        $url = str_ireplace("%26", "&", $url);

        return $url;
    }

    /**
     * @param FacetsEntity $facet
     * @param SStoreEntity $store
     * @return string
     */
    public function getFacetBaseUrl(FacetsEntity $facet, SStoreEntity $store){

        $baseUrl = $facet->getProductGroup()->getUrlPath($store->getId());
        $baseUrl .= "?f=1&index=1&";

        return $baseUrl;
    }
}
