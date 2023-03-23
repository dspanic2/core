<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use CrmBusinessBundle\Entity\WarehouseEntity;
use CrmBusinessBundle\Managers\ProductManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\BlogCategoryEntity;
use ScommerceBusinessBundle\Entity\BrandEntity;
use ScommerceBusinessBundle\Entity\SMenuEntity;
use ScommerceBusinessBundle\Entity\SMenuItemEntity;
use ScommerceBusinessBundle\Entity\SMenuItemTypeEntity;
use ScommerceBusinessBundle\Entity\SPageEntity;
use ScommerceBusinessBundle\Entity\SRouteEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;

class MenuManager extends AbstractScommerceManager
{
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;
    /** @var BlogManager */
    protected $blogManager;
    /** @var BrandsManager */
    protected $brandsManager;
    /** @var ProductManager */
    protected $productManager;

    protected $menuItemTypesArray;
    protected $pagesArray;
    protected $productGroupsArray;
    protected $blogCategoryArray;
    protected $brandArray;
    protected $warehousesArray;

    public function initialize()
    {
        parent::initialize();
        $this->routeManager = $this->container->get('route_manager');
    }

    /**
     * @param $name
     * @param SStoreEntity $storeEntity
     * @return false|mixed|string|string[]|null
     */
    public function createCode($name, SStoreEntity $storeEntity)
    {

        $codeTmp = $code = $this->routeManager->prepareUrl($name);

        $i = 1;
        $existingMenuCode = $this->getMenuByCode($code, $storeEntity);

        while (!empty($existingMenuCode)) {
            $codeTmp = $code . "_" . $i;
            $existingMenuCode = $this->getMenuByCode($code, $storeEntity);
            $i++;
        }

        return $codeTmp;
    }

