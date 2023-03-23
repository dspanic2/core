<?php

namespace IntegrationBusinessBundle\Managers;

class TfoImportManager extends DefaultIntegrationImportManager
{
    protected $client;
    protected $sessionKey;
    protected $dictionary;
    protected $tfoSupplier;
    protected $asIntTfoCategory;
    protected $asIntTfoCategoryProductLink;
    protected $asDeviceType;
    protected $asDeviceBrand;
    protected $asDeviceModel;
    protected $asIntTfoAttribute;
    protected $asIntTfoAttributeValue;
    protected $changedProducts;
    protected $currencyExchange;

    protected $asProductProductGroupLink;
    protected $asSRoute;
    protected $asSProductAttributesLink;
    protected $asProduct;
    protected $asProductImage;

    public function initialize()
    {
        parent::initialize();

        /**
         * ATTRIBUTE SETS
         */
        $this->asIntTfoCategory = $this->entityManager->getAttributeSetByCode('int_tfo_category');
        $this->asIntTfoCategoryProductLink = $this->entityManager->getAttributeSetByCode('int_tfo_category_product_link');
        $this->asDeviceType = $this->entityManager->getAttributeSetByCode('int_tfo_device_type');
        $this->asDeviceBrand = $this->entityManager->getAttributeSetByCode('int_tfo_device_brand');
        $this->asDeviceModel = $this->entityManager->getAttributeSetByCode('int_tfo_device_model');
        $this->asIntTfoAttribute = $this->entityManager->getAttributeSetByCode('int_tfo_attribute');
        $this->asIntTfoAttributeValue = $this->entityManager->getAttributeSetByCode('int_tfo_attribute_values');

        $this->asProductProductGroupLink = $this->entityManager->getAttributeSetByCode("product_product_group_link");
        $this->asSRoute = $this->entityManager->getAttributeSetByCode("s_route");
        $this->asSProductAttributesLink = $this->entityManager->getAttributeSetByCode("s_product_attributes_link");
        $this->asProduct = $this->entityManager->getAttributeSetByCode("product");
        $this->asProductImage = $this->entityManager->getAttributeSetByCode("product_images");

        /**
         * Product attributes
         */
        $this->insertProductAttributes = array_flip(json_decode($_ENV["TFO_INSERT_PRODUCT_ATTRIBUTES"], true) ?? []);
        $this->updateProductAttributes = array_flip(json_decode($_ENV["TFO_UPDATE_PRODUCT_ATTRIBUTES"], true) ?? []);
        $this->customProductAttributes = json_decode($_ENV["TFO_CUSTOM_PRODUCT_ATTRIBUTES"], true) ?? [];

        $this->tfoSupplier = $_ENV['TFO_SUPPLIER_ID'];

        try {
            $this->client = new \SoapClient($_ENV['TFO_WSDL_URL']);
        } catch (\Exception $e) {
            echo $e->getMessage();
            return false;
        }

        /**
         * Ovdje se postavlja session_key
         * u funkciji se postavlja property
         */
        $authResponse = $this->auth();
        if ($authResponse['error'] === true) {
            return $authResponse;
        }

        /**
         * dictionaries
         */
        $this->dictionary = [
            'lang' => $_ENV['TFO_LANGUAGE'],
            'curr' => $_ENV['TFO_CURRENCY']
        ];

        $this->changedProducts = array("product_ids" => [], "supplier_ids" => []);

        $this->setRemoteSource('tfo');

        return true;
    }

    /**
     * @return array|bool
     * metoda za autorizacuju korisnika. DohvaÄ‡a session_key
     */
    protected function auth()
    {
        $res = array();
        $res["message"] = null;
        $res["error"] = true;
        $res["error_code"] = null;

        $parameters = array();
        $parameters["Token"] = $_ENV['TFO_TOKEN'];
        $parameters["EMail"] = $_ENV['TFO_EMAIL'];
        $parameters["Passwd"] = $_ENV['TFO_PASSWORD'];
        $parameters["RefreshPrice"] = false;

        /**
         * methods
         */
        $doLogin = 'doLogin';
        $doNoop = 'doNoop';
        $getSessionSecondsToEnd = 'getSessionSecondsToEnd';

        /**
         * Ako je session_key prop prazan, napravi novi
         */
        if (empty($this->sessionKey)) {

            try {
                $response = $this->client->$doLogin($parameters);

            } catch (\Exception $e) {
                $res['message'] = $e->getMessage();
                return $res;
            }

            if (empty($response->doLoginResult->SessionKey)) {
                $res['message'] = $response->doLoginResult->ErrorMessage;
                $res['error_code'] = $response->doLoginResult->ErrorCode;

                return $res;
            }

            $this->sessionKey = $response->doLoginResult->SessionKey;

        } else {

            /**
             * ako nije prazno
             */
            $sessionParameter = array();
            $sessionParameter["SessionKey"] = $this->sessionKey;

            try {
                $responseSec = $this->client->$getSessionSecondsToEnd($sessionParameter);
            } catch (\Exception $e) {
                $res['message'] = $e->getMessage();
                return $res;
            }

            if (strtolower($responseSec->getSessionSecondsToEndResult->ErrorCode) != 'ok') {
                $res['message'] = $responseSec->getSessionSecondsToEndResult->ErrorMessage;
                $res['error_code'] = $responseSec->getSessionSecondsToEndResult->ErrorCode;
                return $res;
            }

            if ($responseSec->getSessionSecondsToEndResult->SeccondsToEnd <= 100) {
                try {
                    $responseNoop = $this->client->$doNoop($sessionParameter);
                } catch (\Exception $e) {
                    $res['message'] = $e->getMessage();
                    return $res;
                }

                if (strtolower($responseNoop->doNoopResponse->ErrorCode) != 'ok') {
                    $res['message'] = $responseNoop->doNoopResponse->ErrorMessage;
                    $res['error_code'] = $responseNoop->doNoopResponse->ErrorCode;
                    return $res;
                }
            }
        }

        $res["error"] = false;
        return $res;
    }

