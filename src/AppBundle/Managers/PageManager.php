<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\NavigationLinkContext;
use AppBundle\Context\PageBlockContext;
use AppBundle\Context\PageContext;
use AppBundle\Context\RoleContext;
use AppBundle\Entity\AttributeGroup;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\ListView;
use AppBundle\Entity\NavigationLink;
use AppBundle\Entity\Page;
use AppBundle\Entity\PageBlock;
use AppBundle\Helpers\StringHelper;
use Doctrine\Common\Inflector\Inflector;

class PageManager extends AbstractBaseManager
{
    /**@var PageContext $pageContext */
    protected $pageContext;
    /**@var PageBlockContext $pageBlockContext */
    protected $pageBlockContext;
    /**@var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;
    /**@var AttributeGroupContext $attributeGroupContext */
    protected $attributeGroupContext;
    /**@var NavigationLinkContext $navigationLinkContext */
    protected $navigationLinkContext;
    /**@var BlockManager $blockManager */
    protected $blockManager;
    /**@var NavigationLinkManager $navigationLinkManager */
    protected $navigationLinkManager;
    protected $router;
    /** @var SyncManager $syncManager */
    protected $syncManager;
    /** @var PrivilegeManager $privilegeManager */
    protected $privilegeManager;


    public function initialize()
    {
        parent::initialize();
    }

    public function loadPage($type, $url)
    {
        if(empty($this->pageContext)){
            $this->pageContext = $this->container->get("page_context");
        }

        $page = $this->pageContext->getOneBy(array('type' => $type, 'url' => $url), array());

        // TODO provjeriti prava po navigation link controlleru
        // TODO baci exception ako ne postoji

        return $page;
    }

    public function loadPageByUid($page_uid)
    {
        if(empty($this->pageContext)){
            $this->pageContext = $this->container->get("page_context");
        }

        $page = $this->pageContext->getOneBy(array('uid' => $page_uid), array());

        // TODO provjeriti prava po navigation link controlleru
        // TODO baci exception ako ne postoji

        return $page;
    }


    /**
     * @param Page $page
     * @return mixed
     */
    public function getPageUrl(Page $page)
    {
        if(empty($this->router)){
            $this->router = $this->container->get("router");
        }

        return $this->router->generate('page_view', array('url' => $page->getUrl(), 'type' => $page->getType()));
    }

