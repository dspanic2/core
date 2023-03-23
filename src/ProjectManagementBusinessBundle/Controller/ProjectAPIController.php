<?php

namespace ProjectManagementBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Helpers\StringHelper;
use Exception;
use ProjectManagementBusinessBundle\Entity\ProjectStageEntity;
use ProjectManagementBusinessBundle\Managers\ProjectManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use TaskBusinessBundle\Entity\TaskEntity;

class ProjectAPIController extends AbstractController
{

    /**
     * @Route("/project/api/tasks/{project_id}", name="get_project_task_api")
     * @Method("GET")
     * @return JsonResponse
     */
    public function getProjectActivitiesAction(Request $request, string $project_id)
    {
        /** @var ProjectManager $project_manager */
        $project_manager = $this->container->get('project_manager');

        try {
            $data = [];
            $tasks = $project_manager->getProjectTasks($project_id);
            $dependencies = $project_manager->getProjectDependencies($project_id);
            $stages = $project_manager->getProjectStages($project_id);

            /** @var TaskEntity $task */
            foreach ($tasks as $task) {

                $pDepend = "";
                $assignedTo = $task->getAssignedTo() != null ? $task->getAssignedTo()->getFullname() : "";

                $pClass = "gtaskblue";


                /**@var ProjectTaskDependencyEntity $dependency */
                foreach ($dependencies as $dependency) {
                    if ($dependency->getTask()->getId() == $task->getId()) {
                        $pDepend = StringHelper::format("{0},{1}{2}", $pDepend, $dependency->getDependsOn()->getId(), $dependency->getType()->getCode());
                    }
                }


                $data[] = [
                    'pID' => $task->getId(),
                    'pName' => $task->getSubject(),
                    'pStart' => $task->getStartDate()->format('Y-m-d'),
                    'pEnd' => $task->getDueDate()->format('Y-m-d'),
                    "pPlanStart" => "",
                    "pPlanEnd" => "",
                    "pClass" => $pClass,
                    "pLink" => "",
                    "pMile" => $task->getMilestone() == true ? 1 : "",
                    "pRes" => $assignedTo,
                    "pComp" => $task->getProgress(),
                    "pGroup" => "",
                    "pParent" => $task->getProjectStage() != null ? "s" . $task->getProjectStage()->getId() : "",
                    "pOpen" => 1,
                    "pDepend" => $pDepend,
                    "pCaption" => "",
                    "pCost" => "",
                    "pNotes" => $task->getDescription(),
                ];
            }

            /** @var ProjectStageEntity $stage */
            foreach ($stages as $stage) {

                $pDepend = "";

                $pClass = "ggroupblack";


                $data[] = [
                    'pID' => "s" . $stage->getId(),
                    'pName' => $stage->getName(),
                    'pStart' => $stage->getStartDate() != null ? $stage->getStartDate()->format('Y-m-d') : "",
                    'pEnd' => $stage->getEndDate() != null ? $stage->getEndDate()->format('Y-m-d') : "",
                    "pPlanStart" => "",
                    "pPlanEnd" => "",
                    "pClass" => $pClass,
                    "pLink" => "",
                    "pMile" => "",
                    "pRes" => "",
                    "pComp" => "",
                    "pGroup" => 1,
                    "pParent" => "",
                    "pOpen" => 1,
                    "pDepend" => "",
                    "pCaption" => "",
                    "pCost" => "",
                    "pNotes" => "",
                ];
            }

            return new JsonResponse([
                'error' => false,
                'data' => json_encode($data),
                'message' => ""
            ]);
        } catch (Exception $e) {
            return new JsonResponse(array(
                'error' => true,
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * @Route("/activity/change_completed/{id}", name="activity_change_completed")
     * @Method("POST")
     */
    public function changeCompletedAction(Request $request, $id = null)
    {
        $this->initialize();
        /** @var ProjectManager $project_manager */
        $project_manager = $this->container->get('project_manager');

        $entity = $project_manager->changeCompletedById($id);

        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Activity does not exist')));
        }

        $message = "Activity marked uncompleted";
        if ($entity->getIsCompleted())
            $message = "Activity marked completed";

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Activity changed'), 'message' => $this->translator->trans($message)));
    }

    /**
     * @Route("/stage_dependency/add", name="add_stage_dependency")
     * @Method("POST")
     */
    public function addStageDependency(Request $request)
    {
        $this->initialize();
        /** @var \AppBundle\Managers\EntityManager $entityManager */
        $entityManager = $this->container->get('entity_manager');

        $etTask = $entityManager->getEntityTypeByCode("project_stage");
        $etTaskDependencyType = $entityManager->getEntityTypeByCode("project_stage_dependency_type");

        $task_id = $request->get("stage_id");
        $depends_on_id = $request->get("depends_on_id");
        $type_id = $request->get("type_id");

        /**@var ProjectStageEntity $stage */
        $stage = $entityManager->getEntityByEntityTypeAndId($etTask, $task_id);
        $dependsOn = $entityManager->getEntityByEntityTypeAndId($etTask, $depends_on_id);
        $type = $entityManager->getEntityByEntityTypeAndId($etTaskDependencyType, $type_id);

        /**@var \ProjectManagementBusinessBundle\Entity\ProjectStageDependencyEntity $dependency */
        $dependency = $entityManager->getNewEntityByAttributSetName("project_stage_dependency");

        $dependency->setProject($stage->getProject());
        $dependency->setStage($stage);
        $dependency->setDependsOn($dependsOn);
        $dependency->setType($type);

        $entityManager->saveEntity($dependency);

        $html = $this->renderView('ProjectManagementBusinessBundle:Includes:project_stage_dependency.html.twig', array("dependency" => $dependency));

        return new JsonResponse(array('error' => false,
            'title' => $this->translator->trans('Success'),
            'message' => $this->translator->trans('Dependency added'),
            'html' => $html));
    }

    /**
     * @Route("/stage_dependency/remove", name="remove_stage_dependency")
     * @Method("POST")
     */
    public function removeStageDependency(Request $request)
    {
        $this->initialize();
        /** @var \AppBundle\Managers\EntityManager $entityManager */
        $entityManager = $this->container->get('entity_manager');

        $etTaskDependency = $entityManager->getEntityTypeByCode("project_stage_dependency");

        $id = $request->get("id");

        /**@var ProjectStageDependencyEntity $dependency */
        $dependency = $entityManager->getEntityByEntityTypeAndId($etTaskDependency, $id);
        $entityManager->deleteEntityFromDatabase($dependency);


        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Success'), 'message' => $this->translator->trans('Dependency removed')));
    }

    /**
     * @Route("/api/project/all", name="api_get_all_projects")
     * @Method("POST")
     */
    public function getAllProjects(Request $request)
    {
        $p = $_POST;
        $this->initialize();

        if (!isset($p["token"]) || empty($p["token"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Token is empty')));
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getUserByToken($request, $p["token"]);
        if (empty($coreUser)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'token_rebuild' => true,
                    'message' => $this->translator->trans('Token not valid'),
                )
            );
        }

        /** @var ProjectManager $projectApiManager */
        $projectApiManager = $this->container->get('project_manager');
        /** @var \AppBundle\Managers\EntityManager $entityManager */
        $entityManager = $this->container->get('entity_manager');
        $data = [];
        /** @var ProjectEntity $p */
        foreach ($projectApiManager->getFilteredProjects() as $p) {
            $projectArray = $entityManager->entityToArray($p, false);

            $tasks = [];
            foreach ($projectApiManager->getProjectTasks($p->getId()) as $t) {
                $tasks[] = $entityManager->entityToArray($t, false);
            }
            $projectArray["project_tasks"] = $tasks;
            $data[] = $projectArray;
        }

        return new JsonResponse(array("error" => false, "data" => $data));
    }
}