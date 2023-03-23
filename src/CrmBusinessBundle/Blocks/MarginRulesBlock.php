<?php

namespace CrmBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Managers\BlockManager;
use CrmBusinessBundle\Entity\MarginRuleEntity;
use CrmBusinessBundle\Managers\MarginRulesManager;

class MarginRulesBlock extends AbstractBaseBlock
{
    public function GetPageBlockTemplate()
    {
        return 'CrmBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig';
    }

    public function GetPageBlockData()
    {
        /** @var MarginRuleEntity $marginRule */
        $marginRule = $this->pageBlockData["model"]["entity"];

        /** @var MarginRulesManager $marginRuleManager */
        $marginRuleManager = $this->getContainer()->get("margin_rules_manager");

        $this->pageBlockData["filtered_attributes"] = $marginRuleManager->getFilteredAttributes();
        $this->pageBlockData["existing_attribute_fields"] = $marginRuleManager->getRenderedExistingAttributeFields($marginRule->getRules());

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $this->pageBlockData["brand_attribute_id"] = $this->databaseContext->getSingleResult("SELECT id as count FROM attribute WHERE attribute_code = 'brand_id' AND backend_table = 'product_entity';");
        $this->pageBlockData["supplier_attribute_id"] = $this->databaseContext->getSingleResult("SELECT id as count FROM attribute WHERE attribute_code = 'supplier_id' AND backend_table = 'product_entity';");
        $this->pageBlockData["product_groups_attribute_id"] = $this->databaseContext->getSingleResult("SELECT id as count FROM attribute WHERE attribute_code = 'product_groups' AND backend_table = 'product_entity';");

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