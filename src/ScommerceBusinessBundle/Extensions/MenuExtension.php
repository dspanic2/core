<?php

namespace ScommerceBusinessBundle\Extensions;

use AppBundle\Managers\CacheManager;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use ScommerceBusinessBundle\Entity\SMenuEntity;
use ScommerceBusinessBundle\Entity\SMenuItemEntity;
use ScommerceBusinessBundle\Managers\MenuManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MenuExtension extends \Twig_Extension
{

    /**
     * @var ContainerInterface
     */
    protected $container;

    /** @var CacheManager */
    protected $cacheManager;
    /** @var MenuManager */
    protected $menuManager;
    /** @var RouteManager */
    protected $routeManager;
    /** @var BreadcrumbsManager $breadcrumbsManager */
    protected $breadcrumbsManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_menu_data', array($this, 'getMenuData')),
            new \Twig_SimpleFunction('product_group_menu_item_has_products', array($this, 'productGroupMenuItemHasProducts')),
            new \Twig_SimpleFunction('render_menu', array($this, 'renderMenu')),

            // For tracking
            new \Twig_SimpleFunction('get_menu_tree_for_product', array($this, 'getMenuTreeForProduct')),
            new \Twig_SimpleFunction('get_menu_lowest_product_group_for_product', array($this, 'getMenuLowestProductGroupForProduct')),
        ];
    }

    /**
     * @param $menuCode
     * @return bool
     */
    function getMenuData($menuCode)
    {

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $session = $this->container->get("session");

        $cacheItem = $this->cacheManager->getCacheGetItem($menuCode . "_menu_data_" . $session->get("current_store_id"));

        $menuData = [];

        if (empty($cacheItem) || isset($_GET["rebuild_menu"]) || isset($_GET["rebuild_cache"])) {

            if (empty($this->menuManager)) {
                $this->menuManager = $this->container->get("menu_manager");
            }
            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get("route_manager");
            }

            $store = $this->routeManager->getStoreById($session->get("current_store_id"));

            /** @var SMenuEntity $menu */
            $menu = $this->menuManager->getMenuByCode($menuCode, $store);

            if (!empty($menu)) {
                $menuData = $this->menuManager->getMenuItemsArray($menu);
                $this->cacheManager->setCacheItem($menuCode . "_menu_data_" . $session->get("current_store_id"), $menuData, array("s_menu_item", "s_menu", "product_group", "s_page"));
            }
        } else {
            $menuData = $cacheItem->get();
        }

        return $menuData;
    }

    /**
     * @param $menuitemData
     * @return bool
     */
    function productGroupMenuItemHasProducts($menuitemData)
    {
        $hasProducts = $this->checkChildMenuProductCount($menuitemData);
        return $hasProducts;
    }

    private function checkChildMenuProductCount($menuitemData)
    {
        if (isset($menuitemData["product_count"]) && $menuitemData["product_count"] > 0) {
            return true;
        }
        if (!isset($menuitemData["children"]) || empty($menuitemData["children"])) {
            return false;
        }
        foreach ($menuitemData["children"] as $childMenuItem) {
            $childHasProducts = $this->checkChildMenuProductCount($childMenuItem);
            if ($childHasProducts) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string
     */
    public function renderMenu($code)
    {
        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $session = $this->container->get("session");

        $cacheItem = $this->cacheManager->getCacheGetItem("menu_html_" . $code . "_" . $session->get("current_store_id"));

        if (empty($cacheItem) || isset($_GET["rebuild_menu"]) || isset($_GET["rebuild_cache"]) || true) {

            if (empty($this->menuManager)) {
                $this->menuManager = $this->container->get("menu_manager");
            }

            if (empty($this->twig)) {
                $this->twig = $this->container->get("templating");
            }

            if (empty($this->templateManager)) {
                $this->templateManager = $this->container->get("template_manager");
            }

            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get("route_manager");
            }

            $store = $this->routeManager->getStoreById($session->get("current_store_id"));

            /** @var SMenuEntity $menu */
            $menu = $this->menuManager->getMenuByCode($code, $store);
            if (empty($menu)) {
                return "";
            }

            $menuData = $this->menuManager->getMenuItemsArray($menu);

            $html = $this->twig->render($this->templateManager->getTemplatePathByBundle('Components/Menu:main_menu.html.twig', $session->get("current_website_id")), array("menu" => $menuData));

            $this->cacheManager->setCacheItem("menu_html_" . $code . "_" . $session->get("current_store_id"), $html, array("s_menu_item", "s_menu"));

            return $html;
        } else {
            return $cacheItem->get();
        }
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getMenuTreeForProduct(ProductEntity $product)
    {
        $ret = [];

        if (empty($this->breadcrumbsManager)) {
            $this->breadcrumbsManager = $this->container->get("breadcrumbs_manager");
        }
        if (empty($this->menuManager)) {
            $this->menuManager = $this->container->get("menu_manager");
        }

        $session = $this->container->get("session");

        /** @var SMenuItemEntity $menuItem */
        $menuItem = $this->breadcrumbsManager->getLowestProductGroupOnProduct($product, $session->get("current_store_id"));

        if (!empty($menuItem)) {
            $parents = $this->menuManager->getParentsTree($menuItem);

            if (!empty($parents)) {
                $parents = array_reverse($parents);

                /** @var SMenuItemEntity $parent */
                foreach ($parents as $parent) {
                    $url = $parent->getUrl();
                    if ($url == "#") {
                        continue;
                    }
                    $ret[] = $parent->getName();
                }
            }

            $ret[] = $menuItem->getName();
        }

        return $ret;
    }

    /**
     * @param ProductEntity $product
     * @return ProductGroupEntity|null
     */
    public function getMenuLowestProductGroupForProduct(ProductEntity $product)
    {
        if (empty($this->breadcrumbsManager)) {
            $this->breadcrumbsManager = $this->container->get("breadcrumbs_manager");
        }

        $session = $this->container->get("session");

        /** @var SMenuItemEntity $menuItem */
        $menuItem = $this->breadcrumbsManager->getLowestProductGroupOnProduct($product, $session->get("current_store_id"));

        if (!empty($menuItem)) {
            /** @var ProductGroupEntity $productGroup */
            $productGroup = $menuItem->getProductGroup();

            if (!empty($productGroup)) {
                return $productGroup;
            }
        }

        return null;
    }
}
