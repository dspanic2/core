<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Enumerations\SyncStateEnum;
use AppBundle\Entity;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\VarDumper\VarDumper;

class CoreSyncManager extends AbstractBaseManager
{
    /** @var AdministrationManager $administrationManager */
    protected $administrationManager;
    /** @var ListViewManager $listViewManager */
    protected $listViewManager;
    /** @var BlockManager $blockManager */
    protected $blockManager;
    /** @var NavigationLinkManager $navigationLinkManager */
    protected $navigationLinkManager;

    public function initialize()
    {
        parent::initialize();
    }

    public function compareLocalToRemoteArray($core_type_name, $remoteArray, $showChangesOnly)
    {
        $resultsArray = [];
        $localItemsArray = [];
        $context = $this->container->get($core_type_name . "_context");
        $listViewContext = $this->container->get("list_view_context");
        $attributeGroupContext = $this->container->get("attribute_group_context");
        $localItems = $context->getAll();

        /**@var \AppBundle\Abstracts\AbstractCoreEntity $item */
        foreach ($localItems as $item) {
            $itemArray = $item->convertToArray();

            if ($core_type_name == "page_block") {
                $relatedUid = "";
                $relatedItem = null;

                if ($itemArray["type"] == "list_view") {
                    $relatedItem = $listViewContext->getById($item->getRelatedId());
                }

                if ($itemArray["type"] == "attribute_group" || $itemArray["type"] == "custom_html") {
                    $relatedItem = $attributeGroupContext->getById($item->getRelatedId());
                }

                if ($relatedItem != null) {
                    $relatedUid = $relatedItem->getUid();
                }

                $itemArray["relatedUid"] = $relatedUid;
            }

            $localItemsArray[] = $itemArray;
        }

        foreach ($remoteArray as $remoteItem) {
            $syncState = SyncStateEnum::Created;
            foreach ($localItemsArray as $k => $localItem) {
                if ($localItem["uid"] == $remoteItem["uid"]) {
                    $syncState = SyncStateEnum::Unmodified;

                    $difference = array_diff($localItem, $remoteItem);

                    if (count($difference) > 0) {
                        $syncState = SyncStateEnum::Modified;
                    }

                    if ($showChangesOnly == false) {
                        $resultsArray[] = array("local" => $localItem, "remote" => $remoteItem, "state" => $syncState);
                    } else {
                        if ($syncState == SyncStateEnum::Modified) {
                            $resultsArray[] = array("local" => $localItem, "remote" => $remoteItem, "state" => $syncState);
                        }
                    }

                    unset($localItemsArray[$k]);
                }
            }
            if ($syncState == SyncStateEnum::Created) {
                $resultsArray[] = array("local" => "", "remote" => $remoteItem, "state" => $syncState);
            }
        }
        return $resultsArray;
    }

    public function viewDifference($core_type_name, $remoteArray, $uid)
    {
        $resultsArray = [];
        $context = $this->container->get($core_type_name . "_context");
        $localItem = $context->getOneBy(array('uid' => $uid));

        $localItem = $localItem->convertToArray();
        foreach ($remoteArray as $remoteItem) {
            if ($remoteItem["uid"] == $localItem["uid"]) {
                $resultsArray = array("local" => $localItem, "remote" => $remoteItem);
                break;
            }
        }
        return $resultsArray;
    }


    public function sync($type, $remoteArray, $updateExisting = true)
    {

        switch ($type) {
            case "entity_type":
                $this->syncEntityTypes($remoteArray, $updateExisting);
                break;
            case "attribute_set":
                $this->syncAttributeSets($remoteArray, $updateExisting);
                break;
            case "attribute_group":
                $this->syncAttributeGroups($remoteArray, $updateExisting);
                break;
            case "attribute":
                $this->syncAttributes($remoteArray, $updateExisting);
                break;
            case "list_view":
                $this->syncListViews($remoteArray, $updateExisting);
                break;
            case "page":
                $this->syncPages($remoteArray, $updateExisting);
                break;
            case "page_block":
                $this->syncPageBlocks($remoteArray, $updateExisting);
                break;
            case "navigation_link":
                $this->syncNavigationLinks($remoteArray, $updateExisting);
                break;
            default:
                break;
        }
    }

