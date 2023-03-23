<?php

namespace GLSBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\OrderEntity;
use Doctrine\Common\Util\Inflector;
use AppBundle\Helpers\FileHelper;
use Doctrine\ORM\Mapping\Cache;
use Exception;
use GLSBusinessBundle\Entity\GlsParcelEntity;
use GLSBusinessBundle\Events\GlsParcelCreatedEvent;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class GLSManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;

    protected $clientNumber;
    protected $username;

    protected $pwd;
    protected $password;

    protected $wsdl;
    protected $soapOptions = "";

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
        $this->helperManager = $this->container->get("helper_manager");

        $this->soapOptions = array('soap_version' => SOAP_1_1,
            'stream_context' => stream_context_create(
                array('ssl' => array('verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true))));

        $this->clientNumber = $_ENV["GLS_CLIENT_NUMBER"];
        $this->username = $_ENV["GLS_USERNAME"];
        $this->pwd = $_ENV["GLS_PASSWORD"];
        $this->wsdl = $_ENV["GLS_WSDL"];

        $this->password = hash('sha512', $this->pwd, true);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getGlsParcelById($id)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("gls_parcel");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * Get parcel statuses.
     * @param GlsParcelEntity $glsParcel
     * @param bool $returnPOD
     * @return array
     */
    public function getGLSParcelStatus(GlsParcelEntity $glsParcel, bool $returnPOD = false)
    {
        $getParcelStatusesRequest = array(
            'Username' => $this->username,
            'Password' => $this->password,
            'ParcelNumber' => intval($glsParcel->getParcelNumber()),
            'ReturnPOD' => $returnPOD);

        $request = array("getParcelStatusesRequest" => $getParcelStatusesRequest);

        $client = new \SoapClient($this->wsdl, $this->soapOptions);

        $response = $client->GetParcelStatuses($request);

        //covert stdClass to array
        return (json_decode(json_encode($response->GetParcelStatusesResult)));
    }

    /**
     * Supports printing labels for one or multiple parcels
     * @param $glsParcels
     * @return array
     */
    public function printGLSLabels($glsParcels)
    {
        $webPath = $_ENV["WEB_PATH"];

        $targetDir = "/Documents/gls_parcel/";
        $baseName = sha1(time());
        $fileType = "pdf";

        $fileName = $baseName . "." . $fileType;

        if (!file_exists($webPath . $targetDir)) {
            mkdir($webPath . $targetDir, 0777, true);
        }

        $targetFile = $webPath . $targetDir . $fileName;

        try {
            $glsStdParcels = array();

            foreach ($glsParcels as $glsParcel) {
                $glsStdParcels[] = $this->createGLSRequestParcel($glsParcel);
            }

            $printLabelsResult = $this->sendPrintLabelRequest($glsStdParcels);

            /**
             * Parse errors, if any errors are found, no labels are generated
             */
            $errors = (array)$printLabelsResult->PrintLabelsErrorList;
            if (!empty($errors)) {
                $errorInfo = $errors["ErrorInfo"];

                $clientReferenceList = $errorInfo->ClientReferenceList->string;
                if (!is_array($clientReferenceList)) {
                    $clientReferenceList = array($clientReferenceList);
                }

                $errorMessages = Array();

                foreach ($clientReferenceList as $clientReference) {

                    /** @var GlsParcelEntity $glsParcel */
                    $glsParcel = $glsParcels[$clientReference];

                    $glsParcel->setErrorCode($errorInfo->ErrorCode);
                    $glsParcel->setErrorDescription($errorInfo->ErrorDescription);

                    $this->entityManager->saveEntity($glsParcel);

                    $errorArray[$glsParcel->getId()][$errorInfo->ErrorCode] = $errorInfo->ErrorDescription;
                    $errorMessages[] = $errorInfo->ErrorDescription;

                    $this->logger->error("GLS: " . json_encode($errorArray));
                }

                return array(
                    "error" => true,
                    "message" => implode(", ",$errorMessages)
                );
            }

            if (empty($printLabelsResult->Labels)) {
                return array(
                    "error" => true,
                    "message" => $this->translator->trans("GLS service error")
                );
            }

            $bytes = $this->helperManager->saveRawDataToFile($printLabelsResult->Labels, $targetFile);
            if (!$bytes) {
                return array(
                    "error" => true,
                    "message" => $this->translator->trans("GLS service error")
                );
            }

            /**
             * Successfully ordered parcel pickup
             */
            $parcelInfos = (array)$printLabelsResult->PrintLabelsInfoList;
            if (!empty($parcelInfos)) {
                $parcelInfo = $parcelInfos["PrintLabelsInfo"];

                if (!is_array($parcelInfo)) {
                    $parcelInfo = array($parcelInfo);
                }

                foreach ($parcelInfo as $info) {

                    /** @var GlsParcelEntity $glsParcel */
                    $glsParcel = $glsParcels[$info->ClientReference];

                    $glsParcel->setName($info->ParcelId);
                    $glsParcel->setParcelId($info->ParcelId);
                    $glsParcel->setParcelNumber($info->ParcelNumber);

                    $glsParcel->setErrorCode(null);
                    $glsParcel->setErrorDescription(null);

                    $glsParcel->setFileType($fileType);
                    $glsParcel->setFilename($baseName);
                    $glsParcel->setFile($fileName);
                    $glsParcel->setSize(FileHelper::formatSizeUnits($bytes));

                    $this->entityManager->saveEntity($glsParcel);
                    $this->entityManager->refreshEntity($glsParcel);

                    $this->dispatchGlsParcelCreated($glsParcel);
                }
            }
        } catch (Exception $e) {
            $this->logger->error("GLS: " . $e->getMessage());
            return array(
                "error" => true,
                "message" => $this->translator->trans("Error calling GLS service")
            );
        }

        return array(
            "error" => false,
            "message" => $this->translator->trans("Parcel pickup requested"),
            "filepath" => $targetDir . $fileName
        );
    }

    /**
     * Label(s) generation by the service.
     * @return \StdClass     *
     * return value is Std class returned by GLS API     *
     */
    public function sendPrintLabelRequest($parcels)
    {
        $printLabelsRequest = array('Username' => $this->username,
            'Password' => $this->password,
            'ParcelList' => $parcels);

        $request = array("printLabelsRequest" => $printLabelsRequest);

        $client = new \SoapClient($this->wsdl, $this->soapOptions);

        $response = $client->PrintLabels($request);

        return $response->PrintLabelsResult;
    }

    /**
     * Preparing label(s) by the service.
     */
    public function prepareLabels($parcels)
    {
        $prepareLabelsRequest = array('Username' => $this->username,
            'Password' => $this->password,
            'ParcelList' => $parcels);

        $request = array("prepareLabelsRequest" => $prepareLabelsRequest);

        $client = new \SoapClient($this->wsdl, $this->soapOptions);

        $response = $client->PrepareLabels($request);

        $parcelIdList = [];
        if ($response != null &&
            count((array)$response->PrepareLabelsResult->PrepareLabelsError) == 0 &&
            count((array)$response->PrepareLabelsResult->ParcelInfoList) > 0) {

            foreach ($response->PrepareLabelsResult->ParcelInfoList->ParcelInfo as $info) {
                $parcelIdList[] = $info->ParcelId;
            }
        }

        //Test request:
        $getPrintedLabelsRequest = array('Username' => $this->username,
            'Password' => $this->password,
            'ParcelIdList' => $parcelIdList,
            'PrintPosition' => 1,
            'ShowPrintDialog' => 0);

        return $getPrintedLabelsRequest;
    }

    /**
     * Get label(s) by the service.
     */
    public function getPrintedLabels($getPrintedLabelsRequest)
    {
        $request = array("getPrintedLabelsRequest" => $getPrintedLabelsRequest);

        $client = new \SoapClient($this->wsdl, $this->soapOptions);
        $response = $client->GetPrintedLabels($request);

        return $response->GetPrintedLabelsResult;
    }

    /**
     * Get parcel(s) information by date ranges.
     * Use pickup date or print date
     * @param \DateTime $pickupDateFrom
     * @param \DateTime $pickupDateTo
     * @param \DateTime $printDateFrom
     * @param \DateTime $printDateTo
     * @return array
     */
    public function getParcelList(?\DateTime $pickupDateFrom, ?\DateTime $pickupDateTo, ?\DateTime $printDateFrom, ?\DateTime $printDateTo)
    {
        $pickupDateFrom = $pickupDateFrom != null ? date_format($pickupDateFrom, 'Y-m-d') : null;
        $pickupDateTo = $pickupDateTo != null ? date_format($pickupDateTo, 'Y-m-d') : null;
        $printDateFrom = $printDateFrom != null ? date_format($printDateFrom, 'Y-m-d') : null;
        $printDateTo = $printDateTo != null ? date_format($printDateTo, 'Y-m-d') : null;

        $getParcelListRequest = array('Username' => $this->username,
            'Password' => $this->password,
            'PickupDateFrom' => $pickupDateFrom,
            'PickupDateTo' => $pickupDateTo,
            'PrintDateFrom' => $printDateFrom,
            'PrintDateTo' => $printDateTo);

        $request = array("getParcelListRequest" => $getParcelListRequest);
        $client = new \SoapClient($this->wsdl, $this->soapOptions);

        $response = $client->GetParcelList($request);

        //covert stdClass to array
        return json_decode(json_encode($response->GetParcelListResult));
    }

    public function createTestParcel()
    {
        /** @var GlsParcelEntity $glsParcel */
        $glsParcel = $this->entityManager->getNewEntityByAttributSetName("gls_parcel");

        $glsParcel->setClientNumber($this->clientNumber);
        $glsParcel->setClientReference(StringHelper::guidv4());
        $glsParcel->setCount(1);
        $glsParcel->setCodAmount(0);
        $glsParcel->setCodReference("");
        $glsParcel->setContent("Test GLS parcel content");
        $glsParcel->setPickupDate(new \DateTime('2020-07-07'));

        $glsParcel->setPickupName("Ivan Horvat");
        $glsParcel->setPickupCity("Zagreb");
        $glsParcel->setPickupStreet("Zagrebacka");
        $glsParcel->setPickupHouseNumber("6");
        $glsParcel->setPickupHouseNumberInfo("Drugi kat");
        $glsParcel->setPickupZipCode("10000");
        $glsParcel->setPickupContactPhone("+36701234567");
        $glsParcel->setPickupContactName("Ivan Horvat");
        $glsParcel->setPickupContactEmail("ihorvat@mail.com");
        $glsParcel->setPickupCountryIsoCode("HR");

        $glsParcel->setDeliveryName("Iva Horvat");
        $glsParcel->setDeliveryCity("Dubrovnik");
        $glsParcel->setDeliveryStreet("Ulica Split");
        $glsParcel->setDeliveryHouseNumber("2a");
        $glsParcel->setDeliveryHouseNumberInfo("Strazji ulaz");
        $glsParcel->setDeliveryZipCode("20000");
        $glsParcel->setDeliveryContactPhone("+3850222267");
        $glsParcel->setDeliveryContactName("Iva Horvat");
        $glsParcel->setDeliveryContactEmail("ivahorvat@mail.com");
        $glsParcel->setDeliveryCountryIsoCode("HR");

        $glsParcel->setServices('[{"Code":"T12"}]');

        $glsParcel = $this->entityManager->saveEntity($glsParcel);

        return ($glsParcel);
    }

    /**
     * @param GlsParcelEntity $glsParcel
     * @return \StdClass
     */
    public function createGLSRequestParcel(GlsParcelEntity $glsParcel): \StdClass
    {
        $parcel = new \StdClass();

        $parcel->ClientNumber = $glsParcel->getClientNumber();
        $parcel->ClientReference = $glsParcel->getClientReference();
        $parcel->CODAmount = $glsParcel->getCodAmount();
        $parcel->CODReference = $glsParcel->getCodReference();
        $parcel->Content = $glsParcel->getContent();
        $parcel->Count = $glsParcel->getCount();
        $parcel->PickupDate = date_format($glsParcel->getPickupDate(), 'Y-m-d');

        $deliveryAddress = new \StdClass();
        $deliveryAddress->Name = $glsParcel->getDeliveryName();
        $deliveryAddress->ContactName = $glsParcel->getDeliveryContactName();
        $deliveryAddress->ContactPhone = $glsParcel->getDeliveryContactPhone();
        $deliveryAddress->ContactEmail = $glsParcel->getDeliveryContactEmail();
        $deliveryAddress->City = $glsParcel->getDeliveryCity();
        $deliveryAddress->ZipCode = $glsParcel->getDeliveryZipCode();
        $deliveryAddress->Street = $glsParcel->getDeliveryStreet();
        $deliveryAddress->HouseNumber = $glsParcel->getDeliveryHouseNumber();
        $deliveryAddress->HouseNumberInfo = $glsParcel->getDeliveryHouseNumberInfo();
        $deliveryAddress->CountryIsoCode = $glsParcel->getDeliveryCountryIsoCode();
        $parcel->DeliveryAddress = $deliveryAddress;

        $pickupAddress = new \StdClass();
        $pickupAddress->Name = $glsParcel->getPickupName();
        $pickupAddress->ContactName = $glsParcel->getPickupContactName();
        $pickupAddress->ContactPhone = $glsParcel->getPickupContactPhone();
        $pickupAddress->ContactEmail = $glsParcel->getPickupContactEmail();
        $pickupAddress->City = $glsParcel->getPickupCity();
        $pickupAddress->ZipCode = $glsParcel->getPickupZipCode();
        $pickupAddress->Street = $glsParcel->getPickupStreet();
        $pickupAddress->HouseNumber = $glsParcel->getPickupHouseNumber();
        $pickupAddress->HouseNumberInfo = $glsParcel->getPickupHouseNumberInfo();
        $pickupAddress->CountryIsoCode = $glsParcel->getPickupCountryIsoCode();
        $parcel->PickupAddress = $pickupAddress;

        $services = [];

        /*if (substr($glsParcel->getDeliveryZipCode(), 0, 2) == "20") {
            $dpvService = new \StdClass();
            $dpvService->Code = "DPV";
            $parameter = new \StdClass();
            $parameter->StringValue = strval($glsParcel->getCodAmount());
            $parameter->DecimalValue = $glsParcel->getCodAmount();
            $dpvService->DPVParameter = $parameter;
            $services[] = $dpvService;
        }*/

        /*$service1 = new \StdClass();
        $service1->Code = "PSD";
        $parameter1 = new \StdClass();
        $parameter1->StringValue = "2351-CSOMAGPONT";
        $service1->PSDParameter = $parameter1;
        $services[] = $service1;*/

        $parcel->ServiceList = $services;//json_decode($glsParcel->getServices());

        return $parcel;
    }

    /**
     * @param GlsParcelEntity $glsParcel
     */
    public function dispatchGlsParcelCreated(GlsParcelEntity $glsParcel)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->container->get("event_dispatcher");
        $eventDispatcher->dispatch(GlsParcelCreatedEvent::NAME, new GlsParcelCreatedEvent($glsParcel));
    }

    /**
     * @param array $data
     *   key=attribute_code; value=value
     * @return GlsParcelEntity
     */
    public function generateParcel($data, OrderEntity $orderEntity = null)
    {
        /** @var GlsParcelEntity $glsParcelEntity */
        $glsParcelEntity = $this->entityManager->getNewEntityByAttributSetName("gls_parcel");

        if (is_array($data)) {
            if (!empty($orderEntity)) {
                $glsParcelEntity->setOrder($orderEntity);
            }
            foreach ($data as $key => $value) {
                $setter = EntityHelper::makeSetter($key);

                if (EntityHelper::checkIfMethodExists($glsParcelEntity, $setter)) {
                    $glsParcelEntity->$setter($value);
                }
            }

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->saveEntityWithoutLog($glsParcelEntity);
        }

        return $glsParcelEntity;
    }
}