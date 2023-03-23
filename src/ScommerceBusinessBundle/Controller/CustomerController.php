<?php

namespace ScommerceBusinessBundle\Controller;

use AppBundle\Entity\CoreUserEntity;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\GoogleCaptchaValidateManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountBankEntity;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\CountryEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderItemEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\TrackingEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\NewsletterManager;
use CrmBusinessBundle\Managers\OrderComplaintManager;
use CrmBusinessBundle\Managers\OrderManager;
use CrmBusinessBundle\Managers\OrderReturnManager;
use CrmBusinessBundle\Managers\ProductManager;
use CrmBusinessBundle\Managers\QuoteManager;
use HrBusinessBundle\Entity\CityEntity;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use ScommerceBusinessBundle\Entity\OrderReturnEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ScommerceBusinessBundle\Managers\DefaultScommerceManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Managers\ScommerceHelperManager;
use ScommerceBusinessBundle\Managers\ThirdPartyManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CustomerController extends AbstractScommerceController
{
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var DefaultScommerceManager $defaultScommerceManager */
    protected $defaultScommerceManager;
    /** @var GoogleCaptchaValidateManager $googleCaptchaValidateManager */
    protected $googleCaptchaValidateManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var DefaultCrmProcessManager */
    protected $crmProcessManager;
    /** @var TokenStorageInterface $tokenStorage */
    protected $tokenStorage;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var NewsletterManager $newsletterManager */
    protected $newsletterManager;
    /** @var ThirdPartyManager $thirdPartyManager */
    protected $thirdPartyManager;
    /** @var OrderReturnManager $orderReturnManager */
    protected $orderReturnManager;
    /** @var ScommerceHelperManager $scommerceHelperManager */
    protected $scommerceHelperManager;
    /** @var OrderComplaintManager $orderComplaintManager */
    protected $orderComplaintManager;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->initializeTwigVariables($request);
    }

    /**
     * @Route("/register_customer", name="register_customer")
     * @Method("POST")
     */
    public function registerCustomerAction(Request $request)
    {
        $disableAutomaticAdd = true;

        $this->initialize($request);

        $p = $_POST;
        $p = array_map('trim', $p);

        if (empty($this->googleCaptchaValidateManager)) {
            $this->googleCaptchaValidateManager = $this->container->get("google_captcha_validate_manager");
        }
        if ($this->googleCaptchaValidateManager->shouldValidateGoogleRecaptchaV3()) {
            if (!isset($p["recaptcha_response"]) || empty($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error validating captcha. Missing captcha response.")));
            }

            if (!$this->googleCaptchaValidateManager->validateGoogleRecaptchaV3($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error validating captcha")));
            }
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
        if (!isset($p["first_name"]) || empty($p["first_name"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing first name")));
        }
        if (!isset($p["last_name"]) || empty($p["last_name"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing last name")));
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

        if ($p["is_legal_entity"]) {
            if (!isset($p["name"]) || empty($p["name"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing company name")));
            }
            if (!isset($p["oib"]) || empty($p["oib"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing company vat number")));
            }
        }
        if (!isset($p["newsletter_signup"])) {
            $p["newsletter_signup"] = 0;
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        if (isset($p["sex"]) && !empty($p["sex"])) {
            $p["sex"] = $this->accountManager->getSexById($p["sex"]);
        } else {
            $p["sex"] = null;
        }

        $p["is_active"] = 1;

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->getContactByEmail($p["email"]);

        if (!empty($contact)) {

            /** @var AccountEntity $account */
            $account = $contact->getAccount();

            if ($contact->getFirstName() != $p["first_name"]) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('This email is already used, please reset your password if necessary'), 'open_login_modal' => true));
            }
            if ($p["is_legal_entity"] && $account->getIsLegalEntity() && $account->getOib() != $p["oib"]) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('This email is already used, please reset your password if necessary'), 'open_login_modal' => true));
            }
        }

        $coreUser = $this->accountManager->getCoreUserByEmail($p["email"]);
        if (!empty($coreUser)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('This email is already used, please reset your password if necessary'), 'open_login_modal' => true));
        }

        $session = $request->getSession();

        $trackingData = array();
        $trackingData["email"] = $p["email"];
        $trackingData["first_name"] = $p["first_name"];
        $trackingData["last_name"] = $p["last_name"];
        $trackingData["contact"] = $contact;
        $trackingData["session_id"] = $request->getSession()->getId();

        $this->accountManager->insertUpdateTracking($trackingData);

        /**
         * If contact exists add user to contact
         */
        if (!empty($contact)) {

            $contactData = $p;
            if (isset($contactData["password"])) {
                unset($contactData["password"]);
            }

            /** @var ContactEntity $contact */
            $contact = $this->accountManager->insertContact($contactData);
            if (empty($contact)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error creating contact, please try again')));
            }

            $ret = $this->accountManager->createUser($p);
            if ($ret["error"]) {
                return new JsonResponse(array('error' => true, 'message' => $ret["message"]));
            }

            $contact->setCoreUser($ret["core_user"]);
            $this->accountManager->save($contact);

            $trackingData["contact"] = $contact;
            $this->accountManager->insertUpdateTracking($trackingData);

            $this->helperManager->loginAnonymus($request, $p["email"], $p["password"]);

            if (empty($this->defaultScommerceManager)) {
                $this->defaultScommerceManager = $this->container->get("scommerce_manager");
            }

            $sessionData["account"] = $contact->getAccount();
            $sessionData["contact"] = $contact;

            $this->defaultScommerceManager->updateSessionData($sessionData);

            $redirectUrl = '/' . $request->getSession()->get("current_language");

            return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Your user has been created'), 'redirect_url' => $redirectUrl));
        }

        $p["last_contact_date"] = new \DateTime();

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

        /**
         * Insert accounts
         */
        /**
         * Pravna osoba
         */
        if ($p["is_legal_entity"]) {
            /** @var AccountEntity $account */
            $account = $this->accountManager->getAccountByFilter("oib", $p["oib"]);

            /** Existing company account */
            if (!empty($account)) {
                if ($disableAutomaticAdd) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('This company is already registered, please contact your administrator or reset your password'), 'open_login_modal' => true));
                }

                $contactData = $p;
                if (isset($contactData["password"])) {
                    unset($contactData["password"]);
                }

                /** @var ContactEntity $contact */
                $contact = $this->accountManager->insertContact($contactData);
                if (empty($contact)) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error creating contact, please try again')));
                }

                /**
                 * Newsletter signup
                 */
                if ($p["newsletter_signup"]) {

                    if (empty($this->newsletterManager)) {
                        $this->newsletterManager = $this->container->get("newsletter_manager");
                    }

                    $newsletterData = array();
                    $newsletterData["active"] = 1;
                    $newsletterData["email"] = $p["email"];
                    $newsletterData["contact"] = $contact;

                    $this->newsletterManager->insertUpdateNewsletterSubscriber($newsletterData);
                }

                $ret = $this->accountManager->createUser($p);
                if ($ret["error"]) {
                    return new JsonResponse(array('error' => true, 'message' => $ret["message"]));
                }

                $contact->setCoreUser($ret["core_user"]);
                $this->accountManager->save($contact);

                $trackingData["contact"] = $contact;
                $this->accountManager->insertUpdateTracking($trackingData);

                $this->helperManager->loginAnonymus($request, $p["email"], $p["password"]);

                if (empty($this->defaultScommerceManager)) {
                    $this->defaultScommerceManager = $this->container->get("scommerce_manager");
                }

                $sessionData["account"] = $account;
                $sessionData["contact"] = $contact;

                $this->defaultScommerceManager->updateSessionData($sessionData);

                $redirectUrl = '/' . $request->getSession()->get("current_language");

                return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Your user has been created'), 'redirect_url' => $redirectUrl));
            } /** New company account */
            else {
                $p["lead_source"] = $this->accountManager->getLeadSourceById(CrmConstants::ONLIE_STORE);
                $p["lead_status"] = $this->accountManager->getLeadStatusById(CrmConstants::LEAD_STATUS_ONLINE_PRE_SALES);

                //todo provjera fine

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

                /** @var AddressEntity $address */
                $address = $this->accountManager->insertAddress("address", $addressData);
                if (empty($address)) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error creating address, please try again')));
                }

                /**
                 * Newsletter signup
                 */
                if ($p["newsletter_signup"]) {

                    if (empty($this->newsletterManager)) {
                        $this->newsletterManager = $this->container->get("newsletter_manager");
                    }

                    $newsletterData = array();
                    $newsletterData["active"] = 1;
                    $newsletterData["email"] = $p["email"];
                    $newsletterData["contact"] = $contact;

                    $this->newsletterManager->insertUpdateNewsletterSubscriber($newsletterData);
                }

                $ret = $this->accountManager->createUser($p);
                if ($ret["error"]) {
                    return new JsonResponse(array('error' => true, 'message' => $ret["message"]));
                }

                $contact->setCoreUser($ret["core_user"]);
                $this->accountManager->save($contact);

                $trackingData["contact"] = $contact;
                $this->accountManager->insertUpdateTracking($trackingData);

                $this->helperManager->loginAnonymus($request, $p["email"], $p["password"]);

                if (empty($this->defaultScommerceManager)) {
                    $this->defaultScommerceManager = $this->container->get("scommerce_manager");
                }

                $sessionData["account"] = $account;
                $sessionData["contact"] = $contact;

                $this->defaultScommerceManager->updateSessionData($sessionData);
            }

            $redirectUrl = '/' . $request->getSession()->get("current_language");;

            return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Your user has been created'), 'redirect_url' => $redirectUrl));
        } /**
         * Fizicka osoba
         */
        else {
            $p["lead_source"] = $this->accountManager->getLeadSourceById(CrmConstants::ONLIE_STORE);
            $p["lead_status"] = $this->accountManager->getLeadStatusById(CrmConstants::LEAD_STATUS_ONLINE_PRE_SALES);

            /** @var AccountEntity $account */
            $account = $this->accountManager->insertAccount("lead", $p);
            if (empty($account)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error creating account, please try again')));
            }

            $p["account"] = $account;

            $contacts = $this->accountManager->getContactsByAccount($account);
            /** @var ContactEntity $contact */
            $contact = $contacts[0];
            if (empty($contact)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error creating contact, please try again')));
            }

            $this->accountManager->updateContact($contact, $p);

            /*if (!empty($p["date_of_birth"])) {
                $contact->setDateOfBirth($p["date_of_birth"]);
                $contact->setIsActive(1);
                $this->accountManager->save($contact);
            }*/

            $addressData = array();
            $addressData["city"] = $city;
            $addressData["account"] = $account;
            $addressData["street"] = $p["street"];

            /** @var AddressEntity $address */
            $address = $this->accountManager->insertAddress("address", $addressData);
            if (empty($address)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error creating address, please try again')));
            }

            /**
             * Newsletter signup
             */
            if ($p["newsletter_signup"]) {

                if (empty($this->newsletterManager)) {
                    $this->newsletterManager = $this->container->get("newsletter_manager");
                }

                $newsletterData = array();
                $newsletterData["active"] = 1;
                $newsletterData["email"] = $p["email"];
                $newsletterData["contact"] = $contact;

                $this->newsletterManager->insertUpdateNewsletterSubscriber($newsletterData);
            }

            $ret = $this->accountManager->createUser($p);
            if ($ret["error"]) {
                return new JsonResponse(array('error' => true, 'message' => $ret["message"]));
            }

            $contact->setCoreUser($ret["core_user"]);
            $this->accountManager->save($contact);

            $trackingData["contact"] = $contact;
            /** @var TrackingEntity $tracking */
            $this->accountManager->insertUpdateTracking($trackingData);

            $this->helperManager->loginAnonymus($request, $p["email"], $p["password"]);

            if (empty($this->defaultScommerceManager)) {
                $this->defaultScommerceManager = $this->container->get("scommerce_manager");
            }

            $sessionData["account"] = $account;
            $sessionData["contact"] = $contact;

            $this->defaultScommerceManager->updateSessionData($sessionData);

            $redirectUrl = '/' . $request->getSession()->get("current_language");

            $ret = array(
                'error' => false,
                'message' => $this->translator->trans('Your user has been created'),
                'redirect_url' => $redirectUrl
            );

            if (!empty($contact)) {
                $ret["javascript"] = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Tracking:login.html.twig", $session->get("current_website_id")), array(
                    'is_register' => true,
                    'contact' => $contact,
                ));
            }

            return new JsonResponse($ret);
        }
    }


    /**
     * @Route("/register/get_country_autocomplete", name="get_country_autocomplete")
     * @Method("GET")
     */
    public function getCountryAutocompleteAction(Request $request)
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

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $countries = $this->accountManager->getCountries($term, $formData);

        $session = $request->getSession();
        $storeId = $session->get("current_store_id");

        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $ret = array();

        if (!empty($countries)) {
            /**
             * @var  $key
             * @var CountryEntity $country
             */
            foreach ($countries as $key => $country) {
                $ret[$key]["id"] = $country->getId();
                if (!isset($country->getName()[$storeId])) {
                    $ret[$key]["html"] = $country->getName()[3];
                } else {
                    $ret[$key]["html"] = $country->getName()[$storeId];
                }
            }
        }

        return new JsonResponse(array('error' => false, 'ret' => $ret, 'create_new' => false, 'create_new_type', 'create_new_type' => null, 'create_new_url' => null));
    }

    /**
     * @Route("/register/get_city_autocomplete", name="get_city_autocomplete")
     * @Method("GET")
     */
    public function getCityAutocompleteAction(Request $request)
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

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $cities = $this->accountManager->getCities($term, $formData);

        $ret = array();

        if (!empty($cities)) {
            /**
             * @var  $key
             * @var CityEntity $city
             */
            foreach ($cities as $key => $city) {
                $ret[$key]["id"] = $city->getId();
                $ret[$key]["html"] = $this->twig->render("CrmBusinessBundle:AutocompleteTemplates:city.html.twig", array('field_data' => $city));
            }
        }

        return new JsonResponse(array('error' => false, 'ret' => $ret, 'create_new' => false, 'create_new_type', 'create_new_type' => null, 'create_new_url' => null));
    }

    /**
     * @Route("/compare/toggle", name="toggle_compare")
     * @Method("POST")
     */
    public function compareToggleAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if (!isset($p["product_id"]) || empty($p["product_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing product")));
        }
        if (!isset($p["is_compare"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('is_compare is missing')));
        }

        /** @var ProductManager $productManager */
        $productManager = $this->container->get("product_manager");

        /** @var ProductEntity $product */
        $product = $productManager->getProductById($p["product_id"]);
        if (empty($product)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product does not exist")));
        }

        $session = $request->getSession();

        $compareProducts = $session->get('compare');

        /**
         * Add to compare
         */
        if ($p["is_compare"]) {
            if (!empty($compareProducts) && in_array($product->getId(), $compareProducts)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("This product is allready in compare")));
            }

            if (!empty($compareProducts) && count($compareProducts) > 3) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Only 4 products can be compared at the same time")));
            }
            $compareProducts[] = $product->getId();
        } else {
            if (empty($compareProducts) || !in_array($product->getId(), $compareProducts)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("This product is not in compare list")));
            }

            if (($key = array_search($product->getId(), $compareProducts)) !== false) {
                unset($compareProducts[$key]);
            }
        }

        $session->set('compare', $compareProducts);

        $ret = [];
        $ret["error"] = false;
        $ret["count"] = count($compareProducts);

        if ($p["is_compare"]) {
            $ret["javascript"] = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Tracking:add_to_compare.html.twig", $session->get("current_website_id")), array('product' => $product));
            $ret["message"] = $this->translator->trans("Product added to compare");
        }else{
            $ret["message"] = $this->translator->trans("Product removed from compare");
        }

        return new JsonResponse($ret);
    }

    /**
     * @Route("/compare/remove", name="remove_from_compare")
     * @Method("POST")
     */
    public function compareRemoveAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if (!isset($p["product_id"]) || empty($p["product_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing product")));
        }

        /** @var ProductManager $productManager */
        $productManager = $this->container->get("product_manager");

        /** @var ProductEntity $product */
        $product = $productManager->getProductById($p["product_id"]);
        if (empty($product)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product does not exist")));
        }

        $session = $request->getSession();

        $compareProducts = $session->get('compare');

        if (!in_array($product->getId(), $compareProducts)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("This product is not in your compare list")));
        }

        if (($key = array_search($product->getId(), $compareProducts)) !== false) {
            unset($compareProducts[$key]);
        }

        $session->set('compare', $compareProducts);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Product removed from compare")));
    }

    /**
     * @Route("/compare/clear", name="clear_compare")
     * @Method("POST")
     */
    public function compareClearAction(Request $request)
    {
        $this->initialize($request);

        $session = $request->getSession();
        $session->set('compare', array());

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Compare list is cleared")));
    }

    /**
     * @Route("/compare/get_compare_count", name="get_compare_count")
     * @Method("POST")
     */
    public function getCompareCountAction(Request $request)
    {
        $session = $request->getSession();

        $this->initialize($request);

        $compareProducts = $session->get('compare');

        $total = 0;
        if (!empty($compareProducts)) {
            $total = count($compareProducts);
        }

        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Utilities:items_count.html.twig", $session->get("current_website_id")), array('count' => $total));

        return new Response($html);
    }

    /**
     * @Route("/login_customer_google", name="login_customer_google")
     * @Method("GET")
     */
    public function loginCustomerGoogleAction(Request $request)
    {
        $this->initialize($request);

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }
        $session = $request->getSession();

        $loginUrl = $this->getPageUrlExtension->getPageUrl($session->get("current_store_id"), 58, "s_page");
        $registerUrl = $this->getPageUrlExtension->getPageUrl($session->get("current_store_id"), 52, "s_page");

        /**
         * If code is missing redirect to login url
         */
        $code = $_GET["code"] ?? null;
        if (empty($code)) {
            // Query string is sometimes invalid. Parse from URL
            $queries = [];
            parse_str(parse_url($_SERVER["REQUEST_URI"], PHP_URL_QUERY), $queries);
            $code = $queries["code"] ?? null;
        }
        if (empty($code)) {
            return $this->redirect($loginUrl);
        }

        if (empty($this->thirdPartyManager)) {
            $this->thirdPartyManager = $this->container->get("third_party_manager");
        }

        $destinationUrl = $this->thirdPartyManager->getGoogleDestinationUrl($session, "login_customer_google");

        $client = $this->thirdPartyManager->getGoogleClient($destinationUrl);

        $google_account_info = $this->thirdPartyManager->getGoogleAccount($client, $code);

        if (empty($google_account_info)) {
            $this->helperManager->setSystemMessage($session, $this->translator->trans('Google authentication failed.'), "error");
            return $this->redirect($loginUrl);
        }

        $p = array();
        $p["email"] = $google_account_info->email;

        $res = $this->helperManager->loginAnonymus($request, $p["email"]);
        if (!$res) {
            $this->helperManager->setSystemMessage($session, $this->translator->trans('User with this email does not exist. Please register.'), "error");
            $session->set("social_email", $p["email"]);
            $session->set("social_first_name", $google_account_info->givenName);
            $session->set("social_last_name", $google_account_info->familyName);
            return $this->redirect($registerUrl);
        }

        $this->helperManager->reloadCurrentUser();

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();
        if (empty($user)) {
            $this->helperManager->setSystemMessage($session, $this->translator->trans('User does not exist or is disabled'), "error");
            return $this->redirect($loginUrl);
        }

        /** @var ContactEntity $contact */
        $contact = $user->getDefaultContact();
        if (empty($contact) || !$contact->getIsActive()) {
            if (empty($this->tokenStorage)) {
                $this->tokenStorage = $this->container->get("security.token_storage");
            }

            $this->tokenStorage->setToken(null);
            $request->getSession()->invalidate();
            $this->helperManager->setSystemMessage($session, $this->translator->trans('User does not exist or is disabled'), "error");
            return $this->redirect($loginUrl);
        }

        if (empty($contact->getAccount())) {
            if (empty($this->tokenStorage)) {
                $this->tokenStorage = $this->container->get("security.token_storage");
            }

            $this->tokenStorage->setToken(null);
            $request->getSession()->invalidate();
            $this->helperManager->setSystemMessage($session, $this->translator->trans('User does not exist or is disabled'), "error");
            return $this->redirect($loginUrl);
        }

        $trackingData = array();
        $trackingData["email"] = $p["email"];
        $trackingData["contact"] = $contact;
        $trackingData["session_id"] = $request->getSession()->getId();

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $this->accountManager->insertUpdateTracking($trackingData);

        if (empty($this->defaultScommerceManager)) {
            $this->defaultScommerceManager = $this->container->get("scommerce_manager");
        }

        $sessionData["account"] = $contact->getAccount();
        $sessionData["contact"] = $contact;

        $this->defaultScommerceManager->updateSessionData($sessionData);

        $redirectUrl = "/";
        if (!empty($session->get("last_url"))) {
            $redirectUrl = $session->get("last_url");
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * @Route("/login_customer_facebook", name="login_customer_facebook")
     * @Method("GET")
     */
    public function loginCustomerFacebookAction(Request $request)
    {
        $this->initialize($request);

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }
        $session = $request->getSession();

        $loginUrl = $this->getPageUrlExtension->getPageUrl($session->get("current_store_id"), 58, "s_page");
        $registerUrl = $this->getPageUrlExtension->getPageUrl($session->get("current_store_id"), 52, "s_page");

        if (isset($_GET["error_code"])) {
            $this->helperManager->setSystemMessage($session, $_GET["error_message"], "error");
            return $this->redirect($loginUrl);
        }

        /**
         * If code is missing redirect to login url
         */
        if (!isset($_GET["code"])) {
            return $this->redirect($loginUrl);
        }

        if (empty($this->thirdPartyManager)) {
            $this->thirdPartyManager = $this->container->get("third_party_manager");
        }

        $client = $this->thirdPartyManager->getFacebookClient();
        $profile = $this->thirdPartyManager->getFacebookAccount($client);

        if (empty($profile)) {
            $this->helperManager->setSystemMessage($session, $this->translator->trans('Google authentication failed.'), "error");
            return $this->redirect($loginUrl);
        }

        $p = array();
        $p["email"] = $profile->getProperty('email');

        $res = $this->helperManager->loginAnonymus($request, $p["email"]);
        if (!$res) {
            $this->helperManager->setSystemMessage($session, $this->translator->trans('User with this email does not exist. Please register.'), "error");
            $session->set("social_email", $p["email"]);
            $session->set("social_first_name", $profile->getProperty('first_name'));
            $session->set("social_last_name", $profile->getProperty('last_name'));
            return $this->redirect($registerUrl);
        }

        $this->helperManager->reloadCurrentUser();

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();
        if (empty($user)) {
            $this->helperManager->setSystemMessage($session, $this->translator->trans('User does not exist or is disabled'), "error");
            return $this->redirect($loginUrl);
        }

        /** @var ContactEntity $contact */
        $contact = $user->getDefaultContact();
        if (empty($contact) || !$contact->getIsActive()) {
            if (empty($this->tokenStorage)) {
                $this->tokenStorage = $this->container->get("security.token_storage");
            }

            $this->tokenStorage->setToken(null);
            $request->getSession()->invalidate();
            $this->helperManager->setSystemMessage($session, $this->translator->trans('User does not exist or is disabled'), "error");
            return $this->redirect($loginUrl);
        }

        if (empty($contact->getAccount())) {
            if (empty($this->tokenStorage)) {
                $this->tokenStorage = $this->container->get("security.token_storage");
            }

            $this->tokenStorage->setToken(null);
            $request->getSession()->invalidate();
            $this->helperManager->setSystemMessage($session, $this->translator->trans('User does not exist or is disabled'), "error");
            return $this->redirect($loginUrl);
        }

        $trackingData = array();
        $trackingData["email"] = $p["email"];
        $trackingData["contact"] = $contact;
        $trackingData["session_id"] = $request->getSession()->getId();

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $this->accountManager->insertUpdateTracking($trackingData);

        if (empty($this->defaultScommerceManager)) {
            $this->defaultScommerceManager = $this->container->get("scommerce_manager");
        }

        $sessionData["account"] = $contact->getAccount();
        $sessionData["contact"] = $contact;

        $this->defaultScommerceManager->updateSessionData($sessionData);

        $redirectUrl = "/";
        if (!empty($session->get("last_url"))) {
            $redirectUrl = $session->get("last_url");
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * @Route("/login_customer", name="login_customer")
     * @Method("POST")
     */
    public function loginCustomerAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if (empty($this->googleCaptchaValidateManager)) {
            $this->googleCaptchaValidateManager = $this->container->get("google_captcha_validate_manager");
        }
        if ($this->googleCaptchaValidateManager->shouldValidateGoogleRecaptchaV3()) {
            if (!isset($p["recaptcha_response"]) || empty($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error validating captcha. Missing captcha response.")));
            }

            if (!$this->googleCaptchaValidateManager->validateGoogleRecaptchaV3($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error validating captcha")));
            }
        }

        if (!isset($p["email"]) || empty($p["email"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing email")));
        }
        if (!isset($p["password"]) || empty($p["password"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing password")));
        }

        $res = $this->helperManager->loginAnonymus($request, $p["email"], $p["password"]);
        if (!$res) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Email or password incorrect')));
        }

        $this->helperManager->reloadCurrentUser();

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();
        if (empty($user)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('User does not exist')));
        }

        /** @var ContactEntity $contact */
        $contact = $user->getDefaultContact();
        if (empty($contact) || !$contact->getIsActive()) {
            if (empty($this->tokenStorage)) {
                $this->tokenStorage = $this->container->get("security.token_storage");
            }

            $this->tokenStorage->setToken(null);
            $request->getSession()->invalidate();
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('User does not exist or is disabled')));
        }

        if (empty($contact->getAccount())) {
            if (empty($this->tokenStorage)) {
                $this->tokenStorage = $this->container->get("security.token_storage");
            }

            $this->tokenStorage->setToken(null);
            $request->getSession()->invalidate();
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('User does not exist or is disabled')));
        }

        $trackingData = array();
        $trackingData["email"] = $p["email"];
        $trackingData["contact"] = $contact;
        $trackingData["session_id"] = $request->getSession()->getId();

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $this->accountManager->insertUpdateTracking($trackingData);

        if (empty($this->defaultScommerceManager)) {
            $this->defaultScommerceManager = $this->container->get("scommerce_manager");
        }

        $sessionData["account"] = $contact->getAccount();
        $sessionData["contact"] = $contact;

        $this->defaultScommerceManager->updateSessionData($sessionData);

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }
        $session = $request->getSession();

        $loginUrl = $this->getPageUrlExtension->getPageUrl($session->get("current_store_id"), 58, "s_page");

        /**
         * Redirect na dash ako ne postoje adrese
         */

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();

        /** @var ContactEntity $contact */
        $contact = $coreUser->getDefaultContact();
        if (empty($contact)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Contact is empty")));
        }

        /** @var AccountEntity $account */
        $account = $contact->getAccount();

        if (!EntityHelper::isCountable($account->getAddresses()) || count($account->getAddresses()) == 0) {
            $redirectUrl = $this->getPageUrlExtension->getPageUrl($session->get("current_store_id"), 64, "s_page");
            return new JsonResponse(array('error' => false, 'redirect_url' => $redirectUrl));
        }

        $redirectUrl = $request->server->get('HTTP_REFERER');
        if (isset($p["destination"]) && !empty($p["destination"])) {
            if (substr($p["destination"], 0, 1) === "/") {
                $redirectUrl = "/" . $session->get("current_language") . $p["destination"];
            } else {
                $redirectUrl = "/" . $session->get("current_language") . "/" . $p["destination"];
            }
        } elseif (strpos($redirectUrl, $loginUrl) !== false) {
            $redirectUrl = "/" . $session->get("current_language");
        }
        $redirectUrl = str_replace('|||', '&', $redirectUrl);

        $ret = array(
            'error' => false,
            'redirect_url' => $redirectUrl
        );

        if (!empty($contact)) {
            $ret["javascript"] = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Tracking:login.html.twig", $session->get("current_website_id")), array('contact' => $contact));
        }

        return new JsonResponse($ret);
    }

    /**
     * @Route("/set_new_password", name="set_new_password")
     * @Method("POST")
     */
    public function setNewPasswordAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if (empty($this->googleCaptchaValidateManager)) {
            $this->googleCaptchaValidateManager = $this->container->get("google_captcha_validate_manager");
        }
        if ($this->googleCaptchaValidateManager->shouldValidateGoogleRecaptchaV3()) {
            if (!isset($p["recaptcha_response"]) || empty($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error validating captcha. Missing captcha response.")));
            }

            if (!$this->googleCaptchaValidateManager->validateGoogleRecaptchaV3($p["recaptcha_response"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Error validating captcha")));
            }
        }

        if (!isset($p["token"]) || empty($p["token"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing token")));
        }
        if (!isset($p["new_password"]) || empty($p["new_password"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing new password")));
        }
        if (!isset($p["new_password_2"]) || empty($p["new_password_2"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing repeated password")));
        }
        if ($p["new_password"] !== $p["new_password_2"]) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Passwords do not match")));
        }
        if (strlen($p["new_password"]) < 6 || strlen($p["new_password_2"]) < 6) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Minimal password length is 6 characters")));
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $res = $this->accountManager->setNewPassword($p["token"], $p["new_password"]);

        if (empty($res)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('User does not exist or is disabled')));
        }

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }
        $session = $request->getSession();

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('You have successfully set new password. You will be redirected to login page'), 'redirect_url' => $this->getPageUrlExtension->getPageUrl($session->get("current_store_id"), 58, "s_page")));
    }

    /**
     * @Route("/reset_password_request", name="reset_password_request")
     * @Method("POST")
     */
    public function resetPasswordRequestAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

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

        $res = $this->accountManager->requestPasswordReset($p["email"]);

        if (empty($res)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('User does not exist or is disabled')));
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('An email with link to reset your password has been sent to your email address')));
    }

    /**
     * @Route("/logout_customer", name="logout_customer")
     * @Method("POST")
     */
    public function logoutAction(Request $request)
    {
        $this->initialize($request);

        $session = $request->getSession();
        $currentLanguage = $session->get("current_language");

        if ($_ENV["SAVE_QUOTE_ON_LOGIN"] ?? false) {
            if (empty($this->quoteManager)) {
                $this->quoteManager = $this->getContainer()->get("quote_manager");
            }

            /** @var QuoteEntity $quote */
            $quote = $this->quoteManager->getActiveQuote(false);
        }

        if (is_object($this->user)) {
            if (empty($this->tokenStorage)) {
                $this->tokenStorage = $this->getContainer()->get("security.token_storage");
            }

            $this->tokenStorage->setToken(null);
        }

        $sessionKeys = array_keys($request->getSession()->all());
        foreach ($sessionKeys as $sessionKey) {
            if (in_array($sessionKey, array("_locale", "store"))) {
                continue;
            }
            $request->getSession()->remove($sessionKey);
        }
        $request->getSession()->migrate();

        if ($_ENV["SAVE_QUOTE_ON_LOGIN"] ?? false) {
            if (!empty($quote)) {

                $this->quoteManager->cleanQuoteCustomerData($quote);

                $data = array();
                $data["session_id"] = $request->getSession()->getId();

                $this->quoteManager->updateQuote($quote, $data);
            }
        }

        return new JsonResponse(array('error' => false, 'redirect_url' => "/" . $currentLanguage));
    }

    /**
     * @Route("/dashboard/update_password", name="dashboard_update_password")
     * @Method("POST")
     */
    public function dashboardUpdatePasswordAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $p = array_map('trim', $p);

        if (!isset($p["password"]) || empty($p["password"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing current password")));
        }
        if (!isset($p["new_password"]) || empty($p["new_password"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing new password")));
        }
        if (!isset($p["new_password_2"]) || empty($p["new_password_2"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing repeated password")));
        }
        if ($p["new_password"] !== $p["new_password_2"]) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Passwords do not match")));
        }
        if (strlen($p["new_password"]) < 6 || strlen($p["new_password_2"]) < 6) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Minimal password length is 6 characters")));
        }

        if (!is_object($this->user)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid user")));
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();

        $res = $this->helperManager->loginAnonymus($request, $coreUser->getEmail(), $p["password"]);
        if (!$res) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Current password incorrect")));
        }

        /** @var ContactEntity $contact */
        $contact = $coreUser->getDefaultContact();
        if (empty($contact)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Contact is empty")));
        }

        $p["email"] = $coreUser->getEmail();
        $p["password"] = $p["new_password"];
        $p["google_authenticator_secret"] = null;
        $p["system_role"] = "ROLE_USER";
        $p["id"] = $this->user->getId();

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $ret = $this->accountManager->updateUser($p);
        if ($ret["error"]) {
            return new JsonResponse(array('error' => true, 'message' => $ret["message"]));
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Your user data has been updated')));
    }

    /**
     * @Route("/dashboard/update_company_data", name="dashboard_update_company_data")
     * @Method("POST")
     */
    public function dashboardUpdateCompanyDataAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $p = array_map('trim', $p);

        if (!isset($p["name"]) || empty($p["name"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing company name")));
        }
        if (!isset($p["oib"]) || empty($p["oib"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing company vat number")));
        }

        if (!is_object($this->user)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid user")));
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();

        /** @var ContactEntity $contact */
        $contact = $coreUser->getDefaultContact();
        if (empty($contact)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Contact is empty")));
        }

        /** @var AccountEntity $account */
        $account = $contact->getAccount();

        $account->setName($p["name"]);
        $account->setOib($p["oib"]);

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $this->accountManager->save($account);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Company data updated')));
    }

    /**
     * @Route("/dashboard/update_personal_data", name="dashboard_update_personal_data")
     * @Method("POST")
     */
    public function dashboardUpdatePersonalDataAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $p = array_map('trim', $p);

        if (!isset($p["first_name"]) || empty($p["first_name"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing first name")));
        }
        if (!isset($p["last_name"]) || empty($p["last_name"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing last name")));
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
        if (!isset($p["phone"]) || empty($p["phone"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing phone")));
        }
        if (!isset($p["fax"])) {
            $p["fax"] = null;
        }
        if (!isset($p["phone_2"])) {
            $p["phone_2"] = null;
        }
        if (!isset($p["newsletter_signup"])) {
            $p["newsletter_signup"] = 0;
        }
        if (!isset($p["marketing_signup"])) {
            $p["marketing_signup"] = 0;
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        if (isset($p["sex"]) && !empty($p["sex"])) {
            $p["sex"] = $this->accountManager->getSexById($p["sex"]);
        } else {
            $p["sex"] = null;
        }

        if (!is_object($this->user)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid user")));
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();

        /** @var ContactEntity $contact */
        $contact = $coreUser->getDefaultContact();
        if (empty($contact)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Contact is empty")));
        }
        $p["email"] = $contact->getEmail();

        $contactData = $p;
        if (isset($contactData["password"])) {
            unset($contactData["password"]);
        }

        $this->accountManager->insertContact($contactData);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Personal data updated')));
    }

    /**
     * @Route("/dashboard/anonymize", name="dashboard_anonymize")
     * @Method("POST")
     */
    public function contactAnonymizeAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $p = array_map('trim', $p);

        if (!is_object($this->user)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid user")));
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();

        /** @var ContactEntity $contact */
        $contact = $coreUser->getDefaultContact();
        if (empty($contact)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Contact is empty")));
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->getContainer()->get("account_manager");
        }

        try {
            $this->accountManager->gdprAnonymize($contact);
        } catch (\Exception $e) {
            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }
            $this->errorLogManager->logExceptionEvent("Error GDPR anonymize", $e, true);

            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('There has been an error. Please contact us on support mail.')));
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Your account has been removed')));
    }

    /**
     * @Route("/dashboard/insert_update_address_data", name="dashboard_insert_update_address_data")
     * @Method("POST")
     */
    public function dashboardInsertUpdateAddressDataAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $p = array_map('trim', $p);

        if (!isset($p["id"])) {
            $p["id"] = null;
        }
        if (!isset($p["country_id"]) || empty($p["country_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing country")));
        }
        if (!isset($p["city_id"]) || empty($p["city_id"])) {
            if (!isset($p["city_name"]) || empty($p["city_name"]) || !isset($p["postal_code"]) || empty($p["postal_code"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing city")));
            }
        }
        if (!isset($p["street"]) || empty($p["street"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing street")));
        }
        if (!isset($p["remove"])) {
            $p["remove"] = 0;
        }
        if (!isset($p["first_name"])) {
            $p["first_name"] = null;
        }
        if (!isset($p["last_name"])) {
            $p["last_name"] = null;
        }
        if (!isset($p["phone"])) {
            $p["phone"] = null;
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        if (!is_object($this->user)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid user")));
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();

        /** @var ContactEntity $contact */
        $contact = $coreUser->getDefaultContact();
        if (empty($contact)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Contact is empty")));
        }

        /** @var AccountEntity $account */
        $account = $contact->getAccount();

        /**
         * Disable remove if only one address
         */
        if ($p["remove"]) {
            $addresses = $account->getAddresses();
            if (EntityHelper::isCountable($addresses) && count($addresses) == 1) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("This address cannot be deleted")));
            }
        }

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

        /**
         * Update
         */
        if (!empty($p["id"])) {
            /** @var AddressEntity $address */
            $address = $this->accountManager->getAddressById($p["id"]);

            if (empty($address)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("This address does not exist")));
            } elseif ($address->getAccountId() != $account->getId()) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("You cannot edit this address")));
            }

            if ($p["remove"]) {
                $this->accountManager->delete($address);
                return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Address has been deleted')));
            } else {
                $address->setCity($city);
                $address->setStreet($p["street"]);
                $address->setFirstName($p["first_name"]);
                $address->setLastName($p["last_name"]);
                $address->setPhone($p["phone"]);
                if (isset($p["address_billable"])) {
                    $address->setBilling(true);

                    // Disable previous billing address
                    /** @var AddressEntity $prevAddress */
                    foreach ($account->getAddresses() as $prevAddress) {
                        if ($prevAddress->getId() != $address->getId() && $prevAddress->getBilling()) {
                            $prevAddress->setBilling(false);
                            $this->accountManager->save($prevAddress);
                        }
                    }
                }
                if (isset($p["default_shipping_address"])) {
                    $address->setDefaultShippingAddress(true);

                    // Disable previous shipping address
                    /** @var AddressEntity $prevAddress */
                    foreach ($account->getAddresses() as $prevAddress) {
                        if ($prevAddress->getId() != $address->getId() && $prevAddress->getDefaultShippingAddress()) {
                            $prevAddress->setDefaultShippingAddress(false);
                            $this->accountManager->save($prevAddress);
                        }
                    }
                }

                $this->accountManager->save($address);

                return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Address has been saved')));
            }
        } /**
         * Insert
         */
        else {
            $data = array();
            $data["account"] = $account;
            $data["city"] = $city;
            $data["street"] = $p["street"];
            $data["first_name"] = $p["first_name"];
            $data["last_name"] = $p["last_name"];
            $data["phone"] = $p["phone"];
            $data["billing"] = false;
            if (isset($p["address_billable"])) {
                $data["billing"] = true;
            }
            if (isset($p["default_shipping_address"])) {
                $data["default_shipping_address"] = true;
            }

            //TODO DAVOR izbaciti ovo smece dolje

            /** @var AddressEntity $newAddress */
            $newAddress = $this->accountManager->insertAddress("address", $data);
            if ($newAddress->getBilling()) {
                // Disable previous billing address
                /** @var AddressEntity $prevAddress */
                foreach ($account->getAddresses() as $prevAddress) {
                    if ($prevAddress->getId() != $newAddress->getId() && $prevAddress->getBilling()) {
                        $prevAddress->setBilling(false);
                        $this->accountManager->save($prevAddress);
                    }
                }
            }

            if ($newAddress->getDefaultShippingAddress()) {
                // Disable previous shipping address
                /** @var AddressEntity $prevAddress */
                foreach ($account->getAddresses() as $prevAddress) {
                    if ($prevAddress->getId() != $newAddress->getId() && $prevAddress->getDefaultShippingAddress()) {
                        $prevAddress->setDefaultShippingAddress(false);
                        $this->accountManager->save($prevAddress);
                    }
                }
            }

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Address has been added'), "address" => $this->entityManager->entityToArray($newAddress)));
        }

        return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('There has been an error. Please try again.')));
    }

    /**
     * @Route("/dashboard/repeat_order", name="dashboard_repeat_order")
     * @Method("POST")
     */
    public function dashboardRepeatOrderAction(Request $request)
    {
        $this->initialize($request);
        $session = $request->getSession();

        $p = $_POST;

        if (!isset($p["order_id"]) || empty($p["order_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing order")));
        }

        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderById($p["order_id"]);

        if (empty($order)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Order does not exist")));
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();

        /** @var ContactEntity $contact */
        $contact = $coreUser->getDefaultContact();
        if (empty($contact)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Contact is empty")));
        }

        /** @var AccountEntity $account */
        $account = $contact->getAccount();

        if ($order->getAccountId() != $account->getId()) {
            $this->logger->error("DASHBOARD repeat order: Different accounts - order {$order->getAccountId()}, customer {$account->getId()}"); //todo
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Wrong customer on order")));
        }

        $orderItems = $order->getOrderItems();

        if (!EntityHelper::isCountable($orderItems) || count($orderItems) == 0) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Selected order is empty")));
        }

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(true);

        if ($quote->getIsLocked()) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Your cart is locked for payment.")));
        }

        /** @var OrderItemEntity $orderItem */
        foreach ($orderItems as $orderItem) {

            $options = array();

            /**
             * Opcija kada je parent configurabilni
             */
            if (!empty($orderItem->getParentItem()) && ($orderItem->getParentItem()->getProduct()->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE || $orderItem->getParentItem()->getProduct()->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE)) {
                continue;
            }

            $options["product_id"] = $orderItem->getProduct()->getId();
            $options["qty"] = $orderItem->getQty();

            //TODO ovo potencijalno treba napisati negdje drugdje ako ce se koristiti na vise mjesta
            if ($orderItem->getProduct()->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
                $options["configurable"] = $orderItem->getConfigurableProductOptions();

                if (empty($options["configurable"])) {
                    continue;
                }
            } elseif ($orderItem->getProduct()->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE) {
                $options["configurable_bundle"] = $orderItem->getConfigurableBundleProductOptions();

                if (empty($options["configurable_bundle"])) {
                    continue;
                }
            }

            $this->quoteManager->addUpdateProductInQuote($orderItem->getProduct(), $quote, $orderItem->getQty(), true, $options);
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $ret = $this->crmProcessManager->getPaymentProviderUrls();

        $data = array();
        $data["payment_type"] = $order->getPaymentType();
        $data["delivery_type"] = $order->getDeliveryType();
        $data["account_billing_address"] = $order->getAccountBillingAddress();
        $data["account_shipping_address"] = $order->getAccountShippingAddress();
        $data["additional_data"] = $order->getAdditionalData();
        $data["message"] = $order->getMessage();

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        $data["store"] = $this->routeManager->getStoreById($session->get("current_store_id"));

        $this->quoteManager->updateQuote($quote, $data);

        /**
         * Additional add to cart action for custom automatic cart actions
         */
        $this->crmProcessManager->additionalAddToCartAction($quote, $p);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Product/s have been added to cart."), "redirect_url" => $ret["cartUrl"]));
    }


    /**
     * @Route("/dashboard/insert_update_account_bank_data", name="dashboard_insert_update_account_bank_data")
     * @Method("POST")
     */
    public function dashboardInsertUpdateAccountBankDataAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        $p = array_map('trim', $p);

        if (!isset($p["id"])) {
            $p["id"] = null;
        }
        if (!isset($p["iban"]) || empty($p["iban"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing iban")));
        }

        if (!is_object($this->user)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid user")));
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();

        /** @var ContactEntity $contact */
        $contact = $coreUser->getDefaultContact();
        if (empty($contact)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Contact is empty")));
        }
        if (!isset($p["remove"])) {
            $p["remove"] = 0;
        }

        /** @var AccountEntity $account */
        $account = $contact->getAccount();

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        /**
         * Update
         */
        if (!empty($p["id"])) {
            /** @var AccountBankEntity $accountBank */
            $accountBank = $this->accountManager->getAccountBankById($p["id"]);

            if (empty($accountBank)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("This bank account does not exist")));
            } elseif ($accountBank->getAccountId() != $account->getId()) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("You cannot edit this bank account")));
            }

            if ($p["remove"]) {
                $this->accountManager->delete($accountBank);
                return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Bank account has been deleted')));
            } else {
                $accountBank->setIban($p["iban"]);

                $this->accountManager->save($accountBank);

                return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Bank account has been saved')));
            }
        } else {
            /**
             * Insert
             */

            $data = array();
            $data["account"] = $account;
            $data["iban"] = $p["iban"];

            $newBankAccount = $this->accountManager->insertAccountBank($data);

            if (empty($this->entityManager)) {
                $this->entityManager = $this->getContainer()->get("entity_manager");
            }

            return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Bank account has been added'), "bank_account" => $this->entityManager->entityToArray($newBankAccount)));
        }

        return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('There has been an error. Please try again.')));
    }

    /**
     * @Route("/dashboard/return_order_modal", name="dashboard_return_order_modal")
     * @Method("POST")
     */
    public function dashboardReturnOrderModalAction(Request $request)
    {
        $this->initialize($request);
        $session = $request->getSession();

        $p = $_POST;

        if (!isset($p["order"]) || empty($p["order"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing order")));
        }

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing items")));
        }

        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderById($p["order"]);

        if (empty($order)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Order does not exist")));
        }

//        $items = [];
//        /** @var OrderItemEntity $orderItem */
//        foreach ($order->getOrderItems() as $orderItem) {
//            if (in_array($orderItem->getId(), array_column($p["items"], "quote_item"))) {
//                $items[] = $orderItem;
//            }
//        }
//
//        $p["items"] = $items;

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();

        /** @var ContactEntity $contact */
        $contact = $coreUser->getDefaultContact();
        if (empty($contact)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Contact is empty")));
        }

        /** @var AccountEntity $account */
        $account = $contact->getAccount();

        $p["account"] = $account;

        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:order_return_modal.html.twig", $session->get("current_website_id")), array('data' => $p));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/dashboard/complaint_order_modal", name="dashboard_complaint_order_modal")
     * @Method("POST")
     */
    public function dashboardComplaintOrderModalAction(Request $request)
    {
        $this->initialize($request);
        $session = $request->getSession();

        $p = $_POST;

        if (!isset($p["order"]) || empty($p["order"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing order")));
        }

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing items")));
        }

        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderById($p["order"]);

        if (empty($order)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Order does not exist")));
        }

        foreach ($p["items"] as $key => $orderItemId) {
            $p["items"][$key] = $this->orderManager->getOrderItemById($orderItemId);
        }

        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Cart:order_complaint_modal.html.twig", $session->get("current_website_id")), array('data' => $p));

        return new JsonResponse(array('error' => false, 'html' => $html));
    }


    /**
     * @Route("/dashboard/get_bank_accounts", name="get_bank_accounts")
     * @Method("GET")
     */
    public function getBankAccountsAutocompleteAction(Request $request)
    {
        $this->initialize($request);

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();

        /** @var ContactEntity $contact */
        $contact = $coreUser->getDefaultContact();
        if (empty($contact)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Contact is empty")));
        }

        /** @var AccountEntity $account */
        $account = $contact->getAccount();
        $bankAccounts = $account->getBankAccounts();

        $ret = array();

        if (!empty($bankAccounts)) {
            /**
             * @var  $key
             * @var AccountBankEntity $bankAccount
             */
            foreach ($bankAccounts as $key => $bankAccount) {
                $ret[$key]["id"] = $bankAccount->getId();
                $ret[$key]["html"] = $this->twig->render("AppBundle:Form/AutocompleteTemplates:default.html.twig", array('field_data' => $bankAccount, 'attribute' => "iban"));
            }
        }

        return new JsonResponse(array('error' => false, 'ret' => $ret, 'create_new' => false, 'create_new_type', 'create_new_type' => null, 'create_new_url' => null));
    }

    /**
     * @Route("/dashboard/return_order", name="dashboard_return_order")
     * @Method("POST")
     */
    public function dashboardReturnOrderAction(Request $request)
    {
        $this->initialize($request);
        $session = $request->getSession();

        $p = $_POST;
        if (!isset($p["order"]) || empty($p["order"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing order")));
        }

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing items")));
        } else {
            $p["items"] = json_decode($p["items"], true);
            if (empty($p["items"])) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing items")));
            }
        }

        if (!isset($p["date"]) || empty($p["date"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Pickup date is missing")));
        } else {
            $p["date"] = \DateTime::createFromFormat('d.m.Y H:i', $p["date"]);
            $now = new \DateTime('now + 1 day');

            if ($p["date"] < $now) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Pickup date has to be at least 1 days in future")));
            }

            if (empty($this->scommerceHelperManager)) {
                $this->scommerceHelperManager = $this->container->get("scommerce_helper_manager");
            }
            $date = clone $p["date"];
            $date = $this->scommerceHelperManager->getNextWorkDay($date, array(0, 6));

            if ($date != $p["date"]) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("First available pickup date is") . ": " . $date->format("d.m.Y.")));
            }
        }

        if (!isset($p["bank_account"]) || empty($p["bank_account"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Bank account is missing")));
        }

        if (!isset($p["address"]) || empty($p["address"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Address is missing")));
        }

        if (!isset($p["return_reason"])) {
            $p["return_reason"] = null;
        }

        if (!isset($p["delivery_message"])) {
            $p["delivery_message"] = null;
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->getContainer()->get("account_manager");
        }

        /** @var AccountBankEntity $bankAccount */
        $bankAccount = $this->accountManager->getAccountBankById($p["bank_account"]);
        if (empty($bankAccount)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Bank account does not exist")));
        }

        /** @var AddressEntity $address */
        $address = $this->accountManager->getAddressById($p["address"]);
        if (empty($address)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Address does not exist")));
        }

        if (empty($this->orderManager)) {
            $this->orderManager = $this->getContainer()->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderById($p["order"]);

        if (empty($this->orderReturnManager)) {
            $this->orderReturnManager = $this->getContainer()->get("order_return_manager");
        }

        $data = array();
        $data["pickup_date"] = $p["date"];
        $data["bank_account"] = $bankAccount;
        $data["order"] = $order;
        $data["contact"] = $order->getContact();
        $data["account_pickup_address"] = $address;
        $data["account_pickup_city"] = $address->getCity();
        $data["account_pickup_street"] = $address->getStreet();
        $data["order_return_state"] = $this->orderReturnManager->getOrderReturnStateById(CrmConstants::ORDER_RETURN_STATE_NEW);
        $data["increment_id"] = $this->orderReturnManager->getOrderReturnNextIncrementId($order->getStore());
        $data["return_reason"] = $p["return_reason"];
        $data["delivery_message"] = $p["delivery_message"];

        try {
            /** @var OrderReturnEntity $orderReturn */
            $orderReturn = $this->orderReturnManager->createOrderReturn($order, $p["items"], $data);
        } catch (\Exception $e) {
            return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Return failed")));
        }

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->getContainer()->get("get_page_url_extension");
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Return order created"), 'redirect' => $this->getPageUrlExtension->getPageUrl($session->get("current_store_id"), 60, "s_page"), 'file' => null));
    }

    /**
     * @Route("/dashboard/complaint_order", name="dashboard_complaint_order")
     * @Method("POST")
     */
    public function dashboardComplaintOrderAction(Request $request)
    {
        $this->initialize($request);
        $session = $request->getSession();

        $p = $_POST;

        if (!isset($p["order"]) || empty($p["order"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing order")));
        }

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing items")));
        }

        if (empty($this->orderComplaintManager)) {
            $this->orderComplaintManager = $this->getContainer()->get("order_complaint_manager");
        }

        if (empty($this->orderManager)) {
            $this->orderManager = $this->getContainer()->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderById($p["order"]);

        try {
            /** @var OrderReturnEntity $orderReturn */
            $this->orderComplaintManager->createOrderComplaint($order, $p["items"]);
        } catch (\Exception $e) {
            return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Return failed")));
        }

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->getContainer()->get("get_page_url_extension");
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans("Complaint created"), 'reload' => true));
    }
}
