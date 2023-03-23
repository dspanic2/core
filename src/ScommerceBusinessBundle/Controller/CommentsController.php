<?php

namespace ScommerceBusinessBundle\Controller;

use AppBundle\Managers\GoogleCaptchaValidateManager;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\TrackingEntity;
use CrmBusinessBundle\Managers\AccountManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use ScommerceBusinessBundle\Entity\SEntityCommentEntity;
use ScommerceBusinessBundle\Entity\SEntityRatingEntity;
use ScommerceBusinessBundle\Managers\CommentsManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class CommentsController extends AbstractScommerceController
{
    /** @var CommentsManager */
    protected $commentsManager;
    /** @var GoogleCaptchaValidateManager $googleCaptchaValidateManager */
    protected $googleCaptchaValidateManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->initializeTwigVariables($request);
        $this->commentsManager = $this->container->get("comments_manager");
    }

    /**
     * @Route("/api/add_comment", name="add_comment")
     * @Method("POST")
     */
    public function saveNewCommentAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if (empty($this->googleCaptchaValidateManager)) {
            $this->googleCaptchaValidateManager = $this->container->get("google_captcha_validate_manager");
        }
        if ($this->googleCaptchaValidateManager->shouldValidateGoogleRecaptchaV3()) {
            if (!isset($p["recaptcha_response"]) || empty($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true));
            }

            if (!$this->googleCaptchaValidateManager->validateGoogleRecaptchaV3($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true));
            }
        }

        if (!isset($p["entity_id"]) || empty($p["entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing entity ID")));
        }

        if (!isset($p["s_entity_type"]) || empty($p["s_entity_type"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing entity type ID")));
        }

        if (!isset($p["comment"]) || empty($p["comment"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing comment")));
        }

        if (!isset($p["gdpr"]) || empty($p["gdpr"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Please comply with privacy policy")));
        }

        if (!isset($p["first_name"]) || empty($p["first_name"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("First name missing")));
        }

        if (!isset($p["email"]) || empty($p["email"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Email missing")));
        }
        if (!filter_var($p["email"], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid email")));
        }

        if (!isset($p["last_name"]) || empty($p["last_name"])) {
            $p["last_name"] = null;
        }

        if (isset($_FILES) && !empty($_FILES)) {
            $p["files"] = $_FILES;
        }

        $session = $request->getSession();

        if (empty($this->accountManager)) {
            $this->accountManager = $this->getContainer()->get("account_manager");
        }

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->getDefaultContact();

        /** @var TrackingEntity $tracking */
        $tracking = $this->accountManager->getTracking($session->getId());
        if (empty($tracking)) {
            if (!empty($contact)) {
                $p["email"] = $contact->getEmail();
                $p["first_name"] = $contact->getFirstName();
                $p["last_name"] = $contact->getLastName();
            }

            if (!isset($p["email"])) {
                return new JsonResponse(array('error' => false, 'request_data' => true));
            }
            if (!filter_var($p["email"], FILTER_VALIDATE_EMAIL)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid email")));
            }

            /**
             * Save tracking
             */
            $trackingData = array();
            $trackingData["email"] = $p["email"];
            $trackingData["first_name"] = $p["first_name"];
            $trackingData["last_name"] = $p["last_name"];
            $trackingData["contact"] = $contact;
            $trackingData["session_id"] = $session->getId();

            /** @var TrackingEntity $tracking */
            $tracking = $this->accountManager->insertUpdateTracking($trackingData);
        }

        /**
         * Save GDPR
         */
        $p["given_on_process"] = "product_comment";
        $this->accountManager->insertGdpr($p, $contact);

        if ($this->commentsManager->userCommentedEntity($p["s_entity_type"], $p["entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("You already commented")));
        }

        $rating = null;
        if (isset($p["rate"]) && !empty($p["rate"])) {
            /** @var SEntityRatingEntity $comment */
            $rating = $this->commentsManager->saveEntityRatePost($request, $p);
        }

        /** @var SEntityCommentEntity $comment */
        $comment = $this->commentsManager->saveCommentFromPost($request, $p, $rating);
        if (!empty($comment)) {
            return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Your comment has been saved")));
        }

        return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error saving comment")));
    }

    /**
     * @Route("/api/rate_entity", name="rate_entity")
     * @Method("POST")
     */
    public function rateEntityAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if (!isset($p["entity_id"]) || empty($p["entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing entity ID")));
        }

        if (!isset($p["s_entity_type"]) || empty($p["s_entity_type"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing entity type ID")));
        }

        if (!isset($p["rate"]) || empty($p["rate"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing rate")));
        }

        if ($this->commentsManager->userRatedEntity($p["s_entity_type"], $p["entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("You already rated")));
        }

        /** @var SEntityRatingEntity $comment */
        $rating = $this->commentsManager->saveEntityRatePost($request, $p);
        if (!empty($rating)) {
            return new JsonResponse(array('error' => false, 'content' => $this->translator->trans("Your rating has been saved")));
        }

        return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error saving rating")));
    }
}
