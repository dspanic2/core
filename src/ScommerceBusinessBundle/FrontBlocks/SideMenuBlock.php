<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Managers\CacheManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Entity\SMenuEntity;
use ScommerceBusinessBundle\Managers\MenuManager;

class SideMenuBlock extends AbstractBaseFrontBlock
{
    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var MenuManager $menuManager */
    protected $menuManager;
    /** @var bool $isActive */
    protected $isActive = false;

    public function GetBlockData()
    {
        $this->blockData["model"]["side_menu_items"] = [];
        $this->blockData["model"]["is_active"] = false;
        $this->blockData["model"]["subtitle"] = "";

        $session = $this->getContainer()->get("session");

        $productFilterData = $this->blockData["block"]->getProductFilterData();
        if (!empty($productFilterData)) {
            $productFilterData = json_decode($productFilterData, true);
        }

        if (isset($productFilterData["menu"])) {
            $menuId = $productFilterData["menu"];
        } else {
            $menuIds = $this->blockData["block"]->getUrl();
            if (isset($menuIds[$session->get("current_store_id")])) {
                $menuId = $menuIds[$session->get("current_store_id")];
            }
        }

        if (!empty($menuId)) {
            $active = [];

            $productGroupSetId = null;

            $entity = $this->blockData["page"];
            if ($entity->getEntityType()->getEntityTypeCode() == "product") {
                foreach ($entity->getProductGroups() as $productGroupLink) {
                    $active[] = $productGroupLink->getProductGroup()->getId();
                }
            } elseif ($entity->getEntityType()->getEntityTypeCode() == "product_group") {
                $active[] = $entity->getId();
            }

            if (empty($this->cacheManager)) {
                $this->cacheManager = $this->container->get("cache_manager");
            }

            $cacheItem = $this->cacheManager->getCacheGetItem("sub_menu_data_{$menuId}_" . $session->get("current_store_id"));

            if (empty($cacheItem) || isset($_GET["rebuild_menu"])) {

                if (empty($this->menuManager)) {
                    $this->menuManager = $this->container->get("menu_manager");
                }

                /** @var SMenuEntity $menu */
                $menu = $this->menuManager->getMenuById($menuId);

                $menuData = [];
                if (!empty($menu)) {
                    $menuData = $this->menuManager->getMenuItemsArray($menu);
                }
                $this->cacheManager->setCacheItem("sub_menu_data_{$menuId}_" . $session->get("current_store_id"), $menuData, array("s_menu_item", "s_menu", "product_group", "s_page"));
            } else {
                $menuData = $cacheItem->get();
            }

            if (!empty($active)) {
                $menuData = $this->prepareActiveMenuItems($menuData, $active);
            }

            $this->blockData["model"]["side_menu_items"] = $menuData;
            $this->blockData["model"]["is_active"] = $this->isActive;
            $this->blockData["model"]["subtitle"] = $this->getPageUrlExtension->getEntityStoreAttribute(null, $this->blockData["block"], "subtitle");
        }

        return $this->blockData;
    }

    private function prepareActiveMenuItems($menuData, $active)
    {
        foreach ($menuData as $key => $value) {
            if (in_array($value["product_group"], $active)) {
                $menuData[$key]["css_class"] = "active expanded";
                if (!$this->isActive) {
                    $this->isActive = true;
                }
            }
            if (isset($value["children"])) {
                $menuData[$key]["children"] = $this->prepareActiveMenuItems($value["children"], $active);
                foreach ($menuData[$key]["children"] as $child) {
                    if (strpos($child["css_class"], "active") !== false) {
                        $menuData[$key]["css_class"] = "active expanded";
                    }
                }
            }
        }

        return $menuData;
    }
}
