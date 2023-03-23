<?php

namespace SanitarijeBusinessBundle\Managers;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\ApplicationSettingsManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\MailManager;
use AppBundle\Managers\RestManager;
use AppBundle\Models\InsertModel;
use AppBundle\Models\UpdateModel;
use CrmBusinessBundle\Abstracts\AbstractImportManager;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Entity\ProductDocumentEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use CrmBusinessBundle\Entity\ProductImagesEntity;
use CrmBusinessBundle\Entity\YoutubeEmbedEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\EmailTemplateManager;
use CrmBusinessBundle\Managers\ProductManager;
use ScommerceBusinessBundle\Managers\ScommerceHelperManager;

class SanitarijeHelperManager extends AbstractImportManager
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var ProductManager $productManager */
    protected $productManager;
    protected $apiUrl;
    /** @var ApplicationSettingsManager $applicationSettingsManager */
    protected $applicationSettingsManager;

    public function initialize()
    {
        parent::initialize();
        $this->apiUrl = $_ENV["B2B_API_URL"];
    }

    private function getApiData(RestManager $restManager, $url, $params = [], $body = [])
    {
        $url = $this->apiUrl."/{$url}";

        if (!empty($params)) {
            $url .= "&" . http_build_query($params);
        }

        if (!empty($body)) {
            $restManager->CURLOPT_POST = 1;
            $restManager->CURLOPT_POSTFIELDS = http_build_query($body);
            $restManager->CURLOPT_CUSTOMREQUEST = "POST";
        }

        $restManager->CURLOPT_RETURNTRANSFER = 1;
        $restManager->CURLOPT_SSL_VERIFYHOST = 0;
        $restManager->CURLOPT_SSL_VERIFYPEER = 0;
        $restManager->CURLOPT_FOLLOWLOCATION = 0;
        $restManager->CURLOPT_TIMEOUT = 300;

        try {
            $data = $restManager->get($url, false);
        } catch (\Exception $e) {
            throw $e;
        }

        if (empty($data)) {
            throw new \Exception("Response is empty ".json_encode($body));
        }

        return json_decode($data,true);
    }

    /**
     * @return string|null
     * @throws \Exception
     */
    public function apiLogin(){

        /** @var RestManager $restManager */
        $restManager = new RestManager();

        $body = Array();
        $body["username"] = $_ENV["B2B_API_USER"];
        $body["password"] = $_ENV["B2B_API_PASS"];

        try {
            $data = $this->getApiData($restManager,"login",null,$body);
        } catch (\Exception $e) {

            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->container->get("error_log_manager");
            }

            $this->errorLogManager->logExceptionEvent("Internal API error", $e, true);
            throw $e;
        }

        if(!isset($data["data"]["token"])){

            $e = new \Exception("Error getting token from internal API");

            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->container->get("error_log_manager");
            }

            $this->errorLogManager->logExceptionEvent("Internal API error - cannot get token", $e, true);

            throw $e;
        }

        if(empty($this->applicationSettingsManager)){
            $this->applicationSettingsManager = $this->container->get("application_settings_manager");
        }

        $tokenSettings = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode("internal_api_token");

        $settingsData = Array();
        $settingsData["settings_value"] = Array($_ENV["DEFAULT_STORE_ID"] => $data["data"]["token"]);

        if(empty($tokenSettings)){
            $settingsData["code"] = "internal_api_token";
            $settingsData["name"] = Array($_ENV["DEFAULT_STORE_ID"] => "Internal API token");
            $settingsData["show_on_store"] = Array($_ENV["DEFAULT_STORE_ID"] => 1);
        }

        $this->applicationSettingsManager->createUpdateSettings($tokenSettings,$settingsData);

        $refreshTokenSettings = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode("internal_api_refresh_token");

        $settingsData = Array();
        $settingsData["settings_value"] = Array($_ENV["DEFAULT_STORE_ID"] => $data["data"]["refresh_token"]);

        if(empty($refreshTokenSettings)){
            $settingsData["code"] = "internal_api_refresh_token";
            $settingsData["name"] = Array($_ENV["DEFAULT_STORE_ID"] => "Internal API refresh token");
            $settingsData["show_on_store"] = Array($_ENV["DEFAULT_STORE_ID"] => 1);
        }

        $this->applicationSettingsManager->createUpdateSettings($refreshTokenSettings,$settingsData);

        return $this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("internal_api_token",$_ENV["DEFAULT_STORE_ID"]);
    }

    /**
     * @param $remoteId
     * @throws \Exception
     */
    public function getProductDataFromApi($remoteId){

        if(empty($remoteId)){
            throw new \Exception("Missing remote id - get_product_data");
        }

        /** @var RestManager $restManager */
        $restManager = new RestManager();

        if(empty($this->applicationSettingsManager)){
            $this->applicationSettingsManager = $this->container->get("application_settings_manager");
        }
        if (empty($this->errorLogManager)) {
            $this->errorLogManager = $this->container->get("error_log_manager");
        }

        $token = $this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("internal_api_token",$_ENV["DEFAULT_STORE_ID"]);

        if(empty($token)){
            $token = $this->apiLogin();
        }

        $body = Array();
        $body["remote_id"] = $remoteId;
        $body["token"] = $token;

        try {
            $data = $this->getApiData($restManager,"get_product_data",null,$body);
        } catch (\Exception $e) {
            $this->errorLogManager->logExceptionEvent("Internal API error", $e, true);
            throw $e;
        }

        if(!isset($data["error"])){
            $e = new \Exception("Unknown API error - get_product_data");
            $this->errorLogManager->logExceptionEvent("Unknown API error - get_product_data", $e, true);
            throw $e;
        }

        if($data["error"] == true && $data["code"] == 111){
            try {
                $body["token"] = $this->apiLogin();
            } catch (\Exception $e) {
                $this->errorLogManager->logExceptionEvent("Internal API error", $e, true);
                throw $e;
            }

            try {
                $data = $this->getApiData($restManager,"get_product_data",null,$body);
            } catch (\Exception $e) {
                $this->errorLogManager->logExceptionEvent("Internal API error", $e, true);
                throw $e;
            }
        }

        if($data["error"] == true){
            $e = new \Exception($data["message"]);
            $this->errorLogManager->logExceptionEvent("Unknown API error - get_product_data", $e, true);
            throw $e;
        }

        return $data["data"];
    }

    /**
     * @param ProductEntity $product
     * @throws \Exception
     */
    public function updateProductData(ProductEntity $product){

        if(empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }

        if(empty($product->getRemoteId())){
            throw new \Exception("Missing remote id - get_product_data");
        }

        try {
            $productData = $this->getProductDataFromApi($product->getRemoteId());
        } catch (\Exception $e) {
            throw $e;
        }

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        /**
         * Remove existing entities
         */
        $q = "DELETE FROM product_images_entity WHERE product_id = {$product->getId()};";
        $this->databaseContext->executeNonQuery($q);

        $q = "DELETE FROM product_document_entity WHERE product_id = {$product->getId()};";
        $this->databaseContext->executeNonQuery($q);

        $q = "DELETE FROM youtube_embed_entity WHERE product_id = {$product->getId()};";
        $this->databaseContext->executeNonQuery($q);

        /**
         * Insert images
         */
        if(!empty($productData["images"])){

            $imagesBasePath = $_ENV["WEB_PATH"]."Documents/Products/";

            foreach ($productData["images"] as $image){

                if(!file_exists($imagesBasePath.$product->getId())){
                    mkdir($imagesBasePath.$product->getId(),0777,true);
                }

                $baseFilename = $filename = $imagesBasePath.$product->getId()."/".$image["filename"];
                $i = 1;
                while (file_exists($filename.".".$image["file_type"])){
                    $filename = $baseFilename."-".$i;
                    $i++;
                }

                if(!$this->helperManager->saveRemoteFileToDisk($image["full_url"],$filename.".".$image["file_type"])){
                    continue;
                }

                $filenameParts = explode("/",$filename);
                $filenamePart = end($filenameParts);

                /** @var ProductImagesEntity $productImage */
                $productImage = $this->entityManager->getNewEntityByAttributSetName("product_images");

                $productImage->setProduct($product);
                $productImage->setFile($product->getId()."/".$filenamePart.".".$image["file_type"]);
                $productImage->setFilename($filenamePart);
                $productImage->setFileType($image["file_type"]);
                $productImage->setFileSource("b2b");
                $productImage->setSize($image["size"]);
                $productImage->setOrd($image["ord"]);

                $this->entityManager->saveEntityWithoutLog($productImage);
            }
        }

        /**
         * Insert documents
         */
        if(!empty($productData["documents"])){

            $imagesBasePath = $_ENV["WEB_PATH"]."Documents/product_document/";

            foreach ($productData["documents"] as $image){

                if(!file_exists($imagesBasePath.$product->getId())){
                    mkdir($imagesBasePath.$product->getId(),0777,true);
                }

                $baseFilename = $filename = $imagesBasePath.$product->getId()."/".$image["filename"];
                $i = 1;
                while (file_exists($filename.".".$image["file_type"])){
                    $filename = $baseFilename."-".$i;
                    $i++;
                }

                if(!$this->helperManager->saveRemoteFileToDisk($image["full_url"],$filename.".".$image["file_type"])){
                    continue;
                }

                $filenameParts = explode("/",$filename);
                $filenamePart = end($filenameParts);

                /** @var ProductDocumentEntity $productDocument */
                $productDocument = $this->entityManager->getNewEntityByAttributSetName("product_document");

                $productDocument->setProduct($product);
                $productDocument->setFile($product->getId()."/".$filenamePart.".".$image["file_type"]);
                $productDocument->setFilename($filenamePart);
                $productDocument->setFileType($image["file_type"]);
                $productDocument->setFileSource("b2b");
                $productDocument->setSize($image["size"]);

                $this->entityManager->saveEntityWithoutLog($productDocument);
            }
        }

        if(!empty($productData["youtube"])){
            foreach ($productData["youtube"] as $youtube){

                /** @var YoutubeEmbedEntity $youtubeEntity */
                $youtubeEntity = $this->entityManager->getNewEntityByAttributSetName("youtube_embed");

                $youtubeEntity->setProduct($product);
                $youtubeEntity->setUrl($youtube["url"]);

                $this->entityManager->saveEntityWithoutLog($youtubeEntity);
            }
        }

        if (!isset($_ENV["DISABLE_FRONT_BLOCK_HTML_CACHE"]) || $_ENV["DISABLE_FRONT_BLOCK_HTML_CACHE"] != 1) {

            if (empty($this->cacheManager)) {
                $this->cacheManager = $this->getContainer()->get("cache_manager");
            }

            $this->cacheManager->invalidateCacheByTag("product");
            $this->cacheManager->invalidateCacheByTag("product_" . $product->getId());
        }

        return true;

        //todo api za ping
        //todo button za push
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function syncProducts(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT p.id FROM product_entity as p WHERE entity_state_id = 1 and active = 1 and p.id NOT IN (SELECT product_id FROM product_images_entity) ORDER BY p.created DESC;";
        $data = $this->databaseContext->getAll($q);

        if(empty($data)){
            return true;
        }

        $productIds = array_column($data,"id");

        if(empty($this->productManager)){
            $this->productManager = $this->container->get("product_manager");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",",$productIds)));

        $products = $this->productManager->getProductsByFilter($compositeFilter);

        if(!EntityHelper::isCountable($products) || count($products) == 0){
            return true;
        }

        foreach ($products as $product){
            $this->updateProductData($product);
        }

        return true;
    }

    /**
     * @return array|int[]
     */
    public function getOutletProductIds()
    {

        $ret = array(0);

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
        }
        $attributeOptionIds = $this->applicationSettingsManager->getApplicationSettingByCode("outlet_attribute_values")[$_ENV["DEFAULT_STORE_ID"]];

        $q = "SELECT product_id FROM s_product_attributes_link_entity WHERE s_product_attribute_configuration_id = 5 AND configuration_option IN ({$attributeOptionIds})";
        $data = $this->databaseContext->getAll($q);

        if (!empty($data)) {
            $ret = array_column($data, "product_id");
        }

        return implode(",", $ret);
    }

    public function oldDataImport()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $remoteSource = "old_site";

        $productSelectColumns = [
            "id",
            "code",
            "name",
            "ean",
            "active",
            "tax_type_id",
            "measure",
            "weight",
            "url"
        ];

        $this->asSProductAttributeConfiguration = $this->entityManager->getAttributeSetByCode("s_product_attribute_configuration");
        $this->asSProductAttributeConfigurationOptions = $this->entityManager->getAttributeSetByCode("s_product_attribute_configuration_options");
        $this->asSProductAttributesLink = $this->entityManager->getAttributeSetByCode("s_product_attributes_link");
        $this->asSRoute = $this->entityManager->getAttributeSetByCode("s_route");
        $this->asProductProductGroupLink = $this->entityManager->getAttributeSetByCode("product_product_group_link");

        $existingSProductAttributeConfigurations = $this->getEntitiesArray(["id", "s_product_attribute_configuration_type_id", "is_active", "filter_key"], "s_product_attribute_configuration_entity", ["filter_key"], "", "WHERE filter_key IS NOT NULL AND filter_key != ''");
        $existingSProductAttributeConfigurationOptions = $this->getEntitiesArray(["id", "configuration_attribute_id", "MD5(configuration_value) AS md5_configuration_value"], "s_product_attribute_configuration_options_entity", ["configuration_attribute_id", "md5_configuration_value"], "", "WHERE entity_state_id = 1");
        $existingSProductAttributeLinks = $this->getSProductAttributesLinksByConfigurationAndOption($remoteSource);
        $existingProducts = $this->getEntitiesArray($productSelectColumns, "product_entity", ["code"], "", "WHERE entity_state_id = 1 AND product_type_id IN (1,4)");
        $existingProductGroupsTmp = $this->getEntitiesArray(["id, url"], "product_group_entity", ["url"], "", "WHERE entity_state_id = 1");
        $existingProductProductGroupLinks = $this->getEntitiesArray(["a1.id", "a3.product_group_code", "a2.code", "a2.id AS product_id"], "product_product_group_link_entity", ["code", "product_group_code"], "JOIN product_entity a2 ON a1.product_id = a2.id JOIN product_group_entity a3 ON a1.product_group_id = a3.id", "WHERE a1.entity_state_id = 1 AND a2.entity_state_id = 1 AND a2.code IS NOT NULL AND a2.code != ''");

        $storeId = $_ENV["DEFAULT_STORE_ID"];

        $existingProductGroups = array();
        foreach ($existingProductGroupsTmp as $existingProductGroupTmp) {
            $existingProductGroups[json_decode($existingProductGroupTmp["url"], true)[$storeId]] = $existingProductGroupTmp["id"];
        }

        $q = "SELECT * FROM _data WHERE code is not null;";
        $data = $this->databaseContext->getAll($q);

        $insertArray = array();
        $insertArray2 = array();
        $insertArray3 = array();
        $updateArray = array();
        $reselectArray = array();
        $deleteArray = array();

        $base_path = $_ENV["WEB_PATH"] . "Documents/import_files/product_images/";
        $base_path_files = $_ENV["WEB_PATH"] . "Documents/import_files/product_document/";
        if (!file_exists($base_path_files)) {
            mkdir($base_path_files, 0777, true);
        }

        $insertQueryProductProductGroupLink = "INSERT IGNORE INTO product_product_group_link_entity (entity_type_id, attribute_set_id, created, modified, created_by, modified_by, product_id, product_group_id) VALUES ";

        foreach ($data as $d) {

            $images = array();
            if (!empty($d["image"])) {
                $images[] = trim($d["image"]);
            }
            if (!empty(trim($d["images"]))) {
                $imagesTmp = explode(";", $d["images"]);
                $imagesTmp = array_map('trim', $imagesTmp);
                $images = array_merge($images, $imagesTmp);
            }

            /**
             * Download images
             */
            if (!empty($images) && 0) {
                $images = array_unique($images);

                foreach ($images as $key => $url) {
                    $url = str_replace("thumb/", "", $url);
                    $tmpUrl = explode(".", $url);

                    $tmpUrlName = explode("_", $tmpUrl[1]);
                    array_pop($tmpUrlName);

                    $url = $tmpUrl[0] . "." . implode("_", $tmpUrlName) . "." . $tmpUrl[2];

                    $code = trim($d["code"]);
                    if ($key) {
                        $code .= "_" . ($key + 1);
                    }

                    $destination = $base_path . $code . "." . $tmpUrl[2];

                    $this->helperManager->saveRemoteFileToDisk($url, $destination);
                }
            }

            $remoteId = $d["code"];


            /**
             * Set url redirect
             */
            if (0 && isset($existingProducts[$d["code"]]) && !empty($d["old_url"])) {
                $currentUrl = json_decode($existingProducts[$d["code"]]["url"], true)[$storeId];

                if ($currentUrl != $d["old_url"]) {

                    $q = "SELECT * FROM s_route_entity WHERE request_url = '{$d["old_url"]}';";
                    $oldUrlExists = $this->databaseContext->getAll($q);

                    if (empty($oldUrlExists)) {
                        $insertArray2["s_route_entity"][$storeId . "_" . $d["old_url"]] = $this->getSRouteInsertEntity($d["old_url"], "product", $storeId, $remoteId, $currentUrl); // remote_id
                    }
                }
            }

            /**
             * Download pdf
             */
            if (!empty(trim($d["document"])) && 0) {
                $documents = explode(";", $d["document"]);
                $documents = array_map('trim', $documents);

                foreach ($documents as $key => $url) {
                    $tmpUrl = explode(".", $url);

                    $tmpUrlName = explode("_", $tmpUrl[1]);
                    array_pop($tmpUrlName);

                    $url = "https://sanitarije.eu" . $tmpUrl[0] . "." . implode("_", $tmpUrlName) . $tmpUrl[1];

                    $code = trim($d["code"]);
                    if ($key) {
                        $code .= "-" . $key;
                    }

                    $destination = $base_path_files . $code . "." . $tmpUrl[1];

                    $this->helperManager->saveRemoteFileToDisk($url, $destination);
                }
            }

            /**
             * Set description
             */
            if (0) {
                if (!empty(trim($d["description_clean"]))) {
                    $descTmp = explode(",", $d["description_clean"]);
                    $descTmp = array_map('trim', $descTmp);
                    $descTmp = array_unique($descTmp);

                    if (empty($descTmp)) {
                        continue;
                    }

                    foreach ($descTmp as $key => $desTmp) {
                        $descTmp[$key] = "<p>{$desTmp}</p>";
                    }

                    $descTmp = implode("", $descTmp);
                    $descTmp = str_ireplace("'", "", $descTmp);
                    $descriptionArray[$storeId] = $descTmp;
                    $descriptionJson = json_encode($descriptionArray, JSON_UNESCAPED_UNICODE);

                    $q = "UPDATE product_entity SET short_description = '{$descriptionJson}' WHERE id = {$d["product_id"]};";
                    try {
                        $this->databaseContext->executeNonQuery($q);
                    } catch (\Exception $e) {
                        dump($q);
                    }

                }
                continue;
            }

            /**
             * Set product groups
             */
            if (1) {
                $productGroupsTmp = explode(",", $d["categories_clean"]);
                $productGroupsTmp = array_map('trim', $productGroupsTmp);
                $insertQueryValues = array();
                if (!empty($productGroupsTmp)) {

                    foreach ($productGroupsTmp as $productGroupTmp) {
                        $productGroupTmp = trim(str_ireplace("https://sanitarije.eu/", "", $productGroupTmp));
                        if (empty($productGroupTmp)) {
                            continue;
                        }
                        $productGroupTmp = explode("proizvodi/", $productGroupTmp);
                        if (!isset($productGroupTmp[1]) || empty($productGroupTmp[1])) {
                            continue;
                        }

                        $productGroupTmp = rtrim($productGroupTmp[1], "/");
                        $productGroupTmp = explode("/", $productGroupTmp);
                        $productGroupTmp = end($productGroupTmp);

                        if (stripos($productGroupTmp, "elektricni-bojleri-grijalice") === false && stripos($productGroupTmp, "grijanje-i-hladenje") === false) {
                            continue;
                        }

                        if (isset($existingProductGroups[$productGroupTmp])) {
                            $insertQueryValues[] = "({$this->asProductProductGroupLink->getEntityTypeId()}, {$this->asProductProductGroupLink->getId()}, NOW(), NOW(), 'system', 'system', {$d["product_id"]}, {$existingProductGroups[$productGroupTmp]})";
                        }
                    }

                    if (!empty($insertQueryValues)) {
                        $this->databaseContext->executeNonQuery($insertQueryProductProductGroupLink . implode(",", $insertQueryValues));
                    }

                }

                /** @var ScommerceHelperManager $sCommerceHelperManager */
                $sCommerceHelperManager = $this->getContainer()->get("scommerce_helper_manager");
                $sCommerceHelperManager->assignParentProductGroups(array($d["product_id"]));
                continue;
            }

            continue;

            $attributes = trim($d["attributes_clean"]);
            if (!empty($attributes)) {
                $attributes = explode(",", $attributes);
                $attributes = array_map('trim', $attributes);

                $configurationArray = array();

                foreach ($attributes as $attribute) {
                    $attribute = explode(":", $attribute);
                    $attribute = array_map('trim', $attribute);

                    if (count($attribute) != 2) {
                        continue;
                    }

                    $type = 1;
                    if (strtolower($attribute[0]) == "boja" || strtolower($attribute[0]) == "posebnosti" || strtolower($attribute[0]) == "podkategorija galanterija" || strtolower($attribute[0]) == "podkategorija tuš kade i kade"
                        || strtolower($attribute[0]) == "podkategorija bojleri i grijalice" || strtolower($attribute[0]) == "montaža" || strtolower($attribute[0]) == "podkategorija namještaj"
                        || strtolower($attribute[0]) == "podkategorija umivaonik" || strtolower($attribute[0]) == "podkategorija wc" || strtolower($attribute[0]) == "broj rupa za miješalicu"
                        || strtolower($attribute[0]) == "podkategorija miješalice" || strtolower($attribute[0]) == "serija proizvoda" || strtolower($attribute[0]) == "podkategorija tuševi"
                        || strtolower($attribute[0]) == "podkategorija montažni elementi i vodokotlići" || $attribute[0] == "Podkategorija instalacijski materijal II"
                        || strtolower($attribute[0]) == "materijal" || strtolower($attribute[0]) == "vrste mlaza" || strtolower($attribute[0]) == "broj izljevnih mjesta"
                        || strtolower($attribute[0]) == "podkategorija tuš kabine") {
                        $type = 2;
                    }

                    $configurationArray[$attribute[0]][] = array("type" => $type, "value" => $attribute[1]);
                }

                foreach ($configurationArray as $configurationName => $configurationValues) {
                    $filterKey = $this->helperManager->nameToFilename($configurationName);
                    foreach ($configurationValues as $attributeCode => $configurationData) {
                        $configurationTypeId = $configurationData["type"];
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
                                    ->add("remote_source", $remoteSource)
                                    ->add("filter_key", $filterKey);
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
                                                ->add("remote_source", $remoteSource)
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
        }

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $reselectArray["product_entity"] = $this->getEntitiesArray($productSelectColumns, "product_entity", ["code"], "", "WHERE entity_state_id = 1 AND product_type_id IN (1,4)");
        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);

        $reselectArray["s_product_attribute_configuration_options_entity"] = $this->getEntitiesArray(["id", "configuration_attribute_id", "MD5(configuration_value) AS md5_configuration_value"], "s_product_attribute_configuration_options_entity", ["configuration_attribute_id", "md5_configuration_value"], "", "WHERE entity_state_id = 1");
        $insertArray3 = $this->resolveImportArray($insertArray3, $reselectArray);
        $this->executeInsertQuery($insertArray3);
        unset($insertArray3);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        /*if (isset($deleteArray["product_product_group_link_entity"])) {
            foreach ($deleteArray["product_product_group_link_entity"] as $productProductGroupLink) {
                $productIds[] = $productProductGroupLink["product_id"];
            }
        }

        $this->executeDeleteQuery($deleteArray);
        unset($deleteArray);*/

        return true;
    }


    /**
     * @param $remoteSource
     * @return array
     */
    protected function getSProductAttributesLinksByConfigurationAndOption($remoteSource)
    {
        $q = "SELECT 
                spal.id,
                spal.s_product_attribute_configuration_id,
                spal.configuration_option,
                spal.attribute_value_key,
                spal.attribute_value
            FROM s_product_attributes_link_entity spal
            JOIN s_product_attribute_configuration_entity spac ON spal.s_product_attribute_configuration_id = spac.id 
            AND spac.remote_source = '{$remoteSource}'
            JOIN product_entity p ON spal.product_id = p.id 
            AND p.product_type_id = 1;";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["s_product_attribute_configuration_id"]][(int)$d["configuration_option"]][$d["attribute_value_key"]] = [
                "id" => $d["id"],
                "attribute_value" => $d["attribute_value"]
            ];
        }

        return $ret;
    }

    /**
     * @param $url
     * @param $type
     * @param $storeId
     * @param $destinationSortKey
     * @return InsertModel
     */
    protected function getSRouteInsertEntity($url, $type, $storeId, $destinationSortKey, $redirectTo)
    {
        $sRoute = new InsertModel($this->asSRoute);

        $sRoute->add("request_url", $url)
            ->add("destination_type", $type)
            ->add("store_id", $storeId)
            ->add("redirect_type_id", 1)
            ->add("redirect_to", $redirectTo)
            ->addLookup("destination_id", $destinationSortKey, $type . "_entity");

        return $sRoute;
    }

    /**
     * @return array
     */
    public function getPreparedHomeCategories()
    {
        $data = [];

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $pageEntityType = $this->entityManager->getEntityTypeByCode("product_group");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("productGroup", "nu"));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $topLevelCategories = $this->entityManager->getEntitiesByEntityTypeAndFilter($pageEntityType, $compositeFilters);

        /** @var ProductGroupEntity $topLevelCategory */
        foreach ($topLevelCategories as $topLevelCategory) {
            $children = $this->getChildCategories($topLevelCategory->getId());
            if (!empty($children)) {
                $data[] = [
                    "product_group" => $topLevelCategory,
                    "items" => $children,
                ];
            }
        }

        return $data;
    }

    /**
     * @param $parentId
     * @return mixed
     */
    private function getChildCategories($parentId)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $pageEntityType = $this->entityManager->getEntityTypeByCode("product_group");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("productGroup.id", "eq", $parentId));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($pageEntityType, $compositeFilters);
    }

    /**
     * @param $brandId
     * @return int
     */
    public function getBrandProductCount($brandId)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT count(id) as product_count FROM product_entity WHERE entity_state_id=1 AND ready_for_webshop=1 AND brand_id={$brandId};";
        $res = $this->databaseContext->executeQuery($q);

        if (empty($res)) {
            return 0;
        }

        return $res[0]["product_count"];
    }

    /**
     * @return mixed
     */
    public function getImportedContacts()
    {
        $entityType = $this->entityManager->getEntityTypeByCode("contact");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("coreUserId", "nu", ""));
        $compositeFilter->addFilter(new SearchFilter("remoteId", "nn", ""));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function generateUsersForImportedContacts()
    {
        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        /** @var EmailTemplateManager $emailTemplateManager */
        $emailTemplateManager = $this->container->get("email_template_manager");

        /** @var EmailTemplateEntity $template */
        $template = $emailTemplateManager->getEmailTemplateByCode("generated_account");

        /** @var MailManager mailManager */
        $mailManager = $this->container->get("mail_manager");

        $contacts = $this->getImportedContacts();

        $i = 0;

        /** @var ContactEntity $contact */
        foreach ($contacts as $contact) {

            dump(sprintf("%s (%u/%u)", $contact->getEmail(), ++$i, count($contacts)));

            if (!empty($this->helperManager->getUserByEmail($contact->getEmail()))) {
                dump(sprintf("%s user already exists", $contact->getEmail()));
                continue;
            }

            $customData = ["password" => $contact->getPassword()];

            $this->accountManager->createUserForContact($contact, null, false, false);

            $coreUser = $contact->getCoreUser();

            $templateData = $emailTemplateManager->renderEmailTemplate($coreUser, $template, null, $customData);

            $mailManager->sendEmail(array("email" => $contact->getEmail(), "name" => $contact->getFirstName()), null, null, null, $templateData["subject"], "", null, [], $templateData["content"], $attachments ?? [], 3);
        }

        return true;
    }

    /**
     * @param $importType
     * @return bool
     */
    public function updateProductTotalQty($importType)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $productIds = $this->getOutletProductIds();

        /**
         * Artikli koji nisu na outletu
         */
        $q1 = "
            UPDATE product_entity p
            JOIN (
                SELECT
                    p.id AS product_id,
                    sum( l.qty ) AS total_qty
                FROM
                    product_entity p
                    INNER JOIN product_warehouse_link_entity l ON p.id = l.product_id
                    LEFT JOIN warehouse_entity as w ON l.warehouse_id = w.id 
                WHERE
                    p.entity_state_id = 1
                    AND w.code = '0000101'
                    AND p.id NOT IN ({$productIds})
                    AND p.remote_source = '{$importType}'
                    AND w.is_active = 1
                GROUP BY
                    product_id
                ) AS x ON p.id = x.product_id
                SET p.qty = x.total_qty;
        ";
        $this->databaseContext->executeNonQuery($q1);

        /**
         * Artikli koji su na outletu
         */
        $q2 = "
            UPDATE product_entity p
            JOIN (
                SELECT
                    p.id AS product_id,
                    sum( l.qty ) AS total_qty
                FROM
                    product_entity p
                    INNER JOIN product_warehouse_link_entity l ON p.id = l.product_id
                    LEFT JOIN warehouse_entity as w ON l.warehouse_id = w.id
                WHERE
                    p.entity_state_id = 1
                    AND p.id IN ({$productIds})
                    AND p.remote_source = '{$importType}'
                    AND w.is_active = 1
                GROUP BY
                    product_id
                ) AS x ON p.id = x.product_id
                SET p.qty = x.total_qty;
        ";
        $this->databaseContext->executeNonQuery($q2);

        $q = "UPDATE product_entity SET qty = 0 WHERE qty < 0;";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }
}
