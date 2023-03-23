<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreDeletedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use CrmBusinessBundle\Entity\MarginRuleEntity;
use CrmBusinessBundle\Managers\MarginRulesManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MarginRuleListener implements ContainerAwareInterface
{
    protected $container;
    /** @var MarginRulesManager $marginRulesManager */
    protected $marginRulesManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @throws \Exception
     */
    public function onMarginRulePreCreated(EntityPreCreatedEvent $event)
    {
        /** @var MarginRuleEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "margin_rule") {

            if (!$entity->getIsActive()) {
                $entity->setIsApplied(0);
            } else {
                $entity->setIsApplied(1);
            }

            if(empty($this->marginRulesManager)){
                $this->marginRulesManager = $this->container->get("margin_rules_manager");
            }

            $entity = $this->marginRulesManager->validateRule($entity);

            $entity->setRecalculate(1);
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onMarginRulePreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var MarginRuleEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "margin_rule") {

            if (!$entity->getIsActive()) {
                $entity->setIsApplied(0);
            } else {
                $entity->setIsApplied(1);
            }

            if(empty($this->marginRulesManager)){
                $this->marginRulesManager = $this->container->get("margin_rules_manager");
            }

            $entity = $this->marginRulesManager->validateRule($entity);

            $entity->setRecalculate(1);
        }
    }

    /**
     * @param EntityPreDeletedEvent $event
     * @throws \Exception
     */
    public function onMarginRulePreDeleted(EntityPreDeletedEvent $event)
    {
        /** @var MarginRuleEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "margin_rule") {

            $entity->setIsApplied(0);
            $entity->setRecalculate(1);
        }
    }
}
