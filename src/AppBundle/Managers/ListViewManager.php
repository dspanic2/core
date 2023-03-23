<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\ListViewAttributeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Context\PageContext;
use AppBundle\Context\RoleContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\ListView;
use AppBundle\Entity\ListViewAttribute;
use AppBundle\Entity\UserEntity;
use AppBundle\Factory\FactoryContext;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\UUIDHelper;
use AppBundle\Interfaces\Managers\ListViewManagerInterface;
use AppBundle\QueryBuilders\AttributeQueryBuilder;
use Doctrine\Common\Util\Inflector;
use Monolog\Logger;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Collections\ArrayCollection;

class ListViewManager extends AbstractBaseManager implements ListViewManagerInterface
{
    /**@var FactoryContext $factoryContext */
    protected $factoryContext;
    /**@var ListViewContext $listViewContext */
    protected $listViewContext;
    /**@var ListViewAttributeContext $listViewAttributeContext */
    protected $listViewAttributeContext;
    /**@var AttributeContext $attributeContext */
    protected $attributeContext;
    /**@var DatabaseContext $databaseContext */
    protected $databaseContext;
    /**@var PageContext $pageContext */
    protected $pageContext;
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /**@var PageManager $pageManager */
    protected $pageManager;
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /** @var SyncManager $syncManager */
    protected $syncManager;
    /** @var PrivilegeManager $privilegeManager */
    protected $privilegeManager;

    public function initialize()
    {
        parent::initialize();
    }

    public function getEntityType($typeName)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $attributeSet = $this->entityManager->getAttributeSetByCode($typeName);
        $entityType = $attributeSet->getEntityType();

