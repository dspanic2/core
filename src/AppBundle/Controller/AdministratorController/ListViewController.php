<?php

namespace AppBundle\Controller\AdministratorController;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Constants\SearchFilterOperations;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityAttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewAttributeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\AppBundle;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\ListView;
use AppBundle\Entity\ListViewAttribute;
use AppBundle\Entity\Privilege;
use AppBundle\Entity\RoleEntity;
use AppBundle\Factory\FactoryContext;
use AppBundle\Factory\FactoryManager;
use AppBundle\Helpers\QuerybuilderHelper;
use AppBundle\Helpers\UUIDHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ListViewManager;
use AppBundle\Managers\PrivilegeManager;
use Monolog\Logger;
use Doctrine\Common\Util\Inflector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\UserEntity;
use Symfony\Component\Form\Extension\Templating\TemplatingExtension;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;

class ListViewController extends AbstractController
{
    /**@var ListViewContext $listViewContext */
    protected $listViewContext;
    /**@var ListViewAttributeContext $listViewAttributeContext */
    protected $listViewAttributeContext;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /**@var AttributeContext $attributeContext */
    protected $attributeContext;
    /**@var EntityAttributeContext $entityAttributeContext */
    protected $entityAttributeContext;
    /**@var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;

    /**@var ListViewManager $listViewManager */
    protected $listViewManager;

    protected $managedEntityType;
    protected $baseTemplatePath;

    protected function initialize()
    {
        parent::initialize();
        $this->authenticateAdministrator();

        $this->listViewContext = $this->getContainer()->get('list_view_context');
        $this->listViewAttributeContext = $this->getContainer()->get('list_view_attribute_context');
        $this->entityTypeContext = $this->getContainer()->get('entity_type_context');
        $this->attributeContext = $this->getContainer()->get('attribute_context');
        $this->entityAttributeContext = $this->getContainer()->get('entity_attribute_context');
        $this->attributeSetContext = $this->getContainer()->get('attribute_set_context');
        $this->listViewManager = $this->getContainer()->get("list_view_manager");
        $this->managedEntityType = "list_view";
        $this->baseTemplatePath = $this->getContainer()->get('kernel')->locateResource('@AppBundle/Resources/views');
    }

