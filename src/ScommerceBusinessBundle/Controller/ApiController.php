<?php

namespace ScommerceBusinessBundle\Controller;

use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\GoogleCaptchaValidateManager;
use AppBundle\Managers\MailManager;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Entity\FavoriteEntity;
use CrmBusinessBundle\Entity\GeneralQuestionEntity;
use CrmBusinessBundle\Entity\NewsletterEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\TrackingEntity;
use CrmBusinessBundle\Entity\WarehouseEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\EmailTemplateManager;
use CrmBusinessBundle\Managers\NewsletterManager;
use CrmBusinessBundle\Managers\ProductManager;
use CrmBusinessBundle\Managers\QuoteManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use ScommerceBusinessBundle\Constants\ScommerceConstants;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ScommerceBusinessBundle\Managers\DefaultScommerceManager;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Managers\ScommerceHelperManager;
use ScommerceBusinessBundle\Managers\TrackingManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;

class ApiController extends AbstractScommerceController
{

    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var ProductManager $productManager */
    protected $productManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var DefaultScommerceManager $defaultScommerceManager */
    protected $defaultScommerceManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;
    /** @var NewsletterManager $newsletterManager */
    protected $newsletterManager;
    /** @var TrackingManager $trackingManager */
    protected $trackingManager;
    /** @var ScommerceHelperManager $sCommerceHelperManager */
    protected $sCommerceHelperManager;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var MailManager $mailManager */
    protected $mailManager;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->initializeTwigVariables($request);
        $this->routeManager = $this->container->get("route_manager");
    }

    /**
     * @Route("/api/remind_me", name="remind_me")
     * @Method("POST")
     */
    public function insertRemindMeAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        // Validate only if reponse is there. Ajax does not send it.
        if (isset($p["recaptcha_response"])) {
            /** @var GoogleCaptchaValidateManager $googleCaptchaValidateManager */
            $googleCaptchaValidateManager = $this->container->get("google_captcha_validate_manager");
            if ($googleCaptchaValidateManager->shouldValidateGoogleRecaptchaV3()) {
                if (empty($p["recaptcha_response"])) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Captcha response is empty.")));
                }

                if (!$googleCaptchaValidateManager->validateGoogleRecaptchaV3($p["recaptcha_response"])) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error validating captcha")));
                }
            }
        }

        if (!isset($p["session_id"]) || empty($p["session_id"])) {
            $p["session_id"] = $request->getSession()->getId();
        }

        if (!isset($p["product_id"]) || empty($p["product_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Item is missing')));
        }
        if (!isset($p["first_name"])) {
            $p["first_name"] = null;
        }
        if (!isset($p["last_name"])) {
            $p["last_name"] = null;
        }

        if (empty($this->productManager)) {
            $this->productManager = $this->container->get("product_manager");
        }

        /** @var ProductEntity $product */
        $product = $this->productManager->getProductById($p["product_id"]);

        if (empty($product)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Item does no exist')));
        }

        $warehouse = null;
        if(isset($p["warehouse_id"]) && !empty($p["warehouse_id"])){
            /** @var WarehouseEntity $warehouse */
            $warehouse = $this->productManager->getWarehouseById($p["warehouse_id"]);
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->getContainer()->get("account_manager");
        }

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->getDefaultContact();

        /** @var TrackingEntity $tracking */
        $tracking = $this->accountManager->getTracking($p["session_id"]);
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
            $trackingData["session_id"] = $request->getSession()->getId();

            /** @var TrackingEntity $tracking */
            $tracking = $this->accountManager->insertUpdateTracking($trackingData);
        }

        $session = $request->getSession();

        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        /** @var SStoreEntity $store */
        $store = $this->routeManager->getStoreById($storeId);

        $p["email"] = $tracking->getEmail();
        $p["first_name"] = $tracking->getFirstName();
        $p["last_name"] = $tracking->getLastName();
        $p["store"] = $store;

        $this->accountManager->insertUpdateRemindMe($p, $product, $contact, $warehouse);

        /**
         * Save GDPR
         */
        $p["given_on_process"] = "remind_me";
        $this->accountManager->insertGdpr($p, $contact);

        if ($_ENV["SHAPE_TRACK"] ?? 0) {
            /**
             * Tracking event
             */
            if (empty($this->trackingManager)) {
                $this->trackingManager = $this->container->get("tracking_manager");
            }

            $eventName = ScommerceConstants::EVENT_NAME_ADDED;

            $this->trackingManager->insertTrackingEvent($request, $product->getId(), $product->getEntityType()->getEntityTypeCode(), ScommerceConstants::EVENT_TYPE_REMIND_ME, $eventName);
        }

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('You have successfully added') . " " . $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $product, "name") . " " . $this->translator->trans('to reminder list')));
    }

    /**
     * @Route("/api/favorite", name="favorite")
     * @Method("POST")
     */
    public function insertFavoriteAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        // Validate only if reponse is there. Ajax does not send it.
        if (isset($p["recaptcha_response"])) {
            /** @var GoogleCaptchaValidateManager $googleCaptchaValidateManager */
            $googleCaptchaValidateManager = $this->container->get("google_captcha_validate_manager");
            if ($googleCaptchaValidateManager->shouldValidateGoogleRecaptchaV3()) {
                if (empty($p["recaptcha_response"])) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Captcha response is empty.")));
                }

                if (!$googleCaptchaValidateManager->validateGoogleRecaptchaV3($p["recaptcha_response"])) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error validating captcha")));
                }
            }
        }

        if (!isset($p["session_id"]) || empty($p["session_id"])) {
            $p["session_id"] = $request->getSession()->getId();
        }

        if (!isset($p["product_id"]) || empty($p["product_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Item is missing')));
        }
        if (!isset($p["is_favorite"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('is_favorite is missing')));
        }
        if (!isset($p["first_name"])) {
            $p["first_name"] = null;
        }
        if (!isset($p["last_name"])) {
            $p["last_name"] = null;
        }

        if (empty($this->productManager)) {
            $this->productManager = $this->container->get("product_manager");
        }

        /** @var ProductEntity $product */
        $product = $this->productManager->getProductById($p["product_id"]);

        if (empty($product)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Item does no exist')));
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->getContainer()->get("account_manager");
        }

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->getDefaultContact();

        /** @var TrackingEntity $tracking */
        $tracking = $this->accountManager->getTracking($p["session_id"]);
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
            $trackingData["session_id"] = $request->getSession()->getId();

            /** @var TrackingEntity $tracking */
            $tracking = $this->accountManager->insertUpdateTracking($trackingData);
        }

        $p["email"] = $tracking->getEmail();
        $p["first_name"] = $tracking->getFirstName();
        $p["last_name"] = $tracking->getLastName();

        $this->accountManager->insertUpdateFavorite($p, $product, $contact);

        if ($p["is_favorite"]) {
            /**
             * Save GDPR
             */
            $p["given_on_process"] = "favorite";
            $this->accountManager->insertGdpr($p, $contact);
        }

        if ($_ENV["SHAPE_TRACK"] ?? 0) {
            /**
             * Tracking event
             */
            if (empty($this->trackingManager)) {
                $this->trackingManager = $this->container->get("tracking_manager");
            }

            $eventName = ScommerceConstants::EVENT_NAME_REMOVED;
            if ($p["is_favorite"]) {
                $eventName = ScommerceConstants::EVENT_NAME_ADDED;
            }

            $this->trackingManager->insertTrackingEvent($request, $product->getId(), $product->getEntityType()->getEntityTypeCode(), ScommerceConstants::EVENT_TYPE_FAVORITE, $eventName);
        }

        $count = count($this->accountManager->getFavoritesByEmail($p["email"]));

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }
        $session = $request->getSession();

        $favorited = implode(",", $session->get('favorites_product_ids'));

        if ($p["is_favorite"]) {
            $ret = array(
                'error' => false,
                'message' => $this->translator->trans('You have successfully added') . " " . $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $product, "name") . " " . $this->translator->trans('to favourites.'),
                'count' => $count,
                'favorited' => $favorited
            );

            if (!empty($product)) {
                $ret["javascript"] = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Tracking:add_to_favorite.html.twig", $session->get("current_website_id")), array('product' => $product));
            }

            return new JsonResponse($ret);
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('You have successfully removed') . " " . $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $product, "name") . " " . $this->translator->trans('from favourites.'), 'count' => $count, 'favorited' => $favorited));
    }

    /**
     * @Route("/api/get_favorites_count", name="get_favorites_count")
     * @Method("POST")
     */
    public function getFavoritesCountAction(Request $request)
    {
        $this->initialize($request);

        $session = $request->getSession();

        if (empty($this->accountManager)) {
            $this->accountManager = $this->getContainer()->get("account_manager");
        }

        $sessionId = $session->getId();
        /** @var TrackingEntity $tracking */
        $tracking = $this->accountManager->getTracking($sessionId);

        $count = 0;
        $favorites = null;
        if (!empty($tracking)) {
            $favorites = $this->accountManager->getFavoritesByEmail($tracking->getEmail());
        } else {
            /** @var ContactEntity $contact */
            $contact = $this->accountManager->getDefaultContact();
            if (!empty($contact)) {
                $favorites = $this->accountManager->getFavoritesByEmail($contact->getEmail());
            }
        }

        if (EntityHelper::isCountable($favorites) && count($favorites)) {

            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
            }

            /** @var FavoriteEntity $favorite */
            foreach ($favorites as $favorite) {

                if ($this->crmProcessManager->isProductValid($favorite->getProduct())) {
                    $count++;
                }
            }
        }

        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Utilities:items_count.html.twig", $session->get("current_website_id")), array('count' => $count));

        return new Response($html);
    }

    /**
     * @Route("/api/get_compare_count", name="get_compare_count")
     * @Method("POST")
     */
    public function getCompareCountAction(Request $request)
    {
        $this->initialize($request);

        $session = $request->getSession();

        $compareProducts = $session->get('compare');

        if (empty($compareProducts)) {
            $compareProducts = [];
        }

        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Utilities:items_count.html.twig", $session->get("current_website_id")), array('count' => count($compareProducts)));

        return new Response($html);
    }

    /**
     * @Route("/api/newsletter_subscribe", name="newsletter_subscribe")
     * @Method("POST")
     */
    public function newsletterSubscribeAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $p = array_map('trim', $p);

        if (isset($p["im_not_a_human"]) && !empty($p["im_not_a_human"])) {
            $clientIp = $_SERVER["HTTP_X_REAL_IP"] ?? null;
            if (empty($clientIp)) {
                $clientIp = $request->getClientIp();
            }
            if (!empty($clientIp)) {
                if (empty($this->sCommerceHelperManager)) {
                    $this->sCommerceHelperManager = $this->getContainer()->get("scommerce_helper_manager");
                }

                $this->sCommerceHelperManager->addIpToBlockedIps($clientIp, 2, "bot newsletter_subscribe");
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Are you a bot?')));
            }
        }

        if (!isset($p["email"]) || empty($p["email"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Email is missing')));
        }
        if (!filter_var($p["email"], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid email")));
        }

        // Validate only if reponse is there. Ajax does not send it.
        if (isset($p["recaptcha_response"])) {
            /** @var GoogleCaptchaValidateManager $googleCaptchaValidateManager */
            $googleCaptchaValidateManager = $this->container->get("google_captcha_validate_manager");
            if ($googleCaptchaValidateManager->shouldValidateGoogleRecaptchaV3()) {
                if (empty($p["recaptcha_response"])) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Captcha response is empty.")));
                }

                if (!$googleCaptchaValidateManager->validateGoogleRecaptchaV3($p["recaptcha_response"])) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error validating captcha")));
                }
            }
        }

        if (empty($this->newsletterManager)) {
            $this->newsletterManager = $this->container->get("newsletter_manager");
        }

        $p["active"] = 1;
        $p["store"] = $request->getSession()->get("current_store_id");

        $ret = $this->newsletterManager->insertUpdateNewsletterSubscriber($p);

        if (!$ret) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Subscription failed")));
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->getContactByEmail($p["email"]);

        if (!empty($contact)) {
            $data = array();
            $data["newsletter_signup"] = $p["active"];

            $this->accountManager->updateContact($contact, $data);
        }

        $message = $this->translator->trans("You have successfully subscribed to our newsletter");
        if (isset($ret["is_new"]) && !$ret["is_new"]) {
            $message = $this->translator->trans("You are already subscribed to our newsletter");
        }

        $session = $request->getSession();

        $ret = [];
        $ret["error"] = false;
        $ret["message"] = $message;
        $ret["javascript"] = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Tracking:newsletter_subscribed.html.twig", $session->get("current_website_id")), array('contact' => $contact));

        $response = new JsonResponse($ret);
        $response->headers->setCookie(new Cookie('newsletter_subscribed', 1));
        return $response;
    }

    /**
     * @Route("/api/newsletter_unsubscribe", name="newsletter_unsubscribe")
     * @Method("GET")
     */
    public function newsletterUnsubscribeAction(Request $request)
    {
        $this->initialize($request);

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->getContainer()->get("get_page_url_extension");
        }

        $baseUrl = "/" . $this->getPageUrlExtension->getPageUrl($_ENV["DEFAULT_STORE_ID"], 146, "s_page");

        if (!isset($_GET["uid"]) || empty($_GET["uid"])) {
            return $this->redirect($baseUrl . "?success=0", 301);
        }

        $uid = $_GET["uid"];

        if (empty($this->newsletterManager)) {
            $this->newsletterManager = $this->container->get("newsletter_manager");
        }

        /** @var NewsletterEntity $newsletterSubscriber */
        $newsletterSubscriber = $this->newsletterManager->getNewsletterByUid($uid);

        if (empty($newsletterSubscriber)) {
            return $this->redirect($baseUrl . "?success=1", 301);
        }

        $data = array();
        $data["email"] = $newsletterSubscriber->getEmail();
        $data["active"] = 0;

        $this->newsletterManager->insertUpdateNewsletterSubscriber($data);

        if (empty($this->accountManager)) {
            $this->accountManager = $this->getContainer()->get("account_manager");
        }

        if (!empty($newsletterSubscriber->getContact())) {
            $data = array();
            $data["newsletter_signup"] = 0;

            $this->accountManager->updateContact($newsletterSubscriber->getContact(), $data);
        }

        return $this->redirect($baseUrl . "?success=1", 301);
    }

    /**
     * @Route("/api/general_contact_form", name="general_contact_form")
     * @Method("POST")
     */
    public function insertGeneralContactAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $p = array_map('trim', $p);

        $timeNow = time();
        $p["question_received"] = (new \DateTime(date("Y-m-d H:i:s", $timeNow)));

        if (isset($p["im_not_a_human"]) && !empty($p["im_not_a_human"])) {
            $clientIp = $_SERVER["HTTP_X_REAL_IP"] ?? null;
            if (empty($clientIp)) {
                $clientIp = $request->getClientIp();
            }
            if (!empty($clientIp)) {
                if (empty($this->sCommerceHelperManager)) {
                    $this->sCommerceHelperManager = $this->getContainer()->get("scommerce_helper_manager");
                }

                $this->sCommerceHelperManager->addIpToBlockedIps($clientIp, 2, "bot general_contact_form");
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Are you a bot?')));
            }
        }

        /** @var GoogleCaptchaValidateManager $googleCaptchaValidateManager */
        $googleCaptchaValidateManager = $this->getContainer()->get("google_captcha_validate_manager");
        if ($googleCaptchaValidateManager->shouldValidateGoogleRecaptchaV3()) {
            if (!isset($p["recaptcha_response"]) || empty($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error validating captcha. Missing captcha response.")));
            }

            if (!$googleCaptchaValidateManager->validateGoogleRecaptchaV3($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error validating captcha")));
            }
        }

        if (!isset($p["session_id"]) || empty($p["session_id"])) {
            $p["session_id"] = $request->getSession()->getId();
        }
        if (!isset($p["email"]) || empty($p["email"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Email is missing')));
        }
        if (!filter_var($p["email"], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid email")));
        }
        if (!isset($p["first_name"]) || empty($p["first_name"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('First name is missing')));
        }
        if (!isset($p["last_name"]) || empty($p["last_name"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Last name is missing')));
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->getContainer()->get("account_manager");
        }

        /** @var ContactEntity $contact */
        $p["contact"] = $contact = $this->accountManager->getDefaultContact();
        $p["closed"] = 0;

        /** @var TrackingEntity $tracking */
        $tracking = $this->accountManager->getTracking($p["session_id"]);
        if (empty($tracking)) {
            if (!empty($contact)) {
                $p["email"] = $contact->getEmail();
                $p["first_name"] = $contact->getFirstName();
                $p["last_name"] = $contact->getLastName();
            }

            if (!isset($p["email"]) || empty($p["email"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Email is missing')));
            }
            if (empty($contact)) {
                if (!filter_var($p["email"], FILTER_VALIDATE_EMAIL)) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid email")));
                }
            }
            if (!isset($p["first_name"]) || empty($p["first_name"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('First name is missing')));
            }
            if (!isset($p["last_name"]) || empty($p["last_name"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Last name is missing')));
            }

            /**
             * Save tracking
             */
            $trackingData = array();
            $trackingData["email"] = $p["email"];
            $trackingData["first_name"] = $p["first_name"];
            $trackingData["last_name"] = $p["last_name"];
            $trackingData["contact"] = $contact;
            $trackingData["session_id"] = $request->getSession()->getId();

            /** @var TrackingEntity $tracking */
            $tracking = $this->accountManager->insertUpdateTracking($trackingData);
        }

        /*$p["email"] = $tracking->getEmail();
        $p["first_name"] = $tracking->getFirstName();
        $p["last_name"] = $tracking->getLastName();*/
        /** @var ContactEntity $contact */
        //$contact = $tracking->getContact();

        if ((!isset($p["message"]) || empty($p["message"])) && (!isset($_ENV["ALLOW_EMPTY_CONTACT_MESSAGE"]) || $_ENV["ALLOW_EMPTY_CONTACT_MESSAGE"] != 1)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Message is missing')));
        }
        if (!isset($p["phone"])) {
            $p["phone"] = null;
        }

        $product = null;
        if (isset($p["product_id"]) && !empty($p["product_id"])) {
            if (empty($this->productManager)) {
                $this->productManager = $this->container->get("product_manager");
            }

            /** @var ProductEntity $product */
            $p["product"] = $product = $this->productManager->getProductById($p["product_id"]);

            if (empty($product)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Item does no exist')));
            }

            if ($_ENV["SHAPE_TRACK"] ?? 0) {
                /**
                 * Tracking event
                 */
                if (empty($this->trackingManager)) {
                    $this->trackingManager = $this->container->get("tracking_manager");
                }

                $this->trackingManager->insertTrackingEvent($request, $product->getId(), $product->getEntityType()->getEntityTypeCode(), ScommerceConstants::EVENT_TYPE_GENERAL_QUESTION, ScommerceConstants::EVENT_NAME_SENT);
            }
        }

        /** @var GeneralQuestionEntity $generalQuestion */
        $generalQuestion = $this->accountManager->insertUpdateGeneralQuestion($p);
        if (empty($generalQuestion)) {

            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $this->errorLogManager->logErrorEvent("SEND EMAIL ERROR: Error general question: " . json_encode($p), true);
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('There has been an error. Please try again.')));
        }

        if (empty($this->mailManager)) {
            $this->mailManager = $this->container->get("mail_manager");
        }

        $bcc = $replyto = array('email' => $generalQuestion->getEmail(), 'name' => $generalQuestion->getEmail());

        /** @var EmailTemplateManager $emailTemplateManager */
        $emailTemplateManager = $this->container->get('email_template_manager');
        /** @var EmailTemplateEntity $template */
        $template = $emailTemplateManager->getEmailTemplateByCode("general_question");
        if (!empty($template)) {
            $templateData = $emailTemplateManager->renderEmailTemplate($generalQuestion, $template);

            $templateAttachments = $template->getAttachments();
            if (!empty($templateAttachments)) {
                $attachments = $template->getPreparedAttachments();
            }

            $this->mailManager->sendEmail(
                array('email' => $_ENV["GENERAL_CONTACT_EMAIL_RECIPIENT"], 'name' => $_ENV["GENERAL_CONTACT_EMAIL_RECIPIENT"]),
                null,
                $bcc,
                $replyto,
                $templateData["subject"],
                "",
                null,
                [],
                $templateData["content"],
                $attachments ?? [],
                $request->getSession()->get("current_store_id")
            );
        } else {
            $this->mailManager->sendEmail(array('email' => $_ENV["GENERAL_CONTACT_EMAIL_RECIPIENT"], 'name' => $_ENV["GENERAL_CONTACT_EMAIL_RECIPIENT"]), null, $bcc, $replyto, $this->translator->trans('General question'), "", "general_question", array("quote" => $p, "product" => $product), null, array(), $request->getSession()->get("current_store_id"));
        }

        $session = $request->getSession();

        $ret = [
            'error' => false,
            'message' => $this->translator->trans('Your message has been successfully submitted'),
        ];

        $ret["javascript"] = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Tracking:inquiry.html.twig", $session->get("current_website_id")), array(
            'data' => $p
        ));

        return new JsonResponse($ret);
    }

}
