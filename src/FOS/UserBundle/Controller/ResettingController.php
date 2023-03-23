<?php

namespace FOS\UserBundle\Controller;

use AppBundle\Entity\CoreUserEntity;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\MailManager;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Managers\EmailTemplateManager;
use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Controller managing the resetting of the password
 */
class ResettingController extends Controller
{
    /** @var MailManager $mailManager */
    protected $mailManager;

    /** @var HelperManager $helperManager */
    protected $helperManager;

    /**
     * Request reset user password: show form
     */
    public function requestAction()
    {
        return $this->render('FOSUserBundle:Resetting:request.html.twig');
    }

    /**
     * Request reset user password: submit form and send email
     */
    public function sendEmailAction(Request $request)
    {
        $username = $request->request->get('username');

        /** @var $user UserInterface */
        $user = $this->get('fos_user.user_manager')->findUserByUsernameOrEmail($username);

        if (null === $user) {
            return $this->render('FOSUserBundle:Resetting:request.html.twig', array(
                'invalid_username' => $username
            ));
        }

        /*if ($user->isPasswordRequestNonExpired($this->container->getParameter('fos_user.resetting.token_ttl'))) {
            return $this->render('FOSUserBundle:Resetting:passwordAlreadyRequested.html.twig');
        }*/

        if(empty($this->mailManager)){
            $this->mailManager = $this->container->get("mail_manager");
        }

        if (null === $user->getConfirmationToken()) {
            /** @var $tokenGenerator \FOS\UserBundle\Util\TokenGeneratorInterface */
            $tokenGenerator = $this->get('fos_user.util.token_generator');
            $user->setConfirmationToken($tokenGenerator->generateToken());
        }
        $confirmationUrl = $this->container->get('router')->generate('fos_user_resetting_reset', array('token' => $user->getConfirmationToken()), UrlGeneratorInterface::ABSOLUTE_URL);

        /** @var EmailTemplateManager $emailTemplateManager */
        $emailTemplateManager = $this->container->get('email_template_manager');
        /** @var EmailTemplateEntity $template */
        $template = $emailTemplateManager->getEmailTemplateByCode("reset_password_admin");
        if (!empty($template)) {

            if(empty($this->helperManager)){
                $this->helperManager = $this->container->get("helper_manager");
            }

            /** @var CoreUserEntity $coreUser */
            $coreUser = $this->helperManager->getCoreUserById($user->getId());

            $templateData = $emailTemplateManager->renderEmailTemplate($coreUser, $template, null, Array("confirmation_token" => $confirmationUrl));

            $templateAttachments = $template->getAttachments();
            if (!empty($templateAttachments)) {
                $attachments = $template->getPreparedAttachments();
            }

            $this->mailManager->sendEmail(array('email' => $coreUser->getEmail(), 'name' => $coreUser->getFullName()), null, null, null, $templateData["subject"], "", null, [], $templateData["content"], $attachments ?? [], $_ENV["DEFAULT_STORE_ID"]);
        } else {
            $translator = $this->container->get("translator");
            $this->mailManager->sendEmail(Array('email' => $user->getEmail(), 'name' => $user->getEmail()),null,null,null,$translator->trans("Reset password"),"","reset_password_fos",Array("user" => $user, "confirmationUrl" => $confirmationUrl));
        }

        $user->setPasswordRequestedAt(new \DateTime());
        $this->get('fos_user.user_manager')->updateUser($user);

        return new RedirectResponse($this->generateUrl('fos_user_resetting_check_email',
            array('email' => $this->getObfuscatedEmail($user))
        ));
    }

    /**
     * Tell the user to check his email provider
     */
    public function checkEmailAction(Request $request)
    {
        $email = $request->query->get('email');

        if (empty($email)) {
            // the user does not come from the sendEmail action
            return new RedirectResponse($this->generateUrl('fos_user_resetting_request'));
        }

        return $this->render('FOSUserBundle:Resetting:checkEmail.html.twig', array(
            'email' => $email,
        ));
    }

