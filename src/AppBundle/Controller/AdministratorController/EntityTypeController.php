<?php

namespace AppBundle\Controller\AdministratorController;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeGroup;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\EntityType;
use AppBundle\Factory\FactoryContext;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\DatabaseManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\PageManager;
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
use Symfony\Component\Intl\Data\Util\ArrayAccessibleResourceBundle;
use Doctrine\Common\Inflector\Inflector;

class EntityTypeController extends AbstractController
{
    /**@var FactoryContext $factoryContext */
    protected $factoryContext;

    /** @var AttributeContext $attributeContext */
    protected $attributeContext;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /**@var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;
    /**@var AttributeGroupContext $attributeGroupContext */
    protected $attributeGroupContext;
    /**@var AdministrationManager $administrationManager */
    protected $administrationManager;
    /**@var PageManager $pageManager */
    protected $pageManager;
    /**@var DatabaseManager $databaseManager */
    protected $databaseManager;

    protected $baseTemplatePath;
    protected $managedEntityType;

    protected function initialize()
    {
        parent::initialize();
        $this->authenticateAdministrator();

        $this->entityTypeContext = $this->getContainer()->get('entity_type_context');
        $this->attributeSetContext = $this->getContainer()->get('attribute_set_context');
        $this->attributeGroupContext = $this->getContainer()->get('attribute_group_context');
        $this->administrationManager = $this->getContainer()->get("administration_manager");
        $this->databaseManager = $this->getContainer()->get("database_manager");
        $this->pageManager = $this->getContainer()->get("page_manager");
        $this->managedEntityType = "entity_type";
        $this->baseTemplatePath = $this->getContainer()->get('kernel')->locateResource('@AppBundle/Resources/views');
    }


