<?php

namespace ScommerceBusinessBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use ScommerceBusinessBundle\Entity\BlockedIpsEntity;
use ScommerceBusinessBundle\Managers\ScommerceHelperManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BlockedIpsListener implements ContainerAwareInterface
{
    /** @var ScommerceHelperManager $sCommerceHelperManager */
    protected $sCommerceHelperManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityCreatedEvent $event
     */
    public function onBlockedIpCreated(EntityCreatedEvent $event)
    {
        /** @var BlockedIpsEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "blocked_ips") {
            if (empty($this->sCommerceHelperManager)) {
                $this->sCommerceHelperManager = $this->container->get("scommerce_helper_manager");
            }

            $this->sCommerceHelperManager->reloadBlockedIpsCache();
        }
    }

    /**
     * @param EntityDeletedEvent $event
     */
    public function onBlockedIpDeleted(EntityDeletedEvent $event)
    {
        /** @var BlockedIpsEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "blocked_ips") {
            if (empty($this->sCommerceHelperManager)) {
                $this->sCommerceHelperManager = $this->container->get("scommerce_helper_manager");
            }

            $this->sCommerceHelperManager->reloadBlockedIpsCache();
        }
    }
}
