<?php

namespace IntegrationBusinessBundle\Managers;

use CrmBusinessBundle\Managers\AccountManager;

// TODO: ovdje se ne zna ko pije a ko plaća

class PantheonShapeManager extends DefaultIntegrationImportManager
{
    protected $wsdl;
    protected $wsdlFile;
    protected $usedContactEmails;
    protected $existingAccountEmails;
    protected $client;
    protected $pantheonSupplier;
    protected $changedProducts;
    protected $insertAccountAttributes;
    protected $updateAccountAttributes;
    protected $insertContactAttributes;
    protected $updateContactAttributes;
    protected $insertAddressAttributes;
    protected $updateAddressAttributes;

    protected $asCity;
    protected $asCountry;
    protected $asAccount;
    protected $asAddress;
    protected $asTaxType;
    protected $asSRoute;
    protected $asProductProductGroupLink;
    protected $asProductGroup;
    protected $asProduct;

    /** @var AccountManager $accountManager */
    protected $accountManager;

    public function initialize()
    {
        parent::initialize();

        $this->insertProductAttributes = array_flip(json_decode($_ENV["PANTHEON_INSERT_PRODUCT_ATTRIBUTES"], true) ?? []);
        $this->updateProductAttributes = array_flip(json_decode($_ENV["PANTHEON_UPDATE_PRODUCT_ATTRIBUTES"], true) ?? []);
        $this->customProductAttributes = json_decode($_ENV["PANTHEON_CUSTOM_PRODUCT_ATTRIBUTES"], true) ?? [];

        $this->insertAccountAttributes = array_flip(json_decode($_ENV["PANTHEON_INSERT_ACCOUNT_ATTRIBUTES"], true) ?? []);
        $this->updateAccountAttributes = array_flip(json_decode($_ENV["PANTHEON_UPDATE_ACCOUNT_ATTRIBUTES"], true) ?? []);

        $this->insertContactAttributes = array_flip(json_decode($_ENV["PANTHEON_INSERT_CONTACT_ATTRIBUTES"], true) ?? []);
        $this->updateContactAttributes = array_flip(json_decode($_ENV["PANTHEON_UPDATE_CONTACT_ATTRIBUTES"], true) ?? []);

        $this->insertAddressAttributes = array_flip(json_decode($_ENV["PANTHEON_INSERT_ADDRESS_ATTRIBUTES"], true) ?? []);
        $this->updateAddressAttributes = array_flip(json_decode($_ENV["PANTHEON_UPDATE_ADDRESS_ATTRIBUTES"], true) ?? []);

        $this->asCity = $this->entityManager->getAttributeSetByCode("city");
        $this->asCountry = $this->entityManager->getAttributeSetByCode("country");
        $this->asAccount = $this->entityManager->getAttributeSetByCode("account");
        $this->asAddress = $this->entityManager->getAttributeSetByCode("address");
        $this->asTaxType = $this->entityManager->getAttributeSetByCode("tax_type");
        $this->asSRoute = $this->entityManager->getAttributeSetByCode("s_route");
        $this->asProductProductGroupLink = $this->entityManager->getAttributeSetByCode("product_product_group_link");
        $this->asProductGroup = $this->entityManager->getAttributeSetByCode("product_group");
        $this->asProduct = $this->entityManager->getAttributeSetByCode("product");

        $this->wsdl = $_ENV["PANTHEON_WSDL_URL"];
        $this->wsdlFile = $this->webPath . "Documents/pantheon.wsdl.xml";
        $this->pantheonSupplier = $_ENV["PANTHEON_SUPPLIER_ID"];

        $this->changedProducts = array("product_ids" => [], "supplier_ids" => []);

        $this->setRemoteSource("pantheon");
    }

    /**
     * @param int $id
     * @param string $code
     * @return array
     */
    public function getPantheonCustomer(int $id = 0, string $code = ""): array
    {
        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;
        $ret["result"] = null;

        if (empty($id) && empty($code)){
            $ret['message'] = "Both parameters are empty";
            return $ret;
        }

        $response = $this->api("getCustomer", ["Code" => $code, "Id" => $id]);
        if ($response["error"] === true) {
            $ret["message"] = $response["message"];
            return $ret;
        }

        $ret["error"] = false;
        $ret["result"] = $response["data"];

        return $ret;
    }

    /**
     * @param string $email
     * @param int $id
     * @return array
     */
    public function getPantheonCustomerByEmail(string $email = "", int $id = 0): array
    {
        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;
        $ret["result"] = null;

        if (empty($id) && empty($email)){
            $ret['message'] = "Both parameters are empty";
            return $ret;
        }

        $response = $this->api("getCustomerByEmail", ["Id" => $id, "eMail" => $email]);
        if ($response["error"] === true) {
            $ret["message"] = $response["message"];
            return $ret;
        }

        $ret["error"] = false;
        $ret["result"] = $response["data"];

        return $ret;
    }

    /**
     * @param string $oib
     * @return array
     */
    public function getPantheonCustomerByOib(string $oib = ""): array
    {
        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;
        $ret["result"] = null;

        if (empty($oib)){
            $ret['message'] = "Oib is empty";
            return $ret;
        }

        $response = $this->api("getCustomerByOIB", ["oib" => $oib]);
        if ($response["error"] === true) {
            $ret["message"] = $response["message"];
            return $ret;
        }

        $ret["error"] = false;
        $ret["result"] = $response["data"];

        return $ret;
    }

    /**
     * @param string $registrationNumber
     * @return array
     */
    public function getPantheonCustomerByRegistrationNumber(string $registrationNumber = ""): array
    {
        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;
        $ret["result"] = null;

        if (empty($registrationNumber)){
            $ret['message'] = "Registration number is empty";
            return $ret;
        }

        $response = $this->api("getCustomerByRegistrationNumber", ["RegistrationNumber" => $registrationNumber]);
        if ($response["error"] === true) {
            $ret["message"] = $response["message"];
            return $ret;
        }

        $ret["error"] = false;
        $ret["result"] = $response["data"];

        return $ret;
    }

    /**
     * @param string $vatNumber
     * @return array
     */
    public function getPantheonCustomerByVatNumber(string $vatNumber = ""): array
    {
        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;
        $ret["result"] = null;

        if (empty($vatNumber)){
            $ret['message'] = "Vat number is empty";
            return $ret;
        }

        $response = $this->api("getCustomerByVATNumber", ["VATNumber" => $vatNumber]);
        if ($response["error"] === true) {
            $ret["message"] = $response["message"];
            return $ret;
        }

        $ret["error"] = false;
        $ret["result"] = $response["data"];

        return $ret;
    }

