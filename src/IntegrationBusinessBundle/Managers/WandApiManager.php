<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Abstracts\AbstractImportManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\AccountTypeEntity;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Managers\AccountManager;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;

class WandApiManager extends AbstractImportManager
{
    /** @var string $apiUrl */
    protected $apiUrl;

    /** @var string $wandKomunikatorUrl */
    protected $wandKomunikatorUrl;
    /** @var string $wandKomunikatorPassword */
    protected $wandKomunikatorPassword;
    /** @var string $wandUsername */
    protected $wandUsername;
    /** @var string $wandUserPassword */
    protected $wandUserPassword;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;

    public function initialize()
    {
        parent::initialize();
        $this->wandKomunikatorUrl = $_ENV["WAND_KOMUNIKATOR_URL"];
        $this->wandKomunikatorPassword = $_ENV["WAND_KOMUNIKATOR_PASS"];
        $this->wandUsername = $_ENV["WAND_USERNAME"];
        $this->wandUserPassword = $_ENV["WAND_USER_PASS"];
    }

    /**
     * @param $wandKomunikatorPassword
     */
    public function setWandKomunikatorPassword($wandKomunikatorPassword){
        $this->wandKomunikatorPassword = $wandKomunikatorPassword;
    }

    /**
     * @param $wandKomunikatorUrl
     */
    public function setWandKomunikatorUrl($wandKomunikatorUrl){
        $this->wandKomunikatorUrl = $wandKomunikatorUrl;
    }

    /**
     * @param $wandUsername
     */
    public function setWandUsername($wandUsername){
        $this->wandUsername = $wandUsername;
    }

    /**
     * @param $wandUserPassword
     */
    public function setWandPassword($wandUserPassword){
        $this->wandUserPassword = $wandUserPassword;
    }

