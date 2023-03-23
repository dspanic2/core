<?php

namespace WikiBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\PageManager;
use WikiBusinessBundle\Entity\WikiPageEntity;
use WikiBusinessBundle\Entity\WikiTopicEntity;

class WikiManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var BlockManager $blockManager */
    protected $blockManager;
    /** @var PageManager $pageManager */
    protected $pageManager;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->blockManager = $this->getContainer()->get("block_manager");
        $this->pageManager = $this->getContainer()->get("page_manager");
    }

    /**
     * @return mixed
     */
    public function getAllTopics()
    {
        $etWikiTopic = $this->entityManager->getEntityTypeByCode("wiki_topic");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($etWikiTopic, $compositeFilters);
    }

    /**
     * @param $pageId
     * @return mixed
     */
    public function getTagsForPage($pageId)
    {
        $etWikiPageTag = $this->entityManager->getEntityTypeByCode("wiki_page_tag");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("wikiPageId", "eq", $pageId));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($etWikiPageTag, $compositeFilters);
    }

    /**
     * Generate a path for a topic or a page
     * @param $pageId
     * @param EntityType $etPage
     * @return array
     */
    public function getWikiPath($pageId, EntityType $etPage)
    {
        $pathArray = array();

        if (!empty($pageId) && !empty($etPage)) {

            /** @var WikiPageEntity $entity */
            $entity = $this->entityManager->getEntityByEntityTypeAndId($etPage, $pageId);
            $topLevelEntity = null;

            if ($etPage->getEntityTypeCode() == "wiki_page") {
                /** @var WikiPageEntity $parent */
                $parent = $entity;
                while ($parent) {
                    array_unshift($pathArray, $parent);
                    $parent = $parent->getParentPage();
                }
                $topLevelEntity = $entity->getParentTopic();
            } else if ($etPage->getEntityTypeCode() == "wiki_topic") {
                $topLevelEntity = $entity;
            }

            if ($topLevelEntity) {
                array_unshift($pathArray, $topLevelEntity);
            }
        }

        return $pathArray;
    }

    /**
     * Get all descendants of a page
     *
     * @param $page
     * @return array
     */
    public function getDescendantsTree(WikiPageEntity $page)
    {
        $ret = Array();

        if (!empty($page->getChildPages())) {
            $pages = $page->getChildPages();
            /** @var WikiPageEntity $p */
            foreach ($pages as $p) {
                if ($p->getEntityStateId() == 1) {
                    $ret[] = $p;
                }

                $retTmp = $this->getDescendantsTree($p);
                if (!empty($retTmp)) {
                    $ret = array_merge($ret, $retTmp);
                }
            }
        }

        return $ret;
    }

    /**
     * Coverts an array of pages into a btree suitable for bootstrap-treeview.js plugin
     *
     * @param $pages
     * @param int $parentId
     * @return array
     */
    public function getTreeViewPages($pages, $parentId = 0) {
        $tree = array();
        $code = null;

        /** @var WikiPageEntity $page */
        foreach ($pages as $page) {
            if ($code == null) {
                $code = $page->getEntityType()->getEntityTypeCode();
            }

            if ($page->getParentPageId() == $parentId) {
                $child = $this->getTreeViewPages($pages, $page->getId());
                if ($page->getEntityStateId() == 1) { // filter out deleted pages
                    $branch = array(
                        "text" => $page->getName(),
                        "nodes" => $child ? $child : null,
                        "href" => "/page/" . $code . "/form/" . $page->getId(),
                        "selectable" => false,
                        "state" => array(
                            "expanded" => true
                        )
                    );
                    $tree[] = $branch;
                }
            }
        }

        return $tree;
    }

    /**
     * Get all topics and pages and make a btree suitable for bootstrap-treeview.js plugin
     *
     * @return array
     */
    public function getTreeViewData()
    {
        $treeViewData = array();
        $topics = $this->getAllTopics();
        $code = null;

        /** @var WikiTopicEntity $topic */
        foreach ($topics as $topic) {
            if ($code == null) {
                $code = $topic->getEntityType()->getEntityTypeCode();
            }

            // get child pages for each topic
            $nodes = $this->getTreeViewPages($topic->getChildPages());

            // set node for this topic and attach child page nodes
            $treeViewData[] = array(
                "text" => $topic->getName(),
                "nodes" => $nodes,
                "href" => "/page/" . $code . "/form/" . $topic->getId(),
                "selectable" => false,
                "state" => array(
                    "expanded" => true
                )
            );
        }

        return $treeViewData;
    }

    /**
     * @param $term
     * @return array
     */
    public function searchWiki($term)
    {
        $results = Array();

        foreach (Array("wiki_topic", "wiki_page") as $entityTypeCode) {

            $entityType = $this->entityManager->getEntityTypeByCode($entityTypeCode);

            $andFilter = new CompositeFilter();
            $andFilter->setConnector("and");
            $andFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

            $orFilter = new CompositeFilter();
            $orFilter->setConnector("or");
            $orFilter->addFilter(new SearchFilter("name", "bw", $term));
            $orFilter->addFilter(new SearchFilter("content", "bw", $term));
            $orFilter->addFilter(new SearchFilter("description", "bw", $term));

            $compositeFilters = new CompositeFilterCollection();
            $compositeFilters->addCompositeFilter($andFilter);
            $compositeFilters->addCompositeFilter($orFilter);

            $results[] = $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);
        }

        return $results;
    }
}