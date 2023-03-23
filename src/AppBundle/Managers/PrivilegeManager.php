<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\ActionContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\PrivilegeContext;
use AppBundle\Entity;
use AppBundle\Helpers\EntityHelper;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\PersistentCollection;

class PrivilegeManager extends AbstractBaseManager
{

    /**@var PrivilegeContext $privilegeContext */
    protected $privilegeContext;
    /**@var RoleContext $roleContext */
    protected $roleContext;
    /**@var UserRoleContext $userRoleContext */
    protected $userRoleContext;
    /**@var ActionContext $actionContext*/
    protected $actionContext;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;

    protected $roleAdmin = 1;

    public function initialize()
    {
        parent::initialize();
        $this->privilegeContext = $this->container->get('privilege_context');
        $this->roleContext = $this->container->get('role_entity_context');
        $this->userRoleContext = $this->container->get('user_role_entity_context');
        $this->actionContext = $this->container->get('action_context');
    }

    /**
     * @return mixed
     */
    public function getActionTypes()
    {

        $actionTypes = $this->actionContext->getAll();
        $actionTypesTmp = array();

        foreach ($actionTypes as $actionType) {
            $actionTypesTmp[$actionType->getId()] = $actionType->getActionType();
        }

        return $actionTypesTmp;
    }

    /**
     * @param $actionContext
     * @return array
     */
    public function getActionTypesByContext($actionContext)
    {

        $actionTypes = $this->actionContext->getBy(array('context' => $actionContext));
        $actionTypesTmp = array();

        foreach ($actionTypes as $actionType) {
            $actionTypesTmp[$actionType->getId()] = $actionType->getActionType();
        }

        return $actionTypesTmp;
    }

    /**
     * @param array $avoidRoleCodes
     * @return mixed
     */
    public function getAllRoles($avoidRoleCodes = Array())
    {
        $roles = $this->roleContext->getAll();

        if(!empty($avoidRoleCodes)){
            /** @var Entity\RoleEntity $role */
            foreach ($roles as $key => $role){
                if(in_array($role->getRoleCode(),$avoidRoleCodes)){
                    unset($roles[$key]);
                }
            }
        }
        return $roles;
    }


    /**
     * @return array
     */
    public function getAllPrivileges($actionContext = null, $actionCode = null)
    {
        $privileges = array();

        $actionTypes = $this->actionContext->getAll();

        foreach ($actionTypes as $actionType) {
            if (!empty($actionContext) && $actionContext != $actionType->getContext()) {
                continue;
            }

            $context = $this->container->get($actionType->getContext()."_context");
            $data = $context->getAll();

            $nameField = "get".ucfirst($actionType->getNameField());

            foreach ($data as $d) {
                if (!empty($actionCode) && $d->getId() != $actionCode) {
                    continue;
                }

                $privilege = array();
                $privilege["entity_code"] = $d->$nameField();
                $privilege["action_type"] = $actionType->getId();
                if ($actionCode === 0) {
                    $privilege["action_code"] = 0;
                } else {
                    $privilege["action_code"] = $d->getUid();
                }

                //$privileges[$actionType->getContext()]["privileges"][$d->$nameField()][] = $privilege;
                $privileges[$actionType->getContext()]["privileges"][$d->getUid()][] = $privilege;

                if ($actionCode === 0) {
                    break;
                }
            }

            $privileges[$actionType->getContext()]["name"] = $actionType->getContext();
        }

        return $privileges;
    }

