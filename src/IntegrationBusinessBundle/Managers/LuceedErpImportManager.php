<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Helpers\FileHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Constants\CrmConstants;
use AppBundle\Models\InsertModel;
use AppBundle\Models\UpdateModel;
use IntegrationBusinessBundle\Models\LuceedErpProductParams;
use IntegrationBusinessBundle\Models\LuceedErpWarehouseParams;

class LuceedErpImportManager extends DefaultIntegrationImportManager
{
    /** @var RestManager $luceedErpRestManager */
    private $luceedErpRestManager;
    /** @var LuceedErpProductParams $productParams */
    private $productParams;
    /** @var LuceedErpWarehouseParams $warehouseParams */
    private $warehouseParams;

    protected $apiUrl;
    protected $apiUsername;
    protected $apiPassword;
    protected $apiAuth;

    /** @var AttributeSet $asWarehouse */
    protected $asWarehouse;
    /** @var AttributeSet $asSRoute */
    protected $asSRoute;
    /** @var AttributeSet $asProduct */
    protected $asProduct;
    /** @var AttributeSet $asSProductAttributeConfiguration */
    protected $asSProductAttributeConfiguration;
    /** @var AttributeSet $asSProductAttributeConfigurationOptions */
    protected $asSProductAttributeConfigurationOptions;
    /** @var AttributeSet $asSProductAttributesLink */
    protected $asSProductAttributesLink;
    /** @var AttributeSet $asProductProductGroupLink */
    protected $asProductProductGroupLink;
    /** @var AttributeSet $asProductGroup */
    protected $asProductGroup;
    /** @var AttributeSet $asProductWarehouseLink */
    protected $asProductWarehouseLink;
    /** @var AttributeSet $asProductImages */
    protected $asProductImages;
    /** @var AttributeSet $asContact */
    protected $asContact;
    /** @var AttributeSet $asAccount */
    protected $asAccount;
    /** @var AttributeSet $asAddress */
    protected $asAddress;

    protected $warehouseInsertAttributes;
    protected $warehouseUpdateAttributes;

    protected $productInsertAttributes;
    protected $productUpdateAttributes;
    protected $productCustomAttributes;

    const ACCOUNT_LIST_ENDPOINT = "partneri/naziv";
    const ACCOUNT_PRICES_LIST_ENDPOINT = "prodajniuvjeti/partneri/[%s]";
    const PRODUCT_LIST_ENDPOINT = "artikli/naziv";
    const PRODUCT_LIST_BY_GROUP_ENDPOINT = "artikli/grupaartikla/%s";
    const PRODUCT_SINGLE_ENDPOINT = "artikli/sifra/%s";
    const PRODUCT_DOCUMENT_LIST_ENDPOINT = "artikli/dokumenti/%s";
    const PRODUCT_GROUP_LIST_ENDPOINT = "grupeartikala/lista";
    const DISCOUNT_LIST_ENDPOINT = "akcije/lista";
    const SUPPLIER_WAREHOUSE_STOCK_LIST_ENDPOINT = "stanjezalihedobavljaci/lista";
    const WAREHOUSE_STOCK_LIST_ENDPOINT = "stanjezalihe/skladiste";
    const WAREHOUSE_LIST_ENDPOINT = "skladista/lista";
    const WAREHOUSE_SINGLE_ENDPOINT = "skladista/sifra/%s";
    const ACCOUNT_OIB_ENDPOINT = "partneri/oib/%s";

    const SUPPLIER_WAREHOUSE_CODE = "luceed_suppliers";

    public function initialize()
    {
        parent::initialize();

        $this->apiUrl = $_ENV["LUCEED_ERP_URL"];
        $this->apiUsername = $_ENV["LUCEED_ERP_USERNAME"];
        $this->apiPassword = $_ENV["LUCEED_ERP_PASSWORD"];
        $this->apiAuth = base64_encode($this->apiUsername . ":" . $this->apiPassword);

        $this->luceedErpRestManager = new RestManager();
        $this->luceedErpRestManager->CURLOPT_HTTPHEADER = ["Authorization: Basic " . $this->apiAuth];

        $this->productParams = new LuceedErpProductParams();
        $this->warehouseParams = new LuceedErpWarehouseParams();

        $this->warehouseInsertAttributes = $this->getAttributesFromEnv("LUCEED_ERP_WAREHOUSE_INSERT_ATTRIBUTES");
        $this->warehouseUpdateAttributes = $this->getAttributesFromEnv("LUCEED_ERP_WAREHOUSE_UPDATE_ATTRIBUTES");

        $this->productInsertAttributes = $this->getAttributesFromEnv("LUCEED_ERP_PRODUCT_INSERT_ATTRIBUTES");
        $this->productUpdateAttributes = $this->getAttributesFromEnv("LUCEED_ERP_PRODUCT_UPDATE_ATTRIBUTES");
        $this->productCustomAttributes = $this->getAttributesFromEnv("LUCEED_ERP_PRODUCT_CUSTOM_ATTRIBUTES", false);

        if (!file_exists($this->getImportDir())) {
            mkdir($this->getImportDir());
        }

        $this->setRemoteSource("luceed_erp");

        $this->asWarehouse = $this->entityManager->getAttributeSetByCode("warehouse");
        $this->asSRoute = $this->entityManager->getAttributeSetByCode("s_route");
        $this->asProduct = $this->entityManager->getAttributeSetByCode("product");
        $this->asSProductAttributeConfiguration = $this->entityManager->getAttributeSetByCode("s_product_attribute_configuration");
        $this->asSProductAttributeConfigurationOptions = $this->entityManager->getAttributeSetByCode("s_product_attribute_configuration_options");
        $this->asSProductAttributesLink = $this->entityManager->getAttributeSetByCode("s_product_attributes_link");
        $this->asProductProductGroupLink = $this->entityManager->getAttributeSetByCode("product_product_group_link");
        $this->asProductGroup = $this->entityManager->getAttributeSetByCode("product_group");
        $this->asProductWarehouseLink = $this->entityManager->getAttributeSetByCode("product_warehouse_link");
        $this->asProductImages = $this->entityManager->getAttributeSetByCode("product_images");
        $this->asContact = $this->entityManager->getAttributeSetByCode("contact");
        $this->asAccount = $this->entityManager->getAttributeSetByCode("account");
        $this->asAddress = $this->entityManager->getAttributeSetByCode("address");
    }

    /**
     * @return LuceedErpProductParams
     */
    public function getProductParams()
    {
        return $this->productParams;
    }

    /**
     * @param LuceedErpProductParams $productParams
     * @return LuceedErpImportManager
     */
    public function setProductParams(LuceedErpProductParams $productParams)
    {
        $this->productParams = $productParams;

        return $this;
    }

    /**
     * @return LuceedErpWarehouseParams
     */
    public function getWarehouseParams()
    {
        return $this->warehouseParams;
    }

