<?php

namespace CrmBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Managers\BlockManager;
use CrmBusinessBundle\Entity\BulkPriceEntity;
use CrmBusinessBundle\Managers\BulkPriceManager;

class BulkPricesBlock extends AbstractBaseBlock
{
    public function GetPageBlockTemplate()
    {
        return 'CrmBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig';
    }

    public function GetPageBlockData()
    {
        /** @var BulkPriceEntity $entity */
        $entity = $this->pageBlockData["model"]["entity"];

        /** @var BulkPriceManager $bulkPriceManager */
        $bulkPriceManager = $this->getContainer()->get("bulk_price_manager");

        $this->pageBlockData["filtered_attributes"] = $bulkPriceManager->getFilteredAttributes();
        $this->pageBlockData["existing_attribute_fields"] = $bulkPriceManager->getRenderedExistingAttributeFields($entity->getRules());

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'CrmBusinessBundle:BlockSettings:' . $this->pageBlock->getType() . '.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        /** @var EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->getContainer()->get("entity_type_context");
        $entityTypes = $entityTypeContext->getAll();

        return array(
            'entity' => $this->pageBlock,
            'entity_types' => $entityTypes,
        );
    }

    public function SavePageBlockSettings($data)
    {
        /** @var BlockManager $blockManager */
        $blockManager = $this->getContainer()->get("block_manager");

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }
}