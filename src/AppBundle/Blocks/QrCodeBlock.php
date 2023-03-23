<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeSet;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\EntityManager;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class QrCodeBlock extends AbstractBaseBlock
{
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /**@var AttributeContext $attributeContext */
    protected $attributeContext;

    public function GetPageBlockTemplate()
    {
        return ('AppBundle:Block:'.$this->pageBlock->getType().'.html.twig');
    }

    public function GetPageBlockData()
    {
        if (empty($this->pageBlockData["id"])) {
            return false;
        }

        $settings = json_decode($this->pageBlock->getContent(),true);

        $this->entityManager = $this->container->get("entity_manager");
        $this->attributeContext = $this->container->get("attribute_context");

        if (!isset($settings["attribute_id"]) || empty($settings["attribute_id"])) {
            return false;
        }

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getById($settings["attribute_id"]);
        if (empty($attribute)) {
            return false;
        }

        $getter = EntityHelper::makeGetter($attribute->getAttributeCode());

        /** @var AttributeSet $parentSet */
        $parentSet = $this->entityManager->getAttributeSetByCode($settings["parent_entity"]);
        if (empty($parentSet)) {
            return false;
        }

        /**
         * Parent entity
         */
        $parent = $this->entityManager->getEntityByEntityTypeAndId($parentSet->getEntityType(), $this->pageBlockData["id"]);

        $code = $parent->{$getter}();

        if (empty($code)) {
            return false;
        }

        $this->pageBlockData["model"]["code"] = $code;

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        /** @var EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get('entity_type_context');
        $entityTypes = $entityTypeContext->getAll();

        $contentJSON = $this->pageBlock->getContent();
        $content = json_decode($contentJSON);

        $parent_entity = "";
        $attribute_id = "";
        $parentAttributes = array();

        if ($content != null) {
            $parent_entity = $content->parent_entity;
            if (isset($content->attribute_id)) {
                $attribute_id = $content->attribute_id;
            }

            $parentEntityType = $entityTypeContext->getOneBy(array("entityTypeCode" => $parent_entity));

            if (!empty($parentEntityType)) {

                /** @var AttributeContext $attributeContext */
                $attributeContext = $this->container->get('attribute_context');

                $parentAttributes = $attributeContext->getBy(array("entityType" => $parentEntityType));
            }
        }

        return array(
            'entity' => $this->pageBlock,
            'entity_types' => $entityTypes,
            'attributes' => $parentAttributes,
            'parent_entity' => $parent_entity,
            'attribute_id' => $attribute_id,
        );
    }

    public function SavePageBlockSettings($data)
    {

        $blockManager = $this->container->get('block_manager');

        $content=[];
        $content["parent_entity"]=$data["parentEntityType"];
        $content["attribute_id"]=$data["attributeForQrCode"];

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setContent(json_encode($content));
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }

    public function isVisible()
    {
        $id = $this->pageBlockData["id"];
        if ($id == null) {
            return false;
        } else {
            return true;
        }
    }
}