    /**
     * @param LuceedErpWarehouseParams $warehouseParams
     * @return LuceedErpImportManager
     */
    public function setWarehouseParams(LuceedErpWarehouseParams $warehouseParams)
    {
        $this->warehouseParams = $warehouseParams;

        return $this;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    private function getLuceedProducts()
    {
        $this->luceedErpRestManager->CURLOPT_TIMEOUT = 300;
        $data = $this->luceedErpRestManager->get($this->apiUrl . self::PRODUCT_LIST_ENDPOINT);
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        return $data["result"][0]["artikli"];
    }

    /**
     * @param $productCode
     * @return mixed
     * @throws \Exception
     */
    private function getLuceedSingleProduct($productCode)
    {
        $endpoint = sprintf(self::PRODUCT_SINGLE_ENDPOINT, $productCode);

        $data = $this->luceedErpRestManager->get($this->apiUrl . $endpoint);
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        return $data["result"][0]["artikli"];
    }

    /**
     * @param $productGroupCode
     * @return mixed
     * @throws \Exception
     */
    private function getLuceedProductsByGroup($productGroupCode)
    {
        $endpoint = sprintf(self::PRODUCT_LIST_BY_GROUP_ENDPOINT, $productGroupCode);

        $data = $this->luceedErpRestManager->get($this->apiUrl . $endpoint);
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        return $data["result"][0]["artikli"];
    }

    /**
     * @param $productCode
     * @return mixed
     * @throws \Exception
     */
    private function getLuceedProductDocumentsByProduct($productCode)
    {
        $endpoint = sprintf(self::PRODUCT_DOCUMENT_LIST_ENDPOINT, $productCode);

        $data = $this->luceedErpRestManager->get($this->apiUrl . $endpoint);
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        return $data["result"][0]["files"];
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    private function getLuceedWarehouseStock()
    {
        $data = $this->luceedErpRestManager->get($this->apiUrl . self::WAREHOUSE_STOCK_LIST_ENDPOINT);
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        return $data["result"][0]["stanje"];
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    private function getLuceedSupplierWarehouseStock()
    {
        $data = $this->luceedErpRestManager->get($this->apiUrl . self::SUPPLIER_WAREHOUSE_STOCK_LIST_ENDPOINT);
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        $warehouseStockArray = [];

        foreach ($data["result"][0]["artikli_dobavljaci"] as $d) {
            $code = trim($d["sifra_artikla"]);
            $qty = $d["dobavljac_stanje"];
            if ($qty > 0) {
                if (!isset($warehouseStockArray[$code])) {
                    $warehouseStockArray[$code] = 0;
                }
                $warehouseStockArray[$code] = bcadd($warehouseStockArray[$code], $qty, 4);
            }
        }

        return $warehouseStockArray;
    }

    /**
     * @param null $warehouseCode
     * @return mixed
     * @throws \Exception
     */
    private function getLuceedWarehouses($warehouseCode = null)
    {
        $endpoint = self::WAREHOUSE_LIST_ENDPOINT;
        if (!empty($warehouseCode)) {
            $endpoint = sprintf(self::WAREHOUSE_SINGLE_ENDPOINT, $warehouseCode);
        }

        $data = $this->luceedErpRestManager->get($this->apiUrl . $endpoint);
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        return $data["result"][0]["skladista"];
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    private function getLuceedDiscounts()
    {
        $data = $this->luceedErpRestManager->get($this->apiUrl . self::DISCOUNT_LIST_ENDPOINT);
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        return $data["result"][0]["akcije"];
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function getLuceedProductGroups()
    {
        $data = $this->luceedErpRestManager->get($this->apiUrl . self::PRODUCT_GROUP_LIST_ENDPOINT);
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        $productGroupsArray = [];

        foreach ($data["result"][0]["grupe_artikala"] as $d) {
            $name = trim($d["naziv"]);
            $code = trim($d["grupa_artikla"]);
            $parent = trim($d["nadgrupa_artikla"]);
            if (!empty($name) && !empty($code)) {
                $productGroupsArray[$code] = [
                    "name" => $name,
                    "code" => $code,
                    "parent" => $parent,
                    "remote_id" => $this->getLuceedIdFromUid($d["grupa_artikla_uid"])
                ];
            }
        }

        return $productGroupsArray;
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function getLuceedAccounts()
    {
        $data = $this->luceedErpRestManager->get($this->apiUrl . self::ACCOUNT_LIST_ENDPOINT);
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        $accountsArray = [];

        foreach ($data["result"][0]["partner"] as $d) {
            $accountsArray[$d["partner"]] = $d;
        }

        return $accountsArray;
    }

    /**
     * @param $partnerIds
     * @return mixed
     * @throws \Exception
     */
    private function getLuceedPrices($partnerIds)
    {
        $endpoint = sprintf(self::ACCOUNT_PRICES_LIST_ENDPOINT, implode(",", $partnerIds));

        $data = $this->luceedErpRestManager->get($this->apiUrl . $endpoint);
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        return $data["result"][0]["partneri"];
    }

    /**
     * @param $oib
     * @return mixed
     * @throws \Exception
     */
    public function getLuceedAccountByOib($oib)
    {
        $endpoint = sprintf(self::ACCOUNT_OIB_ENDPOINT, $oib);

        $data = $this->luceedErpRestManager->get($this->apiUrl . $endpoint);
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        return $data["result"][0]["partner"][0];
    }

    /**
     * @param $name
     * @return string
     */
    private function getLuceedBrandName($name)
    {
        if (StringHelper::endsWith($name, " SUN") || StringHelper::endsWith($name, " OPT")) {
            $name = substr($name, 0, -4);
        }
        return trim($name);
    }

    /**
     * @param $active
     * @return mixed
     */
    private function getLuceedBoolean($active)
    {
        $active = strtolower(trim($active));

        return [
            "n" => false,
            "d" => true
        ][$active];
    }

    /**
     * @param $isLegalEntity
     * @return mixed
     */
    private function getLuceedIsLegalEntity($isLegalEntity)
    {
        $isLegalEntity = strtolower(trim($isLegalEntity));

        return [
            "f" => false, // Fizička osoba
            "p" => true // Pravna osoba
        ][$isLegalEntity];
    }

    /**
     * Ovdje će pucati ako tip nije mapiran
     *
     * @param $luceedType
     * @return int
     */
    private function getLuceedConfigurationType($luceedType)
    {
        return [
            "Da/Ne" => 1, // Autocomplete
            "Decimalni broj" => 3, // Text
            "Cijeli broj" => 3, // Text
            "Opis" => 3, // Text
            "Tekst" => 3, // Text
            "Referenca" => 3, // Text
        ][$luceedType];
    }

    /**
     * @param $uid
     * @return mixed|string
     */
    private function getLuceedIdFromUid($uid)
    {
        return (explode("-", $uid, 2))[0];
    }

    /**
     * @param $percentage
     * @param $price
     * @return string|null
     */
    private function calculateDiscountPrice($percentage, $price)
    {
        return bcmul($price, bcdiv(bcsub(100, $percentage), 100, 4), 4);
    }

    /**
     * @param string $dateStr
     * @param string $timeStr
     * @return string
     * @throws \Exception
     */
    private function calculateDiscountDate(string $dateStr, string $timeStr)
    {
        $discountDate = $dateStr . " " . $timeStr;

        return (new \DateTime($discountDate))->format("Y-m-d H:i:s");
    }

    /**
     * @param $entityArray
     * @return mixed
     */
    protected function getProductImageFilename($entityArray)
    {
        $entityArray["file"] = $entityArray["product_id"] . "/" . $entityArray["filename"] . "." . $entityArray["file_type"];

        return $entityArray;
    }

    /**
     * @param $entityArray
     * @return array
     */
    protected function saveProductImage($entityArray)
    {
        if (!file_exists($this->getProductImagesDir() . $entityArray["product_id"])) {
            mkdir($this->getProductImagesDir() . $entityArray["product_id"], 0777, true);
        }

        $bytes = $this->helperManager->saveRawDataToFile($entityArray["content"], $this->getProductImagesDir() . $entityArray["file"]);
        if (!$bytes) {
            return [];
        }

        $entityArray["size"] = FileHelper::formatSizeUnits($bytes);

        unset($entityArray["content"]);

        return $entityArray;
    }

    /**
     * @param $syncProductCode
     * @return array
     * @throws \Exception
     */
    public function importSingleProduct($syncProductCode)
    {
        if (empty($syncProductCode)) {
            throw new \Exception("Sync product code is missing");
        }

        $data = $this->getLuceedSingleProduct($syncProductCode);

        /**
         * Obavezno overrideanje ovih parametara u false jer ne želimo da im se obrišu attribute/product group linkovi
         */
        $this->getProductParams()->setDeleteUnusedAttributeLinks(false)
            ->setDeleteUnusedProductProductGroupLinks(false);

        return $this->importProductsFromData($data, $syncProductCode);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importProducts()
    {
        $data = $this->getLuceedProducts();

        return $this->importProductsFromData($data);
    }

    /**
     * @param $data
     * @param $syncProductCode
     * @return array
     * @throws \Exception
     */
    private function importProductsFromData($data, $syncProductCode = null)
    {
        $this->echo("Importing products...\n");

        $data2 = [];
        if ($this->getProductParams()->getUseProductGroups()) {
            $data2 = $this->getLuceedProductGroups();
        }

        $productSelectColumns = array_merge(["id", "code"], array_keys($this->productUpdateAttributes));

        $existingProducts = $this->getExistingProducts("remote_id", $productSelectColumns, "AND product_type_id = 1 AND remote_source = '{$this->getRemoteSource()}'");
        $existingProductGroups = $this->getExistingProductGroups("product_group_code", ["id"]);
        $existingProductProductGroupLinks = $this->getExistingProductProductGroupLinks();
        $existingTaxTypes = $this->getExistingTaxTypes("name", ["id"]);
        $existingSRoutes = $this->getExistingSRoutes();
        $existingSProductAttributeConfigurations = $this->getExistingSProductAttributeConfigurations("filter_key", ["id", "s_product_attribute_configuration_type_id", "is_active"]);
        $existingSProductAttributeConfigurationOptions = $this->getExistingSProductAttributeConfigurationOptions(null, ["id"], ["configuration_attribute_id", "MD5(configuration_value)"]);
        $existingSProductAttributeLinks = $this->getSProductAttributesLinksByConfigurationAndOption($this->getRemoteSource());

        $existingProductImages = [];
        if ($this->getProductParams()->getUseProductDocuments()) {
            $existingProductImages = $this->getEntitiesArray(["id", "file"], "product_images_entity", ["file"]);
        }

        $insertArray = [
            // product_entity
            // s_product_attribute_configuration_entity
        ];
        $insertArray2 = [
            // s_route_entity
            // s_product_attribute_configuration_options_entity
            // product_product_group_link_entity
            // product_images_entity
        ];
        $insertArray3 = [
            // s_product_attributes_link_entity
        ];
        $updateArray = [
            // product_entity
            // s_product_attributes_link_entity
        ];
        $deleteArray = [
            // s_product_attributes_link_entity
            // product_product_group_link_entity
        ];
        $insertProductGroupsArray = [
            // product_group_entity
        ];

        $productIds = [];
        $productRemoteIds = [];

        foreach ($existingProducts as $existingProduct) {
            if ($existingProduct["active"] && (empty($syncProductCode) || strcmp($syncProductCode, $existingProduct["code"]) === 0)) {
                $productUpdate = new UpdateModel($existingProduct);
                $productUpdate->add("active", false, false)
                    ->add("date_synced", "NOW()", false);
                $updateArray["product_entity"][$productUpdate->getEntityId()] = $productUpdate->getArray();
                $productIds[] = $productUpdate->getEntityId();
            }
        }

        if ($this->getProductParams()->getDeleteUnusedAttributeLinks()) {
            foreach ($existingSProductAttributeLinks as $configurationOptions) {
                foreach ($configurationOptions as $attributeValues) {
                    foreach ($attributeValues as $attributeValueKey => $attributeLink) {
                        $deleteArray["s_product_attributes_link_entity"][$attributeValueKey] = [
                            "id" => $attributeLink["id"]
                        ];
                    }
                }
            }
        }

        if ($this->getProductParams()->getDeleteUnusedProductProductGroupLinks()) {
            foreach ($existingProductProductGroupLinks as $key => $existingProductProductGroupLink) {
                $deleteArray["product_product_group_link_entity"][$key] = [
                    "id" => $existingProductProductGroupLink["id"],
                    "product_id" => $existingProductProductGroupLink["product_id"]
                ];
            }
        }

        $this->startProgressBar(count($data));

        foreach ($data as $d) {

            $this->advanceProgressBar();
            if ($this->getProgress() % 100 == 0) {
                $this->databaseContext->reconnectToDatabase();
            }

            $name = trim($d["naziv"]);
            if (empty($name)) {
                continue;
            }
            $code = trim($d["artikl"]);
            if (empty($code)) {
                continue;
            }
            $ean = trim($d["barcode"]);
            if (empty($ean)) {
                continue;
            }

            $remoteId = trim($d["id"]);

            if (isset($existingProducts[$remoteId]) && !empty($syncProductCode) && strcmp($syncProductCode, $code) !== 0) {
                continue;
            }

            $catalogCode = trim($d["kataloski_broj"]);
            $modelCode = isset($d["model_naziv"]) ? trim($d["model_naziv"]) : NULL;
            $description = nl2br(trim($d["opis"]));

            $remoteModified = NULL;
            if (!empty($d["modified"])) {
                $remoteModified = $d["modified"];//\DateTime::createFromFormat("d.m.Y. H:i:s", $d["modified"]);
            }

            $priceBase = isset($d["vpc"]) ? $d["vpc"] : NULL;
            $priceRetail = $d["mpc"]; // artikl_pj_mpc

            $active = $this->getLuceedBoolean($d["enabled"]);

            $qty = $d["raspolozivo_kol"];
            if ($qty < 0.0) {
                $qty = 0.0;
            }

            $taxTypeId = 3;

            $taxTypePercent = (int)trim($d["porezna_tarifa"]); // stopa_pdv
            if ($taxTypePercent > 0) {
                $taxTypeName = "PDV" . $taxTypePercent;
                if (isset($existingTaxTypes[$taxTypeName])) {
                    $taxTypeId = $existingTaxTypes[$taxTypeName]["id"];
                }
            }

            $priceReturn = NULL;
            if (in_array(trim($d["porezna_tarifa"]), $this->getProductParams()->getTaxTypesForPriceReturn())) {
                $priceReturn = 0.07;
                $priceRetail = $priceRetail - $priceReturn;
            }

            $unit = strtolower(trim($d["jm"]));

            if ($this->getProductParams()->getBrandFieldName() == "grupa_artikla_naziv") {
                $brandName = $this->getLuceedBrandName($d[$this->getProductParams()->getBrandFieldName()]); // Anda
            } else {
                $brandName = trim($d[$this->getProductParams()->getBrandFieldName()]);
            }

            $productGroupArray = [];

            if (empty($data2)) {

                $superGroup = trim($d["supergrupa_artikla_naziv"]);
                $upperGroup = trim($d["nadgrupa_artikla_naziv"]);
                $group = trim($d["grupa_artikla_naziv"]);
                if (empty($group)) {
                    continue;
                }

                $superGroupCode = mb_strtolower($superGroup);
                $upperGroupCode = $superGroupCode . "_" . mb_strtolower($upperGroup);
                $groupCode = $upperGroupCode . "_" . mb_strtolower($group);

                $data3 = [];
                $data3[$groupCode] = ["name" => $group, "code" => $groupCode, "parent" => $upperGroupCode];
                $data3[$upperGroupCode] = ["name" => $upperGroup, "code" => $upperGroupCode, "parent" => $superGroupCode];
                $data3[$superGroupCode] = ["name" => $superGroup, "code" => $superGroupCode, "parent" => NULL];

            } else {

                $groupCode = trim($d["grupa_artikla"]);
                $data3 = $data2;
            }

            $currentGroup = $data3[$groupCode];

            while (!empty($currentGroup)) {
                array_unshift($productGroupArray, $currentGroup);
                if (!empty($currentGroup["parent"]) && isset($data3[$currentGroup["parent"]])) {
                    $currentGroup = $data3[$currentGroup["parent"]];
                } else {
                    $currentGroup = [];
                }
            }

            $configurationArray = [];

            if (!empty($unit) && isset($this->getProductParams()->getAttributes()["unit"])) {
                $configurationArray["unit"][$unit] = [
                    "type" => "Opis",
                    "value" => $unit
                ];
            }
            if (!empty($brandName)) {
                $configurationArray["brand"][$brandName] = [
                    "type" => "Opis",
                    "value" => $brandName
                ];
            }

            $nameArray = [];
            $descriptionArray = [];
            $metaKeywordsArray = [];
            $showOnStoreArray = [];
            $urlArray = [];

            foreach ($this->getStores() as $storeId) {

                $nameArray[$storeId] = $name;
                $descriptionArray[$storeId] = $description;
                $metaKeywordsArray[$storeId] = "";
                $showOnStoreArray[$storeId] = 1;

                if (!isset($existingProducts[$remoteId])) {

                    $i = 1;
                    $url = $key = $this->routeManager->prepareUrl($name);
                    while (isset($existingSRoutes[$storeId . "_" . $url]) || isset($insertArray2["s_route_entity"][$storeId . "_" . $url])) {
                        $url = $key . "-" . $i++;
                    }
                    $urlArray[$storeId] = $url;

                    $insertArray2["s_route_entity"][$storeId . "_" . $url] =
                        $this->getSRouteInsertEntity($url, "product", $storeId, $remoteId); // remote_id
                }
            }

            $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);
            $descriptionJson = json_encode($descriptionArray, JSON_UNESCAPED_UNICODE);
            $metaKeywordsJson = json_encode($metaKeywordsArray, JSON_UNESCAPED_UNICODE);
            $showOnStoreJson = json_encode($showOnStoreArray, JSON_UNESCAPED_UNICODE);
            $urlJson = json_encode($urlArray, JSON_UNESCAPED_UNICODE);

            if (!isset($existingProducts[$remoteId])) {

                $productInsert = new InsertModel($this->asProduct,
                    $this->productInsertAttributes,
                    $this->productCustomAttributes);

                $productInsert->add("date_synced", "NOW()")
                    ->add("remote_id", $remoteId)
                    ->add("remote_source", $this->getRemoteSource())
                    ->add("name", $nameJson)
                    ->add("ean", $ean)
                    ->add("code", $code)
                    ->add("catalog_code", $catalogCode)
                    ->add("meta_title", $nameJson)
                    ->add("meta_description", $nameJson)
                    ->add("description", $descriptionJson)
                    ->add("meta_keywords", $metaKeywordsJson)
                    ->add("show_on_store", $showOnStoreJson)
                    ->add("price_base", $priceBase)
                    ->add("price_retail", $priceRetail)
                    ->add("price_return", $priceReturn)
                    ->add("active", $active)
                    ->add("url", $urlJson)
                    ->add("qty", $qty)
                    ->add("qty_step", 1)
                    ->add("tax_type_id", $taxTypeId)
                    ->add("currency_id", $_ENV["DEFAULT_CURRENCY"])
                    ->add("product_type_id", CrmConstants::PRODUCT_TYPE_SIMPLE)
                    ->add("ord", 100)
                    ->add("is_visible", true)
                    ->add("template_type_id", 5)
                    ->add("auto_generate_url", true)
                    ->add("keep_url", true)
                    ->add("show_on_homepage", false)
                    ->add("model_code", $modelCode)
                    ->add("remote_modified", $remoteModified)
                    ->add("content_changed", true);

                $insertArray["product_entity"][$remoteId] = $productInsert->getArray();

                $productRemoteIds[] = $remoteId;

            } else {

                $productUpdate = new UpdateModel($existingProducts[$remoteId],
                    $this->productUpdateAttributes);

                unset($updateArray["product_entity"][$productUpdate->getEntityId()]);

                $k = array_search($productUpdate->getEntityId(), $productIds);
                if ($k !== false) {
                    unset($productIds[$k]);
                }

                /**
                 * Update name
                 */
                if (isset($this->productUpdateAttributes["name"]) &&
                    $nameArray != json_decode($existingProducts[$remoteId]["name"], true)) {
                    $productUpdate->add("name", $nameJson, false)
                        ->add("meta_title", $nameJson, false)
                        ->add("meta_description", $nameJson, false);
                }

                /**
                 * Update description
                 */
                if (isset($this->productUpdateAttributes["description"]) &&
                    $descriptionArray != json_decode($existingProducts[$remoteId]["description"], true)) {
                    $productUpdate->add("description", $descriptionJson, false);
                }

                $productUpdate->add("code", $code)
                    ->add("ean", $ean)
                    ->add("catalog_code", $catalogCode)
                    ->add("active", $active)
                    ->add("tax_type_id", $taxTypeId)
                    ->add("model_code", $modelCode)
                    ->add("remote_modified", $remoteModified)
                    ->addFloat("qty", $qty)
                    ->addFloat("price_base", $priceBase)
                    ->addFloat("price_retail", $priceRetail)
                    ->addFloat("price_return", $priceReturn);

                if (!empty($productUpdate->getArray())) {
                    $productUpdate->add("date_synced", "NOW()", false);
                    if (!empty(array_intersect(array_keys($productUpdate->getArray()), $this->triggerContentChangesArray))) {
                        $productUpdate->add("content_changed", true, false);
                    }
                    $updateArray["product_entity"][$productUpdate->getEntityId()] = $productUpdate->getArray();
                    if (!empty(array_intersect(array_keys($productUpdate->getArray()), $this->triggerChangesArray))) {
                        $productIds[] = $productUpdate->getEntityId();
                    }
                }
            }

            foreach ($productGroupArray as $level => $productGroup) {

                if (in_array($level, $this->getProductParams()->getSkipProductGroupLevels())) {
                    continue;
                }
                if (empty($productGroup["name"])) {
                    /**
                     * Ako ijedna grupa ima prazan name, njeni childovi neće se moći dodati, zato breakamo
                     */
                    break;
                }

                $productProductGroupLinkKey = $remoteId . "_" . $productGroup["code"];
                if (!isset($existingProductProductGroupLinks[$productProductGroupLinkKey])) {
                    $productProductGroupLinkInsert = new InsertModel($this->asProductProductGroupLink);
                    if (isset($existingProducts[$remoteId])) {
                        $productProductGroupLinkInsert->add("product_id", $existingProducts[$remoteId]["id"]);
                        $productIds[] = $existingProducts[$remoteId]["id"];
                    } else {
                        $productProductGroupLinkInsert->addLookup("product_id", $remoteId, "product_entity"); // remote_id
                        $productRemoteIds[] = $remoteId;
                    }
                    if (isset($existingProductGroups[$productGroup["code"]])) {
                        $productProductGroupLinkInsert->add("product_group_id", $existingProductGroups[$productGroup["code"]]["id"]);
                    } else {
                        $productProductGroupLinkInsert->addLookup("product_group_id", $productGroup["code"], "product_group_entity"); // product_group_code
                    }
                    $insertArray2["product_product_group_link_entity"][$productProductGroupLinkKey] = $productProductGroupLinkInsert;
                } else {
                    unset($deleteArray["product_product_group_link_entity"][$productProductGroupLinkKey]);
                }

                if (!isset($existingProductGroups[$productGroup["code"]]) && !isset($insertProductGroupsArray[$level][$productGroup["code"]])) {

                    $groupNameArray = [];
                    $groupMetaDescriptionArray = [];
                    $groupShowOnStoreArray = [];
                    $groupUrlArray = [];

                    foreach ($this->getStores() as $storeId) {

                        $groupNameArray[$storeId] = $productGroup["name"];
                        $groupMetaDescriptionArray[$storeId] = "";
                        $groupShowOnStoreArray[$storeId] = 1;

                        $i = 1;
                        $url = $key = $this->routeManager->prepareUrl($productGroup["name"]);
                        while (isset($existingSRoutes[$storeId . "_" . $url]) || isset($insertArray2["s_route_entity"][$storeId . "_" . $url])) {
                            $url = $key . "-" . $i++;
                        }
                        $groupUrlArray[$storeId] = $url;

                        $insertArray2["s_route_entity"][$storeId . "_" . $url] =
                            $this->getSRouteInsertEntity($url, "product_group", $storeId, $productGroup["code"]); // product_group_code
                    }

                    $groupNameJson = json_encode($groupNameArray, JSON_UNESCAPED_UNICODE);
                    $groupMetaDescriptionJson = json_encode($groupMetaDescriptionArray, JSON_UNESCAPED_UNICODE);
                    $groupUrlJson = json_encode($groupUrlArray, JSON_UNESCAPED_UNICODE);
                    $groupShowOnStoreJson = json_encode($groupShowOnStoreArray, JSON_UNESCAPED_UNICODE);

                    $productGroupInsert = new InsertModel($this->asProductGroup);
                    $productGroupInsert->add("remote_source", $this->getRemoteSource())
                        ->add("product_group_code", $productGroup["code"])
                        ->add("name", $groupNameJson)
                        ->add("meta_title", $groupNameJson)
                        ->add("meta_description", $groupMetaDescriptionJson)
                        ->add("url", $groupUrlJson)
                        ->add("template_type_id", 4)
                        ->add("level", $level)
                        ->add("show_on_store", $groupShowOnStoreJson)
                        ->add("is_active", false)
                        ->add("keep_url", true)
                        ->add("auto_generate_url", true)
                        ->add("product_group_id", NULL);

                    if (!empty($productGroup["parent"])) {
                        if (isset($existingProductGroups[$productGroup["parent"]])) {
                            $productGroupInsert->add("product_group_id", $existingProductGroups[$productGroup["parent"]]["id"]);
                        } else {
                            $productGroupInsert->addLookup("product_group_id", $productGroup["parent"], "product_group_entity"); // product_group_code
                        }
                    }

                    $insertProductGroupsArray[$level][$productGroup["code"]] = $productGroupInsert;
                }
            }

            if (!empty($this->getProductParams()->getAttributes())) {

                foreach ($this->getProductParams()->getAttributes() as $attributeKey => $attributeCustomKey) {

                    /**
                     * Get attributes directly from product
                     */
                    if (isset($d[$attributeKey]) && !empty(trim($d[$attributeKey])) &&
                        isset($d[$attributeKey . "_naziv"]) && !empty(trim($d[$attributeKey . "_naziv"])) &&
                        isset($d[$attributeKey . "_uid"])) {

                        $attributeCode = trim($d[$attributeKey]);
                        $attributeValue = trim($d[$attributeKey . "_naziv"]);

                        if ($attributeCode && $attributeValue) {
                            $configurationArray[$attributeCustomKey][$attributeCode] = [
                                "type" => "Opis",
                                "value" => $attributeValue
                            ];
                        }
                    }
                }

                if (isset($d["atributi"]) && !empty($d["atributi"])) {

                    foreach ($d["atributi"] as $d2) {

                        $attributeActive = $this->getLuceedBoolean($d2["aktivan"]);
                        if (!$attributeActive) {
                            continue;
                        }

                        $attributeVisible = $this->getLuceedBoolean($d2["vidljiv"]);
                        if (!$attributeVisible) {
                            continue;
                        }

                        $attributeKey = trim($d2["naziv"]); // is also configuration name
                        $attributeValue = trim($d2["vrijednost"]);

                        if ($attributeKey && $attributeValue && $attributeValue !== "0.00") {
                            $configurationArray[$attributeKey][$attributeValue] = [
                                "type" => trim($d2["atribut_tip"]),
                                "value" => $attributeValue
                            ];
                        }
                    }
                }
            }

            if (!empty($this->getProductParams()->getUseProductDocuments())) {

                foreach ($d["dokumenti"] as $ord => $d3) {

                    $imageId = $d3["file_uid"];

                    $data4 = $this->getLuceedProductDocumentsByProduct($imageId);
                    if (!empty($data4)) {
                        foreach ($data4 as $d4) {

                            $content = base64_decode(str_replace("\r\n", "", $d4["content"]));
                            $extension = $this->helperManager->getFileExtension($d4["filename"]);
                            $filename = $this->helperManager->getFilenameWithoutExtension($d4["filename"]);
                            $filename = $this->helperManager->nameToFilename($filename);

                            if (isset($existingProducts[$remoteId])) {
                                $file = $existingProducts[$remoteId]["id"] . "/" . $filename . "." . $extension;
                                if (!isset($existingProductImages[$file])) {
                                    $productImagesInsert = new InsertModel($this->asProductImages);
                                    $productImagesInsert->add("content", $content)
                                        ->add("file", $file)
                                        ->add("filename", $filename)
                                        ->add("file_type", $extension)
                                        ->add("selected", ($ord == 0))
                                        ->add("ord", ++$ord)
                                        ->add("is_optimised", false)
                                        ->add("file_source", $this->getRemoteSource())
                                        ->add("product_id", $existingProducts[$remoteId]["id"])
                                        ->addFunction([$this, "saveProductImage"]);
                                    $insertArray2["product_images_entity"][$remoteId . "_" . $imageId] = $productImagesInsert;
                                }
                            } else {
                                $productImagesInsert = new InsertModel($this->asProductImages);
                                $productImagesInsert->add("content", $content)
                                    ->add("filename", $filename)
                                    ->add("file_type", $extension)
                                    ->add("selected", ($ord == 0))
                                    ->add("ord", ++$ord)
                                    ->add("is_optimised", false)
                                    ->add("file_source", $this->getRemoteSource())
                                    ->addLookup("product_id", $remoteId, "product_entity")
                                    ->addFunction([$this, "getProductImageFilename"])
                                    ->addFunction([$this, "saveProductImage"]);
                                $insertArray2["product_images_entity"][$remoteId . "_" . $imageId] = $productImagesInsert;
                            }
                        }
                    }
                }
            }

            foreach ($configurationArray as $configurationName => $configurationValues) {

                $filterKey = $this->helperManager->nameToFilename($configurationName);
                if (isset($this->getProductParams()->getAttributes()[$filterKey])) {
                    $filterKey = $this->getProductParams()->getAttributes()[$filterKey];
                }

                foreach ($configurationValues as $attributeCode => $configurationData) {
                    $configurationTypeId = $this->getLuceedConfigurationType($configurationData["type"]);
                    if (!isset($existingSProductAttributeConfigurations[$filterKey])) {
                        /**
                         * Konfiguracija ne postoji, insertaj i to je to do idućeg importa
                         */
                        if (!isset($insertArray["s_product_attribute_configuration_entity"][$filterKey])) {
                            $sProductAttributeConfigurationInsert = new InsertModel($this->asSProductAttributeConfiguration);
                            $sProductAttributeConfigurationInsert->add("name", $configurationName)
                                ->add("s_product_attribute_configuration_type_id", $configurationTypeId)
                                ->add("is_active", false)
                                ->add("ord", 100)
                                ->add("show_in_filter", false)
                                ->add("show_in_list", false)
                                ->add("filter_key", $filterKey)
                                ->add("remote_source", $this->getRemoteSource());
                            $insertArray["s_product_attribute_configuration_entity"][$filterKey] = $sProductAttributeConfigurationInsert->getArray();
                        }
                    } else {
                        /**
                         * Na prvom importu insertat će se tip konfiguracije onakav kakav dođe iz luceed-a,
                         * admin zatim ima mogućnost modificirati tip prije nego što postavi konfiguraciju kao aktivnu
                         */
                        if (!empty($existingSProductAttributeConfigurations[$filterKey]["is_active"])) {
                            /**
                             * Konfiguracija je insertana, pregledana i postavljena aktivnom
                             */
                            $configurationId = $existingSProductAttributeConfigurations[$filterKey]["id"];

                            /**
                             * Iz prethodnog razloga ovdje gledamo tip koji je naveden u bazi u slučaju da je tip promijenjen
                             */
                            $configurationTypeId = $existingSProductAttributeConfigurations[$filterKey]["s_product_attribute_configuration_type_id"];
                            if ($configurationTypeId == 1 || $configurationTypeId == 2) {
                                /**
                                 * Konfiguracija je autocomplete ili multiselect, koriste se opcije
                                 */
                                $optionKey = $configurationId . "_" . md5($configurationData["value"]);
                                if (!isset($existingSProductAttributeConfigurationOptions[$optionKey])) {
                                    /**
                                     * Opcija ne postoji, linkovi ne postoje
                                     */
                                    if (!isset($insertArray2["s_product_attribute_configuration_options_entity"][$optionKey])) {
                                        $sProductAttributeConfigurationOptionsInsert = new InsertModel($this->asSProductAttributeConfigurationOptions);
                                        $sProductAttributeConfigurationOptionsInsert->add("configuration_value", $configurationData["value"])
                                            ->add("remote_source", $this->getRemoteSource())
                                            ->add("configuration_attribute_id", $configurationId);
                                        $insertArray2["s_product_attribute_configuration_options_entity"][$optionKey] = $sProductAttributeConfigurationOptionsInsert->getArray();
                                    }

                                    $linkKey = md5($remoteId . $filterKey . $configurationData["value"]);
                                    if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                        $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                        if (isset($existingProducts[$remoteId])) {
                                            $sProductAttributeLinksInsert->add("product_id", $existingProducts[$remoteId]["id"]);
                                        } else {
                                            $sProductAttributeLinksInsert->addLookup("product_id", $remoteId, "product_entity");
                                        }
                                        $sProductAttributeLinksInsert->add("s_product_attribute_configuration_id", $configurationId)
                                            ->add("attribute_value", $configurationData["value"])
                                            ->addLookup("configuration_option", $optionKey, "s_product_attribute_configuration_options_entity")
                                            ->addFunction(function ($entity) {
                                                $entity["attribute_value_key"] = md5($entity["product_id"] .
                                                    $entity["s_product_attribute_configuration_id"] .
                                                    $entity["configuration_option"]);
                                                return $entity;
                                            });
                                        $insertArray3["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert;
                                    }
                                } else {
                                    /**
                                     * Opcija postoji
                                     */
                                    $optionId = $existingSProductAttributeConfigurationOptions[$optionKey]["id"];
                                    if (!isset($existingSProductAttributeLinks[$configurationId][$optionId])) {
                                        /**
                                         * Linkovi ne postoje, dodaj sve
                                         */
                                        $linkKey = md5($remoteId . $filterKey . $configurationData["value"]);
                                        if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                            $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                            if (isset($existingProducts[$remoteId])) {
                                                $sProductAttributeLinksInsert->add("product_id", $existingProducts[$remoteId]["id"]);
                                            } else {
                                                $sProductAttributeLinksInsert->addLookup("product_id", $remoteId, "product_entity");
                                            }
                                            $sProductAttributeLinksInsert->add("s_product_attribute_configuration_id", $configurationId)
                                                ->add("attribute_value", $configurationData["value"])
                                                ->add("configuration_option", $optionId)
                                                ->addFunction(function ($entity) {
                                                    $entity["attribute_value_key"] = md5($entity["product_id"] .
                                                        $entity["s_product_attribute_configuration_id"] .
                                                        $entity["configuration_option"]);
                                                    return $entity;
                                                });
                                            $insertArray3["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert;
                                        }
                                    } else {
                                        /**
                                         * Jedan ili više linkova postoji
                                         */
                                        if (isset($existingProducts[$remoteId])) {
                                            /**
                                             * Proizvod postoji, linkovi potencijalno postoje
                                             */
                                            $attributeValueKey = md5($existingProducts[$remoteId]["id"] . $configurationId . $optionId);
                                            if (!isset($existingSProductAttributeLinks[$configurationId][$optionId][$attributeValueKey])) {
                                                /**
                                                 * Link ne postoji
                                                 */
                                                $linkKey = md5($remoteId . $filterKey . $configurationData["value"]);
                                                if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                                    $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                                    $sProductAttributeLinksInsert->add("product_id", $existingProducts[$remoteId]["id"])
                                                        ->add("s_product_attribute_configuration_id", $configurationId)
                                                        ->add("attribute_value", $configurationData["value"])
                                                        ->add("configuration_option", $optionId)
                                                        ->add("attribute_value_key", $attributeValueKey);
                                                    $insertArray3["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert->getArray();
                                                }
                                            } else {
                                                /**
                                                 * Link postoji
                                                 */
                                                unset($deleteArray["s_product_attributes_link_entity"][$attributeValueKey]);
                                                $sProductAttributeLink = $existingSProductAttributeLinks[$configurationId][$optionId][$attributeValueKey];
                                                $sProductAttributeLinksUpdate = new UpdateModel($sProductAttributeLink);
                                                $sProductAttributeLinksUpdate->add("attribute_value", $configurationData["value"]);
                                                if (!empty($sProductAttributeLinksUpdate->getArray())) {
                                                    /**
                                                     * Promijenio se attribute value (na ovoj vrsti konfiguracije ne bi se trebalo dogoditi)
                                                     */
                                                    $updateArray["s_product_attributes_link_entity"][$sProductAttributeLink["id"]] = $sProductAttributeLinksUpdate->getArray();
                                                }
                                            }
                                        } else {
                                            /**
                                             * Proizvod ne postoji, linkovi ne postoje
                                             */
                                            $linkKey = md5($remoteId . $filterKey . $configurationData["value"]);
                                            if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                                $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                                $sProductAttributeLinksInsert->addLookup("product_id", $remoteId, "product_entity")
                                                    ->add("s_product_attribute_configuration_id", $configurationId)
                                                    ->add("attribute_value", $configurationData["value"])
                                                    ->add("configuration_option", $optionId)
                                                    ->addFunction(function ($entity) {
                                                        $entity["attribute_value_key"] = md5($entity["product_id"] .
                                                            $entity["s_product_attribute_configuration_id"] .
                                                            $entity["configuration_option"]);
                                                        return $entity;
                                                    });
                                                $insertArray3["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert;
                                            }
                                        }
                                    }
                                }
                            } else {
                                $optionId = 0;
                                if (!isset($existingSProductAttributeLinks[$configurationId][$optionId])) {
                                    /**
                                     * Linkovi ne postoje, dodaj sve
                                     */
                                    $linkKey = md5($remoteId . $filterKey . $configurationData["value"]);
                                    if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                        $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                        if (isset($existingProducts[$remoteId])) {
                                            $sProductAttributeLinksInsert->add("product_id", $existingProducts[$remoteId]["id"]);
                                        } else {
                                            $sProductAttributeLinksInsert->addLookup("product_id", $remoteId, "product_entity");
                                        }
                                        $sProductAttributeLinksInsert->add("s_product_attribute_configuration_id", $configurationId)
                                            ->add("attribute_value", $configurationData["value"])
                                            ->add("configuration_option", NULL)
                                            ->addFunction(function ($entity) {
                                                $entity["attribute_value_key"] = md5($entity["product_id"] .
                                                    $entity["s_product_attribute_configuration_id"] .
                                                    $entity["configuration_option"]);
                                                return $entity;
                                            });
                                        $insertArray3["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert;
                                    }
                                } else {
                                    /**
                                     * Jedan ili više linkova postoji
                                     */
                                    if (isset($existingProducts[$remoteId])) {
                                        /**
                                         * Proizvod postoji, linkovi potencijalno postoje
                                         */
                                        $attributeValueKey = md5($existingProducts[$remoteId]["id"] . $configurationId . NULL);
                                        if (!isset($existingSProductAttributeLinks[$configurationId][$optionId][$attributeValueKey])) {
                                            /**
                                             * Link ne postoji
                                             */
                                            $linkKey = md5($remoteId . $filterKey . $configurationData["value"]);
                                            if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                                $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                                $sProductAttributeLinksInsert->add("product_id", $existingProducts[$remoteId]["id"])
                                                    ->add("s_product_attribute_configuration_id", $configurationId)
                                                    ->add("attribute_value", $configurationData["value"])
                                                    ->add("configuration_option", NULL)
                                                    ->add("attribute_value_key", $attributeValueKey);
                                                $insertArray3["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert->getArray();
                                            }
                                        } else {
                                            /**
                                             * Link postoji
                                             */
                                            unset($deleteArray["s_product_attributes_link_entity"][$attributeValueKey]);
                                            $sProductAttributeLink = $existingSProductAttributeLinks[$configurationId][$optionId][$attributeValueKey];
                                            $sProductAttributeLinksUpdate = new UpdateModel($sProductAttributeLink);
                                            $sProductAttributeLinksUpdate->add("attribute_value", $configurationData["value"]);
                                            if (!empty($sProductAttributeLinksUpdate->getArray())) {
                                                /**
                                                 * Promijenio se attribute value
                                                 */
                                                $updateArray["s_product_attributes_link_entity"][$sProductAttributeLink["id"]] = $sProductAttributeLinksUpdate->getArray();
                                            }
                                        }
                                    } else {
                                        /**
                                         * Proizvod ne postoji, linkovi ne postoje
                                         */
                                        $linkKey = md5($remoteId . $filterKey . $configurationData["value"]);
                                        if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                            $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                            $sProductAttributeLinksInsert->addLookup("product_id", $remoteId, "product_entity")
                                                ->add("s_product_attribute_configuration_id", $configurationId)
                                                ->add("attribute_value", $configurationData["value"])
                                                ->add("configuration_option", NULL)
                                                ->addFunction(function ($entity) {
                                                    $entity["attribute_value_key"] = md5($entity["product_id"] .
                                                        $entity["s_product_attribute_configuration_id"] .
                                                        $entity["configuration_option"]);
                                                    return $entity;
                                                });
                                            $insertArray3["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->finishProgressBar();

        unset($existingProducts);
        unset($existingProductGroups);
        unset($existingProductProductGroupLinks);
        unset($existingTaxTypes);
        unset($existingSRoutes);
        unset($existingSProductAttributeConfigurations);
        unset($existingSProductAttributeConfigurationOptions);
        unset($existingSProductAttributeLinks);

        $reselectArray = [];

        /**
         * Custom product group insert order implementation
         */
        if (!empty($insertProductGroupsArray)) {
            ksort($insertProductGroupsArray);
            foreach ($insertProductGroupsArray as $level => $productGroups) {
                $productGroups = $this->resolveImportArray(["product_group_entity" => $productGroups], $reselectArray);
                $this->executeInsertQuery($productGroups);
                $reselectArray["product_group_entity"] = $this->getExistingProductGroups("product_group_code", ["id"]);
            }
            unset($insertProductGroupsArray);
        }

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $reselectArray["product_entity"] = $this->getExistingProducts("remote_id", $productSelectColumns, "AND product_type_id = 1");
        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);

        $reselectArray["s_product_attribute_configuration_options_entity"] = $this->getExistingSProductAttributeConfigurationOptions(null, ["id"], ["configuration_attribute_id", "MD5(configuration_value)"]);
        $insertArray3 = $this->resolveImportArray($insertArray3, $reselectArray);
        $this->executeInsertQuery($insertArray3);
        unset($insertArray3);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        if (isset($deleteArray["product_product_group_link_entity"])) {
            foreach ($deleteArray["product_product_group_link_entity"] as $productProductGroupLink) {
                $productIds[] = $productProductGroupLink["product_id"];
            }
        }

        $this->executeDeleteQuery($deleteArray);
        unset($deleteArray);

        $ret = [];
        if (!empty($productIds)) {
            $ret["product_ids"] = $this->resolveChangedProducts($productIds, $productRemoteIds, $reselectArray["product_entity"]);
        }

        unset($reselectArray);

        $this->echo("Importing products complete\n");

        return $ret;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importWarehouses()
    {
        $this->echo("Importing warehouses...\n");

        $data = $this->getLuceedWarehouses($this->getWarehouseParams()->getWarehouseCode());

        $existingWarehouses = $this->getExistingWarehouses("remote_id", ["id", "code", "name", "address", "city_id", "country", "email", "is_active"]);
        $existingCities = $this->getExistingCities("postal_code", ["id", "country_id"]);
        $existingCountries = $this->getExistingCountries("id", ["name"], true);

        $insertArray = [
            // warehouse_entity
        ];
        $updateArray = [
            // warehouse_entity
        ];

        foreach ($existingWarehouses as $existingWarehouse) {
            if ($existingWarehouse["is_active"]) {
                $warehouseUpdate = new UpdateModel($existingWarehouse);
                $warehouseUpdate->add("is_active", false, false);
                $updateArray["warehouse_entity"][$warehouseUpdate->getEntityId()] = $warehouseUpdate->getArray();
            }
        }

        $this->startProgressBar(count($data));

        foreach ($data as $d) {

            $this->advanceProgressBar();

            $remoteId = $this->getLuceedIdFromUid($d["skladiste_uid"]);
            $code = trim($d["skladiste"]);
            $name = trim($d["naziv"]);
            $address = trim($d["adresa"]);
            $postalCode = trim($d["postanski_broj"]);
            $email = trim($d["e_mail"]);
            $cityId = $countryName = NULL;

            if (!empty($postalCode) && isset($existingCities[$postalCode])) {
                $cityId = $existingCities[$postalCode]["id"];
                if (!empty($existingCities[$postalCode]["country_id"]) && isset($existingCountries[$existingCities[$postalCode]["country_id"]]["name"])) {
                    $countryName = $existingCountries[$existingCities[$postalCode]["country_id"]]["name"];
                }
            }

            $nameArray = [];
            foreach ($this->getStores() as $storeId) {
                $nameArray[$storeId] = $name;
            }
            $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);

            if (!isset($existingWarehouses[$remoteId])) {

                $warehouseInsert = new InsertModel($this->asWarehouse,
                    $this->warehouseInsertAttributes);

                $warehouseInsert->add("remote_id", $remoteId)
                    ->add("code", $code)
                    ->add("name", $nameJson)
                    ->add("address", $address)
                    ->add("city_id", $cityId)
                    ->add("country", $countryName)
                    ->add("email", $email)
                    ->add("is_active", true);

                $insertArray["warehouse_entity"][$remoteId] = $warehouseInsert->getArray();

            } else {

                $warehouseUpdate = new UpdateModel($existingWarehouses[$remoteId],
                    $this->warehouseUpdateAttributes);

                unset($updateArray["warehouse_entity"][$warehouseUpdate->getEntityId()]);

                $warehouseUpdate->add("code", $code)
                    ->add("address", $address)
                    ->add("city_id", $cityId)
                    ->add("country", $countryName)
                    ->add("email", $email);

                if ($nameArray != json_decode($existingWarehouses[$remoteId]["name"], true)) {
                    $warehouseUpdate->add("name", $nameJson, false);
                }

                if (!empty($warehouseUpdate->getArray())) {
                    $updateArray["warehouse_entity"][$warehouseUpdate->getEntityId()] = $warehouseUpdate->getArray();
                }
            }
        }

        $this->finishProgressBar();

        unset($existingWarehouses);
        unset($existingCities);
        unset($existingCountries);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $this->echo("Importing warehouses complete\n");

        return [];
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importWarehouseStock()
    {
        $this->echo("Importing warehouse stock...\n");

        $data = $this->getLuceedWarehouseStock();

        $existingWarehouses = $this->getExistingWarehouses("remote_id", ["id"]);
        $existingProducts = $this->getExistingLuceedProducts();
        $existingProductWarehouseLinks = $this->getExistingLuceedProductWarehouseLinks();

        $insertArray = [
            // product_warehouse_link_entity
        ];
        $updateArray = [
            // product_warehouse_link_entity
        ];
        $deleteArray = [
            // product_warehouse_link_entity
        ];

        $productIds = [];

        foreach ($existingProductWarehouseLinks as $key => $existingProductWarehouseLink) {
            $deleteArray["product_warehouse_link_entity"][$key] = [
                "id" => $existingProductWarehouseLink["id"]
            ];
        }

        $this->startProgressBar(count($data));

        foreach ($data as $d) {

            $this->advanceProgressBar();

            $productRemoteId = $this->getLuceedIdFromUid($d["artikl_uid"]);
            if (!isset($existingProducts[$productRemoteId])) {
                continue;
            }

            $warehouseRemoteId = $this->getLuceedIdFromUid($d["skladiste_uid"]);
            if (!isset($existingWarehouses[$warehouseRemoteId])) {
                continue;
            }

            $qty = (int)$d["raspolozivo_kol"];
            if ($qty <= 0) {
                continue;
            }

            $productWarehouseLinkKey = $existingProducts[$productRemoteId]["id"] . "_" . $existingWarehouses[$warehouseRemoteId]["id"];
            unset($deleteArray["product_warehouse_link_entity"][$productWarehouseLinkKey]);

            if (!isset($existingProductWarehouseLinks[$productWarehouseLinkKey])) {
                $productWarehouseLinkInsert = new InsertModel($this->asProductWarehouseLink);
                $productWarehouseLinkInsert->add("product_id", $existingProducts[$productRemoteId]["id"])
                    ->add("warehouse_id", $existingWarehouses[$warehouseRemoteId]["id"])
                    ->add("qty", $qty);
                $insertArray["product_warehouse_link_entity"][$productWarehouseLinkKey] = $productWarehouseLinkInsert->getArray();
                $productIds[] = $existingProducts[$productRemoteId]["id"];
            } else {
                $productWarehouseLinkUpdate = new UpdateModel($existingProductWarehouseLinks[$productWarehouseLinkKey]);
                $productWarehouseLinkUpdate->addFloat("qty", $qty);
                if (!empty($productWarehouseLinkUpdate->getArray())) {
                    $updateArray["product_warehouse_link_entity"][$productWarehouseLinkUpdate->getEntityId()] = $productWarehouseLinkUpdate->getArray();
                    $productIds[] = $existingProducts[$productRemoteId]["id"];
                }
            }
        }

        $this->finishProgressBar();

        unset($existingWarehouses);
        unset($existingProducts);
        unset($existingProductWarehouseLinks);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $this->executeDeleteQuery($deleteArray);
        unset($deleteArray);

        $ret = [];
        if (!empty($productIds)) {
            $ret["product_ids"] = array_unique($productIds);
        }

        $this->echo("Importing warehouse stock complete\n");

        return $ret;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importDiscounts()
    {
        $this->echo("Importing discounts...\n");

        $data = $this->getLuceedDiscounts();

        $productSelectColumns = [
            "id",
            "code",
            "active",
            "discount_price_base",
            "discount_price_retail",
            "discount_percentage",
            "discount_percentage_base",
            "price_base",
            "price_retail",
            "date_discount_from",
            "date_discount_to",
            "discount_diff",
            "discount_diff_base",
            "date_discount_base_from",
            "date_discount_base_to",
            "discount_type",
            "discount_type_base"
        ];

        $existingProducts = $this->getEntitiesArray($productSelectColumns, "product_entity", ["code"], "", "WHERE a1.entity_state_id = 1");

        $updateArray = [
            // product_entity
        ];

        $productIds = [];
        $discountsByProductCode = [];

        foreach ($existingProducts as $existingProduct) {
            if ($existingProduct["active"]) {
                $productUpdate = new UpdateModel($existingProduct);
                $productUpdate->addFloat("discount_price_base", 0.0)
                    ->addFloat("discount_price_retail", 0.0)
                    ->addFloat("discount_percentage", 0.0)
                    ->addFloat("discount_percentage_base", 0.0)
                    ->add("date_discount_from", NULL)
                    ->add("date_discount_to", NULL)
                    ->addFloat("discount_diff", 0.0)
                    ->addFloat("discount_diff_base", 0.0)
                    ->addFloat("date_discount_base_from", 0.0)
                    ->addFloat("date_discount_base_to", 0.0)
                    ->add("discount_type", 0)
                    ->add("discount_type_base", 0);

                if (!empty($productUpdate->getArray())) {
                    $productUpdate->add("date_synced", "NOW()", false);
                    $updateArray["product_entity"][$productUpdate->getEntityId()] = $productUpdate->getArray();
                    $productIds[] = $productUpdate->getEntityId();
                }
            }
        }

        $this->startProgressBar(count($data));

        foreach ($data as $d) {

            $this->advanceProgressBar();

            /**
             * Preskoči ako nema detalja o popustu
             */
            if (!isset($d["stavke"]) || empty($d["stavke"])) {
                continue;
            }
            /**
             * Preskoči ako poslovne jedinice nisu navedene ili prva i jedina poslovna jedinica nije WEB (tražimo samo WEB akcije)
             */
            if (!isset($d["poslovne_jedinice"][0]["pj"]) || count($d["poslovne_jedinice"]) != 1 || $d["poslovne_jedinice"][0]["pj"] != "WEB") {
                continue;
            }

            foreach ($d["stavke"] as $d2) {

                $productCode = trim($d2["artikl"]);
                $productGroupCode = urlencode(trim($d2["grupa_artikla"]));
                $discountInfo = [
                    "start_date" => $d["start_date"],
                    "end_date" => $d["end_date"],
                    "start_time" => $d["start_time"],
                    "end_time" => $d["end_time"],
                    "status" => $d["status"],
                    "vpc_rabat" => (float)$d2["vpc_rabat"],
                    "mpc_rabat" => (float)$d2["mpc_rabat"]
                ];

                if (!empty($productCode)) {
                    /**
                     * Popust na proizvodu
                     */
                    $discountsByProductCode[$productCode] = $discountInfo;
                } else if (!empty($productGroupCode)) {
                    /**
                     * Popust na grupi proizvoda/brandu
                     */
                    $data3 = $this->getLuceedProductsByGroup($productGroupCode);
                    if (!empty($data3)) {
                        foreach ($data3 as $d3) {
                            $productCode2 = trim($d3["artikl"]);
                            /**
                             * Popust na proizvodu ima prioritet prema popustu na grupi
                             * i zato pazimo da popust sa grupe ne pregazi popust na proizvodu
                             */
                            if (!isset($discountsByProductCode[$productCode2])) {
                                $discountsByProductCode[$productCode2] = $discountInfo;
                            }
                        }
                    }
                }
            }
        }

        $this->finishProgressBar();

        $this->echo("\nApplying discounts to products...");

        $this->startProgressBar(count($discountsByProductCode));

        foreach ($discountsByProductCode as $productCode => $discountInfo) {

            $this->advanceProgressBar();

            if (!isset($existingProducts[$productCode]) || $existingProducts[$productCode]["active"] != 1) {
                continue;
            }

            $priceBase = $existingProducts[$productCode]["price_base"]; // vpc
            $priceRetail = $existingProducts[$productCode]["price_retail"]; // mpc

            $discountPercentageBase = $discountInfo["vpc_rabat"];
            $discountPercentage = $discountInfo["mpc_rabat"];

            $discountPriceBase = $this->calculateDiscountPrice($discountPercentageBase, $priceBase); // vpc
            $discountPriceRetail = $this->calculateDiscountPrice($discountPercentage, $priceRetail); // mpc

            $dateDiscountFrom = $this->calculateDiscountDate($discountInfo["start_date"], $discountInfo["start_time"]);
            $dateDiscountTo = $this->calculateDiscountDate($discountInfo["end_date"], $discountInfo["end_time"]);

            $productUpdate = new UpdateModel($existingProducts[$productCode]);

            unset($updateArray["product_entity"][$productUpdate->getEntityId()]);

            $k = array_search($productUpdate->getEntityId(), $productIds);
            if ($k !== false) {
                unset($productIds[$k]);
            }

            /**
             * 01: New
             * 02: Approved
             * 03: Finished
             * 99: Cancelled
             */
            if ($discountInfo["status"] == "01" || $discountInfo["status"] == "02") {
                $productUpdate->addFloat("discount_percentage", $discountPercentage)
                    ->addFloat("discount_price_retail", $discountPriceRetail)
                    ->addFloat("discount_percentage_base", $discountPercentageBase)
                    ->addFloat("discount_price_base", $discountPriceBase)
                    ->add("date_discount_from", $dateDiscountFrom)
                    ->add("date_discount_to", $dateDiscountTo);
            } else {
                if (!empty($existingProducts[$productCode]["discount_percentage"])) {
                    $productUpdate->addFloat("discount_percentage", NULL, false)
                        ->addFloat("discount_price_retail", NULL, false)
                        ->add("date_discount_from", NULL, false)
                        ->add("date_discount_to", NULL, false);
                }
                if (!empty($existingProducts[$productCode]["discount_percentage_base"])) {
                    $productUpdate->addFloat("discount_percentage_base", NULL, false)
                        ->addFloat("discount_price_base", NULL, false);
                }
            }

            if (!empty($productUpdate->getArray())) {
                $productUpdate->add("date_synced", "NOW()", false);
                $updateArray["product_entity"][$productUpdate->getEntityId()] = $productUpdate->getArray();
                $productIds[] = $productUpdate->getEntityId();
            }
        }

        $this->finishProgressBar();

        unset($existingProducts);
        unset($discountsByProductCode);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $ret = [];
        if (!empty($productIds)) {
            $ret["product_ids"] = array_unique($productIds);
        }

        $this->echo("Importing discounts complete\n");

        return $ret;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importProductGroups()
    {
        $this->echo("Importing product groups...\n");

        $data2 = $this->getLuceedProductGroups();

        $existingSRoutes = $this->getExistingSRoutes();
        $existingProductGroups = $this->getExistingProductGroups("product_group_code", ["id", "remote_id"]);

        $insertArray2 = [
            // s_route_entity
        ];
        $updateArray = [
            // product_group_entity
        ];
        $insertProductGroupsArray = [];

        $this->startProgressBar(count($data2));

        foreach ($data2 as $d2) {

            $this->advanceProgressBar();

            $productGroupArray = [];
            $currentGroup = $d2;

            while (!empty($currentGroup)) {
                array_unshift($productGroupArray, $currentGroup);
                if (isset($data2[$currentGroup["parent"]])) {
                    $currentGroup = $data2[$currentGroup["parent"]];
                } else {
                    $currentGroup = [];
                }
            }

            foreach ($productGroupArray as $level => $productGroup) {

                if (in_array($level, $this->getProductParams()->getSkipProductGroupLevels())) {
                    continue;
                }
                if (empty($productGroup["name"])) {
                    /**
                     * Ako ijedna grupa ima prazan name, njeni childovi neće se moći dodati, zato breakamo
                     */
                    break;
                }

                if (!isset($existingProductGroups[$productGroup["code"]])) {

                    if (!isset($insertProductGroupsArray[$level][$productGroup["code"]])) {

                        $groupNameArray = [];
                        $groupMetaDescriptionArray = [];
                        $groupShowOnStoreArray = [];
                        $groupUrlArray = [];

                        foreach ($this->getStores() as $storeId) {

                            $groupNameArray[$storeId] = $productGroup["name"];
                            $groupMetaDescriptionArray[$storeId] = "";
                            $groupShowOnStoreArray[$storeId] = 1;

                            $i = 1;
                            $url = $key = $this->routeManager->prepareUrl($productGroup["name"]);
                            while (isset($existingSRoutes[$storeId . "_" . $url]) || isset($insertArray2["s_route_entity"][$storeId . "_" . $url])) {
                                $url = $key . "-" . $i++;
                            }
                            $groupUrlArray[$storeId] = $url;

                            $insertArray2["s_route_entity"][$storeId . "_" . $url] =
                                $this->getSRouteInsertEntity($url, "product_group", $storeId, $productGroup["code"]); // product_group_code
                        }

                        $groupNameJson = json_encode($groupNameArray, JSON_UNESCAPED_UNICODE);
                        $groupMetaDescriptionJson = json_encode($groupMetaDescriptionArray, JSON_UNESCAPED_UNICODE);
                        $groupUrlJson = json_encode($groupUrlArray, JSON_UNESCAPED_UNICODE);
                        $groupShowOnStoreJson = json_encode($groupShowOnStoreArray, JSON_UNESCAPED_UNICODE);

                        $productGroupInsert = new InsertModel($this->asProductGroup);
                        $productGroupInsert->add("remote_source", $this->getRemoteSource())
                            ->add("remote_id", $productGroup["remote_id"])
                            ->add("product_group_code", $productGroup["code"])
                            ->add("name", $groupNameJson)
                            ->add("meta_title", $groupNameJson)
                            ->add("meta_description", $groupMetaDescriptionJson)
                            ->add("url", $groupUrlJson)
                            ->add("template_type_id", 4)
                            ->add("level", $level)
                            ->add("show_on_store", $groupShowOnStoreJson)
                            ->add("is_active", false)
                            ->add("keep_url", true)
                            ->add("auto_generate_url", true)
                            ->add("product_group_id", NULL);

                        if (!empty($productGroup["parent"])) {
                            if (isset($existingProductGroups[$productGroup["parent"]])) {
                                $productGroupInsert->add("product_group_id", $existingProductGroups[$productGroup["parent"]]["id"]);
                            } else {
                                $productGroupInsert->addLookup("product_group_id", $productGroup["parent"], "product_group_entity"); // product_group_code
                            }
                        }

                        $insertProductGroupsArray[$level][$productGroup["code"]] = $productGroupInsert;
                    }

                } else {

                    $productGroupUpdate = new UpdateModel($existingProductGroups[$productGroup["code"]]);
                    $productGroupUpdate->add("remote_id", $productGroup["remote_id"]);

                    if (!empty($productGroupUpdate->getArray())) {
                        $updateArray["product_group_entity"][$productGroupUpdate->getEntityId()] = $productGroupUpdate->getArray();
                    }
                }
            }
        }

        $this->finishProgressBar();

        unset($existingSRoutes);
        unset($existingProductGroups);

        $reselectArray = [];

        /**
         * Custom product group insert order implementation
         */
        if (!empty($insertProductGroupsArray)) {
            ksort($insertProductGroupsArray);
            foreach ($insertProductGroupsArray as $level => $productGroups) {
                $productGroups = $this->resolveImportArray(["product_group_entity" => $productGroups], $reselectArray);
                $this->executeInsertQuery($productGroups);
                $reselectArray["product_group_entity"] = $this->getExistingProductGroups("product_group_code", ["id"]);
            }
            unset($insertProductGroupsArray);
        }

        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $this->echo("Importing product groups complete\n");

        return [];
    }

    const GET_ACCOUNTS = 0;
    const GET_CONTACTS_AND_ADDRESSES = 1;

    /**
     * @return array
     * @throws \Exception
     */
    public function importAccounts()
    {
        $this->echo("Importing accounts...\n");

        $data = $this->getLuceedAccounts();

        $existingAccounts = $this->getExistingLuceedAccounts();
        $existingAccountsByEmail = $this->getExistingLuceedAccounts("email");
        $existingContacts = $this->getExistingLuceedContacts();
        $existingContactsByEmail = $this->getExistingLuceedContacts("email");
        $existingAddresses = $this->getExistingLuceedAddresses();
        $existingCities = $this->getExistingLuceedCities();

        $insertArray = [
            // account_entity
        ];
        $insertArray2 = [
            // contact_entity
        ];
        $insertArray3 = [
            // address_entity
        ];
        $updateArray = [
            // contact_entity
        ];

        $usedAccountEmails = [];
        $usedContactEmails = [];

        $this->startProgressBar(count($data));

        for ($importType = self::GET_ACCOUNTS; $importType <= self::GET_CONTACTS_AND_ADDRESSES; $importType++) {

            foreach ($data as $d) {

                $this->advanceProgressBar();

                $remoteId = $this->getLuceedIdFromUid($d["partner_uid"]);
                $code = $d["partner"];
                $name = $d["naziv"];
                $firstName = $d["ime"];
                $lastName = $d["prezime"];
                $active = $this->getLuceedBoolean($d["enabled"]);
                $isLegalEntity = $this->getLuceedIsLegalEntity($d["tip_komitenta"]);
                $address = $d["adresa"];
                $oib = $d["oib"];
                $mbr = $d["maticni_broj"];
                $cityName = $d["naziv_mjesta"];
                $postalCode = $d["postanski_broj"];
                $countryCode = $d["drzava"];
                $email = $d["e_mail"];
                $contactName = $d["kontakt_osoba"];
                $parentCode = $d["grupacija"];
                $partnerGroup = $d["grupa_partnera"];

                if (empty($name) && empty($firstName)) {
                    continue;
                }
                if (empty($cityName) || empty($postalCode) || empty($countryCode)) {
                    continue;
                }

                $cityCode = $cityName . "_" . $postalCode . "_" . $countryCode;
                if (!isset($existingCities[$cityCode])) {
                    continue;
                }

                $isAccount = false;
                if (empty($parentCode) && $partnerGroup == "B2B" && $isLegalEntity && !empty($email) /* && !empty($oib) && !empty($contactName)*/) {
                    $isAccount = true;
                }

                $checkCode = $code;
                if (!$isAccount) {
                    $checkCode = $parentCode;
                }

                if ($importType == self::GET_ACCOUNTS && $isAccount) {

                    if (!isset($existingAccounts[$code])) {

                        if (isset($existingAccountsByEmail[$email]) || in_array($email, $usedAccountEmails)) {
                            continue;
                        }

                        $accountInsert = new InsertModel($this->asAccount);
                        $accountInsert->add("code", $code)
                            ->add("remote_id", $remoteId)
                            ->add("name", $name)
                            ->add("oib", $oib)
                            ->add("mbr", $mbr)
                            ->add("first_name", $firstName)
                            ->add("last_name", $lastName)
                            ->add("email", $email)
                            ->add("is_legal_entity", $isLegalEntity)
                            ->add("is_active", $active);

                        $insertArray["account_entity"][$code] = $accountInsert->getArray();
                        $usedAccountEmails[] = $email;

                    } else {

                        if ((isset($existingAccountsByEmail[$email]) && $existingAccountsByEmail[$email]["code"] != $code) || in_array($email, $usedAccountEmails)) {
                            continue;
                        }

                        $accountUpdate = new UpdateModel($existingAccounts[$code]);
                        $accountUpdate->add("name", $name)
                            ->add("oib", $oib)
                            ->add("mbr", $mbr)
                            ->add("first_name", $firstName)
                            ->add("last_name", $lastName)
                            ->add("email", $email)
                            ->add("is_legal_entity", $isLegalEntity)
                            ->add("is_active", $active);

                        if (!empty($accountUpdate->getArray())) {
                            $updateArray["account_entity"][$accountUpdate->getEntityId()] = $accountUpdate->getArray();
                            $usedAccountEmails[] = $email;
                        }
                    }
                }

                if ($importType == self::GET_CONTACTS_AND_ADDRESSES &&
                    (isset($insertArray["account_entity"][$checkCode]) || isset($existingAccounts[$checkCode]))) {

                    if (isset($existingContactsByEmail[$email]) || in_array($email, $usedContactEmails)) {
                        continue;
                    }

                    if (!isset($existingContacts[$code])) {

                        $contactInsert = new InsertModel($this->asContact);
                        $contactInsert->add("code", $code)
                            ->add("remote_id", $remoteId)
                            ->add("email", $email)
                            ->add("first_name", $firstName)
                            ->add("last_name", $lastName)
                            ->add("full_name", $contactName)
                            ->add("is_active", $active)
                            ->add("account_id", null);

                        if (!isset($existingAccounts[$checkCode])) {
                            $contactInsert->addLookup("account_id", $checkCode, "account_entity");
                        } else {
                            $contactInsert->add("account_id", $existingAccounts[$checkCode]["id"]);
                        }

                        $insertArray2["contact_entity"][$code] = $contactInsert;
                        $usedContactEmails[] = $email;

                    } else {

                        // Don't update email on contacts
//                        if ((isset($existingContactsByEmail[$email]) && $existingContactsByEmail[$email]["code"] != $code) || in_array($email, $usedContactEmails)) {
//                            continue;
//                        }

                        $contactUpdate = new UpdateModel($existingContacts[$code]);
                        $contactUpdate
                            //->add("email", $email)
                            ->add("first_name", $firstName)
                            ->add("last_name", $lastName)
                            ->add("full_name", $contactName)
                            ->add("is_active", $active);

                        if (!empty($contactUpdate->getArray())) {
                            $updateArray["contact_entity"][$contactUpdate->getEntityId()] = $contactUpdate;
                            //$usedContactEmails[] = $email;
                        }
                    }

                    if (!isset($existingAddresses[$code])) {

                        $addressInsert = new InsertModel($this->asAddress);
                        $addressInsert->add("code", $code)
                            ->add("street", $address)
                            ->add("account_id", null)
                            ->add("city_id", $existingCities[$cityCode]["id"])
                            ->add("headquarters", false)
                            ->add("billing", false);

                        $addressInsert->add("headquarters", true);
                        if (!isset($existingAccounts[$checkCode])) {
                            $addressInsert->addLookup("account_id", $checkCode, "account_entity");
                        } else {
                            $addressInsert->add("account_id", $existingAccounts[$checkCode]["id"]);
                        }
                        if (!isset($existingContacts[$code])) {
                            $addressInsert->addLookup("contact_id", $code, "contact_entity");
                        } else {
                            $addressInsert->add("contact_id", $existingContacts[$code]["id"]);
                        }

                        $insertArray3["address_entity"][$code] = $addressInsert;

                    } else {

                        $addressUpdate = new UpdateModel($existingAddresses[$code]);
                        $addressUpdate->add("street", $address);

                        if (!empty($addressUpdate->getArray())) {
                            $updateArray["address_entity"][$addressUpdate->getEntityId()] = $addressUpdate->getArray();
                        }
                    }
                }
            }
        }

        $this->finishProgressBar();

        unset($existingAccounts);
        unset($existingAccountsByEmail);
        unset($existingContacts);
        unset($existingContactsByEmail);
        unset($existingAddresses);
        unset($existingCities);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $reselectArray = [];
        $reselectArray["account_entity"] = $this->getExistingLuceedAccounts();

        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);

        $reselectArray["contact_entity"] = $this->getExistingLuceedContacts();

        $insertArray3 = $this->resolveImportArray($insertArray3, $reselectArray);
        $this->executeInsertQuery($insertArray3);
        unset($insertArray3);

        $updateArray = $this->resolveImportArray($updateArray, $reselectArray);
        $this->executeUpdateQuery($updateArray);
        unset($updateArray);
        unset($reselectArray);

        $this->echo("Importing accounts complete\n");

        // TODO: Generate users based on contacts

        return [];
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importPrices()
    {
        $this->echo("Importing prices...\n");

        $q = "TRUNCATE TABLE product_account_price_staging;";
        $this->databaseContext->executeNonQuery($q);

        // $q = "TRUNCATE TABLE product_account_group_price_staging;";
        // $this->databaseContext->executeNonQuery($q);

        $existingAccounts = $this->getExistingLuceedAccounts();

        $data = [];

        // Make requests for 500 partner ID's at a time
        $partnerIds = array_chunk(array_keys($existingAccounts), 500);
        foreach ($partnerIds as $pIds) {
            $data = array_merge($data, $this->getLuceedPrices($pIds));
        }

        $existingProducts = $this->getExistingLuceedProducts();
        $existingProductGroups = $this->getExistingLuceedProductGroups();
        $existingProductsByProductGroups = $this->getExistingLuceedProductsByProductGroups();

        $insertProductAccountPriceStagingArray = [];

        $this->startProgressBar(count($data));

        foreach ($data as $d) {

            $this->advanceProgressBar();

            $accountCode = $d["partner"];
            if (!isset($existingAccounts[$accountCode])) {
                continue;
            }

            $accountId = $existingAccounts[$accountCode]["id"];

            if (isset($d["rabati"])) {
                foreach ($d["rabati"] as $d2) {

                    $dateFrom = $d2["od_datuma"] ? \DateTime::createFromFormat("d.m.Y", $d2["od_datuma"])->format("Y-m-d H:i:s") : null;
                    $dateTo = $d2["do_datuma"] ? \DateTime::createFromFormat("d.m.Y", $d2["do_datuma"])->format("Y-m-d H:i:s") : null;
                    $rebatePercent = $d2["rabat"];

                    if (!empty($d2["artikl_uid"])) {

                        /**
                         * Rabat na pojedini proizvod
                         */
                        $productRemoteId = $this->getLuceedIdFromUid($d2["artikl_uid"]);
                        if (!isset($existingProducts[$productRemoteId])) {
                            continue;
                        }

                        $productId = $existingProducts[$productRemoteId]["id"];

                        $insertProductAccountPriceStagingArray[$accountId][$productId] = [
                            "product_id" => $productId,
                            "account_id" => $accountId,
                            "price_base" => null,
                            "rebate" => $rebatePercent,
                            "date_valid_from" => $dateFrom,
                            "date_valid_to" => $dateTo
                        ];

                    } else if (!empty($d2["grupa_artikla_uid"])) {

                        /**
                         * Rabat na grupu proizvoda
                         */
                        $productGroupRemoteId = $this->getLuceedIdFromUid($d2["grupa_artikla_uid"]);
                        if (!isset($existingProductGroups[$productGroupRemoteId])) {
                            continue;
                        }

                        $productGroupId = $existingProductGroups[$productGroupRemoteId]["id"];

                        foreach ($existingProductsByProductGroups[$productGroupId] as $productId) {
                            if (!isset($insertProductAccountPriceStagingArray[$accountId][$productId])) {
                                $insertProductAccountPriceStagingArray[$accountId][$productId] = [
                                    "product_id" => $productId,
                                    "account_id" => $accountId,
                                    "price_base" => null,
                                    "rebate" => $rebatePercent,
                                    "date_valid_from" => $dateFrom,
                                    "date_valid_to" => $dateTo
                                ];
                            }
                        }
                    } else {
                        continue;
                    }
                }
            }

            if (isset($d["cijene"])) {
                foreach ($d["cijene"] as $d3) {

                    $productRemoteId = $this->getLuceedIdFromUid($d3["artikl_uid"]);
                    if (!isset($existingProducts[$productRemoteId])) {
                        continue;
                    }

                    $productId = $existingProducts[$productRemoteId]["id"];

                    $dateFrom = $d3["od_datuma"] ? \DateTime::createFromFormat("d.m.Y", $d3["od_datuma"])->format("Y-m-d H:i:s") : null;
                    $dateTo = $d3["do_datuma"] ? \DateTime::createFromFormat("d.m.Y", $d3["do_datuma"])->format("Y-m-d H:i:s") : null;
                    $priceBase = $d3["cijena"];

                    $insertProductAccountPriceStagingArray[$accountId][$productId] = [
                        "product_id" => $productId,
                        "account_id" => $accountId,
                        "price_base" => $priceBase,
                        "rebate" => null,
                        "date_valid_from" => $dateFrom,
                        "date_valid_to" => $dateTo
                    ];
                }
            }
        }

        $this->finishProgressBar();

        unset($existingProducts);
        unset($existingProductGroups);
        unset($existingProductsByProductGroups);

        $productIds = [];

        if (!empty($insertProductAccountPriceStagingArray)) {
            foreach ($insertProductAccountPriceStagingArray as $productAccountPriceStaging) {
                $productIds = array_merge($productIds, array_keys($productAccountPriceStaging));
                $productAccountPriceStaging = ["product_account_price_staging" => $productAccountPriceStaging];
                $this->executeInsertQuery($productAccountPriceStaging);
            }
            unset($insertProductAccountPriceStagingArray);
        }

        $q = "CALL sp_import_partner_rabats();";
        $this->databaseContext->executeNonQuery($q);

        // $q = "CALL sp_import_account_groups_rabats();";
        // $this->databaseContext->executeNonQuery($q);

        $ret = [];
        if (!empty($productIds)) {
            $ret["product_ids"] = array_unique($productIds);
        }

        $this->echo("Importing prices complete\n");

        return $ret;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importSupplierWarehouseStock()
    {
        $this->echo("Importing supplier warehouse stock...\n");

        $data = $this->getLuceedSupplierWarehouseStock();

        $suppliersWarehouse = $this->getExistingLuceedSuppliersWarehouse();
        if (empty($suppliersWarehouse)) {
            throw new \Exception("Warehouse with the code 'luceed_suppliers' does not exist");
        }
        $suppliersWarehouseId = $suppliersWarehouse["id"];

        $existingProducts = $this->getExistingLuceedProductsByCode();
        $existingProductWarehouseLinks = $this->getExistingLuceedSupplierProductWarehouseLinks();

        $insertArray = [
            // product_warehouse_link_entity
        ];
        $updateArray = [
            // product_warehouse_link_entity
        ];
        $deleteArray = [
            // product_warehouse_link_entity
        ];

        $productIds = [];

        foreach ($existingProductWarehouseLinks as $key => $existingProductWarehouseLink) {
            $deleteArray["product_warehouse_link_entity"][$key] = [
                "id" => $existingProductWarehouseLink["id"]
            ];
        }

        $this->startProgressBar(count($data));

        foreach ($data as $productCode => $qty) {

            $this->advanceProgressBar();

            if (!isset($existingProducts[$productCode])) {
                continue;
            }

            $productWarehouseLinkKey = $existingProducts[$productCode]["id"] . "_" . $suppliersWarehouseId;
            unset($deleteArray["product_warehouse_link_entity"][$productWarehouseLinkKey]);

            if (!isset($existingProductWarehouseLinks[$productWarehouseLinkKey])) {
                $productWarehouseLinkInsert = new InsertModel($this->asProductWarehouseLink);
                $productWarehouseLinkInsert->add("product_id", $existingProducts[$productCode]["id"])
                    ->add("warehouse_id", $suppliersWarehouseId)
                    ->add("qty", $qty);
                $insertArray["product_warehouse_link_entity"][$productWarehouseLinkKey] = $productWarehouseLinkInsert->getArray();
                $productIds[] = $existingProducts[$productCode]["id"];
            } else {
                $productWarehouseLinkUpdate = new UpdateModel($existingProductWarehouseLinks[$productWarehouseLinkKey]);
                $productWarehouseLinkUpdate->addFloat("qty", $qty);
                if (!empty($productWarehouseLinkUpdate->getArray())) {
                    $updateArray["product_warehouse_link_entity"][$productWarehouseLinkUpdate->getEntityId()] = $productWarehouseLinkUpdate->getArray();
                    $productIds[] = $existingProducts[$productCode]["id"];
                }
            }
        }

        $this->finishProgressBar();

        unset($existingWarehouses);
        unset($existingProducts);
        unset($existingProductWarehouseLinks);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $this->executeDeleteQuery($deleteArray);
        unset($deleteArray);

        $ret = [];
        if (!empty($productIds)) {
            $ret["product_ids"] = array_unique($productIds);
        }

        $this->echo("Importing supplier warehouse stock complete\n");

        return $ret;
    }

    /**
     * @param string $sortKey
     * @return array
     */
    private function getExistingLuceedAccounts($sortKey = "code")
    {
        return $this->getEntitiesArray(["id", "name", "oib", "mbr", "first_name", "last_name", "email", "is_legal_entity", "is_active", "code"], "account_entity", [$sortKey], "", "WHERE entity_state_id = 1 AND " . $sortKey . " IS NOT NULL AND " . $sortKey . " != ''");
    }

    /**
     * @param string $sortKey
     * @return array
     */
    private function getExistingLuceedContacts($sortKey = "code")
    {
        return $this->getEntitiesArray(["id", "email", "first_name", "last_name", "full_name", "is_active", "code"], "contact_entity", [$sortKey], "", "WHERE entity_state_id = 1 AND " . $sortKey . " IS NOT NULL AND " . $sortKey . " != ''");
    }

    /**
     * @return array
     */
    private function getExistingLuceedAddresses()
    {
        return $this->getEntitiesArray(["id", "street", "code"], "address_entity", ["code"], "", "WHERE entity_state_id = 1 AND code IS NOT NULL AND code != ''");
    }

    /**
     * @return array
     */
    private function getExistingLuceedCities()
    {
        return $this->getEntitiesArray(["a1.id", "a1.name", "a1.postal_code", "a2.code"], "city_entity", ["name", "postal_code", "code"], "JOIN country_entity a2 ON a1.country_id = a2.id", "WHERE a1.entity_state_id = 1 AND a1.postal_code IS NOT NULL AND a1.postal_code != ''");
    }

    /**
     * @return array
     */
    private function getExistingLuceedProducts()
    {
        return $this->getEntitiesArray(["id", "remote_id"], "product_entity", ["remote_id"], "", "WHERE entity_state_id = 1 AND product_type_id = 1 AND remote_id IS NOT NULL AND remote_id != '' AND remote_source = 'luceed_erp'");
    }

    /**
     * @return array
     */
    private function getExistingLuceedProductsByCode()
    {
        return $this->getEntitiesArray(["id", "code"], "product_entity", ["code"], "", "WHERE entity_state_id = 1 AND product_type_id = 1 AND code IS NOT NULL AND code != '' AND remote_source = 'luceed_erp'");
    }

    /**
     * @return array
     */
    private function getExistingLuceedProductGroups()
    {
        return $this->getEntitiesArray(["id", "remote_id"], "product_group_entity", ["remote_id"], "", "WHERE entity_state_id = 1 AND remote_source = 'luceed_erp'");
    }

    /**
     * @return array
     */
    private function getExistingLuceedProductsByProductGroups()
    {
        $q = "SELECT product_id, product_group_id FROM product_product_group_link_entity;";

        $data = $this->databaseContext->getAll($q);

        $ret = [];

        foreach ($data as $d) {
            $ret[$d["product_group_id"]][$d["product_id"]] = $d["product_id"];
        }

        return $ret;
    }

    /**
     * @return array
     */
    private function getExistingLuceedWarehouses()
    {
        return $this->getEntitiesArray(["id", "remote_id"], "warehouse_entity", ["remote_id"], "", "WHERE entity_state_id = 1 AND remote_id IS NOT NULL AND remote_id != ''");
    }

    /**
     * @return array
     */
    private function getExistingLuceedWarehousesByCode()
    {
        return $this->getEntitiesArray(["id", "code"], "warehouse_entity", ["code"], "", "WHERE entity_state_id = 1 AND code IS NOT NULL AND code != ''");
    }

    /**
     * @return false|mixed
     */
    private function getExistingLuceedSuppliersWarehouse()
    {
        $q = "SELECT id FROM warehouse_entity WHERE entity_state_id = 1 AND code = '" . self::SUPPLIER_WAREHOUSE_CODE . "';";

        return $this->databaseContext->getSingleEntity($q);
    }

    /**
     * @return array
     */
    private function getExistingLuceedProductWarehouseLinks()
    {
        $q = "SELECT
                pwl.id,
                pwl.product_id,
                pwl.warehouse_id,
                pwl.qty
            FROM product_warehouse_link_entity pwl
            JOIN product_entity p ON pwl.product_id = p.id
            JOIN warehouse_entity w ON pwl.warehouse_id = w.id
            WHERE p.remote_source = 'luceed_erp'
            AND w.code != 'luceed_suppliers';";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["product_id"] . "_" . $d["warehouse_id"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    private function getExistingLuceedSupplierProductWarehouseLinks()
    {
        $q = "SELECT
                pwl.id,
                pwl.product_id,
                pwl.warehouse_id,
                pwl.qty
            FROM product_warehouse_link_entity pwl
            JOIN product_entity p ON pwl.product_id = p.id
            JOIN warehouse_entity w ON pwl.warehouse_id = w.id
            WHERE p.remote_source = 'luceed_erp'
            AND w.code = 'luceed_suppliers';";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["product_id"] . "_" . $d["warehouse_id"]] = $d;
        }

        return $ret;
    }
}