    /**
     * Reset user password
     */
    public function resetAction(Request $request, $token)
    {
        /** @var $formFactory \FOS\UserBundle\Form\Factory\FactoryInterface */
        $formFactory = $this->get('fos_user.resetting.form.factory');
        /** @var $userManager \FOS\UserBundle\Model\UserManagerInterface */
        $userManager = $this->get('fos_user.user_manager');
        /** @var $dispatcher \Symfony\Component\EventDispatcher\EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');

        $user = $userManager->findUserByConfirmationToken($token);

        if (null === $user) {
            return $this->render('FOSUserBundle:Resetting:reset.html.twig', array('invalid_username' => "user_does_not_exist", 'form' => null, 'token' => $token));
            //throw new NotFoundHttpException(sprintf('The user with "confirmation token" does not exist for value "%s"', $token));
        }

        $event = new GetResponseUserEvent($user, $request);
        $dispatcher->dispatch(FOSUserEvents::RESETTING_RESET_INITIALIZE, $event);

        if (null !== $event->getResponse()) {
            return $event->getResponse();
        }

        $form = $formFactory->createForm();
        $form->setData($user);

        $form->handleRequest($request);

        $error = Array();

        if (isset($_POST["fos_user_resetting_form"]["plainPassword"]["first"]) && !empty($_POST["fos_user_resetting_form"]["plainPassword"]["first"]) && isset($_POST["fos_user_resetting_form"]["plainPassword"]["second"]) && !empty($_POST["fos_user_resetting_form"]["plainPassword"]["second"]) && $_POST["fos_user_resetting_form"]["plainPassword"]["first"] == $_POST["fos_user_resetting_form"]["plainPassword"]["second"]) {

            $isPasswordValid = true;
            $password = trim($_POST["fos_user_resetting_form"]["plainPassword"]["first"]);

            $uppercase = preg_match('@[A-Z]@', $password);
            $lowercase = preg_match('@[a-z]@', $password);
            $number    = preg_match('@[0-9]@', $password);
            $specialChars = preg_match('@[^\w]@', $password);

            if(strpos($password, ' ') !== false){
                $isPasswordValid = false;
                $error[] = 'Remove spaces from your password';
            }
            if(!$uppercase){
                $isPasswordValid = false;
                $error[] = 'Add at least one uppercase letter';
            }
            if(!$lowercase){
                $isPasswordValid = false;
                $error[] = 'Add at least one lowercase letter';
            }
            if(!$number){
                $isPasswordValid = false;
                $error[] = 'Add at least one number';
            }
            if(!$specialChars){
                $isPasswordValid = false;
                $error[] = 'Add at least one special character';
            }
            if(strlen($password) < 8){
                $isPasswordValid = false;
                $error[] = 'Password should be at least 8 characters long';
            }

            if($isPasswordValid){
                $event = new FormEvent($form, $request);
                $dispatcher->dispatch(FOSUserEvents::RESETTING_RESET_SUCCESS, $event);

                $userManager->updateUser($user);

                if (true || null === $response = $event->getResponse()) {
                    $url = $this->generateUrl('fos_user_security_login');
                    $response = new RedirectResponse($url);
                }

                $dispatcher->dispatch(FOSUserEvents::RESETTING_RESET_COMPLETED, new FilterUserResponseEvent($user, $request, $response));

                return $response;
            }
        }

        return $this->render('FOSUserBundle:Resetting:reset.html.twig', array(
            'token' => $token,
            'error' => $error,
            'form' => $form->createView(),
        ));
    }

    /**
     * Get the truncated email displayed when requesting the resetting.
     *
     * The default implementation only keeps the part following @ in the address.
     *
     * @param \FOS\UserBundle\Model\UserInterface $user
     *
     * @return string
     */
    protected function getObfuscatedEmail(UserInterface $user)
    {
        $email = $user->getEmail();
        if (false !== $pos = strpos($email, '@')) {
            $email = '...' . substr($email, $pos);
        }

        return $email;
    }
}
