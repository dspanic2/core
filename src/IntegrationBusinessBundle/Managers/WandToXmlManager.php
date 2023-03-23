<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use CrmBusinessBundle\Abstracts\AbstractImportManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;

class WandToXmlManager extends AbstractImportManager
{
    /** @var $targetPath */
    protected $targetPath;
    /** @var AttributeSet $asAccount */
    protected $asAccount;
    /** @var AttributeSet $asAddress */
    protected $asAddress;
    /** @var AttributeSet $asCity */
    protected $asCity;
    /** @var AttributeSet $asContact */
    protected $asContact;

    /** @var AttributeSet $asAccountTypeLink */
    protected $asAccountTypeLink;

    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;

    protected $insertAccountAttributes;
    protected $updateAccountAttributes;
    protected $insertAddressAttributes;
    protected $updateAddressAttributes;
    protected $insertContactAttributes;
    protected $updateContactAttributes;

    protected $ftpHostname;
    protected $ftpUsername;
    protected $ftpPassword;
    protected $xmlFolder;

    public function initialize()
    {
        parent::initialize();

        $this->asAccount = $this->entityManager->getAttributeSetByCode("account");
        $this->asAddress = $this->entityManager->getAttributeSetByCode("address");
        $this->asCity = $this->entityManager->getAttributeSetByCode("city");
        $this->asContact = $this->entityManager->getAttributeSetByCode("contact");
        $this->asAccountTypeLink = $this->entityManager->getAttributeSetByCode("account_type_link");

        $this->insertAccountAttributes = array_flip(json_decode($_ENV["INSERT_ACCOUNT_ATTRIBUTES"], true) ?? array());
        $this->updateAccountAttributes = array_flip(json_decode($_ENV["UPDATE_ACCOUNT_ATTRIBUTES"], true) ?? array());
        $this->insertAddressAttributes = array_flip(json_decode($_ENV["INSERT_ADDRESS_ATTRIBUTES"], true) ?? array());
        $this->updateAddressAttributes = array_flip(json_decode($_ENV["UPDATE_ADDRESS_ATTRIBUTES"], true) ?? array());
        $this->insertContactAttributes = array_flip(json_decode($_ENV["INSERT_CONTACT_ATTRIBUTES"], true) ?? array());
        $this->updateContactAttributes = array_flip(json_decode($_ENV["UPDATE_CONTACT_ATTRIBUTES"], true) ?? array());

        $this->ftpUsername = $_ENV["WAND_FTP_USERNAME"];
        $this->ftpPassword = $_ENV["WAND_FTP_PASSWORD"];
        $this->ftpHostname = $_ENV["WAND_FTP_HOSTNAME"];
        $this->ftpHostname = $_ENV["WAND_FTP_HOSTNAME"];
        $this->xmlFolder = $_ENV["WAND_XML_FOLDER"];

        $this->targetPath = "Documents/import/";
    }

    /**
     * @return array|bool|mixed
     */
    public function start()
    {
        $res1 = $this->importPartners();
        if ($res1['error'] == true) {
            return $res1;
        }

        $res2 = $this->importContacts();
        if ($res2['error'] == true) {
            return $res2;
        }

        return true;
    }