    /**
     * @param $privileges
     * @param $actionCode
     * @param null $actionType
     * @return bool
     */
    public function savePrivilegesForEntity($privileges, $actionCode)
    {
        $usedPrivileges = array();

        $actionType = key($privileges[1]);

        $usedPrivilegesTmp = $this->privilegeContext->getBy(array('actionType' => $actionType, 'actionCode' => $actionCode));
        foreach ($usedPrivilegesTmp as $usedPrivilegeTmp) {
            $usedPrivileges[$usedPrivilegeTmp->getRole()->getId()][$usedPrivilegeTmp->getActionType()->getId()] = $usedPrivilegeTmp->getId();
        }

        $addPrivilegesArray = array();
        $actionTypesArray = $this->getActionsIdArray();

        foreach ($privileges as $roleId => $privilege) {
            $role = $this->roleContext->getById($roleId);

            foreach ($privilege as $actionTypeId => $val) {
                if (!isset($usedPrivileges[$roleId][$actionTypeId])) {
                    $addPrivilegesArray[] = $this->preparePrivilege($actionTypesArray[$actionTypeId], $actionCode, $role);
                } else {
                    unset($usedPrivileges[$roleId][$actionTypeId]);
                }
            }
        }

        if (!empty($addPrivilegesArray)) {
            $this->savePrivilegeArray($addPrivilegesArray);
        }

        if (!empty($usedPrivileges)) {
            $privilegesDeleteArray = array();

            foreach ($usedPrivileges as $roleId => $privilege_group) {
                if ($roleId != $this->roleAdmin) {
                    foreach ($privilege_group as $privilegeId) {
                        $privilegesDeleteArray[] = $this->privilegeContext->getById($privilegeId);
                    }
                }
            }

            if (!empty($privilegesDeleteArray)) {
                $this->deletePrivilegesArray($privilegesDeleteArray);
            }
        }

        return true;
    }

    /**
     * @param $actionContext
     * @param $actionCode
     * @return array
     */
    public function getEntityRolePrivileges($actionContext, $actionCode)
    {

        $actionTypes = $this->getActionTypesByContext($actionContext);
        if (empty($actionTypes)) {
            throwException(new \Exception("No action types found in database"));
        }


        if (empty($actionCode)) {
            $actionCode = 0;
        }

        $privileges = $this->getAllPrivileges($actionContext, $actionCode);

        $roles = $this->roleContext->getAll();
        $usedPrivileges = array();

        if (!empty($actionCode)) {
            $usedPrivilegesTmp = $this->privilegeContext->getBy(array('actionCode' => $actionCode));
            foreach ($usedPrivilegesTmp as $usedPrivilegeTmp) {
                $usedPrivileges[$usedPrivilegeTmp->getRole()->getId()][$usedPrivilegeTmp->getActionType()->getId()] = 1;
            }
        }

        return array(
            'action_types' => $actionTypes,
            'privilege_list' => $privileges,
            'roles' => $roles,
            'usedPrivileges' => $usedPrivileges,
            'actionCode' => $actionCode,
            'type' => Inflector::camelize("default_for_".strtolower($actionContext))
        );
    }

    /**
     * @param $actionContext
     * @param $actionCode
     * @return bool
     */
    public function addPrivilegesToAllGroups($actionContext, $actionCode)
    {

        $actionTypes = $this->actionContext->getBy(array('context' => $actionContext));
        $roles = $this->roleContext->getAll();

        $privileges = array();

        foreach ($roles as $role) {
            foreach ($actionTypes as $actionType) {
                if (in_array($actionType->getId(), array(5,6,7))) {
                    $defaultValue = Inflector::camelize("default_for_".strtolower($actionType->getActionType()));
                    $defaultValue = EntityHelper::makeGetter($defaultValue);
                    if ($role->getId() != 1 && $role->{$defaultValue}() == 0) {
                        continue;
                    }
                }
                $privileges[] = $this->preparePrivilege($actionType, $actionCode, $role);
            }
        }

        if (!empty($privileges)) {
            $this->savePrivilegeArray($privileges);
        }

        return true;
    }

    /**
     * @param $actionType
     * @param $actionCode
     * @param Entity\RoleEntity $role
     * @return Entity\Privilege
     */
    public function preparePrivilege($actionType, $actionCode, Entity\RoleEntity $role)
    {
        $privilege = new Entity\Privilege();
        $privilege->setRole($role);
        $privilege->setActionType($actionType);
        $privilege->setActionCode($actionCode);

        return $privilege;
    }

    /**
     * @param $actionTypeId
     * @param $entityType
     * @param $actionCode
     * @return bool
     * Add new privilege when creating new function
     */
    public function addPrivilege($actionTypeId, $actionCode, Entity\RoleEntity $role)
    {

        $actionType = $this->actionContext->getById($actionTypeId);

        $privilege = new Entity\Privilege();
        $privilege->setRole($role);
        $privilege->setActionType($actionType);
        $privilege->setActionCode($actionCode);

        $privilege = $this->savePrivilege($privilege);

        return $privilege;
    }

