<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreDeletedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use CrmBusinessBundle\Entity\ProductLabelEntity;
use CrmBusinessBundle\Managers\ProductLabelRulesManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductLabelListener implements ContainerAwareInterface
{
    protected $container;
    /** @var ProductLabelRulesManager $productLabelRulesManager */
    protected $productLabelRulesManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @throws \Exception
     */
    public function onProductLabelPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var ProductLabelEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product_label") {

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

            if(empty($this->productLabelRulesManager)){
                $this->productLabelRulesManager = $this->container->get("product_label_rules_manager");
            }

            $entity = $this->productLabelRulesManager->validateRule($entity);

            $entity->setRecalculate(1);
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onProductLabelPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var ProductLabelEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product_label") {

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

            if(empty($this->productLabelRulesManager)){
                $this->productLabelRulesManager = $this->container->get("product_label_rules_manager");
            }

            $entity = $this->productLabelRulesManager->validateRule($entity);

            $entity->setRecalculate(1);
        }
    }

    /**
     * @param EntityPreDeletedEvent $event
     * @throws \Exception
     */
    public function onProductLabelPreDeleted(EntityPreDeletedEvent $event)
    {
        /** @var ProductLabelEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product_label") {

            $entity->setIsApplied(0);
            $entity->setRecalculate(1);
        }
    }
}
