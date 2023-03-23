<?php

namespace CrmBusinessBundle\Abstracts;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ErrorLogManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\MailManager;
use CrmBusinessBundle\Entity\ImportLogEntity;
use AppBundle\Models\InsertModel;
use Monolog\Logger;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Managers\TemplateManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use AppBundle\Models\LookupModel;
use Symfony\Component\Console\Output\OutputInterface;

class AbstractImportManager extends AbstractBaseManager
{
    /** @var OutputInterface $consoleOutput */
    protected $consoleOutput;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var TemplateManager $templateManager */
    protected $templateManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var AttributeContext $attributeContext */
    protected $attributeContext;
    /** @var MailManager $mailManager */
    protected $mailManager;

    /** @var array $stores */
    protected $stores;
    /** @var array $triggerChangesArray */
    protected $triggerChangesArray;
    /** @var array $triggerContentChangesArray */
    protected $triggerContentChangesArray;
    /** @var string $webPath */
    protected $webPath;
    /** @var string $productImagesDir */
    protected $productImagesDir;
    /** @var string $productDocumentsDir */
    protected $productDocumentsDir;
    /** @var string $importDir */
    protected $importDir;
    /** @var string $remoteSource */
    protected $remoteSource;
    /** @var bool $debug */
    protected $debug;
    /** @var bool $fast */
    protected $fast;
    /** @deprecated */
    protected $defaultStores;

    public function initialize()
    {
        parent::initialize();

        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->helperManager = $this->getContainer()->get("helper_manager");
        $this->routeManager = $this->getContainer()->get("route_manager");
        $this->templateManager = $this->getContainer()->get("template_manager");
        $this->databaseContext = $this->getContainer()->get("database_context");
        $this->attributeContext = $this->getContainer()->get("attribute_context");
        $this->restManager = $this->getContainer()->get("rest_manager");

        $this->stores = [3];

        $stores = $this->routeManager->getStores();
        if (!empty($stores)) {
            $this->stores = [];
            /** @var SStoreEntity $store */
            foreach ($stores as $store) {
                $this->stores[] = $store->getId();
            }
        }

        $this->defaultStores = $this->stores;

        $this->triggerChangesArray = [
            "qty",
            "supplier_id",
            "price_purchase",
            "price_base",
            "price_retail",
            "brand_id",
            "active",
            "tax_type_id",
            "currency_id",
            "discount_price_base",
            "discount_price_retail",
            "discount_percentage",
            "discount_percentage_base",
            "date_discount_base_from",
            "date_discount_base_to",
            "discount_type",
            "discount_type_base",
            "discount_diff",
            "discount_diff_base",
            "ready_for_webshop"
        ];
        $this->triggerContentChangesArray = [
            "name",
            "description",
            "short_description"
        ];

        $this->webPath = $_ENV["WEB_PATH"];
        $this->productImagesDir = "Documents/Products/";
        $this->productDocumentsDir = "Documents/product_document/";
        $this->importDir = "Documents/import/";
        $this->remoteSource = "import";
        $this->debug = false;
        $this->fast = false;
    }

    /**
     * @return string
     */
    protected function getWebPath()
    {
        return $this->webPath;
    }

    /**
     * @return string
     */
    protected function getProductImagesDir()
    {
        return $this->webPath . $this->productImagesDir;
    }

    /**
     * @return string
     */
    protected function getProductDocumentsDir()
    {
        return $this->webPath . $this->productDocumentsDir;
    }

    /**
     * @return string
     */
    protected function getImportDir()
    {
        return $this->webPath . $this->importDir;
    }

    /**
     * @return bool
     */
    protected function getDebug()
    {
        return $this->debug;
    }

    /**
     * @param $debug
     * @return $this
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * @return bool
     */
    protected function getFast()
    {
        return $this->fast;
    }

    /**
     * @param $fast
     * @return $this
     */
    public function setFast($fast)
    {
        $this->fast = $fast;

        return $this;
    }

    /**
     * @return string
     */
    public function getRemoteSource()
    {
        return $this->remoteSource;
    }

    /**
     * @param $remoteSource
     */
    protected function setRemoteSource($remoteSource)
    {
        $this->remoteSource = $remoteSource;
    }

    /**
     * @param OutputInterface $output
     * @return $this
     */
    public function setConsoleOutput(OutputInterface $output)
    {
        $this->consoleOutput = $output;

        return $this;
    }

    /**
     * @return OutputInterface
     */
    protected function getConsoleOutput()
    {
        return $this->consoleOutput;
    }

    /**
     * @param array $stores
     * @return $this
     */
    public function setStores(array $stores)
    {
        $this->stores = $stores;

        return $this;
    }

