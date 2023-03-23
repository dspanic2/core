<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\PageBlockContext;
use AppBundle\Context\PageContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\Page;
use AppBundle\Entity\PageBlock;
use AppBundle\Interfaces\Blocks\BlockInterface;
use Doctrine\Common\Inflector\Inflector;

class BlockManager extends AbstractBaseManager
{
    /**@var PageBlockContext $pageBlockContext */
    protected $pageBlockContext;
    /**@var PageContext $pageContext */
    protected $pageContext;
    /** @var HelperManager */
    protected $helperManager;
    /** @var SyncManager $syncManager */
    protected $syncManager;
    /** @var PageManager $pageManager */
    protected $pageManager;
    /** @var PrivilegeManager $privilegeManager */
    protected $privilegeManager;

    public function initialize()
    {
        parent::initialize();
        $this->pageBlockContext = $this->container->get("page_block_context");
    }

    public function getBlock(PageBlock $pageBlock, $data)
    {
        /**@var AbstractBaseBlock $block */
        $block = $this->container->get($pageBlock->getType() . "_block");
        $block->setPageBlock($pageBlock);
        $block->setPageBlockData($data);

        return $block;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getBlockById($id)
    {
        return $this->pageBlockContext->getById($id);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getBlockByUid($id)
    {

        return $this->pageBlockContext->getBlockByUid($id);
    }

    /**
     * @param $blockType
     * @param $remoteId
     * @return mixed
     */
    public function getBlockByTypeAndRelatedId($blockType, $remoteId)
    {

        return $this->pageBlockContext->getOneBy(array('type' => $blockType, 'relatedId' => $remoteId));
    }

    /**
     * @param $attributeSetCode
     * @return bool|string
     */
    public function getDefautlEditFromIdByAttributeSet($attributeSetCode)
    {

        /** @var AttributeSetContext $attributeSetContext */
        $attributeSetContext = $this->container->get("attribute_set_context");

        /** @var AttributeSet $attributeSet */
        $attributeSet = $attributeSetContext->getOneBy(array("attributeSetCode" => $attributeSetCode));

        if (empty($attributeSet)) {
            return false;
        }

        /** @var PageBlock $pageBlock */
        $pageBlock = $this->pageBlockContext->getOneBy(array("attributeSet" => $attributeSet->getId(), "type" => "edit_form"));

        if (empty($pageBlock)) {
            return false;
        }

        return $pageBlock->getUid();
    }


    /**
     * @param PageBlock $entity
     * @return bool
     */
    public function save($entity)
    {
        $saveDefaultPrivileges = false;
        if (empty($entity->getId())) {
            $saveDefaultPrivileges = true;
        }

        try {
            $this->pageBlockContext->save($entity);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        $this->syncManager->exportEntityByTableAndId("page_block",$entity->getId(),true);

        if ($saveDefaultPrivileges) {
            if (empty($this->privilegeManager)) {
                $this->privilegeManager = $this->container->get("privilege_manager");
            }
            $this->privilegeManager->addPrivilegesToAllGroups('page_block', $entity->getUid());
        }

        return $entity;
    }

    /**
     * @param PageBlock $entity
     * @return bool
     */
    public function delete($entity)
    {
        if (empty($this->pageContext)) {
            $this->pageContext = $this->container->get("page_context");
        }

        $blocks = $this->pageBlockContext->findBlockInContent($entity->getUid());
        if (!empty($blocks)) {

            /** @var PageBlock $block */
            foreach ($blocks as $block) {

                $tmp = $block->getContent();
                $content = $this->removeBlockFromContent($block->getContent(), $entity->getUid());

                if ($tmp != $content) {
                    $block->setContent($content);
                    $block->setIsCustom(1);
                    $this->save($block);
                }
            }
        }

        $pages = $this->pageContext->findBlockInContent($entity->getUid());
        if (!empty($pages)) {

            if (empty($this->pageManager)) {
                $this->pageManager = $this->container->get("page_manager");
            }

            /** @var Page $page */
            foreach ($pages as $page) {

                $tmp = $page->getContent();
                $content = $this->removeBlockFromContent($page->getContent(), $entity->getUid());

                if ($tmp != $content) {
                    $page->setContent($content);
                    $page->setIsCustom(1);
                    $this->pageManager->save($page);
                }
            }
        }

        return $this->deleteBlockFromDatabase($entity);
    }

    /**
     * @param $entity
     * @return bool
     */
    public function deleteBlockFromDatabase($entity)
    {

        if (empty($this->pageBlockContext)) {
            $this->pageBlockContext = $this->container->get("page_block_context");
        }

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        try {
            $row = $this->syncManager->getEntityRecordById("page_block", $entity->getId());
            $this->syncManager->deleteEntityRecord("page_block", $row);

            $this->pageBlockContext->delete($entity);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
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

    public function generateBlockHtml($data, $blockId)
    {
        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }

        $block = $this->getBlockById($blockId);

        if (empty($block)) {
            return $this->twig->render('AppBundle:Block:block_empty.html.twig', array("data" => $data));
        }

        /**
         * Check privileges
         */
        if (!$this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
            $authorized = false;

            /**
             * Check access
             */
            if ($this->user->hasPrivilege(7, $block->getUid())) {
                $authorized = true;
            }

            if (!$authorized) {
                $this->logger->info("Unauthorized access: username " . $this->user->getUsername() . " - block " . $block->getUid());
                return $this->twig->render('AppBundle:Block:block_empty.html.twig', array("data" => $data));
            }
        }

        $data["block"] = $block;

        /**@var BlockInterface $pageBlock */
        $pageBlock = $this->getBlock($block, $data);

        if (!empty($block->getContent()) && $block->getType() != "text") {
            $content = null;
            if (!is_array($block->getContent())) {
                $content = json_decode($block->getContent(), true);
            }
            $block_settings = $pageBlock->GetPageBlockSetingsData();
            if (isset($block_settings["show_content"]) && $block_settings["show_content"] == 1) {
                if (empty($content)) {
                    $this->logger->error("BLOCK: missing content: " . $block->getId());
                } else {
                    $content = $this->helperManager->prepareBlockGrid($content);
                }
            }
            $block->setPreparedContent($content);
        }

        if ($pageBlock->isVisible()) {

            /**
             * If page block data is empty make it unauthorized
             */
            $pageBlockData = $pageBlock->GetPageBlockData();

            if (!$pageBlock->isVisible()) {
                return false;
            }

            if (empty($pageBlockData)) {
                return $this->twig->render('AppBundle:Block:block_error.html.twig', array("data" => $data));
            }

            if ($this->container->get('templating')->exists($pageBlock->GetPageBlockTemplate())) {
                return $this->twig->render($pageBlock->GetPageBlockTemplate(), array("data" => $pageBlockData));
            }

            try {
                $twig = new \Twig_Environment(new \Twig_Loader_Chain());
                $rendered = $twig->render('AppBundle:Block:block_error.html.twig', array("data" => $data));
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                return false;
            }

            return $rendered;
        }

        return false;
    }

    /**
     * @param PageBlock $block
     * @return array
     */
    public function getPageBlockContentRecursiveFlatArray(PageBlock $block){

        $content = Array();

        if(!empty($block->getContent())){
            $data = json_decode($block->getContent(),true);
            if(!empty($data)){
                foreach ($data as $d){
                    $children = null;
                    if(!isset($d["id"])){
                        continue;
                    }
                    $childBlock = $this->getBlockById($d["id"]);
                    if(!empty($childBlock)){
                        $children = $this->getPageBlockContentRecursiveFlatArray($childBlock);
                        foreach ($children as $child){
                            $content[] = $child;
                        }
                        $content[] = Array("type" => $d["type"], "uid" => $d["id"], "related_id" => $childBlock->getRelatedId());
                    }
                    else{
                        $content[] = Array("type" => $d["type"], "uid" => $d["id"]);
                    }
                }
            }
        }

        $content[] = Array("type" => $block->getType(), "uid" => $block->getUid(), "related_id" => $block->getRelatedId());

        return $content;
    }

    /**
     * @param PageBlock $block
     * @return array
     */
    public function getPageBlockContentRecursiveArray(PageBlock $block){

        $content = Array();

        if(!empty($block->getContent())){
            $data = json_decode($block->getContent(),true);
            if(!empty($data)){
                foreach ($data as $d){
                    $children = null;
                    if(!isset($d["id"])){
                        continue;
                    }
                    $childBlock = $this->getBlockById($d["id"]);
                    if(!empty($childBlock)){
                        $children = $this->getPageBlockContentRecursiveArray($childBlock);
                    }
                    $content["content"][] = Array("type" => $d["type"], "uid" => $d["id"], "children" => $children);
                }
            }
        }

        return $content;
    }

    public function generateBlockHtmlV2($data, $block)
    {

        /**
         * Check privileges
         */
        if (!$this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
            $authorized = false;

            /**
             * Check access
             */
            if ($this->user->hasPrivilege(7, $block->getUid())) {
                $authorized = true;
            }

            if (!$authorized) {
                $this->logger->info("Unauthorized access: username " . $this->user->getUsername() . " - block " . $block->getUid());
                return $this->twig->render('AppBundle:Block:block_empty.html.twig', array("data" => $data));
            }
        }

        $data["block"] = $block;
        $data["locale"] = $this->user->getCoreLanguage()->getCode();
        /**@var BlockInterface $pageBlock */
        $pageBlock = $this->getBlock($block, $data);

        if (!empty($block->getContent()) && $block->getType() != "text") {
            if (!is_array($block->getContent())) {
                $content = json_decode($block->getContent(), true);
            }
            $block_settings = $pageBlock->GetPageBlockSetingsData();
            if (isset($block_settings["show_content"]) && $block_settings["show_content"] == 1) {
                $content = $this->helperManager->prepareBlockGrid($content);
            }
            $block->setPreparedContent($content);
        }

        if ($pageBlock->isVisible()) {

            /**
             * If page block data is empty make it unauthorized
             */
            $pageBlockData = $pageBlock->GetPageBlockData();


            if (!$pageBlock->isVisible()) {
                return false;
            }

            if (empty($pageBlockData)) {
                return $this->twig->render('AppBundle:Block:block_error.html.twig', array("data" => $data));
            }

            if ($this->container->get('templating')->exists($pageBlock->GetPageBlockTemplate())) {
                return $this->twig->render($pageBlock->GetPageBlockTemplate(), array("data" => $pageBlockData));
            }

            try {
                $twig = new \Twig_Environment(new \Twig_Loader_Chain());
                $rendered = $twig->render('AppBundle:Block:block_error.html.twig', array("data" => $data));
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                return false;
            }

            return $rendered;
        } else {
            return false;
        }

        return false;
    }

    public function generateAdminBlockHtml($data, $blockId)
    {

        $block = $this->getBlockById($blockId);

        if (empty($block)) {
            return $this->twig->render('AppBundle:Block:block_empty.html.twig');
        }

        $data["block"] = $block;

        /**@var BlockInterface $pageBlock */
        $pageBlock = $this->getBlock($block, $data);

        /**
         * If page block data is empty make it unauthorized
         */
        $pageBlockData = $pageBlock->GetAdminPageBlockData();

        if ($this->container->get('templating')->exists($pageBlock->GetPageBlockAdminTemplate())) {
            return $this->twig->render($pageBlock->GetPageBlockAdminTemplate(), array("data" => $pageBlockData));
        }

        try {
            $twig = new \Twig_Environment(new \Twig_Loader_Chain());
            $rendered = $twig->render('AppBundle:Block:block_error.html.twig', array("data" => $data));
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return $rendered;
    }

    /**
     * @param PageBlock $parentPageBlock
     * @param $contentBlocks
     * @return bool
     */
    public function savePageBlockContent(PageBlock $parentPageBlock, $contentBlocks)
    {

        $newContent = array();

        if (!empty($contentBlocks)) {
            foreach ($contentBlocks as $key => $contentBlock) {

                /** @var PageBlock $pageBlock */
                $pageBlock = $this->pageBlockContext->getById($contentBlock["id"]);
                if (!empty($pageBlock)) {
                    $contentBlock["id"] = $pageBlock->getUid();

                    $block = $this->getBlock($pageBlock, null);
                    $blockSettings = $block->GetPageBlockSetingsData();

                    if (isset($blockSettings["show_content"]) && $blockSettings["show_content"] == 1) {

                        $tmp = json_decode($pageBlock->getContent(), true);
                        if (!empty($tmp)) {
                            foreach (array_keys($tmp) as $key) {
                                unset($tmp[$key]["id"]);
                                unset($tmp[$key]["children"]);
                            }
                        }
                        $tmp2 = $contentBlock["children"];
                        foreach (array_keys($tmp2) as $key) {
                            unset($tmp2[$key]["id"]);
                            unset($tmp2[$key]["children"]);
                        }

                        if (!empty($tmp) && !empty($tmp2) && (json_encode($tmp) != json_encode($tmp2))) {
                            $pageBlock->setIsCustom(1);
                        }

                        $this->savePageBlockContent($pageBlock, $contentBlock["children"]);
                    }
                    unset($contentBlock["children"]);
                    $newContent[] = $contentBlock;

                    if ($pageBlock->getIsCustom()) {
                        $parentPageBlock->setIsCustom($pageBlock->getIsCustom());
                    }
                }
            }
        }

        $parentPageBlock->setContent(json_encode($newContent));

        $this->save($parentPageBlock);

        return true;
    }
}
