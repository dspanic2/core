<?php

namespace TaskBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\CoreContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use NotificationsAndAlertsBusinessBundle\Entity\NotificationTypeEntity;
use NotificationsAndAlertsBusinessBundle\Managers\NotificationManager;
use TaskBusinessBundle\Constants\TaskConstants;
use TaskBusinessBundle\Entity\TaskEntity;
use TaskBusinessBundle\Entity\TaskPriorityEntity;

class TaskManager extends AbstractBaseManager
{
    /** @var AttributeContext $attributeContext */
    protected $attributeContext;
    /** @var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var NotificationManager $notificationManager */
    protected $notificationManager;
    protected $notificationTypes;

    public function initialize()
    {
        parent::initialize();
        $this->attributeContext = $this->container->get("attribute_context");
        $this->attributeSetContext = $this->container->get("attribute_set_context");
        $this->entityManager = $this->container->get("entity_manager");
        $this->helperManager = $this->container->get("helper_manager");
    }

    /**
     * @param TaskEntity $task
     * @param $user
     * @param $notificationTypeCode
     * @param false $avoidCheckExists
     * @param false $onlyNotification
     * @return bool
     */
    public function generateNotificationForTask(TaskEntity $task, $user, $notificationTypeCode, $avoidCheckExists = false, $onlyNotification = false)
    {

        if (empty($this->notificationManager)) {
            $this->notificationManager = $this->container->get("notification_manager");
        }

        if (!isset($this->notificationTypes[$notificationTypeCode])) {
            /** @var NotificationTypeEntity $notificationType */
            $notificationType = $this->notificationManager->getNotificationTypeByCode($notificationTypeCode);
            if (empty($notificationType)) {
                return false;
            }

            $this->notificationTypes[$notificationTypeCode] = $notificationType;
        }

        $getter = EntityHelper::makeGetter("employee");

        if (EntityHelper::checkIfMethodExists($user, $getter)) {
            $employee = $user->$getter();

            if (!empty($employee)) {
                $getter = EntityHelper::makeGetter("force_email_notification");

                if (EntityHelper::checkIfMethodExists($employee, $getter)) {
                    if ($employee->$getter()) {
                        $onlyNotification = false;
                    }
                }
            }
        }

        $this->notificationManager->createNotification($task, $this->notificationTypes[$notificationTypeCode], $task->getSubject(), $user, $avoidCheckExists, $onlyNotification);

        return true;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function generateNotificationsForTasks()
    {
        $this->initialize();

        $taskEntityType = $this->entityManager->getEntityTypeByCode("task");

        $dateNow = new \DateTime();
        $dateNow->add(new \DateInterval('P20D'));
        $bottomLimit = new \DateTime();
        $bottomLimit->sub(new \DateInterval('P60D'));

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("dueDate", "le", $dateNow->format("Y-m-d")));
        $compositeFilter->addFilter(new SearchFilter("dueDate", "ge", $bottomLimit->format("Y-m-d")));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isCompleted", "eq", 0));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $tasks = $this->entityManager->getEntitiesByEntityTypeAndFilter($taskEntityType, $compositeFilters);

        if (empty($tasks)) {
            return false;
        }

        /** Do not delete this */
        $now = new \DateTime();

        /** @var TaskEntity $task */
        foreach ($tasks as $task) {

            $name = $task->getSubject();

            $onlyNotification = true;
            if ($task->getPriorityId() == TaskConstants::TASK_PRIORITY_HIGH) {
                $onlyNotification = false;
            }

            if (method_exists($task, "getNotifyDaysBefore")) {

                $taskDate = $task->getDueDate();
                if (intval($task->getNotifyDaysBefore()) > 0) {
                    $taskDate->sub(new \DateInterval('P' . $task->getNotifyDaysBefore() . 'D'));
                }

                if ($now >= $task->getDueDate()) {

                    $users = $this->getUsersToNotify($task);
                    if (!empty($users)) {
                        /** @var CoreUserEntity $user */
                        foreach ($users as $user) {
                            $this->generateNotificationForTask($task, $user, "task_overdue_" . strtolower($task->getPriority()->getName()), false, $onlyNotification);
                        }
                    }
                } elseif ($now >= $taskDate) {

                    $users = $this->getUsersToNotify($task);
                    if (!empty($users)) {
                        /** @var CoreUserEntity $user */
                        foreach ($users as $user) {
                            $this->generateNotificationForTask($task, $user, "task_reminder_" . strtolower($task->getPriority()->getName()), false, $onlyNotification);
                        }
                    }
                }
            } else {

                if ($now >= $task->getDueDate()) {
                    $users = $this->getUsersToNotify($task);
                    if (!empty($users)) {
                        /** @var CoreUserEntity $user */
                        foreach ($users as $user) {
                            $this->generateNotificationForTask($task, $user, "task_overdue_" . strtolower($task->getPriority()->getName()), false, $onlyNotification);
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param TaskEntity $task
     * @param bool $assignedTo
     * @param bool $notifiers
     * @param bool $owner
     * @return array
     */
    public function getUsersToNotify(TaskEntity $task, $assignedTo = true, $notifiers = true, $owner = true)
    {
        $ret = array();

        if ($assignedTo) {
            $ret[$task->getAssignedTo()->getId()] = $task->getAssignedTo();
        }

        if ($owner) {
            $ret[$task->getOwner()->getId()] = $task->getOwner();
        }

        if ($notifiers) {
            if (EntityHelper::isCountable($task->getNotifiers()) && count($task->getNotifiers())) {
                /** @var CoreUserEntity $notifier */
                foreach ($task->getNotifiers() as $notifier) {
                    $ret[$notifier->getId()] = $notifier;
                }
            }
        }

        if (!empty($ret)) {
            /**
             * @var  $key
             * @var CoreUserEntity $user
             */
            foreach ($ret as $key => $user) {
                if (in_array($user->getUsername(), array("partner", "system"))) {
                    unset($ret[$key]);
                }
            }
        }

        return $ret;
    }

    /**
     * @param $id
     * @return null
     */
    public function getTaskById($id)
    {
        $this->initialize();

        $et = $this->entityManager->getEntityTypeByCode("task");

        return $this->entityManager->getEntityByEntityTypeAndId($et, $id);
    }

    /**
     * @param $data
     * @param TaskEntity|null $task
     * @return TaskEntity
     */
    public function createUpdateTask($data, TaskEntity $task = null)
    {
        $this->initialize();

        if (empty($task)) {
            $task = $this->entityManager->getNewEntityByAttributSetName("task");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($task, $setter)) {
                $task->$setter($value);
            }
        }

        $this->entityManager->saveEntity($task, true);
        $this->entityManager->refreshEntity($task);

        return $task;
    }

    /**
     * @param $id
     * @return null
     */
    public function getTaskTypeById($id)
    {
        $et = $this->entityManager->getEntityTypeByCode("task_type");

        return $this->entityManager->getEntityByEntityTypeAndId($et, $id);
    }

    /**
     * @param $id
     * @return null
     */
    public function getTaskStatusById($id)
    {
        $et = $this->entityManager->getEntityTypeByCode("task_status");

        return $this->entityManager->getEntityByEntityTypeAndId($et, $id);
    }

    /**
     * @param $id
     * @return null
     */
    public function getTaskPriorityById($id)
    {
        $et = $this->entityManager->getEntityTypeByCode("task_priority");

        return $this->entityManager->getEntityByEntityTypeAndId($et, $id);
    }

    /**
     * @param $additionalCompositeFilter
     * @return mixed
     */
    public function getFilteredTasks($additionalCompositeFilter = null)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("task");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalCompositeFilter)) {
            $compositeFilters->addCompositeFilter($additionalCompositeFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("priority", "desc"));
        $sortFilters->addSortFilter(new SortFilter("modified", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @return mixed
     */
    public function getTaskPriorities()
    {
        $entityType = $this->entityManager->getEntityTypeByCode("task_priority");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("ord", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @return mixed
     */
    public function getTaskUsers()
    {
        $entityType = $this->entityManager->getEntityTypeByCode("core_user");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("enabled", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("expired", "eq", 0));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("username", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param CoreUserEntity|null $assignee
     * @return mixed
     */
    public function getKanbanTasks(CoreUserEntity $assignee = null)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("task");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isCompleted", "eq", 0));
        $compositeFilter->addFilter(new SearchFilter("project.showTaskOnKanban", "eq", 1));
        if(!empty($assignee)){
            $compositeFilter->addFilter(new SearchFilter("assignedTo.id", "eq", $assignee->getId()));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @return mixed
     */
    public function getKanbanColumnTasks($priorityId, $userId)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("task");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isCompleted", "eq", 0));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("modified", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param $taskId
     * @param $userId
     * @param $priorityId
     * @return TaskEntity
     */
    public function updateKanbanTaskItem($taskId, $userId, $priorityId)
    {
        /** @var TaskEntity $task */
        $task = $this->getTaskById($taskId);
        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCoreUserById($userId);
        /** @var TaskPriorityEntity $priority */
        $priority = $this->getTaskPriorityById($priorityId);

        $task->setAssignedTo($coreUser);
        $task->setPriority($priority);
        $this->entityManager->saveEntityWithoutLog($task);

        return $task;
    }
}