<?php

namespace AppBundle\EventListener;

use AppBundle\Context\EntityLogContext;
use AppBundle\Entity\EntityLog;
use AppBundle\Entity\UserEntity;
use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityLogListener implements ContainerAwareInterface
{
    /** @var UserEntity $user */
    protected $user;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var EntityLogContext $eventLogContext */
    protected $eventLogContext;
    /** @var HelperManager $helperManager */
    protected $helperManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityCreatedEvent $event
     * @throws \Exception
     */
    public function onEntityCreated(EntityCreatedEvent $event)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        if (empty($this->eventLogContext)) {
            $this->eventLogContext = $this->container->get("entity_log_context");
        }

        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }
        $this->user = $this->helperManager->getCurrentUser();

        $entity = $event->getEntity();
        $aEntity = $this->entityManager->entityToArray($entity, false);

        $eventLog = new EntityLog();
        $eventLog->setEntityId($entity->getId());
        $eventLog->setEntityTypeId($entity->getEntityType()->getId());
        $eventLog->setEntityTypeCode($entity->getEntityType()->getEntityTypeCode());
        $eventLog->setAttributeSetId($entity->getAttributeSet()->getId());
        $eventLog->setAttributeSetCode($entity->getAttributeSet()->getAttributeSetCode());
        $eventLog->setAction("created");
        $eventLog->setContent(json_encode($aEntity));
        if (is_object($this->user)) {
            $eventLog->setUsername($this->user->getUsername());
        } else {
            $eventLog->setUsername("anon.");
        }
        $eventLog->setEventTime(new \DateTime());

        $this->eventLogContext->save($eventLog);
    }

    /**
     * @param EntityUpdatedEvent $event
     * @throws \Exception
     */
    public function onEntityUpdated(EntityUpdatedEvent $event)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        if (empty($this->eventLogContext)) {
            $this->eventLogContext = $this->container->get("entity_log_context");
        }

        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }
        $this->user = $this->helperManager->getCurrentUser();

        $entity = $event->getEntity();
        $aEntity = $this->entityManager->entityToArray($entity, false);
        $previousValues = $event->getPreviousValuesArray();

        $eventLog = new EntityLog();
        $eventLog->setEntityId($entity->getId());
        $eventLog->setEntityTypeId($entity->getEntityType()->getId());
        $eventLog->setEntityTypeCode($entity->getEntityType()->getEntityTypeCode());
        $eventLog->setAttributeSetId($entity->getAttributeSet()->getId());
        $eventLog->setAttributeSetCode($entity->getAttributeSet()->getAttributeSetCode());
        $eventLog->setAction("updated");
        $eventLog->setContent(json_encode($aEntity));
        $eventLog->setPreviousValues(json_encode($previousValues));
        if (is_object($this->user)) {
            $eventLog->setUsername($this->user->getUsername());
        } else {
            $eventLog->setUsername("anon.");
        }
        $eventLog->setEventTime(new \DateTime());

        $this->eventLogContext->save($eventLog);
    }

    /**
     * @param EntityDeletedEvent $event
     * @throws \Exception
     */
    public function onEntityDeleted(EntityDeletedEvent $event)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        if (empty($this->eventLogContext)) {
            $this->eventLogContext = $this->container->get("entity_log_context");
        }

        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }
        $this->user = $this->helperManager->getCurrentUser();

        $entity = $event->getEntity();
        $aEntity = $this->entityManager->entityToArray($entity);

        $eventLog = new EntityLog();
        $eventLog->setEntityId($entity->getId());
        $eventLog->setEntityTypeId($entity->getEntityType()->getId());
        $eventLog->setEntityTypeCode($entity->getEntityType()->getEntityTypeCode());
        $eventLog->setAttributeSetId($entity->getAttributeSet()->getId());
        $eventLog->setAttributeSetCode($entity->getAttributeSet()->getAttributeSetCode());
        $eventLog->setAction("deleted");
        $eventLog->setContent(json_encode($aEntity));
        if (is_object($this->user)) {
            $eventLog->setUsername($this->user->getUsername());
        } else {
            $eventLog->setUsername("anon.");
        }
        $eventLog->setEventTime(new \DateTime());

        $this->eventLogContext->save($eventLog);
    }
}
