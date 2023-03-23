<?php

namespace ScommerceBusinessBundle\Controller;

use AppBundle\Entity\CoreUserEntity;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\ApplicationSettingsManager;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ErrorLogManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\CountryEntity;
use CrmBusinessBundle\Entity\DeliveryTypeEntity;
use CrmBusinessBundle\Entity\DiscountCouponEntity;
use CrmBusinessBundle\Entity\LoyaltyCardEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\DiscountCouponManager;
use CrmBusinessBundle\Managers\OrderManager;
use CrmBusinessBundle\Managers\ProductManager;
use CrmBusinessBundle\Managers\QuoteManager;
use HrBusinessBundle\Entity\CityEntity;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use ScommerceBusinessBundle\Constants\ScommerceConstants;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ScommerceBusinessBundle\FrontBlocks\CartBlock;
use ScommerceBusinessBundle\FrontBlocks\CartGiftsBlock;
use ScommerceBusinessBundle\Managers\DefaultScommerceManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Managers\TrackingManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class CartController extends AbstractScommerceController
{
    /** @var ProductManager $productManager */
    protected $productManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var DefaultScommerceManager $defaultScommerceManager */
    protected $defaultScommerceManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var DiscountCouponManager $discountCouponManager */
    protected $discountCouponManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var TrackingManager $trackingManager */
    protected $trackingManager;
    /** @var ApplicationSettingsManager $applicationSettingsManager */
    protected $applicationSettingsManager;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->productManager = $this->container->get("product_manager");
        $this->quoteManager = $this->container->get("quote_manager");
        $this->entityManager = $this->container->get("entity_manager");
        $this->initializeTwigVariables($request);
    }

    /**
     * @Route("/cart/get_mini_cart", name="get_mini_cart")
     * @Method("POST")
     */
    public function getMiniCartAction(Request $request)
    {
        $this->initialize($request);

        $session = $request->getSession();

        $html = null;

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);

        $additionalData = [];
        if (!empty($quote)) {
            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $additionalData = $this->crmProcessManager->getAdditionalQuoteData($quote);
        }
        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:minicart.html.twig", $session->get("current_website_id")), array('quote' => $quote, 'additional_data' => $additionalData));

        return new Response($html);
    }

    /**
     * @Route("/cart/get_mini_cart_count", name="get_mini_cart_count")
     * @Method("POST")
     */
    public function getMiniCartCountAction(Request $request)
    {
        $this->initialize($request);

        $session = $request->getSession();

        $html = null;

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);

        $count = 0;
        if (!empty($quote)) {
            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }
            $count = $this->crmProcessManager->getMinicartQuantity($quote);
        }
        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Utilities:items_count.html.twig", $session->get("current_website_id")), array('count' => $count));

        return new Response($html);
    }

    /**
     * @Route("/cart/get_mini_cart_total", name="get_mini_cart_total")
     * @Method("POST")
     */
    public function getMiniCartTotalAction(Request $request)
    {
        $this->initialize($request);

        $session = $request->getSession();

        $html = null;

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);
        if (!empty($quote)) {
            $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Utilities:price.html.twig", $session->get("current_website_id")), array('price' => $quote->getPriceItemsTotal(), 'currency' => $quote->getCurrency()->getSign()));
        }

        return new Response($html);
    }

    /**
     * @Route("/cart/get_cart_totals", name="get_cart_totals")
     * @Method("POST")
     */
    public function getCartTotalsAction(Request $request)
    {
        $this->initialize($request);

        $session = $request->getSession();

        $html = null;

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);
        if (!empty($quote)) {
            $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:cart_totals.html.twig", $session->get("current_website_id")), array('data' => ["model" => ["quote" => $quote]]));
            return new JsonResponse(array('error' => false, 'html' => $html));
        }
        return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error loading cart totals")));
    }

    /**
     * @Route("/cart/cart_confirm", name="cart_confirm")
     * @Method("POST")
     */
    public function cartConfirmAction(Request $request)
    {
        $this->initialize($request);

        $session = $request->getSession();

        $p = $_POST;

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);

        if (isset($p["quote_id"]) && !empty($p["quote_id"])) {
            /** @var QuoteEntity $quote */
            $quote = $this->quoteManager->getQuoteById($p["quote_id"]);
        }

        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $ret = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $ret["quoteUrl"];
        $quoteErrorUrl = $ret["quoteErrorUrl"];
        $quoteSuccessUrl = $ret["quoteSuccessUrl"];

        $ret = $this->crmProcessManager->validateQuote($quote);

        if ($ret["error"]) {
            return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("There has been an error. Please try again!"), 'redirect_url' => $ret["redirect_url"]));
        } elseif ($ret["changed"]) {
            return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Your cart items have been changed!"), 'redirect_url' => $ret["redirect_url"]));
        } elseif (isset($ret["redirect_url"]) && !empty($ret["redirect_url"])) {
            return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Your cart items have been changed!"), 'redirect_url' => $ret["redirect_url"]));
        }

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        $data = array();
        $data["is_locked"] = 1;
        $data["store"] = $this->routeManager->getStoreById($session->get("current_store_id"));
        $this->quoteManager->updateQuote($quote, $data);

        if ((!isset($_ENV["CREATE_ORDER_BEFORE_CARD_PAYMENT"]) || $_ENV["CREATE_ORDER_BEFORE_CARD_PAYMENT"] != 1) && ($quote->getPaymentTypeId() == CrmConstants::PAYMENT_TYPE_CARD || $quote->getPaymentTypeId() == CrmConstants::PAYMENT_TYPE_PAYPAL || $quote->getPaymentTypeId() == CrmConstants::PAYMENT_TYPE_LEANPAY)) {
            return new JsonResponse(array('error' => false));
        }

        $quoteStatus = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_ACCEPTED);

        $this->quoteManager->changeQuoteStatus($quote, $quoteStatus);

        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderByQuoteId($quote->getId());
        if (empty($order)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("There has been an error. Please try again!"), 'redirect_url' => $quoteErrorUrl));
        }

        $session = $request->getSession();

        $session->set("order_id", $order->getId());

        $ret = array(
            'error' => false,
            'message' => $this->translator->trans("Order created"),
            'redirect_url' => $quoteSuccessUrl
        );

        if ((isset($_ENV["CREATE_ORDER_BEFORE_CARD_PAYMENT"]) && $_ENV["CREATE_ORDER_BEFORE_CARD_PAYMENT"] == 1) && ($quote->getPaymentTypeId() == CrmConstants::PAYMENT_TYPE_CARD || $quote->getPaymentTypeId() == CrmConstants::PAYMENT_TYPE_PAYPAL)) {
            $paymentProvider = $this->container->get($quote->getPaymentType()->getProvider());
            $providerData = $paymentProvider->renderTemplateFromQuote($quote);

            $ret["forms"] = $providerData["forms"];
            $ret["buttons"] = $providerData["buttons"];
        }

        $ret["javascript"] = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Tracking:cart_confirm.html.twig", $session->get("current_website_id")), array(
            'quote' => $quote
        ));

        return new JsonResponse($ret);
    }

    /**
     * @Route("/cart/update_cart_customer_marketing_data", name="update_cart_customer_marketing_data")
     * @Method("POST")
     */
    public function updateCartCustomerMarketingDataAction(Request $request)
    {

        $this->initialize($request);
        $session = $request->getSession();

        $p = $_POST;

        $p = array_map('trim', $p);

        /*if (empty($this->googleCaptchaValidateManager)) {
            $this->googleCaptchaValidateManager = $this->container->get("google_captcha_validate_manager");
        }
        if ($this->googleCaptchaValidateManager->shouldValidateGoogleRecaptchaV3()) {
            if (!isset($p["recaptcha_response"]) || empty($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true));
            }

            if (!$this->googleCaptchaValidateManager->validateGoogleRecaptchaV3($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true));
            }
        }*/

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true));
        }

        if (!empty($quote->getAccountEmail())) {
            return new JsonResponse(array('error' => false));
        }

        if (!isset($p["email"]) || empty($p["email"])) {
            return new JsonResponse(array('error' => true));
        }
        if (!filter_var($p["email"], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(array('error' => true));
        }

        $quoteData = array();
        if (isset($p["phone"]) && !empty($p["phone"]) && empty($quote->getAccountPhone())) {
            $quoteData["account_phone"] = $p["phone"];
        }
        $quoteData["account_email"] = $p["email"];

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        $quoteData["store"] = $this->routeManager->getStoreById($session->get("current_store_id"));

        $this->quoteManager->updateQuote($quote, $quoteData);

        return new JsonResponse(array('error' => false));
    }

    /**
     * @Route("/cart/update_cart_customer_data", name="update_cart_customer_data")
     * @Method("POST")
     */
    public function updateCartCustomerDataAction(Request $request)
    {
        $this->initialize($request);
        $session = $request->getSession();

        $p = $_POST;

        $createUser = false;

        $p = array_map('trim', $p);

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        if (!isset($p["is_legal_entity"]) || empty($p["is_legal_entity"])) {
            $p["is_legal_entity"] = 0;
        }
        if (!isset($p["email"]) || empty($p["email"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing email")));
        }
        if (!filter_var($p["email"], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid email")));
        }
        if ((isset($p["create_account"]) && !empty($p["create_account"])) && (isset($p["password"]) && !empty($p["password"]))) {
            if (!isset($p["password"]) || empty($p["password"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing password")));
            }
            if (!isset($p["repeat_password"]) || empty($p["repeat_password"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing repeat password")));
            }
            if ($p["password"] != $p["repeat_password"]) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Passwords do not match")));
            }
            if (strlen($p["password"]) < 6 || strlen($p["repeat_password"]) < 6) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Minimal password length is 6 characters")));
            }
            $createUser = true;
        }
        if (!isset($p["last_name"]) || empty($p["last_name"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing last name")));
        }
        if (!isset($p["first_name"]) || empty($p["first_name"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing first name")));
        }
        if (!isset($p["country_id"]) || empty($p["country_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing country id")));
        }
        if (!isset($p["city_id"]) || empty($p["city_id"])) {
            if (!isset($p["city_name"]) || empty($p["city_name"]) || !isset($p["postal_code"]) || empty($p["postal_code"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing city")));
            }
        }
        if (!isset($p["street"]) || empty($p["street"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing street")));
        }
        if (!isset($p["phone"]) || empty($p["phone"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing phone")));
        }
        if (!isset($p["newsletter_signup"])) {
            $p["newsletter_signup"] = 0;
        }

        if (isset($p["birth_day"]) && $p["birth_day"] == "Dan") {
            $p["birth_day"] = null;
        }
        if (isset($p["birth_month"]) && $p["birth_month"] == "Mjesec") {
            $p["birth_month"] = null;
        }
        if (isset($p["birth_year"]) && $p["birth_year"] == "Godina") {
            $p["birth_year"] = null;
        }

        if (!isset($p["birth_day"]) || empty($p["birth_day"]) || !isset($p["birth_month"]) || empty($p["birth_month"]) || !isset($p["birth_year"]) || empty($p["birth_year"])) {
            $p["date_of_birth"] = null;
        } else {
            $p["birth_day"] = str_pad($p["birth_day"], 2, "0", STR_PAD_LEFT);
            $p["birth_month"] = str_pad($p["birth_month"], 2, "0", STR_PAD_LEFT);

            $p["date_of_birth"] = new \DateTime($p["birth_year"] . "-" . $p["birth_month"] . "-" . $p["birth_day"]);
        }

        if ($p["is_legal_entity"] || (isset($_ENV["FORCE_R1"]) && $_ENV["FORCE_R1"] == 1)) {
            if (!isset($p["name"]) || empty($p["name"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing company name")));
            }
            if (!isset($p["oib"]) || empty($p["oib"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing company vat number")));
            }
        }
        $p["is_active"] = 1;

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        if (isset($p["sex"]) && !empty($p["sex"])) {
            $p["sex"] = $this->accountManager->getSexById($p["sex"]);
        }

        if ($createUser) {
            $coreUser = $this->accountManager->getCoreUserByEmail($p["email"]);
            if (!empty($coreUser)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('This email is already used, please reset your password if necessary'), 'open_login_modal' => true));
            }
        }

        /** @var AccountEntity $account */
        $account = null;
        /** @var AddressEntity $billingAddress */
        $billingAddress = null;

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->getContactByEmail($p["email"]);

        $trackingData = array();
        $trackingData["email"] = $p["email"];
        $trackingData["first_name"] = $p["first_name"];
        $trackingData["last_name"] = $p["last_name"];
        $trackingData["contact"] = $contact;
        $trackingData["session_id"] = $request->getSession()->getId();

        $this->accountManager->insertUpdateTracking($trackingData);

        $p["last_contact_date"] = new \DateTime();

        $quoteData = array();

        if (isset($p["country_id"])) {
            /** @var CountryEntity $country */
            $country = $this->accountManager->getCountryById($p["country_id"]);
        } elseif (isset($p["country_name"])) {
            /** @var CountryEntity $country */
            $country = $this->accountManager->getCountryByName($p["country_name"]);
        } else {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error creating country, please try again')));
        }

        if (empty($country)) {
            $countryData = array();
            $countryData["name"] = $p["country_name"];

            /** @var CountryEntity $country */
            $country = $this->accountManager->insertCountry($countryData);
        }

        /**
         * Prepare country and city data
         */
        if (isset($p["city_id"])) {
            /** @var CityEntity $city */
            $city = $this->accountManager->getCityById($p["city_id"]);
        } elseif (isset($p["postal_code"]) && isset($p["city_name"])) {
            /** @var CityEntity $city */
            $city = $this->accountManager->getCityByPostalCodeAndCountry($p["postal_code"], $country);
        } else {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error creating city, please try again')));
        }

        /**
         * Create/get city and country
         */
        if (empty($city)) {
            $cityData = array();
            $cityData["name"] = $p["city_name"];
            $cityData["postal_code"] = $p["postal_code"];
            $cityData["country"] = $country;

            /** @var CityEntity $city */
            $city = $this->accountManager->insertCity($cityData);
        }

        if (!empty($contact)) {

            /**
             * Check if contact is the same as email
             */
            /*if ($contact->getFirstName() != $p["first_name"]) {
                $this->logger->error("CHECKOUT USER: same email differet name " . $quote->getId());
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("This email is already used, please reset your password if necessary")));
            }*/

            $account = $contact->getAccount();

            if ($p["is_legal_entity"]) {
                if ($account->getIsLegalEntity()) {
                    if ($account->getOib() != $p["oib"]) {
                        $this->logger->error("CHECKOUT USER: same email oib " . $quote->getId());
                        return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("This email is already used, please reset your password if necessary")));
                    }
                }
                /**
                 * Ipak ne zelimo da naknadno postane legal entity
                 */
                /*else{

                    $accountData["oib"] = $p["oib"];
                    $accountData["name"] = $p["name"];
                    $accountData["is_legal_entity"] = $p["is_legal_entity"];

                    $this->accountManager->updateAccount($account, $accountData);
                }*/
            }

            $addresses = $account->getAddresses();
            $found = false;
            if (EntityHelper::isCountable($addresses) && count($addresses)) {
                /** @var AddressEntity $address */
                foreach ($addresses as $address) {
                    if ($address->getStreet() == $p["street"] && $address->getCityId() == $city->getId()) {
                        $found = true;
                        $billingAddress = $address;
                        break;
                    }
                }
            }

            if (!$found) {
                $addressData = array();
                $addressData["city"] = $city;
                $addressData["account"] = $account;
                $addressData["street"] = $p["street"];
                $addressData["headquarters"] = 1;
                $addressData["billing"] = 1;

                /** @var AddressEntity $billingAddress */
                $billingAddress = $this->accountManager->insertAddress("address", $addressData);
                if (empty($billingAddress)) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error creating address, please try again')));
                }
            }

            /**
             * Update contact data
             */
            $contactData = $p;
            if (isset($contactData["password"])) {
                unset($contactData["password"]);
            }

            /**
             * Ne zelimo da se reganim userima mijenja ime
             */
            if (!empty($contact->getCoreUser())) {
                unset($contactData["first_name"]);
                unset($contactData["last_name"]);
            }

            $this->accountManager->insertContact($contactData);
        } /**
         * Create new contact and account
         */
        else {
            $p["lead_source"] = $this->accountManager->getLeadSourceById(CrmConstants::ONLIE_STORE);
            $p["lead_status"] = $this->accountManager->getLeadStatusById(CrmConstants::LEAD_STATUS_ONLINE_PRE_SALES);

            $accountData = $p;
            unset($accountData["first_name"]);
            unset($accountData["last_name"]);

            /** @var AccountEntity $account */
            $account = $this->accountManager->insertAccount("lead", $p);
            if (empty($account)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error creating account, please try again')));
            }

            $p["account"] = $account;

            $contactData = $p;
            if (isset($contactData["password"])) {
                unset($contactData["password"]);
            }

            /** @var ContactEntity $contact */
            $contact = $this->accountManager->insertContact($contactData);
            if (empty($contact)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error creating contact, please try again')));
            }

            $addressData = array();
            $addressData["city"] = $city;
            $addressData["account"] = $account;
            $addressData["street"] = $p["street"];

            /** @var AddressEntity $billingAddress */
            $billingAddress = $this->accountManager->insertAddress("address", $addressData);
            if (empty($billingAddress)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error creating address, please try again')));
            }
        }

        $trackingData["contact"] = $contact;
        $this->accountManager->insertUpdateTracking($trackingData);

        if ($createUser) {
            $ret = $this->accountManager->createUser($p);
            if ($ret["error"]) {
                return new JsonResponse(array('error' => true, 'message' => $ret["message"]));
            }

            $contact->setCoreUser($ret["core_user"]);
            $this->accountManager->save($contact);

            $this->helperManager->loginAnonymus($request, $p["email"], $p["password"]);

            if (empty($this->defaultScommerceManager)) {
                $this->defaultScommerceManager = $this->container->get("scommerce_manager");
            }

            $sessionData["account"] = $account;
            $sessionData["contact"] = $contact;

            $this->defaultScommerceManager->updateSessionData($sessionData);
        }

        $quoteData["account_name"] = $contact->getFullName();
        $quoteData["account_oib"] = null;
        if ($p["is_legal_entity"]) {
            $quoteData["account_name"] = $p["name"];
            $quoteData["account_oib"] = $p["oib"];
        }
        $quoteData["name"] = $account->getName();
        $quoteData["account"] = $contact->getAccount();
        $quoteData["contact"] = $contact;
        $quoteData["account_phone"] = $contact->getPhone();
        $quoteData["account_email"] = $contact->getEmail();
        $quoteData["account_billing_address"] = $billingAddress;
        $quoteData["account_billing_city"] = $billingAddress->getCity();
        $quoteData["account_billing_street"] = $billingAddress->getStreet();

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        $quoteData["store"] = $this->routeManager->getStoreById($session->get("current_store_id"));

        $this->quoteManager->updateQuote($quote, $quoteData);

        $p["given_on_process"] = "cart";
        $this->accountManager->insertGdpr($p, $contact);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Quote updated')));
    }

    /**
     * @Route("/cart/update_cart_legal_data", name="update_cart_legal_data")
     * @Method("POST")
     */
    public function updateCartLegalDataAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $p = array_map('trim', $p);

        /*if (empty($this->googleCaptchaValidateManager)) {
            $this->googleCaptchaValidateManager = $this->container->get("google_captcha_validate_manager");
        }
        if ($this->googleCaptchaValidateManager->shouldValidateGoogleRecaptchaV3()) {
            if (!isset($p["recaptcha_response"]) || empty($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error validating captcha. Missing captcha response.")));
            }

            if (!$this->googleCaptchaValidateManager->validateGoogleRecaptchaV3($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error validating captcha")));
            }
        }*/

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(true);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        /**
         * Ako je korisnik logiran i ako je pravna osoba, nemoj nista mijenjati
         */
        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();
        if(!empty($user) && !isset($p["is_legal_entity"])){
            return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Quote updated')));
        }

        if (!isset($p["is_legal_entity"])) {
            $p["is_legal_entity"] = 0;
        }

        if ($p["is_legal_entity"]) {
            if (!isset($p["name"]) || empty($p["name"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing company name")));
            }
            if (!isset($p["oib"]) || empty($p["oib"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing company vat number")));
            }
        } else {
            if (empty($user)) {
                if(empty($this->errorLogManager)){
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }
                $this->errorLogManager->logErrorEvent("update_cart_legal_data","Missing user and is_legal_entity flag",true);
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('User does not exist')));
            }

            /** @var ContactEntity $contact */
            $contact = $user->getDefaultContact();

            $p["name"] = $contact->getFullName();
            $p["oib"] = null;
        }

        $quoteData = array();
        $quoteData["account_name"] = $p["name"];
        $quoteData["account_oib"] = $p["oib"];

        $this->quoteManager->updateQuote($quote, $quoteData);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Quote updated')));
    }

    /**
     * @Route("/cart/update_cart_delivery_address", name="update_cart_delivery_address")
     * @Method("POST")
     */
    public function updateCartDeliveryAddressAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $p = array_map('trim', $p);

        if (!isset($p["shipping_address_same"])) {
            $p["shipping_address_same"] = 1;
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        $shippingAddress = null;

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        /** @var AccountEntity $account */
        $account = $quote->getAccount();

        if ($p["shipping_address_same"] == 1) {
            $shippingAddress = $quote->getAccountBillingAddress();
        } else {
            if (isset($p["shipping_address_id"]) && !empty($p["shipping_address_id"])) {
                /** @var AddressEntity $shippingAddress */
                $shippingAddress = $this->accountManager->getAddressById($p["shipping_address_id"]);

                if (empty($shippingAddress)) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Address does not exist")));
                } elseif ($shippingAddress->getAccountId() != $quote->getAccountId()) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Address not correct")));
                }
            } else {
                if (((isset($p["shipping_city_id"]) && !empty($p["shipping_city_id"])) || (isset($p["shipping_city_name"]) && !empty($p["shipping_city_name"]) && isset($p["shipping_postal_code"]) && !empty($p["shipping_postal_code"]))) && isset($p["shipping_street"]) && !empty($p["shipping_street"]) && isset($p["shipping_country_id"]) && !empty($p["shipping_country_id"])) {
                    if (!isset($p["shipping_first_name"])) {
                        $p["shipping_first_name"] = null;
                    }
                    if (!isset($p["shipping_last_name"])) {
                        $p["shipping_last_name"] = null;
                    }
                    if (!isset($p["shipping_phone"])) {
                        $p["shipping_phone"] = null;
                    }

                    $country = null;

                    if (isset($p["shipping_country_id"])) {
                        /** @var CountryEntity $country */
                        $country = $this->accountManager->getCountryById($p["shipping_country_id"]);
                    } elseif (isset($p["shipping_country_name"])) {
                        /** @var CountryEntity $country */
                        $country = $this->accountManager->getCountryByName($p["shipping_country_name"]);
                    } else {
                        return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error creating country, please try again')));
                    }

                    if (empty($country)) {
                        $countryData = array();
                        $countryData["name"] = $p["shipping_country_name"];

                        /** @var CountryEntity $country */
                        $country = $this->accountManager->insertCountry($countryData);
                    }

                    $city = null;

                    /**
                     * Prepare country and city data
                     */
                    if (isset($p["shipping_city_id"])) {
                        /** @var CityEntity $city */
                        $city = $this->accountManager->getCityById($p["shipping_city_id"]);
                    } elseif (isset($p["shipping_postal_code"]) && isset($p["shipping_city_name"])) {
                        /** @var CityEntity $city */
                        $city = $this->accountManager->getCityByPostalCodeAndCountry($p["shipping_postal_code"], $country);
                    } else {
                        return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error creating city, please try again')));
                    }

                    /**
                     * Create/get city and country
                     */
                    if (empty($city)) {
                        $cityData = array();
                        $cityData["name"] = $p["shipping_city_name"];
                        $cityData["postal_code"] = $p["shipping_postal_code"];
                        $cityData["country"] = $country;

                        /** @var CityEntity $city */
                        $city = $this->accountManager->insertCity($cityData);
                    }

                    $addresses = $account->getAddresses();
                    $found = false;
                    if (EntityHelper::isCountable($addresses) && count($addresses)) {
                        /** @var AddressEntity $address */
                        foreach ($addresses as $address) {
                            if ($address->getStreet() == $p["shipping_street"] && $address->getCityId() == $city->getId()) {
                                $found = true;
                                $shippingAddress = $address;
                                break;
                            }
                        }
                    }

                    if (!$found) {
                        $addressData = array();
                        $addressData["city"] = $city;
                        $addressData["account"] = $account;
                        $addressData["street"] = $p["shipping_street"];
                        $addressData["first_name"] = $p["shipping_first_name"];
                        $addressData["last_name"] = $p["shipping_last_name"];
                        $addressData["phone"] = $p["shipping_phone"];

                        $shippingAddress = $this->accountManager->insertAddress("address", $addressData);
                    } else {

                        $addressData = array();
                        if ($shippingAddress->getFirstName() != $p["shipping_first_name"]) {
                            $addressData["first_name"] = $p["shipping_first_name"];
                        }
                        if ($shippingAddress->getLastName() != $p["shipping_last_name"]) {
                            $addressData["last_name"] = $p["shipping_last_name"];
                        }
                        if ($shippingAddress->getPhone() != $p["shipping_phone"]) {
                            $addressData["phone"] = $p["shipping_phone"];
                        }

                        if (!empty($addressData)) {
                            $shippingAddress = $this->accountManager->updateAddress($shippingAddress, $addressData);
                        }
                    }
                } else {
                    $shippingAddress = $account->getBillingAddress();
                }
            }
        }

        if (empty($shippingAddress)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Shipping address is missing")));
        }

        $data = array();
        $data["account_shipping_address"] = $shippingAddress;
        $data["account_shipping_city"] = $shippingAddress->getCity();
        $data["account_shipping_street"] = $shippingAddress->getStreet();

        $this->quoteManager->updateQuote($quote, $data);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Addresses updated")));
    }

    /**
     * @Route("/cart/validate_customer_email", name="validate_customer_email")
     * @Method("POST")
     */
    public function validateCustomerEmailAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $p = array_map('trim', $p);

        /*if (empty($this->googleCaptchaValidateManager)) {
            $this->googleCaptchaValidateManager = $this->container->get("google_captcha_validate_manager");
        }
        if ($this->googleCaptchaValidateManager->shouldValidateGoogleRecaptchaV3()) {
            if (!isset($p["recaptcha_response"]) || empty($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error validating captcha. Missing captcha response.")));
            }

            if (!$this->googleCaptchaValidateManager->validateGoogleRecaptchaV3($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error validating captcha")));
            }
        }*/

        if (!isset($p["email"]) || empty($p["email"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing email")));
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $coreUser = $this->accountManager->getCoreUserByEmail($p["email"]);
        if (!empty($coreUser)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('This email is already used, please reset your password if necessary'), 'open_login_modal' => true));
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Email is valid")));
    }

    /**
     * @Route("/cart/update_cart_payment_and_delivery", name="update_cart_payment_and_delivery")
     * @Method("POST")
     */
    public function updateCartPaymentAndDeliveryAction(Request $request)
    {
        if (isset($_ENV["ORDER_REQUEST_OFFER"]) && $_ENV["ORDER_REQUEST_OFFER"] == 1) {
            return new JsonResponse(array('error' => false));
        }

        $this->initialize($request);

        $p = $_POST;

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        if (!isset($p["delivery_type_id"]) || empty($p["delivery_type_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing delivery type")));
        }
        if (!isset($p["payment_type_id"]) || empty($p["payment_type_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing payment type")));
        }

        $quoteData = array();

        if ($p["delivery_type_id"] != $quote->getDeliveryTypeId()) {
            if (empty($p["delivery_type_id"])) {
                $quoteData["delivery_type"] = null;
            } else {
                $quoteData["delivery_type"] = $this->quoteManager->getDeliveryTypeById($p["delivery_type_id"]);
            }
        }

        if ($p["payment_type_id"] != $quote->getPaymentTypeId()) {
            if (empty($p["payment_type_id"])) {
                $quoteData["payment_type"] = null;
            } else {
                $quoteData["payment_type"] = $this->quoteManager->getPaymentTypeById($p["payment_type_id"]);
            }
        }

        if (!empty($quoteData)) {
            $this->quoteManager->updateQuote($quote, $quoteData);
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Payment and delivery updated")));
    }

    /**
     * @Route("/cart/update_cart_message", name="update_cart_message")
     * @Method("POST")
     */
    public function updateCartMessageAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        if (!isset($p["message"])) {
            $p["message"] = "";
        }
        if (!isset($p["delivery_message"])) {
            $p["delivery_message"] = "";
        }

        $quoteData = array();
        $quoteData["message"] = StringHelper::removeNonAsciiCharacters($p["message"]);
        $quoteData["delivery_message"] = StringHelper::removeNonAsciiCharacters($p["delivery_message"]);

        if ($quoteData["message"] != $quote->getMessage()) {
            $this->quoteManager->updateQuote($quote, $quoteData);
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Message updated")));
    }

    /**
     * @Route("/cart/generate_confirm_modal", name="generate_confirm_modal")
     * @Method("POST")
     */
    public function generateCartConfirmModalAction(Request $request)
    {
        $this->initialize($request);

        $session = $request->getSession();

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        $quote = $this->quoteManager->recalculateQuoteItems($quote);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $ret = $this->crmProcessManager->validateQuote($quote);

        if ($ret["error"]) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("There has been an error. Please try again!")));
        }
        elseif (isset($ret["redirect_url"]) && !empty($ret["redirect_url"])) {
            return new JsonResponse(array('redirect_url' => $ret["redirect_url"], 'error' => false, 'message' => $this->translator->trans("Your cart items have been changed!")));
        }
        elseif ($ret["changed"]) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Your cart items have been changed!")));
        }

        $data = [];
        $data['model']['account'] = $quote->getAccount();
        $data['model']['contact'] = $quote->getContact();
        $data['model']['quote'] = $quote;
        $data['model']['payment_data'] = $this->crmProcessManager->getQuoteButtons($quote);
        $data["model"]["additional_data"] = $this->crmProcessManager->getAdditionalQuoteData($quote);

        if(isset($data['model']['payment_data']["redirect_url"]) && !empty($data['model']['payment_data']["redirect_url"])){
            return new JsonResponse(array('redirect_url' => $data['model']['payment_data']["redirect_url"], 'error' => false));
        }

        if (!isset($data['model']['payment_data']["buttons"]) || empty($data['model']['payment_data']["buttons"])) {
            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }
            $this->errorLogManager->logErrorEvent("Missing payment button on checkout", "Missing payment button on checkout quote id: {$quote->getId()}", true);
            return new JsonResponse(array('error' => $this->translator->trans("Error"), 'message' => $this->translator->trans("Please check cart data")));
        }

        $validation = $this->crmProcessManager->validateQuotePaymentDelivery($quote);
        if ($validation["error"]) {
            return new JsonResponse(array('error' => $validation["error"], 'message' => $validation["message"]));
        }

        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:cart_confirm_modal.html.twig", $session->get("current_website_id")), [
            'data' => $data,
        ]);

        $ret = array(
            'error' => false,
            'html' => $html
        );

        if (!empty($quote)) {
            $ret["javascript"] = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Tracking:checkout_payment_info.html.twig", $session->get("current_website_id")), array('quote' => $quote));
        }

        return new JsonResponse($ret);
    }

    /**
     * @Route("/cart/add_to_cart", name="add_to_cart")
     * @Method("POST")
     */
    public function addToCartAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $message = null;
        $reload = false;
        $session = $request->getSession();

        /**
         * Tracking event
         */
        if (empty($this->trackingManager)) {
            $this->trackingManager = $this->container->get("tracking_manager");
        }

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(true);

        if ($quote->getIsLocked()) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Your cart is locked for payment.")));
        }

        if (isset($p["products"]) && !empty($p["products"])) {
            $error = false;
            $failedToAdd = [];
            foreach ($p["products"] as $data) {
                if (!isset($data["product_id"]) || empty($data["product_id"])) {
                    continue;
                }
                $pid = $data["product_id"];
                $qty = $data["qty"] ?? 1;

                /** @var ProductEntity $product */
                $product = $this->productManager->getProductById($pid);
                if (empty($product)) {
                    continue;
                }

                $ret = $this->quoteManager->addUpdateProductInQuote($product, $quote, $qty, true, $p);

                if ($ret["error"]) {
                    $error = true;
                    $failedToAdd[] = $product;
                }

                if ($_ENV["SHAPE_TRACK"] ?? 0) {
                    $this->trackingManager->insertTrackingEvent($request, $product->getId(), $product->getEntityType()->getEntityTypeCode(), ScommerceConstants::EVENT_TYPE_PRODUCT_TO_CART, ScommerceConstants::EVENT_NAME_ADDED);
                }
            }

            if ($error) {
                $message = $this->translator->trans("Failed to add products") . ":";
                foreach ($failedToAdd as $failedToAddProduct) {
                    $message = "<br>" . $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $failedToAddProduct, "name");
                }
            } else {
                $message = $this->translator->trans("Products added to cart");
            }
        } else {

            if (!isset($p["product_id"]) || empty($p["product_id"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing product id")));
            }
            if (!isset($p["qty"]) || empty($p["qty"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing qty")));
            }
            if (intval($p["qty"]) < 0) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Qty cannot be negative")));
            }

            /** @var ProductEntity $product */
            $product = $this->productManager->getProductById($p["product_id"]);
            if (empty($product)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product not found")));
            }

            $ret = $this->quoteManager->addUpdateProductInQuote($product, $quote, $p["qty"], true, $p);


            if (!$ret["error"]) {
                $message = $this->translator->trans("Product") . " " . $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $product, "name") . " " . $this->translator->trans("is added to your cart");
            }
            if (isset($ret["message"]) && !empty($ret["message"])) {
                if (!empty($message)) {
                    $message .= "<br>";
                }
                $message = $message . $ret["message"];
            }

            if (isset($ret["reload"]) && $ret["reload"]) {
                $reload = true;
            }

            if ($_ENV["SHAPE_TRACK"] ?? 0) {
                $this->trackingManager->insertTrackingEvent($request, $product->getId(), $product->getEntityType()->getEntityTypeCode(), ScommerceConstants::EVENT_TYPE_PRODUCT_TO_CART, ScommerceConstants::EVENT_NAME_ADDED);
            }
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        /**
         * Additional add to cart action for custom automatic cart actions
         */
        $this->crmProcessManager->additionalAddToCartAction($quote, $p, $ret);

        $minicart_num = $this->crmProcessManager->getMinicartQuantity($quote);

        $additionalData = $this->crmProcessManager->getAdditionalQuoteData($quote);

        $quote = $this->crmProcessManager->calculateQuoteDeliveryPrice($quote, true);

        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:minicart.html.twig", $session->get("current_website_id")), array('quote' => $quote, 'additional_data' => $additionalData));

        $priceHtml = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Utilities:price.html.twig", $session->get("current_website_id")), array('price' => $quote->getPriceTotal(), 'currency' => $quote->getCurrency()->getSign()));

        $ret = array(
            'quote_item' => $ret["quote_item"] ?? null,
            'quote_item_id' => !empty($ret["quote_item"]) ? $ret["quote_item"]->getId() : null,
            'error' => $ret["error"],
            'message' => $message,
            'minicart_html' => $html,
            'minicart_num' => $minicart_num,
            'total_price' => number_format($quote->getPriceTotal(), 2, ",", ""),
            'price_html' => $priceHtml,
            "reload" => $reload
        );

        if (isset($_ENV["AJAX_REFRESH_CART"]) && $_ENV["AJAX_REFRESH_CART"] == 1) {
            /** @var CartBlock $cartBlock */
            $cartBlock = $this->getContainer()->get("cart_front_block");
            $cartHtml = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:cart.html.twig", $session->get("current_website_id")), array(
                'data' => $cartBlock->GetBlockData(),
            ));
            $ret["cart_html"] = $cartHtml;

            /** @var CartGiftsBlock $giftsBlock */
            $giftsBlock = $this->getContainer()->get("cart_gifts_front_block");
            $giftsHtml = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:cart_gifts.html.twig", $session->get("current_website_id")), array(
                'data' => $giftsBlock->GetBlockData(),
            ));
            $ret["gifts_html"] = $giftsHtml;
        }

        if (!$ret["error"] && !empty($ret["quote_item"])) {
            $ret["javascript"] = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Tracking:add_to_cart.html.twig", $session->get("current_website_id")), array('quote_item' => $ret["quote_item"]));
        }

        $ret = $this->crmProcessManager->afterAddToCartAction($ret);

        return new JsonResponse($ret);
    }

    /**
     * @Route("/cart/update_cart", name="update_cart")
     * @Method("POST")
     */
    public function updateCartAction(Request $request)
    {
        $this->initialize($request);

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->getContainer()->get("quote_manager");
        }

        $session = $request->getSession();

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(true);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        if ($quote->getIsLocked()) {
            //todo unlock cart
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Your cart is locked for payment.")));
        }

        $p = $_POST;

        if (isset($p["data"]) && !empty($p["data"]) && is_array($p["data"])) {
            $updateItems = [];
            foreach ($p["data"] as $items) {
                $preparedItem = [];
                foreach ($items as $itemValue) {
                    if ($itemValue['name'] == 'quote_item_id') {
                        $preparedItem['quote_item_id'] = $itemValue['value'];
                    }
                    if ($itemValue['name'] == 'qty') {
                        if (intval($itemValue['value']) < 0) {
                            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Qty cannot be negative")));
                        }
                        $preparedItem['qty'] = $itemValue['value'];
                    }
                    $preparedItem['percentage_discount_fixed'] = 0;
                    if ($itemValue['name'] == 'percentage_discount_fixed') {
                        if (intval($itemValue['value']) < 0) {
                            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Percentage discount cannot be negative")));
                        }
                        $preparedItem['percentage_discount_fixed'] = $itemValue['value'];
                    }
                }
                if (!isset($preparedItem['quote_item_id'])) {
                    continue;
                    //return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing product id")));
                } elseif (!isset($preparedItem['qty'])) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing qty")));
                } else {
                    $updateItems[] = $preparedItem;
                }
            }

            $ret = array();
            $ret["message"] = null;
            $ret["error"] = false;

            if (!empty($updateItems)) {
                foreach ($updateItems as $updateItem) {

                    /** @var QuoteItemEntity $quoteItem */
                    $quoteItem = $this->quoteManager->getQuoteItemById($updateItem["quote_item_id"]);
                    if (empty($quoteItem)) {
                        return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product not found")));
                    }
                    $quoteItem->setPercentageDiscountFixed($updateItem["percentage_discount_fixed"]);

                    /**
                     * Koristi se za dohvat opcija custom vrsta proizvoda
                     */
                    $options = $this->quoteManager->prepareOptionsForQuoteItem($quoteItem);
                    //todo ovdje treba napraviti update opcija kod konfigurabilnog bundle

                    /**
                     * Kreira optionse kako bi mogli updateati bundle
                     */
                    if($quoteItem->getIsPartOfBundle()){

                        $options["bundle"] = Array();
                        $options["bundle"][] = $quoteItem->getProductId();

                        $childItems = $quoteItem->getChildItems();
                        if(EntityHelper::isCountable($childItems) && count($childItems)){
                            foreach ($childItems as $childItem){
                                $options["bundle"][] = $childItem->getProductId();
                            }
                        }
                        $options["bundle"] = json_encode($options["bundle"]);
                    }
                    /**
                     * End kreira optionse
                     */
                    $tmpRet = $this->quoteManager->addUpdateProductInQuote($quoteItem->getProduct(), $quote, $updateItem["qty"], false, $options);
                    if (isset($tmpRet["message"])) {
                        $ret["message"] .= $tmpRet["message"];
                    }

                    if ($_ENV["SHAPE_TRACK"] ?? 0) {
                        /**
                         * Tracking event
                         */
                        if (empty($this->trackingManager)) {
                            $this->trackingManager = $this->container->get("tracking_manager");
                        }

                        $this->trackingManager->insertTrackingEvent($request, $quoteItem->getProduct()->getId(), $quoteItem->getProduct()->getEntityType()->getEntityTypeCode(), ScommerceConstants::EVENT_TYPE_PRODUCT_TO_CART, ScommerceConstants::EVENT_NAME_UPDATED);
                    }
                }

                if (empty($this->crmProcessManager)) {
                    $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
                }

                $this->crmProcessManager->additionalAddToCartAction($quote);

                $minicart_num = $this->crmProcessManager->getMinicartQuantity($quote);

                $additionalData = $this->crmProcessManager->getAdditionalQuoteData($quote);

                $quote = $this->crmProcessManager->calculateQuoteDeliveryPrice($quote, true);

                $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:minicart.html.twig", $session->get("current_website_id")), array('quote' => $quote, 'additional_data' => $additionalData));

                $priceHtml = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Utilities:price.html.twig", $session->get("current_website_id")), array('price' => $quote->getPriceTotal(), 'currency' => $quote->getCurrency()->getSign()));

                if (empty($this->getPageUrlExtension)) {
                    $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
                }

                $message = null;
                if (!$ret["error"]) {
                    $message = $this->translator->trans("Items updated");
                }
                if (isset($ret["message"]) && !empty($ret["message"])) {
                    if (!empty($message)) {
                        $message .= "<br>";
                    }
                    $message = $message . $ret["message"];
                }

                $reload = false;
                if (isset($ret["reload"]) && $ret["reload"]) {
                    $reload = true;
                }

                $ret = array(
                    'error' => $ret["error"],
                    'message' => $message,
                    'minicart_html' => $html,
                    'minicart_num' => $minicart_num,
                    'total_price' => number_format($quote->getPriceTotal(), 2, ",", ""),
                    'price_html' => $priceHtml,
                    "reload" => $reload
                );

                if (isset($_ENV["AJAX_REFRESH_CART"]) && $_ENV["AJAX_REFRESH_CART"] == 1) {
                    /** @var CartBlock $cartBlock */
                    $cartBlock = $this->getContainer()->get("cart_front_block");
                    $cartHtml = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:cart.html.twig", $session->get("current_website_id")), array(
                        'data' => $cartBlock->GetBlockData(),
                    ));
                    $ret["cart_html"] = $cartHtml;

                    /** @var CartGiftsBlock $giftsBlock */
                    $giftsBlock = $this->getContainer()->get("cart_gifts_front_block");
                    $giftsHtml = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:cart_gifts.html.twig", $session->get("current_website_id")), array(
                        'data' => $giftsBlock->GetBlockData(),
                    ));
                    $ret["gifts_html"] = $giftsHtml;
                }

                $ret = $this->crmProcessManager->afterUpdateCartAction($ret);

                return new JsonResponse($ret);
            }

            return new JsonResponse(array(
                'error' => true,
                'message' => $this->translator->trans("Error updating items")
            ));
        }

        return new JsonResponse(array(
            'error' => true,
            'message' => $this->translator->trans("Error updating items"),
        ));
    }

    /**
     * @Route("/cart/remove_from_cart", name="remove_from_cart")
     * @Method("POST")
     */
    public function removeFromCartAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $quote = $this->quoteManager->getActiveQuote(false);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        if ($quote->getIsLocked()) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Your cart is locked for payment.")));
        }

        if(isset($p["quote_item_id"]) && !empty($p["quote_item_id"])){

            /** @var QuoteItemEntity $quoteItem */
            $quoteItem = $this->quoteManager->getQuoteItemById($p["quote_item_id"]);

            if (empty($quoteItem)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product does not exist in quote")));
            }

            $product = $quoteItem->getProduct();
        }
        else{
            if (!isset($p["product_id"]) || empty($p["product_id"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing product id")));
            }

            /** @var ProductEntity $product */
            $product = $this->productManager->getProductById($p["product_id"]);
            if (empty($product)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product not found")));
            }

            /** @var QuoteItemEntity $quoteItem */
            $quoteItem = $this->quoteManager->getQuoteItemByQuoteAndProduct($quote, $product);

            if (empty($quoteItem)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product does not exist in quote")));
            }
        }

        if($quoteItem->getQuoteId() != $quote->getId()){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product does not exist in quote")));
        }

        $session = $request->getSession();

        $postRemoveJavascript = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Tracking:remove_from_cart.html.twig", $session->get("current_website_id")), array('quote_item' => $quoteItem));

        /**
         * Potrebno jer se kod remove from cart posta child a potreban je parent
         */
        if (!empty($quoteItem->getParentItem()) && $quoteItem->getParentItem()->getProduct()->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
            $this->quoteManager->removeItemFromQuote($quoteItem->getParentItem());
        } else {
            $this->quoteManager->removeItemFromQuote($quoteItem);
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        if ($_ENV["SHAPE_TRACK"] ?? 0) {
            /**
             * Tracking event
             */
            if (empty($this->trackingManager)) {
                $this->trackingManager = $this->getContainer()->get("tracking_manager");
            }

            $this->trackingManager->insertTrackingEvent($request, $product->getId(), $product->getEntityType()->getEntityTypeCode(), ScommerceConstants::EVENT_TYPE_PRODUCT_TO_CART, ScommerceConstants::EVENT_NAME_REMOVED);
        }

        $this->crmProcessManager->additionalAddToCartAction($quote);

        $minicart_num = $this->crmProcessManager->getMinicartQuantity($quote);

        $additionalData = $this->crmProcessManager->getAdditionalQuoteData($quote);

        $quote = $this->crmProcessManager->calculateQuoteDeliveryPrice($quote, true);

        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:minicart.html.twig", $session->get("current_website_id")), array('quote' => $quote, 'additional_data' => $additionalData));

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }

        $ret = array(
            'error' => false,
            'message' => $this->translator->trans("Product") . " " . $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $product, "name") . " " . $this->translator->trans("removed from cart"),
            'minicart_html' => $html,
            'minicart_num' => $minicart_num,
            'total_price' => number_format($quote->getPriceTotal(), 2, ",", "")
        );

        if (isset($_ENV["AJAX_REFRESH_CART"]) && $_ENV["AJAX_REFRESH_CART"] == 1) {
            /** @var CartBlock $cartBlock */
            $cartBlock = $this->getContainer()->get("cart_front_block");
            $cartHtml = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:cart.html.twig", $session->get("current_website_id")), array(
                'data' => $cartBlock->GetBlockData(),
            ));
            $ret["cart_html"] = $cartHtml;

            /** @var CartGiftsBlock $giftsBlock */
            $giftsBlock = $this->getContainer()->get("cart_gifts_front_block");
            $giftsHtml = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:cart_gifts.html.twig", $session->get("current_website_id")), array(
                'data' => $giftsBlock->GetBlockData(),
            ));
            $ret["gifts_html"] = $giftsHtml;
        }

        if (!$ret["error"] && !empty($postRemoveJavascript)) {
            $ret["javascript"] = $postRemoveJavascript;
        }

        $ret = $this->crmProcessManager->afterRemoveFromCartAction($ret);

        return new JsonResponse($ret);
    }

    /**
     * @Route("/cart/remove_all_items_from_cart", name="remove_all_items_from_cart")
     * @Method("POST")
     */
    public function removeAllItemsFromCartAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $quote = $this->quoteManager->getActiveQuote(false);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        if ($quote->getIsLocked()) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Your cart is locked for payment.")));
        }

        $quoteItems = $quote->getQuoteItems();

        if (empty($quoteItems)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Your cart is already empty.")));
        }

        $session = $request->getSession();

        $postRemoveJavascript = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Tracking:remove_all_from_cart.html.twig", $session->get("current_website_id")), array(
            'quote' => $quote,
            'quote_items' => $quoteItems
        ));

        /** @var QuoteItemEntity $quoteItem */
        foreach ($quoteItems as $quoteItem) {

            /** @var ProductEntity $product */
            $product = $quoteItem->getProduct();

            /**
             * Potrebno jer se kod remove from cart posta child a potreban je parent
             */
            if (!empty($quoteItem->getParentItem()) && $quoteItem->getParentItem()->getProduct()->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
                $this->quoteManager->removeItemFromQuote($quoteItem->getParentItem());
            } else {
                $this->quoteManager->removeItemFromQuote($quoteItem);
            }

            if ($_ENV["SHAPE_TRACK"] ?? 0) {
                /**
                 * Tracking event
                 */
                if (empty($this->trackingManager)) {
                    $this->trackingManager = $this->container->get("tracking_manager");
                }

                $this->trackingManager->insertTrackingEvent($request, $product->getId(), $product->getEntityType()->getEntityTypeCode(), ScommerceConstants::EVENT_TYPE_PRODUCT_TO_CART, ScommerceConstants::EVENT_NAME_REMOVED);
            }
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $this->crmProcessManager->additionalAddToCartAction($quote);

        $minicart_num = $this->crmProcessManager->getMinicartQuantity($quote);

        $additionalData = $this->crmProcessManager->getAdditionalQuoteData($quote);

        $quote = $this->crmProcessManager->calculateQuoteDeliveryPrice($quote, true);

        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:minicart.html.twig", $session->get("current_website_id")), array('quote' => $quote, 'additional_data' => $additionalData));

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }

        $ret = array(
            'error' => false,
            'message' => $this->translator->trans("All products were removed from cart"),
            'minicart_html' => $html,
            'minicart_num' => $minicart_num,
            'total_price' => number_format($quote->getPriceTotal(), 2, ",", "")
        );

        if (isset($_ENV["AJAX_REFRESH_CART"]) && $_ENV["AJAX_REFRESH_CART"] == 1) {
            /** @var CartBlock $cartBlock */
            $cartBlock = $this->getContainer()->get("cart_front_block");
            $cartHtml = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:cart.html.twig", $session->get("current_website_id")), array(
                'data' => $cartBlock->GetBlockData(),
            ));
            $ret["cart_html"] = $cartHtml;

            /** @var CartGiftsBlock $giftsBlock */
            $giftsBlock = $this->getContainer()->get("cart_gifts_front_block");
            $giftsHtml = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:cart_gifts.html.twig", $session->get("current_website_id")), array(
                'data' => $giftsBlock->GetBlockData(),
            ));
            $ret["gifts_html"] = $giftsHtml;
        }

        if (!empty($postRemoveJavascript)) {
            $ret["javascript"] = $postRemoveJavascript;
        }

        return new JsonResponse($ret);
    }

    /**
     * @Route("/cart/get_delivery_type_autocomplete", name="get_delivery_type_autocomplete")
     * @Method("GET")
     */
    public function getDeliveryTypeAutocompleteAction(Request $request)
    {
        $this->initialize($request);

        $term = "";
        if (isset($_GET["q"]["term"])) {
            $term = $_GET["q"]["term"];
        }

        $formData = null;
        if (isset($_GET["form"])) {
            $formData = array();
            parse_str($_GET["form"], $formData);
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $quote = $this->quoteManager->getActiveQuote(true);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        $data = $this->crmProcessManager->getAvailableDeliveryTypes($quote, $formData);

        $ret = array();

        if (!empty($data)) {

            if (empty($this->getPageUrlExtension)) {
                $this->getPageUrlExtension = $this->getContainer()->get("get_page_url_extension");
            }
            $session = $request->getSession();

            /**
             * @var  $key
             * @var DeliveryTypeEntity $d
             */
            foreach ($data as $key => $d) {
                $ret[$key]["id"] = $d->getId();
                $ret[$key]["html"] = $this->twig->render("CrmBusinessBundle:AutocompleteTemplates:delivery_type.html.twig", array('field_data' => $d));
                $ret[$key]["description"] = $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $d, "description");
                $ret[$key]["is_delivery"] = 0;
                if ($d->getIsDelivery()) {
                    $ret[$key]["is_delivery"] = 1;
                }
                $ret[$key]["use_as_default"] = 0;

                $useAsDefault = $d->getUseAsDefault();
                if (!empty($session->get("current_website_id")) && isset($useAsDefault[$session->get("current_website_id")]) && $useAsDefault[$session->get("current_website_id")] == 1) {
                    $ret[$key]["use_as_default"] = $useAsDefault[$session->get("current_website_id")];
                }

            }
        }

        return new JsonResponse(array('error' => false, 'ret' => $ret, 'create_new' => false, 'create_new_type', 'create_new_type' => null, 'create_new_url' => null));
    }

    /**
     * @Route("/cart/get_payment_type_autocomplete", name="get_payment_type_autocomplete")
     * @Method("GET")
     */
    public function getPaymentTypeAutocompleteAction(Request $request)
    {
        $this->initialize($request);

        $term = "";
        if (isset($_GET["q"]["term"])) {
            $term = $_GET["q"]["term"];
        }

        $formData = null;
        if (isset($_GET["form"])) {
            $formData = array();
            parse_str($_GET["form"], $formData);
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $quote = $this->quoteManager->getActiveQuote(true);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        $quoteData = array();

        if ($formData["delivery_type_id"] != $quote->getDeliveryTypeId()) {
            if (empty($formData["delivery_type_id"])) {
                $quoteData["delivery_type"] = null;
            } else {
                $quoteData["delivery_type"] = $this->quoteManager->getDeliveryTypeById($formData["delivery_type_id"]);
            }
        }

        if (!empty($quoteData)) {
            $quote = $this->quoteManager->updateQuote($quote, $quoteData);
        }

        if (empty($formData["delivery_type_id"])) {
            return new JsonResponse(array('error' => false, 'ret' => null));
        }

        $data = $this->crmProcessManager->getAvailablePaymentTypes($quote, $formData);
        if (!empty($data)) {

            if (empty($this->getPageUrlExtension)) {
                $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
            }
            $session = $request->getSession();

            /**
             * @var  $key
             * @var PaymentTypeEntity $d
             */
            foreach ($data as $key => $d) {
                $ret[$key]["id"] = $d->getId();
                $ret[$key]["html"] = $this->twig->render("CrmBusinessBundle:AutocompleteTemplates:payment_type.html.twig", array('field_data' => $d));
                $ret[$key]["description"] = $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $d, "description");
                $ret[$key]["use_as_default"] = 0;

                $useAsDefault = $d->getUseAsDefault();
                if (!empty($session->get("current_website_id")) && isset($useAsDefault[$session->get("current_website_id")]) && $useAsDefault[$session->get("current_website_id")] == 1) {
                    $ret[$key]["use_as_default"] = $useAsDefault[$session->get("current_website_id")];
                }
            }
        }

        return new JsonResponse(array('error' => false, 'ret' => $ret, 'create_new' => false, 'create_new_type', 'create_new_type' => null, 'create_new_url' => null));
    }

    /**
     * @Route("/cart/recalculate_totals", name="recalculate_totals")
     * @Method("GET")
     */
    public function recalculateTotalsAction(Request $request)
    {
        $this->initialize($request);

        $formData = null;
        if (isset($_GET["form"])) {
            $formData = array();
            parse_str($_GET["form"], $formData);
        }

        $quote = $this->quoteManager->getActiveQuote(true);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        $quoteData = array();

        if ($formData["delivery_type_id"] != $quote->getDeliveryTypeId()) {
            if (empty($formData["delivery_type_id"])) {
                $quoteData["delivery_type"] = null;
            } else {
                $quoteData["delivery_type"] = $this->quoteManager->getDeliveryTypeById($formData["delivery_type_id"]);
            }
        }

        if (empty($formData["payment_type_id"]) || $formData["payment_type_id"] != $quote->getPaymentTypeId()) {
            if (empty($formData["payment_type_id"])) {
                $quoteData["payment_type"] = null;
            } else {
                $quoteData["payment_type"] = $this->quoteManager->getPaymentTypeById($formData["payment_type_id"]);
            }
        }

        if (!empty($quoteData)) {
            $quote = $this->quoteManager->updateQuote($quote, $quoteData);
        }

        $quote = $this->quoteManager->recalculateQuoteItems($quote);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $quote = $this->crmProcessManager->calculateQuoteDeliveryPrice($quote, true, $formData);

        $data["model"]["quote"] = $quote;
        //$data["model"]["delivery_prices"] = $deliveryPrices;
        $data["model"]["delivery_type_id"] = $formData["delivery_type_id"];

        $session = $request->getSession();
        $html = $this->twig->render($this->templateManager->getTemplatePathByBundle('Components/Cart:cart_totals.html.twig', $session->get("current_website_id")), array("data" => $data, 'show_delivery' => true));

        $cartProductListHtml = "";

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(true);
        if (!empty($quote)) {
            $quoteItems = $quote->getQuoteItems();
            $cartProductListHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle('Components/Cart:cart_product_list.html.twig', $session->get("current_website_id")), array("data" => $data, 'quoteItems' => $quoteItems, 'editable' => false));
        }

        return new JsonResponse(array('error' => false, 'html' => $html, 'cart_product_list_html' => $cartProductListHtml));
    }

    /**
     * @Route("/api/apply_coupon", name="apply_coupon")
     * @Method("POST")
     */
    public function applyCouponAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $discountCoupon = null;

        if (!isset($p["discount_coupon_name"])) {
            $p["discount_coupon_name"] = null;
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(true);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $message = "";
        if (!empty($p["discount_coupon_name"])) {
            if (empty($this->discountCouponManager)) {
                $this->discountCouponManager = $this->container->get("discount_coupon_manager");
            }

            /** @var DiscountCouponEntity $discountCoupon */
            $discountCoupon = $this->discountCouponManager->getDiscountCouponByCode($p["discount_coupon_name"]);

            if (empty($discountCoupon)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("This coupon does not exist or is not active")));
            }

            $isValid = $this->crmProcessManager->checkIfDiscountCouponCanBeApplied($quote, $discountCoupon);

            if (!$isValid) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("This coupon cannot be applied")));
            }

            $this->crmProcessManager->applyDiscountCoupon($quote, $discountCoupon);

            $message = $this->translator->trans("Coupon successfully applied");
        } /**
         * Remove coupon
         */
        elseif (empty($p["discount_coupon_name"])) {
            if (empty($quote->getDiscountCoupon())) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Your cart does not have an applied coupon")));
            }

            $this->crmProcessManager->applyDiscountCoupon($quote);

            $message = $this->translator->trans("Coupon has been successfully removed");
        }

        $session = $request->getSession();
        $additionalData = $this->crmProcessManager->getAdditionalQuoteData($quote);
        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:minicart.html.twig", $session->get("current_website_id")), array('quote' => $quote, 'additional_data' => $additionalData));

        $ret = array(
            'error' => false,
            'message' => $message,
            'minicart_html' => $html,
        );

        if (isset($_ENV["AJAX_REFRESH_CART"]) && $_ENV["AJAX_REFRESH_CART"] == 1) {
            /** @var CartBlock $cartBlock */
            $cartBlock = $this->getContainer()->get("cart_front_block");
            $cartHtml = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:cart.html.twig", $session->get("current_website_id")), array(
                'data' => $cartBlock->GetBlockData(),
                'step' => $p["step"] ?? 1,
            ));
            $ret["cart_html"] = $cartHtml;

            /** @var CartGiftsBlock $giftsBlock */
            $giftsBlock = $this->getContainer()->get("cart_gifts_front_block");
            $giftsHtml = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:cart_gifts.html.twig", $session->get("current_website_id")), array(
                'data' => $giftsBlock->GetBlockData(),
            ));
            $ret["gifts_html"] = $giftsHtml;
        }

        return new JsonResponse($ret);
    }

    /**
     * @Route("/cart/create_admin_order", name="create_admin_order")
     * @Method("POST")
     */
    public function createAdminOrderAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $session = $request->getSession();

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();
        if (empty($user)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('User does not exist')));
        }

        /** @var ContactEntity $contact */
        $contact = $user->getDefaultContact();
        /** @var AccountEntity $account */
        $account = $contact->getAccount();

        $billingAddress = $account->getBillingAddress();

        $quoteData["account_name"] = $contact->getFullName();
        $quoteData["account_oib"] = null;
        if ($account->getIsLegalEntity()) {
            $quoteData["account_name"] = $account->getName();
            $quoteData["account_oib"] = $account->getOib();
        }
        $quoteData["name"] = $account->getName();
        $quoteData["account"] = $contact->getAccount();
        $quoteData["contact"] = $contact;
        $quoteData["account_phone"] = $contact->getPhone();
        $quoteData["account_email"] = $contact->getEmail();
        $quoteData["account_billing_address"] = $billingAddress;
        $quoteData["account_billing_city"] = $billingAddress->getCity();
        $quoteData["account_billing_street"] = $billingAddress->getStreet();
        $quoteData["account_shipping_address"] = $billingAddress;
        $quoteData["account_shipping_city"] = $billingAddress->getCity();
        $quoteData["account_shipping_street"] = $billingAddress->getStreet();

        /**
         * Set payment and delivery types
         */
        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->container->get("application_settings_manager");
        }

        $defaultAdminPaymentTypeId = 1;
        $defaultAdminPaymentTypeSetting = $this->applicationSettingsManager->getApplicationSettingByCode("default_admin_payment_type");
        if (!empty($defaultAdminPaymentTypeSetting) && isset($defaultAdminPaymentTypeSetting[$session->get("current_store_id")])) {
            $defaultAdminPaymentTypeId = $defaultAdminPaymentTypeSetting[$session->get("current_store_id")];
        }
        $quoteData["payment_type"] = $this->quoteManager->getPaymentTypeById($defaultAdminPaymentTypeId);

        $defaultAdminDeliveryTypeId = 1;
        $defaultAdminDeliveryTypeSetting = $this->applicationSettingsManager->getApplicationSettingByCode("default_admin_delivery_type");
        if (!empty($defaultAdminDeliveryTypeSetting) && isset($defaultAdminDeliveryTypeSetting[$session->get("current_store_id")])) {
            $defaultAdminDeliveryTypeId = $defaultAdminDeliveryTypeSetting[$session->get("current_store_id")];
        }
        $quoteData["delivery_type"] = $this->quoteManager->getDeliveryTypeById($defaultAdminDeliveryTypeId);

        if (empty($this->routeManager)) {
            $this->routeManager = $this->getContainer()->get("route_manager");
        }

        $quoteData["store"] = $this->routeManager->getStoreById($session->get("current_store_id"));

        $this->quoteManager->updateQuote($quote, $quoteData);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $ret = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $ret["quoteUrl"];
        $quoteErrorUrl = $ret["quoteErrorUrl"];
        $quoteSuccessUrl = $ret["quoteSuccessUrl"];

        $ret = $this->crmProcessManager->validateQuote($quote);

        if ($ret["error"]) {
            return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("There has been an error. Please try again!"), 'redirect_url' => $ret["redirect_url"]));
        } elseif ($ret["changed"]) {
            return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Your cart items have been changed!"), 'redirect_url' => $ret["redirect_url"]));
        } elseif (isset($ret["redirect_url"]) && !empty($ret["redirect_url"])) {
            return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Your cart items have been changed!"), 'redirect_url' => $ret["redirect_url"]));
        }

        $quoteStatus = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_ACCEPTED);

        $this->quoteManager->changeQuoteStatus($quote, $quoteStatus);

        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderByQuoteId($quote->getId());
        if (empty($order)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("There has been an error. Please try again!"), 'redirect_url' => $quoteErrorUrl));
        }

        $session->set("order_id", $order->getId());

        $ret = array(
            'error' => false,
            'message' => $this->translator->trans("Order created"),
            'redirect_url' => $quoteSuccessUrl
        );

        return new JsonResponse($ret);
    }

    /**
     * @Route("/cart/apply_loyalty", name="apply_loyalty")
     * @Method("POST")
     */
    public function applyLoyaltyAction(Request $request)
    {
        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }
        if(empty($this->loyaltyManager)){
            $this->loyaltyManager = $this->container->get("loyalty_manager");
        }

        if (empty($_POST["data"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Empty data")));
        }

        $loyaltyDiscountLevelId = $_POST['data'];

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(true);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        /** @var LoyaltyCardEntity $loyaltyCard */
        $loyaltyCard = $this->loyaltyManager->getCurrentLoyaltyCard();

        if(empty($loyaltyCard)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Loyalty card does not exist")));
        }

        $availablePoints = $this->loyaltyManager->getAvailableLoyaltyPoints($loyaltyCard);
        $availableDiscounts = $this->loyaltyManager->getAvailableLoyaltyDiscountLevels($availablePoints);

        if(empty($availableDiscounts)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Contact has no available discount levels")));
        }

        // define selected loyalty discount
        $discountPercentage = 0;
        $points = 0;
        foreach ($availableDiscounts as $discountLevel) {
            if ($discountLevel['id'] == $loyaltyDiscountLevelId) {
                $discountPercentage = $discountLevel['percent_discount'];
                $points = $discountLevel['points'];
                break;
            }
        }

        $data = array();
        $data["percent_discount"] = $discountPercentage;
        $data["points"] = $points;

        $this->loyaltyManager->createUpdateLoyaltyCard($loyaltyCard, $data);
        $this->crmProcessManager->calculateQuoteDeliveryPrice($quote, true);

        return new JsonResponse(array('error' => false));
    }

    /**
     * @Route("/cart/remove_loyalty", name="remove_loyalty")
     * @Method("POST")
     */
    public function removeLoyaltyAction(Request $request)
    {
        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }
        if(empty($this->loyaltyManager)){
            $this->loyaltyManager = $this->container->get("loyalty_manager");
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(true);
        if (empty($quote)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Quote does not exist")));
        }

        /** @var LoyaltyCardEntity $loyaltyCard */
        $loyaltyCard = $this->loyaltyManager->getCurrentLoyaltyCard();

        if(empty($loyaltyCard)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Loyalty card does not exist")));
        }

        $data = array();
        $data["percent_discount"] = 0;
        $data["points"] = 0;

        $this->loyaltyManager->createUpdateLoyaltyCard($loyaltyCard, $data);
        $this->crmProcessManager->calculateQuoteDeliveryPrice($quote, true);

        return new JsonResponse(array('error' => false));
    }
}
