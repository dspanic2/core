<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\ProductConfigurationProductLinkEntity;
use CrmBusinessBundle\Managers\ProductManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductConfigurationProductLinkListener implements ContainerAwareInterface
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
     * @param EntityDeletedEvent $event
     */
    public function onProductConfigurationProductLinkDeleted(EntityDeletedEvent $event)
    {
        /** @var ProductConfigurationProductLinkEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product_configuration_product_link") {
            if(empty($this->productManager)){
                $this->productManager = $this->container->get("product_manager");
            }

            $this->productManager->deleteProductConfigurationProductLink($entity);
        }
    }
}
