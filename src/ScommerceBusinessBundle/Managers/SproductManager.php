<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use CrmBusinessBundle\Abstracts\AbstractImportManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\ProductManager;
use CrmBusinessBundle\Managers\QuoteManager;
use AppBundle\Models\InsertModel;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\ProductTypeEntity;
use ScommerceBusinessBundle\Entity\SProductAttributeConfigurationEntity;
use ScommerceBusinessBundle\Entity\SRouteEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;

class SproductManager extends AbstractScommerceManager
{
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var EntityType $etSproductAttributeConfiguration */
    protected $etSproductAttributeConfiguration;
    /** @var EntityType $etProductType */
    protected $etProductType;
    /** @var EntityType $etProduct */
    protected $etProduct;
    /** @var AbstractImportManager $importManager */
    protected $importManager;
    /** @var AttributeSet $asAttributeLink */
    protected $asAttributeLink;
    /** @var ProductManager $productManager */
    protected $productManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $productId
     * @return mixed
     */
    public function getSproductGroupAttributeConfigurations($productId)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $ret = array();

        $q = "SELECT DISTINCT s_product_attribute_configuration_id
            FROM s_product_configuration_product_group_link_entity spc
            JOIN product_product_group_link_entity ppg ON spc.product_group_id = ppg.product_group_id
            JOIN s_product_attribute_configuration_entity as spac ON spc.s_product_attribute_configuration_id = spac.id
            WHERE ppg.product_id = '{$productId}'
            AND spc.entity_state_id = 1
            AND spac.entity_state_id = 1
            AND spac.is_active = 1
            AND ppg.entity_state_id = 1
            ORDER BY spac.name ASC;";
        $data = $this->databaseContext->executeQuery($q);
        if (!empty($data)) {

            $ids = array_column($data, "s_product_attribute_configuration_id");

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",", $ids)));

            $ret = $this->getSproductAttributeConfigurations($compositeFilter);
        }

        return $ret;
    }

    /**
     * @param $filterKey
     * @return |null
     */
    public function getSproductAttributeConfigurationByKey($filterKey)
    {

        if (empty($this->etSproductAttributeConfiguration)) {
            $this->etSproductAttributeConfiguration = $this->entityManager->getEntityTypeByCode("s_product_attribute_configuration");
        }

        $compositeFilters = new CompositeFilterCollection();

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("filterKey", "eq", $filterKey));
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($this->etSproductAttributeConfiguration, $compositeFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getSproductAttributeConfigurationById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(SProductAttributeConfigurationEntity::class);
        return $repository->find($id);
    }

    /**
     * @param CompositeFilter|null $compositeFilterAddon
     * @return array
     */
    public function getSproductAttributeConfigurations(CompositeFilter $compositeFilterAddon = null)
    {
        if (empty($this->etSproductAttributeConfiguration)) {
            $this->etSproductAttributeConfiguration = $this->entityManager->getEntityTypeByCode("s_product_attribute_configuration");
        }

        $compositeFilters = new CompositeFilterCollection();

        if (!empty($compositeFilterAddon)) {
            $compositeFilters->addCompositeFilter($compositeFilterAddon);
        } else {
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("entityStateId", "nn", null));
            $compositeFilters->addCompositeFilter($compositeFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("name", "asc"));

        $ret = array();

        $data = $this->entityManager->getEntitiesByEntityTypeAndFilter($this->etSproductAttributeConfiguration, $compositeFilters, $sortFilters);

        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d->getId()] = $d;
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getProductTypes()
    {
        if (empty($this->etProductType)) {
            $this->etProductType = $this->entityManager->getEntityTypeByCode("product_type");
        }

        $ret = array();

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $productTypes = $this->entityManager->getEntitiesByEntityTypeAndFilter($this->etProductType, $compositeFilters);

        /** @var ProductTypeEntity $productType */
        foreach ($productTypes as $productType) {
            $ret[$productType->getId()] = $productType;
        }

        return $ret;
    }

    /**
     * @param $remoteId
     * @param $remoteSource
     * @return |null
     */
    public function getProductByRemoteIdAndSource($remoteId, $remoteSource)
    {
        if (empty($this->etProduct)) {
            $this->etProduct = $this->entityManager->getEntityTypeByCode("product");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("remoteId", "eq", $remoteId));
        $compositeFilter->addFilter(new SearchFilter("remoteSource", "eq", $remoteSource));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($this->etProduct, $compositeFilters);
    }

    /**
     * @param SProductAttributeConfigurationEntity $productAttributeConfigurationEntity
     * @return string
     */
    public function generateSProductAttributeConfigurationKey(SProductAttributeConfigurationEntity $productAttributeConfigurationEntity)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $urlTmp = $url = $this->createAttributeKey(StringHelper::sanitizeFileName($productAttributeConfigurationEntity->getName()));

        $i = 1;

        $id = $productAttributeConfigurationEntity->getId();

        if (empty($id) || !isset($id)) {
            $id = 0;
        }

        //{$productAttributeConfigurationEntity->getId()}
        $q = "SELECT * FROM s_product_attribute_configuration_entity WHERE filter_key = '{$urlTmp}' AND id != $id;";
        $existingRoute = $this->databaseContext->getAll($q);

        while (!empty($existingRoute)) {
            $urlTmp = $url . "_" . $i;
            $q = "SELECT * FROM s_product_attribute_configuration_entity WHERE filter_key = '{$urlTmp}' AND id != $id;";
            $existingRoute = $this->databaseContext->getAll($q);
            $i++;
        }

        return $urlTmp;
    }

    /**
     * @param $url
     * @return false|mixed|string|string[]|null
     */
    public function createAttributeKey($url)
    {

        $url = trim($url);
        $url = mb_strtolower($url, mb_detect_encoding($url));
        $url = preg_replace('/[^A-Za-z0-9-\s]/', ' ', $url);
        $url = preg_replace('/[\s]+/', '_', $url);
        $url = preg_replace('/[_][_]+/', '_', $url);
        $url = trim($url, '_');

        return $url;
    }

    /**
     * @param array $productIds
     * @return bool
     */
    public function setProductSortPrices($productIds = array())
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (!empty($productIds)) {
            $productIds = array_filter($productIds);
        }

        $where = "WHERE ";
        if (!empty($productIds)) {
            $where .= " id IN (" . implode(",", $productIds) . ") AND ";
        }

        $q = "UPDATE product_entity SET sort_price_retail =
            CASE
               WHEN discount_price_retail > 0 AND (date_discount_from IS NULL OR date_discount_from <= NOW()) AND (date_discount_to IS NULL OR date_discount_to > NOW()) THEN discount_price_retail
               ELSE price_retail
            END {$where} product_type_id NOT IN (" . CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE . "," . CrmConstants::PRODUCT_TYPE_CONFIGURABLE . ");";
        $this->databaseContext->executeNonQuery($q);

        $q = "UPDATE product_entity SET sort_price_base =
            CASE
                WHEN discount_price_base > 0 AND (date_discount_base_from IS NULL OR date_discount_base_from <= NOW()) AND (date_discount_base_to IS NULL OR date_discount_base_to > NOW()) THEN discount_price_base
               ELSE price_base
            END {$where} product_type_id NOT IN (" . CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE . "," . CrmConstants::PRODUCT_TYPE_CONFIGURABLE . ");";
        $this->databaseContext->executeNonQuery($q);

        $updateQuery = array();

        /**
         * Get calculation type
         */
        if(empty($this->crmProcessManager)){
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $priceRetailCode = "price";
        $priceBaseCode = "price_other";

        if($this->crmProcessManager->getCalculationMethod() == "Vpc"){
            $priceRetailCode = "price_other";
            $priceBaseCode = "price";
        }

        /**
         * Set configurable bundle sort prices
         */
        $tmpWhere = "WHERE ";
        if (!empty($productIds)) {
            $tmpWhere .= " pcbop.product_id IN (" . implode(",", $productIds) . ") AND ";
        }
        $q = "SELECT DISTINCT(pcpl.product_id) as id FROM product_configuration_bundle_option_product_link_entity as pcbop LEFT JOIN product_configuration_product_link_entity AS pcpl ON pcbop.configurable_bundle_option_id = pcpl.configurable_bundle_option_id {$tmpWhere} pcpl.product_id is not null AND pcpl.entity_state_id = 1
              UNION SELECT id FROM product_entity {$where} product_type_id = " . CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE . ";";
        $configurableBundleIds = $this->databaseContext->getAll($q);

        if (!empty($configurableBundleIds)) {
            $configurableBundleIds = array_column($configurableBundleIds, "id");
            $configurableBundleIds = array_unique($configurableBundleIds);

            if (empty($this->productManager)) {
                $this->productManager = $this->container->get("product_manager");
            }

            if (empty($this->quoteManager)) {
                $this->quoteManager = $this->container->get("quote_manager");
            }

            foreach ($configurableBundleIds as $configurableBundleId) {

                /** @var ProductEntity $product */
                $product = $this->productManager->getProductById($configurableBundleId);
                $productIds = array();

                $configurableBundleProductDetails = $this->productManager->getConfigurableBundleProductDetails($product);

                if (!empty($configurableBundleProductDetails)) {
                    foreach ($configurableBundleProductDetails as $configurableBundleProductDetail) {
                        if (!empty($configurableBundleProductDetail["default"])) {
                            $productIds[] = $configurableBundleProductDetail["default"]->getId();
                        }
                    }
                }

                $ret = $this->quoteManager->getConfigurableBundleProductPrices($product, null, $productIds);

                if (!empty($ret)) {
                    $updateQuery[] = "UPDATE product_entity SET sort_price_base = '{$ret["{$priceBaseCode}"]}', sort_price_retail = '{$ret["{$priceRetailCode}"]}' WHERE id = {$configurableBundleId};";
                }
            }
        }

        /**
         * Set configurable product prices
         */
        $tmpWhere = "WHERE ";
        if (!empty($productIds)) {
            $tmpWhere .= " pcple.child_product_id IN (" . implode(",", $productIds) . ") AND ";
        }
        $q = "SELECT DISTINCT(pcple.product_id) as id FROM product_configuration_product_link_entity as pcple {$tmpWhere} pcple.configurable_product_attributes is not null";
        $childProductIds = $this->databaseContext->getAll($q);

        if (!empty($childProductIds)) {
            $childProductIds = array_column($childProductIds, "id");
            $childProductIds = array_unique($childProductIds);

            if (empty($this->productManager)) {
                $this->productManager = $this->container->get("product_manager");
            }

            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            foreach ($childProductIds as $childProductId) {

                /** @var ProductEntity $parentProduct */
                $parentProduct = $this->productManager->getProductById($childProductId);

                $ret = null;
                $product = null;

                $configurableProductDetails = $this->productManager->getConfigurableProductDetails($parentProduct);
                if (!empty($configurableProductDetails)) {
                    $product = $configurableProductDetails["default_product"];

                    if (!empty($product)) {
                        $ret = $this->crmProcessManager->getProductPrices($product, null, $parentProduct);
                    }
                }
                if (!empty($ret)) {
                    $updateQuery[] = "UPDATE product_entity SET sort_price_base = '{$ret["{$priceBaseCode}"]}', sort_price_retail = '{$ret["{$priceRetailCode}"]}' WHERE id = {$childProductId};";
                }
            }
        }

        if (!empty($updateQuery)) {
            $updateQuery = implode(" ", $updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }

        //$this->databaseContext->executeNonQuery("CALL sp_product_active_discount_check()");

        return true;
    }

    /**
     * @return array
     */
    public function existingAttributeConfigurations()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $ret = array();

        $q = "SELECT *
            FROM s_product_attribute_configuration_entity;";

        $data = $this->databaseContext->getAll($q);
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d["id"]] = $d;
            }
        }

        return $ret;
    }

    /**
     * @param $productId
     * @return array
     */
    public function existingAttributeLinks($productId)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $ret = array();

        $q = "SELECT *
            FROM s_product_attributes_link_entity
            WHERE product_id = '{$productId}';";

        $data = $this->databaseContext->getAll($q);
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d["s_product_attribute_configuration_id"]][$d["attribute_value"]] = $d;
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingConfigurationOptions()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $ret = array();

        $q = "SELECT *
            FROM s_product_attribute_configuration_options_entity;";

        $data = $this->databaseContext->getAll($q);
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d["id"]] = $d;
            }
        }

        return $ret;
    }

    /**
     * @return bool
     */
    public function updateAllsProductAttributeLinkValues()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE s_product_attributes_link_entity as spal
            LEFT JOIN s_product_attribute_configuration_options_entity as spaco ON spal.configuration_option = spaco.id and spal.s_product_attribute_configuration_id = spaco.configuration_attribute_id
            SET spal.attribute_value = spaco.configuration_value
            WHERE spal.configuration_option is not null
            AND BINARY(spal.attribute_value) != BINARY(spaco.configuration_value);";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param $productId
     * @param $configurationId
     * @param $attributeValue
     * @param null $optionId
     * @return mixed
     */
    public function getLinkInsert($productId, $configurationId, $attributeValue, $optionId = NULL)
    {
        if (empty($this->importManager)) {
            $this->importManager = new AbstractImportManager();
            $this->importManager->setContainer($this->getContainer());
            $this->importManager->initialize();
        }

        if (empty($this->asAttributeLink)) {
            $this->asAttributeLink = $this->entityManager->getAttributeSetByCode("s_product_attributes_link");
        }

        $linkInsertArray = (new InsertModel($this->asAttributeLink))->getArray();

        $linkInsertArray["product_id"] = $productId;
        $linkInsertArray["s_product_attribute_configuration_id"] = $configurationId;
        $linkInsertArray["attribute_value"] = $attributeValue;
        $linkInsertArray["attribute_value_key"] = md5($productId . $configurationId . $optionId);
        $linkInsertArray["configuration_option"] = $optionId;

        return $linkInsertArray;
    }

    /**
     * @param ProductEntity $product
     * @param $p
     * @return bool
     */
    public function updateSProductAttributeConfiguration(ProductEntity $product, $p)
    {
        $this->entityManager->refreshEntity($product);

        if (empty($this->importManager)) {
            $this->importManager = new AbstractImportManager();
            $this->importManager->setContainer($this->getContainer());
            $this->importManager->initialize();
        }
        if (empty($this->asAttributeLink)) {
            $this->asAttributeLink = $this->entityManager->getAttributeSetByCode("s_product_attributes_link");
        }
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $existingAttributeConfigurations = $this->existingAttributeConfigurations();
        $existingAttributeLinks = $this->existingAttributeLinks($product->getId());
        $existingConfigurationOptions = $this->getExistingConfigurationOptions();

        $insertArray = array();
        $updateArray = array();
        $deleteArray = array();

        if (!empty($existingAttributeLinks)) {
            foreach ($existingAttributeLinks as $configurationId => $links) {
                foreach ($links as $link) {
                    $deleteArray["s_product_attributes_link_entity"][$link["id"]] = array("id" => $link["id"]);
                }
            }
        }

        if (isset($p["s_product_attributes_link"]) && !empty($p["s_product_attributes_link"])) {

            foreach ($p["s_product_attributes_link"] as $configurationId => $item) {

                /**
                 * Autocomplete
                 */
                if ($existingAttributeConfigurations[$configurationId]["s_product_attribute_configuration_type_id"] == 1) {

                    $optionId = StringHelper::sanitizeFileName($item);
                    if (empty($optionId)) {
                        continue;
                    }

                    if (!isset($existingAttributeLinks[$configurationId])) {
                        $insertArray["s_product_attributes_link_entity"][] = $this->getLinkInsert(
                            $product->getId(),
                            $configurationId,
                            $existingConfigurationOptions[$optionId]["configuration_value"],
                            $optionId);
                    } else {
                        $configValue = $existingConfigurationOptions[$optionId]["configuration_value"];
                        if (!isset($existingAttributeLinks[$configurationId][$configValue])) {
                            $insertArray["s_product_attributes_link_entity"][] = $this->getLinkInsert(
                                $product->getId(),
                                $configurationId,
                                $existingConfigurationOptions[$optionId]["configuration_value"],
                                $optionId);
                        } else {
                            unset($deleteArray["s_product_attributes_link_entity"][$existingAttributeLinks[$configurationId][$configValue]["id"]]);
                            $updateArray["s_product_attributes_link_entity"][$existingAttributeLinks[$configurationId][$configValue]["id"]] = array(
                                "modified" => "NOW()",
                                "attribute_value" => $existingConfigurationOptions[$optionId]["configuration_value"],
                                "configuration_option" => $optionId
                            );
                        }
                    }
                } /**
                 * Multiselect
                 */
                else if ($existingAttributeConfigurations[$configurationId]["s_product_attribute_configuration_type_id"] == 2) {
                    
                    $item = array_unique($item);

                    foreach ($item as $optionId) {
                        $optionId = StringHelper::sanitizeFileName($optionId);
                        if (empty($optionId)) {
                            continue;
                        }
                        if (!isset($existingAttributeLinks[$configurationId])) {
                            $insertArray["s_product_attributes_link_entity"][] = $this->getLinkInsert(
                                $product->getId(),
                                $configurationId,
                                $existingConfigurationOptions[$optionId]["configuration_value"],
                                $optionId);
                        } else {
                            $configValue = $existingConfigurationOptions[$optionId]["configuration_value"];
                            if (!isset($existingAttributeLinks[$configurationId][$configValue])) {
                                $insertArray["s_product_attributes_link_entity"][] = $this->getLinkInsert(
                                    $product->getId(),
                                    $configurationId,
                                    $existingConfigurationOptions[$optionId]["configuration_value"],
                                    $optionId);
                            } else {
                                unset($deleteArray["s_product_attributes_link_entity"][$existingAttributeLinks[$configurationId][$configValue]["id"]]);
                                $updateArray["s_product_attributes_link_entity"][$existingAttributeLinks[$configurationId][$configValue]["id"]] = array(
                                    "modified" => "NOW()",
                                    "attribute_value" => $existingConfigurationOptions[$optionId]["configuration_value"],
                                    "configuration_option" => $optionId
                                );
                            }
                        }
                    }
                } /**
                 * Text/bool
                 */
                else if ($existingAttributeConfigurations[$configurationId]["s_product_attribute_configuration_type_id"] == 3 ||
                    $existingAttributeConfigurations[$configurationId]["s_product_attribute_configuration_type_id"] == 4) {
                    $item = StringHelper::sanitizeFileName($item);
                    if (empty($item)) {
                        continue;
                    }
                    if (!isset($existingAttributeLinks[$configurationId])) {
                        $insertArray["s_product_attributes_link_entity"][] = $this->getLinkInsert(
                            $product->getId(),
                            $configurationId,
                            $item);
                    } else {
                        $attrValue = array_key_first($existingAttributeLinks[$configurationId]);
                        unset($deleteArray["s_product_attributes_link_entity"][$existingAttributeLinks[$configurationId][$attrValue]["id"]]);
                        if ($item != $attrValue) {
                            $updateArray["s_product_attributes_link_entity"][$existingAttributeLinks[$configurationId][$attrValue]["id"]] = array(
                                "modified" => "NOW()",
                                "attribute_value" => $item
                            );
                        }
                    }
                }
            }
        }

        $this->importManager->executeDeleteQuery($deleteArray);
        $this->importManager->executeInsertQuery($insertArray);
        $this->importManager->executeUpdateQuery($updateArray);
        
        unset($existingAttributeConfigurations);
        unset($existingAttributeLinks);
        unset($existingConfigurationOptions);

        if (empty($this->brandsManager)) {
            $this->brandsManager = $this->container->get("brands_manager");
        }

        $this->brandsManager->syncBrandsWithSProductAttributeConfigurationOptions();

        $this->entityManager->refreshEntity($product);

        /**
         * Regenerate configurations if configurable product
         */
        $parentProducts = $product->getParentConfigurableProducts();
        if (EntityHelper::isCountable($parentProducts) && count($parentProducts)) {

            if (empty($this->productManager)) {
                $this->productManager = $this->container->get("product_manager");
            }

            /** @var ProductEntity $parentProduct */
            foreach ($parentProducts as $parentProduct) {
                try {
                    $this->productManager->insertProductConfigurationProductLink($parentProduct->getId(), $product->getId());
                } catch (\Exception $e) {
                    if (empty($this->errorLogManager)) {
                        $this->errorLogManager = $this->container->get("error_log_manager");
                    }

                    $this->errorLogManager->logExceptionEvent("updateSProductAttributeConfiguration", $e, false);
                }
            }

            $this->entityManager->refreshEntity($product);
        }

        return true;
    }

    /**
     * @param ProductEntity $product
     * @param $filterKey
     * @return array|bool
     */
    public function getProductAttributeValueByKey(ProductEntity $product, $filterKey)
    {
        $sCompositeFilter = new CompositeFilter();
        $sCompositeFilter->setConnector("and");
        $sCompositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $sCompositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
        $sCompositeFilter->addFilter(new SearchFilter("filterKey", "eq", $filterKey));

        /** @var SProductAttributeConfigurationEntity $sProductAttributeConfiguration */
        $sProductAttributeConfiguration = null;

        $sProductAttributeConfigurations = $this->getSproductAttributeConfigurations($sCompositeFilter);
        if (!empty($sProductAttributeConfigurations)) {
            $sProductAttributeConfiguration = array_shift($sProductAttributeConfigurations);
        }

        if (empty($sProductAttributeConfiguration) || $sProductAttributeConfiguration->getEntityStateId() != 1 || $sProductAttributeConfiguration->getIsActive() == 0) {
            return false;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT id, attribute_value_key, configuration_option, attribute_value, prefix, sufix FROM s_product_attributes_link_entity WHERE s_product_attribute_configuration_id = {$sProductAttributeConfiguration->getId()} AND product_id = {$product->getId()};";
        $productAttributes = $this->databaseContext->getAll($q);

        if (empty($productAttributes)) {
            return false;
        }

        $ret = array();

        $ret["ord"] = $sProductAttributeConfiguration->getOrd();
        $ret["attribute"] = $sProductAttributeConfiguration;
        foreach ($productAttributes as $productAttribute) {
            $ret["values"][] = array("id" => $productAttribute["id"], "option_id" => $productAttribute["configuration_option"], "attribute_value_key" => $productAttribute["attribute_value_key"], "value" => $productAttribute["attribute_value"], "prefix" => $productAttribute["prefix"], "sufix" => $productAttribute["sufix"]);
        }

        return $ret;
    }

    /**
     * @param $filterKey
     * @return array|bool
     */
    public function getProductAttributeByKey($filterKey)
    {
        $sCompositeFilter = new CompositeFilter();
        $sCompositeFilter->setConnector("and");
        $sCompositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $sCompositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
        $sCompositeFilter->addFilter(new SearchFilter("filterKey", "eq", $filterKey));

        /** @var SProductAttributeConfigurationEntity $sProductAttributeConfiguration */
        $sProductAttributeConfiguration = null;

        $sProductAttributeConfigurations = $this->getSproductAttributeConfigurations($sCompositeFilter);
        if (!empty($sProductAttributeConfigurations)) {
            $sProductAttributeConfiguration = array_shift($sProductAttributeConfigurations);
        }

        return $sProductAttributeConfiguration;
    }

    /**
     * @param $optionId
     * @return array|bool
     */
    public function getProductAttributeOptionById($optionId)
    {
        $attributeOptionEntityType = $this->entityManager->getEntityTypeByCode("s_product_attribute_configuration_options");

        $sCompositeFilter = new CompositeFilter();
        $sCompositeFilter->setConnector("and");
        $sCompositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $sCompositeFilter->addFilter(new SearchFilter("id", "eq", $optionId));

        $sCompositeFilters = new CompositeFilterCollection();
        $sCompositeFilters->addCompositeFilter($sCompositeFilter);

        $optionEntity = $this->entityManager->getEntityByEntityTypeAndFilter($attributeOptionEntityType, $sCompositeFilters);

        return $optionEntity;
    }

    /**
     * @param $textToAutocompleteIds
     * @return bool
     */
    public function convertSProductAttributesTextToAutocomplete($textToAutocompleteIds)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $configurationOptionsArray = [];
        $dbDataQuery = "SELECT * FROM s_product_attribute_configuration_options_entity;";
        $dbData = $this->databaseContext->getAll($dbDataQuery);

        if (!empty($dbData)) {
            foreach ($dbData as $data) {
                $configurationOptionsArray[$data["configuration_attribute_id"] . "-" . $data["configuration_value"]] = true;
            }
        }

        foreach ($textToAutocompleteIds as $configurationId) {

            $dbDataQuery = "SELECT * FROM s_product_attributes_link_entity WHERE s_product_attribute_configuration_id = {$configurationId};";
            $dbData = $this->databaseContext->getAll($dbDataQuery);

            if (empty($dbData)) {
                continue;
            }

            foreach ($dbData as $attribute) {
                $attributeKey = $attribute["s_product_attribute_configuration_id"] . "-" . $attribute["attribute_value"];

//                $attributeKey = preg_replace("/('')(?![^ ])/i", '"', $attributeKey);
//                $attributeKey = preg_replace("/[ ]+/i", ' ', $attributeKey);

                if (isset($configurationOptionsArray[$attributeKey])) {
                    continue;
                }

                $insertQuery = "INSERT INTO s_product_attribute_configuration_options_entity (entity_type_id, attribute_set_id, created, modified, created_by, modified_by, entity_state_id, version, min_version, configuration_attribute_id, configuration_value, remote_id) VALUES (635, 489, NOW(), NOW(), 'system', 'system', 1, NULL, NULL, {$attribute["s_product_attribute_configuration_id"]}, '{$attribute["attribute_value"]}', NULL);";
                $this->databaseContext->executeNonQuery($insertQuery);
                $configurationOptionsArray[$attributeKey] = true;

                $dbDataQuery = "SELECT * FROM s_product_attribute_configuration_options_entity WHERE configuration_value LIKE '{$attribute["attribute_value"]}' AND configuration_attribute_id = {$attribute["s_product_attribute_configuration_id"]}";
                $insertedConfigurationOption = $this->databaseContext->getAll($dbDataQuery);

                if (empty($insertedConfigurationOption)) {
                    continue;
                }

                $dbDataQuery = "SELECT * FROM s_product_attributes_link_entity WHERE attribute_value LIKE '{$attribute["attribute_value"]}' AND s_product_attribute_configuration_id = {$attribute["s_product_attribute_configuration_id"]}";
                $sProductAttributeLinks = $this->databaseContext->getAll($dbDataQuery);

                $queryCounter = 0;
                $updateQuery = "";
                foreach ($sProductAttributeLinks as $spal) {

                    if ($queryCounter >= 1) {

                        if (!empty($updateQuery)) {
                            $this->databaseContext->executeNonQuery($updateQuery);
                        }

                        $queryCounter = 0;
                        $updateQuery = "";
                    }

                    $attributeValueKey = md5($spal["product_id"] . $insertedConfigurationOption[0]["configuration_attribute_id"] . $insertedConfigurationOption[0]["id"]);
                    $updateQuery .= "UPDATE s_product_attributes_link_entity SET configuration_option = {$insertedConfigurationOption[0]["id"]}, attribute_value_key = '{$attributeValueKey}', modified = NOW() WHERE attribute_value LIKE '{$attribute["attribute_value"]}' AND s_product_attribute_configuration_id = {$attribute["s_product_attribute_configuration_id"]} AND product_id = {$spal["product_id"]}";
                    $queryCounter++;
                }

                if (!empty($updateQuery)) {
                    $this->databaseContext->executeNonQuery($updateQuery);
                }
            }

            $dbDataQuery = "UPDATE s_product_attribute_configuration_entity SET s_product_attribute_configuration_type_id = 1 WHERE id = {$configurationId};";
            $this->databaseContext->executeNonQuery($dbDataQuery);
        }

        return true;
    }

    /**
     * @param $filterKey
     * @param null $productGroupId
     * @return bool
     */
    public function getProductAttributeValuesByKey($filterKey, $productGroupId = null)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $additionalWhere = "";
        if (!empty($productGroupId)) {
            $additionalWhere = " AND ao.id in (SELECT configuration_option FROM s_product_attributes_link_entity as spa LEFT JOIN product_entity as p ON spa.product_id = p.id LEFT JOIN product_product_group_link_entity AS ppg ON p.id = ppg.product_id WHERE ppg.product_group_id = {$productGroupId})";
        }

        $q = "SELECT ao.id AS id, ao.configuration_value AS value FROM s_product_attribute_configuration_options_entity AS ao JOIN s_product_attribute_configuration_entity AS acn ON acn.id = ao.configuration_attribute_id WHERE acn.filter_key = '{$filterKey}' {$additionalWhere};";
        $attributeValues = $this->databaseContext->getAll($q);

        return $attributeValues;
    }

    /**
     * @param $filterKey
     * @return array|bool
     */
    public function getProductAttributeAdditionalParametersByKey($filterKey)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT additional_params FROM s_product_attribute_configuration_entity WHERE filter_key = '{$filterKey}';";
        $params = $this->databaseContext->getSingleEntity($q);

        return $params["additional_params"];
    }

    /**
     * @param false $clean
     * @return false
     */
    public function checkForDuplicateProductAttributeLinks($clean = false){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM s_product_attributes_link_entity WHERE configuration_option is not null GROUP BY s_product_attribute_configuration_id,product_id HAVING count(*) > 1;";
        $data = $this->databaseContext->getAll($q);

        if(!$clean){
            return $data;
        }

        $q = "DELETE s_product_attributes_link_entity
            FROM s_product_attributes_link_entity
            INNER JOIN (
                 SELECT MAX(id) AS lastId, s_product_attribute_configuration_id, product_id
                 FROM s_product_attributes_link_entity
                 GROUP BY s_product_attribute_configuration_id,product_id
                 HAVING count(*) > 1) duplic on duplic.s_product_attribute_configuration_id = s_product_attributes_link_entity.s_product_attribute_configuration_id and duplic.product_id = s_product_attributes_link_entity.product_id
            WHERE s_product_attributes_link_entity.id < duplic.lastId;";
        $this->databaseContext->executeNonQuery($q);

        return $data;
    }

    /**
     * @return array|int[]
     */
    public function getProductsIdsForCurrentAccount(){

        $ret = Array(0);

        $accountId = null;
        if(isset($_GET["account_id"]) && !empty($_GET["account_id"])){
            $session = $this->container->get("session");
            $session->set("tender_account_id",$_GET["account_id"]);
        }

        $session = $this->container->get("session");
        if(!empty($session->get("tender_account_id"))){
            $accountId = $session->get("tender_account_id");
        }
        elseif(!empty($session->get("account"))){
            $accountId = $session->get("account")->getId();
        }

        if (!empty($accountId)) {

            if(empty($this->accountManager)){
                $this->accountManager = $this->container->get("account_manager");
            }

            /** @var AccountEntity $account */
            $account = $this->accountManager->getAccountById($accountId);
        }

        if(empty($account)){
            return implode(",",$ret);
        }

        $productIds = $account->getAccountProductIds();

        if(!empty($productIds)){
            $ret = array_keys($productIds);
        }

        return implode(",",$ret);
    }
}
