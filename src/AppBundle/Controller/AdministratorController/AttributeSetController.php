<?php

namespace AppBundle\Controller\AdministratorController;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityAttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\AttributeGroup;
use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\AdministrationManager;
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

class AttributeSetController extends AbstractController
{
    /**@var AttributeGroupContext $attributeGroupContext */
    protected $attributeGroupContext;
    /**@var EntityAttributeContext $entityAttributeContext */
    protected $entityAttributeContext;
    /**@var AttributeContext $attributeContext */
    protected $attributeContext;
    /**@var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;

    /**@var AdministrationManager $administrationManager */
    protected $administrationManager;
    /**@var PageManager $pageManager */
    protected $pageManager;

    protected $managedEntityType;


    protected function initialize()
    {
        parent::initialize();
        $this->authenticateAdministrator();

        $this->attributeGroupContext = $this->getContainer()->get('attribute_set_context');
        $this->entityAttributeContext = $this->getContainer()->get('entity_attribute_context');
        $this->entityTypeContext = $this->getContainer()->get('entity_type_context');
        $this->attributeContext = $this->getContainer()->get('attribute_context');
        $this->attributeSetContext = $this->getContainer()->get('attribute_set_context');
        $this->administrationManager = $this->getContainer()->get("administration_manager");
        $this->pageManager = $this->getContainer()->get("page_manager");

        $this->managedEntityType = "attribute_set";
    }

