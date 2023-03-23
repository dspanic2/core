<?php

namespace DPDBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\FileHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\RestManager;
use DateTime;
use DPDBusinessBundle\Entity\DpdCollectionRequestEntity;
use DPDBusinessBundle\Entity\DpdParcelEntity;
use DPDBusinessBundle\Entity\DpdParcelNumbersEntity;
use DPDBusinessBundle\Entity\DpdParcelStatusEntity;
use DPDBusinessBundle\Events\DpdParcelCreatedEvent;
use DPDBusinessBundle\Events\DpdParcelNumberStatusChangedEvent;
use Exception;
use ScommerceBusinessBundle\Entity\OrderReturnEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;

class DPDManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;

    protected $username;
    protected $password;
    protected $secret;

    protected $webPath;

    /** @var $targetDir */
    protected $targetDir;

    public function initialize()
    {
        parent::initialize();

        /**
         * Postoji username, password i secret koji služi za određene metode (uvijek je isti)
         */
        $this->username = $_ENV["DPD_USERNAME"];
        $this->password = $_ENV["DPD_PASSWORD"];
        $this->secret = $_ENV["DPD_SECRET"];

        $this->webPath = $_ENV["WEB_PATH"];

        $this->targetDir = "/Documents/dpd_parcel/";
    }

    /**
     * @param $headers
     * @return mixed|string|null
     */
    public function extractInfoFromResponseHeaders($headers, $info = 'filename')
    {
        $filename = null;

        $separator = '';
        if ($info == 'filename') {
            $separator = "Content-Disposition: attachment; filename=";
        } else if ($info == 'length') {
            $separator = "Content-Length: ";
        }

        $headers = explode("\r\n", $headers);
        if (!empty($headers)) {
            foreach ($headers as $header) {
                $header = explode($separator, $header);

                if (sizeof($header) > 1) {
                    $filename = $header[1];
                    break;
                }
            }
        }

        return $filename;
    }

    /**
     * @param DpdCollectionRequestEntity $dpdCollectionRequest
     * @return array|bool
     */
    public function requestDPDCollectionRequest(DpdCollectionRequestEntity $dpdCollectionRequest){

        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;

        /**
         * Export parcels need to have contact info
         */
        if ($dpdCollectionRequest->getSenderCountryCode() != "HR") {
            $ret["message"] = "Country code must be HR";
            return $ret;
        }

        if (empty($dpdCollectionRequest->getReceiverEmail()) || empty($dpdCollectionRequest->getReceiverPhone())) {
            $ret["message"] = "Recipient email or phone cannot be empty";
            return $ret;
        }

        $data = array();
        $data["username"] = $this->username;
        $data["password"] = $this->password;

        $data["cname"] = substr($dpdCollectionRequest->getSenderName(),0,35);

        if (!empty($dpdCollectionRequest->getSenderContactName())) {
            $data["cname1"] = substr($dpdCollectionRequest->getSenderContactName(),0,35);
        }

        $data["cstreet"] = substr($dpdCollectionRequest->getSenderStreet(),0,35);
        $data["cPropertyNumber"] = substr($dpdCollectionRequest->getSenderHouseNumber(),0,8);
        $data["ccity"] = substr($dpdCollectionRequest->getSenderCity(),0,25);
        $data["cpostal"] = substr($dpdCollectionRequest->getSenderPostalCode(),0,8);
        $data["ccountry"] = substr($dpdCollectionRequest->getSenderCountryCode(),0,2);
        $data["cphone"] = substr($dpdCollectionRequest->getSenderPhone(),0,20);
        $data["cemail"] = substr($dpdCollectionRequest->getSenderEmail(),0,30);

        if (!empty($dpdCollectionRequest->getDeliveryRemark1())) {
            $data["info1"] = substr($dpdCollectionRequest->getDeliveryRemark1(),0,30);
        }

        if (!empty($dpdCollectionRequest->getDeliveryRemark2())) {
            $data["info2"] = substr($dpdCollectionRequest->getDeliveryRemark2(),0,30);
        }

        $data["rname"] = substr($dpdCollectionRequest->getReceiverName(),0,35);

        if (!empty($dpdCollectionRequest->getReceiverContactPerson())) {
            $data["rname2"] = substr($dpdCollectionRequest->getReceiverContactPerson(),0,35);
        }

        $data["rstreet"] = substr($dpdCollectionRequest->getReceiverStreet(),0,35);
        $data["rPropertyNumber"] = substr($dpdCollectionRequest->getReceiverHouseNumber(),0,8);
        $data["rcity"] = substr($dpdCollectionRequest->getReceiverCity(),0,25);
        $data["rpostal"] = substr($dpdCollectionRequest->getReceiverPostalCode(),0,8);
        $data["rcountry"] = substr($dpdCollectionRequest->getReceiverCountryCode(),0,2);
        $data["rphone"] = substr($dpdCollectionRequest->getReceiverPhone(),0,20);
        $data["remail"] = substr($dpdCollectionRequest->getReceiverEmail(),0,30);

        $data["pickup_date"] = $dpdCollectionRequest->getPickupDate()->format("Ymd");

        $params = http_build_query($data);
        $url = "https://easyship.hr/api/collection_request/cr_import" . "?" . $params;

        $apiResponse = $this->requestDpdApiRequest($url);
        if ($apiResponse['error'] === true) {
            $ret["message"] = $apiResponse["message"];
            return $ret;
        }

        $response = $apiResponse["result"];

        if (!empty($response["errlog"]) || strtolower($response["status"]) !== "ok") {
            $dpdCollectionRequest->setErrorDescription($response["errlog"]);
            $this->saveDPDEntity($dpdCollectionRequest);

            $ret["message"] = $response["errlog"];
            return $ret;
        }

        $dpdCollectionRequest->setReference($response["reference"]);
        $dpdCollectionRequest->setErrorDescription(null);
        $this->saveDPDEntity($dpdCollectionRequest);

        $ret["error"] = false;
        return $ret;
    }

    /**
     * @param DpdParcelEntity $dpdParcel
     * @return mixed|null
     * parcel_import je "glavna" funkcija pomoću koje se šalju svi podaci vezani za order.
     * Uglavnom generira parcel_numbere
     */
    public function requestDPDParcel(DpdParcelEntity $dpdParcel)
    {
        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;

        /**
         * Export parcels need to have contact info
         */
        if ($dpdParcel->getCountry() != "HR") {
            $ret["message"] = "Country code must be HR";
            return $ret;
        }

        if (empty($dpdParcel->getRecipientEmail()) || empty($dpdParcel->getRecipientPhone())) {
            $ret["message"] = "Recipient email or phone cannot be empty";
            return $ret;
        }

        $data = array();
        $data["username"] = $this->username;
        $data["password"] = $this->password;
        $data["name1"] = $dpdParcel->getRecipientName();

        if(isset($data["name1"]) && strlen($data["name1"]) > 35){
            $data["name1"] = substr($data["name1"],0,35);
        }

        if (!empty($dpdParcel->getRecipientName2())) {
            $data["name2"] = $dpdParcel->getRecipientName2();
        }
        if(isset($data["name2"]) && strlen($data["name2"]) > 35){
            $data["name2"] = substr($data["name2"],0,35);
        }

        if (!empty($dpdParcel->getContactInformation())) {
            $data["contact"] = $dpdParcel->getContactInformation();
        }

        $data["street"] = $dpdParcel->getRecipientStreet();
        $data["rPropNum"] = $dpdParcel->getRecipientHouseNumber();
        $data["city"] = $dpdParcel->getRecipientCity();
        $data["country"] = $dpdParcel->getCountry();
        $data["pcode"] = $dpdParcel->getPostalCode();

        $data["email"] = $dpdParcel->getRecipientEmail();
        $data["phone"] = $dpdParcel->getRecipientPhone();
        if(empty($data["phone"])){
            $data["phone"] = "00000";
        }

        if (!empty($dpdParcel->getSenderRemark())) {
            $data["sender_remark"] = $dpdParcel->getSenderRemark();
        }

        $data["weight"] = $dpdParcel->getWeight();
        $data["num_of_parcel"] = $dpdParcel->getNumberOfParcels();
        $data["order_number"] = $dpdParcel->getOrderNumber();
        $data["parcel_type"] = $dpdParcel->getParcelType()->getCode();

        /**
         * Cash On Delivery options
         */
        if ($dpdParcel->getParcelTypeId() == 2) {
           // $data["parcel_cod_type"] = $dpdParcel->getParcelCodType();
           // $data["cod_purpose"] = $dpdParcel->getCodPurpose();
           $data["parcel_cod_type"] = "firstonly";
            $data["cod_purpose"] = "COD-ref" . $data["order_number"];
            $data["cod_amount"] = $dpdParcel->getCodAmount();
        }

        /**
         * Delivery notifications
         */
        $data["predict"] = $dpdParcel->getPredict();

        /**
         * Check ID on delivery
         */
        if ($dpdParcel->getCheckId() == true) {
            $data["is_id_check"] = $dpdParcel->getCheckId();
            $data["is_check_num"] = $dpdParcel->getIdCheckNumber();
            $data["is_check_receiver"] = $dpdParcel->getIdCheckName();
        }

        /**
         * Override sender details
         */
        if (!empty($dpdParcel->getSenderName())) {
            $data["sender_name"] = $dpdParcel->getSenderName();
        }
        if (!empty($dpdParcel->getSenderPostalCode())) {
            $data["sender_pcode"] = $dpdParcel->getSenderPostalCode();
        }
        if (!empty($dpdParcel->getSenderCountry())) {
            $data["sender_country"] = $dpdParcel->getSenderCountry();
        }
        if (!empty($dpdParcel->getSenderStreet())) {
            $data["sender_street"] = $dpdParcel->getSenderStreet();
        }
        if (!empty($dpdParcel->getSenderPhone())) {
            $data["sender_phone"] = $dpdParcel->getSenderPhone();
        }

        if (!empty($dpdParcel->getPudoId())) {
            $data["pudo_id"] = $dpdParcel->getPudoId();
        }

        $params = http_build_query($data);
        $url = "https://easyship.hr/api/parcel/parcel_import" . "?" . $params;

        $apiResponse = $this->requestDpdApiRequest($url);
        if ($apiResponse['error'] === true) {
            $ret["message"] = $apiResponse["message"];
            return $ret;
        }

        $response = $apiResponse["result"];

        if (!empty($response["errlog"]) || $response["status"] !== "ok") {
            $dpdParcel->setErrorDescription($response["errlog"]);
            $this->saveDPDEntity($dpdParcel);

            $ret["message"] = $response["errlog"];
            return $ret;
        }

        /**
         * spremi svaki parcel_number u dpd_parcel_numbers_entity. parcel_number i id od parcele
         */
        foreach ($response['pl_number'] as $plNumber) {
            /** @var DpdParcelNumbersEntity $dpdParcelNumber */
            $dpdParcelNumber = $this->entityManager->getNewEntityByAttributSetName('dpd_parcel_numbers');

            $dpdParcelNumber->setDpdParcelNumber($plNumber);
            $dpdParcelNumber->setDpdParcel($dpdParcel);
            $dpdParcelNumber->setDeleted(0);

            $this->saveDPDEntity($dpdParcelNumber);
        }

        $dpdParcel->setErrorDescription(null);

        /**
         * Requested služi za buttone. Da se zna kada su hidden
         */
        $dpdParcel->setRequested(1);
        $this->saveDPDEntity($dpdParcel);

        /**
         * Automatsko dohvaćanje statusa na requestu
         */
        $parcelRes = $this->getDPDParcelStatus($dpdParcel);
        if ($parcelRes['error'] === true) {
            return $parcelRes;
        }

        $this->dispatchDpdParcelCreated($dpdParcel);

        $ret["error"] = false;
        return $ret;
    }

    /**
     * @param $url
     * @return array
     */
    private function requestDpdApiRequest($url)
    {
        $ret = array();
        $ret["message"] = null;
        $ret["error"] = true;
        $ret["result"] = null;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
        ));

        $err = curl_error($curl);

        if (!empty($err)) {
            $ret['message'] = $err;
            return $ret;
        }

        $response = curl_exec($curl);
        $response = json_decode($response, true);

        curl_close($curl);

        if (empty($response)) {
            $ret["message"] = "Empty response";
            return $ret;
        }

        $ret["result"] = $response;
        $ret["error"] = false;
        return $ret;
    }

    /**
     * @param $entity
     * @return \AppBundle\Interfaces\Entity\IFormEntityInterface|null
     */
    public function saveDPDEntity($entity)
    {
        $entity = $this->entityManager->saveEntity($entity);
        $this->entityManager->refreshEntity($entity);

        return $entity;
    }

    /**
     * @param $d
     * @param $fileName
     * @param string $type
     * @return array|false|string|null
     */
    public function getDPDPdf($d, &$fileName, string $type = 'labels')
    {
        $ret = array();
        $ret["message"] = null;
        $ret["error"] = true;
        $ret["result"] = null;

        $data = array();

        $data["username"] = $this->username;
        $data["password"] = $this->password;

        if ($type == 'labels') {
            $data["parcels"] = implode(',', $d);
            $method = 'parcel_print';
        } else {
            $dateTime = DateTime::createFromFormat("d/m/Y", $d["date"]);

            $data["type"] = $d['type'];
            $data["date"] = $dateTime->format("Y-m-d");
            $method = 'parcel_manifest_print';
        }

        $params = urldecode(http_build_query($data));
        $url = "https://easyship.hr/api/parcel/{$method}" . "?" . $params;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => 1,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
        ));

        $header_size = 0;
        $response = curl_exec($curl);

        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) === 200) {
            $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        }

        curl_close($curl);

        if (empty($header_size)) {
            $ret["message"] = "Empty header size";
            return $ret;
        }

        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        $length = $this->extractInfoFromResponseHeaders($header, 'length');

        /**
         * Ako je prazan pdf, nema smisla spremiti
         */
        if ($type == 'labels') {
            if ($length == "979") {
                $ret["message"] = "Empty label";
                return $ret;
            }

            $fileName = $this->extractInfoFromResponseHeaders($header);
        } else {
            if ($length == "1396") {
                $ret["message"] = "Empty pdf";
                return $ret;
            }
        }

        $ret["error"] = false;
        $ret["result"] = $body;
        return $ret;
    }

    /**
     * @param DpdParcelEntity|null $dpdParcel
     * @param array $parcels
     * @return array
     */
    public function printDPDLabels(DpdParcelEntity $dpdParcel = null, $parcels = Array())
    {
        $ret = array();
        $ret["message"] = null;
        $ret["error"] = true;

        if(empty($dpdParcel) && empty($parcels)){
            $ret["message"] = 'No parcels';
            return $ret;
        }

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        if (!file_exists($this->webPath . $this->targetDir)) {
            mkdir($this->webPath . $this->targetDir, 0777, true);
        }

        $parcelNumbers = $this->getDPDParcelNumbers($dpdParcel, $parcels);

        if (empty($parcelNumbers)) {
            $ret["message"] = 'No parcels';
            return $ret;
        }

        $dpdParcels = Array();
        /** @var DpdParcelNumbersEntity $parcelNumber */
        foreach ($parcelNumbers as $key => $parcelNumber){

            /*if(!empty($parcelNumber->getDpdParcel()->getFile())){
                unset($parcelNumbers[$key]);
                continue;
            }*/

            if(!isset($dpdParcels[$parcelNumber->getId()])){
                $dpdParcels[] = $parcelNumber->getDpdParcel();
            }
        }

        if (empty($dpdParcels)) {
            $ret["message"] = 'No parcels';
            return $ret;
        }

        $parcelNumberArray = [];
        /** @var DpdParcelNumbersEntity $parcelNumber */
        foreach ($parcelNumbers as $parcelNumber) {
            if ($parcelNumber->getDeleted() != 1) {
                $parcelNumberArray[] = $parcelNumber->getDpdParcelNumber();
            }
        }

        if (empty($parcelNumberArray)) {
            $ret["message"] = 'No parcels';
            return $ret;
        }

        $fileData = $this->getDPDPdf($parcelNumberArray, $fileName);
        if ($fileData["error"] == true || empty($fileData["result"])) {
            $ret["message"] = $this->translator->trans("DPD service error or parcel does not exist");
            return $ret;
        }

        $fileType = "pdf";

        if (empty($fileName)) {
            $fileName = sha1(time()) . "." . $fileType;
        }

        $targetFile = $this->webPath . $this->targetDir . $fileName;
        $targetFile = str_ireplace("//","/",$targetFile);

        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }

        if(file_exists($targetFile)){
            unlink($targetFile);
        }

        $baseName = $this->helperManager->getFilenameWithoutExtension($fileName);
        $fileSize = $this->helperManager->saveRawDataToFile($fileData["result"], $targetFile);
        if (empty($fileSize)) {
            $ret["message"] = $this->translator->trans("DPD service error");
            return $ret;
        }

        /** @var DpdParcelEntity $dpdParcel */
        foreach ($dpdParcels as $dpdParcel){
            $this->updateDpdParcelFile($dpdParcel, $fileType, $baseName, $fileName, $fileSize);
            if (empty($dpdParcel->getFile())) {
                $this->updateDpdParcelFile($dpdParcel, $fileType, $baseName, $fileName, $fileSize);
            } /*else {
                $this->unlinkAndUpdateDpdParcelFile($this->webPath . $this->targetDir . $dpdParcel->getFile(), $dpdParcel, $fileType, $baseName, $fileName, $fileSize);
            }*/

            $parcelRes = $this->getDPDParcelStatus($dpdParcel);
        }

        $ret["error"] = false;
        return $ret;
    }

    /**
     * @param $fullPath
     * @param $dpdParcel
     * @param $fileType
     * @param $baseName
     * @param $fileName
     * @param $fileSize
     * @return bool
     */
    private function unlinkAndUpdateDpdParcelFile($fullPath, $dpdParcel, $fileType = null, $baseName = null, $fileName = null, $fileSize = null)
    {
        if(file_exists($fullPath)){
            unlink($fullPath);
        }
        $this->updateDpdParcelFile($dpdParcel, $fileType, $baseName, $fileName, $fileSize);

        return true;
    }

    /**
     * @param $dpdParcel
     * @param $fileType
     * @param $baseName
     * @param $fileName
     * @param $fileSize
     * @return \AppBundle\Interfaces\Entity\IFormEntityInterface|null
     */
    private function updateDpdParcelFile($dpdParcel, $fileType, $baseName, $fileName, $fileSize)
    {
        $dpdParcel->setFileType($fileType);
        $dpdParcel->setFilename($baseName);
        $dpdParcel->setFile($fileName);
        $dpdParcel->setSize(($fileSize != null) ? FileHelper::formatSizeUnits($fileSize) : null);

        $entity = $this->entityManager->saveEntity($dpdParcel);
        $this->entityManager->refreshEntity($dpdParcel);

        return $entity;
    }

    /**
     * @param $id
     * @param $entityTypeCode
     * @return null
     */
    public function getDPDEntityById($id, $entityTypeCode)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $etDpdParcel = $this->entityManager->getEntityTypeByCode($entityTypeCode);

        return $this->entityManager->getEntityByEntityTypeAndId($etDpdParcel, $id);
    }

    /**
     * @param null $additionalFilter
     * @return null
     */
    public function getFilteredDPDParcelNumbers($additionalFilter = null){

        if (empty($this->entityManager)) {
            $this->entityManager = $this->getContainer()->get('entity_manager');
        }

        $et = $this->entityManager->getEntityTypeByCode("dpd_parcel_numbers");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if(!empty($additionalFilter)){
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters);
    }

    /**
     * @param DpdParcelEntity $dpdParcel
     * @param $parcels
     * @return array|mixed
     */
    private function getDPDParcelNumbers(DpdParcelEntity $dpdParcel = null, $parcels = Array())
    {
        if(empty($dpdParcel) && empty($parcels)){
            return Array();
        }

        $parcelNumbers = null;
        /**
         * Metoda može primiti i određene numbere na koje se želi primijeniti tracking_status,
         * uglavnom se pomoću filtera dohvaćaju ti brojevi (treali bi biti unique)
         */
        if (!empty($parcels)) {
            foreach ($parcels as $p) {
                $parcelNumbers[] = $this->getParcelEntityByAttribute('numbers', 'dpdParcelNumber', $p);
            }
            /**
             * Makne prazne
             */
            $parcelNumbers = array_filter($parcelNumbers);
        } else {
            /**
             * Ako nisu definirani, uzima sve od trenutne parcele
             */
            $parcelNumbers = $dpdParcel->getParcelNumbers();
        }

        return $parcelNumbers;
    }

    /**
     * @param DpdParcelNumbersEntity $dpdParcelNumbers
     */
    public function dispatchParcelNumberStatusChanged(DpdParcelNumbersEntity $dpdParcelNumbers)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->container->get("event_dispatcher");
        $eventDispatcher->dispatch(DpdParcelNumberStatusChangedEvent::NAME, new DpdParcelNumberStatusChangedEvent($dpdParcelNumbers));
    }

    /**
     * @param DpdParcelEntity|null $dpdParcel
     * @param array $parcels
     * @return array
     */
    public function getDPDParcelStatus(DpdParcelEntity $dpdParcel = null, $parcels = Array())
    {
        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;

        $data = array();
        $data["secret"] = $this->secret;
        $parcelNumbers = $this->getDPDParcelNumbers($dpdParcel, $parcels);

        if (empty($parcelNumbers)) {
            $ret["message"] = "No parcels; message_line: " . __LINE__;
            return $ret;
        }

        $tempNumbers = [];
        /**
         * Za svaki parcel_number
         */
        /** @var DpdParcelNumbersEntity $parcelNumber */
        foreach ($parcelNumbers as $parcelNumber) {

            /**
             * Postoji i entity dpd_parcel_status koji sadrži response (name) od tih statuse koji se dobivaju. Pokupljeni su iz dokumentacije
             * ima i polje finished koje označava kao završeni status. Recimo za status DELIVERED - pretpostavka je da se taj status neće mijenjati se finsished ručno postavi
             * I to pomaže da se status ne updatea svaki puta nego samo ako nije završen
             */
            /** @var DpdParcelStatusEntity $parcelStatus */
            $parcelStatus = $parcelNumber->getStatus();
            if ($parcelStatus !== null) {
                if ($parcelStatus->getFinished() || $parcelStatus->getDeleted()) {
                    continue;
                }
            }

            $data["parcel_number"] = $parcelNumber->getDpdParcelNumber();

            $params = http_build_query($data);
            $url = "https://easyship.hr/api/parcel/parcel_status" . "?" . $params;

            $apiResponse = $this->requestDpdApiRequest($url);
            if ($apiResponse['error'] === true) {
                $ret["message"] = $apiResponse["message"];
                return $ret;
            }

            $status = $apiResponse["result"];

            if (!isset($status['parcel_status'])) {
                $ret["message"] = "No parcel status; message_line: " . __LINE__;
                return $ret;
            }

            /**
             * dohvati status iz responsea
             */
            $status = trim($status['parcel_status']);

            /**
             * Ovdje ga pokušava naći u bazi pomoću filtera
             */
            /** @var DpdParcelStatusEntity $parcelStatus */
            $parcelStatus = $this->getParcelEntityByAttribute('status', 'name', $status);

            /**
             * Dodaj novi ako nije peonađen
             * ručno se treba postaviti finished i deleted
             */
            if (empty($parcelStatus)) {
                $parcelStatus = $this->entityManager->getNewEntityByAttributSetName('dpd_parcel_status');

                $parcelStatus->setName($status);
                $parcelStatus->setFinished(0);
                $parcelStatus->setDeleted(0);

                $parcelStatus = $this->saveDPDEntity($parcelStatus);
            }

            /**
             * Updateaj status tog numbera
             */
            if($parcelNumber->getStatusId() != $parcelStatus->getId()){
                $parcelNumber->setStatus($parcelStatus);
                $parcelNumber->setModified(new DateTime());
                $parcelNumber->setDeleted($parcelStatus->getDeleted());
                $tempNumbers[] = $this->saveDPDEntity($parcelNumber);

                $this->dispatchParcelNumberStatusChanged($parcelNumber);
            }
        }

        /**
         * Refresh requesta prilikom dohvaćanja statusa parcela
         * Znači poanta je recimo ako oni ručno obrišu parcelu, u updateu se neće updateati na 0
         */
        /*if (!empty($tempNumbers) && $dpdParcel->getRequested() == 1) {
            foreach ($tempNumbers as $parcelNumber) {
                $statusName = strtolower($parcelNumber->getStatus()->getName());
                if ($parcelNumber->getDeleted() == 1 || $statusName === 'cancelled') {

                    if (!empty($dpdParcel->getFile())) {
                        $this->unlinkAndUpdateDpdParcelFile($this->webPath . $this->targetDir . $dpdParcel->getFile(), $dpdParcel);
                    }

                    $dpdParcel->setRequested(0);
                    $this->saveDPDEntity($dpdParcel);

                    break;
                }
            }
        }*/

        $ret["error"] = false;
        return $ret;
    }

    /**
     * @param $codePart
     * @param $field
     * @param $value
     * @return mixed|null
     */
    public function getParcelEntityByAttribute($codePart, $field, $value)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->getContainer()->get('entity_manager');
        }

        $entityTypeCode = "dpd_parcel";
        if (!empty($codePart)){
            $entityTypeCode = "dpd_parcel_" . $codePart;
        }

        $etDpdParcelEntity = $this->entityManager->getEntityTypeByCode($entityTypeCode);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter($field, "eq", $value));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($etDpdParcelEntity, $compositeFilters);
    }

    /**
     * @param DpdParcelEntity $dpdParcel
     * @param string $operation = 'delete' ? 'cancel'
     * @return string[]
     *  Metoda služi za brisanje numbera
     * api može primiti više brojeva odvojenih zarezom
     * uglavnom svi brojevi u parceli moraju biti poslani jer ova metoda funkcionira na način da se sve briše
     * Također postoji i checkbox deleted koje se updatea na 1 kada se obriše number.
     * VAŽNO ZA CANCEL: With this interface, customer can cancel the parcel after printing and after the data was
     * sent. Cancelled parcels will not appear on the manifest list.
     */
    public function deleteOrCancelDPDParcel(DpdParcelEntity $dpdParcel, string $operation = 'delete')
    {
        $ret = array();
        $ret["error"] = true;
        $ret["message"] = null;

        $data = [];
        $data['username'] = $this->username;
        $data['password'] = $this->password;

        $parcels = $dpdParcel->getParcelNumbers();

        $parcelArray = [];
        /** @var DpdParcelNumbersEntity $parcel */
        foreach ($parcels as $parcel) {
            if ($parcel->getDeleted() == 1) {
                continue;
            }

            if (empty($parcel->getStatus())) {
                continue;
            }

            $statusName = strtolower($parcel->getStatus()->getName());

            /**
             * Ne može obrisati ako je isprintano
             */
            if ($operation === 'delete' && ($statusName === 'printed' || !empty($dpdParcel->getFile()))) {
                $ret["message"] = 'Can\'t delete. Already printed';
                return $ret;
            }

            /**
             * Ne može stornirati ako nije poslano ili nije isprintano
             */
            if ($operation === 'cancel' && ($statusName !== 'sent' && ($statusName !== 'printed' || empty($dpdParcel->getFile())))) {
                $ret["message"] = 'Can\'t cancel. Parcels must be sent or printed';
                return $ret;
            }

            $parcelArray[] = $parcel->getDpdParcelNumber();
        }

        if (empty($parcelArray)) {
            $ret["message"] = 'No parcels';
            return $ret;
        }

        $data['parcels'] = implode(',', $parcelArray);

        $params = http_build_query($data);
        $url = "https://easyship.hr/api/parcel/parcel_{$operation}" . "?" . $params;

        $apiResponse = $this->requestDpdApiRequest($url);
        if ($apiResponse['error'] === true) {
            $ret["message"] = $apiResponse["message"];
            return $ret;
        }

        $res = $apiResponse["result"];

        if (isset($res['errlog']) || !empty($res['errlog'])) {
            $dpdParcel->setErrorDescription($res['errlog']);
            $this->saveDPDEntity($dpdParcel);

            $ret["message"] = $res['errlog'];
            return $ret;
        }

        /** @var DpdParcelNumbersEntity $parcel */
        foreach ($parcels as $parcel) {
            $parcel->setDeleted(1);
            $this->saveDPDEntity($parcel);
        }

        $this->getDPDParcelStatus($dpdParcel);

        $ret["error"] = false;
        return $ret;
    }

    /**
     * @param DpdParcelEntity $dpdParcel
     */
    public function dispatchDpdParcelCreated(DpdParcelEntity $dpdParcel)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->container->get("event_dispatcher");
        $eventDispatcher->dispatch(DpdParcelCreatedEvent::NAME, new DpdParcelCreatedEvent($dpdParcel));
    }

    /**
     * @param DpdParcelEntity|null $dpdParcel
     * @param $data
     * @return DpdParcelEntity|mixed|null
     */
    public function createUpdateDPDParcel(DpdParcelEntity $dpdParcel = null, $data){

        if(empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }

        if(empty($dpdParcel)){
            $dpdParcel = $this->entityManager->getNewEntityByAttributSetName("dpd_parcel");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($dpdParcel, $setter)) {
                $dpdParcel->$setter($value);
            }
        }

        $this->entityManager->saveEntity($dpdParcel);
        $this->entityManager->refreshEntity($dpdParcel);

        return $dpdParcel;
    }

    /**
     * @param DpdCollectionRequestEntity|null $dpdCollectionRequest
     * @param $data
     * @return DpdCollectionRequestEntity|mixed|null
     */
    public function createUpdateDPDCollectionRequest(DpdCollectionRequestEntity $dpdCollectionRequest = null, $data){

        if(empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }

        if(empty($dpdCollectionRequest)){
            $dpdCollectionRequest = $this->entityManager->getNewEntityByAttributSetName("dpd_collection_request");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($dpdCollectionRequest, $setter)) {
                $dpdCollectionRequest->$setter($value);
            }
        }

        $this->entityManager->saveEntity($dpdCollectionRequest);
        $this->entityManager->refreshEntity($dpdCollectionRequest);

        return $dpdCollectionRequest;
    }

    /**
     * @return array
     */
    public function getUnfinishedDPDParcels(){

        $ret = Array();

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT dpdpn.dpd_parcel_number FROM `dpd_parcel_numbers_entity` as dpdpn LEFT JOIN dpd_parcel_status_entity as dpdps ON dpdpn.status_id = dpdps.id
        LEFT JOIN dpd_parcel_entity as d ON dpdpn.dpd_parcel_id = d.id
        WHERE dpdpn.entity_state_id = 1 and d.entity_state_id = 1 and dpdps.finished = 0 and dpdpn.deleted = 0;";
        $data = $this->databaseContext->getAll($q);

        if(!empty($data)){
            $ret = array_column($data,"dpd_parcel_number");
        }

        return $ret;
    }
}
