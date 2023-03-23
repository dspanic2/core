<?php

namespace AppBundle\EventListener;

use AppBundle\Interfaces\Entity\ITrackChanges;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\ORM\Event;

class DoctrineListener implements ContainerAwareInterface
{
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function preUpdate(Event\PreUpdateEventArgs $eventArgs)
    {
        if ($eventArgs->getObject() instanceof ITrackChanges) {
            $eventArgs->getObject()->setChangeSet($eventArgs->getEntityChangeSet());
        }
    }
}
