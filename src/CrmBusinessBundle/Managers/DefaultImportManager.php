<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\FileHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\ExcelManager;
use AppBundle\Models\InsertModel;
use AppBundle\Models\UpdateModel;
use CrmBusinessBundle\Abstracts\AbstractImportManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\ProductEntity;

class DefaultImportManager extends AbstractImportManager
{
    /** @var AttributeSet $asProduct */
    protected $asProduct;
    /** @var AttributeSet $asSRoute */
    protected $asSRoute;
    /** @var AttributeSet $asProductProductGroupLink */
    protected $asProductProductGroupLink;
    /** @var AttributeSet $asSProductAttributesLink */
    protected $asSProductAttributesLink;
    /** @var AttributeSet $asProductConfigurationProductLink */
    protected $asProductConfigurationProductLink;
    /** @var AttributeSet $asSProductAttributeConfigurationOptions */
    protected $asSProductAttributeConfigurationOptions;
    /** @var AttributeSet $asProductConfigurableAttribute */
    protected $asProductConfigurableAttribute;
    /** @var AttributeSet $asSProductAttributeConfiguration */
    protected $asSProductAttributeConfiguration;
    /** @var AttributeSet $asAccountTypeLink */
    protected $asAccountTypeLink;
    /** @var AttributeSet $asAccount */
    protected $asAccount;
    /** @var AttributeSet $asContact */
    protected $asContact;
    /** @var AttributeSet $asAddress */
    protected $asAddress;
    /** @var AttributeSet $asUser */
    protected $asUser;

    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var ExcelManager $excelManager */
    protected $excelManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;

    public function initialize()
    {
        parent::initialize();

        $this->accountManager = $this->getContainer()->get("account_manager");
        $this->asSProductAttributesLink = $this->entityManager->getAttributeSetByCode("s_product_attributes_link");
        $this->asProductProductGroupLink = $this->entityManager->getAttributeSetByCode("product_product_group_link");
        $this->asProductConfigurationProductLink = $this->entityManager->getAttributeSetByCode("product_configuration_product_link");
        $this->asSRoute = $this->entityManager->getAttributeSetByCode("s_route");
        $this->asProduct = $this->entityManager->getAttributeSetByCode("product");
        $this->asSProductAttributeConfigurationOptions = $this->entityManager->getAttributeSetByCode("s_product_attribute_configuration_options");
        $this->asProductConfigurableAttribute = $this->entityManager->getAttributeSetByCode("product_configurable_attribute");
        $this->asSProductAttributeConfiguration = $this->entityManager->getAttributeSetByCode("s_product_attribute_configuration");
        $this->asAccountTypeLink = $this->entityManager->getAttributeSetByCode("account_type_link");
        $this->asAccount = $this->entityManager->getAttributeSetByCode("account");
        $this->asContact = $this->entityManager->getAttributeSetByCode("contact");
        $this->asAddress = $this->entityManager->getAttributeSetByCode("address");
        $this->asUser = $this->entityManager->getAttributeSetByCode("core_user");
    }

    /**
     * Ovdje trpamo sve gluposti koje se mogu pojaviti u excelicama, a možemo ih lako očistiti
     *
     * @param $value
     * @return bool|mixed|string
     */
    protected function sanitizeExcelValue($value)
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ((empty($value) && $value !== false && $value !== 0.0) ||
            strcasecmp($value, "\"\"") == 0 ||
            strcasecmp($value, "''") == 0 ||
            strcasecmp($value, "'") == 0 ||
            strcasecmp($value, "null") == 0 ||
            strcasecmp($value, "#N/A") == 0 ||
            strcasecmp($value, "N/A") == 0) {
            $value = NULL;
        } else if (strcasecmp($value, "true") == 0) {
            $value = true;
        } else if (strcasecmp($value, "false") == 0) {
            $value = false;
        }

