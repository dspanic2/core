<?php

namespace CrmBusinessBundle\Blocks;

use AppBundle\Managers\FormManager;
use AppBundle\Abstracts\AbstractBaseBlock;

class AccountFormBlock extends AbstractBaseBlock
{
    /**@var FormManager $formManager */
    protected $formManager;

    public function GetPageBlockTemplate()
    {
        if (isset($this->pageBlockData["model"]) && !empty($this->pageBlockData["model"])) {
            return ('AppBundle:Block:'.$this->pageBlock->getType().'.html.twig');
        } else {
            return ('AppBundle:Block:block_error.html.twig');
        }
    }

    public function GetPageBlockData()
    {
        if (!empty($this->pageBlock->getEntityType())) {
            $this->formManager = $this->factoryManager->loadFormManager($this->pageBlock->getEntityType()->getEntityTypeCode());
            $this->pageBlockData["model"] = $this->formManager->getFormModel($this->pageBlock->getAttributeSet(), $this->pageBlockData["id"], $this->pageBlockData["subtype"], $this->pageBlock->getRelatedId());
        }

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
    }


    public function GetPageBlockSetingsData()
    {
        $attributeGroupsContext = $this->container->get('attribute_group_context');
        $attributeGroups = $attributeGroupsContext->getBy(array(), array("attributeSet" => "asc"));

        return array(
            'entity' => $this->pageBlock,
            'attribute_groups' => $attributeGroups,
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        $attributeGroupsContext = $this->container->get('attribute_group_context');
        $attributeGroup = $attributeGroupsContext->getById($data["relatedId"]);

        $attributeSet = $attributeGroup->getAttributeSet();
        $this->pageBlock->setAttributeSet($attributeSet);
        $this->pageBlock->setEntityType($attributeSet->getEntityType());

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setRelatedId($attributeGroup->getId());
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }

    public function isVisible()
    {
        //Check permission
        return true;
    }
}
