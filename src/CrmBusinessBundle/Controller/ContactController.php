<?php

namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\AppTemplateManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ErrorLogManager;
use AppBundle\Managers\FormManager;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\NewsletterManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContactController extends AbstractController
{
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var NewsletterManager $newsletterManager */
    protected $newsletterManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var FormManager $formManager */
    protected $formManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;

    protected function initialize()
    {
        parent::initialize();
    }

    protected function initializeForm($type)
    {
        $factoryManager = $this->getContainer()->get('factory_manager');
        $this->formManager = $factoryManager->loadFormManager($type);
    }

    /**
     * @Route("/contact/save", name="contact_save_form")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $type = "contact";

        $this->initializeForm($type);

        if (!isset($_POST["email"]) || empty($_POST["email"])) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Email cannot be empty')));
        }
        if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid email")));
        }

        if (!isset($_POST["id"]) || empty($_POST["id"])) {
            if (empty($this->accountManager)) {
                $this->accountManager = $this->getContainer()->get("account_manager");
            }

            if (!isset($_ENV["ENABLE_MULTIPLE_CONTACTS_PER_EMAIL"]) || $_ENV["ENABLE_MULTIPLE_CONTACTS_PER_EMAIL"] != 1) {
                $contact = $this->accountManager->getContactByEmail($_POST["email"]);

                if (!empty($contact)) {
                    return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Contact with this email already exists with id: ') . $contact->getId()));
                }
            }
        }

        /** @var ContactEntity $entity */
        $entity = $this->formManager->saveFormModel($type, $_POST);

        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('There has been an error')));
        }

        if (empty($this->entityManager)) {
            $this->entityManager = $this->getContainer()->get("entity_manager");
        }

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Success'), 'message' => $this->translator->trans('Form has been submitted'), 'entity' => $this->entityManager->entityToArray($entity)));
    }

    /**
     * @Route("/contact/anonymize", name="contact_anonymize")
     * @Method("POST")
     */
    public function contactAnonymizeAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Contact id is not defined')));
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->getContactById($p["id"]);

        if (empty($contact)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Contact does not exist')));
        }

        try{
            $this->accountManager->gdprAnonymize($contact);
        }
        catch (\Exception $e){
            if(empty($this->errorLogManager)){
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }
            $this->errorLogManager->logExceptionEvent("Error GDPR anonymize", $e, true);

            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('There has been an error. Please contact us on support mail.')));
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Contact anonymized and removed from newsletter')));
    }

    /**
     * @Route("/contact/remove_from_newsletter", name="contact_remove_from_newsletter")
     * @Method("POST")
     */
    public function contactRemoveFromNewsletterAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Contact id is not defined')));
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->getContactById($p["id"]);

        if (empty($contact)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Contact does not exist')));
        }

        if (empty($this->newsletterManager)) {
            $this->newsletterManager = $this->container->get("newsletter_manager");
        }

        $this->newsletterManager->removeContactFromNewsletter($contact);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Contact removed from newsletter')));
    }

    /**
     * @Route("/contact/generate_user_account_form", name="contact_generate_user_account_form")
     * @Method("POST")
     */
    public function contactGenerateUserAccountFormAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Contact id is not defined')));
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->getContactById($p["id"]);

        if (empty($contact)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Contact does not exist')));
        }

        if (!empty($contact->getCoreUserId())) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Contact already has user account')));
        }

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        $stores = $this->routeManager->getStores();

        $html = $this->renderView(
            "CrmBusinessBundle:Includes:contact_create_user_account.html.twig",
            [
                'id' => $p["id"],
                'stores' => $stores,
            ]
        );

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/contact/generate_user_account", name="contact_generate_user_account")
     * @Method("POST")
     */
    public function contactGenerateUserAccountAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        $p = array_map('trim', $p);

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Contact id is not defined')));
        }
        if (!isset($p["password"]) || empty($p["password"])) {
            $p["password"] = StringHelper::generateRandomString(6,false,false);
        }
        if (strlen($p["password"]) < 6) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Password must be at least 6 characters long')));
        }
        if (!isset($p["store_id"]) || empty($p["store_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Store id is not defined')));
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->getContactById($p["id"]);

        if (empty($contact)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Contact does not exist')));
        }

        if (!empty($contact->getCoreUserId())) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Contact already has user account')));
        }

        $session = $request->getSession();
        $session->set("current_store_id", $p["store_id"]);

        if (!$this->accountManager->createUserForContact($contact, $p["password"])) {
            return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Error creating user account for contact')));
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('User account created for contact')));
    }
}
