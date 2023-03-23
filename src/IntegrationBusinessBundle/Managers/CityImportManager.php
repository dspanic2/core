<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Helpers\StringHelper;
use AppBundle\Models\InsertModel;

class CityImportManager extends DefaultIntegrationImportManager
{
    /** @var AttributeSet $asCity */
    private $asCity;
    /** @var AttributeSet $asRegion */
    private $asRegion;

    private $hrvatskaPostaUrl;
    private $postaSlovenijeUrl;

    const HRVATSKA_COUNTRY_ID = 1;
    const SLOVENIJA_COUNTRY_ID = 3;

    public function initialize()
    {
        parent::initialize();

        $this->hrvatskaPostaUrl = "https://www.posta.hr/mjestaRh.aspx?vrsta=xml";
        $this->postaSlovenijeUrl = "http://b2b.posta.si/CrxWebService/CrnService.aspx?Uid=PosNaslovne&Key=" . $_ENV["POSTA_SLOVENIJE_KEY"] . "&Method=PosteVse";

        $this->asCity = $this->entityManager->getAttributeSetByCode("city");
        $this->asRegion = $this->entityManager->getAttributeSetByCode("region");
    }

    /**
     * @param false $deleteNonExisting
     * @return array
     * @throws \Exception
     */
    public function importHrvatskaPostaCities($deleteNonExisting = false)
    {
        if (!$xml = simplexml_load_file($this->hrvatskaPostaUrl)) {
            throw new \Exception("Error loading xml");
        }

        $existingRegions = $this->getEntitiesArray(["id", "name", "country_id"], "region_entity", ["country_id", "name"], "", sprintf("WHERE country_id = %s", self::HRVATSKA_COUNTRY_ID));
        $existingCities = $this->getEntitiesArray(["id", "name", "postal_code", "country_id", "entity_state_id"], "city_entity", ["country_id", "postal_code", "name"], "", sprintf("WHERE entity_state_id = 1 AND country_id = %s", self::HRVATSKA_COUNTRY_ID));

        $insertArray = [
            // region_entity
        ];
        $insertArray2 = [
            // city_entity
        ];
        $updateArray = [
            // region_entity
            // city_entity
        ];

        if ($deleteNonExisting) {
            foreach ($existingCities as $existingCity) {
                if ($existingCity["entity_state_id"] == 1) {
                    $updateArray["city_entity"][$existingCity["id"]] = [
                        "entity_state_id" => 2
                    ];
                }
            }
        }

        $cityArray = [];
        $postOfficeArray = [];

        foreach ($xml as $key => $item) {

            if ($key != "mjesto") {
                continue;
            }

            $postalCode = (string)$item->brojPu;
            $redBroj = (string)$item->redBroj;
            $nazivPu = (string)$item->nazivPu;
            $name = (string)$item->naselje;
            $regionName = StringHelper::mb_ucwords(mb_strtolower((string)$item->zupanija));

            $cityArray[$redBroj] = ["name" => $name, "postalCode" => $postalCode, "regionName" => $regionName];
            $postOfficeArray[$postalCode] = ["name" => $nazivPu, "postalCode" => $postalCode, "regionName" => $regionName];
        }

        unset($xml);

        $updateQuery = "";
        $sortedArray = array_merge(array_values($cityArray), array_values($postOfficeArray));

        foreach ($sortedArray as $item) {

            $name = $item["name"];
            $postalCode = $item["postalCode"];
            $regionName = $item["regionName"];

            $regionKey = self::HRVATSKA_COUNTRY_ID . "_" . $regionName;
            if (!isset($existingRegions[$regionKey])) {
                $regionInsert = new InsertModel($this->asRegion);
                $regionInsert->add("name", $regionName)
                    ->add("country_id", self::HRVATSKA_COUNTRY_ID);
                $insertArray["region_entity"][$regionKey] = $regionInsert;
                $updateQuery = "UPDATE region_entity SET uid = MD5(id), modified = NOW(), modified_by = 'system';";
            }

            $cityKey = self::HRVATSKA_COUNTRY_ID . "_" . $postalCode . "_" . $name;
            if (!isset($existingCities[$cityKey])) {
                $cityInsert = new InsertModel($this->asCity);
                $cityInsert->add("postal_code", $postalCode)
                    ->add("name", $name)
                    ->add("country_id", self::HRVATSKA_COUNTRY_ID);
                if (!isset($existingRegions[$regionKey])) {
                    $cityInsert->addLookup("region_id", $regionKey, "region_entity");
                } else {
                    $cityInsert->add("region_id", $existingRegions[$regionKey]["id"]);
                }
                $insertArray2["city_entity"][$cityKey] = $cityInsert;
            } else {
                unset($updateArray["city_entity"][$existingCities[$cityKey]["id"]]);
            }
        }

        unset($existingRegions);
        unset($existingCities);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $reselectArray["region_entity"] = $this->getEntitiesArray(["id", "name", "country_id"], "region_entity", ["country_id", "name"]);
        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        if (!empty($updateQuery)) {
            $this->databaseContext->executeNonQuery($updateQuery);
        }

        return [];
    }

    /**
     * @param false $deleteNonExisting
     * @return array
     * @throws \Exception
     */
    public function importPostaSlovenijeCities($deleteNonExisting = false)
    {
        if (!isset($_ENV["POSTA_SLOVENIJE_KEY"]) || empty($_ENV["POSTA_SLOVENIJE_KEY"])) {
            throw new \Exception("POSTA_SLOVENIJE_KEY is empty");
        }
        if (!$xml = simplexml_load_file($this->postaSlovenijeUrl)) {
            throw new \Exception("Error loading xml");
        }

        $existingCities = $this->getEntitiesArray(["id", "name", "postal_code", "country_id"], "city_entity", ["country_id", "postal_code", "name"], "", sprintf("WHERE entity_state_id = 1 AND country_id = %s", self::SLOVENIJA_COUNTRY_ID));

        $insertArray = [
            // city_entity
        ];
        $updateArray = [
            // city_entity
        ];

        if ($deleteNonExisting) {
            foreach ($existingCities as $existingCity) {
                if ($existingCity["entity_state_id"] == 1) {
                    $updateArray["city_entity"][$existingCity["id"]] = [
                        "entity_state_id" => 2
                    ];
                }
            }
        }

        foreach ($xml as $key => $item) {

            if ($key != "DataRow") {
                continue;
            }
            
            $name = (string)$item->NazivPosteDolgi;
            $postalCode = (string)$item->PostaId;

            $cityKey = self::SLOVENIJA_COUNTRY_ID . "_" . $postalCode . "_" . $name;
            if (!isset($existingCities[$cityKey])) {
                $cityInsert = new InsertModel($this->asCity);
                $cityInsert->add("postal_code", $postalCode)
                    ->add("name", $name)
                    ->add("country_id", self::SLOVENIJA_COUNTRY_ID);
                $insertArray["city_entity"][$cityKey] = $cityInsert->getArray();
            } else {
                unset($updateArray["city_entity"][$existingCities[$cityKey]["id"]]);
            }
        }

        unset($existingCities);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        return [];
    }
}
