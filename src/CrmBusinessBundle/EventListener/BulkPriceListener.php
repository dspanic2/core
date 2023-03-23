<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreDeletedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use CrmBusinessBundle\Entity\BulkPriceEntity;
use CrmBusinessBundle\Managers\BulkPriceManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BulkPriceListener implements ContainerAwareInterface
{
    protected $container;
    /** @var BulkPriceManager $bulkPriceManager */
    protected $bulkPriceManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @throws \Exception
     */
    public function onBulkPricePreCreated(EntityPreCreatedEvent $event)
    {
        /** @var BulkPriceEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "bulk_price") {

            $bulkPriceOptions = $entity->getBulkPriceOptions();
            if(empty($bulkPriceOptions)){
                $entity->setIsActive(0);
            }

            if($entity->getIsActive()){
                $entity->setIsApplied(1);
            }
            else{
                $entity->setIsApplied(0);
            }

            if(empty($this->bulkPriceManager)){
                $this->bulkPriceManager = $this->container->get("bulk_price_manager");
            }

            $entity = $this->bulkPriceManager->validateRule($entity);

            $entity->setRecalculate(1);
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onBulkPricePreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var BulkPriceEntity $entity */
        $entity = $event->getEntity();


        if ($entity->getEntityType()->getEntityTypeCode() == "bulk_price") {

            $bulkPriceOptions = $entity->getBulkPriceOptions();
            if(empty($bulkPriceOptions)){
                $entity->setIsActive(0);
            }

            if($entity->getIsActive()){
                $entity->setIsApplied(1);
            }
            else{
                $entity->setIsApplied(0);
            }

            if(empty($this->bulkPriceManager)){
                $this->bulkPriceManager = $this->container->get("bulk_price_manager");
            }

            $entity = $this->bulkPriceManager->validateRule($entity);

            $entity->setRecalculate(1);
        }
    }

    /**
     * @param EntityPreDeletedEvent $event
     * @throws \Exception
     */
    public function onBulkPricePreDeleted(EntityPreDeletedEvent $event)
    {
        /** @var BulkPriceEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "bulk_price") {

            $entity->setIsApplied(0);
            $entity->setRecalculate(1);
        }
    }
}
