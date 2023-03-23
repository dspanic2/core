<?php

namespace ProjectManagementBusinessBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Helpers\StringHelper;
use AppBundle\Interfaces\Entity\IFormEntityInterface;
use AppBundle\Managers\EntityManager;
use ProjectManagementBusinessBundle\Entity\ProjectEntity;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use TaskBusinessBundle\Entity\TaskEntity;

class ProjectTaskListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     */
    public function onTaskPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var TaskEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "task") {
            $this->setTaskPrefix($entity);
            /*if ($entity->getAttributeSet()->getAttributeSetCode() == "project_task") {
                $this->adjustProjectEnd($entity);
            }*/
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     */
    public function onTaskPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var TaskEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getAttributeSet()->getAttributeSetCode() == "project_task") {
            //$this->adjustProjectEnd($entity);
        }
    }

    /**
     * @param EntityUpdatedEvent $event
     * @return TaskEntity
     */
    public function onTaskUpdated(EntityUpdatedEvent $event)
    {
        /** @var TaskEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getAttributeSet()->getAttributeSetCode() == "project_task") {
            $this->setProjectTaskCount($entity);
        }
    }

    /**
     * @param EntityCreatedEvent $event
     * @return TaskEntity
     */
    public function onTaskCreated(EntityCreatedEvent $event)
    {
        /** @var TaskEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getAttributeSet()->getAttributeSetCode() == "project_task") {
            $this->setProjectTaskCount($entity);
        }
    }

    /**
     * @param IFormEntityInterface $entity
     */
    public function setTaskPrefix(IFormEntityInterface $entity)
    {
        if ($entity->getProject() != null) {
            if ($entity->getProject()->getTaskPrefix() != "") {
                $entity->setSubject(StringHelper::format("{0}-{1} {2}", $entity->getProject()->getTaskPrefix(), count($entity->getProject()->getProjectTasks()), $entity->getSubject()));
            }
        }
    }

    /**
     * @param TaskEntity $entity
     */
    public function setProjectTaskCount(TaskEntity $entity)
    {
        /** @var ProjectEntity $project */
        $project = $entity->getProject();
        if (!empty($project)) {

            $num_of_tasks = 0;
            $num_of_completed_tasks = 0;

            /** @var TaskEntity $projectTask */
            foreach ($project->getProjectTasks() as $projectTask) {

                if ($projectTask->getEntityStateId() == 1) {
                    $num_of_tasks++;
                } else {
                    continue;
                }

                if ($projectTask->getIsCompleted() == 1) {
                    $num_of_completed_tasks++;
                }
            }

            $completion = $num_of_completed_tasks / $num_of_tasks * 100;
            $project->setNumberOfTasks($num_of_tasks);
            $project->setCompletion($completion);

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->saveEntityWithoutLog($project);
        }
    }
}