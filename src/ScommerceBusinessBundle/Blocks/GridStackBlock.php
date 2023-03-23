<?php

namespace ScommerceBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Managers\EntityManager;

class GridStackBlock extends AbstractBaseBlock
{
    public function GetPageBlockTemplate()
    {
        return ('ScommerceBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
    }

    public function GetPageBlockData()
    {
        if (!empty($this->pageBlockData["id"])) {

            /** @var EntityManager $entityManager */
            $entityManager = $this->getContainer()->get("entity_manager");

            $this->pageBlockData["model"]["entity"] = $entityManager->getEntityByEntityTypeAndId($this->pageBlockData["block"]->getEntityType(), $this->pageBlockData["id"]);
        }

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'ScommerceBusinessBundle:BlockSettings:' . $this->pageBlock->getType() . '.html.twig';
    }


    public function GetPageBlockSetingsData()
    {
        $attributeSetContext = $this->container->get('attribute_set_context');
        $attributeSets = $attributeSetContext->getAll();

        return array(
            'entity' => $this->pageBlock,
            'attribute_sets' => $attributeSets,
        );
    }

    public function SavePageBlockSettings($data)
    {

        $blockManager = $this->container->get('block_manager');

        $attributeSetContext = $this->container->get('attribute_set_context');

        $attributeSet = $attributeSetContext->getById($data["attributeSet"]);
        $this->pageBlock->setAttributeSet($attributeSet);
        $this->pageBlock->setEntityType($attributeSet->getEntityType());

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }

    public function isVisible()
    {
        if (empty($this->pageBlockData["id"])) {
            return false;
        } else {

            /** Hide if on front block and type is not container */
            if ($this->pageBlockData["block"]->getEntityType()->getEntityTypeCode() == "s_front_block") {

                /** @var EntityManager $entityManager */
                $entityManager = $this->getContainer()->get("entity_manager");

                $parent = $entityManager->getEntityByEntityTypeAndId($this->pageBlockData["block"]->getEntityType(), $this->pageBlockData["id"]);

                if ($parent->getType() != "container") {
                    return false;
                }
            }
        }
        return true;
    }
}