    /**
     * @Route("administrator/attribute_set", name="attribute_set_index")
     */
    public function indexAction(Request $request)
    {
        $this->initialize();

        $this->authenticateAdministrator();

        return new Response($this->renderView('AppBundle:Admin/AttributeSet:index.html.twig', array('managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("administrator/attribute_set/list", name="get_attribute_set_list")
     * @Method("POST")
     */
    public function GetList(Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();

        $pager = new DataTablePager();
        $pager->setFromPost($_POST);

        $entities = $this->attributeGroupContext->getItemsWithPaging($pager);

        $html = $this->renderView('AppBundle:Admin/AttributeSet:list.html.twig', array('entities' => $entities, 'managed_entity_type' => $this->managedEntityType));
        $num_of_items = $this->attributeGroupContext->countAllItems();

        $ret = array();
        $ret["draw"] = $pager->getDraw();
        $ret["recordsTotal"] = $num_of_items;
        $ret["recordsFiltered"] = $num_of_items;
        $ret["data"] = array();
        $ret["html"] = $html;

        return new JsonResponse($ret);
    }

    /**
     * @Route("administrator/attribute_set/update/{id}", defaults={"id"=null}, name="attribute_set_update_form")
     */
    public function updateAction($id, Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();

        $entityTypes = $this->entityTypeContext->getAll();

        /**
         * Create
         */
        if (empty($id)) {
            return new Response($this->renderView(
                'AppBundle:Admin/AttributeSet:form.html.twig',
                array(
                    'attribute_set' => null,
                    'layouts'=> null,
                    'entity_types' => $entityTypes,
                    'managed_entity_type' => $this->managedEntityType
                )
            ));
        }
        /**
         * Update
         */
        else {
            $attribute_set = $this->attributeSetContext->getById($id);

            if (!isset($attribute_set) || empty($attribute_set)) {
                return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
            }

            return new Response($this->renderView(
                'AppBundle:Admin/AttributeSet:form.html.twig',
                array(
                    'attribute_set' => $attribute_set,
                    'entity_types' => $entityTypes,
                    'managed_entity_type' => $this->managedEntityType
                )
            ));
        }

        return false;
    }

    /**
     * @Route("administrator/attribute_set/view/{id}", name="attribute_set_view_form")
     */
    public function viewAction($id, Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();

        $id = $request->get('id');

        $attribute_set = $this->attributeSetContext->getById($id);

        if (!isset($attribute_set) || empty($attribute_set)) {
            return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
        }

        $entityTypes = $this->entityTypeContext->getAll();

        return new Response($this->renderView(
            'AppBundle:Admin/AttributeSet:form.html.twig',
            array(
                'attribute_set' => $attribute_set,
                'entity_types' => $entityTypes,
                'managed_entity_type' => $this->managedEntityType
            )
        ));
    }

    /**
     * @Route("administrator/attribute_set/delete", name="attribute_set_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $entity = $this->attributeSetContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Attribute group does not exist'));
        }

        if (!$this->administrationManager->deleteAttributeSet($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'Delete attribute group', 'message' => 'Attribute group has been deleted'));
    }

    /**
     * @Route("administrator/attribute_set/save", name="attribute_set_save")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();

        $p = $_POST;

        if (!isset($p["attributeSetName"]) || empty($p["attributeSetName"])) {
            return new JsonResponse(array('error' => true, 'message' => 'attributeSetName is not correct'));
        }

        if (!isset($p["isCustom"])) {
            $p["isCustom"] = 0;
        }

        /**
         * INSERT
         */
        if (!isset($p["id"]) || empty($p["id"])) {
            if (!isset($p["entityType"]) || empty($p["entityType"])) {
                return new JsonResponse(array('error' => true, 'message' => 'entityType is not correct'));
            }

            $entityType = $this->entityTypeContext->getById($p["entityType"]);
            if (!isset($entityType) || empty($entityType)) {
                return new JsonResponse(array('error' => true, 'message' => 'Attribute set doesnt exist'));
            }

            if (!isset($p["attributeSetCode"]) || empty($p["attributeSetCode"])) {
                return new JsonResponse(array('error' => true, 'message' => 'attributeSetCode is not correct'));
            }

            /**
             * Check if need to generate pages
             */
            if (!isset($p["generatePages"])) {
                $p["generatePages"] = 0;
            }

            $check = $this->attributeSetContext->getBy(array("attributeSetCode" => $p["attributeSetCode"]));
            if (!empty($check)) {
                return new JsonResponse(array('error' => true, 'message' => 'Attribute set with this code already exists'));
            }

            $attributeSet = new AttributeSet();
            $attributeSet->setEntityType($entityType);
            $attributeSet->setAttributeSetCode($p["attributeSetCode"]);
            $attributeSet->setAttributeSetName($p["attributeSetName"]);
            $attributeSet->setIsCustom($p["isCustom"]);

            $attributeSet = $this->administrationManager->saveAttributeSet($attributeSet);

            if (empty($attributeSet)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            $attributeGroup = new AttributeGroup();
            $attributeGroup->setAttributeGroupName($p["attributeSetName"]." details");
            $attributeGroup->setAttributeSet($attributeSet);
            $attributeGroup->setIsCustom($p["isCustom"]);

            $attributeGroup = $this->administrationManager->saveAttributeGroup($attributeGroup, $p["generatePages"]);

            if (empty($attributeGroup)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            if ($p["generatePages"]) {
                $this->pageManager->generateAttributeSetPages($attributeSet);
            }

            if (!isset($p["privilege"])) {
                $p["privilege"] = array();
            }
            $privilegeManager = $this->getContainer()->get('privilege_manager');
            $privilegeManager->savePrivilegesForEntity($p["privilege"], $attributeSet->getUid());

            return new JsonResponse(array('error' => false, 'title' => 'Insert new attribute set', 'message' => 'Attribute set has been added', 'entity' =>  array('id' => $attributeSet->getId())));
        }
        /**
         * UPDATE
         */
        else {
            if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
                return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
            }

            /** @var AttributeSet $attributeSet */
            $attributeSet = $this->attributeSetContext->getById($p["id"]);
            if (!isset($attributeSet) || empty($attributeSet)) {
                return new JsonResponse(array('error' => true, 'message' => 'Attribute set does not exist'));
            }

            $attributeSet->setAttributeSetName($p["attributeSetName"]);
            $attributeSet->setIsCustom($p["isCustom"]);

            $attributeSet = $this->administrationManager->saveAttributeSet($attributeSet);

            if (empty($attributeSet)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            if (!isset($p["privilege"])) {
                $p["privilege"] = array();
            }
            /** @var PrivilegeManager $privilegeManager */
            $privilegeManager = $this->getContainer()->get('privilege_manager');
            $privilegeManager->savePrivilegesForEntity($p["privilege"], $attributeSet->getUid());

            return new JsonResponse(array('error' => false, 'title' => 'Update attribute set', 'message' => 'Attribute set has been updated', 'entity' =>  array('id' => $attributeSet->getId())));
        }

        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
    }
}
