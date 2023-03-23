<?php

namespace AppBundle\Controller\AdministratorController;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\ActionContext;
use AppBundle\Context\PrivilegeContext;
use AppBundle\Context\RoleContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\RoleEntity;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\PrivilegeManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\UserEntity;
use Symfony\Component\Form\Extension\Templating\TemplatingExtension;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;

class RoleController extends AbstractController
{
    /**@var RoleContext\ $roleContext */
    protected $roleContext;
    /**@var PrivilegeContext $privilegeContext */
    protected $privilegeContext;
    /**@var PrivilegeManager $privilegeManager */
    protected $privilegeManager;
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /** @var ActionContext $actionContext */
    protected $actionContext;

    protected $managedEntityType;

    protected function initialize()
    {
        parent::initialize();
        $this->authenticateAdministrator();

        $this->roleContext = $this->getContainer()->get('role_entity_context');
        $this->privilegeContext = $this->getContainer()->get('privilege_context');
        $this->privilegeManager = $this->getContainer()->get('privilege_manager');
        $this->entityManager = $this->getContainer()->get('entity_manager');

        $this->managedEntityType = "role";
    }

    /**
     * @Route("administrator/role", name="role_index")
     */
    public function indexAction(Request $request)
    {
        $this->initialize();

        return new Response($this->renderView('AppBundle:Admin/Role:index.html.twig', array('managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("administrator/role/list", name="get_role_list")
     * @Method("POST")
     */
    public function GetList(Request $request)
    {
        $this->initialize();

        $pager = new DataTablePager();
        $pager->setFromPost($_POST);

        $entities = $this->roleContext->getItemsWithPaging($pager);

        $html = $this->renderView('AppBundle:Admin/Role:list.html.twig', array('entities' => $entities, 'managed_entity_type' => $this->managedEntityType));
        $num_of_items = $this->roleContext->countAllItems();

        $ret = array();
        $ret["draw"] = $pager->getDraw();
        $ret["recordsTotal"] = $num_of_items;
        $ret["recordsFiltered"] = $num_of_items;
        $ret["data"] = array();
        $ret["html"] = $html;

        return new JsonResponse($ret);
    }

    /**
     * @Route("administrator/role/update/{id}", defaults={"id"=null}, name="role_update_form")
     */
    public function updateAction($id, Request $request)
    {
        $this->initialize();

        $privilegesList = $this->privilegeManager->getAllPrivileges();
        $actionTypes = $this->privilegeManager->getActionTypes();

        /**
         * Create
         */
        if (empty($id)) {
            return new Response($this->renderView(
                'AppBundle:Admin/Role:form.html.twig',
                array(
                    'entity' => null,
                    'managed_entity_type' => $this->managedEntityType,
                    'privileges_list' => $privilegesList,
                    'action_types' => $actionTypes,
                    'entity_privileges' => null,
                )
            ));
        }
        /**
         * Update
         */
        else {
            $entity = $this->roleContext->getById($id);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
            }

            $entityPrivileges = array();
            foreach ($entity->getPrivileges() as $privilege) {
                $entityPrivileges[$privilege->getActionCode()][$privilege->getActionType()->getId()] = 1;
            }

            return new Response($this->renderView(
                'AppBundle:Admin/Role:form.html.twig',
                array(
                    'entity' => $entity,
                    'managed_entity_type' => $this->managedEntityType,
                    'privileges_list' => $privilegesList,
                    'action_types' => $actionTypes,
                    'entity_privileges' => $entityPrivileges,
                )
            ));
        }

        return false;
    }

    /**
     * @Route("administrator/role/view/{id}", name="role_view_form")
     */
    public function viewAction($id, Request $request)
    {
        $this->initialize();

        $entity = $this->roleContext->getById($id);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Does not exist'));
        }

        $entityPrivileges = array();
        foreach ($entity->getPrivileges() as $privilege) {
            $entityPrivileges[$privilege->getActionCode()][$privilege->getActionType()->getId()] = 1;
        }

        $privilegesList = $this->privilegeManager->getAllPrivileges();
        $actionTypes = $this->privilegeManager->getActionTypes();

        return new Response($this->renderView(
            'AppBundle:Admin/Role:view.html.twig',
            array(
                'entity' => $entity,
                'managed_entity_type' => $this->managedEntityType,
                'privileges_list' => $privilegesList,
                'action_types' => $actionTypes,
                'entity_privileges' => $entityPrivileges,
            )
        ));
    }

    /**
     * @Route("administrator/role/delete", name="role_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Role id is not correct'));
        }

        $entity = $this->roleContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Role does not exist'));
        }

        if (!$this->privilegeManager->deleteRole($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'Delete role', 'message' => 'Role has been deleted'));
    }

    /**
     * @Route("administrator/role/regenerate", name="role_regenerate")
     * @Method("POST")
     */
    public function regenerateAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Role id is not correct'));
        }

        $entity = $this->roleContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'Role does not exist'));
        }

        $entityPrivileges = array();
        foreach ($entity->getPrivileges() as $privilege) {
            $entityPrivileges[$privilege->getActionCode()][$privilege->getActionType()->getId()] = 1;
        }

        $privilegesList = $this->privilegeManager->getAllPrivileges();

        $actionsArray = $this->privilegeManager->getActionsTypeArray();
        $privilegesArrayToAdd = array();

        foreach ($privilegesList as $privilegesType) {
            foreach ($privilegesType["privileges"] as $privilegeGroup) {
                foreach ($privilegeGroup as $privilege) {
                    if (!isset($entityPrivileges[$privilege["action_code"]][$privilege["action_type"]])) {
                        $privilegesArrayToAdd[] = $this->privilegeManager->preparePrivilege($actionsArray[$privilege["action_type"]], $privilege["action_code"], $entity);
                    }
                }
            }
        }

        if (!empty($privilegesArrayToAdd)) {
            $this->privilegeManager->savePrivilegeArray($privilegesArrayToAdd);
        }

        return new JsonResponse(array('error' => false, 'title' => 'Delete user', 'message' => 'User has been deleted'));
    }

    public function getEntityRolePrivilegesAction(Request $request)
    {

        $this->initialize();

        $type = $request->get('type');
        $entity = $request->get('entity');

        $privileges = $this->privilegeManager->getEntityRolePrivileges($type, $entity);
        $privileges["entity_id"] = $entity;

        if(empty($this->actionContext)){
            $this->actionContext = $this->getContainer()->get("action_context");
        }

        $actionTypes = $this->actionContext->getAll();

        if(empty($this->roleContext)){
            $this->roleContext = $this->getContainer()->get("role_context");
        }

        $roles = $this->roleContext->getAll();

        return new Response($this->renderView(
            'AppBundle:Admin/Role:privileges_on_entity.html.twig', Array("privilege" => $privileges, "action_types" => $actionTypes, "roles" => $roles)
        ));
    }


    /**
     * @Route("administrator/role/save", name="role_save")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["attributeCode"]) || empty($p["attributeCode"])) {
            return new JsonResponse(array('error' => true, 'message' => 'attributeCode is not defined'));
        }

        /**
         * INSERT
         */
        if (!isset($p["id"]) || empty($p["id"])) {
            $attributeSet = $this->entityManager->getAttributeSetByCode('role');

            $entity = new RoleEntity();
            $entity->setRoleCode($p["role_code"]);
            $entity->setEntityType($attributeSet->getEntityType());
            $entity->setAttributeSet($attributeSet);
            $entity->setEntityStateId(1);

            $entity = $this->privilegeManager->saveRole($entity);
            if (empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            $actionsArray = $this->privilegeManager->getActionsIdArray();
            $privilegesArrayToAdd = array();

            if (isset($p["privilege"]) && !empty($p["privilege"])) {
                foreach ($p["privilege"] as $action_code => $actions) {
                    foreach ($actions as $action_type_id => $action) {
                        $privilegesArrayToAdd[] = $this->privilegeManager->preparePrivilege($actionsArray[$action_type_id], $action_code, $entity);
                    }
                }
            }

            if (!empty($privilegesArrayToAdd)) {
                $this->privilegeManager->savePrivilegeArray($privilegesArrayToAdd);
            }

            return new JsonResponse(array('error' => false, 'title' => 'Insert new role', 'message' => 'Role has been added', 'entity' =>  array('id' => $entity->getId())));
        } /**
         * UPDATE
         */
        else {
            if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
                return new JsonResponse(array('error' => true, 'message' => 'Role id is not correct'));
            }

            $entity = $this->roleContext->getById($p["id"]);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'Role does not exist'));
            }

            $entity->setRoleCode($p["attributeCode"]);

            $entity = $this->privilegeManager->saveRole($entity);
            if (empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            $entityPrivileges = array();
            $ePrivileges = $entity->getPrivileges();
            foreach ($ePrivileges as $key => $privilege) {
                $entityPrivileges[$privilege->getActionCode()][$privilege->getActionType()->getId()] = $privilege->getId();
            }


            $actionsArray = $this->privilegeManager->getActionsIdArray();
            $privilegesArrayToAdd = array();

            if (isset($p["privilege"]) && !empty($p["privilege"])) {
                foreach ($p["privilege"] as $action_code => $actions) {
                    foreach ($actions as $action_type_id => $action) {
                        if (!isset($entityPrivileges[$action_code][$action])) {
                            $privilegesArrayToAdd[] = $this->privilegeManager->preparePrivilege($actionsArray[$action_type_id], $action_code, $entity);
                        } else {
                            unset($entityPrivileges[$action_code][$action_type_id]);
                        }
                    }
                }
            }

            if (!empty($privilegesArrayToAdd)) {
                $this->privilegeManager->savePrivilegeArray($privilegesArrayToAdd);
            }

            $privilegesArrayToDelete = array();

            if (!empty($entityPrivileges)) {
                foreach ($entityPrivileges as $action_code => $actions) {
                    foreach ($actions as $action => $id) {
                        $privilegesArrayToDelete[] = $this->privilegeContext->getById($id);
                    }
                }
            }

            if (!empty($privilegesArrayToDelete)) {
                $this->privilegeManager->deletePrivilegesArray($privilegesArrayToDelete);
            }

            return new JsonResponse(array('error' => false, 'title' => 'Update role', 'message' => 'role has been updated', 'entity' =>  array('id' => $entity->getId())));
        }

        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
    }


}
