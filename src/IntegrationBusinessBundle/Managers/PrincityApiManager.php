<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\SettingsManager;

class PrincityApiManager extends AbstractBaseManager
{
    private $em;
    /** @var string $apiUrl */
    private $apiUrl;
    /** @var string $authKey */
    private $authKey;
    /** @var AttributeSet $asDevicePrincity */
    private $asDevicePrincity;

    public function initialize()
    {
        parent::initialize();

        /** @var EntityManager $entityManager */
        $entityManager = $this->getContainer()->get("entity_manager");

        /** @var SettingsManager $settingsManager */
        $settingsManager = $this->getContainer()->get("settings_manager");

        $this->apiUrl = $settingsManager->getSettingByKey("princity_api_url")->getValue();
        $this->authKey = $settingsManager->getSettingByKey("princity_auth_key")->getValue();

        $this->em = $this->container->get("doctrine.orm.entity_manager");

        $this->asDevicePrincity = $entityManager->getAttributeSetByCode("device_princity");
    }

    /**
     * @param $method
     * @return mixed
     * @throws \Exception
     */
    private function getApiResponse($endpoint)
    {
        $ch = curl_init();

        $options = [
            CURLOPT_URL => $this->apiUrl . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                'Princity-auth-key: ' . $this->authKey,
                'Content-Type: application/json'
            ]
        ];

        curl_setopt_array($ch, $options);

        $data = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if (!empty($error)) {
            throw new \Exception($error);
        }
        if ($status != 200) {
            throw new \Exception(sprintf("getApiResponse error: %u, %s", $status, $data));
        }
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        $data = json_decode($data, true);

        return $data;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getContracts()
    {
        return $this->getApiResponse("/contracts");
    }

    /**
     * @param $contract
     * @return mixed
     * @throws \Exception
     */
    public function getInventory($contract)
    {
        return $this->getApiResponse("/devices?contract=" . $contract);
    }

    /**
     * @param $deviceId
     * @return mixed
     * @throws \Exception
     */
    public function getSnmpAlerts($deviceId)
    {
        return $this->getApiResponse("/snmpalerts?deviceId=" . $deviceId);
    }

    /**
     * @param $deviceId
     * @return mixed
     * @throws \Exception
     */
    public function getSupplies($deviceId)
    {
        return $this->getApiResponse("/supplies?deviceId=" . $deviceId);
    }

    /**
     * @param $contract
     * @return mixed
     * @throws \Exception
     */
    public function getOrders($contract)
    {
        return $this->getApiResponse("/orders?contract=" . $contract. "&state=NEW");
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importInventory()
    {
        $contracts = $this->getContracts();

        $devicePrincityRepository = $this->em->getRepository(DevicePrincityEntity::class);

        foreach ($contracts as $contract) {

            $data = $this->getInventory($contract["prefix"]);

            $i = 0;
            $c = count($data);

            foreach ($data as $d) {

                echo sprintf("%s - %s (%u/%u)\n", $d["id"], $d["serial"], ++$i, $c);

                $dateTime = new \DateTime();

                /** @var DevicePrincityEntity $devicePrincity */
                $devicePrincity = $devicePrincityRepository->findOneBy(["remoteId" => $d["id"]]);
                if (empty($devicePrincity)) {

                    $devicePrincity = new DevicePrincityEntity;
                    $devicePrincity->setCreated($dateTime);
                    $devicePrincity->setCreatedBy("system");
                    $devicePrincity->setModified($dateTime);
                    $devicePrincity->setModifiedBy("system");
                    $devicePrincity->setEntityType($this->asDevicePrincity->getEntityType());
                    $devicePrincity->setAttributeSet($this->asDevicePrincity);
                    $devicePrincity->setEntityStateId(1);

                    $devicePrincity->setRemoteId($d["id"]);
                    $devicePrincity->setSerialNumber($d["serial"]);

                    $this->em->persist($devicePrincity);
                }
            }
        }

        $this->em->flush();

        return [];
    }
}
