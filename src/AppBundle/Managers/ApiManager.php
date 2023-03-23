<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Abstracts\AbstractField;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\ListViewContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\AttributeGroup;
use AppBundle\Entity\EntityAttribute;
use AppBundle\Entity\ListView;
use AppBundle\Entity\UserEntity;

class ApiManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var ListViewManager $listViewManager */
    protected $listViewManager;
    /** @var AttributeGroupContext $attributeGroupContext */
    protected $attributeGroupContext;
    /** @var ListViewContext $listViewContext */
    protected $listViewContext;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;

    protected $translator;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
        $this->helperManager = $this->container->get("helper_manager");
        $this->listViewManager = $this->container->get("list_view_manager");
        $this->attributeGroupContext = $this->container->get("attribute_group_context");
        $this->listViewContext = $this->container->get("list_view_context");
        $this->translator = $this->container->get("translator");
    }

    /**
     * @param $post
     * @param $start
     * @param $length
     * @return array
     */
    public function getListViewEntities($post, $start, $length)
    {
        $ret = Array();

        /** @var ListView $listView */
        $listView = $this->listViewContext->getById($post["id"]);
        if (!empty($listView)) {

            $pager = new DataTablePager();
            $pager->setStart($start);
            $pager->setLenght($length);

            if (isset($post["data"])) {
                $pager->setFromPost($post);
            }

            $entitiesArray = Array();

            $entities = $this->listViewManager->getListViewDataModel($listView, $pager);
            if (count($entities) > 0) {
                foreach ($entities as $key => $entity) {
                    $entitiesArray[$key] = $this->entityManager->entityToArray($entity, false);
                }
            }

            $ret = array(
                "name" => $listView->getDisplayName(),
                "entities" => $entitiesArray
            );
        }

        return $ret;
    }

    /**
     * @param $pageBlocks
     * @param $object
     * @return array
     */
    public function getBlocksContentTree($pageBlocks, $object)
    {
        $ret = Array();

        if (isset($object["content"]) && !empty($object["content"])) {
            $content = json_decode($object["content"], true);
            foreach ($content as $key => $c) {
                if (isset($c["id"]) && !empty($c["id"])) {
                    if (isset($pageBlocks[$c["id"]])) {
                        $pageBlock = $pageBlocks[$c["id"]];

                        $childrenArray = $this->getBlocksContentTree($pageBlocks, $pageBlock);
                        if (empty($childrenArray)) {
                            switch ($pageBlock["type"]) {
                                // list_view
                                // library_view
                                case "attribute_group":
                                    if (!empty($pageBlock["related_id"])) {
                                        /** @var AttributeGroup $attributeGroup */
                                        $attributeGroup = $this->attributeGroupContext->getById($pageBlock["related_id"]);
                                        $fieldsArray = Array();
                                        /** @var EntityAttribute $entityAttribute */
                                        foreach ($attributeGroup->getEntityAttributes() as $entityAttribute) {
                                            if (empty($entityAttribute->getAttribute())) {
                                                continue;
                                            }
                                            /** @var AbstractField $field */
                                            $field = $this->container->get($entityAttribute->getAttribute()->getFrontendType() . "_field");
                                            $fieldsArray[] = Array(
                                                "name" => $entityAttribute->getAttribute()->getAttributeCode(),
                                                "type" => $field->getBackendType(),
                                                "hidden" => $entityAttribute->getAttribute()->getFrontendHidden() == 1,
                                                "label" => $this->translator->trans($entityAttribute->getAttribute()->getFrontendLabel())
                                            );
                                        }
                                        $childrenArray = Array(
                                            "name" => $attributeGroup->getAttributeGroupName(),
                                            "fields" => $fieldsArray
                                        );
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }

                        $ret[] = array(
                            "title" => $pageBlock["title"],
                            "type" => $pageBlock["type"],
                            "related_id" => $pageBlock["related_id"],
                            "entity_type" => $pageBlock["entity_type"],
                            "attribute_set" => $pageBlock["attribute_set"],
                            "children" => $childrenArray
                        );
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getNavigationLinksArray()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $ret = Array();

        $q = "SELECT
                id, 
                parent_id, 
                is_parent,
                display_name,
                url, 
                ord,
                page,
                p.uid as page_uid
            FROM navigation_link as n LEFT JOIN page as p ON n.page_id = p.id
            ORDER BY n.parent_id, n.ord;";

        $data = $this->databaseContext->getAll($q);
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d["id"]] = $d;
            }
        }

        return $ret;
    }

    /**
     * @param UserEntity $user
     * @param $navigationLinksArray
     * @param $navigationLinkChildrenArray
     * @return array
     */
    public function getNavigationLinksTree(UserEntity $user, $navigationLinksArray, $navigationLinkChildrenArray)
    {
        $ret = Array();

        foreach ($navigationLinksArray as $navigationLink) {
            $childrenArray = Array();
            if (isset($navigationLinkChildrenArray[$navigationLink["id"]])) {
                $childrenArray = $this->getNavigationLinksTree(
                    $user,
                    $navigationLinkChildrenArray[$navigationLink["id"]],
                    $navigationLinkChildrenArray
                );
            }

            if (!$user->hasPrivilege(5, $navigationLink["page_uid"])) {
                if (empty($childrenArray)) {
                    continue;
                }
            }
            $ret[] = Array(
                "url" => $navigationLink["url"],
                "page" => $navigationLink["page"],
                "label" => $navigationLink["display_name"],
                "children" => $childrenArray
            );
        }

        return $ret;
    }

    /**
     * @param $page
     * @return false
     */
    public function getPageById($page)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT * 
            FROM page
            WHERE id = '{$page}';";

        return $this->databaseContext->getSingleEntity($q);
    }

    /**
     * @return array
     */
    public function getPageBlocksArray()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $ret = Array();

        $q = "SELECT *
            FROM page_block;";

        $data = $this->databaseContext->getAll($q);
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d["uid"]] = $d;
            }
        }

        return $ret;
    }
}
