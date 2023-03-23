<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\NavigationLinkContext;
use AppBundle\Context\PageContext;
use AppBundle\Context\RoleContext;
use AppBundle\Entity;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\PersistentCollection;

class NavigationLinkManager extends AbstractBaseManager
{
    /**@var NavigationLinkContext $navigationLinkContext */
    protected $navigationLinkContext;
    /**@var PageContext $pageContext */
    protected $pageContext;
    /** @var SyncManager $syncManager */
    protected $syncManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    public function initialize()
    {
        parent::initialize();
        $this->navigationLinkContext = $this->container->get("navigation_link_context");
    }

    public function save(Entity\NavigationLink $navigationLink)
    {
        try {
            $this->navigationLinkContext->save($navigationLink);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        if(empty($this->syncManager)){
            $this->syncManager = $this->container->get("sync_manager");
        }

        $this->syncManager->exportEntityByTableAndId("navigation_link",$navigationLink->getId());

        if(empty($this->cacheManager)){
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $this->cacheManager->invalidateCacheByTag("navigation_link");

        return $navigationLink;
    }

    /**
     * @param $navigationLink
     * @param $key
     * @param null $parent
     * @return bool
     */
    public function addNavigationLink($navigationLink, $key, $parent = null)
    {
        if(empty($this->pageContext)){
            $this->pageContext = $this->container->get("page_context");
        }

        /** @var Entity\NavigationLink $link */
        $link = $this->navigationLinkContext->getById($navigationLink["id"]);

        if (empty($link)) {
            $link = new Entity\NavigationLink();
            $link->setDisplayName($navigationLink["text"]);
            $link->setImage($navigationLink["icon"]);
            $link->setCssClass($navigationLink["cssClass"]);
            $link->setOrder($key+10);
            $link->setShow($navigationLink["show"]);
            $link->setTarget($navigationLink["target"]);
            $link->setUrl($navigationLink["url"]);
            $link->setParent($parent);
            $link->setIsParent(0);
            $link->setIsCustom(1);
            if (!empty($navigationLink["children"]) || empty($parent)) {
                $link->setIsParent(1);
            }
            $link->setPage(null);
            if (isset($navigationLink["page"]) && !empty($navigationLink["page"])) {
                /** @var Entity\Page $page */
                $page = $this->pageContext->getById($navigationLink["page"]);
                if (empty($page)) {
                    $this->logger->error('Page is missing for link '.$navigationLink["text"]);
                    return false;
                }
                $link->setPage($page);
                $link->setIsCustom($page->getIsCustom());
            }

            $link = $this->save($link);
        } else {
            $hasChanges = false;
            $orderChanged = false;

            if($link->getDisplayName() != $navigationLink["text"]){
                $hasChanges = true;
                $link->setDisplayName($navigationLink["text"]);
            }

            if($link->getImage() != $navigationLink["icon"]){
                $hasChanges = true;
                $link->setImage($navigationLink["icon"]);
            }

            if($link->getCssClass() != $navigationLink["cssClass"]){
                $hasChanges = true;
                $link->setCssClass($navigationLink["cssClass"]);
            }

            if($link->getOrder() != $key+10){
                $orderChanged = true;
                $link->setOrder($key+10);
            }

            if($link->getShow() != $navigationLink["show"]){
                $hasChanges = true;
                $link->setShow($navigationLink["show"]);
            }

            if($link->getTarget() != $navigationLink["target"]){
                $hasChanges = true;
                $link->setTarget($navigationLink["target"]);
            }

            if($link->getUrl() != $navigationLink["url"]){
                $hasChanges = true;
                $link->setUrl($navigationLink["url"]);
            }
            
            if(!empty($parent) && !empty($link->getParent()) && $link->getParent()->getId() != $parent->getId() && $link->getParent()->getUid() == "db6e0a2a9f0be97c13850a27312360f6"){
                $orderChanged = true;
                $link->setParent($parent);
            }
            elseif((empty($link->getParent()) && !empty($parent)) || (!empty($link->getParent()) && empty($parent)) || (!empty($link->getParent()) && !empty($parent) && $link->getParent()->getId() != $parent->getId())){
                $hasChanges = true;
                $link->setParent($parent);
            }

            $isParent = 0;
            if (!empty($navigationLink["children"]) || empty($parent)) {
                $isParent = 1;
            }

            if($link->getIsParent() != $isParent){
                $hasChanges = true;
                $link->setIsParent($isParent);
            }

            if($hasChanges){
                $link->setIsCustom(1);
            }

            $page = null;
            if (isset($navigationLink["page"]) && !empty($navigationLink["page"])) {
                /** @var Entity\Page $page */
                $page = $this->pageContext->getById($navigationLink["page"]);
                if (empty($page)) {
                    $this->logger->error('Page is missing for link '.$navigationLink["text"]);
                    return false;
                }
            }

            if((empty($link->getPage()) && !empty($page)) || (!empty($link->getPage()) && empty($page)) || (!empty($link->getPage()) && !empty($page) && $link->getPage()->getId() != $page->getId())){
                if(!$hasChanges) {
                    $link->setIsCustom($page->getIsCustom());
                }
                $hasChanges = true;
                $link->setPage($page);
            }

            if($hasChanges || $orderChanged){
                $link = $this->save($link);
            }
        }

        if (!empty($navigationLink["children"])) {
            foreach ($navigationLink["children"] as $key2 => $child) {
                $this->addNavigationLink($child, $key2, $link);
            }
        }

        return true;
    }



    /**
     * @return bool
     */
    public function getDefaultListParent()
    {

        $entity = $this->navigationLinkContext->getById(999);
        if (!isset($entity) || empty($entity)) {
            return false;
        }

        return $entity;
    }

    /**
     * @param $uid
     * @return mixed
     */
    public function getNavigationLinkByUid($uid){

        if(empty($this->navigationLinkContext)){
            $this->navigationLinkContext = $this->container->get("navigation_link_context");
        }

        return $this->navigationLinkContext->getOneBy(Array("uid" => $uid));
    }

    /**
     * @param $entity
     * @return bool
     */
    public function delete($entity)
    {
        if(empty($this->syncManager)){
            $this->syncManager = $this->container->get("sync_manager");
        }

        try {
            $row = $this->syncManager->getEntityRecordById("navigation_link",$entity->getId());
            $this->syncManager->deleteEntityRecord("navigation_link",$row);

            $this->navigationLinkContext->delete($entity);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        if(empty($this->cacheManager)){
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $this->cacheManager->invalidateCacheByTag("navigation_link");

        return true;
    }

    /**
     * @param Entity\NavigationLink $navigationLink
     * @return array
     */
    public function navigationLinkToArray(Entity\NavigationLink $navigationLink)
    {

        $ret = array();

        $ret["id"] = $navigationLink->getId();
        $ret["url"] = $navigationLink->getUrl();
        $ret["uid"] = null;
        if (!empty($navigationLink->getPage())) {
            $ret["page"] = $navigationLink->getPage()->getId();
            $ret["uid"] = $navigationLink->getPage()->getUid();
        }
        $ret["icon"] = $navigationLink->getImage();
        $ret["cssClass"] = $navigationLink->getCssClass();
        $ret["target"] = $navigationLink->getTarget();
        $ret["show"] = $navigationLink->getShow();
        $ret["text"] = $navigationLink->getDisplayName();
        $ret["order"] = $navigationLink->getOrder();
        $ret["isParent"] = $navigationLink->getIsParent();
        $ret["displayName"] = $navigationLink->getDisplayName();

        $children = array();
        if (!empty($navigationLink->getChildLinks()) && count($navigationLink->getChildLinks()) > 0) {
            foreach ($navigationLink->getChildLinks() as $key => $childLink) {
                $children[] = $this->navigationLinkToArray($childLink);
            }

            usort($children, array($this,'cmp'));

            $ret["children"] = $children;
        }

        return $ret;
    }

    public function cmp($a, $b)
    {
        return $a['order'] <=> $b['order'];
    }

    /**
     * @return array
     */
    public function getNavigationJson($avoidAdmin = true)
    {

        $links = $this->navigationLinkContext->getNavigationParents();

        $ret = array();

        /** @var Entity\NavigationLink $link */
        foreach ($links as $link) {
            if ($avoidAdmin && $link->getId() == 8) {
                continue;
            }


            if(!empty($link->getPage()) && $link->getPage()->getType() == "form"){
                continue;
            }

            $linkTmp = $this->navigationLinkToArray($link);

            $children = array();
            if (!empty($link->getChildLinks()) && count($link->getChildLinks()) > 0) {
                foreach ($link->getChildLinks() as $key => $childLink) {
                    if(!empty($childLink->getPage()) && $childLink->getPage()->getType() == "form"){
                        continue;
                    }
                    $children[] = $this->navigationLinkToArray($childLink);
                }

                usort($children, array($this,'cmp'));

                $linkTmp["children"] = $children;
            }

            $ret[] = $linkTmp;
        }

        $ret = json_encode($ret);

        return $ret;
    }
}
