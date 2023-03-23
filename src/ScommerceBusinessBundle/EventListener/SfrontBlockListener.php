<?php

namespace ScommerceBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\Managers\ProductAttributeFilterRulesManager;
use ScommerceBusinessBundle\Entity\SFrontBlockEntity;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SfrontBlockListener implements ContainerAwareInterface
{
    protected $container;
    /** @var ProductAttributeFilterRulesManager */
    protected $productAttributeFilterRulesManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @return void
     */
    public function onSfrontBlockPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var SFrontBlockEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_front_block") {
            if (in_array($entity->getType(), array("html", "banner_medium", "banner_large"))) {
                $entity->setEnableEdit(1);
            }

            if(!empty($entity->getRules())){

                if(empty($this->productAttributeFilterRulesManager)){
                    $this->productAttributeFilterRulesManager = $this->container->get("product_attribute_filter_rules_manager");
                }

                $entity = $this->productAttributeFilterRulesManager->validateRule($entity);
            }

            if ($this->container->has($entity->getType() . "_front_block")) {
                $blockService = $this->container->get($entity->getType() . "_front_block");
                if (EntityHelper::checkIfMethodExists($blockService, "getPageBuilderValidation")) {
                    $entity = $blockService->getPageBuilderValidation($entity);
                    return $entity;
                }
            }
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @return void
     */
    public function onSfrontBlockPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var SFrontBlockEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_front_block") {

            if(!empty($entity->getRules())){

                if(empty($this->productAttributeFilterRulesManager)){
                    $this->productAttributeFilterRulesManager = $this->container->get("product_attribute_filter_rules_manager");
                }

                $entity = $this->productAttributeFilterRulesManager->validateRule($entity);
            }
        }
    }
}
