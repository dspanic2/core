<?php

namespace TaskBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TaskBusinessBundle\Constants\TaskConstants;
use TaskBusinessBundle\Managers\TaskManager;

class TaskController extends AbstractController
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var TaskManager $taskManager */
    protected $taskManager;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->taskManager = $this->getContainer()->get("task_manager");
    }

    /**
     * @Route("/task/change_completed/{id}", name="change_completed")
     * @Method("POST")
     */
    public function changeCompletedAction(Request $request, $id = null)
    {
        $this->initialize();

        $task = $this->taskManager->getTaskById($id);
        if (empty($task)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Task does not exist")));
        }

        $data = array();
        if ($task->getStatusId() == TaskConstants::TASK_STATUS_COMPLETED) {
            $data["status"] = $this->taskManager->getTaskStatusById(TaskConstants::TASK_STATUS_IN_PROGRESS);
        } else {
            $data["status"] = $this->taskManager->getTaskStatusById(TaskConstants::TASK_STATUS_COMPLETED);
        }

        $task = $this->taskManager->createUpdateTask($data, $task);

        $message = "Task marked uncompleted";
        if ($task->getIsCompleted()) {
            $message = "Task marked completed";
        }

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Task changed"), "message" => $this->translator->trans($message)));
    }

    /**
     * @Route("/task/get_header_button", name="get_header_button")
     * @Method("POST")
     */
    public function getHeaderTaskButtonAction(Request $request)
    {
        $this->initialize();

        $data = $request->get("data");

        if (!isset($data["page"]) || empty($data["page"]) || $data["page"]->getUrl() == "task" || $data["page"]->getType() == "list") {
            return new Response("");
        }

        if (empty($data["id"]) || empty($data["page"]->getAttributeSet())) {
            return new Response("");
        }

        $html = $this->renderView("TaskBusinessBundle:Includes:add_task_header.html.twig", array("data" => $data));

        return new Response($html);
    }
}