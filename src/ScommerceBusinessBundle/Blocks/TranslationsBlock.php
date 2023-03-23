<?php

namespace ScommerceBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use ScommerceBusinessBundle\Entity\SMenuEntity;
use ScommerceBusinessBundle\Managers\BlogManager;
use ScommerceBusinessBundle\Managers\MenuManager;
use ScommerceBusinessBundle\Managers\ProductGroupManager;

class TranslationsBlock extends AbstractBaseBlock
{
    public function GetPageBlockTemplate()
    {
        return ('ScommerceBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
    }

    public function GetPageBlockData()
    {
        /**@var MenuManager $menuManager */
        $menuManager = $this->container->get("menu_manager");

        $menuItems = $pages = $productGroups = $blogCategories = array();


        if (!empty($this->pageBlockData["id"])) {

            /** @var SMenuEntity $menu */
            $menu = $menuManager->getMenuById($this->pageBlockData["id"]);

            $menuItems = $menuManager->getMenuItemsArray($menu);

            $pages = $menuManager->getPagesByStore($menu->getStore());

            /** @var ProductGroupManager $productGroupManager */
            $productGroupManager = $this->container->get("product_group_manager");
            $productGroups = $productGroupManager->getProductGroupsByStore($menu->getStore());

            /** @var BlogManager $blogManager */
            $blogManager = $this->container->get("blog_manager");
            $blogCategories = $blogManager->getBlogCategoriesByStore($menu->getStore());
        }

        $this->pageBlockData["model"]["pages"] = $pages;
        $this->pageBlockData["model"]["product_groups"] = $productGroups;
        $this->pageBlockData["model"]["blog_categories"] = $blogCategories;
        $this->pageBlockData["model"]["menu_item_types"] = $menuManager->getMenuItemTypes();
        $this->pageBlockData["model"]["navigation_json"] = json_encode($menuItems);

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
        }
        //Check permission
        return true;
    }
}