    public function syncEntityTypes($remoteArray, $updateExisting = true)
    {
        /**@var \AppBundle\Context\EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get("entity_type_context");

        foreach ($remoteArray as $remoteItem) {


            $locaLEntityType = $entityTypeContext->getItemByUid($remoteItem["uid"]);

            if ($locaLEntityType != null && $updateExisting == false)
                continue;

            if ($locaLEntityType == null) {
                $locaLEntityType = new Entity\EntityType();
                $locaLEntityType->setEntityTypeCode($remoteItem["entityTypeCode"]);
                $locaLEntityType->setUid($remoteItem["uid"]);
            }

            $locaLEntityType->setEntityModel($remoteItem["entityModel"]);
            $locaLEntityType->setEntityTable($remoteItem["entityTable"]);
            $locaLEntityType->setEntityIdField($remoteItem["entityIdField"]);
            $locaLEntityType->setBundle($remoteItem["bundle"]);
            $locaLEntityType->setDoctrineCustom($remoteItem["doctrineCustom"]);
            $locaLEntityType->setEntityCustom($remoteItem["entityCustom"]);
            $locaLEntityType->setEntityUseClasses($remoteItem["entityUseClasses"]);
            $locaLEntityType->setEntityExtendClass($remoteItem["entityExtendClass"]);
            $locaLEntityType->setIsRelation($remoteItem["isRelation"]);
            $locaLEntityType->setIsDocument($remoteItem["isDocument"]);
            $locaLEntityType->setHasUniquePermissions($remoteItem["hasUniquePermissions"]);
            if(isset($remoteItem["isView"])){
                $locaLEntityType->setIsView($remoteItem["isView"]);
            }
            else{
                $locaLEntityType->setIsView(0);
            }
            if(isset($remoteItem["isCustom"])){
                $locaLEntityType->setIsCustom($remoteItem["isCustom"]);
            }
            else{
                $locaLEntityType->setIsCustom(0);
            }
            if(isset($remoteItem["syncContent"])){
                $locaLEntityType->setSyncContent($remoteItem["syncContent"]);
            }
            else{
                $locaLEntityType->setSyncContent(0);
            }

            if(empty($this->administrationManager)){
               $this->administrationManager = $this->container->get("administration_manager");
            }

            $this->administrationManager->saveEntityType($locaLEntityType);
        }
    }


    public function syncAttributeSets($remoteArray, $updateExisting = true)
    {
        /**@var \AppBundle\Context\AttributeSetContext $attributSetContext */
        $attributSetContext = $this->container->get("attribute_set_context");
        /**@var \AppBundle\Context\EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get("entity_type_context");

        foreach ($remoteArray as $remoteItem) {
            $localItem = $attributSetContext->getItemByUid($remoteItem["uid"]);

            if ($localItem != null && $updateExisting == false)
                continue;


            if ($localItem == null) {
                $localItem = new Entity\AttributeSet();
                $localItem->setUid($remoteItem["uid"]);
                $localItem->setAttributeSetCode($remoteItem["attributeSetCode"]);
            }

            $entityType = $entityTypeContext->getItemByCode($remoteItem["entityTypeCode"]);
            $localItem->setEntityType($entityType);
            $localItem->setAttributeSetName($remoteItem["attributeSetName"]);
            $localItem->setSortOrder($remoteItem["sortOrder"]);
            if(isset($remoteItem["isCustom"])){
                $localItem->setIsCustom($remoteItem["isCustom"]);
            }
            else{
                $localItem->setIsCustom(0);
            }

            if(empty($this->administrationManager)){
               $this->administrationManager = $this->container->get("administration_manager");
            }

            $this->administrationManager->saveAttributeSet($localItem);
        }
    }

    public function syncAttributeGroups($remoteArray, $updateExisting = true)
    {
        /**@var \AppBundle\Context\AttributeSetContext $attributSetContext */
        $attributSetContext = $this->container->get("attribute_set_context");
        /**@var \AppBundle\Context\AttributeGroupContext $attributeGroupContext */
        $attributeGroupContext = $this->container->get("attribute_group_context");
        /**@var \AppBundle\Context\AttributeContext $attributeContext */
        $attributeContext = $this->container->get("attribute_context");
        /**@var \AppBundle\Context\EntityAttributeContext $entityAttributeContext */
        $entityAttributeContext = $this->container->get("entity_attribute_context");


        foreach ($remoteArray as $remoteItem) {
            $remoteAttributes = json_decode($remoteItem["attributes"], true);

            $localItem = $attributeGroupContext->getItemByUid($remoteItem["uid"]);

            if ($localItem != null && $updateExisting == false)
                continue;

            if ($localItem == null) {
                $localItem = new Entity\AttributeGroup();
                $localItem->setUid($remoteItem["uid"]);
            }

            $attributeSet = $attributSetContext->getItemByCode($remoteItem["attributeSetCode"]);
            $localItem->setAttributeSet($attributeSet);
            $localItem->setAttributeGroupName($remoteItem["attributeGroupName"]);
            $localItem->setSortOrder($remoteItem["sortOrder"]);
            $localItem->setDefaultId($remoteItem["defaultId"]);
            if(isset($remoteItem["isCustom"])){
                $localItem->setIsCustom($remoteItem["isCustom"]);
            }
            else{
                $localItem->setIsCustom(0);
            }

            if(empty($this->administrationManager)){
               $this->administrationManager = $this->container->get("administration_manager");
            }

            $localItem = $this->administrationManager->saveAttributeGroup($localItem,true);

            if(!empty($localItem)){
                foreach ($remoteAttributes as $remoteAttribute) {
                    $attribute = $attributeContext->getItemByUid($remoteAttribute["attributeUid"]);
                    $attributeAttributeSet = $attributSetContext->getItemByUid($remoteAttribute["attributeSetUid"]);

                    /** @var Entity\EntityAttribute $localAttribute */
                    $localAttribute = $entityAttributeContext->getOneBy(array('attribute' => $attribute, 'attributeGroup' => $localItem));

                    if ($localAttribute == null) {
                        $localAttribute = new Entity\EntityAttribute();
                    }

                    $localAttribute->setAttribute($attribute);
                    $localAttribute->setAttributeGroup($localItem);
                    $localAttribute->setSortOrder($remoteAttribute["sortOrder"]);
                    $localAttribute->setAttributeSet($attributeAttributeSet);
                    $localAttribute->setEntityType($attributeAttributeSet->getEntityType());

                    if(empty($this->administrationManager)){
                       $this->administrationManager = $this->container->get("administration_manager");
                    }

                    $this->administrationManager->saveEntityAttribute($localAttribute);
                }
            }
        }
    }

