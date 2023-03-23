<?php

namespace AppBundle\Controller\AdministratorController;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Constants\SQLKeywords;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityAttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\PageBlockContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeGroup;
use AppBundle\Entity\AttributeOptionValue;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\EntityAttribute;
use AppBundle\Entity\EntityType;
use AppBundle\Interfaces\Fields\FieldInterface;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\DatabaseManager;
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
use Symfony\Component\VarDumper\VarDumper;

class AttributeController extends AbstractController
{
    /**@var EntityAttributeContext $entityAttributeContext */
    protected $entityAttributeContext;
    /**@var AttributeContext $attributeContext */
    protected $attributeContext;
    /**@var PageBlockContext $blockContext */
    protected $blockContext;
    /**@var AttributeGroupContext $attributeGroupContext */
    protected $attributeGroupContext;
    /**@var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /**@var DatabaseManager $databaseManager */
    protected $databaseManager;
    /**@var AdministrationManager $administrationManager */
    protected $administrationManager;
    protected $baseTemplatePath;
    protected $managedEntityType;
    protected $attributeDefinition;
    protected $fieldTypes;

    protected function initialize()
    {
        parent::initialize();
        $this->authenticateAdministrator();

        $this->entityAttributeContext = $this->getContainer()->get('entity_attribute_context');
        $this->entityTypeContext = $this->getContainer()->get('entity_type_context');
        $this->attributeContext = $this->getContainer()->get('attribute_context');
        $this->blockContext = $this->getContainer()->get('page_block_context');
        $this->attributeSetContext = $this->getContainer()->get('attribute_set_context');
        $this->attributeGroupContext = $this->getContainer()->get('attribute_group_context');
        $this->databaseManager = $this->getContainer()->get("database_manager");
        $this->administrationManager = $this->getContainer()->get("administration_manager");
        $this->baseTemplatePath = $this->getContainer()->get('kernel')->locateResource('@AppBundle/Resources/views');
        $this->attributeDefinition = new \AppBundle\Definitions\AttributeDefinition();
        $this->managedEntityType = "attribute";
        $this->fieldTypes = array();

        $services = $this->container->getServiceIds();

        $needle = "_field";
        $length = strlen($needle);

        foreach ($services as $service) {
            if (substr($service, -$length) === $needle) {
                $field_name = str_replace($needle, "", $service);
                /** @var FieldInterface $field */
                $field = $this->getContainer()->get($service);
                if ($field instanceof FieldInterface) {
                    $this->fieldTypes[$field_name] = array(
                        "input" => $field->getInput() == "" ? $field_name : $field->getInput(),
                        "type" => $field->getType() == "" ? $field_name : $field->getType());
                }
            }
        }
    }

