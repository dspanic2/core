<?php

namespace AppBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UserListener implements ContainerAwareInterface
{
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     */
    public function onUserPreCreated(EntityPreCreatedEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "core_user") {
            $entity->setFullName($entity->getFirstName()." ".$entity->getLastName());
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     */
    public function onUserPreUpdated(EntityPreUpdatedEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "core_user") {
            $entity->setFullName($entity->getFirstName()." ".$entity->getLastName());
        }
    }
}