    public function syncAttributes($remoteArray, $updateExisting = true)
    {
        /**@var \AppBundle\Context\EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get("entity_type_context");
        /**@var \AppBundle\Context\AttributeSetContext $attributSetContext */
        $attributSetContext = $this->container->get("attribute_set_context");
        /**@var \AppBundle\Context\AttributeContext $attributeContext */
        $attributeContext = $this->container->get("attribute_context");
        /**@var \AppBundle\Context\PageBlockContext $pageBlockContext */
        $pageBlockContext = $this->container->get("page_block_context");

        foreach ($remoteArray as $remoteItem) {
            $localItem = $attributeContext->getItemByUid($remoteItem["uid"]);

            if ($localItem != null && $updateExisting == false)
                continue;

            if ($localItem == null) {
                $localItem = new Entity\Attribute();
                $localItem->setUid($remoteItem["uid"]);
            }

            $entityType = $entityTypeContext->getItemByCode($remoteItem["entityTypeCode"]);
            $localItem->setEntityType($entityType);

            $localItem->setAttributeCode($remoteItem["attributeCode"]);
            $localItem->setAttributeModel($remoteItem["attributeModel"]);
            $localItem->setBackendModel($remoteItem["backendModel"]);
            $localItem->setBackendType($remoteItem["backendType"]);
            $localItem->setBackendTable($remoteItem["backendTable"]);
            $localItem->setFolder($remoteItem["folder"]);
            $localItem->setFrontendModel($remoteItem["frontendModel"]);
            $localItem->setFrontendType($remoteItem["frontendType"]);
            $localItem->setFrontendInput($remoteItem["frontendInput"]);
            $localItem->setFrontendRelated($remoteItem["frontendRelated"]);
            $localItem->setFrontendLabel($remoteItem["frontendLabel"]);
            $localItem->setFrontendDisplayFormat($remoteItem["frontendDisplayFormat"]);
            $localItem->setFrontendHidden($remoteItem["frontendHidden"]);
            $localItem->setFrontendDisplayOnNew($remoteItem["frontendDisplayOnNew"]);
            $localItem->setFrontendDisplayOnUpdate($remoteItem["frontendDisplayOnUpdate"]);
            $localItem->setFrontendDisplayOnView($remoteItem["frontendDisplayOnView"]);
            $localItem->setFrontendDisplayOnPreview($remoteItem["frontendDisplayOnPreview"]);
            $localItem->setFrontendClass($remoteItem["frontendClass"]);
            $localItem->setSourceModel($remoteItem["sourceModel"]);
            $localItem->setIsRequired($remoteItem["isRequired"]);
            $localItem->setValidator($remoteItem["validator"]);
            $localItem->setIsUserDefined($remoteItem["isUserDefined"]);
            $localItem->setDefaultValue($remoteItem["defaultValue"]);
            $localItem->setIsUnique($remoteItem["isUnique"]);
            $localItem->setNote($remoteItem["note"]);
            $localItem->setUseLookupLink($remoteItem["useLookupLink"]);
            $localItem->setUseInQuickSearch($remoteItem["useInQuickSearch"]);
            $localItem->setUseInAdvancedSearch($remoteItem["useInAdvancedSearch"]);
            $localItem->setReadOnly($remoteItem["readOnly"]);
            $localItem->setEnableModalCreate($remoteItem["enableModalCreate"]);
            if(isset($remoteItem["isCustom"])){
                $localItem->setIsCustom($remoteItem["isCustom"]);
            }
            else{
                $localItem->setIsCustom(0);
            }
            if(isset($remoteItem["prefix"])){
                $localItem->setPrefix($remoteItem["prefix"]);
            }
            if(isset($remoteItem["sufix"])){
                $localItem->setSufix($remoteItem["sufix"]);
            }

            if (isset($remoteItem["modalPageBlockUid"])) {
                $modalBlock = $pageBlockContext->getByUid($remoteItem["modalPageBlockUid"]);
                if ($modalBlock != null) {
                    $localItem->setModalPageBlockId($modalBlock->getId());
                }
            }

            if ($remoteItem["lookupEntityTypeCode"] != null) {
                $lookupEntityType = $entityTypeContext->getItemByCode($remoteItem["lookupEntityTypeCode"]);
                $localItem->setLookupEntityType($lookupEntityType);
            }
            if ($remoteItem["lookupAttributeSetCode"] != null) {
                $lookupAttributeSet = $attributSetContext->getItemByCode($remoteItem["lookupAttributeSetCode"]);
                $localItem->setLookupAttributeSet($lookupAttributeSet);
            }
            if ($remoteItem["lookupAttributeCode"] != null) {
                $lookupAttribute = $attributeContext->getItemByUid($remoteItem["lookupAttributeCode"]);
                $localItem->setLookupAttribute($lookupAttribute);
            }

            if(empty($this->administrationManager)){
               $this->administrationManager = $this->container->get("administration_manager");
            }

            $this->administrationManager->saveAttribute($localItem);
        }
    }