    /**
     * @return array
     */
    protected function getStores()
    {
        return $this->stores;
    }

    /**
     * @param string $query
     * @param string $filename
     */
    protected function logString(string $query, $filename = "")
    {
        if (empty($filename)) {
            $filename = $this->getRemoteSource() . ".sql";
        }
        $this->saveFile($this->container->get("kernel")->getLogDir() . "/" . $filename, $query);
    }

    /**
     * @param array $array
     * @param string $filename
     */
    protected function logArray(array $array, $filename = "")
    {
        if (empty($filename)) {
            $filename = $this->getRemoteSource() . ".json";
        }
        $this->saveFile($this->container->get("kernel")->getLogDir() . "/" . $filename, json_encode($array));
    }

    /**
     * @param $envKey
     * @param true $flip
     * @return int[]|mixed|string[]
     */
    protected function getAttributesFromEnv($envKey, $flip = true)
    {
        $attributes = json_decode($_ENV[$envKey], true);
        if ($flip) {
            $attributes = array_flip($attributes);
        }
        return $attributes;
    }

    /**
     * @param $value
     * @return float|int|string
     */
    protected function wrapQueryValue($value)
    {
        if (is_array($value) || is_object($value)) {
            throw new \Exception(json_encode((array)$value)); // TODO: malo ovo bolje hendlat
        }

        if ($value === 0 || $value === '0' || $value === 0.0 || $value === '0.0' || $value === FALSE) {
            return 0;
        } else if (empty($value)) {
            return 'NULL';
        } else if (!is_integer($value) && !is_float($value) && $value != 'NOW()') {
            return '\'' . addslashes($value) . '\'';
        }
        return $value;
    }

    /**
     * @param $insertArray
     * @return string|null
     */
    public function getInsertQuery($insertArray)
    {
        $query = NULL;

        foreach ($insertArray as $table => $entities) {
            if (!empty($entities)) {

                if ($this->getDebug()) {
                    if (!$query) {
                        echo "Generating insert query...\n";
                    }
                    echo "\t" . $table . "\n";
                }

                $q = NULL;
                foreach ($entities as $entity) {
                    if (!empty($entity)) {
                        ksort($entity);
                        if (!$q) {
                            $q .= "INSERT INTO " . $table . " (" . implode(", ", array_keys($entity)) . ") VALUES\n";
                        }
                        $q .= "(" . implode(", ", array_map([$this, "wrapQueryValue"], array_values($entity))) . "),\n";
                    }
                }
                if ($q) {
                    $query .= substr($q, 0, -2) . ";\n";
                }
            }
        }

        return $query;
    }

    /**
     * @param $updateArray
     * @return string|null
     */
    public function getUpdateQuery($updateArray)
    {
        $query = NULL;

        foreach ($updateArray as $table => $entities) {
            if (!empty($entities)) {

                if ($this->getDebug()) {
                    if (!$query) {
                        echo "Generating update query...\n";
                    }
                    echo "\t" . $table . "\n";
                }

                foreach ($entities as $index => $entity) {
                    if (!empty($entity)) {
                        ksort($entity);
                        $q = "UPDATE " . $table . " SET ";
                        foreach ($entity as $field => $value) {
                            $q .= $field . " = " . $this->wrapQueryValue($value) . ", ";
                        }
                        $query .= substr($q, 0, -2) . " WHERE id = '" . $index . "';\n";
                    }
                }
            }
        }

        return $query;
    }

    /**
     * @param $deleteArray
     * @return string|null
     */
    public function getDeleteQuery($deleteArray)
    {
        $query = NULL;

        foreach ($deleteArray as $table => $entities) {
            if (!empty($entities)) {

                if ($this->getDebug()) {
                    if (!$query) {
                        echo "Generating delete query...\n";
                    }
                    echo "\t" . $table . "\n";
                }

                foreach ($entities as $index => $entity) {
                    if (!empty($entity)) {
                        ksort($entity);
                        $conditions = NULL;
                        foreach ($entity as $column => $value) {
                            if (!empty($conditions)) {
                                $conditions .= " AND ";
                            }
                            $conditions .= $column . " = " . $this->wrapQueryValue($value);
                        }
                        if (!empty($conditions)) {
                            $query .= "DELETE FROM " . $table . " WHERE " . $conditions . ";\n";
                        }
                    }
                }
            }
        }

        return $query;
    }

