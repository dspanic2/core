<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreDeletedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use CrmBusinessBundle\Entity\LoyaltyEarningsConfigurationEntity;
use CrmBusinessBundle\Entity\LoyaltyEarningsEntity;
use CrmBusinessBundle\Managers\LoyaltyManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoyaltyListener implements ContainerAwareInterface
{
    protected $container;
    /** @var LoyaltyManager $loyaltyManager */
    protected $loyaltyManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @throws \Exception
     */
    public function onLoyaltyEarningConfigurationPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var LoyaltyEarningsConfigurationEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "loyalty_earnings_configuration") {

            if(!$entity->getIsApplied() && $entity->getIsActive()){
                $entity->setIsApplied(1);
            }
            else{
                $entity->setIsApplied(0);
            }

            $entity->setRecalculate(1);
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onLoyaltyEarningConfigurationPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var LoyaltyEarningsConfigurationEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "loyalty_earnings_configuration") {

            if(!$entity->getIsApplied() && $entity->getIsActive()){
                $entity->setIsApplied(1);
            }
            else{
                $entity->setIsApplied(0);
            }

            $entity->setRecalculate(1);
        }
    }

    /**
     * @param EntityPreDeletedEvent $event
     * @throws \Exception
     */
    public function onLoyaltyEarningConfigurationPreDeleted(EntityPreDeletedEvent $event)
    {
        /** @var LoyaltyEarningsConfigurationEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "loyalty_earnings_configuration") {

            $entity->setIsApplied(0);
            $entity->setRecalculate(1);
        }
    }

    /**
     * @param EntityCreatedEvent $event
     * @throws \Exception
     */
    public function onLoyaltyEarningsCreated(EntityCreatedEvent $event)
    {
        /** @var LoyaltyEarningsEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "loyalty_earnings") {

            if(empty($this->crmProcessManager)){
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $this->crmProcessManager->calculateLoyaltyPointsOnCard($entity->getLoyaltyCard());
        }
    }
}