    public function syncListViews($remoteArray, $updateExisting = true)
    {
        /**@var \AppBundle\Context\EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get("entity_type_context");
        /**@var \AppBundle\Context\AttributeSetContext $attributSetContext */
        $attributSetContext = $this->container->get("attribute_set_context");
        /**@var \AppBundle\Context\ListViewContext $listViewContext */
        $listViewContext = $this->container->get("list_view_context");
        /**@var \AppBundle\Context\ListViewAttributeContext $listViewAttributeContext */
        $listViewAttributeContext = $this->container->get("list_view_attribute_context");
        /**@var \AppBundle\Context\AttributeContext $attributeContext */
        $attributeContext = $this->container->get("attribute_context");


        foreach ($remoteArray as $remoteItem) {
            $remoteAttributes = json_decode($remoteItem["attributes"], true);

            $localItem = $listViewContext->getItemByUid($remoteItem["uid"]);

            if ($localItem != null && $updateExisting == false)
                continue;

            if ($localItem == null) {
                $localItem = new Entity\ListView();
                $localItem->setUid($remoteItem["uid"]);
            }

            $localItem->setName($remoteItem["name"]);
            $localItem->setDisplayName($remoteItem["displayName"]);
            $localItem->setFilter($remoteItem["filter"]);
            $localItem->setDefaultSort($remoteItem["defaultSort"]);
            $localItem->setDefaultSortType($remoteItem["defaultSortType"]);
            $localItem->setShowFilter($remoteItem["showFilter"]);
            $localItem->setShowLimit($remoteItem["showLimit"]);
            $localItem->setShowExport($remoteItem["showExport"]);
            $localItem->setShowAdvancedSearch($remoteItem["showAdvancedSearch"]);
            $localItem->setMainButton($remoteItem["mainButton"]);
            $localItem->setDropdownButtons($remoteItem["dropdownButtons"]);
            $localItem->setRowActions($remoteItem["rowActions"]);
            if(isset($remoteItem["massActions"])){
                $localItem->setMassActions($remoteItem["massActions"]);
            }
            $localItem->setShowImport($remoteItem["showImport"]);
            $localItem->setBundle($remoteItem["bundle"]);
            $localItem->setModalAdd($remoteItem["modalAdd"] ?? 0);
            if(isset($remoteItem["isCustom"])){
                $localItem->setIsCustom($remoteItem["isCustom"]);
            }
            else{
                $localItem->setIsCustom(0);
            }

            $attributeSet = $attributSetContext->getItemByCode($remoteItem["attributeSetCode"]);
            $localItem->setAttributeSet($attributeSet);

            $entityType = $entityTypeContext->getItemByCode($remoteItem["entityTypeCode"]);
            $localItem->setEntityType($entityType);

            if(empty($this->listViewManager)){
               $this->listViewManager = $this->container->get("list_view_manager");
            }

            $localItem = $this->listViewManager->saveListView($localItem);

            foreach ($remoteAttributes as $remoteAttribute) {
                $attribute = $attributeContext->getItemByUid($remoteAttribute["attribute_uid"]);
                $localAttribute = $listViewAttributeContext->getOneBy(array('attribute' => $attribute, "listView" => $localItem));

                if ($localAttribute != null && $updateExisting == false)
                    continue;

                if ($localAttribute == null) {
                    $localAttribute = new Entity\ListViewAttribute();
                    $localAttribute->setAttribute($attribute);
                    $localAttribute->setListView($localItem);
                }

                $localAttribute->setOrder($remoteAttribute["ord"]);
                $localAttribute->setLabel($remoteAttribute["label"]);
                $localAttribute->setField($remoteAttribute["field"]);
                $localAttribute->setDisplay($remoteAttribute["display"]);

                $this->listViewManager->saveListViewAttribute($localAttribute);
            }
        }
    }

