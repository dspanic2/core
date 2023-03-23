<?php

namespace AppBundle\Controller\AdministratorController;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\ActionContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\Action;
use AppBundle\Managers\PrivilegeManager;
use Monolog\Logger;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Templating\TemplatingExtension;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;

class ActionController extends AbstractController
{
    /**@var ActionContext $actionContext */
    protected $actionContext;

    /**@var PrivilegeManager $privilegeManager */
    protected $privilegeManager;

    protected $managedEntityType;

    protected function initialize()
    {
        parent::initialize();
        $this->authenticateAdministrator();
        $this->actionContext = $this->getContainer()->get('action_context');
        $this->privilegeManager = $this->getContainer()->get('privilege_manager');
        $this->managedEntityType = "action";
    }

    /**
     * @Route("administrator/action", name="action_index")
     */
    public function indexAction(Request $request)
    {
        $this->initialize();
        return new Response($this->renderView('AppBundle:Admin/Action:index.html.twig', array('managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("administrator/action/list", name="get_action_list")
     * @Method("POST")
     */
    public function GetList(Request $request)
    {
        $this->initialize();

        $pager = new DataTablePager();
        $pager->setFromPost($_POST);

        $entities = $this->actionContext->getItemsWithPaging($pager);

        $html = $this->renderView('AppBundle:Admin/Action:list.html.twig', array('entities' => $entities, 'managed_entity_type' => $this->managedEntityType));
        $num_of_items = $this->actionContext->countAllItems();

        $ret = array();
        $ret["draw"] = $pager->getDraw();
        $ret["recordsTotal"] = $num_of_items;
        $ret["recordsFiltered"] = $num_of_items;
        $ret["data"] = array();
        $ret["html"] = $html;

        return new JsonResponse($ret);
    }

    /**
     * @Route("administrator/action/update/{id}", defaults={"id"=null}, name="action_update_form")
     */
    public function updateAction($id, Request $request)
    {
        $this->initialize();

        /**
         * Create
         */
        if (empty($id)) {
            return new Response($this->renderView(
                'AppBundle:Admin/Action:form.html.twig',
                array(
                    'entity' => null,
                    'managed_entity_type' => $this->managedEntityType,
                )
            ));
        }
        /**
         * Update
         */
        else {
            $entity = $this->actionContext->getById($id);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
            }

            return new Response($this->renderView(
                'AppBundle:Admin/Action:form.html.twig',
                array(
                    'entity' => $entity,
                    'managed_entity_type' => $this->managedEntityType,
                )
            ));
        }

        return false;
    }

    /**
     * @Route("administrator/action/delete", name="action_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Action id is not correct'));
        }

        $entity = $this->actionContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Action does not exist'));
        }

        if (!$this->privilegeManager->deleteAction($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'Delete role', 'message' => 'Action has been deleted'));
    }

    /**
     * @Route("administrator/action/save", name="action_save")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["actionType"]) || empty($p["actionType"])) {
            return new JsonResponse(array('error' => true, 'message' => 'actionType is not defined'));
        }
        if (!isset($p["context"]) || empty($p["context"])) {
            return new JsonResponse(array('error' => true, 'message' => 'context is not defined'));
        }
        if (!isset($p["nameField"]) || empty($p["nameField"])) {
            return new JsonResponse(array('error' => true, 'message' => 'nameField is not defined'));
        }

        /**
         * INSERT
         */
        if (!isset($p["id"]) || empty($p["id"])) {
            $entity = new Action();
            $entity->setActionType($p["actionType"]);
            $entity->setContext($p["context"]);
            $entity->setNameField($p["nameField"]);

            $entity = $this->privilegeManager->saveAction($entity);
            if (empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            return new JsonResponse(array('error' => false, 'title' => 'Insert new action', 'message' => 'Action has been added', 'entity' =>  array('id' => $entity->getId())));
        } /**
         * UPDATE
         */
        else {
            if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
                return new JsonResponse(array('error' => true, 'message' => 'Action id is not correct'));
            }

            $entity = $this->actionContext->getById($p["id"]);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Action does not exist'));
            }

            $entity->setActionType($p["actionType"]);
            $entity->setContext($p["context"]);
            $entity->setNameField($p["nameField"]);

            $entity = $this->privilegeManager->saveAction($entity);
            if (empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            return new JsonResponse(array('error' => false, 'title' => 'Update action', 'message' => 'Action has been updated', 'entity' =>  array('id' => $entity->getId())));
        }

        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
    }
}
