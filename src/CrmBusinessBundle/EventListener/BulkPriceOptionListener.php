<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use CrmBusinessBundle\Entity\BulkPriceOptionEntity;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BulkPriceOptionListener implements ContainerAwareInterface
{
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @throws \Exception
     */
    public function onBulkPriceOptionPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var BulkPriceOptionEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "bulk_price_option") {

            if($entity->getDiscountPercentage() < 0 OR $entity->getDiscountPercentageBase() > 100){
                $entity->setDiscountPercentage(0);
            }

            if($entity->getDiscountPercentageBase() < 0 OR $entity->getDiscountPercentageBase() > 100){
                $entity->setDiscountPercentageBase(0);
            }

            if((empty($entity->getDiscountPercentageBase()) && empty($entity->getDiscountPercentage())) || $entity->getMinQty() < 2){
                $entity->setEntityStateId(2);
            }
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onBulkPriceOptionPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var BulkPriceOptionEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "bulk_price_option") {

            if($entity->getDiscountPercentage() < 0 OR $entity->getDiscountPercentageBase() > 100){
                $entity->setDiscountPercentage(0);
            }

            if($entity->getDiscountPercentageBase() < 0 OR $entity->getDiscountPercentageBase() > 100){
                $entity->setDiscountPercentageBase(0);
            }

            if((empty($entity->getDiscountPercentageBase()) && empty($entity->getDiscountPercentage())) || $entity->getMinQty() < 2){
                $entity->setEntityStateId(2);
            }
        }
    }
}