    public function syncPages($remoteArray, $updateExisting = true)
    {
        /**@var \AppBundle\Context\EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get("entity_type_context");
        /**@var \AppBundle\Context\AttributeSetContext $attributSetContext */
        $attributSetContext = $this->container->get("attribute_set_context");
        /**@var \AppBundle\Context\PageContext $pageContext */
        $pageContext = $this->container->get("page_context");

        foreach ($remoteArray as $remoteItem) {
            $localItem = $pageContext->getItemByUid($remoteItem["uid"]);

            if ($localItem != null && $updateExisting == false)
                continue;

            if ($localItem == null) {
                $localItem = new Entity\Page();
                $localItem->setUid($remoteItem["uid"]);
            }

            $localItem->setTitle($remoteItem["title"]);
            $localItem->setUrl($remoteItem["url"]);
            $localItem->setType($remoteItem["type"]);
            $localItem->setContent($remoteItem["content"]);

            $attributeSet = $attributSetContext->getItemByCode($remoteItem["attributeSetCode"]);
            $localItem->setAttributeSet($attributeSet);

            $entityType = $entityTypeContext->getItemByCode($remoteItem["entityTypeCode"]);
            $localItem->setEntityType($entityType);

            $localItem->setClass($remoteItem["class"]);
            $localItem->setDataAttributes($remoteItem["dataAttributes"]);
            $localItem->setButtons($remoteItem["dataAttributes"]);
            $localItem->setBundle($remoteItem["bundle"]);
            if(isset($remoteItem["isCustom"])){
                $localItem->setIsCustom($remoteItem["isCustom"]);
            }
            else{
                $localItem->setIsCustom(0);
            }

            $pageContext->save($localItem);
        }
    }

