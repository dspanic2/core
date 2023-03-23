<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\SettingsManager;

class NubeprintApiManager extends AbstractBaseManager
{
    private $curlHeaders;
    private $curlStatus;

    private $em;
    /** @var string $apiUrl */
    private $apiUrl;
    /** @var string $apiUsername */
    private $apiUsername;
    /** @var string $apiPassword */
    private $apiPassword;
    /** @var AttributeSet $asDevicePrintalerts */
    private $asDevicePrintalerts;

    public function initialize()
    {
        parent::initialize();

        /** @var EntityManager $entityManager */
        $entityManager = $this->getContainer()->get("entity_manager");

        /** @var SettingsManager $settingsManager */
        $settingsManager = $this->getContainer()->get("settings_manager");

        $this->apiUrl = $settingsManager->getSettingByKey("nubeprint_api_url")->getValue();
        $this->apiUsername = $settingsManager->getSettingByKey("nubeprint_api_username")->getValue();
        $this->apiPassword = $settingsManager->getSettingByKey("nubeprint_api_password")->getValue();

        $this->em = $this->container->get("doctrine.orm.entity_manager");

        $this->asDevicePrintalerts = $entityManager->getAttributeSetByCode("device_printalerts");
    }

    /**
     * @param $options
     * @param bool $decodeJson
     * @return bool|mixed|string
     * @throws \Exception
     */
    private function getApiResponse($options, $decodeJson = true)
    {
        $ch = curl_init();

        curl_setopt_array($ch, $options);

        $headers = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$headers) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) {
                    return $len;
                }
                $headers[strtolower(trim($header[0]))][] = trim($header[1]);
                return $len;
            }
        );

        $response = curl_exec($ch);
        $error = curl_error($ch);

        $this->curlHeaders = $headers;
        $this->curlStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if (!empty($error)) {
            throw new \Exception($error);
        }
        if ($decodeJson) {
            $response = json_decode($response, true);
        }

        return $response;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getApiKey()
    {
        $options = [
            CURLOPT_URL => $this->apiUrl . "/panel/apiv1/auth/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => ["login" => $this->apiUsername, "password" => $this->apiPassword]
        ];

        $data = $this->getApiResponse($options, false);

        if ($this->curlStatus != 200) {
            throw new \Exception(sprintf("getApiKey request error: %u, %s", $this->curlStatus, $data));
        }
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }
        if (!isset($this->curlHeaders["set-cookie"][0]) || empty($this->curlHeaders["set-cookie"][0])) {
            throw new \Exception(sprintf("Cookie is empty: %s", $data));
        }

        return $this->curlHeaders["set-cookie"][0];
    }

    /**
     * @return mixed|string
     * @throws \Exception
     */
    public function getInventory()
    {
        $apiKey = $this->getApiKey();

        $options = [
            CURLOPT_URL => $this->apiUrl . "/panel/apiv1/inventory/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => ["Cookie: " . $apiKey]
        ];

        $data = $this->getApiResponse($options, true);
        if (!isset($data["Nubeprint-apiv1-inventory"])) {
            throw new \Exception("Inventory is empty");
        }

        return $data["Nubeprint-apiv1-inventory"];
    }

    /**
     * @return mixed|string
     * @throws \Exception
     */
    public function getConsumableAlerts()
    {
        $apiKey = $this->getApiKey();

        $options = [
            CURLOPT_URL => $this->apiUrl . "/panel/apiv1/consumable_alerts/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => ["Cookie: " . $apiKey]
        ];

        $data = $this->getApiResponse($options, true);
        if (!isset($data["Nubeprint-apiv1-consumable_alerts"])) {
            throw new \Exception("Consumable alerts are empty");
        }

        return $data["Nubeprint-apiv1-consumable_alerts"];
    }

    /**
     * @return mixed|string
     * @throws \Exception
     */
    public function getTechnicalAlerts()
    {
        $apiKey = $this->getApiKey();

        $options = [
            CURLOPT_URL => $this->apiUrl . "/panel/apiv1/technical_alerts/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => ["Cookie: " . $apiKey]
        ];

        $data = $this->getApiResponse($options, true);
        if (!isset($data["Nubeprint-apiv1-tech_alerts"])) {
            throw new \Exception("Technical alerts are empty");
        }

        return $data["Nubeprint-apiv1-tech_alerts"];
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importInventory()
    {
        $data = $this->getInventory();

        $devicePrintalertsRepository = $this->em->getRepository(DevicePrintalertsEntity::class);

        $i = 0;
        $c = count($data);

        foreach ($data as $d) {

            echo sprintf("%s (%u/%u)\n", $d["Cloud"], ++$i, $c);

            $dateTime = new \DateTime();

            /** @var DevicePrintalertsEntity $devicePrintalerts */
            $devicePrintalerts = $devicePrintalertsRepository->findOneBy(["cloud" => $d["Cloud"]]);
            if (empty($devicePrintalerts)) {

                $devicePrintalerts = new DevicePrintalertsEntity;
                $devicePrintalerts->setCreated($dateTime);
                $devicePrintalerts->setCreatedBy("system");
                $devicePrintalerts->setModified($dateTime);
                $devicePrintalerts->setModifiedBy("system");
                $devicePrintalerts->setEntityType($this->asDevicePrintalerts->getEntityType());
                $devicePrintalerts->setAttributeSet($this->asDevicePrintalerts);
                $devicePrintalerts->setEntityStateId(1);

                $devicePrintalerts->setProject($d["Project"]);
                $devicePrintalerts->setCompany($d["Company"]);
                $devicePrintalerts->setSerialNumber($d["SerialNumber"]);
                $devicePrintalerts->setVariant($d["Variant"]);
                $devicePrintalerts->setStatus($d["Status"]);
                $devicePrintalerts->setClass($d["Class"]);
                $devicePrintalerts->setCloud($d["Cloud"]);
                $devicePrintalerts->setLocRefCode($d["LocRefCode"]);
                $devicePrintalerts->setLoc1($d["Loc1"]);
                $devicePrintalerts->setLoc2($d["Loc2"]);
                $devicePrintalerts->setLoc3($d["Loc3"]);
                $devicePrintalerts->setLoc4($d["Loc4"]);
                $devicePrintalerts->setLoc5($d["Loc5"]);
                $devicePrintalerts->setIp($d["IP"]);
                $devicePrintalerts->setHostname($d["Hostname"]);
                $devicePrintalerts->setLastDate($d["LastDate"]);
                $devicePrintalerts->setLastTime($d["LastTime"]);
                $devicePrintalerts->setTechAlerts($d["TechAlerts"]);
                $devicePrintalerts->setConsAlerts($d["ConsAlerts"]);

                $this->em->persist($devicePrintalerts);

            } else {

                $modified = false;
                if ($devicePrintalerts->getProject() != $d["Project"]) {
                    $devicePrintalerts->setProject($d["Project"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getCompany() != $d["Company"]) {
                    $devicePrintalerts->setCompany($d["Company"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getSerialNumber() != $d["SerialNumber"]) {
                    $devicePrintalerts->setSerialNumber($d["SerialNumber"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getVariant() != $d["Variant"]) {
                    $devicePrintalerts->setVariant($d["Variant"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getStatus() != $d["Status"]) {
                    $devicePrintalerts->setStatus($d["Status"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getClass() != $d["Class"]) {
                    $devicePrintalerts->setClass($d["Class"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getLocRefCode() != $d["LocRefCode"]) {
                    $devicePrintalerts->setLocRefCode($d["LocRefCode"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getLoc1() != $d["Loc1"]) {
                    $devicePrintalerts->setLoc1($d["Loc1"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getLoc2() != $d["Loc2"]) {
                    $devicePrintalerts->setLoc2($d["Loc2"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getLoc3() != $d["Loc3"]) {
                    $devicePrintalerts->setLoc3($d["Loc3"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getLoc4() != $d["Loc4"]) {
                    $devicePrintalerts->setLoc4($d["Loc4"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getLoc5() != $d["Loc5"]) {
                    $devicePrintalerts->setLoc5($d["Loc5"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getIp() != $d["IP"]) {
                    $devicePrintalerts->setIp($d["IP"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getHostname() != $d["Hostname"]) {
                    $devicePrintalerts->setHostname($d["Hostname"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getLastDate() != $d["LastDate"]) {
                    $devicePrintalerts->setLastDate($d["LastDate"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getLastTime() != $d["LastTime"]) {
                    $devicePrintalerts->setLastTime($d["LastTime"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getTechAlerts() != $d["TechAlerts"]) {
                    $devicePrintalerts->setTechAlerts($d["TechAlerts"]);
                    $modified = true;
                }
                if ($devicePrintalerts->getConsAlerts() != $d["ConsAlerts"]) {
                    $devicePrintalerts->setConsAlerts($d["ConsAlerts"]);
                    $modified = true;
                }

                if ($modified) {
                    $devicePrintalerts->setModified($dateTime);
                    $devicePrintalerts->setModifiedBy("system");
                    $this->em->persist($devicePrintalerts);
                }
            }
        }

        $this->em->flush();

        return [];
    }
}
