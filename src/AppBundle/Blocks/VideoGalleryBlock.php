<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FileManager;
use Monolog\Logger;

class VideoGalleryBlock extends AbstractBaseBlock
{
    /** @var Logger $logger */
    protected $logger;

    public function GetPageBlockTemplate()
    {
        if (!isset($this->pageBlockData["fileAttributeId"]) || empty($this->pageBlockData["fileAttributeId"])) {
            return ('AppBundle:Block:block_error.html.twig');
        } else {
            return ('AppBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
        }
    }

    public function GetPageBlockData()
    {
        /** @var AttributeContext $attributeContext */
        $attributeContext = $this->container->get("attribute_context");

        $fileAttribute = $attributeContext->getOneBy(Array("entityType" => $this->pageBlock->getEntityType(), "attributeCode" => "file"));
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
        $this->pageBlockData["dropzoneSettings"] = json_decode($fileAttribute->getValidator());
        if (!empty($this->pageBlock->getRelatedId())) {
            $this->pageBlockData["primaryAttributeCode"] = $this->attributeContext->getById($this->pageBlock->getRelatedId())->getAttributeCode();
        }
        //TODO OVDJE CE TREBATI FIXATI PUTANJU PO UZORUI NA GALLERY BLOCK

        $this->pageBlockData["entities"] = Array();
        if (!empty($this->pageBlockData["id"])) {
            /** @var EntityManager $entityManager */
            $entityManager = $this->container->get("entity_manager");

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
            $compositeFilter->addFilter(new SearchFilter($this->pageBlockData["page"]->getEntityType()->getEntityTypeCode(), "eq", $this->pageBlockData["id"]));

            $compositeFilters = new CompositeFilterCollection();
            $compositeFilters->addCompositeFilter($compositeFilter);

            $this->pageBlockData["entities"] = $entityManager->getEntitiesByEntityTypeAndFilter($this->pageBlock->getEntityType(), $compositeFilters);
        }

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return "AppBundle:BlockSettings:" . $this->pageBlock->getType() . ".html.twig";
    }

    public function GetPageBlockSetingsData()
    {
        /** @var AttributeContext $attributeContext */
        $attributeContext = $this->container->get("attribute_context");

        $availableAttributes = $attributeContext->getBy(Array("frontendType" => "file"));
        $attributeSetIds = Array();
        $attributeSets = Array();

        if (!empty($availableAttributes)) {
            $entityAttributeContext = $this->container->get("entity_attribute_context");

            foreach ($availableAttributes as $availableAttribute) {
                $entityAttributes = $entityAttributeContext->getByAttribute($availableAttribute);
                if (!empty($entityAttributes)) {
                    foreach ($entityAttributes as $entityAttribute) {
                        $attributeSetIds[] = $entityAttribute->getAttributeSet()->getId();
                    }
                }
            }

            if (!empty($attributeSetIds)) {
                $attributeSetIds = array_unique($attributeSetIds);
                /** @var AttributeSetContext $attributeSetContext */
                $attributeSetContext = $this->container->get("attribute_set_context");
                $attributeSets = $attributeSetContext->getEntitiesOfTypeByIds($attributeSetIds);
            }
        }

        return array(
            "entity" => $this->pageBlock,
            "attribute_sets" => $attributeSets,
        );
    }

    public function SavePageBlockSettings($data)
    {
        /** @var BlockManager $blockManager */
        $blockManager = $this->container->get("block_manager");

        /** @var AttributeSetContext $attributeSetContext */
        $attributeSetContext = $this->container->get("attribute_set_context");
        $attributeSet = $attributeSetContext->getById($data["attributeSet"]);

        $this->pageBlock->setAttributeSet($attributeSet);
        $this->pageBlock->setEntityType($attributeSet->getEntityType());
        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }
}
