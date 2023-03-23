<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Entity\EntityValidation;
use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\CalculationProviders\DefaultCalculationProvider;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderItemEntity;
use CrmBusinessBundle\Managers\OrderManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OrderItemListener implements ContainerAwareInterface
{
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var DefaultCalculationProvider $calculationProvider */
    protected $calculationProvider;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @return EntityPreCreatedEvent
     * @throws \Exception
     */
    public function onOrderItemPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var OrderItemEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "order_item") {

            if ($entity->getOrder() != null)
                if ($entity->getOrder()->getLocked() != null) {
                    $entityValidation = new EntityValidation();
                    $entityValidation->setTitle("Error");
                    $entityValidation->setMessage("Order is locked for editing");
                    $entity->addEntityValidation($entityValidation);

                    return $event;
                }

            if (empty($entity->getTaxType())) {
                $entity->setTaxType($entity->getProduct()->getTaxType());
            }

            if (empty($entity->getName())) {
                $entity->setName($entity->getProduct()->getName());
            }

            if (empty($entity->getCode())) {
                $entity->setCode($entity->getProduct()->getCode());
            }
        }

        return $event;
    }

    /**
     * @param EntityCreatedEvent $event
     * @throws \Exception
     */
    public function onOrderItemCreated(EntityCreatedEvent $event)
    {
        /** @var OrderItemEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "order_item") {

            if ($entity->getOrder() != null) {
                $currencyRate = $entity->getOrder()->getCurrencyRate();

                if(empty($this->calculationProvider)){
                    $this->calculationProvider = $this->container->get($_ENV["CALCULATION_PROVIDER"]);
                }

                $entity = $this->calculationProvider->calculatePriceOrderItem($entity, $currencyRate);

                if (empty($this->entityManager)) {
                    $this->entityManager = $this->container->get("entity_manager");
                }

                $this->entityManager->saveEntityWithoutLog($entity);

                $this->entityManager->refreshEntity($entity);

                $this->calculationProvider->recalculateOrderTotals($entity->getOrder());
            }
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onOrderItemPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var OrderItemEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "order_item") {
            if (empty($entity->getTaxType())) {
                $entity->setTaxType($entity->getProduct()->getTaxType());
            }

            if (empty($entity->getName())) {
                $entity->setName($entity->getProduct()->getName());
            }

            if (empty($entity->getCode())) {
                $entity->setCode($entity->getProduct()->getCode());
            }
        }
    }

    /**
     * @param EntityUpdatedEvent $event
     * @throws \Exception
     */
    public function onOrderItemUpdated(EntityUpdatedEvent $event)
    {
        /** @var OrderItemEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "order_item") {

            $currencyRate = $entity->getOrder()->getCurrencyRate();

            if(empty($this->calculationProvider)){
                $this->calculationProvider = $this->container->get($_ENV["CALCULATION_PROVIDER"]);
            }

            $entity = $this->calculationProvider->calculatePriceOrderItem($entity, $currencyRate);

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->saveEntityWithoutLog($entity);

            $this->entityManager->refreshEntity($entity);

            $this->calculationProvider->recalculateOrderTotals($entity->getOrder());
        }
    }

    /**
     * @param EntityDeletedEvent $event
     */
    public function onOrderItemDeleted(EntityDeletedEvent $event)
    {
        /** @var OrderItemEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "order_item") {
            if (empty($this->quoteManager)) {
                $this->orderManager = $this->container->get("order_manager");
            }

            /** @var OrderEntity $order */
            $order = $entity->getOrder();

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->deleteEntityFromDatabase($entity);

            if(empty($this->calculationProvider)){
                $this->calculationProvider = $this->container->get($_ENV["CALCULATION_PROVIDER"]);
            }

            $this->calculationProvider->recalculateOrderTotals($order);
        }
    }
}
