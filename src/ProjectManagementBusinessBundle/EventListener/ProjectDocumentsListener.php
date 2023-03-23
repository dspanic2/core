<?php

namespace ProjectManagementBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use ProjectManagementBusinessBundle\Entity\ProjectDocumentsEntity;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProjectDocumentsListener implements ContainerAwareInterface
{
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     */
    public function onDocumentPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var ProjectDocumentsEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "project_documents") {
            if ($entity->getProjectTask() != null && $entity->getProject() == null) {
                $entity->setProject($entity->getProjectTask()->getProject());
            }
        }
    }
}