<?php

namespace AppBundle\Controller\AdministratorController;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\PageBlockContext;
use AppBundle\Context\PageContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\Page;
use AppBundle\Entity\PageBlock;
use AppBundle\Entity\RoleEntity;
use AppBundle\Helpers\ArrayHelper;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\ListViewManager;
use AppBundle\Managers\PageManager;
use AppBundle\Managers\PrivilegeManager;
use Doctrine\Tests\ORM\Functional\Ticket\Role;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Templating\TemplatingExtension;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;

class PageController extends AbstractController
{
    /**@var PageContext $pageContext */
    protected $pageContext;
    /**@var PageBlockContext $pageBlockContext */
    protected $pageBlockContext;
    /**@var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;

    /**@var AdministrationManager $administrationManager */
    protected $administrationManager;
    /**@var blockManager $blockManager */
    protected $blockManager;
    /**@var PageManager $pageManager */
    protected $pageManager;

    /** @var PrivilegeManager $privilegeManager */
    protected $privilegeManager;
    /** @var ListViewManager $listViewManager */
    protected $listViewManager;

    protected $managedEntityType;
    protected $pageTypes;

    protected function initialize()
    {
        parent::initialize();
        $this->authenticateAdministrator();

        $this->attributeSetContext = $this->getContainer()->get('attribute_set_context');
        $this->pageBlockContext = $this->getContainer()->get("page_block_context");
        $this->pageContext = $this->getContainer()->get("page_context");
        $this->administrationManager = $this->getContainer()->get("administration_manager");
        $this->pageManager = $this->getContainer()->get("page_manager");
        $this->blockManager = $this->getContainer()->get("block_manager");

        $this->managedEntityType = "page";

        $this->pageTypes = array(
            "form" => array(
                "attribute-set" => true,
                "content" => true,
                "is_available_in_block" => 0,
                "is_available_in_page" => 1
            ),
            "view" => array(
                "attribute-set" => true,
                "content" => true,
                "is_available_in_block" => 0,
                "is_available_in_page" => 1
            ),
            /*"view_form" => Array(
                "attribute-set" => true,
                "content" => true,
                "is_available_in_block" => 0,
                "is_available_in_page" => 1
            ),
            "attribute_group" => Array(
                "attribute-set" => true,
                "related-id" => true,
                "relatedType" => "attribute_group",
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),*/
            "list" => array(
                "attribute-set" => true,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "dashboard" => array(
                "attribute-set" => false,
                "content" => true,
                "is_available_in_block" => 0,
                "is_available_in_page" => 1
            ),
            /*"text_block" => Array(
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),*/
        );
    }

    /**
     * @Route("administrator/page", name="page_index")
     */
    public function indexAction(Request $request)
    {
        $this->initialize();

        return new Response($this->renderView('AppBundle:Admin/Page:index.html.twig', array('managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("administrator/page/list", name="get_page_list")
     * @Method("POST")
     */
    public function GetList(Request $request)
    {
        $this->initialize();

        $pager = new DataTablePager();
        $pager->setFromPost($_POST);
        $entities = $this->pageContext->getItemsWithPaging($pager);

        $html = $this->renderView('AppBundle:Admin/Page:list.html.twig', array('entities' => $entities, 'managed_entity_type' => $this->managedEntityType));
        $num_of_items = $this->pageContext->countAllItems();

        $ret = array();
        $ret["draw"] = $pager->getDraw();
        $ret["recordsTotal"] = $num_of_items;
        $ret["recordsFiltered"] = $num_of_items;
        $ret["data"] = array();
        $ret["html"] = $html;

        return new JsonResponse($ret);
    }

    /**
     * @Route("administrator/page/privilege/{id}", defaults={"id"=null}, name="page_privilege_form")
     */
    public function privilegeUpdateAction($id, Request $request)
    {
        $this->initialize();

        $privilegesOnPage = Array();

        /** @var Page $entity */
        $entity = $this->pageContext->getById($id);

        if(empty($this->privilegeManager)){
            $this->privilegeManager = $this->getContainer()->get("privilege_manager");
        }

        $privilegesList = $this->privilegeManager->getAllPrivileges();
        $actionTypes = $this->privilegeManager->getActionTypes();
        $roles = $this->privilegeManager->getAllRoles(Array("ROLE_ADMIN"));

        $privilegesOnPage["attribute_set"][] = $entity->getAttributeSet()->getUid();
        $privilegesOnPage["page"][] = $entity->getUid();
        $listViewIds = Array();

        $content = json_decode($entity->getContent(),true);
        if(!empty($content)){
            foreach ($content as $c){
                /** @var PageBlock $pageBlock */
                $pageBlock = $this->blockManager->getBlockById($c["id"]);
                $content = $this->blockManager->getPageBlockContentRecursiveFlatArray($pageBlock);

                if(!empty($content)){
                    foreach ($content as $co){
                        $privilegesOnPage["page_block"][] = $co["uid"];
                        if($co["type"] == "list_view" || $co["type"] == "library_view"){
                            $listViewIds[] = $co["related_id"];
                        }
                    }
                }
            }
        }

        if(!empty($listViewIds)){

            if(empty($this->listViewManager)){
                $this->listViewManager = $this->getContainer()->get("list_view_manager");
            }

            foreach ($listViewIds as $listViewId){
                $listView = $this->listViewManager->getListViewById($listViewId);
                if(!empty($listView)){
                    $privilegesOnPage["list_view"][] = $listView->getUid();
                    $privilegesOnPage["attribute_set"][] = $listView->getAttributeSet()->getUid();
                }
            }
        }

        foreach ($privilegesList as $type => $values){
            if(!isset($privilegesOnPage[$type])){
                unset($privilegesList[$type]);
                continue;
            }

            foreach ($values["privileges"] as $uid => $val){
                if(!in_array($uid, $privilegesOnPage[$type])){
                    unset($privilegesList[$type]["privileges"][$uid]);
                }
            }
        }

        $entityPrivileges = array();
        /** @var RoleEntity $role */
        foreach ($roles as $role){
            foreach ($role->getPrivileges() as $privilege) {
                $entityPrivileges[$role->getRoleCode()][$privilege->getActionCode()][$privilege->getActionType()->getId()] = 1;
            }
        }

        return new Response($this->renderView(
            'AppBundle:Admin/Role:privileges_on_page.html.twig',
            array(
                'entity' => $entity,
                'managed_entity_type' => $this->managedEntityType,
                'privileges_list' => $privilegesList,
                'action_types' => $actionTypes,
                'entity_privileges' => $entityPrivileges,
                'roles' => $roles
            )
        ));
    }

    /**
     * @Route("/administrator/privilege/page/save", name="page_privilege_save")
     */
    public function privilegeSaveAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if(!isset($p["privilege_set"]) || empty($p["privilege_set"])){
            return new JsonResponse(array('error' => true, 'message' => 'Privileges is empty'));
        }

        if(empty($this->privilegeManager)){
            $this->privilegeManager = $this->getContainer()->get("privilege_manager");
        }

        foreach ($p["privilege_set"] as $actionCode => $privileges){
            foreach ($privileges as $actionType => $roles){
                $sendArray = Array();
                $sendArray[1] = Array($actionType => 1);
                foreach ($roles as $key => $value){
                    if(intval($value) > 0){
                        $sendArray[$key] = Array($actionType => 1);
                    }
                }
                $this->privilegeManager->savePrivilegesForEntity($sendArray, $actionCode);
            }
        }

        return new JsonResponse(array('error' => false, 'message' => 'Privileges saved', 'entity' => array('id' => $p["id"])));
    }

    /**
     * @Route("administrator/page/form/{id}", defaults={"id"=null}, name="page_update_form")
     */
    public function updateAction($id, Request $request)
    {
        $this->initialize();

        $attributeSets = $this->attributeSetContext->getAll();

        /**
         * Create
         */
        if (empty($id)) {
            return new Response($this->renderView(
                'AppBundle:Admin/Page:form.html.twig',
                array(
                    'entity' => null,
                    'attribute_sets' => $attributeSets,
                    'managed_entity_type' => $this->managedEntityType,
                    'page_types' => $this->pageTypes
                )
            ));
        } /**
         * Update
         */
        else {
            $entity = $this->pageContext->getById($id);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
            }

            return new Response($this->renderView(
                'AppBundle:Admin/Page:form.html.twig',
                array(
                    'entity' => $entity,
                    'attribute_sets' => $attributeSets,
                    'managed_entity_type' => $this->managedEntityType,
                    'page_types' => $this->pageTypes
                )
            ));
        }

        return false;
    }

    /**
     * @Route("administrator/page/view/{id}", name="page_view_form")
     */
    public function viewAction($id, Request $request)
    {
        $this->initialize();

        $entity = $this->pageContext->getById($id);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
        }

        $attributeSets = $this->attributeSetContext->getAll();

        return new Response($this->renderView(
            'AppBundle:Admin/Page:form.html.twig',
            array(
                'entity' => $entity,
                'attribute_sets' => $attributeSets,
                'managed_entity_type' => $this->managedEntityType,
                'page_types' => $this->pageTypes
            )
        ));
    }

    /**
     * @Route("administrator/page/delete", name="page_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $entity = $this->pageContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Page does not exist'));
        }

        /**First delete all related blocks to the page*/
        $relatedBlocks = $this->pageBlockContext->getBlocksByParent($entity->getUid());

        if(empty($this->blockManager)){
            $this->blockManager = $this->getContainer()->get("block_manager");
        }

        foreach ($relatedBlocks as $relatedBlock) {
            $childBlocks = $this->pageBlockContext->getBlocksByParent($relatedBlock->getUid());
            foreach ($childBlocks as $childBlock) {
                $this->blockManager->deleteBlockFromDatabase($childBlock);
            }
            $this->blockManager->deleteBlockFromDatabase($relatedBlock);
        }

        if (!$this->pageManager->delete($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'Delete page', 'message' => 'Page has been deleted'));
    }

    /**
     * @Route("administrator/page/save", name="page_save")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["title"]) || empty($p["title"])) {
            return new JsonResponse(array('error' => true, 'message' => 'title is not correct'));
        }
        if (!isset($p["url"]) || empty($p["url"])) {
            return new JsonResponse(array('error' => true, 'message' => 'url is not correct'));
        }

        if (!isset($p["class"]) || empty($p["class"])) {
            $p["class"] = null;
        }
        if (!isset($p["content"]) || empty($p["content"])) {
            $p["content"] = null;
        }
        if (!isset($p["dataAttributes"]) || empty($p["dataAttributes"])) {
            $p["dataAttributes"] = null;
        }
        if (!isset($p["isCustom"])) {
            $p["isCustom"] = 0;
        }

        /**
         * INSERT
         */
        if (!isset($p["id"]) || empty($p["id"])) {
            if (!isset($p["type"]) || empty($p["type"])) {
                return new JsonResponse(array('error' => true, 'message' => 'type is not correct'));
            }
            if (!isset($p["attributeSet"]) || empty($p["attributeSet"])) {
                return new JsonResponse(array('error' => true, 'message' => 'attributeSet is not correct'));
            }

            $attributeSet = $this->attributeSetContext->getById($p["attributeSet"]);

            $entity = new Page();
            $entity->setTitle($p["title"]);
            $entity->setType($p["type"]);
            $entity->setUrl($p["url"]);
            $entity->setAttributeSet($attributeSet);
            $entity->setEntityType($attributeSet->getEntityType());
            $entity->setBundle($attributeSet->getEntityType()->getBundle());
            $entity->setClass($p["class"]);
            $entity->setDataAttributes($p["dataAttributes"]);
            $entity->setIsCustom($p["isCustom"]);

            $contentBlocks = json_decode($p["content"], true);

            $newContent = array();

            foreach ($contentBlocks as $key => $contentBlock) {

                /** @var PageBlock $pageBlock */
                $pageBlock = $this->pageBlockContext->getById($contentBlock["id"]);
                if (!empty($pageBlock)) {
                    $contentBlock["id"] = $pageBlock->getUid();

                    $block = $this->blockManager->getBlock($pageBlock, null);
                    $blockSettings = $block->GetPageBlockSetingsData();

                    if (isset($blockSettings["show_content"]) && $blockSettings["show_content"] == 1) {
                        $this->blockManager->savePageBlockContent($pageBlock, $contentBlock["children"]);
                    }
                    unset($contentBlock["children"]);

                    $newContent[] = $contentBlock;
                }
            }

            $p["content"] = json_encode($newContent);
            $entity->setContent($p["content"]);

            $entity = $this->pageManager->save($entity);

            if (empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            if (!isset($p["privilege"])) {
                $p["privilege"] = array();
            }

            if(empty($this->privilegeManager)){
                $this->privilegeManager = $this->getContainer()->get("privilege_manager");
            }

            $this->privilegeManager->savePrivilegesForEntity($p["privilege"], $entity->getUid());

            return new JsonResponse(array('error' => false, 'title' => 'Insert new page', 'message' => 'Page has been added', 'entity' => array('id' => $entity->getId())));
        } /**
         * UPDATE
         */
        else {
            if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
                return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
            }

            $entity = $this->pageContext->getById($p["id"]);
            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Attribute group does not exist'));
            }

            $entity->setTitle($p["title"]);
            $entity->setClass($p["class"]);
            $entity->setUrl($p["url"]);
            $entity->setDataAttributes($p["dataAttributes"]);
            $entity->setIsCustom($p["isCustom"]);

            $contentBlocks = json_decode($p["content"], true);

            $newContent = array();

            foreach ($contentBlocks as $key => $contentBlock) {

                /** @var PageBlock $pageBlock */
                $pageBlock = $this->pageBlockContext->getById($contentBlock["id"]);

                if (!empty($pageBlock)) {
                    $contentBlock["id"] = $pageBlock->getUid();

                    $block = $this->blockManager->getBlock($pageBlock, null);
                    $blockSettings = $block->GetPageBlockSetingsData();

                    if (isset($blockSettings["show_content"]) && $blockSettings["show_content"] == 1) {
                        $tmp = json_decode($pageBlock->getContent(), true);
                        if(!empty($tmp)){
                            foreach(array_keys($tmp) as $key) {
                               unset($tmp[$key]["id"]);
                               unset($tmp[$key]["children"]);
                            }
                        }
                        $tmp2 = $contentBlock["children"];
                        foreach(array_keys($tmp2) as $key) {
                           unset($tmp2[$key]["id"]);
                           unset($tmp2[$key]["children"]);
                        }

                        if(!empty($tmp) && !empty($tmp2) && (json_encode($tmp) != json_encode($tmp2))){
                            $pageBlock->setIsCustom(1);
                        }

                        $this->blockManager->savePageBlockContent($pageBlock, $contentBlock["children"]);
                    }
                    unset($contentBlock["children"]);

                    $newContent[] = $contentBlock;

                    if($pageBlock->getIsCustom()){
                        $entity->setIsCustom($pageBlock->getIsCustom());
                    }
                }
            }

            $p["content"] = json_encode($newContent);
            $entity->setContent($p["content"]);

            $entity = $this->pageManager->save($entity);

            if (empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            if (!isset($p["privilege"])) {
                $p["privilege"] = array();
            }

            if(empty($this->privilegeManager)){
                $this->privilegeManager = $this->getContainer()->get("privilege_manager");
            }

            $this->privilegeManager->savePrivilegesForEntity($p["privilege"], $entity->getUid());

            return new JsonResponse(array('error' => false, 'title' => 'Update page', 'message' => 'Page has been updated', 'entity' => array('id' => $entity->getId())));
        }

        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
    }
}