    /**
     * @param $privileges
     * @return bool
     */
    public function savePrivilegeArray($privileges)
    {

        try {
            $this->privilegeContext->saveArray($privileges);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param Entity\Privilege $privilege
     * @return bool|Entity\Privilege
     */
    public function savePrivilege(Entity\Privilege $privilege)
    {

        try {
            $this->privilegeContext->save($privilege);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return $privilege;
    }

    /**
     * @param $actionType
     * @param $entityType
     * @param $actionCode
     * @return bool
     * remove privilege when deleting function
     */
    public function removePrivilege($actionType, $actionCode)
    {

        $entities = $this->privilegeContext->getBy(array("actionType" => $actionType,"actionCode" => $actionCode));

        if (!empty($entities)) {
            $this->deletePrivilegesArray($entities);
        }

        return true;
    }

    /**
     * @param $privileges
     * @return bool
     */
    public function deletePrivilegesArray($privileges)
    {

        try {
            $this->privilegeContext->deleteArray($privileges);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param Entity\Privilege $privilege
     * @return bool
     */
    public function deletePrivilege(Entity\Privilege $privilege)
    {



        try {
            $this->privilegeContext->delete($privilege);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param Entity\UserRoleEntity $userRole
     * @return bool
     */
    public function deleteUserRole(Entity\UserRoleEntity $userRole)
    {

        try {
            $this->userRoleContext->delete($userRole);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param Entity\UserRoleEntity $userRole
     * @return bool|Entity\UserRoleEntity|mixed
     */
    public function saveUserRole(Entity\UserRoleEntity $userRole)
    {

        try {
            $userRole = $this->userRoleContext->save($userRole);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return $userRole;
    }

    /**
     * @param Entity\RoleEntity $role
     * @return bool|Entity\RoleEntity|mixed
     */
    public function saveRole(Entity\RoleEntity $role)
    {

        try {
            $role = $this->roleContext->save($role);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return $role;
    }

    /**
     * @param Entity\RoleEntity $role
     * @return bool
     */
    public function deleteRole(Entity\RoleEntity $role)
    {

        $privileges = $this->privilegeContext->getBy(array("role" => $role));

        $this->deletePrivilegesArray($privileges);

        $userRoles = $this->userRoleContext->getBy(array("role" => $role));

        foreach ($userRoles as $userRole) {
            $this->deleteUserRole($userRole);
        }

        try {
            $this->roleContext->delete($role);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param Entity\Action $action
     * @return bool|Entity\Action|mixed
     */
    public function saveAction(Entity\Action $action)
    {

        try {
            $action = $this->actionContext->save($action);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return $action;
    }

    /**
     * @param Entity\Action $action
     * @return bool
     */
    public function deleteAction(Entity\Action $action)
    {

        $privileges = $this->privilegeContext->getBy(array("actionType" => $action));

        if (!empty($privileges)) {
            $this->deletePrivilegesArray($privileges);
        }

        try {
            $this->actionContext->delete($action);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return true;
    }

    public function getActionsTypeArray()
    {

        $actionsArray = array();

        $actions = $this->actionContext->getAll();

        foreach ($actions as $action) {
            $actionsArray[$action->getActionType()] = $action;
        }

        return $actionsArray;
    }

    public function getActionsIdArray()
    {

        $actionsArray = array();

        $actions = $this->actionContext->getAll();

        foreach ($actions as $action) {
            $actionsArray[$action->getId()] = $action;
        }

        return $actionsArray;
    }

    /**
     * @param $roleId
     * @param $surceTable
     * @param $actionType
     * @return bool|void
     */
    public function recreateRolePrivileges($roleId,$surceTable,$actionType){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM {$surceTable} WHERE uid not in (SELECT action_code FROM privilege WHERE role = {$roleId} and action_type = {$actionType});";
        $missingPrivileges = $this->databaseContext->getAll($q);

        if(empty($missingPrivileges)){
            return true;
        }

        $insertQuery = "INSERT INTO privilege (role,action_type,action_code) VALUES ";
        $insertQueryValues = Array();
        foreach ($missingPrivileges as $missingPrivilege){
            $insertQueryValues[] = "({$roleId},{$actionType},'{$missingPrivilege["uid"]}')";
        }

        $q = $insertQuery.implode(",",$insertQueryValues);
        $this->databaseContext->executeNonQuery($q);

        return true;
    }
}
