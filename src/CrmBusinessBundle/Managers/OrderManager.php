<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Managers\ApplicationSettingsManager;
use AppBundle\Managers\AppTemplateManager;
use AppBundle\Managers\FileManager;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Entity\OrderComplaintEntity;
use CrmBusinessBundle\Entity\OrderComplaintItemEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderItemEntity;
use CrmBusinessBundle\Entity\OrderStateEntity;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\OrderStateHistoryEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;
use CrmBusinessBundle\Events\OrderCanceledEvent;
use CrmBusinessBundle\Events\OrderCreatedEvent;
use CrmBusinessBundle\Events\OrderReversedEvent;
use JMS\Serializer\Tests\Fixtures\Order;
use Monolog\Logger;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\EventDispatcher\EventDispatcher;

class OrderManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var AttributeContext $attributeContext */
    protected $attributeContext;
    /** @var AppTemplateManager $templateManager */
    protected $templateManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var EntityType $orderEntityType */
    protected $orderEntityType;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var ApplicationSettingsManager $applicationSettingsManager */
    protected $applicationSettingsManager;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @param $id
     * @return |null
     */
    public function getOrderStateById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(OrderStateEntity::class);
        return $repository->find($id);
    }

    /**
     * @deprecated
     * Obrisati kada budemo psiholoski spremni
     */
    /**
     * @return int
     */
    /*public function getNextIncrementId($currentOrderId = null)
    {
        $currentIncrementId = 1200001;

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $addon = "";
        if (!empty($currentOrderId)) {
            $addon = "WHERE id != {$currentOrderId} ";
        }

        $q = "SELECT MAX(increment_id) as increment_id FROM order_entity {$addon};";
        $result = $this->databaseContext->getSingleEntity($q);

        if (!empty($result) && isset($result["increment_id"])) {
            $currentIncrementId = $result["increment_id"];
        }

        return intval($currentIncrementId) + 1;
    }*/

    /**
     * @param $id
     * @return |null
     */
    public function getOrderById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(OrderEntity::class);
        return $repository->find($id);

        /*if (empty($this->orderEntityType)) {
            $this->orderEntityType = $this->entityManager->getEntityTypeByCode("order");
        }

        return $this->entityManager->getEntityByEntityTypeAndId($this->orderEntityType, $id);*/
    }

    /**
     * @param null $additionalFilter
     * @return null
     */
    public function getFilteredOrders($additionalFilter = null)
    {

        if (empty($this->orderEntityType)) {
            $this->orderEntityType = $this->entityManager->getEntityTypeByCode("order");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalFilter)) {
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($this->orderEntityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param null $additionalFilter
     * @return null
     */
    public function getFilteredOrderItems($additionalFilter = null)
    {

        $et = $this->entityManager->getEntityTypeByCode("order_item");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalFilter)) {
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param $incrementId
     * @return |null
     */
    public function getOrderByIncrementId($incrementId)
    {
        if (empty($this->orderEntityType)) {
            $this->orderEntityType = $this->entityManager->getEntityTypeByCode("order");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("incrementId", "eq", $incrementId));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($this->orderEntityType, $compositeFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getOrderItemById($id)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("order_item");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param $quoteId
     * @return |null
     */
    public function getOrderByQuoteId($quoteId)
    {
        if (empty($this->orderEntityType)) {
            $this->orderEntityType = $this->entityManager->getEntityTypeByCode("order");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("quote", "eq", $quoteId));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($this->orderEntityType, $compositeFilters);
    }

    /**
     * @param $orderHash
     * @return OrderEntity
     */
    public function getOrderByHash($orderHash)
    {
        if (empty($this->orderEntityType)) {
            $this->orderEntityType = $this->entityManager->getEntityTypeByCode("order");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("previewHash", "eq", $orderHash));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var OrderEntity $order */
        $order = $this->entityManager->getEntityByEntityTypeAndFilter($this->orderEntityType, $compositeFilters);

        return $order;
    }

    /**
     * @param OrderEntity $order
     * @param $data
     * @param false $skipLog
     * @return OrderEntity
     */
    public function updateOrder(OrderEntity $order, $data, $skipLog = false)
    {
        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($order, $setter)) {
                $order->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($order);
        } else {
            $this->entityManager->saveEntity($order);
        }
        $this->entityManager->refreshEntity($order);

        return $order;
    }

    /**
     * @param OrderItemEntity $orderItem
     * @param $data
     * @param false $skipLog
     * @return OrderItemEntity
     * @deprecated koristiti funkciju createUpdateOrderItem
     */
    public function updateOrderItem(OrderItemEntity $orderItem, $data, $skipLog = false)
    {
        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($orderItem, $setter)) {
                $orderItem->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($orderItem);
        } else {
            $this->entityManager->saveEntity($orderItem);
        }

        $this->entityManager->refreshEntity($orderItem);

        return $orderItem;
    }

    /**
     * @param $data
     * @param OrderItemEntity|null $orderItem
     * @param false $skipLog
     * @return OrderItemEntity|null
     */
    public function createUpdateOrderItem($data, OrderItemEntity $orderItem = null, $skipLog = false)
    {
        if (empty($orderItem)) {
            /** @var OrderItemEntity $orderItem */
            $orderItem = $this->entityManager->getNewEntityByAttributSetName("order_item");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($orderItem, $setter)) {
                $orderItem->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($orderItem);
        } else {
            $this->entityManager->saveEntity($orderItem);
        }
        $this->entityManager->refreshEntity($orderItem);

        return $orderItem;
    }

    /**
     * @param AccountEntity $account
     * @return |null
     */
    public function getOrdersByAccount(AccountEntity $account)
    {
        if (empty($this->orderEntityType)) {
            $this->orderEntityType = $this->entityManager->getEntityTypeByCode("order");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("account", "eq", $account->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("created", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($this->orderEntityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param AccountEntity $account
     * @return |null
     */
    public function getOrdersByAccountAndId(AccountEntity $account, $orderId)
    {
        if (empty($this->orderEntityType)) {
            $this->orderEntityType = $this->entityManager->getEntityTypeByCode("order");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("account", "eq", $account->getId()));
        $compositeFilter->addFilter(new SearchFilter("id", "eq", $orderId));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("created", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($this->orderEntityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param QuoteEntity $quote
     * @param $order_state_id
     * @return mixed
     */
    public function generateOrderFromQuote(QuoteEntity $quote, $order_state_id)
    {
        /** @var OrderStateEntity $state */
        $state = $this->getOrderStateById($order_state_id);

        /**
         * 27.08.2022, dodano je da tryAssignLookupAttributes treba biti false
         */
        $order = $this->entityManager->cloneEntity($quote, "order", array(), false);

        $order->setOrderState($state);
        $order->setQuote($quote);

        $this->entityManager->saveEntityWithoutLog($order);

        $quoteItems = $quote->getQuoteItems();
        $orderItemArray = array();
        if (EntityHelper::isCountable($quoteItems) && count($quoteItems)) {
            /** @var QuoteItemEntity $quoteItem */
            foreach ($quoteItems as $quoteItem) {
                /** @var OrderItemEntity $orderItem */
                $orderItem = $this->entityManager->cloneEntity($quoteItem, "order_item", array("parent_item_id" => null));
                $orderItem->setOrder($order);
                if (!empty($quoteItem->getParentItem())) {
                    $orderItem->setParentItem($orderItemArray[$quoteItem->getParentItemId()]);
                }
                $orderItemArray[$quoteItem->getId()] = $this->entityManager->saveEntityWithoutLog($orderItem);
            }
        }

        $this->entityManager->refreshEntity($order);

        $this->dispatchOrderCreated($order);

        return $order;
    }

    // DISPATCH EVENTS

    /**
     * @param OrderEntity $order
     */
    public function dispatchOrderCreated(OrderEntity $order)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->container->get("event_dispatcher");
        $eventDispatcher->dispatch(OrderCreatedEvent::NAME, new OrderCreatedEvent($order));
    }

    /**
     * @param OrderEntity $order
     */
    public function dispatchOrderCanceled(OrderEntity $order)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->container->get("event_dispatcher");
        $eventDispatcher->dispatch(OrderCanceledEvent::NAME, new OrderCanceledEvent($order));
    }

    /**
     * @param OrderEntity $order
     */
    public function dispatchOrderReversed(OrderEntity $order)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->container->get("event_dispatcher");
        $eventDispatcher->dispatch(OrderReversedEvent::NAME, new OrderReversedEvent($order));
    }

    /**
     * @param OrderEntity $order
     * @return OrderEntity
     */
    public function calculateOrderTotals(OrderEntity $order)
    {
        $totalPaid = 0;

        $priceTotal = floatval($order->getPriceTotal());

        $totalLeftToPay = $priceTotal - $totalPaid;

        if ($totalLeftToPay < 0) {
            $this->logger->error("Total paid negative for order id: " . $order->getId() . ". Please sum all installments manually");
            $totalLeftToPay = 0;
        }

        $order->setTotalPaid($totalPaid);
        $order->setTotalLeftToPay($totalLeftToPay);

        if ($totalLeftToPay == 0) {
            if ($_ENV["AUTOMATICALLY_CLOSE_ORDERS"]) {
                //TODO AUTOMATICALLY_CLOSE_ORDERS ovdje mi nije jasno sto se dogadja
                $order->setOrderState($this->getOrderStateById(CrmConstants::ORDER_STATE_COMPLETED));
            } else {
                $order->setOrderState($this->getOrderStateById(CrmConstants::ORDER_STATE_COMPLETED));
            }
        }

        $this->entityManager->saveEntity($order);
        $this->entityManager->refreshEntity($order);

        return $order;
    }

    /**
     * @param OrderEntity $order
     * @param string $contentTemplate
     * @return bool|mixed|string|null
     */
    public function generateOrderFile(OrderEntity $order, $contentTemplate = "order_html")
    {
        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->container->get("attribute_context");
        }
        if (empty($this->templateManager)) {
            $this->templateManager = $this->container->get("app_template_manager");
        }

        /** @var SStoreEntity $store */
        $store = $order->getStore();
        if (empty($store)) {
            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get('route_manager');
            }
            $store = $this->routeManager->getStoreById($_ENV["DEFAULT_STORE_ID"]);
        }

        $data = array(
            "order" => $order,
            "current_language" => $store->getCoreLanguage()->getCode(),
            "orderWebsiteId" => $store->getWebsiteId()
        );

        $header = $this->twig->render(
            $this->templateManager->getTemplatePathByBundle("PDF:memo_header.html.twig", $store->getWebsiteId(), $store->getWebsiteId()), array("data" => $data));
        $footer = $this->twig->render(
            $this->templateManager->getTemplatePathByBundle("PDF:memo_footer.html.twig", $store->getWebsiteId(), $store->getWebsiteId()), array("data" => $data));
        $body = $this->twig->render(
            $this->templateManager->getTemplatePathByBundle("PDF:" . $contentTemplate . ".html.twig", $store->getWebsiteId()), array("data" => $data));

        //todo remove translation

        $fileAttribute = $this->attributeContext->getOneBy(
            array("attributeCode" => "file", "backendTable" => "order_document_entity")
        );

        /** @var FileManager $fileManager */
        $fileManager = $this->container->get("file_manager");

        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }

        $orderName = $this->helperManager->nameToFilename($order->getName());

        $file = $fileManager->saveFileWithPDF(
            $orderName,
            true,
            $body,
            $header,
            $footer,
            "order_document",
            $fileAttribute,
            $order,
            true,
            "Portrait"
        );

        return $file;
    }

    /**
     * @param $entity
     */
    public function save($entity)
    {
        $this->entityManager->saveEntity($entity);
    }

    /**
     * @return array
     */
    public function getOrderStatuses()
    {
        $orderStateEntityType = $this->entityManager->getEntityTypeByCode("order_state");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($orderStateEntityType, $compositeFilters);
    }

    /**
     * @param $orderProductArray
     * @return bool
     */
    public function decreaseProductQty($orderProductArray)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        foreach ($orderProductArray as $orderProduct) {
            $q = "UPDATE product_entity SET qty = qty - " . floatval($orderProduct["qty"]) . " WHERE id = {$orderProduct["product_id"]};";
            $this->databaseContext->executeNonQuery($q);
        }

        return true;
    }

    /**
     * @param OrderEntity $order
     * @return bool
     */
    public function setOrderStateHistory(OrderEntity $order)
    {

        /** @var OrderStateHistoryEntity $orderStateHistory */
        $orderStateHistory = $this->getLastOrderStateHistory($order);

        $now = new \DateTime();

        if (empty($orderStateHistory)) {
            $this->addOrderStateHistory($order);
        } else {
            if ($order->getEntityStateId() == 2) {
                $orderStateHistory->setDateTo($now);
                $orderStateHistory->setSecondsDiff($now->getTimestamp() - $orderStateHistory->getDateFrom()->getTimestamp());
                $this->entityManager->saveEntityWithoutLog($orderStateHistory);
            } else {
                if ($orderStateHistory->getOrderStateId() != $order->getOrderState()->getId()) {
                    $orderStateHistory->setDateTo($now);
                    $orderStateHistory->setSecondsDiff($now->getTimestamp() - $orderStateHistory->getDateFrom()->getTimestamp());
                    $this->entityManager->saveEntityWithoutLog($orderStateHistory);

                    $this->addOrderStateHistory($order, $orderStateHistory);
                }
            }
        }

        return true;
    }

    /**
     * @param OrderEntity $order
     * @return null
     */
    public function getLastOrderStateHistory(OrderEntity $order)
    {

        $et = $this->entityManager->getEntityTypeByCode("order_state_history");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("order", "eq", $order->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "desc"));

        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param OrderEntity $order
     * @param OrderStateEntity $orderState
     * @return null
     */
    public function getOrderStateHistoryByState(OrderEntity $order, OrderStateEntity $orderState)
    {

        $et = $this->entityManager->getEntityTypeByCode("order_state_history");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("order", "eq", $order->getId()));
        $compositeFilter->addFilter(new SearchFilter("orderState", "eq", $orderState->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "desc"));

        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param OrderEntity $order
     * @param null $previousOrderStateHistory
     * @return null
     */
    public function addOrderStateHistory(OrderEntity $order, $previousOrderStateHistory = null)
    {

        /** @var OrderStateHistoryEntity $orderStateHistory */
        $orderStateHistory = $this->entityManager->getNewEntityByAttributSetName("order_state_history");

        $orderStateHistory->setDateFrom(new \DateTime());
        $orderStateHistory->setOrder($order);
        $orderStateHistory->setOrderState($order->getOrderState());
        $orderStateHistory->setPreviousOrderStateHistory($previousOrderStateHistory);

        return $this->entityManager->saveEntity($orderStateHistory);
    }
}
