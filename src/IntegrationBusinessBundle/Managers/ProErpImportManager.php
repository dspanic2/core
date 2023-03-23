<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\RestManager;
use AppBundle\Models\InsertModel;
use AppBundle\Models\UpdateModel;
use CrmBusinessBundle\Constants\CrmConstants;
use IntegrationBusinessBundle\Models\ProductGroupModel;
use Symfony\Component\Console\Helper\ProgressBar;

class ProErpImportManager extends DefaultIntegrationImportManager
{
    private $apiUrl;
    private $apiToken;
    private $fixedQty;

    /** @var AttributeSet $asSRoute */
    protected $asSRoute;
    /** @var AttributeSet $asProduct */
    protected $asProduct;
    /** @var AttributeSet $asProductProductGroupLink */
    protected $asProductProductGroupLink;
    /** @var AttributeSet $asProductGroup */
    protected $asProductGroup;
    /** @var AttributeSet $asSProductAttributeConfiguration */
    protected $asSProductAttributeConfiguration;
    /** @var AttributeSet $asSProductAttributeConfigurationOptions */
    protected $asSProductAttributeConfigurationOptions;
    /** @var AttributeSet $asSProductAttributesLink */
    protected $asSProductAttributesLink;

    /** @var AttributeSet $asAccount */
    protected $asAccount;
    /** @var AttributeSet $asAddress */
    protected $asAddress;
    private $importLogDir;
    private $aggregatedImportLogDir;

    public function initialize()
    {
        parent::initialize();

        $this->apiUrl = $_ENV["PRO_ERP_API_URL"];
        $this->apiToken = $_ENV["PRO_ERP_API_TOKEN"];
        $this->fixedQty = $_ENV["PRO_ERP_FIXED_QTY"];

        $this->setRemoteSource("pro_erp");

        $this->asSRoute = $this->entityManager->getAttributeSetByCode("s_route");
        $this->asProduct = $this->entityManager->getAttributeSetByCode("product");
        $this->asProductProductGroupLink = $this->entityManager->getAttributeSetByCode("product_product_group_link");
        $this->asProductGroup = $this->entityManager->getAttributeSetByCode("product_group");
        $this->asSProductAttributeConfiguration = $this->entityManager->getAttributeSetByCode("s_product_attribute_configuration");
        $this->asSProductAttributeConfigurationOptions = $this->entityManager->getAttributeSetByCode("s_product_attribute_configuration_options");
        $this->asSProductAttributesLink = $this->entityManager->getAttributeSetByCode("s_product_attributes_link");

        $this->asAccount = $this->entityManager->getAttributeSetByCode("account");
        $this->asAddress = $this->entityManager->getAttributeSetByCode("address");

        $this->importLogDir = $this->getWebPath() . "Documents/import_log/pro_erp";
        $this->aggregatedImportLogDir = $this->getWebPath() . "Documents/import_log";
    }

    /**
     * @param $endpoint
     * @param $request
     * @param $response
     * @return false|int
     */
    private function saveImportLog($endpoint, $request, $response)
    {
        $targetDir = $this->importLogDir . "/" . $endpoint . "/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $data = sprintf('{"request":%s,"response":%s}', json_encode($request, JSON_UNESCAPED_UNICODE), json_encode($response, JSON_UNESCAPED_UNICODE));

        $filepath = $targetDir . time() . ".json";

        file_put_contents($filepath, $data);

        return $filepath;
    }

    /**
     * @param $logFiles
     * @param $name
     * @param false $deleteSourceFiles
     */
    public function aggregateImportLog($logFiles,$name,$deleteSourceFiles = false){

        $targetDir = $this->aggregatedImportLogDir . "/";
        if (!file_exists($targetDir."/".$name)) {
            mkdir($targetDir."/".$name, 0777, true);
        }

        $logData = Array();
        $logFiles = array_unique($logFiles);

        foreach ($logFiles as $logFile){

            if(file_exists($logFile)){
                $logData[] = file_get_contents($logFile);

                if($deleteSourceFiles){
                    unlink($logFile);
                }
            }
        }

        if(empty($logData)){
            return null;
        }

        $filename = $name."/".time() . ".json";
        $filepath = $targetDir . $filename;

        file_put_contents($filepath, implode("\r\n",$logData));

        return $filename;
    }

