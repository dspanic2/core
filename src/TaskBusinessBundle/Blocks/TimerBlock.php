<?php

namespace TaskBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Abstracts\JsonResponse;
use AppBundle\Entity\PageBlock;
use AppBundle\Managers\BlockManager;
use TaskBusinessBundle\Constants\TaskConstants;
use TaskBusinessBundle\Entity\ActivityEntity;
use TaskBusinessBundle\Managers\ActivityManager;

class TimerBlock extends AbstractBaseBlock
{
    /** @var ActivityManager $activityManager */
    protected $activityManager;

    /**
     * @return string
     */
    public function GetPageBlockTemplate()
    {
        return "TaskBusinessBundle:Block:" . $this->pageBlock->getType() . ".html.twig";
    }

    /**
     * @return mixed
     */
    public function GetPageBlockData()
    {
        $timer = array(
            "id" => null,
            "name" => null,
            "time_start" => null,
            "time_end" => null,
            "time_elapsed" => null
        );

        if (empty($this->activityManager)) {
            $this->activityManager = $this->getContainer()->get("activity_manager");
        }

        /** @var ActivityEntity $activity */
        $activity = null;

        if ($this->pageBlockData["subtype"] == "form") {
            if (!empty($this->pageBlockData["id"]) &&
                !empty($this->pageBlockData["model"]["entity"]) &&
                $this->pageBlockData["model"]["entity"]->getEntityType()->getEntityTypeCode() == "activity") {
                $activity = $this->activityManager->getActivityById($this->pageBlockData["id"]);
            }
        } else {
            $activity = $this->activityManager->getCurrentActivity();
        }

        if (!empty($activity)) {

            $timer["id"] = $activity->getId();
            $timer["name"] = $activity->getName();

            if (!empty($activity->getDateStart())) {
                $timeNow = new \DateTime();
                $timer["time_start"] = $activity->getDateStart()->format(TaskConstants::ACTIVITY_JS_FORMAT);
                $timer["time_elapsed"] = $timeNow->format("U") - $activity->getDateStart()->format("U");
            }
            if (!empty($activity->getDateEnd())) {
                $timer["time_end"] = $activity->getDateEnd()->format(TaskConstants::ACTIVITY_JS_FORMAT);
            }
        }

        $this->pageBlockData["model"]["timer"] = $timer;

        return $this->pageBlockData;
    }

    /**
     * @return bool
     */
    public function isVisible()
    {
//        if (empty($this->activityManager)) {
//            $this->activityManager = $this->getContainer()->get("activity_manager");
//        }
//
//        if (empty($this->pageBlockData["id"]) && empty($this->activityManager->getCurrentActivity())) {
//            return false;
//        }

        return true;
    }

    /**
     * @return string
     */
    public function GetPageBlockSetingsTemplate()
    {
        return "TaskBusinessBundle:BlockSettings:" . $this->pageBlock->getType() . ".html.twig";
    }

    /**
     * @return array
     */
    public function GetPageBlockSetingsData()
    {
        return array(
            "entity" => $this->pageBlock
        );
    }

    /**
     * @param $data
     * @return JsonResponse|PageBlock|bool
     */
    public function SavePageBlockSettings($data)
    {
        /** @var BlockManager $blockManager */
        $blockManager = $this->container->get("block_manager");

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }
}