    /**
     * @Route("administrator/list_view", name="list_view_index")
     */
    public function indexAction(Request $request)
    {
        $this->initialize();

        return new Response($this->renderView('AppBundle:Admin/ListView:index.html.twig', array('managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("administrator/list_view/list", name="get_list_view_list")
     * @Method("POST")
     */
    public function GetList(Request $request)
    {
        $this->initialize();

        $pager = new DataTablePager();
        $pager->setFromPost($_POST);

//dump($pager);die;
        $entities = $this->listViewContext->getItemsWithPaging($pager);

        $html = $this->renderView('AppBundle:Admin/ListView:list.html.twig', array('entities' => $entities, 'managed_entity_type' => $this->managedEntityType));
        $num_of_items = $this->listViewContext->countAllItems();

        $ret = Array();
        $ret["draw"] = $pager->getDraw();
        $ret["recordsTotal"] = $num_of_items;
        $ret["recordsFiltered"] = $num_of_items;
        $ret["data"] = array();
        $ret["html"] = $html;

        return new JsonResponse($ret);
    }

    /**
     * @Route("administrator/list_view/update/{id}", defaults={"id"=null}, name="list_view_update_form")
     */
    public function updateAction($id, Request $request)
    {
        $this->initialize();

        $entityTypes = $this->entityTypeContext->getBy(Array(), Array("entityTypeCode" => "asc"));

        /**
         * Create
         */
        if (empty($id)) {

            return new Response($this->renderView('AppBundle:Admin/ListView:form.html.twig',
                array(
                    'entity' => null,
                    'managed_entity_type' => $this->managedEntityType,
                    'entity_types' => $entityTypes,
                    'attributes' => null,
                )
            ));
        } /**
         * Update
         */
        else {
            $entity = $this->listViewContext->getById($id);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
            }

            $attributeSets = $this->attributeSetContext->getBy(array('entityType' => $entity->getEntityType()));
            $attributes = $this->attributeContext->getBy(array('entityType' => $entity->getEntityType()));
            //$attributes = $this->entityAttributeContext->getBy(array('entityType' => $entity->getEntityType()));

            $used_entity_types = Array();
            $used_entity_types[] = $entity->getEntityType()->getEntityTypeCode();
            /*foreach ($attributes as $attribute){
                if(!empty($attribute->getAttribute()->getLookupEntityType()) && !in_array($attribute->getAttribute()->getLookupEntityType()->getEntityTypeCode(),$used_entity_types)){
                    $used_entity_types[] = $attribute->getAttribute()->getLookupEntityType()->getEntityTypeCode();
                    $attributes = array_merge($attributes,$this->entityAttributeContext->getBy(array('entityType' => $attribute->getAttribute()->getLookupEntityType())));
                }
            }*/
            $filters = array(
                array(
                    "id" => "entityStateId",
                    "label" => "Entity State-Id",
                    "type" => "string"
                ),
                array(
                    "id" => "entityType",
                    "label" => "Entity Type-Id",
                    "type" => "string"
                ),
                array(
                    "id" => "attributeSet",
                    "label" => "Attribute Set-Id",
                    "type" => "string"
                ),
                array(
                    "id" => "createdBy",
                    "label" => "Created By",
                    "type" => "string"
                ),
                array(
                    "id" => "modifiedBy",
                    "label" => "Modified By",
                    "type" => "string"
                ),
                array(
                    "id" => "created",
                    "label" => "Created",
                    "type" => "date"
                ),
                array(
                    "id" => "modified",
                    "label" => "Modified",
                    "type" => "date"
                ),
                array(
                    "id" => "locked",
                    "label" => "Locked",
                    "type" => "date"
                ),
                array(
                    "id" => "lockedBy",
                    "label" => "Locked By",
                    "type" => "string"
                )
            );

            foreach ($attributes as $attribute) {

                if (!empty($attribute->getLookupEntityType()) && !in_array($attribute->getLookupEntityType()->getEntityTypeCode(), $used_entity_types)) {
                    $used_entity_types[] = $attribute->getLookupEntityType()->getEntityTypeCode();
                    $lookupAttributes = $this->attributeContext->getBy(array('entityType' => $attribute->getLookupEntityType()));

                    $attributes = array_merge($attributes, $this->attributeContext->getBy(array('entityType' => $attribute->getLookupEntityType())));

                    foreach ($lookupAttributes as $lookupAttribute) {

                        $attributeCode = str_replace("Id", "", Inflector::camelize($attribute->getAttributeCode()));

                        $filters[] = array(
                            'id' => $attributeCode . "." . Inflector::camelize($lookupAttribute->getAttributeCode()),
                            'label' => $attribute->getFrontendLabel() . "-" . $lookupAttribute->getFrontendLabel(),
                            'type' => QuerybuilderHelper::mapAttributeType($lookupAttribute));
                    }
                } else
                    $filters[] = array('id' => Inflector::camelize($attribute->getAttributeCode()), 'label' => $attribute->getFrontendLabel(), 'type' => QuerybuilderHelper::mapAttributeType($attribute));

            }

            $listViewAttributes = $this->listViewAttributeContext->getBy(array('listView' => $entity), array('order' => 'asc'));

            return new Response($this->renderView('AppBundle:Admin/ListView:form.html.twig',
                array(
                    'entity' => $entity,
                    'managed_entity_type' => $this->managedEntityType,
                    'entity_types' => $entityTypes,
                    'attribute_sets' => $attributeSets,
                    'attributes' => $attributes,
                    'list_view_attributes' => $listViewAttributes,
                    'filters' => $filters
                )
            ));
        }

        return false;
    }

    /**
     * @Route("administrator/list_view/view/{id}", name="list_view_view_form")
     */
    public function viewAction($id, Request $request)
    {
        $this->initialize();

        $entity = $this->listViewContext->getById($id);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
        }

        $listViewAttributes = $this->listViewAttributeContext->getBy(array('listView' => $entity), array('order' => 'asc'));
        $entityTypes = $this->entityTypeContext->getAll();
        $attributes = $this->attributeContext->getBy(array('entityType' => $entity->getEntityType()));

        return new Response($this->renderView('AppBundle:Admin/ListView:form.html.twig',
            array(
                'entity' => $entity,
                'managed_entity_type' => $this->managedEntityType,
                'list_view_attributes' => $listViewAttributes,
                'entity_types' => $entityTypes,
                'attributes' => $attributes,
            )
        ));
    }

    /**
     * @Route("administrator/list_view/delete", name="list_view_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $entity = $this->listViewContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'List view does not exist'));
        }

        if (!$this->listViewManager->deleteListView($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'Delete list view', 'message' => 'List view has been deleted'));
    }

    /**
     * @Route("administrator/list_view/duplicate", name="list_view_duplicate")
     * @Method("POST")
     */
    public function duplicateAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $entity = $this->listViewContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'List view does not exist'));
        }

        if (!$this->listViewManager->duplicateListView($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'List view duplicate created', 'message' => 'List view duplicate created'));
    }

    /**
     * @Route("administrator/list_view/save", name="list_view_save")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["displayName"]) || empty($p["displayName"])) {
            return new JsonResponse(array('error' => true, 'message' => 'displayName is not defined'));
        }
        if (!isset($p["defaultSort"]) || empty($p["defaultSort"])) {
            return new JsonResponse(array('error' => true, 'message' => 'defaultSort is not defined'));
        }
        if (!isset($p["defaultSortType"]) || empty($p["defaultSortType"])) {
            return new JsonResponse(array('error' => true, 'message' => 'defaultSortType is not defined'));
        }
        if (!isset($p["showLimit"]) || empty($p["showLimit"])) {
            return new JsonResponse(array('error' => true, 'message' => 'showLimit is not defined'));
        }
        if (!isset($p["attribute"]) || empty($p["attribute"])) {
            return new JsonResponse(array('error' => true, 'message' => 'attribute is not defined'));
        }
        if (!isset($p["main_button"])) {
            return new JsonResponse(array('error' => true, 'message' => 'main_button is not defined'));
        }
        if (!isset($p["dropdown_buttons"])) {
            return new JsonResponse(array('error' => true, 'message' => 'dropdown_buttons is not defined'));
        }
        if (!isset($p["row_actions"])) {
            return new JsonResponse(array('error' => true, 'message' => 'row_actions is not defined'));
        }

        $showFilter = isset($p["showFilter"]) ? true : false;
        $showExport = isset($p["showExport"]) ? true : false;
        $showImport = isset($p["showImport"]) ? true : false;
        $publicView = isset($p["publicView"]) ? true : false;
        $showAdvancedSearch = isset($p["showAdvancedSearch"]) ? true : false;
        $modalAdd = isset($p["modalAdd"]) ? true : false;
        $showLimit = isset($p["showLimit"]) ? $p["showLimit"] : 50;


        if (!isset($p["above_list_actions"])) {
            return new JsonResponse(array('error' => true, 'message' => 'above_list_actions is not defined'));
        }

        /**
         * Check if need to generate pages
         */
        if (!isset($p["generatePages"])) {
            $p["generatePages"] = 0;
        }

        if (!isset($p["isCustom"])) {
            $p["isCustom"] = 0;
        }

        if (!isset($p["inlineEditing"])) {
            $p["inlineEditing"] = 0;
        }

        /**
         * INSERT
         */
        if (!isset($p["id"]) || empty($p["id"])) {

            if (!isset($p["entityType"]) || empty($p["entityType"])) {
                return new JsonResponse(array('error' => true, 'message' => 'entity_type is not defined'));
            }
            if (!isset($p["attributeSet"]) || empty($p["attributeSet"])) {
                return new JsonResponse(array('error' => true, 'message' => 'attribute_set is not defined'));
            }
            if (!isset($p["name"]) || empty($p["name"])) {
                return new JsonResponse(array('error' => true, 'message' => 'name is not defined'));
            }

            $entityType = $this->entityTypeContext->getById($p["entityType"]);
            if (!isset($entityType) || empty($entityType)) {
                return new JsonResponse(array('error' => true, 'message' => 'Entity type does not exist'));
            }

            $attributeSet = $this->attributeSetContext->getById($p["attributeSet"]);
            if (!isset($attributeSet) || empty($attributeSet)) {
                return new JsonResponse(array('error' => true, 'message' => 'Attirubte set does not exist'));
            }

            $listView = new ListView();
            $listView->setName($p["name"]);
            $listView->setDisplayName($p["displayName"]);
            $listView->setDefaultSort($p["defaultSort"]);
            $listView->setDefaultSortType($p["defaultSortType"]);
            $listView->setPublicView($publicView);
            $listView->setShowLimit($showLimit);
            $listView->setShowFilter($showFilter);
            $listView->setShowExport($showExport);
            $listView->setShowImport($showImport);
            $listView->setEntityType($entityType);
            $listView->setAttributeSet($attributeSet);
            $listView->setMainButton($p["main_button"]);
            $listView->setDropdownButtons($p["dropdown_buttons"]);
            $listView->setRowActions($p["row_actions"]);
            $listView->setMassActions($p["mass_actions"]);
            $listView->setAboveListActions($p["above_list_actions"]);
            $listView->setFilter($p["filter"]);
            $listView->setShowAdvancedSearch($showAdvancedSearch);
            $listView->setModalAdd($modalAdd);
            $listView->setIsCustom($p["isCustom"]);
            $listView->setInlineEditing($p["inlineEditing"]);
            $listView->setUid(UUIDHelper::generateUUID());

            $listView = $this->listViewManager->addListView($listView, $p["attribute"], $p["generatePages"]);
            if (empty($listView)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            if (!isset($p["privilege"])) {
                $p["privilege"] = Array();
            }
            /** @var PrivilegeManager $privilegeManager */
            $privilegeManager = $this->getContainer()->get('privilege_manager');
            $privilegeManager->savePrivilegesForEntity($p["privilege"], $listView->getUid());

            return new JsonResponse(array('error' => false, 'title' => 'Insert new list view', 'message' => 'List view has been added', 'entity' => Array('id' => $listView->getId())));
        } /**
         * UPDATE
         */
        else {
            if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
                return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
            }

            /**@var ListView $entity */
            $entity = $this->listViewContext->getById($p["id"]);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'List view does not exist'));
            }

            $entity->setName($p["name"]);
            $entity->setDisplayName($p["displayName"]);
            $entity->setDefaultSort($p["defaultSort"]);
            $entity->setDefaultSortType($p["defaultSortType"]);
            $entity->setShowLimit($showLimit);

            $entity->setShowFilter($showFilter);
            $entity->setShowExport($showExport);
            $entity->setShowImport($showImport);
            $entity->setPublicView($publicView);
            $entity->setMainButton($p["main_button"]);
            $entity->setDropdownButtons($p["dropdown_buttons"]);
            $entity->setRowActions($p["row_actions"]);
            $entity->setMassActions($p["mass_actions"]);
            $entity->setAboveListActions($p["above_list_actions"]);
            $entity->setFilter($p["filter"]);
            $entity->setShowAdvancedSearch($showAdvancedSearch);
            $entity->setModalAdd($modalAdd);
            $entity->setInlineEditing($p["inlineEditing"]);
            $entity->setIsCustom($p["isCustom"]);

            if (!isset($p["privilege"])) {
                $p["privilege"] = Array();
            }
            /** @var PrivilegeManager $privilegeManager */
            $privilegeManager = $this->getContainer()->get('privilege_manager');
            $privilegeManager->savePrivilegesForEntity($p["privilege"], $entity->getUid());

            $eListViewAttributes = $this->listViewManager->getListViewAttributes($entity);
          /**
           * Iz nepoznatog razloga ovaj collection se punio dinamicki kako su se nize dodavali novi atributi te su se odmah i obrisali
           */
            //$eListViewAttributes = $entity->getListViewAttributes();

            $order = 1;
            foreach ($p["attribute"] as $attributeId => $details) {

                if (!isset($details["enable_inline_editing"])) {
                    $details["enable_inline_editing"] = 0;
                }
                if (!isset($details["display"])) {
                    $details["display"] = 0;
                }


                /**
                 * Update list view attribute
                 */
                if (isset($details["list_view_id"]) && !empty($details["list_view_id"])) {
                    foreach ($eListViewAttributes as $key => $eListViewAttribute) {
                        if ($eListViewAttribute->getId() == $details["list_view_id"]) {
                            unset($eListViewAttributes[$key]);
                        }
                    }
                    /** @var ListViewAttribute $listViewAttribute */
                    $listViewAttribute = $this->listViewAttributeContext->getById($details["list_view_id"]);

                    if (!isset($listViewAttribute) || empty($listViewAttribute)) {
                        return new JsonResponse(array('error' => true, 'message' => 'List view attribute does not exist'));
                    }

                    if (empty($details["field"])) {
                        $details["field"] = $this->listViewManager->getListViewAttributeField($attributeId, $entity);
                    }

                    $listViewAttribute->setOrder($order);
                    $listViewAttribute->setDisplay($details["display"]);
                    $listViewAttribute->setField($details["field"]);
                    $listViewAttribute->setLabel($details["label"]);
                    $listViewAttribute->setColumnWidth($details["column_width"]);
                    $listViewAttribute->setEnableInlineEditing($details["enable_inline_editing"]);

                    if (!$this->listViewManager->saveListViewAttribute($listViewAttribute)) {
                        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
                    }
                } /**
                 * Insert list view attribute
                 */
                else {

                    $attribute = $this->attributeContext->getById($attributeId);
                    if (!isset($attribute) || empty($attribute)) {
                        return new JsonResponse(array('error' => true, 'message' => 'Attribute does not exist'));
                    }

                    $listViewAttribute = new ListViewAttribute();

                    if (empty($details["field"])) {
                        $details["field"] = $this->listViewManager->getListViewAttributeField($attributeId, $entity);
                    }

                    $listViewAttribute->setOrder($order);
                    $listViewAttribute->setAttribute($attribute);
                    $listViewAttribute->setListView($entity);
                    $listViewAttribute->setDisplay($details["display"]);
                    $listViewAttribute->setField($details["field"]);
                    $listViewAttribute->setLabel($details["label"]);
                    $listViewAttribute->setColumnWidth($details["column_width"]);
                    $listViewAttribute->setEnableInlineEditing($details["enable_inline_editing"]);

                    if (!$this->listViewManager->saveListViewAttribute($listViewAttribute)) {
                        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
                    }
                }
                $order++;
            }

            if (count($eListViewAttributes) > 0) {
                foreach ($eListViewAttributes as $eListViewAttribute) {

                    if (!$this->listViewManager->deleteListViewAttribute($eListViewAttribute)) {
                        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
                    }
                }
            }

            if (!$this->listViewManager->saveListView($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            return new JsonResponse(array('error' => false, 'title' => 'Update list view', 'message' => 'List view has been updated', 'entity' => Array('id' => $entity->getId())));
        }

        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
    }

    /**
     * @Route("administrator/list_view/get_entity_attributes", name="list_view_get_entity_attributes")
     * @Method("POST")
     */
    public function getEntityAttributesAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $attribute_set = $this->attributeSetContext->getById($p["id"]);

        if (!isset($attribute_set) || empty($attribute_set)) {
            return new JsonResponse(array('error' => true, 'message' => 'Attribute set does not exist'));
        }

        $attributes = $this->attributeContext->getBy(array('entityType' => $attribute_set->getEntityType()));

        $used_entity_types = Array();
        $filters = Array();

        $used_entity_types[] = $attribute_set->getEntityType()->getEntityTypeCode();
        /**@var Attribute $attribute */
        foreach ($attributes as $attribute) {

            if (!empty($attribute->getLookupEntityType()) && !in_array($attribute->getLookupEntityType()->getEntityTypeCode(), $used_entity_types)) {
                $used_entity_types[] = $attribute->getLookupEntityType()->getEntityTypeCode();
                $lookupAttributes = $this->attributeContext->getBy(array('entityType' => $attribute->getLookupEntityType()));

                $attributes = array_merge($attributes, $this->attributeContext->getBy(array('entityType' => $attribute->getLookupEntityType())));

                /**@var Attribute $lookupAttribute */
                foreach ($lookupAttributes as $lookupAttribute) {
                    $attributeCode = str_replace("Id", "", Inflector::camelize($attribute->getAttributeCode()));

                    $filters[] = array(
                        'id' => $attributeCode . "." . Inflector::camelize($lookupAttribute->getAttributeCode()),
                        'label' => $attribute->getFrontendLabel() . "-" . $lookupAttribute->getFrontendLabel(),
                        'type' => QuerybuilderHelper::mapAttributeType($lookupAttribute));
                }
            } else {
                $filters[] = array('id' => Inflector::camelize($attribute->getAttributeCode()), 'label' => $attribute->getFrontendLabel(), 'type' => QuerybuilderHelper::mapAttributeType($attribute));
            }

        }

        $html = "";

        foreach ($attributes as $entity) {
            $html .= $this->renderView("AppBundle:Admin/ListView:available_attribute.html.twig", Array('attribute' => $entity));
        }

        return new JsonResponse(array('error' => false, 'html' => $html, 'filters' => $filters));
    }

    /**
     * @Route("administrator/list_view/get_attribute_sets", name="list_view_get_attribute_sets")
     * @Method("POST")
     */
    public function getAttributeSetsAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $entityType = $this->entityTypeContext->getById($p["id"]);

        if (!isset($entityType) || empty($entityType)) {
            return new JsonResponse(array('error' => true, 'message' => 'Entity type does not exist'));
        }

        $attributeSets = $this->attributeSetContext->getBy(array('entityType' => $entityType));

        $html = "";
        $data = Array();

        foreach ($attributeSets as $key => $attributeSet) {
            $data[$key]["value"] = $attributeSet->getId();
            $data[$key]["text"] = $attributeSet->getAttributeSetName();
        }

        $html .= $this->renderView("AppBundle:Admin:select_values.html.twig", Array('data' => $data));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("administrator/list_view/get_attribute", name="list_view_get_attribute")
     * @Method("POST")
     */
    public function getAttributeAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $entity = $this->attributeContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Attribute does not exist'));
        }

        $attribute["id"] = $entity->getId();
        $attribute["frontendLabel"] = $entity->getFrontendLabel();
        $attribute["label"] = $entity->getFrontendLabel();
        $attribute["entityType"] = $entity->getEntityType();

        $html = $this->renderView("AppBundle:Admin/ListView:list_attribute.html.twig", Array('attribute' => Array('attribute' => $attribute)));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }


    /**
     * @Route("administrator/list_view/querybuilder/build", name="build_from_querybuilder")
     * @Method("POST")
     */
    public function buildFromQueryBuilder(Request $request)
    {
        $this->initialize();
        $p = $_POST;

        if (!isset($p["query"])) {
            return new JsonResponse(array('error' => true, 'message' => 'No query built'));
        }

        $query = $p["query"];

        $compositeFilters = [];
        $compositeFilters[] = array("connector" => strtolower($query["condition"]));

        foreach ($query["rules"] as $rule) {

            if (isset($rule["condition"])) {
                QuerybuilderHelper::buildCompositeFilter($rule, $compositeFilters[0]);
            } else
                $compositeFilters[0]["filters"][] = array("field" => $rule["id"], "operation" => QuerybuilderHelper::mapRuleToSearchOperation($rule["operator"]), "value" => $rule["value"]);
        }

        return new JsonResponse(array('error' => false, 'composite_filter' => $compositeFilters));
    }


}
