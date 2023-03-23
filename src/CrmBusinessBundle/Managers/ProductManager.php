<?php

namespace CrmBusinessBundle\Managers;

use Algolia\AlgoliaSearch\Tests\API\PublicApiChecker;
use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\Entity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\ArrayHelper;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ProductConfigurableAttributeEntity;
use CrmBusinessBundle\Entity\ProductConfigurationBundleOptionEntity;
use CrmBusinessBundle\Entity\ProductConfigurationBundleOptionProductLinkEntity;
use CrmBusinessBundle\Entity\ProductConfigurationProductLinkEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use CrmBusinessBundle\Entity\ProductLinkTypeEntity;
use CrmBusinessBundle\Entity\ProductProductLinkEntity;
use CrmBusinessBundle\Entity\WarehouseEntity;
use DoctrineExtensions\Tests\Entities\Product;
use ScommerceBusinessBundle\Entity\SProductAttributeConfigurationEntity;
use ScommerceBusinessBundle\Entity\SProductAttributeConfigurationOptionsEntity;
use ScommerceBusinessBundle\Entity\SProductAttributesLinkEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ScommerceBusinessBundle\Managers\DefaultScommerceManager;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Managers\SproductManager;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Session\Session;

class ProductManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var DefaultScommerceManager $sCommerceManager */
    protected $sCommerceManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var SproductManager $sProductManager */
    protected $sProductManager;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
        $this->databaseContext = $this->container->get('database_context');
    }

    /**
     * @param $id
     * @return |null
     */
    public function getProductLinkTypeById($id)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("product_link_type");
        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param $parentProduct
     * @param $childProduct
     * @param ProductLinkTypeEntity $relationType
     * @return ProductProductLinkEntity
     */
    public function getProductRelation($parentProduct, $childProduct, ProductLinkTypeEntity $relationType)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("product_product_link");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("parentProduct", "eq", $parentProduct->getId()));
        $compositeFilter->addFilter(new SearchFilter("childProduct", "eq", $childProduct->getId()));
        $compositeFilter->addFilter(new SearchFilter("relationType", "eq", $relationType->getId()));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);
        /** @var ProductProductLinkEntity $productLink */
        $productLink = $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);

        return $productLink;
    }

    public function getProductRelationById($relationId)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("product_product_link");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $relationId);
    }

    /**
     * @param $product
     * @param array $productIdsArray
     * @param $relationTypeId
     * @return void
     */
    public function addProductRelations($product, array $productIdsArray, $relationTypeId)
    {
        /** @var ProductLinkTypeEntity $relationType */
        $relationType = $this->getProductLinkTypeById($relationTypeId);

        foreach ($productIdsArray as $product_id) {

            /** @var ProductEntity $childProductEntity */
            $childProductEntity = $this->getProductById($product_id);

            if (empty($childProductEntity)) {
                continue;
            }

            $relation = $this->getProductRelation($product, $childProductEntity, $relationType);
            if (empty($relation)) {
                $this->createProductRelation($product, $childProductEntity, $relationType);

                /**
                 * Update because of sync
                 */
                $this->entityManager->saveEntity($childProductEntity);
            }

            /**
             * Check bidirectional relation
             */
            $relation = $this->getProductRelation($childProductEntity, $product, $relationType);
            if (empty($relation)) {
                $this->createProductRelation($childProductEntity, $product, $relationType);
            }
        }
    }

    /**
     * @param $parentProduct
     * @param $childProduct
     * @param ProductLinkTypeEntity $relationType
     * @return \AppBundle\Interfaces\Entity\IFormEntityInterface|null
     */
    public function createProductRelation($parentProduct, $childProduct, ProductLinkTypeEntity $relationType)
    {
        /** @var ProductProductLinkEntity $relation */
        $relation = $this->entityManager->getNewEntityByAttributSetName("product_product_link");
        $relation->setParentProduct($parentProduct);
        $relation->setChildProduct($childProduct);
        $relation->setRelationType($relationType);

        return $this->entityManager->saveEntity($relation);
    }

    public function deleteProductRelation($relation)
    {
        $this->entityManager->deleteEntityFromDatabase($relation);

        return true;
    }

    /**
     * @param $id
     * @return |null
     */
    public function getProductById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(ProductEntity::class);
        return $repository->find($id);
    }

    /**
     * @param null $additionalCompositeFilter
     * @return mixed
     */
    public function getProductsByFilter($additionalCompositeFilter = null)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("product");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalCompositeFilter)) {
            $compositeFilters->addCompositeFilter($additionalCompositeFilter);
        }

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $productCode
     * @return ProductEntity
     */
    public function getProductByCode($productCode)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("product");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $productCode));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);
        /** @var ProductEntity $product */
        $product = $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);

        return $product;
    }

    /**
     * @param $productEan
     * @return ProductEntity
     */
    public function getProductByEan($productEan)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("product");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("ean", "eq", $productEan));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var ProductEntity $product */
        $product = $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);

        return $product;
    }

    /**
     * @param $productIds
     * @return mixed
     */
    public function getProductByIds($productIds)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("product");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",", $productIds)));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    public function getCurrencyByCode($currencyCode)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("currency");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $currencyCode));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    public function getTaxTypeByName($taxTypeCode)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("tax_type");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("name", "eq", $taxTypeCode));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param CompositeFilter|null $additionalCompositeFilter
     * @return mixed
     */
    public function getAllWarehouses(CompositeFilter $additionalCompositeFilter = null)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("warehouse");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalCompositeFilter)) {
            $compositeFilters->addCompositeFilter($additionalCompositeFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("name", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getWarehouseById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(WarehouseEntity::class);
        return $repository->find($id);
    }

    /**
     * @param $code
     * @return null
     */
    public function getWarehouseByCode($code)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("warehouse");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $code));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param ProductEntity $product
     * @param $storeId
     * @return ProductGroupEntity|null
     */
    public function getLowestProductGroupOnProduct(ProductEntity $product, $storeId = null)
    {
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $productGroups = $product->getProductGroups();

        $length = 0;
        $selectedProductGroup = null;

        if (EntityHelper::isCountable($productGroups) && count($productGroups) > 0) {

            /** @var ProductGroupEntity $productGroup */
            foreach ($productGroups as $productGroup) {

                $urlPath = $productGroup->getUrlPath($storeId);

                if (strlen($urlPath) > $length) {
                    $length = strlen($urlPath);
                    $selectedProductGroup = $productGroup;
                }
            }
        }

        return $selectedProductGroup;
    }

    /**
     * @param $entityTypeCode
     * @param $fromAttributeCode
     * @param $toAttributeCode
     * @param array $storeIds
     * @param bool $override
     * @return bool
     */
    public function setDefaultSeoData($entityTypeCode, $fromAttributeCode, $toAttributeCode, $storeIds = array(), $override = false)
    {

        if (empty($storeIds)) {
            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get("route_manager");
            }

            $stores = $this->routeManager->getStores();

            /** @var SStoreEntity $store */
            foreach ($stores as $store) {
                $storeIds[] = $store->getId();
            }
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        foreach ($storeIds as $storeId) {

            $whereQuery = "";
            if (!$override) {
                $whereQuery = " AND LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.{$toAttributeCode},'$.\"{$storeId}\"'))) is NULL or TRIM(JSON_UNQUOTE(JSON_EXTRACT(p.{$toAttributeCode},'$.\"{$storeId}\"'))) = '' ";
            }

            $q = "SELECT * FROM {$entityTypeCode}_entity as p WHERE JSON_CONTAINS(p.show_on_store, '1', '$.\"{$storeId}\"')  {$whereQuery} AND LOWER(JSON_EXTRACT(p.{$fromAttributeCode},'$.\"{$storeId}\"')) is not null AND JSON_UNQUOTE(JSON_EXTRACT(p.{$fromAttributeCode},'$.\"{$storeId}\"')) != '';";
            $products = $this->databaseContext->getAll($q);

            echo "total products: " . count($products) . "\r\n";

            if (empty($products)) {
                continue;
            }

            if (empty($this->sCommerceManager)) {
                $this->sCommerceManager = $this->container->get("scommerce_manager");
            }
            if (empty($this->cacheManager)) {
                $this->cacheManager = $this->container->get("cache_manager");
            }

            $updateQuery = $this->sCommerceManager->setDefaultSeoData($entityTypeCode, $fromAttributeCode, $toAttributeCode, $storeId, $products);

            if (empty($updateQuery)) {

                foreach ($products as $product) {

                    $toAttributeValues = json_decode($product["{$toAttributeCode}"], true);
                    $fromAttributeValues = json_decode($product["{$fromAttributeCode}"], true);

                    if (!isset($fromAttributeValues[$storeId]) || empty(trim($fromAttributeValues[$storeId]))) {
                        echo "empty {$fromAttributeCode} for store {$storeId} and product: {$product["id"]}\r\n";
                        continue;
                    }

                    $toAttributeValues[$storeId] = substr(strip_tags($fromAttributeValues[$storeId]), 0, 500);
                    if (strlen($fromAttributeValues[$storeId]) > 500) {
                        $toAttributeValues[$storeId] .= "...";
                    }

                    $toAttributeValues = addslashes(json_encode($toAttributeValues, JSON_UNESCAPED_UNICODE));

                    if (empty($toAttributeValues)) {
                        continue;
                    }

                    $updateQuery[] = "UPDATE {$entityTypeCode}_entity SET {$toAttributeCode} = '{$toAttributeValues}' WHERE id = {$product["id"]};";

                    $this->cacheManager->invalidateCacheByTag("{$entityTypeCode}_{$product["id"]}");
                }
            }

            if (!empty($updateQuery)) {
                foreach ($updateQuery as $q) {
                    try {
                        $this->databaseContext->executeNonQuery($q);
                    } catch (\Exception $e) {
                        $this->errorLogManager->logErrorEvent("setDefaultSeoData error", "Entity type code: {$entityTypeCode}, From attribute code: {$fromAttributeCode}, To attribute code: {$toAttributeCode}, Error: " . $e->getMessage() . ", Query: {$q}", true);
                    }
                }
            }
        }
        return true;
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getProductDetails(ProductEntity $product)
    {

        $ret = array();

        /**
         * PRODUCT_TYPE_CONFIGURABLE_BUNDLE
         */
        if ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE) {
            return $this->getConfigurableBundleProductDetails($product);
            //TODO set defaults
        }
        /**
         * PRODUCT_TYPE_CONFIGURABLE
         */
        elseif ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
            $ret = $this->getConfigurableProductDetails($product);
        }
        /**
         * PRODUCT_TYPE_BUNDLE
         */
        elseif ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_BUNDLE) {
            return $this->getBundleProductDetails($product);
        }
        else {
            $parentProducts= $this->getParentBundleProducts($product);

            if(EntityHelper::isCountable($parentProducts) && count($parentProducts)){
                /** @var ProductEntity $parentProduct */
                foreach ($parentProducts as $parentProduct){
                    $ret["child_bundles"][] = $this->getBundleProductDetails($parentProduct);
                }
            }
        }

        return $ret;
    }

    /*
     ******************************************************************
     ****************************************************************** CONFIGURABLE BUNDLE
     ******************************************************************
     */

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getConfigurableBundleProductDetails(ProductEntity $product)
    {

        $ret = array();

        if ($product->getProductTypeId() != CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE) {
            return $ret;
        }

        $productConfigurations = $product->getProductConfigurations();
        if (!EntityHelper::isCountable($productConfigurations) || count($productConfigurations) == 0) {
            return $ret;
        }

        /**
         * Get parent product attributes for defautl
         */
        $parentProductAttributes = array();
        if (EntityHelper::isCountable($product->getProductAttributes()) && count($product->getProductAttributes()) > 0) {
            /** @var SProductAttributesLinkEntity $parentProductAttribute */
            foreach ($product->getProductAttributes() as $parentProductAttribute) {
                $parentProductAttributes[$parentProductAttribute->getSProductAttributeConfigurationId()] = $parentProductAttribute->getConfigurationOption();
            }
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        /** @var ProductConfigurationProductLinkEntity $productConfiguration */
        foreach ($productConfigurations as $productConfiguration) {
            $tmp = array();
            $tmp["default"] = null;
            $tmpDefault = null;

            $tmp["configurable_bundle_option"] = $productConfiguration->getConfigurableBundleOption();

            $childProductLinks = $productConfiguration->getConfigurableBundleOption()->getConfigurationBundleProductLinks();
            $productConfigurationAttributeId = $productConfiguration->getConfigurableBundleOption()->getSProductAttributeConfigurationId();

            /** @var ProductConfigurationBundleOptionProductLinkEntity $childProductLink */
            foreach ($childProductLinks as $childProductLink) {

                /**
                 * Set default from config
                 */
                if ($childProductLink->getIsDefault()) {
                    $tmpDefault = $childProductLink->getProduct();
                }

                /**
                 * Check if product valid for show
                 */
                if (!$this->crmProcessManager->isProductValid($childProductLink->getProduct())) {
                    continue;
                }

                $tmp["products"][] = $childProductLink->getProduct();

                /**
                 * Check if child product has same attribute value as parent product
                 */
                if (!empty($productConfigurationAttributeId) && isset($parentProductAttributes[$productConfigurationAttributeId])) {
                    if (EntityHelper::isCountable($childProductLink->getProduct()->getProductAttributes()) && count($childProductLink->getProduct()->getProductAttributes()) > 0) {
                        /** @var SProductAttributesLinkEntity $childProductAttribute */
                        foreach ($childProductLink->getProduct()->getProductAttributes() as $childProductAttribute) {
                            if ($childProductAttribute->getSProductAttributeConfigurationId() == $productConfigurationAttributeId) {
                                if ($childProductAttribute->getConfigurationOption() == $parentProductAttributes[$productConfigurationAttributeId]) {
                                    $tmp["default"] = $childProductLink->getProduct();
                                }

                                break;
                            }
                        }
                    }
                }
            }

            /**
             * Skip option if it has no products
             */
            if (empty($tmp["products"])) {
                continue;
            }

            if (empty($tmp["default"])) {
                $tmp["default"] = $tmpDefault;
            }

            $ret[] = $tmp;
        }

        return $ret;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getProductConfigurationBundleOptionById($id)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("product_configuration_bundle_option");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param ProductConfigurationBundleOptionEntity $productConfigurationBundleOption
     * @param ProductEntity $product
     * @return |null
     */
    public function getProductConfigurableBundleOptionLink(ProductConfigurationBundleOptionEntity $productConfigurationBundleOption, ProductEntity $product)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("product_configuration_bundle_option_product_link");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("configurableBundleOption", "eq", $productConfigurationBundleOption->getId()));
        $compositeFilter->addFilter(new SearchFilter("product", "eq", $product->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param ProductConfigurationBundleOptionEntity $productConfigurationBundleOption
     * @param ProductEntity $product
     * @return ProductConfigurationBundleOptionProductLinkEntity
     */
    public function createProductConfigurableBundleOptionLink(ProductConfigurationBundleOptionEntity $productConfigurationBundleOption, ProductEntity $product)
    {

        /** @var ProductConfigurationBundleOptionProductLinkEntity $productConfigurationBundleOptionLink */
        $productConfigurationBundleOptionLink = $this->entityManager->getNewEntityByAttributSetName("product_configuration_bundle_option_product_link");

        $productConfigurationBundleOptionLink->setProduct($product);
        $productConfigurationBundleOptionLink->setConfigurableBundleOption($productConfigurationBundleOption);

        $this->entityManager->saveEntity($productConfigurationBundleOptionLink);

        return $productConfigurationBundleOptionLink;
    }

    /**
     * @param $id
     * @return |null
     */
    public function getProductConfigurableBundleOptionLinkById($id)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("product_configuration_bundle_option_product_link");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param ProductConfigurationBundleOptionProductLinkEntity $productConfigurationBundleOptionProductLink
     */
    public function deleteProductConfigurableBundleOptionLink(ProductConfigurationBundleOptionProductLinkEntity $productConfigurationBundleOptionProductLink)
    {
        $this->entityManager->deleteEntityFromDatabase($productConfigurationBundleOptionProductLink);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getProductConfigurationProductLinkById($id)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("product_configuration_product_link");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param ProductEntity $product
     * @param null $additionalCompositeFilter
     * @return |null
     */
    public function getProductConfigurationProductLink($additionalCompositeFilter = null)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("product_configuration_product_link");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalCompositeFilter)) {
            $compositeFilters->addCompositeFilter($additionalCompositeFilter);
        }

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param null $additionalCompositeFilter
     * @return |null
     */
    public function getProductConfigurationProductLinks($additionalCompositeFilter = null)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("product_configuration_product_link");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalCompositeFilter)) {
            $compositeFilters->addCompositeFilter($additionalCompositeFilter);
        }

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $data
     * @param ProductConfigurationProductLinkEntity|null $productConfigurationProductLink
     * @return ProductConfigurationProductLinkEntity
     */
    public function createUpdateProductConfigurationProductLink($data, ProductConfigurationProductLinkEntity $productConfigurationProductLink = null)
    {

        if (empty($productConfigurationProductLink)) {
            /** @var ProductConfigurationProductLinkEntity $productConfigurationProductLink */
            $productConfigurationProductLink = $this->entityManager->getNewEntityByAttributSetName("product_configuration_product_link");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($productConfigurationProductLink, $setter)) {
                $productConfigurationProductLink->$setter($value);
            }
        }

        $this->entityManager->saveEntity($productConfigurationProductLink);

        return $productConfigurationProductLink;
    }

    /**
     * @param ProductConfigurationProductLinkEntity $productConfigurationProductLink
     * @param bool $refreshActiveSaleable
     */
    public function deleteProductConfigurationProductLink(ProductConfigurationProductLinkEntity $productConfigurationProductLink, $refreshActiveSaleable = true)
    {
        $parentId = $productConfigurationProductLink->getProductId();
        $childId = $productConfigurationProductLink->getChildProductId();

        $this->entityManager->deleteEntityFromDatabase($productConfigurationProductLink);

        if ($refreshActiveSaleable) {
            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $this->crmProcessManager->refreshActiveSaleable(array("product_ids" => array($parentId, $childId)));
        }

        return true;
    }

    /**
     * @param ProductConfigurationBundleOptionProductLinkEntity $productConfigurationBundleOptionProductLink
     * @return bool
     */
    public function setProductConfigurableBundleProductAsDefault(ProductConfigurationBundleOptionProductLinkEntity $productConfigurationBundleOptionProductLink)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE product_configuration_bundle_option_product_link_entity SET is_default = 0 WHERE configurable_bundle_option_id = {$productConfigurationBundleOptionProductLink->getConfigurableBundleOptionId()} AND id != {$productConfigurationBundleOptionProductLink->getId()};";
        $this->databaseContext->executeNonQuery($q);

        $q = "UPDATE product_configuration_bundle_option_product_link_entity SET is_default = 1 WHERE id = {$productConfigurationBundleOptionProductLink->getId()};";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param ProductEntity $product
     * @param SProductAttributeConfigurationEntity $sProductAttributeConfiguration
     * @return |null
     */
    public function getProductConfigurableAttributeByProductAndAttribute(ProductEntity $product, SProductAttributeConfigurationEntity $sProductAttributeConfiguration)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("product_configurable_attribute");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("sProductAttributeConfiguration", "eq", $sProductAttributeConfiguration->getId()));
        $compositeFilter->addFilter(new SearchFilter("product", "eq", $product->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param ProductEntity $product
     * @return |null
     */
    public function getProductConfigurableAttributesByProduct(ProductEntity $product)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("product_configurable_attribute");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("product", "eq", $product->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("ord", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getProductConfigurableAttributeById($id)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("product_configurable_attribute");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param ProductEntity $product
     * @param SProductAttributeConfigurationEntity $sProductAttributeConfiguration
     * @return ProductConfigurableAttributeEntity
     */
    public function createProductConfigurableAttribute(ProductEntity $product, SProductAttributeConfigurationEntity $sProductAttributeConfiguration)
    {

        /** @var ProductConfigurableAttributeEntity $entity */
        $entity = $this->entityManager->getNewEntityByAttributSetName("product_configurable_attribute");

        $entity->setProduct($product);
        $entity->setSProductAttributeConfiguration($sProductAttributeConfiguration);

        $this->entityManager->saveEntity($entity);

        return $entity;
    }

    /**
     * @param ProductConfigurableAttributeEntity $productConfigurableAttribute
     */
    public function deleteProductConfigurableAttribute(ProductConfigurableAttributeEntity $productConfigurableAttribute)
    {
        $this->entityManager->deleteEntity($productConfigurableAttribute);
    }

    /**
     * @param $productId
     * @return |null
     */
    public function getProductsForConfigurableByParentId($productId)
    {

        $ret = 0;

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $s_product_attribute_configuration_ids = $this->getProductAttributeConfigurationIdsByParentId($productId);
        if (empty($s_product_attribute_configuration_ids)) {
            return 0;
        }

        $q = "SELECT GROUP_CONCAT(s.product_id) as product_ids FROM
            (SELECT spal.product_id FROM s_product_attributes_link_entity as spal WHERE spal.s_product_attribute_configuration_id IN ({$s_product_attribute_configuration_ids}) GROUP BY spal.product_id
            HAVING GROUP_CONCAT(spal.s_product_attribute_configuration_id ORDER BY spal.s_product_attribute_configuration_id asc) = '{$s_product_attribute_configuration_ids}'
        ) s;";
        $result = $this->databaseContext->getSingleEntity($q);

        if (empty($result) || empty($result["product_ids"])) {
            return $ret;
        }

        return $result["product_ids"];
    }

    /**
     * @param $productId
     * @return |null
     */
    public function getProductAttributeConfigurationIdsByParentId($productId)
    {

        $ret = null;

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT GROUP_CONCAT(pca.s_product_attribute_configuration_id ORDER BY pca.s_product_attribute_configuration_id asc) AS s_product_attribute_configuration_id FROM product_configurable_attribute_entity as pca WHERE pca.product_id = {$productId} GROUP BY pca.product_id;";
        $result = $this->databaseContext->getSingleEntity($q);

        if (empty($result)) {
            return $ret;
        }

        return $result["s_product_attribute_configuration_id"];
    }

    /**
     * @return array
     */
    public function getProductsOnSale()
    {
        if (empty($this->productGroupManager)) {
            $this->productGroupManager = $this->container->get("product_group_manager");
        }
        $ids = $this->productGroupManager->getProductDiscountIds();

        if (empty($ids)) {
            return 0;
        }

        return implode(",", $ids);
    }

    /**
     * @return array
     */
    public function getNewProducts()
    {
        if (empty($this->productGroupManager)) {
            $this->productGroupManager = $this->container->get("product_group_manager");
        }
        $ids = $this->productGroupManager->getProductIsNewIds();

        if (empty($ids)) {
            return 0;
        }

        return implode(",", $ids);
    }


    /**
     * Returns a json ready for insert into configurable_product_attributes in table product_configuration_product_link_entity
     */
    public function getChildProductConfigurableProductAttributesJson(ProductEntity $parentProduct, ProductEntity $childProduct)
    {

        $ret = null;

        $configurableAttributes = $parentProduct->getConfigurableProductAttributes();

        if (!EntityHelper::isCountable($configurableAttributes) || count($configurableAttributes) == 0) {
            return $ret;
        }

        if (empty($this->sProductManager)) {
            $this->sProductManager = $this->container->get("s_product_manager");
        }

        /** @var ProductConfigurableAttributeEntity $configurableAttribute */
        foreach ($configurableAttributes as $configurableAttribute) {
            $data = array();
            $data["attribute_id"] = $configurableAttribute->getSProductAttributeConfigurationId();
            $data["attribute_name"] = $configurableAttribute->getSProductAttributeConfiguration()->getName();
            $attributeValues = $this->sProductManager->getProductAttributeValueByKey($childProduct, $configurableAttribute->getSProductAttributeConfiguration()->getFilterKey());
            if (!isset($attributeValues["values"]) || empty($attributeValues["values"])) {
                continue;
            }
            $data["values"] = $attributeValues["values"];

            $ret[] = $data;
        }

        return json_encode($ret);
    }

    /**
     * @param ProductEntity $product
     * @return ProductEntity|mixed
     */
    public function getMasterProduct(ProductEntity $product)
    {

        $parentProducts = $product->getParentProducts();

        if (EntityHelper::isCountable($parentProducts) && count($parentProducts) > 0) {
            return $parentProducts[0];
        }

        return $product;
    }

    /*
     ******************************************************************
     ****************************************************************** CONFIGURABLE
     ******************************************************************
     */

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getConfigurableProductDetails(ProductEntity $product)
    {
        $ret = array();
        $ret["default"] = null;
        $ret["default_product"] = null;
        $ret["attributes"] = [];

        if ($product->getProductTypeId() != CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
            return $ret;
        }

        $productConfigurations = $product->getProductConfigurations();
        $productConfigurableAttributes = $this->getProductConfigurableAttributesByProduct($product);

        $tmpAttributesArray = array();
        $requiredAttributeIds = array();
        $requiredAttributeKeys = array();
        if (!EntityHelper::isCountable($productConfigurableAttributes) && count($productConfigurableAttributes) < 1) {
            return $ret;
        }

        /**
         * Get parent product attributes for default
         */
        $parentProductAttributes = array();
        if (EntityHelper::isCountable($product->getProductAttributes()) && count($product->getProductAttributes()) > 0) {
            /** @var SProductAttributesLinkEntity $parentProductAttribute */
            foreach ($product->getProductAttributes() as $parentProductAttribute) {
                $parentProductAttributes[$parentProductAttribute->getSProductAttributeConfigurationId()] = $parentProductAttribute->getConfigurationOption();
            }
        }

        /**
         * Get attributes from configurable
         */
        /** @var ProductConfigurableAttributeEntity $productConfigurableAttribute */
        foreach ($productConfigurableAttributes as $productConfigurableAttribute) {
            $requiredAttributeIds[] = $productConfigurableAttribute->getSProductAttributeConfigurationId();
            $requiredAttributeKeys[] = $productConfigurableAttribute->getSProductAttributeConfiguration()->getFilterKey();
        }

        /**
         * Check if parent has all attributes as configurable for default
         * Ovdje se definira koje atribute i vrijednosti ima parent
         */
        if (ArrayHelper::array_keys_exists(array_values($requiredAttributeIds), $parentProductAttributes)) {
            foreach ($requiredAttributeIds as $requiredAttributeId) {
                $ret["default"][$requiredAttributeId] = $parentProductAttributes[$requiredAttributeId];
            }
        }

        if (EntityHelper::isCountable($productConfigurations) && count($productConfigurations) > 0) {
            /** @var ProductConfigurationProductLinkEntity $productConfiguration */
            foreach ($productConfigurations as $productConfiguration) {

                /** @var ProductEntity $childProduct */
                $childProduct = $productConfiguration->getChildProduct();

                if (empty($this->crmProcessManager)) {
                    $this->crmProcessManager = $this->container->get("crm_process_manager");
                }

                /**
                 * Check if product valid for show
                 */
                if (!$this->crmProcessManager->isProductValid($childProduct)) {
                    continue;
                }

                $combinationKey = array();
                $usedAttributeIds = array();
                $productAttributeValues = null;

                $attributes = json_decode($productConfiguration->getConfigurableProductAttributes(), true);

                if (empty($attributes)) {
                    continue;
                }
                foreach ($attributes as $attribute) {
                    $usedAttributeIds[] = $attribute["attribute_id"];
                }
                if (!empty(array_diff($usedAttributeIds, $requiredAttributeIds)) || !empty(array_diff($requiredAttributeIds, $usedAttributeIds))) {
                    continue;
                }

                $isCandidateForDefault = true;

                foreach ($attributes as $attribute) {
                    $tmpAttributesArray[$attribute["attribute_id"]]["attribute_id"] = $attribute["attribute_id"];
                    $tmpAttributesArray[$attribute["attribute_id"]]["attribute_name"] = $attribute["attribute_name"];
                    $tmpAttributesArray[$attribute["attribute_id"]]["values"][$attribute["values"][0]["option_id"]] = $attribute["values"][0];

                    //TODO potencijalno se moze desiti da postoje 2 ista sa istim atributima
                    $combinationKey[$attribute["attribute_id"]] = $attribute["values"][0]["option_id"];

                    $productAttributeValues[$attribute["attribute_id"]] = $attribute["values"][0]["option_id"];

                    /**
                     * Set default child product for configurable
                     */
                    if (!isset($ret["default"][$attribute["attribute_id"]]) || $ret["default"][$attribute["attribute_id"]] != $attribute["values"][0]["option_id"]) {
                        $isCandidateForDefault = false;
                    }
                }

                if ($isCandidateForDefault) {
                    $ret["default_product"] = $childProduct;
                }

                sort($combinationKey);
                $ret["child_products"][md5(implode("", array_values($combinationKey)))] = $childProduct;
            }

            if (empty($ret["default_product"]) && isset($ret["child_products"]) && !empty($ret["child_products"])) {
                $ret["default_product"] = $ret["child_products"][array_key_first($ret["child_products"])];
            }

            if (!isset($ret["child_products"]) || empty($ret["child_products"])) {
                return null;
            }

            /**
             * If default product is empty take the first one
             */
            if (empty($ret["default_product"])) {
                $ret["default_product"] = array_values($ret["child_products"])[0];
            }

            /**
             * Set list of attributes and preselected value
             */
            if (!empty($tmpAttributesArray)) {
                foreach ($requiredAttributeIds as $requiredAttributeId) {
                    $ret["attributes"][$requiredAttributeId] = $tmpAttributesArray[$requiredAttributeId];
                }
                unset($tmpAttributesArray);
            }

            // Set available options
            if (isset($ret["attributes"])) {
                $attrKeys = array_keys($ret["attributes"]);
                foreach ($ret["attributes"] as $attrId => $data) {
                    $attrKey = array_search($attrId, $attrKeys);
                    if ($attrKey === 0) {
                        // First attribute has no dependencies
                        continue;
                    }
                    // Tu ide kroz svaku vrijednost atributa i generira dependencie u odnosu na prethodni atribut
                    foreach ($data["values"] as $key => $productData) {
                        $dependencies = $this->getDependenciesByAttributeKey($attrId, $productData["option_id"], $ret["attributes"][$attrKeys[$attrKey - 1]]["attribute_id"], $ret["child_products"]);
                        $ret["attributes"][$attrId]["values"][$key]["dependencies"][$attrKeys[$attrKey - 1]] = $dependencies;
                    }
                }
            }
        }

        if (isset($ret["attributes"]) && !empty($ret["attributes"])) {
            foreach ($ret["attributes"] as $key => $value) {
                usort($ret["attributes"][$key]["values"], function ($a, $b) {
                    return $a['value'] > $b['value'];
                });
            }
        }

        if (empty($ret["default_product"])) {
            $ret["default_product"] = $product;
        }

        if (empty($this->sProductManager)) {
            $this->sProductManager = $this->container->get("s_product_manager");
        }

        $ret["default"] = array();
        foreach ($requiredAttributeKeys as $requiredAttributeKey) {
            $attributeValue = $this->sProductManager->getProductAttributeValueByKey($ret["default_product"], $requiredAttributeKey);
            if (!empty($attributeValue)) {
                $ret["defaults"][$attributeValue["attribute"]->getId()] = $attributeValue["values"][0]["option_id"];
                if (!empty($ret["attributes"])) {
                    foreach ($ret["attributes"][$attributeValue["attribute"]->getId()]["values"] as $key => $val) {
                        if ($val["option_id"] == $attributeValue["values"][0]["option_id"]) {
                            $ret["attributes"][$attributeValue["attribute"]->getId()]["values"][$key]["selected"] = 1;
                            break;
                        }
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * @param array $childProducts
     * @return array
     */
    private function getDependenciesByAttributeKey($attrId, $optionId, $dependentAttributeId, $childProducts)
    {
        $dependencies = [];
        /** @var ProductEntity $childProduct */
        foreach ($childProducts as $childProduct) {
            // Provjerava da li child proizvod ima oznacenu vrijednost atributa
            $foundAttribute = false;
            foreach ($childProduct->getPreparedProductAttributes() as $attribute) {
                foreach ($attribute["values"] as $attributeValue) {
                    if ($attribute["attribute"]->getId() == $attrId && $attributeValue["option_id"] == $optionId) {
                        $foundAttribute = true;
                        break;
                    }
                }
                if ($foundAttribute) {
                    break;
                }
            }
            // Ako child proizvod ima oznacenu vrijednost atributa ide traziti koje su mu vrijednosti dependent atributa i dodaje ih kao dependencie
            if ($foundAttribute) {
                // Search for dependent attribute values
                foreach ($childProduct->getPreparedProductAttributes() as $attribute) {
                    if ($attribute["attribute"]->getId() == $dependentAttributeId) {
                        foreach ($attribute["values"] as $attributeValue) {
                            $dependencies[] = $attributeValue["option_id"];
                        }
                    }
                }
            }
        }
        return $dependencies;
    }


    /**
     * @param ProductEntity $product
     * @param $selectedOptions
     * @return mixed|null
     */
    public function getSimpleProductFromConfiguration(ProductEntity $product, $selectedOptions)
    {

        if ($product->getProductTypeId() != CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
            return null;
        }

        $configurableOptions = $this->getConfigurableProductDetails($product);

        if (empty($configurableOptions)) {
            return null;
        }

        sort($selectedOptions);

        $combinationKey = md5(implode("", array_values($selectedOptions)));
        if (!isset($configurableOptions["child_products"][$combinationKey])) {
            return null;
        }

        return $configurableOptions["child_products"][$combinationKey];
    }

    /**
     * @param $parentId
     * @param $childId
     * @param false $refreshActiveSaleable
     * @return bool
     * @throws \Exception
     */
    public function insertProductConfigurationProductLink($parentId, $childId, $refreshActiveSaleable = false)
    {
        /** @var ProductEntity $parentEntity */
        $parentEntity = $this->getProductById($parentId);

        if (empty($parentEntity)) {
            throw new \Exception("No product found for parent id: {$parentId}");
        }

        /** @var ProductEntity $childProduct */
        $childProduct = $this->getProductById($childId);

        if (empty($childProduct)) {
            throw new \Exception("No product found for child id: {$childId}");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("childProduct", "eq", $childId));
        $compositeFilter->addFilter(new SearchFilter("product", "eq", $parentEntity->getId()));

        $data = array();
        $data["product"] = $parentEntity;
        $data["child_product"] = $childProduct;
        $data["configurable_product_attributes"] = $this->getChildProductConfigurableProductAttributesJson($parentEntity, $childProduct);

        /** @var ProductConfigurationProductLinkEntity $relation */
        $relation = $this->getProductConfigurationProductLink($compositeFilter);
        if (empty($relation)) {
            $this->createUpdateProductConfigurationProductLink($data);
        } elseif ($relation->getConfigurableProductAttributes() !== $data["configurable_product_attributes"]) {
            $this->createUpdateProductConfigurationProductLink($data, $relation);
        }

        if ($refreshActiveSaleable) {
            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $this->crmProcessManager->refreshActiveSaleable(array("product_ids" => array($parentId, $childId)));
        }

        return true;
    }

    /**
     * @param SProductAttributeConfigurationOptionsEntity $SProductAttributeConfigurationOptions
     * @return bool
     * @throws \Exception
     */
    public function rebuildConfigurableProductConfigurationsForAttributeOption(SProductAttributeConfigurationOptionsEntity $SProductAttributeConfigurationOptions)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM product_configuration_product_link_entity as pcpl WHERE pcpl.child_product_id in (SELECT product_id FROM s_product_attributes_link_entity as spal WHERE configuration_option = {$SProductAttributeConfigurationOptions->getId()})
                AND pcpl.product_id IN (SELECT product_id FROM product_configurable_attribute_entity as pcae WHERE s_product_attribute_configuration_id = {$SProductAttributeConfigurationOptions->getConfigurationAttributeId()})";
        $data = $this->databaseContext->getAll($q);

        if (empty($data)) {
            return true;
        }

        $ids = array();

        foreach ($data as $d) {
            $ids[] = $d["product_id"];
            $ids[] = $d["child_product_id"];
        }

        $ids = array_unique($ids);

        $this->rebuildConfigurableProducts($ids);

        return true;
    }

    /**
     * @param array $productIds
     * @return bool
     * @throws \Exception
     */
    public function rebuildConfigurableProducts($productIds = array())
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $aditionalWhere = "";
        if (!empty($productIds)) {
            $productIds = implode(",", $productIds);
            if (!empty(trim($productIds))) {
                $aditionalWhere = " AND (pcpl.product_id IN ({$productIds}) OR pcpl.child_product_id IN ({$productIds})) ";
            }
        }

        $q = "SELECT pcpl.* FROM product_configuration_product_link_entity as pcpl LEFT JOIN product_entity as p ON pcpl.product_id = p.id WHERE p.product_type_id = " . CrmConstants::PRODUCT_TYPE_CONFIGURABLE . " {$aditionalWhere}";
        $data = $this->databaseContext->getAll($q);

        if (empty($data)) {
            return true;
        }

        $ids = array();

        foreach ($data as $d) {
            $this->insertProductConfigurationProductLink($d["product_id"], $d["child_product_id"]);
            $ids[] = $d["product_id"];
            $ids[] = $d["child_product_id"];
        }

        $ids = array_unique($ids);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $this->crmProcessManager->refreshActiveSaleable(array("product_ids" => $ids));

        return true;
    }

    /*
     ******************************************************************
     ****************************************************************** BUNDLE
     ******************************************************************
     */

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getParentBundleProducts(ProductEntity $product){

        $ret = Array();

        if($product->getProductType()->getId() != CrmConstants::PRODUCT_TYPE_SIMPLE){
            return $ret;
        }

        $parentProducts = $product->getParentBundleProducts();

        if(EntityHelper::isCountable($parentProducts) && count($parentProducts)){

            if(empty($this->crmProcessManager)){
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            foreach ($parentProducts as $parentProduct){
                if($this->crmProcessManager->isProductValid($parentProduct)){
                    $ret[] = $parentProduct;
                }
            }
        }

        return $ret;
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getBundleProductDetails(ProductEntity $product)
    {

        $ret = array();

        if ($product->getProductTypeId() != CrmConstants::PRODUCT_TYPE_BUNDLE) {
            return $ret;
        }

        $productConfigurations = $product->getProductConfigurations();
        if (!EntityHelper::isCountable($productConfigurations) || count($productConfigurations) == 0) {
            return $ret;
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        /** @var ProductConfigurationProductLinkEntity $productConfiguration */
        foreach ($productConfigurations as $productConfiguration) {

            /**
             * Check if product valid for show
             */
            if (!$this->crmProcessManager->isProductValid($productConfiguration->getChildProduct())) {
                continue;
            }

            $data = array();
            $data["childProduct"] = $productConfiguration->getChildProduct();
            $data["minQty"] = $productConfiguration->getMinQty();
            $data["isParent"] = false;

            if ($productConfiguration->getChildProduct()->getId() == $product->getId()) {
                $data["isParent"] = true;
                if (empty($data["minQty"])) {
                    $data["minQty"] = 1;
                }
            }

            $ret["bundle_product"][] = $data;
        }

        return $ret;
    }

    /**
     * @param ProductEntity $product
     * @param array $includeOptions
     * @return mixed
     * @throws \Exception
     */
    public function getBundleProductSavingCalculations(ProductEntity $product, $includeOptions = [])
    {
        $options = $this->getBundleProductDetails($product);

        /** @var Session $session */
        $session = $this->container->get("session");

        /** @var AccountEntity $account */
        $account = null;
        if (!empty($session->get("account"))) {
            $account = $session->get("account");
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $ret = $this->crmProcessManager->getProductPrices($product, $account);
        $ret["total_bundle_price"] = 0;
        $ret["total_price"] = 0;
        if (!empty($options)) {
            /** @var ProductConfigurationProductLinkEntity $option */
            foreach ($options["bundle_product"] as $option) {
                /** @var ProductEntity $childProduct */
                $childProduct = $option["childProduct"];

                if (!empty($includeOptions) && !in_array($childProduct->getId(), $includeOptions)) {
                    continue;
                }

                $productPrices = $this->crmProcessManager->getProductPrices($childProduct, $account, $product);
                $ret["total_bundle_price"] = $ret["total_bundle_price"] + ($productPrices["discount_price"] ?? $productPrices["price"]) * ($option["minQty"] == 0 ? 1 : $option["minQty"]);
                $ret["total_price"] = $ret["total_price"] + $productPrices["price"] * ($option["minQty"] == 0 ? 1 : $option["minQty"]);
            }
            $ret["total_discount_price"] = $ret["total_price"] - $ret["total_bundle_price"];
        }
        return $ret;
    }

    /*
     ******************************************************************
     ****************************************************************** OTHER
     ******************************************************************
     */

    /**
     * @param $ids
     * @param $type
     * @return bool
     */
    public function setExcludeFromDiscounts($ids, $type)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE product_entity SET exclude_from_discounts = {$type} WHERE id in (" . implode(",", $ids) . ");";
        $this->databaseContext->executeNonQuery($q);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $this->crmProcessManager->recalculateProductsPrices(array("product_ids" => $ids));

        return true;
    }

    /**
     * @param ProductEntity $product
     * @param int $days
     * @return float|null
     */
    public function getSingleProductHistoryPrice(ProductEntity $product, $days = 30, $fromDate = null)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (empty($days)) {
            $days = 30;
        }

        if (empty($fromDate)) {
            $fromDate = new \DateTime();
        }

        $q = "SELECT min(price) AS count
            FROM product_price_history_entity
            WHERE entity_state_id = 1 AND price_change_date > DATE_SUB('" . $fromDate->format("Y-m-d H:i:s") . "',INTERVAL {$days} day) AND price_change_date < '" . $fromDate->format("Y-m-d H:i:s") . "' AND product_id = {$product->getId()}";
        $price = $this->databaseContext->getSingleResult($q);

        if (!empty($price)) {
            return floatval($price);
        }

        return $product->getPriceRetail();
    }

    /**
     * @param $productCodes
     * @return array
     */
    public function getProductIdsFromCodes($productCodes)
    {
        if (is_array($productCodes)) {
            $productCodes = implode(",", $productCodes);
        }

        $q = "SELECT id FROM product_entity WHERE code IN ({$productCodes})";
        $res = $this->databaseContext->executeQuery($q);

        if (empty($res)) {
            return [];
        }

        return array_column($res, "id");
    }

    /**
     * @return string
     */
    public function getProductIdsWithoutImages()
    {

        $ret = array(0);

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT id FROM product_entity WHERE id NOT IN (SELECT DISTINCT(product_id) FROM product_images_entity WHERE entity_state_id = 1);";
        $data = $this->databaseContext->getAll($q);

        if (!empty($data)) {
            $ret = array_column($data, "id");
        }

        return implode(",", $ret);
    }

    /**
     * @param ProductEntity $product
     * @param $string
     * @return string
     */
    public function replaceProductStringTokens(ProductEntity $product, $string)
    {
        if (empty($this->sProductManager)) {
            $this->sProductManager = $this->container->get("s_product_manager");
        }

        $session = $this->container->get('session');

        $pieces = explode("{{", $string);

        foreach ($pieces as $key => $piece) {
            if (stripos($piece, "}}") !== false) {
                $replaced = false;

                $secondaryPieces = explode("}}", $piece);

                $secondaryPieces = array_map('trim', $secondaryPieces);

                $filterKey = $secondaryPieces[0];

                $getter = EntityHelper::makeGetter($filterKey);

                if (EntityHelper::checkIfMethodExists($product, $getter)) {
                    $value = $product->{$getter}();

                    if (empty($value)) {
                        return "";
                    }

                    if (empty($this->getPageUrlExtension)) {
                        $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
                    }

                    if (is_object($value)) {
                        $value = $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $value, "name");
                    } elseif (is_array($value)) {
                        $value = $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $product, $filterKey);
                    }

                    $secondaryPieces[0] = $value;

                    $pieces[$key] = implode(" ", $secondaryPieces);

                    $replaced = true;
                } else {
                    $sProductAttributeValue = $this->sProductManager->getProductAttributeValueByKey($product, $filterKey);

                    if (!empty($sProductAttributeValue)) {
                        $secondaryPieces[0] = $sProductAttributeValue["values"][0]["value"];
                        $pieces[$key] = implode(" ", $secondaryPieces);

                        $replaced = true;
                    }
                }

                if (!$replaced) {
                    // Token not replaced. Return empty instead of returning braces to frontend.
                    return "";
                }
            }
        }

        $pieces = array_map('trim', $pieces);

        return implode(" ", $pieces);
    }
}