    /**
     * @param $entity
     * @return bool
     */
    public function save(Page $page)
    {
        if(empty($this->pageContext)){
            $this->pageContext = $this->container->get("page_context");
        }
        if(empty($this->navigationLinkContext)){
            $this->navigationLinkContext = $this->container->get("navigation_link_context");
        }
        if(empty($this->navigationLinkManager)){
            $this->navigationLinkManager = $this->container->get("navigation_link_manager");
        }

        $saveDefaultPrivileges = false;
        if (empty($page->getId())) {
            $saveDefaultPrivileges = true;
        }

        try {
            $this->pageContext->save($page);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        if(empty($this->syncManager)){
            $this->syncManager = $this->container->get("sync_manager");
        }

        $this->syncManager->exportEntityByTableAndId("page",$page->getId(),true);

        if ($saveDefaultPrivileges) {
            if(empty($this->privilegeManager)){
                $this->privilegeManager = $this->container->get("privilege_manager");
            }
            $this->privilegeManager->addPrivilegesToAllGroups('page', $page->getUid());
        }

        $url = $this->getPageUrl($page);

        /** @var NavigationLink $navigationLink */
        $navigationLink = $this->navigationLinkContext->getOneBy(array('page' => $page));

        if (empty($navigationLink)) {
            $parentItem = $this->navigationLinkManager->getDefaultListParent();

            $navigationLinkCheckUrl = $this->navigationLinkContext->getOneBy(array('url' => $url));
            if (!empty($navigationLinkCheckUrl)) {
                $this->logger->error("Url allready exists");
                return false;
            }

            $navigationLink = new NavigationLink();
            $navigationLink->setPage($page);
            $navigationLink->setDisplayName($page->getTitle());
            $navigationLink->setIsParent(false);
            $navigationLink->setParent($parentItem);
            $navigationLink->setUrl($url);
            $navigationLink->setShow(true);
            $navigationLink->setTarget("_self");
            $navigationLink->setIsCustom($page->getIsCustom());

            $this->navigationLinkManager->save($navigationLink);
        } else {

            if($navigationLink->getUrl() != $url){
                $navigationLink->setUrl($url);
            }

            $navigationLink->setIsCustom($page->getIsCustom());
            $this->navigationLinkManager->save($navigationLink);
        }

        return $page;
    }

    /**
     * @param Page $page
     * @return bool
     */
    public function delete(Page $page)
    {
        if(empty($this->pageContext)){
            $this->pageContext = $this->container->get("page_context");
        }
        if(empty($this->navigationLinkContext)){
            $this->navigationLinkContext = $this->container->get("navigation_link_context");
        }
        if(empty($this->navigationLinkManager)){
            $this->navigationLinkManager = $this->container->get("navigation_link_manager");
        }

        /**delete navigation_link **/
        $navigationLink = $this->navigationLinkContext->getOneBy(array('page' => $page));
        if (!empty($navigationLink)) {
            $this->navigationLinkManager->delete($navigationLink);
        }

        if(empty($this->syncManager)){
            $this->syncManager = $this->container->get("sync_manager");
        }

        try {
            $row = $this->syncManager->getEntityRecordById("page",$page->getId());
            $this->syncManager->deleteEntityRecord("page",$row);

            $this->pageContext->delete($page);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param ListView $listView
     * @return bool
     */
    public function generateListViewPage(ListView $listView)
    {
        if(empty($this->pageContext)){
            $this->pageContext = $this->container->get("page_context");
        }
        if(empty($this->blockManager)){
            $this->blockManager = $this->container->get("block_manager");
        }

        /**generate list view page*/
        $pageBlock = $this->blockManager->getBlockByTypeAndRelatedId('list_view', $listView->getId());
        if (empty($pageBlock)) {
            $pageBlock = new PageBlock();

            $pageBlock->setTitle($listView->getDisplayName());
            $pageBlock->setType("list_view");
            $pageBlock->setRelatedId($listView->getId());
            $pageBlock->setEntityType($listView->getEntityType());
            $pageBlock->setAttributeSet($listView->getAttributeSet());
            $pageBlock->setBundle($listView->getEntityType()->getBundle());
            $pageBlock->setIsCustom($listView->getIsCustom());
            $pageBlock = $this->blockManager->save($pageBlock);
        }

        $page = $this->pageContext->getOneBy(array('url' => strtolower($listView->getName()), 'type' => "list"));
        if (empty($page)) {
            $page = new Page();

            $page->setTitle($listView->getDisplayName());
            $page->setUrl($listView->getEntityType()->getEntityTypeCode());
            $page->setType("list");
            $page->setEntityType($listView->getEntityType());
            $page->setAttributeSet($listView->getAttributeSet());
            $page->setBundle($listView->getEntityType()->getBundle());
            $page->setIsCustom($listView->getIsCustom());
            $pageContent = StringHelper::format("[{\"title\":\"{1}\",\"height\":2,\"width\":12,\"id\":\"{0}\",\"x\":0,\"y\":2,\"type\":\"list_view\"}]", $pageBlock->getUid(), $listView->getDisplayName());

            $page->setContent($pageContent);

            $this->save($page);
        }

        $pageBlock->setParent($page->getUid());
        $this->blockManager->save($pageBlock);

        return true;
    }

    /**
     * @param AttributeSet $attributeSet
     * @return bool
     */
    public function generateAttributeSetPages(AttributeSet $attributeSet)
    {
        if(empty($this->pageContext)){
            $this->pageContext = $this->container->get("page_context");
        }
        if(empty($this->pageBlockContext)){
            $this->pageBlockContext = $this->container->get("page_block_context");
        }
        if(empty($this->attributeGroupContext)){
            $this->attributeGroupContext = $this->container->get("attribute_group_context");
        }
        if(empty($this->blockManager)){
            $this->blockManager = $this->container->get("block_manager");
        }

        $attributeGroups = $this->attributeGroupContext->getBy(array('attributeSet' => $attributeSet));
        if (!empty($attributeGroups)) {
            /** @var AttributeGroup $attributeGroup */
            foreach ($attributeGroups as $attributeGroup) {
                $attributeGroupBlock = $this->blockManager->getBlockByTypeAndRelatedId('attribute_group', $attributeGroup->getId());
                if (empty($attributeGroupBlock)) {
                    $attributeGroupBlock = new PageBlock();

                    $attributeGroupBlock->setTitle($attributeGroup->getAttributeGroupName());
                    $attributeGroupBlock->setType("attribute_group");
                    $attributeGroupBlock->setAttributeSet($attributeSet);
                    $attributeGroupBlock->setEntityType($attributeSet->getEntityType());
                    $attributeGroupBlock->setRelatedId($attributeGroup->getId());
                    $attributeGroupBlock->setIsCustom($attributeGroup->getIsCustom());
                    $this->blockManager->save($attributeGroupBlock);
                }
            }
        }

        $blockContent = array();
        $attributeGroupBlocks = $this->pageBlockContext->getBy(array('type' => 'attribute_group', 'attributeSet' => $attributeSet));
        foreach ($attributeGroupBlocks as $attributeGroupBlock) {
            $blockContent[] = StringHelper::format("{\"title\":\"{1}\",\"id\":\"{0}\",\"type\":\"attribute_group\",\"x\":0,\"y\":0,\"width\":12,\"height\":2}", $attributeGroupBlock->getUid(), $attributeGroup->getAttributeGroupName());
        }
        $blockContent = implode(",", $blockContent);
        $blockContent = '['.$blockContent.']';

        /**create block holder for attribute group*/
        /**@var PageBlock $pageBlock*/
        $pageBlock = $this->pageBlockContext->getOneBy(array('attributeSet' => $attributeSet, 'type' => "edit_form"));
        if (empty($pageBlock)) {
            $pageBlock = new PageBlock();
            $pageBlock->setTitle(str_replace("_", " ", ucfirst($attributeSet->getAttributeSetCode()))." form");
            $pageBlock->setType("edit_form");
            $pageBlock->setAttributeSet($attributeSet);
            $pageBlock->setEntityType($attributeSet->getEntityType());
            $pageBlock->setIsCustom($attributeSet->getIsCustom());
            $pageBlock->setContent($blockContent);


            $pageBlock = $this->blockManager->save($pageBlock);
        }

        foreach ($attributeGroupBlocks as $attributeGroupBlock) {
            $attributeGroupBlock->setParent($pageBlock->getUid());
            $attributeGroupBlock->setBundle($attributeSet->getEntityType()->getBundle());
            $attributeGroupBlock->setIsCustom($attributeSet->getIsCustom());
            $this->blockManager->save($attributeGroupBlock);
        }

        /**create page for edit form*/
        /**@var Page $page*/
        $page = $this->pageContext->getOneBy(array('url' => $attributeSet->getAttributeSetCode(), 'type' => "form"));
        if (empty($page)) {
            $page = new Page();
            $page->setBundle($attributeSet->getEntityType()->getBundle());
            $page->setTitle(str_replace("_", " ", ucfirst($attributeSet->getAttributeSetCode()))." form");
            $page->setUrl($attributeSet->getAttributeSetCode());
            $page->setType("form");
            $pageContent = StringHelper::format("[{\"title\":\"{1}\",\"height\":5,\"width\":12,\"id\":\"{0}\",\"x\":0,\"y\":2,\"type\":\"edit_form\"}]", $pageBlock->getUid(), str_replace("_", " ", ucfirst($attributeSet->getAttributeSetCode()))." form");
            $page->setContent($pageContent);
            $page->setAttributeSet($attributeSet);
            $page->setEntityType($attributeSet->getEntityType());
            $page->setIsCustom($attributeSet->getIsCustom());
            $page->setButtons('[{"type":"button","name":"Save","class":"btn-primary btn-blue","url":"","action":"return"},{"type":"button","name":"Save and continue","class":"btn-primary btn-blue","url":"","action":"continue"},{"type":"link","name":"Back","class":"btn-default btn-red","url":"","action":"back"}]');
            $this->save($page);
        }


        $pageBlock->setParent($page->getUid());
        $pageBlock->setBundle($attributeSet->getEntityType()->getBundle());
        $pageBlock->setIsCustom($attributeSet->getIsCustom());
        $this->blockManager->save($pageBlock);

        return true;
    }

    /**
     * @param EntityType $entityType
     * @return bool
     */
    public function generateDefaultPages(EntityType $entityType)
    {
        if(empty($this->attributeSetContext)){
            $this->attributeSetContext = $this->container->get("attribute_set_context");
        }

        $listViewContext = $this->container->get("list_view_context");

        /** generate listViews */
        $listViews = $listViewContext->getBy(array('entityType' => $entityType));
        if (!empty($listViews)) {
            foreach ($listViews as $listView) {
                $this->generateListViewPage($listView);
            }
        }

        /** generate attribute groups */
        $attributeSets = $this->attributeSetContext->getBy(array('entityType' => $entityType));
        if (!empty($attributeSets)) {
            foreach ($attributeSets as $attributeSet) {
                $this->generateAttributeSetPages($attributeSet);
            }
        }

        return true;
    }
}
