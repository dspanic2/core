<?php

namespace IntegrationBusinessBundle\Managers;

use CrmBusinessBundle\Abstracts\AbstractImportManager;
use CrmBusinessBundle\Constants\CrmConstants;

class GathImportManager extends AbstractImportManager
{
    protected $asProduct;
    protected $asSProductAttributesLink;
    protected $asTaxType;
    protected $soapClient;
    protected $soapParameters;
    protected $insertProductAttributes;
    protected $updateProductAttributes;
    protected $customProductAttributes;
    protected $gathSupplier;
    protected $changedProducts;
    protected $productGroups;
    protected $asSupplier;
    protected $asAccountTypeLink;
    protected $asSProductAttributeConfigurationOption;
    protected $asProductProductGroupLink;

    public function initialize()
    {
        parent::initialize();

        $this->asProduct = $this->entityManager->getAttributeSetByCode("product");
        $this->asSProductAttributesLink = $this->entityManager->getAttributeSetByCode("s_product_attributes_link");
        $this->asTaxType = $this->entityManager->getAttributeSetByCode('tax_type');
        $this->asSupplier = $this->entityManager->getAttributeSetByCode('supplier');
        $this->asProductProductGroupLink = $this->entityManager->getAttributeSetByCode("product_product_group_link");
        $this->asAccountTypeLink = $this->entityManager->getAttributeSetByCode('account_type_link');

        if ($_ENV["USE_LOCAL_WSDL"]) {
            $wsdlFilePath = $this->webPath . "Documents/gath.wsdl.xml";
        } else {
            $wsdlFilePath = $_ENV["GATH_IDATAMOD_WSDL"];
        }

        $this->soapClient = new \SoapClient($wsdlFilePath, array('trace' => 1, 'encoding' => ' UTF-8'));

        $this->soapParameters = [
            'vUserName' => $_ENV['GATH_WEBSHOP_USERNAME'],
            'vPassword' => $_ENV['GATH_WEBSHOP_PASSWORD']
        ];

        $this->insertProductAttributes = array_flip(
            json_decode($_ENV["GATH_INSERT_PRODUCT_ATTRIBUTES"], true) ?? array());
        $this->updateProductAttributes = array_flip(
            json_decode($_ENV["GATH_UPDATE_PRODUCT_ATTRIBUTES"], true) ?? array());
        $this->customProductAttributes = json_decode($_ENV["GATH_CUSTOM_PRODUCT_ATTRIBUTES"], true) ?? array();

        $this->productGroups = $_ENV['GATH_GET_PRODUCT_GROUPS'];
        $this->gathSupplier = $_ENV['GATH_SUPPLIER'];

        $this->setRemoteSource("gath");

        $this->changedProducts = array("product_ids" => array(), "supplier_ids" => array());
    }

    /**
     * @param $entity
     * @param $reselectedArray
     * @return mixed
     */
    protected function product_entity_filter($entity, $reselectedArray)
    {
        if (isset($entity['filter_insert']) && isset($entity['filter_insert']['tax_type_name'])) {
            $entity['tax_type_id'] =
                (int)$reselectedArray['tax_type_entity'][$entity['filter_insert']['tax_type_name']]['id'];
            unset($entity['filter_insert']);
        }
        if (isset($entity['filter_insert']) && isset($entity['filter_insert']['supplier_remote_id'])) {
            $entity['supplier_id'] =
                (int)$reselectedArray['account_entity'][$entity['filter_insert']['supplier_remote_id']]['id'];
            unset($entity['filter_insert']);
        }
        if (isset($entity['filter_update'])) {
            if (isset($entity['filter_update']['supplier_remote_id'])) {
                $entity['supplier_id'] =
                    (int)$reselectedArray['account_entity'][$entity['filter_update']['supplier_remote_id']]['id'];
            }
            if (isset($entity['filter_update']['tax_name'])) {
                $entity['tax_type_id'] =
                    (int)$reselectedArray['tax_type_entity'][$entity['filter_update']['tax_name']]['id'];
            }
            unset($entity['filter_update']);
        }

        return $entity;
    }

    /**
     * @param $entity
     * @param $reselectedArray
     * @return mixed
     */
    protected function s_route_entity_filter($entity, $reselectedArray)
    {
        if (isset($entity['filter_insert']) && isset($entity['filter_insert']['product_remote_code'])) {
            $entity['destination_id'] =
                $reselectedArray['product_entity'][$entity['filter_insert']['product_remote_code']]['id'];
            unset($entity['filter_insert']);
        }
        return $entity;
    }

    /**
     * @param $entity
     * @param $reselectedArray
     * @return mixed
     */
    protected function s_product_attributes_link_entity_filter($entity, $reselectedArray)
    {
        if (isset($entity['filter_insert'])) {
            if (isset($entity['filter_insert']['remote_product_code'])) {
                $entity['product_id'] = $reselectedArray['product_entity'][$entity['filter_insert']['remote_product_code']]['id'];
            }
            unset($entity['filter_insert']);
        }

        $entity["attribute_value_key"] = md5($entity["product_id"] .
            $entity["s_product_attribute_configuration_id"] .
            $entity["configuration_option"]);

        return $entity;
    }

