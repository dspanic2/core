<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\ProductPriceHistoryEntity;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductPriceHistoryListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityDeletedEvent $event
     */
    public function onProductPriceHistoryDeleted(EntityDeletedEvent $event)
    {
        /** @var ProductPriceHistoryEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product_price_history") {
            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->deleteEntityFromDatabase($entity);
        }
    }
}
