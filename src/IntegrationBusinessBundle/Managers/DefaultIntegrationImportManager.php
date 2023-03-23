<?php

namespace IntegrationBusinessBundle\Managers;

use CrmBusinessBundle\Abstracts\AbstractImportManager;
use AppBundle\Models\InsertModel;
use IntegrationBusinessBundle\Models\ImportError;
use Symfony\Component\Console\Helper\ProgressBar;

class DefaultIntegrationImportManager extends AbstractImportManager
{
    private $verbosity;
    private $progress;
    private $errors;
    private $progressBar;
    protected $consoleOutput;

    public function initialize()
    {
        parent::initialize();

        $this->verbosity = 0;
        $this->progress = 0;
        $this->errors = [];
    }

    /**
     * @return mixed
     */
    public function getVerbosity()
    {
        return $this->verbosity;
    }

    /**
     * @param $verbosity
     * @return DefaultIntegrationImportManager
     */
    public function setVerbosity($verbosity)
    {
        $this->verbosity = $verbosity;

        return $this;
    }

    /**
     * @return mixed
     */
    public function isVerbose()
    {
        if ($this->getConsoleOutput()) {
            return $this->getConsoleOutput()->isVerbose();
        }

        return $this->getVerbosity();
    }

    /**
     * @param string $string
     */
    public function echo(string $string)
    {
        if ($this->isVerbose()) {
            printf($string);
        }
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param $function
     * @param $code
     * @param $message
     * @param $data
     * @return void
     */
    public function addError($function, $code, $message, $data = [])
    {
        $this->errors[] = new ImportError($function, $code, $message, $data);
    }

    /**
     * @return mixed
     */
    public function getConsoleOutput()
    {
        return $this->consoleOutput;
    }

    /**
     * @param mixed $consoleOutput
     * @return DefaultIntegrationImportManager
     */
    public function setConsoleOutput($consoleOutput)
    {
        $this->consoleOutput = $consoleOutput;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getProgressBar()
    {
        return $this->progressBar;
    }

    /**
     * @param mixed $progressBar
     * @return DefaultIntegrationImportManager
     */
    public function setProgressBar($progressBar)
    {
        $this->progressBar = $progressBar;

        return $this;
    }

    /**
     * @param $count
     * @return void
     */
    public function startProgressBar($count)
    {
        if ($this->isVerbose() && $this->getConsoleOutput()) {
            $this->setProgressBar(new ProgressBar($this->getConsoleOutput(), $count));
        }

        $this->progress = 0;
    }

    /**
     * @return void
     */
    public function advanceProgressBar()
    {
        if ($this->isVerbose() && $this->getProgressBar()) {
            $this->getProgressBar()->advance();
        }

        $this->progress++;
    }

    /**
     * @return void
     */
    public function finishProgressBar()
    {
        if ($this->isVerbose() && $this->getProgressBar()) {
            $this->getProgressBar()->finish();
        }

        $this->progress = 0;
    }

    /**
     * @return mixed
     */
    public function getProgress()
    {
        if ($this->isVerbose() && $this->getProgressBar()) {
            return $this->getProgressBar()->getProgress();
        }

        return $this->progress;
    }

    /**
     * TODO: rijesiti se ovoga svega ispod
     */

    /**
     * @param string $sortKey
     * @param array $columns
     * @return array
     */
    protected function getExistingTaxTypes($sortKey = "name", $columns = [])
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_unique($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT {$selectColumns}
            FROM tax_type_entity
            WHERE {$sortKey} IS NOT NULL AND {$sortKey} != '';";

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
     * @return array
     */
    protected function getExistingCurrencies($sortKey = "code", $columns = [])
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_unique($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT {$selectColumns}
            FROM currency_entity
            WHERE {$sortKey} IS NOT NULL AND {$sortKey} != '';";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[mb_strtolower($d[$sortKey])] = $d;
        }

        return $ret;
    }

    /**
     * @param string $sortKey
     * @param array $columns
     * @param string $additionalAnd
     * @return array
     */
    protected function getExistingProducts($sortKey = "remote_id", $columns = [], $additionalAnd = "")
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
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    /**
     * @param string $additionalAnd
     * @return array
     */
    protected function getExistingSRoutes($additionalAnd = "")
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
     * @param string $sortKey
     * @param array $columns
     * @return array
     */
    protected function getExistingSProductAttributesLinks($sortKey = "attribute_value_key", $columns = ["id"])
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_unique($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT {$selectColumns}
			FROM s_product_attributes_link_entity
            WHERE {$sortKey} IS NOT NULL AND {$sortKey} != '';";

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
     * @param string $additionalAnd
     * @return array
     */
    protected function getExistingAccounts($sortKey = "code", $columns = [], $additionalAnd = "")
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_unique($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT {$selectColumns}
			FROM account_entity
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
     * @param string $additionalAnd
     * @return array
     */
    protected function getExistingContacts($sortKey = "email", $columns = [], $additionalAnd = "")
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_unique($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT {$selectColumns}
			FROM contact_entity
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
     * @param $sortKey
     * @param array $columns
     * @param string $additionalAnd
     * @return array
     */
    protected function getExistingCities($sortKey, $columns = [], $additionalAnd = "")
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_unique($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT {$selectColumns}
            FROM city_entity
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
     * @param $sortKey
     * @param array $columns
     * @param false $decodeName
     * @param int $jsonKey
     * @return array
     */
    protected function getExistingCountries($sortKey, $columns = [], $decodeName = false, $jsonKey = 3)
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_unique($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT {$selectColumns}
            FROM country_entity
            WHERE {$sortKey} IS NOT NULL AND {$sortKey} != '';";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            if ($decodeName && isset($d["name"])) {
                $nameArray = json_decode($d["name"], true);
                if (isset($nameArray[$jsonKey])) {
                    $d["name"] = $nameArray[$jsonKey];
                }
            }
            $ret[mb_strtolower($d[$sortKey])] = $d;
        }

        return $ret;
    }

    /**
     * @param $sortKey
     * @param array $columns
     * @param array $uniqueCode
     * @param string $additionalAnd
     * @return array
     */
    protected function getExistingAddresses($sortKey, $columns = [], $uniqueCode = [], $additionalAnd = "")
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
            FROM address_entity
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
     * @param $sortKey
     * @param array $columns
     * @return array
     */
    protected function getExistingWarehouses($sortKey, $columns = [])
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_unique($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT {$selectColumns}
            FROM warehouse_entity
            WHERE {$sortKey} IS NOT NULL AND {$sortKey} != '';";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    /**
     * @param $sortKey
     * @param array $columns
     * @param false $decodeName
     * @param int $jsonKey
     * @return array
     */
    protected function getExistingProductGroups($sortKey, $columns = [], $decodeName = false, $jsonKey = 3)
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_unique($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT {$selectColumns}
            FROM product_group_entity
            WHERE {$sortKey} IS NOT NULL AND {$sortKey} != '';";

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
    protected function getExistingProductProductGroupLinks()
    {
        $q = "SELECT
                ppg.id,
                pg.product_group_code,
                p.remote_id,
                p.id AS product_id
            FROM product_product_group_link_entity ppg
            JOIN product_entity p ON ppg.product_id = p.id
            JOIN product_group_entity pg ON ppg.product_group_id = pg.id
            WHERE p.remote_id IS NOT NULL
            AND p.remote_id != ''
            AND p.remote_source = '{$this->getRemoteSource()}'
            AND pg.product_group_code IS NOT NULL
            AND pg.product_group_code != ''
            AND pg.remote_source = '{$this->getRemoteSource()}';";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["remote_id"] . "_" . $d["product_group_code"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    protected function getExistingProductWarehouseLinks()
    {
//        $q = "SELECT
//                pwl.id,
//                p.remote_id AS product_remote_id,
//                w.remote_id AS warehouse_remote_id,
//                pwl.qty
//            FROM product_warehouse_link_entity pwl
//            JOIN product_entity p ON pwl.product_id = p.id
//            JOIN warehouse_entity w ON pwl.warehouse_id = w.id
//            WHERE p.remote_id IS NOT NULL
//            AND p.remote_id != ''
//            AND p.remote_source = '{$this->getRemoteSource()}'
//            AND w.remote_id IS NOT NULL
//            AND w.remote_id != '';";
//
//        $data = $this->databaseContext->getAll($q);
//
//        $ret = [];
//        foreach ($data as $d) {
//            $ret[$d["product_remote_id"] . "_" . $d["warehouse_remote_id"]] = $d;
//        }
//
//        return $ret;

        $q = "SELECT
                pwl.id,
                pwl.product_id,
                pwl.warehouse_id,
                pwl.qty
            FROM product_warehouse_link_entity pwl
            JOIN product_entity p ON pwl.product_id = p.id
            WHERE p.remote_source = '{$this->getRemoteSource()}';";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["product_id"] . "_" . $d["warehouse_id"]] = $d;
        }

        return $ret;
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
    public function getSRouteInsertEntity($url, $type, $storeId, $destinationSortKey)
    {
        if(empty($this->asSRoute)){
            $this->asSRoute = $this->entityManager->getAttributeSetByCode("s_route");
        }

        $sRoute = new InsertModel($this->asSRoute);

        $sRoute->add("request_url", $url)
            ->add("destination_type", $type)
            ->add("store_id", $storeId)
            ->addLookup("destination_id", $destinationSortKey, $type . "_entity");

        return $sRoute;
    }

    /**
     * @param $configurationId
     * @param $boolValue
     * @return mixed
     */
    protected function getSProductAttributeConfigurationOptionInsertArray($configurationId, $boolValue)
    {
        $sProductAttributeConfigurationOption = new InsertModel($this->asSProductAttributeConfigurationOptions);

        $sProductAttributeConfigurationOption->add("configuration_attribute_id", $configurationId)
            ->add("configuration_value", [
                true => "Yes",
                false => "No"
            ][$boolValue]);

        return $sProductAttributeConfigurationOption->getArray();
    }
}
