<?php

namespace TaskBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use TaskBusinessBundle\Constants\TaskConstants;
use TaskBusinessBundle\Entity\ActivityEntity;
use TaskBusinessBundle\Entity\TaskEntity;

class ActivityManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->getContainer()->get("entity_manager");
    }

    /**
     * @param $activityId
     * @return |null
     */
    public function getActivityById($activityId)
    {
        $etActivity = $this->entityManager->getEntityTypeByCode("activity");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("id", "eq", $activityId));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($etActivity, $compositeFilters);
    }

    /**
     * @param ActivityEntity $activity
     * @return ActivityEntity
     */
    public function cloneActivity(ActivityEntity $activity)
    {
        /** @var ActivityEntity $newActivity */
        $newActivity = $this->entityManager->getNewEntityByAttributSetName("activity");

        $newActivity->setName($activity->getName());
        $newActivity->setUser($activity->getUser());
        $newActivity->setTask($activity->getTask());
        $newActivity->setDescription($activity->getDescription());

        $this->entityManager->saveEntity($newActivity);
        $this->entityManager->refreshEntity($newActivity);

        return $newActivity;
    }

    /**
     * @param TaskEntity $task
     * @return ActivityEntity
     */
    public function createNewActivityFromTask(TaskEntity $task, $data = array())
    {
        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }

        /** @var ActivityEntity $newActivity */
        $newActivity = $this->entityManager->getNewEntityByAttributSetName("activity");

        $newActivity->setName($task->getSubject());
        $newActivity->setUser($this->helperManager->getCurrentCoreUser());
        $newActivity->setTask($task);
        //$newActivity->setDescription($task->getTaskDescription());
        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($newActivity, $setter)) {
                $newActivity->$setter($value);
            }
        }

        if (isset($data["save_without_log"]) && $data["save_without_log"]) {
            $this->entityManager->saveEntityWithoutLog($newActivity);
        } else {
            $this->entityManager->saveEntity($newActivity);
        }

        $this->entityManager->refreshEntity($newActivity);

        return $newActivity;
    }

    /**
     * @param ActivityEntity $activity
     * @return array
     */
    public function startActivity(ActivityEntity $activity)
    {
        if ($activity->getUser()->getId() != $this->user->getId()) {
            return array(
                "error" => true,
                "title" => $this->translator->trans("Error occurred"),
                "message" => $this->translator->trans("Cannot modify this activity")
            );
        }

        /** @var ActivityEntity $currentActivity */
        $currentActivity = $this->getCurrentActivity($activity->getId());
        while (!empty($currentActivity)) {
            $res = $this->stopActivity($currentActivity);
            if ($res["error"] == true) {
                return $res;
            }
            $currentActivity = $this->getCurrentActivity($activity->getId());
        }

        if ($activity->getIsCompleted()) {
            $activity = $this->cloneActivity($activity);
        }

        $timeNow = new \DateTime();

        $activity->setDateStart($timeNow);
        $activity->setDateEnd(null);
        $activity->setDuration(null);

        /** @var ActivityEntity $activity */
        $activity = $this->entityManager->saveEntity($activity);
        $this->entityManager->refreshEntity($activity);

        return array(
            "error" => false,
            "time_start" => $activity->getDateStart()->format(TaskConstants::ACTIVITY_JS_FORMAT),
            "time_end" => null
        );
    }

    /**
     * @param TaskEntity $task
     * @return array
     */
    public function startActivityForTask(TaskEntity $task)
    {
        $ret = array();
        $ret["error"] = true;

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->user;
        if (empty($coreUser)) {
            $ret["message"] = $this->translator->trans("Missing user");
            return $ret;
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("taskId", "eq", $task->getId()));
        $compositeFilter->addFilter(new SearchFilter("userId", "eq", $this->user->getId()));
        $compositeFilter->addFilter(new SearchFilter("isCompleted", "eq", 0));

        /** @var ActivityEntity $activity */
        $activity = null;
        $activities = $this->getFilteredActivities($compositeFilter);
        if (EntityHelper::isCountable($activities) && count($activities)) {
            if (!$activities[0]->getIsCompleted()) {
                $activity = $activities[0];
            }
        }

        if (empty($activity)) {
            $activity = $this->createNewActivityFromTask($task);
        }

        return $activity;
    }

    /**
     * @param TaskEntity $task
     * @return bool
     */
    public function stopActivitiesForTask(TaskEntity $task)
    {
        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->user;
        if (empty($coreUser)) {
            return false;
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("taskId", "eq", $task->getId()));
//        $compositeFilter->addFilter(new SearchFilter("userId", "eq", $this->user->getId()));
        $compositeFilter->addFilter(new SearchFilter("isCompleted", "eq", 0));

        $activities = $this->getFilteredActivities($compositeFilter);
        /** @var ActivityEntity $activity */
        foreach ($activities as $activity) {
            $this->stopActivity($activity);
        }

        return true;
    }

    /**
     * @param ActivityEntity $activity
     * @return array
     */
    public function stopActivity(ActivityEntity $activity)
    {
        /*if ($activity->getUser()->getId() != $this->user->getId()) {
            return array(
                "error" => true,
                "title" => $this->translator->trans("Error occurred"),
                "message" => $this->translator->trans("Cannot modify this activity")
            );
        }*/

        $timeNow = new \DateTime();

        $activity->setDateEnd($timeNow);

        /** @var ActivityEntity $activity */
        $activity = $this->entityManager->saveEntity($activity);
        $this->entityManager->refreshEntity($activity);

        $timeStart = null;
        if (!empty($activity->getDateStart())) {
            $timeStart = $activity->getDateStart()->format(TaskConstants::ACTIVITY_JS_FORMAT);
        }

        $timeEnd = null;
        if (!empty($activity->getDateEnd())) {
            $timeEnd = $activity->getDateEnd()->format(TaskConstants::ACTIVITY_JS_FORMAT);
        }

        return array(
            "error" => false,
            "time_start" => $timeStart,
            "time_end" => $timeEnd
        );
    }

    /**
     * @param ActivityEntity $activity
     * @return ActivityEntity
     */
    public function updateDuration(ActivityEntity $activity)
    {
        $duration = null;

        if (!empty($activity->getDateStart()) && !empty($activity->getDateEnd()) && $activity->getDateStart() <= $activity->getDateEnd()) {
            $duration = $activity->getDateEnd()->getTimestamp() - $activity->getDateStart()->getTimestamp();
        }

        if (empty($duration)) {
            $activity->setDateEnd(null);
            $activity->setIsCompleted(false);
        } else {
            if ($duration > 86399) {
                $duration = 86399;
                /** @var \DateTime $dateEnd */
                $dateEnd = $activity->getDateStart();
                $dateEnd->setTimestamp($dateEnd->getTimestamp() + $duration);
                $activity->setDateEnd($dateEnd);
            }
            $activity->setIsCompleted(true);
        }

        $activity->setDuration($duration);

        return $activity;
    }

    /**
     * @param null $notId
     * @return |null
     */
    public function getCurrentActivity($notId = null)
    {
        $etActivity = $this->entityManager->getEntityTypeByCode("activity");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isCompleted", "ne", 1));
        $compositeFilter->addFilter(new SearchFilter("userId", "eq", $this->user->getId()));
        if (!empty($notId)) {
            $compositeFilter->addFilter(new SearchFilter("id", "ne", $notId));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($etActivity, $compositeFilters);
    }

    /**
     * @param null $additionaFilter
     * @return mixed
     */
    public function getFilteredActivities($additionaFilter = null)
    {
        $etActivity = $this->entityManager->getEntityTypeByCode("activity");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionaFilter)) {
            $compositeFilters->addCompositeFilter($additionaFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($etActivity, $compositeFilters, $sortFilters);
    }

    /**
     * @param ActivityEntity $activity
     * @param int $durationInMin
     * @param string $direction
     * @return ActivityEntity
     */
    public function changeDuration(ActivityEntity $activity, $durationInMin = 15, $direction = "add")
    {

        $dateStart = $activity->getDateStart();
        if ($direction == "add") {
            $dateStart->add(new \DateInterval("P{$durationInMin}M"));
        } else {
            $dateStart->sub(new \DateInterval("P{$durationInMin}M"));
        }

        $activity->setDateStart($dateStart);
        if (!empty($activity->getDateEnd())) {
            $activity = $this->updateDuration($activity);
        }

        $this->entityManager->saveEntity($activity);

        return $activity;
    }
}
