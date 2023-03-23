<?php

namespace TaskBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Managers\ListViewManager;
use TaskBusinessBundle\Entity\ActivityEntity;
use TaskBusinessBundle\Entity\TaskEntity;
use TaskBusinessBundle\Entity\TaskPriorityEntity;
use TaskBusinessBundle\Managers\ActivityManager;
use TaskBusinessBundle\Managers\TaskManager;

class TaskAgileKanbanBlock extends AbstractBaseBlock
{

    /** @var TaskManager $taskManager */
    protected $taskManager;
    /** @var ActivityManager */
    protected $activityManager;

    public function GetPageBlockTemplate()
    {
        if (isset($this->pageBlockData["model"]) && !empty($this->pageBlockData["model"])) {
            return ('TaskBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
        } else {
            return ('AppBundle:Block:block_error.html.twig');
        }
    }

    public function GetPageBlockData()
    {
        if (empty($this->activityManager)) {
            $this->activityManager = $this->container->get("activity_manager");
        }
        if (empty($this->taskManager)) {
            $this->taskManager = $this->container->get("task_manager");
        }

        $this->pageBlockData["model"]["current_activity"] = $this->activityManager->getCurrentActivity();
        $this->pageBlockData["model"]["kanban"] = [];
        $this->pageBlockData["model"]["projects"] = [];
        $this->pageBlockData["model"]["priorities"] = [];
        $this->pageBlockData["model"]["users"] = [];

        $taskPriorities = $this->taskManager->getTaskPriorities();
        if(empty($taskPriorities)){
            return [];
        }

        $this->pageBlockData["model"]["priorities"] = $taskPriorities;

        $taskUsers = $this->taskManager->getTaskUsers();
        if(empty($taskUsers)){
            return [];
        }

        $this->pageBlockData["model"]["users"] = $taskUsers;

        $tasks = $this->taskManager->getKanbanTasks();

        if (!empty($tasks)) {
            $ret = [];
            /** @var TaskEntity $task */
            foreach ($tasks as $task) {
                $this->pageBlockData["model"]["projects"][$task->getProject()->getId()] =  $task->getProject();
                if (!isset($ret[$task->getAssignedToId()])) {
                    $ret[$task->getAssignedToId()] = [
                        "user" => $task->getAssignedTo(),
                        "tasks" => [],
                    ];

                    /** @var TaskPriorityEntity $taskPriority */
                    foreach($taskPriorities as $taskPriority){
                        $ret[$task->getAssignedToId()]["tasks"][$taskPriority->getId()] = [
                            "priority" => $taskPriority,
                            "items" => [],
                        ];
                    }
                }

                $ret[$task->getAssignedToId()]["tasks"][$task->getPriorityId()]["items"][] = $task;
            }

            $this->pageBlockData["model"]["kanban"] = $ret;
        }

        if(!empty($this->pageBlockData["model"]["projects"])){
            usort($this->pageBlockData["model"]["projects"], function($a, $b) {
                return $a->getName() <=> $b->getName();
            });
        }

        return $this->pageBlockData;

    }
}