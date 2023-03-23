<?php

namespace AppBundle\Controller\AdministratorController;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Context\PageBlockContext;
use AppBundle\Context\PageContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\ListView;
use AppBundle\Entity\PageBlock;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\PageManager;
use AppBundle\Managers\PrivilegeManager;
use Monolog\Logger;
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

class BlockController extends AbstractController
{
    /**@var PageBlockContext $pageBlockContext */
    protected $pageBlockContext;
    /**@var PageContext $pageContext */
    protected $pageContext;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /**@var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;
    /**@var ListViewContext $listViewContext */
    protected $listViewContext;
    /**@var AttributeContext $attributeContext */
    protected $attributeContext;

    /**@var AdministrationManager $administrationManager */
    protected $administrationManager;
    /**@var BlockManager $blockManager */
    protected $blockManager;
    /**@var PageManager $pageManager */
    protected $pageManager;
    /** @var PrivilegeManager $privilegeManager */
    protected $privilegeManager;

    protected $managedEntityType;
    protected $blockTypes;

    protected function initialize()
    {
        parent::initialize();
        $this->authenticateAdministrator();

        $this->entityTypeContext = $this->getContainer()->get('entity_type_context');
        $this->attributeSetContext = $this->getContainer()->get('attribute_set_context');
        $this->attributeContext = $this->getContainer()->get('attribute_context');
        $this->listViewContext = $this->getContainer()->get('list_view_context');
        $this->pageBlockContext = $this->getContainer()->get("page_block_context");
        $this->pageContext = $this->getContainer()->get("page_context");
        $this->blockManager = $this->getContainer()->get('block_manager');
        $this->pageManager = $this->getContainer()->get('page_manager');
        $this->administrationManager = $this->getContainer()->get("administration_manager");

        $this->managedEntityType = "page_block";

        //TODO add check if registered service with _block extension implements Block interface
        $this->blockTypes = array();
        $services = $this->container->getServiceIds();

        foreach ($services as $service) {
            if (strpos($service, '_block') !== false && strpos($service, '_front_') === false) {
                $this->blockTypes[str_replace("_block", "", $service)] = array(
                    "attribute-set" => true,
                    "content" => true,
                    "is_available_in_block" => 1,
                    "is_available_in_page" => 1
                );
                ksort($this->blockTypes);
            }
        }
    }

    /**
     * @Route("administrator/block/grid", name="page_block_grid")
     */
    public function adminBlockAction(Request $request)
    {

        $block_id = $request->get('block_id');
        $data = array();
        $data["x"] = $request->get('x');
        $data["y"] = $request->get('y');
        $data["width"] = $request->get('width');
        $data["height"] = $request->get('height');

        $this->initialize();

        $html = $this->blockManager->generateAdminBlockHtml($data, $block_id);

        if (empty($html)) {
            return new Response();
        }

        return new Response($html);
    }

