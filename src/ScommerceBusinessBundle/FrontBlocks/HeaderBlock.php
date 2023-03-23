<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Managers\CacheManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Entity\SMenuEntity;
use ScommerceBusinessBundle\Managers\MenuManager;

class HeaderBlock extends AbstractBaseFrontBlock
{
    /** @var MenuManager $menuManager */
    protected $menuManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    public function GetBlockData()
    {
        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $session = $this->getContainer()->get("session");

        $cacheItem = $this->cacheManager->getCacheGetItem("menu_data_" . $session->get("current_store_id"));

        $menuData = [];
        if (empty($cacheItem) || isset($_GET["rebuild_menu"]) || isset($_GET["rebuild_cache"])) {

            if (empty($this->menuManager)) {
                $this->menuManager = $this->container->get("menu_manager");
            }

            $menuIds = $this->blockData["block"]->getUrl();
            if (isset($menuIds[$session->get("current_store_id")]) && !empty($menuIds[$session->get("current_store_id")])) {
                $menuId = $menuIds[$session->get("current_store_id")];

                /** @var SMenuEntity $menu */
                $menu = $this->menuManager->getMenuById($menuId);

                $menuData = $this->menuManager->getMenuItemsArray($menu);
                $this->cacheManager->setCacheItem("menu_data_" . $session->get("current_store_id"), $menuData, array("s_menu_item", "s_menu", "product_group", "s_page"));
            }
        } else {
            $menuData = $cacheItem->get();
        }

        $this->blockData["model"]["menu"] = $menuData;

        return $this->blockData;
    }

    /** Nije potrebno dok se ne uvedu zasebni admin template za pojedine blokove */
    /*public function GetBlockSetingsTemplate()
    {
        return 'ScommerceBusinessBundle:BlockSettings:'.$this->block->getType().'.html.twig';
    }*/

    /*public function GetBlockSetingsData()
    {
        return array(
            'entity' => $this->block,
        );
    }*/
}
