<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\AppTemplateManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\SFrontBlockEntity;
use ScommerceBusinessBundle\Entity\SPageEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Entity\STemplateTypeEntity;

class TemplateManager extends AbstractScommerceManager
{
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var AppTemplateManager $appTemplateManager */
    protected $appTemplateManager;

    public function initialize()
    {
        parent::initialize();
        $this->routeManager = $this->container->get('route_manager');
        $this->appTemplateManager = $this->container->get('app_template_manager');
    }

    /**
     * @param int $websiteId
     * @return string
     */
    public function getBaseTemplateBundle($websiteId = 1)
    {
        $cacheItem = $this->cacheManager->getCacheGetItem("base_template_bundle_$websiteId");

        $bundleName = "";
        if (empty($cacheItem)) {
            $bundles = $this->appTemplateManager->getTemplateBundles($websiteId);
            foreach ($bundles as $bundle) {
                if ($this->container->get('templating')->exists($bundle . "::base.html.twig")) {
                    $this->cacheManager->setCacheItem("base_template_bundle_$websiteId", $bundle);
                    $bundleName = $bundle;
                    break;
                }
            }
        } else {
            $bundleName = $cacheItem->get();
        }

        if (empty($bundleName)) {
            throw new \InvalidArgumentException("The template does not exist");
        }

        return strtolower(str_replace("BusinessBundle", "", $bundleName));
    }

