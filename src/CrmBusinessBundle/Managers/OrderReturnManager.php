<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\ApplicationSettingsManager;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderItemEntity;
use CrmBusinessBundle\Entity\OrderReturnStateHistoryEntity;
use CrmBusinessBundle\Entity\OrderStateEntity;
use CrmBusinessBundle\Entity\OrderStateHistoryEntity;
use ScommerceBusinessBundle\Entity\OrderReturnEntity;
use ScommerceBusinessBundle\Entity\OrderReturnItemEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;

class OrderReturnManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var ApplicationSettingsManager $applicationSettingsManager */
    protected $applicationSettingsManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @param $id
     * @return |null
     */
    public function getOrderReturnById($id)
    {
        if (empty($this->orderEntityType)) {
            $this->orderEntityType = $this->entityManager->getEntityTypeByCode("order_return");
        }

        return $this->entityManager->getEntityByEntityTypeAndId($this->orderEntityType, $id);
    }

    /**
     * @param OrderReturnEntity $orderReturn
     * @return bool
     */
    public function setOrderReturnStateHistory(OrderReturnEntity $orderReturn)
    {

        /** @var OrderReturnStateHistoryEntity $orderReturnStateHistory */
        $orderReturnStateHistory = $this->getLastOrderReturnStateHistory($orderReturn);

        $now = new \DateTime();

        if (empty($orderReturnStateHistory)) {
            $this->addOrderReturnStateHistory($orderReturn);
        } else {
            if ($orderReturn->getEntityStateId() == 2) {
                $orderReturnStateHistory->setDateTo($now);
                $orderReturnStateHistory->setSecondsDiff($now->getTimestamp() - $orderReturnStateHistory->getDateFrom()->getTimestamp());
                $this->entityManager->saveEntityWithoutLog($orderReturnStateHistory);
            } else {
                if ($orderReturnStateHistory->getOrderReturnStateId() != $orderReturn->getOrderReturnStateId()) {
                    $orderReturnStateHistory->setDateTo($now);
                    $orderReturnStateHistory->setSecondsDiff($now->getTimestamp() - $orderReturnStateHistory->getDateFrom()->getTimestamp());
                    $this->entityManager->saveEntityWithoutLog($orderReturnStateHistory);

                    $this->addOrderReturnStateHistory($orderReturn, $orderReturnStateHistory);
                }
            }
        }

        return true;
    }

    /**
     * @param OrderReturnEntity $orderReturn
     * @return null
     */
    public function getLastOrderReturnStateHistory(OrderReturnEntity $orderReturn)
    {

        $et = $this->entityManager->getEntityTypeByCode("order_return_state_history");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("orderReturn", "eq", $orderReturn->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "desc"));

        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param OrderReturnEntity $orderReturn
     * @param null $previousOrderReturnStateHistory
     * @return null
     */
    public function addOrderReturnStateHistory(OrderReturnEntity $orderReturn, $previousOrderReturnStateHistory = null)
    {

        /** @var OrderReturnStateHistoryEntity $orderReturnStateHistory */
        $orderReturnStateHistory = $this->entityManager->getNewEntityByAttributSetName("order_return_state_history");

        $orderReturnStateHistory->setDateFrom(new \DateTime());
        $orderReturnStateHistory->setOrderReturn($orderReturn);
        $orderReturnStateHistory->setOrderReturnState($orderReturn->getOrderReturnState());
        $orderReturnStateHistory->setPreviousOrderReturnStateHistory($previousOrderReturnStateHistory);

        return $this->entityManager->saveEntityWithoutLog($orderReturnStateHistory);
    }

    /**
     * @param OrderEntity $order
     * @param array $orderItems
     * @param array $data
     * @return OrderReturnEntity
     */
    public function createOrderReturn(OrderEntity $order, $orderItems = array(), $data = array())
    {

        /** @var OrderReturnEntity $orderReturn */
        $orderReturn = $this->entityManager->cloneEntity($order, "order_return");

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($orderReturn, $setter)) {
                $orderReturn->$setter($value);
            }
        }

        $this->entityManager->saveEntity($orderReturn);

        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        foreach ($orderItems as $orderItemData) {

            /** @var OrderItemEntity $orderItem */
            $orderItem = $this->orderManager->getOrderItemById($orderItemData["item"]);

            /** @var OrderReturnItemEntity $orderReturnItem */
            $orderReturnItem = $this->entityManager->cloneEntity($orderItem, "order_return_item");
            $orderReturnItem->setQty(floatval($orderItemData["return_qty"]));
            $orderReturnItem->setOrderReturn($orderReturn);
            $orderReturnItem->setOrderItem($orderItem);

            $this->entityManager->saveEntity($orderReturnItem);
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $this->crmProcessManager->recalculateOrderReturnTotals($orderReturn);
        $this->crmProcessManager->afterOrderReturnCreated($orderReturn);

        return $orderReturn;
    }

    /**
     * @param OrderReturnEntity $orderReturn
     * @param $data
     * @return OrderReturnEntity
     */
    public function updateOrderReturn(OrderReturnEntity $orderReturn, $data)
    {
        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($orderReturn, $setter)) {
                $orderReturn->$setter($value);
            }
        }

        $this->entityManager->saveEntity($orderReturn);
        $this->entityManager->refreshEntity($orderReturn);

        return $orderReturn;
    }

    /**
     * @return array
     */
    public function getOrderReturnStatuses()
    {
        $orderStateEntityType = $this->entityManager->getEntityTypeByCode("order_return_state");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($orderStateEntityType, $compositeFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getOrderReturnStateById($id)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("order_return_state");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param OrderEntity $order
     * @return false
     */
    public function orderReturnEnabled(OrderEntity $order)
    {
        $orderReturnEnabled = false;

        if (!$this->isOrderValidForReturn($order)) {
            return $orderReturnEnabled;
        }

        $orderItems = $order->getOrderItems();
        if (EntityHelper::isCountable($orderItems) && count($orderItems) > 0) {
            /** @var OrderItemEntity $orderItem */
            foreach ($orderItems as $orderItem) {
                $isOrderReturnEnabled = $this->orderItemReturnEnabled($orderItem);
                if ($isOrderReturnEnabled) {
                    $orderReturnEnabled = true;
                    break;
                }
            }
        }

        return $orderReturnEnabled;
    }

    /**
     * @param OrderItemEntity $orderItem
     * @return false
     */
    public function orderItemReturnEnabled(OrderItemEntity $orderItem)
    {
        $ret = array();
        $ret["max_return_qty"] = 0;
        $ret["is_return_enabled"] = false;

        if (!$this->isOrderValidForReturn($orderItem->getOrder())) {
            return $ret;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT sum(qty) as count FROM order_return_item_entity WHERE order_item_id = {$orderItem->getId()};";
        $totalReturnedQty = $this->databaseContext->getSingleResult($q);

        if (empty($totalReturnedQty)) {
            $totalReturnedQty = 0;
        }

        $ret["max_return_qty"] = floatval($orderItem->getQty()) - floatval($totalReturnedQty);
        if ($ret["max_return_qty"] > 0) {
            $ret["is_return_enabled"] = true;
        } else {
            return $ret;
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $ret = $this->crmProcessManager->orderItemReturnEnabledCustom($orderItem, $ret);

        return $ret;
    }

    /**
     * @param OrderEntity $order
     * @return bool
     */
    public function isOrderValidForReturn(OrderEntity $order)
    {

        if ($order->getOrderState()->getOrderReturnEnabled() != 1) {
            return false;
        }

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->container->get("application_settings_manager");
        }

        if (empty($this->applicationSettingsManager->getApplicationSettingByCodeAndStore("order_return_enabled", $order->getStore()))) {
            return false;
        }

        $orderStateId = $this->applicationSettingsManager->getApplicationSettingByCodeAndStore("order_return_state_from", $order->getStore());
        if (empty($orderStateId)) {
            return false;
        }

        $orderStateReturnDays = $this->applicationSettingsManager->getApplicationSettingByCodeAndStore("order_return_days", $order->getStore());
        if (empty($orderStateReturnDays)) {
            return false;
        }

        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        /** @var OrderStateEntity $orderState */
        $orderState = $this->orderManager->getOrderStateById($orderStateId);

        /** @var OrderStateHistoryEntity $orderStateHistory */
        $orderStateHistory = $this->orderManager->getOrderStateHistoryByState($order, $orderState);

        if (empty($orderStateHistory)) {
            return false;
        }

        $date = $orderStateHistory->getDateFrom();
        $date->add(new \DateInterval("P{$orderStateReturnDays}D"));

        $now = new \DateTime();

        if ($now > $date) {
            return false;
        }

        return true;
    }

    /**
     * @param SStoreEntity $store
     * @return false|int|mixed
     */
    public function getOrderReturnNextIncrementId(SStoreEntity $store)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->container->get("application_settings_manager");
        }

        $q = "SELECT MAX(increment_id) as count FROM order_return_entity;";
        $incrementId = $this->databaseContext->getSingleResult($q);

        if (empty($incrementId)) {
            $incrementId = $this->applicationSettingsManager->getApplicationSettingByCodeAndStore("order_return_increment_start_from", $store);
            if (empty($incrementId)) {
                $incrementId = 1;
            }
        }

        $incrementId++;

        return $incrementId;
    }


    /**
     * @param AccountEntity $account
     * @return mixed |null
     */
    public function getReturnOrdersByAccount(AccountEntity $account)
    {
        $et = $this->entityManager->getEntityTypeByCode("order_return");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("account", "eq", $account->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("created", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }
}