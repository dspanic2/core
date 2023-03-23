<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\RestManager;
use AppBundle\Models\InsertModel;
use AppBundle\Models\UpdateModel;
use Symfony\Component\Console\Helper\ProgressBar;

class MireoApiManager extends DefaultIntegrationImportManager
{
    /** @var string $apiUrl */
    protected $apiUrl;
    /** @var string $apiToken */
    protected $apiToken;
    /** @var AttributeSet $asMireoVehicle */
    protected $asMireoVehicle;
    /** @var AttributeSet $asMireoVehiclePosition */
    protected $asMireoVehiclePosition;
    /** @var AttributeSet $asMireoDrive */
    protected $asMireoDrive;

    public function initialize()
    {
        parent::initialize();

        $this->apiUrl = $_ENV["MIREO_API_URL"];
        $this->apiToken = $_ENV["MIREO_API_TOKEN"];

        $this->asMireoVehicle = $this->entityManager->getAttributeSetByCode("mireo_vehicle");
        $this->asMireoVehiclePosition = $this->entityManager->getAttributeSetByCode("mireo_vehicle_position");
        $this->asMireoDrive = $this->entityManager->getAttributeSetByCode("mireo_drive");

        $this->setRemoteSource("mireo");
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importVehicles()
    {
        $existingMireoVehicles = $this->getEntitiesArray(["id", "has_tracking_device", "remote_id"], "mireo_vehicle_entity", ["remote_id"]);
        $existingMireoVehiclePositions = $this->getEntitiesArray(["a1.id", "mv.remote_id", "a1.utc"], "mireo_vehicle_position_entity", ["remote_id", "utc"], "JOIN mireo_vehicle_entity mv ON a1.mireo_vehicle_id = mv.id");
        $existingMireoDrives = $this->getEntitiesArray(["a1.id", "mv.remote_id", "a1.time_start"], "mireo_drive_entity", ["remote_id", "time_start"], "JOIN mireo_vehicle_entity mv ON a1.mireo_vehicle_id = mv.id");

        $insertArray = [
            // mireo_vehicle_entity
        ];
        $insertArray2 = [
            // mireo_vehicle_position_entity
            // mireo_drive_entity
        ];
        $updateArray = [
            // mireo_vehicle_entity
        ];

        $params = [
            "api_token" => $this->apiToken
        ];

        $utcFrom = strtotime("today -1 month");
        $utcTo = strtotime("today");

        $restManager = new RestManager();

        $data = $restManager->get($this->apiUrl . "/Fleet2009/WebAPIServer/Vehicles/All?" . http_build_query($params));
        if (empty($data)) {
            throw new \Exception("Vehicle data is empty");
        }

        $progressBar = new ProgressBar($this->getConsoleOutput(), count($data));

        foreach ($data as $d) {

            $progressBar->advance();

            if (!isset($existingMireoVehicles[$d["id"]])) {

                $mireoVehicleInsert = new InsertModel($this->asMireoVehicle);
                $mireoVehicleInsert->add("name", $d["name"])
                    ->add("device_type", $d["device_type"])
                    ->add("registration_number", $d["registration_number"])
                    ->add("has_tracking_device", $d["has_tracking_device"])
                    ->add("remote_id", $d["id"]);

                $insertArray["mireo_vehicle_entity"][$d["id"]] = $mireoVehicleInsert->getArray();

            } else {

                $mireoVehicleUpdate = new UpdateModel($existingMireoVehicles[$d["id"]]);
                $mireoVehicleUpdate->add("has_tracking_device", $d["has_tracking_device"]);

                if (!empty($mireoVehicleUpdate->getArray())) {
                    $updateArray["mireo_vehicle_entity"][$d["id"]] = $mireoVehicleUpdate->getArray();
                }
            }

            $params2 = [
                "vehicle_id" => $d["id"],
                "utc_from" => $utcFrom,
                "utc_to" => $utcTo,
                "api_token" => $this->apiToken
            ];

            $data2 = $restManager->get($this->apiUrl . "/Fleet2009/WebAPIServer/Vehicles/Positions?" . http_build_query($params2));
            if (!empty($data2)) {
                foreach ($data2 as $d2) {

                    if (!isset($existingMireoVehiclePositions[$d["id"] . "_" . $d2["utc"]])) {
                        $mireoVehiclePositionInsert = new InsertModel($this->asMireoVehiclePosition);
                        $mireoVehiclePositionInsert->add("speed", $d2["speed"])
                            ->add("utc", $d2["utc"])
                            ->add("pt_x", $d2["pt"]["x"])
                            ->add("pt_y", $d2["pt"]["y"])
                            ->add("distance", $d2["distance"])
                            ->add("course", $d2["course"])
                            ->addLookup("mireo_vehicle_id", $d["id"], "mireo_vehicle_entity");

                        $insertArray2["mireo_vehicle_position_entity"][$d["id"] . "_" . $d2["utc"]] = $mireoVehiclePositionInsert;
                    }
                }
            }

            $params3 = [
                "vehicle_id" => $d["id"],
                "utc_from" => $utcFrom,
                "utc_to" => $utcTo,
                "api_token" => $this->apiToken
            ];

            $data3 = $restManager->get($this->apiUrl . "/Fleet2009/WebAPIServer/Vehicles/Drives?" . http_build_query($params3));
            if (!empty($data3)) {
                foreach ($data3 as $d3) {

                    $timeStart = \DateTime::createFromFormat("U", $d3["from"]["utc"])->format("Y-m-d H:i:s");
                    $timeEnd = \DateTime::createFromFormat("U", $d3["to"]["utc"])->format("Y-m-d H:i:s");

                    if (!isset($existingMireoDrives[$d["id"] . "_" . $timeStart])) {
                        $mireoDriveInsert = new InsertModel($this->asMireoDrive);
                        $mireoDriveInsert->add("time_start", $timeStart)
                            ->add("time_end", $timeEnd)
                            ->add("distance", $d3["meters"])
                            ->add("odometer", $d3["odometer"] ?? null)
                            ->add("working_hours", $d3["working_hours"])
                            ->addLookup("mireo_vehicle_id", $d["id"], "mireo_vehicle_entity");

                        $insertArray2["mireo_drive_entity"][$d["id"] . "_" . $timeStart] = $mireoDriveInsert;
                    }
                }
            }
        }

        $progressBar->finish();
        echo "\n";

        unset($existingMireoVehicles);
        unset($existingMireoVehiclePositions);
        unset($existingMireoDrives);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $reselectArray["mireo_vehicle_entity"] = $this->getEntitiesArray(["id", "remote_id"], "mireo_vehicle_entity", ["remote_id"]);

        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);
        unset($reselectArray);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        return [];
    }
}
