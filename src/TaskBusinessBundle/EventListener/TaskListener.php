<?php

namespace TaskBusinessBundle\EventListener;

use AppBundle\Entity\Attribute;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\NoteEntity;
use AppBundle\Entity\RepeatEventInfo;
use AppBundle\Entity\SearchFilter;
use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\RepeatEventManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use TaskBusinessBundle\Constants\TaskConstants;
use TaskBusinessBundle\Entity\ActivityEntity;
use TaskBusinessBundle\Entity\TaskEntity;
use TaskBusinessBundle\Managers\ActivityManager;
use TaskBusinessBundle\Managers\TaskManager;

class TaskListener implements ContainerAwareInterface
{
    protected $container;
    /** @var RepeatEventManager $repeatEventManager */
    protected $repeatEventManager;
    /** @var TaskManager $taskManager */
    protected $taskManager;
    /** @var ActivityManager $activityManager */
    protected $activityManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function onTaskPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var TaskEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "task") {
            /** @var TaskEntity $entity */
            $entity = $event->getEntity();

            $entity->setIsCompleted(0);
            if (EntityHelper::checkIfMethodExists($entity, "getProgress")) {
                if (empty($entity->getProgress())) {
                    $entity->setProgress(0);
                }
            }

            if(!empty($entity->getStatus()) && $entity->getStatus()->getId() == TaskConstants::TASK_STATUS_COMPLETED){
                $entity->setIsCompleted(1);
            }

            /** @var HelperManager $helperManager */
            $helperManager = $this->container->get("helper_manager");
            $coreUser = $helperManager->getCurrentCoreUser();

            if (!empty($coreUser)) {
                $entity->setOwner($coreUser);
            }
        }

    }

    public function onTaskCreated(EntityCreatedEvent $event)
    {
        /** @var TaskEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "task") {
            $this->generateRepeatEvents($entity);

            if (empty($this->taskManager)) {
                $this->taskManager = $this->container->get("task_manager");
            }

            $onlyNotification = false;
            /*if($entity->getPriorityId() == TaskConstants::TASK_PRIORITY_HIGH){
                $onlyNotification = false;
            }*/

            $users = $this->taskManager->getUsersToNotify($entity);
            if (!empty($users)) {
                /** @var CoreUserEntity $user */
                foreach ($users as $user) {
                    if ($user->getId() == $entity->getOwnerId()) {
                        continue;
                    }
                    $this->taskManager->generateNotificationForTask($entity, $user, "task_assigned_" . strtolower($entity->getPriority()->getName()), false, $onlyNotification);
                }
            }
        }
    }

    public function onTaskPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var TaskEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "task") {

            $postedValues = $event->getData();

            $isCompleted = $entity->getStatus()->getId() == TaskConstants::TASK_STATUS_COMPLETED ? true : false;
            $entity->setIsCompleted($isCompleted);

            if (isset($postedValues["assigned_to_id"]) && $postedValues["assigned_to_id"] != $entity->getAssignedToId()) {
                $_POST["assigned_to_id_changed"] = true;
            }
            if (isset($postedValues["notifiers"]) && !empty($postedValues["notifiers"])) {
                $_POST["notifiers_id_added"] = $postedValues["notifiers"];
                if (EntityHelper::isCountable($entity->getNotifiers()) && count($entity->getNotifiers())) {
                    foreach ($entity->getNotifiers() as $notifier) {
                        if (($key = array_search($notifier->getId(), $_POST["notifiers_id_added"])) !== false) {
                            unset($_POST["notifiers_id_added"][$key]);
                        }
                    }
                }
            }

            /**
             * Automaticly stop activities
             */
            if ($isCompleted) {
                if (empty($this->activityManager)) {
                    $this->activityManager = $this->container->get("activity_manager");
                }

                $compositeFilter = new CompositeFilter();
                $compositeFilter->setConnector("and");
                $compositeFilter->addFilter(new SearchFilter("isCompleted", "eq", 0));
                $compositeFilter->addFilter(new SearchFilter("task", "eq", $entity->getId()));

                $activites = $this->activityManager->getFilteredActivities($compositeFilter);

                if (EntityHelper::isCountable($activites)) {
                    /** @var ActivityEntity $activity */
                    foreach ($activites as $activity) {
                        $this->activityManager->stopActivity($activity);
                    }
                }
            }
        }
    }

    /**
     * @param EntityCreatedEvent $event
     * @return bool
     */
    public function onTaskNoteCreated(EntityCreatedEvent $event)
    {
        /** @var NoteEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "note" && $entity->getRelatedEntityType() == "task") {
            if (empty($this->taskManager)) {
                $this->taskManager = $this->container->get("task_manager");
            }

            /** @var TaskEntity $task */
            $task = $this->taskManager->getTaskById($entity->getRelatedEntityId());

            $users = $this->taskManager->getUsersToNotify($task);
            if (!empty($users)) {
                /** @var CoreUserEntity $user */
                foreach ($users as $user) {
                    $this->taskManager->generateNotificationForTask($task, $user, "new_task_note_" . strtolower($task->getPriority()->getName()), false, false);
                }
            }
        }

        return true;
    }

    public function onTaskUpdated(EntityUpdatedEvent $event)
    {
        /** @var TaskEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "task") {

            $previousValues = $event->getPreviousValuesArray();
            if (empty($this->taskManager)) {
                $this->taskManager = $this->container->get("task_manager");
            }

            $onlyNotification = false;
            /*if($entity->getPriorityId() == TaskConstants::TASK_PRIORITY_HIGH){
                $onlyNotification = false;
            }*/

            if (isset($previousValues["is_completed"]) && $previousValues["is_completed"] != $entity->getIsCompleted() && $entity->getIsCompleted()) {
                $users = $this->taskManager->getUsersToNotify($entity);
                if (!empty($users)) {
                    /** @var CoreUserEntity $user */
                    foreach ($users as $user) {
                        $this->taskManager->generateNotificationForTask($entity, $user, "task_completed_" . strtolower($entity->getPriority()->getName()), false, $onlyNotification);
                    }
                }
            } /**
             * Assignee changed
             */
            elseif (isset($_POST["assigned_to_id_changed"]) && $_POST["assigned_to_id_changed"]) {
                $users = $this->taskManager->getUsersToNotify($entity);
                if (!empty($users)) {
                    /** @var CoreUserEntity $user */
                    foreach ($users as $user) {
                        $this->taskManager->generateNotificationForTask($entity, $user, "task_assigned_" . strtolower($entity->getPriority()->getName()), false, $onlyNotification);
                    }
                }
            } elseif (isset($_POST["task_description_changed"]) && $_POST["task_description_changed"]) {
                $users = $this->taskManager->getUsersToNotify($entity);
                if (!empty($users)) {
                    /** @var CoreUserEntity $user */
                    foreach ($users as $user) {
                        $this->taskManager->generateNotificationForTask($entity, $user, "new_task_note_" . strtolower($entity->getPriority()->getName()), false, $onlyNotification);
                    }
                }
            } elseif (isset($_POST["notifiers_id_added"]) && !empty($_POST["notifiers_id_added"])) {

                if (empty($this->helperManager)) {
                    $this->helperManager = $this->container->get("helper_manager");
                }

                foreach ($_POST["notifiers_id_added"] as $userId) {
                    $this->taskManager->generateNotificationForTask($entity, $this->helperManager->getCoreUserById($userId), "task_assigned_" . strtolower($entity->getPriority()->getName()), false, $onlyNotification);
                }
            }
        }
    }

    /**
     * @param TaskEntity $entity
     */
    public function generateRepeatEvents(TaskEntity $entity)
    {
        if ($entity->getRepeatEvent() != null) {

            $entity->setRepeatSequence($entity->getId());
            $repeat_info = new RepeatEventInfo();

            $repeat_info->setFromJson($entity->getRepeatEvent());
            $fromAttribute = null;

            /** @var Attribute $attribute */
            foreach ($entity->getAttributes() as $attribute) {
                if ($attribute->getAttributeCode() == "due_date") {
                    $fromAttribute = $attribute;
                    break;
                }
            }

            if (empty($this->repeatEventManager)) {
                $this->repeatEventManager = $this->container->get("repeat_event_manager");
            }

            $this->repeatEventManager->generateRepeatingEvents($entity, $repeat_info, $fromAttribute);
        }
    }
}
