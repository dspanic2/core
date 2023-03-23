<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;

class GoogleMapsBlock extends AbstractBaseBlock
{
    /** @var EntityManager $entityManager */
    protected $entityManager;

    public function GetPageBlockTemplate()
    {
        return "AppBundle:Block:" . $this->pageBlock->getType() . ".html.twig";
    }

    public function GetPageBlockData()
    {
        $this->entityManager = $this->container->get("entity_manager");

        if (empty($this->pageBlock->getAttributeSet())) {
            return $this->pageBlockData;
        }

        $entity = $this->entityManager->getEntityByEntityTypeAndId($this->pageBlock->getEntityType(), $this->pageBlockData["id"]);
        if (empty($entity)) {
            return false;
        }

        $settings = $this->pageBlock->getContent();
        if (!empty($settings)) {
            $settings = json_decode($settings, true);
        }

        if (empty($settings) || !isset($settings["lat"]) || empty($settings["lat"])) {
            $lat_attribute_code = "lat";
            $lng_attribute_code = "lng";
            $name_attribute_code = "name";
        } else {
            $lat_attribute_code = $settings["lat"];
            $lng_attribute_code = $settings["lng"];
            $name_attribute_code = $settings["gmaps_title"];
        }

        $getterLat = EntityHelper::makeGetter($lat_attribute_code);
        $getterLng = EntityHelper::makeGetter($lng_attribute_code);
        $getterName = EntityHelper::makeGetter($name_attribute_code);

        if (!method_exists($entity, $getterLat) || !method_exists($entity, $getterLng) || !method_exists($entity, $getterName)) {
            return false;
        }

        $this->pageBlockData["lat"] = $entity->{$getterLat}();
        $this->pageBlockData["lng"] = $entity->{$getterLng}();
        $this->pageBlockData["gmaps_title"] = $entity->{$getterName}();
        $this->pageBlockData["gmaps_key"] = $_ENV["GMAPS_KEY"];

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return "AppBundle:BlockSettings:" . $this->pageBlock->getType() . ".html.twig";
    }

    public function GetPageBlockSetingsData()
    {
        $attributeSetContext = $this->container->get("attribute_set_context");
        $attributeSets = $attributeSetContext->getAll();

        $attributes = array();

        if (!empty($this->pageBlock->getEntityType())) {
            $attributeContext = $this->container->get("attribute_context");
            $attributes = $attributeContext->getBy(array("entityType" => $this->pageBlock->getEntityType()));
        }

        $content = json_decode($this->pageBlock->getContent(), true);
        if (!isset($content["lat"])) {
            $content["lat"] = "";
        }
        if (!isset($content["lng"])) {
            $content["lng"] = "";
        }
        if (!isset($content["gmaps_title"])) {
            $content["gmaps_title"] = "";
        }

        return array(
            "entity" => $this->pageBlock,
            "attribute_sets" => $attributeSets,
            "attributes" => $attributes,
            "lat" => $content["lat"],
            "lng" => $content["lng"],
            "gmaps_title" => $content["gmaps_title"]
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get("block_manager");

        $attributeSetContext = $this->container->get("attribute_set_context");
        $attributeSet = $attributeSetContext->getById($data["attributeSet"]);

        $content["lat"] = $data["lat"];
        $content["lng"] = $data["lng"];
        $content["gmaps_title"] = $data["gmaps_title"];
        $content = json_encode($content);

        $this->pageBlock->setAttributeSet($attributeSet);
        $this->pageBlock->setEntityType($attributeSet->getEntityType());
        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);
        $this->pageBlock->setContent($content);

        return $blockManager->save($this->pageBlock);
    }

    public function isVisible()
    {
        $id = $this->pageBlockData["id"];
        if (empty($id)) {
            return false;
        }

        return true;
    }
}
