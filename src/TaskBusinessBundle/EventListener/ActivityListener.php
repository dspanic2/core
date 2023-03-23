<?php

namespace TaskBusinessBundle\EventListener;

use AppBundle\Entity\EntityValidation;
use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreDeletedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Managers\HelperManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use TaskBusinessBundle\Entity\ActivityEntity;
use TaskBusinessBundle\Managers\ActivityManager;

class ActivityListener implements ContainerAwareInterface
{
    /** @var ActivityManager $activityManager */
    protected $activityManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @return ActivityEntity
     */
    public function onActivityPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var ActivityEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "activity") {

            if (empty($entity->getUser())) {
                if (empty($this->helperManager)) {
                    $this->helperManager = $this->container->get("helper_manager");
                }
                $entity->setUser($this->helperManager->getCurrentCoreUser());
            }

            if (empty($this->activityManager)) {
                $this->activityManager = $this->container->get("activity_manager");
            }

            $entity = $this->activityManager->updateDuration($entity);
        }

        return $entity;
    }

    /**
     * @param EntityCreatedEvent $event
     * @return ActivityEntity
     */
    public function onActivityCreated(EntityCreatedEvent $event)
    {
        /** @var ActivityEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "activity") {

            if (empty($this->activityManager)) {
                $this->activityManager = $this->container->get("activity_manager");
            }

            if (empty($entity->getDateStart())) {
                $res = $this->activityManager->startActivity($entity);
                if ($res["error"] == true) {
                    $entityValidation = new EntityValidation();
                    $entityValidation->setTitle($res["title"]);
                    $entityValidation->setMessage($res["message"]);
                    $entity->addEntityValidation($entityValidation);
                    return $entity;
                }
            }
        }

        return $entity;
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @return ActivityEntity
     */
    public function onActivityPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var ActivityEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "activity") {

            if (empty($this->activityManager)) {
                $this->activityManager = $this->container->get("activity_manager");
            }

            $entity = $this->activityManager->updateDuration($entity);
            /**
             * Da li je nuzno prilikom updatea jednog acivitija gasiti druge
             */
            /*if (!$entity->getIsCompleted()) {
                $currentActivity = $this->activityManager->getCurrentActivity($entity->getId());
                if (!empty($currentActivity)) {
                    $res = $this->activityManager->stopActivity($currentActivity);
                    if ($res["error"] == true) {
                        $entityValidation = new EntityValidation();
                        $entityValidation->setTitle($res["title"]);
                        $entityValidation->setMessage($res["message"]);
                        $entity->addEntityValidation($entityValidation);
                        return $entity;
                    }
                }
            }*/
        }

        return $entity;
    }

    /**
     * @param EntityPreDeletedEvent $event
     * @return ActivityEntity
     */
    public function onActivityPreDeleted(EntityPreDeletedEvent $event)
    {
        /** @var ActivityEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "activity") {

            if (empty($this->activityManager)) {
                $this->activityManager = $this->container->get("activity_manager");
            }
            if (empty($this->helperManager)) {
                $this->helperManager = $this->container->get("helper_manager");
            }

            if (empty($entity->getDateEnd()) || !$entity->getIsCompleted()) {
                $res = $this->activityManager->stopActivity($entity);
                if ($res["error"] == true) {
                    $entityValidation = new EntityValidation();
                    $entityValidation->setTitle($res["title"]);
                    $entityValidation->setMessage($res["message"]);
                    $entity->addEntityValidation($entityValidation);
                    return $entity;
                }
            }
        }

        return $entity;
    }
}
