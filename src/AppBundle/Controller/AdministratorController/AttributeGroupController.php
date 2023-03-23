<?php

namespace AppBundle\Controller\AdministratorController;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityAttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\AttributeGroup;
use AppBundle\Entity\EntityAttribute;
use AppBundle\Factory\FactoryContext;
use AppBundle\Managers\AdministrationManager;
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

class AttributeGroupController extends AbstractController
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
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;

    /**@var AdministrationManager $administrationManager */
    protected $administrationManager;

    protected $managedEntityType;


    protected function initialize()
    {
        parent::initialize();
        $this->authenticateAdministrator();

        $this->attributeGroupContext = $this->getContainer()->get('attribute_group_context');
        $this->entityAttributeContext = $this->getContainer()->get('entity_attribute_context');
        $this->entityTypeContext = $this->getContainer()->get('entity_type_context');
        $this->attributeContext = $this->getContainer()->get('attribute_context');
        $this->attributeSetContext = $this->getContainer()->get('attribute_set_context');
        $this->administrationManager = $this->getContainer()->get("administration_manager");
        $this->databaseContext = $this->getContainer()->get("database_context");

        $this->managedEntityType = "attribute_group";
    }

    /**
     * @Route("administrator/attribute_group", name="attribute_groups_index")
     */
    public function indexAction(Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();

        return new Response($this->renderView('AppBundle:Admin/AttributeGroup:index.html.twig', array('managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("administrator/attribute_group/list", name="get_attribute_group_list")
     * @Method("POST")
     */
    public function GetList(Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();

        $pager = new DataTablePager();
        $pager->setFromPost($_POST);

        $entities = $this->attributeGroupContext->getItemsWithPaging($pager);

        $html = $this->renderView('AppBundle:Admin/AttributeGroup:list.html.twig', array('entities' => $entities, 'managed_entity_type' => $this->managedEntityType));
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
     * @Route("administrator/attribute_group/update/{id}", defaults={"id"=null}, name="attribute_group_update_form")
     */
    public function updateAction($id, Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();
        $q = "SELECT * FROM attribute_set ORDER BY attribute_set_code ASC";
        $attributeSets = $this->databaseContext->getAll($q);

        /**
         * Create
         */
        if (empty($id)) {
            return new Response($this->renderView(
                'AppBundle:Admin/AttributeGroup:form.html.twig',
                array(
                    'entity' => null,
                    'attributes'=> null,
                    'available_attributes'=> null,
                    'attribute_sets' => $attributeSets,
                    'managed_entity_type' => $this->managedEntityType
                )
            ));
        }
        /**
         * Update
         */
        else {
            $entity = $this->attributeGroupContext->getById($id);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
            }

            $attributes = $this->entityAttributeContext->getBy(array('attributeSetId' => $entity->getAttributeSetId(), 'attributeGroup' => $entity->getId()), array('sortOrder' => 'ASC'));

            //$available_attributes = $this->entityAttributeContext->getBy(array('attributeSet' => $entity->getAttributeSet()));
            $available_attributes = $this->attributeContext->getBy(array('entityType' => $entity->getAttributeSet()->getEntityType()), array('attributeCode' => 'ASC'));

            return new Response($this->renderView(
                'AppBundle:Admin/AttributeGroup:form.html.twig',
                array(
                    'entity' => $entity,
                    'attributes'=> $attributes,
                    'available_attributes'=> $available_attributes,
                    'attribute_sets' => $attributeSets,
                    'managed_entity_type' => $this->managedEntityType
                )
            ));
        }

        return false;
    }

    /**
     * @Route("administrator/attribute_group/view/{id}", name="attribute_group_view_form")
     */
    public function viewAction($id, Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();

        $entity = $this->attributeGroupContext->getById($id);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
        }

        $attributes = $this->entityAttributeContext->getBy(array('attributeSetId' => $entity->getAttributeSetId(), 'attributeGroup' => $entity->getId()), array('sortOrder' => 'ASC'));

        $q = "SELECT * FROM attribute_set ORDER BY attribute_set_code ASC";
        $attributeSets = $this->databaseContext->getAll($q);
        $available_attributes = $this->attributeContext->getBy(array('entityType' => $entity->getAttributeSet()->getEntityType()), array('attributeCode' => 'ASC'));
        //$available_attributes = $this->entityAttributeContext->getBy(array('attributeSet' => $entity->getAttributeSet()));

        return new Response($this->renderView(
            'AppBundle:Admin/AttributeGroup:form.html.twig',
            array(
                'entity' => $entity,
                'attributes'=> $attributes,
                'available_attributes'=> $available_attributes,
                'attribute_sets' => $attributeSets,
                'managed_entity_type' => $this->managedEntityType
            )
        ));
    }

    /**
     * @Route("administrator/attribute_group/delete", name="attribute_group_delete")
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

        $entity = $this->attributeGroupContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Attribute group does not exist'));
        }

        if (!$this->administrationManager->deleteAttributeGroup($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'Delete attribute group', 'message' => 'Attribute group has been deleted'));
    }

    /**
     * @Route("administrator/attribute_group/save", name="attribute_group_save")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();
        $this->authenticateAdministrator();

        $p = $_POST;

        if (!isset($p["attributeGroupName"]) || empty($p["attributeGroupName"])) {
            return new JsonResponse(array('error' => true, 'message' => 'attributeGroupName is not correct'));
        }
        if (!isset($p["isCustom"])) {
            $p["isCustom"] = 0;
        }

        /**
         * Check if need to generate block
         */
        if (!isset($p["generateBlock"])) {
            $p["generateBlock"] = 0;
        }

        if(isset($p["attribute"])){
            $p["attribute"] = array_unique($p["attribute"]);
        }

        /**
         * INSERT
         */
        if (!isset($p["id"]) || empty($p["id"])) {
            if (!isset($p["attributeSet"]) || empty($p["attributeSet"])) {
                return new JsonResponse(array('error' => true, 'message' => 'attributeSet is not correct'));
            }

            $attributeSet = $this->attributeSetContext->getById($p["attributeSet"]);
            if (empty($attributeSet)) {
                return new JsonResponse(array('error' => true, 'message' => 'Attribute set doesnt exist'));
            }

            $entityType = $this->entityTypeContext->getById($attributeSet->getEntityType()->getId());
            if (empty($attributeSet)) {
                return new JsonResponse(array('error' => true, 'message' => 'Entity type doesnt exist'));
            }

            $entity = new AttributeGroup();
            $entity->setAttributeGroupName($p["attributeGroupName"]);
            $entity->setAttributeSet($attributeSet);
            $entity->setIsCustom($p["isCustom"]);

            $entity = $this->administrationManager->saveAttributeGroup($entity, $p["generateBlock"]);

            if (empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            if (isset($p["attribute"]) && !empty($p["attribute"])) {
                $order = 1;
                foreach ($p["attribute"] as $attribute_id) {
                    $attribute = $this->attributeContext->getById($attribute_id);
                    if (empty($attribute)) {
                        continue;
                    }

                    $entityAttribute = new EntityAttribute();
                    $entityAttribute->setAttributeSet($attributeSet);
                    $entityAttribute->setAttributeGroup($entity);
                    $entityAttribute->setSortOrder($order);
                    $entityAttribute->setEntityType($entityType);
                    $entityAttribute->setAttribute($attribute);

                    $entityAttribute = $this->administrationManager->saveEntityAttribute($entityAttribute);

                    if (empty($entityAttribute)) {
                        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
                    }

                    $order++;
                }
            }

            return new JsonResponse(array('error' => false, 'title' => 'Insert new attribute group', 'message' => 'Attribute group has been added', 'entity' =>  array('id' => $entity->getId())));
        }
        /**
         * UPDATE
         */
        else {
            if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
                return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
            }

            $entity = $this->attributeGroupContext->getById($p["id"]);
            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Attribute group does not exist'));
            }

            $entity->setAttributeGroupName($p["attributeGroupName"]);
            $entity->setIsCustom($p["isCustom"]);

            $entityAttributes = $this->entityAttributeContext->getBy(array("attributeGroup" => $entity), array("sortOrder" => "asc"));
            $usedAttributes = array();
            foreach ($entityAttributes as $entityAttribute) {
                $usedAttributes[$entityAttribute->getAttribute()->getId()]["order"] = $entityAttribute->getSortOrder();
                $usedAttributes[$entityAttribute->getAttribute()->getId()]["id"] = $entityAttribute->getId();
            }

            $p["attribute"] = array_unique($p["attribute"]);

            $order = 1;
            foreach ($p["attribute"] as $attributeId) {

                /**
                 * Update attribute
                 */
                if (array_key_exists($attributeId, $usedAttributes)) {
                    if ($usedAttributes[$attributeId]["order"] != $order) {
                        $entityAttribute = $this->entityAttributeContext->getById($usedAttributes[$attributeId]["id"]);
                        if (!isset($entityAttribute) || empty($entityAttribute)) {
                            return new JsonResponse(array('error' => true, 'message' => 'Entity attribute does not exist'));
                        }

                        $entityAttribute->setSortOrder($order);

                        $entityAttribute = $this->administrationManager->saveEntityAttribute($entityAttribute);

                        if (empty($entityAttribute)) {
                            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
                        }
                    }
                    unset($usedAttributes[$attributeId]);
                }
                /**
                 * Insert attribute
                 */
                else {
                    $attribute = $this->attributeContext->getById($attributeId);
                    if (!isset($attribute) || empty($attribute)) {
                        return new JsonResponse(array('error' => true, 'message' => 'Attribute does not exist'));
                    }

                    $entityAttribute = new EntityAttribute();
                    $entityAttribute->setAttributeSet($entity->getAttributeSet());
                    $entityAttribute->setAttributeGroup($entity);
                    $entityAttribute->setSortOrder($order);
                    $entityAttribute->setEntityType($entity->getAttributeSet()->getEntityType());
                    $entityAttribute->setAttribute($attribute);

                    $entityAttribute = $this->administrationManager->saveEntityAttribute($entityAttribute);

                    if (empty($entityAttribute)) {
                        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
                    }
                }
                $order++;
            }

            /**
             * Delete remaining attributes
             */
            if (!empty($usedAttributes)) {
                foreach ($usedAttributes as $usedAttribute) {
                    $entityAttribute = $this->entityAttributeContext->getById($usedAttribute["id"]);
                    if (!isset($entityAttribute) || empty($entityAttribute)) {
                        return new JsonResponse(array('error' => true, 'message' => 'Entity attribute does not exist'));
                    }

                    if (!$this->administrationManager->deleteEntityAttribute($entityAttribute)) {
                        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
                    }
                }
            }

            $entity = $this->administrationManager->saveAttributeGroup($entity, $p["generateBlock"]);

            if (empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            return new JsonResponse(array('error' => false, 'title' => 'Update attribute group', 'message' => 'Attribute group has been updated', 'entity' =>  array('id' => $entity->getId())));
        }

        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
    }

    /**
     * @Route("administrator/attribute_group/get_entity_attributes", name="attribute_group_get_entity_attributes")
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

        //$attributes = $this->entityAttributeContext->getBy(array('attributeSet' => $attribute_set));
        $attributes = $this->attributeContext->getBy(array('entityType' => $attribute_set->getEntityType()), array('attributeCode' => 'ASC'));

        $html = "";

        /*foreach ($attributes as $entity){
            $attribute["id"] = $entity->getAttribute()->getId();
            $attribute["frontendLabel"] = $entity->getAttribute()->getFrontendLabel();

            $html.= $this->renderView("AppBundle:Admin/ListView:available_attribute.html.twig", Array('attribute' => Array('attribute' => $attribute)));
        }*/

        foreach ($attributes as $entity) {
            $html.= $this->renderView("AppBundle:Admin/ListView:available_attribute.html.twig", array('attribute' => $entity));
        }

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("administrator/attribute_group/get_attribute", name="attribute_group_get_attribute")
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

        $html = $this->renderView("AppBundle:Admin/AttributeGroup:used_attribute.html.twig", array('attribute' => array('attribute' => $attribute)));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }
}