        return $entityType;
    }

    public function getTemplate($typeName, $viewName)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $attributeSet = $this->entityManager->getAttributeSetByCode($typeName);

        if (!empty($attributeSet->getLayouts())) {
            $layouts = json_decode($attributeSet->getLayouts());
            if ($layouts->$viewName) {
                return $layouts->$viewName->type;
            }
        }

        return "1column";
    }

    /**
     * @param $view_id
     * @return bool
     */
    public function getListViewModel($view_id)
    {
        if (empty($this->listViewContext)) {
            $this->listViewContext = $this->container->get("list_view_context");
        }

        /** @var ListView $listView */
        $listView = $this->listViewContext->getItemById($view_id);

        if (empty($listView)) {
            return false;
        }

        /**
         * Check privileges
         */
        if (!$this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {

            $authorized = false;

            /**
             * Check list view
             */
            if ($this->user->hasPrivilege(6, $listView->getUid())) {
                $authorized = true;
            }

            if (!$authorized) {
                $this->logger->info("Unauthorized access: username " . $this->user->getUsername() . " - getListView " . $listView->getId());
                return false;
            }
        }

        return $listView;
    }

    /**
     * @param CoreUserEntity $user
     * @param EntityType $entityType
     * @return array
     */
    public function getListViewsForUserByType(UserEntity $user, EntityType $entityType)
    {
        if (empty($this->listViewContext)) {
            $this->listViewContext = $this->container->get("list_view_context");
        }

        return $this->listViewContext->getBy(array('entityType' => $entityType, "publicView" => 1), array("displayName" => "asc"));
    }

    /**
     * @param CoreUserEntity $user
     * @param AttributeSet $attributeSet
     * @return array
     */
    public function getListViewsForUserByAttributeSet(UserEntity $user, AttributeSet $attributeSet)
    {
        if (empty($this->listViewContext)) {
            $this->listViewContext = $this->container->get("list_view_context");
        }

        $listViews = $this->listViewContext->getBy(array("attributeSet" => $attributeSet, "publicView" => 1), array("displayName" => "asc"));
        if (!empty($listViews)) {
            /**
             * @var $key
             * @var ListView $listView
             */
            foreach ($listViews as $key => $listView) {
                if (!$user->hasPrivilege(6, $listView->getUid())) {
                    unset($listViews[$key]);
                }
            }
        }

        return $listViews;
    }

    public function getListViewHeader($view_id)
    {
        if (empty($this->listViewContext)) {
            $this->listViewContext = $this->container->get("list_view_context");
        }

        $view = $this->listViewContext->getById($view_id);
        $listviewAttributes = $view->getListViewAttributes();

        $iterator = $listviewAttributes->getIterator();
        $iterator->uasort(function ($a, $b) {
            return ($a->getOrder() < $b->getOrder()) ? -1 : 1;
        });
        $listviewAttributes = new ArrayCollection(iterator_to_array($iterator));

        $html = '<tr class="sp-table-header">';//$this->twig->render("AppBundle:Includes:list_view_row_header_begin.html.twig", array());

        $massActions = $view->getMassActions();
        $showMassActions = false;
        if (!empty(trim($massActions))) {
            if (!$this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                $massActions = json_decode($massActions);
                foreach ($massActions as $massAction) {
                    if (!empty($massAction->actionType) && $this->user->hasPrivilege($massAction->actionType, $view->getAttributeSet()->getUid())) {
                        $showMassActions = true;
                        break;
                    } elseif (empty($massAction->actionType)) {
                        $showMassActions = true;
                        break;
                    }
                }
            } else {
                $showMassActions = true;
            }
        }

        if ($showMassActions) {
            $html .= $this->twig->render("AppBundle:Includes:list_view_header_checkbox.html.twig", array());
        }

        foreach ($listviewAttributes as $listviewAttribute) {
            $attribute = $listviewAttribute->getAttribute();
            $field = $this->container->get($attribute->getFrontendType() . '_field');

            $field->SetAttribute($attribute);
            $field->SetListViewAttribute($listviewAttribute);


            $html .= $field->GetListViewHeaderHtml();
        }

        $html .= $this->twig->render("AppBundle:Includes:list_view_header_actions.html.twig", array());
        $html .= "</tr>";//$this->twig->render("AppBundle:Includes:list_view_row_end.html.twig", array());

        return $html;
    }

    public function getListViewDataModelHtml($view_id, DataTablePager $pager, $editable = false)
    {
        if (empty($this->listViewContext)) {
            $this->listViewContext = $this->container->get("list_view_context");
        }
        if (empty($this->factoryContext)) {
            $this->factoryContext = $this->container->get("factory_context");
        }

        /** @var ListView $view */
        $view = $this->listViewContext->getById($view_id);
        /** @var EntityType $entityType */
        $entityType = $view->getEntityType();

        $context = $this->factoryContext->getContext($entityType);

        $entities = $this->getListViewDataModel($view, $pager);

        $model = array();
        $model["draw"] = $pager->getDraw();
        $model["recordsTotal"] = $context->countAllItems();
        $model["recordsFiltered"] = $context->countFilteredItems($pager);
        $model["data"] = array();

        $html = "";

        $massActions = $view->getMassActions();
        $showMassActions = false;
        if (!empty(trim($massActions))) {
            if (!$this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                $massActions = json_decode($massActions);
                foreach ($massActions as $massAction) {
                    if (!empty($massAction->actionType) && $this->user->hasPrivilege($massAction->actionType, $view->getAttributeSet()->getUid())) {
                        $showMassActions = true;
                        break;
                    } elseif (empty($massAction->actionType)) {
                        $showMassActions = true;
                        break;
                    }
                }
            } else {
                $showMassActions = true;
            }
        }

        if (count($entities) > 0) {
            foreach ($entities as $key => $entity) {

                $index = "odd";
                if ($key % 2 == 0) {
                    $index = "even";
                }

                /**
                 * Check privileges for row action
                 */
                $rowButton = null;
                if (!empty(trim($view->getRowActions()))) {
                    $buttonTmp = json_decode($view->getRowActions());
                    if (!$this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                        if (!empty($buttonTmp->actionType) && $this->user->hasPrivilege($buttonTmp->actionType, $entity->getAttributeSet()->getUid())) {
                            $rowButton = $buttonTmp;
                        } elseif (empty($buttonTmp->actionType)) {
                            $rowButton = $buttonTmp;
                        }
                    } else {
                        $rowButton = $buttonTmp;
                    }
                }

                $html .= $this->twig->render("AppBundle:Includes:list_view_row_begin.html.twig", array('entity' => $entity, 'index' => $index, 'row_action' => $rowButton));

                if ($showMassActions) {
                    $html .= $this->twig->render("AppBundle:Includes:list_view_checkbox.html.twig", array('entity' => $entity));
                }
                $listviewAttributes = $view->getListViewAttributes();
                foreach ($listviewAttributes as $listviewAttribute) {

                    $field = $this->container->get($listviewAttribute->getAttribute()->getFrontendType() . '_field');
                    $field->SetAttribute($listviewAttribute->getAttribute());
                    $field->SetEntity($entity);
                    $field->SetListViewAttribute($listviewAttribute);
                    if ($editable) {
                        $field->setListMode("edit");
                    }
                    $html .= $field->GetListViewHtml();
                }

                /**
                 * Check privileges for each button
                 */
                $mainButton = null;
                if (!empty(trim($view->getMainButton()))) {
                    $buttonTmp = json_decode($view->getMainButton());
                    if (!$this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                        if (!empty($buttonTmp->actionType) && $this->user->hasPrivilege($buttonTmp->actionType, $entity->getAttributeSet()->getUid())) {
                            $mainButton = $buttonTmp;
                        } elseif (empty($buttonTmp->actionType)) {
                            $mainButton = $buttonTmp;
                        }
                    } else {
                        $mainButton = $buttonTmp;
                    }
                }

                $dropdownButtons = array();
                if (!empty(trim($view->getDropdownButtons()))) {
                    $dropdownButtonsTmp = json_decode($view->getDropdownButtons());
                    foreach ($dropdownButtonsTmp as $dropdownButtonTmp) {
                        if (!$this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                            if (!empty($dropdownButtonTmp->actionType) && $this->user->hasPrivilege($dropdownButtonTmp->actionType, $entity->getAttributeSet()->getUid())) {
                                $dropdownButtons[] = $dropdownButtonTmp;
                            } elseif (empty($dropdownButtonTmp->actionType)) {
                                $dropdownButtons[] = $dropdownButtonTmp;
                            }
                        } else {
                            $dropdownButtons[] = $dropdownButtonTmp;
                        }
                    }
                }

                $html .= $this->twig->render("AppBundle:Includes:list_view_actions.html.twig", array(
                    'entity' => $entity,
                    'main_button' => $mainButton,
                    'dropdown_buttons' => $dropdownButtons,
                    'editable' => $editable
                ));

                $html .= "</tr>";//$this->twig->render("AppBundle:Includes:list_view_row_end.html.twig", array());
            }
        } else {
            $html = $this->twig->render("AppBundle:Includes:empty_list.html.twig", array());
        }

        $model["html"] = $html;

        return $model;
    }

    public function getListViewFilters(\AppBundle\DataTable\DataTablePager $pager, $decodedFilters, $currentDate, &$entityStateFilterSet = false)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        if (empty($decodedFilters)) {
            return null;
        }

        if (array_key_exists(0, $decodedFilters)) {
            $decodedFilters = $decodedFilters[0];
        }


        $compositeFilter = new CompositeFilter();

        if (property_exists($decodedFilters, "connector")) {
            $compositeFilter->setConnector($decodedFilters->connector);
        }

        if (isset($decodedFilters->filters)) {
            foreach ($decodedFilters->filters as $subFilter) {
                if (property_exists($subFilter, "connector")) {
                    $subSearchFilter = $this->getListViewFilters($pager, $subFilter, $currentDate, $entityStateFilterSet);
                    $compositeFilter->addFilter($subSearchFilter);
                } else {
                    $subSearchFilter = new SearchFilter();

                    /**
                     * filter po env
                     */
                    if(stripos($subFilter->value,"{ENV") !== false){
                        $tmp = explode("{ENV:",$subFilter->value);
                        if(count($tmp) == 2){
                            $tmp = explode("}",$tmp[1]);
                            if(count($tmp) == 2){
                                if(isset($_ENV[$tmp[0]])){
                                    $subFilter->value = str_replace("{ENV:".$tmp[0]."}", $_ENV[$tmp[0]], $subFilter->value);
                                }
                            }
                            else{
                                continue;
                            }
                        }
                        else{
                            continue;
                        }
                    }

                    $subFilter->value = str_replace("{id}", $pager->getRequestId(), $subFilter->value);
                    $subFilter->value = str_replace("{related_entity_type}", $pager->getType(), $subFilter->value);
                    $subFilter->value = str_replace("{now}", $currentDate->format("Y-m-d H:i:s"), $subFilter->value);
                    $subFilter->value = str_replace("{user_id}", $this->user->getId(), $subFilter->value);

                    if (stripos($subFilter->value, "{parentEntity}") !== false) {
                        $parentEntityType = $this->entityManager->getEntityTypeByCode($pager->getType());
                        $parentEntity = $this->entityManager->getEntityByEntityTypeAndId($parentEntityType, $pager->getRequestId());

                        $filter_parts = explode(".", $subFilter->value);
                        unset($filter_parts[0]);

                        $fValue = $parentEntity;
                        foreach ($filter_parts as $filter_part) {
                            $getter = EntityHelper::makeGetter($filter_part);
                            $fValue = $fValue->{$getter}();
                        }

                        $subFilter->value = $fValue;
                    } else if (stripos($subFilter->value, "{employee}") !== false) {
                        if (empty($this->helperManager)) {
                            $this->helperManager = $this->container->get("helper_manager");
                        }

                        /** @var CoreUserEntity $coreUser */
                        $coreUser = $this->helperManager->getCurrentCoreUser();

                        /** @var EmployeeEntity $employee */
                        $employee = $coreUser->getDefaultEmployee();
                        if (!empty($employee)) {
                            $filter_parts = explode(".", $subFilter->value);
                            unset($filter_parts[0]);

                            $fValue = $employee;
                            foreach ($filter_parts as $filter_part) {
                                $getter = EntityHelper::makeGetter($filter_part);
                                $fValue = $fValue->{$getter}();
                            }

                            $subFilter->value = $fValue;
                        }
                    } elseif (stripos($subFilter->value, "method:") !== false) {
                        $parts = explode(":", $subFilter->value);

                        $manager = $this->container->get($parts[1]);
                        $method = $parts[2];
                        $parameters = null;
                        if (isset($parts[3])) {
                            $parameters = $parts[3];
                            $fValue = $manager->{$method}($parameters);
                        } else {
                            $fValue = $manager->{$method}();
                        }

                        $subFilter->value = $fValue;
                    }


                    if ($subFilter->field == "entityStateId") {
                        $entityStateFilterSet = true;
                    }

                    $subSearchFilter->setFromArray($subFilter);
                    $compositeFilter->addFilter($subSearchFilter);
                }
            }
        }

        return $compositeFilter;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getListViewById($id){

        if(empty($this->listViewContext)){
            $this->listViewContext = $this->container->get("list_view_context");
        }

        return $this->listViewContext->getById($id);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getListViewByUid($uid){

        if(empty($this->listViewContext)){
            $this->listViewContext = $this->container->get("list_view_context");
        }

        return $this->listViewContext->getOneBy(Array("uid" => $uid));
    }

    /**
     * @param $listViewId
     * @param $data
     * @return array
     */
    public function getListViewDataModelEntities($listViewId,$data){

        $pager = new DataTablePager();
        $pager->setFromPost($data);
        $pager->setLenght(100000);

        if(empty($this->listViewManager)){
            $this->listViewManager = $this->getContainer()->get("list_view_manager");
        }

        $view = $this->listViewManager->getListViewById($listViewId);

        return $this->listViewManager->getListViewDataModel($view, $pager);
    }

    public function getListViewDataModel(ListView $view, \AppBundle\DataTable\DataTablePager $pager)
    {
        if (empty($this->factoryContext)) {
            $this->factoryContext = $this->container->get("factory_context");
        }

        $currentDate = new \DateTime();
        $entityStateFilterSet = false;
        $decodedFilters = (array)json_decode($view->getFilter());

        $compositeFilters = $this->getListViewFilters($pager, $decodedFilters, $currentDate, $entityStateFilterSet);

        if (!empty($compositeFilters)) {
            if (!$entityStateFilterSet) {
                $this->includeEntityStateActiveFilter($compositeFilters);
            }
        } else {
            $compositeFilters = new CompositeFilter();
            $this->includeEntityStateActiveFilter($compositeFilters);
        }

        $pager->addFilter($compositeFilters);

        $context = $this->factoryContext->getContext($view->getEntityType());
        $entities = $context->getItemsWithPaging($pager);

        return $entities;
    }

    public function getDistinctLookupValues($attribute)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $queryBuilder = new AttributeQueryBuilder();
        $results = $this->databaseContext->executeQuery($queryBuilder->getDistinctLookupValues($attribute));

        return $results;
    }

    public function getGroupsForPrincipalLookupValues($prinicpal)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $queryBuilder = new AttributeQueryBuilder();
        $results = $this->databaseContext->executeQuery($queryBuilder->getGroupsForPrincipalLookupValues($prinicpal));

        return $results;
    }

    /**
     * @param ListView $listView
     * @return ListView|bool|mixed
     * @throws \Exception
     */
    public function saveListView(ListView $listView)
    {
        if (empty($this->listViewContext)) {
            $this->listViewContext = $this->container->get("list_view_context");
        }

        try {
            $listView = $this->listViewContext->save($listView);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        $this->syncManager->exportEntityByTableAndId("list_view", $listView->getId());

        return $listView;
    }

    /**
     * @param ListViewAttribute $listViewAttribute
     * @return ListViewAttribute|bool
     * @throws \Exception
     */
    public function saveListViewAttribute(ListViewAttribute $listViewAttribute)
    {
        if (empty($this->listViewAttributeContext)) {
            $this->listViewAttributeContext = $this->container->get("list_view_attribute_context");
        }

        try {
            $this->listViewAttributeContext->save($listViewAttribute);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return $listViewAttribute;
    }

    /**
     * @param ListViewAttribute $listViewAttribute
     * @return bool
     * @throws \Exception
     */
    public function deleteListViewAttribute(ListViewAttribute $listViewAttribute)
    {
        if (empty($this->listViewAttributeContext)) {
            $this->listViewAttributeContext = $this->container->get("list_view_attribute_context");
        }

        $listViewId = $listViewAttribute->getListView()->getId();

        try {
            $this->listViewAttributeContext->delete($listViewAttribute);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        $this->syncManager->exportEntityByTableAndId("list_view", $listViewId);

        return true;
    }

    /**
     * @param ListView $listView
     * @return mixed
     */
    public function getListViewAttributes(ListView $listView)
    {

        if (empty($this->listViewAttributeContext)) {
            $this->listViewAttributeContext = $this->container->get("list_view_attribute_context");
        }

        return $this->listViewAttributeContext->getBy(array("listView" => $listView));

    }

    /**
     * @param $attributeId
     * @param ListView $listView
     * @return array|string
     */
    public function getListViewAttributeField($attributeId, ListView $listView)
    {
        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->container->get("attribute_context");
        }

        if (preg_match('/^\d+$/', $attributeId)) {
            /** @var Attribute $attribute */
            $attribute = $this->attributeContext->getById($attributeId);
        } else {
            /** @var Attribute $attribute */
            $attribute = $this->attributeContext->getItemByUid($attributeId);
        }


        $attributeModel = Inflector::camelize($attribute->getAttributeCode());

        if ($attribute->getEntityType()->getEntityTypeCode() != $listView->getEntityType()->getEntityTypeCode()) {

            $linkAttribute = $this->attributeContext->getOneBy(array("entityType" => $listView->getEntityType(), "lookupEntityType" => $attribute->getEntityType()));

            if (!empty($linkAttribute)) {

                $linkAttributeModel = Inflector::camelize($linkAttribute->getAttributeCode());
                $linkAttributeModel = str_replace("Id", "", $linkAttributeModel);

                $attributeModel = $linkAttributeModel . "." . $attributeModel;
            }

        }

        if ($attribute->getBackendType() == "lookup") {

            if ($attribute->getFrontendType() == "multiselect") {
                $attributeModel = str_replace("Id", "", $attributeModel);

                $lookupAttribute = $attribute->getLookupAttribute();
                if(!empty($lookupAttribute->getLookupAttribute())){
                    $lookupAttribute = $lookupAttribute->getLookupAttribute();
                }
                $attributeLookupModel = Inflector::camelize($lookupAttribute->getAttributeCode());
                $attributeLookupModel = str_replace("Id", "", $attributeLookupModel);

                $attributeModel = $attributeModel . "." . $attributeLookupModel;
            } else {
                $attributeModel = str_replace("Id", "", $attributeModel);

                $lookupAttribute = $attribute->getLookupAttribute();
                $attributeLookupModel = Inflector::camelize($lookupAttribute->getAttributeCode());
                $attributeLookupModel = str_replace("Id", "", $attributeLookupModel);

                $attributeModel = $attributeModel . "." . $attributeLookupModel;
            }
        }

        return $attributeModel;
    }

    /**
     * @param ListView $listView
     * @param $attributes
     * @param $generatePages
     * @return bool|ListView|mixed
     */
    public function addListView(ListView $listView, $attributes, $generatePages)
    {
        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->container->get("attribute_context");
        }

        if (empty($listView->getMainButton())) {
            $listView->setMainButton('{"type":"link","name":"Edit","class":"","url":"page_view","action":"","actionType":3,"form_type":"form"}');
        }
        if (empty($listView->getDropdownButtons())) {
            $listView->setDropdownButtons('[{"type":"link","name":"Edit","class":"","url":"page_view","action":"","actionType":3,"form_type":"form"},{"type":"button","name":"Delete","class":"","url":"delete","action":"standard_grid_action","actionType":4,"confirm":"true"}]');
        }
        if (empty($listView->getRowActions())) {
            $listView->setRowActions('{"type":"button","name":"View","class":"","url":"page_view","action":"standard_row_action","actionType":2,"confirm":"false","form_type":"view"}');
        }

        $listView = $this->saveListView($listView);
        if (empty($listView)) {
            return false;
        }

        $order = 1;
        foreach ($attributes as $attributeId => $details) {

            $display = false;
            if (isset($details["display"])) {
                $display = true;
            }

            $attribute = $this->attributeContext->getById($attributeId);
            if (!isset($attribute) || empty($attribute)) {
                continue;
            }

            $listViewAttribute = new ListViewAttribute();

            if (empty($details["field"])) {
                $details["field"] = $this->getListViewAttributeField($attributeId, $listView);
            }

            $listViewAttribute->setOrder($order);
            $listViewAttribute->setAttribute($attribute);
            $listViewAttribute->setListView($listView);
            $listViewAttribute->setDisplay($display);
            $listViewAttribute->setField($details["field"]);
            $listViewAttribute->setLabel($details["label"]);

            $listViewAttribute = $this->saveListViewAttribute($listViewAttribute);
            if (empty($listViewAttribute)) {
                return false;
            }

            $order++;
        }

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        $this->syncManager->exportEntityByTableAndId("list_view", $listView->getId());

        if (empty($this->privilegeManager)) {
            $this->privilegeManager = $this->container->get("privilege_manager");
        }
        $this->privilegeManager->addPrivilegesToAllGroups('list_view', $listView->getUid());

        if ($generatePages) {
            if (empty($this->pageManager)) {
                $this->pageManager = $this->container->get("page_manager");
            }

            $this->pageManager->generateListViewPage($listView);
        }

        return $listView;
    }

    public function deleteListView(ListView $listView)
    {
        if (empty($this->listViewAttributeContext)) {
            $this->listViewAttributeContext = $this->container->get("list_view_attribute_context");
        }
        if (empty($this->listViewContext)) {
            $this->listViewContext = $this->container->get("list_view_context");
        }
        if (empty($this->pageContext)) {
            $this->pageContext = $this->container->get("page_context");
        }
        if (empty($this->pageManager)) {
            $this->pageManager = $this->container->get("page_manager");
        }

        $listViewAttributes = $this->listViewAttributeContext->getBy(array('listView' => $listView));

        foreach ($listViewAttributes as $listViewAttribute) {
            $this->deleteListViewAttribute($listViewAttribute);
        }

        $pages = $this->pageContext->getBy(array('url' => strtolower($listView->getName()), 'type' => 'list'));
        foreach ($pages as $page) {
            $this->pageManager->delete($page);
        }

        if (empty($this->privilegeManager)) {
            $this->privilegeManager = $this->container->get("privilege_manager");
        }
        $this->privilegeManager->removePrivilege(6, $listView->getUid());

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        try {
            $row = $this->syncManager->getEntityRecordById("list_view", $listView->getId());
            $this->syncManager->deleteEntityRecord("list_view", $row);

            $this->listViewContext->delete($listView);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param ListView $listView
     * @return ListView|bool|mixed
     * @throws \Exception
     */
    public function duplicateListView(ListView $listView)
    {
        $clonedListView = clone($listView);
        $clonedListView->setName($listView->getName() . '_copy');
        $clonedListView->setId(null);
        $clonedListView->setUid(null);
        $clonedListView->setIsCustom(1);

        $clonedListView = $this->saveListView($clonedListView);

        /**@var \AppBundle\Entity\ListViewAttribute $oldAttribute */
        foreach ($listView->getListViewAttributes() as $oldAttribute) {
            $listViewAttribute = clone $oldAttribute;
            $listViewAttribute->setListView($clonedListView);
            $this->saveListViewAttribute($listViewAttribute);
        }

        if (empty($this->syncManager)) {
            $this->syncManager = $this->container->get("sync_manager");
        }

        $this->syncManager->exportEntityByTableAndId("list_view", $clonedListView->getId());

        return $clonedListView;
    }

    /**
     * @param $compositeFilters
     */
    public function includeEntityStateActiveFilter($compositeFilters)
    {
        $searchFilter = new SearchFilter();
        $searchFilter->setField("entityStateId");
        $searchFilter->setOperation("eq");
        $searchFilter->setValue("1");
        $compositeFilters->addFilter($searchFilter);

        return true;
    }
}