    /**
     * @return array|bool
     */
    private function importContacts()
    {
        if ($_ENV["WAND_TO_XML_IMPORT_CONTACTS"] == 0) {
            return [
                'error' => true,
                'message' => 'Contacts import is disabled'
            ];
        }

        echo "Starting contacts import...\n";

        $res = $this->getXml('XML_Osobe');
        if ($res["error"] == true) {
            return $res;
        }

        $xml = $res["data"];
        if (!isset($xml->Adresanti) && !isset($xml->Adresanti->Adresant)) {
            return [
                'error' => true,
                'message' => 'No contacts set'
            ];
        }

        $contactFields = [
            "id", "remote_id", "first_name", "last_name", "full_name", "account_id", "phone", "phone_2"
        ];

        /**
         * ExistingArrays
         */
        $existingContacts = $this->getEntitiesArray($contactFields, "contact_entity", ["remote_id"], "", "WHERE entity_state_id = 1");
        $existingAddresses = $this->getEntitiesArray(["id", "remote_id", "account_id"], "address_entity", ["remote_id"], "", "WHERE entity_state_id = 1");

        /**
         * Insert array
         */
        $insertArray = [
            "contact_entity" => []
        ];
        $updateArray = [
            "contact_entity" => []
        ];

        foreach ($xml->Adresanti->Adresant as $contact) {

            $remoteId = trim((string)$contact->Adresant_ID);
            $addressRemoteId = trim((string)$contact->Partner);
            $firstName = trim((string)$contact->Ime);
            $lastName = trim((string)$contact->Prezime);

            $dateErpModified = null;
            if(isset($contact->datum_izmjene) && isset($contact->vrijeme_izmjene)){
                $datum_izmjene = trim((string)$contact->datum_izmjene);
                $vrijeme_izmjene = trim((string)$contact->vrijeme_izmjene);

                $dateErpModified = \DateTime::createFromFormat("n/d/Y H:i:s", $datum_izmjene." ".$vrijeme_izmjene);
            }

            /**
             * Slaganje za phone i phone_2
             */
            $phones = array();
            if (isset($contact->TelefonskiBrojevi)) {
                foreach ($contact->TelefonskiBrojevi->children() as $telefonskiBroj) {
                    $phones[] = (string)$telefonskiBroj;
                }
            }

            /**
             * Postoje crtice u imenu
             */
            if ($firstName === "-") {
                $firstName = null;
            }
            if ($lastName === "-") {
                $lastName = null;
            }

            $fullName = trim($firstName . " " . $lastName);
            if (empty($fullName)) {
                $fullName = null;
            }

            /**
             * Insert
             */
            if (!isset($existingContacts[$remoteId])) {
                $contactInsertArray = $this->getEntityDefaults($this->asContact);

                $contactInsertArray = $this->addTo("contact", $contactInsertArray, "remote_id", $remoteId);
                $contactInsertArray = $this->addTo("contact", $contactInsertArray, "first_name", $firstName);
                $contactInsertArray = $this->addTo("contact", $contactInsertArray, "last_name", $lastName);
                $contactInsertArray = $this->addTo("contact", $contactInsertArray, "full_name", $fullName);
                $contactInsertArray = $this->addTo("contact", $contactInsertArray, "phone", $phones[0] ?? null);
                $contactInsertArray = $this->addTo("contact", $contactInsertArray, "phone_2", $phones[1] ?? null);
                $contactInsertArray = $this->addTo("contact", $contactInsertArray, "is_active", 1);

                if (isset($this->insertContactAttributes["account_id"])) {
                    if (isset($existingAddresses[$addressRemoteId])) {
                        $contactInsertArray["account_id"] = $existingAddresses[$addressRemoteId]["account_id"];
                    } else {
                        $contactInsertArray["account_id"] = null;
                    }
                }

                $insertArray['contact_entity'][$remoteId] = $contactInsertArray;
            } else {

                if(!empty($dateErpModified) && $_ENV["WAND_XML_BIDIRECTIONAL_SYNC"]){

                    $dateCrmModified = \DateTime::createFromFormat("Y-m-d H:i:s", $existingContacts[$remoteId]["modified"]);
                    if($dateCrmModified >= $dateErpModified){
                        continue;
                    }
                }

                $contactId = $existingContacts[$remoteId]["id"];

                $contactUpdateArray = [];

                if (isset($this->updateContactAttributes["first_name"]) && !empty($firstName) && $firstName != $existingContacts[$remoteId]["first_name"]) {
                    $contactUpdateArray["first_name"] = $firstName;
                }
                if (isset($this->updateContactAttributes["last_name"]) && !empty($lastName) && $lastName != $existingContacts[$remoteId]["last_name"]) {
                    $contactUpdateArray["last_name"] = $lastName;
                }
                if (isset($this->updateContactAttributes["full_name"]) && !empty($fullName) && $fullName != $existingContacts[$remoteId]["full_name"]) {
                    $contactUpdateArray["full_name"] = $fullName;
                }
                if (isset($this->updateContactAttributes["account_id"]) && isset($existingAddresses[$addressRemoteId])) {
                    if ($existingContacts[$remoteId]["account_id"] != $existingAddresses[$addressRemoteId]["account_id"]) {
                        $contactUpdateArray["account_id"] = $existingAddresses[$addressRemoteId]["account_id"];
                    }
                }
                if (!empty($phones)) {
                    if (isset($phones[0]) && isset($this->updateContactAttributes["phone"]) && $phones[0] != $existingContacts[$remoteId]["phone"]) {
                        $contactUpdateArray["phone"] = $phones[0];
                    }
                    if (isset($phones[1]) && isset($this->updateContactAttributes["phone_2"]) && $phones[1] != $existingContacts[$remoteId]["phone_2"]) {
                        $contactUpdateArray["phone_2"] = $phones[1];
                    }
                }

                if (!empty($contactUpdateArray)) {
                    $contactUpdateArray["modified"] = "NOW()";
                    $updateArray["contact_entity"][$contactId] = $contactUpdateArray;
                }
            }
        }

        $insertQuery = $this->getInsertQuery($insertArray);
        if (!empty($insertQuery)) {
            try {
                echo "insert contact\n";
                $this->logQueryString($insertQuery);
                $this->databaseContext->executeNonQuery($insertQuery);
            } catch (\PDOException $e) {
                return [
                    "error" => true,
                    "message" => $e->getMessage()
                ];
            }
        }

        $updateQuery = $this->getUpdateQuery($updateArray);
        if (!empty($updateQuery)) {
            try {
                echo "update contact\n";
                $this->logQueryString($updateQuery);
                $this->databaseContext->executeNonQuery($updateQuery);
            } catch (\PDOException $e) {
                return [
                    "error" => true,
                    "message" => $e->getMessage()
                ];
            }
        }

        echo "FINISHED CONTACTS\n";
        return Array("error" => false);
    }