    /**
     * @param $entity
     * @param $reselectedArray
     * @return mixed
     */
    protected function account_type_link_entity_filter($entity, $reselectedArray)
    {
        if (isset($entity['filter_insert'])) {
            if (isset($entity['filter_insert']['account_remote_id'])) {
                $entity['account_id'] = $reselectedArray['account_entity'][$entity['filter_insert']['account_remote_id']]['id'];
            }
            unset($entity['filter_insert']);
        }

        return $entity;
    }

    /**
     * @param $entity
     * @param $reselectedArray
     * @return mixed
     */
    protected function product_product_group_entity_filter($entity, $reselectedArray)
    {
        if (isset($entity['filter_insert'])) {
            if (isset($entity['filter_insert']['product_remote_code'])) {
                $entity['product_id'] = $reselectedArray['product_entity'][$entity['filter_insert']['product_remote_code']]['id'];
            }
            unset($entity['filter_insert']);
        }

        return $entity;
    }

    /**
     * @return array
     */
    private function getExistingGathProducts($columnKeys = [])
    {
        if (!empty($columnKeys)) {
            $columnKeys = implode(",", $columnKeys);
        } else {
            $columnKeys = "*";
        }

        $q = "
            SELECT {$columnKeys}
            FROM product_entity
            WHERE remote_source = 'gath'
            AND entity_state_id = 1;
        ";

        $data = $this->databaseContext->getAll($q);

        $ret = array();
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[trim($d['code'])] = $d;
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    private function getExistingGathTaxTypes()
    {
        $q = "
            SELECT * FROM tax_type_entity WHERE entity_state_id = 1;
        ";

        $data = $this->databaseContext->getAll($q);

        $ret = array();
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d['name']] = $d;
            }
        }

        return $ret;

    }

    /**
     * @return array
     */
    private function getExistingGathAccountTypeLinks()
    {
        $type = CrmConstants::ACCOUNT_TYPE_SUPPLIER;

        $q = "SELECT
               atl.id,
               a.remote_id AS account_remote_id,
               atl.account_type_id
            FROM account_type_link_entity atl
            JOIN account_entity a ON atl.account_id = a.id
            WHERE atl.entity_state_id = 1
            AND atl.account_type_id = '{$type}';";

        $data = $this->databaseContext->getAll($q);

        $ret = array();
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d['account_remote_id'] . '_' . $d['account_type_id']] = $d;
            }
        }

        return $ret;
    }

    /**
     * @param ...$data
     * @return array
     */
    private function getGathSProductAttributeConfigurationOptionInsertArray(...$data)
    {
        $sProductAttributeConfigurationOptionInsertArray = $this->getEntityDefaults($this->asSProductAttributeConfigurationOption);

        $sProductAttributeConfigurationOptionInsertArray['configuration_value'] = $data[0];
        $sProductAttributeConfigurationOptionInsertArray['configuration_attribute_id'] = $data[1];

        return $sProductAttributeConfigurationOptionInsertArray;
    }

    /**
     * @param null $remoteCode
     * @param null $productId
     * @param $brandName
     * @param $optionId
     * @param $brandConfigurationId
     * @return array
     */
    private function getGathSProductAttributesLinkInsertArray($remoteCode = null, $productId = null, $brandName, $optionId, $brandConfigurationId)
    {
        $sProductAttributesLinkInsertArray = $this->getEntityDefaults($this->asSProductAttributesLink);

        $sProductAttributesLinkInsertArray['configuration_option'] = $optionId;
        $sProductAttributesLinkInsertArray['attribute_value'] = $brandName;
        $sProductAttributesLinkInsertArray['s_product_attribute_configuration_id'] = $brandConfigurationId;

        if (!empty($productId)) {
            $sProductAttributesLinkInsertArray['product_id'] = $productId;
        } else {
            $sProductAttributesLinkInsertArray['filter_insert']['remote_product_code'] = $remoteCode;
        }

        return $sProductAttributesLinkInsertArray;
    }

    /**
     * @param mixed ...$data
     * @return mixed
     */
    private function getGathSRouteInsertArray(...$data)
    {
        $sRouteInsertArray = $this->getEntityDefaults($this->asSRoute);

        $sRouteInsertArray['request_url'] = $data[0];
        $sRouteInsertArray['destination_type'] = 'product';
        $sRouteInsertArray['store_id'] = $data[1];
        $sRouteInsertArray['filter_insert']['product_remote_code'] = $data[2];

        return $sRouteInsertArray;
    }

    /**
     * @param bool $id
     * @return array
     */
    private function getExistingGathSProductAttributeConfigurations($id = true)
    {
        $q = "
            SELECT id, name, filter_key, s_product_attribute_configuration_type_id
            FROM s_product_attribute_configuration_entity
            WHERE entity_state_id = 1;
        ";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        if (!empty($data)) {
            foreach ($data as $d) {
                if ($id) {
                    $ret[$d['id']] = $d;
                } else {
                    $ret[$d['filter_key']] = $d;
                }
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    private function getExistingGathSProductAttributeConfigurationOptions($id = true)
    {
        $q = "SELECT
                   id,
                    configuration_attribute_id,
                    configuration_value
            FROM s_product_attribute_configuration_options_entity
             WHERE entity_state_id = 1;";

        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            if ($id) {
                $ret[$d["id"]] = $d;
            } else {
                $ret[$d["configuration_attribute_id"] . '_' . $d['configuration_value']] = $d;
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    private function getExistingGathSProductAttributeLinks()
    {
        $q = "SELECT
                    attribute_value_key
            FROM s_product_attributes_link_entity";

        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d["attribute_value_key"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    private function getExistingGathSProductAttributeAutocompleteLinks()
    {
        $q = "SELECT l.product_id as product_id, l.s_product_attribute_configuration_id as s_product_attribute_configuration_id
            FROM s_product_attributes_link_entity as l
            INNER JOIN s_product_attribute_configuration_entity as c on l.s_product_attribute_configuration_id = c.id
            where c.s_product_attribute_configuration_type_id = 1
";

        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d["product_id"] . '_' . $d["s_product_attribute_configuration_id"]] = $d;
        }

        return $ret;
    }

    /**
     *
     * @param string $additionalAnd
     * @return array
     */
    private function getExistingGathSRoutes($additionalAnd = "")
    {
        $q = "SELECT
                request_url,
                store_id,
                destination_type
            FROM s_route_entity
            WHERE entity_state_id = 1
            {$additionalAnd};";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["store_id"] . "_" . $d["request_url"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    private function getExistingGathAccounts()
    {
        $q = "
            SELECT * FROM account_entity WHERE entity_state_id = 1 AND code IS NOT NULL and code != '';
        ";

        $data = $this->databaseContext->getAll($q);

        $ret = array();
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d['code']] = $d;
            }
        }

        return $ret;
    }

    /**
     * @return array|mixed|\SimpleXMLElement|string
     */
    public function importProducts()
    {
        $articleFields = [
            'vSearchText' => '',
            'vPoljePretrage' => 'Naziv',
            'vPartner' => '',
            'vSkladiste' => "{$_ENV["GATH_SKLADISTE"]}",
            'vSort' => 'Code',
            'vVrstaPretrage' => 'M',
            'vBrojArtikalaZaVratiti' => 10000,
            'vLikeUvijetTip' => 'O'
        ];

        $xml = $this->getGathXmlFile('GetArtiklMP', 'gath-articles-hr', $articleFields);

        if (isset($xml['error']) || $xml['error'] === true) {
            return $xml;
        }

        $productColumnKeys = [
            'id', 'code', 'price_base', 'price_retail', 'price_return', 'tax_type_id',
            'active', 'supplier_id', 'ean', 'name', 'description', 'catalog_code', 'qty', 'weight', 'is_saleable'
        ];

        /**
         * Existing arrays
         */
        $existingProducts = $this->getExistingGathProducts($productColumnKeys); // code
        $existingSRoutes = $this->getExistingGathSRoutes(" AND destination_type = 'product'"); // storeId_RequestUrl
        $existingAccountTypeLinks = $this->getExistingGathAccountTypeLinks(); // accountRemoteId_10
        $existingTaxTypes = $this->getExistingGathTaxTypes();
        $existingConfigurationsByFilterKey = $this->getExistingGathSProductAttributeConfigurations(false);
        $existingConfigurationOptionsByValue = $this->getExistingGathSProductAttributeConfigurationOptions(false);
        $existingSProductAttributeLinks = $this->getExistingGathSProductAttributeLinks();
        $existingAccounts = $this->getExistingEntity("account_entity", "remote_id", ["id", "remote_id"], "AND remote_id IS NOT NULL");
        $existingSProductAttributeAutocompleteLinks = $this->getExistingGathSProductAttributeAutocompleteLinks();
        $changedProductIds = array();
        $changedRemoteCodes = array();
        $changedSupplierRemoteIds = array();
        $changedSupplierIds = array();

        /**
         * Prepare arrays
         */
        $insertArray = [
            // tax_type_entity
            // s_product_attribute_configuration_options_entity
            // account_entity
        ];
        $insertArray2 = [
            // product_entity
            // account_type_link_entity
        ];
        $insertArray3 = [
            // s_product_attributes_link_entity
            // s_route_entity
            // product_product_group_link_entity
        ];
        $updateArray = [
            // product_entity
        ];

        foreach ($existingProducts as $existingProduct) {
            if ($existingProduct['active'] != 0) {
                $updateArray['product_entity'][$existingProduct['id']]['active'] = 0;
                $updateArray['product_entity'][$existingProduct['id']]['modified'] = 'NOW()';
                $updateArray['product_entity'][$existingProduct['id']]['date_synced'] = 'NOW()';
                $changedProductIds[$existingProduct["id"]] = $existingProduct["id"];

                if (isset($existingProduct["supplier_id"]) && !empty($existingProduct["supplier_id"])) {
                    $changedSupplierIds[$existingProduct["supplier_id"]] = $existingProduct["supplier_id"];
                }
            }
        }

        if ($_ENV["GATH_INSERT_UPDATE_BRANDS"]) {
            $brandConfigurationId = $existingConfigurationsByFilterKey['brand']['id'];
        }

        $unitConfigurationId = $existingConfigurationsByFilterKey['unit']['id'];
        $unitConfigurationTypeId = $existingConfigurationsByFilterKey['unit']['s_product_attribute_configuration_type_id'];

        $count = 0;
        foreach ($xml as $article) {

            $count++;
            echo "Product: " . $count . " / " . $xml->count() . "\n";


            $remoteCode = trim((string)$article->Code);
            $name = (string)$article->Naziv;
            $ean = trim((string)$article->Barcode);
            $catalogCode = (string)$article->KatBroj;
            $priceBase = $this->getFloatValue((string)$article->CijenaV);
            $priceRetail = $this->getFloatValue((string)$article->CijenaM);
            $qty = $this->getFloatValue((string)$article->Stanje);
            if ($qty < 0) {
                $qty = 0.0;
            }


            $isSaleable = ($qty == 0.0) ? 0 : 1;
            $categoryRemoteId = (string)$article->GrpArt;
            $categoryName = (string)$article->NazivGrupe;
            $description = (string)$article->OpisMemo;
            $brandName = (string)$article->Proizvodac;
            $weight = round($this->getFloatValue((string)$article->Masa), 4);
            $priceReturn = $this->getFloatValue((string)$article->PovNakIznos);

            if ($priceReturn > 0) {
                $priceRetail = $priceRetail - $priceReturn;
            }

            $supplierRemoteId = empty((int)$article->Dobavljac) ? NULL : (int)$article->Dobavljac;
            $supplierName = (string)trim($article->DobavNaziv);
            //Mjerna jedinica: KOM/KG/L – na webu trebaju biti mala slova
            $unit = strtolower((string)$article->MUnit);
            $unit = preg_replace('/[^a-z\-]/', '', $unit);

            $tax = $this->getFloatValue((string)$article->PDV);
            $taxName = 'PDV' . $tax;

            //$data = $this->getGathXml('GetSvojstvaArtikala', ['m', $remoteCode], true);

            if ($_ENV["GATH_INSERT_UPDATE_BRANDS"]) {
                /**
                 * Import brands
                 * Automatski se povezuju na produkte
                 */
                if (!empty($brandName)) {
                    if (!isset($existingConfigurationOptionsByValue[$brandConfigurationId . '_' . $brandName])) {

                        $insertArray['s_product_attribute_configuration_options_entity'][$brandConfigurationId . '_' . $brandName] =
                            $this->getGathSProductAttributeConfigurationOptionInsertArray($brandName, $brandConfigurationId);

                    } else {
                        $optionId = $existingConfigurationOptionsByValue[$brandConfigurationId . '_' . $brandName]['id'];

                        if (!isset($existingProducts[$remoteCode])) {
                            $insertArray3['s_product_attributes_link_entity'][] =
                                $this->getGathSProductAttributesLinkInsertArray($remoteCode, null, $brandName, $optionId, $brandConfigurationId);
                        } else {
                            $attributeValueKey = md5($existingProducts[$remoteCode]['id'] . $brandConfigurationId . $optionId);

                            if (!isset($existingSProductAttributeLinks[$attributeValueKey])) {
                                $insertArray3['s_product_attributes_link_entity'][] =
                                    $this->getGathSProductAttributesLinkInsertArray(null, $existingProducts[$remoteCode]["id"], $brandName, $optionId, $brandConfigurationId);
                            }
                        }
                    }
                }
            }

            /**
             * Import suppliers
             */
            if (!empty($supplierRemoteId) && !empty($supplierName)) {

                /**
                 * Account insert
                 */
                if (!isset($existingAccounts[$supplierRemoteId]) && !isset($insertArray["account_entity"][$supplierRemoteId])) {

                    $accountInsertArray = $this->getEntityDefaults($this->asSupplier);

                    $accountInsertArray["name"] = $supplierName;
                    $accountInsertArray["remote_id"] = $supplierRemoteId;
                    $accountInsertArray["is_active"] = 1;
                    $accountInsertArray["is_legal_entity"] = 1;

                    $insertArray["account_entity"][$supplierRemoteId] = $accountInsertArray;
                }

                /**
                 * Account type link insert
                 */
                if (!isset($existingAccountTypeLinks[$supplierRemoteId . "_" . CrmConstants::ACCOUNT_TYPE_SUPPLIER])) {

                    $accountTypeLinkInsertArray = $this->getEntityDefaults($this->asAccountTypeLink);

                    $accountTypeLinkInsertArray["account_type_id"] = CrmConstants::ACCOUNT_TYPE_SUPPLIER;

                    if (!isset($existingAccounts[$supplierRemoteId])) {
                        $accountTypeLinkInsertArray["filter_insert"]["account_remote_id"] = $supplierRemoteId;
                    } else {
                        $accountTypeLinkInsertArray["account_id"] = $existingAccounts[$supplierRemoteId]["id"];
                    }

                    $insertArray2["account_type_link_entity"][] = $accountTypeLinkInsertArray;
                }
            }

            /**
             * Importanje mjernih jedinica
             * automatski se povezuju na produkte
             */
            if (!empty($unit)) {
                if (!isset($existingConfigurationOptionsByValue[$unitConfigurationId . '_' . $unit])) {
                    $insertArray['s_product_attribute_configuration_options_entity'][$unitConfigurationId . '_' . $unit] =
                        $this->getGathSProductAttributeConfigurationOptionInsertArray($unit, $unitConfigurationId);
                } else {
                    $optionId = $existingConfigurationOptionsByValue[$unitConfigurationId . '_' . $unit]['id'];

                    if (!isset($existingProducts[$remoteCode])) {
                        $insertArray3['s_product_attributes_link_entity'][] =
                            $this->getGathSProductAttributesLinkInsertArray($remoteCode, null, $unit, $optionId, $unitConfigurationId);
                    } else {
                        $productId = $existingProducts[$remoteCode]['id'];
                        $attributeValueKey = md5($productId . $unitConfigurationId . $optionId);

                        $autocompleteLinkExists = false;
                        if ($unitConfigurationTypeId == 1 && isset($existingSProductAttributeAutocompleteLinks[$productId . '_' . $unitConfigurationId])) {
                            $autocompleteLinkExists = true;
                        }

                        if (!$autocompleteLinkExists && !isset($existingSProductAttributeLinks[$attributeValueKey])) {
                            $insertArray3['s_product_attributes_link_entity'][] =
                                $this->getGathSProductAttributesLinkInsertArray(null, $productId, $unit, $optionId, $unitConfigurationId);
                        }
                    }
                }
            }

            /**
             * Import tax types
             */
            if (!isset($existingTaxTypes[$taxName]) && !isset($insertArray['tax_type_entity'][$taxName])) {
                $taxTypeInsertArray = $this->getEntityDefaults($this->asTaxType);

                $taxTypeInsertArray['name'] = $taxName;
                $taxTypeInsertArray['percent'] = $tax;

                $insertArray['tax_type_entity'][$taxName] = $taxTypeInsertArray;
            }

            /**
             * Prepare JSON arrays
             */
            $nameArray = [];
            $descriptionArray = [];
            $urlArray = [];
            $metaKeywordsArray = [];
            $showOnStoreArray = [];

            foreach ($this->getStores() as $storeId) {
                $nameArray[$storeId] = $name;
                $descriptionArray[$storeId] = $description;
                $metaKeywordsArray[$storeId] = '';
                $showOnStoreArray[$storeId] = 1;

                /**
                 * Add routes
                 */
                if (!isset($existingProducts[$remoteCode])) {
                    $i = 1;
                    $url = $key = $this->routeManager->prepareUrl($name);
                    while (isset($existingSRoutes[$storeId . '_' . $url]) || isset($insertArray3['s_route_entity'][$storeId . '_' . $url])) {
                        $url = $key . '-' . $i++;
                    }

                    $insertArray3['s_route_entity'][$storeId . '_' . $url] = $this->getGathSRouteInsertArray($url, $storeId, $remoteCode);

                    $urlArray[$storeId] = $url;
                }
            }

            /**
             * JSON arrays
             */
            $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);
            $descriptionJson = json_encode($descriptionArray, JSON_UNESCAPED_UNICODE);
            $metaKeywordsJson = json_encode($metaKeywordsArray, JSON_UNESCAPED_UNICODE);
            $showOnStoreJson = json_encode($showOnStoreArray, JSON_UNESCAPED_UNICODE);
            $urlJson = json_encode($urlArray, JSON_UNESCAPED_UNICODE);

            /**
             * Insert products
             */

            if (!isset($existingProducts[$remoteCode])) {
                $productInsertArray = $this->getEntityDefaults($this->asProduct);

                $productInsertArray = $this->addToProduct($productInsertArray, 'date_synced', 'NOW()');
                $productInsertArray = $this->addToProduct($productInsertArray, 'code', $remoteCode);
                $productInsertArray = $this->addToProduct($productInsertArray, 'remote_source', $this->getRemoteSource());
                $productInsertArray = $this->addToProduct($productInsertArray, 'catalog_code', $catalogCode);
                $productInsertArray = $this->addToProduct($productInsertArray, 'price_return', $priceReturn);
                $productInsertArray = $this->addToProduct($productInsertArray, 'name', $nameJson);
                $productInsertArray = $this->addToProduct($productInsertArray, 'meta_title', $nameJson);
                $productInsertArray = $this->addToProduct($productInsertArray, 'meta_description', $nameJson);
                $productInsertArray = $this->addToProduct($productInsertArray, 'description', $descriptionJson);
                $productInsertArray = $this->addToProduct($productInsertArray, 'price_base', $priceBase);
                $productInsertArray = $this->addToProduct($productInsertArray, 'price_retail', $priceRetail);
                $productInsertArray = $this->addToProduct($productInsertArray, 'currency_id', $_ENV["DEFAULT_CURRENCY"]);
                $productInsertArray = $this->addToProduct($productInsertArray, 'product_type_id', 1);
                $productInsertArray = $this->addToProduct($productInsertArray, 'manufacturer_remote_id', NULL);
                $productInsertArray = $this->addToProduct($productInsertArray, 'ord', 100);
                $productInsertArray = $this->addToProduct($productInsertArray, 'ean', $ean);
                $productInsertArray = $this->addToProduct($productInsertArray, 'active', 1);
                $productInsertArray = $this->addToProduct($productInsertArray, 'is_visible', 1);
                $productInsertArray = $this->addToProduct($productInsertArray, 'qty_step', 1);
                $productInsertArray = $this->addToProduct($productInsertArray, 'qty', $qty);
                $productInsertArray = $this->addToProduct($productInsertArray, 'is_saleable', $isSaleable);
                $productInsertArray = $this->addToProduct($productInsertArray, 'auto_generate_url', 1);
                $productInsertArray = $this->addToProduct($productInsertArray, 'template_type_id', 5);
                $productInsertArray = $this->addToProduct($productInsertArray, 'show_on_store', $showOnStoreJson);
                $productInsertArray = $this->addToProduct($productInsertArray, 'url', $urlJson);
                $productInsertArray = $this->addToProduct($productInsertArray, 'keep_url', 1);
                $productInsertArray = $this->addToProduct($productInsertArray, 'content_changed', 1);
                $productInsertArray = $this->addToProduct($productInsertArray, 'meta_keywords', $metaKeywordsJson);
                $productInsertArray = $this->addToProduct($productInsertArray, 'show_on_homepage', 0);
                $productInsertArray = $this->addToProduct($productInsertArray, 'supplier_id', $this->gathSupplier);
                $productInsertArray = $this->addToProduct($productInsertArray, 'weight', $weight);
//                $productInsertArray = $this->addToProduct($productInsertArray, 'remote_brand_name', $brandName);

                if (isset($this->insertProductAttributes["tax_type_id"])) {
                    if (!isset($existingTaxTypes[$taxName])) {
                        $productInsertArray['filter_insert']['tax_type_name'] = $taxName;
                    } else {
                        $productInsertArray["tax_type_id"] = $existingTaxTypes[$taxName]['id'];
                    }
                }

                if (isset($this->insertProductAttributes["supplier_id"])) {
                    if (!empty($supplierRemoteId) && !empty($supplierName)) {
                        if (isset($existingAccounts[$supplierRemoteId])) {
                            $productInsertArray["supplier_id"] = $existingAccounts[$supplierRemoteId]["id"];
                            $changedSupplierIds[$existingAccounts[$supplierRemoteId]['id']] = $existingAccounts[$supplierRemoteId]['id'];
                        } else {
                            $productInsertArray['filter_insert']['supplier_remote_id'] = $supplierRemoteId;
                            $changedSupplierRemoteIds[$supplierRemoteId] = $supplierRemoteId;
                        }
                    } else {
                        $productInsertArray["supplier_id"] = NULL;
                    }
                }

                if (!empty($this->customProductAttributes)) {
                    foreach ($this->customProductAttributes as $customProductAttribute => $customProductAttributeValue) {
                        $productInsertArray[$customProductAttribute] = $customProductAttributeValue;
                    }
                }

                $insertArray2['product_entity'][$remoteCode] = $productInsertArray;

                $changedRemoteCodes[$remoteCode] = $remoteCode;
            } else {
                $productId = $existingProducts[$remoteCode]['id'];
                unset($updateArray['product_entity'][$productId]);
                unset($changedProductIds[$productId]);

                if (isset($existingAccounts[$supplierRemoteId])) {
                    $supplierId = $existingAccounts[$supplierRemoteId]["id"];
                    unset($changedSupplierIds[$supplierId]);
                }

                $productUpdateArray = [];

                /**
                 * Update name
                 */
                if (isset($this->updateProductAttributes['name']) &&
                    $nameArray != json_decode($existingProducts[$remoteCode]['name'], true)) {
                    $productUpdateArray['name'] = $nameJson;
                    $productUpdateArray['meta_title'] = $nameJson;
                    $productUpdateArray['meta_description'] = $nameJson;
                    $productUpdateArray['content_changed'] = 1;
                }

                /**
                 * Update description
                 */
                if (isset($this->updateProductAttributes['description']) &&
                    $descriptionArray != json_decode($existingProducts[$remoteCode]['description'], true)) {
                    $productUpdateArray['description'] = $descriptionJson;
                    $productUpdateArray['content_changed'] = 1;
                }

                if (isset($this->updateProductAttributes['supplier_id']) && !empty($supplierRemoteId) && !empty($supplierName)) {
                    if (isset($existingAccounts[$supplierRemoteId])) {
                        if ($existingAccounts[$supplierRemoteId]['id'] != $existingProducts[$remoteCode]["supplier_id"]) {
                            $productUpdateArray['supplier_id'] = $existingAccounts[$supplierRemoteId]['id'];
                            $supplierChanged = true;
                        }
                    } else {
                        $productUpdateArray['filter_update']['supplier_remote_id'] = $supplierRemoteId;
                        $supplierChanged = true;
                    }
                }

                if (isset($this->updateProductAttributes["tax_type_id"]) && !empty($taxName)) {
                    if (isset($existingTaxTypes[$taxName])) {
                        if ($existingTaxTypes[$taxName]["id"] != $existingProducts[$remoteCode]["tax_type_id"]) {
                            $productUpdateArray['tax_type_id'] = $existingTaxTypes[$taxName]["id"];
                        }
                    } else {
                        $productUpdateArray['filter_update']['tax_name'] = $taxName;
                    }
                }

                /**
                 * Update ean
                 */
                if (isset($this->updateProductAttributes['ean']) && $ean != $existingProducts[$remoteCode]['ean']) {
                    $productUpdateArray['ean'] = $ean;
                    $productUpdateArray['content_changed'] = 1;
                }

                if (isset($this->updateProductAttributes['qty']) && (string)$qty != (string)floatval($existingProducts[$remoteCode]['qty'])) {
                    $productUpdateArray['qty'] = $qty;
                }

                if (isset($this->updateProductAttributes['catalog_code']) && $catalogCode != $existingProducts[$remoteCode]['catalog_code']) {
                    $productUpdateArray['catalog_code'] = $catalogCode;
                    $productUpdateArray['content_changed'] = 1;
                }

                /**
                 * Update active
                 */
                if (isset($this->updateProductAttributes['active']) && $existingProducts[$remoteCode]['active'] != 1) {
                    $productUpdateArray['active'] = 1;
                }

                /**
                 * Update price
                 */
                if (isset($this->updateProductAttributes['price_base']) && (string)$priceBase != (string)floatval($existingProducts[$remoteCode]['price_base'])) {
                    $productUpdateArray['price_base'] = $priceBase;
                }

                if (isset($this->updateProductAttributes['price_retail']) && (string)$priceRetail != (string)floatval($existingProducts[$remoteCode]['price_retail'])) {
                    $productUpdateArray['price_retail'] = $priceRetail;
                }

                if (isset($this->updateProductAttributes['price_return']) && (string)$priceReturn != (string)floatval($existingProducts[$remoteCode]['price_return'])) {
                    $productUpdateArray['price_return'] = $priceReturn;
                }

                if (isset($this->updateProductAttributes['weight']) && $weight != (string)floatval($existingProducts[$remoteCode]['weight'])) {
                    $productUpdateArray['weight'] = $weight;
                }

                if (!empty($productUpdateArray)) {
                    $productUpdateArray['modified'] = 'NOW()';
                    $productUpdateArray['date_synced'] = 'NOW()';
                    $updateArray['product_entity'][$productId] = $productUpdateArray;
                    if (!empty(array_intersect(array_keys($productUpdateArray), $this->triggerChangesArray))) {
                        $productChanged = true;
                    }
                }


                if (isset($productChanged) && $productChanged) {
                    $changedProductIds[$productId] = $productId;
                }

                if (isset($supplierChanged) && $supplierChanged) {
                    if (isset($supplierId) && !empty($supplierId)) {
                        $changedSupplierIds[$supplierId] = $supplierId;
                    } else {
                        if (!empty($supplierRemoteId) && !empty($supplierName)) {
                            $changedSupplierRemoteIds[$supplierRemoteId] = $supplierRemoteId;
                        }
                    }
                }
            }
        }

        $insertQuery = $this->getInsertQuery($insertArray);
        if (!empty($insertQuery)) {
            echo "INSERT QUERY\n";
            $this->logQueryString($insertQuery);
            $this->databaseContext->executeNonQuery($insertQuery);
        }

        /**
         * Reselect tax types, accounts
         */
        $reselectedArray['tax_type_entity'] = $this->getExistingGathTaxTypes();
        $reselectedArray['account_entity'] = $this->getExistingEntity("account_entity", "remote_id", ["id", "remote_id"], "AND remote_id IS NOT NULL");

        $insertArray2 = $this->filterImportArray($insertArray2, $reselectedArray);
        $insertQuery2 = $this->getInsertQuery($insertArray2);
        if (!empty($insertQuery2)) {
            echo "INSERT QUERY 2\n";
            $this->logQueryString($insertQuery2);
            $this->databaseContext->executeNonQuery($insertQuery2);
        }

        $updateArray = $this->filterImportArray($updateArray, $reselectedArray);
        $updateQuery = $this->getUpdateQuery($updateArray);
        if (!empty($updateQuery)) {
            echo "UPDATE QUERY\n";
            $this->logQueryString($updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }

        unset($reselectedArray);


        /**
         * Reselect products and categories
         */
        $reselectedArray['product_entity'] = $this->getExistingGathProducts();
        $reselectedArray['account_entity'] = $this->getExistingGathAccounts();

        $insertArray3 = $this->filterImportArray($insertArray3, $reselectedArray);
        $insertQuery3 = $this->getInsertQuery($insertArray3);
        if (!empty($insertQuery3)) {
            echo "INSERT QUERY 3\n";
            $this->logQueryString($insertQuery3);
            $this->databaseContext->executeNonQuery($insertQuery3);
        }

        $ret["product_ids"] = array_merge($this->getChangedIds($changedProductIds),
            $this->getChangedIds($changedRemoteCodes, "id", $reselectedArray["product_entity"]));
        $ret["supplier_ids"] = array_merge($this->getChangedIds($changedSupplierIds, "supplier_id"),
            $this->getChangedIds($changedSupplierRemoteIds, "supplier_id", $reselectedArray["account_entity"]));

        unset($reselectedArray);

        return $ret;
    }

    /**
     * @param $changedIds
     * @param $key
     * @param null $existingArray
     * @return array
     */
    private function getChangedIds($changedIds, $key = 'id', $existingArray = null)
    {
        $ret = array();

        foreach ($changedIds as $id) {
            if (!empty($existingArray)) {
                if (!isset($existingArray[$remoteId = $id])) {
                    continue;
                }
                if (!empty($existingArray[$remoteId][$key])) {
                    $id = $existingArray[$remoteId][$key];
                }
            }

            $ret[] = $id;
        }

        return $ret;
    }

    /**
     * @param $fun
     * @param $name
     * @param array $params
     * @param bool $enableSoapParams
     * @return array|false|mixed|\SimpleXMLElement|string|null
     */
    private function getGathXmlFile($fun, $name, array $params = [], bool $enableSoapParams = true)
    {
        $targetFile = $this->webPath . 'Documents/import/' . $name . '.xml';

        $parameters = $this->getParameters($params, $enableSoapParams);

        try {
            $response = $this->soapClient->$fun(...array_values($parameters));
        } catch (\SoapFault $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }

        if ($response == '%UNKNOWN%') {
            return [
                'error' => true,
                'message' => 'Unknown input'
            ];
        }

        $bytes = $this->helperManager->saveRawDataToFile($response, $targetFile);
        if (empty($bytes)) {
            return [
                'error' => true,
                'message' => 'Saving file failed'
            ];
        }

        $content = file_get_contents($targetFile);
        $content = str_replace('encoding="windows-1250"', 'encoding="utf-8"', $content);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            $e = [];
            foreach (libxml_get_errors() as $error) {
                $e[] = $error->message;
            }
            libxml_clear_errors();
            return [
                'error' => true,
                'message' => 'Error: ' . implode(', ', $e)
            ];
        }

        return $xml;
    }

    /**
     * @param $params
     * @param $enableSoapParams
     * @return array
     */
    private function getParameters($params, $enableSoapParams)
    {
        $parameters = [];
        if ($enableSoapParams) {
            $parameters = $this->soapParameters;
        }

        if (!empty($params)) {
            $parameters = array_merge($parameters, $params);
        }

        return $parameters;
    }

    /**
     * @param $fun
     * @param array $params
     * @param bool $enableSoapParams
     * @return array|bool|mixed
     */
    public function getGathXml($fun, array $params = [], bool $enableSoapParams = true)
    {
        $parameters = $this->getParameters($params, $enableSoapParams);

        try {
            $response = $this->soapClient->$fun(...array_values($parameters));
        } catch (\SoapFault $e) {
            return array("error" => true, "message" => $e->getMessage(), "data" => null);
        }

        if ($response == 'OK') {
            return array("error" => false, "message" => "ok", "data" => null);
        }

        $errorMessages = [
            // naziv_errora => error_message
            "KorisnikPostoji" => array("error" => false, "message" => "KorisnikPostoji"),
            "Greška krivi User i pass" => array("error" => true, "message" => "Greška krivi User i pass"),
            "Greška! Neispravana šifra jedinice!" => array("error" => true, "message" => "Greška! Neispravana šifra jedinice!"),
            "Kartica već postoji" => array("error" => true, "message" => "Kartica već postoji"),
            "Nepostojeća loyalty kartica" => array("error" => true, "message" => "Nepostojeća loyalty kartica"),
            "Nepostojeća ili neaktivna loyalty kartica" => array("error" => true, "message" => "Nepostojeća ili neaktivna loyalty kartica"),
            "Artikl ne postoji ili je neaktivan" => array("error" => true, "message" => "Artikl ne postoji ili je neaktivan"),
            "Nema artikala u košarici" => array("error" => true, "message" => "Nema artikala u košarici"), // ovaj se ne koristi više
            "Ne postoji CID za korisnika" => array("error" => true, "message" => "Ne postoji CID za korisnika"),
            "Nije pronađen niti jedan artikl u šifrarniku artikala" => array("error" => true, "message" => "Nije pronađen niti jedan artikl u šifrarniku artikala"),
            "Greška kod čitanja xml-a" => array("error" => true, "message" => "Greška kod čitanja xml-a"),
            "Loyalty kartica ne pripada ovom korisniku" => array("error" => true, "message" => "Loyalty kartica ne pripada ovom korisniku"),
            "Korisnik nema karticu" => array("error" => true, "message" => "Korisnik nema karticu")
        ];

        if (in_array($response, array_keys($errorMessages))) {
            return array("error" => $errorMessages[$response]["error"], "message" => $errorMessages[$response]["message"], "data" => null);
        }

        /**
         * ovo je za utf-8
         */
        $response = str_replace('<?xml version="1.0" encoding="windows-1250"?>', '<?xml version="1.0" encoding="UTF-8"?>', $response);
        $response = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $response);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            $e = [];
            foreach (libxml_get_errors() as $error) {
                $e[] = $error->message;
            }

            libxml_clear_errors();

            return array("error" => true, "message" => 'Error: ' . implode(', ', $e), "data" => null);
        }

        if (isset($xml->Message) && in_array(strval($xml->Message), array_keys($errorMessages))) {
            return array("error" => $errorMessages[strval($xml->Message)]["error"], "message" => $errorMessages[strval($xml->Message)]["message"], "data" => null);
        }

        $r = json_decode(json_encode($xml), TRUE);

        if (isset($r[0]) && $r[0] === "\n") {
            return array("error" => true, "message" => "Empty response", "data" => null);
        }

        return array("error" => false, "message" => "ok", "data" => $r);
    }

    /**
     * @param $string
     * @return string
     */
    private function getPhoneNumber($string)
    {
        preg_match_all('!\d+!', $string, $matches);
        return implode($matches[0]);
    }

    /**
     * @param $name
     * @return array|string|string[]
     */
    private function getPartnerName($name)
    {
        if ($n = strpos($name, 'd_o_o')) {
            $name = substr_replace($name, 'd.o.o.', $n, 7);
        }

        return trim(str_replace('_', ' ', $name));
    }

    /**
     * @param $value
     * @return float
     */
    private function getFloatValue($value)
    {
        $value = str_replace(",", ".", $value);
        $value = preg_replace('/\.(?=.*\.)/', '', $value);

        return floatval($value);
    }

    /**
     * @return array
     */
    private function getExistingEntity($entity, $sortKey, $columnKeys, $additionalWhere = "")
    {
        if (!empty($columnKeys)) {
            $columnKeys = implode(",", $columnKeys);
        } else {
            $columnKeys = "*";
        }

        $q = "SELECT
                {$columnKeys}
            FROM {$entity}
            WHERE entity_state_id = 1
                {$additionalWhere};";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    /**
     * @param $productInsertArray
     * @param $attribute
     * @param $value
     * @return mixed
     */
    private function addToProduct($productInsertArray, $attribute, $value)
    {
        if (isset($this->insertProductAttributes[$attribute])) {
            $productInsertArray[$attribute] = $value;
        }

        return $productInsertArray;
    }
}
