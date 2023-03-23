<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Managers\OrderReturnManager;
use ScommerceBusinessBundle\Entity\OrderReturnEntity;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OrderReturnListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var OrderReturnManager $orderReturnManager */
    protected $orderReturnManager;

    protected $container;
    protected $logger;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityCreatedEvent $event
     */
    public function onOrderReturnCreated(EntityCreatedEvent $event)
    {

        /** @var OrderReturnEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "order_return") {

            if(empty($this->orderReturnManager)){
                $this->orderReturnManager = $this->container->get("order_return_manager");
            }

            /**
             * Set order state history
             */
            $this->orderReturnManager->setOrderReturnStateHistory($entity);
        }
    }

    /**
     * @param EntityUpdatedEvent $event
     */
    public function onOrderReturnUpdated(EntityUpdatedEvent $event)
    {

        /** @var OrderReturnEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "order_return") {

            if(empty($this->orderReturnManager)){
                $this->orderReturnManager = $this->container->get("order_return_manager");
            }

            /**
             * Set order state history
             */
            $this->orderReturnManager->setOrderReturnStateHistory($entity);
        }
    }
}
