<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\BlogCategoryEntity;
use ScommerceBusinessBundle\Entity\SMenuItemEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;

class BreadcrumbsManager extends AbstractScommerceManager
{
    /** @var MenuManager $menuManager */
    protected $menuManager;
    /** @var DefaultScommerceManager $defaultScommerceManager */
    protected $defaultScommerceManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $entity
     * @return array
     */
    public function getBreadcrumbForEntity($entity)
    {
        $id = $entity->getId();
        $code = $entity->getEntityType()->getEntityTypeCode();

        $session = $this->container->get("session");
        $storeId = $session->get("current_store_id");

        $breadcrumbCacheItem = $this->cacheManager->getCacheGetItem("breadcrumb_data_" . $code . "_" . $storeId . "_" . $id);

        if (empty($breadcrumbCacheItem) || isset($_GET["rebuild_cache"])) {

            if (empty($this->getPageUrlExtension)) {
                $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
            }

            $homepageUrl = "/";
            if (!empty($session->get("current_language_url"))) {
                $homepageUrl = $session->get("current_language_url");
            }

            $ret = [];
            $cacheTags = [];
            $cacheTags[] = $entity->getEntityType()->getEntityTypeCode() . '_' . $entity->getId();

            $ret[] = array(
                "type" => "link",
                "url" => $homepageUrl,
                "name" => $this->translator->trans('Homepage')
            );

            /** Product group breadcrumb */
            if ($entity->getEntityType()->getEntityTypeCode() == "product_group") {

                /** @var ProductGroupEntity $productGroup */
                $productGroup = $entity;

                $tmp = array();
                $cacheTags[] = 'product_group_' . $productGroup->getId();

                while (!empty($productGroup->getProductGroup())) {
                    $productGroup = $productGroup->getProductGroup();
                    $cacheTags[] = 'product_group_' . $productGroup->getId();

                    $tmp[] = array(
                        "type" => "link",
                        "url" => $session->get("current_language_url") . "/" . $productGroup->getUrlPath($storeId),
                        "name" => $productGroup->getName()[$storeId]
                    );
                }

                if (!empty($tmp)) {
                    $tmp = array_reverse($tmp);
                    $ret = array_merge($ret, $tmp);
                }

                $ret[] = array(
                    "type" => "label",
                    "url" => null,
                    "name" => $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $entity, "name")
                );
            } /** Page breadcrumb */
            elseif ($entity->getEntityType()->getEntityTypeCode() == "s_page") {

                $cacheTags[] = 's_page_' . $entity->getId();

                $ret[] = array(
                    "type" => "label",
                    "url" => null,
                    "name" => $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $entity, "name")
                );
            } /** Blog category breadcrumb */
            elseif ($entity->getEntityType()->getEntityTypeCode() == "blog_category") {

                /** @var BlogCategoryEntity $blogCategory */
                $blogCategory = $entity;

                $tmp = array();
                $cacheTags[] = 'blog_category_' . $blogCategory->getId();

                while (!empty($blogCategory->getParentBlogCategory())) {
                    $blogCategory = $blogCategory->getParentBlogCategory();
                    $cacheTags[] = 'blog_category_' . $blogCategory->getId();

                    $tmp[] = array(
                        "type" => "link",
                        "url" => $session->get("current_language_url") . "/" . $blogCategory->getUrlPath($storeId),
                        "name" => $blogCategory->getName()[$storeId]
                    );
                }

                if (!empty($tmp)) {
                    $tmp = array_reverse($tmp);
                    $ret = array_merge($ret, $tmp);
                }

                $ret[] = array(
                    "type" => "label",
                    "url" => null,
                    "name" => $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $entity, "name")
                );
            } /** Blog post breadcrumb */
            elseif ($entity->getEntityType()->getEntityTypeCode() == "blog_post") {

                $tmp = array();

                $blogCategory = $entity->getBlogCategory();
                $cacheTags[] = 'blog_post_' . $entity->getId();

                if (!empty($blogCategory)) {
                    $tmp[] = array(
                        "type" => "link",
                        "url" => $session->get("current_language_url") . "/" . $blogCategory->getUrlPath($storeId),
                        "name" => $blogCategory->getName()[$storeId]
                    );
                    $cacheTags[] = 'blog_category_' . $entity->getId();

                    while (!empty($blogCategory->getParentBlogCategory())) {
                        $blogCategory = $blogCategory->getParentBlogCategory();
                        $cacheTags[] = 'blog_category_' . $blogCategory->getId();

                        $tmp[] = array(
                            "type" => "link",
                            "url" => $session->get("current_language_url") . "/" . $blogCategory->getUrlPath($storeId),
                            "name" => $blogCategory->getName()[$storeId]
                        );
                    }
                }

                if (!empty($tmp)) {
                    $tmp = array_reverse($tmp);
                    $ret = array_merge($ret, $tmp);
                }

                $ret[] = array(
                    "type" => "label",
                    "url" => null,
                    "name" => $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $entity, "name")
                );
            } /** Product breadcrumb */
            elseif ($entity->getEntityType()->getEntityTypeCode() == "product") {

                $tmp = array();

                //$cacheTags[] = 'product_' . $entity->getId();

                $menuItem = $this->getLowestProductGroupOnProduct($entity, $storeId);
                if (!empty($menuItem) && !empty($menuItem->getProductGroup())) {
                    $productGroup = $menuItem->getProductGroup();
                    $cacheTags[] = 'product_group_' . $productGroup->getId();

                    $tmp[] = array(
                        "type" => "link",
                        "url" => $session->get("current_language_url") . "/" . $productGroup->getUrlPath($storeId),
                        "name" => $productGroup->getName()[$storeId]
                    );

                    while (!empty($productGroup->getProductGroup())) {
                        $productGroup = $productGroup->getProductGroup();
                        $cacheTags[] = 'product_group_' . $productGroup->getId();

                        $tmp[] = array(
                            "type" => "link",
                            "url" => $session->get("current_language_url") . "/" . $productGroup->getUrlPath($storeId),
                            "name" => $productGroup->getName()[$storeId]
                        );
                    }
                }

                if (!empty($tmp)) {
                    $tmp = array_reverse($tmp);
                    $ret = array_merge($ret, $tmp);
                }

                $ret[] = array(
                    "type" => "label",
                    "url" => null,
                    "name" => $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $entity, "name")
                );
            } /** Brand breadcrumb */
            elseif ($entity->getEntityType()->getEntityTypeCode() == "brand") {

                $cacheTags[] = 'brand_' . $entity->getId();

                $ret[] = array(
                    "type" => "label",
                    "url" => null,
                    "name" => $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $entity, "name")
                );
            } /** Warehouse breadcrumb */
            elseif ($entity->getEntityType()->getEntityTypeCode() == "warehouse") {

                $cacheTags[] = 'warehouse_' . $entity->getId();

                $ret[] = array(
                    "type" => "label",
                    "url" => null,
                    "name" => $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $entity, "name")
                );
            } else {
                if (empty($this->defaultScommerceManager)) {
                    $this->defaultScommerceManager = $this->container->get("scommerce_manager");
                }

                $res = $this->defaultScommerceManager->getBreadcrumbs($entity);
                if (!empty($res)) {
                    $ret = array_merge($ret, $res);
                }
            }

            if (!in_array($code . "_" . $id, $cacheTags)) {
                $cacheTags[] = $code . "_" . $id;
            }
            $this->cacheManager->setCacheItem("breadcrumb_data_" . $code . "_" . $storeId . "_" . $id, $ret, $cacheTags);
        } else {
            $ret = $breadcrumbCacheItem->get();
        }

        return $ret;
    }

    /**
     * @param ProductEntity $product
     * @param $storeId
     * @return SMenuItemEntity|null
     */
    public function getLowestProductGroupOnProduct(ProductEntity $product, $storeId)
    {

        $productGroups = $product->getProductGroups();

        $level = -1;
        $selectedMenuItem = null;

        if (EntityHelper::isCountable($productGroups) && count($productGroups) > 0) {
            if (empty($this->menuManager)) {
                $this->menuManager = $this->container->get("menu_manager");
            }

            /** @var ProductGroupEntity $productGroup */
            foreach ($productGroups as $productGroup) {
                /** @var SMenuItemEntity $menuItem */
                $menuItem = $this->menuManager->getMenuItemByRelatedAndId($productGroup->getEntityType()->getEntityTypeCode(), $productGroup->getId(), $storeId);
                if (!empty($menuItem) && $level < intval($menuItem->getLevel())) {
                    $level = intval($menuItem->getLevel());
                    $selectedMenuItem = $menuItem;
                }
            }
        }

        return $selectedMenuItem;
    }
}
