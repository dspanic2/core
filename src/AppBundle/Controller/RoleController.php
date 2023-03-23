<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\ActionContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\PrivilegeContext;
use AppBundle\Context\RoleContext;
use AppBundle\Entity\Page;
use AppBundle\Entity\PageBlock;
use AppBundle\Entity\Privilege;
use AppBundle\Entity\RoleEntity;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\PrivilegeManager;
use Monolog\Logger;
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
    /**@var RoleContext $roleContext */
    protected $roleContext;
    /**@var PrivilegeContext $privilegeContext */
    protected $privilegeContext;
    /**@var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;

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
        $this->roleContext = $this->getContainer()->get('role_entity_context');
        $this->privilegeContext = $this->getContainer()->get('privilege_context');
        $this->attributeSetContext = $this->getContainer()->get('attribute_set_context');
        $this->privilegeManager = $this->getContainer()->get('privilege_manager');
        $this->entityManager = $this->getContainer()->get('entity_manager');

        $this->managedEntityType = "role";
    }

    /**
     * @Route("front/role/delete", name="front_role_delete")
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
     * @Route("front/role/regenerate", name="front_role_regenerate")
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
     * @Route("front/role/save", name="front_role_save")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["role_code"]) || empty($p["role_code"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Role code is not defined'));
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
            if (isset($p["default_for_page"])) {
                $entity->setDefaultForPage($p["default_for_page"]);
            }
            if (isset($p["default_for_page_block"])) {
                $entity->setDefaultForPageBlock($p["default_for_page_block"]);
            }
            if (isset($p["default_for_attribute_set"])) {
                $entity->setDefaultForAttributeSet($p["default_for_attribute_set"]);
            }
            if (isset($p["default_for_list_view"])) {
                $entity->setDefaultForListView($p["default_for_list_view"]);
            }

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

            return new JsonResponse(array('error' => false, 'title' => 'Insert new role', 'message' => 'Role has been added', 'entity' => array('id' => $entity->getId())));
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

            $entity->setRoleCode($p["role_code"]);
            if (isset($p["default_for_page"])) {
                $entity->setDefaultForPage($p["default_for_page"]);
            }
            if (isset($p["default_for_page_block"])) {
                $entity->setDefaultForPageBlock($p["default_for_page_block"]);
            }
            if (isset($p["default_for_attribute_set"])) {
                $entity->setDefaultForAttributeSet($p["default_for_attribute_set"]);
            }
            if (isset($p["default_for_list_view"])) {
                $entity->setDefaultForListView($p["default_for_list_view"]);
            }

            $entity = $this->privilegeManager->saveRole($entity);
            if (empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            $entityPrivileges = array();
            $ePrivileges = $entity->getPrivileges();
            /**
             * @var  $key
             * @var Privilege $privilege
             */
            foreach ($ePrivileges as $key => $privilege) {
                $entityPrivileges[$privilege->getActionCode()][$privilege->getActionType()->getId()] = $privilege->getId();
            }

            $actionsArray = $this->privilegeManager->getActionsIdArray();
            $privilegesArrayToAdd = array();

            if (isset($p["privilege"]) && !empty($p["privilege"])) {
                foreach ($p["privilege"] as $action_code => $actions) {
                    foreach ($actions as $action_type_id => $action) {
                        if (!isset($entityPrivileges[$action_code][$action_type_id])) {
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

            return new JsonResponse(array('error' => false, 'title' => 'Update role', 'message' => 'role has been updated', 'entity' => array('id' => $entity->getId())));
        }

        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
    }
}
