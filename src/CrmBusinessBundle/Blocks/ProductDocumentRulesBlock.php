<?php

namespace CrmBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Managers\BlockManager;
use CrmBusinessBundle\Entity\ProductDocumentRuleEntity;
use CrmBusinessBundle\Managers\ProductAttributeFilterRulesManager;

class ProductDocumentRulesBlock extends AbstractBaseBlock
{
    public function GetPageBlockTemplate()
    {
        return 'CrmBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig';
    }

    public function GetPageBlockData()
    {
        /** @var ProductDocumentRuleEntity $productDocumentRule */
        $productDocumentRule = $this->pageBlockData["model"]["entity"];

        /** @var ProductAttributeFilterRulesManager $productAttributeFilterRulesManager */
        $productAttributeFilterRulesManager = $this->getContainer()->get("product_attribute_filter_rules_manager");

        $this->pageBlockData["filtered_attributes"] = $productAttributeFilterRulesManager->getFilteredAttributes();
        $this->pageBlockData["existing_attribute_fields"] = $productAttributeFilterRulesManager->getRenderedExistingAttributeFields($productDocumentRule->getRules());

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