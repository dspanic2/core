<?php

namespace AppBundle\Blocks;

use AppBundle\Managers\FormManager;
use AppBundle\Abstracts\AbstractBaseBlock;

class AttributeGroupBlock extends AbstractBaseBlock
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

        $blockContent = $this->pageBlock->getContent();

        $disableEdit = false;
        if (!empty($blockContent)) {
            $blockContent = json_decode($blockContent, true);
            if (isset($blockContent["disableEdit"]) && !empty($blockContent["disableEdit"])) {
                $disableEdit = true;
            }
            if (isset($blockContent["tooltip"])) {
                $this->pageBlockData["tooltip"] = $blockContent["tooltip"];
            }
        }

        $this->pageBlockData["disable_edit"] = $disableEdit;

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
        $data = json_decode($this->pageBlock->getContent(),true);

        $disableEdit = false;
        if (isset($data["disableEdit"])) {
            $disableEdit = $data["disableEdit"];
        }
        $tooltip = "";
        if (isset($data["tooltip"])) {
            $tooltip = $data["tooltip"];
        }

        return array(
            'entity' => $this->pageBlock,
            'disable_edit' => $disableEdit,
            'attribute_groups' => $attributeGroups,
            "tooltip" => $tooltip,
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        $settings = [];
        $p = $_POST;

        $attributeGroupsContext = $this->container->get('attribute_group_context');
        $attributeGroup = $attributeGroupsContext->getById($data["relatedId"]);

        $attributeSet = $attributeGroup->getAttributeSet();
        $this->pageBlock->setAttributeSet($attributeSet);
        $this->pageBlock->setEntityType($attributeSet->getEntityType());

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setRelatedId($attributeGroup->getId());
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        $settings["disableEdit"] = false;
        if (isset($p["disableEdit"]) && !empty($p["disableEdit"])) {
            $settings["disableEdit"] = true;
        }
        $settings["tooltip"] = $data["tooltip"];

        $this->pageBlock->setContent(json_encode($settings));

        return $blockManager->save($this->pageBlock);
    }

    public function isVisible()
    {
        //Check permission
        return true;
    }
}
