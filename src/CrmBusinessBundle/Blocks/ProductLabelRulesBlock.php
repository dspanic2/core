<?php

namespace CrmBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Managers\BlockManager;
use CrmBusinessBundle\Entity\ProductLabelEntity;
use CrmBusinessBundle\Managers\ProductLabelRulesManager;

class ProductLabelRulesBlock extends AbstractBaseBlock
{
    public function GetPageBlockTemplate()
    {
        return 'CrmBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig';
    }

    public function GetPageBlockData()
    {
        /** @var ProductLabelEntity $productLabel */
        $productLabel = $this->pageBlockData["model"]["entity"];

        /** @var ProductLabelRulesManager $productLabelRulesManager */
        $productLabelRulesManager = $this->getContainer()->get("product_label_rules_manager");

        $this->pageBlockData["filtered_attributes"] = $productLabelRulesManager->getFilteredAttributes();
        $this->pageBlockData["existing_attribute_fields"] = $productLabelRulesManager->getRenderedExistingAttributeFields($productLabel->getRules());

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