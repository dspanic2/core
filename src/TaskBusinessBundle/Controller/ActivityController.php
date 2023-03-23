<?php

namespace TaskBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\EntityManager;
use TaskBusinessBundle\Constants\TaskConstants;
use TaskBusinessBundle\Entity\ActivityEntity;
use TaskBusinessBundle\Entity\TaskEntity;
use TaskBusinessBundle\Managers\ActivityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use TaskBusinessBundle\Managers\TaskManager;

class ActivityController extends AbstractController
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var ActivityManager $activityManager */
    protected $activityManager;
    /** @var TaskManager $taskManager */
    protected $taskManager;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
        $this->activityManager = $this->container->get("activity_manager");
        $this->taskManager = $this->container->get("task_manager");
    }

    /**
     * @Route("/activity/start/{id}", name="activity_tracking_start")
     * @Method("POST")
     */
    public function activityTrackingStartAction(Request $request, $id = null)
    {
        $this->initialize();

        if (empty($id)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Activity is not set")));
        }

        /** @var ActivityEntity $activity */
        $activity = $this->activityManager->getActivityById($id);
        if (empty($activity)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Activity not found")));
        }

        $ret = $this->activityManager->startActivity($activity);

        return new JsonResponse($ret);
    }

    /**
     * @Route("/activity/stop/{id}", name="activity_tracking_stop")
     * @Method("POST")
     */
    public function activityTrackingStopAction(Request $request, $id = null)
    {
        $this->initialize();

        if (empty($id)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Activity is not set")));
        }

        /** @var ActivityEntity $activity */
        $activity = $this->activityManager->getActivityById($id);
        if (empty($activity)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Activity not found")));
        }

        $ret = $this->activityManager->stopActivity($activity);

        return new JsonResponse($ret);
    }

    /**
     * @Route("/task/start/{id}", name="task_tracking_start")
     * @Method("POST")
     */
    public function taskTrackingStartAction(Request $request, $id = null)
    {
        $this->initialize();

        $ret = array();
        $ret["error"] = true;

        if (empty($id)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Task is not set")));
        }

        /** @var TaskEntity $task */
        $task = $this->taskManager->getTaskById($id);
        if (empty($task)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Task not found")));
        }

        /** @var ActivityEntity $activity */
        $activity = $this->activityManager->startActivityForTask($task);

        if (!empty($activity)) {
            $ret = array(
                "error" => false,
                "time_start" => $activity->getDateStart()->format(TaskConstants::ACTIVITY_JS_FORMAT),
                "time_end" => null
            );
        }

        return new JsonResponse($ret);
    }

    /**
     * @Route("/task/stop/{id}", name="task_tracking_stop")
     * @Method("POST")
     */
    public function taskTrackingStopAction(Request $request, $id = null)
    {
        $this->initialize();

        $ret = array();
        $ret["error"] = true;

        if (empty($id)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Activity is not set")));
        }

        /** @var ActivityEntity $activity */
        $activity = $this->activityManager->getCurrentActivity();

        if (empty($activity)) {
            $ret["message"] = $this->translator->trans("No running activity. Please refresh page.");
            return $ret;
        }

        if ($activity->getTaskId() != $id) {
            $ret["message"] = $this->translator->trans("This task has no running activity. Please refresh page.");
            return $ret;
        }

        $ret = $this->activityManager->stopActivity($activity);

        return new JsonResponse($ret);
    }

    /**
     * @Route("/activity/get_header_button", name="get_header_activity_button")
     * @Method("GET")
     */
    public function getHeaderActivityButtonAction(Request $request)
    {
        $this->initialize();

        $activity = $this->activityManager->getCurrentActivity();

        $html = $this->renderView("TaskBusinessBundle:Includes:activity_header.html.twig", array("activity" => $activity));

        return new Response($html);
    }

    /**
     * @Route("/activity/get_activity_timer_block_html", name="get_activity_timer_block_html")
     * @Method("POST")
     */
    public function getActivityTimerBlockAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;
        if (!isset($p["activity"]) || empty($p["activity"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Missing activity ID")));
        }

        /** @var ActivityEntity $activity */
        $activity = $this->activityManager->getActivityById($p["activity"]);
        if (empty($activity)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Activity not found")));
        }
        $timer = array(
            "id" => null,
            "name" => null,
            "time_start" => null,
            "time_end" => null,
            "time_elapsed" => null
        );

        $ret["model"]["timer"] = [];
        if (!empty($activity)) {

            $timer["id"] = $activity->getId();
            $timer["name"] = $activity->getName();

            if (!empty($activity->getDateStart())) {
                $timeNow = new \DateTime();
                $timer["time_start"] = $activity->getDateStart()->format(TaskConstants::ACTIVITY_JS_FORMAT);
                $timer["time_elapsed"] = $timeNow->format("U") - $activity->getDateStart()->format("U");
            }
//            if (!empty($activity->getDateEnd())) {
//                $timer["time_end"] = $activity->getDateEnd()->format(TaskConstants::ACTIVITY_JS_FORMAT);
//            }
        }

        $ret["model"]["timer"] = $timer;

        $html = $this->renderView('TaskBusinessBundle:Includes:activity_timer.html.twig', array("data" => $ret));

        return new JsonResponse(["error" => false, "html" => $html]);
    }
}