        return $value;
    }

    /**
     * Pregazi postojeće json vrijednosti sa novima ukoliko su došle iz excel tablice
     * ako ne vrati NULL znači da je došlo do promjene
     *
     * @param $jsonAttributes
     * @param $existingProduct
     * @param $key
     * @return false|string|null
     */
    protected function mergeJson($jsonAttributes, $existingProduct, $key)
    {
        $decodedJson = json_decode($existingProduct[$key], true);

        if (!is_array($decodedJson)) {
            $decodedJsonStr = (string)$decodedJson;
            $decodedJson = [
                3 => $decodedJsonStr
            ];
        }

        $mergedValues = array_replace($decodedJson, $jsonAttributes[$key] ?? []);
        if ($mergedValues != $decodedJson) {
            return json_encode($mergedValues, JSON_UNESCAPED_UNICODE);
        }

        return NULL;
    }

    /**
     * @param $insertArray
     * @param $currentInsertArray
     * @return mixed
     */
    protected function mergeInsertArrays($insertArray, $currentInsertArray)
    {
        foreach ($currentInsertArray as $table => $values) {
            if (!isset($insertArray[$table])) {
                $insertArray[$table] = [];
            }
            $insertArray[$table] = array_merge($insertArray[$table], $currentInsertArray[$table]);
        }

        return $insertArray;
    }

    /**
     * @param $files
     * @param $sourceDirectory
     * @param $targetFailedDirectory
     */
    protected function moveFailedFiles($files, $sourceDirectory, $targetFailedDirectory)
    {
        foreach ($files as $file) {
            if ($file["error"] == true) {
                if (!file_exists($targetFailedDirectory)) {
                    mkdir($targetFailedDirectory, 0777, true);
                }
                rename($sourceDirectory . $file["file"], $targetFailedDirectory . $file["file"]);
            }
        }
    }

    /**
     * @param $fileEntityType
     * @param $baseEntityType
     * @param $uniqueEntityAttribute
     * @param $extensionAttributes
     * @return array|array[]
     * @throws \Exception
     */
    public function importFiles($fileEntityType, $baseEntityType, $uniqueEntityAttribute, $extensionAttributes, $customManagerAndMethod = null)
    {
        /** @var AttributeSet $baseEntityAttributeSet */
        $baseEntityAttributeSet = $this->entityManager->getAttributeSetByCode($baseEntityType);
        if (empty($baseEntityAttributeSet)) {
            throw new \Exception(sprintf("%s base entity type does not exist", $baseEntityType));
        }

        /** @var AttributeSet $fileEntityAttributeSet */
        $fileEntityAttributeSet = $this->entityManager->getAttributeSetByCode($fileEntityType);
        if (empty($fileEntityAttributeSet)) {
            throw new \Exception(sprintf("%s file entity type does not exist", $fileEntityType));
        }

        $baseEntityAttributes = array_keys($this->getEntitiesArray(["a1.attribute_code"], "attribute", ["attribute_code"], "JOIN entity_type a2 ON a1.entity_type_id = a2.id", "WHERE a2.entity_type_code = '{$baseEntityType}'"));
        if (!in_array($uniqueEntityAttribute, $baseEntityAttributes)) {
            throw new \Exception(sprintf("%s unique entity attribute does not exist in %s base entity type", $uniqueEntityAttribute, $baseEntityType));
        }

        $baseEntityIdAttribute = $baseEntityType . "_id";
        $fileEntityAttributes = array_keys($this->getEntitiesArray(["a1.attribute_code"], "attribute", ["attribute_code"], "JOIN entity_type a2 ON a1.entity_type_id = a2.id", "WHERE a2.entity_type_code = '{$fileEntityType}'"));
        if (!in_array($baseEntityIdAttribute, $fileEntityAttributes)) {
            throw new \Exception(sprintf("Could not determine base entity id attribute code, tried: %s", $baseEntityIdAttribute));
        }

        if (!empty($extensionAttributes)) {
            $extensionAttributes = explode(",", $extensionAttributes);
            $extensionAttributesDiff = array_diff($extensionAttributes, $fileEntityAttributes);
            if (!empty($extensionAttributesDiff)) {
                throw new \Exception(sprintf("The following attributes do not exist in file entity type: %s", implode(", ", $extensionAttributesDiff)));
            }
        } else {
            $extensionAttributes = [];
        }

        $ret = [];

        $changedIds[$fileEntityType] = Array();

        $dateNowStr = date("Y-m-d_H_i_s");
        $sourceDirectory = $this->getWebPath() . "Documents/import_files/" . $fileEntityType . "/";
        $targetDirectory = $this->getWebPath() . "Documents/" . $fileEntityType . "/";
        $targetFailedDirectory = $this->getWebPath() . "Documents/import_files_failed/" . $dateNowStr . "/" . $fileEntityType . "/";
        $targetDirectoryUsesId = false;

        if ($fileEntityType == "product_images") {
            $targetDirectory = $this->getWebPath() . "Documents/Products/";
            $targetDirectoryUsesId = true;
        }

        $additionalAttributes = Array();
        if(isset($customManagerAndMethod) && !empty($customManagerAndMethod)){

            $tmp = explode("#",$customManagerAndMethod);

            if(count($tmp) != 2){
                throw new \Exception("{$customManagerAndMethod} is not a valid manager#method");
            }

            $manager = $this->getContainer()->get($tmp[0]);
            if (empty($manager)) {
                throw new \Exception("{$manager} manager does not exist");
            }
            if (!EntityHelper::checkIfMethodExists($manager, $tmp[1])) {
                throw new \Exception("Method {$tmp[1]} does not exist in manager {$manager}");
            }

            $customData = $manager->{$tmp[1]}($fileEntityType, $baseEntityType, $uniqueEntityAttribute, $extensionAttributes);

            if(isset($customData["file_entity_type"]) && !empty($customData["file_entity_type"])){
                $fileEntityType = $customData["file_entity_type"];
            }
            if(isset($customData["source_directory"]) && !empty($customData["source_directory"])){
                $sourceDirectory = $customData["source_directory"];
            }
            if(isset($customData["target_directory"]) && !empty($customData["target_directory"])){
                $targetDirectory = $customData["target_directory"];
            }
            if(isset($customData["target_failed_directory"]) && !empty($customData["target_failed_directory"])){
                $targetFailedDirectory = $customData["target_failed_directory"];
            }
            if(isset($customData["additional_attributes"]) && !empty($customData["additional_attributes"])){
                $additionalAttributes = $customData["additional_attributes"];
            }
        }

        if (!file_exists($sourceDirectory)) {
            mkdir($sourceDirectory, 0777, true);
        }
        if (!file_exists($targetDirectory)) {
            mkdir($targetDirectory, 0777, true);
        }

        $insertArray = [];

//        /**
//         * Only used to get ord
//         */
//        $fileEntities = [];
//        if (in_array("ord", $fileEntityAttributes)) {
//            $fileEntities = $this->getEntitiesArray(["id", "MAX(ord) AS ord", $baseEntityIdAttribute], $fileEntityType . "_entity", ["product_id"], "", "WHERE entity_state_id = 1", "", "GROUP BY product_id");
//        }
        $baseEntities = $this->getEntitiesArray(["id", $uniqueEntityAttribute], $baseEntityType . "_entity", [$uniqueEntityAttribute]);
        $fileEntities = $this->getEntitiesArray(["id", "file"], $fileEntityType . "_entity", ["file"], "", "WHERE entity_state_id = 1");

        $importFiles = scandir($sourceDirectory);

        foreach ($importFiles as $file) {

            /**
             * Skip dotfiles and don't do any actions with them
             */
            if (strpos($file, ".") === 0) {
                continue;
            }

            if (is_dir($sourceDirectory . $file)) {
                continue;
            }

            $bytes = filesize($sourceDirectory . $file);
            if (empty($bytes)) {
                $ret[] = ["error" => true, "file" => $file, "message" => "File is empty or has an invalid file size"];
                continue;
            }

            $n = strrpos($file, ".");
            if ($n === false) {
                $ret[] = ["error" => true, "file" => $file, "message" => "File has no extension"];
                continue;
            }

            $extension = strtolower(substr($file, $n + 1));
            if (empty($extension)) {
                $ret[] = ["error" => true, "file" => $file, "message" => "File has empty extension"];
                continue;
            }

            /**
             * Max length in database
             */
            if (strlen($extension) >= 5) {
                $ret[] = ["error" => true, "file" => $file, "message" => "File has an invalid extension"];
                continue;
            }

            $filename = substr($file, 0, $n);
            $filenameParts = explode("_", $filename, count($extensionAttributes) + 1);

            /**
             * Filename must consist of an unique entity attribute + extension attributes
             */
            if (count($filenameParts) < count($extensionAttributes) + 1) {
                $ordKey = array_search("ord", $extensionAttributes);
                if ($ordKey !== false && !isset($filenameParts[$ordKey + 1])) {
                    $filenameParts[$ordKey + 1] = 1;
                } else {
                    $ret[] = ["error" => true, "file" => $file, "message" => "Filename format does not match the specified list of attributes"];
                    continue;
                }
            }

            $filenameParts = array_map("trim", $filenameParts);
            $uniqueEntityAttributeValue = array_shift($filenameParts);

            if(stripos($uniqueEntityAttributeValue,"_") !== false){
                $uniqueEntityAttributeValue = explode("_",$uniqueEntityAttributeValue)[0];
                if(empty($uniqueEntityAttributeValue)){
                    $ret[] = ["error" => true, "file" => $file, "message" => "Empty base entity with the specified identifier after _ split"];
                    continue;
                }
            }

            if (!isset($baseEntities[$uniqueEntityAttributeValue])) {
                $ret[] = ["error" => true, "file" => $file, "message" => "Base entity with the specified identifier could not be found {$uniqueEntityAttributeValue}"];
                continue;
            }

            $baseEntityId = $baseEntities[$uniqueEntityAttributeValue]["id"];
            $changedIds[$fileEntityType][] = $baseEntityId;

            $filename = $this->helperManager->nameToFilename($filename);

            $targetFile = $filename . "." . $extension;
            if ($targetDirectoryUsesId) {
                $targetFile = $baseEntityId . "/" . $targetFile;
                if (!file_exists($targetDirectory . $baseEntityId . "/")) {
                    mkdir($targetDirectory . $baseEntityId . "/", 0777, true);
                }
            }

            if (!isset($_ENV["FILES_IMPORT_OVERWRITE_EXISTING"]) || $_ENV["FILES_IMPORT_OVERWRITE_EXISTING"] == 0) {
                $targetFile = $this->helperManager->incrementFileName($targetDirectory, $targetFile);
            }

            echo $sourceDirectory . $file . " -> " . $targetDirectory . $targetFile . "\n";

            if (!rename($sourceDirectory . $file, $targetDirectory . $targetFile)) {
                $ret[] = ["error" => true, "file" => $file, "message" => "Could not move this file"];
                continue;
            }

            if (!isset($fileEntities[$targetFile])) {

                $insertFileEntity = new InsertModel($fileEntityAttributeSet);
                $insertFileEntity->add("file", $targetFile)
                    ->add("filename", $filename)
                    ->add("file_type", $extension)
                    ->add("size", FileHelper::formatSizeUnits($bytes))
                    ->add("file_source", "files_import")
                    ->add($baseEntityIdAttribute, $baseEntityId);

                foreach ($filenameParts as $key => $attributeValue) {
                    $insertFileEntity->add($extensionAttributes[$key], $attributeValue);
                }

                foreach ($additionalAttributes as $key => $attributeValue) {
                    $insertFileEntity->add($key, $attributeValue);
                }

                $insertArray[$fileEntityType . "_entity"][$targetFile] = $insertFileEntity->getArray();

                $ret[] = ["error" => false, "file" => $file, "message" => null];
            }
        }

        $this->executeInsertQuery($insertArray);

        $this->moveFailedFiles($ret, $sourceDirectory, $targetFailedDirectory);

        if(!empty($changedIds[$fileEntityType])){

            if(empty($this->crmProcessManager)){
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $this->crmProcessManager->afterDocumentImportCompleted($changedIds);
        }

        return $ret;
    }

    /**
     * @param $entities
     * @param null $storeId
     * @param array $attributesArray
     * @return string
     */
    public function generateSimpleEntityImportTemplate($entities, $entityTypeCode, $storeId = null, $attributesArray = Array())
    {
        if(empty($storeId)){
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        if (empty($this->excelManager)) {
            $this->excelManager = $this->container->get("excel_manager");
        }

        if(empty($this->entityManager)){
            $this->entityManager = $this->getContainer()->get("entity_manager");
        }

        $attributesTmp = $this->entityManager->getAttributesOfEntityType($entityTypeCode);

        $attributes = Array();
        foreach ($attributesTmp as $attributeTmp){
            $attributes[$attributeTmp->getAttributeCode()] = $attributeTmp;
            $attributes[EntityHelper::makeAttributeName($attributeTmp->getAttributeCode())] = $attributeTmp;
        }

        /**
         * Prepare data
         */
        $header = [];
        foreach ($attributesArray as $attributeCode => $attributeDetails) {

            if(isset($attributes[$attributeCode])){


                $attributesArray[$attributeCode]["enable_store"] = false;
                if(StringHelper::endsWith($attributes[$attributeCode]->getFrontendType(),"_store")){
                    $attributesArray[$attributeCode]["enable_store"] = true;
                }

                $attributesArray[$attributeCode]["sufix"] = "";
                $attributesArray[$attributeCode]["prefix"] = "";
                if(!$attributeDetails["enable_import"]){
                    $attributesArray[$attributeCode]["prefix"] = "_";
                }
                if($attributesArray[$attributeCode]["enable_store"]){
                    $attributesArray[$attributeCode]["prefix"].= "store:";
                    $attributesArray[$attributeCode]["sufix"] = ":{$storeId}";
                }
                $attributesArray[$attributeCode]["export_key"] = "{$attributesArray[$attributeCode]["prefix"]}{$attributeCode}{$attributesArray[$attributeCode]["sufix"]}";
                $header["{$attributesArray[$attributeCode]["export_key"]}"][] = null;
            }
        }

        $data = [];

        foreach ($entities as $entity){

            foreach ($attributesArray as $attributeCode => $attributeDetails) {

                if(isset($attributes[$attributeCode])){

                    if (strpos($attributeCode, ".")) {
                        $parts = explode(".",$attributeCode);
                        $getters = EntityHelper::getPropertyAccessor($attributeCode);

                        $value = $entity->{$getters[0]}();

                        $count = count($getters);
                        $count = $count - 2;

                        foreach ($getters as $key => $getter) {
                            if ($key == 0) {
                                continue;
                            }

                            if($attributes[$parts[0]]->getFrontendType() == "multiselect"){
                                $tmpVal = Array();
                                if(EntityHelper::isCountable($value)){
                                    foreach ($value as $v){
                                        $tmpVal[] = $v->{$getter}();
                                    }
                                }
                                $value = implode(";",$tmpVal);
                            }
                            else{
                                if (!empty($value) && is_object($value)) {
                                    $value = $value->{$getter}();
                                } else {
                                    $value = "";
                                }
                            }
                        }
                    } else {
                        $getter = EntityHelper::makeGetter($attributeCode);
                        $value = $entity->{$getter}();
                    }
                    /*$getter = EntityHelper::makeGetter($attributeCode);
                    $value = $entity->{$getter}();*/

                    if($attributeDetails["enable_store"]){
                        if(isset($value[$storeId])){
                            $value = $value[$storeId];
                        }
                        else{
                            $value = null;
                        }
                    }

                    $data["{$attributeDetails["export_key"]}"][] = $value;
                }
            }
        }

        return $this->excelManager->exportArray($data, "simple_{$entityTypeCode}_import", null, false, false, true);
    }

    /**
     * @param array $productIds
     * @param null $storeId
     * @return string
     */
    public function generateSimpleProductAttributesImportTemplate($productIds = Array(), $storeId = null)
    {
        $products = Array();
        $sProductConfigurationWhere = "";
        if(empty($storeId)){
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        if(!empty($productIds)){

            if(empty($this->databaseContext)){
               $this->databaseContext = $this->container->get("database_context");
            }

            $q = "SELECT s_product_attribute_configuration_id FROM s_product_attributes_link_entity AS spal WHERE spal.product_id IN (".implode(",",$productIds).")
                UNION
                SELECT s_product_attribute_configuration_id FROM s_product_configuration_product_group_link_entity AS spcpg WHERE spcpg.product_group_id IN (SELECT product_group_id FROM product_product_group_link_entity WHERE product_id IN (".implode(",",$productIds)."))";
            $sProductConfigurationIds = $this->databaseContext->getAll($q);

            if(!empty($sProductConfigurationIds)){
                $sProductConfigurationIds = array_column($sProductConfigurationIds,"s_product_attribute_configuration_id");
                $sProductConfigurationWhere = " AND id IN (".implode(",",$sProductConfigurationIds).") ";
            }
            else{
                $productIds = null;
            }
        }

        $existingSProductAttributeConfigurations = $this->getExistingSProductAttributeConfigurations("filter_key", ["id"],$sProductConfigurationWhere);

        if(!empty($productIds)){

            $q = "SELECT p.id, p.`code`, JSON_UNQUOTE(JSON_EXTRACT(p.name, '$.\"{$storeId}\"')) AS name, GROUP_CONCAT(CONCAT(spac.filter_key,'#',spac.s_product_attribute_configuration_type_id,'#',spal.attribute_value) SEPARATOR '###') AS attributes FROM product_entity as p LEFT JOIN s_product_attributes_link_entity as spal ON p.id = spal.product_id
            LEFT JOIN s_product_attribute_configuration_entity as spac ON spal.s_product_attribute_configuration_id = spac.id WHERE p.id IN (".implode(",",$productIds).") AND spac.id IN (".implode(",",$sProductConfigurationIds).") GROUP BY p.id";
            $products = $this->databaseContext->getAll($q);
        }

        if (empty($this->excelManager)) {
            $this->excelManager = $this->container->get("excel_manager");
        }

        /**
         * Prepare data
         */
        $header = [];
        $header["code"][] = null;

        foreach ($existingSProductAttributeConfigurations as $a) {
            $header["attribute:{$a["filter_key"]}"][] = null;
        }

        if(empty($products)){
            $data = $header;
        }
        else{

            $data = [];

            foreach ($products as $product){

                $data["code"][] = $product["code"];
                $data["_store:name:" . $storeId][] = $product["name"];

                $preparedAttributes = Array();
                $attributes = explode("###",$product["attributes"]);
                if(!empty($attributes)){
                    foreach ($attributes as $attribute){
                        $attribute = explode("#",$attribute);
                        if(count($attribute) <> 3){
                            continue;
                        }

                        $preparedAttributes[$attribute[0]][] =  $attribute[2];
                    }
                }

                foreach ($existingSProductAttributeConfigurations as $a) {
                    if(isset($preparedAttributes[$a["filter_key"]])){
                        $data["attribute:{$a["filter_key"]}"][] = implode(";",$preparedAttributes[$a["filter_key"]]);
                    }
                    else{
                        $data["attribute:{$a["filter_key"]}"][] = null;
                    }
                }
            }
        }

        return $this->excelManager->exportArray($data, "simple_product_import", null, false, false, true);
    }

    /**
     * @return string
     */
    public function generateConfigurableProductImportTemplate()
    {
        if (empty($this->excelManager)) {
            $this->excelManager = $this->container->get("excel_manager");
        }

        $existingSimpleProducts = $this->getExistingEntity("product_entity", "id", ["id", "name"], "WHERE entity_state_id = 1 AND product_type_id = 1");

        $data = [];

        foreach ($existingSimpleProducts as $existingSimpleProduct) {

            //$data["product:id"][] = $existingSimpleProduct["id"];

            $nameArray = json_decode($existingSimpleProduct["name"], true);
            foreach ($nameArray as $storeId => $name) {
                $data["store:name:" . $storeId][] = $name;
            }
        }

        return $this->excelManager->exportArray($data, "configurable_product_import", null, false, false, true);
    }

    /**
     * Konfiguracije i grupe proizvoda se ne importaju i moraju postojati kako bi se vrijednosti importale
     *
     * @param $fileLocation
     * @return array
     * @throws \Exception
     */
    public function importSimpleProducts($fileLocation)
    {
        $ret = [
            "errors" => null,
            "rows" => 0
        ];

        if (empty($this->excelManager)) {
            $this->excelManager = $this->container->get("excel_manager");
        }

        $data = $this->excelManager->importEntityArray($fileLocation);
        if (empty($data)) {
            throw new \Exception("File is empty");
        }
        if (!isset($data["simple_product_import"]) || empty($data["simple_product_import"])) {
            throw new \Exception("Product sheet is empty");
        }
//        if (!isset($data["codebook"]) || empty($data["codebook"])) {
//            throw new \Exception("Codebook sheet is empty");
//        }

        $existingSimpleProducts = $this->getExistingProducts("code", [], false, 3, "AND product_type_id IN (1,3,4,6) ");
        $existingProductGroups = $this->getEntitiesArray(["id"], "product_group_entity", ["id"], "WHERE a1.entity_state_id = 1");
        $existingProductProductGroupLinks = $this->getExistingProductProductGroupLinks();
        $existingSRoutes = $this->getExistingSRoutes();
        $existingSProductAttributeConfigurations = $this->getExistingSProductAttributeConfigurations("filter_key", ["id", "s_product_attribute_configuration_type_id"]);
        $existingSProductAttributeConfigurationOptions = $this->getExistingSProductAttributeConfigurationOptions(null, ["id"], ["configuration_attribute_id", "MD5(configuration_value)"]);
        $existingSProductAttributeLinks = $this->getExistingSProductAttributesLinks();
        $productEntityAttributes = $this->getEntityAttributes("product");

        $insertArray = [
            // product_entity
            // s_product_attribute_configuration_options_entity
        ];
        $insertArray2 = [
            // s_product_attributes_link_entity
            // product_product_group_link_entity
            // s_route_entity
        ];
        $updateArray = [
            // product_entity
            // s_product_attributes_link_entity
            // s_route_entity
        ];

        $productIds = [];
        $productRemoteCodes = [];
        $reservedUrls = [];

        foreach ($data["simple_product_import"] as $rowId => $d) {

            if (!isset($d["code"])) {
                $ret["errors"] .= sprintf("Row %d: Value at column 'code' is not set\n", $rowId);
                continue;
            }

            $code = trim($d["code"]);
            if (empty($code)) {
                $ret["errors"] .= sprintf("Row %d: Value at column 'code' is empty\n", $rowId);
                continue;
            }

            $productInsert = $productUpdate = NULL;
            if (!isset($existingSimpleProducts[$code])) {
                /**
                 * Fill defaults
                 */
                $productInsert = new InsertModel($this->asProduct);
                $productInsert->add("code", $code)
                    ->add("currency_id", $_ENV["DEFAULT_CURRENCY"])
                    ->add("product_type_id", CrmConstants::PRODUCT_TYPE_SIMPLE)
                    ->add("is_visible", 1)
                    ->add("active", 1)
                    ->add("qty_step", 1)
                    ->add("auto_generate_url", 1)
                    ->add("template_type_id", 5)
                    ->add("ready_for_webshop", 0)
                    ->add("keep_url", 1)
                    ->add("is_saleable", 1)
                    ->add("ord", 100)
                    ->add("content_changed", 1)
                    ->add("remote_source", "default_import_manual");
            } else {
                $productUpdate = new UpdateModel($existingSimpleProducts[$code]);
            }

            $jsonAttributes = [];
            $currentInsertArray = [];
            $currentInsertArray2 = [];

            unset($d["id"]);
            unset($d["code"]);

            /**
             * TODO: warning ako netko navede grupu/atribut/opciju/kolonu koja ne postoji
             */

            foreach ($d as $column => $value) {

                /**
                 * Ako kolona pocinje sa _ ta se nece importati ili updateati
                 */
                if(StringHelper::startsWith($column,"_")){
                    continue;
                }

                $value = $this->sanitizeExcelValue($value);

                if (strpos($column, "store:") !== false) {

                    if (!is_string($value)) {
                        $value = (int)$value;
                    }

                    $attributeParts = explode(":", $column, 3);
                    if (!empty($attributeParts) && count($attributeParts) == 3) {
                        /**
                         * Kolona mora postojati i biti json vrste
                         */
                        if (isset($productEntityAttributes[$attributeParts[1]]) && $productEntityAttributes[$attributeParts[1]] == "json") {
                            if ($attributeParts[1] == "url" && $value) {
                                $value = $this->routeManager->prepareUrl($value);
                            }
                            //if (!empty($value)) {
                                $jsonAttributes[$attributeParts[1]][$attributeParts[2]] = $value;
                            //}
                        }
                    }

                } else if (strpos($column, "attribute:") !== false) {

                    $filterKey = substr($column, strlen("attribute:"));
                    if (isset($existingSProductAttributeConfigurations[$filterKey])) {

                        $configurationId = $existingSProductAttributeConfigurations[$filterKey]["id"];
                        $configurationTypeId = $existingSProductAttributeConfigurations[$filterKey]["s_product_attribute_configuration_type_id"];
                        if ($configurationTypeId == 1 || $configurationTypeId == 2) {

                            if (!$value) {
                                continue;
                            }

                            $attributeValues = [$value];
                            if ($configurationTypeId == 2) {
                                $attributeValues = explode(";", $value);
                            }

                            foreach ($attributeValues as $attributeValue) {
                                /**
                                 * Konfiguracija je autocomplete ili multiselect, koriste se opcije
                                 */
                                $optionKey = $configurationId . "_" . md5($attributeValue);
                                if (!isset($existingSProductAttributeConfigurationOptions[$optionKey])) {
                                    /**
                                     * Opcija ne postoji, linkovi ne postoje
                                     */
                                    if (!isset($currentInsertArray["s_product_attribute_configuration_options_entity"][$optionKey])) {
                                        $sProductAttributeConfigurationOptionsInsert = new InsertModel($this->asSProductAttributeConfigurationOptions);
                                        $sProductAttributeConfigurationOptionsInsert->add("configuration_value", $attributeValue)
                                            ->add("configuration_attribute_id", $configurationId);
                                        $currentInsertArray["s_product_attribute_configuration_options_entity"][$optionKey] = $sProductAttributeConfigurationOptionsInsert->getArray();
                                    }

                                    $linkKey = md5($code . $filterKey . $attributeValue);
                                    if (!isset($currentInsertArray2["s_product_attributes_link_entity"][$linkKey])) {
                                        $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                        if (isset($existingSimpleProducts[$code])) {
                                            $sProductAttributeLinksInsert->add("product_id", $existingSimpleProducts[$code]["id"]);
                                        } else {
                                            $sProductAttributeLinksInsert->addLookup("product_id", $code, "product_entity");
                                        }
                                        $sProductAttributeLinksInsert->add("s_product_attribute_configuration_id", $configurationId)
                                            ->add("attribute_value", $attributeValue)
                                            ->addLookup("configuration_option", $optionKey, "s_product_attribute_configuration_options_entity")
                                            ->addFunction(function ($entity) {
                                                $entity["attribute_value_key"] = md5($entity["product_id"] .
                                                    $entity["s_product_attribute_configuration_id"] .
                                                    $entity["configuration_option"]);
                                                return $entity;
                                            });
                                        $currentInsertArray2["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert;
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
                                        $linkKey = md5($code . $filterKey . $attributeValue);
                                        if (!isset($currentInsertArray2["s_product_attributes_link_entity"][$linkKey])) {
                                            $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                            if (isset($existingSimpleProducts[$code])) {
                                                $sProductAttributeLinksInsert->add("product_id", $existingSimpleProducts[$code]["id"]);
                                            } else {
                                                $sProductAttributeLinksInsert->addLookup("product_id", $code, "product_entity");
                                            }
                                            $sProductAttributeLinksInsert->add("s_product_attribute_configuration_id", $configurationId)
                                                ->add("attribute_value", $attributeValue)
                                                ->add("configuration_option", $optionId)
                                                ->addFunction(function ($entity) {
                                                    $entity["attribute_value_key"] = md5($entity["product_id"] .
                                                        $entity["s_product_attribute_configuration_id"] .
                                                        $entity["configuration_option"]);
                                                    return $entity;
                                                });
                                            $currentInsertArray2["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert;
                                        }
                                    } else {
                                        /**
                                         * Jedan ili više linkova postoji
                                         */
                                        if (isset($existingSimpleProducts[$code])) {
                                            /**
                                             * Proizvod postoji, linkovi potencijalno postoje
                                             */
                                            $attributeValueKey = md5($existingSimpleProducts[$code]["id"] . $configurationId . $optionId);
                                            if (!isset($existingSProductAttributeLinks[$configurationId][$optionId][$attributeValueKey])) {
                                                /**
                                                 * Link ne postoji
                                                 */
                                                $linkKey = md5($code . $filterKey . $attributeValue);
                                                if (!isset($currentInsertArray2["s_product_attributes_link_entity"][$linkKey])) {
                                                    $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                                    $sProductAttributeLinksInsert->add("product_id", $existingSimpleProducts[$code]["id"])
                                                        ->add("s_product_attribute_configuration_id", $configurationId)
                                                        ->add("attribute_value", $attributeValue)
                                                        ->add("configuration_option", $optionId)
                                                        ->add("attribute_value_key", $attributeValueKey);
                                                    $currentInsertArray2["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert->getArray();
                                                }
                                            } else {
                                                $sProductAttributeLink = $existingSProductAttributeLinks[$configurationId][$optionId][$attributeValueKey];
                                                $sProductAttributeLinksUpdate = new UpdateModel($sProductAttributeLink);
                                                $sProductAttributeLinksUpdate->add("attribute_value", $attributeValue);
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
                                            $linkKey = md5($code . $filterKey . $attributeValue);
                                            if (!isset($currentInsertArray2["s_product_attributes_link_entity"][$linkKey])) {
                                                $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                                $sProductAttributeLinksInsert->addLookup("product_id", $code, "product_entity")
                                                    ->add("s_product_attribute_configuration_id", $configurationId)
                                                    ->add("attribute_value", $attributeValue)
                                                    ->add("configuration_option", $optionId)
                                                    ->addFunction(function ($entity) {
                                                        $entity["attribute_value_key"] = md5($entity["product_id"] .
                                                            $entity["s_product_attribute_configuration_id"] .
                                                            $entity["configuration_option"]);
                                                        return $entity;
                                                    });
                                                $currentInsertArray2["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert;
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            $optionId = 0;
                            if ($configurationTypeId == 4) {
                                $value = (int)$value;
                            } else {
                                if (!$value) {
                                    continue;
                                }
                            }

                            if (!isset($existingSProductAttributeLinks[$configurationId][$optionId])) {
                                /**
                                 * Linkovi ne postoje, dodaj sve
                                 */
                                $linkKey = md5($code . $filterKey . $value);
                                if (!isset($currentInsertArray2["s_product_attributes_link_entity"][$linkKey])) {
                                    $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                    if (isset($existingSimpleProducts[$code])) {
                                        $sProductAttributeLinksInsert->add("product_id", $existingSimpleProducts[$code]["id"]);
                                    } else {
                                        $sProductAttributeLinksInsert->addLookup("product_id", $code, "product_entity");
                                    }
                                    $sProductAttributeLinksInsert->add("s_product_attribute_configuration_id", $configurationId)
                                        ->add("attribute_value", $value)
                                        ->add("configuration_option", NULL)
                                        ->addFunction(function ($entity) {
                                            $entity["attribute_value_key"] = md5($entity["product_id"] .
                                                $entity["s_product_attribute_configuration_id"] .
                                                $entity["configuration_option"]);
                                            return $entity;
                                        });
                                    $currentInsertArray2["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert;
                                }
                            } else {
                                /**
                                 * Jedan ili više linkova postoji
                                 */
                                if (isset($existingSimpleProducts[$code])) {
                                    /**
                                     * Proizvod postoji, linkovi potencijalno postoje
                                     */
                                    $attributeValueKey = md5($existingSimpleProducts[$code]["id"] . $configurationId . NULL);
                                    if (!isset($existingSProductAttributeLinks[$configurationId][$optionId][$attributeValueKey])) {
                                        /**
                                         * Link ne postoji
                                         */
                                        $linkKey = md5($code . $filterKey . $value);
                                        if (!isset($currentInsertArray2["s_product_attributes_link_entity"][$linkKey])) {
                                            $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                            $sProductAttributeLinksInsert->add("product_id", $existingSimpleProducts[$code]["id"])
                                                ->add("s_product_attribute_configuration_id", $configurationId)
                                                ->add("attribute_value", $value)
                                                ->add("configuration_option", NULL)
                                                ->add("attribute_value_key", $attributeValueKey);
                                            $currentInsertArray2["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert->getArray();
                                        }
                                    } else {
                                        $sProductAttributeLink = $existingSProductAttributeLinks[$configurationId][$optionId][$attributeValueKey];
                                        $sProductAttributeLinksUpdate = new UpdateModel($sProductAttributeLink);
                                        $sProductAttributeLinksUpdate->add("attribute_value", $value);
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
                                    $linkKey = md5($code . $filterKey . $value);
                                    if (!isset($currentInsertArray2["s_product_attributes_link_entity"][$linkKey])) {
                                        $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                        $sProductAttributeLinksInsert->addLookup("product_id", $code, "product_entity")
                                            ->add("s_product_attribute_configuration_id", $configurationId)
                                            ->add("attribute_value", $value)
                                            ->add("configuration_option", NULL)
                                            ->addFunction(function ($entity) {
                                                $entity["attribute_value_key"] = md5($entity["product_id"] .
                                                    $entity["s_product_attribute_configuration_id"] .
                                                    $entity["configuration_option"]);
                                                return $entity;
                                            });
                                        $currentInsertArray2["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert;
                                    }
                                }
                            }
                        }
                    }

                } else if (strpos($column, "related:product_groups") !== false) {

                    if (!$value) {
                        continue;
                    }

                    $productGroupIds = explode(";", $value);
                    if (!empty($productGroupIds)) {
                        foreach ($productGroupIds as $productGroupId) {
                            $productGroupId = (int)$productGroupId;
                            if (isset($existingProductGroups[$productGroupId])) {
                                $productProductGroupLinkKey = $code . "_" . $productGroupId;
                                if (!isset($existingProductProductGroupLinks[$productProductGroupLinkKey])) {
                                    $productProductGroupLinkInsert = new InsertModel($this->asProductProductGroupLink);
                                    $productProductGroupLinkInsert->add("product_group_id", $productGroupId);
                                    if (isset($existingSimpleProducts[$code])) {
                                        $productProductGroupLinkInsert->add("product_id", $existingSimpleProducts[$code]["id"]);
                                        $productIds[] = $existingSimpleProducts[$code]["id"];
                                    } else {
                                        $productProductGroupLinkInsert->addLookup("product_id", $code, "product_entity");
                                        $productRemoteCodes[] = $code;
                                    }
                                    $currentInsertArray2["product_product_group_link_entity"][$productProductGroupLinkKey] = $productProductGroupLinkInsert;
                                }
                            } else {
                                $ret["errors"] .= sprintf("Row %d: Product group %d does not exist\n", $rowId, $productGroupId);
                            }
                        }
                    }

                } else {

                    /**
                     * Make sure this column exists
                     */
                    if (isset($productEntityAttributes[$column])) {

                        if ($productEntityAttributes[$column] == "bool") {
                            if ($value === null) {
                                continue;
                            }
                            $value = (bool)$value;
                        } else if ($productEntityAttributes[$column] == "decimal") {
                            $value = (float)$value;
                        } else if ($productEntityAttributes[$column] == "integer") {
                            $value = (int)$value;
                        }

                        if (!empty($productInsert)) {
                            $productInsert->add($column, $value);
                        } else if (!empty($productUpdate)) {
                            ($productEntityAttributes[$column] == "decimal") ?
                                $productUpdate->addFloat($column, $value) :
                                $productUpdate->add($column, $value);
                        }
                    }
                }
            }

            if (!empty($productInsert)) {

                /**
                 * Kod product inserta name mora biti postavljen
                 */
                if (!isset($jsonAttributes["name"])) {
                    $ret["errors"] .= sprintf("Row %d: Value at column 'name' is empty\n", $rowId);
                    continue;
                }

                $jsonNameExists = false;

                /**
                 * Provjeri ako url nije postavljen za jedan od store-ova pa ga generiraj pomoću imena proizvoda
                 */
                foreach ($jsonAttributes["name"] as $storeId => $simpleProductName) {

                    if (empty($simpleProductName)) {
                        continue;
                    }

                    $jsonNameExists = true;
                    if (!isset($jsonAttributes["url"][$storeId])) {
                        $i = 1;
                        $url = $key = $this->routeManager->prepareUrl($simpleProductName);
                        while (isset($existingSRoutes[$storeId . "_" . $url]) || in_array($storeId . "_" . $url, $reservedUrls)) {
                            $url = $key . "-" . $i++;
                        }
                        $jsonAttributes["url"][$storeId] = $url;
                        $reservedUrls[] = $storeId . "_" . $url;
                    }
                }

                if (!$jsonNameExists) {
                    $ret["errors"] .= sprintf("Row %d: All values for 'name' are empty\n", $rowId);
                    continue;
                }

                foreach ($jsonAttributes["url"] as $storeId => $url) {

                    $sRouteInsert = new InsertModel($this->asSRoute);
                    $sRouteInsert->add("request_url", $url)
                        ->add("destination_type", "product")
                        ->add("store_id", $storeId)
                        ->addLookup("destination_id", $code, "product_entity");
                    $insertArray2["s_route_entity"][$storeId . "_" . $url] = $sRouteInsert;
                }

                if (!isset($jsonAttributes["meta_title"])) {
                    $jsonAttributes["meta_title"] = $jsonAttributes["name"];
                }
                if (!isset($jsonAttributes["meta_description"])) {
                    $jsonAttributes["meta_description"] = $jsonAttributes["name"];
                }

                /**
                 * Dodavanje json atributa prije inserta
                 */
                foreach ($jsonAttributes as $jsonAttributeKey => $jsonAttributeValues) {
                    $productInsert->add($jsonAttributeKey, json_encode($jsonAttributeValues, JSON_UNESCAPED_UNICODE));
                }

                if (!empty($productInsert->getArray())) {
                    $insertArray["product_entity"][$code] = $productInsert->getArray();
                }

            } else if (!empty($productUpdate)) {

                $nameJson = $this->mergeJson($jsonAttributes, $existingSimpleProducts[$code], "name");
                if ($nameJson) {
                    $productUpdate->add("name", $nameJson, false)
                        ->add("meta_title", $nameJson, false)
                        ->add("meta_description", $nameJson, false);
                }

                $decodedUrl = json_decode($existingSimpleProducts[$code]["url"], true);

                /**
                 * Kombiniraj novi array sa starim i pritom pregazi stare url-ove s novima
                 */
                $mergedUrl = array_replace($decodedUrl, $jsonAttributes["url"] ?? []);
                if ($mergedUrl != $decodedUrl) {

                    /**
                     * Novi i stari array se razlikuju, jedan ili više url-ova su dodani ili su se promijenili
                     */
                    foreach ($mergedUrl as $storeId => $url) {

                        if (!isset($decodedUrl[$storeId]) || $decodedUrl[$storeId] != $url) {
                            /**
                             * Generiraj novi url
                             */
                            $i = 1;
                            $key = $url;
                            while (isset($existingSRoutes[$storeId . "_" . $url]) || in_array($storeId . "_" . $url, $reservedUrls)) {
                                $url = $key . "-" . $i++;
                            }
                            $mergedUrl[$storeId] = $url;
                            $reservedUrls[] = $storeId . "_" . $url;

                            /**
                             * Insertaj novi route
                             */
                            $sRouteInsert = new InsertModel($this->asSRoute);
                            $sRouteInsert->add("request_url", $url)
                                ->add("destination_type", "product")
                                ->add("store_id", $storeId)
                                ->add("destination_id", $productUpdate->getEntityId());

                            $insertArray2["s_route_entity"][$storeId . "_" . $url] = $sRouteInsert;

                            /**
                             * Updataj stari route
                             */
                            if (isset($decodedUrl[$storeId]) && $decodedUrl[$storeId] != $url) {
                                $sRouteUpdate = new UpdateModel($existingSRoutes[$storeId . "_" . $decodedUrl[$storeId]]);
                                $sRouteUpdate->add("redirect_type_id", 1)
                                    ->add("redirect_to", $url);
                                $updateArray["s_route_entity"][$sRouteUpdate->getEntityId()] = $sRouteUpdate->getArray();
                            }
                        }
                    }

                    $urlJson = json_encode($mergedUrl, JSON_UNESCAPED_UNICODE);
                    $productUpdate->add("url", $urlJson, false);
                }

                foreach ($jsonAttributes as $jsonAttributeKey => $jsonAttributeValues) {
                    if ($jsonAttributeKey == "name" || $jsonAttributeKey == "url") {
                        continue;
                    }
                    $encodedJson = $this->mergeJson($jsonAttributes, $existingSimpleProducts[$code], $jsonAttributeKey);
                    if ($encodedJson) {
                        $productUpdate->add($jsonAttributeKey, $encodedJson, false);
                    }
                }

                if (!empty($productUpdate->getArray())) {
                    $updateArray["product_entity"][$productUpdate->getEntityId()] = $productUpdate->getArray();
                    $productIds[] = $productUpdate->getEntityId();
                }
            }

            $insertArray = $this->mergeInsertArrays($insertArray, $currentInsertArray);
            $insertArray2 = $this->mergeInsertArrays($insertArray2, $currentInsertArray2);

            $ret["rows"]++;
        }
        
        unset($data);
        unset($existingSimpleProducts);
        unset($existingProductProductGroupLinks);
        unset($existingSRoutes);
        unset($existingSProductAttributeConfigurations);
        unset($existingSProductAttributeConfigurationOptions);
        unset($existingSProductAttributeLinks);
        unset($productEntityAttributes);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $reselectArray["product_entity"] = $this->getExistingProducts("code", [], false, 3, "AND product_type_id IN (1,3,4,6) ");
        $reselectArray["s_product_attribute_configuration_options_entity"] = $this->getExistingSProductAttributeConfigurationOptions(null, ["id"], ["configuration_attribute_id", "MD5(configuration_value)"]);
        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $ret["product_ids"] = $this->resolveChangedProducts($productIds, $productRemoteCodes, $reselectArray["product_entity"]);
        if (!empty($ret["product_ids"])) {
            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->container->get("database_context");
            }
            $q = "SELECT DISTINCT(supplier_id) FROM product_entity WHERE id IN (" . implode(",", $ret["product_ids"]) . ") AND supplier_id IS NOT NULL;";
            $supplierIds = $this->databaseContext->getAll($q);
            if (!empty($supplierIds)) {
                $ret["supplier_ids"] = array_column($supplierIds, "supplier_id");
            }
        }

        unset($reselectArray);

        return $ret;
    }

    /**
     * @param $fileLocation
     * @return array
     * @throws \Exception
     */
    public function importConfigurableProducts($fileLocation)
    {
        $ret = [
            "errors" => null,
            "rows" => 0
        ];

        if (empty($this->excelManager)) {
            $this->excelManager = $this->container->get("excel_manager");
        }

        $data = $this->excelManager->importEntityArray($fileLocation);
        if (empty($data)) {
            throw new \Exception("File is empty");
        }
        if (!isset($data["configurable_product_import"]) || empty($data["configurable_product_import"])) {
            throw new \Exception("Product sheet is empty");
        }

        $existingSimpleProducts = $this->getExistingProducts("name", ["id"], true, 3, "AND product_type_id = 1");
        $existingConfigurableProducts = $this->getExistingProducts("name", ["id"], true, 3, "AND product_type_id = 2");
        $configurableProductsBySimpleProducts = $this->getConfigurableProductsBySimpleProducts();
        $existingSRoutes = $this->getExistingSRoutes();
        $existingProductProductGroupLinksByProduct = $this->getExistingProductProductGroupLinksByProduct();
        $existingSProductAttributesLinksByProduct = $this->getExistingSProductAttributesLinksByProduct();
        $existingSProductAttributesLinksForSimpleProducts = $this->getExistingSProductAttributesLinksForSimpleProducts();
        $existingProductConfigurableAttributesByProduct = $this->getExistingProductConfigurableAttributesByProduct();

        $insertArray = [
            // product_entity
        ];
        $insertArray2 = [
            // s_route_entity
            // s_product_attributes_link_entity
            // product_configuration_product_link_entity
            // product_product_group_link_entity
            // product_configurable_attribute_entity
        ];
        $updateArray = [
            // product_configuration_product_link_entity
        ];
        $deleteArray = [
            // s_product_attributes_link_entity
            // product_configuration_product_link_entity
            // product_product_group_link_entity
            // product_configurable_attribute_entity
        ];

        foreach ($existingConfigurableProducts as $existingConfigurableProduct) {
            if (isset($existingSProductAttributesLinksByProduct[$existingConfigurableProduct["id"]])) {
                foreach ($existingSProductAttributesLinksByProduct[$existingConfigurableProduct["id"]] as $configurationOptions) {
                    foreach ($configurationOptions as $attributeValues) {
                        foreach ($attributeValues as $attributeValueKey => $attributeLink) {
                            $deleteArray["s_product_attributes_link_entity"][$attributeValueKey] = [
                                "id" => $attributeLink["id"]
                            ];
                        }
                    }
                }
            }
            if (isset($existingProductProductGroupLinksByProduct[$existingConfigurableProduct["id"]])) {
                foreach ($existingProductProductGroupLinksByProduct[$existingConfigurableProduct["id"]] as $productProductGroupLink) {
                    $deleteArray["product_product_group_link_entity"][$productProductGroupLink["id"]] = [
                        "id" => $productProductGroupLink["id"]
                    ];
                }
            }
        }

        foreach ($configurableProductsBySimpleProducts as $configurableProductBySimpleProduct) {
            foreach ($configurableProductBySimpleProduct as $productConfigurationProductLink) {
                $deleteArray["product_configuration_product_link_entity"][$productConfigurationProductLink["id"]] = [
                    "id" => $productConfigurationProductLink["id"]
                ];
            }
        }

        foreach ($existingProductConfigurableAttributesByProduct as $productId => $existingProductConfigurableAttribute) {
            foreach ($existingProductConfigurableAttribute as $configurationId => $existingProductConfigurableAttributeId) {
                $productConfigurableAttributeKey = $productId . "_" . $configurationId;
                $deleteArray["product_configurable_attribute_entity"][$productConfigurableAttributeKey] = [
                    "id" => $existingProductConfigurableAttributeId
                ];
            }
        }

        $productNames = [];

        foreach ($data["configurable_product_import"] as $rowId => $d) {

            if (!isset($d["product:name:3"])) {
                continue;
            }
            if (!isset($d["configurable_product:name:3"])) {
                continue;
            }

            $simpleProductName = trim($d["product:name:3"]);
            if (empty($simpleProductName)) {
                $ret["errors"] .= sprintf("Row %d: Value at column 'product:name:3' is empty\n", $rowId);
                continue;
            }

            $configurableProductName = trim($d["configurable_product:name:3"]);
            if (empty($configurableProductName)) {
                $ret["errors"] .= sprintf("Row %d: Value at column 'configurable_product:name:3' is empty\n", $rowId);
                continue;
            }

            if (!isset($existingSimpleProducts[$simpleProductName]["id"])) {
                $ret["errors"] .= sprintf("Row %d: Product was not found\n", $rowId);
                continue;
            }

            $productId = $existingSimpleProducts[$simpleProductName]["id"];
            $ret["product_ids"][] = $productId;

            $configurationIds = [];
            if (isset($d["s_product_attribute_configuration"]) && !empty($d["s_product_attribute_configuration"])) {
                $configurationIds = explode(";", $d["s_product_attribute_configuration"]);
            }

            /**
             * Check if configurable product exists
             */
            if (!isset($existingConfigurableProducts[$configurableProductName])) {

                /**
                 * Copy product attribute links from simple product
                 */
                if (isset($existingSProductAttributesLinksByProduct[$productId])) {
                    foreach ($existingSProductAttributesLinksByProduct[$productId] as $configurationId => $configurationOptions) {
                        foreach ($configurationOptions as $optionId => $attributeValues) {
                            foreach ($attributeValues as $attributeLink) {
                                $linkKey = md5($configurableProductName . $configurationId . $optionId);
                                if (!isset($insertArray2["s_product_attributes_link_entity"][$linkKey])) {
                                    $sProductAttributesLinkInsert = new InsertModel($this->asSProductAttributesLink);
                                    $sProductAttributesLinkInsert->addLookup("product_id", $configurableProductName, "product_entity")
                                        ->add("s_product_attribute_configuration_id", $configurationId)
                                        ->add("attribute_value", $attributeLink["attribute_value"])
                                        ->add("configuration_option", $optionId)
                                        ->addFunction(function ($entity) {
                                            $entity["attribute_value_key"] = md5($entity["product_id"] .
                                                $entity["s_product_attribute_configuration_id"] .
                                                $entity["configuration_option"]);
                                            return $entity;
                                        });
                                    $insertArray2["s_product_attributes_link_entity"][$linkKey] = $sProductAttributesLinkInsert;
                                }
                            }
                        }
                    }
                }

                /**
                 * Copy product group links from simple product
                 */
                if (isset($existingProductProductGroupLinksByProduct[$productId])) {
                    foreach ($existingProductProductGroupLinksByProduct[$productId] as $productGroupId => $productProductGroupLink) {
                        $productProductGroupLinkKey = $productGroupId . "_" . $configurableProductName;
                        if (!isset($insertArray2["product_product_group_link_entity"][$productProductGroupLinkKey])) {
                            $productProductGroupLinkInsert = new InsertModel($this->asProductProductGroupLink);
                            $productProductGroupLinkInsert->addLookup("product_id", $configurableProductName, "product_entity")
                                ->add("product_group_id", $productGroupId);
                            $insertArray2["product_product_group_link_entity"][$productProductGroupLinkKey] = $productProductGroupLinkInsert;
                        }
                    }
                }

                /**
                 * Create product link
                 */
                $newConfigurableProductAttributes = [];
                if (isset($existingSProductAttributesLinksForSimpleProducts[$productId])) {
                    $newConfigurableProductAttributes = $existingSProductAttributesLinksForSimpleProducts[$productId];
                }

                foreach ($newConfigurableProductAttributes as $key => $configurableProductAttribute) {

                    $configurationId = $configurableProductAttribute["attribute_id"];
                    if (!empty($configurationIds) && !in_array($configurationId, $configurationIds)) {
                        unset($newConfigurableProductAttributes[$key]);
                        continue;
                    }

                    $productConfigurableAttributeInsertKey = $configurableProductName . "_" . $configurationId;
                    if (!isset($insertArray2["product_configurable_attribute_entity"][$productConfigurableAttributeInsertKey])) {
                        $productConfigurableAttributeInsert = new InsertModel($this->asProductConfigurableAttribute);
                        $productConfigurableAttributeInsert->addLookup("product_id", $configurableProductName, "product_entity")
                            ->add("s_product_attribute_configuration_id", $configurationId)
                            ->add("ord", 100);
                        $insertArray2["product_configurable_attribute_entity"][$productConfigurableAttributeInsertKey] = $productConfigurableAttributeInsert;
                    }
                }

                $productConfigurationProductLinkInsert = new InsertModel($this->asProductConfigurationProductLink);
                $productConfigurationProductLinkInsert->addLookup("product_id", $configurableProductName, "product_entity")
                    ->add("child_product_id", $productId)
                    ->add("configurable_product_attributes", json_encode($newConfigurableProductAttributes));
                $insertArray2["product_configuration_product_link_entity"][] = $productConfigurationProductLinkInsert;

                /**
                 * Check if configurable product has been queued for insert
                 */
                if (!isset($insertArray["product_entity"][$configurableProductName])) {

                    /**
                     * Insert s route
                     */
                    $nameArray = [];
                    $descriptionArray = [];
                    $metaKeywordsArray = [];
                    $showOnStoreArray = [];
                    $urlArray = [];

                    foreach ($this->getStores() as $storeId) {

                        $nameArray[$storeId] = $configurableProductName;
                        $descriptionArray[$storeId] = "";
                        $metaKeywordsArray[$storeId] = "";
                        $showOnStoreArray[$storeId] = 1;

                        $i = 1;
                        $url = $key = $this->routeManager->prepareUrl($configurableProductName);
                        while (isset($existingSRoutes[$storeId . "_" . $url]) || isset($insertArray2["s_route_entity"][$storeId . "_" . $url])) {
                            $url = $key . "-" . $i++;
                        }
                        $urlArray[$storeId] = $url;

                        $sRouteInsertEntity = new InsertModel($this->asSRoute);
                        $sRouteInsertEntity->add("request_url", $url)
                            ->add("destination_type", "product")
                            ->add("store_id", $storeId)
                            ->addLookup("destination_id", $configurableProductName, "product_entity");

                        $insertArray2["s_route_entity"][$storeId . "_" . $url] = $sRouteInsertEntity;
                    }

                    $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);
                    $descriptionJson = json_encode($descriptionArray, JSON_UNESCAPED_UNICODE);
                    $metaKeywordsJson = json_encode($metaKeywordsArray, JSON_UNESCAPED_UNICODE);
                    $showOnStoreJson = json_encode($showOnStoreArray, JSON_UNESCAPED_UNICODE);
                    $urlJson = json_encode($urlArray, JSON_UNESCAPED_UNICODE);

                    /**
                     * Insert product
                     */
                    $productInsert = new InsertModel($this->asProduct);
                    $productInsert->add("date_synced", "NOW()")
                        ->add("name", $nameJson)
                        ->add("meta_title", $nameJson)
                        ->add("meta_description", $nameJson)
                        ->add("description", $descriptionJson)
                        ->add("meta_keywords", $metaKeywordsJson)
                        ->add("show_on_store", $showOnStoreJson)
                        ->add("active", 1)
                        ->add("url", $urlJson)
                        ->add("qty", 0)
                        ->add("qty_step", 1)
                        ->add("tax_type_id", 3)
                        ->add("currency_id", $_ENV["DEFAULT_CURRENCY"])
                        ->add("product_type_id", CrmConstants::PRODUCT_TYPE_CONFIGURABLE)
                        ->add("ord", 100)
                        ->add("is_visible", 1)
                        ->add("template_type_id", 5)
                        ->add("auto_generate_url", 1)
                        ->add("keep_url", 1)
                        ->add("show_on_homepage", 0)
                        ->add("ready_for_webshop", 1)
                        ->add("content_changed", 1)
                        ->add("remote_source", "default_import_manual");

                    $insertArray["product_entity"][$configurableProductName] = $productInsert->getArray();
                    $productNames[] = $configurableProductName;
                }
            } else {

                $configurableProductId = $existingConfigurableProducts[$configurableProductName]["id"];

                /**
                 * Copy product attribute links from simple product, ignore existing, delete if unused
                 */
                if (isset($existingSProductAttributesLinksByProduct[$productId])) {
                    foreach ($existingSProductAttributesLinksByProduct[$productId] as $configurationId => $configurationOptions) {
                        foreach ($configurationOptions as $optionId => $attributeValues) {
                            foreach ($attributeValues as $attributeLink) {
                                $linkKey = md5($configurableProductName . $configurationId . $optionId);
                                if (!isset($insertArray2["s_product_attributes_link_entity"][$linkKey])) {
                                    $attributeValueKey = md5($configurableProductId . $configurationId . $optionId);
                                    if (!isset($existingSProductAttributesLinksByProduct[$configurableProductId][$configurationId][$optionId][$attributeValueKey])) {
                                        $sProductAttributesLinkInsert = new InsertModel($this->asSProductAttributesLink);
                                        $sProductAttributesLinkInsert->add("product_id", $configurableProductId)
                                            ->add("s_product_attribute_configuration_id", $configurationId)
                                            ->add("attribute_value", $attributeLink["attribute_value"])
                                            ->add("configuration_option", $optionId)
                                            ->add("attribute_value_key", $attributeValueKey);
                                        $insertArray2["s_product_attributes_link_entity"][$linkKey] = $sProductAttributesLinkInsert->getArray();
                                    } else {
                                        unset($deleteArray["s_product_attributes_link_entity"][$attributeValueKey]);
                                    }
                                }
                            }
                        }
                    }
                }

                $newConfigurableProductAttributes = [];
                if (isset($existingSProductAttributesLinksForSimpleProducts[$productId])) {
                    $newConfigurableProductAttributes = $existingSProductAttributesLinksForSimpleProducts[$productId];
                }

                foreach ($newConfigurableProductAttributes as $key => $configurableProductAttribute) {

                    $configurationId = $configurableProductAttribute["attribute_id"];
                    if (!empty($configurationIds) && !in_array($configurationId, $configurationIds)) {
                        unset($newConfigurableProductAttributes[$key]);
                        continue;
                    }

                    $productConfigurableAttributeInsertKey = $configurableProductName . "_" . $configurationId;
                    if (!isset($existingProductConfigurableAttributesByProduct[$configurableProductId][$configurationId]) &&
                        !isset($insertArray2["product_configurable_attribute_entity"][$productConfigurableAttributeInsertKey])) {
                        $productConfigurableAttributeInsert = new InsertModel($this->asProductConfigurableAttribute);
                        $productConfigurableAttributeInsert->add("product_id", $configurableProductId)
                            ->add("s_product_attribute_configuration_id", $configurationId)
                            ->add("ord", 100);
                        $insertArray2["product_configurable_attribute_entity"][$productConfigurableAttributeInsertKey] = $productConfigurableAttributeInsert->getArray();
                    } else {
                        $productConfigurableAttributeDeleteKey = $configurableProductId . "_" . $configurationId;
                        unset($deleteArray["product_configurable_attribute_entity"][$productConfigurableAttributeDeleteKey]);
                    }
                }

                /**
                 * Check if this product has configurable product linked
                 */
                if (!isset($configurableProductsBySimpleProducts[$productId][$configurableProductId])) {

                    /**
                     * Create product link
                     */
                    $productConfigurationProductLinkInsert = new InsertModel($this->asProductConfigurationProductLink);
                    $productConfigurationProductLinkInsert->add("product_id", $configurableProductId)
                        ->add("child_product_id", $productId)
                        ->add("configurable_product_attributes", json_encode($newConfigurableProductAttributes));
                    $insertArray2["product_configuration_product_link_entity"][] = $productConfigurationProductLinkInsert->getArray();

                } else {

                    $productConfigurationProductLink = $configurableProductsBySimpleProducts[$productId][$configurableProductId];
                    unset($deleteArray["product_configuration_product_link_entity"][$productConfigurationProductLink["id"]]);

                    $configurableProductAttributes = json_decode($productConfigurationProductLink["configurable_product_attributes"], true);
                    if ($configurableProductAttributes != $newConfigurableProductAttributes) {
                        $productConfigurationProductLinkUpdate = new UpdateModel($productConfigurationProductLink);
                        $productConfigurationProductLinkUpdate->add("configurable_product_attributes", json_encode($newConfigurableProductAttributes), false);
                        $updateArray["product_configuration_product_link_entity"][$productConfigurationProductLink["id"]] = $productConfigurationProductLinkUpdate->getArray();
                    }
                }

                /**
                 * Copy product group links from simple product
                 */
                if (isset($existingProductProductGroupLinksByProduct[$productId])) {
                    foreach ($existingProductProductGroupLinksByProduct[$productId] as $productGroupId => $productProductGroupLink) {
                        if (!isset($existingProductProductGroupLinksByProduct[$configurableProductId][$productGroupId])) {
                            $productProductGroupLinkKey = $productGroupId . "_" . $configurableProductName;
                            if (!isset($insertArray2["product_product_group_link_entity"][$productProductGroupLinkKey])) {
                                $productProductGroupLinkInsert = new InsertModel($this->asProductProductGroupLink);
                                $productProductGroupLinkInsert->add("product_group_id", $productGroupId)
                                    ->add("product_id", $configurableProductId);
                                $insertArray2["product_product_group_link_entity"][$productProductGroupLinkKey] = $productProductGroupLinkInsert->getArray();
                            }
                        } else {
                            $productProductGroupLinkId = $existingProductProductGroupLinksByProduct[$configurableProductId][$productGroupId]["id"];
                            unset($deleteArray["product_product_group_link_entity"][$productProductGroupLinkId]);
                        }
                    }
                }
            }

            $ret["rows"]++;
        }

        unset($data);
        unset($existingSimpleProducts);
        unset($existingConfigurableProducts);
        unset($configurableProductsBySimpleProducts);
        unset($existingSRoutes);
        unset($existingProductProductGroupLinksByProduct);
        unset($existingSProductAttributesLinksByProduct);
        unset($existingSProductAttributesLinksForSimpleProducts);
        unset($existingProductConfigurableAttributesByProduct);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $reselectArray["product_entity"] = $this->getExistingProducts("name", ["id"], true, 3, "AND product_type_id = 2");
        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $this->executeDeleteQuery($deleteArray);
        unset($deleteArray);

        if (!empty($productNames)) {
            $ret["product_ids"] = $this->resolveChangedProducts([], $productNames, $reselectArray["product_entity"]);

            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->container->get("database_context");
            }
            $q = "SELECT DISTINCT(supplier_id) FROM product_entity WHERE id IN (" . implode(",", $ret["product_ids"]) . ") AND supplier_id IS NOT NULL;";
            $supplierIds = $this->databaseContext->getAll($q);
            if (!empty($supplierIds)) {
                $ret["supplier_ids"] = array_column($supplierIds, "supplier_id");
            }
        }

        unset($reselectArray);

        return $ret;
    }

    /**
     * @param $fileLocation
     * @return null[]
     * @throws \Exception
     */
    public function importAccountsContactsUsers($fileLocation)
    {
        $ret = [
            "errors" => null,
            "rows" => 0
        ];

        if (empty($this->excelManager)) {
            $this->excelManager = $this->container->get("excel_manager");
        }

        $data = $this->excelManager->importEntityArray($fileLocation);
        if (empty($data)) {
            throw new \Exception("File is empty");
        }
        if (!isset($data["account_contact"]) || empty($data["account_contact"])) {
            throw new \Exception("Account/contact sheet is empty");
        }

        $existingAccounts = $this->getEntitiesArray(["*"], "account_entity", ["code"], "", "WHERE code IS NOT NULL AND code != ''");
        $existingAccountsByEmail = $this->getEntitiesArray(["*"], "account_entity", ["email"], "WHERE email IS NOT NULL AND email != ''");
        $existingAccountTypes = $this->getEntitiesArray(["id", "name"], "account_type_entity", ["id"], "", "WHERE a1.entity_state_id = 1");
        $existingAccountTypeLinks = $this->getEntitiesArray(["account_type_id", "a2.code AS account_code"], "account_type_link_entity", ["account_code", "account_type_id"], "JOIN account_entity a2 ON a1.account_id = a2.id");
        $existingContacts = $this->getEntitiesArray(["a1.*", "a2.code AS account_code"], "contact_entity", ["account_code"], "JOIN account_entity a2 ON a1.account_id = a2.id", "WHERE a2.code IS NOT NULL AND a2.code != ''");
        $existingContactsByEmail = $this->getEntitiesArray(["*"], "contact_entity", ["email"], "", "WHERE email IS NOT NULL AND email != ''");
        $existingAddresses = $this->getEntitiesArray(["a1.*", "a2.code AS account_code"], "address_entity", ["account_code"], "JOIN account_entity a2 ON a1.account_id = a2.id", "WHERE a2.code IS NOT NULL AND a2.code != ''");
        $existingCities = $this->getEntitiesArray(["id", "postal_code"], "city_entity", ["postal_code"], "", "WHERE a1.entity_state_id = 1");

        $entityAttributes = [];
        $entityAttributes["account"] = $this->getEntityAttributes("account");
        $entityAttributes["contact"] = $this->getEntityAttributes("contact");
        $entityAttributes["address"] = $this->getEntityAttributes("address");

        $insertArray = [
            // account_entity
        ];
        $insertArray2 = [
            // account_type_link_entity
            // contact_entity
        ];
        $insertArray3 = [
            // address_entity
        ];
        $updateArray = [
            // account_entity
            // contact_entity
            // address_entity
        ];

        $accountEmails = [];
        $contactEmails = [];

        foreach ($data["account_contact"] as $rowId => $d) {

            if (!isset($d["account:code"])) {
                $ret["errors"] .= sprintf("Row %d: Value at column '%s' is empty\n", $rowId, "account:code");
                continue;
            }
            if (!isset($d["related:account_types"])) {
                $ret["errors"] .= sprintf("Row %d: Value at column '%s' is empty\n", $rowId, "related:account_types");
                continue;
            }
            if (!isset($d["account:is_legal_entity"])) {
                $ret["errors"] .= sprintf("Row %d: Value at column '%s' is empty\n", $rowId, "account:is_legal_entity");
                continue;
            }
            if (!isset($d["account:is_active"])) {
                $ret["errors"] .= sprintf("Row %d: Value at column '%s' is empty\n", $rowId, "account:is_active");
                continue;
            }
            if (!isset($d["account:email"])) {
                $ret["errors"] .= sprintf("Row %d: Value at column '%s' is empty\n", $rowId, "account:email");
                continue;
            }

            $accountCode = trim($d["account:code"]);
            if (empty($accountCode)) {
                $ret["errors"] .= sprintf("Row %d: Value at column '%s' is empty\n", $rowId, "account:code");
                continue;
            }
            $accountTypes = trim($d["related:account_types"]);
            if (empty($accountTypes)) {
                $ret["errors"] .= sprintf("Row %d: Value at column '%s' is empty\n", $rowId, "related:account_types");
                continue;
            }
            $accountEmail = filter_var(trim($d["account:email"]), FILTER_SANITIZE_EMAIL);
            if (empty($accountEmail)) {
                $ret["errors"] .= sprintf("Row %d: Value at column '%s' is empty\n", $rowId, "account:email");
                continue;
            }
            if ($accountEmail != trim($d["account:email"])) {
                $ret["errors"] .= sprintf("Row %d: Illegal characters removed from %s value\n", $rowId, "account:email");
            }
            $contactEmail = filter_var(trim($d["contact:email"]), FILTER_SANITIZE_EMAIL);
            if ($contactEmail != trim($d["contact:email"])) {
                $ret["errors"] .= sprintf("Row %d: Illegal characters removed from %s value\n", $rowId, "contact:email");
            }

            unset($d["account:code"]);
            unset($d["related:account_types"]);
            unset($d["account:email"]);
            unset($d["contact:email"]);

            $entityActions = [];

            if (!isset($existingAccounts[$accountCode])) {

                if (isset($existingAccountsByEmail[$accountEmail]) || in_array($accountEmail, $accountEmails)) {
                    $ret["errors"] .= sprintf("Row %d: Skipped due to duplicate %s value\n", $rowId, "account:email");
                    continue;
                }

                $accountInsert = new InsertModel($this->asAccount);
                $accountInsert->add("code", $accountCode)
                    ->add("email", $accountEmail);
                $entityActions["account"]["insert"] = $accountInsert;
            } else {
                if ($existingAccounts[$accountCode]["entity_state_id"] != 1) {
                    continue;
                }
                $accountUpdate = new UpdateModel($existingAccounts[$accountCode]);
                $entityActions["account"]["update"] = $accountUpdate;
            }

            if (!isset($existingContacts[$accountCode])) {

                if (!empty($contactEmail)) {
                    if (isset($existingContactsByEmail[$contactEmail]) || in_array($contactEmail, $contactEmails)) {
                        $ret["errors"] .= sprintf("Row %d: Skipped due to duplicate %s value\n", $rowId, "contact:email");
                        continue;
                    }
                }

                $contactInsert = new InsertModel($this->asContact);
                $contactInsert->addLookup("account_id", $accountCode, "account_entity")
                    ->add("email", $contactEmail)
                    ->add("first_name", NULL)
                    ->add("last_name", NULL)
                    ->add("full_name", NULL)
                    ->add("core_user_id", NULL);
                $entityActions["contact"]["insert"] = $contactInsert;
            } else {
                if ($existingContacts[$accountCode]["entity_state_id"] != 1) {
                    continue;
                }
                $contactUpdate = new UpdateModel($existingContacts[$accountCode]);
                $entityActions["contact"]["update"] = $contactUpdate;
            }

            if (!isset($existingAddresses[$accountCode])) {
                $addressInsert = new InsertModel($this->asAddress);
                $addressInsert->addLookup("account_id", $accountCode, "account_entity")
                    ->addLookup("contact_id", $accountCode, "contact_entity")
                    ->add("billing", 1)
                    ->add("headquarters", 1)
                    ->add("is_active", 1)
                    ->add("city_id", NULL);
                $entityActions["address"]["insert"] = $addressInsert;
            } else {
                if ($existingAddresses[$accountCode]["entity_state_id"] != 1) {
                    continue;
                }
                $addressUpdate = new UpdateModel($existingAddresses[$accountCode]);
                $entityActions["address"]["update"] = $addressUpdate;
            }

            $accountTypeIds = explode(";", $accountTypes);
            if (!empty($accountTypeIds)) {
                foreach ($accountTypeIds as $accountTypeId) {
                    $accountTypeId = (int)$accountTypeId;
                    if (isset($existingAccountTypes[$accountTypeId])) {
                        $accountTypeLinkKey = $accountCode . "_" . $accountTypeId;
                        if (!isset($existingAccountTypeLinks[$accountTypeLinkKey])) {
                            $accountTypeLinkInsert = new InsertModel($this->asAccountTypeLink);
                            $accountTypeLinkInsert->add("account_type_id", $accountTypeId);
                            if (isset($existingAccounts[$accountCode])) {
                                $accountTypeLinkInsert->add("account_id", $existingAccounts[$accountCode]["id"]);
                            } else {
                                $accountTypeLinkInsert->addLookup("account_id", $accountCode, "account_entity");
                            }
                            $insertArray2["account_type_link_entity"][$accountTypeLinkKey] = $accountTypeLinkInsert;
                        }
                    } else {
                        $ret["errors"] .= sprintf("Row %d: Account type %d does not exist\n", $rowId, $accountTypeId);
                        continue;
                    }
                }
            }

            foreach ($d as $key => $value) {

                $value = $this->sanitizeExcelValue($value);

                $entityTypeColumnKeyword = explode(":", $key, 2);
                if (count($entityTypeColumnKeyword) != 2) {
                    continue;
                }

                $entityTypeCode = $entityTypeColumnKeyword[0];
                if ($entityTypeCode == "billing_address") {
                    $entityTypeCode = "address";
                }

                $column = $entityTypeColumnKeyword[1];

                if (isset($entityAttributes[$entityTypeCode][$column])) {

                    if ($entityAttributes[$entityTypeCode][$column] == "bool") {
                        $value = (bool)$value;
                    } else if ($entityAttributes[$entityTypeCode][$column] == "decimal") {
                        $value = (float)$value;
                    } else if ($entityAttributes[$entityTypeCode][$column] == "integer") {
                        $value = (int)$value;
                    }

                    if (isset($entityActions[$entityTypeCode]["insert"])) {
                        $entityActions[$entityTypeCode]["insert"]->add($column, $value);
                    } else if (isset($entityActions[$entityTypeCode]["update"])) {
                        ($entityAttributes[$entityTypeCode][$column] == "decimal") ?
                            $entityActions[$entityTypeCode]["update"]->addFloat($column, $value) :
                            $entityActions[$entityTypeCode]["update"]->add($column, $value);
                    }
                }
            }

            /**
             * Validation
             */
            foreach ($entityActions as $entityTypeCode => $dbActions) {
                if ($entityTypeCode == "account" || $entityTypeCode == "address") {
                    foreach ($dbActions as $action => $columns) {
                        $columnsArray = $columns->getArray();
                        if ($entityTypeCode == "account" && isset($columnsArray["is_legal_entity"])) {
                            /**
                             * Do account specific validation/actions here
                             */
                            if ($columnsArray["is_legal_entity"] == 1) {
                                if (!isset($columnsArray["name"]) || empty($columnsArray["name"])) {
                                    $ret["errors"] .= sprintf("Row %d: Account name should exist for legal entities\n", $rowId);
                                    continue;
                                }
                                if (!isset($columnsArray["oib"]) || empty($columnsArray["oib"])) {
                                    $ret["errors"] .= sprintf("Row %d: Account oib should exist for legal entities\n", $rowId);
                                    continue;
                                }
                            } else {
                                if (!isset($columnsArray["first_name"]) || empty($columnsArray["first_name"])) {
                                    $ret["errors"] .= sprintf("Row %d: Account first name should exist for non-legal entities\n", $rowId);
                                    continue;
                                }
                                if (!isset($columnsArray["last_name"]) || empty($columnsArray["last_name"])) {
                                    $ret["errors"] .= sprintf("Row %d: Account last name should exist for non-legal entities\n", $rowId);
                                    continue;
                                }
                                $entityActions["contact"][$action]->add("full_name", $columnsArray["first_name"] . " " . $columnsArray["last_name"]);
                            }
                        } else if ($entityTypeCode == "address" && isset($columnsArray["postal_code"]) && !empty($columnsArray["postal_code"])) {
                            /**
                             * Do address specific validation/actions here
                             */
                            if (isset($existingCities[$columnsArray["postal_code"]])) {
                                $entityActions["address"][$action]->add("city_id", $existingCities[$columnsArray["postal_code"]]["id"]);
                            }
                        }
                    }
                }
            }

            if (isset($entityActions["account"]["insert"]) && !empty($entityActions["account"]["insert"]->getArray())) {
                $insertArray["account_entity"][$accountCode] = $entityActions["account"]["insert"]->getArray();
            }
            if (isset($entityActions["account"]["update"]) && !empty($entityActions["account"]["update"]->getArray())) {
                $updateArray["account_entity"][$entityActions["account"]["update"]->getEntityId()] = $entityActions["account"]["update"]->getArray();
            }
            if (isset($entityActions["contact"]["insert"]) && !empty($entityActions["contact"]["insert"]->getArray())) {
                $insertArray2["contact_entity"][$accountCode] = $entityActions["contact"]["insert"];
            }
            if (isset($entityActions["contact"]["update"]) && !empty($entityActions["contact"]["update"]->getArray())) {
                $updateArray["contact_entity"][$entityActions["contact"]["update"]->getEntityId()] = $entityActions["contact"]["update"]->getArray();
            }
            if (isset($entityActions["address"]["insert"]) && !empty($entityActions["address"]["insert"]->getArray())) {
                $insertArray3["address_entity"][$accountCode] = $entityActions["address"]["insert"];
            }
            if (isset($entityActions["address"]["update"]) && !empty($entityActions["address"]["update"]->getArray())) {
                $updateArray["address_entity"][$entityActions["address"]["update"]->getEntityId()] = $entityActions["address"]["update"]->getArray();
            }

            $accountEmails[] = $accountEmail;
            if (!empty($contactEmail)) {
                $contactEmails[] = $contactEmail;
            }

            $ret["rows"]++;
        }

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $reselectArray["account_entity"] = $this->getEntitiesArray(["*"], "account_entity", ["code"], "", "WHERE code IS NOT NULL AND code != ''");
        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);

        $reselectArray["contact_entity"] = $this->getEntitiesArray(["a1.*", "a2.code AS account_code"], "contact_entity", ["account_code"], "JOIN account_entity a2 ON a1.account_id = a2.id", "WHERE a2.code IS NOT NULL AND a2.code != ''");
        $insertArray3 = $this->resolveImportArray($insertArray3, $reselectArray);
        $this->executeInsertQuery($insertArray3);
        unset($insertArray3);
        unset($reselectArray);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        return $ret;
    }

    /**
     * @param $entityTypeCode
     * @return array
     */
    protected function getEntityAttributes($entityTypeCode)
    {
        $entityTypeCode .= "_entity";

        $q = "SELECT
                attribute_code,
                backend_type
            FROM attribute
            WHERE backend_table = '{$entityTypeCode}';";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["attribute_code"]] = $d["backend_type"];
        }

        return $ret;
    }

    /**
     * @return array
     */
    protected function getExistingProductProductGroupLinks()
    {
        $q = "SELECT
                pg.id,
                p.code AS product_code,
                pg.product_group_id
            FROM product_product_group_link_entity pg
            JOIN product_entity p ON pg.product_id = p.id;";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["product_code"] . "_" . $d["product_group_id"]] = [
                "id" => $d["id"]
            ];
        }

        return $ret;
    }

    /**
     * @param string $sortKey
     * @param array $columns
     * @param string $additionalAnd
     * @return array
     */
    protected function getExistingSProductAttributeConfigurations($sortKey = "id", $columns = [], $additionalAnd = "")
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_unique($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT {$selectColumns}
            FROM s_product_attribute_configuration_entity
            WHERE {$sortKey} IS NOT NULL AND {$sortKey} != ''
            {$additionalAnd};";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    /**
     * @param string $sortKey
     * @param array $columns
     * @param array $uniqueCode
     * @param string $additionalAnd
     * @return array
     */
    protected function getExistingSProductAttributeConfigurationOptions($sortKey = "id", $columns = [], $uniqueCode = [], $additionalAnd = "")
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns = array_unique(array_filter(array_merge($columns, [$sortKey], $uniqueCode)));
            $selectColumns = implode(",", $columns);
        }

        $requiredAnd = " AND {$sortKey} IS NOT NULL AND {$sortKey} != '' ";
        if (!empty($uniqueCode)) {
            $requiredAnd = "";
            foreach ($uniqueCode as $uc) {
                $requiredAnd .= " AND {$uc} IS NOT NULL AND {$uc} != '' ";
            }
        }

        $q = "SELECT {$selectColumns}
            FROM s_product_attribute_configuration_options_entity
            WHERE entity_state_id = 1
            {$requiredAnd}
            {$additionalAnd};";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            if (!empty($uniqueCode)) {
                $ret[$d[$uniqueCode[0]] . "_" . $d[$uniqueCode[1]]] = $d;
            } else {
                $ret[$d[$sortKey]] = $d;
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    protected function getExistingSProductAttributesLinks()
    {
        $q = "SELECT
                spal.id,
                spal.s_product_attribute_configuration_id,
                spal.configuration_option,
                spal.attribute_value_key,
                spal.attribute_value
            FROM s_product_attributes_link_entity spal
            JOIN s_product_attribute_configuration_entity spac ON spal.s_product_attribute_configuration_id = spac.id
            JOIN product_entity p ON spal.product_id = p.id
            AND p.product_type_id IN (1,3,4,6);";
        
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
     * @return array
     */
    protected function getConfigurableProductsBySimpleProducts()
    {
        $q = "SELECT
                id,
                product_id,
                child_product_id,
                configurable_product_attributes
            FROM product_configuration_product_link_entity;";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["child_product_id"]][$d["product_id"]] = [
                "id" => $d["id"],
                "configurable_product_attributes" => $d["configurable_product_attributes"]
            ];
        }

        return $ret;
    }

    /**
     * @return array
     */
    protected function getExistingSRoutes()
    {
        $q = "SELECT
                id,
                request_url,
                store_id,
                destination_type,
                redirect_type_id,
                redirect_to
            FROM s_route_entity;";

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
    protected function getExistingProductProductGroupLinksByProduct()
    {
        $q = "SELECT
                id,
                product_id,
                product_group_id
            FROM product_product_group_link_entity;";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["product_id"]][$d["product_group_id"]] = [
                "id" => $d["id"]
            ];
        }

        return $ret;
    }

    /**
     * @return array
     */
    protected function getExistingSProductAttributesLinksByProduct()
    {
        $q = "SELECT
                spal.id,
                spal.product_id,
                spal.s_product_attribute_configuration_id,
                spal.configuration_option,
                spal.attribute_value_key,
                spal.attribute_value
            FROM s_product_attributes_link_entity spal
            JOIN s_product_attribute_configuration_entity spac ON spal.s_product_attribute_configuration_id = spac.id;";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["product_id"]][$d["s_product_attribute_configuration_id"]][$d["configuration_option"]][$d["attribute_value_key"]] = [
                "id" => $d["id"],
                "attribute_value" => $d["attribute_value"]
            ];
        }

        return $ret;
    }

    /**
     * @param $sortKey
     * @param array $columns
     * @param false $decodeName
     * @param int $jsonKey
     * @param string $additionalAnd
     * @return array
     */
    protected function getExistingProducts($sortKey, $columns = [], $decodeName = false, $jsonKey = 3, $additionalAnd = "")
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_unique($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT {$selectColumns}
            FROM product_entity
            WHERE {$sortKey} IS NOT NULL AND {$sortKey} != ''
            {$additionalAnd};";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            if ($decodeName && isset($d["name"])) {
                $nameArray = json_decode($d["name"], true);
                if (isset($nameArray[$jsonKey])) {
                    $d["name"] = $nameArray[$jsonKey];
                }
            }
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    protected function getExistingSProductAttributesLinksForSimpleProducts()
    {
        $q = "SELECT
                spac.id AS attribute_id,
                spac.name AS attribute_name,
                spal.id,
                spal.configuration_option AS option_id,
                spal.attribute_value_key,
                spal.attribute_value AS value,
                spal.prefix,
                spal.sufix,
                spal.product_id
            FROM s_product_attributes_link_entity spal
            JOIN s_product_attribute_configuration_entity spac ON spal.s_product_attribute_configuration_id = spac.id
            AND spac.entity_state_id = 1
            AND spal.entity_state_id = 1;";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["product_id"]][] = [
                "attribute_id" => (int)$d["attribute_id"],
                "attribute_name" => $d["attribute_name"],
                "values" => [
                    [
                        "id" => $d["id"],
                        "option_id" => $d["option_id"],
                        "attribute_value_key" => $d["attribute_value_key"],
                        "value" => $d["value"],
                        "prefix" => $d["prefix"],
                        "sufix" => $d["sufix"]
                    ]
                ]
            ];
        }

        return $ret;
    }

    /**
     * @return array
     */
    protected function getExistingProductConfigurableAttributesByProduct()
    {
        $q = "SELECT
                id,
                product_id,
                s_product_attribute_configuration_id
            FROM product_configurable_attribute_entity;";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["product_id"]][$d["s_product_attribute_configuration_id"]] = $d["id"];
        }

        return $ret;
    }
}
