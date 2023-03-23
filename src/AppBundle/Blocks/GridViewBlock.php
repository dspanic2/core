<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\PageBlock;
use AppBundle\Entity\SearchFilter;
use AppBundle\Factory\FactoryContext;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;
use Doctrine\Common\Util\Inflector;
use Symfony\Component\Config\Definition\Exception\Exception;

class GridViewBlock extends AbstractBaseBlock
{
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /** @var  BlockManager $entityManager */
    protected $blockManager;
    /**@var ListViewContext $listViewContext */
    protected $listViewContext;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /**@var FactoryContext $factoryContext */
    protected $factoryContext;
    /**@var AttributeContext $factoryContext */
    protected $attributeContext;

    public function GetPageBlockTemplate()
    {
        if (!isset($this->pageBlockData["type"]) || empty($this->pageBlockData["type"]) || !isset($this->pageBlockData["height"]) || empty($this->pageBlockData["height"])) {
            return ('AppBundle:Block:block_error.html.twig');
        } else {
            return ('AppBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
        }
    }

    public function GetPageBlockData()
    {
        $this->entityManager = $this->container->get("entity_manager");
        $this->listViewContext = $this->container->get("list_view_context");
        $this->entityTypeContext = $this->container->get("entity_type_context");
        $this->factoryContext = $this->container->get("factory_context");
        $this->attributeContext = $this->container->get("attribute_context");

        /** @var PageBlock $block */
        $block = $this->pageBlock;

        $blockContent = $block->getContent();
        if (is_string($blockContent)) {
            $blockContent = json_decode($blockContent, true);
        }

        if (empty($block->getEntityType())) {
            return $this->pageBlockData;
        }

        $allICategories = [];
        if (isset($blockContent["category"]) && !empty($blockContent["category"])) {
            if ($blockContent["category"] == "attribute_set") {
                // List attribute set as filter
                /**@var AttributeSetContext $attributeSetContext */
                $attributeSetContext = $this->container->get("attribute_set_context");
                $attributeSets = $attributeSetContext->getAttributeSetsByEntityType($block->getEntityType());

                /** @var AttributeSet $attributeSet */
                foreach ($attributeSets as $attributeSet) {
                    if (isset($blockContent["attribute_set_definition"]) &&
                        isset($blockContent["attribute_set_definition"][$attributeSet->getAttributeSetCode()]) &&
                        empty($blockContent["attribute_set_definition"][$attributeSet->getAttributeSetCode()])
                    ) {
                        continue;
                    }
                    $allICategories[] = [
                        "id" => $attributeSet->getId(),
                        "name" => $attributeSet->getAttributeSetName(),
                    ];
                }
            } else {
                /** @var Attribute $categoryAttr */
                $categoryAttr = $this->attributeContext->getBy(
                    array('attributeCode' => $blockContent["category"], 'entityType' => $block->getEntityType())
                );

                if (!empty($categoryAttr)) {
                    $categoryAttr = $categoryAttr[0];
                    /** @var EntityType $lookupEntityType */
                    if ($categoryAttr->getFrontendType() == "multiselect") {
                        $lookupAttribute = $categoryAttr->getLookupAttribute();
                        $lookupEntityType = $lookupAttribute->getLookupEntityType();
                    } else {
                        $lookupEntityType = $categoryAttr->getLookupEntityType();
                    }

                    if (!empty($lookupEntityType)) {
                        $compositeFilter = new CompositeFilter();
                        $compositeFilter->setConnector("and");
                        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

                        $compositeFilters = new CompositeFilterCollection();
                        $compositeFilters->addCompositeFilter($compositeFilter);

                        $allICategories = $this->entityManager->getEntitiesByEntityTypeAndFilter(
                            $lookupEntityType,
                            $compositeFilters
                        );
                    }
                }
            }
        }

        $this->pageBlockData["type"] = $block->getEntityType()->getEntityTypeCode();
        $this->pageBlockData["categories"] = $allICategories;
        $this->pageBlockData["height"] = isset($blockContent["height"]) ? $blockContent["height"] : null;
        //$this->pageBlockData["price_attr_code"] = (isset($blockContent["price"]) && !empty($blockContent["price"])) ? $blockContent["price"] : "";
        //$this->pageBlockData["callback"] = (isset($blockContent["callback"]) && !empty($blockContent["callback"])) ? $blockContent["callback"] : "";

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:' . $this->pageBlock->getType() . '.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        /** @var AttributeContext $attributeContext */
        $attributeContext = $this->container->get('attribute_context');

        /** @var EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get('entity_type_context');
        $entityTypes = $entityTypeContext->getAll();

        $data = json_decode($this->pageBlock->getContent(), true);

        /*$title1 = [];
        if(isset($data->title1)){
            $attrObj = $attributeContext->getBy(Array('attributeCode' => $data->title1,'entityType' => $this->pageBlock->getAttributeSet()->getEntityType()));
            if(!empty($attrObj)) {
                $attrObj = $attrObj[0];
                $title1 = [
                    "id" => $attrObj->getAttributeCode(),
                    "name" => $attrObj->getFrontendLabel()
                ];
            }
        }*/
        /*$price = [];
        if(isset($data->price)){
            $attrObj = $attributeContext->getBy(Array('attributeCode' => $data->price,'entityType' => $this->pageBlock->getAttributeSet()->getEntityType()));
            if(!empty($attrObj)){
                $attrObj = $attrObj[0];
                $price = [
                    "id" => $attrObj->getAttributeCode(),
                    "name" => $attrObj->getFrontendLabel()
                ];
            }
        }*/
        $category = null;
        if (isset($data["category"])) {
            $category = $data["category"];
        }
        /*$image = [];
        if(isset($data->image)){
            $attrObj = $attributeContext->getBy(Array('attributeCode' => $data->image,'entityType' => $this->pageBlock->getAttributeSet()->getEntityType()));
            if(!empty($attrObj)) {
                $attrObj = $attrObj[0];
                $image = [
                    "id" => $attrObj->getAttributeCode(),
                    "name" => $attrObj->getFrontendLabel()
                ];
            }
        }*/
        $height = "initial";
        if (isset($data["height"])) {
            $height = $data["height"];
        }
        $filter = "";
        if (isset($data["filter"])) {
            $filter = $data["filter"];
        }
        $attribute_set_definition = "";
        if (isset($data["attribute_set_definition"])) {
            $attribute_set_definition = $data["attribute_set_definition"];
        }

        $attributes = null;
        if (!empty($this->pageBlock->getEntityType())) {
            $attributes = $attributeContext->getBy(array("entityType" => $this->pageBlock->getEntityType()));
        }

        /*$callback = "";
        if(isset($data->callback)){
            $callback = $data->callback;
        }*/

        /*$quoteCalculateTable = "off";
        if(isset($data->quoteCalculateTable)){
            $quoteCalculateTable = $data->quoteCalculateTable;
        }
        $productCalculateTable = "off";
        if(isset($data->productCalculateTable)){
            $productCalculateTable = $data->productCalculateTable;
        }
        $includeAccountCreation = "off";
        if(isset($data->includeAccountCreation)){
            $includeAccountCreation = $data->includeAccountCreation;
        }*/

        return array(
            'entity' => $this->pageBlock,
            'entity_types' => $entityTypes,
            'content' => $data,
            /*'title1' => $title1,
            'price' => $price,*/
            'attribute_set_definition' => $attribute_set_definition,
            'category' => $category,
            'height' => $height,
            'filter' => $filter,
            'attributes' => $attributes,
            //'callback' => $callback,
            /*'quoteCalculateTable' => $quoteCalculateTable,
            'productCalculateTable' => $productCalculateTable,
            'includeAccountCreation' => $includeAccountCreation,*/
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        $p = $_POST;

        /** @var EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get('entity_type_context');
        /** @var AttributeSet $attributeSet */
        $entityType = $entityTypeContext->getById($p["entity_type"]);

        $settings = array();

        if (!isset($entityType) || empty($entityType)) {
            return false;
        }

        if (!isset($p["category"]) || empty($p["category"])) {
            return false;
        }
        $settings["category"] = $p["category"];

        $settings["height"] = "inital";
        if (isset($p["height"]) && !empty($p["height"])) {
            $settings["height"] = $p["height"];
        }

        $settings["filter"] = "";
        if (isset($p["filter"]) && !empty($p["filter"])) {
            $settings["filter"] = $p["filter"];
        }

        $settings["attribute_set_definition"] = "";
        if (isset($p["attribute_set_definition"]) && !empty($p["attribute_set_definition"])) {
            $settings["attribute_set_definition"] = $p["attribute_set_definition"];
        }


        /*if (isset($p["title1"]) && !empty($p["title1"])) {
            $settings["title1"] = $p["title1"];
        }
        if (isset($p["price"]) && !empty($p["price"])) {
            $settings["price"] = $p["price"];
        }
        if (isset($p["category"]) && !empty($p["category"])) {
            $settings["category"] = $p["category"];
        }
        if (isset($p["image"]) && !empty($p["image"])) {
            $settings["image"] = $p["image"];
        }
        $settings["quoteCalculateTable"] = "off";
        if (isset($p["quoteCalculateTable"]) && !empty($p["quoteCalculateTable"])) {
            $settings["quoteCalculateTable"] = $p["quoteCalculateTable"];
        }
        $settings["productCalculateTable"] = "off";
        if (isset($p["productCalculateTable"]) && !empty($p["productCalculateTable"])) {
            $settings["productCalculateTable"] = $p["productCalculateTable"];
        }
        $settings["includeAccountCreation"] = "off";
        if (isset($p["includeAccountCreation"]) && !empty($p["includeAccountCreation"])) {
            $settings["includeAccountCreation"] = $p["includeAccountCreation"];
        }*/

        /*$settings["filter"] = "";
        if (isset($p["filter"]) && !empty($p["filter"])) {
            $settings["filter"] = $p["filter"];
        }

        $settings["callback"] = "";
        if (isset($p["callback"]) && !empty($p["callback"])) {
            $settings["callback"] = $p["callback"];
        }*/

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setEntityType($entityType);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);
        $this->pageBlock->setContent(json_encode($settings));

        return $blockManager->save($this->pageBlock);
    }
}