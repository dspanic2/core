<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FileManager;
use Doctrine\Common\Util\Inflector;
use Monolog\Logger;

class GalleryBlock extends AbstractBaseBlock
{
    /** @var Logger $logger */
    protected $logger;

    public function GetPageBlockTemplate()
    {
        if (!isset($this->pageBlockData["fileAttributeId"]) || empty($this->pageBlockData["fileAttributeId"])) {
            return ("AppBundle:Block:block_error.html.twig");
        } else {
            return ("AppBundle:Block:" . $this->pageBlock->getType() . ".html.twig");
        }
    }

    public function isVisible()
    {
        if(is_array($this->pageBlockData["page"])){
            return false;
        }
        return $this->isVisible;
    }

    public function GetPageBlockData()
    {
        /** @var Attribute $fileAttribute */
        $fileAttribute = null;
        $this->pageBlockData["parentAttribute"] = false;
        $this->pageBlockData["order"] = false;
        $this->pageBlockData["selected"] = false;

        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get("entity_manager");

        $attributes = $entityManager->getAttributesOfEntityType($this->pageBlock->getEntityType()->getEntityTypeCode(), false);

        /** @var Attribute $attribute */
        foreach ($attributes as $attribute) {
            if ($attribute->getAttributeCode() == "file") {
                $fileAttribute = $attribute;
            }
            /*if(!empty($this->pageBlock->getRelatedId()) && $this->pageBlock->getRelatedId() == $attribute->getId()){
                $this->pageBlockData["primaryAttributeCode"] = $attribute;
            }*/
            if (!empty($attribute->getLookupEntityType()) && $attribute->getLookupEntityType()->getId() == $this->pageBlockData["page"]->getEntityType()->getId()) {
                $this->pageBlockData["parentAttribute"] = $attribute;
            }
            if ($attribute->getAttributeCode() == "ord") {
                $this->pageBlockData["order"] = $attribute;
            }
            if ($attribute->getAttributeCode() == "selected") {
                $this->pageBlockData["selected"] = $attribute;
            }
        }

        if (empty($fileAttribute)) {
            $this->logger = $this->container->get("logger");
            $this->logger->error("Block id " . $this->pageBlock->getId() . " of type " . $this->pageBlock->getType() . " has no file attribute");
            return $this->pageBlockData;
        }

        /** @var FileManager $fileManager */
        $fileManager = $this->getContainer()->get("file_manager");

        $sourceFolder = $fileManager->getSourcePath($fileAttribute->getFolder());

        $this->pageBlockData["fileAttributeId"] = $fileAttribute->getId();
        $this->pageBlockData["fileAttributeFolder"] = $sourceFolder;
        $this->pageBlockData["entityTypeCode"] = $this->pageBlock->getEntityType()->getEntityTypeCode();
        $this->pageBlockData["dropzoneSettings"] = json_decode($fileAttribute->getValidator());
        $this->pageBlockData["entities"] = Array();

        if (!empty($this->pageBlockData["id"])) {
            if (!empty($this->pageBlockData["parentAttribute"])) {
                $entities = $fileManager->getRelatedFiles($this->pageBlock->getEntityType(), $this->pageBlockData["parentAttribute"], $this->pageBlockData["id"], $this->pageBlockData["order"]);
                if (!empty($entities)) {

                    $webDir = $_ENV["WEB_PATH"];
                    foreach ($entities as $entity) {
                        if (file_exists($webDir . $sourceFolder . $entity->getFile())) {
                            $this->pageBlockData["entities"][] = $entity;
                        }
                    }
                }
            }
        }

        $settings = $this->pageBlock->getContent();
        if (!is_array($settings)) {
            $settings = json_decode($settings, true);
        }
        if (isset($settings["tooltip"])) {
            $this->pageBlockData["tooltip"] = $settings["tooltip"];
        }

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return "AppBundle:BlockSettings:" . $this->pageBlock->getType() . ".html.twig";
    }

    public function GetPageBlockSetingsData()
    {
        $attributeSets = Array();

        /** @var AttributeContext $attributeContext */
        $attributeContext = $this->container->get("attribute_context");
        $availableAttributes = $attributeContext->getBy(Array("frontendType" => "file"));

        if (!empty($availableAttributes)) {
            /** @var AttributeSetContext $attributeSetContext */
            $attributeSetContext = $this->container->get("attribute_set_context");
            /** @var Attribute $availableAttribute */
            foreach ($availableAttributes as $availableAttribute) {
                $tempAttributeSets = $attributeSetContext->getAttributeSetsByEntityType($availableAttribute->getEntityType());
                if (!empty($tempAttributeSets)) {
                    /** @var AttributeSet $tempAttributeSet */
                    foreach ($tempAttributeSets as $tempAttributeSet) {
                        $attributeSets[] = $tempAttributeSet;
                    }
                }
            }
        }

        $contentJson = $this->pageBlock->getContent();
        $content = json_decode($contentJson, true);

        $tooltip = "";
        if (!empty($content)) {
            if (isset($content["tooltip"])) {
                $tooltip = $content["tooltip"];
            }
        }

        return array(
            "entity" => $this->pageBlock,
            "attribute_sets" => $attributeSets,
            "tooltip" => $tooltip,
        );
    }

    public function SavePageBlockSettings($data)
    {
        /** @var BlockManager $blockManager */
        $blockManager = $this->container->get("block_manager");

        /** @var AttributeSetContext $attributeSetContext */
        $attributeSetContext = $this->container->get("attribute_set_context");

        /** @var AttributeSet $attributeSet */
        $attributeSet = $attributeSetContext->getById($data["attributeSet"]);

        $this->pageBlock->setAttributeSet($attributeSet);
        $this->pageBlock->setEntityType($attributeSet->getEntityType());
        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        $content = [];
        $content["tooltip"] = $data["tooltip"];
        $this->pageBlock->setContent(json_encode($content));

        return $blockManager->save($this->pageBlock);
    }
}
