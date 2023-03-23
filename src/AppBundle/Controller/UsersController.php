<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\CoreContext;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;
use AppBundle\Managers\MailManager;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Managers\EmailTemplateManager;
use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Templating\TemplatingExtension;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\Session;
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

    /**@var CoreContext $usersContext */
    protected $usersContext;
    /** @var MailManager $mailManager */
    protected $mailManager;
    /**@var AdministrationManager $administrationManager */
    protected $administrationManager;
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /** @var FormManager $formManager */
    protected $formManager;

    protected function initialize()
    {
        parent::initialize();
        $this->usersContext = $this->getContainer()->get('user_entity_context');
        $this->mailManager = $this->getContainer()->get("mail_manager");
        $this->administrationManager = $this->getContainer()->get("administration_manager");
        $this->entityManager = $this->getContainer()->get('entity_manager');
    }

    protected function initializeForm($type)
    {
        $factoryManager = $this->getContainer()->get('factory_manager');
        $this->formManager = $factoryManager->loadFormManager($type);
    }

    /**
     * @Route("admin/users/delete", name="users_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'User id is not correct'));
        }

        $entity = $this->usersContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'User does not exist'));
        }


        if (!$this->administrationManager->deleteUser($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'Delete user', 'message' => 'User has been deleted'));
    }

    /**
     * @Route("admin/users/resetpassword_user", name="users_reset_password")
     * @Method("POST")
     */
    public function resetPasswordAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'User id is not correct'));
        }

        $entity = $this->usersContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'User does not exist'));
        }

        $password = StringHelper::generateRandomString(6);

        $entity = $this->administrationManager->setUserPassword($entity, $password);

        $entity = $this->administrationManager->saveUser($entity);
        if (!$entity) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        $this->mailManager->sendEmail(array('email' => $entity->getEmail(), 'name' => $entity->getEmail()), null, null, null, "Reset admin password", "", "reset_admin_password", array("user" => $entity, "password" => $password));

        return new JsonResponse(array('error' => false, 'title' => 'Password reset', 'message' => 'Password reset successful'));
    }

    /**
     * @Route("admin/users/disable_user", name="users_disable")
     * @Method("POST")
     */
    public function disableAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'User id is not correct'));
        }

        $entity = $this->usersContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'User does not exist'));
        }

        $entity->setEnabled(false);

        $entity = $this->administrationManager->saveUser($entity);
        if (!$entity) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'User status changed', 'message' => 'User has been disabled'));
    }

    /**
     * @Route("admin/users/enable_user", name="users_enable")
     * @Method("POST")
     */
    public function enableAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => 'User id is not correct'));
        }

        $entity = $this->usersContext->getById($p["id"]);

        if (!isset($entity) || empty($entity)) {
            return new JsonResponse(array('error' => true, 'message' => 'User does not exist'));
        }

        $entity->setEnabled(true);

        $entity = $this->administrationManager->saveUser($entity);
        if (!$entity) {
            return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
        }

        return new JsonResponse(array('error' => false, 'title' => 'User status changed', 'message' => 'User has been enabled'));
    }

    /**
     * @Route("admin/users/save_user", name="front_users_save")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        $type = "core_user";

        $this->initializeForm($type);

        if (!isset($p["username"]) || empty($p["username"])) {
            return new JsonResponse(array('error' => true, 'message' => 'Username is not correct'));
        }
        if (!isset($p["email"]) || empty($p["email"])) {
            return new JsonResponse(array('error' => true, 'message' => 'e-mail is not correct'));
        }

        /**
         * INSERT
         */
        if (!isset($p["id"]) || empty($p["id"])) {
            if (!isset($p["system_role"]) || empty($p["system_role"])) {
                return new JsonResponse(array('error' => true, 'message' => 'System role is not correct'));
            }

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

            $p["enabled"] = true;
            $p["expired"] = false;
            $p["credentials_expired"] = false;
            $p["roles"] = serialize(array($p["system_role"]));
            $p["locked"] = null;
            $p["username_canonical"] = $p["username"];
            $p["email_canonical"] = $p["email"];
            $p["salt"] = StringHelper::generateRandomString(31);

            /** @var CoreUserEntity $entity */
            $entity = $this->formManager->saveFormModel($type, $p);
            if (!$entity) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }
            $this->entityManager->refreshEntity($entity);

            $user = $this->helperManager->getUserBySalt($entity->getSalt());

            /**
             * Set password
             */
            $user = $this->administrationManager->setUserPassword($user, $p["password"]);
            if ($p["system_role"] == "ROLE_ADMIN") {
                $user->setEntityStateId(2);
            }

            $user = $this->administrationManager->saveUser($user);
            if (!$user) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }

            /**
             * Check if is admin
             */
            $isAdmin = false;
            if(isset($_ENV["FRONTEND_ADMIN_ACCOUNT_ROLES"]) && !empty($_ENV["FRONTEND_ADMIN_ACCOUNT_ROLES"])){
                $frontendAdminAccountRoles = json_decode($_ENV["FRONTEND_ADMIN_ACCOUNT_ROLES"], true);
                $roleCodes = $entity->getUserRoleCodes();
                if (EntityHelper::isCountable($roleCodes) && count($roleCodes) > 0 && count(array_intersect($frontendAdminAccountRoles, $roleCodes)) != 0) {
                    $isAdmin = true;
                }
            }

            /**
             * Automaticly create frontend account if needed
             */
            if(isset($_ENV["FRONTEND_ADMIN_ACCOUNT_CREATE"]) && $_ENV["FRONTEND_ADMIN_ACCOUNT_CREATE"] && $isAdmin){
                $this->helperManager->generateAccountAndContactForAdmin($entity);
            }

            /**
             * Send new account email
             */
            if($isAdmin){

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
            }
            else{
                //TODO send frontend account
            }

            return new JsonResponse(array('error' => false, 'title' => 'Insert new user', 'message' => 'User has been added', 'entity' => array('id' => $entity->getId())));
        } /**
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

            $existing_users = $this->usersContext->getBy(array("email" => $p["email"]));

            if (isset($existing_users)) {
                foreach ($existing_users as $existing_user) {
                    if ($existing_user->getId() != $p["id"]) {
                        return new JsonResponse(array('error' => true, 'message' => 'User with this email already exists'));
                    }
                }
            }

            if(empty($p["password"]) || empty($p["password_again"])){
                unset($p["password"]);
                unset($p["password_again"]);
            }

            $p["username_canonical"] = $p["username"];
            $p["email_canonical"] = $p["email"];
            $p["roles"] = serialize(array($p["system_role"]));


            /** @var CoreUserEntity $entity */
            $entity = $this->formManager->saveFormModel($type, $p);
            if (!$entity) {
                return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
            }
            $this->entityManager->refreshEntity($entity);

            if ($p["system_role"] == "ROLE_ADMIN") {
                $entity->setEntityStateId(2);
                $this->entityManager->saveEntityWithoutLog($entity);
                $this->entityManager->refreshEntity($entity);
            }

            /**
             * Check if is admin
             */
            $isAdmin = false;
            if(isset($_ENV["FRONTEND_ADMIN_ACCOUNT_ROLES"]) && !empty($_ENV["FRONTEND_ADMIN_ACCOUNT_ROLES"])){
                $frontendAdminAccountRoles = json_decode($_ENV["FRONTEND_ADMIN_ACCOUNT_ROLES"], true);
                $roleCodes = $entity->getUserRoleCodes();
                if (EntityHelper::isCountable($roleCodes) && count($roleCodes) > 0 && count(array_intersect($frontendAdminAccountRoles, $roleCodes)) != 0) {
                    $isAdmin = true;
                }
            }

            /**
             * Reset password
             */
            if (!empty($p["password"]) && !empty($p["password_again"])) {
                $user = $this->helperManager->getUserBySalt($entity->getSalt());
                $user = $this->administrationManager->setUserPassword($user, $p["password"]);
                $this->administrationManager->saveUser($user);

                if($isAdmin){
                    $this->mailManager->sendEmail(array('email' => $entity->getEmail(), 'name' => $entity->getEmail()), null, null, null, "Reset admin password", "", "reset_admin_password", array("user" => $entity, "password" => $p["password"]));
                }
            }

            /**
             * Automaticly create frontend account if needed
             */
            if(isset($_ENV["FRONTEND_ADMIN_ACCOUNT_CREATE"]) && $_ENV["FRONTEND_ADMIN_ACCOUNT_CREATE"] && $isAdmin){
                $this->helperManager->generateAccountAndContactForAdmin($entity);
            }

            return new JsonResponse(array('error' => false, 'title' => 'Update user', 'message' => 'User has been updated', 'entity' => array('id' => $entity->getId())));
        }

        return new JsonResponse(array('error' => true, 'message' => 'There has been an error please try again'));
    }

    /**
     * @Route("/users/login", name="users_login")
     */
    public function loginAction(Request $request)
    {
        /** @var $session \Symfony\Component\HttpFoundation\Session\Session */
        $session = $request->getSession();

        if (class_exists('\Symfony\Component\Security\Core\Security')) {
            $authErrorKey = Security::AUTHENTICATION_ERROR;
            $lastUsernameKey = Security::LAST_USERNAME;
        } else {
            // BC for SF < 2.6
            $authErrorKey = SecurityContextInterface::AUTHENTICATION_ERROR;
            $lastUsernameKey = SecurityContextInterface::LAST_USERNAME;
        }

        // get the error if any (works with forward and redirect -- see below)
        if ($request->attributes->has($authErrorKey)) {
            $error = $request->attributes->get($authErrorKey);
        } elseif (null !== $session && $session->has($authErrorKey)) {
            $error = $session->get($authErrorKey);
            $session->remove($authErrorKey);
        } else {
            $error = null;
        }

        if (!$error instanceof AuthenticationException) {
            $error = null; // The value does not come from the security component.
        }

        // last username entered by the user
        $lastUsername = (null === $session) ? '' : $session->get($lastUsernameKey);


        if ($this->has('security.csrf.token_manager')) {
            $csrfToken = $this->getContainer()->get('security.csrf.token_manager')->getToken('authenticate')->getValue();
        } else {
            // BC for SF < 2.4
            $csrfToken = $this->has('form.csrf_provider')
                ? $this->getContainer()->get('form.csrf_provider')->generateCsrfToken('authenticate')
                : null;
        }

        return new Response($this->renderView('AppBundle:Admin/Users:login.html.twig', array(
            'last_username' => $lastUsername,
            'error' => $error,
            'csrf_token' => $csrfToken,
        )));
    }

    /**
     * @Route("/users/login/check", name="users_login_check")
     * @Method("POST")
     */
    public function checkAction()
    {
        throw new \RuntimeException('You must configure the check path to be handled by the firewall using form_login in your security firewall configuration.');
    }

    /**
     * @Route("/users/display-admin", name="users_display_admin")
     * @Method("POST")
     */
    public function displayAsAdminAction(Request $request)
    {
        /** @var Session $session */
        $session = $request->getSession();

        $superadminDisabled = $session->get("disable_superadmin") ?? false;

        $session->set("disable_superadmin", !$superadminDisabled);

        return new JsonResponse(array('error' => false));
    }
}
