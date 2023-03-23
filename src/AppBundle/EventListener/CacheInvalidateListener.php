<?php

namespace AppBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Managers\CacheManager;
use Doctrine\Common\Persistence\Event\LoadClassMetadataEventArgs;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CacheInvalidateListener implements ContainerAwareInterface
{
    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param $entity
     * @return bool
     */
    private function isEntityInvalidateable($entity)
    {
        $cacheableEntites = $_ENV["CACHEABLE_ENTITIES"] ?? null;
        if (empty($cacheableEntites)) {
            return false;
        }
        $cacheableEntites = explode(",", $cacheableEntites);

        return in_array($entity->getEntityType()->getEntityTypeCode(), $cacheableEntites);
    }

    /**
     * @param EntityCreatedEvent $event
     * @return EntityCreatedEvent
     */
    public function onEntityCreated(EntityCreatedEvent $event)
    {
        $entity = $event->getEntity();

        $this->entityInvalidateCaches($entity);

        return $event;
    }

    /**
     * @param EntityUpdatedEvent $event
     * @return EntityUpdatedEvent
     */
    public function onEntityUpdated(EntityUpdatedEvent $event)
    {
        $entity = $event->getEntity();

        $this->entityInvalidateCaches($entity);

        return $event;
    }

    /**
     * @param $entity
     */
    private function entityInvalidateCaches($entity)
    {
        if ($this->isEntityInvalidateable($entity)) {
            /** @var CacheManager $cacheManager */
            $cacheManager = $this->container->get("cache_manager");
            $cacheManager->invalidateCacheByTag($entity->getEntityType()->getEntityTypeCode());
            $cacheManager->invalidateCacheByTag($entity->getEntityType()->getEntityTypeCode() . "_" . $entity->getId());
        }
    }
}