    /**
     * @return array|mixed
     */
    private function importPartners()
    {
        if ($_ENV["WAND_TO_XML_IMPORT_PARTNERS"] == 0) {
            return [
                'error' => true,
                'message' => 'Partners import is disabled'
            ];
        }

        echo "Starting partner import...\n";

        $res = $this->getXml('XML_Partneri');
        if ($res['error'] == true) {
            return $res;
        }

        $xml = $res["data"];
        if (!isset($xml->Partneri) && !isset($xml->Partneri->Partner)) {
            return [
                'error' => true,
                'message' => 'No partners set'
            ];
        }

        $accountFields = [
            "id", "remote_id", "name", "oib", "mbr", "phone", "fax"
        ];
        $accountFields = array_merge($accountFields,array_keys($this->insertAccountAttributes));
        $accountFields = array_merge($accountFields,array_keys($this->updateAccountAttributes));
        $accountFields = array_unique($accountFields);

        $addressFields = [
            "id", "remote_id", "name", "account_id", "city_id", "street", "phone"
        ];
        $addressFields = array_merge($addressFields,array_keys($this->insertAddressAttributes));
        $addressFields = array_merge($addressFields,array_keys($this->updateAddressAttributes));
        $addressFields = array_unique($addressFields);

        $cityFields = [
            "id", "postal_code", "name"
        ];

        $accountGroupFields = [
            "id", "remote_id"
        ];

        /**
         * Update account_entity where the remote_id field does not exist.
         * After that, the account_entity will be reselected by its remote_id
         */
        $existingAccountsByOib = $this->getEntitiesArray(["id", "oib", "remote_id"], "account_entity", ["oib"], "", "WHERE entity_state_id = 1 AND (remote_id IS NULL OR remote_id = '')");

        $updateArray = [
            "account_entity" => []
        ];

        if (!empty($existingAccountsByOib)) {

            foreach ($xml->Partneri->Partner as $partner) {

                $oib = trim((string)$partner->PorezniBroj1);
                $remoteId = trim((string)$partner->Partner_id);

                /**
                 * Update remote_id ako postoji existing
                 */
                if (!empty($oib) && isset($existingAccountsByOib[$oib])) {

                    $accountId = $existingAccountsByOib[$oib]["id"];

                    if ($remoteId != $existingAccountsByOib[$oib]["id"]) {
                        $updateArray["account_entity"][$accountId]["remote_id"] = $remoteId;
                    }
                }
            }
        }

        // update 0
        $updateQ = $this->getUpdateQuery($updateArray);
        if (!empty($updateQ)) {
            echo "update 0 partner\n";
            try {
                $this->logQueryString($updateQ);
                $this->databaseContext->executeNonQuery($updateQ);
            } catch (\PDOException $e) {
                return [
                    "error" => true,
                    "message" => $e->getMessage()
                ];
            }
        }

        unset($updateArray);

        $existingAccounts = $this->getEntitiesArray($accountFields, "account_entity", ["remote_id"], "", "WHERE entity_state_id = 1");
        $existingAddresses = $this->getEntitiesArray($addressFields, "address_entity", ["remote_id"], "", "WHERE entity_state_id = 1");
        $existingCities = $this->getEntitiesArray($cityFields, "city_entity", ["postal_code"], "", "WHERE entity_state_id = 1");
        $existingAccountGroups = $this->getEntitiesArray($accountGroupFields, "account_group_entity", ["remote_id"], "", "WHERE entity_state_id = 1");
        $existingEmployees = $this->getEntitiesArray(Array("u.id as user_id","a1.remote_id as remote_id"),"employee_entity",Array("remote_id")," LEFT JOIN user_entity as u on user_id = u.id ","WHERE a1.entity_state_id = 1 AND u.id is not null AND a1.remote_id is not null");
        $existingAccountTypeLinks = $this->getEntitiesArray(Array("a.remote_id as remote_id","account_type_id"),"account_type_link_entity",Array("remote_id","account_type_id"), "LEFT JOIN account_entity as a on a1.account_id = a.id", "WHERE a1.entity_state_id = 1");
        $existingCountries = $this->getEntitiesArray(Array("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name,'$.\"3\"'))) as country_name"),"country_entity",Array("country_name"), "", "WHERE a1.entity_state_id = 1");

        $insertArray1 = [
            'account_entity' => []
        ];

        $insertArray2 = [
            'account_type_link_entity' => [],
            'city_entity' => [],
            'address_entity' => []
        ];

        $updateArray = [
            'account_entity' => [],
            'address_entity' => []
        ];


        if(empty($this->crmProcessManager)){
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        foreach ($xml->Partneri->Partner as $partner) {

            /**
             * For an account
             */
            $remoteId = $this->filterRemoteId(trim((string)$partner->Partner_id));
            $oib = trim((string)$partner->PorezniBroj1);
            $mbr = trim((string)$partner->PorezniBroj2);
            $name = trim((string)$partner->naziv_komitenta);
            $phone = trim((string)$partner->telefon);
            $fax = trim((string)$partner->fax);
            $headQuartersRemoteId = $this->filterRemoteId(str_ireplace(",","",trim((string)$partner->sifra_centrale)));
            $countryName = trim((string)$partner->drzava);

            $rabat = null;
            if(isset($partner->rabat)){
                $rabat = trim((string)$partner->rabat);
            }

            $accountGroupId = null;
            $remoteIdGrupePartnera = null;
            if(isset($partner->grupa_partnera)){
                $remoteIdGrupePartnera = trim((string)$partner->grupa_partnera);
                $remoteIdGrupePartnera = intval($remoteIdGrupePartnera);
                if(isset($existingAccountGroups[$remoteIdGrupePartnera])){
                    $accountGroupId = $existingAccountGroups[$remoteIdGrupePartnera]["id"];
                }
            }

            $isLegalEntity = 1;
            if(isset($partner->fizicka_osoba)){
                if(trim((string)$partner->fizicka_osoba) === "1"){
                    $isLegalEntity = 0;
                }
            }

            #rabat_kupcu

            $dimenzija3 = null;
            if(isset($partner->dimenzija3)){
                $dimenzija3 = trim((string)$partner->dimenzija3);
                $dimenzija3 = intval($dimenzija3);
            }

            $accountTypeIds = Array();
            if(isset($partner->kupac)){
                $kupac = intval(trim((string)$partner->kupac));
                if($kupac){
                    $accountTypeIds[] = CrmConstants::ACCOUNT_TYPE_CUSTOMER;
                }
            }
            else{
                $accountTypeIds[] = CrmConstants::ACCOUNT_TYPE_CUSTOMER;
            }

            $dobavljac = null;
            if(isset($partner->dobavljac)){
                $dobavljac = intval(trim((string)$partner->dobavljac));
                if($dobavljac){
                    $accountTypeIds[] = CrmConstants::ACCOUNT_TYPE_SUPPLIER;
                }
            }

            $proizvodjac = null;
            if(isset($partner->proizvodjac)){
                $proizvodjac = intval(trim((string)$partner->proizvodjac));
                if($proizvodjac){
                    $accountTypeIds[] = CrmConstants::ACCOUNT_TYPE_MANUFACTURER;
                }
            }

            $isActive = 1;
            if(isset($partner->status)){
                if(trim((string)$partner->status) === 1){
                    $isActive = 0;
                }
            }

            $email = null;
            if(isset($partner->email)){
                $email = trim((string)$partner->email);
            }

            $napomena = null;
            if(isset($partner->napomena)){
                $napomena = trim((string)$partner->napomena);
            }

            $owner_id = null;
            if(isset($partner->komercijalist)){
                $komercijalist = trim((string)$partner->komercijalist);
                $komercijalist = intval($komercijalist);
                if(isset($existingEmployees[$komercijalist])){
                    $owner_id = $existingEmployees[$komercijalist]["user_id"];
                }
            }

            $dateErpModified = null;
            if(isset($partner->datum_izmjene) && isset($partner->vrijeme_izmjene)){
                $datum_izmjene = trim((string)$partner->datum_izmjene);
                $vrijeme_izmjene = trim((string)$partner->vrijeme_izmjene);

                $dateErpModified = \DateTime::createFromFormat("n/d/Y H:i:s", $datum_izmjene." ".$vrijeme_izmjene);
            }

            //rok_placanja

            if (empty($remoteId)){
                continue;
            }

            /**
             * For an address
             */
            $street = trim((string)$partner->adresa_komitenta);
            $cityName = trim((string)$partner->grad);
            $pbr = trim((string)$partner->pbr);

            // samo HR postanski brojevi
            if (!is_numeric($pbr)) {
                $pbr = null;
            }

            /**
             * Ako je $headQuartersRemoteId = 0, account je, headquarters = 1
             * to je taj glavni
             */
            if ($headQuartersRemoteId === "0") {

                $deleteArray = array();

                /**
                 * Insert account
                 */
                if (!isset($existingAccounts[$remoteId])) {

                    $accountInsertArray = $this->getEntityDefaults($this->asAccount);

                    $accountInsertArray = $this->addTo("account", $accountInsertArray, "remote_sync_date", "NOW()");
                    $accountInsertArray = $this->addTo("account", $accountInsertArray, "remote_id", $remoteId);
                    $accountInsertArray = $this->addTo("account", $accountInsertArray, "name", $name);
                    $accountInsertArray = $this->addTo("account", $accountInsertArray, "oib", $oib);
                    $accountInsertArray = $this->addTo("account", $accountInsertArray, "mbr", $mbr);
                    $accountInsertArray = $this->addTo("account", $accountInsertArray, "phone", $phone);
                    $accountInsertArray = $this->addTo("account", $accountInsertArray, "fax", $fax);
                    $accountInsertArray = $this->addTo("account", $accountInsertArray, "is_active", $isActive);
                    $accountInsertArray = $this->addTo("account", $accountInsertArray, "is_legal_entity", $isLegalEntity);
                    $accountInsertArray = $this->addTo("account", $accountInsertArray, "account_group_id", $accountGroupId);
                    $accountInsertArray = $this->addTo("account", $accountInsertArray, "email", $email);
                    $accountInsertArray = $this->addTo("account", $accountInsertArray, "owner_id", $owner_id);
                    $accountInsertArray = $this->crmProcessManager->customWandToXMLImport("partners","insert",$partner,$accountInsertArray,Array("remote_id" => $remoteId, "id" => 0),$insertArray2);

                    $insertArray1["account_entity"][$remoteId] = $accountInsertArray;

                } else {

                    if(!empty($dateErpModified) && $_ENV["WAND_XML_BIDIRECTIONAL_SYNC"]){

                        $dateCrmModified = \DateTime::createFromFormat("Y-m-d H:i:s", $existingAccounts[$remoteId]["modified"]);
                        if($dateCrmModified >= $dateErpModified){
                            continue;
                        }
                    }

                    $accountId = $existingAccounts[$remoteId]["id"];
                    $accountUpdateArray = [];
                    //$deleteArray["account_type_link_entity"] = $this->getEntitiesArray(Array("a.remote_id as remote_id","account_type_id"),"account_type_link_entity",Array("id"), "LEFT JOIN account_entity as a on a1.account_id = a.id","AND a.id = {$accountId}");

                    if (isset($this->updateAccountAttributes["mbr"]) && $mbr != $existingAccounts[$remoteId]["mbr"]) {
                        $accountUpdateArray["mbr"] = $mbr;
                    }
                    if (isset($this->updateAccountAttributes["oib"]) && $oib != $existingAccounts[$remoteId]["oib"]) {
                        $accountUpdateArray["oib"] = $oib;
                    }
                    if (isset($this->updateAccountAttributes["phone"]) && $phone != $existingAccounts[$remoteId]["phone"]) {
                        $accountUpdateArray["phone"] = $phone;
                    }
                    if (isset($this->updateAccountAttributes["fax"]) && $fax != $existingAccounts[$remoteId]["fax"]) {
                        $accountUpdateArray["fax"] = $fax;
                    }
                    if (isset($this->updateAccountAttributes["name"]) && $name != $existingAccounts[$remoteId]["name"]) {
                        $accountUpdateArray["name"] = $name;
                    }
                    if (isset($this->updateAccountAttributes["is_active"]) && $isActive != $existingAccounts[$remoteId]["is_active"]) {
                        $accountUpdateArray["is_active"] = $isActive;
                    }
                    if (isset($this->updateAccountAttributes["is_legal_entity"]) && $isLegalEntity != $existingAccounts[$remoteId]["is_legal_entity"]) {
                        $accountUpdateArray["is_legal_entity"] = $isLegalEntity;
                    }
                    if (isset($this->updateAccountAttributes["account_group_id"]) && $accountGroupId != $existingAccounts[$remoteId]["account_group_id"]) {
                        $accountUpdateArray["account_group_id"] = $accountGroupId;
                    }
                    if (isset($this->updateAccountAttributes["email"]) && $email != $existingAccounts[$remoteId]["email"]) {
                        $accountUpdateArray["email"] = $email;
                    }
                    if (isset($this->updateAccountAttributes["owner_id"]) && $owner_id != $existingAccounts[$remoteId]["owner_id"]) {
                        $accountUpdateArray["owner_id"] = $owner_id;
                    }
                    $accountUpdateArray = $this->crmProcessManager->customWandToXMLImport("partners","update",$partner,$accountUpdateArray,$existingAccounts[$remoteId],$insertArray2);

                    if (!empty($accountUpdateArray)) {
                        $accountUpdateArray["modified"] = "NOW()";
                        $accountUpdateArray["remote_sync_date"] = "NOW()";
                        $updateArray["account_entity"][$accountId] = $accountUpdateArray;
                    }
                }

                if(!empty($accountTypeIds)){
                    foreach ($accountTypeIds as $accountTypeId){
                        if(!isset($existingAccountTypeLinks[$remoteId . "_" . $accountTypeId])){
                            $accountTypeLinkInsertArray = $this->getEntityDefaults($this->asAccountTypeLink);

                            $accountTypeLinkInsertArray["filter_insert"]["account_remote_id"] = $remoteId;
                            $accountTypeLinkInsertArray["account_type_id"] = $accountTypeId;

                            $insertArray2["account_type_link_entity"][$remoteId . "_" . $accountTypeId] = $accountTypeLinkInsertArray;
                        }
                        else{
                            if(isset($deleteArray["account_type_link_entity"]) && !empty($deleteArray["account_type_link_entity"])) {
                                foreach ($deleteArray["account_type_link_entity"] as $key => $tmp) {
                                    if($tmp["account_type_id"] == $accountTypeId){
                                        unset($deleteArray["account_type_link_entity"][$key]);
                                    }
                                }
                            }
                        }
                    }
                }

                /*if(isset($deleteArray["account_type_link_entity"]) && !empty($deleteArray["account_type_link_entity"])){
                    foreach ($deleteArray["account_type_link_entity"] as $key => $tmp){
                        unset($deleteArray["account_type_link_entity"][$key]["account_type_id"]);
                        unset($deleteArray["account_type_link_entity"][$key]["remote_id"]);
                    }
                    $deleteQuery = $this->getDeleteQuery($deleteArray);
                    if (!empty($deleteQuery)) {
                        $this->logQueryString($deleteQuery);
                        $this->databaseContext->executeNonQuery($deleteQuery);
                    }
                }*/
            }
            /**
             * Za entitete koji su prvo bili account onda postali filijala treba prebaciti adresu
             */
            elseif(isset($existingAccounts[$remoteId])){
                if(isset($existingAccounts[$headQuartersRemoteId])){
                    $q = "UPDATE address_entity SET headquarters = 0, billing = 0, account_id = {$existingAccounts[$headQuartersRemoteId]["id"]} WHERE account_id = {$existingAccounts[$remoteId]["id"]};";
                    $this->databaseContext->executeNonQuery($q);

                    $q = "UPDATE account_location_entity SET account_id = {$existingAccounts[$headQuartersRemoteId]["id"]} WHERE account_id = {$existingAccounts[$remoteId]["id"]};";
                    $this->databaseContext->executeNonQuery($q);

                    $q = "UPDATE contact_entity SET account_id = {$existingAccounts[$headQuartersRemoteId]["id"]} WHERE account_id = {$existingAccounts[$remoteId]["id"]};";
                    $this->databaseContext->executeNonQuery($q);

                    $q = "UPDATE account_entity SET remote_id = null, oib = null, entity_state_id = 2 WHERE id = {$existingAccounts[$remoteId]["id"]};";
                    $this->databaseContext->executeNonQuery($q);

                    //TODO eventualno ako ce negdje trebati prebaciti jos account id
                    //Moze ih se naci tako da se potraze accounti sa praznim imenom i entity state id 2
                }
            }

            /**
             * IMPORT CITIES
             * is_numeric gleda samo za hr
             */
            if (!empty($pbr) && !isset($existingCities[$pbr]) && isset($existingCountries[strtolower($countryName)]) && !isset($insertArray2["city_entity"][$pbr]))
            {
                $cityInsertArray = $this->getEntityDefaults($this->asCity);

                $cityInsertArray["name"] = $cityName;
                $cityInsertArray["postal_code"] = $pbr;

                /**
                 * Region id je prazan jer se ne može nikako znti koja je županija
                 */
                $cityInsertArray["region_id"] = null;
                $cityInsertArray["country_id"] = $existingCountries[strtolower($countryName)]["id"];

                $insertArray2["city_entity"][$pbr] = $cityInsertArray;
            }

            /**
             * Insert addresses
             */
            if (!isset($existingAddresses[$remoteId]) && !isset($insertArray2['address_entity'][$remoteId])) {

                $addressInsertArray = $this->getEntityDefaults($this->asAddress);

                $addressInsertArray = $this->addTo("address", $addressInsertArray, "remote_sync_date", "NOW()");
                $addressInsertArray = $this->addTo("address", $addressInsertArray, "name", $name);
                $addressInsertArray = $this->addTo("address", $addressInsertArray, "remote_id", $remoteId);
                $addressInsertArray = $this->addTo("address", $addressInsertArray, "street", $street);
                $addressInsertArray = $this->addTo("address", $addressInsertArray, "phone", $phone);
                $addressInsertArray = $this->addTo("address", $addressInsertArray, "email", $email);
                $addressInsertArray = $this->addTo("address", $addressInsertArray, "is_active", $isActive);

                /**
                 * Ako "nije iz hrv", stavi samo city_id null
                 */
                if (isset($this->insertAddressAttributes["city_id"]) && !empty($pbr) && isset($existingCities[$pbr])) {
                    $addressInsertArray["city_id"] = $existingCities[$pbr]["id"];
                } else {
                    $addressInsertArray["city_id"] = null;
                }

                if (isset($this->insertAddressAttributes["account_id"])) {
                    /**
                     * If the headquartersRemoteId = 0
                     * The account_id must be the id of the current account
                     */
                    if ($headQuartersRemoteId === "0") {
                        $addressInsertArray = $this->getAccountInsertData($addressInsertArray, $existingAccounts, $remoteId);

                        /**
                         * If the headquartersRemoteId != 0
                         * the account_id must be headquarters
                         *
                         * To se gleda kao dodatna adresa
                         */
                    } else {
                        $addressInsertArray = $this->getAccountInsertData($addressInsertArray, $existingAccounts, $headQuartersRemoteId, 0, 0);
                    }
                }

                $insertArray2['address_entity'][$remoteId] = $addressInsertArray;

                /**
                 * UPDATE ADDRESS
                 */
            } else {

                if(!empty($dateErpModified) && $_ENV["WAND_XML_BIDIRECTIONAL_SYNC"]){

                    $dateCrmModified = \DateTime::createFromFormat("Y-m-d H:i:s", $existingAddresses[$remoteId]["modified"]);
                    if($dateCrmModified >= $dateErpModified){
                        continue;
                    }
                }

                $addressId = $existingAddresses[$remoteId]["id"];
                $addressUpdateArray = array();

                if (isset($this->updateAddressAttributes["name"]) && $name != $existingAddresses[$remoteId]["name"]) {
                    $addressUpdateArray["name"] = $name;
                }
                if (isset($this->updateAddressAttributes["street"]) && $street != $existingAddresses[$remoteId]["street"]) {
                    $addressUpdateArray["street"] = $street;
                }
                if (isset($this->updateAddressAttributes["phone"]) && $phone != $existingAddresses[$remoteId]["phone"]) {
                    $addressUpdateArray["phone"] = $phone;
                }
                if (isset($this->updateAddressAttributes["city_id"]) && !empty($pbr) && isset($existingCities[$pbr])) {
                    if ($existingAddresses[$remoteId]["city_id"] != $existingCities[$pbr]["id"]) {
                        $addressUpdateArray["city_id"] = $existingCities[$pbr]["id"];
                    }
                }
                if (isset($this->updateAddressAttributes["email"]) && $email != $existingAddresses[$remoteId]["email"]) {
                    $addressUpdateArray["email"] = $email;
                }
                if (isset($this->updateAddressAttributes["is_active"]) && $isActive != $existingAddresses[$remoteId]["is_active"]) {
                    $addressUpdateArray["is_active"] = $isActive;
                }

                if (!empty($addressUpdateArray)) {
                    $addressUpdateArray["modified"] = "NOW()";
                    $addressUpdateArray["remote_sync_date"] = "NOW()";
                    $updateArray["address_entity"][$addressId] = $addressUpdateArray;
                }
            }
        }

        /**
         * iNSERT ARRAY: dodaje account_entity
         */
        $insertQuery1 = $this->getInsertQuery($insertArray1);
        if (!empty($insertQuery1)) {
            try {
                echo "insert 1 partner\n";
                $this->logQueryString($insertQuery1);
                $this->databaseContext->executeNonQuery($insertQuery1);
            } catch (\PDOException $e) {
                return [
                    "error" => true,
                    "message" => $e->getMessage()
                ];
            }
        }

        /**
         * UPDATE postojeće: account_entity, address_entity
         */
        $updateQuery = $this->getUpdateQuery($updateArray);
        if (!empty($updateQuery)) {
            try {
                echo "update partner\n";
                $this->logQueryString($updateQuery);
                $this->databaseContext->executeNonQuery($updateQuery);
            } catch (\PDOException $e) {
                return [
                    "error" => true,
                    "message" => $e->getMessage()
                ];
            }
        }

        /**
         * Reselect arrays
         */
        /**
         * Dohvaća account_entity kako bi mogao updateati address_entity
         */
        $reselectedArray = [];
        $reselectedArray["account_entity"] = $this->getEntitiesArray($accountFields, "account_entity", ["remote_id"], "", "WHERE entity_state_id = 1");

        $insertArray2 = $this->filterImportArray($insertArray2, $reselectedArray);
        $insertQuery2 = $this->getInsertQuery($insertArray2);
        if (!empty($insertQuery2)) {
            try {
                echo "insert 2 partner\n";
                $this->logQueryString($insertQuery2);
                $this->databaseContext->executeNonQuery($insertQuery2);
            } catch (\PDOException $e) {
                return [
                    "error" => true,
                    "message" => $e->getMessage()
                ];
            }
        }

        echo "FINISHED PARTNERS\n";
        return Array("error" => false);
    }

    /**
     * @param $remoteId
     * @return array|mixed|string|string[]|null
     */
    private function filterRemoteId($remoteId)
    {
        /**
         * NEKI IDJEVI imaju zarez u stringu pa ga se ovdje uklanja kako bi se za remoteId dobio cijeli broj
         */
        if (strpos($remoteId, ",") !== false) {
            $remoteId = preg_replace("/[^0-9]/", "", $remoteId);
        }

        return $remoteId;
    }

    /**
     * @param $property
     * @param $insertArray
     * @param $attribute
     * @param $value
     * @return mixed
     */
    protected function addTo($property, $insertArray, $attribute, $value)
    {
        if (empty($value) || !isset($value)){
            $value = null;
        }

        $property = 'insert' . ucfirst($property) . "Attributes";
        if (isset($this->$property[$attribute])) {
            $insertArray[$attribute] = $value;
        }

        return $insertArray;
    }

//    /**
//     * @param $entity
//     * @param $reselectedArray
//     * @return mixed
//     */
//    protected function city_entity_filter($entity, $reselectedArray)
//    {
//        if (isset($entity["filter_insert"]["country_name"])) {
//
//            $entity["country_id"] = $reselectedArray["country_entity"][$entity["filter_insert"]["country_name"]]["id"];
//
//            unset($entity["filter_insert"]);
//        }
//        return $entity;
//    }

    /**
     * @param $entity
     * @param $reselectedArray
     * @return mixed
     */
    protected function address_entity_filter($entity, $reselectedArray)
    {
        if (isset($entity["filter_insert"])) {

            if (isset($entity["filter_insert"]["account_remote_id"])) {
                $entity["account_id"] = $reselectedArray["account_entity"][$entity["filter_insert"]["account_remote_id"]]["id"];
            }

//            if (isset($entity["filter_insert"]["city_pbr"])) {
//                $entity["city_id"] = $reselectedArray["city_entity"][$entity["filter_insert"]["city_pbr"]]["id"];
//            }

            unset($entity["filter_insert"]);
        }

//    if (isset($entity["filter_update"])) {
//
//      if (isset($entity["filter_update"]["city_pbr"])) {
//        $entity["city_id"] = $reselectedArray["city_entity"][$entity["filter_update"]["city_pbr"]]["id"];
//      }
//
//      unset($entity["filter_update"]);
//    }

        return $entity;
    }

    /**
     * @param $entity
     * @param $reselectedArray
     * @return mixed
     */
    protected function account_type_link_entity_filter($entity, $reselectedArray)
    {
        if (isset($entity["filter_insert"])) {

            if (isset($entity["filter_insert"]["account_remote_id"])) {
                $entity["account_id"] = $reselectedArray["account_entity"][$entity["filter_insert"]["account_remote_id"]]["id"];
            }

            unset($entity["filter_insert"]);
        }


        return $entity;
    }

    /**
     * @param $entity
     * @param $reselectedArray
     * @return mixed
     */
    protected function account_account_classification_link_entity_filter($entity, $reselectedArray)
    {
        if (isset($entity["filter_insert"])) {

            if (isset($entity["filter_insert"]["account_remote_id"])) {
                $entity["account_id"] = $reselectedArray["account_entity"][$entity["filter_insert"]["account_remote_id"]]["id"];
            }

            unset($entity["filter_insert"]);
        }


        return $entity;
    }

    /**
     * @param $addressInsertArray
     * @param $existingAccounts
     * @param $remoteId
     * @param int $headquarters
     * @param int $billing
     * @return mixed
     */
    private function getAccountInsertData($addressInsertArray, $existingAccounts, $remoteId, $headquarters = 1, $billing = 1)
    {
        if (!isset($existingAccounts[$remoteId])) {
            $addressInsertArray["filter_insert"]["account_remote_id"] = $remoteId;
        } else {
            $addressInsertArray["account_id"] = $existingAccounts[$remoteId]["id"];
        }

        $addressInsertArray["headquarters"] = $headquarters;
        $addressInsertArray["billing"] = $billing;

        return $addressInsertArray;
    }

    /**
     * @param $filename
     * @return array
     */
    private function getXml($filename)
    {
        $sourceFile = $filename . ".xml";
        $fullPath = $this->webPath . $this->targetPath . $sourceFile;

        if (!file_exists($this->webPath . $this->targetPath)) {
            mkdir($this->webPath . $this->targetPath, 0777, true);
        }

        $ftp = ftp_connect($this->ftpHostname);
        if ($ftp === false) {
            return array("error" => true, "message" => "FTP connection failed");
        }
        if (ftp_login($ftp, $this->ftpUsername, $this->ftpPassword) === false) {
            return array("error" => true, "message" => "FTP login failed");
        }

        ftp_set_option($ftp, FTP_USEPASVADDRESS, false);
        ftp_pasv($ftp, true);

        if (ftp_size($ftp, $this->xmlFolder . $sourceFile) == -1) {
            return array("error" => true, "message" => "FTP file was not found");
        }
        if (!ftp_get($ftp, $fullPath, $this->xmlFolder . $sourceFile, FTP_BINARY)) {
            return array("error" => true, "message" => "FTP file was not downloaded");
        }

        ftp_close($ftp);

        $fileString = file_get_contents($fullPath);

        /**
         * UTF-8 za xml
         */
        $fileString = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $fileString);

        $errors = array();
        $use_errors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($fileString, 'SimpleXMLElement', LIBXML_NOWARNING);
        if ($xml === false) {
            foreach (libxml_get_errors() as $error) {
                $errors[] = $error->message;
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($use_errors);

        if (!empty($errors)) {
            return array("error" => true, "message" => implode("\n", $errors));
        }

        return array("error" => false, "data" => $xml);
    }
}