    /**
     * @param $endpoint
     * @return mixed
     * @throws \Exception
     */
    private function getApiResponse($endpoint, $params = [], $body = [])
    {
        $url = $this->apiUrl . $endpoint;
        if (!empty($params)) {
            $url .= "?" . http_build_query($params);
        }

        $restManager = new RestManager();
        $restManager->CURLOPT_HTTPHEADER = ["Authorization: Bearer " . $this->apiToken];
        $restManager->CURLOPT_SSL_VERIFYPEER = 0;
        $restManager->CURLOPT_SSL_VERIFYHOST = 0;

        if (!empty($body)) {
            $restManager->CURLOPT_CUSTOMREQUEST = "POST";
            $restManager->CURLOPT_POSTFIELDS = json_encode($body);
            $restManager->CURLOPT_HTTPHEADER[] = 'Content-Type:application/json';
        }


        $data = $restManager->get($url, false);

        try {

        } catch (\Exception $e) {
            $this->saveImportLog($endpoint, $params, []);
            throw $e;
        }

        if (empty($data)) {
            $this->saveImportLog($endpoint, $params, []);
            throw new \Exception("{$endpoint} Response is empty");
        }

        $data = json_decode($data, true);

        //$data["log_file"] = $this->saveImportLog($endpoint, $params, $data);

        if (empty($data)) {
            throw new \Exception("Response is empty");
        }
        if (!isset($data["data"]) && !isset($data["result"])){
            throw new \Exception("Data is empty");
        }

        return $data;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function sendOrder($preparedOrder){
        return $this->getApiResponse("/ponuda/store",null,$preparedOrder);
    }

    /**
     * @param array $args
     * @return array
     * @throws \Exception
     */
    public function importProducts($args = [])
    {
        echo "Importing products...\n";

        $data = $this->getApiResponse("/artikl/list/webshop");

        if(!isset($data["data"]) || empty($data["data"])){
            throw new \Exception("Data is empty");
        }

        $data = $data["data"];

        $productSelectColumns = [
            "id",
            "code",
            "name",
            "active",
            "price_base",
            "price_retail",
            "qty",
            "show_on_store"
        ];

        $existingProducts = $this->getEntitiesArray($productSelectColumns, "product_entity", ["code"], "", "WHERE entity_state_id = 1 AND product_type_id = 1 AND remote_source = '{$this->getRemoteSource()}'");
        $existingProductGroups = $this->getEntitiesArray(["id", "product_group_code"], "product_group_entity", ["product_group_code"], "", "WHERE entity_state_id = 1");
        $existingProductProductGroupLinks = $this->getEntitiesArray(["a1.id", "a3.product_group_code", "a2.code", "a2.id AS product_id"], "product_product_group_link_entity", ["code", "product_group_code"], "JOIN product_entity a2 ON a1.product_id = a2.id JOIN product_group_entity a3 ON a1.product_group_id = a3.id",
            "WHERE a1.entity_state_id = 1 AND a2.entity_state_id = 1 AND a2.code IS NOT NULL AND a2.code != '' AND a2.remote_source = '{$this->getRemoteSource()}' AND a3.product_group_code IS NOT NULL AND a3.product_group_code != '' AND a3.remote_source = '{$this->getRemoteSource()}'");
        $existingSRoutes = $this->getEntitiesArray(["request_url", "store_id", "destination_type"], "s_route_entity", ["store_id", "request_url"]);
        $existingSProductAttributeConfigurations = $this->getEntitiesArray(["id", "s_product_attribute_configuration_type_id", "is_active", "filter_key"], "s_product_attribute_configuration_entity", ["filter_key"], "", "WHERE entity_state_id = 1");
        $existingSProductAttributeConfigurationOptions = $this->getEntitiesArray(["id", "configuration_attribute_id", "MD5(configuration_value) AS md5_configuration_value"], "s_product_attribute_configuration_options_entity", ["configuration_attribute_id", "md5_configuration_value"], "", "WHERE entity_state_id = 1");
        $existingSProductAttributeLinks = $this->getSProductAttributesLinksByConfigurationAndOption($this->getRemoteSource());

        $insertArray = [
            // product_entity
            // s_product_attribute_configuration_entity
        ];
        $insertArray2 = [
            // s_route_entity
            // s_product_attribute_configuration_options_entity
            // product_product_group_link_entity
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
        $productCodes = [];

        foreach ($existingProducts as $existingProduct) {
            if ($existingProduct["active"]) {
                $productUpdate = new UpdateModel($existingProduct);
                $productUpdate->add("active", false, false)
                    ->add("date_synced", "NOW()", false);
                $updateArray["product_entity"][$productUpdate->getEntityId()] = $productUpdate->getArray();
                $productIds[] = $productUpdate->getEntityId();
            }
        }

//        foreach ($existingSProductAttributeLinks as $configurationOptions) {
//            foreach ($configurationOptions as $attributeValues) {
//                foreach ($attributeValues as $attributeValueKey => $attributeLink) {
//                    $deleteArray["s_product_attributes_link_entity"][$attributeValueKey] = [
//                        "id" => $attributeLink["id"]
//                    ];
//                }
//            }
//        }

        foreach ($existingProductProductGroupLinks as $key => $existingProductProductGroupLink) {
            $deleteArray["product_product_group_link_entity"][$key] = [
                "id" => $existingProductProductGroupLink["id"],
                "product_id" => $existingProductProductGroupLink["product_id"]
            ];
        }

        $progressBar = new ProgressBar($this->getConsoleOutput(), count($data));

        foreach ($data as $d) {

            $progressBar->advance();

            $remoteId = $d["id"];
            $nameArray = [
                3 => $d["naziv"],
                5 => !empty($d["ino_naziv"]) ? $d["ino_naziv"] : ""
            ];
            $code = $d["sifra"];
            $description = $d["opis"];
            $qty = $d["stanje"];
            $ean = $d["barkod"];
            $priceBase = $d["cijena_eur"]; // VPC
            $priceRetail = bcmul($priceBase, 1.25, 4);

            $productGroupModels = [
                0 => new ProductGroupModel($d["artikl_grupa_parent_naziv"], $d["artikl_grupa_parent_sifra"], NULL, $d["artikl_parent_grupa_id"]),
                1 => new ProductGroupModel($d["artikl_grupa_naziv"], $d["artikl_grupa_sifra"], $d["artikl_grupa_parent_sifra"], $d["artikl_grupa_id"])
            ];

            $configurationArray = [];

            if (isset($args["attributes"])) {
                foreach ($d as $key => $value) {
                    if (isset($args["attributes"][$key]) && $value != "" && $value != null) {
                        $configurationArray[$key][$value] = [
                            "type_id" => $args["attributes"][$key],
                            "value" => $value
                        ];
                    }
                }
            }

            $descriptionArray = [];
            $metaKeywordsArray = [];
            $showOnStoreArray = [];
            $urlArray = [];

            foreach ($this->getStores() as $storeId) {

                $name = $nameArray[$storeId];
                $descriptionArray[$storeId] = $description;
                $metaKeywordsArray[$storeId] = "";
                $showOnStoreArray[$storeId] = !empty($name) ? 1 : 0;

                if (!isset($existingProducts[$code])) {

                    $i = 1;
                    $url = $key = $this->routeManager->prepareUrl($name);
                    while (isset($existingSRoutes[$storeId . "_" . $url]) || isset($insertArray2["s_route_entity"][$storeId . "_" . $url])) {
                        $url = $key . "-" . $i++;
                    }
                    $urlArray[$storeId] = $url;

                    $insertArray2["s_route_entity"][$storeId . "_" . $url] =
                        $this->getSRouteInsertEntity($url, "product", $storeId, $code); // remote_id
                }
            }

            $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);
            $descriptionJson = json_encode($descriptionArray, JSON_UNESCAPED_UNICODE);
            $metaKeywordsJson = json_encode($metaKeywordsArray, JSON_UNESCAPED_UNICODE);
            $showOnStoreJson = json_encode($showOnStoreArray, JSON_UNESCAPED_UNICODE);
            $urlJson = json_encode($urlArray, JSON_UNESCAPED_UNICODE);

            if (!isset($existingProducts[$code])) {

                $productInsert = new InsertModel($this->asProduct);
                $productInsert->add("date_synced", "NOW()")
                    ->add("remote_id", $remoteId)
                    ->add("remote_source", $this->getRemoteSource())
                    ->add("name", $nameJson)
                    ->add("ean", $ean)
                    ->add("code", $code)
                    ->add("meta_title", $nameJson)
                    ->add("meta_description", $nameJson)
                    ->add("description", $descriptionJson)
                    ->add("meta_keywords", $metaKeywordsJson)
                    ->add("show_on_store", $showOnStoreJson)
                    ->add("price_base", $priceBase)
                    ->add("price_retail", $priceRetail)
                    ->add("active", 1)
                    ->add("url", $urlJson)
                    ->add("qty", $qty)
                    ->add("qty_step", 1)
                    ->add("fixed_qty", $this->fixedQty)
                    ->add("tax_type_id", 3)
                    ->add("currency_id", $_ENV["DEFAULT_CURRENCY"])
                    ->add("product_type_id", CrmConstants::PRODUCT_TYPE_SIMPLE)
                    ->add("ord", 100)
                    ->add("is_visible", true)
                    ->add("template_type_id", 5)
                    ->add("auto_generate_url", true)
                    ->add("keep_url", true)
                    ->add("show_on_homepage", false)
                    ->add("content_changed", true);

                $insertArray["product_entity"][$code] = $productInsert->getArray();
                $productCodes[] = $code;

            } else {

                $productUpdate = new UpdateModel($existingProducts[$code]);

                unset($updateArray["product_entity"][$productUpdate->getEntityId()]);

                $k = array_search($productUpdate->getEntityId(), $productIds);
                if ($k !== false) {
                    unset($productIds[$k]);
                }

                if ($nameArray !== json_decode($existingProducts[$code]["name"], true)) {
                    $productUpdate->add("name", $nameJson, false)
                        ->add("meta_title", $nameJson, false)
                        ->add("meta_description", $nameJson, false);
                }
                if ($showOnStoreArray !== json_decode($existingProducts[$code]["show_on_store"], true)) {
                    $productUpdate->add("show_on_store", $showOnStoreJson, false);
                }

                $productUpdate->add("active", 1)
                    //->add("qty", $qty)
                    ->addFloat("price_base", $priceBase)
                    ->addFloat("price_retail", $priceRetail);

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

            /** @var ProductGroupModel $productGroupModel */
            foreach ($productGroupModels as $level => $productGroupModel) {

                /**
                 * Trenutni level ne postoji, znači da nema ni childova
                 */
                if (empty($productGroupModel->getName())) {
                    break;
                }

                $productProductGroupLinkKey = $code . "_" . $productGroupModel->getCode();
                if (!isset($existingProductProductGroupLinks[$productProductGroupLinkKey])) {
                    $productProductGroupLinkInsert = new InsertModel($this->asProductProductGroupLink);
                    if (isset($existingProducts[$code])) {
                        $productProductGroupLinkInsert->add("product_id", $existingProducts[$code]["id"]);
                        $productIds[] = $existingProducts[$code]["id"];
                    } else {
                        $productProductGroupLinkInsert->addLookup("product_id", $code, "product_entity"); // remote_id
                        $productCodes[] = $code;
                    }
                    if (isset($existingProductGroups[$productGroupModel->getCode()])) {
                        $productProductGroupLinkInsert->add("product_group_id", $existingProductGroups[$productGroupModel->getCode()]["id"]);
                    } else {
                        $productProductGroupLinkInsert->addLookup("product_group_id", $productGroupModel->getCode(), "product_group_entity"); // product_group_code
                    }
                    $insertArray2["product_product_group_link_entity"][$productProductGroupLinkKey] = $productProductGroupLinkInsert;
                } else {
                    unset($deleteArray["product_product_group_link_entity"][$productProductGroupLinkKey]);
                }

                if (!isset($existingProductGroups[$productGroupModel->getCode()]) && !isset($insertProductGroupsArray[$level][$productGroupModel->getCode()])) {

                    $groupNameArray = [];
                    $groupMetaDescriptionArray = [];
                    $groupShowOnStoreArray = [];
                    $groupUrlArray = [];

                    foreach ($this->getStores() as $storeId) {

                        $groupNameArray[$storeId] = $productGroupModel->getName();
                        $groupMetaDescriptionArray[$storeId] = "";
                        $groupShowOnStoreArray[$storeId] = 1;

                        $i = 1;
                        $url = $key = $this->routeManager->prepareUrl($productGroupModel->getName());
                        while (isset($existingSRoutes[$storeId . "_" . $url]) || isset($insertArray2["s_route_entity"][$storeId . "_" . $url])) {
                            $url = $key . "-" . $i++;
                        }
                        $groupUrlArray[$storeId] = $url;

                        $insertArray2["s_route_entity"][$storeId . "_" . $url] =
                            $this->getSRouteInsertEntity($url, "product_group", $storeId, $productGroupModel->getCode()); // product_group_code
                    }

                    $groupNameJson = json_encode($groupNameArray, JSON_UNESCAPED_UNICODE);
                    $groupMetaDescriptionJson = json_encode($groupMetaDescriptionArray, JSON_UNESCAPED_UNICODE);
                    $groupUrlJson = json_encode($groupUrlArray, JSON_UNESCAPED_UNICODE);
                    $groupShowOnStoreJson = json_encode($groupShowOnStoreArray, JSON_UNESCAPED_UNICODE);

                    $productGroupInsert = new InsertModel($this->asProductGroup);
                    $productGroupInsert->add("remote_source", $this->getRemoteSource())
                        ->add("product_group_code", $productGroupModel->getCode())
                        ->add("remote_id", $productGroupModel->getRemoteId())
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

                    if (!empty($productGroupModel->getParent())) {
                        if (isset($existingProductGroups[$productGroupModel->getParent()])) {
                            $productGroupInsert->add("product_group_id", $existingProductGroups[$productGroupModel->getParent()]["id"]);
                        } else {
                            $productGroupInsert->addLookup("product_group_id", $productGroupModel->getParent(), "product_group_entity"); // product_group_code
                        }
                    }

                    $insertProductGroupsArray[$level][$productGroupModel->getCode()] = $productGroupInsert;
                }
            }

            foreach ($configurationArray as $configurationName => $configurationValues) {
                $filterKey = $configurationName;//$this->helperManager->nameToFilename($configurationName);
                foreach ($configurationValues as $attributeCode => $configurationData) {
                    $configurationTypeId = $configurationData["type_id"];
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

                                    $linkKey = md5($code . $filterKey . $configurationData["value"]);
                                    if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                        $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                        if (isset($existingProducts[$code])) {
                                            $sProductAttributeLinksInsert->add("product_id", $existingProducts[$code]["id"]);
                                        } else {
                                            $sProductAttributeLinksInsert->addLookup("product_id", $code, "product_entity");
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
                                        $linkKey = md5($code . $filterKey . $configurationData["value"]);
                                        if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                            $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                            if (isset($existingProducts[$code])) {
                                                $sProductAttributeLinksInsert->add("product_id", $existingProducts[$code]["id"]);
                                            } else {
                                                $sProductAttributeLinksInsert->addLookup("product_id", $code, "product_entity");
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
                                        if (isset($existingProducts[$code])) {
                                            /**
                                             * Proizvod postoji, linkovi potencijalno postoje
                                             */
                                            $attributeValueKey = md5($existingProducts[$code]["id"] . $configurationId . $optionId);
                                            if (!isset($existingSProductAttributeLinks[$configurationId][$optionId][$attributeValueKey])) {
                                                /**
                                                 * Link ne postoji
                                                 */
                                                $linkKey = md5($code . $filterKey . $configurationData["value"]);
                                                if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                                    $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                                    $sProductAttributeLinksInsert->add("product_id", $existingProducts[$code]["id"])
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
                                                    $updateArray["s_product_attributes_link_entity"][$sProductAttributeLinksUpdate->getEntityId()] = $sProductAttributeLinksUpdate->getArray();
                                                }
                                            }
                                        } else {
                                            /**
                                             * Proizvod ne postoji, linkovi ne postoje
                                             */
                                            $linkKey = md5($code . $filterKey . $configurationData["value"]);
                                            if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                                $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                                $sProductAttributeLinksInsert->addLookup("product_id", $code, "product_entity")
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
                                    $linkKey = md5($code . $filterKey . $configurationData["value"]);
                                    if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                        $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                        if (isset($existingProducts[$code])) {
                                            $sProductAttributeLinksInsert->add("product_id", $existingProducts[$code]["id"]);
                                        } else {
                                            $sProductAttributeLinksInsert->addLookup("product_id", $code, "product_entity");
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
                                    if (isset($existingProducts[$code])) {
                                        /**
                                         * Proizvod postoji, linkovi potencijalno postoje
                                         */
                                        $attributeValueKey = md5($existingProducts[$code]["id"] . $configurationId . NULL);
                                        if (!isset($existingSProductAttributeLinks[$configurationId][$optionId][$attributeValueKey])) {
                                            /**
                                             * Link ne postoji
                                             */
                                            $linkKey = md5($code . $filterKey . $configurationData["value"]);
                                            if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                                $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                                $sProductAttributeLinksInsert->add("product_id", $existingProducts[$code]["id"])
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
                                                $updateArray["s_product_attributes_link_entity"][$sProductAttributeLinksUpdate->getEntityId()] = $sProductAttributeLinksUpdate->getArray();
                                            }
                                        }
                                    } else {
                                        /**
                                         * Proizvod ne postoji, linkovi ne postoje
                                         */
                                        $linkKey = md5($code . $filterKey . $configurationData["value"]);
                                        if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                            $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                            $sProductAttributeLinksInsert->addLookup("product_id", $code, "product_entity")
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

        $progressBar->finish();
        echo "\n";

        unset($existingProducts);
        unset($existingProductGroups);
        unset($existingProductProductGroupLinks);
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
                $reselectArray["product_group_entity"] = $this->getEntitiesArray(["id", "product_group_code"], "product_group_entity", ["product_group_code"]);
            }
            unset($insertProductGroupsArray);
        }

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $reselectArray["product_entity"] = $this->getEntitiesArray($productSelectColumns, "product_entity", ["code"], "", "WHERE entity_state_id = 1 AND product_type_id = 1 AND remote_source = '{$this->getRemoteSource()}'");
        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);

        $reselectArray["s_product_attribute_configuration_options_entity"] = $this->getEntitiesArray(["id", "configuration_attribute_id", "MD5(configuration_value) AS md5_configuration_value"], "s_product_attribute_configuration_options_entity", ["configuration_attribute_id", "md5_configuration_value"], "", "WHERE entity_state_id = 1");
        $insertArray3 = $this->resolveImportArray($insertArray3, $reselectArray);
        $this->executeInsertQuery($insertArray3);

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
            $ret["product_ids"] = $this->resolveChangedProducts($productIds, $productCodes, $reselectArray["product_entity"]);
        }

        unset($reselectArray);

        echo "Importing products complete\n";

        return $ret;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importAccounts()
    {
        echo "Importing accounts...\n";

        $data = $this->getApiResponse("/partner/list");

        if(!isset($data["data"]) || empty($data["data"])){
            throw new \Exception("Data is empty");
        }

        $data = $data["data"];

        $accountSelectColumns = [
            "id",
            "name",
            "oib",
            "email",
            "code",
            "is_active"
        ];
        $addressSelectColumns = [
            "a1.id",
            "a1.name",
            "a1.street",
            "a2.code AS account_code"
        ];

        $existingAccounts = $this->getEntitiesArray($accountSelectColumns, "account_entity", ["code"], "WHERE entity_state_id = 1");
        $existingAddresses = $this->getEntitiesArray($addressSelectColumns, "address_entity", ["account_code"], "JOIN account_entity a2 ON a1.account_id = a2.id", "WHERE a1.entity_state_id = 1 AND a2.entity_state_id = 1 AND a2.code IS NOT NULL AND a2.code != '' AND a1.headquarters = 1 AND a1.billing = 1");

        $insertArray = [
            // account_entity
        ];
        $insertArray2 = [
            // address_entity
        ];
        $updateArray = [
            // account_entity
            // address_entity
        ];

        $accountEmailsArray = [];

        foreach ($existingAccounts as $existingAccount) {
            if ($existingAccount["is_active"]) {
                $accountUpdate = new UpdateModel($existingAccount);
                $accountUpdate->add("is_active", false, false);
                $updateArray["account_entity"][$accountUpdate->getEntityId()] = $accountUpdate->getArray();
            }
        }

        $progressBar = new ProgressBar($this->getConsoleOutput(), count($data));

        foreach ($data as $d) {

            $progressBar->advance();

            $remoteId = $d["id"];
            $code = $d["sifra"];
            $name = $d["naziv"];
            $street = $d["adresa"];
            $vatId = $d["porezni_broj"];
            $iban = $d["iban"];
            $email = $d["email"];
            //$countryId = $d["drzava_id"];
            //$countryCode = $d["drzava_sifra"];
            //$countryName = $d["drzava_naziv"];

            if (!isset($existingAccounts[$code])) {

                $accountInsert = new InsertModel($this->asAccount);
                $accountInsert->add("remote_id", $remoteId)
                    ->add("code", $code)
                    ->add("name", $name)
                    ->add("oib", $vatId)
                    ->add("is_active", 1)
                    ->add("is_legal_entity", 1)
                    ->add("email", NULL);

                if (!$this->getExistingEntityByEmail($existingAccounts, $accountEmailsArray, $email)) {
                    $accountEmailsArray[] = $email;
                    $accountInsert->add("email", $email);
                }

                $insertArray["account_entity"][$code] = $accountInsert->getArray();

            } else {

                $accountUpdate = new UpdateModel($existingAccounts[$code]);

                unset($updateArray["account_entity"][$accountUpdate->getEntityId()]);

                $accountUpdate->add("name", $name)
                    ->add("oib", $vatId)
                    ->add("is_active", 1);

                if (!empty($email) &&
                    !$this->getExistingEntityByEmail($existingAccounts, $accountEmailsArray, $email)) {
                    $accountUpdate->add("email", $email);
                }

                if (!empty($accountUpdate->getArray())) {
                    $updateArray["account_entity"][$accountUpdate->getEntityId()] = $accountUpdate->getArray();
                    if (isset($updateArray["account_entity"][$accountUpdate->getEntityId()]["email"])) {
                        $accountEmailsArray[] = $email;
                    }
                }
            }

            if (!isset($existingAddresses[$code])) {

                $addressInsert = new InsertModel($this->asAddress);
                $addressInsert->add("name", $name)
                    ->add("headquarters", 1)
                    ->add("billing", 1)
                    ->add("street", $street);

                if (!isset($existingAccounts[$code])) {
                    $addressInsert->addLookup("account_id", $code, "account_entity"); // code
                } else {
                    $addressInsert->add("account_id", $existingAccounts[$code]["id"]);
                }

                $insertArray2["address_entity"][] = $addressInsert;

            } else {

                $addressUpdate = new UpdateModel($existingAddresses[$code]);
                $addressUpdate->add("name", $name)
                    ->add("street", $street);

                if (!empty($addressUpdate->getArray())) {
                    $updateArray["address_entity"][$addressUpdate->getEntityId()] = $addressUpdate->getArray();
                }
            }
        }

        $progressBar->finish();
        echo "\n";

        unset($existingAccounts);
        unset($existingAddresses);

        unset($accountEmailsArray);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $reselectArray["account_entity"] = $this->getEntitiesArray($accountSelectColumns, "account_entity", ["code"]);
        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);
        unset($reselectArray);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        echo "Importing accounts complete\n";

        return [];
    }
}