    /**
     * @param $template
     * @param int $websiteId
     * @return string
     */
    public function getTemplatePathByBundle($template, $websiteId = 1, $blockId = null)
    {
        $session = $this->container->get("session");
        $currentWebsiteId = $session->get("current_website_id");
        if (!empty($currentWebsiteId) && $currentWebsiteId != $websiteId) {
            $websiteId = $currentWebsiteId;
        }
        return $this->appTemplateManager->getTemplatePathByBundle($template, $websiteId, $blockId);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getTemplateTypeById($id)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("s_template_type");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param $code
     * @return |null
     */
    public function getTemplateTypeByCode($code)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("s_template_type");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $code));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getFrontBlockById($id)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("s_front_block");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param $ids
     * @return mixed
     */
    public function getFrontBlocks($ids = null)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("s_front_block");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        if (!empty($ids)) {
            $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",", $ids)));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("type", "asc"));
        $sortFilters->addSortFilter(new SortFilter("name", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param $ids
     * @return mixed
     */
    public function getPagesByIds($ids)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("s_page");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",", $ids)));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param STemplateTypeEntity $templateType
     * @param SStoreEntity|null $store
     * @return mixed
     */
    public function getPagesByTemplateAndStore(STemplateTypeEntity $templateType, SStoreEntity $store = null)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("s_page");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("templateType", "eq", $templateType->getId()));
        if (!empty($store)) {
            $compositeFilter->addFilter(new SearchFilter("store", "eq", $store->getId()));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param SFrontBlockEntity $frontBlock
     * @param $data
     * @return AbstractBaseFrontBlock
     */
    public function getBlock(SFrontBlockEntity $frontBlock, $data)
    {
        /**@var AbstractBaseFrontBlock $block */
        $block = $this->container->get($frontBlock->getType() . "_front_block");
        $block->setBlock($frontBlock);
        $block->setBlockData($data);

        return $block;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function createNewFrontBlock($data)
    {
        /** @var SFrontBlockEntity $entity */
        $entity = $this->entityManager->getNewEntityByAttributSetName("s_front_block");

        $entity->setName($data["name"]);
        $entity->setType($data["type"]);
        $entity->setClass($data["class"]);
        $entity->setDataAttributes($data["data_attributes"]);
        $entity->setActive($data["active"] ?? 1);

        return $this->entityManager->saveEntity($entity);
    }

    /**
     * @param $entity
     * @return mixed
     */
    public function save($entity)
    {

        return $this->entityManager->saveEntity($entity);
    }

    /**
     * @param $data
     * @param $id
     * @return bool|string
     */
    public function generateAdminBlockHtml($data, $id)
    {

        /** @var SFrontBlockEntity $frontBlock */
        $frontBlock = $this->getFrontBlockById($id);

        $session = $this->container->get("session");

        if (empty($frontBlock)) {
            return $this->twig->render($this->getTemplatePathByBundle("FrontBlock:block_empty.html.twig", $session->get("current_website_id")));
        }

        $data["block"] = $frontBlock;

        /**@var AbstractBaseFrontBlock $block */
        $block = $this->getBlock($frontBlock, $data);

        /**
         * If page block data is empty make it unauthorized
         */
        $frontBlockData = $block->GetAdminBlockData();

        if ($this->container->get('templating')->exists($block->GetBlockAdminTemplate())) {
            return $this->twig->render($block->GetBlockAdminTemplate(), array("data" => $frontBlockData));
        }

        try {
            $twig = new \Twig_Environment(new \Twig_Loader_Chain());
            $rendered = $twig->render(
                $this->getTemplatePathByBundle("FrontBlock:block_error.html.twig", $session->get("current_website_id")),
                array("data" => $data)
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return false;
        }

        return $rendered;
    }

    /**
     * @param $block_id
     * @return array
     */
    public function findBlockInBlockContent($block_id)
    {

        /** @var DatabaseContext $databaseContext */
        $databaseContext = $this->container->get("database_context");

        $query = "SELECT id FROM s_front_block_entity WHERE content like '%\"id\":\"{$block_id}\"%'";

        $res = $databaseContext->executeQuery($query);

        $ret = array();

        if (!empty($res)) {
            $ids = array();
            foreach ($res as $r) {
                $ids[] = $r["id"];
            }

            $ret = $this->getFrontBlocks($ids);
        }

        return $ret;
    }

    /**
     * @param $block_id
     * @return array
     */
    public function findBlockInPageContent($block_id)
    {

        /** @var DatabaseContext $databaseContext */
        $databaseContext = $this->container->get("database_context");

        $query = "SELECT id FROM s_page_entity WHERE content like '%\"id\":\"{$block_id}\"%'";

        $res = $databaseContext->executeQuery($query);

        $ret = array();

        if (!empty($res)) {
            $ids = array();
            foreach ($res as $r) {
                $ids[] = $r["id"];
            }

            $ret = $this->getPagesByIds($ids);
        }

        return $ret;
    }

    /**
     * @param SFrontBlockEntity $entity
     * @param null $parentEntity
     * @param bool $forceDeleteAll
     * @return bool
     */
    public function deleteFrontBlock(SFrontBlockEntity $entity, $parentEntity = null, $forceDeleteAll = false)
    {
        if (!empty($parentEntity)) {
            $parentEntityTypeCode = $parentEntity->getEntityType()->getEntityTypeCode();
            if ($parentEntityTypeCode == "s_page") {
                $layoutColumnKey = "layout";
            } else {
                $layoutColumnKey = "content";
            }
            $getter = EntityHelper::makeGetter($layoutColumnKey);
            $setter = EntityHelper::makeSetter($layoutColumnKey);

            $content = $parentEntity->$getter();
            $content = $this->removeBlockFromContent($content, $entity->getId());
            $parentEntity->$setter($content);
            $this->save($parentEntity);
        }

        $existing = false;

        $blocks = $this->findBlockInBlockContent($entity->getId());
        if (!empty($blocks)) {
            $existing = true;
            if ($forceDeleteAll) {
                /** @var SFrontBlockEntity $block */
                foreach ($blocks as $block) {
                    $content = $this->removeBlockFromContent($block->getContent(), $entity->getId());
                    $block->setContent($content);
                    $this->save($block);
                }
            }
        }

        $pages = $this->findBlockInPageContent($entity->getId());
        if (!empty($pages)) {
            $existing = true;
            if ($forceDeleteAll) {
                /** @var SPageEntity $page */
                foreach ($pages as $page) {
                    $content = $this->removeBlockFromContent($page->getContent(), $entity->getId());
                    $page->setContent($content);
                    $this->save($page);
                }
            }
        }

        if (!$existing || $forceDeleteAll) {
            //$this->entityManager->deleteEntityFromDatabase($entity);
        }

        return true;
    }

    /**
     * @param $content
     * @param $id
     * @return string
     */
    public function removeBlockFromContent($content, $id)
    {

        if (empty($content)) {
            return false;
        }

        $content = json_decode($content);
        $newContent = array();
        foreach ($content as $key => $c) {
            if ($c->id != $id) {
                $newContent[] = $c;
            }
        }

        return json_encode($newContent);
    }

    /**
     * @param $data
     * @param $blockId
     * @return bool|string
     */
    public function generateBlockHtml($data, $blockId)
    {
        /** @var SFrontBlockEntity $block */
        $block = $this->getFrontBlockById($blockId);

        $session = $this->container->get("session");

        if (empty($block) || $block->getEntityStateId() != 1) {
            return false;
        }

        // show inactive blocks for admin
        $isAdmin = false;
        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();

        if (!empty($coreUser)) {
            $roleCodes = $coreUser->getUserRoleCodes();
            if (in_array("ROLE_COMMERCE_ADMIN", $roleCodes)) {
                $isAdmin = true;
            }
            if (!$isAdmin) {
                $frontendAdminRoles = json_decode($_ENV["FRONTEND_ADMIN_ACCOUNT_ROLES"], true);
                foreach ($frontendAdminRoles as $frontendAdminRole) {
                    if (in_array($frontendAdminRole, $roleCodes)) {
                        $isAdmin = true;
                        break;
                    }
                }
            }
        }

        if (!$block->getActiveIncludingDates() && !$isAdmin) {
            return false;
        }

        $data["block"] = $block;

        /**@var AbstractBaseFrontBlock $frontBlock */
        $frontBlock = $this->getBlock($block, $data);

        if ($frontBlock->isVisible()) {
            $showOnStore = $block->getShowOnStore();
            if (!empty($showOnStore) && empty($showOnStore[$session->get("current_store_id")])) {
                return false;
            }

            if (!empty($block->getContent()) && ($block->getType() == "container" || $block->getType() == "tabs")) {
                $content = json_decode($block->getContent(), true);
                $content = $this->helperManager->prepareBlockGrid($content);
                $block->setPreparedContent($content);
            }

            if ($frontBlock::CACHE_BLOCK_HTML && ((!isset($_ENV["DISABLE_FRONT_BLOCK_HTML_CACHE"]) || $_ENV["DISABLE_FRONT_BLOCK_HTML_CACHE"] != 1) && !$isAdmin)) {
                $sFrontBlockCacheItem = $this->cacheManager->getCacheGetItem("s_front_block_" . $block->getId() . "_" . $session->get("current_store_id"));
                if (empty($sFrontBlockCacheItem) || isset($_GET["rebuild_cache"])) {
                    $blockHtml = $this->renderBlockHtml($frontBlock, $data, $session);

                    $cacheTags = $frontBlock::CACHE_BLOCK_HTML_TAGS;
                    $cacheTags[] = "s_front_block";
                    $cacheTags[] = $block->getType();

                    $this->cacheManager->setCacheItem("s_front_block_" . $block->getId() . "_" . $session->get("current_store_id"), $blockHtml, $cacheTags);

                    return $blockHtml;
                } else {
                    return $sFrontBlockCacheItem->get();
                }
            } else {
                return $this->renderBlockHtml($frontBlock, $data, $session);
            }
        }

        return false;
    }

    /**
     * Generate raw html for block
     *
     * @param $frontBlock
     * @param $data
     * @param $session
     * @return false
     */
    private function renderBlockHtml($frontBlock, $data, $session)
    {
        $frontBlockData = $frontBlock->GetBlockData();

        if (empty($frontBlockData)) {
            return $this->twig->render(
                $this->getTemplatePathByBundle("FrontBlock:block_error.html.twig", $session->get("current_website_id")),
                array("data" => $data)
            );
        }

        if ($this->container->get('templating')->exists($frontBlock->GetBlockTemplate())) {
            return $this->twig->render($frontBlock->GetBlockTemplate(), array("data" => $frontBlockData));
        }

        try {
            $twig = new \Twig_Environment(new \Twig_Loader_Chain());
            return $twig->render(
                $this->getTemplatePathByBundle("FrontBlock:block_error.html.twig", $session->get("current_website_id")),
                array("data" => $data)
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return false;
    }

    /**
     * @param SFrontBlockEntity $parentFrontBlock
     * @param $contentBlocks
     * @return bool
     */
    public function saveFrontBlockContent(SFrontBlockEntity $parentFrontBlock, $contentBlocks)
    {

        $newContent = array();

        if (!empty($contentBlocks)) {
            foreach ($contentBlocks as $key => $contentBlock) {

                /** @var SFrontBlockEntity $frontBlock */
                $frontBlock = $this->getFrontBlockById($contentBlock["id"]);

                if (!empty($frontBlock)) {
                    $contentBlock["id"] = $frontBlock->getId();

                    if ($frontBlock->getType() == "container") {
                        $this->saveFrontBlockContent($frontBlock, $contentBlock["children"]);
                    }

                    /*$block = $this->getBlock($frontBlock, null);
                    $blockSettings = $block->GetBlockSetingsData();

                    if(isset($blockSettings["show_content"]) && $blockSettings["show_content"] == 1){
                        $this->saveFrontBlockContent($frontBlock,$contentBlock["children"]);
                    }*/
                    unset($contentBlock["children"]);
                    $newContent[] = $contentBlock;
                }
            }
        }

        $parentFrontBlock->setContent(json_encode($newContent));

        $this->entityManager->saveEntityWithoutLog($parentFrontBlock);

        return true;
    }
}
