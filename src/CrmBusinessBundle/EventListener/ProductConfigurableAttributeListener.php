<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\ProductConfigurableAttributeEntity;
use CrmBusinessBundle\Managers\ProductManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductConfigurableAttributeListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var ProductManager */
    protected $productManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityCreatedEvent $event
     */
    public function onProductConfigurableAttributeCreated(EntityCreatedEvent $event)
    {
        /** @var ProductConfigurableAttributeEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product_configurable_attribute") {
            if(empty($this->productManager)){
                $this->productManager = $this->container->get("product_manager");
            }

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->refreshEntity($entity);

            $this->productManager->rebuildConfigurableProducts(Array($entity->getProductId()));

            return true;
        }
    }

    /**
     * @param EntityDeletedEvent $event
     */
    public function onProductConfigurableAttributeDeleted(EntityDeletedEvent $event)
    {
        /** @var ProductConfigurableAttributeEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product_configurable_attribute") {

            $parentProductId = $entity->getProductId();

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->deleteEntityFromDatabase($entity);

            if (empty($this->productManager)) {
                $this->productManager = $this->container->get("product_manager");
            }

            $this->productManager->rebuildConfigurableProducts(array($parentProductId));

            return true;
        }
    }

}