    /**
     * @param $entity
     * @param $reselectedArray
     * @return mixed
     */
    protected function product_entity_filter($entity, $reselectedArray)
    {
        if (isset($entity['filter_insert'])) {

            if (isset($entity['filter_insert']['tax_type_code'])) {
                $entity['product_id'] = $reselectedArray['tax_type_entity'][$entity['filter_insert']['tax_type_code']]['id'];
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
    protected function product_group_entity_filter($entity, $reselectedArray)
    {
        if (isset($entity['filter_insert'])) {

            if (isset($entity['filter_insert']['parent_remote_name'])) {
                $entity['product_group_id'] = $reselectedArray['product_group_entity'][$entity['filter_insert']['parent_remote_name']]['id'];
                $entity['tax_type_id'] = $reselectedArray['tax_type_entity'][$entity['filter_insert']['tax_type_code']]['id'];
            }

            if (isset($entity['filter_insert']['currency_name_lower'])) {
                $entity['currency_id'] = $reselectedArray['currency_entity'][$entity['filter_insert']['currency_name_lower']]['id'];
            }

            unset($entity['filter_insert']);
        }

        if (isset($entity['filter_update'])) {

            if (isset($entity['filter_update']['tax_type_code'])) {
                $entity['tax_type_id'] = $reselectedArray['tax_type_entity'][$entity['filter_update']['tax_type_code']]['id'];
            }

            if (isset($entity['filter_update']['currency_code'])) {
                $entity['currency_id'] = $reselectedArray['currency_entity'][$entity['filter_update']['currency_code']]['id'];
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
        if (isset($entity['filter_insert'])) {
            if (isset($entity['filter_insert']['product_remote_id'])) {
                $entity['destination_id'] = $reselectedArray['product_entity'][$entity['filter_insert']['product_remote_id']]['id'];
            }

            if (isset($entity['filter_insert']['group_remote_name'])) {
                $entity['destination_id'] = $reselectedArray['product_group_entity'][$entity['filter_insert']['group_remote_name']]['id'];
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
    protected function s_product_attributes_link_entity_filter($entity, $reselectedArray)
    {
        if (isset($entity['filter_insert'])) {

            if (isset($entity['filter_insert']['product_remote_id'])) {
                $entity['product_id'] = $reselectedArray['product_entity'][$entity['filter_insert']['product_remote_id']]['id'];
            }

            if (isset($entity['filter_insert']['s_attribute_configuration_option_sort_key'])) {
                $entity['configuration_option'] =
                    $reselectedArray['s_product_attribute_configuration_options_entity'][$entity['filter_insert']['s_attribute_configuration_option_sort_key']]['id'];
            }

            if (isset($entity['filter_insert']['s_product_attribute_configuration_filter_key'])) {
                $entity['s_product_attribute_configuration_id'] =
                    $reselectedArray['s_product_attribute_configuration_entity'][$entity['filter_insert']['s_product_attribute_configuration_filter_key']]['id'];
            }

            unset($entity['filter_insert']);
        }

        if (!isset($entity["attribute_value_key"])) {
            $entity["attribute_value_key"] = md5($entity['product_id'] . $entity['s_product_attribute_configuration_id'] . $entity['configuration_option']);
        }

        return $entity;
    }

    /**
     * @param $entity
     * @param $reselectedArray
     * @return mixed
     */
    protected function product_product_group_link_entity_filter($entity, $reselectedArray)
    {
        if (isset($entity['filter_insert'])) {

            if (isset($entity['filter_insert']['product_remote_id'])) {
                $entity['product_id'] = $reselectedArray['product_entity'][$entity['filter_insert']['product_remote_id']]['id'];
            }

            if (isset($entity['filter_insert']['product_group_name'])) {
                $entity['product_group_id'] = $reselectedArray['product_group_entity'][$entity['filter_insert']['product_group_name']]['id'];
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
    protected function city_entity_filter($entity, $reselectedArray)
    {
        if (isset($entity['filter_insert'])) {

            if (isset($entity['filter_insert']['region_sort_key'])) {
                $entity['region_id'] = $reselectedArray['region_entity'][$entity['filter_insert']['region_sort_key']]['id'];
            }

            unset($entity['filter_insert']);
        }

        if (isset($entity['filter_update'])) {

            if (isset($entity['filter_update']['region_sort_key'])) {
                $entity['region_id'] = $reselectedArray['region_entity'][$entity['filter_update']['region_sort_key']]['id'];
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
    protected function contact_entity_filter($entity, $reselectedArray)
    {
        if (isset($entity['filter_insert'])) {

            if (isset($entity['filter_insert']['account_remote_id'])) {
                $entity['account_id'] = $reselectedArray['account_entity'][$entity['filter_insert']['account_remote_id']]['id'];
            }

            if (isset($entity["filter_insert"]["account_code"])) {
                $entity["account_id"] = $reselectedArray["account_entity"][$entity["filter_insert"]["account_code"]]["id"];
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
    protected function address_entity_filter($entity, $reselectedArray)
    {
        if (isset($entity['filter_insert'])) {

            if (isset($entity['filter_insert']['account_remote_id'])) {
                $entity['account_id'] = $reselectedArray['account_entity'][$entity['filter_insert']['account_remote_id']]['id'];
            }

            if (isset($entity['filter_insert']['contact_sort_key'])) {
                $entity['contact_id'] = $reselectedArray['contact_entity'][$entity['filter_insert']['contact_sort_key']]['id'];
            }

            if (isset($entity['filter_insert']['city_sort_key'])) {
                $entity['city_id'] = $reselectedArray['city_entity'][$entity['filter_insert']['city_sort_key']]['id'];
            }

            if (isset($entity["filter_insert"]["account_code"])) {
                $entity["account_id"] = $reselectedArray["account_entity"][$entity["filter_insert"]["account_code"]]["id"];
            }

            if (isset($entity["filter_insert"]["city_postal_code"])) {
                $entity["city_id"] = $reselectedArray["city_entity"][$entity["filter_insert"]["city_postal_code"]]["id"];
            }

            if (isset($entity["filter_insert"]["account_contact_code"])) {
                $entity["contact_id"] = $reselectedArray["contact_entity"][$entity["filter_insert"]["account_contact_code"]]["id"];
            }

            unset($entity['filter_insert']);
        }

        if (isset($entity['filter_update'])) {

            if (isset($entity['filter_update']['city_sort_key'])) {
                $entity['city_id'] = $reselectedArray['city_entity'][$entity['filter_update']['city_sort_key']]['id'];
            }

            if (isset($entity["filter_update"]["city_postal_code"])) {
                $entity["city_id"] = $reselectedArray["city_entity"][$entity["filter_insert"]["city_postal_code"]]["id"];
            }

            unset($entity["filter_update"]);
        }

        return $entity;
    }

    private function getExistingPantheonAccounts($sortKey, array $columns = [], string $additionalAnd = '')
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_filter($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "
            SELECT {$selectColumns} FROM account_entity WHERE ({$sortKey} IS NOT NULL AND {$sortKey} != '')
            AND entity_state_id = 1 {$additionalAnd};
        ";

        $ret = array();
        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    private function getExistingPantheonAccountContacts()
    {
        $q = "
        SELECT a.remote_id as account_remote_id,
               c.remote_id as contact_remote_id,
               c.id as id,
               c.full_name as full_name,
               c.email as email,
               c.phone as phone,
               c.is_active as is_active,
               c.first_name as first_name,
               c.last_name as last_name
            FROM contact_entity c
                INNER JOIN account_entity a ON c.account_id = a.id
                WHERE c.entity_state_id = 1;
        ";

        $ret = array();

        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d["account_remote_id"] . "_" . $d["contact_remote_id"]] = $d;
        }

        return $ret;
    }

    private function getPantheonCountryCities(string $additionalAnd = '')
    {
        $q = "SELECT * FROM city_entity WHERE entity_state_id = 1 {$additionalAnd};";

        $ret = array();

        foreach ($this->databaseContext->getAll($q) as $d) {
            $key = $d["postal_code"] . "_" . mb_strtolower($d["name"]);

            $ret[$key] = $d;
        }

        return $ret;
    }

    private function getExistingPantheonAddresses()
    {
        $q = "
                SELECT
            ad.id as id,
            a.remote_id as account_remote_id,
            ad.remote_id as address_remote_id,
                       ad.email as email,
            ad.name as address_name,
            ad.street as address_street,
                       ad.city_id as city_id,
            IFNULL(ad.headquarters, 0) AS headquarters,
            IFNULL(ad.billing, 0) AS billing

            FROM address_entity ad
            INNER JOIN account_entity a ON ad.account_id = a.id;";

        $ret = array();

        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d["account_remote_id"] . "_" . $d["headquarters"] . "_" . $d["address_street"]] = $d;
        }

        return $ret;
    }

    private function getExistingPantheonCountries($sortKey, $columns = [])
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_filter($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT {$selectColumns} FROM country_entity WHERE entity_state_id = 1 AND ($sortKey IS NOT NULL OR {$sortKey} != '')";

        $ret = array();

        foreach ($this->databaseContext->getAll($q) as $d) {

            if ($sortKey === "name") {
                $d[$sortKey] = json_decode($d[$sortKey], true)[3];
            }
            $ret[mb_strtolower($d[$sortKey])] = $d;
        }

        return $ret;
    }

    private function getBooleanFromLetter($x, string $lang = "cro")
    {
        if(empty($x)){
            return 0;
        }

        $x = strtolower($x);

        $letterArray = array();
        if ($lang === "cro"){
            $letterArray = ["d" => 1, "n" => 0];
        } else if ($lang === "eng"){
            $letterArray = ["y" => 1, "n" => 0];
        }

        return $letterArray[$x];
    }

    private function addToAccount($accountInsertArray, $attribute, $value)
    {
        if (isset($this->insertAccountAttributes[$attribute])) {
            $accountInsertArray[$attribute] = $value;
        }

        return $accountInsertArray;
    }

    private function addToContact($contactInsertArray, $attribute, $value)
    {
        if (isset($this->insertContactAttributes[$attribute])) {
            $contactInsertArray[$attribute] = $value;
        }

        return $contactInsertArray;
    }

    private function addToAddress($addressInsertArray, $attribute, $value)
    {
        if (isset($this->insertAddressAttributes[$attribute])) {
            $addressInsertArray[$attribute] = $value;
        }

        return $addressInsertArray;
    }

    private function getPantheonCityInsertArray($name, $postalCode, $countryId, $existingRegions = null, $regionSortKey = null, $existingCountries = [], $countrySortKey = null)
    {
        $cityInsertArray = $this->getEntityDefaults($this->asCity);

        $cityInsertArray["name"] = $name;
        $cityInsertArray["postal_code"] = $postalCode;

        if ($countryId == 1){
            if (isset($existingRegions[$regionSortKey])) {
                $cityInsertArray["region_id"] = $existingRegions[$regionSortKey]["id"];
            } else {
                $cityInsertArray["filter_insert"]["region_sort_key"] = $regionSortKey;
            }
        }

        if (!empty($countryId)){
            $cityInsertArray["country_id"] = $countryId;
        } else {
            if (isset($existingCountries[$countrySortKey])){
                $cityInsertArray["country_id"] = $existingCountries[$countrySortKey]["id"];
            } else {
                $cityInsertArray["filter_insert"]["country_sort_key"] = $countrySortKey;
            }
        }

        return $cityInsertArray;
    }

    private function getPantheonCountryInsertArray($countryCode, $country)
    {
        $countryInsertArray = $this->getEntityDefaults($this->asCountry);

        $countryInsertArray["code"] = $countryCode;

        $nameArray = array();
        foreach ($this->getStores() as $storeId) {
            $nameArray[$storeId] = $country;
        }

        $nameJson = json_encode($nameArray, JSON_UNESCAPED_SLASHES);
        $countryInsertArray["name"] = $nameJson;

        return $countryInsertArray;
    }

    private function getClient()
    {
        if (!empty($this->client) && $this->client instanceof \SoapClient) {
            return $this->client;
        }

        $client = null;
        try {
            $client = new \SoapClient($this->wsdlFile, array('trace' => 1, 'encoding' => ' UTF-8'));
        } catch (\SoapFault $e) {
            return array("error" => true, "message" => $e->getMessage());
        }

        return $client;
    }

    /**
     * @param $remoteId
     * @return array
     */
    public function getStockByProduct($remoteId)
    {
        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;
        $ret["result"] = null;

        if (empty($remoteId)){
            $ret['message'] = "Remote id missing";
            return $ret;
        }

        $response = $this->api("getStockByProduct", ["Code" => 0, "Id" => $remoteId]);
        if ($response["error"] === true) {
            $ret["message"] = $response["message"];
            return $ret;
        }

        $ret["error"] = false;
        $ret["result"] = $response["data"];

        return $ret;
    }

    /**
     * @param array $data
     * @return array
     */
    public function createOrder(array $data)
    {
        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;
        $ret["result"] = null;

        if (empty($data)){
            $ret['message'] = "Remote id missing";
            return $ret;
        }

        $response = $this->api("createOrder", $data);
        if ($response["error"] === true) {
            $ret["message"] = $response["message"];
            return $ret;
        }

        $ret["error"] = false;
        $ret["result"] = $response["data"];

        return $ret;
    }

    /**
     * @return array|void
     */
    public function runImport()
    {
        $ret = array("error" => true, "message" => null);

        /**
         * Import proizvoda
         */
        if ($_ENV["PANTHEON_IMPORT_PRODUCTS"] == 1) {
            $ret = $this->importProducts();
            if ($ret["error"] === true) {
                return $ret;
            }

            $ret = array("error" => true, "message" => null);
        }

        /**
         * Import accounta
         */
        if ($_ENV["PANTHEON_IMPORT_ACCOUNTS"] == 1) {
            $ret = $this->importAccounts();
            if ($ret["error"] === true) {
                return $ret;
            }

            $ret = array("error" => true, "message" => null);
        }

        $ret["error"] = false;
        return $ret;
    }

    /**
     * @return array
     */
    private function importAccounts()
    {
        echo "Starting importing accounts...\n";

        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;

        /**
         * U apiju piÅ¡e "optional", ali stavio sam datum
         */
        $productRequestData = array("FromDate" => "2015-01-01");

        $accounts = $this->api("getCustomers", $productRequestData, "pantheon-get-customers");
        if ($accounts['error'] === true) {
            $ret["message"] = $accounts["message"];
            return $ret;
        }

        /**
         * Grupiranje API accounta po oibu
         *
         * legal_accounts
         *  oibs_and_emails - popunjeno oboje
         *  only_oibs - samo oib
         *  only_emails - samo email
         */
        $legalAccounts = array("oibs_and_emails" => array(), "only_oibs" => array(), "only_emails" => array());

        /**
         * non_legal_accounts - nije niÅ¡ta popunjeno
         */
        $nonLegalAccounts = array();

        /**
         * Postoji 6 accounta s istim idjem
         * primjer:  <a:Id>61138</a:Id>
         *      uzimam se prvi ugl
         */
        $usedRemoteIds = array();

        /**
         * PRVO GRUPIRANJE
         *  uzima se oib i email. Na temelju toga se appenda u odreÄ‘eni array
         */
        echo "Grouping 1: Getting oibs and emails...\n";
        foreach ($accounts["data"] as $account) {

            $oib = trim($account["RegistrationNumber"]);
            $id = trim($account["Id"]);
            $email = strtolower($this->extractDataFromString(trim($account["Email"]), "email"));

            /**
             * ovo dosta uspori, ali ne moÅ¾e se bez toga jer ima viÅ¡e istih idjeva
             */
            if (!empty($usedRemoteIds) && in_array($id, $usedRemoteIds, true)) {
                continue;
            }

            if (!empty($oib) && !empty($email)) {
                $legalAccounts["oibs_and_emails"][$oib . "_" . $email][] = $account;
            }
            else if (!empty($oib) && empty($email)) {
                $legalAccounts['only_oibs'][$oib][] = $account;
            }
            else if (empty($oib) && !empty($email)) {
                $legalAccounts['only_emails'][$email][] = $account;
            }
            else {
                $nonLegalAccounts[] = $account;
            }

            $usedRemoteIds[] = $id;
        }

        /**
         * DRUGO GRUPIRANJE - ZA SVAKI OIBS_AND_EMAILS
         *   exploda taj sort_key na oib i email
         *
         *  ako postoji - mergaj arrayeve
         *
         * lega_account_final je sortirani array samo po oibu
         *  on se koristi za dalje
         *
         * ako je setano, dodaj pa unsetaj
         */
        $legalAccountsFinal = array();
        $nonLegalAccountsFinal = array();

        echo "Grouping 2: Sorting oibs and emails (final sort)...\n";
        foreach ($legalAccounts['oibs_and_emails'] as $sortKey => $sortedAccounts) {

            $exploded = explode("_", $sortKey);

            $explodedOib = $exploded[0];
            $explodedEmail = $exploded[1];

            $legalAccountsFinal[$explodedOib] = array_values($sortedAccounts);

            if (isset($legalAccounts['only_oibs'][$explodedOib])) {
                $legalAccountsFinal[$explodedOib] = array_merge($legalAccountsFinal[$explodedOib], array_values($legalAccounts['only_oibs'][$explodedOib]));
                unset($legalAccounts['only_oibs'][$explodedOib]);
            }

            if (isset($legalAccounts['only_emails'][$explodedEmail])) {
                $legalAccountsFinal[$explodedOib] = array_merge($legalAccountsFinal[$explodedOib], array_values($legalAccounts['only_emails'][$explodedEmail]));
                unset($legalAccounts['only_emails'][$explodedEmail]);
            }
        }

        /**
         * DODAVANJE OSTATKA U ODREÄENI ARRAY
         *      oni koji se nisu spojili
         */
        if (!empty($legalAccounts['only_oibs'])) {
            foreach ($legalAccounts['only_oibs'] as $oib => $oibAccountsArray) {
                $legalAccountsFinal[$oib] = array_values($oibAccountsArray);
            }
        }

        if (!empty($legalAccounts['only_emails'])) {
            foreach ($legalAccounts['only_emails'] as $emailAccountsArray) {
                $nonLegalAccounts[] = $emailAccountsArray;
            }
        }

        $nonLegalAccountsFinal = $nonLegalAccounts;

        /**
         * Unset jer viÅ¡e nisu potrebni. Sada se samo $legalAccountsFinal koristi
         */

        unset($legalAccounts);

        /**
         * Nakon grupiranja se uzimaju accounti iz baze po oibu
         *
         */
        $existingAccounts = $this->getExistingPantheonAccounts("oib", ["id", "remote_id", "code"]);

        /**
         * Update is_legal accounta
         * updatea se samo prvi iz arraya - [0]
         */
        $updateArray = array("account_entity" => array());

        if (!empty($existingAccounts)) {
            echo "\tUpdating remote ids...\n";
            foreach ($legalAccountsFinal as $oib => $legalAccount) {

                if (isset($existingAccounts[$oib])) {

                    $accountId = $existingAccounts[$oib]["id"];
                    $remoteId = trim($legalAccount[0]["Id"]);
                    $code = trim($legalAccount[0]["Code"]);

                    $accountUpdateArray = array();

                    if (!empty($remoteId) && $remoteId != $existingAccounts[$oib]["remote_id"]) {
                        $accountUpdateArray["remote_id"] = $remoteId;
                    }

                    if (!empty($code) && $code != $existingAccounts[$oib]["code"]) {
                        $accountUpdateArray["code"] = $code;
                    }

                    if (!empty($accountUpdateArray)) {
                        $accountUpdateArray["modified"] = "NOW()";
                        $updateArray["account_entity"][$accountId] = $accountUpdateArray;
                    }
                }
            }
        }

        $this->executeUpdateQuery($updateArray);

        unset($updateArray);
        unset($existingAccounts);

        /**
         * merganje svih accounta u jedan array
         */
        $allAccounts = array_merge(array_values($legalAccountsFinal), $nonLegalAccountsFinal);

        /**
         * importAccounts2 je nastavak trenutne metode
         */
        $legalRet = $this->importAccounts2($allAccounts);
        if ($legalRet["error"] === true) {
            $ret["message"] = $legalRet["message"];
            return $ret;
        }

        $ret["error"] = false;
        return $ret;
    }

    /**
     * @param $accounts
     * @return array
     */
    private function importAccounts2($accounts)
    {
        /**
         * DohvaÄ‡anje accounta po remote_id
         */
        $accountInsertColumns = array_merge(array_flip($this->insertAccountAttributes), ["id"]);
        $existingAccounts = $this->getExistingPantheonAccounts("remote_id", $accountInsertColumns);

        $this->existingAccountEmails = array_unique(array_column($existingAccounts, "email"));
        foreach ($this->existingAccountEmails as $key => $email) {
            $this->existingAccountEmails[$key] = strtolower($email);
        }

        /**
         * DohvaÄ‡anje: kontakti, gradovi, adrese i drÅ¾ave
         */
        $existingContacts = $this->getExistingPantheonAccountContacts(); // acc_remote_id _ contacts_remote_id
        $existingCities = $this->getPantheonCountryCities(); //$d["postal_code"] . "_" . mb_strtolower($d["name"]
        $existingAddresses = $this->getExistingPantheonAddresses(); //$d["account_remote_id"]."_".$d["contact_remote_id"].$d["headquarters"].$d["address_street"]
        $existingCountries = $this->getExistingPantheonCountries("code", ["id", "name", "code"]); // code

        /**
         * Prazni arrayevi
         */
        $insertArray = array("account_entity" => array(), "country_entity" => array());
        $insertArray2 = array("contact_entity" => array(), "city_entity" => array());
        $insertArray3 = array("address_entity" => array());
        $updateArray = array("account_entity" => array(), "contact_entity" => array(), "address_entity" => array(), "city_entity" => array());

        $cnt = 0;
        $accountCount = count($accounts);
        /**
         * Za svaki kontakt
         *
         * [0] Ä‡e biti headquareters = 1
         */
        foreach ($accounts as $account) {

            $currentUser = null;
            if (!isset($account[0])) {
                $currentUser = $account;
            } else {
                $currentUser = $account[0];
            }

            $remoteId = trim($currentUser["Id"]);
            $oib = trim($currentUser["RegistrationNumber"]);

            $isLegalEntity = 0;
            if (!empty($oib)) {
                $isLegalEntity = 1;
            }

            $code = trim($currentUser["Code"]);
            $name = trim($currentUser["Name"]);

            $firstName = null;
            $lastName = null;

            $explodedName = explode(" ", $name);

            $firstName = $explodedName[0];
            $lastName = end($explodedName);


            $status = $this->getBooleanFromLetter(trim($currentUser["Status"]), "eng");

            $phone = $this->extractDataFromString(trim($currentUser["Phone"]), "phone");
            $email = $this->extractDataFromString(trim($currentUser["Email"]), "email");

            /**
             * ACCOUNT
             */
            if (!isset($existingAccounts[$remoteId])) {
                // INSERT
                $accountInsertArray = $this->getEntityDefaults($this->asAccount);

                $accountInsertArray = $this->addToAccount($accountInsertArray, "remote_id", $remoteId);
                $accountInsertArray = $this->addToAccount($accountInsertArray, "is_active", $status);
                $accountInsertArray = $this->addToAccount($accountInsertArray, "code", $code);
                $accountInsertArray = $this->addToAccount($accountInsertArray, "name", $name);
                $accountInsertArray = $this->addToAccount($accountInsertArray, "email", $email);
                $accountInsertArray = $this->addToAccount($accountInsertArray, "phone", $phone);
                $accountInsertArray = $this->addToAccount($accountInsertArray, "oib", $oib);
                $accountInsertArray = $this->addToAccount($accountInsertArray, "is_legal_entity", $isLegalEntity);
                $accountInsertArray = $this->addToAccount($accountInsertArray, "first_name", $firstName);
                $accountInsertArray = $this->addToAccount($accountInsertArray, "last_name", $lastName);

                $insertArray["account_entity"][$remoteId] = $accountInsertArray;

            } else {

                $accountId = $existingAccounts[$remoteId]["id"];

                $accountUpdateArray = array();

                if (isset($this->updateAccountAttributes["name"]) && !empty($name) && $name != $existingAccounts[$remoteId]["name"]) {
                    $accountUpdateArray["name"] = $name;
                }
                if (isset($this->updateAccountAttributes["first_name"]) && !empty($firstName) && $firstName != $existingAccounts[$remoteId]["first_name"]) {
                    $accountUpdateArray["first_name"] = $firstName;
                }
                if (isset($this->updateAccountAttributes["last_name"]) && !empty($lastName) && $lastName != $existingAccounts[$remoteId]["last_name"]) {
                    $accountUpdateArray["last_name"] = $lastName;
                }
                if (isset($this->updateAccountAttributes["oib"]) && !empty($oib) && $oib != $existingAccounts[$remoteId]["oib"]) {
                    $accountUpdateArray["oib"] = $oib;
                }
                if (isset($this->updateAccountAttributes["code"]) && !empty($code) && $code != $existingAccounts[$remoteId]["code"]) {
                    $accountUpdateArray["code"] = $code;
                }
                if (isset($this->updateAccountAttributes["email"]) && !empty($email) && $email != $existingAccounts[$remoteId]["email"]) {
                    $accountUpdateArray["email"] = $email;
                }
                if (isset($this->updateAccountAttributes["is_active"]) && !empty($status) && $status != $existingAccounts[$remoteId]["is_active"]) {
                    $accountUpdateArray["is_active"] = $status;
                }
                if (isset($this->updateAccountAttributes["phone"]) && !empty($phone) && $phone != $existingAccounts[$remoteId]["phone"]) {
                    $accountUpdateArray["phone"] = $phone;
                }

                if (!empty($accountUpdateArray)) {
                    $accountUpdateArray["modified"] = "NOW()";
                    $updateArray["account_entity"][$accountId] = $accountUpdateArray;
                }
            }

            /**
             * Ako array sadrÅ¾i viÅ¡e od jednog customera
             */
            if (isset($account[0])) {
                /**
                 * Postoji viÅ¡e istih adresa pa se uvijek updatea
                 */
                $usedStreetAddresses = array();
                foreach ($account as $k => $g) {

                    $address = mb_strtolower(trim($g["Address"]));
                    if (!empty($usedStreetAddresses) && in_array($address, $usedStreetAddresses, true)){
                        continue;
                    }
                    $usedStreetAddresses[] = $address;

                    // samo prvi iz arraya ima hq = 1
                    $hq = ($k > 0) ? 0 : 1;

                    $this->importAccounts3(
                        $existingAccounts, $existingContacts, $existingCities, $existingAddresses, $existingCountries,
                        ["contact" => $g, "hq" => $hq, "current_account" => $currentUser],
                        $insertArray, $insertArray2, $insertArray3, $updateArray
                    );
                }
            } else {
                $this->importAccounts3(
                    $existingAccounts, $existingContacts, $existingCities, $existingAddresses, $existingCountries,
                    ["contact" => $currentUser, "hq" => 1, "current_account" => $currentUser],
                    $insertArray, $insertArray2, $insertArray3, $updateArray
                );
            }

        }

        $this->executeInsertQuery($insertArray);

        $reselectArray["account_entity"] = $this->getExistingPantheonAccounts("remote_id", $accountInsertColumns);
        $insertArray2 = $this->filterImportArray($insertArray2, $reselectArray);

        $this->executeInsertQuery($insertArray2);

        $reselectArray["contact_entity"] = $this->getExistingPantheonAccountContacts();
        $reselectArray["city_entity"] = $this->getPantheonCountryCities();
        $insertArray3 = $this->filterImportArray($insertArray3, $reselectArray);

        $this->executeInsertQuery($insertArray3);

        $this->executeUpdateQuery($updateArray);

        $ret["error"] = false;
        return $ret;
    }

    /**
     * @param $existingAccounts
     * @param $existingContacts
     * @param $existingCities
     * @param $existingAddresses
     * @param $existingCountries
     * @param array $data
     * @param $insertArray1
     * @param $insertArray2
     * @param $insertArray3
     * @param $updateArray
     * @return bool|null
     */
    private function importAccounts3($existingAccounts, $existingContacts, $existingCities, $existingAddresses, $existingCountries, array $data, &$insertArray1, &$insertArray2, &$insertArray3, &$updateArray)
    {
        /**
         * Dodavanje iskoriÅ¡tenog emaila u property
         */
        $email = $this->extractDataFromString(trim($data["contact"]["Email"]), "email");

        $accountRemoteId = $data["current_account"]["Id"];

        $contactRemoteId = trim($data["contact"]["Id"]);
        $address = trim($data["contact"]["Address"]);

        $countryCode = trim($data["contact"]["CountryISO2"]);
        $country = ucfirst(trim($data["contact"]["Country"]));

        $postalCode = str_replace("HR-", "", trim($data["contact"]["PostalCode"]));
        $postalName = mb_convert_case(trim($data["contact"]["PostalName"]), MB_CASE_TITLE, 'UTF-8');

        /**
         * swap ako je name numeric
         */
        if (is_numeric($postalName)) {
            $temp = $postalName;
            $postalName = $postalCode;
            $postalCode = $temp;
        }

        if (strpos(mb_strtolower($postalName), "t2l")) {
            $postalName = trim(str_replace("t2l", "", $postalName));
        }

        $citySortKey = $postalCode . "_" . mb_strtolower($postalName);
        $addressSortKey = $accountRemoteId . "_" . $data['hq'] . "_" . $address;

        $name = trim($data["contact"]["Name"]);
        $status = $this->getBooleanFromLetter(trim($data["contact"]["Status"]), "eng");

        $phone = $this->extractDataFromString(trim($data["contact"]["Phone"]), "phone");

        $explodedName = explode(" ", $name);

        $firstName = $explodedName[0];
        $lastName = end($explodedName);

        /**
         * Insert cityja
         */
        if ((!empty($postalName) && !empty($postalCode)) && !isset($existingCities[$citySortKey])) {
            $insertArray2["city_entity"][$citySortKey] =
                $this->getPantheonCityInsertArray($postalName, $postalCode, null, null, null, $existingCountries, $citySortKey);
        }

        /**
         * insert countryja
         */
        if ((!empty($country) && !empty($countryCode)) && !isset($existingCountries[mb_strtolower($countryCode)])) {
            $insertArray1["country_entity"][mb_strtolower($countryCode)] =
                $this->getPantheonCountryInsertArray($countryCode, $country);
        }

        /**
         * BuduÄ‡i da contact_entity ima unique na emailu, ne moÅ¾e se staviti u bazu ako veÄ‡ postoji - razlog zaÅ¡to se to radi je njihov api
         *
         * existingAccountEmails - svi mailovi iz account_entity
         * ZnaÄi na svakom se inseru kontakta email spremi u array i onda se provjerava ako postoji
         *
         */
        $contactCanBeInserted = true;
        if (!empty($email)) {

            if (!empty($this->existingAccountEmails) && in_array(strtolower($email), $this->existingAccountEmails, true)) {
                $contactCanBeInserted = false;
            }

            if (!empty($this->usedContactEmails) && in_array(strtolower($email), $this->usedContactEmails, true)) {
                $contactCanBeInserted = false;
            }
        }

        $contactSortKey = $accountRemoteId . "_" . $contactRemoteId;
        /**
         * Ako kontakt ne postoji - insert
         */
        if (!isset($existingContacts[$contactSortKey])) {

            if ($contactCanBeInserted) {
                $contactInsertArray = $this->getEntityDefaults($this->asContact);

                $contactInsertArray = $this->addToContact($contactInsertArray, "full_name", $name);
                $contactInsertArray = $this->addToContact($contactInsertArray, "email", $email);
                $contactInsertArray = $this->addToContact($contactInsertArray, "phone", $phone);
                $contactInsertArray = $this->addToContact($contactInsertArray, "remote_id", $contactRemoteId);
                $contactInsertArray = $this->addToContact($contactInsertArray, "is_active", $status);
                $contactInsertArray = $this->addToContact($contactInsertArray, "first_name", $firstName);
                $contactInsertArray = $this->addToContact($contactInsertArray, "last_name", $lastName);

                if (isset($this->insertContactAttributes["account_id"])) {
                    if (isset($existingAccounts[$accountRemoteId])) {
                        $contactInsertArray["account_id"] = $existingAccounts[$accountRemoteId]["id"];
                    } else {
                        $contactInsertArray["filter_insert"]["account_remote_id"] = $accountRemoteId;
                    }
                }

                $insertArray2["contact_entity"][$contactSortKey] = $contactInsertArray;

                $this->usedContactEmails[] = strtolower($email);
            }

            /**
             * Ako postoji - update
             */
        } else {

            $contactId = $existingContacts[$accountRemoteId . "_" . $contactRemoteId]["id"];

            $contactUpdateArray = array();

            if (isset($this->updateContactAttributes["full_name"]) && !empty($name) && $name != $existingContacts[$contactSortKey]["full_name"]) {
                $contactUpdateArray["full_name"] = $name;
            }

            if (isset($this->updateContactAttributes["first_name"]) && !empty($firstName) && $firstName != $existingContacts[$contactSortKey]["first_name"]) {
                $contactUpdateArray["first_name"] = $firstName;
            }

            if (isset($this->updateContactAttributes["last_name"]) && !empty($lastName) && $lastName != $existingContacts[$contactSortKey]["last_name"]) {
                $contactUpdateArray["last_name"] = $lastName;
            }

            if (isset($this->updateContactAttributes["email"]) && !empty($email) && $email != $existingContacts[$contactSortKey]["email"]) {
                $contactUpdateArray["email"] = $email;
            }

            if (isset($this->updateContactAttributes["phone"]) && !empty($phone) && $phone != $existingContacts[$contactSortKey]["phone"]) {
                $contactUpdateArray["phone"] = $phone;
            }

            if (isset($this->updateContactAttributes["is_active"]) && !empty($status) && $status != $existingContacts[$contactSortKey]["is_active"]) {
                $contactUpdateArray["is_active"] = $status;
            }

            if (!empty($contactUpdateArray)) {
                $contactUpdateArray["modified"] = "NOW()";
                $updateArray["contact_entity"][$contactId] = $contactUpdateArray;
            }
        }

        $addressRemoteId = $data['hq'] ? $accountRemoteId : $contactRemoteId;

        /**
         * ako adresa ne postoji - dodaj
         */
        if (!isset($existingAddresses[$addressSortKey])) {
            $addressInsertArray = $this->getEntityDefaults($this->asAddress);

            $addressInsertArray = $this->addToAddress($addressInsertArray, "name", $name);
            $addressInsertArray = $this->addToAddress($addressInsertArray, "headquarters", $data['hq']);
            $addressInsertArray = $this->addToAddress($addressInsertArray, "billing", $data['hq']);
            $addressInsertArray = $this->addToAddress($addressInsertArray, "street", $address);
            $addressInsertArray = $this->addToAddress($addressInsertArray, "email", $email);
            $addressInsertArray = $this->addToAddress($addressInsertArray, "remote_id", $addressRemoteId);

            if (isset($this->insertAddressAttributes["account_id"])) {
                /**
                 * dodaj acc
                 */
                if (isset($existingAccounts[$accountRemoteId])) {
                    $addressInsertArray["account_id"] = $existingAccounts[$accountRemoteId]["id"];
                } else {
                    $addressInsertArray["filter_insert"]["account_remote_id"] = $accountRemoteId;
                }
            }

            /**
             * dodaj contact
             */
            if (isset($this->insertAddressAttributes["contact_id"])) {

                if (isset($existingContacts[$contactSortKey])) {
                    $addressInsertArray["contact_id"] = $existingContacts[$contactSortKey]["id"];
                } else {
                    if ($contactCanBeInserted) {
                        $addressInsertArray["filter_insert"]["contact_sort_key"] = $contactSortKey;
                    } else {
                        $addressInsertArray["contact_id"] = null;
                    }
                }
            }

            if (isset($this->insertAddressAttributes["city_id"])) {
                //dodaj city
                if (!empty($postalName) && !empty($postalCode)) {
                    if (isset($existingCities[$citySortKey])) {
                        $addressInsertArray["city_id"] = $existingCities[$citySortKey]["id"];

                        if ($existingCities[$citySortKey]["entity_state_id"] != 1) {
                            $updateArray["city_entity"][$existingCities[$citySortKey]["id"]]["entity_state_id"] = 1;
                            $updateArray["city_entity"][$existingCities[$citySortKey]["id"]]["modified"] = "NOW()";
                        }
                    } else {
                        $addressInsertArray["filter_insert"]["city_sort_key"] = $citySortKey;
                    }
                } else {
                    $addressInsertArray["city_id"] = null;
                }
            }


            $insertArray3["address_entity"][$addressSortKey] = $addressInsertArray;

            /**
             * ako postoji - update
             */
        } else {
            $addressId = $existingAddresses[$addressSortKey]["id"];
            $addressUpdateArray = array();

            if (isset($this->updateAddressAttributes["email"]) && !empty($email) && $email != $existingAddresses[$addressSortKey]["email"]) {
                $addressUpdateArray["email"] = $email;
            }

            if (isset($this->updateAddressAttributes["remote_id"]) && $addressRemoteId != $existingAddresses[$addressSortKey]["address_remote_id"]) {
                $addressUpdateArray["remote_id"] = $addressRemoteId;
            }

            if (isset($this->updateAddressAttributes["name"]) && !empty($name) && $name != $existingAddresses[$addressSortKey]["address_name"]) {
                $addressUpdateArray["name"] = $name;
            }

            if (isset($this->updateAddressAttributes["city_id"]) && !empty($postalName) && !empty($postalCode)) {
                if (isset($existingCities[$citySortKey])) {
                    if ($existingCities[$citySortKey]["id"] != $existingAddresses[$addressSortKey]["city_id"]) {
                        $addressUpdateArray["city_id"] = $existingCities[$citySortKey]["id"];
                    }
                } else {
                    $addressUpdateArray["filter_update"]["city_sort_key"] = $citySortKey;
                }
            }

            if (!empty($addressUpdateArray)) {
                $addressUpdateArray["modified"] = "NOW()";
                $updateArray["address_entity"][$addressId] = $addressUpdateArray;
            }
        }

        return true;
    }


    /**
     * @param $value
     * @param $type
     * @return mixed|string|void|null
     * funkcija koja filtrira podatke za telefon i mail
     * u nekim situacijama se mail nalazi u telefonu i obrnuto
     */
    private function extractDataFromString($value, $type)
    {
        if (empty($value)) {
            return null;
        }

        if ($type === "phone") {

            if (strpos($value, "@")) {
                $value = null;
            }

            return $value;
        }

        $value = rtrim($value, ".");

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {

            return $value;
        } else if (strpos($value, "<")) {
            preg_match("/\<(.*?)\>/", $value, $matches);

            if (isset($matches) && isset($matches[0])) {
                return (filter_var($value, FILTER_VALIDATE_EMAIL) === false) ? null : $matches[0];
            } else {
                return null;
            }
        }
    }

    /**
     * @param string $attribute
     * @param $customerData
     * @return array
     */
    public function getCustomerBy(string $attribute, $customerData)
    {
        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;
        $ret["result"] = null;

        if (empty($customerData) || is_string($customerData)) {
            $ret["message"] = "Data is empty or is string";
            return $ret;
        }

        $method = null;

        if ($attribute === "email") {
            $method = "getCustomerByEmail";
        } else if ($attribute === "oib") {
            $method = "getCustomerByOIB";
        } else if ($attribute === "registration_number") {
            $method = "getCustomerByRegistrationNumber";
        } else if ($attribute === "vat_number") {
            $method = "getCustomerByVATNumber"; // OVA METODA NE RADI
        } else if ($attribute === "remote_id" || $attribute === "code") {
            $method = "getCustomer";
        } else {
            $ret["message"] = "DATA ERROR: CANNOT PASS ATTRIBUTE: " . $attribute . "; LINE: " . __LINE__;
            return $ret;
        }

        $response = $this->api($method, $customerData);
        if ($response["error"] === true) {
            $ret["message"] = $response["message"];
            return $ret;
        }

        $ret["error"] = false;
        $ret["result"] = $response["data"];

        return $ret;
    }

    private function getExistingPantheonTaxTypes($sortKey = "name", $columns = [])
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_filter($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "
            SELECT {$selectColumns}
            FROM tax_type_entity
            WHERE entity_state_id = 1;
        ";

        $ret = [];

        foreach ($this->databaseContext->getAll($q) as $d) {
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    /**
     * @param $sortKey
     * @param $columns
     * @param $additionalAnd
     * @return array
     */
    private function getExistingPantheonProducts($sortKey, $columns = [], $additionalAnd = "")
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

    private function getExistingPantheonSRoutes($additionalAnd = "")
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

    private function getExistingPantheonProductGroups($sortKey, array $columns = [])
    {
        $selectColumns = "*";
        if (!empty($columns)) {
            $columns[] = $sortKey;
            $columns = array_filter($columns);
            $selectColumns = implode(",", $columns);
        }

        $q = "SELECT
                {$selectColumns}
                FROM product_group_entity
            WHERE entity_state_id = 1;";

        $ret = array();

        foreach ($this->databaseContext->getAll($q) as $d) {
            if ($sortKey == 'name') {
                $d[$sortKey] = json_decode($d[$sortKey], true);
                $tmp = reset($d[$sortKey]);
                $d[$sortKey] = mb_strtolower($tmp);
            }
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    private function getExistingPantheonProductProductGroupLinks()
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

    private function getFloatValue($value)
    {
        $value = str_replace(",", ".", $value);
        $value = preg_replace('/\.(?=.*\.)/', '', $value);

        return floatval($value);
    }

    private function getPantheonTaxTypeInsertArray($name, $percent)
    {
        $taxTypeInsertArray = $this->getEntityDefaults($this->asTaxType);

        $taxTypeInsertArray["name"] = $name;
        $taxTypeInsertArray["percent"] = $percent;

        return $taxTypeInsertArray;
    }

    private function getPantheonSRouteInsertArray(array $filterArray, $url, $storeId, $type = "product")
    {
        $sRouteInsertArray = $this->getEntityDefaults($this->asSRoute);

        $sRouteInsertArray['filter_insert'][array_key_first($filterArray)] = $filterArray[array_key_first($filterArray)];
        $sRouteInsertArray['request_url'] = $url;
        $sRouteInsertArray['store_id'] = $storeId;
        $sRouteInsertArray['destination_type'] = $type;

        return $sRouteInsertArray;
    }

    private function addToProduct($productInsertArray, $attribute, $value)
    {
        if (isset($this->insertProductAttributes[$attribute])) {
            $productInsertArray[$attribute] = $value;
        }

        return $productInsertArray;
    }

    /**
     * @param $existingProducts
     * @param $remoteId
     * @param $productGroupValues
     * @return array
     */
    private function getPantheonProductProductGroupLinkInsertArray($existingProducts, $remoteId, $productGroupValues)
    {
        $productProductGroupLinkInsertArray = $this->getEntityDefaults($this->asProductProductGroupLink);

        if (!isset($existingProducts[$remoteId])){
            $productProductGroupLinkInsertArray['filter_insert']['product_remote_id'] = $remoteId;
        } else {
            $productProductGroupLinkInsertArray['product_id'] = $existingProducts[$remoteId]['id'];
        }

        if (is_array($productGroupValues)){
            $productProductGroupLinkInsertArray['filter_insert'][array_key_first($productGroupValues)] = $productGroupValues[array_key_first($productGroupValues)];
        } else {
            $productProductGroupLinkInsertArray['product_group_id'] = $productGroupValues;
        }

        $productProductGroupLinkInsertArray['ord'] = 100;


        return $productProductGroupLinkInsertArray;
    }

    private function filterFilterKey($string)
    {
        $string = $this->helperManager->nameToFilename($string);
        $string = str_replace('-', '', $string);

        return preg_replace('/([_])\\1+/', '_', $string);
    }

    private function getPantheonProductGroupInsertArray($category, $existingProductGroups, &$insertArray3, &$insertCategoryArray, $level)
    {
        $existingSRoutes = $this->getExistingPantheonSRoutes();

        /**
         * ISTO KAO I ZA PROIZVOD, SAMO ŠTO SE GLEDA TYPE
         */
        $nameArray = [];
        $descriptionArray = [];
        $urlArray = [];
        $showOnStoreArray = [];
        $metaTitleArray = [];

        foreach ($this->getStores() as $storeId) {
            $nameArray[$storeId] = $category["name"];
            $descriptionArray[$storeId] = '';
            $metaKeywordsArray[$storeId] = '';
            $metaTitleArray[$storeId] = '';
            $showOnStoreArray[$storeId] = 1;

            $i = 1;
            $url = $key = $this->routeManager->prepareUrl($category["name"]);

            /**
             * U slučaju ako ruta postoji i ako je različito od ovog tipa
             * Ruta od product_group je uvijek unique. Recimo dođe neki proizvod koji ima istu takvu rutu.
             */
            while (isset($existingSRoutes[$storeId . "_" . $url]) &&
                $existingSRoutes[$storeId . "_" . $url]["destination_type"] != "product_group") {
                $url = $key . "-" . $i++;
            }

            /**
             * Dodaj rutu ako ne postoji u insert array 2
             */
            if (!isset($insertArray2['s_route_entity'][$storeId . "_" . $url])) {
                $insertArray3['s_route_entity'][$storeId . "_" . $url] = $this->getPantheonSRouteInsertArray(["group_remote_name" => mb_strtolower($category["name"])], $url, $storeId, "product_group");
            }

            $urlArray[$storeId] = $url;
        }

        /**
         * JSON files
         */
        $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);
        $descriptionJson = json_encode($descriptionArray, JSON_UNESCAPED_UNICODE);
        $metaTitleJson = json_encode($metaTitleArray, JSON_UNESCAPED_UNICODE);
        $urlJson = json_encode($urlArray, JSON_UNESCAPED_UNICODE);
        $showOnStoreJson = json_encode($showOnStoreArray, JSON_UNESCAPED_UNICODE);

        $productGroupInsertArray = $this->getEntityDefaults($this->asProductGroup);

        $productGroupCode = $this->filterFilterKey(mb_strtolower($category["name"]));
        /**
         * Spremam u bazu sve što sam mislio da se može spremiti
         * što su: canonical_id, menu_id?
         */
        $productGroupInsertArray['name'] = $nameJson;
        $productGroupInsertArray['product_group_code'] = $productGroupCode;
        $productGroupInsertArray['meta_description'] = $nameJson;
        $productGroupInsertArray['meta_keywords'] = $nameJson;
        $productGroupInsertArray['meta_title'] = $metaTitleJson;
        $productGroupInsertArray['description'] = $descriptionJson;
        $productGroupInsertArray['url'] = $urlJson;
        $productGroupInsertArray['remote_source'] = $this->getRemoteSource();
        $productGroupInsertArray['show_on_store'] = $showOnStoreJson;
        $productGroupInsertArray["template_type_id"] = 4;
        $productGroupInsertArray["is_active"] = 1;
        $productGroupInsertArray["keep_url"] = 1;
        $productGroupInsertArray["auto_generate_url"] = 1;
        $productGroupInsertArray["ord"] = 100;
        $productGroupInsertArray["show_on_homepage"] = 0;

        /**
         * Ako je parent prazan, znači da se radi o parentu i u taj atribut sprema null
         */
        if (empty($category["parent"])) {
            $productGroupInsertArray['product_group_id'] = null;

            /**
             * Ako nije prazan, child je pa ga treba povezati s parentom
             */
        } else {
            /**
             * Filtriraj ako parent ne postoji u bazi
             */
            if (!isset($existingProductGroups[strtolower($category["parent"])])) {
                $productGroupInsertArray['filter_insert']['parent_remote_name'] = mb_strtolower($category["parent"]);

                /**
                 * Ako postoji, spremi njegov id
                 */
            } else {
                $productGroupInsertArray['product_group_id'] = $existingProductGroups[strtolower($category["parent"])]['id'];
            }
        }

        $insertCategoryArray[$level][mb_strtolower($category["name"])] = $productGroupInsertArray;

        return true;
    }

    /**
     * @return array
     */
    private function importProducts()
    {
        echo "Starting importing products...\n";

        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;

        /**
         * U apiju piÅ¡e "optional", ali stavio sam datum
         */
        $productRequestData = array("FromDate" => "2015-01-01");

        /**
         * DOHVAÄ†ANJE SVIH PROIZVODA POMOÄ†U METODE getProducts
         */
        $products = $this->api("getProducts", $productRequestData, "pantheon-get-products");
        if ($products['error'] === true) {
            $ret["message"] = $products["message"];
            return $ret;
        }

        /**
         * DOHVAÄ†ANJE POSTOJEÄ†IH STVARI IZ BAZE
         */
        $productSelectColumns = [
            "id", "remote_id", "name", "description", "code", "catalog_code", "price_base", "price_retail",
            "active", "qty", "ean", "tax_type_id"
        ];

        $productGroupSelectColumns = [
            "id", "name", "url", "product_group_id", "remote_id", "remote_source", "product_group_code"
        ];

        $existingConfigurationsSelectColumns = [
            "id", "name", "s_product_attribute_configuration_type_id"
        ];

        $existingTaxTypes = $this->getExistingPantheonTaxTypes("name", ["id", "name", "percent"]); // name
        $existingProducts = $this->getExistingPantheonProducts("remote_id", $productSelectColumns, " AND remote_source = '{$this->getRemoteSource()}' "); // remote_id
        $existingSRoutes = $this->getExistingPantheonSRoutes(); //storeId_requestUrl
        $existingProductGroups = $this->getExistingPantheonProductGroups("name", $productGroupSelectColumns); //storeId_requestUrl
        $existingProductProductGroupLinks = $this->getExistingPantheonProductProductGroupLinks();   //storeId_requestUrl

        /**
         * PRAZNI ARRAYEVI
         */
        $insertArray1 = array("tax_type_entity" => array());
        $insertArray2 = array("product_entity" => array());
        $insertArray3 = array("s_route_entity" => array(), "product_product_group_link_entity" => array());
        $updateArray = array("product_entity" => array());
        $insertCategoryArray = array();
        $changedIds = array();
        $changedRemoteIds = array();

        $productCount = count($products['data']);
        $count = 0;

        /**
         * PROMJENA ACTIVE STATUSA NA SVIM PROIZVODIMA
         */
        if (!empty($existingProducts)) {
            foreach ($existingProducts as $product) {
                if ($product['active'] != 0) {
                    $updateArray['product_entity'][$product['id']]['active'] = 0;
                    $updateArray['product_entity'][$product['id']]['date_synced'] = 'NOW()';
                    $updateArray['product_entity'][$product['id']]['modified'] = 'NOW()';
                    $changedIds[$product["id"]] = $product["id"];
                }
            }
        }

        foreach ($products["data"] as $product) {

            /**
             * POLJA IZ API-JA
             */
            $remoteId = trim($product["Id"]);
            $code = trim($product["Code"]);
            $name = preg_replace('/\s+/', ' ', trim($product["Name"])); // kod nekih postoje viÅ¡e spaceova
            $ean = trim($product["EAN"]);
            $catalogCode = trim($product["SifDob"]);
            $weight = trim($product["GrossWeight"]);
            $active = $this->getBooleanFromLetter(trim($product["Status"]), "eng");
            $qty = $this->getFloatValue(trim($product["Stock"]));
            $taxType = trim($product["VAT"]);
            $parentCategoryName = trim($product["Klas1"]);
            $childCategoryName = trim($product["Klas2"]);

            /**
             * price - mpc
             *
             * vpc1 je za jedan shop
             * vpc2 je za drugi shop
             */
            $priceBase = $this->getFloatValue(trim($product["VPC2"]));
            $priceRetail = $this->getFloatValue(trim($product["Price"]));

            $priceBase = round($priceBase, 4);
            $priceRetail = round($priceRetail, 4);

            // VARIJABLA KOJA SLUÅ½I ZA PROVJERU PROIZVODA AKO SE PROMIJENIO
            $productChanged = null;

            /**
             * Check if tax_type is int or float
             */
            if (floor($taxType) == $taxType) {
                $taxType = (int)$taxType;
            }

            /**
             * TAX_TYPE_ENTITY
             */
            $taxTypeName = 'PDV' . $taxType;
            $taxTypePercent = round($this->getFloatValue($taxType), 4);
            /**
             * Ako tax_type_entity nije prazan
             */
            if (!empty($taxType) && !isset($existingTaxTypes[$taxTypeName]) && !isset($insertArray1["tax_type_entity"][$taxTypeName])) {
                $insertArray1['tax_type_entity'][$taxTypeName] = $this->getPantheonTaxTypeInsertArray($taxTypeName, $taxTypePercent);
            }

            /**
             * Privremeno dodano sve dok adler ne bude trebao biti u pogonu, onda treba obrisati DAVOR
             */
            $priceBase = $priceRetail/(1+($taxTypePercent/100));

            if($priceRetail == 0){
                $active = 0;
            }

            /**
             * PREPARING JSON ARRAYS
             */
            $showOnStoreArray = [];
            $nameArray = [];
            $descriptionArray = [];
            $metaKeywordsArray = [];
            $urlArray = [];

            /**
             * ZA SVAKI STORE
             */
            foreach ($this->getStores() as $storeId) {
                $showOnStoreArray[$storeId] = 1;
                $nameArray[$storeId] = $name;
                //$descriptionArray[$storeId] = "";
                $metaKeywordsArray[$storeId] = '';

                /**
                 * DODAJ RUTU ZA PROIZVOD ZA SVAKI STORE
                 */
                if (!isset($existingProducts[$remoteId])) {
                    $i = 0;
                    $url = $key = $this->routeManager->prepareUrl($name);
                    while (isset($existingSRoutes[$storeId . '_' . $url]) || isset($insertArray3['s_route_entity'][$storeId . '_' . $url])) {
                        $url = $key . '_' . $i++;
                    }

                    $insertArray3["s_route_entity"][$storeId . '_' . $url] = $this->getPantheonSRouteInsertArray(["product_remote_id" => $remoteId], $url, $storeId);
                    $urlArray[$storeId] = $url;
                }
            }

            /**
             * JSON arrays
             */
            $showOnStoreJson = json_encode($showOnStoreArray, JSON_UNESCAPED_UNICODE);
            $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);
            //$descriptionJson = json_encode($descriptionArray, JSON_UNESCAPED_UNICODE);
            $metaKeywordsJson = json_encode($metaKeywordsArray, JSON_UNESCAPED_UNICODE);
            $urlJson = json_encode($urlArray, JSON_UNESCAPED_UNICODE);

            /**
             * PRODUCT_ENTITY
             * ako ne postoji - dodaj
             */
            if (!isset($existingProducts[$remoteId])) {
                $productInsertArray = $this->getEntityDefaults($this->asProduct);

                $productInsertArray = $this->addToProduct($productInsertArray, 'date_synced', 'NOW()');
                $productInsertArray = $this->addToProduct($productInsertArray, 'remote_id', $remoteId);
                $productInsertArray = $this->addToProduct($productInsertArray, 'code', $code);
                $productInsertArray = $this->addToProduct($productInsertArray, 'remote_source', $this->getRemoteSource());
                $productInsertArray = $this->addToProduct($productInsertArray, 'name', $nameJson);
                $productInsertArray = $this->addToProduct($productInsertArray, 'meta_title', $nameJson);
                //$productInsertArray = $this->addToProduct($productInsertArray, 'meta_description', $descriptionJson);
                //$productInsertArray = $this->addToProduct($productInsertArray, 'description', $descriptionJson);
                $productInsertArray = $this->addToProduct($productInsertArray, 'price_base', $priceBase);
                $productInsertArray = $this->addToProduct($productInsertArray, 'price_retail', $priceRetail);
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
                $productInsertArray = $this->addToProduct($productInsertArray, 'keep_url', 1);
                $productInsertArray = $this->addToProduct($productInsertArray, 'show_on_store', $showOnStoreJson);
                $productInsertArray = $this->addToProduct($productInsertArray, 'url', $urlJson);
                $productInsertArray = $this->addToProduct($productInsertArray, 'active', $active);
                $productInsertArray = $this->addToProduct($productInsertArray, 'content_changed', 1);
                $productInsertArray = $this->addToProduct($productInsertArray, 'meta_keywords', $metaKeywordsJson);
                $productInsertArray = $this->addToProduct($productInsertArray, 'show_on_homepage', 0);
                $productInsertArray = $this->addToProduct($productInsertArray, 'supplier_id', $this->pantheonSupplier);
                $productInsertArray = $this->addToProduct($productInsertArray, 'weight', $weight);
                $productInsertArray = $this->addToProduct($productInsertArray, 'catalog_code', $catalogCode);

                /**
                 * DODAVANJE tax_type_id
                 */
                if (isset($this->insertProductAttributes['tax_type_id'])) {
                    if (!isset($existingTaxTypes[$taxTypeName])) {
                        $productInsertArray['filter_insert']['tax_type_code'] = $taxTypeName;
                    } else {
                        $productInsertArray['tax_type_id'] = $existingTaxTypes[$taxTypeName]['id'];
                    }
                }

                /**
                 * DODAVANJE CUSTOM ATRIBUTA
                 */
                if (!empty($this->customProductAttributes)) {
                    foreach ($this->customProductAttributes as $customAttribute => $customAttributeValue) {
                        $productInsertArray[$customAttribute] = $customAttributeValue;
                    }
                }

                $insertArray2['product_entity'][] = $productInsertArray;
                $changedRemoteIds[$remoteId] = $remoteId;

                /**
                 * ako proizvod prostoji - update
                 */
            } else {
                $productId = $existingProducts[$remoteId]["id"];
                unset($updateArray["product_entity"][$productId]);

                $productUpdateArray = [];

                if (isset($this->updateProductAttributes['name']) &&
                    $nameArray != json_decode($existingProducts[$remoteId]["name"], true)) {
                    $productUpdateArray['name'] = $nameJson;
                    $productUpdateArray['meta_title'] = $nameJson;
                    $productUpdateArray['content_changed'] = 1;
                }

                if (isset($this->updateProductAttributes['ean']) && $ean != $existingProducts[$remoteId]["ean"]) {
                    $productUpdateArray["ean"] = $ean;
                }

                if (isset($this->updateProductAttributes['qty']) &&
                    (string)$qty != (string)floatval($existingProducts[$remoteId]["qty"])) {
                    $productUpdateArray["qty"] = $qty;
                }

                if (isset($this->updateProductAttributes['price_base']) &&
                    (string)$priceBase != (string)floatval($existingProducts[$remoteId]['price_base'])) {
                    $productUpdateArray['price_base'] = $priceBase;
                }

                if (isset($this->updateProductAttributes['price_retail']) &&
                    (string)$priceRetail != (string)floatval($existingProducts[$remoteId]['price_retail'])) {
                    $productUpdateArray['price_retail'] = $priceRetail;
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
                        $productChanged = $productId;
                    }
                }
            }

            /**
             * DODAVANJE KATEGORIJA
             * key predstavlja level
             * ako je parent = null. znaÄi da je taj parent
             */
            if ($_ENV["PANTHEON_IMPORT_CATEGORIES"] == 1) {

                $productGroupArray = [
                    0 => ["name" => $parentCategoryName, "parent" => null],
                    1 => ["name" => $childCategoryName, "parent" => $parentCategoryName]
                ];

                /**
                 * za svaku kategoriju
                 */
                foreach ($productGroupArray as $level => $group) {

                    // preskoÄi ako je prazno
                    if (empty($group["name"])) {
                        continue;
                    }

                    $groupNameLower = mb_strtolower($group["name"]);
                    /**
                     * product_product_group_link_entity
                     */
                    // ako je postavljena grupa, dodaj link
                    if (isset($existingProductGroups[$groupNameLower])) {
                        $productGroupId = $existingProductGroups[$groupNameLower]["id"];
                        // provjeri ako postoji link izmeÄ‘u produkta i produkt grupe
                        if (!isset($existingProductProductGroupLinks[$remoteId . '_' . $productGroupId])) {

                            /**
                             * dodavanje linka
                             */
                            $insertArray3["product_product_group_link_entity"][$remoteId . '_' . $group["name"]] =
                                $this->getPantheonProductProductGroupLinkInsertArray($existingProducts, $remoteId, $productGroupId);

                            /**
                             * proizvod se promijeno
                             */
                            if (isset($existingProducts[$remoteId])) {
                                $changedIds[$existingProducts[$remoteId]["id"]] = $existingProducts[$remoteId]["id"];
                            } else {
                                $changedRemoteIds[$remoteId] = $remoteId;
                            }
                        }

                    } else {

                        /**
                         * PRODUCT_GROUP_ENTITY
                         * GRUPA NIJE POSTAVLJENA
                         * DODAJ GRUPU
                         *  koriste se reference pa metoda ne vraÄ‡a niÅ¡ta
                         */
                        if (!isset($insertCategoryArray[$level]) || !isset($insertCategoryArray[$level][$groupNameLower])) {
                            $this->getPantheonProductGroupInsertArray($group, $existingProductGroups, $insertArray3, $insertCategoryArray, $level);
                        }

                        /**
                         * dodaj link
                         */
                        $insertArray3["product_product_group_link_entity"][$remoteId . " " . $groupNameLower] =
                            $this->getPantheonProductProductGroupLinkInsertArray($existingProducts, $remoteId, ["product_group_name" => mb_strtolower($group["name"])]);

                        /**
                         * dodaj changed product
                         */
                        if (isset($existingProducts[$remoteId])) {
                            $changedIds[$existingProducts[$remoteId]["id"]] = $existingProducts[$remoteId]["id"];
                        } else {
                            $changedRemoteIds[$remoteId] = $remoteId;
                        }
                    }
                }
            }

            if (!empty($productChanged)) {
                $changedIds[$productChanged] = $productChanged;
            }
        }

        echo "\tProducts from API: " . $productCount . "\n\n";

        echo "\tStarting database exec...\n";

        if (!empty($insertCategoryArray)) {

            $reselectedArray["product_group_entity"] = $this->getExistingPantheonProductGroups("name", $productGroupSelectColumns);

            ksort($insertCategoryArray);

            foreach ($insertCategoryArray as $level => $productGroups) {

                $productGroups = array('product_group_entity' => $productGroups);
                if ($level > 0) {
                    $productGroups = $this->filterImportArray($productGroups, $reselectedArray);
                }

                $this->executeInsertQuery($productGroups);

                $reselectedArray["product_group_entity"] = $this->getExistingPantheonProductGroups("name", $productGroupSelectColumns);
            }

            unset($reselectArray);
        } else {
            echo "\t\tcategory_query_insert: is_empty\n";
        }

        $this->executeInsertQuery($insertArray1);

        $reselectedArray = array();
        $reselectedArray["tax_type_entity"] = $this->getExistingPantheonTaxTypes("name", ["id", "name", "percent"]); // name

        $this->executeInsertQuery($insertArray2);

        $this->executeUpdateQuery($updateArray);

        $reselectedArray = array();
        $reselectedArray["product_entity"] = $this->getExistingPantheonProducts("remote_id", $productSelectColumns);
        $reselectedArray["product_group_entity"] = $this->getExistingPantheonProductGroups("name", $productGroupSelectColumns);

        $insertArray3 = $this->filterImportArray($insertArray3, $reselectedArray);

        $this->executeInsertQuery($insertArray3);

        $productIds = array_merge(
            $this->getChangedProductsFromIds($changedIds),
            $this->getChangedProductsFromIds($changedRemoteIds, $reselectedArray['product_entity'])
        );

        if (!empty($productIds)) {
            $this->changedProducts['product_ids'] = $productIds;
            $this->changedProducts['supplier_ids'][] = $this->pantheonSupplier;
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
     * @param $data
     * @return array
     */
    public function createCustomer($data)
    {
        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;
        $ret["data"] = null;

        $response = $this->api("createCustomer", $data);

        if ($response["error"] == true) {
            $ret["message"] = $response["message"];
            return $ret;
        }

        $ret["error"] = false;
        $ret['data'] = $response["data"];
        return $ret;
    }

    /**
     * @param $method
     * @param array $requestData
     * @param string $filename
     * @return array
     *
     * funkcija koja se povezuje na server
     */
    private function api($method, array $requestData, string $filename = ''): array
    {
        // return array("error" => false, "data" => json_decode(file_get_contents($this->webPath . "Documents/import/" . $filename . ".json"), true)["getCustomersResult"]["Customer"]["getCustomersOutCustomer"]);

        //  echo "Requesting API method '" . $method . "'...\n";
        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;
        $ret["data"] = null;

        $this->client = $this->getClient();
        if (!$this->client instanceof \SoapClient && is_array($this->client)) {
            $ret["message"] = $this->client["message"];
            return $ret;
        }

        if (empty($requestData)) {
            $ret["message"] = "Data cannot be empty";
            return $ret;
        }

        if ($method == "getCustomerByOIB") {
            $parameters = $requestData;
        } else {
            $parameters = array("parameters" => $requestData);
        }

        $response = null;
        try {
            $response = $this->client->$method($parameters);
        } catch (\Exception $e) {
            $ret["message"] = $e->getMessage();
            return $ret;
        }

        /**
         * Ako je sve u redu
         */
        $resultArray = json_decode(json_encode($response), true);

        $resultName = $method . "Result";

        //TODO: testirati
        if ($resultArray[$resultName]["Error"] == true) {
            $ret["message"] = $resultArray[$resultName]["ErrorDescription"];
            return $ret;
        }


        if ($method === "getProducts") {
            $ret["data"] = $resultArray[$resultName]["Product"]["getProductsOutProduct"];
        } else if ($method === "getCustomers") {
            $ret["data"] = $resultArray[$resultName]["Customer"]["getCustomersOutCustomer"];
        } else {
            $ret["data"] = $resultArray[$resultName];
        }

        if (!empty($filename)) {
            $targetPath = $this->webPath . "Documents/import/" . $filename . ".json";

            $bytes = $this->helperManager->saveRawDataToFile(json_encode($response), $targetPath);
            if (empty($bytes)) {
                $ret["message"] = "FILE ERROR: path not saved: " . $targetPath;
                return $ret;
            }
        }

        $ret["error"] = false;
        return $ret;
    }

}
