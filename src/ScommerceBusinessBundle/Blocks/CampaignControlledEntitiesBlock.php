<?php

namespace ScommerceBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\DatabaseContext;
use AppBundle\Helpers\StringHelper;
use CrmBusinessBundle\Entity\CampaignEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;

class CampaignControlledEntitiesBlock extends AbstractBaseBlock
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;

    public function GetPageBlockTemplate()
    {
        return 'ScommerceBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig';
    }

    public function GetPageBlockData()
    {
        /** @var CampaignEntity $campaign */
        $campaign = $this->pageBlockData["model"]["entity"];

        if (!empty($campaign->getId())) {
            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->container->get("database_context");
            }
            if (empty($this->getPageUrlExtension)) {
                $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
            }

            $q = 'SELECT attribute_code, backend_table, frontend_label FROM attribute WHERE lookup_entity_type_id = (SELECT id FROM entity_type WHERE entity_type_code = "campaign") AND backend_table NOT LIKE "%_link%";';
            $res = $this->databaseContext->getAll($q);

            if (!empty($res)) {
                $this->pageBlockData["model"]["entities"] = [];

                foreach ($res as $row) {
                    $entityTypeCode = str_ireplace("_entity", "", $row["backend_table"]);
                    if (!isset($this->pageBlockData["model"]["entities"][$entityTypeCode])) {
                        $label = str_ireplace("_entity", "", $entityTypeCode);
                        $label = str_ireplace("_", " ", $label);
                        $label = StringHelper::mb_ucfirst($label);
                        $this->pageBlockData["model"]["entities"][$entityTypeCode] = [
                            "label" => $label,
                            "attributes" => [],
                        ];
                    }

                    $q = "SELECT * FROM {$row["backend_table"]} WHERE entity_state_id=1 AND {$row["attribute_code"]}={$campaign->getId()};";
                    $res = $this->databaseContext->getAll($q);
                    if (!empty($res)) {
                        if (!isset($this->pageBlockData["model"]["entities"][$entityTypeCode]["attributes"][$row["attribute_code"]])) {
                            $this->pageBlockData["model"]["entities"][$entityTypeCode]["attributes"][$row["attribute_code"]] = [
                                "label" => $row["frontend_label"],
                                "entities" => [],
                            ];
                        }
                        foreach ($res as $entityData) {
                            $name = "";
                            if (isset($entityData["name"])) {
                                if (StringHelper::isJson($entityData["name"])) {
                                    $d = json_decode($entityData["name"], true);
                                    $name = $this->getPageUrlExtension->getArrayStoreAttribute($_ENV["DEFAULT_STORE_ID"], $d);
                                } else {
                                    $name = $entityData["name"];
                                }
                            }
                            if (empty($name) && isset($entityData["main_title"])) {
                                if (StringHelper::isJson($entityData["main_title"])) {
                                    $d = json_decode($entityData["main_title"], true);
                                    $name = $this->getPageUrlExtension->getArrayStoreAttribute($_ENV["DEFAULT_STORE_ID"], $d);
                                } else {
                                    $name = $entityData["main_title"];
                                }
                            }

                            if (empty($name)) {
                                $name = $this->pageBlockData["model"]["entities"][$entityTypeCode]["label"] . " " . $entityData["id"];
                            }
                            $this->pageBlockData["model"]["entities"][$entityTypeCode]["attributes"][$row["attribute_code"]]["entities"][] = [
                                "id" => $entityData["id"],
                                "name" => $name,
                            ];
                        }
                    }
                }
            }
        }
        return $this->pageBlockData;
    }
}