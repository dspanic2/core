<?php

namespace AppBundle\Blocks;

use AppBundle\Context\AttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Factory\FactoryContext;
use Doctrine\Common\Inflector\Inflector;

class MultiselectBlock extends AbstractBaseBlock
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
        $settings = $this->pageBlock->getPreparedContent();

        $this->entityManager = $this->container->get("entity_manager");
        $this->attributeContext = $this->container->get("attribute_context");

        if (!isset($settings["mandatory"])) {
            $settings["mandatory"] = 0;
        }

        if (!isset($settings["attribute_id"]) || empty($settings["attribute_id"])) {
            return false;
        }
        $this->pageBlockData["model"]["attribute_id"] = $settings["attribute_id"];

        /** @var AttributeSet $parentSet */
        $parentSet = $this->entityManager->getAttributeSetByCode($settings["parent_entity"]);
        if (empty($parentSet)) {
            return false;
        }

        /** @var AttributeSet $childSet */
        $childSet = $this->entityManager->getAttributeSetByCode($settings["child_entity"]);

        /** @var AttributeSet $linkSet */
        $linkSet = $this->entityManager->getAttributeSetByCode($settings["link_entity"]);

        /**
         * Get relations
         */
        $links = array();

        if (!empty($this->pageBlockData["id"])) {

            /**
             * Parent entity
             */
            $parent = $this->entityManager->getEntityByEntityTypeAndId($parentSet->getEntityType(), $this->pageBlockData["id"]);

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
            $compositeFilter->addFilter(new SearchFilter(Inflector::camelize($settings["parent_entity"]), "eq", $parent->getId()));

            $compositeFilters = new CompositeFilterCollection();
            $compositeFilters->addCompositeFilter($compositeFilter);

            $links = $this->entityManager->getEntitiesByAttributeSetAndFilter($linkSet, $compositeFilters);
        }


        /**
         * Child attribute code
         */

        $childAttributeLink = $this->attributeContext->getOneBy(array("entityTypeId" => $linkSet->getEntityType(), "lookupAttributeSet" => $childSet));
        $this->pageBlockData["model"]["child_entity_attribute_name"] = Inflector::camelize($childAttributeLink->getLookupAttribute()->getAttributeCode());


        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter($this->pageBlockData["model"]["child_entity_attribute_name"], "asc"));

        $this->pageBlockData["model"]["options"] = $this->entityManager->getEntitiesByAttributeSetAndFilter($childSet, $compositeFilters, $sortFilters);

        $getterChild = EntityHelper::makeGetter(str_replace("_id", "", $childAttributeLink->getAttributeCode()));
        $selectedOptions = array();

        foreach ($links as $link) {
            if (empty($link->$getterChild())) {
                dump($this->pageBlock->getId());
                dump($linkSet->getEntityType());
                dump($getterChild);
                die;
            }
            $childId = $link->$getterChild()->getId();
            $selectedOptions[] = $childId;
        }

        $this->pageBlockData["model"]["selectedOptions"] = $selectedOptions;
        $this->pageBlockData["model"]["parent_entity"] = $settings["parent_entity"];
        $this->pageBlockData["model"]["child_entity"] = $settings["child_entity"];
        $this->pageBlockData["model"]["link_entity"] = $settings["link_entity"];
        $this->pageBlockData["model"]["mandatory"] = $settings["mandatory"];

        $parentAttributeLink = $this->attributeContext->getOneBy(array("entityTypeId" => $linkSet->getEntityType(), "lookupAttributeSet" => $parentSet));
        $this->pageBlockData["model"]["parent_entity_attribute_name"] = Inflector::camelize($parentAttributeLink->getLookupAttribute()->getAttributeCode());

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

        $link_entity = "";
        $parent_entity = "";
        $child_entity = "";
        $attribute_id = "";
        $parentAttributes = array();
        $mandatory = 0;

        if ($content != null) {
            $link_entity = $content->link_entity;
            $parent_entity = $content->parent_entity;
            $child_entity = $content->child_entity;
            if (isset($content->attribute_id)) {
                $attribute_id = $content->attribute_id;
            }
            if (isset($content->mandatory)) {
                $mandatory = $content->mandatory;
            }

            $parentEntityType = $entityTypeContext->getOneBy(array("entityTypeCode" => $parent_entity));

            if (!empty($parentEntityType)) {

                /** @var AttributeContext $attributeContext */
                $attributeContext = $this->container->get('attribute_context');

                $parentAttributes = $attributeContext->getBy(array("entityType" => $parentEntityType, "backendType" => "lookup"));
            }
        }

        return array(
            'entity' => $this->pageBlock,
            'entity_types' => $entityTypes,
            'attributes' => $parentAttributes,
            'link_entity' => $link_entity,
            'parent_entity' => $parent_entity,
            'child_entity' => $child_entity,
            'mandatory' => $mandatory,
            'attribute_id' => $attribute_id,
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        $content=[];
        $content["link_entity"]=$data["linkEntityType"];
        $content["parent_entity"]=$data["parentEntityType"];
        $content["child_entity"]=$data["childEntityType"];
        $content["attribute_id"]=$data["attributeOnParentLookupToChild"];
        $content["mandatory"]=$data["mandatory"];

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setContent(json_encode($content));
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }
}