    /**
     * @Route("administrator/entity_type", name="entity_type_index")
     */
    public function indexAction(Request $request)
    {
        $this->initialize();
        return new Response($this->renderView('AppBundle:Admin/EntityType:index.html.twig', array('managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("administrator/entity_type/list", name="get_entity_type_list")
     * @Method("POST")
     */
    public function GetList(Request $request)
    {
        $this->initialize();

        $pager = new DataTablePager();
        $pager->setFromPost($_POST);

        $entities = $this->entityTypeContext->getItemsWithPaging($pager);

        $html = $this->renderView('AppBundle:Admin/EntityType:list.html.twig', array('entities' => $entities, 'managed_entity_type' => $this->managedEntityType));
        $num_of_items = $this->entityTypeContext->countAllItems();

        $ret = array();
        $ret["draw"] = $pager->getDraw();
        $ret["recordsTotal"] = $num_of_items;
        $ret["recordsFiltered"] = $num_of_items;
        $ret["data"] = array();
        $ret["html"] = $html;

        return new JsonResponse($ret);
    }

    /**
     * @Route("administrator/entity_type/update/{id}", defaults={"id"=null}, name="entity_type_update_form")
     */
    public function updateAction($id, Request $request)
    {
        $this->initialize();

        /**
         * Create
         */
        if (empty($id)) {
            /**
             * List all bundles
             */
            $bundles = $this->getParameter('kernel.bundles');
            foreach ($bundles as $key => $bundle) {
                if (strpos(strtolower($key), 'business') !== false || strtolower($key) == 'appbundle') {
                    continue;
                }
                unset($bundles[$key]);
            }

            ksort($bundles);

            return new Response($this->renderView(
                'AppBundle:Admin/EntityType:form.html.twig',
                array(
                    'entity' => null,
                    'bundles' => $bundles,
                    'attribute_sets' => null,
                    'action_templates_modal' => null,
                    'action_templates_standard' => null,
                    'row_templates_modal' => null,
                    'row_templates_standard' => null,
                    'managed_entity_type' => $this->managedEntityType,
                )
            ));
        } /**
         * Update
         */
        else {
            $entity = $this->entityTypeContext->getById($id);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
            }

            $attribute_sets = $this->attributeSetContext->getBy(array('entityType' => $entity));

            /**
             * List all bundles
             */
            $bundles = $this->getParameter('kernel.bundles');
            foreach ($bundles as $key => $bundle) {
                if (strpos(strtolower($key), 'business') !== false || strtolower($key) == 'appbundle') {
                    continue;
                }
                unset($bundles[$key]);
            }

            ksort($bundles);

            return new Response($this->renderView(
                'AppBundle:Admin/EntityType:form.html.twig',
                array(
                    'entity' => $entity,
                    'bundles' => $bundles,
                    'attribute_sets' => $attribute_sets,
                    'managed_entity_type' => $this->managedEntityType,
                )
            ));
        }

        return false;
    }

    /**
     * @Route("administrator/entity_type/view/{id}", name="entity_type_view_form")
     */
    public function viewAction($id, Request $request)
    {
        $this->initialize();

        $entity = $this->entityTypeContext->getById($id);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
        }

        $attribute_sets = $this->attributeSetContext->getBy(array('entityType' => $entity));

        /**
         * List all bundles
         *
         */
        $bundles = $this->getParameter('kernel.bundles');
        foreach ($bundles as $key => $bundle) {
            if (strpos(strtolower($key), 'business') !== false || strtolower($key) == 'appbundle') {
                continue;
            }
            unset($bundles[$key]);
        }

        return new Response($this->renderView(
            'AppBundle:Admin/EntityType:form.html.twig',
            array(
                'entity' => $entity,
                'bundles' => $bundles,
                'attribute_sets' => $attribute_sets,
                'managed_entity_type' => $this->managedEntityType
            )
        ));
    }

    /**
     * @Route("administrator/entity_type/set_custom", name="entity_type_set_custom")
     * @Method("POST")
     */
    public function setCustomAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $entity = $this->entityTypeContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Entity type does not exist'));
        }

        if (!$this->administrationManager->changeIsCustom($entity, 1)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'Delete entity type', 'message' => 'Entity type has been set custom'));
    }

    /**
     * @Route("administrator/entity_type/unset_custom", name="entity_type_unset_custom")
     * @Method("POST")
     */
    public function unsetCustomAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $entity = $this->entityTypeContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Entity type does not exist'));
        }

        if (!$this->administrationManager->changeIsCustom($entity, 0)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'Delete entity type', 'message' => 'Entity type has been unset custom'));
    }

    /**
     * @Route("administrator/entity_type/delete", name="entity_type_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $entity = $this->entityTypeContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Entity type does not exist'));
        }

        if (!$this->administrationManager->deleteEntityType($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'Delete entity type', 'message' => 'Entity type has been deleted'));
    }

    /**
     * @Route("administrator/entity_type/regenerate_all", name="entity_type_regenerate_all")
     * @Method("POST")
     */
    public function regenerateAllAction(Request $request)
    {
        $this->initialize();

        $entityTypes = $this->entityTypeContext->getAll();

        /** @var EntityType $entityType */
        foreach ($entityTypes as $entityType) {
            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->administrationManager->generateDoctrineXML($entityType, true);
            $this->administrationManager->generateEntityClasses($entityType, true);
        }

        return new JsonResponse(array('error' => false, 'id' => $entityType->getId(), 'title' => 'Regenerate files', 'message' => 'PHP and XML have been regenerated'));
    }


    /**
     * @Route("administrator/entity_type/regenerate", name="entity_type_regenerate")
     * @Method("POST")
     */
    public function regenerateAction(Request $request)
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

        $this->databaseManager->createTableIfDoesntExist($entityType, null);
        $this->administrationManager->generateDoctrineXML($entityType, true);
        $this->administrationManager->generateEntityClasses($entityType, true);

        return new JsonResponse(array('error' => false, 'id' => $entityType->getId(), 'title' => 'Regenerate files', 'message' => 'PHP and XML have been regenerated'));
    }

    /**
     * @Route("administrator/entity_type/url_to_entity_type", name="entity_type_url_to_entity_type")
     * @Method("POST")
     */
    public function urlToEntityTypeAction(Request $request)
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

        try{
            $this->administrationManager->addUrlOptionToEntity($entityType->getEntityTypeCode());
        }
        catch (\Exception $e){
            return new JsonResponse(array('error' => true, 'id' => $entityType->getId(), 'title' => 'Error', 'message' => $e->getMessage()));
        }

        return new JsonResponse(array('error' => false, 'id' => $entityType->getId(), 'title' => 'Regenerate files', 'message' => "\r\nTo generate url-s add entity type to sCommerceManager:filterCustomDestinationTypes and run 'php bin/console scommercehelper:function automatically_fix_rutes' \r\nAlso, add event listener (check blog_post_listener) and custom validateDestination in sCommerceManager.\r\nDont forget to add Template type\r\n\r\n"));
    }

    /**
     * @Route("administrator/entity_type/export", name="entity_type_export_entity_type")
     * @Method("GET")
     */
    public function ExportEntityTypeAction(Request $request)
    {
        $this->initialize();

        $id = $request->query->get("id");

        if (!$id) {
            return new JsonResponse(array('error' => true, 'message' => 'Entity ID missing.'));
        }

        /** @var $entityType EntityType */
        $entityType = $this->entityTypeContext->getById($id);
        if (!isset($entityType) || empty($entityType)) {
            return new JsonResponse(array('error' => true, 'message' => 'Entity type does not exist'));
        }

        $data = [
            "entityType" => $entityType->convertToArray(),
            "attributes" => [],
            "attributeGroups" => [],
            "attributeSets" => [],
        ];

        /** @var EntityManager $entityManager */
        $entityManager = $this->getContainer()->get("entity_manager");

        // Attributes.
        $repo = $entityManager->getDoctrineEntityManager()->getRepository(Attribute::class);
        foreach ($repo->findBy(["entityTypeId" => $entityType->getId()]) as $attributeEntity) {
            /** @var Attribute $attributeEntity */
            $data["attributes"][] = $attributeEntity->convertToArray();
        }

        $repo = $entityManager->getDoctrineEntityManager()->getRepository(AttributeSet::class);

        // Attribute sets.
        // Entity can have one or more attribute sets. Each set can have one or more attribute groups.
        foreach ($repo->findBy(["entityTypeId" => $entityType->getId()]) as $attributeSetEntity) {
            /** @var AttributeSet $attributeSetEntity */
            $data["attributeSets"][] = $attributeSetEntity->convertToArray();

            // Attribute groups - let's immediately pull them out since we have an attribute set here.
            // Entity can have one or more attribute groups. Each attribute group has a parent attribute set.
            $repo = $entityManager->getDoctrineEntityManager()->getRepository(AttributeGroup::class);

            foreach ($repo->findBy(["attributeSetId" => $attributeSetEntity->getId()]) as $attributeGroupEntity) {
                /** @var AttributeGroup $attributeGroupEntity */
                $data["attributeGroups"][] = $attributeGroupEntity->convertToArray();
            }
        }

        $name = 'export_entity_' . $entityType->getEntityTypeCode() . '.json';
        header('Content-disposition: attachment; filename=' . $name);
        header('Content-type: application/json');
        die(json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * @Route("administrator/entity_type/create_pages", name="entity_type_create_pages")
     * @Method("POST")
     */
    public function createPagesAction(Request $request)
    {
        $this->initialize();
        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $entityType = $this->entityTypeContext->getById($p["id"]);

        if (!isset($entityType) || empty($entityType)) {
            return new JsonResponse(array('error' => true, 'message' => 'Id does not exist'));
        }

        /*if($entityType->getIsRelation()){
            $this->administrationManager->generateMultiselectBlocks($entityType);
        }*/
        if ($entityType->getIsRelation() == 0) {
            $this->pageManager->generateDefaultPages($entityType);
        }

        return new JsonResponse(array('error' => false, 'id' => $entityType->getId(), 'title' => 'Create default pages', 'message' => 'Default pages have been created'));
    }

    /**
     * @Route("administrator/entity_type/make_document", name="entity_type_make_document")
     * @Method("POST")
     */
    public function makeDocumentAction(Request $request)
    {
        $this->initialize();
        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        /** @var EntityType $entityType */
        $entityType = $this->entityTypeContext->getById($p["id"]);

        if (!isset($entityType) || empty($entityType)) {
            return new JsonResponse(array('error' => true, 'message' => 'Id does not exist'));
        }

        if ($entityType->getIsDocument() == 1) {
            return new JsonResponse(array('error' => false, 'id' => $entityType->getId(), 'title' => 'Make document', 'message' => 'Entity type is allready a document'));
        }

        if (!$this->administrationManager->addDocumentAttributesToEntityType($entityType)) {
            return new JsonResponse(array('error' => false, 'id' => $entityType->getId(), 'title' => 'Make document', 'message' => 'Error adding file attribute'));
        }

        $entityType->setIsDocument(1);

        $this->administrationManager->saveEntityType($entityType);

        $this->databaseManager->createTableIfDoesntExist($entityType, null);
        $this->administrationManager->generateDoctrineXML($entityType, true);
        $this->administrationManager->generateEntityClasses($entityType, true);

        return new JsonResponse(array('error' => false, 'id' => $entityType->getId(), 'title' => 'Make document', 'message' => 'Document added to entity type'));
    }

    /**
     * @Route("administrator/entity_type/save", name="entity_type_save")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();
        $p = $_POST;

        if (!isset($p["isView"])) {
            $p["isView"] = 0;
        }

        if (!isset($p["isCustom"])) {
            $p["isCustom"] = 0;
        }

        if (!isset($p["syncContent"])) {
            $p["syncContent"] = 0;
        }

        /**
         * INSERT
         */
        if (!isset($p["id"]) || empty($p["id"])) {
            /**
             * Check if is document
             */
            if (!isset($p["isDocument"])) {
                $p["isDocument"] = 0;
            }

            /**
             * Check if need to generate pages
             */
            if (!isset($p["isRelation"])) {
                $p["isRelation"] = 0;
            }
            if (!isset($p["generatePages"])) {
                $p["generatePages"] = 0;
            }
            if (!isset($p["generateListView"])) {
                $p["generateListView"] = 0;
            }

            if ($p["isRelation"]) {
                $p["generateListView"] = 0;
                $p["generatePages"] = 0;
            }

            if ($p["isRelation"]) {
                $p["generateListView"] = 0;
                $p["generatePages"] = 0;
            }

            $ret = $this->administrationManager->createEntityType($p);

            if ($ret["error"]) {
                return new JsonResponse(array('error' => true, 'message' => $ret["message"]));
            }

            /** @var EntityType $entityType */
            $entityType = $ret["entity_type"];

            return new JsonResponse(array('error' => false, 'title' => 'Insert new entity type', 'message' => 'Entity type has been added', 'entity' => array('id' => $entityType->getId())));
        } /**
         * UPDATE
         */
        else {
            if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
                return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
            }

            /** @var EntityType $entity */
            $entity = $this->entityTypeContext->getById($p["id"]);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Id does not exist'));
            }

            $entity->setEntityCustom($p["entityCustom"]);
            $entity->setDoctrineCustom($p["doctrineCustom"]);
            $entity->setEntityUseClasses($p["entityUseClasses"]);
            $entity->setEntityExtendClass($p["entityExtendClass"]);
            $entity->setHasUniquePermissions($p["hasUniquePermissions"]);
            $entity->setIsView($p["isView"]);
            $entity->setSyncContent($p["syncContent"]);
            $entity->setIsCustom($p["isCustom"]);

            $entity = $this->administrationManager->saveEntityType($entity);
            if (empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            return new JsonResponse(array('error' => false, 'title' => 'Update entity type', 'message' => 'Entity type has been updated', 'entity' => array('id' => $entity->getId())));
        }

        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
    }

    /**
     * @Route("administrator/entity_type/get_attribute_sets", name="get_attribute_sets_for_entity_type")
     * @Method("POST")
     */
    public function getAttributeSetsForEntityType(Request $request)
    {
        $this->initialize();
        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $entity = $this->entityTypeContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Entity type does not exist'));
        }

        $attribute_sets = $this->attributeSetContext->getBy(array('entityType' => $entity));

        $data = array();

        foreach ($attribute_sets as $key => $attribute_set) {
            $data[$key]["value"] = $attribute_set->getId();
            $data[$key]["label"] = $attribute_set->getAttributeSetCode();
        }
        $data[0]["selected"] = true;

        $html = $this->renderView("AppBundle:Admin:select.html.twig", array('entities' => $data, 'default' => 'Please select'));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("administrator/entity_type/get_attribute_groups", name="get_attribute_groups_for_attribute_set")
     * @Method("POST")
     */
    public function getAttributeGroupsForAttributeSet(Request $request)
    {
        $this->initialize();
        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $entity = $this->attributeSetContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Attribute set does not exist'));
        }

        $attribute_groups = $this->attributeGroupContext->getBy(array('attributeSet' => $entity));

        $data = array();

        foreach ($attribute_groups as $key => $attribute_group) {
            $data[$key]["value"] = $attribute_group->getId();
            $data[$key]["label"] = $attribute_group->getAttributeGroupName();
        }
        $data[0]["selected"] = true;

        $html = $this->renderView("AppBundle:Admin:select.html.twig", array('entities' => $data, 'default' => 'Please select'));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("administrator/entity_type/get_attribute_group", name="entity_type_get_attribute_group")
     * @Method("POST")
     */
    public function getAttributeGroupAction(Request $request)
    {
        $this->initialize();
        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Id is not correct'));
        }

        $entity = $this->attributeGroupContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Attribute group does not exist'));
        }

        $column["group"] = $entity->getId();
        $column["size"] = 6;
        $column["group_name"] = $entity->getAttributeGroupName();
        $column["show_group_name"] = 1;
        $type = $p["type"];
        $count = $p["count"] + 1;
        $attribute_set["id"] = $p["attribute_set_id"];

        $html = $this->renderView("AppBundle:Admin/EntityType:column.html.twig", array('key' => 0, 'key2' => $count, 'column' => $column, 'layout_type' => $type, 'attribute_set' => $attribute_set));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("administrator/entity_type/get_attributes", name="get_attributes_for_entity_type")
     * @Method("POST")
     */
    public function getAttributesForEntityType(Request $request)
    {
        $this->initialize();
        $p = $_POST;

        /** @var EntityType $entityType */
        $entityType = null;

        if (isset($p["id"]) && !empty($p["id"]) && preg_match('/^[0-9]*$/', $p["id"])) {
            $entityType = $this->entityTypeContext->getById($p["id"]);
        } elseif (isset($p["entity_type_code"]) && !empty($p["entity_type_code"])) {
            $entityType = $this->entityTypeContext->getItemByCode($p["entity_type_code"]);
        }

        if (empty($entityType)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Entity type does not exist')));
        }

        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->getContainer()->get("attribute_context");
        }

        $attributes = $this->attributeContext->getAttributesByEntityType($entityType) ?? [];

        $ret = [];

        /** @var Attribute $attribute */
        foreach ($attributes as $attribute) {
            $ret[$attribute->getId()] = [
                "code" => $attribute->getBackendType() == "lookup" ? EntityHelper::makeGetter(str_ireplace("_id", "", $attribute->getAttributeCode())) : EntityHelper::makeGetter($attribute->getAttributeCode()),
                "children" => []
            ];

            if ($attribute->getBackendType() == "lookup") {
                $secondaryEntityType = $attribute->getLookupEntityType();
                $secondaryAttributes = $this->attributeContext->getAttributesByEntityType($secondaryEntityType) ?? [];
                $retSecondary = [];
                foreach ($secondaryAttributes as $secondaryAttribute) {
                    if ($secondaryAttribute->getBackendType() == "lookup") {
                        continue;
                    }
                    $retSecondary[$secondaryAttribute->getId()] = [
                        "code" => EntityHelper::makeGetter(str_ireplace("_id", "", $attribute->getAttributeCode())) . "." . EntityHelper::makeGetter($secondaryAttribute->getAttributeCode())
                    ];
                }
                $ret[$attribute->getId()]["children"] = $retSecondary;
            }
        }

        return new JsonResponse(array('error' => false, 'attributes' => $ret));
    }
}
