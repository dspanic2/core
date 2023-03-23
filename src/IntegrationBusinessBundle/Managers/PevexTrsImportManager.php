<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\RestManager;
use AppBundle\Models\InsertModel;
use AppBundle\Models\UpdateModel;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderItemEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use DOMDocument;
use IntegrationBusinessBundle\Models\ProductGroupModel;
use mysql_xdevapi\Exception;
use PaymentProvidersBusinessBundle\Managers\PaymentTransactionManager;
use PevexAdminBusinessBundle\Entity\OrderItemGroupEntity;
use PevexBusinessBundle\Constants\ScommerceConstants;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use SoapClient;
use SoapHeader;
use SoapVar;
use stdClass;
use Symfony\Component\Console\Helper\ProgressBar;

class PevexTrsImportManager extends DefaultIntegrationImportManager
{
    private $apiUrl;
    private $apiAuth;
    private $importLogDir;
    private $aggregatedImportLogDir;

    private $soapApiUrl;
    private $soapApiUsername;
    private $soapApiPassword;
    private $soapApiDatabase1;
    private $soapApiDatabase2;

    /** @var RestManager $trsRestManager */
    private $trsRestManager;
    /** @var RestManager $trsAttributeRestManager */
    private $trsAttributeRestManager;
    /** @var RestManager $trsOrderRestManager */
    private $trsOrderRestManager;

    /** @var AttributeSet $asSRoute */
    protected $asSRoute;
    /** @var AttributeSet $asProduct */
    protected $asProduct;
    /** @var AttributeSet $asProductProductGroupLink */
    protected $asProductProductGroupLink;
    /** @var AttributeSet $asProductGroup */
    protected $asProductGroup;
    /** @var AttributeSet $asWarehouse */
    protected $asWarehouse;
    /** @var AttributeSet $asProductWarehouseLink */
    protected $asProductWarehouseLink;
    /** @var AttributeSet $asTrsClassification */
    protected $asTrsClassification;
    /** @var AttributeSet $asSProductAttributeConfiguration */
    protected $asSProductAttributeConfiguration;
    /** @var AttributeSet $asSProductAttributeConfigurationOptions */
    protected $asSProductAttributeConfigurationOptions;
    /** @var AttributeSet $asSProductAttributesLink */
    protected $asSProductAttributesLink;
    /** @var AttributeSet $asProductAccountGroupPrice */
    protected $asProductAccountGroupPrice;
    /** @var AttributeSet $asLoyaltyCard */
    protected $asLoyaltyCard;

    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;
    protected $productGroups;

    /** @var PaymentTransactionManager $paymentTransactionManager */
    protected $paymentTransactionManager;

    /**
     * Nije uneseno
     */
    /**
     * 5 => array:12 [
        "sifra_artikla" => "256407"
        "id_atributa" => 27
        "naziv" => "Jedinica mjere grupna - pretvornik"
        "vrijednost" => "1"
        "web_filter" => "NE"
        "jmj" => "kom"
        "tip" => "C"
        "dflt" => "-"
        "format" => null
        "rbr" => 27
        "obavezna_vr_iz_popisa" => "NE"
        "popis_vrijednosti" => []
      ]
     * 7 => array:12 [
        "sifra_artikla" => "256407"
        "id_atributa" => 29
        "naziv" => "Transportna jedinica mjere - pretvornik"
        "vrijednost" => "1"
        "web_filter" => "NE"
        "jmj" => "kom"
        "tip" => "C"
        "dflt" => "-"
        "format" => null
        "rbr" => 29
        "obavezna_vr_iz_popisa" => "NE"
        "popis_vrijednosti" => []
      ]
     */

    const TRS_ATTR_JEDINICA_MJERE_GRUPNA = 26; // measure
    const TRS_ATTR_TEZINA_ARTIKLA_NETTO_U_KG = 107; // weight
    const TRS_ATTR_SPECIFIKACIJA_TEHNICKI_OPIS_ARTIKLA = 154; // trs_specification
    const TRS_ATTR_MARKETINSKI_OPIS_ARTIKLA = 156; // trs_marketing_description
    const TRS_ATTR_SIFRA_NARUCIVANJA_ARTIKLA = 80;
    const TRS_ATTR_ZAPALJIVOST_TEKUCINA = 3010;
    const TRS_ATTR_STETNA_TVAR = 3011;
    const TRS_ATTR_ZNAKOVI_OPASNOSTI = 3019;
    const TRS_ATTR_PEVEX_DODATNO_JAMSTVO = 5;

    const TRS_ATTR_TRANSPORTNA_JEDINICA_MJERE_PRETVORNIK = 29; // trs_transportna_jedinica_mjere_pretvornik

    /**
     * Sprema se u manufacturer_id na product NE KORISTI SE VISE
     */
    const TRS_ATTR_SIFRA_OSNOVNOG_DOBAVLJACA = 41;
    /**
     * Koristi se za rezanje naziva proizvoda
     * Sprema se na product
     */
    const TRS_ATTR_NAZIV_PODGRUPE = 45;

    /**
     * Pretvara se u brand
     */
    const TRS_ATTR_ROBNA_MARKA = 52;
    const TRS_ATTR_JAMSTVO = 152;
    const TRS_ATTR_JEDINICA_MJERE = 25;
    const TRS_ATTR_TRANSPORTNA_JEDINICA_MJERE = 28;
    const TRS_ATTR_TIP_MODEL_PROIZVODA = 62;
    const TRS_ATTR_NAZIV_PROIZVODACA = 64;
    /**
     * Sprema se u manufacturer_remote_name NE KORISTI SE VISE
     */
    const TRS_ATTR_NAZIV_OSNOVNOG_DOBAVLJACA = 40;
    const TRS_ATTR_UVOZNIK_ARTIKLA = 66;
    const TRS_ATTR_SIROVINSKI_SASTAV = 68;
    const TRS_ATTR_DRZAVA_PORIJEKLA_SIFRA = 75;
    const TRS_ATTR_DIMENZIJE_ARTIKLA_DUZINA_CM = 100;
    const TRS_ATTR_DIMENZIJE_ARTIKLA_SIRINA_CM = 101;
    const TRS_ATTR_DIMENZIJE_ARTIKLA_VISINA_CM = 102;
    const TRS_ATTR_BROJ_NA_PALETI = 105;
    const TRS_ATTR_DIMENZIJE_TRANSPORTNOG_PAKIRANJA_DUZINA_CM = 110;
    const TRS_ATTR_DIMENZIJE_TRANSPORTNOG_PAKIRANJA_SIRINA_CM = 111;
    const TRS_ATTR_DIMENZIJE_TRANSPORTNOG_PAKIRANJA_VISINA_CM = 112;
    const TRS_ATTR_BROJ_KOMADA_TRANSPORTNOG_PAKIRANJA_NA_PALETI = 115;
    const TRS_ATTR_TEZINA_TRANSPORTNOG_PAKIRANJA_U_KG = 117;

    public function initialize()
    {
        parent::initialize();

        $this->apiUrl = $_ENV["TRS_API_URL"];
        $this->apiAuth = "Authorization: Basic " . base64_encode($_ENV["TRS_API_USERNAME"] . ":" . $_ENV["TRS_API_PASSWORD"]);

        $this->soapApiUrl = $_ENV["TRS_SOAP_API_URL"];
        $this->soapApiUsername = $_ENV["TRS_SOAP_API_USERNAME"];
        $this->soapApiPassword = $_ENV["TRS_SOAP_API_PASSWORD"];
        $this->soapApiDatabase1 = $_ENV["TRS_SOAP_API_DATABASE_1"];
        $this->soapApiDatabase2 = $_ENV["TRS_SOAP_API_DATABASE_2"];

        $this->importLogDir = $this->getWebPath() . "Documents/import_log/pevex_trs";
        $this->aggregatedImportLogDir = $this->getWebPath() . "Documents/import_log";

        $this->setRemoteSource("pevex_trs");

        $this->trsRestManager = new RestManager();
        $this->trsAttributeRestManager = new RestManager();
        $this->trsOrderRestManager = new RestManager();

        $this->trsOrderRestManager->CURLOPT_POST = 1;
        $this->trsOrderRestManager->CURLOPT_CUSTOMREQUEST = "POST";

        $this->asSRoute = $this->entityManager->getAttributeSetByCode("s_route");
        $this->asProduct = $this->entityManager->getAttributeSetByCode("product");
        $this->asProductProductGroupLink = $this->entityManager->getAttributeSetByCode("product_product_group_link");
        $this->asProductGroup = $this->entityManager->getAttributeSetByCode("product_group");
        $this->asWarehouse = $this->entityManager->getAttributeSetByCode("warehouse");
        $this->asProductWarehouseLink = $this->entityManager->getAttributeSetByCode("product_warehouse_link");
        $this->asTrsClassification = $this->entityManager->getAttributeSetByCode("trs_classification");
        $this->asSProductAttributeConfiguration = $this->entityManager->getAttributeSetByCode("s_product_attribute_configuration");
        $this->asSProductAttributeConfigurationOptions = $this->entityManager->getAttributeSetByCode("s_product_attribute_configuration_options");
        $this->asSProductAttributesLink = $this->entityManager->getAttributeSetByCode("s_product_attributes_link");
        $this->asProductAccountGroupPrice = $this->entityManager->getAttributeSetByCode("product_account_group_price");
        $this->asLoyaltyCard = $this->entityManager->getAttributeSetByCode("loyalty_card");
    }

