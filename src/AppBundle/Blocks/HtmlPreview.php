<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;

class HtmlPreview extends AbstractBaseBlock
{
    /**@var EntityManager $entityManager*/
    protected $entityManager;

    public function GetPageBlockTemplate()
    {
        return ('AppBundle:Block:'.$this->pageBlock->getType().'.html.twig');
    }

    public function GetPageBlockData()
    {
        $this->entityManager = $this->container->get("entity_manager");

        $data = json_decode($this->pageBlock->getContent(),true);

        if(!isset($data["attr"]) || empty($data["attr"])){
            return $this->pageBlockData;
        }

        $entity = $this->entityManager->getEntityByEntityTypeAndId($this->pageBlock->getEntityType(), $this->pageBlockData["id"]);
        if (empty($entity)) {
            return $this->pageBlockData;
        }

        $getter = EntityHelper::makeGetter($data["attr"]);

        if(!EntityHelper::checkIfMethodExists($entity,$getter)){
            return $this->pageBlockData;
        }

        $htmlJson = $entity->{$getter}();

        if(empty($htmlJson)){
            return $this->pageBlockData;
        }

        $htmlJson = json_decode($htmlJson,true);

        if(!isset($htmlJson["html"]) || empty($htmlJson["html"])){
            return $this->pageBlockData;
        }

        $this->pageBlockData["model"]["content"] = $htmlJson["html"];

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
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
        if (!isset($content["attr"])) {
            $content["attr"] = "";
        }

        return array(
            "entity" => $this->pageBlock,
            "attribute_sets" => $attributeSets,
            "attributes" => $attributes,
            "attr" => $content["attr"]
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get("block_manager");

        $attributeSetContext = $this->container->get("attribute_set_context");
        $attributeSet = $attributeSetContext->getById($data["attributeSet"]);

        $content["attr"] = $data["attr"];
        $content = json_encode($content);

        $this->pageBlock->setAttributeSet($attributeSet);
        $this->pageBlock->setEntityType($attributeSet->getEntityType());
        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);
        $this->pageBlock->setContent($content);

        return $blockManager->save($this->pageBlock);
    }
}
