<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreDeletedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use CrmBusinessBundle\Entity\DiscountCatalogEntity;
use CrmBusinessBundle\Managers\DiscountRulesManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DiscountCatalogListener implements ContainerAwareInterface
{
    protected $container;
    /** @var DiscountRulesManager $discountRulesManager */
    protected $discountRulesManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @throws \Exception
     */
    public function onDiscountCatalogPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var DiscountCatalogEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "discount_catalog") {

            if(!empty($entity->getDateFrom()) && !empty($entity->getDateTo())){

                $now = new \DateTime();

                if($now >= $entity->getDateFrom() && $now <= $entity->getDateTo() && !$entity->getIsApplied() && $entity->getIsActive()){
                    $entity->setIsApplied(1);
                }
                elseif($now < $entity->getDateFrom() || $now > $entity->getDateTo() || !$entity->getIsActive()){
                    $entity->setIsApplied(0);
                }
            }
            else{
                $entity->setIsApplied(0);
            }

            if(empty($this->discountRulesManager)){
                $this->discountRulesManager = $this->container->get("discount_rules_manager");
            }

            $entity = $this->discountRulesManager->validateRule($entity);

            $entity->setRecalculate(1);
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onDiscountCatalogPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var DiscountCatalogEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "discount_catalog") {

            if(!empty($entity->getDateFrom()) && !empty($entity->getDateTo())){

                $now = new \DateTime();

                if($now >= $entity->getDateFrom() && $now <= $entity->getDateTo() && !$entity->getIsApplied() && $entity->getIsActive()){
                    $entity->setIsApplied(1);
                }
                elseif($now < $entity->getDateFrom() || $now > $entity->getDateTo() || !$entity->getIsActive()){
                    $entity->setIsApplied(0);
                }
            }
            else{
                $entity->setIsApplied(0);
            }

            if(empty($this->discountRulesManager)){
                $this->discountRulesManager = $this->container->get("discount_rules_manager");
            }

            $entity = $this->discountRulesManager->validateRule($entity);

            $entity->setRecalculate(1);
        }
    }

    /**
     * @param EntityPreDeletedEvent $event
     * @throws \Exception
     */
    public function onDiscountCatalogPreDeleted(EntityPreDeletedEvent $event)
    {
        /** @var DiscountCatalogEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "discount_catalog") {

            $entity->setIsApplied(0);
            $entity->setRecalculate(1);
        }
    }
}
