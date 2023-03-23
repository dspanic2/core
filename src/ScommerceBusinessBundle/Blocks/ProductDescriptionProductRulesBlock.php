<?php

namespace ScommerceBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Managers\BlockManager;
use CrmBusinessBundle\Entity\PaymentTypeRuleEntity;
use CrmBusinessBundle\Managers\PaymentTypeRulesManager;
use ScommerceBusinessBundle\Entity\SFrontBlockEntity;
use ScommerceBusinessBundle\Managers\FrontProductsRulesManager;

class ProductDescriptionProductRulesBlock extends AbstractBaseBlock
{
    public function GetPageBlockTemplate()
    {
        return 'ScommerceBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig';
    }

    public function GetPageBlockData()
    {
        /** @var SFrontBlockEntity $frontBlock */
        $frontBlock = $this->pageBlockData["model"]["entity"];

        /** @var FrontProductsRulesManager $rulesManager */
        $rulesManager = $this->getContainer()->get("front_product_rules_manager");

        $this->pageBlockData["filtered_attributes"] = $rulesManager->getFilteredAttributes();
        $this->pageBlockData["existing_attribute_fields"] = $rulesManager->getRenderedExistingAttributeFields($frontBlock->getRules());

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'ScommerceBusinessBundle:BlockSettings:' . $this->pageBlock->getType() . '.html.twig';
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