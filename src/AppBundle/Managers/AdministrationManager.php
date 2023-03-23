<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\CoreContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityAttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewAttributeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Context\NavigationLinkContext;
use AppBundle\Context\PageBlockContext;
use AppBundle\Context\PageContext;
use AppBundle\Context\UserRoleContext;
use AppBundle\Controller\CoreApiController\NavigationLinkApiController;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeGroup;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\CoreUserRoleLinkEntity;
use AppBundle\Entity\EntityAttribute;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\ListView;
use AppBundle\Entity\NavigationLink;
use AppBundle\Entity\Page;
use AppBundle\Entity\PageBlock;
use AppBundle\Entity\RoleEntity;
use AppBundle\Entity\UserEntity;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use Doctrine\Common\Inflector\Inflector;
use FOS\UserBundle\Model\UserInterface;
use mysql_xdevapi\Exception;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\HttpFoundation\JsonResponse;

class AdministrationManager extends AbstractBaseManager
{
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /**@var AttributeContext $attributeContext */
    protected $attributeContext;
    /**@var PageBlockContext $pageBlockContext */
    protected $pageBlockContext;
    /**@var BlockManager $blockManager */
    protected $blockManager;
    /**@var PageContext $pageContext */
    protected $pageContext;
    /**@var ListViewContext $listViewContext */
    protected $listViewContext;
    /**@var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;
    /**@var AttributeGroupContext $attributeGroupContext */
    protected $attributeGroupContext;
    /**@var ListViewAttributeContext $listViewAttributeContext */
    protected $listViewAttributeContext;
    /**@var CoreContext $usersContext */
    protected $usersContext;
    /**@var UserRoleContext $userRoleContext */
    protected $userRoleContext;
    /**@var EntityAttributeContext $entityAttributeContext */
    protected $entityAttributeContext;
    /**@var PrivilegeManager $privilegeManager */
    protected $privilegeManager;
    /**@var DatabaseManager $databaseManager */
    protected $databaseManager;
    /**@var EntityManager $entityManager */
    protected $entityManager;
    protected $templateFolder;
    /** @var SyncManager $syncManager */
    protected $syncManager;
    /** @var PageManager $pageManager */
    protected $pageManager;
    /** @var ListViewManager $listViewManager */
    protected $listViewManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var NavigationLinkContext $navigationLinkContext */
    protected $navigationLinkContext;
    /** @var NavigationLinkManager $navigationLinkManager */
    protected $navigationLinkManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get('entity_manager');
        $this->templateFolder = $this->container->get('kernel')->locateResource('@AppBundle/Resources/config/templates/');
    }

    /**
     * @param AttributeGroup $entity
     * @param bool $generateBlock
     * @return AttributeGroup|bool
     * @throws \Exception
     */
    public function saveAttributeGroup(AttributeGroup $entity, $generateBlock = true)
    {
        if (empty($this->attributeGroupContext)) {
            $this->attributeGroupContext = $this->container->get("attribute_group_context");
        }
        if (empty($this->blockManager)) {
            $this->blockManager = $this->container->get('block_manager');
        }

        try {
            $this->attributeGroupContext->save($entity);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        $this->syncManager->exportEntityByTableAndId("attribute_group", $entity->getId(), true);

        $block = $this->blockManager->getBlockByTypeAndRelatedId('attribute_group', $entity->getId());

        if (empty($block) && $generateBlock) {
            $block = new PageBlock();
            $block->setType('attribute_group');
            $block->setRelatedId($entity->getId());
            $block->setEntityType($entity->getAttributeSet()->getEntityType());
            $block->setAttributeSet($entity->getAttributeSet());
            $block->setTitle($entity->getAttributeGroupName());
            $block->setIsCustom($entity->getIsCustom());

            $block = $this->blockManager->save($block);

            if (empty($block)) {
                return false;
            }
        }

        return $entity;
    }

    /**
     * @param AttributeGroup $entity
     * @return bool
     */
    public function deleteAttributeGroup(AttributeGroup $entity)
    {
        if (empty($this->attributeGroupContext)) {
            $this->attributeGroupContext = $this->container->get("attribute_group_context");
        }
        if (empty($this->blockManager)) {
            $this->blockManager = $this->container->get('block_manager');
        }

        $block = $this->blockManager->getBlockByTypeAndRelatedId('attribute_group', $entity->getId());

        if (!empty($block)) {
            $this->blockManager->delete($block);
        }

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        try {
            $row = $this->syncManager->getEntityRecordById("attribute_group", $entity->getId());
            $this->syncManager->deleteEntityRecord("attribute_group", $row);

            $this->attributeGroupContext->delete($entity);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param AttributeSet $attributeSet
     * @return AttributeSet
     */
    public function saveAttributeSet(AttributeSet $attributeSet)
    {
        if (empty($this->attributeSetContext)) {
            $this->attributeSetContext = $this->container->get("attribute_set_context");
        }

        $saveDefaultPrivileges = false;
        if (empty($attributeSet->getId())) {
            $saveDefaultPrivileges = true;
        }

        try {
            $attributeSet = $this->attributeSetContext->save($attributeSet);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return null;
        }

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        $this->syncManager->exportEntityByTableAndId("attribute_set", $attributeSet->getId(), true);

        if ($saveDefaultPrivileges) {
            if (empty($this->privilegeManager)) {
                $this->privilegeManager = $this->container->get("privilege_manager");
            }
            $this->privilegeManager->addPrivilegesToAllGroups('attribute_set', $attributeSet->getUid());
        }

        return $attributeSet;
    }

    /**
     * @param AttributeSet $entity
     * @return bool
     */
    public function deleteAttributeSet(AttributeSet $entity)
    {
        if (empty($this->attributeSetContext)) {
            $this->attributeSetContext = $this->container->get("attribute_set_context");
        }
        if (empty($this->attributeGroupContext)) {
            $this->attributeGroupContext = $this->container->get("attribute_group_context");
        }
        if (empty($this->pageContext)) {
            $this->pageContext = $this->container->get("page_context");
        }
        if (empty($this->pageBlockContext)) {
            $this->pageBlockContext = $this->container->get("page_block_context");
        }
        if (empty($this->blockManager)) {
            $this->blockManager = $this->container->get('block_manager');
        }

        /**delete pages**/
        $pages = $this->pageContext->getBy(array('attributeSet' => $entity));

        $pageManager = $this->container->get('page_manager');
        foreach ($pages as $page) {
            $pageManager->delete($page);
        }

        /**delete page_blocks **/
        $pageBlocks = $this->pageBlockContext->getBy(array('attributeSet' => $entity));
        foreach ($pageBlocks as $pageBlock) {
            $this->blockManager->delete($pageBlock);
        }

        /**delete attribute_groups **/
        $attributeGroups = $this->attributeGroupContext->getBy(array('attributeSet' => $entity));
        foreach ($attributeGroups as $attributeGroup) {
            $this->deleteAttributeGroup($attributeGroup);
        }

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        try {
            $row = $this->syncManager->getEntityRecordById("attribute_set", $entity->getId());
            $this->syncManager->deleteEntityRecord("attribute_set", $row);

            $this->attributeSetContext->delete($entity);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param Attribute $attribute
     * @return bool|Attribute
     */
    public function saveAttribute(Attribute $attribute)
    {
        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->container->get("attribute_context");
        }

        try {
            $this->attributeContext->save($attribute);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        $this->syncManager->exportEntityByTableAndId("attribute", $attribute->getId(), true);

        return $attribute;
    }

    /**
     * @param EntityAttribute $entityAttribute
     * @return bool|EntityAttribute
     */
    public function saveEntityAttribute(EntityAttribute $entityAttribute)
    {
        if (empty($this->entityAttributeContext)) {
            $this->entityAttributeContext = $this->container->get("entity_attribute_context");
        }

        try {
            $this->entityAttributeContext->save($entityAttribute);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return $entityAttribute;
    }

    /**
     * @param EntityAttribute $entityAttribute
     * @return bool
     * @throws \Exception
     */
    public function deleteEntityAttribute(EntityAttribute $entityAttribute)
    {
        if (empty($this->entityAttributeContext)) {
            $this->entityAttributeContext = $this->container->get("entity_attribute_context");
        }

        $attributeGroupId = $entityAttribute->getAttributeGroup()->getId();

        try {
            $this->entityAttributeContext->delete($entityAttribute);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        $this->syncManager->exportEntityByTableAndId("attribute_group", $attributeGroupId);

        return true;
    }

    /**
     * @param Attribute $attribute
     * @return bool
     * @throws \Exception
     */
    public function deleteAttribute(Attribute $attribute)
    {
        if (empty($this->entityAttributeContext)) {
            $this->entityAttributeContext = $this->container->get("entity_attribute_context");
        }
        if (empty($this->listViewAttributeContext)) {
            $this->listViewAttributeContext = $this->container->get("list_view_attribute_context");
        }
        if (empty($this->databaseManager)) {
            $this->databaseManager = $this->container->get('database_manager');
        }

        /** delete from listview '*/
        $listViewAttributes = $this->listViewAttributeContext->getBy(array('attribute' => $attribute));

        /** @var ListViewManager $listViewManager */
        $listViewManager = $this->container->get('list_view_manager');
        foreach ($listViewAttributes as $listViewAttribute) {
            $listViewManager->deleteListViewAttribute($listViewAttribute);
        }

        /** delete entity attribute */
        $entityAttributes = $this->entityAttributeContext->getBy(array('attribute' => $attribute));
        foreach ($entityAttributes as $entityAttribute) {
            $this->deleteEntityAttribute($entityAttribute);
        }

        if ($attribute->getBackendModel() != "Entity") {
            $pieces = preg_split('/(?=[A-Z])/', $attribute->getBackendModel());
            $pieces = array_map('strtolower', $pieces);
            $backend_table = implode("_", $pieces);
            $backend_table = trim($backend_table, "_");

            $this->databaseManager->deleteFieldIfExist($backend_table, $attribute);
        }

        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->container->get("attribute_context");
        }

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        try {
            $row = $this->syncManager->getEntityRecordById("attribute", $attribute->getId());
            $this->syncManager->deleteEntityRecord("attribute", $row);

            $this->attributeContext->delete($attribute);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param $fromEntityTypeCode
     * @param $toEntityTypeCode
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function cloneEntityType($fromEntityTypeCode, $toEntityTypeCode, $data = array())
    {

        $ret = array();
        $ret["error"] = true;

        /** @var EntityType $fromEntityType */
        $fromEntityType = $this->entityManager->getEntityTypeByCode($fromEntityTypeCode);
        if (empty($fromEntityType)) {
            $ret["message"] = "from entity type does not exist " . $fromEntityTypeCode;
            return $ret;
        }

        $toEntityType = $this->entityManager->getEntityTypeByCode($fromEntityTypeCode);
        if (!empty($toEntityType)) {
            $ret["message"] = "to entity type allready exists " . $toEntityTypeCode;
            return $ret;
        }

        $p = array();
        $p["entityTypeCode"] = $toEntityTypeCode;
        $p["bundle"] = $fromEntityType->getBundle();
        $p["entityCustom"] = $fromEntityType->getEntityCustom();
        $p["doctrineCustom"] = $fromEntityType->getDoctrineCustom();
        $p["entityUseClasses"] = $fromEntityType->getEntityUseClasses();
        $p["entityExtendClass"] = $fromEntityType->getEntityExtendClass();
        $p["isRelation"] = $fromEntityType->getIsRelation();
        $p["isDocument"] = $fromEntityType->getIsDocument();
        $p["hasUniquePermissions"] = $fromEntityType->getHasUniquePermissions();
        if (isset($data["generatePages"])) {
            $p["generatePages"] = $data["generatePages"];
        } else {
            $p["generatePages"] = false;
        }
        if (isset($data["generateListView"])) {
            $p["generateListView"] = $data["generateListView"];
        } else {
            $p["generateListView"] = false;
        }

        return $this->createEntityType($p);
    }

    /**
     * @param $p
     * @return array
     * @throws \Exception
     */
    public function createEntityType($p)
    {
        $ret = array();
        $ret["error"] = true;

        if (!isset($p["entityTypeCode"]) || empty($p["entityTypeCode"])) {
            $ret["message"] = $this->translator->trans('entityTypeCode is not correct');
            return $ret;
        }

        $p["entityTypeCode"] = StringHelper::sanitizeFileName($p["entityTypeCode"]);
        $p["entityTypeCode"] = strtolower(trim($p["entityTypeCode"]));
        $p["entityTypeCode"] = preg_replace('/\s+/', '_', $p["entityTypeCode"]);
        $p["entityTypeCode"] = preg_replace('/_+/', '_', $p["entityTypeCode"]);

        if (!isset($p["entityTypeCode"]) || empty($p["entityTypeCode"])) {
            $ret["message"] = $this->translator->trans('entityTypeCode is not correct');
            return $ret;
        }

        if(empty($this->entityTypeContext)){
            $this->entityTypeContext = $this->container->get("entity_type_context");
        }
        if (empty($this->databaseManager)) {
            $this->databaseManager = $this->container->get('database_manager');
        }

        $check = $this->entityTypeContext->getBy(array("entityTypeCode" => $p["entityTypeCode"]));
        if (!empty($check)) {
            $ret["message"] = $this->translator->trans('Entity type with this code already exists');
            return $ret;
        }

        /**
         * lowercase on entity type code
         */
        $entityTypeCode = strtolower($p["entityTypeCode"]);
        /**
         * Prepare table and model name
         */
        $entityTable = $entityTypeCode . "_entity";
        $entityModel = ucfirst(Inflector::camelize($entityTable));

        $entityType = new EntityType();
        $entityType->setEntityModel($entityModel);
        $entityType->setEntityTypeCode($entityTypeCode);
        $entityType->setEntityTable($entityTable);
        $entityType->setEntityIdField('id');
        $entityType->setIsDataSharing(1);
        $entityType->setDataSharingKey('default');
        $entityType->setBundle($p["bundle"]);
        $entityType->setEntityCustom($p["entityCustom"]);
        $entityType->setDoctrineCustom($p["doctrineCustom"]);
        $entityType->setEntityUseClasses($p["entityUseClasses"]);
        $entityType->setEntityExtendClass($p["entityExtendClass"]);
        $entityType->setIsRelation($p["isRelation"]);
        $entityType->setIsDocument($p["isDocument"]);
        $entityType->setIsView($p["isView"]);
        $entityType->setIsCustom($p["isCustom"]);
        $entityType->setSyncContent($p["syncContent"]);
        $entityType->setHasUniquePermissions($p["hasUniquePermissions"]);

        $entityType = $this->saveEntityType($entityType);
        if (empty($entityType)) {
            $ret["message"] = $this->translator->trans('There has been an error please try again');
            return $ret;
        }

        $this->databaseManager->createTableIfDoesntExist($entityType, null);
        $this->generateDoctrineXML($entityType, true);
        $this->generateEntityClasses($entityType, true);

        $attributeSet = new AttributeSet();
        $attributeSet->setEntityType($entityType);
        $attributeSet->setAttributeSetCode($p["entityTypeCode"]);
        $attributeSet->setAttributeSetName($p["entityTypeCode"]);
        $attributeSet->setIsCustom($p["isCustom"]);

        $attributeSet = $this->saveAttributeSet($attributeSet);
        if (empty($attributeSet)) {
            $ret["message"] = $this->translator->trans('There has been an error please try again');
            return $ret;
        }

        $attributeGroup = new AttributeGroup();
        $attributeGroup->setAttributeGroupName(str_replace("_", " ", $p["entityTypeCode"]));
        $attributeGroup->setAttributeSet($attributeSet);
        $attributeGroup->setIsCustom($p["isCustom"]);
        $attributeGroup = $this->saveAttributeGroup($attributeGroup, $p["generatePages"]);

        if (empty($attributeGroup)) {
            $ret["message"] = $this->translator->trans('There has been an error please try again');
            return $ret;
        }

        /** Add default id */
        $attribute = new Attribute();
        $attribute->setFrontendLabel("Id");
        $attribute->setFrontendInput("text");
        $attribute->setFrontendType("text");
        $attribute->setFrontendHidden(1);
        $attribute->setFrontendDisplayOnNew(1);
        $attribute->setFrontendDisplayOnUpdate(1);
        $attribute->setFrontendDisplayOnView(1);
        $attribute->setFrontendDisplayOnPreview(1);
        $attribute->setAttributeCode("id");
        $attribute->setEntityType($entityType);
        $attribute->setBackendModel($entityType->getEntityModel());
        $attribute->setBackendType("static");
        $attribute->setBackendTable($entityType->getEntityTable());
        $attribute->setIsCustom($p["isCustom"]);
        $attribute = $this->saveAttribute($attribute);

        if (empty($attribute)) {
            $ret["message"] = $this->translator->trans('There has been an error please try again');
            return $ret;
        }

        $entityAttribute = new EntityAttribute();
        $entityAttribute->setEntityType($entityType);
        $entityAttribute->setAttributeSet($attributeSet);
        $entityAttribute->setAttributeGroup($attributeGroup);
        $entityAttribute->setAttribute($attribute);

        $entityAttribute = $this->saveEntityAttribute($entityAttribute);
        if (empty($entityAttribute)) {
            $ret["message"] = $this->translator->trans('There has been an error please try again');
            return $ret;
        }

        /** Add default name */
        $attribute = new Attribute();
        $attribute->setFrontendLabel("Name");
        $attribute->setFrontendInput("text");
        $attribute->setFrontendType("text");
        $attribute->setFrontendHidden(0);
        $attribute->setReadOnly(0);
        $attribute->setFrontendDisplayOnNew(1);
        $attribute->setFrontendDisplayOnUpdate(1);
        $attribute->setFrontendDisplayOnView(1);
        $attribute->setFrontendDisplayOnPreview(1);
        $attribute->setAttributeCode("name");
        $attribute->setValidator('[{"type":"notempty","message":"Please fill in this field"}]');
        $attribute->setEntityType($entityType);
        $attribute->setBackendModel($entityType->getEntityModel());
        $attribute->setBackendType("varchar");
        $attribute->setBackendTable($entityType->getEntityTable());
        $attribute->setIsCustom($p["isCustom"]);
        $attribute = $this->saveAttribute($attribute);

        if (empty($attribute)) {
            $ret["message"] = $this->translator->trans('There has been an error please try again');
            return $ret;
        }

        $entityAttribute = new EntityAttribute();
        $entityAttribute->setEntityType($entityType);
        $entityAttribute->setAttributeSet($attributeSet);
        $entityAttribute->setAttributeGroup($attributeGroup);
        $entityAttribute->setAttribute($attribute);

        $entityAttribute = $this->saveEntityAttribute($entityAttribute);
        if (empty($entityAttribute)) {
            $ret["message"] = $this->translator->trans('There has been an error please try again');
            return $ret;
        }

        if ($entityType->getIsDocument()) {
            if (!$this->addDocumentAttributesToEntityType($entityType)) {
                $ret["message"] = $this->translator->trans('There has been an trying to create file attribute');
                return $ret;
            }
        }

        $this->saveAttributeGroup($attributeGroup);

        if ($p["generateListView"]) {
            $listView = new ListView();
            $listView->setName($p["entityTypeCode"]);
            $listView->setDisplayName(str_replace("_", " ", ucfirst($p["entityTypeCode"])));
            $listView->setDefaultSort($attribute->getId());
            $listView->setDefaultSortType('asc');
            $listView->setShowLimit(50);
            $listView->setShowFilter(true);
            $listView->setShowExport(true);
            $listView->setEntityType($entityType);
            $listView->setAttributeSet($attributeSet);
            $listView->setModalAdd(0);
            $listView->setIsCustom($p["isCustom"]);

            $listViewattributes = array();
            $listViewattributes[$attribute->getId()] = array(
                "display" => true,
                "field" => null,
                "label" => $attribute->getFrontendLabel(),
            );

            /** @var ListViewManager $listViewManager */
            $listViewManager = $this->container->get("list_view_manager");

            $listView = $listViewManager->addListView($listView, $listViewattributes, $p["generatePages"]);
            if (empty($listView)) {
                $ret["message"] = $this->translator->trans('There has been an error please try again');
                return $ret;
            }
        }

        if ($p["generatePages"]) {

            /** @var PageManager $pageManager */
            $pageManager = $this->container->get("page_manager");
            $pageManager->generateDefaultPages($entityType);
        }

        /**
         * Regenerate all because of default attributes
         */
        $this->databaseManager->createTableIfDoesntExist($entityType, null);
        $this->generateDoctrineXML($entityType, true);
        $this->generateEntityClasses($entityType, true);

        $ret["error"] = false;
        $ret["entity_type"] = $entityType;
        return $ret;
    }

    /**
     * @param EntityType $entityType
     * @param AttributeSet $attributeSet
     * @param AttributeGroup|null $attributeGroup
     * @param $data
     * @return array
     */
    public function createAttribute(EntityType $entityType, AttributeSet $attributeSet, AttributeGroup $attributeGroup = null, $data)
    {

        $ret = array();
        $ret["error"] = true;

        /** Add default id */
        $attribute = new Attribute();
        $attribute->setFrontendLabel($data["frontendLabel"]);
        $attribute->setFrontendInput($data["frontendInput"]);
        $attribute->setFrontendType($data["frontendType"]);
        $attribute->setFrontendModel($data["frontendModel"]);
        $attribute->setFrontendHidden($data["frontendHidden"]);
        $attribute->setReadOnly($data["readOnly"]);
        $attribute->setFrontendDisplayOnNew($data["frontendDisplayOnNew"]);
        $attribute->setFrontendDisplayOnUpdate($data["frontendDisplayOnUpdate"]);
        $attribute->setFrontendDisplayOnView($data["frontendDisplayOnView"]);
        $attribute->setFrontendDisplayOnPreview($data["frontendDisplayOnPreview"]);
        $attribute->setAttributeCode($data["attributeCode"]);
        $attribute->setEntityType($entityType);
        $attribute->setBackendModel($entityType->getEntityModel());
        $attribute->setBackendType($data["backendType"]);
        $attribute->setBackendTable($entityType->getEntityTable());
        $attribute->setIsCustom($data["isCustom"]);
        if (isset($data["uid"])) {
            $attribute->setUid($data["uid"]);
        }

        if ($data["backendType"] == "lookup") {
            $attribute->setLookupEntityType($data["lookupEntityType"]);
            $attribute->setLookupAttributeSet($data["lookupAttributeSet"]);
            $attribute->setLookupAttribute($data["lookupAttribute"]);
            $attribute->setEnableModalCreate($data["enableModalCreate"]);
            $attribute->setUseLookupLink($data["useLookupLink"]);
            $attribute->setModalPageBlockId($data["modalPageBlockId"]);
        }

        $attribute = $this->saveAttribute($attribute);

        if (empty($attribute)) {
            $ret["message"] = $this->translator->trans('There has been an error please try again');
            return $ret;
        }

        if (empty($attributeGroup)) {
            $ret["error"] = false;
            $ret["attribute"] = $attribute;
            return $ret;
        }

        $entityAttribute = new EntityAttribute();
        $entityAttribute->setEntityType($entityType);
        $entityAttribute->setAttributeSet($attributeSet);
        $entityAttribute->setAttributeGroup($attributeGroup);
        $entityAttribute->setAttribute($attribute);

        $entityAttribute = $this->saveEntityAttribute($entityAttribute);
        if (empty($entityAttribute)) {
            $ret["message"] = $this->translator->trans('There has been an error please try again');
            return $ret;
        }

        $ret["error"] = false;
        $ret["attribute"] = $attribute;
        return $ret;
    }

    /**
     * @param EntityType $entityType
     * @return bool|EntityType
     */
    public function saveEntityType(EntityType $entityType)
    {
        if (empty($this->entityTypeContext)) {
            $this->entityTypeContext = $this->container->get("entity_type_context");
        }

        try {
            $this->entityTypeContext->save($entityType);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        $this->syncManager->exportEntityByTableAndId("entity_type", $entityType->getId(), true);

        return $entityType;
    }

    /**
     * @param EntityType $entityType
     * @return bool
     * @throws \Exception
     */
    public function deleteEntityType(EntityType $entityType)
    {
        if (empty($this->entityTypeContext)) {
            $this->entityTypeContext = $this->container->get("entity_type_context");
        }
        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->container->get("attribute_context");
        }
        if (empty($this->attributeSetContext)) {
            $this->attributeSetContext = $this->container->get("attribute_set_context");
        }
        if (empty($this->pageContext)) {
            $this->pageContext = $this->container->get("page_context");
        }
        if (empty($this->listViewContext)) {
            $this->listViewContext = $this->container->get("list_view_context");
        }
        if (empty($this->blockManager)) {
            $this->blockManager = $this->container->get('block_manager');
        }
        if (empty($this->databaseManager)) {
            $this->databaseManager = $this->container->get('database_manager');
        }
        if (empty($this->privilegeManager)) {
            $this->privilegeManager = $this->container->get('privilege_manager');
        }

        /**delete pages**/
        $pages = $this->pageContext->getBy(array('entityType' => $entityType));

        $pageManager = $this->container->get('page_manager');
        foreach ($pages as $page) {
            $pageManager->delete($page);
        }

        /**delete pageBlocks**/
        $pageBlocks = $this->pageContext->getBy(array('entityType' => $entityType));

        foreach ($pageBlocks as $pageBlock) {
            $this->blockManager->delete($pageBlock);
        }

        /** delete list views **/
        $listViews = $this->listViewContext->getBy(array('entityType' => $entityType));

        $listViewManager = $this->container->get('list_view_manager');
        foreach ($listViews as $listView) {
            $listViewManager->deleteListView($listView);
        }

        /**delete attributes**/
        $attributes = $this->attributeContext->getBy(array('entityType' => $entityType));

        foreach ($attributes as $attribute) {
            $this->deleteAttribute($attribute);
        }

        /**delete lookup attributes**/
        $lookupAttributes = $this->attributeContext->getBy(array('lookupEntityType' => $entityType));

        foreach ($lookupAttributes as $attribute) {
            $this->deleteAttribute($attribute);
        }

        /**delete attribute sets**/
        $attributeSets = $this->attributeSetContext->getBy(array('entityType' => $entityType));

        foreach ($attributeSets as $attributeSet) {
            $this->deleteAttributeSet($attributeSet);
        }

        $backendTable = $entityType->getEntityTable();
        $backendModel = explode("_", $backendTable);
        $backendModel = array_map('ucfirst', $backendModel);
        $backendModel = implode("", $backendModel);

        if ($entityType->getEntityModel() != "Entity") {
            $this->databaseManager->deleteTableIfExist($backendTable);
        }

        $this->deleteFiles($entityType);

        $this->privilegeManager->removePrivilege(1, $entityType->getUid());
        $this->privilegeManager->removePrivilege(2, $entityType->getUid());
        $this->privilegeManager->removePrivilege(3, $entityType->getUid());
        $this->privilegeManager->removePrivilege(4, $entityType->getUid());

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        try {
            $row = $this->syncManager->getEntityRecordById("entity_type", $entityType->getId());
            $this->syncManager->deleteEntityRecord("entity_type", $row);

            $this->entityTypeContext->delete($entityType);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return true;
    }

    /***
     * @param $table
     * @param $entity
     * @param $isCustom
     * @return bool
     */
    public function changeIsCustomByTable($table, $entity, $isCustom){

        if($table == "page"){
            if (empty($this->pageManager)) {
                $this->pageManager = $this->container->get('page_manager');
            }
            $entity->setIsCustom($isCustom);
            $this->pageManager->save($entity);
        }
        elseif ($table == "page_block"){
            if (empty($this->blockManager)) {
                $this->blockManager = $this->container->get('block_manager');
            }
            $entity->setIsCustom($isCustom);
            $this->blockManager->save($entity);
        }
        elseif ($table == "list_view"){
            if (empty($this->listViewManager)) {
                $this->listViewManager = $this->container->get('list_view_manager');
            }
            $entity->setIsCustom($isCustom);
            $this->listViewManager->saveListView($entity);
        }
        elseif ($table == "attribute"){
            $entity->setIsCustom($isCustom);
            $this->saveAttribute($entity);
        }
        elseif ($table == "attribute_set"){
            $entity->setIsCustom($isCustom);
            $this->saveAttributeSet($entity);
        }
        elseif ($table == "attribute_group"){
            $entity->setIsCustom($isCustom);
            $this->saveAttributeGroup($entity);
        }
        elseif ($table == "entity_type"){
            $entity->setIsCustom($isCustom);
            $this->saveEntityType($entity);
        }
        elseif($table == "navigation_link"){
            if (empty($this->navigationLinkContext)) {
                $this->navigationLinkContext = $this->container->get('navigation_link_context');
            }
            $entity->setIsCustom($isCustom);
            $this->navigationLinkContext->save($entity);

            if (empty($this->syncManager)) {
                $this->syncManager = $this->container->get("sync_manager");
            }

            $this->syncManager->exportEntityByTableAndId("navigation_link", $entity->getId());
        }

        return true;
    }

    /**
     * @param EntityType $entityType
     * @return bool
     * @throws \Exception
     */
    public function changeIsCustom(EntityType $entityType, $isCustom)
    {
        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->container->get("attribute_context");
        }
        if (empty($this->attributeSetContext)) {
            $this->attributeSetContext = $this->container->get("attribute_set_context");
        }
        if (empty($this->attributeGroupContext)) {
            $this->attributeGroupContext = $this->container->get("attribute_group_context");
        }
        if (empty($this->pageContext)) {
            $this->pageContext = $this->container->get("page_context");
        }
        if (empty($this->pageBlockContext)) {
            $this->pageBlockContext = $this->container->get("page_block_context");
        }
        if (empty($this->listViewContext)) {
            $this->listViewContext = $this->container->get("list_view_context");
        }
        if (empty($this->navigationLinkContext)) {
            $this->navigationLinkContext = $this->container->get("navigation_link_context");
        }
        if (empty($this->blockManager)) {
            $this->blockManager = $this->container->get('block_manager');
        }
        if (empty($this->databaseManager)) {
            $this->databaseManager = $this->container->get('database_manager');
        }
        if (empty($this->privilegeManager)) {
            $this->privilegeManager = $this->container->get('privilege_manager');
        }
        if (empty($this->pageManager)) {
            $this->pageManager = $this->container->get('page_manager');
        }
        if (empty($this->listViewManager)) {
            $this->listViewManager = $this->container->get('list_view_manager');
        }

        $pages = $this->pageContext->getBy(array('entityType' => $entityType));

        $this->pageManager = $this->container->get('page_manager');
        /** @var Page $page */
        foreach ($pages as $page) {
            $page->setIsCustom($isCustom);
            $this->pageManager->save($page);
        }

        $pageBlocks = $this->pageBlockContext->getBy(array('entityType' => $entityType));

        /** @var PageBlock $pageBlock */
        foreach ($pageBlocks as $pageBlock) {
            $pageBlock->setIsCustom($isCustom);
            $this->blockManager->save($pageBlock);
        }

        $listViews = $this->listViewContext->getBy(array('entityType' => $entityType));

        /** @var ListView $listView */
        foreach ($listViews as $listView) {
            $listView->setIsCustom($isCustom);
            $this->listViewManager->saveListView($listView);
        }

        $attributes = $this->attributeContext->getBy(array('entityType' => $entityType));

        /** @var Attribute $attribute */
        foreach ($attributes as $attribute) {
            $attribute->setIsCustom($isCustom);
            $this->saveAttribute($attribute);
        }

        $attributeSets = $this->attributeSetContext->getBy(array('entityType' => $entityType));

        /** @var AttributeSet $attributeSet */
        foreach ($attributeSets as $attributeSet) {
            $attributeSet->setIsCustom($isCustom);
            $this->saveAttributeSet($attributeSet);

            $attributeGroups = $this->attributeGroupContext->getBy(array('attributeSetId' => $attributeSet));

            /** @var AttributeGroup $attributeGroup */
            foreach ($attributeGroups as $attributeGroup) {
                $attributeGroup->setIsCustom($isCustom);
                $this->saveAttributeGroup($attributeGroup);
            }
        }

        $entityType->setIsCustom($isCustom);
        $this->saveEntityType($entityType);

        return true;
    }

    public function deleteBundleEntityTypes($bundle)
    {
        if (empty($this->entityTypeContext)) {
            $this->entityTypeContext = $this->container->get("entity_type_context");
        }

        /**@var \AppBundle\Entity\EntityType $entityType */
        $entityTypes = $this->entityTypeContext->getAllItems();

        foreach ($entityTypes as $entityType) {
            if ($entityType->getBundle() == $bundle) {
                if (!$this->deleteEntityType($entityType)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param UserEntity $userEntity
     * @return bool|UserEntity
     */
    public function saveUser(UserEntity $userEntity)
    {
        if (empty($this->usersContext)) {
            $this->usersContext = $this->container->get('user_entity_context');
        }

        $userEntity = $this->usersContext->save($userEntity);

        return $userEntity;
    }

    /**
     * @param UserEntity $userEntity
     * @return bool|UserEntity
     */
    public function deleteUser(UserEntity $userEntity)
    {
        if(empty($userEntity)){
            throw new \Exception("Empty user");
        }
        elseif ($userEntity->getUsername() == "system"){
            throw new \Exception("Cannot delete system");
        }

        if (empty($this->usersContext)) {
            $this->usersContext = $this->container->get('user_entity_context');
        }
        if (empty($this->userRoleContext)) {
            $this->userRoleContext = $this->container->get('user_role_entity_context');
        }
        if (empty($this->privilegeManager)) {
            $this->privilegeManager = $this->container->get('privilege_manager');
        }

        $userRoles = $this->userRoleContext->getBy(array("userEntity" => $userEntity));

        foreach ($userRoles as $userRole) {
            $this->privilegeManager->deleteUserRole($userRole);
        }

        $this->usersContext->delete($userEntity);

        return true;
    }

    /**
     * @param $id
     * @return |null
     */
    public function getRoleById($id)
    {

        $etRole = $this->entityManager->getEntityTypeByCode("role");

        return $this->entityManager->getEntityByEntityTypeAndId($etRole, $id);
    }

    /**
     * @param RoleEntity $role
     * @param CoreUserEntity $coreUser
     * @return CoreUserRoleLinkEntity
     */
    public function createRoleUser(RoleEntity $role, CoreUserEntity $coreUser)
    {

        /** @var CoreUserRoleLinkEntity $roleUser */
        $roleUser = $this->entityManager->getNewEntityByAttributSetName("core_user_role_link");

        $roleUser->setRole($role);
        $roleUser->setCoreUser($coreUser);

        $this->entityManager->saveEntityWithoutLog($roleUser);

        return $roleUser;
    }

    /**
     * @param CoreUserEntity $coreUser
     * @param $data
     * @return CoreUserEntity
     */
    public function updateCoreUser(CoreUserEntity $coreUser, $data)
    {

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($coreUser, $setter)) {
                $coreUser->$setter($value);
            }
        }

        $this->entityManager->saveEntity($coreUser);

        return $coreUser;
    }

    /**
     * @param $data
     * @return UserEntity|bool
     */
    public function createUpdateUser($data)
    {
        if (empty($this->usersContext)) {
            $this->usersContext = $this->container->get('user_entity_context');
        }

        $attributeSet = $this->entityManager->getAttributeSetByCode('core_user');

        if (isset($data["id"]) && !empty($data["id"])) {
            $entity = $this->usersContext->getById($data["id"]);
        } else {
            $entity = new UserEntity();
            $entity->setEnabled(true);
        }

        $entity->setUsername($data["username"]);
        $entity->setEmail($data["email"]);
        $entity->setEntityType($attributeSet->getEntityType());
        $entity->setAttributeSet($attributeSet);
        $entity->setGoogleAuthenticatorSecret($data["google_authenticator_secret"]);
        $entity->setRoles(array($data["system_role"]));
        $entity->setEntityStateId(1);
        $entity->setLocked(null);
        if ($data["system_role"] == "ROLE_ADMIN") {
            $entity->setEntityStateId(2);
        }

        if (isset($data["password"]) && !empty($data["password"])) {
            $entity = $this->setUserPassword($entity, $data["password"]);
        }

        try {
            $this->saveUser($entity);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return $entity;
    }

    /**
     * @param $user
     * @param $plaintextPassword
     * @return UserInterface
     */
    public function setUserPassword($user, $plaintextPassword)
    {
        $encoderFactory = $this->container->get('security.encoder_factory');

        $hash = $encoderFactory->getEncoder($user)->encodePassword($plaintextPassword, $user->getSalt());
        $user->setPassword($hash);

        return $user;
    }

    /**
     * @param $entityType
     * @return |null
     */
    public function getDefaultAttributeSet($entityType)
    {
        if (empty($this->attributeSetContext)) {
            $this->attributeSetContext = $this->container->get("attribute_set_context");
        }

        return $this->attributeSetContext->getOneBy(array("entityTypeId" => $entityType), array("id" => "desc"));
    }

    /**
     * @param $attributeSet
     * @return |null
     */
    public function getDefaultAttributeGroup($attributeSet)
    {
        if (empty($this->attributeGroupContext)) {
            $this->attributeGroupContext = $this->container->get("attribute_group_context");
        }

        return $this->attributeGroupContext->getOneBy(array("attributeSetId" => $attributeSet), array("id" => "desc"));
    }


    /**
     * @param $p
     * @return bool
     */
    public function addAttributeToEntityType($p)
    {
        $attribute = new Attribute();

        $attribute->setFrontendLabel($p["frontendLabel"]);
        $attribute->setFrontendInput($p["frontendInput"]);
        $attribute->setFrontendType($p["frontendType"]);
        $attribute->setFrontendHidden($p["frontendHidden"]);
        $attribute->setReadOnly($p["readOnly"]);
        $attribute->setFrontendDisplayOnNew($p["frontendDisplayOnNew"]);
        $attribute->setFrontendDisplayOnUpdate($p["frontendDisplayOnUpdate"]);
        $attribute->setFrontendDisplayOnView($p["frontendDisplayOnView"]);
        $attribute->setFrontendDisplayOnPreview($p["frontendDisplayOnPreview"]);
        $attribute->setAttributeCode($p["attributeCode"]);
        $attribute->setEntityType($p["entityType"]);
        $attribute->setBackendModel($p["backendModel"]);
        $attribute->setBackendType($p["backendType"]);
        $attribute->setBackendTable($p["backendTable"]);
        $attribute->setIsCustom($p["isCustom"]);

        if (isset($p["folder"]) && !empty($p["folder"])) {
            $attribute->setFolder($p["folder"]);
        }

        $attribute = $this->saveAttribute($attribute);
        if (empty($attribute)) {
            return false;
        }

        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->getDefaultAttributeSet($p["entityType"]);

        $entityAttribute = new EntityAttribute();
        $entityAttribute->setEntityType($p["entityType"]);
        $entityAttribute->setAttributeSet($attributeSet);
        $entityAttribute->setAttributeGroup($this->getDefaultAttributeGroup($attributeSet));
        $entityAttribute->setAttribute($attribute);

        $entityAttribute = $this->saveEntityAttribute($entityAttribute);
        if (empty($entityAttribute)) {
            return false;
        }

        return true;
    }

    /** @return bool
     * @var EntityType $entityType
     */
    public function addDocumentAttributesToEntityType($entityType)
    {
        $flag = $this->addAttributeToEntityType(
            array(
                "frontendLabel" => "File",
                "frontendInput" => "file",
                "frontendType" => "file",
                "frontendHidden" => 0,
                "readOnly" => 1,
                "frontendDisplayOnNew" => 1,
                "frontendDisplayOnUpdate" => 1,
                "frontendDisplayOnView" => 1,
                "frontendDisplayOnPreview" => 1,
                "attributeCode" => "file",
                "entityType" => $entityType,
                "backendModel" => $entityType->getEntityModel(),
                "backendType" => "varchar",
                "backendTable" => $entityType->getEntityTable(),
                "folder" => "/Documents/" . $entityType->getEntityTypeCode() . "/",
                "isCustom" => $entityType->getIsCustom()
            )
        );

        $flag = $this->addAttributeToEntityType(
            array(
                "frontendLabel" => "Source",
                "frontendInput" => "text",
                "frontendType" => "text",
                "frontendHidden" => 0,
                "readOnly" => 1,
                "frontendDisplayOnNew" => 1,
                "frontendDisplayOnUpdate" => 1,
                "frontendDisplayOnView" => 1,
                "frontendDisplayOnPreview" => 1,
                "attributeCode" => "file_source",
                "entityType" => $entityType,
                "backendModel" => $entityType->getEntityModel(),
                "backendType" => "varchar",
                "backendTable" => $entityType->getEntityTable(),
                "folder" => "/Documents/" . $entityType->getEntityTypeCode() . "/",
                "isCustom" => $entityType->getIsCustom()
            )
        );

        $flag = $this->addAttributeToEntityType(
            array(
                "frontendLabel" => "Filename",
                "frontendInput" => "text",
                "frontendType" => "text",
                "frontendHidden" => 0,
                "readOnly" => 1,
                "frontendDisplayOnNew" => 1,
                "frontendDisplayOnUpdate" => 1,
                "frontendDisplayOnView" => 1,
                "frontendDisplayOnPreview" => 1,
                "attributeCode" => "filename",
                "entityType" => $entityType,
                "backendModel" => $entityType->getEntityModel(),
                "backendType" => "varchar",
                "backendTable" => $entityType->getEntityTable(),
                "isCustom" => $entityType->getIsCustom()
            )
        );

        $flag = $this->addAttributeToEntityType(
            array(
                "frontendLabel" => "File type",
                "frontendInput" => "text",
                "frontendType" => "text",
                "frontendHidden" => 0,
                "readOnly" => 1,
                "frontendDisplayOnNew" => 1,
                "frontendDisplayOnUpdate" => 1,
                "frontendDisplayOnView" => 1,
                "frontendDisplayOnPreview" => 1,
                "attributeCode" => "file_type",
                "entityType" => $entityType,
                "backendModel" => $entityType->getEntityModel(),
                "backendType" => "varchar",
                "backendTable" => $entityType->getEntityTable(),
                "isCustom" => $entityType->getIsCustom()
            )
        );

        $flag = $this->addAttributeToEntityType(
            array(
                "frontendLabel" => "Size",
                "frontendInput" => "text",
                "frontendType" => "text",
                "frontendHidden" => 0,
                "readOnly" => 1,
                "frontendDisplayOnNew" => 1,
                "frontendDisplayOnUpdate" => 1,
                "frontendDisplayOnView" => 1,
                "frontendDisplayOnPreview" => 1,
                "attributeCode" => "size",
                "entityType" => $entityType,
                "backendModel" => $entityType->getEntityModel(),
                "backendType" => "varchar",
                "backendTable" => $entityType->getEntityTable(),
                "isCustom" => $entityType->getIsCustom()
            )
        );

        if (stripos($entityType->getEntityTypeCode(), "image") !== false) {
            $flag = $this->addAttributeToEntityType(
                array(
                    "frontendLabel" => "Alt",
                    "frontendInput" => "text",
                    "frontendType" => "text",
                    "frontendHidden" => 0,
                    "readOnly" => 1,
                    "frontendDisplayOnNew" => 1,
                    "frontendDisplayOnUpdate" => 1,
                    "frontendDisplayOnView" => 1,
                    "frontendDisplayOnPreview" => 1,
                    "attributeCode" => "alt",
                    "entityType" => $entityType,
                    "backendModel" => $entityType->getEntityModel(),
                    "backendType" => "varchar",
                    "backendTable" => $entityType->getEntityTable(),
                    "isCustom" => $entityType->getIsCustom()
                )
            );
            $flag = $this->addAttributeToEntityType(
                array(
                    "frontendLabel" => "Title",
                    "frontendInput" => "text",
                    "frontendType" => "text",
                    "frontendHidden" => 0,
                    "readOnly" => 1,
                    "frontendDisplayOnNew" => 1,
                    "frontendDisplayOnUpdate" => 1,
                    "frontendDisplayOnView" => 1,
                    "frontendDisplayOnPreview" => 1,
                    "attributeCode" => "title",
                    "entityType" => $entityType,
                    "backendModel" => $entityType->getEntityModel(),
                    "backendType" => "varchar",
                    "backendTable" => $entityType->getEntityTable(),
                    "isCustom" => $entityType->getIsCustom()
                )
            );
            $flag = $this->addAttributeToEntityType(
                array(
                    "frontendLabel" => "Selected",
                    "frontendInput" => "checkbox",
                    "frontendType" => "checkbox",
                    "frontendHidden" => 0,
                    "readOnly" => 1,
                    "frontendDisplayOnNew" => 1,
                    "frontendDisplayOnUpdate" => 1,
                    "frontendDisplayOnView" => 1,
                    "frontendDisplayOnPreview" => 1,
                    "attributeCode" => "selected",
                    "entityType" => $entityType,
                    "backendModel" => $entityType->getEntityModel(),
                    "backendType" => "bool",
                    "backendTable" => $entityType->getEntityTable(),
                    "isCustom" => $entityType->getIsCustom()

                )
            );
            $flag = $this->addAttributeToEntityType(
                array(
                    "frontendLabel" => "Order",
                    "frontendInput" => "integer",
                    "frontendType" => "integer",
                    "frontendHidden" => 0,
                    "readOnly" => 1,
                    "frontendDisplayOnNew" => 1,
                    "frontendDisplayOnUpdate" => 1,
                    "frontendDisplayOnView" => 1,
                    "frontendDisplayOnPreview" => 1,
                    "attributeCode" => "ord",
                    "entityType" => $entityType,
                    "backendModel" => $entityType->getEntityModel(),
                    "backendType" => "integer",
                    "backendTable" => $entityType->getEntityTable(),
                    "isCustom" => $entityType->getIsCustom()

                )
            );
        }

        return ($flag);
    }

    public function addDocumentToEntityType($entityType)
    {
        if (empty($this->databaseManager)) {
            $this->databaseManager = $this->container->get('database_manager');
        }

        $fileAttribute = new Attribute();
        $fileAttribute->setFrontendLabel("File");
        $fileAttribute->setFrontendInput("file");
        $fileAttribute->setFrontendType("file");
        $fileAttribute->setFrontendHidden(0);
        $fileAttribute->setFrontendDisplayOnNew(1);
        $fileAttribute->setFrontendDisplayOnUpdate(1);
        $fileAttribute->setFrontendDisplayOnView(1);
        $fileAttribute->setFrontendDisplayOnPreview(1);
        $fileAttribute->setAttributeCode("file");
        $fileAttribute->setEntityType($entityType);
        $fileAttribute->setBackendModel($entityType->getEntityModel());
        $fileAttribute->setBackendType("varchar");
        $fileAttribute->setBackendTable($entityType->getEntityTable());
        $fileAttribute->setFolder("/Documents/" . $entityType->getEntityTypeCode() . "/");

        $fileAttribute = $this->saveAttribute($fileAttribute);

        if (empty($fileAttribute)) {
            return false;
        }
        $attributeSet = $this->getDefaultAttributeSet($entityType);

        $entityAttribute = new EntityAttribute();
        $entityAttribute->setEntityType($entityType);
        $entityAttribute->setAttributeSet($attributeSet);
        $entityAttribute->setAttributeGroup($this->getDefaultAttributeGroup($attributeSet));
        $entityAttribute->setAttribute($fileAttribute);

        $entityAttribute = $this->saveEntityAttribute($entityAttribute);
        if (empty($entityAttribute)) {
            return false;
        }

        $this->databaseManager->addDocumentColumnsToTable($entityType);

        return true;
    }

    function generateDoctrineXML($entityType, $generate_fields = false)
    {

        $table = $entityType->getEntityTable();
        $backend_model = $entityType->getEntityModel();

        $parts = explode("_", $table);
        $template_name = array_pop($parts);

        if ($entityType->getIsDocument()) {
            $template_name = "document_entity";
        }

        $base_entity = array_map('ucfirst', $parts);
        $base_entity = implode("", $base_entity);
        $attributesContent = "";

        /**
         * Default settings
         */
        $doctrineCustom = '';

        if ($generate_fields) {

            if (empty($this->attributeContext)) {
                $this->attributeContext = $this->container->get("attribute_context");
            }

            $baseBundlePath = $this->container->get('kernel')->locateResource('@' . $entityType->getBundle()) . 'Resources/config/custom/';

            /** Check if base extend file exists */
            if (file_exists($baseBundlePath . "doctrine/" . $base_entity . "EntityExtensionBase.orm.xml")) {
                $content = file_get_contents($baseBundlePath . "doctrine/" . $base_entity . "EntityExtensionBase.orm.xml");
                $doctrineCustom = $this->get_string_between($content, '###CLASS_START', '###CLASS_END');
            }

            $bundles = $this->prepareBundles();

            /** @var Check if custom extension exists */
            foreach ($bundles as $bundle) {
                $bundlePath = $this->container->get('kernel')->locateResource('@' . $bundle) . 'Resources/config/custom/';
                if (file_exists($bundlePath . "doctrine/" . $base_entity . "EntityExtension.orm.xml")) {
                    $content = file_get_contents($bundlePath . "doctrine/" . $base_entity . "EntityExtension.orm.xml");
                    $content = $this->get_string_between($content, '###CLASS_START', '###CLASS_END');

                    $doctrineCustom .= $content;
                    break;
                }
            }

            /** Fallback na database */
            if (empty($doctrineCustom) && !empty($entityType->getDoctrineCustom())) {
                $doctrineCustom = $entityType->getDoctrineCustom();
            }

            $entityAttributes = $this->attributeContext->getBy(array('backendTable' => $table), array());
            if (!empty($entityAttributes)) {
                $attributeFilename = $this->templateFolder . "attribute.orm.xml";
                $lookupFilename = $this->templateFolder . "lookup.orm.xml";

                if (!file_exists($attributeFilename)) {
                    return false;
                }
                $attributeContent = file_get_contents($attributeFilename);

                /**@var Attribute $entityAttribute */
                foreach ($entityAttributes as $entityAttribute) {
                    if ($entityAttribute->getAttributeCode() == "id") {
                        continue;
                    }
                    if ($entityAttribute->getAttributeCode() == "created") {
                        continue;
                    }
                    if ($entityAttribute->getAttributeCode() == "modified") {
                        continue;
                    }
                    if ($entityAttribute->getAttributeCode() == "created_by") {
                        continue;
                    }
                    if ($entityAttribute->getAttributeCode() == "modified_by") {
                        continue;
                    }

                    $lookupContent = "";
                    $entityAttributeCode = $entityAttribute->getAttributeCode();
                    $parts = explode("_", $entityAttributeCode);
                    $entityAttributeCode = array_map('ucfirst', $parts);
                    $entityAttributeCode = implode("", $entityAttributeCode);
                    $entityAttributeCode = lcfirst($entityAttributeCode);

                    $backendType = $entityAttribute->getBackendType();
                    $frontendType = $entityAttribute->getFrontendType();

                    if ($backendType == "varchar" || $backendType == "ckeditor") {
                        $backendType = "string";
                    }

                    if ($backendType == "bool") {
                        $backendType = "boolean";
                    }
                    if ($backendType == "lookup" &&
                        (strpos($frontendType, 'autocomplete') !== false ||
                            $frontendType == 'select' ||
                            strpos($frontendType, 'lookup_image') !== false)) {
                        $backendType = "integer";
                    } elseif ($backendType == "option") {
                        $backendType = "integer";
                    }

                    if (strpos($frontendType, 'multiselect') !== false) {
                        $lookupEntityType = $entityAttribute->getLookupAttribute()->getLookupEntityType();

                        if(empty($lookupEntityType)){
                            $lookupEntityType = $entityAttribute->getLookupEntityType();
                        }

                        $lookupEntityTypeBundle = $lookupEntityType->getBundle();

                        $multipleFilename = $this->templateFolder . "multiple.orm.xml";
                        $multipleContent = file_get_contents($multipleFilename);
                        $attributesContent .= sprintf(
                            $multipleContent,
                            $entityAttributeCode,
                            $lookupEntityTypeBundle . "\\Entity\\" . $lookupEntityType->getEntityModel(),
                            $entityAttribute->getLookupEntityType()->getEntityTable(),
                            $entityType->getEntityTypeCode() . "_id",
                            $entityAttribute->getLookupAttribute()->getAttributeCode(),
                            $entityAttribute->getAttributeCode()
                        );
                    } else {
                        $attributesContent .= sprintf($attributeContent, $entityAttributeCode, $backendType, $entityAttribute->getAttributeCode());


                        if ($entityAttribute->getLookupEntityType() != null) {
                            $lookupEntityType = $entityAttribute->getLookupEntityType();
                            $lookupEntityTypeBundle = $lookupEntityType->getBundle();


                            $attributesContent .= sprintf(
                                file_get_contents($lookupFilename),
                                $lookupEntityTypeBundle . "\\Entity\\" . $lookupEntityType->getEntityModel(),
                                $entityAttribute->getAttributeCode(),
                                Inflector::camelize(str_replace("_id", "", $entityAttribute->getAttributeCode()))
                            );
                        }
                    }
                }
            }
        }

        $filename = $this->templateFolder . $template_name . ".orm.xml";

        if (!file_exists($filename)) {
            return false;
        }

        $content = file_get_contents($filename);
        $content = sprintf($content, $table, $entityType->getBundle(), $base_entity, $backend_model, $attributesContent, $doctrineCustom);

        if ($this->checkIfBundleExists($entityType->getBundle())) {
            if (!file_exists($this->container->get('kernel')->locateResource('@' . $entityType->getBundle()) . '/Resources/config/doctrine/')) {
                mkdir($this->container->get('kernel')->locateResource('@' . $entityType->getBundle()) . '/Resources/config/doctrine/', 0777, true);
            }

            $destinationFilename = $this->container->get('kernel')->locateResource('@' . $entityType->getBundle() . '/Resources/config/doctrine/') . $backend_model . ".orm.xml";

            if (file_exists($destinationFilename)) {
                unlink($destinationFilename);
            }

            if (file_put_contents($destinationFilename, $content)) {
                return true;
            }
        } else {
            return true;
        }

        return false;
    }

    function generateEntityClasses($entityType, $generate_fields = false)
    {
        $table = $entityType->getEntityTable();
        $backend_model = $entityType->getEntityModel();

        $parts = explode("_", $table);
        $template_name = array_pop($parts);

        if ($entityType->getIsDocument()) {
            $template_name = "document_entity";
        }

        $base_entity = $entityType->getEntityModel();
        $attributesContent = "";

        /**
         * Default settings
         */
        $useClasses = 'use AppBundle\Abstracts\AbstractEntity;';
        $extendClass = 'AbstractEntity';
        $entityCustom = '';

        if ($generate_fields) {

            if (empty($this->attributeContext)) {
                $this->attributeContext = $this->container->get("attribute_context");
            }

            $baseBundlePath = $this->container->get('kernel')->locateResource('@' . $entityType->getBundle()) . '/Resources/config/custom/';

            if (!empty($entityType->getEntityUseClasses())) {
                $useClasses = $entityType->getEntityUseClasses();
            }
            if (!empty($entityType->getEntityExtendClass())) {
                $extendClass = $entityType->getEntityExtendClass();
            }

            /** Check if base extend file exists */
            if (file_exists($baseBundlePath . "entity/" . $base_entity . "ExtensionBase.php")) {
                $content = file_get_contents($baseBundlePath . "entity/" . $base_entity . "ExtensionBase.php");
                $entityCustom = $this->get_string_between($content, '###CLASS_START', '###CLASS_END');
            }

            $bundles = $this->prepareBundles();

            /** @var Check if custom extension exists */
            foreach ($bundles as $bundle) {
                $bundlePath = $this->container->get('kernel')->locateResource('@' . $bundle) . 'Resources/config/custom/';
                if (file_exists($bundlePath . "entity/" . $base_entity . "Extension.php")) {
                    $content = file_get_contents($bundlePath . "entity/" . $base_entity . "Extension.php");
                    $content = $this->get_string_between($content, '###CLASS_START', '###CLASS_END');
                    $entityCustom .= $content;
                    break;
                }
            }

            /** Fallback na database */
            if (empty($entityCustom) && !empty($entityType->getEntityCustom())) {
                $entityCustom = $entityType->getEntityCustom();
            }

            $entityAttributes = $this->attributeContext->getBy(array('backendTable' => $table), array());
            if (!empty($entityAttributes)) {
                $attributeFilename = $this->templateFolder . "attribute.php.tpl";
                $lookupFilename = $this->templateFolder . "lookup.php.tpl";

                if (!file_exists($attributeFilename)) {
                    return false;
                }
                $attributeContent = file_get_contents($attributeFilename);

                foreach ($entityAttributes as $entityAttribute) {
                    if ($entityAttribute->getAttributeCode() == "id") {
                        continue;
                    }
                    if ($entityAttribute->getAttributeCode() == "created") {
                        continue;
                    }
                    if ($entityAttribute->getAttributeCode() == "modified") {
                        continue;
                    }

                    $lookupContent = "";
                    $entityAttributeCode = $entityAttribute->getAttributeCode();
                    $parts = explode("_", $entityAttributeCode);
                    $entityAttributeCode = array_map('ucfirst', $parts);
                    $entityAttributeCode = implode("", $entityAttributeCode);

                    $entityAttributeCodeFirstLower = lcfirst($entityAttributeCode);

                    $backendType = $entityAttribute->getBackendType();
                    $frontendType = $entityAttribute->getFrontendType();

                    if ($backendType == "varchar") {
                        $backendType = "string";
                    }

                    if ($backendType == "lookup" && (strpos($frontendType, 'autocomplete') !== false ||
                            $frontendType == 'select' || strpos($frontendType, 'lookup_image') !== false)) {
                        $backendType = "integer";
                    } elseif ($backendType == "option") {
                        $backendType = "integer";
                    } elseif ($backendType == "lookup" && strpos($frontendType, 'multiselect') !== false) {
                        $backendType = "collection";
                    }

                    $attributesContent .= sprintf($attributeContent, $entityAttributeCode, $backendType, $entityAttributeCodeFirstLower);

                    if ($entityAttribute->getLookupEntityType() != null &&
                        (strpos($frontendType, 'autocomplete') !== false || $frontendType == 'select' || strpos($frontendType, 'lookup_image') !== false)) {
                        $fieldName = Inflector::camelize(str_replace("_id", "", $entityAttribute->getAttributeCode()));


                        $attributesContent .= sprintf(
                            file_get_contents($lookupFilename),
                            ucfirst($fieldName),
                            ucfirst($fieldName),
                            $fieldName
                        );
                    }
                }
            }
        }

        $filename = $this->templateFolder . $template_name . ".php.tpl";

        if (!file_exists($filename)) {
            return false;
        }

        $content = file_get_contents($filename);
        $content = sprintf($content, $entityType->getBundle(), $base_entity, $attributesContent, $entityCustom, $useClasses, $extendClass);

        if ($this->checkIfBundleExists($entityType->getBundle())) {
            if (!file_exists($this->container->get('kernel')->locateResource('@' . $entityType->getBundle()) . '/Entity/')) {
                mkdir($this->container->get('kernel')->locateResource('@' . $entityType->getBundle()) . '/Entity/', 0777, true);
            }

            $destinationFilename = $this->container->get('kernel')->locateResource('@' . $entityType->getBundle() . '/Entity/') . $backend_model . ".php";

            if (file_exists($destinationFilename)) {
                unlink($destinationFilename);
            }

            if (file_put_contents($destinationFilename, $content)) {
                return true;
            }
        } else {
            return true;
        }

        return false;
    }

    public function checkIfBundleExists($bundle)
    {

        $bundles = $this->container->getParameter('kernel.bundles');

        if (array_key_exists($bundle, $bundles)) {
            return true;
        }

        return false;
    }

    public function getBusinessBundles()
    {
        /**
         * List all bundles
         */
        $bundles = $this->container->getParameter('kernel.bundles');
        foreach ($bundles as $key => $bundle) {
            if (strpos(strtolower($key), 'business') !== false || strtolower($key) == 'appbundle') {
                continue;
            }
            unset($bundles[$key]);
        }

        return $bundles;
    }

    public function deleteFiles(EntityType $entityType)
    {

        $backendTable = $entityType->getEntityTable();
        $backendModel = explode("_", $backendTable);
        $backendModel = array_map('ucfirst', $backendModel);
        $backendModel = implode("", $backendModel);

        if ($this->checkIfBundleExists($entityType->getBundle())) {
            $destinationFolderORM = $this->container->get('kernel')->locateResource('@' . $entityType->getBundle() . '/Resources/config/doctrine/') . $backendModel . ".orm.xml";
            if (file_exists($destinationFolderORM)) {
                unlink($destinationFolderORM);
            }
            $destinationFilename = $this->container->get('kernel')->locateResource('@' . $entityType->getBundle() . '/Entity/') . $backendModel . ".php";
            if (file_exists($destinationFilename)) {
                unlink($destinationFilename);
            }
        }
    }

    public function exportBundleObjects($bundle)
    {
        if (empty($this->entityTypeContext)) {
            $this->entityTypeContext = $this->container->get("entity_type_context");
        }
        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->container->get("attribute_context");
        }
        if (empty($this->attributeSetContext)) {
            $this->attributeSetContext = $this->container->get("attribute_set_context");
        }
        if (empty($this->attributeGroupContext)) {
            $this->attributeGroupContext = $this->container->get("attribute_group_context");
        }
        if (empty($this->pageContext)) {
            $this->pageContext = $this->container->get("page_context");
        }
        if (empty($this->pageBlockContext)) {
            $this->pageBlockContext = $this->container->get("page_block_context");
        }
        if (empty($this->listViewContext)) {
            $this->listViewContext = $this->container->get("list_view_context");
        }

        $bundleObjects = array();
        $entityTypeArray = array();
        $attributeSetsArray = array();

        $attributeCodes = array();

        /**@var \AppBundle\Entity\EntityType $entityType */
        $entityTypes = $this->entityTypeContext->getAllItems();
        foreach ($entityTypes as $entityType) {
            if ($entityType->getBundle() == $bundle) {
                $entityTypeArray[] = $entityType->convertToArray();

                $attributes = $this->attributeContext->getAttributesByEntityType($entityType);
                foreach ($attributes as $attribute) {
                    $attributeCodes[] = $attribute->getAttributeCode();
                }

                foreach ($attributes as $attribute) {
                    $attributesArray[] = $attribute->convertToArray();

                    if ($attribute->getLookupAttribute() != null) {
                        /**@var \AppBundle\Entity\Attribute $lookupAttribute */
                        $lookupAttribute = $attribute->getLookupAttribute();


                        if (!in_array($lookupAttribute->getAttributeCode(), $attributeCodes) && $lookupAttribute->getEntityType()->getBundle() == $bundle) {
                            array_unshift($attributesArray, $lookupAttribute->convertToArray());
                            $attributeCodes[] = $lookupAttribute->getAttributeCode();
                        }
                    }

                }

                $attributeSets = $this->attributeSetContext->getAttributeSetsByEntityType($entityType);
                foreach ($attributeSets as $attributeSet) {
                    $attributeSetsArray[] = $attributeSet->convertToArray();

                    $attributeGroups = $this->attributeGroupContext->getAttributesGroupsBySet($attributeSet);
                    foreach ($attributeGroups as $attributeGroup) {
                        $attributeGroupsArray[] = $attributeGroup->convertToArray();
                    }

                }

                $listViews = $this->listViewContext->getListViewsByEntityType($entityType);
                foreach ($listViews as $listView) {
                    $listViewsArray[] = $listView->convertToArray();
                }

                $pageBlocks = $this->pageBlockContext->getBy(array("bundle" => $bundle));
                foreach ($pageBlocks as $pageBlock) {
                    $itemArray = $pageBlock->convertToArray();

                    if ($itemArray["type"] == "list_view") {
                        $relatedItem = $this->listViewContext->getById($pageBlock->getRelatedId());
                    }

                    if ($itemArray["type"] == "attribute_group" || $itemArray["type"] == "custom_html") {
                        $relatedItem = $this->attributeGroupContext->getById($pageBlock->getRelatedId());
                    }

                    if ($relatedItem != null) {
                        $relatedUid = $relatedItem->getUid();
                    }

                    $itemArray["relatedUid"] = $relatedUid;
                    $pageBlocksArray[] = $itemArray;
                }

                $pages = $this->pageContext->getBy(array("bundle" => $bundle));
                foreach ($pages as $page) {
                    $pagesArray[] = $page->convertToArray();
                }
            }
        }

        $bundleObjects["entityTypes"] = $entityTypeArray;
        $bundleObjects["attributes"] = $attributesArray;
        $bundleObjects["attributeSets"] = $attributeSetsArray;
        $bundleObjects["attributeGroups"] = $attributeGroupsArray;
        $bundleObjects["listViews"] = $listViewsArray;
        $bundleObjects["pageBlocks"] = $pageBlocksArray;
        $bundleObjects["pages"] = $pagesArray;


        return json_encode($bundleObjects, JSON_PRETTY_PRINT);
    }

    /**
     * @return array
     */
    public function prepareBundles()
    {

        $ret = array();

        $bundles = $this->container->getParameter('kernel.bundles');

        foreach ($bundles as $key => $value) {
            if (stripos($key, "business") !== false || stripos($key, "app") !== false) {
                $ret[] = $key;
            }
        }

        return array_reverse($ret);
    }

    function get_string_between($string, $start, $end)
    {
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) return '';
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    /**
     * @param $bundleName
     * @return boolean
     */
    public function rebuildBundleIndexes($bundleName)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $databaseName = $this->shapeDb = $_ENV["DATABASE_NAME"];

        $bundleIndexesArray = json_decode(file_get_contents($_ENV["WEB_PATH"] . "/../src/{$bundleName}/Resources/config/custom/indexes/indexes.json"), true);

        if (empty($bundleIndexesArray)) {
            print "INVALID JSON: {$_ENV["WEB_PATH"]}/../src/{$bundleName}/Resources/config/custom/indexes/indexes.json";
            die;
        }

        if (!empty($bundleIndexesArray)) {

            foreach ($bundleIndexesArray as $indexType => $indexTypeArray) {

                foreach ($indexTypeArray as $tableName => $attributeNames) {

                    $tableIndexAttributesArray = explode(",", $attributeNames);

                    //print("\n{$bundleName} => {$tableName}!\n");

                    // Check if given table exists, query should return 1 if table exists
                    $q = "SELECT COUNT(*) AS table_exists
                            FROM ssinformation.TABLES
                            WHERE TABLE_SCHEMA = '{$databaseName}' AND TABLE_NAME = '{$tableName}';";
                    try {
                        $qResult = $this->databaseContext->getAll($q);

                        if ($qResult[0]["table_exists"] == 0) {
                            print("Table {$tableName} does't exist!\n");
                            continue;
//                            throw new \Exception("Table {$tableName} does't exist!");
                        }
                    } catch (\Exception $e) {
                        print("Table {$tableName} does't exist!\n");
                        continue;
//                        throw new \Exception("Table {$tableName} does't exist!");
                        //
                    }

                    // Get all table columns
                    $tableAttributesArray = [];
                    $q = "SHOW COLUMNS FROM {$tableName};";
                    $qResult = $this->databaseContext->getAll($q);
                    foreach ($qResult as $attribute) {
                        $tableAttributesArray[$attribute["Field"]] = true;
                    }

                    // Get all table indexes
                    $tableIndexesArray = [];
                    $tableIndexNamesArray = [];
                    if ($indexType == "index") {
                        $q = "SHOW INDEX FROM {$tableName} WHERE Non_unique = 1;";
                    } else if ($indexType == "unique") {
                        $q = "SHOW INDEX FROM {$tableName} WHERE Non_unique = 0;";
                    }
                    $qResult = $this->databaseContext->getAll($q);
                    foreach ($qResult as $attribute) {
                        $tableIndexesArray[$attribute["Column_name"]] = true;
                        $tableIndexNamesArray[$attribute["Key_name"]] = true;
                    }

                    foreach ($tableIndexAttributesArray as $attribute) {


                        // Check if column exists within the table
                        if (!isset($tableAttributesArray[$attribute])) {
                            print("Attribute '{$attribute}' doesn't exist within the '{$tableName}' table.\n");
                            continue;
//                            throw new \Exception("Attribute '{$attribute}' doesn't exist within the '{$tableName}' table.");
                        }

                        if (isset($tableIndexesArray[$attribute])) {
                            //print("NOT ADDED - Table '{$tableName}' already contains index '{$attribute}'!\n");
                            continue;
                        }

                        if ($indexType == "index") {
                            $keyName = "{$tableName}_{$attribute}";

                            if(strlen($keyName) > 64){

                                $tmpTableName = $tableName;
                                $tmpAttribute = $attribute;

                                if(strlen($tmpTableName) > 31){
                                    $tmpTableName = substr($tmpTableName, 0, 31);
                                }
                                if(strlen($tmpAttribute) > 32){
                                    $tmpAttribute = substr($tmpAttribute, 0, 32);
                                }

                                $keyName = "{$tmpTableName}_{$tmpAttribute}";
                            }

                            if (isset($tableIndexNamesArray[$keyName])) {
                                $baseName = $keyName;
                                $i = 1;
                                while (isset($tableIndexNamesArray[$keyName])) {
                                    $keyName = $baseName . "_" . $i;
                                }
                            }

                            $insertTableIndex = "CREATE INDEX {$keyName} ON {$tableName} ({$attribute});\n";
                            $this->databaseContext->executeNonQuery($insertTableIndex);
                            $tableIndexesArray[$attribute] = true;
                            $tableIndexNamesArray[$keyName] = true;
                            print("ADDED - Added index '{$attribute}' to the '{$tableName}' table!\n");
                        } else if ($indexType == "unique") {

                            $insertTableIndex = "CREATE UNIQUE INDEX {$tableName}_{$attribute}_unique ON {$tableName} ({$attribute});\n";
                            $this->databaseContext->executeNonQuery($insertTableIndex);
                            $tableIndexesArray[$attribute] = true;
                            print("ADDED - Added unique index '{$tableName}_{$attribute}_unique' to the '{$tableName}' table!\n");
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param $attributeSetCode
     * @return bool
     * @throws \Exception
     */
    public function addUidToEntityType($attributeSetCode, $addCustom = false)
    {

        if (empty($this->attributeSetContext)) {
            $this->attributeSetContext = $this->container->get("attribute_set_context");
        }

        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->attributeSetContext->getItemByCode($attributeSetCode);

        if (empty($attributeSet)) {
            return false;
        }

        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->container->get("attribute_context");
        }

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getAttributeByCode("uid", $attributeSet->getEntityType());

        if (empty($attribute)) {
            $data = array();
            $data["frontendLabel"] = "UID";
            $data["frontendInput"] = "text";
            $data["frontendType"] = "text";
            $data["frontendModel"] = null;
            $data["frontendHidden"] = 1;
            $data["readOnly"] = 1;
            $data["frontendDisplayOnNew"] = 0;
            $data["frontendDisplayOnUpdate"] = 0;
            $data["frontendDisplayOnView"] = 0;
            $data["frontendDisplayOnPreview"] = 0;
            $data["attributeCode"] = "uid";
            $data["backendType"] = "varchar";
            $data["isCustom"] = 0;
            if ($attributeSetCode == "core_user") {
                $data["uid"] = "";
            } elseif ($attributeSetCode == "role") {
                $data["uid"] = "6011d675771af6.84784320";
            } elseif ($attributeSetCode == "core_language") {
                $data["uid"] = "6011d6922f42d2.24192384";
            } elseif ($attributeSetCode == "country") {
                $data["uid"] = "6011e3df85d572.98138375";
            } elseif ($attributeSetCode == "region") {
                $data["uid"] = "6011e3e6b26002.36780841";
            }

            $ret = $this->createAttribute($attributeSet->getEntityType(), $attributeSet, null, $data);

            if ($ret["error"]) {
                echo $ret["message"];
            }
        }

        if($addCustom){

            dump($addCustom);
            die;

            /** @var Attribute $attribute */
            $attribute = $this->attributeContext->getAttributeByCode("is_custom", $attributeSet->getEntityType());

            if (empty($attribute)) {
                $data = array();
                $data["frontendLabel"] = "Is custom";
                $data["frontendInput"] = "checkbox";
                $data["frontendType"] = "checkbox";
                $data["frontendModel"] = null;
                $data["frontendHidden"] = 0;
                $data["readOnly"] = 0;
                $data["frontendDisplayOnNew"] = 0;
                $data["frontendDisplayOnUpdate"] = 0;
                $data["frontendDisplayOnView"] = 0;
                $data["frontendDisplayOnPreview"] = 0;
                $data["attributeCode"] = "is_custom";
                $data["backendType"] = "bool";
                $data["isCustom"] = 0;

                $ret = $this->createAttribute($attributeSet->getEntityType(), $attributeSet, null, $data);

                if ($ret["error"]) {
                    echo $ret["message"];
                }
            }
        }

        if (empty($this->databaseManager)) {
            $this->databaseManager = $this->container->get("database_manager");
        }

        $this->databaseManager->createTableIfDoesntExist($attributeSet->getEntityType(), null);
        $this->generateDoctrineXML($attributeSet->getEntityType(), true);
        $this->generateEntityClasses($attributeSet->getEntityType(), true);

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        /**
         * Generate custom uid
         */
        if ($attributeSetCode == "core_user") {
            $q = "UPDATE user_entity SET uid = MD5(email);";
            $this->databaseContext->executeNonQuery($q);
        } elseif ($attributeSetCode == "role") {
            $q = "UPDATE role_entity SET uid = MD5(role_code);";
            $this->databaseContext->executeNonQuery($q);
        } elseif ($attributeSetCode == "core_language") {
            $q = "UPDATE core_language_entity SET uid = MD5(code);";
            $this->databaseContext->executeNonQuery($q);
        } elseif ($attributeSetCode == "country") {
            $q = "UPDATE country_entity SET uid = MD5(id);";
            $this->databaseContext->executeNonQuery($q);
        } elseif ($attributeSetCode == "region") {
            $q = "UPDATE region_entity SET uid = MD5(id);";
            $this->databaseContext->executeNonQuery($q);
        }
        else{
            $additional = "";
            if($addCustom){
                $additional = " , is_custom = 0 ";
            }

            $q = "UPDATE {$attributeSet->getEntityType()->getEntityTable()} SET uid = MD5(id) {$additional} WHERE uid is not null;";
            $this->databaseContext->executeNonQuery($q);
            $q = "UPDATE entity_type SET sync_content = 1 WHERE uid = '{$attributeSet->getEntityType()->getUid()}';";
            $this->databaseContext->executeNonQuery($q);

            /** @var SyncManager $syncManager */
            $syncManager = $this->container->get("sync_manager");
            $syncManager->exportCustomEntities($attributeSet->getEntityType()->getUid());
        }

        return true;
    }

    /**
     * @param $entityTypeCode
     * @param $destinationBundle
     * @return bool
     */
    public function changeBundleOfEntityType($entityTypeCode, $destinationBundle)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        /** @var EntityType $entityType */
        $entityType = $this->entityManager->getEntityTypeByCode($entityTypeCode);

        if (empty($entityType)) {
            echo "Missing entity type {$entityTypeCode}\r\n";
            return false;
        }

        if ($entityType->getBundle() == $destinationBundle) {
            echo "Bundle on entity type {$entityTypeCode} is the same as destination bundle {$destinationBundle}\r\n";
            return false;
        }

        if (!$this->checkIfBundleExists($destinationBundle)) {
            echo "Missing destination bundle {$destinationBundle}\r\n";
            return false;
        }

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        /**
         * Delete entity and doctrine files of entity_type
         */
        $baseBundlePath = $this->container->get('kernel')->locateResource('@' . $entityType->getBundle());

        if (file_exists($baseBundlePath . "Entity/" . $entityType->getEntityModel() . ".php")) {
            unlink($baseBundlePath . "Entity/" . $entityType->getEntityModel() . ".php");
        }
        if (file_exists($baseBundlePath . "Resources/config/doctrine/" . $entityType->getEntityModel() . ".orm.xml")) {
            unlink($baseBundlePath . "Resources/config/doctrine/" . $entityType->getEntityModel() . ".orm.xml");
        }

        /**
         * Delete generated json files
         */
        $this->syncManager->deleteEntityRecord("entity_type", array("id" => $entityType->getId(), "uid" => $entityType->getUid()), true);

        /**
         * Get attributes and delete json files
         */
        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->container->get("attribute_context");
        }

        $relatedEntityTypesToRegenerate = array();

        $attributes = $this->attributeContext->getAttributesByEntityType($entityType);
        if (!empty($attributes)) {
            /** @var Attribute $attribute */
            foreach ($attributes as $attribute) {
                $this->syncManager->deleteEntityRecord("attribute", array("id" => $attribute->getId(), "uid" => $attribute->getUid()), true);
            }

            $lookupAttributes = $this->attributeContext->getBy(array("lookupEntityType" => $entityType));
            if (!empty($lookupAttributes)) {
                /** @var Attribute $lookupAttribute */
                foreach ($lookupAttributes as $lookupAttribute) {
                    if (!isset($relatedEntityTypesToRegenerate[$lookupAttribute->getEntityType()->getId()])) {
                        $relatedEntityTypesToRegenerate[$lookupAttribute->getEntityType()->getId()] = $lookupAttribute->getEntityType();
                    }
                }
            }
        }

        /**
         * Get attribute sets and delete json files
         */
        if (empty($this->attributeSetContext)) {
            $this->attributeSetContext = $this->container->get("attribute_set_context");
        }
        if (empty($this->attributeGroupContext)) {
            $this->attributeGroupContext = $this->container->get("attribute_group_context");
        }

        $allAttributeGroups = array();

        $attributeSets = $this->attributeSetContext->getAttributeSetsByEntityType($entityType);
        if (!empty($attributeSets)) {
            /** @var AttributeSet $attributeSet */
            foreach ($attributeSets as $attributeSet) {
                $this->syncManager->deleteEntityRecord("attribute_set", array("id" => $attributeSet->getId(), "uid" => $attributeSet->getUid()), true);

                $attributeGroups = $this->attributeGroupContext->getAttributesGroupsBySet($attributeSet);
                if (!empty($attributeGroups)) {
                    /** @var AttributeGroup $attributeGroup */
                    foreach ($attributeGroups as $attributeGroup) {
                        $allAttributeGroups[] = $attributeGroup;
                        $this->syncManager->deleteEntityRecord("attribute_group", array("id" => $attributeGroup->getId(), "uid" => $attributeGroup->getUid()), true);
                    }
                }
            }
        }

        /**
         * Get listViews and delete json files
         */
        if (empty($this->listViewContext)) {
            $this->listViewContext = $this->container->get("list_view_context");
        }

        $listViews = $this->listViewContext->getListViewsByEntityType($entityType);
        if (!empty($listViews)) {
            /** @var ListView $listView */
            foreach ($listViews as $listView) {
                $this->syncManager->deleteEntityRecord("list_view", array("id" => $listView->getId(), "uid" => $listView->getUid()), true);
            }
        }

        /**
         * Get pages and delete json files
         */
        if (empty($this->pageContext)) {
            $this->pageContext = $this->container->get("page_context");
        }
        if (empty($this->navigationLinkContext)) {
            $this->navigationLinkContext = $this->container->get("navigation_link_context");
        }

        $allNavigationLinks = array();

        $pages = $this->pageContext->getPagesByEntityType($entityType);
        if (!empty($pages)) {
            /** @var Page $page */
            foreach ($pages as $page) {
                $this->syncManager->deleteEntityRecord("page", array("id" => $page->getId(), "uid" => $page->getUid()), true);

                $navigationLinks = $this->navigationLinkContext->getNavigationLinksByPage($page);
                if (!empty($navigationLinks)) {
                    /** @var NavigationLink $navigationLink */
                    foreach ($navigationLinks as $navigationLink) {
                        $allNavigationLinks[] = $navigationLink;
                        $this->syncManager->deleteEntityRecord("navigation_link", array("id" => $navigationLink->getId(), "uid" => $navigationLink->getUid()), true);
                    }
                }
            }
        }

        /**
         * Get page_blocks and delete json files
         */
        if (empty($this->pageBlockContext)) {
            $this->pageBlockContext = $this->container->get("page_block_context");
        }

        $pageBlocks = $this->pageBlockContext->getPageBlocksByEntityType($entityType);
        if (!empty($pageBlocks)) {
            /** @var PageBlock $pageBlock */
            foreach ($pageBlocks as $pageBlock) {
                $this->syncManager->deleteEntityRecord("page_block", array("id" => $pageBlock->getId(), "uid" => $pageBlock->getUid()), true);
            }
        }

        /**
         * Update to new bundle and set not custom
         */
        $entityType->setBundle($destinationBundle);
        $entityType->setIsCustom(0);

        $this->saveEntityType($entityType);

        if (!empty($attributeSets)) {
            /** @var AttributeSet $attributeSet */
            foreach ($attributeSets as $attributeSet) {
                $attributeSet->setIsCustom(0);
                $this->saveAttributeSet($attributeSet);
            }
        }

        if (!empty($attributes)) {
            /** @var Attribute $attribute */
            foreach ($attributes as $attribute) {
                $attribute->setIsCustom(0);
                $this->saveAttribute($attribute);
            }
        }

        if (!empty($allAttributeGroups)) {
            /** @var AttributeGroup $attributeGroup */
            foreach ($allAttributeGroups as $attributeGroup) {
                $attributeGroup->setIsCustom(0);
                $this->saveAttributeGroup($attributeGroup);
            }
        }

        if (!empty($listViews)) {
            if (empty($this->listViewManager)) {
                $this->listViewManager = $this->container->get("list_view_manager");
            }

            /** @var ListView $listView */
            foreach ($listViews as $listView) {
                $listView->setBundle($destinationBundle);
                $listView->setIsCustom(0);
                $this->listViewManager->saveListView($listView);
            }
        }

        if (!empty($pageBlocks)) {
            if (empty($this->blockManager)) {
                $this->blockManager = $this->container->get("block_manager");
            }

            /** @var PageBlock $pageBlock */
            foreach ($pageBlocks as $pageBlock) {
                $pageBlock->setBundle($destinationBundle);
                $pageBlock->setIsCustom(0);
                $this->blockManager->save($pageBlock);
            }
        }

        if (!empty($pages)) {
            if (empty($this->pageManager)) {
                $this->pageManager = $this->container->get("page_manager");
            }

            /** @var Page $page */
            foreach ($pages as $page) {
                $page->setBundle($destinationBundle);
                $page->setIsCustom(0);
                $this->pageManager->save($page);
            }
        }

        if (!empty($allNavigationLinks)) {
            if (empty($this->navigationLinkManager)) {
                $this->navigationLinkManager = $this->container->get("navigation_link_manager");
            }

            /** @var NavigationLink $navigationLink */
            foreach ($allNavigationLinks as $navigationLink) {
                $navigationLink->setBundle($destinationBundle);
                $navigationLink->setIsCustom(0);
                $this->navigationLinkManager->save($navigationLink);
            }
        }

        /**
         * Generate new entity classes
         */
        $this->generateDoctrineXML($entityType, true);
        $this->generateEntityClasses($entityType, true);

        if (!empty($relatedEntityTypesToRegenerate)) {
            foreach ($relatedEntityTypesToRegenerate as $relatedEntityType) {
                $this->generateDoctrineXML($relatedEntityType, true);
                $this->generateEntityClasses($relatedEntityType, true);
            }
        }

        return true;
    }

    /**
     * @param $table
     * @param $id
     * @return mixed
     */
    public function getEntityByTableAndId($table,$id){

        $context = $this->container->get("{$table}_context");

        if (preg_match("/^\d+$/", $id) && strlen($id) < 10) {
            return $context->getById($id);
        }

        return $context->getItemByUid($id);
    }

    /**
     * @param null $attributeSetCode
     * @return bool
     */
    public function fixEntityTypeAndAttributeSetIdInEntities($attributeSetCode = null){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $where = "";
        if(!empty($attributeSetCode)){
            $where = " AND attribute_set_code = '{$attributeSetCode}' ";
        }

        $q = "SELECT a.id as attribute_set_id, a.attribute_set_code as attribute_set_code, e.id AS entity_type_id, e.entity_table FROM attribute_set as a LEFT JOIN entity_type as e ON a.entity_type_id = e.id WHERE e.is_view = 0 {$where} group by e.id; ";
        $data = $this->databaseContext->getAll($q);

        if(empty($data)){
            return false;
        }

        foreach ($data as $d){

            $q = "UPDATE {$d["entity_table"]} SET entity_type_id = {$d["entity_type_id"]}, attribute_set_id = {$d["attribute_set_id"]};";
            $this->databaseContext->executeNonQuery($q);
        }

        return true;
    }

    /**
     * @param $entityTypeCode
     * @return bool
     * @throws \Exception
     */
    public function addUrlOptionToEntity($entityTypeCode){

        if(empty($entityTypeCode)){
            throw new \Exception("Empty entity type code");
        }

        if(empty($this->entityManager)){
            $this->entityManager = $this->getContainer()->get("entity_type_manager");
        }

        /** @var EntityType $entityType */
        $entityType = $this->entityManager->getEntityTypeByCode($entityTypeCode);

        if(empty($entityType)){
            throw new \Exception("Entity type does not exist");
        }

        if(empty($this->attributeContext)){
            $this->attributeContext = $this->getContainer()->get("attribute_context");
        }

        if(empty($this->attributeGroupContext)){
            $this->attributeGroupContext = $this->getContainer()->get("attribute_group_context");
        }

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        if (empty($this->databaseManager)) {
            $this->databaseManager = $this->container->get("database_manager");
        }

        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->entityManager->getAttributeSetByCode($entityTypeCode);

        $attributeGroups = $attributeSet->getAttributeGroups();

        if(!EntityHelper::isCountable($attributeGroups) || count($attributeGroups) == 0){
            throw new \Exception("Missing attribute group for entity type {$entityTypeCode}");
        }

        $attributeGroup = $attributeGroups[0];

        if(empty($this->routeManager)){
            $this->routeManager = $this->container->get("route_manager");
        }

        $stores = $this->routeManager->getStores();

        $baseStore = $stores[0];

        /**
         * Check for url
         */
        $attributeCode = "url";

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);

        if(empty($attribute)){

            $data = array();
            $data["frontendLabel"] = "Url";
            $data["frontendInput"] = "text_store";
            $data["frontendType"] = "text_store";
            $data["frontendModel"] = null;
            $data["frontendHidden"] = 0;
            $data["readOnly"] = 1;
            $data["frontendDisplayOnNew"] = 1;
            $data["frontendDisplayOnUpdate"] = 1;
            $data["frontendDisplayOnView"] = 1;
            $data["frontendDisplayOnPreview"] = 1;
            $data["attributeCode"] = $attributeCode;
            $data["backendType"] = "json";
            $data["isCustom"] = 1;

            $ret = $this->createAttribute($entityType, $attributeSet, $attributeGroup, $data);
            if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                throw new \Exception("Cannot create attribute {$attributeCode}");
            }

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }
        elseif ($attribute->getBackendType() != "json"){

            $q = "SELECT id, {$attribute->getAttributeCode()} FROM {$entityTypeCode}_entity;";
            $data = $this->databaseContext->getAll($q);

            if(!empty($data)){

                $updateQuery = Array();
                $i = 0;

                foreach ($data as $d) {

                    $updateQueryTmp = "UPDATE {$entityTypeCode}_entity SET ";

                    if ($i == 100) {
                        $this->databaseContext->executeNonQuery(implode("",$updateQuery));
                        $updateQuery = Array();
                        $i = 0;
                    }

                    $valueArray = Array();

                    foreach ($stores as $store) {
                        $valueArray[$store->getId()] = $d[$attribute->getAttributeCode()];
                    }
                    $valueArray = json_encode($valueArray, JSON_UNESCAPED_UNICODE);
                    $updateQueryTmp .= " {$attribute->getAttributeCode()} = '{$valueArray}' ";
                    $updateQueryTmp .= " WHERE id = {$d["id"]};";

                    $updateQuery[] = $updateQueryTmp;

                    $i++;
                }

                if(!empty($updateQuery)){
                    $this->databaseContext->executeNonQuery(implode("",$updateQuery));
                }
            }

            $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'text_store', frontend_input = 'text_store' WHERE id = {$attribute->getId()};";
            $this->databaseContext->executeNonQuery($q);

            /**
             * Resave attribute
             */
            /** @var Attribute $attribute */
            $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);
            $attribute->setIsCustom(1);

            $this->saveAttribute($attribute);

            /**
             * Change column
             */
            $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY {$attribute->getAttributeCode()} JSON;";
            $this->databaseContext->executeNonQuery($q);

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }

        /**
         * Check for show_on_store
         */
        $attributeCode = "show_on_store";

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);

        if(empty($attribute)){

            $data = array();
            $data["frontendLabel"] = "Show on store";
            $data["frontendInput"] = "checkbox_store";
            $data["frontendType"] = "checkbox_store";
            $data["frontendModel"] = null;
            $data["frontendHidden"] = 0;
            $data["readOnly"] = 0;
            $data["frontendDisplayOnNew"] = 1;
            $data["frontendDisplayOnUpdate"] = 1;
            $data["frontendDisplayOnView"] = 1;
            $data["frontendDisplayOnPreview"] = 1;
            $data["attributeCode"] = $attributeCode;
            $data["backendType"] = "json";
            $data["defaultValue"] = '{"'.$baseStore->getId().'": 1}';
            $data["isCustom"] = 1;

            $ret = $this->createAttribute($entityType, $attributeSet, $attributeGroup, $data);
            if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                throw new \Exception("Cannot create attribute {$attributeCode}");
            }

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }
        else{
            if(empty($attribute->getDefaultValue())){
                $attribute->setDefaultValue('{"'.$baseStore->getId().'": 1}');
                $attribute->setIsCustom(1);
                $this->saveAttribute($attribute);
            }

            $defaultValue = Array();
            foreach ($stores as $store){
                $defaultValue[$store->getId()] = 1;
            }
            $defaultValueJson = json_encode($defaultValue, JSON_UNESCAPED_UNICODE);

            $q = "UPDATE {$entityTypeCode}_entity SET {$attribute->getAttributeCode()} = '{$defaultValueJson}';";
            $this->databaseContext->executeNonQuery($q);
        }

        /**
         * Check for template_type_id
         */
        $attributeCode = "template_type_id";

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);

        if(empty($attribute)){

            $data = array();
            $data["frontendLabel"] = "Template type";
            $data["frontendInput"] = "lookup";
            $data["frontendType"] = "autocomplete";
            $data["frontendModel"] = "default";
            $data["frontendHidden"] = 0;
            $data["readOnly"] = 0;
            $data["frontendDisplayOnNew"] = 1;
            $data["frontendDisplayOnUpdate"] = 1;
            $data["frontendDisplayOnView"] = 1;
            $data["frontendDisplayOnPreview"] = 1;
            $data["attributeCode"] = $attributeCode;
            $data["backendType"] = "lookup";
            $data["isCustom"] = 1;

            /** @var EntityType $templateTypeEntityType */
            $templateTypeEntityType = $this->entityManager->getEntityTypeByCode("s_template_type");
            /** @var AttributeSet $templateTypeAttributeSet */
            $templateTypeAttributeSet = $this->getDefaultAttributeSet($templateTypeEntityType);
            /** @var Attribute $sourceAttribute */
            $sourceAttribute = $this->attributeContext->getAttributeByCode("name", $templateTypeEntityType);

            $data["lookupEntityType"] = $templateTypeEntityType;
            $data["lookupAttributeSet"] = $templateTypeAttributeSet;
            $data["lookupAttribute"] = $sourceAttribute;
            $data["enableModalCreate"] = 0;
            $data["useLookupLink"] = 0;
            $data["modalPageBlockId"] = null;

            $ret = $this->createAttribute($entityType, $attributeSet, $attributeGroup, $data);
            if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                throw new \Exception("Cannot create attribute {$attributeCode}");
            }

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }

        /**
         * Check for keep_url
         */
        $attributeCode = "keep_url";

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);

        if(empty($attribute)){

            $data = array();
            $data["frontendLabel"] = "Keep url";
            $data["frontendInput"] = "checkbox";
            $data["frontendType"] = "checkbox";
            $data["frontendModel"] = null;
            $data["frontendHidden"] = 0;
            $data["readOnly"] = 0;
            $data["frontendDisplayOnNew"] = 0;
            $data["frontendDisplayOnUpdate"] = 1;
            $data["frontendDisplayOnView"] = 1;
            $data["frontendDisplayOnPreview"] = 1;
            $data["attributeCode"] = $attributeCode;
            $data["backendType"] = "bool";
            $data["defaultValue"] = 1;
            $data["isCustom"] = 1;

            $ret = $this->createAttribute($entityType, $attributeSet, $attributeGroup, $data);
            if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                throw new \Exception("Cannot create attribute {$attributeCode}");
            }

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }
        else{
            $q = "UPDATE {$entityTypeCode}_entity SET {$attribute->getAttributeCode()} = 1;";
            $this->databaseContext->executeNonQuery($q);
        }

        /**
         * Check for auto_generate_url
         */
        $attributeCode = "auto_generate_url";

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);

        if(empty($attribute)){

            $data = array();
            $data["frontendLabel"] = "Auto generate url";
            $data["frontendInput"] = "checkbox";
            $data["frontendType"] = "checkbox";
            $data["frontendModel"] = null;
            $data["frontendHidden"] = 0;
            $data["readOnly"] = 0;
            $data["frontendDisplayOnNew"] = 0;
            $data["frontendDisplayOnUpdate"] = 1;
            $data["frontendDisplayOnView"] = 1;
            $data["frontendDisplayOnPreview"] = 1;
            $data["attributeCode"] = $attributeCode;
            $data["backendType"] = "bool";
            $data["defaultValue"] = 1;
            $data["isCustom"] = 1;

            $ret = $this->createAttribute($entityType, $attributeSet, $attributeGroup, $data);
            if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                throw new \Exception("Cannot create attribute {$attributeCode}");
            }

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }
        else{
            $q = "UPDATE {$entityTypeCode}_entity SET {$attribute->getAttributeCode()} = 1;";
            $this->databaseContext->executeNonQuery($q);
        }

        /**
         * Check for is_active
         */
        $attributeCode = "is_active";

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);

        if(empty($attribute)){

            $data = array();
            $data["frontendLabel"] = "Active";
            $data["frontendInput"] = "checkbox";
            $data["frontendType"] = "checkbox";
            $data["frontendModel"] = null;
            $data["frontendHidden"] = 0;
            $data["readOnly"] = 0;
            $data["frontendDisplayOnNew"] = 0;
            $data["frontendDisplayOnUpdate"] = 1;
            $data["frontendDisplayOnView"] = 1;
            $data["frontendDisplayOnPreview"] = 1;
            $data["attributeCode"] = $attributeCode;
            $data["backendType"] = "bool";
            $data["defaultValue"] = 1;
            $data["isCustom"] = 1;

            $ret = $this->createAttribute($entityType, $attributeSet, $attributeGroup, $data);
            if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                throw new \Exception("Cannot create attribute {$attributeCode}");
            }

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }
        else{
            $q = "UPDATE {$entityTypeCode}_entity SET {$attribute->getAttributeCode()} = 1;";
            $this->databaseContext->executeNonQuery($q);
        }

        /**
         * Check for name
         */
        $attributeCode = "name";

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);

        if(empty($attribute)){

            $data = array();
            $data["frontendLabel"] = "Name";
            $data["frontendInput"] = "text_store";
            $data["frontendType"] = "text_store";
            $data["frontendModel"] = null;
            $data["frontendHidden"] = 0;
            $data["readOnly"] = 0;
            $data["frontendDisplayOnNew"] = 1;
            $data["frontendDisplayOnUpdate"] = 1;
            $data["frontendDisplayOnView"] = 1;
            $data["frontendDisplayOnPreview"] = 1;
            $data["attributeCode"] = $attributeCode;
            $data["backendType"] = "json";
            $data["isCustom"] = 1;

            $ret = $this->createAttribute($entityType, $attributeSet, $attributeGroup, $data);
            if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                throw new \Exception("Cannot create attribute {$attributeCode}");
            }

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }
        elseif ($attribute->getBackendType() != "json"){

            $q = "SELECT id, {$attribute->getAttributeCode()} FROM {$entityTypeCode}_entity;";
            $data = $this->databaseContext->getAll($q);

            if(!empty($data)){

                $updateQuery = Array();
                $i = 0;

                foreach ($data as $d) {

                    $updateQueryTmp = "UPDATE {$entityTypeCode}_entity SET ";

                    if ($i == 100) {
                        $this->databaseContext->executeNonQuery(implode("",$updateQuery));
                        $updateQuery = Array();
                        $i = 0;
                    }

                    $valueArray = Array();

                    foreach ($stores as $store) {
                        $valueArray[$store->getId()] = $d[$attribute->getAttributeCode()];
                    }
                    $valueArray = json_encode($valueArray, JSON_UNESCAPED_UNICODE);
                    $updateQueryTmp .= " {$attribute->getAttributeCode()} = '{$valueArray}' ";
                    $updateQueryTmp .= " WHERE id = {$d["id"]};";

                    $updateQuery[] = $updateQueryTmp;

                    $i++;
                }

                if(!empty($updateQuery)){
                    $this->databaseContext->executeNonQuery(implode("",$updateQuery));
                }
            }

            $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'text_store', frontend_input = 'text_store' WHERE id = {$attribute->getId()};";
            $this->databaseContext->executeNonQuery($q);

            /**
             * Resave attribute
             */
            /** @var Attribute $attribute */
            $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);
            $attribute->setIsCustom(1);

            $this->saveAttribute($attribute);

            /**
             * Change column
             */
            $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY {$attribute->getAttributeCode()} JSON;";
            $this->databaseContext->executeNonQuery($q);

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }

        /**
         * Check for meta_title
         */
        $attributeCode = "meta_title";

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);

        if(empty($attribute)){

            $data = array();
            $data["frontendLabel"] = "Meta title";
            $data["frontendInput"] = "text_store";
            $data["frontendType"] = "text_store";
            $data["frontendModel"] = null;
            $data["frontendHidden"] = 0;
            $data["readOnly"] = 0;
            $data["frontendDisplayOnNew"] = 1;
            $data["frontendDisplayOnUpdate"] = 1;
            $data["frontendDisplayOnView"] = 1;
            $data["frontendDisplayOnPreview"] = 1;
            $data["attributeCode"] = $attributeCode;
            $data["backendType"] = "json";
            $data["isCustom"] = 1;

            $ret = $this->createAttribute($entityType, $attributeSet, $attributeGroup, $data);
            if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                throw new \Exception("Cannot create attribute {$attributeCode}");
            }

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }
        elseif ($attribute->getBackendType() != "json"){

            $q = "SELECT id, {$attribute->getAttributeCode()} FROM {$entityTypeCode}_entity;";
            $data = $this->databaseContext->getAll($q);

            if(!empty($data)){

                $updateQuery = Array();
                $i = 0;

                foreach ($data as $d) {

                    $updateQueryTmp = "UPDATE {$entityTypeCode}_entity SET ";

                    if ($i == 100) {
                        $this->databaseContext->executeNonQuery(implode("",$updateQuery));
                        $updateQuery = Array();
                        $i = 0;
                    }

                    $valueArray = Array();

                    foreach ($stores as $store) {
                        $valueArray[$store->getId()] = $d[$attribute->getAttributeCode()];
                    }
                    $valueArray = json_encode($valueArray, JSON_UNESCAPED_UNICODE);
                    $updateQueryTmp .= " {$attribute->getAttributeCode()} = '{$valueArray}' ";
                    $updateQueryTmp .= " WHERE id = {$d["id"]};";

                    $updateQuery[] = $updateQueryTmp;

                    $i++;
                }

                if(!empty($updateQuery)){
                    $this->databaseContext->executeNonQuery(implode("",$updateQuery));
                }
            }

            $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'text_store', frontend_input = 'text_store' WHERE id = {$attribute->getId()};";
            $this->databaseContext->executeNonQuery($q);

            /**
             * Resave attribute
             */
            /** @var Attribute $attribute */
            $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);
            $attribute->setIsCustom(1);

            $this->saveAttribute($attribute);

            /**
             * Change column
             */
            $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY {$attribute->getAttributeCode()} JSON;";
            $this->databaseContext->executeNonQuery($q);

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }

        /**
         * Check for meta_keywords
         */
        $attributeCode = "meta_keywords";

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);

        if(empty($attribute)){

            $data = array();
            $data["frontendLabel"] = "Meta keywords";
            $data["frontendInput"] = "textarea_store";
            $data["frontendType"] = "textarea_store";
            $data["frontendModel"] = null;
            $data["frontendHidden"] = 0;
            $data["readOnly"] = 0;
            $data["frontendDisplayOnNew"] = 1;
            $data["frontendDisplayOnUpdate"] = 1;
            $data["frontendDisplayOnView"] = 1;
            $data["frontendDisplayOnPreview"] = 1;
            $data["attributeCode"] = $attributeCode;
            $data["backendType"] = "json";
            $data["isCustom"] = 1;

            $ret = $this->createAttribute($entityType, $attributeSet, $attributeGroup, $data);
            if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                throw new \Exception("Cannot create attribute {$attributeCode}");
            }

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }
        elseif ($attribute->getBackendType() != "json"){

            $q = "SELECT id, {$attribute->getAttributeCode()} FROM {$entityTypeCode}_entity;";
            $data = $this->databaseContext->getAll($q);

            if(!empty($data)){

                $updateQuery = Array();
                $i = 0;

                foreach ($data as $d) {

                    $updateQueryTmp = "UPDATE {$entityTypeCode}_entity SET ";

                    if ($i == 100) {
                        $this->databaseContext->executeNonQuery(implode("",$updateQuery));
                        $updateQuery = Array();
                        $i = 0;
                    }

                    $valueArray = Array();

                    foreach ($stores as $store) {
                        $valueArray[$store->getId()] = $d[$attribute->getAttributeCode()];
                    }
                    $valueArray = json_encode($valueArray, JSON_UNESCAPED_UNICODE);
                    $updateQueryTmp .= " {$attribute->getAttributeCode()} = '{$valueArray}' ";
                    $updateQueryTmp .= " WHERE id = {$d["id"]};";

                    $updateQuery[] = $updateQueryTmp;

                    $i++;
                }

                if(!empty($updateQuery)){
                    $this->databaseContext->executeNonQuery(implode("",$updateQuery));
                }
            }

            $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'textarea_store', frontend_input = 'textarea_store' WHERE id = {$attribute->getId()};";
            $this->databaseContext->executeNonQuery($q);

            /**
             * Resave attribute
             */
            /** @var Attribute $attribute */
            $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);
            $attribute->setIsCustom(1);

            $this->saveAttribute($attribute);

            /**
             * Change column
             */
            $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY {$attribute->getAttributeCode()} JSON;";
            $this->databaseContext->executeNonQuery($q);

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }

        /**
         * Check for meta_description
         */
        $attributeCode = "meta_description";

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);

        if(empty($attribute)){

            $data = array();
            $data["frontendLabel"] = "Meta description";
            $data["frontendInput"] = "textarea_store";
            $data["frontendType"] = "textarea_store";
            $data["frontendModel"] = null;
            $data["frontendHidden"] = 0;
            $data["readOnly"] = 0;
            $data["frontendDisplayOnNew"] = 1;
            $data["frontendDisplayOnUpdate"] = 1;
            $data["frontendDisplayOnView"] = 1;
            $data["frontendDisplayOnPreview"] = 1;
            $data["attributeCode"] = $attributeCode;
            $data["backendType"] = "json";
            $data["isCustom"] = 1;

            $ret = $this->createAttribute($entityType, $attributeSet, $attributeGroup, $data);
            if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                throw new \Exception("Cannot create attribute {$attributeCode}");
            }

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }
        elseif ($attribute->getBackendType() != "json"){

            $q = "SELECT id, {$attribute->getAttributeCode()} FROM {$entityTypeCode}_entity;";
            $data = $this->databaseContext->getAll($q);

            if(!empty($data)){

                $updateQuery = Array();
                $i = 0;

                foreach ($data as $d) {

                    $updateQueryTmp = "UPDATE {$entityTypeCode}_entity SET ";

                    if ($i == 100) {
                        $this->databaseContext->executeNonQuery(implode("",$updateQuery));
                        $updateQuery = Array();
                        $i = 0;
                    }

                    $valueArray = Array();

                    foreach ($stores as $store) {
                        $valueArray[$store->getId()] = $d[$attribute->getAttributeCode()];
                    }
                    $valueArray = json_encode($valueArray, JSON_UNESCAPED_UNICODE);
                    $updateQueryTmp .= " {$attribute->getAttributeCode()} = '{$valueArray}' ";
                    $updateQueryTmp .= " WHERE id = {$d["id"]};";

                    $updateQuery[] = $updateQueryTmp;

                    $i++;
                }

                if(!empty($updateQuery)){
                    $this->databaseContext->executeNonQuery(implode("",$updateQuery));
                }
            }

            $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'textarea_store', frontend_input = 'textarea_store' WHERE id = {$attribute->getId()};";
            $this->databaseContext->executeNonQuery($q);

            /**
             * Resave attribute
             */
            /** @var Attribute $attribute */
            $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);
            $attribute->setIsCustom(1);

            $this->saveAttribute($attribute);

            /**
             * Change column
             */
            $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY {$attribute->getAttributeCode()} JSON;";
            $this->databaseContext->executeNonQuery($q);

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }

        /**
         * Check for do_not_index
         */
        $attributeCode = "do_not_index";

        /** @var Attribute $attribute */
        $attribute = $this->attributeContext->getAttributeByCode($attributeCode,$entityType);

        if(empty($attribute)){

            $data = array();
            $data["frontendLabel"] = "Do not index";
            $data["frontendInput"] = "checkbox";
            $data["frontendType"] = "checkbox";
            $data["frontendModel"] = null;
            $data["frontendHidden"] = 0;
            $data["readOnly"] = 0;
            $data["frontendDisplayOnNew"] = 1;
            $data["frontendDisplayOnUpdate"] = 1;
            $data["frontendDisplayOnView"] = 1;
            $data["frontendDisplayOnPreview"] = 1;
            $data["attributeCode"] = $attributeCode;
            $data["backendType"] = "bool";
            $data["defaultValue"] = 0;
            $data["isCustom"] = 1;

            $ret = $this->createAttribute($entityType, $attributeSet, $attributeGroup, $data);
            if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                throw new \Exception("Cannot create attribute {$attributeCode}");
            }

            $this->databaseManager->createTableIfDoesntExist($entityType, null);
            $this->generateDoctrineXML($entityType, true);
            $this->generateEntityClasses($entityType, true);
        }
        else{
            $q = "UPDATE {$entityTypeCode}_entity SET {$attribute->getAttributeCode()} = 0;";
            $this->databaseContext->executeNonQuery($q);
        }

        $this->clearCache();

        return true;
    }

    /**
     * @return bool
     */
    public function clearCache(){

        try{
            shell_exec("php bin/console cache:clear");
        }
        catch (\Exception $e){

        }

        if($_ENV["IS_PRODUCTION"]){
            try{
                shell_exec("php bin/console cache:clear");
            }
            catch (\Exception $e){

            }
        }

        if(isset($_ENV["USE_BACKEND_CACHE"]) && $_ENV["USE_BACKEND_CACHE"] == "redis" && isset($_ENV["REDIS_CACHE_DB"]) && !empty($_ENV["REDIS_CACHE_DB"]) && intval($_ENV["REDIS_CACHE_DB"]) < 99){
            try{
                shell_exec("php bin/console admin:entity clear_backend_cache");
            }
            catch (\Exception $e){

            }
        }

        return true;
    }
}

