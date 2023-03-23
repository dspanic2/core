<?php

namespace ScommerceBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use CrmBusinessBundle\Managers\ProductAttributeFilterRulesManager;
use PevexBusinessBundle\Entity\ProductDeliveryMessageEntity;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductDescriptionRuleListener implements ContainerAwareInterface
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
    public function onProductDescriptionRulePreCreated(EntityPreCreatedEvent $event)
    {
        /** @var ProductDeliveryMessageEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product_description_rule") {

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
    public function onProductDescriptionRulePreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var ProductDeliveryMessageEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product_description_rule") {

            if(empty($this->productAttributeFilterRulesManager)){
                $this->productAttributeFilterRulesManager = $this->container->get("product_attribute_filter_rules_manager");
            }

            $entity = $this->productAttributeFilterRulesManager->validateRule($entity);
        }
    }
}