    public function syncPageBlocks($remoteArray, $updateExisting = true)
    {
        /**@var \AppBundle\Context\EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get("entity_type_context");
        /**@var \AppBundle\Context\AttributeSetContext $attributSetContext */
        $attributSetContext = $this->container->get("attribute_set_context");
        /**@var \AppBundle\Context\PageBlockContext $pageBlockContext */
        $pageBlockContext = $this->container->get("page_block_context");
        /**@var \AppBundle\Context\AttributeGroupContext $attributeGroupContext */
        $attributeGroupContext = $this->container->get("attribute_group_context");
        /**@var \AppBundle\Context\ListViewContext $listViewContext */
        $listViewContext = $this->container->get("list_view_context");

        $relatedItem = null;

        foreach ($remoteArray as $remoteItem) {
            $localItem = $pageBlockContext->getByUid($remoteItem["uid"]);

            if ($localItem != null && $updateExisting == false)
                continue;

            if ($localItem == null) {
                $localItem = new Entity\PageBlock();
                $localItem->setUid($remoteItem["uid"]);
            }

            $localItem->setTitle($remoteItem["title"]);
            $localItem->setType($remoteItem["type"]);
            $localItem->setContent($remoteItem["content"]);

            $attributeSet = $attributSetContext->getItemByCode($remoteItem["attributeSetCode"]);
            $localItem->setAttributeSet($attributeSet);

            $entityType = $entityTypeContext->getItemByCode($remoteItem["entityTypeCode"]);
            $localItem->setEntityType($entityType);
            $localItem->setParent($remoteItem["parent"]);
            $localItem->setClass($remoteItem["class"]);
            $localItem->setDataAttributes($remoteItem["dataAttributes"]);
            $localItem->setBundle($remoteItem["bundle"]);
            if(isset($remoteItem["isCustom"])){
                $localItem->setIsCustom($remoteItem["isCustom"]);
            }
            else{
                $localItem->setIsCustom(0);
            }

            if ($remoteItem["type"] == "list_view") {
                $relatedItem = $listViewContext->getItemByUid($remoteItem["relatedUid"]);
            }

            if ($remoteItem["type"] == "attribute_group" || $remoteItem["type"] == "custom_html") {
                $relatedItem = $attributeGroupContext->getOneBy(array("uid" => $remoteItem["relatedUid"]));
            }

            if ($relatedItem != null) {
                $localItem->setRelatedId($relatedItem->getId());
            }

            if(empty($this->blockManager)){
                $this->blockManager = $this->container->get("block_manager");
            }

            $this->blockManager->save($localItem);
        }
    }


    public function syncNavigationLinks($remoteArray, $updateExisting = true)
    {
        /**@var \AppBundle\Context\NavigationLinkContext $navigationLinkContext */
        $navigationLinkContext = $this->container->get("navigation_link_context");

        /**@var \AppBundle\Context\PageContext $pageContext */
        $pageContext = $this->container->get("page_context");

        foreach ($remoteArray as $remoteItem) {
            $localItem = $navigationLinkContext->getOneBy(array("uid" => $remoteItem["uid"]));

            if ($localItem != null && $updateExisting == false)
                continue;

            if ($localItem == null) {
                $localItem = new Entity\NavigationLink();
                $localItem->setUid($remoteItem["uid"]);
            }

            if ($remoteItem["pageUid"] != "") {
                $page = $pageContext->getItemByUid($remoteItem["pageUid"]);
                $localItem->setPage($page);
            }

            if ($remoteItem["parentUid"] != "") {
                $parent = $navigationLinkContext->getOneBy(array("uid" => $remoteItem["parentUid"]));

                $localItem->setParent($parent);
            }

            $localItem->setBundle($remoteItem["bundle"]);
            $localItem->setDisplayName($remoteItem["displayName"]);
            $localItem->setUrl($remoteItem["url"]);
            $localItem->setOrder($remoteItem["ord"]);
            $localItem->setShow($remoteItem["shw"]);
            $localItem->setTarget($remoteItem["target"]);
            $localItem->setImage($remoteItem["image"]);
            $localItem->setCssClass($remoteItem["cssClass"]);
            $localItem->setIsParent($remoteItem["isParent"]);
            if(isset($remoteItem["isCustom"])){
                $localItem->setIsCustom($remoteItem["isCustom"]);
            }
            else{
                $localItem->setIsCustom(0);
            }

            if(empty($this->navigationLinkManager)){
                $this->navigationLinkManager = $this->container->get("navigation_link_manager");
            }

            $this->navigationLinkManager->save($localItem);
        }
    }
}