    /**
     * @param $importArray
     * @param array $reselectArray
     * @return array
     */
    public function resolveImportArray($importArray, $reselectArray = [])
    {
        foreach ($importArray as $table => $entities) {
            foreach ($entities as $index => $entity) {
                if (!is_array($entity)) {
                    $entityArray = $entity->getArray();
                    /** @var LookupModel $lookup */
                    foreach ($entity->getLookups() as $lookup) {
                        $entityArray[$lookup->getColumn()] =
                            $reselectArray[$lookup->getLookupTable()]
                            [$lookup->getSortValue()]
                            ["id"];
                    }
                    foreach ($entity->getFunctions() as $function) {
                        $entityArray = $function($entityArray, $reselectArray);
                    }
                    $importArray[$table][$index] = $entityArray;
                }
            }
        }

        return $importArray;
    }

    /**
     * @param $changedIds
     * @param $changedRemoteIds
     * @param $existingProducts
     * @return array|mixed
     */
    protected function resolveChangedProducts($changedIds, $changedRemoteIds, $existingProducts)
    {
        foreach ($changedRemoteIds as $changedRemoteId) {
            if (isset($existingProducts[$changedRemoteId])) {
                $changedIds[] = $existingProducts[$changedRemoteId]["id"];
            }
        }

        return array_values(array_unique($changedIds));
    }

    /**
     * @param $insertArray
     */
    public function executeInsertQuery($insertArray)
    {
        foreach ($insertArray as $key => $values){

            $insertArrayTmp = array_chunk($values,10000,true);
            foreach ($insertArrayTmp as $insertArrayPart){

                $insertQuery = $this->getInsertQuery(Array($key => $insertArrayPart));
                if (!empty($insertQuery)) {
                    if ($this->getDebug()) {
                        $this->logString($insertQuery);
                    }
                    $this->databaseContext->executeNonQuery($insertQuery);
                }
            }
        }
    }

    /**
     * @param $updateArray
     */
    public function executeUpdateQuery($updateArray)
    {
        foreach ($updateArray as $key => $values){
            $updateArrayTmp = array_chunk($values,10000,true);
            foreach ($updateArrayTmp as $updateArrayPart){

                $updateQuery = $this->getUpdateQuery(Array($key => $updateArrayPart));
                if (!empty($updateQuery)) {
                    if ($this->getDebug()) {
                        $this->logString($updateQuery);
                    }
                    $this->databaseContext->executeNonQuery($updateQuery);
                }
            }
        }
    }

    /**
     * @param $deleteArray
     */
    public function executeDeleteQuery($deleteArray)
    {
        foreach ($deleteArray as $key => $values){
            $deleteArrayTmp = array_chunk($values,10000,true);
            foreach ($deleteArrayTmp as $deleteArrayPart){
                $deleteQuery = $this->getDeleteQuery(Array($key => $deleteArrayPart));
                if (!empty($deleteQuery)) {
                    if ($this->getDebug()) {
                        $this->logString($deleteQuery);
                    }
                    $this->databaseContext->executeNonQuery($deleteQuery);
                }
            }
        }
    }

    /**
     * @param $fileName
     * @param $dataToSave
     * @return false
     */
    protected function saveFile($fileName, $dataToSave)
    {
        if (!$fp = fopen($fileName, 'a')) {
            return false;
        }

        $startTime = microtime(TRUE);
        do {
            $canWrite = flock($fp, LOCK_EX);
            // If lock not obtained sleep for 0 - 100 milliseconds, to avoid collision and CPU load
            if (!$canWrite) {
                usleep(round(rand(0, 100) * 1000));
            }
        } while ((!$canWrite) && ((microtime(TRUE) - $startTime) < 5));

        //file was locked so now we can store information
        if ($canWrite) {
            fwrite($fp, $dataToSave);
            flock($fp, LOCK_UN);
        }

        return fclose($fp);
    }

    /**
     * @param $data
     * @param bool $sendEmail
     * @return ImportLogEntity
     */
    public function insertImportLog($data, $sendEmail = true)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        /** @var ImportLogEntity $entity */
        $entity = $this->entityManager->getNewEntityByAttributSetName("import_log");