    /**
     * @param $code
     * @param SStoreEntity $storeEntity
     * @return |null
     */
    public function getMenuByCode($code, SStoreEntity $storeEntity)
    {

        $menuEntityType = $this->entityManager->getEntityTypeByCode("s_menu");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("menuCode", "eq", $code));
        $compositeFilter->addFilter(new SearchFilter("store", "eq", $storeEntity->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($menuEntityType, $compositeFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getMenuById($id)
    {

        $menuEntityType = $this->entityManager->getEntityTypeByCode("s_menu");
        return $this->entityManager->getEntityByEntityTypeAndId($menuEntityType, $id);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getMenuItemById($id)
    {

        $menuItemEntityType = $this->entityManager->getEntityTypeByCode("s_menu_item");
        return $this->entityManager->getEntityByEntityTypeAndId($menuItemEntityType, $id);
    }

    /**
     * @param $entityTypeCode
     * @param $id
     * @param null $storeId
     * @return bool|null
     */
    public function getMenuItemByRelatedAndId($entityTypeCode, $id, $storeId = null)
    {
        $menuItemEntityType = $this->entityManager->getEntityTypeByCode("s_menu_item");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        if (!empty($storeId)) {
            $compositeFilter->addFilter(new SearchFilter("menu.store", "eq", $storeId));
        }
        if ($entityTypeCode == "s_page") {
            $compositeFilter->addFilter(new SearchFilter("page", "eq", $id));
        } elseif ($entityTypeCode == "product_group") {
            $compositeFilter->addFilter(new SearchFilter("productGroup", "eq", $id));
        } else {
            return false;
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($menuItemEntityType, $compositeFilters);
    }

    /**
     * @param $entity
     * @return bool
     */
    public function checkMenuItem($entity)
    {

        /** @var SMenuItemEntity $menuItem */
        $menuItem = $this->getMenuItemByRelatedAndId($entity->getEntityType()->getEntityTypeCode(), $entity->getId());

        /** Check if deleted */
        if ($entity->getEntityStateId() == 2 || !$entity->getMenu()) {
            if (!empty($menuItem)) {
                $this->entityManager->deleteEntityFromDatabase($menuItem);
                return true;
            }
        } else {
            if (!empty($menuItem)) {
                if ($menuItem->getMenu() != $entity->getMenu()) {
                    $this->entityManager->deleteEntityFromDatabase($menuItem);

                    //todo sta se desi kod deleta
                    //todo sto se dogodi sa urlo, url bi se trebao rjesavati direkto sa entiteta jer on postoji neovisno da li je u menu ili ne
                } else {
                    return false;
                }
            }

            /** @var SPageEntity $pageEntity */
            $pageEntity = null;
            /** @var ProductGroupEntity $productGroupEntity */
            $productGroupEntity = null;
            /** @var BlogCategoryEntity $blogCategoryEntity */
            $blogCategoryEntity = null;
            /** @var BrandEntity $brand */
            $brand = null;
            /** @var WarehouseEntity $warehouse */
            $warehouse = null;
            /** @var SMenuItemTypeEntity $menuItemTypeEntity */
            $menuItemTypeEntity = null;
            /** @var SMenuItemEntity $parentMenuItem */
            $parentMenuItem = null;

            $url = null;
            /** @var SRouteEntity $route */
            $route = $this->routeManager->getRouteByDestination($entity->getId(), $entity->getEntityType()->getEntityTypeCode(), $entity->getMenu()->getStore());
            if (!empty($route)) {
                $url = $route->getRequestUrl();
            }

            $parentMenuItems = $this->getLevelMenuItems($entity->getMenu(), 0);
            if (!empty($parentMenuItems)) {
                $parentMenuItem = $parentMenuItems[0];
            }

            if ($entity->getEntityType()->getEntityTypeCode() == "s_page") {
                $pageEntity = $entity;
                $menuItemTypeEntity = $this->getMenuTypeById(2);
            } elseif ($entity->getEntityType()->getEntityTypeCode() == "product_group") {
                $productGroupEntity = $entity;
                $menuItemTypeEntity = $this->getMenuTypeById(3);
            } elseif ($entity->getEntityType()->getEntityTypeCode() == "blog_category") {
                $blogCategoryEntity = $entity;
                $menuItemTypeEntity = $this->getMenuTypeById(5);
            } elseif ($entity->getEntityType()->getEntityTypeCode() == "brand") {
                $brand = $entity;
                $menuItemTypeEntity = $this->getMenuTypeById(6);
            } elseif ($entity->getEntityType()->getEntityTypeCode() == "warehouse") {
                $warehouse = $entity;
                $menuItemTypeEntity = $this->getMenuTypeById(7);
            }

            $this->createNewMenuItem(
                $entity->getName(),
                $entity->getMenu(),
                $menuItemTypeEntity,
                $pageEntity,
                $productGroupEntity,
                $blogCategoryEntity,
                $brand,
                $warehouse,
                $url,
                $parentMenuItem
            );
        }

        return true;
    }

    /**
     * @param $id
     * @return |null
     */
    public function getMenuTypeById($id)
    {

        $menuTypeEntityType = $this->entityManager->getEntityTypeByCode("s_menu_item_type");
        return $this->entityManager->getEntityByEntityTypeAndId($menuTypeEntityType, $id);
    }

    /**
     * @param SMenuEntity $menuEntity
     * @param $level
     * @return mixed
     */
    public function getLevelMenuItems(SMenuEntity $menuEntity, $level)
    {

        $menuEntityType = $this->entityManager->getEntityTypeByCode("s_menu_item");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        /** Removed on purpose because menu items will be force deleted */
        //$compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("menu", "eq", $menuEntity->getId()));
        $compositeFilter->addFilter(new SearchFilter("level", "eq", $level));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("ord", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($menuEntityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param SMenuEntity $menuEntity
     * @param int $level
     * @return array
     */
    public function getMenuItemsArray(SMenuEntity $menuEntity, $level = 0, $loadFullTree = false)
    {

        $menuItems = $this->getLevelMenuItems($menuEntity, $level);

        $ret = array();

        /** @var SMenuItemEntity $menuItem */
        foreach ($menuItems as $menuItem) {
            $menuItemArray = $this->getMenuItemArray($menuItem);

            $ret[] = $menuItemArray;
        }

        return $ret;
    }

    /**
     * @param SMenuItemEntity $menuItemEntity
     * @return array
     */
    public function getMenuItemArray(SMenuItemEntity $menuItemEntity)
    {
        $menuItemArray = $this->menuItemToArray($menuItemEntity);

        $children = array();

        if (!empty($menuItemEntity->getChildMenuItems()) && count($menuItemEntity->getChildMenuItems()) > 0) {
            /** @var SMenuItemEntity $childMenuItem */
            foreach ($menuItemEntity->getChildMenuItems() as $key => $childMenuItem) {
                $children[] = $this->getMenuItemArray($childMenuItem);
            }

            usort($children, array($this, 'cmp'));

            $menuItemArray["children"] = $children;
        }

        return $menuItemArray;
    }

    /**
     * @param SMenuItemEntity $menuItemEntity
     * @return array
     */
    public function menuItemToArray(SMenuItemEntity $menuItemEntity)
    {

        $ret = array();

        $ret["id"] = $menuItemEntity->getId();
        $ret["url"] = $menuItemEntity->getUrl();
        $ret["menu_item_type"] = $menuItemEntity->getMenuItemType()->getId();
        $ret["page"] = null;
        if (!empty($menuItemEntity->getPage())) {
            $ret["page"] = $menuItemEntity->getPage()->getId();
        }
        $ret["product_group"] = null;
        $ret["product_count"] = 0;
        if (!empty($menuItemEntity->getProductGroup())) {
            $ret["product_group"] = $menuItemEntity->getProductGroup()->getId();
            $ret["product_count"] = $menuItemEntity->getProductGroup()->getProductsInGroup();
            $ret["url"] = $menuItemEntity->getProductGroup()->getUrlPath($menuItemEntity->getMenu()->getStoreId());
        }

        $ret["blog_category"] = null;
        if (!empty($menuItemEntity->getBlogCategory())) {
            $ret["blog_category"] = $menuItemEntity->getBlogCategory()->getId();
            $ret["url"] = $menuItemEntity->getBlogCategory()->getUrlPath($menuItemEntity->getMenu()->getStoreId());
        }

        $ret["brand"] = null;
        if (!empty($menuItemEntity->getBrand())) {
            $ret["brand"] = $menuItemEntity->getBrand()->getId();
            $ret["url"] = $menuItemEntity->getBrand()->getUrlPath($menuItemEntity->getMenu()->getStoreId());
        }

        $ret["warehouse"] = null;
        if (!empty($menuItemEntity->getWarehouse())) {
            $ret["warehouse"] = $menuItemEntity->getWarehouse()->getId();
            $ret["url"] = $menuItemEntity->getWarehouse()->getUrlPath($menuItemEntity->getMenu()->getStoreId());
        }

        $ret["css_class"] = $menuItemEntity->getCssClass();
        $ret["target"] = $menuItemEntity->getTarget();
        $ret["show"] = 0;
        if ($menuItemEntity->getShw()) {
            $ret["show"] = 1;
        }
        $ret["text"] = $menuItemEntity->getName();
        $ret["order"] = $menuItemEntity->getOrd();
        $ret["level"] = $menuItemEntity->getLevel();
        $ret["menu_item"] = null;
        if (!empty($menuItemEntity->getMenuItem())) {
            $ret["menu_item"] = $menuItemEntity->getMenuItem()->getId();
        }

        $ret["selected_product"] = null;
        $menuItemProducts = $menuItemEntity->getMenuItemProducts();

        if (EntityHelper::isCountable($menuItemProducts) && count($menuItemProducts)) {
            $randIndex = 0;
            if (count($menuItemProducts) > 1) {
                $randIndex = rand(0, count($menuItemProducts) - 1);
            }

            /** @var ProductEntity $product */
            $product = $menuItemProducts[$randIndex]->getProduct();

            $session = $this->container->get("session");
            $url = $product->getUrl();
            if (isset($url[$session->get("current_store_id")])) {
                $ret["selected_product"]["product"] = $product;
                $ret["selected_product"]["url"] = $url[$session->get("current_store_id")];
                $ret["selected_product"]["image"] = null;
                if (!empty($product->getSelectedImage())) {
                    $ret["selected_product"]["image"] = $product->getSelectedImage()->getFile();
                }
            }
        }

        return $ret;
    }

    public function cmp($a, $b)
    {
        return $a['order'] <=> $b['order'];
    }

    /**
     * @return mixed
     */
    public function getMenuItemTypes()
    {

        $menuItemTypeEntityType = $this->entityManager->getEntityTypeByCode("s_menu_item_type");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($menuItemTypeEntityType, $compositeFilters);
    }

    /**
     * @param SStoreEntity $storeEntity
     * @return mixed
     */
    public function getPagesByStore(SStoreEntity $storeEntity)
    {

        $pageEntityType = $this->entityManager->getEntityTypeByCode("s_page");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeEntity->getId() . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("name", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($pageEntityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param SMenuEntity $menuEntity
     * @return bool
     */
    public function initializeLists(SMenuEntity $menuEntity)
    {

        $menuItemTypes = $this->getMenuItemTypes();
        $this->menuItemTypesArray = array();
        /** @var SMenuItemTypeEntity $menuItemType */
        foreach ($menuItemTypes as $menuItemType) {
            $this->menuItemTypesArray[$menuItemType->getId()] = $menuItemType;
        }

        $pages = $this->getPagesByStore($menuEntity->getStore());
        $this->pagesArray = array();
        /** @var SPageEntity $page */
        foreach ($pages as $page) {
            $this->pagesArray[$page->getId()] = $page;
        }

        if (empty($this->productGroupManager)) {
            $this->productGroupManager = $this->container->get("product_group_manager");
        }

        $productGroups = $this->productGroupManager->getProductGroupsByStore($menuEntity->getStore());
        $this->productGroupsArray = array();
        /** @var ProductGroupEntity $productGroup */
        foreach ($productGroups as $productGroup) {
            $this->productGroupsArray[$productGroup->getId()] = $productGroup;
        }

        if (empty($this->blogManager)) {
            $this->blogManager = $this->container->get("blog_manager");
        }

        if (empty($this->blogManager)) {
            $this->blogManager = $this->container->get("blog_manager");
        }
        if (empty($this->brandsManager)) {
            $this->brandsManager = $this->container->get("brands_manager");
        }
        if (empty($this->productManager)) {
            $this->productManager = $this->container->get("product_manager");
        }

        $blogCategories = $this->blogManager->getBlogCategoriesByStore($menuEntity->getStore());
        $this->blogCategoryArray = array();
        /** @var BlogCategoryEntity $blogCategory */
        foreach ($blogCategories as $blogCategory) {
            $this->blogCategoryArray[$blogCategory->getId()] = $blogCategory;
        }

        $brands = $this->brandsManager->getAllBrands();
        $this->brandArray = array();
        /** @var BrandEntity $brand */
        foreach ($brands as $brand) {
            $this->brandArray[$brand->getId()] = $brand;
        }

        $warehouses = $this->productManager->getAllWarehouses();
        $this->warehousesArray = array();
        /** @var WarehouseEntity $warehouse */
        foreach ($warehouses as $warehouse) {
            $this->warehousesArray[$warehouse->getId()] = $warehouse;
        }

        return true;
    }

    /**
     * @param SMenuEntity $menuEntity
     * @param $menuItemJson
     * @return bool
     */
    public function saveMenuItemJson(SMenuEntity $menuEntity, $menuItemJson)
    {

        if (empty($this->menuItemTypesArray)) {
            $this->initializeLists($menuEntity);
        }

        $menuItems = json_decode($menuItemJson, true);
        $currentIds = $this->getIds($menuItems);

        $existingMenuItems = $this->getMenuItemsArray($menuEntity);
        $usedIds = $this->getIds($existingMenuItems);

        foreach ($menuItems as $key => $menuItem) {
            if (!$this->updateMenuItem($menuItem, $menuEntity, $key, null)) {
                $this->logger->error("Error saving menu: " . $menuEntity->getMenuCode());
            }
        }

        foreach ($usedIds as $usedId) {
            if (!in_array($usedId, $currentIds)) {
                /** @var SMenuItemEntity $menuItem */
                $menuItem = $this->getMenuItemById($usedId);
                if (!empty($menuItem)) {
                    $menuItem->setChildMenuItems(null);
                    $this->entityManager->deleteEntity($menuItem);
                }
            }
        }

        return true;
    }

    /**
     * @param $name
     * @param SMenuEntity $menuEntity
     * @param SMenuItemTypeEntity $menuItemTypeEntity
     * @param null $pageEntity
     * @param null $productGroupEntity
     * @param null $blogCategoryEntity
     * @param null $url
     * @param null $parentMenuItem
     * @param int $show
     * @param null $cssClass
     * @param string $target
     * @param int $order
     * @return SMenuItemEntity
     */
    public function createNewMenuItem(
        $name,
        SMenuEntity $menuEntity,
        SMenuItemTypeEntity $menuItemTypeEntity,
        $pageEntity = null,
        $productGroupEntity = null,
        $blogCategoryEntity = null,
        $brandEntity = null,
        $warehouseEntity = null,
        $url = null,
        $parentMenuItem = null,
        $show = 1,
        $cssClass = null,
        $target = "_self",
        $order = 99
    )
    {

        /** @var SMenuItemEntity $menuItemEntity */
        $menuItemEntity = $this->entityManager->getNewEntityByAttributSetName("s_menu_item");

        $menuItemEntity->setName($name);
        $menuItemEntity->setMenu($menuEntity);
        $menuItemEntity->setShw($show);
        $menuItemEntity->setCssClass($cssClass);
        $menuItemEntity->setTarget($target);
        $menuItemEntity->setOrd($order);
        $menuItemEntity->setMenuItem($parentMenuItem);
        $menuItemEntity->setMenuItemType($menuItemTypeEntity);
        $menuItemEntity->setUrl($url);
        $level = 0;
        if (!empty($parentMenuItem)) {
            $level = $parentMenuItem->getLevel() + 1;
        }
        $menuItemEntity->setLevel($level);
        $menuItemEntity->setPage($pageEntity);
        $menuItemEntity->setProductGroup($productGroupEntity);
        $menuItemEntity->setBlogCategory($blogCategoryEntity);
        $menuItemEntity->setBrand($brandEntity);
        $menuItemEntity->setWarehouse($warehouseEntity);

        $this->entityManager->saveEntity($menuItemEntity);

        return $menuItemEntity;
    }

    /**
     * @param $menuItemData
     * @param SMenuEntity $menuEntity
     * @param $key
     * @param null $parent
     * @return bool
     */
    public function updateMenuItem($menuItemData, SMenuEntity $menuEntity, $key, $parent = null)
    {
        $menuItemEntity = null;

        if (isset($menuItemData["id"]) && !empty($menuItemData["id"])) {
            /** @var SMenuItemEntity $menuItemEntity */
            $menuItemEntity = $this->getMenuItemById($menuItemData["id"]);
        }

        if (empty($this->menuItemTypesArray)) {
            $this->initializeLists($menuEntity);
        }

        /** @var SMenuItemTypeEntity $menuItemType */
        $menuItemType = $this->menuItemTypesArray[$menuItemData["menu_item_type"]];

        /** @var SPageEntity $page */
        $page = null;
        if (!empty($menuItemData["page"]) && isset($this->pagesArray[$menuItemData["page"]])) {
            $page = $this->pagesArray[$menuItemData["page"]];
        }

        /** @var ProductGroupEntity $productGroup */
        $productGroup = null;
        if (!empty($menuItemData["product_group"]) && isset($this->productGroupsArray[$menuItemData["product_group"]])) {
            $productGroup = $this->productGroupsArray[$menuItemData["product_group"]];
        }

        /** @var BlogCategoryEntity $blogCategory */
        $blogCategory = null;
        if (!empty($menuItemData["blog_category"]) && isset($this->blogCategoryArray[$menuItemData["blog_category"]])) {
            $blogCategory = $this->blogCategoryArray[$menuItemData["blog_category"]];
        }

        /** @var BrandEntity $brand */
        $brand = null;
        if (!empty($menuItemData["brand"]) && isset($this->brandArray[$menuItemData["brand"]])) {
            $brand = $this->brandArray[$menuItemData["brand"]];
        }

        /** @var WarehouseEntity $warehouse */
        $warehouse = null;
        if (!empty($menuItemData["warehouse"]) && isset($this->warehousesArray[$menuItemData["warehouse"]])) {
            $warehouse = $this->warehousesArray[$menuItemData["warehouse"]];
        }

        $level = 0;
        if (!empty($parent)) {
            $level = $parent->getLevel() + 1;
        }

        $order = $key + 10;

        $url = "#";
        if ($menuItemType->getId() == 2) {
            if (!empty($page)) {
                /** @var SRouteEntity $route */
                $route = $this->routeManager->getRouteByDestination($page->getId(), $page->getEntityType()->getEntityTypeCode(), $menuEntity->getStore());
                if (!empty($route)) {
                    $url = $route->getRequestUrl();
                }
            }
        } elseif ($menuItemType->getId() == 3) {
            if (!empty($productGroup)) {
                /** @var SRouteEntity $route */
                $route = $this->routeManager->getRouteByDestination($productGroup->getId(), $productGroup->getEntityType()->getEntityTypeCode(), $menuEntity->getStore());
                if (!empty($route)) {
                    $url = $route->getRequestUrl();
                }
            }
        } elseif ($menuItemType->getId() == 4) {
            $url = $menuItemData["url"];
        } elseif ($menuItemType->getId() == 5) {
            if (!empty($blogCategory)) {
                /** @var SRouteEntity $route */
                $route = $this->routeManager->getRouteByDestination($blogCategory->getId(), $blogCategory->getEntityType()->getEntityTypeCode(), $menuEntity->getStore());
                if (!empty($route)) {
                    $url = $route->getRequestUrl();
                }
            }
        } elseif ($menuItemType->getId() == 6) {
            if (!empty($brand)) {
                /** @var SRouteEntity $route */
                $route = $this->routeManager->getRouteByDestination($brand->getId(), $brand->getEntityType()->getEntityTypeCode(), $menuEntity->getStore());
                if (!empty($route)) {
                    $url = $route->getRequestUrl();
                }
            }
        } elseif ($menuItemType->getId() == 7) {
            if (!empty($warehouse)) {
                /** @var SRouteEntity $route */
                $warehouse = $this->routeManager->getRouteByDestination($warehouse->getId(), $warehouse->getEntityType()->getEntityTypeCode(), $menuEntity->getStore());
                if (!empty($route)) {
                    $url = $route->getRequestUrl();
                }
            }
        }

        if (empty($menuItemEntity)) { // Insert
            $menuItemEntity = $this->createNewMenuItem(
                $menuItemData["text"],
                $menuEntity,
                $menuItemType,
                $page,
                $productGroup,
                $blogCategory,
                $brand,
                $warehouse,
                $url,
                $parent,
                $menuItemData["show"],
                $menuItemData["css_class"],
                $menuItemData["target"],
                $order
            );
        } else { // Update
            $hasUpdate = false;

            if ($menuItemEntity->getName() != $menuItemData["text"]) {
                $menuItemEntity->setName($menuItemData["text"]);
                $hasUpdate = true;
            }
            //$menuItemEntity->setMenu($menuEntity);
            if ($menuItemEntity->getCssClass() != $menuItemData["css_class"]) {
                $menuItemEntity->setCssClass($menuItemData["css_class"]);
                $hasUpdate = true;
            }
            if ($menuItemEntity->getOrd() != $order) {
                $menuItemEntity->setOrd($order);
                $hasUpdate = true;
            }
            $show = 1;
            if (!$menuItemEntity->getShw()) {
                $show = 0;
            }
            if ($show != $menuItemData["show"]) {
                $menuItemEntity->setShw($menuItemData["show"]);
                $hasUpdate = true;
            }
            if ($menuItemEntity->getTarget() != $menuItemData["target"]) {
                $menuItemEntity->setTarget($menuItemData["target"]);
                $hasUpdate = true;
            }
            if ($menuItemEntity->getMenuItemType()->getId() != $menuItemType->getId()) {
                $menuItemEntity->setMenuItemType($menuItemType);
                $hasUpdate = true;
            }
            if ($menuItemEntity->getUrl() != $menuItemData["url"]) {
                $menuItemEntity->setUrl($menuItemData["url"]);
                $hasUpdate = true;
            }

            if ($menuItemEntity->getPage() != $page) {
                $menuItemEntity->setPage($page);
                $hasUpdate = true;
            }
            if ($menuItemEntity->getProductGroup() != $productGroup) {
                $menuItemEntity->setProductGroup($productGroup);
                $hasUpdate = true;
            }
            if ($menuItemEntity->getBlogCategory() != $blogCategory) {
                $menuItemEntity->setBlogCategory($blogCategory);
                $hasUpdate = true;
            }
            if ($menuItemEntity->getBrand() != $brand) {
                $menuItemEntity->setBrand($brand);
                $hasUpdate = true;
            }
            if ($menuItemEntity->getWarehouse() != $warehouse) {
                $menuItemEntity->setWarehouse($warehouse);
                $hasUpdate = true;
            }
            if ($menuItemEntity->getMenuItem() != $parent) {
                $menuItemEntity->setMenuItem($parent);
                $hasUpdate = true;
            }
            if ($menuItemEntity->getLevel() != $level) {
                $menuItemEntity->setLevel($level);
                $hasUpdate = true;
            }
            if ($menuItemEntity->getUrl() != $url) {
                $menuItemEntity->setUrl($url);
                $hasUpdate = true;
            }
            if ($hasUpdate) {
                $this->entityManager->saveEntity($menuItemEntity);
            }
        }

        if (isset($menuItemData["children"]) && !empty($menuItemData["children"])) {
            foreach ($menuItemData["children"] as $key2 => $child) {
                $this->updateMenuItem($child, $menuEntity, $key2, $menuItemEntity);
            }
        }

        return true;
    }

    /**
     * @param $menuItems
     * @return array
     */
    public function getIds($menuItems)
    {

        $usedIds = array();

        foreach ($menuItems as $menuItem) {
            if (!empty($menuItem["id"])) {
                $usedIds[] = $menuItem["id"];
            }
            if (isset($menuItem["children"]) && !empty($menuItem["children"])) {
                $usedIdsChildren = $this->getIds($menuItem["children"]);
                $usedIds = array_merge($usedIds, $usedIdsChildren);
            }
        }

        return $usedIds;
    }

    /**
     * @param SMenuItemEntity $menuItem
     * @param int $level
     * @return array
     */
    public function getParentsTree(SMenuItemEntity $menuItem, $level = 1)
    {

        $level++;

        if ($level > 5) {
            return array();
        }

        $ret = array();

        if (!empty($menuItem->getMenuItem())) {
            $menuItem = $menuItem->getMenuItem();
            $ret[] = $menuItem;
            $retTmp = $this->getParentsTree($menuItem, $level);
            if (!empty($retTmp)) {
                $ret = array_merge($ret, $retTmp);
            }
        }

        return $ret;
    }
}
