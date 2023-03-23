<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\EntityManager;

class UserFormBlock extends AbstractBaseBlock
{
    /**@var EntityManager $entityManager */
    protected $entityManager;

    public function GetPageBlockTemplate()
    {
        return 'AppBundle:Block:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockData()
    {

        $this->entityManager = $this->container->get('entity_manager');

        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->entityManager->getAttributeSetByCode("core_user");

        if (isset($this->pageBlockData['id'])) {
            $entity_id = $this->pageBlockData['id'];
        } else {
            $entity_id = null;
        }

        if ($entity_id == "") {
            $entity = $this->entityManager->getNewEntityByAttributSetName("core_user");
        } else {
            $entity = $this->entityManager->getEntityByEntityTypeAndId($attributeSet->getEntityType(), $entity_id);
        }

        $this->pageBlockData["model"]["entity"] = $entity;

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        $attributeSetContext = $this->container->get('attribute_set_context');
        $attributeSets = $attributeSetContext->getBy(array('attributeSetCode' => 'core_user'));

        return array(
            'entity' => $this->pageBlock,
            'attribute_sets' => $attributeSets,
            'managed_entity_type' => "page_block",
            'show_add_button' => 1,
            'show_content' => 1
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        if (isset($data["attributeSet"])) {
            $attributeSetContext = $this->container->get('attribute_set_context');
            $attributeSet = $attributeSetContext->getById($data["attributeSet"]);
            $this->pageBlock->setAttributeSet($attributeSet);
            $this->pageBlock->setEntityType($attributeSet->getEntityType());
        }

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setContent($data["content"]);

        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }
}