    /**
     * @Route("administrator/attribute", name="attribute_index")
     */
    public function indexAction(Request $request)
    {
        $this->initialize();
        return new Response($this->renderView('AppBundle:Admin/Attribute:index.html.twig', array('managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("administrator/attribute/list", name="get_attribute_list")
     * @Method("POST")
     */
    public function GetList(Request $request)
    {
        $this->initialize();

        $pager = new DataTablePager();
        $pager->setFromPost($_POST);

        $entities = $this->attributeContext->getItemsWithPaging($pager);

        $html = $this->renderView('AppBundle:Admin/Attribute:list.html.twig', array('entities' => $entities, 'managed_entity_type' => $this->managedEntityType));
        $num_of_items = $this->attributeContext->countAllItems();

        $ret = array();
        $ret["draw"] = $pager->getDraw();
        $ret["recordsTotal"] = $num_of_items;
        $ret["recordsFiltered"] = $num_of_items;
        $ret["data"] = array();
        $ret["html"] = $html;

        return new JsonResponse($ret);
    }

    /**
     * @Route("administrator/attribute/update/{id}", defaults={"id"=null}, name="attribute_update_form")
     */
    public function createAction($id, Request $request)
    {
        $this->initialize();

        /** @var EntityType $entityTypes */
        $entityTypes = $this->entityTypeContext->getBy(array(), array("entityTypeCode" => "asc"));
        /**
         * Create
         */
        if (empty($id)) {
            $entity_types = array();
            foreach ($entityTypes as $entityType) {
                $attributeSets = $this->attributeSetContext->getBy(array("entityType" => $entityType), array("id" => "asc"));
                if (empty($attributeSets)) {
                    continue;
                }
                $entity_attribute_types = $this->entityAttributeContext->getBy(array("entityType" => $entityType, "attributeSet" => $attributeSets[0]));

                foreach ($attributeSets as $attributeSet) {
                    $blocks = $this->blockContext->getBy(array("type" => "edit_form", "attributeSet" => $attributeSet));

                    foreach ($entity_attribute_types as $entity_attribute_type) {
                        if ($entity_attribute_type->getAttribute() == null) {
                            dump($entity_attribute_type);
                            die;
                        }
                        $entity_types[$entity_attribute_type->getEntityType()->getId()]["id"] = $entity_attribute_type->getEntityType()->getId();
                        $entity_types[$entity_attribute_type->getEntityType()->getId()]["name"] = $entity_attribute_type->getEntityType()->getEntityTypeCode();
                        $entity_types[$entity_attribute_type->getEntityType()->getId()]["attribute_sets"][$attributeSet->getId()]["name"] = $attributeSet->getAttributeSetCode();
                        $entity_types[$entity_attribute_type->getEntityType()->getId()]["attribute_sets"][$attributeSet->getId()]["attributes"][$entity_attribute_type->getAttribute()->getId()]["name"] = $entity_attribute_type->getAttribute()->getFrontendLabel();
                        if (!empty($blocks)) {
                            foreach ($blocks as $block) {
                                $entity_types[$entity_attribute_type->getEntityType()->getId()]["attribute_sets"][$attributeSet->getId()]["blocks"][$block->getId()]["name"] = $block->getTitle();
                            }
                        }
                    }
                }
            }

            $autocomplete_templates = array();
            $files = scandir($this->baseTemplatePath . $this->getParameter('autocomplete_template_path'));
            if (!empty($files)) {
                foreach ($files as $file) {
                    if ($file[0] == "." || $file[0] == "_") {
                        continue;
                    }
                    $file_part = explode(".", $file);
                    $autocomplete_templates[] = $file_part[0];
                }
            }

            return new Response($this->renderView(
                'AppBundle:Admin/Attribute:form.html.twig',
                array(
                    'entity' => null,
                    'managed_entity_type' => $this->managedEntityType,
                    'attribute_definition' => $this->attributeDefinition,
                    'field_types' => $this->fieldTypes,
                    'entity_types' => $entity_types,
                    'autocomplete_templates' => $autocomplete_templates
                )
            ));
        } /**
         * Update
         */
        else {
            $entity = $this->attributeContext->getById($id);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
            }

            $entity_types = array();
            foreach ($entityTypes as $entityType) {
                $attributeSets = $this->attributeSetContext->getBy(array("entityType" => $entityType), array("id" => "asc"));
                if (empty($attributeSets)) {
                    continue;
                }
                $entity_attribute_types = $this->entityAttributeContext->getBy(array("entityType" => $entityType, "attributeSet" => $attributeSets[0]));


                foreach ($attributeSets as $attributeSet) {
                    $blocks = $this->blockContext->getBy(array("type" => "edit_form", "attributeSet" => $attributeSet));

                    foreach ($entity_attribute_types as $entity_attribute_type) {
                        if ($entityType->getId() == $entity->getEntityTypeId()) {
                            $entity_types[$entity_attribute_type->getEntityType()->getId()]["self"] = true;
                        }

                        if ($entity_attribute_type->getAttribute() == null) {
                            dump($entity_attribute_type);
                            die;
                        }
                        $entity_types[$entity_attribute_type->getEntityType()->getId()]["id"] = $entity_attribute_type->getEntityType()->getId();
                        $entity_types[$entity_attribute_type->getEntityType()->getId()]["name"] = $entity_attribute_type->getEntityType()->getEntityTypeCode();
                        $entity_types[$entity_attribute_type->getEntityType()->getId()]["attribute_sets"][$attributeSet->getId()]["name"] = $attributeSet->getAttributeSetCode();
                        $entity_types[$entity_attribute_type->getEntityType()->getId()]["attribute_sets"][$attributeSet->getId()]["attributes"][$entity_attribute_type->getAttribute()->getId()]["name"] = $entity_attribute_type->getAttribute()->getFrontendLabel();
                        if (!empty($blocks)) {
                            foreach ($blocks as $block) {
                                $entity_types[$entity_attribute_type->getEntityType()->getId()]["attribute_sets"][$attributeSet->getId()]["blocks"][$block->getId()]["name"] = $block->getTitle();
                            }
                        }
                    }
                }
            }


            $autocomplete_templates = array();
            $files = scandir($this->baseTemplatePath . $this->getParameter('autocomplete_template_path'));
            if (!empty($files)) {
                foreach ($files as $file) {
                    if ($file[0] == "." || $file[0] == "_") {
                        continue;
                    }
                    $file_part = explode(".", $file);
                    $autocomplete_templates[] = $file_part[0];
                }
            }

            return new Response($this->renderView(
                'AppBundle:Admin/Attribute:form.html.twig',
                array(
                    'entity' => $entity,
                    'managed_entity_type' => $this->managedEntityType,
                    'attribute_definition' => $this->attributeDefinition,
                    'field_types' => $this->fieldTypes,
                    'entity_types' => $entity_types,
                    'autocomplete_templates' => $autocomplete_templates
                )
            ));
        }

        return false;
    }


    /**
     * @Method("POST")
     * @Route("administrator/attribute/custom_admin",  name="get_custom_admin")
     */
    public function getCustomAdmin(Request $request)
    {
        $this->initialize();
        $p = $_POST;
        $fieldType = $p["type"];
        $attributeId = $p["attributeId"];
        $entity = $this->attributeContext->getById($attributeId);

        if ($entity === null) {
            $entity = new Attribute();
        }

        /**@var FieldInterface $field */
        $field = $this->getContainer()->get($fieldType . "_field");

        return new Response($field->getCustomAdmin($entity));
    }


    /**
     * @Route("administrator/attribute/delete", name="attribute_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Attribute id is not correct'));
        }

        $entity = $this->attributeContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Attribute does not exist'));
        }

        if ($entity->getBackendType() == "static") {
            return new JsonResponse(array('error' => true, 'message' => 'Static type cannot be deleted'));
        }

        if (!$this->administrationManager->deleteAttribute($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        /**
         * Regenerate files
         */
        $this->databaseManager->createTableIfDoesntExist($entity->getEntityType(), null);
        $this->administrationManager->generateDoctrineXML($entity->getEntityType(), true);
        $this->administrationManager->generateEntityClasses($entity->getEntityType(), true);

        return new JsonResponse(array('error' => false, 'title' => 'Delete attribute', 'message' => 'Attribute has been deleted'));
    }

    /**
     * @Route("administrator/attribute/save", name="attribute_save")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["frontendLabel"]) || empty($p["frontendLabel"])) {
            return new JsonResponse(array('error' => true, 'message' => 'frontendLabel is not correct'));
        }

        if (isset($p["frontendInput"]) && $p["frontendInput"] == "lookup") {
            if (!isset($p["frontendModel"]) || empty($p["frontendModel"])) {
                return new JsonResponse(array('error' => true, 'message' => 'frontendModel is not correct'));
            }
        }
        if (isset($p["frontendInput"]) && ($p["frontendInput"] == "lookup" || $p["frontendInput"] == "reverse_lookup")) {
            if (!isset($p["lookupEntityType"]) || empty($p["lookupEntityType"])) {
                return new JsonResponse(array('error' => true, 'message' => 'lookupEntityType is not correct'));
            }
            if (!isset($p["lookupAttributeSet"]) || empty($p["lookupAttributeSet"])) {
                return new JsonResponse(array('error' => true, 'message' => 'lookupAttributeSet is not correct'));
            }
            if (!isset($p["lookupAttribute"]) || empty($p["lookupAttribute"])) {
                return new JsonResponse(array('error' => true, 'message' => 'lookupAttribute is not correct'));
            }
            $lookup_entity_type = $this->entityTypeContext->getById($p["lookupEntityType"]);
            if (empty($lookup_entity_type)) {
                return new JsonResponse(array('error' => true, 'message' => 'This entity type does not exist'));
            }
        }


        if (!isset($p["frontendModel"])) {
            $p["frontendModel"] = null;
        }

        if (!isset($p["useInAdvancedSearch"])) {
            $p["useInAdvancedSearch"] = 0;
        }
        if (!isset($p["useInQuickSearch"])) {
            $p["useInQuickSearch"] = 0;
        }

        if (!isset($p["isCustom"])) {
            $p["isCustom"] = 0;
        }


        if (!isset($p["lookupEntityType"]) || empty($p["lookupEntityType"])) {
            $lookupEntityType = null;
        } else {
            $lookupEntityType = $this->entityTypeContext->getById($p["lookupEntityType"]);
            if (empty($lookupEntityType)) {
                return new JsonResponse(array('error' => true, 'message' => 'This lookup entity type does not exist'));
            }
        }
        if (!isset($p["lookupAttributeSet"]) || empty($p["lookupAttributeSet"])) {
            $lookupAttributeSet = null;
        } else {
            $lookupAttributeSet = $this->attributeSetContext->getById($p["lookupAttributeSet"]);
            if (empty($lookupAttributeSet)) {
                return new JsonResponse(array('error' => true, 'message' => 'This lookup attribute set does not exist'));
            }
        }
        if (!isset($p["lookupAttribute"]) || empty($p["lookupAttribute"])) {
            $lookupAttribute = null;
        } else {
            $lookupAttribute = $this->attributeContext->getById($p["lookupAttribute"]);
            if (empty($lookupAttribute)) {
                return new JsonResponse(array('error' => true, 'message' => 'This lookup attribute does not exist'));
            }
        }

        if (!isset($p["modalPageBlockId"]) || empty($p["modalPageBlockId"])) {
            $modalPageBlockId = null;
        } else {
            $modalPageBlockId = $p["modalPageBlockId"];
        }

        if (!isset($p["enableModalCreate"]) || empty($p["enableModalCreate"])) {
            $enableModalCreate = false;
            $modalPageBlockId = null;
        } else {
            $enableModalCreate = true;
        }

        if (!isset($p["useLookupLink"]) || empty($p["useLookupLink"])) {
            $useLookupLink = false;
        } else {
            $useLookupLink = true;
        }

        /**
         * INSERT
         */
        if (!isset($p["id"]) || empty($p["id"])) {
            if (!isset($p["frontendType"]) || empty($p["frontendType"])) {
                return new JsonResponse(array('error' => true, 'message' => 'frontendType is not correct'));
            }
            if (!isset($p["attributeCode"]) || empty($p["attributeCode"])) {
                return new JsonResponse(array('error' => true, 'message' => 'attributeCode is not correct'));
            }
            if (!isset($p["entityType"]) || empty($p["entityType"])) {
                return new JsonResponse(array('error' => true, 'message' => 'entityType is not correct'));
            }

            if (in_array(strtoupper($p["attributeCode"]), SQLKeywords::SQL_KEYWORDS)) {
                return new JsonResponse(array('error' => true, 'message' => 'attributeCode is not allowed'));
            }

            /**@var FieldInterface $field */
            $field = $this->getContainer()->get($p["frontendType"] . "_field");

            /**
             * lowercase on attribute code
             */
            $p["attributeCode"] = strtolower(trim($p["attributeCode"]));

            /**
             * Add _id for single lookups if dont exist
             */
            if (($field->getInput() == "lookup" || $field->getInput() == "select") && $field->getType() != "multiselect" && substr($p["attributeCode"], -3) != "_id") {
                $p["attributeCode"] = $p["attributeCode"] . "_id";
            }

            /**@var EntityType $entity_type */
            $entity_type = $this->entityTypeContext->getById($p["entityType"]);
            if (empty($entity_type)) {
                return new JsonResponse(array('error' => true, 'message' => 'This entity type does not exist'));
            }

            if($entity_type->getIsCustom()){
                $p["isCustom"] = $entity_type->getIsCustom();
            }

            if (strpos($p["attributeCode"], "_id") !== false) {
                $check_attribute_code = $this->attributeContext->getBy(array("attributeCode" => $p["attributeCode"], "entityTypeId" => $entity_type));
                if (!empty($check_attribute_code)) {
                    return new JsonResponse(array('error' => true, 'message' => 'Attribute with this code in this entity type already exists'));
                }

                $check_attribute_code = $this->attributeContext->getBy(array("attributeCode" => substr($p["attributeCode"], 0, -3), "entityTypeId" => $entity_type));
                if (!empty($check_attribute_code)) {
                    return new JsonResponse(array('error' => true, 'message' => 'Attribute with this code in this entity type already exists'));
                }
            } else {
                $check_attribute_code = $this->attributeContext->getBy(array("attributeCode" => $p["attributeCode"], "entityTypeId" => $entity_type));
                if (!empty($check_attribute_code)) {
                    return new JsonResponse(array('error' => true, 'message' => 'Attribute with this code in this entity type already exists'));
                }

                $check_attribute_code = $this->attributeContext->getBy(array("attributeCode" => $p["attributeCode"] . "_id", "entityTypeId" => $entity_type));
                if (!empty($check_attribute_code)) {
                    return new JsonResponse(array('error' => true, 'message' => 'Attribute with this code in this entity type already exists'));
                }
            }

            $pieces = preg_split('/(?=[A-Z])/', $entity_type->getEntityModel());
            $pieces = array_map('strtolower', $pieces);
            $backend_table = implode("_", $pieces);
            $backend_table = trim($backend_table, "_");

            $entity = new Attribute();
            $entity->setFrontendLabel($p["frontendLabel"]);
            $entity->setFrontendModel($p["frontendModel"]);
            if (!empty($field->getInput())) {
                $entity->setFrontendInput($field->getInput());
            } else {
                $entity->setFrontendInput($p["frontendType"]);
            }
            $entity->setFrontendType($p["frontendType"]);
            $entity->setFrontendRelated($p["frontendRelated"]);
            $entity->setFrontendClass($p["frontendClass"]);
            $entity->setFrontendDisplayFormat($p["frontendDisplayFormat"]);
            $entity->setFrontendHidden($p["frontendHidden"]);
            $entity->setFrontendDisplayOnNew($p["frontendDisplayOnNew"]);
            $entity->setFrontendDisplayOnUpdate($p["frontendDisplayOnUpdate"]);
            $entity->setFrontendDisplayOnView($p["frontendDisplayOnView"]);
            $entity->setFrontendDisplayOnPreview($p["frontendDisplayOnPreview"]);
            $entity->setUseInQuickSearch($p["useInQuickSearch"]);
            $entity->setUseInAdvancedSearch($p["useInAdvancedSearch"]);
            $entity->setReadOnly($p["readOnly"]);
            $entity->setPrefix($p["prefix"]);
            $entity->setSufix($p["sufix"]);
            $entity->setIsCustom($p["isCustom"]);
            $entity->setValidator($p["validator"]);
            $entity->setNote($p["note"]);
            $entity->setAttributeCode($p["attributeCode"]);
            $entity->setEntityType($entity_type);
            $entity->setBackendModel($entity_type->getEntityModel());
            $entity->setBackendType($field->getBackendType());
            $entity->setBackendTable($backend_table);
            $entity->setDefaultValue($p["defaultValue"]);
            $entity->setLookupEntityType($lookupEntityType);
            $entity->setLookupAttributeSet($lookupAttributeSet);
            $entity->setLookupAttribute($lookupAttribute);
            $entity->setEnableModalCreate($enableModalCreate);
            $entity->setUseLookupLink($useLookupLink);
            $entity->setModalPageBlockId($modalPageBlockId);
            if (isset($p["folder"])) {
                $entity->setFolder($p["folder"]);
            }

            if(empty($this->administrationManager)){
               $this->administrationManager = $this->getContainer()->get("administration_manager");
            }

            if(!$this->administrationManager->saveAttribute($entity)){
               return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again 1'));
            }

            if (isset($p["attributeSet"]) && isset($p["attributeGroup"]) && !empty($p["attributeGroup"])) {

                /** @var AttributeSet $attributeSet */
                $attributeSet = $this->attributeSetContext->getById($p["attributeSet"]);
                if (empty($attributeSet)) {
                    return new JsonResponse(array('error' => false, 'title' => 'Insert new attribute', 'message' => 'Attribute has been added, but not to attribute set and group'));
                }

                /** @var AttributeGroup $attributeGroup */
                $attributeGroup = $this->attributeGroupContext->getById($p["attributeGroup"]);
                if (empty($attributeGroup)) {
                    return new JsonResponse(array('error' => false, 'title' => 'Insert new attribute', 'message' => 'Attribute has been added, but not to attribute set and group'));
                }

                $entityAttribute = new EntityAttribute();
                $entityAttribute->setEntityType($entity_type);
                $entityAttribute->setAttributeSet($attributeSet);
                $entityAttribute->setAttributeGroup($attributeGroup);
                $entityAttribute->setAttribute($entity);



                try {
                    $this->entityAttributeContext->save($entityAttribute);
                    $entity_type->getEntityAttributes()->add($entityAttribute);
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                    return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
                }

                if ($field->getBackendType() != "Entity") {
                    if (!$this->databaseManager->addFieldIfDoesntExist($entity)) {
                        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
                    }
                }

                if($p["isCustom"]){
                    $attributeGroup->setIsCustom($p["isCustom"]);
                    $this->administrationManager->saveAttributeGroup($attributeGroup);
                }
            }

            /**
             * Regenerate all because of default attributes
             */
            $this->databaseManager->createTableIfDoesntExist($entity_type, null);
            $this->administrationManager->generateDoctrineXML($entity_type, true);
            $this->administrationManager->generateEntityClasses($entity_type, true);

            return new JsonResponse(array('error' => false, 'title' => 'Insert new attribute', 'message' => 'Attribute has been added', 'entity' => array('id' => $entity->getId())));
        } /**
         * UPDATE
         */
        else {
            if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
                return new JsonResponse(array('error' => true, 'message' => 'Attribute id is not correct'));
            }

            $entity = $this->attributeContext->getById($p["id"]);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Attribute does not exist'));
            }

//            if ($entity->getFrontendInput() == "select") {
//                if (!isset($p["option"]) || empty($p["option"])) {
//                    return new JsonResponse(array('error' => true, 'message' => 'options are not correct'));
//                }
//            }

            $entity->setFrontendLabel($p["frontendLabel"]);
            $entity->setFrontendModel($p["frontendModel"]);
            $entity->setFrontendRelated($p["frontendRelated"]);
            $entity->setFrontendClass($p["frontendClass"]);
            $entity->setFrontendDisplayFormat($p["frontendDisplayFormat"]);
            $entity->setFrontendHidden($p["frontendHidden"]);
            $entity->setFrontendDisplayOnNew($p["frontendDisplayOnNew"]);
            $entity->setFrontendDisplayOnUpdate($p["frontendDisplayOnUpdate"]);
            $entity->setFrontendDisplayOnView($p["frontendDisplayOnView"]);
            $entity->setFrontendDisplayOnPreview($p["frontendDisplayOnPreview"]);
            $entity->setUseInQuickSearch($p["useInQuickSearch"]);
            $entity->setUseInAdvancedSearch($p["useInAdvancedSearch"]);
            $entity->setReadOnly($p["readOnly"]);
            $entity->setPrefix($p["prefix"]);
            $entity->setSufix($p["sufix"]);

            $entity->setValidator($p["validator"]);
            $entity->setNote($p["note"]);

            $entity->setDefaultValue($p["defaultValue"]);

            $entity->setLookupEntityType($lookupEntityType);
            $entity->setLookupAttributeSet($lookupAttributeSet);
            $entity->setLookupAttribute($lookupAttribute);
            $entity->setEnableModalCreate($enableModalCreate);
            $entity->setModalPageBlockId($modalPageBlockId);
            $entity->setUseLookupLink($useLookupLink);
            $entity->setIsCustom($p["isCustom"]);
            if (isset($p["folder"])) {
                $entity->setFolder($p["folder"]);
            }

            if(empty($this->administrationManager)){
               $this->administrationManager = $this->getContainer()->get("administration_manager");
            }

            if(!$this->administrationManager->saveAttribute($entity)){
               return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again 1'));
            }

            return new JsonResponse(array('error' => false, 'title' => 'Update attribute', 'message' => 'Atribute has been updated', 'entity' => array('id' => $entity->getId())));
        }
    }

    /**
     * @Route("administrator/attribute/get_select_options", name="attribute_get_select_options")
     * @Method("POST")
     */
    public function getAttributeGetSelectOptionsAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        $optionsList = array();

        if (isset($p["id"]) && !empty($p["id"]) && preg_match('/^[0-9]*$/', $p["id"])) {
            $entity = $this->attributeContext->getById($p["id"]);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Attribute group does not exist'));
            }

            $optionsList = array();
        }

        $html = $this->renderView("AppBundle:Admin/Attribute:select_options.html.twig", array('entites' => $optionsList, 'class' => ''));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("administrator/attribute/add_select_option", name="attribute_add_select_option")
     * @Method("POST")
     */
    public function getAttributeAddSelectOptionsAction(Request $request)
    {
        $this->initialize();

        $optionsList[] = array();

        $html = $this->renderView("AppBundle:Admin/Attribute:select_options.html.twig", array('entites' => $optionsList, 'class' => 'open'));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }
}