    /**
     * @param $function
     * @param array $parameters
     * @param null $filename
     * @return array|bool|null
     */
    private function api($function, array $parameters = [], $filename = null)
    {
        $apiRes = array();
        $apiRes["message"] = null;
        $apiRes["error"] = true;
        $apiRes["error_code"] = null;
        $apiRes["result"] = null;

        $authResponse = $this->auth();
        if ($authResponse['error'] === true) {
            return $authResponse;
        }

        $finalParameters = array_merge(["SessionKey" => $this->sessionKey], $parameters);
        $property = $function . 'Result';
        $response = $this->client->$function($finalParameters)->$property;

        if ($function == 'getCategoryList') {
            if (isset($response->Cat) && isset($response->Cat->rowCategoryList)) {
                $apiRes["result"] = $response->Cat->rowCategoryList;
            }
        } else if ($function == 'getProductList') {
            if (isset($response->Products) && isset($response->Products->rowProductList)) {
                if (is_object($response->Products->rowProductList)) {
                    $apiRes["result"][] = $response->Products->rowProductList;
                } else {
                    $apiRes["result"] = $response->Products->rowProductList;
                }
            }
        } else if ($function == 'getProductInfo') {
            if (isset($response->Products) && isset($response->Products->rowProductInfo)) {
                $apiRes["result"] = $response->Products->rowProductInfo;
            }
        }

        if (!empty($filename) && !empty($apiRes["result"])) {
            $targetPath = $this->getWebPath() . 'Documents/import/' . $this->getRemoteSource() . '-' . $filename . '.json';

            $bytes = $this->helperManager->saveRawDataToFile(json_encode($apiRes["result"], true), $targetPath);
            if (empty($bytes)) {
                $apiRes['message'] = 'Saving file failed';
                $apiRes['error_code'] = 'FileError';
                return $apiRes;
            }
        }

        $apiRes["error"] = false;
        return $apiRes;
    }

    private function getExistingTfoProducts($sortKey, $columns = [], $additionalAnd = "")
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT {$selectColumns}
			FROM product_entity
            WHERE entity_state_id = 1

            {$additionalAnd}
        ;";

