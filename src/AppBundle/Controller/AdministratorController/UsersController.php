<?php

namespace AppBundle\Controller\AdministratorController;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\CoreContext;
use AppBundle\Context\RoleContext;
use AppBundle\Context\UserRoleContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\UserRoleEntity;
use AppBundle\Factory\FactoryContext;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\MailManager;
use AppBundle\Managers\PrivilegeManager;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Managers\EmailTemplateManager;
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
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class UsersController extends AbstractController
{

    /**@var FactoryContext $factoryContext */
    protected $factoryContext;

    /**@var CoreContext $usersContext */
    protected $usersContext;
    /**@var RoleContext $roleContext */
    protected $roleContext;
    /**@var UserRoleContext $userRoleContext */
    protected $userRoleContext;

    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /**@var AdministrationManager $administrationManager */
    protected $administrationManager;
    /**@var PrivilegeManager $privilegeManager */
    protected $privilegeManager;
    /**@var EntityManager $entityManager */
    protected $entityManager;

    protected $encoderFactory;
    protected $managedEntityType;

    protected function initialize()
    {
        parent::initialize();
        $this->authenticateAdministrator();

        $this->usersContext = $this->getContainer()->get('user_entity_context');
        $this->encoderFactory = $this->getContainer()->get('security.encoder_factory');
        $this->roleContext = $this->getContainer()->get('role_entity_context');
        $this->userRoleContext = $this->getContainer()->get('user_role_entity_context');

        $this->managedEntityType = "users";
        $this->mailManager = $this->getContainer()->get("mail_manager");
        $this->administrationManager = $this->getContainer()->get("administration_manager");
        $this->privilegeManager = $this->getContainer()->get('privilege_manager');
        $this->entityManager = $this->getContainer()->get('entity_manager');
    }

    /**
     * @Route("administrator/users", name="users_index")
     */
    public function indexAction(Request $request)
    {
        $this->initialize();
        return new Response($this->renderView('AppBundle:Admin/Users:index.html.twig', array('managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("administrator/users/list", name="get_users_list")
     * @Method("POST")
     */
    public function GetUsersList(Request $request)
    {
        $this->initialize();

        $pager = new DataTablePager();
        $pager->setFromPost($_POST);

        $entities = $this->usersContext->getItemsWithPaging($pager);

        $html = $this->renderView('AppBundle:Admin/Users:list.html.twig', array('entities' => $entities, 'managed_entity_type' => $this->managedEntityType));
        $num_of_items = $this->usersContext->countAllItems();

        $ret = array();
        $ret["draw"] = $pager->getDraw();
        $ret["recordsTotal"] = $num_of_items;
        $ret["recordsFiltered"] = $num_of_items;
        $ret["data"] = array();
        $ret["html"] = $html;

        return new JsonResponse($ret);
    }

    /**
     * @Route("administrator/users/update/{id}", defaults={"id"=null}, name="users_update_form")
     */
    public function updateAction($id, Request $request)
    {
        $this->initialize();

        $roles = $this->roleContext->getAll();

        /**
         * Create
         */
        if (empty($id)) {
            return new Response($this->renderView('AppBundle:Admin/Users:form.html.twig', array('entity' => null, 'roles' => $roles, 'managed_entity_type' => $this->managedEntityType)));
        }
        /**
         * Update
         */
        else {
            /** @var UserEntity $entity */
            $entity = $this->usersContext->getById($id);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'User does not exist'));
            }

            return new Response($this->renderView('AppBundle:Admin/Users:form.html.twig', array('entity' => $entity, 'roles' => $roles, 'managed_entity_type' => $this->managedEntityType)));
        }

        return false;
    }

    /**
     * @Route("administrator/users/view/{id}", name="users_view_form")
     */
    public function viewAction($id, Request $request)
    {
        $this->initialize();
        $entity = $this->usersContext->getById($id);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'User does not exist'));
        }

        return new Response($this->renderView('AppBundle:Admin/Users:view.html.twig', array('entity' => $entity, 'managed_entity_type' => $this->managedEntityType)));
    }

    /**
     * @Route("administrator/users/save_user", name="users_save")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["username"]) || empty($p["username"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Username is not correct'));
        }
        if (!isset($p["email"]) || empty($p["email"])) {
            return new JsonResponse(array('error' => true, 'message' => 'e-mail is not correct'));
        }
        if (!isset($p["roles"]) || empty($p["roles"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Role is not correct'));
        }
        if (!isset($p["system_role"]) || empty($p["system_role"])) {
            return new JsonResponse(array('error' => true, 'message' => 'System role is not correct'));
        }

        /**
         * Here we handle "MFA - Google Authenticator" enabled / disabled state.
         * If enabled, we generate a new secret and set it into "googleAuthenticatorSecret" attribute. Otherwise,
         * we set an empty value.
         */
        if (isset($p['mfa_google_authenticator_enabled']) && $p['mfa_google_authenticator_enabled'] === "on") {
            $p["google_authenticator_secret"] = $this->getContainer()->get("scheb_two_factor.security.google_authenticator")->generateSecret();
        } else {
            $p["google_authenticator_secret"] = null;
        }

        // We don't need this anymore.
        unset($p['mfa_google_authenticator_enabled']);

        /**
         * INSERT
         */
        if (!isset($p["id"]) || empty($p["id"])) {
            if (!isset($p["password"]) || empty($p["password"])) {
                return new JsonResponse(array('error' => true, 'message' => 'Password is not correct'));
            }
            if (!isset($p["password_again"]) || empty($p["password_again"])) {
                return new JsonResponse(array('error' => true, 'message' => 'Password confirm is not correct'));
            }
            if ($p["password"] != $p["password_again"]) {
                return new JsonResponse(array('error' => true, 'message' => 'Passwords are not the same'));
            }

            $existing_user = $this->usersContext->getBy(array("email" => $p["email"]));
            if (!empty($existing_user)) {
                return new JsonResponse(array('error' => true, 'message' => 'User with this email already exists'));
            }

            $entity = $this->administrationManager->createUpdateUser($p);
            if (!$entity) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            /**
             * Manage user roles
             */
            foreach ($p["roles"] as $addRolesId) {
                $role = $this->roleContext->getById($addRolesId);
                if (!isset($role) || empty($role)) {
                    return new JsonResponse(array('error' => true, 'message' => 'Role does not exist'));
                }

                $roleUser = new UserRoleEntity();
                $roleUser->setRole($role);
                $roleUser->setUserEntity($entity);

                $roleUser = $this->privilegeManager->saveUserRole($roleUser);
                if (empty($roleUser)) {
                    return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
                }
            }

            /** @var EmailTemplateManager $emailTemplateManager */
            $emailTemplateManager = $this->container->get('email_template_manager');
            /** @var EmailTemplateEntity $template */
            $template = $emailTemplateManager->getEmailTemplateByCode("new_admin_account");
            if (!empty($template)) {

                if(empty($this->helperManager)){
                    $this->helperManager = $this->container->get("helper_manager");
                }

                /** @var CoreUserEntity $coreUser */
                $coreUser = $this->helperManager->getCoreUserById($entity->getId());

                $templateData = $emailTemplateManager->renderEmailTemplate($coreUser, $template);

                $templateAttachments = $template->getAttachments();
                if (!empty($templateAttachments)) {
                    $attachments = $template->getPreparedAttachments();
                }

                $this->mailManager->sendEmail(array('email' => $coreUser->getEmail(), 'name' => $coreUser->getFullName()), null, null, null, $templateData["subject"], "", null, [], $templateData["content"], $attachments ?? [], $_ENV["DEFAULT_STORE_ID"]);
            } else {
                $this->mailManager->sendEmail(array('email' => $entity->getEmail(), 'name' => $entity->getEmail()), null, null, null, "New admin account", "", "new_admin_account", array("user" => $entity, "password" => $p["password"]));
            }

            return new JsonResponse(array('error' => false, 'title' => 'Insert new user', 'message' => 'User has been added', 'entity' =>  array('id' => $entity->getId())));
        }
        /**
         * UPDATE
         */
        else {
            if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
                return new JsonResponse(array('error' => true, 'message' => 'User id is not correct'));
            }

            $entity = $this->usersContext->getById($p["id"]);

            if (!isset($entity) || empty($entity)) {
                return new JsonResponse(array('error' => true, 'message' => 'User does not exist'));
            }

            $existing_user = $this->usersContext->getBy(array("email" => $p["email"]));
            if (isset($existing_user) && $existing_user[0]->getId() != $p["id"]) {
                return new JsonResponse(array('error' => true, 'message' => 'User with this email already exists'));
            }

            $entity = $this->administrationManager->createUpdateUser($p);
            if (!$entity) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            /**
             * Manage user roles
             */
            $existingRoles = array();
            $removeRoles = array();
            $addRoles = array();

            if (!empty($entity->getUserRoles())) {
                foreach ($entity->getUserRoles() as $userRoles) {
                    $existingRoles[] = $userRoles->getRole()->getId();
                }
            }

            $removeRoles = array_diff($existingRoles, $p["roles"]);
            $addRoles = array_diff($p["roles"], $existingRoles);

            /**
             * Remove roles
             */
            if (!empty($removeRoles)) {
                foreach ($removeRoles as $removeRole) {
                    $role = $this->userRoleContext->getBy(array('userEntity' => $entity, 'role' => $removeRole));
                    if (!isset($role[0]) || empty($role[0])) {
                        return new JsonResponse(array('error' => true, 'message' => 'Role does not exist'));
                    }

                    try {
                        $this->userRoleContext->delete($role[0]);
                    } catch (\Exception $e) {
                        $this->logger->error($e->getMessage());
                        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
                    }
                }
            }

            /**
             * Add roles
             */
            if (!empty($addRoles)) {
                foreach ($addRoles as $addRolesId) {
                    $role = $this->roleContext->getById($addRolesId);
                    if (!isset($role) || empty($role)) {
                        return new JsonResponse(array('error' => true, 'message' => 'Role does not exist'));
                    }

                    $roleUser = new UserRoleEntity();
                    $roleUser->setRole($role);
                    $roleUser->setUserEntity($entity);

                    $roleUser = $this->privilegeManager->saveUserRole($roleUser);
                    if (empty($roleUser)) {
                        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
                    }
                }
            }

            $entity = $this->administrationManager->saveUser($entity);
            if (!$entity) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            return new JsonResponse(array('error' => false, 'title' => 'Update user', 'message' => 'User has been updated', 'entity' =>  array('id' => $entity->getId())));
        }

        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
    }
}