    /**
     * @param $productRemoteIds
     * @return array
     */
    public function apiKomunikatorProvjeraZalihe($productRemoteIds){

        $ret = array();
        $ret["error"] = true;
        $ret["data"] = null;
        $ret["request"] = null;
        $ret["message"] = null;

        if(empty($productRemoteIds)){
            return $ret;
        }

        $this->restManager = new RestManager();

        $post = Array();
        $post["poruka"] = implode(";",$productRemoteIds);
        $post["password"] = $this->wandKomunikatorPassword;

        $ret["request"] = $post;

        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_POSTFIELDS = json_encode($post);
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array("Content-Type: application/json");

        try{
            $res = $this->restManager->get($this->wandKomunikatorUrl."Narudzba/KomunikatorProvjeraZalihe");

            $error = false;

            if(empty($res)){
                $error = true;
            }
            else{
                if(isset($res["Status"]) && $res["Status"] == "ERROR"){
                    $error = true;
                }
            }

            if($error){
                if(!isset($res["StatusText"])){
                    //TODO komunikator je oflajn
                    $ret["message"] = "Komunikator je offline";
                }
                else{
                    $ret["message"] = $res["StatusText"];
                }
            }
            else{
                $ret["error"] = false;
                $ret["data"] = $res;
            }
        }
        catch (\Exception $e){
            $ret["message"] = $e->getMessage();
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function apiKomunikatorUcitajNarudzbu()
    {
        $ret = array();
        $ret["error"] = true;
        $ret["data"] = null;
        $ret["request"] = null;
        $ret["message"] = null;

        $this->restManager = new RestManager();

        $post = Array();
        $post["password"] = $this->wandKomunikatorPassword;

        $ret["request"] = $post;

        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_POSTFIELDS = json_encode($post);
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array("Content-Type: application/json");

        try{
            $res = $this->restManager->get($this->wandKomunikatorUrl."Narudzba/KomunikatorUcitajNarudzbu");

            $error = false;

            if(empty($res)){
                $error = true;
            }
            else{
                if(isset($res["Status"]) && $res["Status"] == "ERROR"){
                    $error = true;
                }
            }

            if($error){
                if(!isset($res["StatusText"])){
                    //TODO komunikator je oflajn
                    $ret["message"] = "Komunikator je offline";
                }
                else{
                    $ret["message"] = $res["StatusText"];
                }
            }
            else{
                $ret["error"] = false;
                $ret["data"] = $res;
            }
        }
        catch (\Exception $e){
            $ret["message"] = $e->getMessage();
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getApiToken()
    {
        $ret = array();
        $ret["error"] = true;
        $ret["data"] = null;
        $ret["request"] = null;
        $ret["message"] = null;

        $this->restManager = new RestManager();

        $post = Array();
        $post["username"] = $this->wandUsername;
        $post["password"] = $this->wandUserPassword;
        $post["hash"] = $this->wandKomunikatorPassword;

        $ret["request"] = $post;

        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_POSTFIELDS = json_encode($post);
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array("Content-Type: application/json");

        try{
            $res = $this->restManager->get($this->wandKomunikatorUrl."Wkom/GetToken");

            $error = false;

            if(empty($res)){
                $error = true;
            }
            else{
                if(isset($res["Status"]) && $res["Status"] == "ERROR"){
                    $error = true;
                }
            }

            if($error){
                if(!isset($res["StatusText"])){
                    //TODO komunikator je oflajn
                    $ret["message"] = "Komunikator je offline";
                }
                else{
                    $ret["message"] = $res["StatusText"];
                }
            }
            else{
                $ret["error"] = false;
                $ret["data"] = $res;
            }
        }
        catch (\Exception $e){
            $ret["message"] = $e->getMessage();
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function checkApiIosDate(){
        $ret = array();
        $ret["error"] = true;
        $ret["data"] = null;
        $ret["request"] = null;
        $ret["message"] = null;

        $tokenData = $this->getApiToken();
        if(!isset($tokenData["error"]) || $tokenData["error"]){
            return $tokenData;
        }

        $token = $tokenData["data"]["Token"];

        $this->restManager = new RestManager();

        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array("Authorization: Bearer {$token}");

        try{
            $res = $this->restManager->get($this->wandKomunikatorUrl."Wkom/IOS?akcija=1");

            $error = false;

            if(empty($res)){
                $error = true;
            }
            else{
                if(isset($res["Status"]) && $res["Status"] == "ERROR"){
                    $error = true;
                }
            }

            if($error){
                if(!isset($res["StatusText"])){
                    //TODO komunikator je oflajn
                    $ret["message"] = "Komunikator je offline";
                }
                else{
                    $ret["message"] = $res["StatusText"];
                }
            }
            else{
                $ret["error"] = false;
                $ret["data"] = $res;
            }
        }
        catch (\Exception $e){
            $ret["message"] = $e->getMessage();
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getApiIos()
    {
        $ret = array();
        $ret["error"] = true;
        $ret["data"] = null;
        $ret["request"] = null;
        $ret["message"] = null;

        $action = 3;
        $dateCachedData = $this->checkApiIosDate();
        if(!isset($dateCachedData["error"]) || $dateCachedData["error"]){
            return $dateCachedData;
        }
        $regenerateDateTime = $dateCachedData["data"]["Info"]["DateCached"]." ".$dateCachedData["data"]["Info"]["TimeCached"];
        $regenerateDateTime = \DateTime::createFromFormat("d.m.Y H:i:s",$regenerateDateTime);

        $now = new \DateTime();
        $diffSeconds = $now->getTimestamp() - $regenerateDateTime->getTimestamp();
        if($diffSeconds > $_ENV["WAND_IOS_CACHE_SECONDS"]){
            $action = 2;
        }

        $tokenData = $this->getApiToken();
        if(!isset($tokenData["error"]) || $tokenData["error"]){
            return $tokenData;
        }

        $token = $tokenData["data"]["Token"];

        $this->restManager = new RestManager();

        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array("Authorization: Bearer {$token}");

        try{
            $res = $this->restManager->get($this->wandKomunikatorUrl."Wkom/IOS?akcija={$action}");

            $error = false;

            if(empty($res)){
                $error = true;
            }
            else{
                if(isset($res["Status"]) && $res["Status"] == "ERROR"){
                    $error = true;
                }
            }

            if($error){
                if(!isset($res["StatusText"])){
                    //TODO komunikator je oflajn
                    $ret["message"] = "Komunikator je offline";
                }
                else{
                    $ret["message"] = $res["StatusText"];
                }
            }
            else{
                $ret["error"] = false;
                $ret["data"] = $res;
            }
        }
        catch (\Exception $e){
            $ret["message"] = $e->getMessage();
        }

        /**
         * Import data
         */
        if(!$ret["error"]){

            if(empty($this->databaseContext)){
                $this->databaseContext = $this->container->get("database_context");
            }

            $q = "DELETE FROM wand_ios_entity;";
            $this->databaseContext->executeNonQuery($q);

            if(isset($ret["data"]["IOS"]["Statistika"]) && !empty($ret["data"]["IOS"]["Statistika"])){

                $regenerateDateTime = $ret["data"]["Info"]["DateCached"]." ".$ret["data"]["Info"]["TimeCached"];
                $regenerateDateTime = \DateTime::createFromFormat("d.m.Y H:i:s",$regenerateDateTime);

                if(empty($this->accountManager)){
                    $this->accountManager = $this->container->get("account_manager");
                }

                /** @var AttributeSet $wandIosAttributeSet */
                $wandIosAttributeSet = $this->entityManager->getAttributeSetByCode("wand_ios");

                $insertWandIosQuery = "INSERT INTO wand_ios_entity (entity_type_id, attribute_set_id, created, modified, created_by, modified_by, entity_state_id, partner_id, account_id, name, date_refreshed, nedospjelo, do_30_dana, do_60_dana, do_90_dana, do_120_dana, do_150_dana, do_180_dana, do_210_dana, do_240_dana, vise_od_240_dana, u_kasnjenju, ukupan_dug) VALUES ";
                $insertWandIosQueryValues = "";

                foreach ($ret["data"]["IOS"]["Statistika"] as $d){

                    $accountId = null;

                    /** @var AccountEntity $account */
                    $account = $this->accountManager->getAccountByFilter("remote_id",$d["Sifra"]);

                    if(empty($account)){
                        /** @var AddressEntity $address */
                        $address = $this->accountManager->getAddressByFilter("remote_id",$d["Sifra"]);

                        if(!empty($address)){
                            $accountId = $address->getAccountId();
                        }
                    }
                    else{
                        $accountId = $account->getId();
                    }

                    $insertWandIosQueryValues .= "('{$wandIosAttributeSet->getEntityTypeId()}','{$wandIosAttributeSet->getId()}',NOW(),NOW(),'system','system','1','{$d["Sifra"]}','{$accountId}','{$d["Naziv"]}','{$regenerateDateTime->format("Y-m-d H:i:s")}','".floatval($d["Iznos01"])."','".floatval($d["Iznos02"])."', '".floatval($d["Iznos03"])."', '".floatval($d["Iznos04"])."', '".floatval($d["Iznos05"])."', '".floatval($d["Iznos06"])."', '".floatval($d["Iznos07"])."', '".floatval($d["Iznos08"])."', '".floatval($d["Iznos09"])."', '".floatval($d["Iznos10"])."', '".floatval($d["Iznos11"])."', '".floatval($d["Iznos12"])."'),";
                }

                if(!empty($insertWandIosQueryValues)){
                    $q = $insertWandIosQuery.substr(str_ireplace("''", "NULL", $insertWandIosQueryValues), 0, -1);
                    $this->databaseContext->executeNonQuery($q);
                }
            }
        }

        return $ret;
    }

    /**
     * @param ContactEntity $contact
     * @return array
     * @throws \Exception
     */
    public function importOsoba(ContactEntity $contact){

        $ret = array();
        $ret["error"] = true;
        $ret["data"] = null;
        $ret["request"] = null;
        $ret["message"] = null;

        /**
         * Check if data is correct
         */
        if(empty($contact->getAccount())){
            $ret["message"] = "Nedostaje partner";
            return $ret;
        }
        if(empty($contact->getAccount()->getRemoteId())){
            $ret["message"] = "Partner nije unesen u wand";
            return $ret;
        }
        if(empty($contact->getLastName())){
            $ret["message"] = "Nedostaje prezime";
            return $ret;
        }

        /**
         * Get postal code
         */
        $postalCode = null;

        $addresses = $contact->getAddresses();
        if(EntityHelper::isCountable($addresses) && count($addresses) > 0){
            /** @var AddressEntity $defaultAddress */
            $defaultAddress = $addresses[0];
            if(!empty($defaultAddress->getCity())){
                $postalCode = $defaultAddress->getCity()->getPostalCode();
            }
        }

        if(empty($postalCode)){

            /** @var AccountEntity $account */
            $account = $contact->getAccount();

            $addresses = $account->getAddresses();
            if(EntityHelper::isCountable($addresses) && count($addresses) > 0){
                /** @var AddressEntity $defaultAddress */
                $defaultAddress = $addresses[0];
                if(!empty($defaultAddress->getCity())){
                    $postalCode = $defaultAddress->getCity()->getPostalCode();
                }
            }
        }

        if(empty($postalCode)){
            $ret["message"] = "Nedostaje poštanski broj";
            return $ret;
        }

        $tokenData = $this->getApiToken();
        if(!isset($tokenData["error"]) || $tokenData["error"]){
            return $tokenData;
        }

        $post = Array();
        $post["tablica"] = "osobe";
        $post["akcija"] = "INSERT";
        $post["osobe"] = Array();
        $tmpOsoba["partnerId"] = $contact->getAccount()->getRemoteId();
        $tmpOsoba["ime"] = $contact->getFirstName();
        $tmpOsoba["prezime"] = $contact->getLastName();
        $tmpOsoba["email"] = Array($contact->getEmail());
        $tmpOsoba["telefon"] = $contact->getPhone();
        $tmpOsoba["grad"] = $postalCode;
        $neaktivan = 0;
        if(!$contact->getIsActive() || $contact->getEntityStateId() == 2){
            $neaktivan = 1;
        }
        $tmpOsoba["neaktivan"] = $neaktivan;

        if(!empty($contact->getRemoteId())){
            $post["akcija"] = "UPDATE";
            $tmpOsoba["osobaId"] = $contact->getRemoteId();
        }

        $post["osobe"][] = $tmpOsoba;

        $token = $tokenData["data"]["Token"];

        $this->restManager = new RestManager();

        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array("Authorization: Bearer {$token}");
        $this->restManager->CURLOPT_POSTFIELDS = json_encode($post);

        try{
            $res = $this->restManager->get($this->wandKomunikatorUrl."Wkom/ImportOsobe");

            $error = false;

            if(empty($res)){
                $error = true;
            }
            else{
                if(isset($res["Status"]) && $res["Status"] == "ERROR"){
                    $error = true;
                }
            }

            if($error){
                if(!isset($res["StatusText"])){
                    $ret["message"] = "Greška prilikom unosa";
                }
                else{
                    $ret["message"] = $res["StatusText"];
                }
            }
            else{
                $ret["error"] = false;
                $ret["data"] = $res;
            }
        }
        catch (\Exception $e){
            $ret["message"] = $e->getMessage();
        }

        if(!isset($ret["data"]["Osobe"][0]["OsobaId"])){
            $ret["error"] = true;
            return $ret;
        }

        $remoteId = $ret["data"]["Osobe"][0]["OsobaId"];

        if(empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }

        /**
         * Update contact
         */
        $contact->setModified(new \DateTime());
        $contact->setRemoteSyncDate(new \DateTime());
        if(empty($contact->getRemoteId())){
            $contact->setRemoteId($remoteId);
        }

        $this->entityManager->saveEntityWithoutLog($contact);
        $this->entityManager->refreshEntity($contact);

        return $ret;
        /*array:2 [
          "Status" => "OK"
          "Osobe" => array:1 [
            0 => array:5 [
              "Idx" => 1
              "OsobaId" => 22
              "PartnerId" => null
              "RobaId" => null
              "Status" => "OK"
            ]
          ]
        ]*/
    }

    /**
     * @param AccountEntity $account
     * @return array
     * @throws \Exception
     */
    public function importPartner(AccountEntity $account){

        $ret = array();
        $ret["error"] = true;
        $ret["data"] = null;
        $ret["request"] = null;
        $ret["message"] = null;

        /**
         * Check if data is correct
         */
        if(empty($account->getName())){
            $ret["message"] = "Nedostaje naziv parnera";
            return $ret;
        }

        /**
         * Get postal code
         */
        $postalCode = null;

        /** @var AddressEntity $defaultAddress */
        $defaultAddress = $account->getHeadquartersAddress();
        if(!empty($defaultAddress)){
            if(!empty($defaultAddress->getCity())){
                $postalCode = $defaultAddress->getCity()->getPostalCode();
            }
        }

        if(empty($postalCode)){

            $addresses = $account->getAddresses();
            if(EntityHelper::isCountable($addresses) && count($addresses) > 0){
                /** @var AddressEntity $defaultAddress */
                $defaultAddress = $addresses[0];
                if(!empty($defaultAddress->getCity())){
                    $postalCode = $defaultAddress->getCity()->getPostalCode();
                }
            }
        }

        if(empty($postalCode) || empty($defaultAddress)){
            $ret["message"] = "Nedostaje poštanski broj";
            return $ret;
        }

        $tokenData = $this->getApiToken();
        if(!isset($tokenData["error"]) || $tokenData["error"]){
            return $tokenData;
        }

        $post = Array();
        $post["tablica"] = "partneri";
        $post["akcija"] = "INSERT";
        $post["partneri"] = Array();
        //$tmpPartner["partnerId"] = $contact->getAccount()->getRemoteId();
        $tmpPartner["OIB"] = $account->getOib();
        $tmpPartner["naziv"] = $account->getName();
        $tmpPartner["email"] = Array($account->getEmail());
        $tmpPartner["telefon"] = $account->getPhone();
        $tmpPartner["grad"] = $postalCode;
        $tmpPartner["poslovnica"] = 0;

        /*if(!empty($account->getAccountGroup())){
            $tmpPartner["grupapartnera"] = $account->getAccountGroup()->getRemoteId();
        }*/
        $tmpPartner["fizickaosoba"] = 1;
        if($account->getIsLegalEntity()){
            $tmpPartner["fizickaosoba"] = 0;
        }

        $kupac = 0;
        $dobavljac = 0;
        $proizvodjac = 0;

        $accountTypes = $account->getAccountTypes();
        if(EntityHelper::isCountable($accountTypes) && count($accountTypes)){
            /** @var AccountTypeEntity $accountType */
            foreach ($accountTypes as $accountType){
                if($accountType->getId() == CrmConstants::ACCOUNT_TYPE_CUSTOMER){
                    $kupac = 1;
                }
                elseif($accountType->getId() == CrmConstants::ACCOUNT_TYPE_MANUFACTURER){
                    $proizvodjac = 1;
                }
                elseif($accountType->getId() == CrmConstants::ACCOUNT_TYPE_SUPPLIER){
                    $dobavljac = 1;
                }
            }
        }

        $tmpPartner["kupac"] = $kupac;
        $tmpPartner["dobavljac"] = $dobavljac;
        $tmpPartner["proizvodjac"] = $proizvodjac;

        //webkupac

        $neaktivan = 0;
        if(!$account->getIsActive() || $account->getEntityStateId() == 2){
            $neaktivan = 1;
        }
        $tmpPartner["neaktivan"] = $neaktivan;

        if(!empty($account->getRemoteId())){
            $post["akcija"] = "UPDATE";
            $tmpPartner["partnerId"] = $account->getRemoteId();
        }

        $post["partneri"][] = $tmpPartner;

        $token = $tokenData["data"]["Token"];

        $this->restManager = new RestManager();

        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array("Authorization: Bearer {$token}");
        $this->restManager->CURLOPT_POSTFIELDS = json_encode($post);

        try{
            $res = $this->restManager->get($this->wandKomunikatorUrl."Wkom/ImportPartneri");

            $error = false;

            if(empty($res)){
                $error = true;
            }
            else{
                if(isset($res["Status"]) && $res["Status"] == "ERROR"){
                    $error = true;
                }
            }

            if($error){
                if(!isset($res["StatusText"])){
                    $ret["message"] = "Greška prilikom unosa";
                }
                else{
                    $ret["message"] = $res["StatusText"];
                }
            }
            else{
                $ret["error"] = false;
                $ret["data"] = $res;
            }
        }
        catch (\Exception $e){
            $ret["message"] = $e->getMessage();
        }

        if(!isset($ret["data"]["Partneri"][0]["PartnerId"])){
            $ret["error"] = true;
            return $ret;
        }

        $remoteId = $ret["data"]["Partneri"][0]["PartnerId"];

        if(empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }

        /**
         * Update contact
         */
        $account->setModified(new \DateTime());
        $account->setRemoteSyncDate(new \DateTime());
        if(empty($account->getRemoteId())){
            $account->setRemoteId($remoteId);
        }

        $this->entityManager->saveEntityWithoutLog($account);
        $this->entityManager->refreshEntity($account);

        /**
         * Fill address id only if address remote_id is empty
         */
        if(empty($defaultAddress->getRemoteId())){
            $defaultAddress->setRemoteId($remoteId);
            $defaultAddress->setModified(new \DateTime());
            $defaultAddress->setRemoteSyncDate(new \DateTime());

            $this->entityManager->saveEntityWithoutLog($defaultAddress);
            $this->entityManager->refreshEntity($defaultAddress);
        }

        return $ret;
        /*array:2 [
          "Status" => "OK"
          "Osobe" => array:1 [
            0 => array:5 [
              "Idx" => 1
              "OsobaId" => 22
              "PartnerId" => null
              "RobaId" => null
              "Status" => "OK"
            ]
          ]
        ]*/
    }

    /**
     * @param AddressEntity $address
     * @return array
     * @throws \Exception
     */
    public function importAddress(AddressEntity $address){

        $ret = array();
        $ret["error"] = true;
        $ret["data"] = null;
        $ret["request"] = null;
        $ret["message"] = null;

        $account = $address->getAccount();

        /**
         * Check if data is correct
         */
        if(empty($account->getName())){
            $ret["message"] = "Nedostaje naziv partnera";
            return $ret;
        }

        /**
         * Get postal code
         */
        $postalCode = null;

        $defaultAddress = $address;
        if(!empty($defaultAddress)){
            if(!empty($defaultAddress->getCity())){
                $postalCode = $defaultAddress->getCity()->getPostalCode();
            }
        }

        if(empty($postalCode)){
            $ret["message"] = "Nedostaje poštanski broj";
            return $ret;
        }

        $tokenData = $this->getApiToken();
        if(!isset($tokenData["error"]) || $tokenData["error"]){
            return $tokenData;
        }

        $post = Array();
        $post["tablica"] = "partneri";
        $post["akcija"] = "INSERT";
        $post["partneri"] = Array();
        $tmpPartner["centralaId"] = $account->getRemoteId();
        $tmpPartner["OIB"] = $account->getOib();

        $tmpPartner["naziv"] = $account->getName()." - ".$address->getStreet();
        /*if(!empty($address->getName())){
            $tmpPartner["naziv"] = $address->getName();
        }*/

        $tmpPartner["email"] = "";
        if(!empty($address->getEmail())){
            $tmpPartner["email"] = Array($address->getEmail());
        }
        $tmpPartner["telefon"] = "";
        if(!empty($address->getPhone())){
            $tmpPartner["telefon"] = $address->getPhone();
        }
        $tmpPartner["grad"] = $postalCode;
        $tmpPartner["poslovnica"] = 1;

        if(!empty($account->getAccountGroup())){
            $tmpPartner["grupapartnera"] = $account->getAccountGroup()->getRemoteId();
        }
        $tmpPartner["fizickaosoba"] = 1;
        if($account->getIsLegalEntity()){
            $tmpPartner["fizickaosoba"] = 0;
        }

        $kupac = 0;
        $dobavljac = 0;
        $proizvodjac = 0;

        $accountTypes = $account->getAccountTypes();
        if(EntityHelper::isCountable($accountTypes) && count($accountTypes)){
            /** @var AccountTypeEntity $accountType */
            foreach ($accountTypes as $accountType){
                if($accountType->getId() == CrmConstants::ACCOUNT_TYPE_CUSTOMER){
                    $kupac = 1;
                }
                elseif($accountType->getId() == CrmConstants::ACCOUNT_TYPE_MANUFACTURER){
                    $proizvodjac = 1;
                }
                elseif($accountType->getId() == CrmConstants::ACCOUNT_TYPE_SUPPLIER){
                    $dobavljac = 1;
                }
            }
        }

        $tmpPartner["kupac"] = $kupac;
        $tmpPartner["dobavljac"] = $dobavljac;
        $tmpPartner["proizvodjac"] = $proizvodjac;


        //webkupac

        $neaktivan = 0;
        if(!$address->getIsActive() || $account->getEntityStateId() == 2){
            $neaktivan = 1;
        }
        $tmpPartner["neaktivan"] = $neaktivan;

        if(!empty($address->getRemoteId())){
            $post["akcija"] = "UPDATE";
            $tmpPartner["partnerId"] = $address->getRemoteId();
        }

        $post["partneri"][] = $tmpPartner;

        $token = $tokenData["data"]["Token"];

        $this->restManager = new RestManager();

        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array("Authorization: Bearer {$token}");
        $this->restManager->CURLOPT_POSTFIELDS = json_encode($post);

        try{
            $res = $this->restManager->get($this->wandKomunikatorUrl."Wkom/ImportPartneri");

            $error = false;

            if(empty($res)){
                $error = true;
            }
            else{
                if(isset($res["Status"]) && $res["Status"] == "ERROR"){
                    $error = true;
                }
            }

            if($error){
                if(!isset($res["StatusText"])){
                    $ret["message"] = "Greška prilikom unosa";
                }
                else{
                    $ret["message"] = $res["StatusText"];
                }
            }
            else{
                $ret["error"] = false;
                $ret["data"] = $res;
            }
        }
        catch (\Exception $e){
            $ret["message"] = $e->getMessage();
        }

        if(!isset($ret["data"]["Partneri"][0]["PartnerId"])){
            $ret["error"] = true;
            return $ret;
        }

        $remoteId = $ret["data"]["Partneri"][0]["PartnerId"];

        if(empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }

        /**
         * Update address
         */
        $address->setModified(new \DateTime());
        $address->setRemoteSyncDate(new \DateTime());
        if(empty($address->getRemoteId())){
            $address->setRemoteId($remoteId);
        }

        $this->entityManager->saveEntityWithoutLog($address);
        $this->entityManager->refreshEntity($address);

        return $ret;
        /*array:2 [
          "Status" => "OK"
          "Osobe" => array:1 [
            0 => array:5 [
              "Idx" => 1
              "OsobaId" => 22
              "PartnerId" => null
              "RobaId" => null
              "Status" => "OK"
            ]
          ]
        ]*/
    }

    /**
     * @param AddressEntity $address
     * @return array
     * @throws \Exception
     */
    public function importRoba(ProductEntity $product){

        $ret = array();
        $ret["error"] = true;
        $ret["data"] = null;
        $ret["request"] = null;
        $ret["message"] = null;

        $tokenData = $this->getApiToken();
        if(!isset($tokenData["error"]) || $tokenData["error"]){
            return $tokenData;
        }

        $post = Array();
        $post["tablica"] = "robe";
        $post["akcija"] = "INSERT";
        $post["robe"] = Array();

        $tmpRoba = Array();

        if(!empty($product->getRemoteId())){
            $post["akcija"] = "UPDATE";
            $tmpRoba["robaId"] = $product->getRemoteId();
        }

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }

        $naziv = $this->getPageUrlExtension->getEntityStoreAttribute($_ENV["DEFAULT_STORE_ID"], $product, "name");
        if(strlen($naziv > 70)){
            $naziv = substr($naziv, 0, 70);
        }

        $naziv = StringHelper::sanitizeFileName($naziv);

        $tmpRoba["naziv"] = $naziv;
        $tmpRoba["tip"] = "0"; //default roba
        $tmpRoba["grupa"] = "W101"; //Šifra grupe kojoj roba pripada – broj u grupi će se dodijeliti sam
        $tmpRoba["katbroj"] = $product->getCatalogCode();

        $katbroj = $tmpRoba["katbroj"];

        //$tmpRoba["barkod"] = $product->getEan();
        //$tmpRoba["a1"] = null; //Šifra atributa 1 definiranog u 4D Wandu
        //$tmpRoba["a2"] = null; //Šifra atributa 2 definiranog u 4D Wandu
        //$tmpRoba["a3"] = null; //Šifra atributa 3 definiranog u 4D Wandu
        //$tmpRoba["a4"] = null; //Šifra atributa 4 definiranog u 4D Wandu

        $measure = $product->getMeasure();
        if(empty($measure) || !in_array($measure,Array("kom","kg"))){
            $measure = "kom";
        }

        $tmpRoba["jm"] = $measure; //Kratica jedinice mjere robe definirane u 4D Wandu, npr. kom, kg,…
        $tmpRoba["tarifa"] = intval($product->getTaxType()->getPercent()); //Porezna tarifa, npr. upisuje se samo 25 ako se PDV na tu robu obračunava po poreznoj stopi od 25%.

        $tmpRoba["vpcijena"] = number_format($product->getPriceBase(),2,".","");
        $tmpRoba["mpcijena"] = number_format($product->getPriceRetail(),2,".","");
        $tmpRoba["proizvodjacId"] = $product->getManufacturerRemoteId();
        $tmpRoba["neaktivan"] = intval(!$product->getActive()); //ide obrnuta logika

        $post["robe"][] = $tmpRoba;

        $token = $tokenData["data"]["Token"];

        $this->restManager = new RestManager();

        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array("Authorization: Bearer {$token}");
        //$this->restManager->CURLOPT_POSTFIELDS = json_encode($post);

        $remoteId = null;
        $count = 0;
        while (empty($remoteId) && $count < 4){

            if($count > 0){
                $naziv = $naziv."-".strval(rand(10,10000));
                $post["robe"][0]["naziv"] = $naziv;
                $katbroj = $katbroj."-".strval(rand(10,10000));
                $post["robe"][0]["katbroj"] = $katbroj;
            }

            $this->restManager->CURLOPT_POSTFIELDS = json_encode($post);

            $count++;
            $res = Array();
            $ret["error"] = true;
            $ret["data"] = $res;

            try{
                $res = $this->restManager->get($this->wandKomunikatorUrl."Wkom/ImportRobe");

                $error = false;

                if(empty($res)){
                    $error = true;
                }
                else{
                    if(isset($res["Status"]) && $res["Status"] == "ERROR"){
                        $error = true;
                    }
                }

                if($error){
                    if(!isset($res["StatusText"])){
                        $ret["message"] = "Greška prilikom unosa";
                    }
                    else{
                        $ret["message"] = $res["StatusText"];
                    }
                }
                else{
                    $ret["error"] = false;
                    $ret["data"] = $res;
                }
            }
            catch (\Exception $e){
                $ret["message"] = $e->getMessage();
            }

            if(isset($ret["data"]["Robe"][0]["RobaId"])){
                $remoteId = $ret["data"]["Robe"][0]["RobaId"];
            }
        }

        if(!isset($ret["data"]["Robe"][0]["RobaId"]) || empty($remoteId)){
            $ret["error"] = true;
            return $ret;
        }

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        /**
         * Update products
         */
        $q = "UPDATE product_entity SET remote_id = {$remoteId}, modified = NOW() WHERE id = {$product->getId()};";
        $this->databaseContext->executeNonQuery($q);

        return $ret;

        /*array:2 [
          "Status" => "OK"
          "Osobe" => array:1 [
            0 => array:5 [
              "Idx" => 1
              "OsobaId" => 22
              "PartnerId" => null
              "RobaId" => null
              "Status" => "OK"
            ]
          ]
        ]*/
    }
}