        $ret = array();

        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    private function getExistingTfoSRoutes($additionalAnd = "")
    {
        $q = "SELECT
                request_url,
                store_id,
                destination_type
            FROM s_route_entity
            WHERE entity_state_id = 1
            {$additionalAnd};";

        $ret = array();

        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d["store_id"] . "_" . $d["request_url"]] = $d;
        }

        return $ret;
    }

    private function getExistingIntTfoCategoryProductLinks()
    {
        $q = "
                SELECT p.remote_id as remote_id,
                         c.code as code
                FROM int_tfo_category_product_link_entity tl
                    INNER JOIN product_entity p ON tl.product_id = p.id
                    INNER JOIN int_tfo_category_entity c ON tl.int_tfo_category_id = c.id
                WHERE p.remote_source = 'tfo'
                AND tl.entity_state_id = 1
        ";

        $ret = [];

        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d['code'] . '_' . $d['remote_id']] = $d;
        }

        return $ret;
    }

    private function getExistingTfoProductProductGroupLinks()
    {
        $q = "SELECT
                   ppg.product_group_id as product_group_id,
                   p.remote_id as remote_id
                FROM product_product_group_link_entity ppg INNER JOIN product_entity p
                ON ppg.product_id = p.id
                   WHERE p.remote_id IS NOT NULL
                    AND p.remote_source = '{$this->getRemoteSource()}'";

        $ret = array();
        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d["remote_id"] . "_" . $d["product_group_id"]] = $d;
        }

        return $ret;
    }

    private function getExistingTfoImages()
    {
        $q = "SELECT
                i.product_id AS product_id,
                i.filename AS filename
            FROM product_images_entity i
		    JOIN product_entity p
			ON i.product_id = p.id
			WHERE p.remote_source = '{$this->getRemoteSource()}';";

        $ret = [];

        foreach ($this->databaseContext->getAll($q) as $datum) {
            $ret[$datum['product_id']][$datum['filename']] = $datum;
        }

        return $ret;
    }

    private function getExistingTfoDeviceTypes()
    {
        $q = "SELECT id, name FROM int_tfo_device_type_entity WHERE entity_state_id = 1;";

        $ret = [];

        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d['name']] = $d;
        }

        return $ret;
    }

    private function getExistingTfoDeviceBrands()
    {
        $q = "SELECT
            b.id AS brand_id,
            t.id AS type_id,
            b.NAME AS brand_name,
            t.NAME AS type_name
        FROM
            int_tfo_device_brand_entity b
            INNER JOIN int_tfo_device_type_entity t ON b.type_id = t.id;
        ";

        $ret = [];

        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d['type_name'] . '_' . $d['brand_name']] = $d;
        }

        return $ret;
    }

    private function getExistingTfoDeviceModels()
    {
        $q = "SELECT
            b.id AS brand_id,
            t.id AS type_id,
            m.id as model_id,
            b.name AS brand_name,
            t.name AS type_name,
            m.name AS model_name
        FROM
            int_tfo_device_model_entity m
            INNER JOIN int_tfo_device_brand_entity b ON m.brand_id = b.id
            INNER JOIN int_tfo_device_type_entity t ON b.type_id = t.id;
        ";

        $ret = [];

        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d['type_name'] . '_' . $d['brand_name'] . '_' . $d['model_name']] = $d;
        }

        return $ret;
    }

    private function getExistingIntTfoAttributes()
    {
        $q = "SELECT id, LOWER(name) AS name, configuration_id
            FROM int_tfo_attribute_entity WHERE entity_state_id = 1;
        ";

        $ret = [];

        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d['name']] = $d;
        }

        return $ret;
    }

    private function getExistingIntTfoAttributeValues()
    {
        $q = "
            SELECT
                    v.id as id,
                    LOWER(a.name) as attribute_name,
                    LOWER(v.name) as option_name,
                    v.option_id as option_id
         FROM int_tfo_attribute_values_entity v INNER JOIN int_tfo_attribute_entity a
	        ON v.int_tfo_attribute_id = a.id
        WHERE v.entity_state_id = 1;
        ";

        $ret = array();

        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d['attribute_name'] . '_' . $d["option_name"]] = $d;
        }

        return $ret;
    }

    private function getExistingTfoSProductAttributeConfigurations($sortKey = "id", $columns = [])
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_filter($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "
            SELECT {$selectColumns}
         FROM s_product_attribute_configuration_entity
        WHERE entity_state_id = 1;
        ";

        $ret = array();

        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    private function getExistingTfoSProductAttributesLinks($sortKey = "", $columns = [])
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_filter($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT
                    {$selectColumns}
			FROM s_product_attributes_link_entity;";

        $ret = array();
        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    protected function getExistingTfoSProductAttributeConfigurationOptions($sortKey = "id", $columns = [], $uniqueCode = [], $additinalAnd = "")
    {
        $selectColumns = "*";
        if (!empty($columns)) {

            if (empty($uniqueCode)) {
                $columns[] = $sortKey;
                $additinalAnd = "AND {$sortKey} IS NOT NULL" . $additinalAnd;
            } else {
                $columns = array_merge($columns, $uniqueCode);
            }

            $columns = array_filter($columns);

            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT {$selectColumns} FROM s_product_attribute_configuration_options_entity WHERE entity_state_id = 1 {$additinalAnd};";

        $ret = array();
        foreach ($this->databaseContext->getAll($q) as $d) {
            if (!empty($uniqueCode)) {
                $d[$uniqueCode[1]] = mb_strtolower($d[$uniqueCode[1]]);
                $ret[$d[$uniqueCode[0]] . "_" . $d[$uniqueCode[1]]] = $d;
            } else {
                $ret[$d[$sortKey]] = $d;
            }
        }

        return $ret;
    }

    private function getFloatValue($value)
    {
        $value = str_replace(",", ".", $value);
        $value = preg_replace('/\.(?=.*\.)/', '', $value);

        return floatval($value);
    }

    private function getIntTfoCategoryProductLinkInsertArray($categoryId, $remoteId, $existingProducts)
    {
        $intTfoCategoryProductLinkInsertArray = $this->getEntityDefaults($this->asIntTfoCategoryProductLink);

        $intTfoCategoryProductLinkInsertArray['int_tfo_category_id'] = $categoryId;

        if (!isset($existingProducts[$remoteId])) {
            $intTfoCategoryProductLinkInsertArray['filter_insert']['product_remote_id'] = $remoteId;
        } else {
            $intTfoCategoryProductLinkInsertArray['product_id'] = $existingProducts[$remoteId]['id'];
        }

        return $intTfoCategoryProductLinkInsertArray;
    }

    private function getTfoProductProductGroupInsertArray($productGroupId, $existingProducts, $remoteId)
    {
        $productProductGroupLinkInsertArray = $this->getEntityDefaults($this->asProductProductGroupLink);

        if (!isset($existingProducts[$remoteId])) {
            $productProductGroupLinkInsertArray['filter_insert']['product_remote_id'] = $remoteId;
        } else {
            $productProductGroupLinkInsertArray['product_id'] = $existingProducts[$remoteId]['id'];
        }

        $productProductGroupLinkInsertArray['product_group_id'] = $productGroupId;
        $productProductGroupLinkInsertArray['ord'] = 100;

        return $productProductGroupLinkInsertArray;
    }

    private function getTfoSRouteInsertArray($remoteId, $url, $storeId)
    {
        $sRouteInsertArray = $this->getEntityDefaults($this->asSRoute);

        $sRouteInsertArray['filter_insert']['product_remote_id'] = $remoteId;
        $sRouteInsertArray['request_url'] = $url;
        $sRouteInsertArray['store_id'] = $storeId;
        $sRouteInsertArray['destination_type'] = 'product';

        return $sRouteInsertArray;
    }

    private function addToProduct($productInsertArray, $attribute, $value)
    {
        if (isset($this->insertProductAttributes[$attribute])) {
            $productInsertArray[$attribute] = $value;
        }

        return $productInsertArray;
    }

    private function filterFilterKey($string)
    {
        $string = $this->helperManager->nameToFilename($string);
        $string = str_replace('-', '', $string);

        return preg_replace('/([_])\\1+/', '_', $string);
    }

    private function getIntTfoAttributeInsertArray($attributeName, $configurationId = null)
    {
        $intTfoAttributeInsertArray = $this->getEntityDefaults($this->asIntTfoAttribute);

        $intTfoAttributeInsertArray['name'] = $attributeName;
        $intTfoAttributeInsertArray['configuration_id'] = $configurationId;

        return $intTfoAttributeInsertArray;
    }

    private function getIntTfoAttributeValueInsertArray($valueName, $attributeName = null, $intTfoAttributeId = null, $optionId = null)
    {
        $intTfoAttributeValueInsertArray = $this->getEntityDefaults($this->asIntTfoAttributeValue);

        $intTfoAttributeValueInsertArray['name'] = $valueName;
        $intTfoAttributeValueInsertArray['option_id'] = $optionId;

        if (!empty($intTfoAttributeId)) {
            $intTfoAttributeValueInsertArray['int_tfo_attribute_id'] = $intTfoAttributeId;
        } else {
            $intTfoAttributeValueInsertArray['filter_insert']['int_tfo_attribute_name'] = mb_strtolower($attributeName);
        }

        return $intTfoAttributeValueInsertArray;
    }

    private function getTfoSProductAttributesLinkInsertArray($optionId, $valueName, $configurationId, $remoteId = null, $productId = null)
    {
        $sProductAttributesLinkInsertArray = $this->getEntityDefaults($this->asSProductAttributesLink);

        if (!empty($productId)) {
            $sProductAttributesLinkInsertArray['product_id'] = $productId;
        } else {
            $sProductAttributesLinkInsertArray['filter_insert']['product_remote_id'] = $remoteId;
        }

        $sProductAttributesLinkInsertArray['configuration_option'] = $optionId;
        $sProductAttributesLinkInsertArray['attribute_value'] = $valueName;
        $sProductAttributesLinkInsertArray['s_product_attribute_configuration_id'] = $configurationId;

        return $sProductAttributesLinkInsertArray;
    }

    /**
     * @return array|bool|null
     */
    public function import()
    {
        echo "Starting import...\n";
        $ret = array();
        $ret["message"] = null;
        $ret["error"] = true;
        $ret["error_code"] = null;

        /**
         * Prvo se moraju importati kategorije jer se proizvodi dohvaÄ‡aju za svaku kategoriju posebno.
         */
        $categories = $this->importCategories();

        echo "\tStarting importing products...\n";

        if ($categories['error'] === true) {
            $ret["message"] = $categories["message"];
            $ret["error_code"] = $categories["error_code"];

            return $ret;
        }

        if (empty($categories["joined_categories"])) {
            $ret['message'] = "There is no joined categories";
            $ret['error_code'] = "EmptyCategoryIds";
            return $ret;
        }

        $productColumnKeys = [
            'id', 'remote_id', 'active', 'code', 'catalog_code', 'name', 'description', 'specification', 'qty', 'price_base', 'price_retail', 'ean', 'price_purchase'
        ];

        $attributeConfigurationColumnKeys = ["id", "name", "s_product_attribute_configuration_type_id", "filter_key"];

        /**
         * Existing arrays
         */
        $existingProducts = $this->getExistingTfoProducts("remote_id", $productColumnKeys, " AND remote_source = '{$this->getRemoteSource()}' "); // remote_id
        $existingSRoutes = $this->getExistingTfoSRoutes(); // store_id _ request_url
        $existingIntTfoCategoryProductLinks = $this->getExistingIntTfoCategoryProductLinks(); // code_remote_id
        $existingProductProductGroupLinks = $this->getExistingTfoProductProductGroupLinks(); // remote_id_product_group_id
        $existingIntTfoImages = $this->getExistingTfoImages(); //[product_id][filenames]
        $existingDeviceTypes = $this->getExistingTfoDeviceTypes(); // name
        $existingDeviceBrands = $this->getExistingTfoDeviceBrands(); // typename_brand_name
        $existingDeviceModels = $this->getExistingTfoDeviceModels(); // typename_brandname_modelname
        $existingIntTfoAttributes = $this->getExistingIntTfoAttributes(); // name
        $existingIntTfoAttributeValues = $this->getExistingIntTfoAttributeValues(); // name
        $existingSProductAttributeConfigurations = $this->getExistingTfoSProductAttributeConfigurations("id", $attributeConfigurationColumnKeys); // id
        $existingSProductAttributesLinks = $this->getExistingTfoSProductAttributesLinks("attribute_value_key", ["attribute_value_key"]); // name
        $existingSProductAttributeConfigurationOptions = $this->getExistingTfoSProductAttributeConfigurationOptions("id", ["id", "configuration_value"]); // id
        $existingSProductAttributeConfigurationOptionsBySortKey = $this->getExistingTfoSProductAttributeConfigurationOptions(null, ["id", "configuration_attribute_id", "configuration_value"], ["configuration_attribute_id", "configuration_value"]); // id
        $existingSProductAttributeConfigurationsByFilterKey = $this->getExistingTfoSProductAttributeConfigurations("filter_key", ["id", "name"]); // filter_key

        /**
         * Import arrays
         */
        $insertArray = [
            'int_tfo_device_type_entity' => [],
            'int_tfo_attribute_entity' => []
        ];

        $insertArray2 = [
            'product_entity' => [],
            'int_tfo_device_brand_entity' => [],
            'int_tfo_attribute_values_entity' => [],
        ];

        $insertArray3 = [
            's_route_entity' => [],
            's_product_attributes_link_entity' => [],
            'int_tfo_category_product_link_entity' => [],
            'product_product_group_link_entity' => [],
            'product_images_entity' => [],
            'int_tfo_device_model_entity' => []
        ];

        $updateArray = [
            'product_entity' => []
        ];

        $changedIds = array();
        $changedRemoteIds = array();
        $productAlreadyLooped = array();

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->container->get("application_settings_manager");
        }
        $this->currencyExchange = $this->applicationSettingsManager->getApplicationSettingByCode("tfo_currency_exchange")[3];

        /**
         * Za svaku kategoriju uzmi artikle
         */
        foreach ($categories['joined_categories'] as $categoryRemoteId => $categoryArray) {

            $xml = $this->api('getProductList', [
                'Language' => $this->dictionary['lang'],
                'Currency' => $this->dictionary['curr'],
                'CategoryId' => (int)$categoryRemoteId
            ]);

            if ($xml["error"] === true) {
                $ret["message"] = $xml["message"];
                $ret["error_code"] = $xml["error_code"];

                return $ret;
            }

            /**
             * MoÅ¾e se dogoditi da kategorija nema proizvoda ili da xml vrati null
             */
            if (empty($xml["result"])) {
                continue;
            }

            $products = json_decode(json_encode($xml['result']), true);

            $count = 0;
            $productCount = count($products);

            echo 'Products for category: ' . $categoryArray['category_id'] . ' (out of ' . count($categories) . ")\n";

            foreach ($products as $product) {

                $remoteId = trim($product["Id"]);

                $productChanged = false;

                $code = trim($product["Code"]);
                $catalogCode = trim($product["CodeByProducer"]);
                $name = trim($product["Name"]);

                /**
                 * Pretvaranje u kune
                 */
                $pricePurchase = $this->getFloatValue(trim($product["PriceNett"])) * $this->getFloatValue($this->currencyExchange);
                $active = $this->getStockStatus(trim($product["StockStatus"]));
                $qty = $this->getFloatValue($this->getProductQuantity($active));
                $ean = trim(trim($product["EANCode"]));

                /**
                 * 1. DODAVANJE LINKA NA PROIZVOD I GRUPU
                 */
                if (!isset($existingIntTfoCategoryProductLinks[$categoryArray['code'] . '_' . $remoteId])) {

                    $insertArray3['int_tfo_category_product_link_entity'][$categoryArray['code'] . '_' . $remoteId] =
                        $this->getIntTfoCategoryProductLinkInsertArray($categoryArray['category_id'], $remoteId, $existingProducts);
                }

                foreach ($categoryArray as $key => $productGroupId) {
                    if (!is_numeric($key)) {
                        continue;
                    }

                    if (!isset($existingProductProductGroupLinks[$remoteId . '_' . $productGroupId])) {

                        $insertArray3['product_product_group_link_entity'][$remoteId . '_' . $productGroupId] =
                            $this->getTfoProductProductGroupInsertArray($productGroupId, $existingProducts, $remoteId);

                        $productChanged = true;
                    }
                }

                /**
                 * Ako je proizvod veÄ‡ loopan, preskoÄi
                 * bitno je samo da se kategorija provjeri
                 */
                if (isset($productAlreadyLooped[$remoteId])) {
                    continue;
                }


                /**
                 * DohvaÄ‡anje dodatnog linka
                 */
                $productInfo = $this->getProductInfo($remoteId);

                $description = $productInfo['description'];
                $images = $productInfo['images'];
                $specification = $productInfo['specification'];
                $compatibility = $productInfo['compatibility'];

                /**
                 * INSERING COMPATIBILITY
                 * dohvaÄ‡anje po nameu, remote id ni ne treba pa je obrisan
                 */
                if (!empty($compatibility)) {
                    foreach ($compatibility as $c) {
                        $typeName = trim($c['TypeName']);
                        $brandName = trim($c['BrandName']);
                        $modelName = trim($c['ModelName']);

                        /**
                         * Type
                         */
                        if (!isset($existingDeviceTypes[$typeName]) && !isset($insertArray['int_tfo_device_type_entity'][$typeName])) {
                            $deviceTypeInsertArray = $this->getEntityDefaults($this->asDeviceType);
                            $deviceTypeInsertArray['name'] = $typeName;

                            $insertArray['int_tfo_device_type_entity'][$typeName] = $deviceTypeInsertArray;
                        }

                        /**
                         * Brand
                         */
                        if (!isset($existingDeviceBrands[$typeName . '_' . $brandName]) && !isset($insertArray2['int_tfo_device_brand_entity'][$typeName . '_' . $brandName])) {
                            $deviceBrandInsertArray = $this->getEntityDefaults($this->asDeviceBrand);

                            $deviceBrandInsertArray['name'] = $brandName;

                            if (!isset($existingDeviceTypes[$typeName])) {
                                $deviceBrandInsertArray['filter_insert']['type_remote_code'] = $typeName;
                            } else {
                                $deviceBrandInsertArray['type_id'] = $existingDeviceTypes[$typeName]['id'];
                            }

                            $insertArray2['int_tfo_device_brand_entity'][$typeName . '_' . $brandName] = $deviceBrandInsertArray;
                        }

                        /**
                         * Model
                         */
                        if (!isset($existingDeviceModels[$typeName . '_' . $brandName . '_' . $modelName]) &&
                            !isset($insertArray3['int_tfo_device_model_entity'][$typeName . '_' . $brandName . '_' . $modelName])
                        ) {
                            $deviceModelInsertArray = $this->getEntityDefaults($this->asDeviceModel);

                            $deviceModelInsertArray['name'] = $modelName;

                            if (!isset($existingDeviceBrands[$typeName . '_' . $brandName])) {
                                $deviceModelInsertArray['filter_insert']['brand_remote_code'] = $typeName . '_' . $brandName;
                            } else {
                                $deviceModelInsertArray['brand_id'] = $existingDeviceBrands[$typeName . '_' . $brandName]['brand_id'];
                            }

                            $insertArray3['int_tfo_device_model_entity'][$typeName . '_' . $brandName . '_' . $modelName] = $deviceModelInsertArray;
                        }
                    }
                }

                /**
                 * Preparing json arrays
                 */
                $showOnStoreArray = [];
                $nameArray = [];
                $descriptionArray = [];
                $metaKeywordsArray = [];
                $urlArray = [];


                foreach ($this->getStores() as $storeId) {
                    $showOnStoreArray[$storeId] = 1;
                    $nameArray[$storeId] = $name;
                    $descriptionArray[$storeId] = $description;
                    $metaKeywordsArray[$storeId] = '';

                    if (!isset($existingProducts[$remoteId])) {
                        $i = 0;
                        $url = $key = $this->routeManager->prepareUrl($name);
                        while (isset($existingSRoutes[$storeId . '_' . $url]) || isset($insertArray3['s_route_entity'][$storeId . '_' . $url])) {
                            $url = $key . '_' . $i++;
                        }

                        $insertArray3['s_route_entity'][$storeId . '_' . $url] = $this->getTfoSRouteInsertArray($remoteId, $url, $storeId);
                        $urlArray[$storeId] = $url;
                    }
                }
                /**
                 * JSON files
                 */
                $showOnStoreJson = json_encode($showOnStoreArray, JSON_UNESCAPED_UNICODE);
                $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);
                $descriptionJson = json_encode($descriptionArray, JSON_UNESCAPED_UNICODE);
                $metaKeywordsJson = json_encode($metaKeywordsArray, JSON_UNESCAPED_UNICODE);
                $urlJson = json_encode($urlArray, JSON_UNESCAPED_UNICODE);
                $specificationJson = json_encode($specification, JSON_UNESCAPED_UNICODE);


                /**
                 * DODAVANJE PROIZVODA
                 */
                if (!isset($existingProducts[$remoteId])) {

                    $productInsertArray = $this->getEntityDefaults($this->asProduct);

                    $productInsertArray = $this->addToProduct($productInsertArray, 'date_synced', 'NOW()');
                    $productInsertArray = $this->addToProduct($productInsertArray, 'remote_id', $remoteId);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'code', $code);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'catalog_code', $catalogCode);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'remote_source', $this->getRemoteSource());
                    $productInsertArray = $this->addToProduct($productInsertArray, 'name', $nameJson);
                    if(in_array("meta_title",$this->insertProductAttributes)){
                        $productInsertArray = $this->addToProduct($productInsertArray, 'meta_title', $nameJson);
                    }
                    if(in_array("meta_description",$this->insertProductAttributes)){
                        $productInsertArray = $this->addToProduct($productInsertArray, 'meta_description', $nameJson);
                    }
                    $productInsertArray = $this->addToProduct($productInsertArray, 'description', $descriptionJson);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'price_purchase', $pricePurchase);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'currency_id', $_ENV["DEFAULT_CURRENCY"]);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'product_type_id', 1);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'manufacturer_remote_id', null);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'ord', 100);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'ean', $ean);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'is_visible', 1);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'qty_step', 1);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'template_type_id', 5);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'qty', $qty);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'auto_generate_url', 1);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'show_on_store', $showOnStoreJson);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'url', $urlJson);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'active', $active);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'keep_url', 1);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'content_changed', 1);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'meta_keywords', $metaKeywordsJson);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'show_on_homepage', 0);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'supplier_id', $this->tfoSupplier);
                    $productInsertArray = $this->addToProduct($productInsertArray, 'specification', $specificationJson);

                    if (!empty($this->customProductAttributes)) {
                        foreach ($this->customProductAttributes as $customAttribute => $customAttributeValue) {
                            $productInsertArray[$customAttribute] = $customAttributeValue;
                        }
                    }

                    $insertArray2['product_entity'][$remoteId] = $productInsertArray;

                    // $changedRemoteIds[$remoteId] = $remoteId;
                    $productChanged = true;

                    // UPDATE
                } else {
                    $productId = $existingProducts[$remoteId]['id'];
                    unset($updateArray['product_entity'][$productId]);
                    $productUpdateArray = [];

                    if (isset($this->updateProductAttributes['name']) &&
                        $nameArray != json_decode($existingProducts[$remoteId]["name"], true)) {
                        $productUpdateArray['name'] = $nameJson;
                        $productUpdateArray['meta_title'] = $nameJson;
                        $productUpdateArray['content_changed'] = 1;
                    }
                    if (isset($this->updateProductAttributes['description']) &&
                        $descriptionArray != json_decode($existingProducts[$remoteId]["description"], true)) {
                        $productUpdateArray['meta_description'] = $nameJson;
                        $productUpdateArray['description'] = $descriptionJson;
                    }
                    if (isset($this->updateProductAttributes['specification']) &&
                        $specification != json_decode($existingProducts[$remoteId]["specification"], true)) {
                        $productUpdateArray['specification'] = $specificationJson;
                    }
                    if (isset($this->updateProductAttributes['ean']) && $ean != $existingProducts[$remoteId]["ean"]) {
                        $productUpdateArray["ean"] = $ean;
                    }
                    if (isset($this->updateProductAttributes['qty']) &&
                        (string)$qty != (string)(float)$existingProducts[$remoteId]["qty"]) {
                        $productUpdateArray["qty"] = $qty;
                    }
                    if (isset($this->updateProductAttributes['price_purchase']) &&
                        (string)$pricePurchase != (string)(float)$existingProducts[$remoteId]['price_purchase']) {
                        $productUpdateArray['price_purchase'] = $pricePurchase;
                    }
                    if (isset($this->updateProductAttributes['active']) && $active != $existingProducts[$remoteId]['active']) {
                        $productUpdateArray['active'] = $active;
                    }
                    if (isset($this->updateProductAttributes['code']) && $code != $existingProducts[$remoteId]['code']) {
                        $productUpdateArray['code'] = $code;
                    }
                    if (isset($this->updateProductAttributes['catalog_code']) && $catalogCode != $existingProducts[$remoteId]['catalog_code']) {
                        $productUpdateArray['catalog_code'] = $catalogCode;
                    }
                    if (!empty($productUpdateArray)) {
                        $productUpdateArray['date_synced'] = 'NOW()';
                        $productUpdateArray['modified'] = 'NOW()';
                        $updateArray['product_entity'][$productId] = $productUpdateArray;
                        if(!empty(array_intersect(array_keys($productUpdateArray), $this->triggerChangesArray))){
                            $productChanged = true;
                        }
                    }
                }

                /**
                 * Spajanje konfiguracija
                 */
                if (!empty($specification)) {
                    foreach ($specification as $attributeName => $valueName) {

                        if (empty($attributeName) || empty($valueName)) {
                            continue;
                        }

                        $attributeNameFilterKey = $this->filterFilterKey($attributeName);
                        $configurationId = null;
                        $optionId = null;
                        $valueNameLower = mb_strtolower($valueName);
                        $attributeNameLower = mb_strtolower($attributeName);
                        $intTfoAttributeValueSortKey = mb_strtolower($attributeName) . "_" . mb_strtolower($valueName);

                        if ($attributeNameFilterKey === "colour"){
                            $attributeNameFilterKey = "color";
                        }

                        /**
                         * AKO JE POSTAVLJENA KONFIGURACIJA I OPCIJA, UZMI IH
                         */
                        if (isset($existingSProductAttributeConfigurationsByFilterKey[$attributeNameFilterKey])) {
                            $configurationId = $existingSProductAttributeConfigurationsByFilterKey[$attributeNameFilterKey]["id"];
                            $optionSortKey = $configurationId . "_" . $valueNameLower;
                            if (isset($existingSProductAttributeConfigurationOptionsBySortKey[$optionSortKey])) {
                                $optionId = $existingSProductAttributeConfigurationOptionsBySortKey[$optionSortKey]["id"];
                            }
                        }

                        /**
                         * TODO: ako option_id nije prazan, update valuea
                         */

                        if (!isset($existingIntTfoAttributes[$attributeNameLower])) {
                            if (!isset($insertArray['int_tfo_attribute_entity'][$attributeNameLower])) {
                                $insertArray['int_tfo_attribute_entity'][$attributeNameLower] = $this->getIntTfoAttributeInsertArray($attributeName, $configurationId);
                            }

                            if (!isset($existingIntTfoAttributeValues[$intTfoAttributeValueSortKey]) && !isset($insertArray2['int_tfo_attribute_values_entity'][$intTfoAttributeValueSortKey])) {
                                $insertArray2['int_tfo_attribute_values_entity'][$intTfoAttributeValueSortKey] = $this->getIntTfoAttributeValueInsertArray($valueName, $attributeName, null, $optionId);
                            }

                        } else {
                            $intTfoAttributeId = $existingIntTfoAttributes[$attributeNameLower]['id'];

                            /** automatski povezivanje s opcijama */
                            if (!isset($existingIntTfoAttributeValues[$intTfoAttributeValueSortKey]) && !isset($insertArray2['int_tfo_attribute_values_entity'][$intTfoAttributeValueSortKey])) {
                                $insertArray2['int_tfo_attribute_values_entity'][$intTfoAttributeValueSortKey] = $this->getIntTfoAttributeValueInsertArray($valueName, null, $intTfoAttributeId, $optionId);
                            } elseif (isset($existingIntTfoAttributeValues[$intTfoAttributeValueSortKey]) && !empty($existingIntTfoAttributeValues[$intTfoAttributeValueSortKey]['option_id'])) {
                                $optionId = $existingIntTfoAttributeValues[$intTfoAttributeValueSortKey]['option_id'];
                            }
                        }

                        if (!empty($configurationId)) {
                            $configurationTypeId = $existingSProductAttributeConfigurations[$configurationId]['s_product_attribute_configuration_type_id'];

                            if ($configurationTypeId == 1 || $configurationTypeId == 2) {

                                if (!empty($optionId)) {
                                    $configurationValue = $existingSProductAttributeConfigurationOptions[$optionId]['configuration_value'];

                                    if (!isset($existingProducts[$remoteId])) {
                                        $insertArray3['s_product_attributes_link_entity'][] = $this->getTfoSProductAttributesLinkInsertArray($optionId, $configurationValue, $configurationId, $remoteId);
                                    } else {
                                        $attributeValueKey = md5($existingProducts[$remoteId]['id'] . $configurationId . $optionId);

                                        if (!isset($existingSProductAttributesLinks[$attributeValueKey])) {
                                            $insertArray3['s_product_attributes_link_entity'][$attributeValueKey] = $this->getTfoSProductAttributesLinkInsertArray($optionId, $configurationValue, $configurationId, null, $existingProducts[$remoteId]['id']);
                                        }
                                    }
                                }

                            } else if ($configurationTypeId == 3 || $configurationTypeId == 4) {

                                if (!isset($existingProducts[$remoteId])) {
                                    $insertArray3['s_product_attributes_link_entity'][] = $this->getTfoSProductAttributesLinkInsertArray(null, $valueName, $configurationId, $remoteId);
                                } else {
                                    $attributeValueKey = md5($existingProducts[$remoteId]['id'] . $configurationId . null);
                                    if (!isset($existingSProductAttributesLinks[$attributeValueKey])) {
                                        $insertArray3['s_product_attributes_link_entity'][$attributeValueKey] = $this->getTfoSProductAttributesLinkInsertArray(null, $valueName, $configurationId, null, $existingProducts[$remoteId]['id']);
                                    }
                                }
                            }
                        }
                    }
                }

                /**
                 * Images
                 */
                if (!empty($images)) {
                    $ord = 0;

                    foreach ($images as $image) {

                        $filename = $this->helperManager->getFilenameFromUrl($image);
                        $extension = $this->helperManager->getFileExtension($filename);
                        $filename = $this->helperManager->getFilenameWithoutExtension($filename);

                        if (isset($existingProducts[$remoteId])) {
                            $productId = $existingProducts[$remoteId]['id'];


                            if (!isset($existingIntTfoImages[$productId]) ||
                                (!isset($existingIntTfoImages[$productId][$filename]) && !isset($insertArray3['product_images_entity'][$productId . "_" . $filename]))) {

                                if (empty($ord) && isset($existingIntTfoImages[$productId])) {
                                    $ord = count($existingIntTfoImages[$productId]);
                                }

                                $productImageInsertArray = $this->getEntityDefaults($this->asProductImage);

                                $productImageInsertArray['filename'] = $filename;
                                $productImageInsertArray["file_type"] = strtolower($extension);
                                $productImageInsertArray['product_id'] = $productId;
                                $productImageInsertArray['filter_insert']['image_url'] = $image;
                                $productImageInsertArray['selected'] = ($ord == 0);
                                $productImageInsertArray['ord'] = ++$ord;

                                $insertArray3['product_images_entity'][$productId . "_" . $filename] = $productImageInsertArray;
                            }
                        } else {
                            $productImageInsertArray = $this->getEntityDefaults($this->asProductImage);

                            $productImageInsertArray['filename'] = $filename;
                            $productImageInsertArray["file_type"] = strtolower($extension);
                            $productImageInsertArray['filter_insert']['product_remote_id'] = $remoteId;
                            $productImageInsertArray['filter_insert']['image_url'] = $image;
                            $productImageInsertArray['selected'] = ($ord == 0);
                            $productImageInsertArray['ord'] = ++$ord;

                            $insertArray3['product_images_entity'][] = $productImageInsertArray;
                        }
                    }
                }

                if ($productChanged === true) {
                    if (isset($existingProducts[$remoteId])) {
                        $changedIds[$existingProducts[$remoteId]["id"]] = $existingProducts[$remoteId]["id"];
                    } else {
                        $changedRemoteIds[$remoteId] = $remoteId;
                    }
                }

                $productAlreadyLooped[$remoteId] = $remoteId;
            }
        }

        /**
         * INSERT ARRAY 1
         * int_tfo_device_type_entity, int_tfo_attribute_entity
         */
        $this->executeInsertQuery($insertArray);

        /**
         * INSERT ARRAY 2
         * product_entity, int_tfo_device_brand_entity, int_tfo_attribute_values_entity
         * filteri: (device > type), (value > attribute)
         */
        $reselectedArray['int_tfo_device_type_entity'] = $this->getExistingTfoDeviceTypes();
        $reselectedArray['int_tfo_attribute_entity'] = $this->getExistingIntTfoAttributes();
        $insertArray2 = $this->filterImportArray($insertArray2, $reselectedArray);

        $this->executeInsertQuery($insertArray2);

        /**
         * Update array
         * product_entity
         */
        $this->executeUpdateQuery($updateArray);

        /**
         * INSERT ARRAY 3
         * s_route_entity, int_tfo_category_product_link_entity, product_product_group_link_entity, int_tfo_device_model_entity
         * filteri: (product -> product_category_link), ( product_product_group_link -> product), (model > brand)
         */
        $reselectedArray['product_entity'] = $this->getExistingTfoProducts("remote_id", $productColumnKeys); // remote_id
        $reselectedArray['int_tfo_device_brand_entity'] = $this->getExistingTfoDeviceBrands();
        $insertArray3 = $this->filterImportArray($insertArray3, $reselectedArray);

        $this->executeInsertQuery($insertArray3);

        $productIds = array_merge(
            $this->getChangedProductsFromIds($changedIds),
            $this->getChangedProductsFromIds($changedRemoteIds, $reselectedArray['product_entity'])
        );

        if (!empty($productIds)) {
            $this->changedProducts['product_ids'] = $productIds;
            $this->changedProducts['supplier_ids'][] = $this->tfoSupplier;
        }

        $ret["error"] = false;

        return $ret;
    }

    private function getChangedProductsFromIds($changedIds, $existingProducts = null)
    {
        $ret = array();

        foreach ($changedIds as $productId) {
            if (!empty($existingProducts)) {
                if (!isset($existingProducts[$remoteId = $productId])) {
                    continue;
                }
                $productId = $existingProducts[$remoteId]["id"];
            }
            $ret[] = $productId;
        }

        return $ret;
    }

    /**
     * @param $remoteId
     * @return array|null
     */
    private function getProductInfo($remoteId)
    {
        $xml = $this->api('getProductInfo', [
            'Language' => $this->dictionary['lang'],
            'Currency' => $this->dictionary['curr'],
            'ProductId' => (int)$remoteId
        ]);

        if ($xml["error"] === true || empty($xml['result'])) {
            return [];
        }

        $ret = array();
        $ret["description"] = "";
        $ret["images"] = null;
        $ret["specification"] = null;
        $ret["compatibility"] = null;

        $resultXml = json_decode(json_encode($xml['result']), true);

        /**
         * Ako postoji description
         */
        if (isset($resultXml["Description"])) {
            $ret['description'] = nl2br(trim($resultXml["Description"]));
        }

        /**
         * Ako postoje slike
         */
        if (isset($resultXml["ImgURL"]) && isset($resultXml["ImgURL"]["string"]) && !empty($resultXml["ImgURL"]["string"])) {

            if (!is_array($resultXml["ImgURL"]["string"])) {
                $ret['images'][] = urldecode($resultXml["ImgURL"]["string"]);
            } else {
                foreach ($resultXml["ImgURL"]["string"] as $imageUrl) {
                    if (empty($imageUrl)) {
                        continue;
                    }

                    $ret['images'][] = urldecode((string)$imageUrl);
                }
            }
        }

        /**
         * Ako postoje specifikacije
         */
        if (isset($resultXml["Attr"]) && isset($resultXml["Attr"]["rowProductAttr"]) && !empty($resultXml["Attr"]["rowProductAttr"])) {

            if (isset($resultXml["Attr"]["rowProductAttr"]["Name"])) {
                $resultXml["Attr"]["rowProductAttr"]["Name"] = mb_ereg_replace("([^\w\s\d\-])", "", $resultXml["Attr"]["rowProductAttr"]["Name"]);
                $ret['specification'][trim($resultXml["Attr"]["rowProductAttr"]["Name"])] = $resultXml["Attr"]["rowProductAttr"]["Value"];
            } else {
                foreach ($resultXml["Attr"]["rowProductAttr"] as $specification) {
                    $specification["Name"] = mb_ereg_replace("([^\w\s\d\-])", "", $specification["Name"]);
                    $ret['specification'][trim($specification["Name"])] = $specification["Value"];
                }
            }
        }

        /**
         * ako postoji compatibility
         */
        if (isset($resultXml["Compatibility"]) && isset($resultXml["Compatibility"]["rowProductCompatibility"]) && !empty($resultXml["Compatibility"]["rowProductCompatibility"])) {
            if (isset($resultXml["Compatibility"]["rowProductCompatibility"]["TypeId"])) {
                $ret['compatibility'][] = $resultXml["Compatibility"]["rowProductCompatibility"];
            } else {
                foreach ($resultXml["Compatibility"]["rowProductCompatibility"] as $compatibility) {
                    $ret['compatibility'][] = $compatibility;
                }
            }

        }

        return $ret;
    }

    /**
     * @param string $status
     * @return int
     */
    private function getStockStatus(string $status)
    {
        return ($status === "Unavailable") ? 0 : 1;
    }

    /**
     * @param $active
     * @return int
     */
    private function getProductQuantity($active)
    {
        return ($active == 1) ? 100 : 0;
    }


    private function getExistingIntTfoCategories()
    {
        $q = "
            SELECT id, name, code, remote_id, parent_remote_id, product_groups, parent_id
            FROM int_tfo_category_entity
            WHERE entity_state_id = 1;
        ";

        $ret = [];

        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d['remote_id']] = $d;
        }

        return $ret;
    }

    private function getExistingIntTfoCategoryGroupLinks()
    {
        $q = "SELECT id, int_tfo_category_id, product_group_id
        FROM int_tfo_category_group_link_entity
        WHERE entity_state_id = 1;
        ";

        $ret = [];

        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d['int_tfo_category_id']][] = $d['product_group_id'];
        }

        return $ret;
    }

    private function getIntTfoCategoryInsertArray($remoteId, $parentRemoteId, $name, $treeCode, $existingIntTfoCategoriesByRemoteId)
    {
        $intTfoCategoryInsertArray = $this->getEntityDefaults($this->asIntTfoCategory);

        $intTfoCategoryInsertArray['remote_id'] = $remoteId;
        $intTfoCategoryInsertArray['parent_remote_id'] = $parentRemoteId;
        $intTfoCategoryInsertArray['name'] = $name;
        $intTfoCategoryInsertArray['code'] = $treeCode;

        if (!isset($existingIntTfoCategoriesByRemoteId[$parentRemoteId])) {
            $intTfoCategoryInsertArray['filter_insert']['parent_remote_id'] = $parentRemoteId;
        } else {
            $intTfoCategoryInsertArray['parent_id'] = $existingIntTfoCategoriesByRemoteId[$parentRemoteId]['id'];
        }

        return $intTfoCategoryInsertArray;
    }

    /**
     * @return array
     */
    private function importCategories(): array
    {
        echo "\tImporting categories\n";
        $ret = array();
        $ret["message"] = null;
        $ret["error"] = true;
        $ret["error_code"] = null;
        $ret["joined_categories"] = null;

        $categoryParameters = array();
        $categoryParameters["Language"] = $this->dictionary['lang'];

        $xml = $this->api('getCategoryList', $categoryParameters, 'category-list');

        if ($xml['error'] === true) {
            $ret["message"] = $xml["message"];
            $ret["error_code"] = $xml["error_code"];
            return $ret;
        }

        /**
         * Existing arrays
         */
        $existingIntTfoCategoriesByRemoteId = $this->getExistingIntTfoCategories();
        $existingIntTfoCategoryGroupLinksByRemoteId = $this->getExistingIntTfoCategoryGroupLinks();

        /**
         * temp sluÅ¾i da se mogu uzeti podaci od te kategorije koja se promatra u odreÄ‘enoj iteraciji
         * Recimo da se explode-a tree_code: 2209330\1282. Uzme se remote_id 1282 i iz api-ja se dobiju ti podaci
         * Taj naÄin dohvaÄ‡anja olakÅ¡ava insert
         */
        $temp = array();
        foreach ($xml["result"] as $category) {
            $temp[(string)$category->Id]["remote_id"] = (string)$category->Id;
            $temp[(string)$category->Id]["parent_remote_id"] = (string)$category->ParentId;
            $temp[(string)$category->Id]["tree_code"] = (string)$category->TreeCode;
            $temp[(string)$category->Id]["name"] = (string)$category->Name;
        }

        /**
         * import arrays
         */
        $tfoCategoryInsertArray = [
            // 0 - parent
            // 1 - child
            // ...
        ];

        /**
         * U ovaj se array spremaju povezane kategorije.
         * Ovo treba jer se proizvodi dohvaÄ‡aju po kategorijama pa je potrebno povezati one kategorije koje trebaju.
         * Ne trebaju sve kategorije
         */
        $joinedCategoryRemoteIds = array();
        $categoryCount = count($xml["result"]);

        $count = 0;
        foreach ($xml["result"] as $category) {

            /**
             * Podaci o kategoriji
             */
            $remoteId = (string)$category->Id;
            $parentRemoteId = (string)$category->ParentId;
            $treeCode = str_replace('\\', '_', (string)$category->TreeCode);
            $name = (string)$category->Name;
            //$childCount = (string) $category->ChildCount;
            //$ordinal = (string) $category->Ordinal;

            if (isset($existingIntTfoCategoriesByRemoteId[$remoteId]) && isset($existingIntTfoCategoryGroupLinksByRemoteId[$existingIntTfoCategoriesByRemoteId[$remoteId]['id']])) {
                foreach ($existingIntTfoCategoryGroupLinksByRemoteId[$existingIntTfoCategoriesByRemoteId[$remoteId]['id']] as $productGroupId) {
                    $joinedCategoryRemoteIds[$remoteId][] = $productGroupId;
                    $joinedCategoryRemoteIds[$remoteId]['code'] = $existingIntTfoCategoriesByRemoteId[$remoteId]['code'];
                    $joinedCategoryRemoteIds[$remoteId]['category_id'] = $existingIntTfoCategoriesByRemoteId[$remoteId]['id'];

                    echo "\t\t" . "TFO CATEGORY {$existingIntTfoCategoriesByRemoteId[$remoteId]["id"]} JOINED WITH CATEGORY {$productGroupId}";
                }
            }

            // ako je parent_remote_id prazan, radi se o parentu
            if (empty($parentRemoteId)) {

                if (!isset($existingIntTfoCategoriesByRemoteId[$remoteId])
                    && !isset($tfoCategoryInsertArray[0][$remoteId])) {
                    $intTfoCategoryInsertArray = $this->getEntityDefaults($this->asIntTfoCategory);

                    $intTfoCategoryInsertArray['remote_id'] = $remoteId;
                    $intTfoCategoryInsertArray['parent_remote_id'] = null;
                    $intTfoCategoryInsertArray['parent_id'] = null;
                    $intTfoCategoryInsertArray['name'] = $name;
                    $intTfoCategoryInsertArray['code'] = $treeCode;
                    $tfoCategoryInsertArray[0][$remoteId] = $intTfoCategoryInsertArray;
                    echo "\t\t" . "TFO PARENT CATEGORY {$remoteId} ADDED\n";
                }

            } else {
                $treeCodeIds = explode('_', $treeCode);
                foreach ($treeCodeIds as $key => $id) {
                    if ($remoteId === $id) {
                        if (!isset($existingIntTfoCategoriesByRemoteId[$remoteId])
                            && !isset($tfoCategoryInsertArray[$key][$remoteId])) {

                            $tfoCategoryInsertArray[$key][$remoteId] = $this->getIntTfoCategoryInsertArray(
                                $remoteId, $parentRemoteId, $name, $treeCode, $existingIntTfoCategoriesByRemoteId
                            );
                        }
                    } else if (!isset($existingIntTfoCategoriesByRemoteId[$id])
                        && !isset($tfoCategoryInsertArray[$key][$id]) && isset($temp[$id])) {

                        $tfoCategoryInsertArray[$key][$remoteId] = $this->getIntTfoCategoryInsertArray(
                            $temp[$id]['remote_id'], $temp[$id]['parent_remote_id'], $temp[$id]['name'], $temp[$id]['tree_code'], $existingIntTfoCategoriesByRemoteId
                        );
                    }
                }
            }
        }

        if (!empty($tfoCategoryInsertArray)) {
            ksort($tfoCategoryInsertArray);
            $reselectArray['int_tfo_category_entity'] = $this->getExistingIntTfoCategories();

            foreach ($tfoCategoryInsertArray as $level => $categories) {
                $categories = ['int_tfo_category_entity' => $categories];

                if ($level > 0) {
                    $categories = $this->filterImportArray($categories, $reselectArray);
                }

                $this->executeInsertQuery($categories);

                $reselectArray['int_tfo_category_entity'] = $this->getExistingIntTfoCategories();
            }

            unset($reselectArray);
        }

        echo "\tCategories finished \n";
        $ret["error"] = false;
        $ret["joined_categories"] = $joinedCategoryRemoteIds;

        return $ret;
    }
}
