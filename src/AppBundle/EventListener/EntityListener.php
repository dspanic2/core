<?php

namespace AppBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\SyncManager;
use Doctrine\Common\Persistence\Event\LoadClassMetadataEventArgs;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityListener implements ContainerAwareInterface
{
    protected $container;
    /** @var EntityManager $entityManager */
    protected $entityManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param EntityCreatedEvent $event
     * @return EntityCreatedEvent
     */
    public function onEntityCreated(EntityCreatedEvent $event)
    {
        $entity = $event->getEntity();

        if (EntityHelper::checkIfPropertyExists($entity, "uid") && EntityHelper::checkIfPropertyExists($entity, "isCustom")) {

            if(empty($this->entityManager)){
                $this->entityManager = $this->container->get("entity_manager");
            }

            $entity->setUid(md5($entity->getId()));
            $this->entityManager->saveEntityWithoutLog($entity);
        }

        return true;
    }
}