        $entity->setDateImported(new \DateTime());

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);
            $getter = EntityHelper::makeGetter($key);

            if (EntityHelper::checkIfMethodExists($entity, $getter)) {
                if ($entity->$getter() != $value) {
                    $entity->$setter($value);
                }
            }
        }

        $this->entityManager->saveEntityWithoutLog($entity);

        if(isset($data["exception"]) && !empty($data["exception"])){
            if(empty($this->errorLogManager)){
                $this->errorLogManager = $this->container->get("error_log_manager");
            }
            $this->errorLogManager->logExceptionEvent($data["name"],$data["exception"],true);
        }
        elseif (!$entity->getCompleted() && $sendEmail) {

            if (empty($this->mailManager)) {
                $this->mailManager = $this->container->get("mail_manager");
            }

            $this->mailManager->sendEmail(array("email" => $_ENV["SUPPORT_EMAIL"], "name" => $_ENV["SUPPORT_EMAIL"]), null, null, null, "Import error log", "", "general_error_log", array("importLog" => $entity));
        }

        return $entity;
    }

    /**
     * @param $function
     * @param array $args
     * @return false|mixed
     * @throws \Exception
     */
    public function executeImport($function, $args = [])
    {
        try {
            $result = call_user_func_array(array($this, $function), array($args));
        } catch (\Exception $e) {
            /** @var ErrorLogManager $errorLogManager */
            $errorLogManager = $this->getContainer()->get("error_log_manager");
            $errorLogManager->logExceptionEvent(sprintf("%s error log", $function), $e, true);
            throw $e;
        }

        $data = [
            "completed" => true,
            "error_log" => "",
            "name" => $function
        ];

        $this->insertImportLog($data);

        return $result;
    }

    /**
     * @deprecated
     * @param AttributeSet $attributeSet
     * @return array
     */
    public function getEntityDefaults(AttributeSet $attributeSet)
    {
        return (new InsertModel($attributeSet))->getArray();
    }

    /**
     * @deprecated
     * @param $string
     */
    public function logQueryString($string)
    {
        if ($this->getDebug()) {
            $this->logString($string);
        }
    }

    /**
     * @deprecated
     * @param $array
     */
    public function logQueryArray($array)
    {
        if ($this->getDebug()) {
            $this->logArray($array);
        }
    }

    /**
     * @deprecated
     * @param $array
     * @param $conditions
     * @return array
     */
    public function getDeleteConditions($array, $conditions)
    {
        $ret = array();

        foreach ($array as $a) {
            $temp = array();
            foreach ($conditions as $key) {
                $temp[$key] = $a[$key];
            }
            if (!empty($temp)) {
                $ret[$a["id"]] = $temp;
            }
        }

        return $ret;
    }

    /**
     * @deprecated
     * @param $importArray
     * @param $reselectArray
     * @return array
     */
    public function filterImportArray($importArray, $reselectArray, $params = array())
    {
        $ret = array();

        if (!empty($importArray)) {
            foreach ($importArray as $tableName => $tableData) {
                $func = $tableName . "_filter";
                if (method_exists($this, $func)) {
                    $ret[$tableName] = $this->filterTableData($tableData, $reselectArray, $func, $params);
                } else {
                    $ret[$tableName] = $tableData;
                }
            }
        }

        return $ret;
    }

    /**
     * @deprecated
     * @param $tableData
     * @param $reselectArray
     * @param $func
     * @param array $params
     * @return array
     */
    public function filterTableData($tableData, $reselectArray, $func, $params = array())
    {
        $ret = array();

        foreach ($tableData as $key => $entityData) {
            if (!empty($entityData)) {
                $d = call_user_func(array($this, $func), $entityData, $reselectArray, $params);
                if (!empty($d)) {
                    ksort($d);
                    $ret[$key] = $d;
                }
            }
        }

        return $ret;
    }

    /**
     * @param array $arrayOfKeys
     * @param $baseTable
     * @param array $compositeKey
     * @param string $additionalJoin
     * @param string $additionalWhere
     * @param string $additionalSort
     * @param string $additionalGroupBy
     * @return array
     */
    public function getEntitiesArray($arrayOfKeys = [], $baseTable, $compositeKey = [], $additionalJoin = "", $additionalWhere = "", $additionalSort = "", $additionalGroupBy = "")
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (!empty($arrayOfKeys)) {
            if (!in_array("a1.id", $arrayOfKeys)) {
                $arrayOfKeys[] = "a1.id";
            }
            $arrayOfKeys = implode(",", $arrayOfKeys);
        } else {
            $arrayOfKeys = "a1.*";
        }

        if (empty($compositeKey)) {
            $compositeKey[] = "a1.id";
        }

        $q = "SELECT {$arrayOfKeys} FROM {$baseTable} AS a1 {$additionalJoin} {$additionalWhere} {$additionalSort} {$additionalGroupBy};";

        $ret = [];

        $data = $this->databaseContext->getAll($q);
        if (!empty($data)) {
            foreach ($data as $d) {
                $key = [];
                foreach ($compositeKey as $c) {
                    $key[] = $d[$c];
                }
                $key = implode("_", $key);
                if (strlen($key) == 0 || $key == "_") {
                    continue;
                }
                $ret[$key] = $d;
            }
        }

        return $ret;
    }

    /**
     * @param $existingEntities
     * @param $insertedEmails
     * @param $email
     * @return bool
     */
    protected function getExistingEntityByEmail($existingEntities, $insertedEmails, $email)
    {
        return in_array($email, array_column($existingEntities, "email")) ||
            in_array($email, $insertedEmails);
    }
}