    /**
     * @param $entity
     * @return mixed
     */
    protected function getAttributeValueKey($entity)
    {
        $entity["attribute_value_key"] = md5($entity["product_id"] . //"\0" .
            $entity["s_product_attribute_configuration_id"] . //"\0" .
            $entity["configuration_option"]);
        return $entity;
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
     * @param RestManager $restManager
     * @param $endpoint
     * @param array $params
     * @param array $body
     * @return mixed
     * @throws \Exception
     */
    private function getTrsApiData(RestManager $restManager, $endpoint, $params = [], $body = [])
    {
        $url = $this->apiUrl . $endpoint . "?sif_sustava=SHIPSHAPE";
        if (!empty($params)) {
            $url .= "&" . http_build_query($params);
        }

        //$url = str_ireplace("+","%20",$url);

        $restManager->CURLOPT_HTTPHEADER = [
            $this->apiAuth
        ];

        if (!empty($body)) {
            $restManager->CURLOPT_POSTFIELDS = json_encode($body);
            $restManager->CURLOPT_HTTPHEADER[] = 'Content-Type:application/json';
        }

        //$restManager->CURLOPT_ENCODING = "Content-Type application/json";
        $restManager->CURLOPT_SSL_VERIFYHOST = 0;
        $restManager->CURLOPT_SSL_VERIFYPEER = 0;
        $restManager->CURLOPT_TIMEOUT = 300;

        /*$data = $restManager->get($url, false);
        dump($data);
        die;*/

        try {
            $data = $restManager->get($url, false);
        } catch (\Exception $e) {
            $this->saveImportLog($endpoint, $params, []);
            throw $e;
        }

        if (empty($data)) {
            $this->saveImportLog($endpoint, $params, []);
            throw new \Exception("{$endpoint} Response is empty");
        }

        $data = json_decode($data, true);

        $data["log_file"] = $this->saveImportLog($endpoint, $params, $data);

        if (isset($data["poruka"])) {
            throw new \Exception($data["poruka"]);
        }
        if (!isset($data["result"])) {
            throw new \Exception("{$endpoint} Result is empty");
        }

        return $data;
    }

    /**
     * @param $endpoint
     * @param $params
     * @param $database
     * @return mixed
     * @throws \SOAPFault
     */
    private function getTrsSoapApiData($endpoint, $params, $database)
    {
//        $result = $client->PrijaveNaNewsletter(new \SOAPVar($xml, XSD_ANYXML));
//        $result = $client->__soapCall('PrijaveNaNewsletter', array(new \SOAPVar($xml, XSD_ANYXML)));

        $this->soapApiUrl = $_ENV["WEB_PATH"] . "wsdl/llsoap.php?wsdl";

        $client = new SOAPClient($this->soapApiUrl);

        $ns = "http://schemas.xmlsoap.org/soap/envelope/";

        $client->__setSoapHeaders([
            new SOAPHeader($ns, "Username", $this->soapApiUsername),
            new SOAPHeader($ns, "Password", $this->soapApiPassword),
            new SOAPHeader($ns, "Database", $database)
        ]);

        try {
            $data = json_encode($client->$endpoint($params));
        } catch (\Exception $e) {
            $this->saveImportLog($endpoint, $params, []);
            throw $e;
        }

        $data = json_decode($data, true);

        if (empty($data)) {
            $this->saveImportLog($endpoint, $params, []);
            throw new \Exception("Response is empty");
        }

        if(isset($data["PrijavaNaNewsletter"])){
            $data = $data["PrijavaNaNewsletter"];
        }

        $data["log_file"] = $this->saveImportLog($endpoint, $params, $data);

        if ($data["Status"] != 1 /*|| $data["Poruka"] != "OK"*/) {
            throw new \Exception(sprintf("%s request error: %s", $endpoint, $data));
        }

        /*if (isset($data["Artikli"]["Artikal"])) {
            $data = $data["Artikli"]["Artikal"];
        } else if (isset($data["ZaliheArtikala"]["ZalihaArtikla"])) {
            $data = $data["ZaliheArtikala"]["ZalihaArtikla"];
        } else if (isset($data["CjenikArtikala"]["CjenikArtikla"])) {
            $data = $data["CjenikArtikala"]["CjenikArtikla"];
        } else */if (isset($data["SifrarnikPartnera"]["Partner"])) {
            $data["result"] = $data["SifrarnikPartnera"]["Partner"];
        }
        else if (isset($data["DohvatRNKlijenata"])) {
            $data["result"] = $data;
        } else if (isset($data["LoyaltyCjenici"]["LoyaltyCjenik"])) {
            $data["result"] = $data["LoyaltyCjenici"]["LoyaltyCjenik"];
        }

        return $data;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importClassification()
    {
        /**
         * TODO: optimizirati u bazi VARCHAR duljinu
         */

        $trsClassificationIds = [];
        $trsClassificationCodes = [];

        $trsClassificationSelectColumns = [
            "id",
            "id_klasifikacije",
            "id_nadredjenog",
            "naziv",
            "nivo",
            "program_id",
            "potprogram_id",
            "grupa_id",
            "podgrupa_id"
        ];

        $existingTrsClassifications = $this->getEntitiesArray($trsClassificationSelectColumns, "trs_classification_entity", ["id_klasifikacije"], "", "WHERE entity_state_id = 1");

        $insertArray = [
            // trs_classification_entity
        ];
        $updateArray = [
            // trs_classification_entity
        ];

        $data = $this->getTrsApiData($this->trsRestManager, "klasifikacija");
        $data = $data["result"];

        if(!empty($this->getConsoleOutput())){
            $progressBar = new ProgressBar($this->getConsoleOutput(), count($data));
        }

        foreach ($data as $d) {

            if(!empty($this->getConsoleOutput())) {
                $progressBar->advance();
            }

            if (!isset($existingTrsClassifications[$d["id_klasifikacije"]])) {
                $trsClassificationInsert = new InsertModel($this->asTrsClassification);
                $trsClassificationInsert->add("id_klasifikacije", $d["id_klasifikacije"])
                    ->add("id_nadredjenog", $d["id_nadredjenog"])
                    ->add("naziv", $d["naziv"])
                    ->add("nivo", $d["nivo"])
                    ->add("program_id", $d["program_id"])
                    ->add("potprogram_id", $d["potprogram_id"])
                    ->add("grupa_id", $d["grupa_id"])
                    ->add("podgrupa_id", $d["podgrupa_id"]);
                $insertArray["trs_classification_entity"][$d["id_klasifikacije"]] = $trsClassificationInsert->getArray();
                $trsClassificationCodes[] = $d["id_klasifikacije"];
            } else {
                $trsClassificationUpdate = new UpdateModel($existingTrsClassifications[$d["id_klasifikacije"]]);
                $trsClassificationUpdate->add("id_nadredjenog", $d["id_nadredjenog"])
                    ->add("naziv", $d["naziv"])
                    ->add("nivo", $d["nivo"])
                    ->add("program_id", $d["program_id"])
                    ->add("potprogram_id", $d["potprogram_id"])
                    ->add("grupa_id", $d["grupa_id"])
                    ->add("podgrupa_id", $d["podgrupa_id"]);
                if (!empty($trsClassificationUpdate->getArray())) {
                    $trsClassificationIds[] = $existingTrsClassifications[$d["id_klasifikacije"]]["id"];
                    $updateArray["trs_classification_entity"][$trsClassificationUpdate->getEntityId()] = $trsClassificationUpdate->getArray();
                }
            }
        }

        if(!empty($this->getConsoleOutput())) {
            $progressBar->finish();
        }

        unset($existingTrsClassifications);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $ret = [];

        $reselectArray["trs_classification_entity"] = $this->getEntitiesArray($trsClassificationSelectColumns, "trs_classification_entity", ["id_klasifikacije"], "", "WHERE entity_state_id = 1");

        $ret["trs_classification_ids"] = $this->resolveChangedProducts($trsClassificationIds, $trsClassificationCodes, $reselectArray["trs_classification_entity"]);
        unset($reselectArray);

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingTrsClassificationProductGroups(){

        $ret = Array();

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT t.id_klasifikacije, GROUP_CONCAT(tcpg.product_group_id) as product_group_ids FROM trs_classification_product_group_link_entity as tcpg LEFT JOIN trs_classification_entity as t ON tcpg.trs_classification_id = t.id WHERE t.entity_state_id = 1 and t.nivo in (3,4) GROUP BY t.id_klasifikacije;";
        $data = $this->databaseContext->getAll($q);

        if(!empty($data)){
            foreach ($data as $d){
                $ret[$d["id_klasifikacije"]] = explode(",",$d["product_group_ids"]);
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingTrsClassification(){

        $ret = Array();

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT t.id_klasifikacije, t.naziv, t.id_nadredjenog FROM trs_classification_entity as t WHERE t.entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        if(!empty($data)){
            foreach ($data as $d){
                $ret[$d["id_klasifikacije"]] = $d;
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getPevexProductAttributeTransition(){

        $ret = Array();

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM pevex_product_attribute_transition_entity WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        if(!empty($data)){
            foreach ($data as $d){
                $values = explode(",",$d["change_from"]);
                if(!empty($values)){
                    foreach ($values as $v){
                        $ret[$d["code"]][strtolower($v)] = $d["change_to"];
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * @param $remoteSource
     * @return array
     */
    protected function getSProductAttributesLinksByConfigurationAndOptionByProductCode($remoteSource,$productCodesList = Array())
    {

        $additionalWhere = "";
        if(!empty($productCodesList)){
            $additionalWhere = " AND p.code IN ('".implode("','",$productCodesList)."')";
        }

        $q = "SELECT 
                spal.id,
                spal.s_product_attribute_configuration_id,
                spal.configuration_option,
                spal.attribute_value_key,
                spal.attribute_value,
                p.code
            FROM s_product_attributes_link_entity spal
            JOIN s_product_attribute_configuration_entity spac ON spal.s_product_attribute_configuration_id = spac.id
            AND spac.remote_source = '{$remoteSource}'
            JOIN product_entity p ON spal.product_id = p.id 
            AND p.product_type_id IN (1,3,4,6) {$additionalWhere};";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["code"]][$d["s_product_attribute_configuration_id"]][(int)$d["configuration_option"]][$d["attribute_value_key"]] = [
                "id" => $d["id"],
                "attribute_value" => $d["attribute_value"]
            ];
        }

        return $ret;
    }

    /**
     * @param array $args
     * @return array
     * @throws \Exception
     */
    public function importProducts($args = ["product_codes" => [""]])
    {
        /**
         * Za dohvat po siframa proizvoda
         */
        $logFiles = Array();
        $productCodeFrom = null;
        $productCodeTo = null;
        $productCodesList = Array();
        if(isset($args["product_codes"]) && !empty($args["product_codes"])){
            sort($args["product_codes"]);
            $productCodeFrom = $args["product_codes"][0];
            $productCodeTo = end($args["product_codes"]);
            $productCodesList = array_flip($args["product_codes"]);
        }

        $productSelectColumns = [
            "id",
            "code",
            "name",
            "base_name",
            "category_name",
            "ean",
            "active",
            "tax_type_id",
            "measure",
            "weight",
            "trs_classification",
            "trs_specification",
            "trs_marketing_description",
            "central_status",
            "trs_name",
            "enable_cnc",
            "enable_delivery",
            "manufacturer_remote_id",
            "manufacturer_remote_name",
            "group_name",
            "subgroup_name",
            "brand_name",
            "producer_name",
            "transport_length",
            "transport_width",
            "transport_height",
            "transport_qty",
            "trs_sifra_narucivanja",
            "trs_stetna_tvar",
            "trs_zapaljivost",
            "trs_znakovi_opasnosti",
            "pevex_guarantee",
            "trs_measure",
            "is_on_promotion",
            "sirovinski_sastav",
            "trs_disable_discount"
        ];

        $centralStatuses = Array(
            "P" => 1,
            "A" => 1,
            "RPN" => 1,
            "WRPN" => 1,
            "T" => 0,
            "INO" => 1,
            "Z" => 1,
            "VP" => 0,
        );


        $existingProducts = $this->getEntitiesArray($productSelectColumns, "product_entity", ["code"], "", "WHERE product_type_id IN (1,3,4,6) AND remote_source = '{$this->getRemoteSource()}'");
        //$existingProductGroups = $this->getEntitiesArray(["id", "product_group_code"], "product_group_entity", ["product_group_code"], "", "WHERE entity_state_id = 1");
        //$existingProductProductGroupLinks = $this->getEntitiesArray(["a1.id", "a3.product_group_code", "a2.code", "a2.id AS product_id"], "product_product_group_link_entity", ["code", "product_group_code"], "JOIN product_entity a2 ON a1.product_id = a2.id JOIN product_group_entity a3 ON a1.product_group_id = a3.id", "WHERE a1.entity_state_id = 1 AND a2.entity_state_id = 1 AND a2.code IS NOT NULL AND a2.code != '' AND a2.remote_source = '{$this->getRemoteSource()}' AND a3.product_group_code IS NOT NULL AND a3.product_group_code != '' AND a3.remote_source = '{$this->getRemoteSource()}'");
        //$existingSRoutes = $this->getEntitiesArray(["request_url", "store_id", "destination_type"], "s_route_entity", ["store_id", "request_url"]);
        $existingSProductAttributeConfigurations = $this->getEntitiesArray(["id", "s_product_attribute_configuration_type_id", "is_active", "filter_key"], "s_product_attribute_configuration_entity", ["filter_key"], "", "WHERE filter_key IS NOT NULL AND filter_key != ''");
        $existingSProductAttributeConfigurationOptions = $this->getEntitiesArray(["id", "configuration_attribute_id", "MD5(configuration_value) AS md5_configuration_value"], "s_product_attribute_configuration_options_entity", ["configuration_attribute_id", "md5_configuration_value"], "", "WHERE entity_state_id = 1");
        //$existingSProductAttributeLinks = $this->getSProductAttributesLinksByConfigurationAndOption($this->getRemoteSource());

        $existingProductEans = $this->getEntitiesArray(["a2.code,a1.ean"], "product_eans_entity", ["code","ean"], "JOIN product_entity a2 ON a1.product_id = a2.id", "");

        $existingSProductAttributeLinksByProductCode = $this->getSProductAttributesLinksByConfigurationAndOptionByProductCode($this->getRemoteSource(),$args["product_codes"]);
        $existingTaxTypes = $this->getEntitiesArray(["id", "CAST(percent AS UNSIGNED) percent"], "tax_type_entity", ["percent"]);
        $existingTrsClassificationProductGroups = $this->getExistingTrsClassificationProductGroups();
        $existingTrsClassification = $this->getExistingTrsClassification();
        $attributeTransition = $this->getPevexProductAttributeTransition();

        $asProductEans = $this->entityManager->getAttributeSetByCode("product_eans");

        $usedProductCodesForAttributeDelete = [];
        $insertArray = [
            // product_entity
            // s_product_attribute_configuration_entity
        ];
        $insertArray2 = [
            // s_route_entity
            // product_product_group_link_entity
            // s_product_attribute_configuration_options_entity
        ];
        $insertArray3 = [
            // s_product_attributes_link_entity
        ];
        $updateArray = [
            // product_entity
            // s_product_attributes_link_entity
        ];
        $deleteArray = [
            // product_product_group_link_entity
        ];
        $insertProductGroupsArray = [
            // product_group_entity
        ];

        $productRegenerateNames = [];
        $productIds = [];
        $productChangedTrsClassificationIds = [];
        $productChangedTrsNameIds = [];
        $productCodes = [];
        $productAvailabilityIds = [];
        $productCentralStatusIds = [];
        $productBrandCodes = [];
        $productBrandIds = [];

        /**
         * Ovo nam ipak za TRS ne treba
         */
        /*foreach ($existingProducts as $existingProduct) {
            if ($existingProduct["active"]) {
                $productUpdate = new UpdateModel($existingProduct);
                $productUpdate->add("active", false, false)
                    ->add("date_synced", "NOW()", false);
                $updateArray["product_entity"][$productUpdate->getEntityId()] = $productUpdate->getArray();
                $productIds[] = $productUpdate->getEntityId();
            }
        }*/

//        foreach ($existingProductProductGroupLinks as $key => $existingProductProductGroupLink) {
//            $deleteArray["product_product_group_link_entity"][$key] = [
//                "id" => $existingProductProductGroupLink["id"],
//                "product_id" => $existingProductProductGroupLink["product_id"]
//            ];
//        }

        if(!empty($this->getConsoleOutput())){
            $progressBar = new ProgressBar($this->getConsoleOutput());
        }

        $sifrarnikArtikala = [];
        $sifrarnikArtikala["rownum_od"] = 1;
        $sifrarnikArtikala["rownum_do"] = 1000;
        if(!empty($productCodeFrom) && !empty($productCodeTo)){
            $sifrarnikArtikala["sifra_artikla_od"] = (string)$productCodeFrom;
            $sifrarnikArtikala["sifra_artikla_do"] = (string)$productCodeTo;
        }

        if (isset($args["changes_since_minutes"]) && !empty($args["changes_since_minutes"])) {
            $sifrarnikArtikala["vrijeme_izmjene_od"] = (new \DateTime("now -" . $args["changes_since_minutes"] . " minute"))
                ->format("d.m.Y. H:i");
        }

        $discountuedStatues = explode(",",$_ENV["DISCONTINUED_CENTRAL_STATUS"]);

        do {
            /**
             * Promijenjeno da se koristi lista
             */
            //$codeFrom = $codeTo = NULL;

            /**
             * Lista koja se koristi za dohvat atributa
             */
            $productCodesListForAttributes = Array();

            $data = $this->getTrsApiData($this->trsRestManager, "artikli", $sifrarnikArtikala);

            if(isset($data["log_file"])){
                $logFiles[] = $data["log_file"];
            }
            $data = $data["result"];

            if (!empty($data)) {

                //$progressBar->setMaxSteps($progressBar->getMaxSteps() + count($data));

                foreach ($data as $d) {

                    if(!empty($this->getConsoleOutput())) {
                        $progressBar->advance();
                    }

                    $code = (string)$d["sifra"];

                    /**
                     * Ako se radi dohvat po specificiranim siframa, preskoci one koji nisu navedeni
                     */
                    if(isset($productCodesList) && !empty($productCodesList) && !isset($productCodesList[$code])){
                        continue;
                    }

                    $ean = $d["osnovni_ean_kod"];

                    $eans = Array();
                    if(!empty($d["ean_kodovi"])){
                        foreach ($d["ean_kodovi"] as $ean_kod){
                            $eans[$ean_kod["ean_kod"]] = $ean_kod["ean_kod"];
                        }
                    }
                    if(!isset($eans[$ean])){
                        $eans[$ean] = $ean;
                    }

                    foreach ($eans as $e => $e2){
                        if(!isset($existingProductEans["{$code}_{$e}"])){
                            $productEansInsert = new InsertModel($asProductEans);

                            $productEansInsert
                                ->add("ean", $e)
                                ->addLookup("product_id", $code, "product_entity");

                            $insertArray2["product_eans_entity"]["{$code}_{$e}"] = $productEansInsert;
                            $productCodes[] = $code;
                        }
                    }

                    $trsName = $d["naziv"];
                    $name = trim($d["naziv"]);
                    $name = preg_replace('/[\s]+/', ' ', $name);

                    $trsMeasure = $d["sif_jed_mj"];



                    /**
                     * Promijenjeno da se koristi lista
                     */
                    /*if (!$codeFrom) {
                        $codeFrom = $code;
                    }*/
                    //$codeTo = $code;
                    $centralStatus = (string)$d["centralni_status"];

                    /**
                     * Popunjavanje liste za dohvat atributa
                     */
                    $productCodesListForAttributes[] = $code;

                    $active = 0;
                    if(!empty($centralStatus) && isset($centralStatuses[$centralStatus])){
                        $active = $centralStatuses[$centralStatus];
                    }

                    $taxTypeId = 3;
                    if(isset($existingTaxTypes[$d["pdv"]])){
                        $taxTypeId = $existingTaxTypes[$d["pdv"]]["id"];
                    }

                    $enableDelivery = 1;
                    $enableCnc = 1;

                    $trsClassification = ($d["program_id"] === 0 ? "x" : $d["program_id"]) . "-" .
                        ($d["potprogram_id"] === 0 ? "x" : $d["potprogram_id"]) . "-" .
                        ($d["grupa_id"] === 0 ? "x" : $d["grupa_id"]) . "-" .
                        ($d["podgrupa_id"] === 0 ? "x" : $d["podgrupa_id"]);

                    $groupName = null;
                    $subgroupName = null;
                    if(isset($existingTrsClassification[$trsClassification]) && isset($existingTrsClassification[$existingTrsClassification[$trsClassification]["id_nadredjenog"]])){
                        $subgroupName = $existingTrsClassification[$trsClassification]["naziv"];
                        $groupName = $existingTrsClassification[$existingTrsClassification[$trsClassification]["id_nadredjenog"]]["naziv"];
                    }

                    $manufacturerRemoteName = $d["dobavljac_naziv"];
                    $manufacturerRemoteId = $d["dobavljac_id"];

                    $baseName = $name;
                    if(!empty($groupName)){
                        $baseName = trim(str_ireplace($groupName,"",$baseName));
                    }
                    if(!empty($subgroupName)){
                        $baseName = trim(str_ireplace($subgroupName,"",$baseName));
                    }

                    $trsDisableDiscount = 0;
                    if(isset($d["grupiranja"])){
                        foreach ($d["grupiranja"] as $g){
                            if($g["sif_nac_gru"] == "RAB_0"){
                                if($g["vrijednost"] == "DA"){
                                    $trsDisableDiscount = 1;
                                }
                                break;
                            }
                        }
                    }

//                    $superGroup = $d["NazivPrograma"];
//                    $upperGroup = $d["NazivPotprograma"];
//                    $group = $d["NazivGrupe"];
//                    $subGroup = $d["NazivPodgrupe"];
//
//                    $superGroupCode = mb_strtolower($superGroup);
//                    $upperGroupCode = $superGroupCode . "_" . mb_strtolower($upperGroup);
//                    $groupCode = $upperGroupCode . "_" . mb_strtolower($group);
//                    $subGroupCode = $groupCode . "_" . mb_strtolower($subGroup);
//
//                    $productGroupModels = [
//                        0 => new ProductGroupModel($superGroup, $superGroupCode, NULL),
//                        1 => new ProductGroupModel($upperGroup, $upperGroupCode, $superGroupCode),
//                        2 => new ProductGroupModel($group, $groupCode, $upperGroupCode),
//                        3 => new ProductGroupModel($subGroup, $subGroupCode, $groupCode)
//                    ];

                    $productGroupModels = [];

                    $baseNameArray = [];
                    $nameArray = [];
                    $descriptionArray = [];
                    $metaKeywordsArray = [];
                    $showOnStoreArray = [];
                    $urlArray = [];

                    foreach ($this->getStores() as $storeId) {

                        $nameArray[$storeId] = $name;
                        $baseNameArray[$storeId] = $baseName;
                        $descriptionArray[$storeId] = "";
                        $metaKeywordsArray[$storeId] = "";
                        $showOnStoreArray[$storeId] = 1;

                        /**
                         * Prebaceno iza importa radi nuzne promjene imena
                         */
                        /*if (!isset($existingProducts[$code])) {

                            $i = 1;
                            $url = $key = $this->routeManager->prepareUrl($name);
                            while (isset($existingSRoutes[$storeId . "_" . $url]) || isset($insertArray2["s_route_entity"][$storeId . "_" . $url])) {
                                $url = $key . "-" . $i++;
                            }
                            $urlArray[$storeId] = $url;

                            $insertArray2["s_route_entity"][$storeId . "_" . $url] =
                                $this->getSRouteInsertEntity($url, "product", $storeId, $code); // remote_id
                        }*/
                    }

                    $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);
                    $baseNameJson = json_encode($baseNameArray, JSON_UNESCAPED_UNICODE);
                    $descriptionJson = json_encode($descriptionArray, JSON_UNESCAPED_UNICODE);
                    $metaKeywordsJson = json_encode($metaKeywordsArray, JSON_UNESCAPED_UNICODE);
                    $showOnStoreJson = json_encode($showOnStoreArray, JSON_UNESCAPED_UNICODE);
                    //$urlJson = json_encode($urlArray, JSON_UNESCAPED_UNICODE);

                    if (!isset($existingProducts[$code])) {

                        if(in_array($centralStatus,Array("RPN","WRPN"))){
                            $enableCnc = 0;
                        }

                        $productInsert = new InsertModel($this->asProduct);

                        $productInsert->add("date_synced", "NOW()")
                            ->add("remote_source", $this->getRemoteSource())
                            ->add("name", $nameJson)
                            ->add("base_name", $baseNameJson)
                            ->add("ean", $ean)
                            ->add("code", $code)
                            ->add("meta_title", $nameJson)
                            ->add("meta_description", $nameJson)
                            ->add("description", $descriptionJson)
                            ->add("meta_keywords", $metaKeywordsJson)
                            ->add("show_on_store", $showOnStoreJson)
                            ->add("active", $active)
                            ->add("url", null)
                            ->add("qty", 0)
                            ->add("qty_delivery", 0)
                            ->add("qty_cnc", 0)
                            ->add("current_reserved_qty_cnc", 0)
                            ->add("current_reserved_qty_delivery", 0)
                            ->add("current_reserved_qty", 0)
                            ->add("fixed_qty", 0)
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
                            ->add("content_changed", true)
                            ->add("measure", NULL)
                            ->add("weight", NULL)
                            ->add("brand_name", NULL)
                            ->add("manufacturer_remote_id", $manufacturerRemoteId)
                            ->add("manufacturer_remote_name", $manufacturerRemoteName)
                            ->add("producer_name", NULL)
                            ->add("ready_for_webshop", false)
                            ->add("trs_classification", $trsClassification)
                            ->add("trs_specification", NULL)
                            ->add("trs_marketing_description", NULL)
                            ->add("central_status", $centralStatus)
                            ->add("trs_name", $trsName)
                            ->add("enable_delivery", $enableDelivery)
                            ->add("enable_cnc", $enableCnc)
                            ->add("group_name", $groupName)
                            ->add("subgroup_name", $subgroupName)
                            ->add("transport_length", 0)
                            ->add("transport_width", 0)
                            ->add("transport_height", 0)
                            ->add("transport_qty", 1)
                            ->add("trs_sifra_narucivanja", NULL)
                            ->add("trs_zapaljivost", NULL)
                            ->add("trs_stetna_tvar", NULL)
                            ->add("trs_znakovi_opasnosti", NULL)
                            ->add("pevex_guarantee", NULL)
                            ->add("trs_measure", $trsMeasure)
                            ->add("is_on_promotion", 0)
                            ->add("trs_disable_discount", $trsDisableDiscount)
                            ->add("sirovinski_sastav", NULL);


                        $insertArray["product_entity"][$code] = $productInsert->getArray();
                        $productCodes[] = $code;

                        /**
                         * Add new products to product groups
                         */
                        if(isset($existingTrsClassificationProductGroups[$trsClassification])){

                            if(empty($this->productGroupManager)){
                                $this->productGroupManager = $this->container->get("product_group_manager");
                            }

                            foreach ($existingTrsClassificationProductGroups[$trsClassification] as $productGroupId){

                                $productProductGroupLinkKey = $code . "_" . $productGroupId;
                                $productProductGroupLinkInsert = new InsertModel($this->asProductProductGroupLink);
                                $productProductGroupLinkInsert->addLookup("product_id", $code, "product_entity"); // remote_id
                                $productProductGroupLinkInsert->add("product_group_id", $productGroupId);
                                $insertArray2["product_product_group_link_entity"][$productProductGroupLinkKey] = $productProductGroupLinkInsert;

                                if(!isset($this->productGroups[$productGroupId])){
                                    /** @var ProductGroupEntity $productGroup */
                                    $this->productGroups[$productGroupId] = $this->productGroupManager->getProductGroupById($productGroupId);
                                }

                                if(!$this->productGroups[$productGroupId]->getEnableDelivery() && $enableDelivery){
                                    $insertArray["product_entity"][$code]["enable_delivery"] = 0;
                                }
                                if(!$this->productGroups[$productGroupId]->getEnableCnc() && $enableCnc){
                                    $insertArray["product_entity"][$code]["enable_cnc"] = 0;
                                }
                                if(!empty($this->productGroups[$productGroupId]->getProductNamePrefix()) && !isset($productRegenerateNames[$code])){
                                    $productRegenerateNames[$code] = Array("id" => $productGroupId, "prefix" => $this->productGroups[$productGroupId]->getProductNamePrefix());
                                }
                                elseif (!isset($productRegenerateNames[$code])){
                                    $productRegenerateNames[$code] = Array("id" => $productGroupId, "prefix" => "");
                                }

                                $parentProductGroupsIds = $this->productGroups[$productGroupId]->getParentProductGroupIds();

                                if(!empty($parentProductGroupsIds)){
                                    foreach ($parentProductGroupsIds as $parentProductGroupId){

                                        $productProductGroupLinkKey = $code . "_" . $parentProductGroupId;

                                        if(!isset($insertArray2["product_product_group_link_entity"][$productProductGroupLinkKey])){
                                            $productProductGroupLinkInsert = new InsertModel($this->asProductProductGroupLink);
                                            $productProductGroupLinkInsert->addLookup("product_id", $code, "product_entity"); // remote_id
                                            $productProductGroupLinkInsert->add("product_group_id", $parentProductGroupId);
                                            $insertArray2["product_product_group_link_entity"][$productProductGroupLinkKey] = $productProductGroupLinkInsert;
                                        }
                                    }
                                }
                            }
                        }
                        else{
                            $productRegenerateNames[$code] = Array("id" => null, "prefix" => "");
                        }

                    } else {

                        $productUpdate = new UpdateModel($existingProducts[$code]);

                        unset($updateArray["product_entity"][$productUpdate->getEntityId()]);

                        $k = array_search($productUpdate->getEntityId(), $productIds);
                        if ($k !== false) {
                            unset($productIds[$k]);
                        }

                        /**
                         * Uklonjeno, ne zelimo da se updatea
                         */
                        /*if ($nameArray != json_decode($existingProducts[$code]["name"], true)) {
                            $productUpdate->add("name", $nameJson, false)
                                ->add("meta_title", $nameJson, false)
                                ->add("meta_description", $nameJson, false);
                        }*/

                        /**
                         * Privremeno za regeneriranje
                         */
                        /*if ($baseNameArray != json_decode($existingProducts[$code]["base_name"], true)) {
                            $productUpdate->add("base_name", $baseNameJson, false);
                        }*/

                        if ($trsClassification != $existingProducts[$code]["trs_classification"]) {
                            $productChangedTrsClassificationIds[] = $productUpdate->getEntityId();
                        }

                        if ($active != $existingProducts[$code]["active"]) {
                            $productAvailabilityIds[] = $productUpdate->getEntityId();
                        }

                        if ($name != $existingProducts[$code]["trs_name"]) {
                            $productChangedTrsNameIds[] = $productUpdate->getEntityId();
                        }

                        if ($centralStatus != $existingProducts[$code]["central_status"]) {
                            if(in_array($centralStatus,$discountuedStatues) || in_array($existingProducts[$code]["central_status"],$discountuedStatues)){
                                $productCentralStatusIds[] = $productUpdate->getEntityId();
                            }
                        }

                        $productUpdate
                            ->add("ean", $ean)
                            ->add("active", $active)
                            ->add("trs_name", $name)
                            //->add("tax_type_id", $taxTypeId)
                            ->add("trs_classification", $trsClassification)
                            ->add("group_name", $groupName)
                            ->add("subgroup_name", $subgroupName)
                            ->add("trs_measure", $trsMeasure)
                            ->add("central_status", $centralStatus)
                            ->add("trs_disable_discount", $trsDisableDiscount)
                            ->add("manufacturer_remote_id", $manufacturerRemoteId)
                            ->add("manufacturer_remote_name", $manufacturerRemoteName);

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


                    /**
                     * NE KORISTI SE
                     */
                    /** @var ProductGroupModel $productGroupModel */
                    /*foreach ($productGroupModels as $level => $productGroupModel) {

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
                    }*/
                }
            }

            $sifrarnikArtikala["rownum_od"] = $sifrarnikArtikala["rownum_do"] + 1;
            $sifrarnikArtikala["rownum_do"] = $sifrarnikArtikala["rownum_od"] + 999;

            if (!$this->getFast() && !empty($productCodesListForAttributes)) {

                $productCodesListForAttributesTmp = array_chunk($productCodesListForAttributes, 500);

                foreach ($productCodesListForAttributesTmp as $productCodesListForAttributes) {

                    $atributiArtikala = [];
                    //$atributiArtikala["sifra_artikla_od"] = $codeFrom;
                    //$atributiArtikala["sifra_artikla_do"] = $codeTo;
                    $atributiArtikala["sifre_artikala"] = implode(",", $productCodesListForAttributes);

                    /*$atributiArtikala["vrijeme_izmjene_od"] = (new \DateTime("now -1 day", new \DateTimeZone("+0000")))
                        ->format("d.m.Y. H:i");*/

                    if (isset($args["changes_since_minutes"]) && !empty($args["changes_since_minutes"])) {
                        $atributiArtikala["vrijeme_izmjene_od"] = (new \DateTime("now -" . $args["changes_since_minutes"] . " minute"))
                            ->format("d.m.Y. H:i");
                    }

                    try {
                        $data2 = $this->getTrsApiData($this->trsAttributeRestManager, "atributi_artikala", $atributiArtikala);

                        if(isset($data2["log_file"])){
                            $logFiles[] = $data2["log_file"];
                        }
                        $data2 = $data2["result"];
                    } catch (\Exception $e) {
                        continue;
                    }

                    if (!empty($data2)) {

                        foreach ($data2 as $d2) {

                            $code = $d2["sifra_artikla"];

                            if (isset($existingSProductAttributeLinksByProductCode[$d2["sifra_artikla"]]) && !isset($usedProductCodesForAttributeDelete[$d2["sifra_artikla"]])) {
                                $usedProductCodesForAttributeDelete[$d2["sifra_artikla"]] = true;
                                foreach ($existingSProductAttributeLinksByProductCode[$d2["sifra_artikla"]] as $attributes) {
                                    foreach ($attributes as $attributeValues) {
                                        foreach ($attributeValues as $attributeValueKey => $attributeLink) {
                                            $deleteArray["s_product_attributes_link_entity"][$attributeValueKey] = [
                                                "id" => $attributeLink["id"]
                                            ];
                                        }
                                    }
                                }
                            }

                            /**
                             * Ako se radi dohvat po specificiranim siframa, preskoci one koji nisu navedeni
                             */
                            if (isset($productCodesList) && !empty($productCodesList) && !isset($productCodesList[$code])) {
                                continue;
                            }

                            /**
                             * Preskoči parsiranje atributa za proizvode koji nisu insertani ili pripremljeni u arrayu za insert
                             */
                            if (!isset($existingProducts[$code]) && !isset($insertArray["product_entity"][$code])) {
                                continue;
                            }

                            $d2["vrijednost"] = trim($d2["vrijednost"]);
                            if ($d2["vrijednost"] == "-") {
                                continue;
                            }
                            if (mb_strlen($d2["vrijednost"]) > 255 && $d2["id_atributa"] != self::TRS_ATTR_SIROVINSKI_SASTAV && $d2["id_atributa"] != self::TRS_ATTR_SPECIFIKACIJA_TEHNICKI_OPIS_ARTIKLA) {
                                continue;
                            }
                            if (mb_strlen($d2["vrijednost"]) > 1024 && $d2["id_atributa"] == self::TRS_ATTR_SPECIFIKACIJA_TEHNICKI_OPIS_ARTIKLA) {
                                continue;
                            }

                            if (isset($attributeTransition[$d2["id_atributa"]][strtolower($d2["vrijednost"])])) {
                                $d2["vrijednost"] = $attributeTransition[$d2["id_atributa"]][strtolower($d2["vrijednost"])];
                            }
                            if ($d2["id_atributa"] == self::TRS_ATTR_SIROVINSKI_SASTAV && !empty($d2["vrijednost"])) {
                                $tmp = explode(";", $d2["vrijednost"]);
                                $tmp = array_map('trim', $tmp);
                                $d2["vrijednost"] = implode("<br/>", $tmp);
                                unset($tmp);
                            }

                            $attributeValue = $d2["vrijednost"];

                            $productAttribute = $configurationName = NULL;
                            $floatType = false;

                            switch ($d2["id_atributa"]) {
                                /**
                                 * product_entity
                                 */
                                case self::TRS_ATTR_JEDINICA_MJERE_GRUPNA:
                                    $productAttribute = "measure";
                                    break;
                                #case self::TRS_ATTR_JEDINICA_MJERE:
                                #    $productAttribute = "trs_measure";
                                #    break;
                                case self::TRS_ATTR_TEZINA_ARTIKLA_NETTO_U_KG:
                                    $productAttribute = "weight";
                                    $floatType = true;
                                    break;
                                case self::TRS_ATTR_SPECIFIKACIJA_TEHNICKI_OPIS_ARTIKLA:
                                    $productAttribute = "trs_specification";
                                    break;
                                case self::TRS_ATTR_MARKETINSKI_OPIS_ARTIKLA:
                                    $productAttribute = "trs_marketing_description";
                                    break;
                                /**
                                 * Prebaceno da se cita direktno sa producta
                                 */
                                /*case self::TRS_ATTR_SIFRA_OSNOVNOG_DOBAVLJACA:
                                    $productAttribute = "manufacturer_remote_id";
                                    break;
                                case self::TRS_ATTR_NAZIV_OSNOVNOG_DOBAVLJACA:
                                    $productAttribute = "manufacturer_remote_name";
                                    break;*/
                                case self::TRS_ATTR_DIMENZIJE_TRANSPORTNOG_PAKIRANJA_DUZINA_CM:
                                    $floatType = true;
                                    $productAttribute = "transport_length";
                                    break;
                                case self::TRS_ATTR_DIMENZIJE_TRANSPORTNOG_PAKIRANJA_SIRINA_CM:
                                    $floatType = true;
                                    $productAttribute = "transport_width";
                                    break;
                                case self::TRS_ATTR_DIMENZIJE_TRANSPORTNOG_PAKIRANJA_VISINA_CM:
                                    $floatType = true;
                                    $productAttribute = "transport_height";
                                    break;
                                case self::TRS_ATTR_TRANSPORTNA_JEDINICA_MJERE_PRETVORNIK:
                                    $floatType = true;
                                    $productAttribute = "transport_qty";
                                    break;
                                case self::TRS_ATTR_SIFRA_NARUCIVANJA_ARTIKLA:
                                    $productAttribute = "trs_sifra_narucivanja";
                                    break;
                                case self::TRS_ATTR_ZAPALJIVOST_TEKUCINA:
                                    $productAttribute = "trs_zapaljivost";
                                    break;
                                case self::TRS_ATTR_STETNA_TVAR:
                                    $productAttribute = "trs_stetna_tvar";
                                    break;
                                case self::TRS_ATTR_ZNAKOVI_OPASNOSTI:
                                    $productAttribute = "trs_znakovi_opasnosti";
                                    break;
                                case self::TRS_ATTR_PEVEX_DODATNO_JAMSTVO:
                                    $productAttribute = "pevex_guarantee";
                                    break;
                                case self::TRS_ATTR_SIROVINSKI_SASTAV:
                                    $productAttribute = "sirovinski_sastav";
                                    break;
                                /*case self::TRS_ATTR_BROJ_KOMADA_TRANSPORTNOG_PAKIRANJA_NA_PALETI:
                                    $floatType = true;
                                    $productAttribute = "transport_qty";
                                    break;*/

                                /**
                                 * Nepotrebno, rijeseno gore
                                 */
                                #case self::TRS_ATTR_NAZIV_PODGRUPE:
                                #    $productAttribute = "subgroup_name";
                                #    break;
                                case self::TRS_ATTR_NAZIV_PROIZVODACA:
                                    $productAttribute = "producer_name";
                                    break;

                                /**
                                 * s_product_attribute_configuration_entity
                                 */
                                case self::TRS_ATTR_TRANSPORTNA_JEDINICA_MJERE:
                                case self::TRS_ATTR_TIP_MODEL_PROIZVODA:
                                case self::TRS_ATTR_UVOZNIK_ARTIKLA:
                                case self::TRS_ATTR_DRZAVA_PORIJEKLA_SIFRA:
                                case self::TRS_ATTR_BROJ_NA_PALETI:
                                case self::TRS_ATTR_ROBNA_MARKA:
                                case self::TRS_ATTR_JAMSTVO:
                                    $configurationName = trim($d2["naziv"]);
                                    if ($d2["id_atributa"] == self::TRS_ATTR_ROBNA_MARKA) {
                                        $productAttribute = "brand_name";
                                    }
                                    break;
                                /**
                                 * s_product_attribute_configuration_entity - floats
                                 */
                                case self::TRS_ATTR_TEZINA_TRANSPORTNOG_PAKIRANJA_U_KG:
                                case self::TRS_ATTR_DIMENZIJE_ARTIKLA_DUZINA_CM:
                                case self::TRS_ATTR_DIMENZIJE_ARTIKLA_SIRINA_CM:
                                case self::TRS_ATTR_DIMENZIJE_ARTIKLA_VISINA_CM:
                                    $floatType = true;
                                    $configurationName = trim($d2["naziv"]);
                                    break;
                            }

                            if ($floatType) {
                                $attributeValue = (float)str_replace(",", ".", $attributeValue);
                            }

                            if (!empty($productAttribute)) {

                                /*if($productAttribute == "weight"){
                                    dump($existingProducts[$code]["weight"]);
                                    dump($attributeValue);
                                    die;
                                }*/

                                if (!isset($existingProducts[$code])) {
                                    $insertArray["product_entity"][$code][$productAttribute] = $attributeValue;
                                } else {
                                    $productId = $existingProducts[$code]["id"];
                                    if (isset($updateArray["product_entity"][$productId])) {
                                        $productUpdate = new UpdateModel($existingProducts[$code]);
                                        $productUpdate->setArray($updateArray["product_entity"][$productId])
                                            ->add($productAttribute, $attributeValue);
                                        $updateArray["product_entity"][$productId] = $productUpdate->getArray();
                                    } else {
                                        $productUpdate = new UpdateModel($existingProducts[$code]);
                                        $productUpdate->add($productAttribute, $attributeValue);
                                        $updateArray["product_entity"][$productId] = $productUpdate->getArray();
                                    }
                                }

                            }
                            if (!empty($configurationName)) {

                                $filterKey = $this->helperManager->nameToFilename($configurationName);
                                $filterKey = str_ireplace("-","_",$filterKey);
                                $filterKey = preg_replace("/_+/", "_", $filterKey);
                                if ($filterKey == "robna_marka") {
                                    $filterKey = "brand";
                                }
                                if (!isset($existingSProductAttributeConfigurations[$filterKey])) {
                                    /**
                                     * Konfiguracija ne postoji, insertaj i to je to do idućeg importa
                                     */
                                    if (!isset($insertArray["s_product_attribute_configuration_entity"][$filterKey])) {
                                        $sProductAttributeConfigurationInsert = new InsertModel($this->asSProductAttributeConfiguration);
                                        $sProductAttributeConfigurationInsert->add("name", $configurationName)
                                            ->add("s_product_attribute_configuration_type_id", 3)
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
                                     * Na prvom importu insertat će se tip konfiguracije vrste text
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
                                            $optionKey = $configurationId . "_" . md5($attributeValue);
                                            if (!isset($existingSProductAttributeConfigurationOptions[$optionKey])) {
                                                /**
                                                 * Opcija ne postoji, linkovi ne postoje
                                                 */
                                                if (!isset($insertArray2["s_product_attribute_configuration_options_entity"][$optionKey])) {
                                                    $sProductAttributeConfigurationOptionsInsert = new InsertModel($this->asSProductAttributeConfigurationOptions);
                                                    $sProductAttributeConfigurationOptionsInsert->add("configuration_value", $attributeValue)
                                                        ->add("remote_source", $this->getRemoteSource())
                                                        ->add("configuration_attribute_id", $configurationId);
                                                    $insertArray2["s_product_attribute_configuration_options_entity"][$optionKey] = $sProductAttributeConfigurationOptionsInsert->getArray();
                                                }

                                                $linkKey = md5($code . $filterKey . $attributeValue);
                                                if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                                    $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                                    if (isset($existingProducts[$code])) {
                                                        $sProductAttributeLinksInsert->add("product_id", $existingProducts[$code]["id"]);
                                                    } else {
                                                        $sProductAttributeLinksInsert->addLookup("product_id", $code, "product_entity");
                                                    }
                                                    $sProductAttributeLinksInsert->add("s_product_attribute_configuration_id", $configurationId)
                                                        ->add("attribute_value", $attributeValue)
                                                        ->addLookup("configuration_option", $optionKey, "s_product_attribute_configuration_options_entity")
                                                        ->addFunction([$this, "getAttributeValueKey"]);
                                                    $insertArray3["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert;
                                                }
                                            } else {
                                                /**
                                                 * Opcija postoji
                                                 */
                                                $optionId = $existingSProductAttributeConfigurationOptions[$optionKey]["id"];
                                                if (!isset($existingSProductAttributeLinksByProductCode[$code][$configurationId][$optionId])) {

                                                    /**
                                                     * Linkovi ne postoje, dodaj sve
                                                     */
                                                    $linkKey = md5($code . $filterKey . $attributeValue);
                                                    if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                                        $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                                        if (isset($existingProducts[$code])) {
                                                            if ($filterKey == "brand") {
                                                                $productBrandIds[] = $existingProducts[$code]["id"];
                                                            }
                                                            $sProductAttributeLinksInsert->add("product_id", $existingProducts[$code]["id"]);
                                                        } else {
                                                            if ($filterKey == "brand") {
                                                                $productBrandCodes[] = $code;
                                                            }
                                                            $sProductAttributeLinksInsert->addLookup("product_id", $code, "product_entity");
                                                        }
                                                        $sProductAttributeLinksInsert->add("s_product_attribute_configuration_id", $configurationId)
                                                            ->add("attribute_value", $attributeValue)
                                                            ->add("configuration_option", $optionId)
                                                            ->addFunction([$this, "getAttributeValueKey"]);
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

                                                        if (!isset($existingSProductAttributeLinksByProductCode[$code][$configurationId][$optionId][$attributeValueKey])) {
                                                            /**
                                                             * Link ne postoji
                                                             */
                                                            if ($filterKey == "brand") {
                                                                $productBrandIds[] = $existingProducts[$code]["id"];
                                                            }
                                                            $linkKey = md5($code . $filterKey . $attributeValue);
                                                            if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                                                $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                                                $sProductAttributeLinksInsert->add("product_id", $existingProducts[$code]["id"])
                                                                    ->add("s_product_attribute_configuration_id", $configurationId)
                                                                    ->add("attribute_value", $attributeValue)
                                                                    ->add("configuration_option", $optionId)
                                                                    ->add("attribute_value_key", $attributeValueKey);
                                                                $insertArray3["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert->getArray();
                                                            }
                                                        } else {
                                                            /**
                                                             * Link postoji
                                                             */
                                                            unset($deleteArray["s_product_attributes_link_entity"][$attributeValueKey]);
                                                            $sProductAttributeLink = $existingSProductAttributeLinksByProductCode[$code][$configurationId][$optionId][$attributeValueKey];
                                                            $sProductAttributeLinksUpdate = new UpdateModel($sProductAttributeLink);
                                                            $sProductAttributeLinksUpdate->add("attribute_value", $attributeValue);
                                                            if (!empty($sProductAttributeLinksUpdate->getArray())) {
                                                                /**
                                                                 * Promijenio se attribute value (na ovoj vrsti konfiguracije ne bi se trebalo dogoditi)
                                                                 */
                                                                if ($filterKey == "brand") {
                                                                    $productBrandIds[] = $existingProducts[$code]["id"];
                                                                }
                                                                $updateArray["s_product_attributes_link_entity"][$sProductAttributeLink["id"]] = $sProductAttributeLinksUpdate->getArray();
                                                            }
                                                        }
                                                    } else {

                                                        /**
                                                         * Proizvod ne postoji, linkovi ne postoje
                                                         */
                                                        if ($filterKey == "brand") {
                                                            $productBrandCodes[] = $code;
                                                        }
                                                        $linkKey = md5($code . $filterKey . $attributeValue);
                                                        if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                                            $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                                            $sProductAttributeLinksInsert->addLookup("product_id", $code, "product_entity")
                                                                ->add("s_product_attribute_configuration_id", $configurationId)
                                                                ->add("attribute_value", $attributeValue)
                                                                ->add("configuration_option", $optionId)
                                                                ->addFunction([$this, "getAttributeValueKey"]);
                                                            $insertArray3["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert;
                                                        }
                                                    }
                                                }
                                            }
                                        } else {

                                            $optionId = 0;
                                            if (!isset($existingSProductAttributeLinksByProductCode[$code][$configurationId][$optionId])) {
                                                /**
                                                 * Linkovi ne postoje, dodaj sve
                                                 */
                                                $linkKey = md5($code . $filterKey . $attributeValue);
                                                if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                                    $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                                    if (isset($existingProducts[$code])) {
                                                        if ($filterKey == "brand") {
                                                            $productBrandIds[] = $existingProducts[$code]["id"];
                                                        }
                                                        $sProductAttributeLinksInsert->add("product_id", $existingProducts[$code]["id"]);
                                                    } else {
                                                        if ($filterKey == "brand") {
                                                            $productBrandCodes[] = $code;
                                                        }
                                                        $sProductAttributeLinksInsert->addLookup("product_id", $code, "product_entity");
                                                    }
                                                    $sProductAttributeLinksInsert->add("s_product_attribute_configuration_id", $configurationId)
                                                        ->add("attribute_value", $attributeValue)
                                                        ->add("configuration_option", NULL)
                                                        ->addFunction([$this, "getAttributeValueKey"]);
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
                                                    $attributeValueKey = md5($existingProducts[$code]["id"] . $configurationId);
                                                    if (!isset($existingSProductAttributeLinksByProductCode[$code][$configurationId][$optionId][$attributeValueKey])) {
                                                        /**
                                                         * Link ne postoji
                                                         */
                                                        if ($filterKey == "brand") {
                                                            $productBrandIds[] = $existingProducts[$code]["id"];
                                                        }
                                                        $linkKey = md5($code . $filterKey . $attributeValue);
                                                        if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                                            $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                                            $sProductAttributeLinksInsert->add("product_id", $existingProducts[$code]["id"])
                                                                ->add("s_product_attribute_configuration_id", $configurationId)
                                                                ->add("attribute_value", $attributeValue)
                                                                ->add("configuration_option", NULL)
                                                                ->add("attribute_value_key", $attributeValueKey);
                                                            $insertArray3["s_product_attributes_link_entity"][$linkKey] = $sProductAttributeLinksInsert->getArray();
                                                        }
                                                    } else {
                                                        /**
                                                         * Link postoji
                                                         */
                                                        unset($deleteArray["s_product_attributes_link_entity"][$attributeValueKey]);
                                                        $sProductAttributeLink = $existingSProductAttributeLinksByProductCode[$code][$configurationId][$optionId][$attributeValueKey];
                                                        $sProductAttributeLinksUpdate = new UpdateModel($sProductAttributeLink);
                                                        $sProductAttributeLinksUpdate->add("attribute_value", $attributeValue);
                                                        if (!empty($sProductAttributeLinksUpdate->getArray())) {
                                                            /**
                                                             * Promijenio se attribute value
                                                             */
                                                            if ($filterKey == "brand") {
                                                                $productBrandIds[] = $existingProducts[$code]["id"];
                                                            }
                                                            $updateArray["s_product_attributes_link_entity"][$sProductAttributeLink["id"]] = $sProductAttributeLinksUpdate->getArray();
                                                        }
                                                    }
                                                } else {
                                                    /**
                                                     * Proizvod ne postoji, linkovi ne postoje
                                                     */
                                                    if ($filterKey == "brand") {
                                                        $productBrandCodes[] = $code;
                                                    }
                                                    $linkKey = md5($code . $filterKey . $attributeValue);
                                                    if (!isset($insertArray3["s_product_attributes_link_entity"][$linkKey])) {
                                                        $sProductAttributeLinksInsert = new InsertModel($this->asSProductAttributesLink);
                                                        $sProductAttributeLinksInsert->addLookup("product_id", $code, "product_entity")
                                                            ->add("s_product_attribute_configuration_id", $configurationId)
                                                            ->add("attribute_value", $attributeValue)
                                                            ->add("configuration_option", NULL)
                                                            ->addFunction([$this, "getAttributeValueKey"]);
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
            }

            $this->databaseContext->reconnectToDatabase();



        } while (!empty($data) && count($data) >= 1000);

        if(!empty($this->getConsoleOutput())) {
            $progressBar->finish();
            echo "\n";
        }

        unset($existingProducts);
        //unset($existingProductGroups);
        //unset($existingProductProductGroupLinks);
        //unset($existingSRoutes);
        unset($existingSProductAttributeConfigurations);
        unset($existingSProductAttributeConfigurationOptions);
        unset($existingSProductAttributeLinksByProductCode);

        $reselectArray = [];

        /**
         * Custom product group insert order implementation
         * Ne koristi se
         */
        /*if (!empty($insertProductGroupsArray)) {
            ksort($insertProductGroupsArray);
            foreach ($insertProductGroupsArray as $level => $productGroups) {
                $productGroups = $this->resolveImportArray(["product_group_entity" => $productGroups], $reselectArray);
                $this->executeInsertQuery($productGroups);
                $reselectArray["product_group_entity"] = $this->getEntitiesArray(["id", "product_group_code"], "product_group_entity", ["product_group_code"], "", "WHERE entity_state_id = 1");
            }
            unset($insertProductGroupsArray);
        }*/

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $reselectArray["product_entity"] = $this->getEntitiesArray($productSelectColumns, "product_entity", ["code"], "", "WHERE product_type_id IN (1,3,4,6) AND remote_source = '{$this->getRemoteSource()}'");
        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);

        $reselectArray["s_product_attribute_configuration_options_entity"] = $this->getEntitiesArray(["id", "configuration_attribute_id", "MD5(configuration_value) AS md5_configuration_value"], "s_product_attribute_configuration_options_entity", ["configuration_attribute_id", "md5_configuration_value"], "", "WHERE entity_state_id = 1");
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

        $ret["log_files"] = $logFiles;
        $ret["product_new_ids"] = $this->resolveChangedProducts(Array(), $productCodes, $reselectArray["product_entity"]);
        $ret["product_ids"] = $this->resolveChangedProducts($productIds, $productCodes, $reselectArray["product_entity"]);
        $ret["product_changed_trs_classification_ids"] = $productChangedTrsClassificationIds;
        $ret["product_changed_trs_name_ids"] = $productChangedTrsNameIds;
        $ret["product_availability_ids"] = $this->resolveChangedProducts($productAvailabilityIds, $productCodes, $reselectArray["product_entity"]);
        $ret["product_central_status_ids"] = $this->resolveChangedProducts($productCentralStatusIds, $productCodes, $reselectArray["product_entity"]);
        $ret["product_brand_ids"] = $this->resolveChangedProducts($productBrandIds, $productBrandCodes, $reselectArray["product_entity"]);


        /**
         * Prepare regenerate names array
         */
        $ret["product_regenerate_names"] = [];
        foreach ($productRegenerateNames as $code => $productGroupData){
            if(isset($reselectArray["product_entity"][$code])){
                $ret["product_regenerate_names"][$productGroupData["id"]]["prefix"] = $productGroupData["prefix"];
                $ret["product_regenerate_names"][$productGroupData["id"]]["product_ids"][] = $reselectArray["product_entity"][$code]["id"];
            }
        }

        unset($reselectArray);

        if(!empty($this->getConsoleOutput())) {
            echo "Importing products complete\n";
        }

        return $ret;
    }

    /**
     * @param \string[][] $args
     * @return array
     * @throws \Exception
     */
    public function importWarehouseStock($args = ["product_codes" => [""]])
    {
        $logFiles = Array();

        $additionalWarehouseWhere = "WHERE entity_state_id = 1 AND is_active = 1 AND code is not null and LENGTH(code) = 4 AND ps = {$_ENV["TRS_SIFRA_PS"]} ";
        if (isset($args["warehouse_code"]) && !empty($args["warehouse_code"])) {
            $additionalWarehouseWhere .= " AND code IN ('{$args["warehouse_code"]}') ";
        }

        $additionalProductWhere = "";
        if(!empty($args["product_codes"]) && count($args["product_codes"]) > 0){
            $additionalProductWhere = " AND code IN ('".implode("','",$args["product_codes"])."') ";
        }

        $existingProducts = $this->getEntitiesArray(["id", "code", "transport_qty"], "product_entity", ["code"], "", "WHERE product_type_id IN (1,3,4,6) AND remote_source = '{$this->getRemoteSource()}' {$additionalProductWhere}");
        $existingWarehouses = $this->getEntitiesArray(["id", "code", "is_auxiliary_warehouse"], "warehouse_entity", ["code"], "", $additionalWarehouseWhere);

        /**
         * Get product which are disabled in some warehouse
         */
        if(empty($this->pevexProductHelperManager)){
            $this->pevexProductHelperManager = $this->container->get("pevex_product_helper_manager");
        }
        $disabledProductWarehouse = $this->pevexProductHelperManager->getWarehouseDisableProducts();
        if(!empty($disabledProductWarehouse)){
            $disabledProductWarehouse = array_flip($disabledProductWarehouse);
        }

        $additionalProductWarehouseLinkWhere = "";
        if(!empty($args["product_codes"]) && count($args["product_codes"]) > 0){
            $additionalProductWarehouseLinkWhere = " AND a2.code IN ('".implode("','",$args["product_codes"])."') ";
        }

        $existingProductWarehouseLinks = $this->getEntitiesArray(["a1.id", "a1.product_id", "a1.warehouse_id", "a1.qty", "a1.qty_supplier"], "product_warehouse_link_entity", ["product_id", "warehouse_id"], "JOIN product_entity a2 ON a1.product_id = a2.id", "WHERE a1.entity_state_id = 1 AND a2.remote_source = '{$this->getRemoteSource()}' {$additionalProductWarehouseLinkWhere}");

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
        }
        $maxTransportQty = intval($this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("max_transport_qty", $_ENV["DEFAULT_STORE_ID"]));

        $insertArray = [
            // product_warehouse_link_entity
        ];
        $updateArray = [
            // product_warehouse_link_entity
        ];

        $productIds = [];



        $productCodes = Array(0 => Array());
        /**
         * Ako ima bilo kakav popis proizvoda podijeli na 500 kom po upitu - TESTIRANO, NE MOZE VISE
         */
        if(!empty($args["product_codes"]) && count($args["product_codes"]) > 0){
            $productCodes = array_chunk($args["product_codes"], 500);
        }

        if ((isset($args["warehouse_code"]) && !empty($args["warehouse_code"])) || count($productCodes) > 2 || !isset($args["changes_since_minutes"])) {

            if(!empty($this->getConsoleOutput())){
                $progressBar = new ProgressBar($this->getConsoleOutput(), count($existingWarehouses)*count($productCodes));
            }

            foreach ($existingWarehouses as $warehouseCode => $existingWarehouse) {

                foreach ($productCodes as $productCode) {

                    if(!empty($this->getConsoleOutput())) {
                        $progressBar->advance();
                    }

                    $zaliheArtikala = [];
                    $cjenikArtikala["sifra_ps"] = $_ENV["TRS_SIFRA_PS"];
                    $zaliheArtikala["sifra_mt_od"] = (string)$warehouseCode;
                    $zaliheArtikala["sifra_mt_do"] = (string)$warehouseCode;
                    if(!empty($productCode)){
                        $zaliheArtikala["sifre_artikala"] = (string)implode(",",$productCode);
                    }
                    /*if(!empty($productCode)){
                        $zaliheArtikala["sifra_artikla_od"] = (string)$productCode;
                        $zaliheArtikala["sifra_artikla_do"] = (string)$productCode;
                    }*/
                    $zaliheArtikala["rownum_od"] = 1;
                    $zaliheArtikala["rownum_do"] = 1000;
                    if(isset($args["aktivan_za_web"]) && !empty($args["aktivan_za_web"])){
                        $zaliheArtikala["aktivan_za_web"] = $args["aktivan_za_web"];
                    }
                    if (isset($args["changes_since_minutes"]) && !empty($args["changes_since_minutes"])) {
                        $zaliheArtikala["vrijeme_izmjene_od"] = (new \DateTime("now -" . $args["changes_since_minutes"] . " minute"))
                            ->format("d.m.Y. H:i");
                    }

                    do {
                        $data = $this->getTrsApiData($this->trsRestManager, "zalihe", $zaliheArtikala);

                        if(isset($data["log_file"])){
                            $logFiles[] = $data["log_file"];
                        }
                        $data = $data["result"];

                        //$count = count($data);
                        if (!empty($data)) {

                            foreach ($data as $d) {

                                if(!isset($existingWarehouses[$d["sifra_mt"]])){
                                    continue;
                                }

                                $qty = (int)$d["kolicina_slobodna"];
                                if($qty < 0){
                                    $qty = 0;
                                }

                                /**
                                 * Provjera ako slucajno dode neki novi proizvod kojeg jos nemamo u bazi
                                 */
                                if (isset($existingProducts[$d["sifra_artikla"]]["id"])) {

                                    $productId = $existingProducts[$d["sifra_artikla"]]["id"];

                                    /**
                                     * Fiksna provjera:
                                     * Ako je skladiste CS ili neko pomocno skladiste, uzima se samo kolicina kod onih proizvoda koji imaju transpoort_qty manji od definiranog u settings
                                     */
                                    if($existingWarehouses[$d["sifra_mt"]]["is_auxiliary_warehouse"] == 1 && (floatval($existingProducts[$d["sifra_artikla"]]["transport_qty"]) == 0 || floatval($existingProducts[$d["sifra_artikla"]]["transport_qty"]) > $maxTransportQty)){
                                        $qty = 0;
                                    }

                                    $productWarehouseLinkKey = $productId . "_" . $existingWarehouses[$d["sifra_mt"]]["id"];
                                    if (!isset($existingProductWarehouseLinks[$productWarehouseLinkKey])) {
                                        $productWarehouseLinkInsert = new InsertModel($this->asProductWarehouseLink);
                                        $productWarehouseLinkInsert->add("product_id", $productId);
                                        $productWarehouseLinkInsert->add("warehouse_id", $existingWarehouses[$d["sifra_mt"]]["id"]);
                                        if(!isset($disabledProductWarehouse[$productWarehouseLinkKey])) {
                                            $productWarehouseLinkInsert->add("qty", $qty);
                                        }
                                        $productWarehouseLinkInsert->add("qty_supplier", $qty);
                                        $insertArray["product_warehouse_link_entity"][$productWarehouseLinkKey] = $productWarehouseLinkInsert->getArray();
                                        $productIds[] = $productId;
                                    } else {
                                        $productWarehouseLinkUpdate = new UpdateModel($existingProductWarehouseLinks[$productWarehouseLinkKey]);
                                        if(!isset($disabledProductWarehouse[$productWarehouseLinkKey])){
                                            $productWarehouseLinkUpdate->addFloat("qty", $qty);
                                        }
                                        $productWarehouseLinkUpdate->addFloat("qty_supplier", $qty);
                                        if (!empty($productWarehouseLinkUpdate->getArray())) {
                                            $updateArray["product_warehouse_link_entity"][$productWarehouseLinkUpdate->getEntityId()] = $productWarehouseLinkUpdate->getArray();
                                            $productIds[] = $productId;
                                        }
                                    }
                                }
                            }
                        }

                        $zaliheArtikala["rownum_od"] = $zaliheArtikala["rownum_do"] + 1;
                        $zaliheArtikala["rownum_do"] = $zaliheArtikala["rownum_od"] + 999;

                        $this->databaseContext->reconnectToDatabase();

                        if(!empty($insertArray)){
                            $this->executeInsertQuery($insertArray);
                            $insertArray = Array();
                        }

                        if(!empty($updateArray)){
                            $this->executeUpdateQuery($updateArray);
                            $updateArray = Array();
                        }

                    } while (!empty($data) && count($data) >= 1000);
                }
            }
        }
        else{
            if(!empty($this->getConsoleOutput())){
                $progressBar = new ProgressBar($this->getConsoleOutput(), count($productCodes));
            }

            foreach ($productCodes as $productCode) {

                if(!empty($this->getConsoleOutput())) {
                    $progressBar->advance();
                }

                $zaliheArtikala = [];
                $cjenikArtikala["sifra_ps"] = $_ENV["TRS_SIFRA_PS"];
                //$zaliheArtikala["sifra_mt_od"] = (string)$warehouseCode;
                //$zaliheArtikala["sifra_mt_do"] = (string)$warehouseCode;
                if(!empty($productCode)){
                    $zaliheArtikala["sifre_artikala"] = (string)implode(",",$productCode);
                }
                /*if(!empty($productCode)){
                    $zaliheArtikala["sifra_artikla_od"] = (string)$productCode;
                    $zaliheArtikala["sifra_artikla_do"] = (string)$productCode;
                }*/
                $zaliheArtikala["rownum_od"] = 1;
                $zaliheArtikala["rownum_do"] = 10000;
                if(isset($args["aktivan_za_web"]) && !empty($args["aktivan_za_web"])){
                    $zaliheArtikala["aktivan_za_web"] = $args["aktivan_za_web"];
                }
                if (isset($args["changes_since_minutes"]) && !empty($args["changes_since_minutes"])) {
                    $zaliheArtikala["vrijeme_izmjene_od"] = (new \DateTime("now -" . $args["changes_since_minutes"] . " minute"))
                        ->format("d.m.Y. H:i");
                }

                do {
                    $data = $this->getTrsApiData($this->trsRestManager, "zalihe", $zaliheArtikala);

                    if(isset($data["log_file"])){
                        $logFiles[] = $data["log_file"];
                    }
                    $data = $data["result"];

                    //$count = count($data);
                    if (!empty($data)) {

                        foreach ($data as $d) {

                            if(!isset($existingWarehouses[$d["sifra_mt"]])){
                                continue;
                            }

                            $qty = (int)$d["kolicina_slobodna"];
                            if($qty < 0){
                                $qty = 0;
                            }

                            /**
                             * Provjera ako slucajno dode neki novi proizvod kojeg jos nemamo u bazi
                             */
                            if (isset($existingProducts[$d["sifra_artikla"]]["id"])) {

                                $productId = $existingProducts[$d["sifra_artikla"]]["id"];

                                /**
                                 * Fiksna provjera:
                                 * Ako je skladiste CS ili neko pomocno skladiste, uzima se samo kolicina kod onih proizvoda koji imaju transpoort_qty manji od definiranog u settings
                                 */
                                if($existingWarehouses[$d["sifra_mt"]]["is_auxiliary_warehouse"] == 1 && (floatval($existingProducts[$d["sifra_artikla"]]["transport_qty"]) == 0 || floatval($existingProducts[$d["sifra_artikla"]]["transport_qty"]) > $maxTransportQty)){
                                    $qty = 0;
                                }

                                $productWarehouseLinkKey = $productId . "_" . $existingWarehouses[$d["sifra_mt"]]["id"];
                                if (!isset($existingProductWarehouseLinks[$productWarehouseLinkKey])) {
                                    $productWarehouseLinkInsert = new InsertModel($this->asProductWarehouseLink);
                                    $productWarehouseLinkInsert->add("product_id", $productId);
                                    $productWarehouseLinkInsert->add("warehouse_id", $existingWarehouses[$d["sifra_mt"]]["id"]);
                                    $productWarehouseLinkInsert->add("qty", 0);
                                    if(!isset($disabledProductWarehouse[$productWarehouseLinkKey])){
                                        $productWarehouseLinkInsert->add("qty", $qty);
                                    }
                                    $productWarehouseLinkInsert->add("qty_supplier", $qty);
                                    $insertArray["product_warehouse_link_entity"][$productWarehouseLinkKey] = $productWarehouseLinkInsert->getArray();
                                    $productIds[] = $productId;
                                } else {
                                    $productWarehouseLinkUpdate = new UpdateModel($existingProductWarehouseLinks[$productWarehouseLinkKey]);
                                    if(!isset($disabledProductWarehouse[$productWarehouseLinkKey])){
                                        $productWarehouseLinkUpdate->addFloat("qty", $qty);
                                    }
                                    $productWarehouseLinkUpdate->addFloat("qty_supplier", $qty);
                                    if (!empty($productWarehouseLinkUpdate->getArray())) {
                                        $updateArray["product_warehouse_link_entity"][$productWarehouseLinkUpdate->getEntityId()] = $productWarehouseLinkUpdate->getArray();
                                        $productIds[] = $productId;
                                    }
                                }
                            }
                        }
                    }

                    $zaliheArtikala["rownum_od"] = $zaliheArtikala["rownum_do"] + 1;
                    $zaliheArtikala["rownum_do"] = $zaliheArtikala["rownum_od"] + 9999;

                    $this->databaseContext->reconnectToDatabase();

                    if(!empty($insertArray)){
                        $this->executeInsertQuery($insertArray);
                        $insertArray = Array();
                    }

                    if(!empty($updateArray)){
                        $this->executeUpdateQuery($updateArray);
                        $updateArray = Array();
                    }

                } while (!empty($data) && count($data) >= 10000);
            }
        }

        if(!empty($this->getConsoleOutput())) {
            $progressBar->finish();
            echo "\n";
        }

        unset($existingProducts);
        unset($existingWarehouses);
        unset($existingProductWarehouseLinks);

        if(!empty($insertArray)) {
            $this->executeInsertQuery($insertArray);
            unset($insertArray);
        }

        if(!empty($updateArray)) {
            $this->executeUpdateQuery($updateArray);
            unset($updateArray);
        }

        $ret = [];
        if (!empty($productIds)) {
            $ret["product_ids"] = array_unique($productIds);
        }
        $ret["log_files"] = $logFiles;

        return $ret;
    }

    /**
     * @param array $args
     * @return array
     * @throws \Exception
     */
    public function importProductPrices($args = ["product_codes" => [""], "from_date" => ""])
    {
        if(!empty($this->getConsoleOutput())){
            echo "Importing product prices...\n";
        }

        $logFiles = Array();

        $productSelectColumns = [
            "id",
            "code",
            "price_base",
            "price_retail",
            "discount_price_base",
            "discount_price_retail",
            "date_discount_from",
            "date_discount_base_from",
            "date_discount_to",
            "date_discount_base_to",
            "price_return",
            "price_list_type",
            "price_list_id",
            "kol_art_u_jmj_gr",
            "mpc_u_jmj_gr",
            "vpc_u_jmj_gr",
            "flag_okretanja",
            "mpc_u_jmj_gr_old",
            "vpc_u_jmj_gr_old",
            "min_price_retail",
            "is_on_promotion",
            "is_on_pk",
            "tax_type_id"
        ];

        $existingProductAccountGroupPrices = $this->getEntitiesArray(["id", "product_id", "account_group_id", "date_valid_from", "date_valid_to", "discount_price_base", "discount_price_retail","remote_source"], "product_account_group_price_entity", ["product_id", "account_group_id"], "", "WHERE entity_state_id = 1");
        $existingProducts = $this->getEntitiesArray($productSelectColumns, "product_entity", ["code"], "", "WHERE product_type_id IN (1,3,4,6) AND remote_source = '{$this->getRemoteSource()}' AND (avoid_price_sync IS NULL OR avoid_price_sync = 0)");
        $existingTaxTypes = $this->getEntitiesArray(["id", "CAST(percent AS UNSIGNED) percent"], "tax_type_entity", ["percent"]);
        //$existingWarehouses = $this->getEntitiesArray(["id", "code"], "warehouse_entity", ["code"], "", "WHERE entity_state_id = 1 AND code = '0037'");

        $updateArray = [
            // product_entity
        ];
        $deleteArray = [
            // product_account_group_price_entity
        ];
        $insertArray = [
            // product_account_group_price_entity
        ];

        $productIds = [];

        if((!isset($args["product_codes"]) || empty($args["product_codes"])) && (!isset($args["samo_danasnje_promjene"]) || $args["samo_danasnje_promjene"] != "DA")){
            $args["product_codes"] = array_keys($existingProducts);
        }

        $productCodes = Array(0 => Array());
        /**
         * Ako ima bilo kakav popis proizvoda podijeli na 500 kom po upitu - TESTIRANO, NE MOZE VISE
         */
        if(!empty($args["product_codes"]) && count($args["product_codes"]) > 0){
            $productCodes = array_chunk($args["product_codes"], 500);
            $count = count($productCodes);
        }
        else{
            $count = count($existingProducts);
        }

        if(!empty($this->getConsoleOutput())) {
            $progressBar = new ProgressBar($this->getConsoleOutput(), $count);
        }

        $now = new \DateTime();
        $end = clone $now;
        $end->add(new \DateInterval("P10Y"));

        //foreach ($existingWarehouses as $warehouseCode => $existingWarehouse) {

            foreach ($productCodes as $productCode) {

                if(!empty($this->getConsoleOutput())) {
                    $progressBar->advance();
                }

                $cjenikArtikala = [];
                $cjenikArtikala["sifra_ps"] = $_ENV["TRS_SIFRA_PS"];
                $cjenikArtikala["sifra_mt"] = $_ENV["TRS_MT_WEBSHOP"];
                //$cjenikArtikala["sifra_artikla_od"] = (string)$productCode;
                //$cjenikArtikala["sifra_artikla_do"] = (string)$productCode;
                if(!empty($productCode)){
                    $cjenikArtikala["sifre_artikala"] = (string)implode(",",$productCode);
                }
                $cjenikArtikala["rownum_od"] = 1;
                $cjenikArtikala["rownum_do"] = 1000;

                if(isset($args["from_date"]) && !empty($args["from_date"])){
                    $cjenikArtikala["od_datuma"] = $args["from_date"];
                }
                $cjenikArtikala["samo_danasnje_promjene"] = $this->getFast() ? "DA" : "NE";
                if(isset($args["samo_danasnje_promjene"]) && !empty($args["samo_danasnje_promjene"])){
                    $cjenikArtikala["samo_danasnje_promjene"] = "NE";
                    if($args["samo_danasnje_promjene"] == "DA"){
                        $yesterday = new \DateTime();
                        $yesterday->modify('-2 day');
                        $cjenikArtikala["od_datuma"] = $yesterday->format("d.m.Y.");
                    }
                    //$cjenikArtikala["samo_danasnje_promjene"] = $args["samo_danasnje_promjene"];
                }

                do {
                    $data = $this->getTrsApiData($this->trsRestManager, "cjenik", $cjenikArtikala);

                    if(isset($data["log_file"])){
                        $logFiles[] = $data["log_file"];
                    }
                    $data = $data["result"];

                    //$count = count($data);
                    if (!empty($data)) {
                        foreach ($data as $d) {

                            /**
                             * Provjera ako slucajno dode neki novi proizvod kojeg jos nemamo u bazi
                             */
                            if (isset($existingProducts[$d["sifra_artikla"]]["id"])) {

                                $taxTypeId = 3;
                                if(isset($existingTaxTypes[$d["pdv_postotak"]])){
                                    $taxTypeId = $existingTaxTypes[$d["pdv_postotak"]]["id"];
                                }
                                $taxPercent = 1+((float)$d["pdv_postotak"])/100;

                                /**
                                 * Iracunavamo sami
                                 */
                                //
                                /*$d["mpc"] = round($d["vpc"]*$taxPercent,2);
                                $d["mpc_old"] = round($d["vpc_old"]*$taxPercent,2);
                                $d["mpc_u_jmj_gr"] = round($d["vpc_u_jmj_gr"]*$taxPercent,2);
                                $d["mpc_u_jmj_gr_old"] = round($d["vpc_u_jmj_gr_old"]*$taxPercent,2);
                                $d["loyalty_mpc"] = round($d["loyalty_vpc"]*$taxPercent,2);*/

                                //$currencyCode = $d["valuta"];
                                $priceReturn = $d["pn"];
                                $priceBase = $d["vpc"];
                                $priceRetail = $d["mpc"];
                                $discountPriceBase = null;
                                $discountPriceRetail = null;
                                $dateDiscountFrom = $dateDiscountBaseFrom = null;
                                $dateDiscountTo = $dateDiscountBaseTo = null;

                                /**
                                 * Custom Pevex atributi
                                 */
                                $priceListType = $d["tip_cjenika"]; // string
                                $priceListId = $d["id_cjenika"]; // int
                                $kolArtUJmjGr = number_format($d["kol_art_u_jmj_gr"], 4, ".", ""); // float
                                if (strlen($kolArtUJmjGr) > 6) {
                                    $kolArtUJmjGr = 1;
                                }
                                $kolArtUJmjGr = (float)$kolArtUJmjGr;
                                $vpcUJmjGr = (float)$d["vpc_u_jmj_gr"]; // float
                                $mpcUJmjGr = $d["mpc_u_jmj_gr"]; // float

                                $flagOkretanja = $d["flag_okretanja"]; // int
                                $vpcUJmjGrOld = (float)$d["vpc_u_jmj_gr_old"]; // float
                                $mpcUJmjGrOld = $d["mpc_u_jmj_gr_old"]; // float

                                $loyaltyVpc = (float)$d["loyalty_vpc"];
                                $loyaltyMpc = (float)$d["loyalty_mpc"];

                                $dateLoyaltyFrom = null;
                                if(!empty($d["loyalty_dat_vrijedi_od"])){
                                    $dateLoyaltyFromDate = new \DateTime($d["loyalty_dat_vrijedi_od"]);
                                    $dateLoyaltyFrom = $dateLoyaltyFromDate->format("Y-m-d H:i:s");
                                }
                                $dateLoyaltyTo = null;
                                if(!empty($d["loyalty_dat_vrijedi_do"])){
                                    $dateLoyaltyToDate = new \DateTime($d["loyalty_dat_vrijedi_do"]);
                                    $dateLoyaltyTo = $dateLoyaltyToDate->format("Y-m-d")." 23:59:00";
                                }

                                if($loyaltyVpc <= 0){
                                    $loyaltyMpc = 0;
                                }

                                $isOnPromotion = 0;
                                if(!empty($priceListType) && in_array($priceListType,Array("KAT","AKC","RAS","WEB"))){
                                    $isOnPromotion = 1;
                                }

                                $isOnPK = 0;
                                if($loyaltyMpc > 0){
                                    $isOnPK = 1;
                                }

                                $minPriceRetail = null;

                                if(!empty($d["dat_vrijedi_od"])){
                                    $dtDateDiscountFrom = new \DateTime($d["dat_vrijedi_od"]);
                                    $dateDiscountFrom = $dateDiscountBaseFrom = $dtDateDiscountFrom->format("Y-m-d H:i:s");
                                }
                                if(!empty($d["dat_vrijedi_do"])){
                                    $dtDateDiscountTo = new \DateTime($d["dat_vrijedi_do"]);
                                    $dateDiscountTo = $dateDiscountBaseTo = $dtDateDiscountTo->format("Y-m-d")." 23:59:00";
                                }

                                if($_ENV["MPC_OLD_IS_MINIMAL_PRICE"]){
                                    if ((float)$d["mpc_old"] > 0 && $d["mpc_old"] > $d["mpc"] && !empty($d["dat_vrijedi_od"])) {

                                        $d["vpc_old"] = $d["mpc_old"] / $taxPercent;

                                        $priceBase = $d["vpc_old"];
                                        $minPriceRetail = $priceRetail = $d["mpc_old"];
                                        $discountPriceBase = $d["vpc"];
                                        $discountPriceRetail = $d["mpc"];
                                    }
                                }
                                else{
                                    if ((float)$d["vpc_old"] > 0 && (float)$d["mpc_old"] > 0 &&
                                        $d["vpc_old"] > $d["vpc"] && $d["mpc_old"] > $d["mpc"] &&
                                        !empty($d["dat_vrijedi_od"]) && !empty($d["dat_vrijedi_do"])) {

                                        $priceBase = $d["vpc_old"];
                                        $priceRetail = $d["mpc_old"];
                                        $discountPriceBase = $d["vpc"];
                                        $discountPriceRetail = $d["mpc"];
                                    }
                                }

                                $productUpdate = new UpdateModel($existingProducts[$d["sifra_artikla"]]);
                                $productUpdate->addFloat("price_base", $priceBase)
                                    ->addFloat("price_retail", $priceRetail)
                                    ->addFloat("min_price_retail", $minPriceRetail)
                                    ->addFloat("discount_price_base", $discountPriceBase)
                                    ->addFloat("discount_price_retail", $discountPriceRetail)
                                    ->add("date_discount_from", $dateDiscountFrom)
                                    ->add("date_discount_base_from", $dateDiscountBaseFrom)
                                    ->add("date_discount_to", $dateDiscountTo)
                                    ->add("date_discount_base_to", $dateDiscountBaseTo)
                                    ->addFloat("price_return", $priceReturn)
                                    ->add("price_list_type", $priceListType)
                                    ->add("price_list_id", $priceListId)
                                    ->addFloat("kol_art_u_jmj_gr", $kolArtUJmjGr, true, 4)
                                    ->addFloat("mpc_u_jmj_gr", $mpcUJmjGr)
                                    ->addFloat("vpc_u_jmj_gr", $vpcUJmjGr)
                                    ->add("flag_okretanja", $flagOkretanja)
                                    ->add("is_on_promotion", $isOnPromotion)
                                    ->add("is_on_pk", $isOnPK)
                                    ->add("tax_type_id", $taxTypeId)
                                    ->addFloat("mpc_u_jmj_gr_old", $mpcUJmjGrOld)
                                    ->addFloat("vpc_u_jmj_gr_old", $vpcUJmjGrOld);

                                if (!empty($productUpdate->getArray())) {
                                    $updateArray["product_entity"][$productUpdate->getEntityId()] = $productUpdate->getArray();
                                    $productIds[] = $productUpdate->getEntityId();
                                }

                                $productAccountGroupPriceKey = $existingProducts[$d["sifra_artikla"]]["id"]."_".$_ENV["PEVEX_KLUB_ACCOUNT_GROUP_ID"];

                                if($loyaltyMpc == 0 && isset($existingProductAccountGroupPrices[$productAccountGroupPriceKey]) && $existingProductAccountGroupPrices[$productAccountGroupPriceKey]["remote_source"] == $this->getRemoteSource()){
                                    $deleteArray["product_account_group_price_entity"][$productAccountGroupPriceKey] = [
                                        "id" => $existingProductAccountGroupPrices[$productAccountGroupPriceKey]["id"],
                                        "product_id" => $existingProductAccountGroupPrices[$productAccountGroupPriceKey]["product_id"]
                                    ];
                                }
                                elseif ($loyaltyMpc > 0){

                                    /**
                                     * Insert loyalty price
                                     */
                                    if(!isset($existingProductAccountGroupPrices[$productAccountGroupPriceKey])){

                                        $productAccountGroupPriceInsert = new InsertModel($this->asProductAccountGroupPrice);
                                        $productAccountGroupPriceInsert->add("product_id", $existingProducts[$d["sifra_artikla"]]["id"])
                                            ->add("account_group_id", $_ENV["PEVEX_KLUB_ACCOUNT_GROUP_ID"])
                                            ->add("date_valid_from", $dateLoyaltyFrom)
                                            ->add("date_valid_to", $dateLoyaltyTo)
                                            ->add("discount_price_base", $loyaltyVpc)
                                            ->add("discount_price_retail", $loyaltyMpc)
                                            ->add("remote_source", $this->getRemoteSource());

                                        $insertArray["product_account_group_price_entity"][$productAccountGroupPriceKey] = $productAccountGroupPriceInsert->getArray();

                                        $productIds[] = $productUpdate->getEntityId();
                                    }
                                    else{

                                        $productAccountGroupPriceUpdate = new UpdateModel($existingProductAccountGroupPrices[$productAccountGroupPriceKey]);
                                        $productAccountGroupPriceUpdate
                                            ->add("date_valid_from", $dateLoyaltyFrom)
                                            ->add("date_valid_to", $dateLoyaltyTo)
                                            ->addFloat("discount_price_base", $loyaltyVpc)
                                            ->addFloat("discount_price_retail", $loyaltyMpc)
                                            ->add("remote_source", $this->getRemoteSource());

                                        if (!empty($productAccountGroupPriceUpdate->getArray())) {
                                            $updateArray["product_account_group_price_entity"][$productAccountGroupPriceUpdate->getEntityId()] = $productAccountGroupPriceUpdate->getArray();
                                            $productIds[] = $productUpdate->getEntityId();
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $cjenikArtikala["rownum_od"] = $cjenikArtikala["rownum_do"] + 1;
                    $cjenikArtikala["rownum_do"] = $cjenikArtikala["rownum_od"] + 999;

                    //echo "Warehouse code: " . $warehouseCode . " product code: " . $productCode . " data count: " . $count . "\n";

                    $this->databaseContext->reconnectToDatabase();

                } while (!empty($data) && count($data) >= 1000);
            }
        //}

        if(!empty($this->getConsoleOutput())) {
            $progressBar->finish();
            echo "\n";
        }

        unset($existingProducts);
        unset($existingWarehouses);
        unset($existingProductAccountGroupPrices);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        if (isset($deleteArray["product_account_group_price_entity"])) {
            foreach ($deleteArray["product_account_group_price_entity"] as $productAccountGroupPrice) {
                $productIds[] = $productAccountGroupPrice["product_id"];
            }
            $this->executeDeleteQuery($deleteArray);
            unset($deleteArray);
        }

        $ret = [];
        if (!empty($productIds)) {
            $ret["product_ids"] = array_unique($productIds);
        }

        if(!empty($this->getConsoleOutput())) {
            echo "Importing product prices complete\n";
        }
        $ret["log_files"] = $logFiles;

        return $ret;
    }

    /**
     * @param OrderItemEntity $orderItem
     * @param $rbr
     * @return array|void
     */
    public function getNarStavke(OrderItemEntity $orderItem, $rbr)
    {
        $taxPercent = $orderItem->getTaxType()->getPercent();

        $mpc_prije_popusta = floatval($orderItem->getOriginalBasePriceItem());
        $mpc_poslije_popusta = floatval($orderItem->getBasePriceItem());

        $struktura_popusta = "";
        $postotak_popusta = 0;

        /**
         * NOVO
         */
        $percentageDiscount = floatval($orderItem->getPercentageDiscount());
        $percentageDiscountCartRule = floatval($orderItem->getPercentageDiscountCartRule());
        $percentageDiscountCoupon = floatval($orderItem->getPercentageDiscountCoupon());

        $m = 1;
        if($percentageDiscount > 0){
            $struktura_popusta = "(M{$m}={$percentageDiscount})";
            $m = 2;
        }
        if($percentageDiscountCartRule > 0){
            $struktura_popusta.= "(M{$m}={$percentageDiscountCartRule})";
            $m = 3;
        }
        if($percentageDiscountCoupon > 0){
            $struktura_popusta.= "(M{$m}={$percentageDiscountCoupon})";
            $m = 4;
        }

        if($mpc_prije_popusta == $mpc_poslije_popusta){
            $postotak_popusta = 0;
        }
        else {
            $controlPercentage = ($mpc_prije_popusta - $mpc_poslije_popusta) / $mpc_prije_popusta * 100;
            if (abs($postotak_popusta - $controlPercentage) > 1) {
                $postotak_popusta = round($controlPercentage, 2);

                if(empty($struktura_popusta)){
                    if (empty($this->errorLogManager)) {
                        $this->errorLogManager = $this->container->get("error_log_manager");
                    }
                    $this->errorLogManager->logErrorEvent("getNarStavke - neispravan postotak popusta", "Order item: {$orderItem->getId()}, order: {$orderItem->getOrder()->getId()}, predlozeni postotak {$postotak_popusta}, stvarni postotak {$controlPercentage}.", true);
                }
            }
        }

        /**
         * END NOVO
         */

        /**
         * TRS - Ostavljamo staru varijantu da lako vratimo
         */
        //$postotak_popusta = floatval($orderItem->getPercentageDiscount());
        //if($postotak_popusta == 0){
        /**
         * Ostavili smo samo coupon percentage zato jer sve ostalo vec treba biti uracunato u getOriginalBasePriceItem
         */
        ################$postotak_popusta = floatval($orderItem->getPercentageDiscountCoupon());
        ################if($orderItem->getIsPartOfBundle()){
        ################    $postotak_popusta = floatval($orderItem->getPercentageDiscount());
        ################}
        //}

        /**
         * Opcija 2
         */
        /*$discountPercent = floatval($orderItem->getPercentageDiscount());
        $percentageDiscountCoupon = floatval($orderItem->getPercentageDiscountCoupon());
        if($discountPercent > 0 && $percentageDiscountCoupon > 0){
            $postotak_popusta = ($mpc_prije_popusta-$mpc_poslije_popusta)/$mpc_prije_popusta * 100;
            $struktura_popusta = "(M1={$discountPercent})(M2={$percentageDiscountCoupon})";
        }
        else{
            $postotak_popusta = $percentageDiscountCoupon;
        }*/

        /**
         * Kontrola popusta
         */
        /*if($mpc_prije_popusta == $mpc_poslije_popusta){
            $postotak_popusta = 0;
        }
        else{
            $controlPercentage = ($mpc_prije_popusta-$mpc_poslije_popusta)/$mpc_prije_popusta * 100;
            if(abs($postotak_popusta - $controlPercentage) > 1){
                $postotak_popusta = round($controlPercentage,2);

                if(empty($this->errorLogManager)){
                    $this->errorLogManager = $this->container->get("error_log_manager");
                }
                $this->errorLogManager->logErrorEvent("getNarStavke - neispravan postotak popusta","Order item: {$orderItem->getId()}, order: {$orderItem->getOrder()->getId()}, predlozeni postotak {$postotak_popusta}, stvarni postotak {$controlPercentage}.",true);
            }
        }*/

        $povratna_naknada = floatval($orderItem->getPriceItemReturn());
        $konacna_cijena = $mpc_poslije_popusta + $povratna_naknada;
        $iznos_mp = floatval($orderItem->getBasePriceTotal()) + floatval($orderItem->getPriceReturnTotal());

        $ret = Array(
            "rbr" => $rbr,
            "id_stavke" => $orderItem->getId(),
            "ident_sifra" => $orderItem->getProduct()->getCode(),
            "naziv_artikla" => $orderItem->getName(),
            "tip_art_amb" => "",
            "jmj" => $orderItem->getProduct()->getTrsMeasure(),
            "kolicina" => floatval($orderItem->getQty()),
            "mpc_prije_popusta" => $mpc_prije_popusta,
            "postotak_popusta" => $postotak_popusta,
            "struktura_popusta" => $struktura_popusta,
/*            "postotak_popusta": 19,
"struktura_popusta": "(M1=10)(M2=10)",*/
            "mpc_poslije_popusta" => $mpc_poslije_popusta,
            "povratna_naknada" => $povratna_naknada,
            "konacna_cijena" => $konacna_cijena,
            "iznos_por_osn" => null,
            "pdv_postotak" => $taxPercent,
            "iznos_pdv" => null,
            "iznos_mp" => $iznos_mp,
            "bruto_masa_kg" => $orderItem->getProduct()->getWeight(),
            "komentar" => ""
        );

        /*dump($ret);
        die;*/

        /*if($orderItem->getProduct()->getCode() == "008670"){
            dump($ret);
            die;
        }*/

        return $ret;
    }

    /**
     * @param OrderEntity $order
     * @param $total
     * @param $rbr
     * @return array
     */
    public function getNarPlacanja(OrderEntity $order, $total, $rbr)
    {
        $ret = Array(
            "rbr" => $rbr,
            "sif_nac_pla" => $order->getPaymentType()->getRemoteCode(),
            "iznos" => $total,
            "iznos_valuta" => $order->getCurrency()->getCode(),
            "oznaka_placanja" => "", //popuniti getCcLast4()); -- popunjeno dolje
            "broj_autorizacije" => "", //popuniti getCcTransId()); -- popunjeno dolje
            "id_transakcije" => "", //popuniti getCcTransId()); -- popunjeno dolje
            "komentar" => "",
            "ecr_sif_proizvodjaca" => "", //popuniti acquirer -- popunjeno dolje
            "ecr_sif_kartice" => "", //popuniti card_type -- popunjeno dolje
            "ecr_br_rata" => "" //popuniti purchase_installments -- popunjeno dolje
        );

        if(in_array($order->getPaymentTypeId(),Array(ScommerceConstants::PAYMENT_KARTICA,ScommerceConstants::PAYMENT_KEKS))){

            if(empty($this->paymentTransactionManager)){
                $this->paymentTransactionManager = $this->container->get("payment_transaction_manager");
            }

            /** @var PaymentTransactionEntity $paymentTransaction */
            $paymentTransaction = $this->paymentTransactionManager->getPaymentTransactionByOrderId($order->getId());

            if(empty($paymentTransaction)){
                if(empty($this->errorLogManager)){
                    $this->errorLogManager = $this->container->get("error_log_manager");
                }

                $e = new \Exception("Missing payment transaction for order: {$order->getId()}");

                $this->errorLogManager->logExceptionEvent("getNarPlacanja - missing payment transaction",$e,true);

                throw $e;
            }

            if($order->getPaymentTypeId() == ScommerceConstants::PAYMENT_KARTICA){
                if($paymentTransaction->getTransactionType() != "preauth" || $paymentTransaction->getResponseResult() != "000"){
                    if(empty($this->errorLogManager)){
                        $this->errorLogManager = $this->container->get("error_log_manager");
                    }

                    $e = new \Exception("Payment transaction not valid: {$paymentTransaction->getId()} for order id {$order->getId()}");

                    $this->errorLogManager->logExceptionEvent("getNarPlacanja - Payment transaction not valid: {$paymentTransaction->getId()} for order id {$order->getId()}",$e,true);

                    throw $e;
                }

                $ret["oznaka_placanja"] = substr($paymentTransaction->getMaskedPan(), -4);
                $ret["broj_autorizacije"] = $paymentTransaction->getTransactionIdentifier();
                $ret["id_transakcije"] = $paymentTransaction->getTransactionIdentifierSecond();
                $ret["ecr_sif_proizvodjaca"] = $paymentTransaction->getAcquirer();
                $ret["ecr_sif_kartice"] = $paymentTransaction->getCardType();
                $ret["ecr_br_rata"] = $paymentTransaction->getPurchaseInstallments();
            }
            elseif ($order->getPaymentTypeId() == ScommerceConstants::PAYMENT_KEKS){

                /**
                 * Popuniti kekst drek
                 */
                $ret["oznaka_placanja"] = "";
                $ret["broj_autorizacije"] = $paymentTransaction->getTransactionIdentifier();
                $ret["id_transakcije"] = $paymentTransaction->getTransactionIdentifierSecond();
                $ret["ecr_sif_proizvodjaca"] = "Erste";
                $ret["ecr_sif_kartice"] = "keks";
                $ret["ecr_br_rata"] = 1;
            }
        }

        return $ret;
    }

    /**
     * @param OrderItemGroupEntity $orderItemGroup
     * @return array[]
     */
    public function getPreparedOrder(OrderItemGroupEntity $orderItemGroup)
    {
        /** @var OrderEntity $order */
        $order = $orderItemGroup->getRelatedOrder();

        /**
         * Set default data
         */
        $tipDokumenta = "N";
        $kupacIme = null;
        $kupacOib = null;
        $brutoMasaKg = 0;
        $pozivNaBroj = $order->getReferenceNumber();
        $narStavke = [];
        $narPlacanja = [];
        $rbr = 1;
        $basePriceWithoutTax=0;
        $basePriceTax=0;
        $basePriceTotal=0;
        $idDostavljaca = "";

        /**
         * Set account and contact data
         */

        if(!empty($order->getAccountOib())){
            $kupacPrezime = $order->getAccountName();
            $kupacKontaktTel = $order->getContact()->getPhone();
            $kupacKontaktEmail = $order->getContact()->getEmail();
            $kupacOib = $order->getAccountOib();
            $tipDokumenta = "N-R1";
        }
        else{
            $kupacIme = $order->getContact()->getFirstName();
            $kupacPrezime = $order->getContact()->getLastName();
            $kupacKontaktTel = $order->getContact()->getPhone();
            $kupacKontaktEmail = $order->getContact()->getEmail();
        }

        /**
         * Set Order items
         */
        $orderItems = $orderItemGroup->getOrderItems();

        if(!EntityHelper::isCountable($orderItems) || count($orderItems) == 0){
            throw new \Exception("Missing order items on order id: {$order->getId()}");
        }

        /** @var OrderItemEntity $orderItem */
        foreach ($orderItems as $orderItem) {

            if (in_array($orderItem->getProduct()->getProductTypeId(), [CrmConstants::PRODUCT_TYPE_CONFIGURABLE, CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE])) {
                continue;
            }

            $narStavka = $this->getNarStavke($orderItem, $rbr);
            $narStavke[] = $narStavka;
            $rbr++;

            $basePriceWithoutTax = $basePriceWithoutTax + floatval($orderItem->getBasePriceWithoutTax());
            $basePriceTax = $basePriceTax + floatval($orderItem->getBasePriceTax());
            $basePriceTotal = $basePriceTotal + floatval($orderItem->getBasePriceTotal()) + floatval($orderItem->getPriceReturnTotal());

            $brutoMasaKg = $brutoMasaKg + (floatval($orderItem->getProduct()->getWeight())*floatval($orderItem->getQty()));
        }

        /**
         * Set delivery data
         */
        $nacinDostave = "C";
        $deliveryKupacIme = "";
        $deliveryKupacPrezime = "";
        $deliveryStreet = "";
        $deliveryCity = "";
        $deliveryPostalCode = "";
        $deliveryComment = "";
        $deliveryCountry = "HRV";
        $deliveryContact = "";
        $invoiceComment = "";

        if($orderItemGroup->getDeliveryType()->getIsDelivery()){
            $nacinDostave = "D";

            /** @var AddressEntity $address */
            $address = $order->getAccountShippingAddress();

            /**
             * Uzmi shipping address sa order item group ako postoji
             */
            if(!empty($orderItemGroup->getAccountShippingAddress())){
                /** @var AddressEntity $address */
                $address = $orderItemGroup->getAccountShippingAddress();
            }

            if(!empty($address->getFirstName()) && !empty($address->getLastName())){
                $deliveryKupacIme = $address->getFirstName();
                $deliveryKupacPrezime = $address->getLastName();
                $deliveryContact = $address->getPhone();
            }
            else{
                $deliveryKupacIme = $order->getContact()->getFirstName();
                $deliveryKupacPrezime = $order->getContact()->getLastName();
                $deliveryContact = $order->getContact()->getPhone();
            }

            if(empty($deliveryContact)){
                $deliveryContact = $order->getContact()->getPhone();
            }

            $deliveryStreet = $address->getStreet();
            $deliveryCity = $address->getCity()->getName();
            $deliveryPostalCode = $address->getCity()->getPostalCode();

            if(!empty($orderItemGroup->getDeliveryServiceRule()) && $orderItemGroup->getDeliveryServiceRule()->getId() == ScommerceConstants::DELIVERY_SERVICE_IN_TIME){
                $deliveryCity = $address->getCity()->getInTimeName();
                $deliveryPostalCode = $address->getCity()->getInTimePostalCode();
            }

            $deliveryCountry = "HRV";
            $idDostavljaca = "Vlastita dostava";
            if(!empty($orderItemGroup->getDeliveryServiceRule())){
                $idDostavljaca = $orderItemGroup->getDeliveryServiceRule()->getRemoteCode();
            }

            $konacna_cijena = 0;
            $postotak_popusta = 0;
            $povratna_naknada = 0;

            /**
             * Ovo je HACK jer su naknadno dodane delivery cijene na order item group
             * Ako je adresa na order item group uzmi cijenu sa order item groupa
             */
            if(!empty($orderItemGroup->getAccountShippingAddress())){

                if(floatval($orderItemGroup->getBasePriceDeliveryTotal()) > 0){
                    $konacna_cijena = $mpc_poslije_popusta = $mpc_prije_popusta = floatval($orderItemGroup->getBasePriceDeliveryTotal());

                    $basePriceWithoutTax = $basePriceWithoutTax + floatval($orderItemGroup->getBasePriceDeliveryWithoutTax());
                    $basePriceTax = $basePriceTax + floatval($orderItemGroup->getBasePriceDeliveryTax());
                    $basePriceTotal = $basePriceTotal + floatval($orderItemGroup->getBasePriceDeliveryTotal());
                }
            }
            elseif(floatval($order->getBasePriceDeliveryTotal()) > 0){

                $konacna_cijena = $mpc_poslije_popusta = $mpc_prije_popusta = floatval($order->getBasePriceDeliveryTotal());

                $basePriceWithoutTax = $basePriceWithoutTax + floatval($order->getBasePriceDeliveryWithoutTax());
                $basePriceTax = $basePriceTax + floatval($order->getBasePriceDeliveryTax());
                $basePriceTotal = $basePriceTotal + floatval($order->getBasePriceDeliveryTotal());
            }

            if($konacna_cijena > 0){

                //$taxPercent = $_ENV["TRS_DELIVERY_TAX_PERCENTAGE"];
                //$iznos_por_osn = floatval($order->getBasePriceDeliveryWithoutTax());

                 $narStavka = Array(
                    "rbr" => $rbr,
                    "id_stavke" => $order->getIncrementId()."_D",
                    "ident_sifra" => $_ENV["TRS_DELIVERY_CODE"],
                    "naziv_artikla" => $_ENV["TRS_DELIVERY_NAME"],
                    "tip_art_amb" => "",
                    "jmj" => "kom",
                    "kolicina" => 1,
                    "mpc_prije_popusta" => $mpc_prije_popusta,
                    "postotak_popusta" => $postotak_popusta,
                    "struktura_popusta" => "",
                    "mpc_poslije_popusta" => $mpc_poslije_popusta,
                    "povratna_naknada" => $povratna_naknada,
                    "konacna_cijena" => $konacna_cijena,
                    "iznos_por_osn" => null,
                    "pdv_postotak" => $_ENV["TRS_DELIVERY_TAX_PERCENTAGE"],
                    "iznos_pdv" => null,
                    "iznos_mp" => $konacna_cijena,
                    "bruto_masa_kg" => 0,
                    "komentar" => ""
                 );

                 $narStavke[] = $narStavka;
                 $rbr++;
            }
        }

        $deliveryComment = $order->getDeliveryMessage();
        if(strlen($deliveryComment) > 999){
            $deliveryComment = StringHelper::substrAtWordBoundary($deliveryComment,999);
        }

        $invoiceComment = $order->getInvoiceMessage();
        if(strlen($invoiceComment) > 999){
            $invoiceComment = StringHelper::substrAtWordBoundary($invoiceComment,999);
        }

        /**
         * Set nacin placanja
         */
        $rbr = 1;
        $narPlacanja[] = $this->getNarPlacanja($order, $basePriceTotal, $rbr);

        $mtWebshop = $_ENV["TRS_MT_WEBSHOP"];
        $sifraPs = $_ENV["TRS_SIFRA_PS"];
        $warehouseCode = $orderItemGroup->getWarehouse()->getCode();
        if(!$_ENV["IS_PRODUCTION_ERP"]){
            $mtWebshop = $warehouseCode = $_ENV["TRS_MT_WEBSHOP_DEV"];
            $sifraPs = $_ENV["TRS_SIFRA_PS_DEV"];
        }

        $operacija = "INSERT";
        if(!empty($orderItemGroup->getTrsOrder())){
            $operacija = "UPDATE";
        }

        $loyaltyId = null;
        if(!empty($order->getLoyaltyCard())){
            $loyaltyId = $order->getLoyaltyCard()->getLoyaltyBroj();
        }

        //porezna osnovica
        //iznos pdv
        //total mpc -- samo ovo cemo slati

        $orderItemGroupData = Array();
        $orderItemGroupData["base_price_total"] = $basePriceTotal;
        if(empty($this->pevexOrderHelperManager)){
            $this->pevexOrderHelperManager = $this->getContainer()->get("pevex_order_helper_manager");
        }

        $this->pevexOrderHelperManager->createUpdateOrderItemGroup($orderItemGroupData, $orderItemGroup, true);


        return [
            "nar_zag" => [
                Array(
                    "trs_dokument_id" => $orderItemGroup->getTrsOrder(),
                    "id_dokumenta" => $orderItemGroup->getId(), //ovo mora biti jedinstveno
                    "operacija" => $operacija,
                    "tip_dokumenta" => $tipDokumenta,
                    "status_dokumenta" => "OK",
                    "broj_dokumenta" => $order->getIncrementId(),
                    "loyalty_id" => $loyaltyId,
                    "kupac_id" => $order->getAccountId(),//85883
                    "kupac_ime" => $kupacIme,
                    "kupac_prezime" => $kupacPrezime,
                    "kupac_ulica" => $order->getAccountBillingStreet(),
                    "kupac_kbr" => "",
                    "kupac_posta_broj" => $order->getAccountBillingCity()->getPostalCode(),
                    "kupac_grad" => $order->getAccountBillingCity()->getName(),
                    "kupac_drzava" => "HRV", // $order->getAccountBillingCity()->getCountry()->getCode(),
                    "kupac_kontakt_tel" => $kupacKontaktTel,
                    "kupac_kontakt_email" => $kupacKontaktEmail,
                    "kupac_komentar" => $invoiceComment,
                    "kupac_oib" => $kupacOib,
                    "datum" => $order->getCreated()->format("d.m.Y."),
                    "iznos_por_osn" => $basePriceWithoutTax,
                    "iznos_pdv" => $basePriceTax,
                    "iznos_mp" => $basePriceTotal,
                    "iznos_valuta" => $order->getCurrency()->getCode(),
                    "bruto_masa_kg" => $brutoMasaKg,
                    "nacin_dostave" => $nacinDostave,
                    "id_dostavljaca" => $idDostavljaca,
                    "dostava_por_osn" => "",
                    "dostava_pdv" => "",
                    "dostava_mp" => "",
                    "dostava_valuta" => "",
                    "dostava_termin" => "",
                    "dostava_ime" => $deliveryKupacIme,
                    "dostava_prezime" => $deliveryKupacPrezime,
                    "dostava_ulica" => $deliveryStreet,
                    "dostava_kbr" => "",
                    "dostava_posta_broj" => $deliveryPostalCode,
                    "dostava_grad" => $deliveryCity,
                    "dostava_drzava" => $deliveryCountry,
                    "dostava_kontakt" => $deliveryContact,
                    "dostava_komentar" => $deliveryComment,
                    "ps" => $sifraPs,
                    "mt_webshop" => $mtWebshop,
                    "mt_isporuke" => $warehouseCode,
                    "poziv_na_broj" => $pozivNaBroj,
                    "nar_stavke" => $narStavke,
                    "nar_placanja" => $narPlacanja
                )
            ]
        ];
    }

    /**
     * @param $preparedOrder
     * @return mixed
     * @throws \Exception
     */
    public function sendOrderItemGroup($preparedOrder)
    {
        $data = $this->getTrsApiData($this->trsOrderRestManager, "dostava_narudzbe", [], $preparedOrder);

        return $data["result"];
    }

    /**
     * @param $trsDokumentId
     * @return mixed
     * @throws \Exception
     */
    public function cancelOrderItemGroup($trsDokumentId)
    {
        $body = [
            "parametri" => Array([
                "trs_dokument_id" => $trsDokumentId
            ])
        ];

        $data = $this->getTrsApiData($this->trsOrderRestManager, "otkazivanje_narudzbe", [], $body);

        return $data["result"];
    }

    /**
     * @param $trsDokumentId
     * @return mixed
     * @throws \Exception
     */
    public function getOrderById($trsDokumentId)
    {
        $params = [
            "trs_dokument_id" => $trsDokumentId
        ];

        $data = $this->getTrsApiData($this->trsOrderRestManager, "dohvat_narudzbe", $params);

        return $data["result"];
    }

    /**
     * @param $trsDokumentId
     * @return mixed
     * @throws \Exception
     */
    public function invoiceOrderItemGroup($trsDokumentId)
    {
        $body = [
            "parametri" => Array([
                "trs_dokument_id" => $trsDokumentId
            ])
        ];

        $data = $this->getTrsApiData($this->trsOrderRestManager, "fakturiranje_narudzbe", [], $body);

        return $data["result"];
    }

    /**
     * @param $oib
     * @return mixed
     * @throws \SoapFault
     */
    public function getPartnerByOib($oib)
    {
        $sifrarnikPartnera = new StdClass();
        $sifrarnikPartnera->PorezniBroj = $oib;

        $request = new StdClass();
        $request->SifrarnikPartnera = $sifrarnikPartnera;

        $data = $this->getTrsSoapApiData("OPPSifrarnikPartnera", $request, $this->soapApiDatabase1);

        return $data["result"];
    }

    /**
     * @return mixed
     * @throws \SoapFault
     */
    public function getLoyaltyUsers()
    {
        $dohvatRNKlijenata = new StdClass();
        $dohvatRNKlijenata->LoyaltyID = "";
        $dohvatRNKlijenata->LoyaltyEAN = "";
        $dohvatRNKlijenata->Email = "";
        $dohvatRNKlijenata->DatumZadnjeIzmjeneOd = "";
        $dohvatRNKlijenata->RownumKlijentaOd = 1;
        $dohvatRNKlijenata->RownumKlijentaDo = 1000;

        $request = new StdClass();
        $request->DohvatRNKlijenata = $dohvatRNKlijenata;

        $data = $this->getTrsSoapApiData("DohvatRNKlijenata", $request, $this->soapApiDatabase1);

        return $data["result"];
    }

    /**
     * @return mixed
     * @throws \SoapFault
     */
    public function getLoyaltyPrices()
    {
        $loyaltyCjenici = new StdClass();
        $loyaltyCjenici->SifMT = "0037";
        $loyaltyCjenici->IDLoyaltyGrupe = "35";
        $loyaltyCjenici->SifraArtikla = "";

        $request = new StdClass();
        $request->LoyaltyCjenici = $loyaltyCjenici;

        $data = $this->getTrsSoapApiData("TRSTriveLoyaltyCjenici", $request, $this->soapApiDatabase2);

        return $data["result"];
    }

    /**
     * @return array
     * @throws \SoapFault
     * @deprecated
     */
    public function importLoyaltyPrices()
    {
        if (!empty($this->getConsoleOutput())) {
            echo "Importing loyalty prices...\n";
        }

        $existingProducts = $this->getEntitiesArray(["id", "code"], "product_entity", ["code"]);
        $existingAccountGroups = $this->getEntitiesArray(["id", "remote_id"], "account_group_entity", ["remote_id"]);
        $existingProductAccountGroupPrices = $this->getEntitiesArray(["id", "product_id", "account_group_id", "date_valid_from", "date_valid_to", "discount_price_base", "discount_price_retail"], "product_account_group_price_entity", ["product_id", "account_group_id"], "", "WHERE entity_state_id = 1");

        $insertArray = [
            // product_account_group_price_entity
        ];
        $updateArray = [
            // product_account_group_price_entity
        ];
        $deleteArray = [
            // product_account_group_price_entity
        ];
        $productIds = [];

        foreach ($existingProductAccountGroupPrices as $key => $existingProductAccountGroupPrice) {
            $deleteArray["product_account_group_price_entity"][$key] = [
                "id" => $existingProductAccountGroupPrice["id"],
                "product_id" => $existingProductAccountGroupPrice["product_id"]
            ];
        }

        if (!empty($this->getConsoleOutput())) {
            $progressBar = new ProgressBar($this->getConsoleOutput());
        }

        $data = $this->getLoyaltyPrices();
        if (!empty($data)) {

            foreach ($data as $d) {

                if (!empty($this->getConsoleOutput())) {
                    $progressBar->advance();
                }

                if (!isset($existingProducts[$d["SifraArtikla"]])) {
                    continue;
                }
                if (!isset($existingAccountGroups[$d["IDLoyaltyGrupe"]])) {
                    continue;
                }

                $dtDateDiscountFrom = \DateTime::createFromFormat("d.m.Y.", $d["DatVrijediOd"]);
                $dtDateDiscountTo = \DateTime::createFromFormat("d.m.Y.", $d["DatVrijediDo"]);
                $discountPriceBase = $d["LoyaltyMpc"];
                $discountPriceRetail = $d["MpcUProdaji"];

                $productAccountGroupPriceKey = $existingProducts[$d["SifraArtikla"]]["id"] . "_" . $existingAccountGroups[$d["IDLoyaltyGrupe"]]["id"];
                if (!isset($existingProductAccountGroupPrices[$productAccountGroupPriceKey])) {

                    $productAccountGroupPriceInsert = new InsertModel($this->asProductAccountGroupPrice);
                    $productAccountGroupPriceInsert->add("product_id", $existingProducts[$d["SifraArtikla"]]["id"])
                        ->add("account_group_id", $existingAccountGroups[$d["IDLoyaltyGrupe"]]["id"])
                        ->add("date_valid_from", $dtDateDiscountFrom->format("Y-m-d"))
                        ->add("date_valid_to", $dtDateDiscountTo->format("Y-m-d"))
                        ->add("discount_price_base", $discountPriceBase)
                        ->add("discount_price_retail", $discountPriceRetail);

                    $insertArray["product_account_group_price_entity"][$productAccountGroupPriceKey] = $productAccountGroupPriceInsert->getArray();
                    $productIds[] = $existingProducts[$d["SifraArtikla"]]["id"];

                } else {

                    $productAccountGroupPriceUpdate = new UpdateModel($existingProductAccountGroupPrices[$productAccountGroupPriceKey]);
                    $productAccountGroupPriceUpdate->add("date_valid_from", $dtDateDiscountFrom->format("Y-m-d") . " 00:00:00")
                        ->add("date_valid_to", $dtDateDiscountTo->format("Y-m-d") . " 00:00:00")
                        ->addFloat("discount_price_base", $discountPriceBase)
                        ->addFloat("discount_price_retail", $discountPriceRetail);

                    if (!empty($productAccountGroupPriceUpdate->getArray())) {
                        $updateArray["product_account_group_price_entity"][$productAccountGroupPriceUpdate->getEntityId()] = $productAccountGroupPriceUpdate->getArray();
                        $productIds[] = $existingProductAccountGroupPrices[$productAccountGroupPriceKey]["product_id"];
                    }

                    unset($deleteArray["product_account_group_price_entity"][$productAccountGroupPriceKey]);
                }
            }
        }

        if (!empty($this->getConsoleOutput())) {
            $progressBar->finish();
            echo "\n";
        }

        unset($existingProducts);
        unset($existingAccountGroups);
        unset($existingProductAccountGroupPrices);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        if (isset($deleteArray["product_account_group_price_entity"])) {
            foreach ($deleteArray["product_account_group_price_entity"] as $productAccountGroupPrice) {
                $productIds[] = $productAccountGroupPrice["product_id"];
            }
        }

        $this->executeDeleteQuery($deleteArray);
        unset($deleteArray);

        $ret = [];
        if (!empty($productIds)) {
            $ret["product_ids"] = array_unique($productIds);
        }

        if (!empty($this->getConsoleOutput())) {
            echo "Importing loyalty prices complete\n";
        }

        return $ret;
    }

    /**
     * TRS vraća vrijeme u obliku Y-m-d\TH:i:s\Z
     * Najbrže ćemo preoblikovati u MySQL format uklanjanjem \T i \Z
     *
     * @param $time
     * @return array|string|string[]
     */
    private function formatTime($time)
    {
        return str_replace("T", " ", str_replace("Z", "", $time));
    }

    /**
     * Potrebno dovršiti da koristi nedavne promjene i zatim testirati
     *
     * @return array
     * @throws \Exception
     */
    public function importLoyaltyUsers($args = [])
    {
        if (!empty($this->getConsoleOutput())) {
            echo "Importing loyalty users...\n";
        }

        $logFiles = Array();

        $loyaltyCardSelectColumns = [
            "id",
            "id_klijenta",
            "loyalty_broj",
            "ime",
            "prezime",
            "datum_rodenja",
            "spol",
            "broj_clanova_kucanstva",
            "ulica",
            "kucni_broj",
            "dopuna_adrese",
            "postanski_broj",
            "mjesto",
            "sif_drzave",
            "drzava",
            "sifra_grupe",
            "sifra_podgrupe",
            "jezik",
            "vrsta_osobe",
            "naziv_pravne_osobe",
            "oib",
            "card_number",
            "aktivno_od",
            "aktivno_do",
            "vrsta_kartice",
            "osnovna_kartica",
            "naziv_kartice",
            "gdpr_email_from",
            "gdpr_email_to",
            "gdpr_sms_from",
            "gdpr_sms_to",
            "gdpr_viber_from",
            "gdpr_viber_to",
            "gdpr_mail_from",
            "gdpr_mail_to",
            "gdpr_phone_from",
            "gdpr_phone_to",
            "contact_address",
            "contact_email",
            "contact_phone",
            "contact_fax",
            "contact_mobile",
            "contact_mobile_clean",
            "contact_phone_clean"
        ];

        $where = "";
        if(isset($args["ean_kartice"]) && !empty($args["ean_kartice"])){
            $where = " WHERE card_number = '{$args["ean_kartice"]}' ";
        }

        $existingLoyaltyCards = $this->getEntitiesArray($loyaltyCardSelectColumns, "loyalty_card_entity", ["card_number"], "", $where);

        $insertArray = [
            // loyalty_card_entity
        ];
        $updateArray = [
            // loyalty_card_entity
        ];

        if (!empty($this->getConsoleOutput())) {
            $progressBar = new ProgressBar($this->getConsoleOutput());
        }

        $params = [];
        $params["sif_sustava"] = "SHIPSHAPE";
//        $params["vrijeme_izmjene_od"] = "";
//        $params["id_klijenta"] = "";
//        $params["loyalty_broj"] = "";
//        $params["ean_kartice"] = "";
//        $params["ime"] = "";
//        $params["prezime"] = "";
//        $params["email"] = "";
//        $params["telefon"] = "";
//        $params["datum_rodenja"] = "";
//        $params["ulica"] = "";
//        $params["kucni_broj"] = "";
//        $params["mjesto"] = "";
//        $params["postanski_broj"] = "";
        $params["rownum_od"] = 1;
        $params["rownum_do"] = 1000;
//        $params["dodatni_uvjet"] = "";

        if(isset($args["ean_kartice"]) && !empty($args["ean_kartice"])){
            $params["ean_kartice"] = $args["ean_kartice"];
        }
        if(isset($args["vrijeme_izmjene_od"]) && !empty($args["vrijeme_izmjene_od"])){
            $params["vrijeme_izmjene_od"] = $args["vrijeme_izmjene_od"];
        }

        $changedIds = [];
        $changedRemoteIds = [];
        $inactiveIds = [];

        $dtNow = new \DateTime();

        if(empty($this->pevexLoyaltyManager)){
            $this->pevexLoyaltyManager = $this->container->get("pevex_loyalty_manager");
        }

        do {
            $data = $this->getTrsApiData($this->trsRestManager, "klijenti", $params); //json_decode(file_get_contents("/home/shapedev/public_html/pevex/var/logs/loyalty_users.json"), true);

            if(isset($data["log_file"])){
                $logFiles[] = $data["log_file"];
            }
            $data = $data["result"];

            if (!empty($data)) {

                if (!empty($this->getConsoleOutput())) {
                    $progressBar->advance();
                }

                foreach ($data as $d) {

                    foreach ($d["kartice"] as $d2) {

                        $loyaltyCardImport = NULL;
                        if (!isset($existingLoyaltyCards[$d2["ean"]])) {
                            $loyaltyCardImport = new InsertModel($this->asLoyaltyCard);
                            $loyaltyCardImport
                            /**
                             * Polja sa privola (fallback vrijednosti)
                             */
                            ->add("gdpr_email_from", NULL)
                            ->add("gdpr_email_to", NULL)
                            ->add("gdpr_sms_from", NULL)
                            ->add("gdpr_sms_to", NULL)
                            ->add("gdpr_viber_from", NULL)
                            ->add("gdpr_viber_to", NULL)
                            ->add("gdpr_mail_from", NULL)
                            ->add("gdpr_mail_to", NULL)
                            ->add("gdpr_phone_from", NULL)
                            ->add("gdpr_phone_to", NULL)
                            /**
                             * Polja sa kontakta (fallback vrijednosti)
                             */
                            ->add("contact_address", NULL)
                            ->add("contact_email", NULL)
                            ->add("contact_phone", NULL)
                            ->add("contact_fax", NULL)
                            ->add("contact_mobile", NULL)
                            ->add("contact_mobile_clean", NULL)
                            ->add("contact_phone_clean", NULL);
                        } else {
                            $loyaltyCardImport = new UpdateModel($existingLoyaltyCards[$d2["ean"]]);
                        }

                        $loyaltyCardImport
                            /**
                             * Polja sa klijenta
                             */
                            ->add("id_klijenta", $d["id_klijenta"])
                            ->add("loyalty_broj", $d["loyalty_broj"])
                            ->add("ime", $d["ime"])
                            ->add("prezime", $d["prezime"])
                            ->add("datum_rodenja", $this->formatTime($d["datum_rodenja"]))
                            ->add("spol", $d["spol"])
                            ->add("broj_clanova_kucanstva", $d["broj_clanova_kucanstva"])
                            ->add("ulica", $d["ulica"])
                            ->add("kucni_broj", $d["kucni_broj"])
                            ->add("dopuna_adrese", $d["dopuna_adrese"])
                            ->add("postanski_broj", $d["postanski_broj"])
                            ->add("mjesto", $d["mjesto"])
                            ->add("sif_drzave", $d["sif_drzave"])
                            ->add("drzava", $d["drzava"])
                            ->add("sifra_grupe", $d["sifra_grupe"])
                            ->add("sifra_podgrupe", $d["sifra_podgrupe"])
                            ->add("jezik", $d["jezik"])
                            ->add("vrsta_osobe", $d["vrsta_osobe"])
                            ->add("naziv_pravne_osobe", $d["naziv_pravne_osobe"])
                            ->add("oib", $d["oib"])
                            /**
                             * Polja sa kartice
                             */
                            ->add("card_number", $d2["ean"])
                            ->add("aktivno_od", $this->formatTime($d2["aktivno_od"]))
                            ->add("aktivno_do", $this->formatTime($d2["aktivno_do"]))
                            ->add("vrsta_kartice", $d2["vrsta_kartice"])
                            ->add("osnovna_kartica", ($d2["osnovna_kartica"] == "DA"))
                            ->add("naziv_kartice", $d2["naziv_kartice"]);

                        foreach ($d["privole"] as $d3) {

                            if ($d3["svrha"] == "GDPR_EMAIL") {
                                $loyaltyCardImport->add("gdpr_email_from", $this->formatTime($d3["aktivno_od"]))
                                    ->add("gdpr_email_to", $this->formatTime($d3["aktivno_do"]));
                            } else if ($d3["svrha"] == "GDPR_SMS") {
                                $loyaltyCardImport->add("gdpr_sms_from", $this->formatTime($d3["aktivno_od"]))
                                    ->add("gdpr_sms_to", $this->formatTime($d3["aktivno_do"]));
                            } else if ($d3["svrha"] == "GDPR_VIBER") {
                                $loyaltyCardImport->add("gdpr_viber_from", $this->formatTime($d3["aktivno_od"]))
                                    ->add("gdpr_viber_to", $this->formatTime($d3["aktivno_do"]));
                            } else if ($d3["svrha"] == "GDPR_POSTA") {
                                $loyaltyCardImport->add("gdpr_mail_from", $this->formatTime($d3["aktivno_od"]))
                                    ->add("gdpr_mail_to", $this->formatTime($d3["aktivno_do"]));
                            } else if ($d3["svrha"] == "GDPR_TEL") {
                                $loyaltyCardImport->add("gdpr_phone_from", $this->formatTime($d3["aktivno_od"]))
                                    ->add("gdpr_phone_to", $this->formatTime($d3["aktivno_do"]));
                            }
                        }

                        foreach ($d["kontakti"] as $d4) {

                            if (!empty($d4["kontakt"]) && $d4["kontakt"] != "-") {
                                if ($d4["tip_kontakta"] == "ADR") {
                                    $loyaltyCardImport->add("contact_address", $d4["kontakt"]);
                                } else if ($d4["tip_kontakta"] == "EMAIL") {
                                    $loyaltyCardImport->add("contact_email", trim($d4["kontakt"]));
                                } else if ($d4["tip_kontakta"] == "MOB") {
                                    $loyaltyCardImport->add("contact_mobile", $d4["kontakt"]);
                                    $clean =  StringHelper::cleanPhone($d4["kontakt"]);
                                    $loyaltyCardImport->add("contact_mobile_clean", $clean);
                                } else if ($d4["tip_kontakta"] == "TEL") {
                                    $loyaltyCardImport->add("contact_phone", $d4["kontakt"]);
                                    $clean =  StringHelper::cleanPhone($d4["kontakt"]);
                                    $loyaltyCardImport->add("contact_phone_clean", $clean);
                                } else if ($d4["tip_kontakta"] == "FAX") {
                                    $loyaltyCardImport->add("contact_fax", $d4["kontakt"]);
                                }
                            }
                        }

                        if (!empty($d2["aktivno_do"])) {
                            $dtActiveUntil = \DateTime::createFromFormat("Y-m-d\TH:i:s\Z", $d2["aktivno_do"]);
                            if ($dtNow > $dtActiveUntil && isset($existingLoyaltyCards[$d2["ean"]])) {
                                $inactiveIds[] = $loyaltyCardImport->getEntityId();
                            }
                        }

                        if (!isset($existingLoyaltyCards[$d2["ean"]])) {
                            $loyaltyCardImport->add("date_synced", "NOW()");
                            $insertArray["loyalty_card_entity"][$d2["ean"]] = $loyaltyCardImport->getArray();
                            $changedRemoteIds[] = $d2["ean"];
                        } else {
                            if (!empty($loyaltyCardImport->getArray())) {
                                $changedIds[] = $loyaltyCardImport->getEntityId();
                            }
                            $loyaltyCardImport->add("date_synced", "NOW()", false);
                            $updateArray["loyalty_card_entity"][$loyaltyCardImport->getEntityId()] = $loyaltyCardImport->getArray();
                        }
                    }
                }
            }

            $params["rownum_od"] = $params["rownum_do"] + 1;
            $params["rownum_do"] = $params["rownum_od"] + 999;

            $this->databaseContext->reconnectToDatabase();

            //break;

        } while (!empty($data) && count($data) >= 1000);

        if (!empty($this->getConsoleOutput())) {
            $progressBar->finish();
            echo "\n";
        }

        unset($existingLoyaltyCards);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $reselectArray["loyalty_card_entity"] = $this->getEntitiesArray(["id", "card_number"], "loyalty_card_entity", ["card_number"], "", "WHERE entity_state_id = 1");

        $ret = [
            "changed_ids" => $this->resolveChangedProducts($changedIds, $changedRemoteIds, $reselectArray["loyalty_card_entity"]),
            "inactive_ids" => $inactiveIds
        ];
        $ret["log_files"] = $logFiles;

        unset($reselectArray);

        if (!empty($this->getConsoleOutput())) {
            echo "Importing loyalty users complete\n";
        }

        return $ret;
    }

    /**
     * Copy pastano iz emaila, vjerojatno je moguće dodati i opcionalne parametre iz primjera na dnu, nije testirano
     *
     * @param $data
     * @return array|false|string|string[]
     */
    private function getLoyaltyUserXml($data)
    {
        $XMLDoc = new DOMDocument('1.0', 'UTF-8');
        $XMLDoc->preserveWhiteSpace = false;
        $XMLDoc->formatOutput = true;

        $bodyDocPrijave = $XMLDoc->createElement('ns1:PrijaveNaNewsletter');
        $bodyDocPrijava = $XMLDoc->createElement('PrijavaNaNewsletter');

        $bodyDocPrijava->appendChild($XMLDoc->createElement('BrojLoyaltyKartice', null));

        $bodyDocPrijave->appendChild(
            $XMLDoc->createElement(
                "Operater",
                "shipshape"
            )
        );

        $bodyDocPrijava->appendChild($XMLDoc->createElement('SifraNacinaDobivanjaPrivole', '1'));
        $bodyDocPrijava->appendChild($XMLDoc->createElement('Drzava', 'HRV'));
        $bodyDocPrijava->appendChild($XMLDoc->createElement('Jezik', 'HRV'));
        $bodyDocPrijava->appendChild($XMLDoc->createElement('TipSmjestaja', null));
        $bodyDocPrijava->appendChild($XMLDoc->createElement('Interes', null));
        $bodyDocPrijava->appendChild($XMLDoc->createElement('Hobi', null));
        $bodyDocPrijava->appendChild($XMLDoc->createElement('ObiteljskiStatus', null));

        //Mandatory fields
        $bodyDocPrijava->appendChild($XMLDoc->createElement('SifraIzvoraPodataka', $data["sifra_izvora"]));
        $bodyDocPrijava->appendChild($XMLDoc->createElement('Email', $data["email"]));
        $bodyDocPrijava->appendChild($XMLDoc->createElement('Ime', $data["ime"]));
        $bodyDocPrijava->appendChild($XMLDoc->createElement('Prezime', $data["prezime"]));
        if (isset($data["kontakt_telefon"]) && !empty($data["kontakt_telefon"])) {
            $bodyDocPrijava->appendChild($XMLDoc->createElement('BrojMobitela', $data["kontakt_telefon"]));
        }
        if (isset($data["fiksni_telefon"]) && !empty($data["fiksni_telefon"])) {
            $bodyDocPrijava->appendChild($XMLDoc->createElement('TelefonskiBroj', $data["fiksni_telefon"]));
        }
        if ($data["datum_rodenja"]) {
            $bodyDocPrijava->appendChild($XMLDoc->createElement('DatumRodjenja', date("d-m-Y", strtotime($data["datum_rodenja"]))));
        }
        if ($data["spol"]) {
            $spol = $XMLDoc->createElement('Spol');
            if ($data["spol"] == 'M') {
                $spol->appendChild($XMLDoc->createElement('Muski', '1'));
            } else {
                $spol->appendChild($XMLDoc->createElement('Zenski', '1'));
            }
            $bodyDocPrijava->appendChild($spol);
        }

        $bodyDocPrijava->appendChild($XMLDoc->createElement('Ulica', rtrim(ltrim($data["ulica"]))));
        $bodyDocPrijava->appendChild($XMLDoc->createElement('KucniBroj', $data["house_number"]));
        $bodyDocPrijava->appendChild($XMLDoc->createElement('Grad', $data["city_name"]));
        $bodyDocPrijava->appendChild($XMLDoc->createElement('SifraPoste', $data["ptt_broj"]));
        $bodyDocPrijava->appendChild($XMLDoc->createElement('DopunaAdrese', $data["naselje"]));
        $bodyDocPrijava->appendChild($XMLDoc->createElement('BrojClanovaKucanstva', rtrim(ltrim($data["broj_clanova_kucanstva"]))));
        $bodyDocPrijava->appendChild($XMLDoc->createElement('Komentar', $data["napomena"]));
        $dodatniPodaci = $XMLDoc->createElement('DodatniPodaci');

        $privole = [];
        $privole['GDPR_EMAIL'] = $data["gdpr_email"] ? "DA" : "NE";
        $privole['GDPR_SMS'] = $data["gdpr_sms"] ? "DA" : "NE";
        $privole['GDPR_VIBER'] = $data["gdpr_viber"] ? "DA" : "NE";
        $privole['GDPR_POSTA'] = $data["gdpr_posta"] ? "DA" : "NE";
        $privole['GDPR_TEL'] = $data["gdpr_tel"] ? "DA" : "NE";

        foreach ($privole as $privola => $value) {
            $dodatniPodatak = $XMLDoc->createElement('DodatniPodatak');
            $dodatniPodatakTip = $XMLDoc->createElement('Tip', $privola);
            $dodatniPodatakVrijednost = $XMLDoc->createElement('Vrijednost', strtoupper($value));
            $dodatniPodatak->appendChild($dodatniPodatakTip);
            $dodatniPodatak->appendChild($dodatniPodatakVrijednost);
            $dodatniPodaci->appendChild($dodatniPodatak);
        }

        $bodyDocPrijava->appendChild($dodatniPodaci);
        $bodyDocPrijave->appendChild($bodyDocPrijava);
        $XMLDoc->appendChild($bodyDocPrijave);

        $xml = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $XMLDoc->saveXML());

//        $prijava->PrijavaNaNewsletter->BrojLoyaltyKartice                              = '00298568';           //optional
//        $prijava->PrijavaNaNewsletter->KomentarKartice                                 = 'KTC klub vjernosti'; //optional
//        $prijava->PrijavaNaNewsletter->Email                                           = 'pero.peric@net.hr';
//        $prijava->PrijavaNaNewsletter->Ime                                             = 'Ivan';
//        $prijava->PrijavaNaNewsletter->Prezime                                         = 'Ivanović';
//        $prijava->PrijavaNaNewsletter->Spol->Muski                                     = 1;
//        $prijava->PrijavaNaNewsletter->Spol->Zenski                                    = 0;
//        $prijava->PrijavaNaNewsletter->Spol->Neizjasnjeni                              = 0;                    //optional
//        $prijava->PrijavaNaNewsletter->DatumRodjenja                                   = '10.11.1978.';        //optional
//        $prijava->PrijavaNaNewsletter->Drzava                                          = 'Hrvatska';           //optional
//        $prijava->PrijavaNaNewsletter->Ulica                                           = 'Trg Petra Zrinskog'; //optional
//        $prijava->PrijavaNaNewsletter->KucniBroj                                       = '1';                  //optional
//        $prijava->PrijavaNaNewsletter->SifraPoste                                      = '10360';              //optional
//        $prijava->PrijavaNaNewsletter->Grad                                            = 'Vrbovec';            //optional
//        $prijava->PrijavaNaNewsletter->DopunaAdrese                                    = '';                   //optional
//        $prijava->PrijavaNaNewsletter->TelefonskiBroj                                  = '';                   //optional
//        $prijava->PrijavaNaNewsletter->BrojMobitela                                    = '0955297572';         //optional
//        $prijava->PrijavaNaNewsletter->Jezik                                           = 'Hrvatski';
//        $prijava->PrijavaNaNewsletter->TipSmjestaja                                    = '';
//        $prijava->PrijavaNaNewsletter->Interes                                         = '';
//        $prijava->PrijavaNaNewsletter->ObiteljskiStatus                                = '';                   //optional
//        $prijava->PrijavaNaNewsletter->InfoGrupe->InfoGrupa->ID                        = 1;                    //optional
//        $prijava->PrijavaNaNewsletter->InfoGrupe->InfoGrupa->Sadrzaji->Sadrzaj->Sifra  = '11';                 //optional
//        $prijava->PrijavaNaNewsletter->Hobi                                            = 'Biciklizam';
//        $prijava->PrijavaNaNewsletter->SifraNacinaDobivanjaPrivole                     = '1';
//        $prijava->PrijavaNaNewsletter->SifraIzvoraPodataka                             = '002';                //optional
//        $prijava->PrijavaNaNewsletter->PrivolaZaNewsletter                             = 1;                    //optional
//        $prijava->PrijavaNaNewsletter->BrojDanaVazenjaTicketa                          = 0;                    //optional
//        $prijava->PrijavaNaNewsletter->TicketPassword                                  = '';                   //optional
//        $prijava->PrijavaNaNewsletter->IDReferenta                                     = 0;                    //optional
//        $prijava->PrijavaNaNewsletter->LokacijaSlike                                   = '';                   //optional

        return $xml;
    }

    /**
     * Potrebno testirati
     *
     * @return mixed
     * @throws \SOAPFault
     */
    public function sendLoyaltyUser($data)
    {
        if(empty($data)){
            throw new \Exception("Empty loyalty request data");
        }

        $data["sifra_izvora"] = "002";
        if(!isset($data["email"])){
            throw new \Exception("Missing email");
        }
        if(!isset($data["ime"])){
            throw new \Exception("Missing email");
        }
        if(!isset($data["prezime"])){
            throw new \Exception("Missing email");
        }
        if(!isset($data["kontakt_telefon"])){
            $data["kontakt_telefon"] = "";
        }
        if(!isset($data["fiksni_telefon"])){
            $data["fiksni_telefon"] = "";
        }
        if(!isset($data["city_name"])){
            $data["city_name"] = "";
        }
        if(!isset($data["house_number"])){
            $data["house_number"] = "";
        }
        if(!isset($data["datum_rodenja"])){
            $data["datum_rodenja"] = "";
        }
        if(!isset($data["spol"])){
            $data["spol"] = "";
        }
        if(!isset($data["ulica"])){
            $data["ulica"] = "";
        }
        if(!isset($data["ptt_broj"])){
            $data["ptt_broj"] = "";
        }
        if(!isset($data["naselje"])){
            $data["naselje"] = "";
        }
        if(!isset($data["broj_clanova_kucanstva"])){
            $data["broj_clanova_kucanstva"] = "";
        }
        if(!isset($data["gdpr_email"])){
            $data["gdpr_email"] = "";
        }
        if(!isset($data["gdpr_sms"])){
            $data["gdpr_sms"] = "";
        }
        if(!isset($data["gdpr_viber"])){
            $data["gdpr_viber"] = "";
        }
        if(!isset($data["gdpr_posta"])){
            $data["gdpr_posta"] = "";
        }
        if(!isset($data["gdpr_tel"])){
            $data["gdpr_tel"] = "";
        }
        if(!isset($data["napomena"])){
            $data["napomena"] = "";
        }
        /*$data["email"] = "";
        $data["ime"] = "";
        $data["prezime"] = "";
        $data["kontakt_telefon"] = "";
        $data["datum_rodenja"] = "";
        $data["spol"] = "";
        $data["ulica"] = "";
        $data["ptt_broj"] = "";
        $data["broj_clanova_kucanstva"] = "";
        $data["gdpr_email"] = "";
        $data["gdpr_sms"] = "";
        $data["gdpr_viber"] = "";
        $data["gdpr_posta"] = "";
        $data["gdpr_tel"] = "";*/

        $xml = $this->getLoyaltyUserXml($data);

        $request = new \SOAPVar($xml, XSD_ANYXML);

        $importLogData = array();
        $importLogData['completed'] = 0;
        $importLogData['name'] = 'prijava_na_newsletter';
        $importLogData['params'] = $xml;

        if (empty($this->errorLogManager)) {
            $this->errorLogManager = $this->getContainer()->get("error_log_manager");
        }

        try{
            $res = $this->getTrsSoapApiData("PrijaveNaNewsletter", $request, $this->soapApiDatabase1);
        }
        catch (\Exception $e){

            $importLogData['error_log'] = $e->getMessage();
            $this->errorLogManager->insertImportLog($importLogData,false);

            throw $e;
        }

        $importLogData['response_data'] = json_encode($res);

        if(!isset($res["LoyaltyEanKlijenta"]) || empty($res["LoyaltyEanKlijenta"])){
            $importLogData['error_log'] = "Greška prilikom stvaranja loyalty kartice";
        }
        else{
            $importLogData['completed'] = 1;
        }

        $this->errorLogManager->insertImportLog($importLogData,false);

        return $res;
    }
}