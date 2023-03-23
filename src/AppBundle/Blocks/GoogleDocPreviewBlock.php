<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\EntityAttributeContext;
use AppBundle\Managers\EntityManager;

class GoogleDocPreviewBlock extends AbstractBaseBlock
{
    /**@var EntityManager $entityManager */
    protected $entityManager;



    public function GetPageBlockTemplate()
    {
        return 'AppBundle:Block:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockData()
    {
        $this->entityManager=$this->container->get('entity_manager');
        /** @var AttributeContext $attributeContext */
        $attributeContext = $this->container->get('attribute_context');

        //dump($this->pageBlock->getAttributeSet()->getAttributeSetCode(),$this->pageBlockData["id"]);die;
        $entity = $this->entityManager->getEntityByEntityTypeAndId($this->pageBlock->getEntityType(), $this->pageBlockData["id"]);

        $attribute = $attributeContext->getOneBy(array('entityType' => $this->pageBlock->getAttributeSet()->getEntityType(), 'attributeCode' => 'file'));

        $this->pageBlockData["attribute"] = $attribute;
        $this->pageBlockData["model"] = $entity;
        if (empty($this->pageBlockData["model"])) {
            return false;
        }

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        $attributeContext = $this->container->get('attribute_context');

        $availableAttributes = $attributeContext->getBy(array('frontendType' => 'file'));
        $attributeSetIds = array();
        $attributeSets = array();
        if (!empty($availableAttributes)) {
            $entityAttributeContext = $this->container->get('entity_attribute_context');

            foreach ($availableAttributes as $availableAttribute) {
                /** @var EntityAttributeContext $entityAttributes */
                $entityAttribute = $entityAttributeContext->getOneBy(array("attribute" => $availableAttribute));
                $attributeSetIds[] = $entityAttribute->getAttributeSet()->getId();
            }

            if (!empty($attributeSetIds)) {
                $attributeSetIds = array_unique($attributeSetIds);

                $attributeSetContext = $this->container->get('attribute_set_context');

                $attributeSets = $attributeSetContext->getEntitiesOfTypeByIds($attributeSetIds);
            }
        }

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
        $id = $this->pageBlockData["id"];

        if ($id ==null) {
            return false;
        } else {
            return true;
        }
    }
}
