<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Factory\FactoryEntityType;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Entity\KanbanColumnEntity;
use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use AppBundle\Managers\EntityManager;
use Symfony\Component\Config\Definition\Exception\Exception;
use TaskBusinessBundle\Managers\TaskManager;

class KanbanController extends AbstractController
{
    /** @var  EntityManager $entityManager */
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
     * @Route("/kanban/update", name="kanban_update")
     * @Method("POST")
     */
    public function updateKanbanPositions(Request $request)
    {
        $p = $_POST;
        $this->initialize();

        if (!isset($p["type"]) || empty($p["type"])) {
            return new JsonResponse(array('error' => true, 'message' => "Kanban type is empty"));
        }
        if ($p["type"] == "generated") {
            if (!isset($p["entity_type"]) || empty($p["entity_type"])) {
                return new JsonResponse(array('error' => true, 'message' => "entity_type not received"));
            }
            if (!isset($p["column_changed"]) || empty($p["column_changed"])) {
                return new JsonResponse(array('error' => true, 'message' => "column_changed not received"));
            }
            if (!isset($p["task_id"]) || empty($p["task_id"])) {
                return new JsonResponse(array('error' => true, 'message' => "task_id not received"));
            }
            if (!isset($p["block_id"]) || empty($p["block_id"])) {
                return new JsonResponse(array('error' => true, 'message' => "block_id not received"));
            }
            if (!isset($p["order"]) || empty($p["order"])) {
                return new JsonResponse(array('error' => true, 'message' => "order not received"));
            }

            $entity_type = $_POST["entity_type"];
            $column_changed = $_POST["column_changed"];
            $task_id = $_POST["task_id"];
            $column_id = $_POST["block_id"];
            $order = $_POST["order"];

            $setter = EntityHelper::makeSetter($column_changed);
            $getter = EntityHelper::makeGetter($column_changed);
            try {
                $task = $this->entityManager->getEntityByEntityTypeAndId($entity_type, $task_id);

                if ($column_id != $task->{$getter}()) {
                    $task->{$setter}($column_id);
                    $this->entityManager->saveEntity($task);
                }

                $this->updateColumnOrder($order, $entity_type);

                return new JsonResponse(array('error' => false, 'message' => "Changes saved"));
            } catch (Exception $ex) {
                return new JsonResponse(array('error' => true, 'message' => "Exception ocured"));
            }
        } elseif ($p["type"] == "custom") {
            if (!isset($p["data"]) || empty($p["data"])) {
                return new JsonResponse(array('error' => true, 'message' => "Data not received"));
            }

            $columnEntityType = $this->entityManager->getEntityTypeByCode("kanban_column");

            foreach ($p["data"] as $column_id => $items) {
                if ($column_id == 0) {
                    continue;
                }

                $column = $this->entityManager->getEntityByEntityTypeAndId($columnEntityType, $column_id);
                $column->setColumnSettings(json_encode($items));
                $this->entityManager->saveEntity($column);
            }
            return new JsonResponse(array('error' => false, 'message' => "Changes saved"));
        }

        return new JsonResponse(array('error' => true, 'message' => "Kanban type not found"));
    }

    /**
     * @param $order
     * @param $entityType
     * @return void
     */
    private function updateColumnOrder($order, $entityType)
    {
        foreach ($order as $key => $value) {
            $task = $this->entityManager->getEntityByEntityTypeAndId($entityType, $value);
            if (method_exists($task, "setKanbanOrder")) {
                $task->setKanbanOrder($key);
                $this->entityManager->saveEntity($task);
            }
        }
    }

    /**
     * @Route("/kanban/update-column", name="kanban_update_column")
     * @Method("POST")
     */
    public function updateColumn(Request $request)
    {
        $p = $_POST;
        $this->initialize();

        if (!isset($p["name"]) || empty($p["name"])) {
            return new JsonResponse(array('error' => true, 'message' => "name not received"));
        }
        if (!isset($p["color"]) || empty($p["color"])) {
            return new JsonResponse(array('error' => true, 'message' => "color not received"));
        }
        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => "id not received"));
        }

        try {
            $this->entityManager = $this->getContainer()->get('entity_manager');
            $columnEntityType = $this->entityManager->getEntityTypeByCode("kanban_column");
            $column = $this->entityManager->getEntityByEntityTypeAndId($columnEntityType, $p["id"]);
            $column->setColor($p["color"]);
            $column->setName($p["name"]);
            $this->entityManager->saveEntity($column);
            return new JsonResponse(array('error' => false, 'message' => "Changes saved", "update" => 1, "entity" => $this->entityManager->entityToArray($column)));
        } catch (\Exception $e) {
            return new JsonResponse(array('error' => true, 'message' => $e->getMessage()));
        }
    }


    /**
     * @Route("/kanban/update_task_item", name="kanban_update_task_item")
     * @Method("POST")
     */
    public function kanbanUpdateTaskItemAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["task"]) || empty($p["task"])) {
            return new JsonResponse(array('error' => true, 'message' => "Task missing"));
        }
        if (!isset($p["priority"]) || empty($p["priority"])) {
            return new JsonResponse(array('error' => true, 'message' => "Priority missing"));
        }
        if (!isset($p["user"]) || empty($p["user"])) {
            return new JsonResponse(array('error' => true, 'message' => "User missing"));
        }

        try {
            $this->taskManager->updateKanbanTaskItem($p["task"], $p["user"], $p["priority"]);
        } catch (\Exception $e) {
            return new JsonResponse(array('error' => true, 'message' => $e->getMessage()));
        }

        return new JsonResponse(array('error' => false, 'message' => "Saved"));
    }
}
