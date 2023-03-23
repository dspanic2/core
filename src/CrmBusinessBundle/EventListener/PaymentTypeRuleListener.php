<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use CrmBusinessBundle\Entity\PaymentTypeRuleEntity;
use CrmBusinessBundle\Managers\ProductAttributeFilterRulesManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PaymentTypeRuleListener implements ContainerAwareInterface
{
    protected $container;
    /** @var ProductAttributeFilterRulesManager $productAttributeFilterRulesManager */
    protected $productAttributeFilterRulesManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @throws \Exception
     */
    public function onPaymentTypeRulePreCreated(EntityPreCreatedEvent $event)
    {
        /** @var PaymentTypeRuleEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "payment_type_rule") {

            if(empty($this->productAttributeFilterRulesManager)){
                $this->productAttributeFilterRulesManager = $this->container->get("product_attribute_filter_rules_manager");
            }

            $entity = $this->productAttributeFilterRulesManager->validateRule($entity);
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onPaymentTypeRulePreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var PaymentTypeRuleEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "payment_type_rule") {

            if(empty($this->productAttributeFilterRulesManager)){
                $this->productAttributeFilterRulesManager = $this->container->get("product_attribute_filter_rules_manager");
            }

            $entity = $this->productAttributeFilterRulesManager->validateRule($entity);
        }
    }
}