    /**
     * @Route("administrator/block", name="page_block_index")
     */
    public function indexAction(Request $request)
    {
        $this->initialize();

        $this->authenticateAdministrator();

        return new Response($this->renderView('AppBundle:Admin/Block:index.html.twig', array('managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("administrator/block/list", name="get_page_block_list")
     * @Method("POST")
     */
    public function GetList(Request $request)
    {
        $this->initialize();

        $this->authenticateAdministrator();

        $pager = new DataTablePager();
        $pager->setFromPost($_POST);

        $entities = $this->pageBlockContext->getItemsWithPaging($pager);

        $html = $this->renderView('AppBundle:Admin/Block:list.html.twig', array('entities' => $entities, 'managed_entity_type' => $this->managedEntityType));
        $num_of_items = $this->pageBlockContext->countAllItems();

        $ret = array();
        $ret["draw"] = $pager->getDraw();
        $ret["recordsTotal"] = $num_of_items;
        $ret["recordsFiltered"] = $num_of_items;
        $ret["data"] = array();
        $ret["html"] = $html;

        return new JsonResponse($ret);
    }

    /**
     * @Route("administrator/block/form/{id}", defaults={"id"=null}, name="page_block_update_form")
     */
    public function updateAction($id, Request $request)
    {
        $this->initialize();

        $this->authenticateAdministrator();

        $form_type = $request->get('form_type');
        $is_front = $request->get('is_front');
        $parent_id = $request->get('parent_id');
        $parent_type = $request->get('parent_type');

        /**
         * Create
         */
        if (empty($id)) {
            $attributeSets = $this->attributeSetContext->getAll();


            $data = array(
                'entity' => null,
                'attribute_sets' => $attributeSets,
                'managed_entity_type' => $this->managedEntityType,
                'block_types' => $this->blockTypes,
                'parent_id' => $parent_id,
                'parent_type' => $parent_type
            );

            $html_part = $this->renderView("AppBundle:BlockSettings:abstract_base_block.html.twig", $data);

            if ($form_type == "modal") {
                $html = $this->renderView(
                    "AppBundle:Admin/Block:form_modal.html.twig",
                    array(
                        'entity' => null,
                        'html' => $html_part,
                        'managed_entity_type' => $this->managedEntityType,
                        'is_front' => $is_front
                    )
                );

                return new JsonResponse(array('error' => false, 'html' => $html));
            }

            return new Response($this->renderView(
                'AppBundle:Admin/Block:form.html.twig',
                array(
                    'entity' => null,
                    'html' => $html_part,
                    'managed_entity_type' => $this->managedEntityType,
                )
            ));
        }
        /**
         * Update
         */
        else {
            $entity = $this->pageBlockContext->getById($id);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
            }


            $pageBlock = $this->blockManager->getBlockById($entity->getId());
            $block = $this->blockManager->getBlock($pageBlock, null);
            $blockSettings = $block->GetPageBlockSetingsData($pageBlock);

            $html_part = $this->renderView($block->GetPageBlockSetingsTemplate(), $blockSettings);

            if ($form_type == "modal") {
                $html = $this->renderView(
                    "AppBundle:Admin/Block:form_modal.html.twig",
                    array(
                        'entity' => $entity,
                        'html' => $html_part,
                        'managed_entity_type' => $this->managedEntityType,
                        'is_front' => $is_front
                    )
                );

                return new JsonResponse(array('error' => false, 'html' => $html));
            }

            $data = array(
                'entity' => $entity,
                'html' => $html_part,
                'managed_entity_type' => $this->managedEntityType,
            );
            if (isset($blockSettings["show_add_button"])) {
                $data["show_add_button"] = $blockSettings["show_add_button"];
            }
            if (isset($blockSettings["show_content"])) {
                $data["show_content"] = $blockSettings["show_content"];
            }

            return new Response($this->renderView('AppBundle:Admin/Block:form.html.twig', $data));
        }

        return false;
    }

    /**
     * @Route("administrator/block/remove/{id}", name="page_block_remove")
     */
    public function removeAction($id, Request $request)
    {
        $this->initialize();

        $this->authenticateAdministrator();

        /** @var PageBlock $entity */
        $entity = $this->pageBlockContext->getById($id);

        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        $pageBlock = $this->blockManager->getBlockById($entity->getId());
        $block = $this->blockManager->getBlock($pageBlock, null);
        $blockSettings = $block->GetPageBlockSetingsData($pageBlock);

        if (isset($blockSettings["show_content"]) && $blockSettings["show_content"] == 1) {
            if (strpos($entity->getContent(), "width") !== false) {
                return new JsonResponse(array('error' => true, 'message' => 'Block is not empty.'));
            }
        }

        $this->blockManager->delete($entity);

        return new JsonResponse(array('error' => false, 'title' => 'Remove page block ', 'message' => 'Page block has been removed'));
    }

    /**
     * @Route("administrator/block/view/{id}", name="page_block_view_form")
     */
    public function viewAction($id, Request $request)
    {
        $this->initialize();

        $this->authenticateAdministrator();

        $entity = $this->pageBlockContext->getById($id);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
        }

        $attributeSets = $this->attributeSetContext->getAll();
        $listViews = $this->listViewContext->getAll();

        $listViewsOutput = [];
        foreach ($listViews as $listView) {
            $attributes = [
                'listView' => $listView,
                'listAttributes' => [],
            ];
            foreach ($listView->getListViewAttributes() as $attribute) {
                $attributes['listAttributes'][] = $attribute;
            }
            $listViewsOutput[] = $attributes;
        }

        return new Response($this->renderView(
            'AppBundle:Admin/Block:form.html.twig',
            array(
                'entity' => $entity,
                'attribute_sets' => $attributeSets,
                'list_views' => $listViewsOutput,
                'managed_entity_type' => $this->managedEntityType,
                'block_types' => $this->blockTypes
            )
        ));
    }

    /**
     * @Route("administrator/block/delete", name="page_block_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request)
    {
        $this->initialize();

        $this->authenticateAdministrator();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $entity = $this->pageBlockContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Page block does not exist'));
        }

        if (!$this->blockManager->delete($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'Delete page block ', 'message' => 'Page block has been deleted'));
    }

    /**
     * @Route("administrator/block/get", name="page_block_get")
     * @Method("POST")
     */
    public function getAction(Request $request)
    {
        $this->initialize();

        $this->authenticateAdministrator();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        /** @var PageBlock $entity */
        $entity = $this->pageBlockContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Page block does not exist'));
        }

        if ($p["is_front"] == "true") {
            $html = $this->renderView(
                "AppBundle:Block:abstract_base_block.html.twig",
                array(
                    'data' => array('block' => $entity),
                )
            );
        } else {
            $block_id = $p["id"];
            $data = array();
            $data["x"] = 0;
            $data["y"] = 50;
            $data["width"] = 6;
            $data["height"] = 6;

            $html = $this->blockManager->generateAdminBlockHtml($data, $block_id);
        }

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("administrator/block/save", name="page_block_save")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $this->authenticateAdministrator();

        $p = $_POST;

        if (!isset($p["title"]) || empty($p["title"])) {
            return new JsonResponse(array('error' => true, 'message' => 'title is not correct'));
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
        if (!isset($p["relatedId"]) || empty($p["relatedId"])) {
            $p["relatedId"] = null;
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
            if (!isset($p["parent_id"]) || empty($p["parent_id"])) {
                return new JsonResponse(array('error' => true, 'message' => 'Block parent is not defined'));
            }
            if (!isset($p["parent_type"]) || empty($p["parent_type"])) {
                return new JsonResponse(array('error' => true, 'message' => 'Block parent type is not defined'));
            }

            /**
             * Set parent
             */
            if ($p["parent_type"] == "page") {
                $parent = $this->pageContext->getById($p["parent_id"]);
            } else {
                $parent = $this->pageBlockContext->getById($p["parent_id"]);
            }

            $entity = new PageBlock();
            $entity->setTitle($p["title"]);
            $entity->setType($p["type"]);
            $entity->setBundle($parent->getBundle());
            $entity->setParent($parent->getUid());
            $entity->setIsCustom($p["isCustom"]);

            $entity = $this->blockManager->save($entity);

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

            if (empty($parent)) {
                return new JsonResponse(array('error' => true, 'message' => 'Parent cannot be found'));
            }

            $content = $parent->getContent();
            $content = json_decode($content, true);

            $newBlock = array();
            $newBlock["id"] = "{$entity->getUid()}";
            $newBlock["type"] = $p["type"];
            $newBlock["title"] = $p["title"];
            $newBlock["x"] = 0;
            $newBlock["y"] = 100;

            if ($p["type"] == "attribute_group") {
                $newBlock["width"] = 6;
            } else {
                $newBlock["width"] = 12;
            }
            $newBlock["height"] = 2;

            $content[] = $newBlock;
            $content = json_encode($content);

            $parent->setContent($content);
            if($p["isCustom"]){
                $parent->setIsCustom($p["isCustom"]);
            }

            if ($p["parent_type"] == "page") {
                $this->pageManager->save($parent);
            } else {
                $this->blockManager->save($parent);
            }

            return new JsonResponse(array('error' => false, 'title' => 'Insert new block group', 'message' => 'Block has been added', 'entity' =>  array('id' => $entity->getUid()) ));
        }
        /**
         * UPDATE
         */
        else {
            if (!isset($p["id"]) || empty($p["id"])) {
                return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
            }

            $entity = $this->pageBlockContext->getById($p["id"]);


            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Page block does not exist'));
            }

            $pageBlock = $this->blockManager->getBlockById($entity->getId());
            $pageBlock->setIsCustom($p["isCustom"]);

            $block = $this->blockManager->getBlock($pageBlock, null);
            $entity = $block->SavePageBlockSettings($p);

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

            return new JsonResponse(array('error' => false, 'title' => 'Update block', 'message' => 'Block has been updated', 'entity' =>  array('id' => $entity->getId())));
        }

        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
    }


    /**
     * @Route("administrator/block/get_related_id", name="remote_get_related_id")
     * @Method("POST")
     */
    public function getRelatedIdAction(Request $request)
    {
        $this->initialize();

        $this->authenticateAdministrator();

        $p = $_POST;

        $data = array();
        $html = "";


        if (!isset($p["type"]) || empty($p["type"])) {
            return new JsonResponse(array('error' => true, 'message' => 'type is not correct'));
        }


        if ($p["type"] == "attribute_group") {
            if (!isset($p["attributeSet"]) || empty($p["attributeSet"])) {
                return new JsonResponse(array('error' => false, 'html' => $html));
            }
            $entity = $this->attributeSetContext->getById($p["attributeSet"]);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Attribute set does not exist'));
            }

            $attributeGroups = $entity->getAttributeGroups();

            foreach ($attributeGroups as $key => $attributeGroup) {
                $data[$key]["value"] = $attributeGroup->getId();
                $data[$key]["text"] = $attributeGroup->getAttributeGroupName();
            }
        }

        if (isset($this->blockTypes[$p["type"]]) && isset($this->blockTypes[$p["type"]]["relatedType"])) {

            /**
             * If page block
             */
            if ($this->blockTypes[$p["type"]]["relatedType"] == "attribute_group") {
                if (!isset($p["attributeSet"]) || empty($p["attributeSet"])) {
                    return new JsonResponse(array('error' => false, 'html' => $html));
                }
                $entity = $this->attributeSetContext->getById($p["attributeSet"]);

                if (!isset($entity) || empty($entity)) {
                    return new JsonResponse(array('error' => true, 'message' => 'Attribute set does not exist'));
                }

                $attributeGroups = $entity->getAttributeGroups();

                foreach ($attributeGroups as $key => $attributeGroup) {
                    $data[$key]["value"] = $attributeGroup->getId();
                    $data[$key]["text"] = $attributeGroup->getAttributeGroupName();
                }
            } elseif ($this->blockTypes[$p["type"]]["relatedType"] == "list_view") {
                if (!isset($p["attributeSet"]) || empty($p["attributeSet"])) {
                    return new JsonResponse(array('error' => false, 'html' => $html));
                }
                $entity = $this->attributeSetContext->getById($p["attributeSet"]);

                if (!isset($entity) || empty($entity)) {
                    return new JsonResponse(array('error' => true, 'message' => 'Attribute set does not exist'));
                }

                $listViews = $this->listViewContext->getBy(array("attributeSet" => $entity));

                foreach ($listViews as $key => $listView) {
                    $data[$key]["value"] = $listView->getId();
                    $data[$key]["text"] = $listView->getDisplayName();
                }
            } elseif ($this->blockTypes[$p["type"]]["relatedType"] == "attribute") {
                if (!isset($p["attributeSet"]) || empty($p["attributeSet"])) {
                    return new JsonResponse(array('error' => false, 'html' => $html));
                }
                $entity = $this->attributeSetContext->getById($p["attributeSet"]);

                if (!isset($entity) || empty($entity)) {
                    return new JsonResponse(array('error' => true, 'message' => 'Attribute set does not exist'));
                }

                $attributes = $this->attributeContext->getBy(array("frontendType" => "lookup_image"));

                if (empty($attributes)) {
                    return new JsonResponse(array('error' => true, 'message' => 'Parent entity does not have lookup_image attribute'));
                }

                foreach ($attributes as $key => $attribute) {
                    $data[$key]["value"] = $attribute->getId();
                    $data[$key]["text"] = $attribute->getAttributeCode();
                }
            }
        }

        $html = $this->renderView("AppBundle:Admin:select_values.html.twig", array('data' => $data, 'selected_value' => null));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("administrator/block/get_lookup_attributes", name="remote_get_lookup_attributes")
     * @Method("POST")
     */
    public function getLookupAttributesAction(Request $request)
    {
        $this->initialize();

        $this->authenticateAdministrator();

        $p = $_POST;

        $data = array();

        if (!isset($p["return_type"]) || empty($p["return_type"])) {
            $p["return_type"] = "attribute_code";
        }
        if (!isset($p["type"]) || empty($p["type"])) {
            return new JsonResponse(array('error' => true, 'message' => 'type is not correct'));
        }
        if ((!isset($p["attributeSet"]) || empty($p["attributeSet"])) && !isset($p["attributeSetFromListView"])) {
            return new JsonResponse(array('error' => true, 'message' => 'attributeSet is not correct'));
        }
        if (!isset($p["backendTypes"]) || empty($p["backendTypes"])) {
            $p["backendTypes"] = array("lookup");
        }

        if (isset($p["attributeSetFromListView"]) && !empty($p["attributeSetFromListView"]) && $p["attributeSetFromListView"]) {
            if (!isset($p["listView"]) || empty($p["listView"])) {
                return new JsonResponse(array('error' => true, 'message' => 'listView is not correct'));
            } else {
                $listViewContext = $this->getContainer()->get('list_view_context');
                /** @var ListView $listView */
                $listView = $listViewContext->getById($p["listView"]);
                $p["attributeSet"] = $listView->getAttributeSet()->getId();
            }
        }

        if (isset($this->blockTypes[$p["type"]])) {
            $attribute_set = $this->attributeSetContext->getById($p["attributeSet"]);

            if (!isset($attribute_set) || empty($attribute_set)) {
                return new JsonResponse(array('error' => true, 'message' => 'Attribute set does not exist'));
            }

            $getter = EntityHelper::makeGetter($p["return_type"]);

            /** @var Attribute $attribute */
            foreach ($this->attributeContext->getAttributesByEntityType($attribute_set->getEntityType()) as $attribute) {
                if (in_array($attribute->getFrontendType(), $p["backendTypes"])) {
                    $data[] = [
                        "value" => $attribute->$getter(),
                        "text" => $attribute->getFrontendLabel()
                    ];
                }
            }
        }

        $html = $this->renderView("AppBundle:Admin:select_values_default.html.twig", array('default' => array('value' => "", 'label' => "Please select"), 'data' => $data, 'selected_value' => null));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("administrator/block/get_lookup_attributes_by_entity_type", name="get_lookup_attributes_by_entity_type")
     * @Method("POST")
     */
    public function getLookupAttributesByEntityTypeAction(Request $request)
    {
        $this->initialize();

        $this->authenticateAdministrator();

        $p = $_POST;

        $data = array();

        if (!isset($p["return_type"]) || empty($p["return_type"])) {
            $p["return_type"] = "attribute_code";
        }

        if (!isset($p["type"]) || empty($p["type"])) {
            return new JsonResponse(array('error' => true, 'message' => 'type is not correct'));
        }
        if ((!isset($p["entityType"]) || empty($p["entityType"])) && !isset($p["entityTypeFromListView"])) {
            return new JsonResponse(array('error' => true, 'message' => 'entityType is not correct'));
        }
        if (!isset($p["backendTypes"]) || empty($p["backendTypes"])) {
            $p["backendTypes"] = array("lookup");
        }

        if (isset($p["entityTypeFromListView"]) && !empty($p["entityTypeFromListView"]) && $p["entityTypeFromListView"]) {
            if (!isset($p["listView"]) || empty($p["listView"])) {
                return new JsonResponse(array('error' => true, 'message' => 'listView is not correct'));
            } else {
                $listViewContext = $this->getContainer()->get('list_view_context');
                /** @var ListView $listView */
                $listView = $listViewContext->getById($p["listView"]);
                $p["entityType"] = $listView->getEntityType()->getId();
            }
        }

        if (isset($this->blockTypes[$p["type"]])) {

            /** @var EntityType $entity_type */
            $entity_type = $this->entityTypeContext->getById($p["entityType"]);

            if (!isset($entity_type) || empty($entity_type)) {
                return new JsonResponse(array('error' => true, 'message' => 'Entity type does not exist'));
            }

            $getter = EntityHelper::makeGetter($p["return_type"]);

            foreach ($this->attributeContext->getAttributesByEntityType($entity_type) as $attribute) {
                if (in_array($attribute->getBackendType(), $p["backendTypes"])) {
                    $data[] = [
                        "value" => $attribute->$getter(),
                        "text" => $attribute->getFrontendLabel()
                    ];
                }
            }
        }

        $html = $this->renderView("AppBundle:Admin:select_values_default.html.twig", array('default' => array('value' => "", 'label' => "Please select"), 'data' => $data, 'selected_value' => null));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("administrator/block/get_attributes", name="remote_get_attributes")
     * @Method("POST")
     */
    public function getAttributes(Request $request)
    {
        $this->initialize();

        $this->authenticateAdministrator();

        $p = $_POST;

        if (!isset($p["list_view_id"]) || empty($p["list_view_id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'type is not correct'));
        }
        if (!isset($p["attribute_type"]) || empty($p["attribute_type"])) {
            return new JsonResponse(array('error' => true, 'message' => 'attribute_type is not correct'));
        }

        $listView = $this->listViewContext->getListViewById($p["list_view_id"]);
        $listView = $listView[0];

        $attributes = [];
        foreach ($listView->getListViewAttributes() as $attribute) {
            $attributes[] = [
                "value" => $attribute->getAttribute()->getId(),
                "text" => $attribute->getLabel()
            ];
        }

        $defaultLabel = "Select value";
        if (isset($p["default_option"]) && !empty($p["default_option"])) {
            $defaultLabel = $p["default_option"];
        }

        $html = $this->renderView("AppBundle:Admin:select_values_default.html.twig", array('default' => array('value' => "", 'label' => $defaultLabel), 'data' => $attributes, 'selected_value' => null));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }
}
