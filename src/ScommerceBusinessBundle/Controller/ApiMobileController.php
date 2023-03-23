<?php

namespace ScommerceBusinessBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use AppBundle\Entity\ApiAccessEntity;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use CrmBusinessBundle\Entity\ProductImagesEntity;
use CrmBusinessBundle\Entity\ProductWarehouseLinkEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\ProductManager;
use CrmBusinessBundle\Managers\QuoteManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use ScommerceBusinessBundle\Entity\SMenuEntity;
use ScommerceBusinessBundle\Entity\SProductSearchResultsEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Entity\SWebsiteEntity;
use ScommerceBusinessBundle\Managers\ApiMobileManager;
use ScommerceBusinessBundle\Managers\DefaultScommerceManager;
use ScommerceBusinessBundle\Managers\MenuManager;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Managers\SsearchManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ApiMobileController extends AbstractScommerceController
{

    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var ProductManager $productManager */
    protected $productManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var DefaultScommerceManager $defaultScommerceManager */
    protected $defaultScommerceManager;
    /** @var ApiMobileManager $apiMobileManager */
    protected $apiMobileManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var MenuManager $menuManager */
    protected $menuManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;

    protected function initialize($request = null)
    {
        parent::initialize();
    }

    /**
     * @Route("/mobile_api/ping", name="mobile_ping")
     * @Method("POST")
     * @ApiDoc(
     *  resource=true,
     *  section=" Mobile Api",
     *  description="Ping api",
     *  filters={},
     *  requirements={},
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function mobilePing()
    {
        return new JsonResponse(array('error' => false, 'data' => "pong"));
    }

    /**
     * @Route("/mobile_api/login", name="mobile_login")
     * @Method("POST")
     * @ApiDoc(
     *  resource=true,
     *  section=" Mobile Api",
     *  description="Login user",
     *  filters={},
     *  requirements={},
     *  parameters={
     *      {"name"="username", "dataType"="string", "required"=true, "description"="Username"},
     *      {"name"="password", "dataType"="string", "required"=true, "description"="Password"}
     *  },
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     *
     * )
     */
    public function mobileLogin(Request $request)
    {
        $p = $_POST;

        $this->initialize();

        if (!isset($p["username"]) || empty($p["username"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Username is empty')));
        }
        if (!isset($p["password"]) || empty($p["password"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Password is empty')));
        }

        $res = $this->helperManager->loginAnonymus($request, $p["username"], $p["password"]);
        if (!$res) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('Username or password incorrect'))
            );
        }

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();
        if (empty($user)) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('User does not exist'))
            );
        }

        /** @var ApiAccessEntity $token */
        $token = $this->helperManager->getTokenByUser($user);
        if (empty($token)) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('Token does not exist'))
            );
        }

        $ret = array();

        $ret["token"] = $token->getToken();
        $ret["refresh_token"] = $token->getRefreshToken();

        return new JsonResponse(array('error' => false, 'data' => $ret));
    }

    /**
     * @Route("/mobile_api/reset_user_password", name="mobile_reset_user_password")
     * @Method("POST")
     * @ApiDoc(
     *  resource=true,
     *  section=" Mobile Api",
     *  description="Reset password",
     *  filters={},
     *  requirements={},
     *  parameters={
     *      {"name"="username", "dataType"="string", "required"=true, "description"="Username"},
     *  },
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     *
     * )
     */
    public function mobileResetUserPassword(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["username"]) || empty($p["username"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Username is empty')));
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $confirmationUrl = $this->accountManager->requestPasswordReset($p["username"]);
        if (!$confirmationUrl) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('Username or email incorrect'))
            );
        }

        return new JsonResponse(
            array(
                'error' => false,
                'data' => array(),
                'message' => $this->translator->trans("You will receive an email with a password reset link shortly"),
            )
        );
    }

    /**
     * @Route("/mobile_api/set_new_user_password", name="mobile_set_new_user_password")
     * @Method("POST")
     * @ApiDoc(
     *  resource=true,
     *  section=" Mobile Api",
     *  description="Set new password",
     *  filters={},
     *  requirements={},
     *  parameters={
     *      {"name"="token", "dataType"="string", "required"=true, "description"="Password reset token"},
     *      {"name"="password", "dataType"="string", "required"=true, "description"="Password"},
     *      {"name"="repeat_password", "dataType"="string", "required"=true, "description"="Repeat password"},
     *  },
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function mobileSetNewUserPassword(Request $request)
    {

        $this->initialize();

        $p = $_POST;
        $p = array_map('trim', $p);

        if (!isset($p["token"]) || empty($p["token"])) {
            $response = new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Missing token"))
            );
            $response->headers->set('Access-Control-Allow-Origin', '*');

            return $response;
        }
        if (!isset($p["password"]) || empty($p["password"])) {
            $response = new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Missing password"))
            );
            $response->headers->set('Access-Control-Allow-Origin', '*');

            return $response;
        }
        if (!isset($p["repeat_password"]) || empty($p["repeat_password"])) {
            $response = new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Missing repeat password"))
            );
            $response->headers->set('Access-Control-Allow-Origin', '*');

            return $response;
        }
        if ($p["password"] != $p["repeat_password"]) {
            $response = new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Passwords do not match"))
            );
            $response->headers->set('Access-Control-Allow-Origin', '*');

            return $response;
        }
        if (strlen($p["password"]) < 6 || strlen($p["repeat_password"]) < 6) {
            $response = new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Minimal password length is 6 characters"))
            );
            $response->headers->set('Access-Control-Allow-Origin', '*');

            return $response;
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $res = $this->accountManager->setNewPassword($p["token"], $p["password"]);

        if (empty($res)) {
            $response = new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('User does not exist or is disabled'))
            );
            $response->headers->set('Access-Control-Allow-Origin', '*');

            return $response;
        }

        $response = new JsonResponse(
            array(
                'error' => false,
                'message' => $this->translator->trans('Your password has been changed'),
                'data' => array(),
            )
        );
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }

    /**
     * @Route("/mobile_api/refresh_token", name="mobile_refresh_token")
     * @Method("POST")
     * @ApiDoc(
     *  resource=true,
     *  section=" Mobile Api",
     *  description="Get new token using refresh token",
     *  filters={},
     *  requirements={},
     *  parameters={
     *      {"name"="refresh_token", "dataType"="string", "required"=true, "description"="Refresh token used to create new token"},
     *  },
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function mobileRefreshToken(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["refresh_token"]) || empty($p["refresh_token"])) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('Refresh token is empty'))
            );
        }

        /** @var ApiAccessEntity $token */
        $token = $this->helperManager->regenerateToken($p["refresh_token"]);
        if (empty($token)) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('Token does not exist'))
            );
        }

        $ret = array();

        $ret["token"] = $token->getToken();
        $ret["refresh_token"] = $token->getRefreshToken();

        return new JsonResponse(array('error' => false, 'data' => $ret));
    }

    /**
     * @Route("/mobile_api/mobile_update_customer", name="mobile_update_customer")
     * @Method("POST")
     * @ApiDoc(
     *  resource=true,
     *  section=" Mobile Api",
     *  description="Update customer data",
     *  filters={},
     *  requirements={},
     *  parameters={
     *      {"name"="token", "dataType"="string", "required"=true, "description"="User token"},
     *      {"name"="email", "dataType"="string", "required"=true, "description"="Email"},
     *      {"name"="first_name", "dataType"="string", "required"=true, "description"="First name"},
     *      {"name"="last_name", "dataType"="string", "required"=true, "description"="Last name"},
     *      {"name"="date_of_birth", "dataType"="string", "required"=true, "description"="yyyy-mm-dd"},
     *      {"name"="password", "dataType"="string", "required"=false, "description"="Password"},
     *      {"name"="repeat_password", "dataType"="string", "required"=false, "description"="Repeat password"},
     *  },
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function mobileUpdateCustomer(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        $p = array_map('trim', $p);

        if (!isset($p["token"]) || empty($p["token"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Token is empty')));
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getUserByToken($request, $p["token"]);
        if (empty($coreUser)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'token_rebuild' => true,
                    'message' => $this->translator->trans('Token not valid'),
                )
            );
        }

        /** @var ContactEntity $contact */
        $contact = $coreUser->getDefaultContact();
        if (empty($contact)) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('User does not exist'))
            );
        }

        if (!isset($p["email"]) || empty($p["email"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing email")));
        }
        if (!filter_var($p["email"], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid email")));
        }
        if (!isset($p["password"]) && !empty($p["password"]) && !isset($p["repeat_password"]) && !empty($p["repeat_password"])) {
            if ($p["password"] != $p["repeat_password"]) {
                return new JsonResponse(
                    array('error' => true, 'message' => $this->translator->trans("Passwords do not match"))
                );
            }
            if (strlen($p["password"]) < 6 || strlen($p["repeat_password"]) < 6) {
                return new JsonResponse(
                    array(
                        'error' => true,
                        'message' => $this->translator->trans("Minimal password length is 6 characters"),
                    )
                );
            }
        } else {
            if (isset($p["password"])) {
                unset($p["password"]);
            }
            if (isset($p["repeat_password"])) {
                unset($p["repeat_password"]);
            }
        }
        if (!isset($p["first_name"]) || empty($p["first_name"])) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Missing first name"))
            );
        }
        if (!isset($p["last_name"]) || empty($p["last_name"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing last name")));
        }
        if (!isset($p["date_of_birth"]) || empty($p["date_of_birth"])) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Missing date of birth"))
            );
        } else {
            if (strpos($p["date_of_birth"], " ") !== false) {
                $date = explode(" ", $p["date_of_birth"]);
                $p["date_of_birth"] = \DateTime::createFromFormat('Y-m-d', $date[0]);
            } else {
                $p["date_of_birth"] = \DateTime::createFromFormat('Y-m-d', $p["date_of_birth"]);
            }
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $this->accountManager->updateAccount($contact->getAccount(), $p);
        $contact = $this->accountManager->updateContact($contact, $p);

        $p["id"] = $coreUser->getId();
        $this->accountManager->updateUser($p);

        if (empty($this->apiMobileManager)) {
            $this->apiMobileManager = $this->container->get("api_mobile_manager");
        }

        $ret = $this->apiMobileManager->getContactDataArray($contact);

        return new JsonResponse(array('error' => false, 'data' => $ret));
    }

    /**
     * @Route("/mobile_api/mobile_create_customer", name="mobile_create_customer")
     * @Method("POST")
     * @ApiDoc(
     *  resource=true,
     *  section=" Mobile Api",
     *  description="Create new customer",
     *  filters={},
     *  requirements={},
     *  parameters={
     *      {"name"="email", "dataType"="string", "required"=true, "description"="Email"},
     *      {"name"="first_name", "dataType"="string", "required"=true, "description"="First name"},
     *      {"name"="last_name", "dataType"="string", "required"=true, "description"="Last name"},
     *      {"name"="date_of_birth", "dataType"="string", "required"=true, "description"="yyyy-mm-dd"},
     *      {"name"="password", "dataType"="string", "required"=true, "description"="Password"},
     *      {"name"="repeat_password", "dataType"="string", "required"=true, "description"="Repeat password"},
     *  },
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function mobileCreateCustomer(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        $p = array_map('trim', $p);

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
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Missing repeat password"))
            );
        }
        if ($p["password"] != $p["repeat_password"]) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Passwords do not match"))
            );
        }
        if (strlen($p["password"]) < 6 || strlen($p["repeat_password"]) < 6) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Minimal password length is 6 characters"))
            );
        }
        if (!isset($p["first_name"]) || empty($p["first_name"])) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Missing first name"))
            );
        }
        if (!isset($p["last_name"]) || empty($p["last_name"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing last name")));
        }
        if (!isset($p["date_of_birth"]) || empty($p["date_of_birth"])) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Missing date of birth"))
            );
        } else {
            if (strpos($p["date_of_birth"], " ") !== false) {
                $date = explode(" ", $p["date_of_birth"]);
                $p["date_of_birth"] = \DateTime::createFromFormat('Y-m-d', $date[0]);
            } else {
                $p["date_of_birth"] = \DateTime::createFromFormat('Y-m-d', $p["date_of_birth"]);
            }
        }
        $p["is_active"] = 1;

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $coreUser = $this->accountManager->getCoreUserByEmail($p["email"]);
        if (!empty($coreUser)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'message' => $this->translator->trans(
                        'This email is already used, please reset your password if necessary'
                    ),
                    'open_login_modal' => true,
                )
            );
        }

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->getContactByEmail($p["email"]);

        if (!empty($contact)) {

            /** @var AccountEntity $account */
            $account = $contact->getAccount();

            if ($contact->getFirstName() != $p["first_name"]) {
                return new JsonResponse(
                    array(
                        'error' => true,
                        'message' => $this->translator->trans(
                            'This email is already used, please reset your password if necessary'
                        ),
                        'open_login_modal' => true,
                    )
                );
            }
            if ($p["is_legal_entity"] && $account->getIsLegalEntity() && $account->getOib() != $p["oib"]) {
                return new JsonResponse(
                    array(
                        'error' => true,
                        'message' => $this->translator->trans(
                            'This email is already used, please reset your password if necessary'
                        ),
                        'open_login_modal' => true,
                    )
                );
            }
        }

        $coreUser = $this->accountManager->getCoreUserByEmail($p["email"]);
        if (!empty($coreUser)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'message' => $this->translator->trans(
                        'This email is already used, please reset your password if necessary'
                    ),
                    'open_login_modal' => true,
                )
            );
        }

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
                return new JsonResponse(
                    array(
                        'error' => true,
                        'message' => $this->translator->trans('Error creating contact, please try again'),
                    )
                );
            }

            $ret = $this->accountManager->createUser($p, false, true);
            if ($ret["error"]) {
                return new JsonResponse(array('error' => true, 'message' => $ret["message"]));
            }

            $contact->setCoreUser($ret["core_user"]);
            $this->accountManager->save($contact);

            $res = $this->helperManager->loginAnonymus($request, $p["email"], $p["password"]);
            if (!$res) {
                return new JsonResponse(
                    array('error' => true, 'message' => $this->translator->trans('Username or password incorrect'))
                );
            }

            /** @var CoreUserEntity $user */
            $user = $this->helperManager->getCurrentCoreUser();
            if (empty($user)) {
                return new JsonResponse(
                    array('error' => true, 'message' => $this->translator->trans('User does not exist'))
                );
            }

            /** @var ApiAccessEntity $token */
            $token = $this->helperManager->getTokenByUser($user);
            if (empty($token)) {
                return new JsonResponse(
                    array('error' => true, 'message' => $this->translator->trans('Token does not exist'))
                );
            }

            $ret = array();

            $ret["token"] = $token->getToken();
            $ret["refresh_token"] = $token->getRefreshToken();

            return new JsonResponse(
                array(
                    'error' => false,
                    'data' => $ret,
                    'message' => $this->translator->trans('Your user has been created'),
                )
            );
        }

        $p["last_contact_date"] = new \DateTime();

        $p["lead_source"] = $this->accountManager->getLeadSourceById(CrmConstants::EXTERAL_REFERAL);
        $p["lead_status"] = $this->accountManager->getLeadStatusById(CrmConstants::LEAD_STATUS_NOT_CONTACTED);

        /** @var AccountEntity $account */
        $account = $this->accountManager->insertAccount("lead", $p);
        if (empty($account)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'message' => $this->translator->trans('Error creating account, please try again'),
                )
            );
        }

        $p["account"] = $account;

        $contacts = $this->accountManager->getContactsByAccount($account);
        /** @var ContactEntity $contact */
        $contact = $contacts[0];
        if (empty($contact)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'message' => $this->translator->trans('Error creating contact, please try again'),
                )
            );
        }

        if (!empty($p["date_of_birth"])) {
            $contact->setDateOfBirth($p["date_of_birth"]);
            $contact->setIsActive(1);
            $this->accountManager->save($contact);
        }

        $ret = $this->accountManager->createUser($p, false, true);
        if ($ret["error"]) {
            return new JsonResponse(array('error' => true, 'message' => $ret["message"]));
        }

        $contact->setCoreUser($ret["core_user"]);
        $this->accountManager->save($contact);

        $res = $this->helperManager->loginAnonymus($request, $p["email"], $p["password"]);
        if (!$res) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('Username or password incorrect'))
            );
        }

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();
        if (empty($user)) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('User does not exist'))
            );
        }

        /** @var ApiAccessEntity $token */
        $token = $this->helperManager->getTokenByUser($user);
        if (empty($token)) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('Token does not exist'))
            );
        }

        $ret = array();

        $ret["token"] = $token->getToken();
        $ret["refresh_token"] = $token->getRefreshToken();

        return new JsonResponse(
            array('error' => false, 'data' => $ret, 'message' => $this->translator->trans('Your user has been created'))
        );
    }

    /**
     * @Route("/mobile_api/google_create_customer", name="mobile_api_google_create_customer")
     * @Method("POST")
     * @ApiDoc(
     *  resource=true,
     *  section=" Mobile Api",
     *  description="Create new customer from google sign in",
     *  filters={},
     *  requirements={},
     *  parameters={
     *      {"name"="email", "dataType"="string", "required"=true, "description"="Email"},
     *      {"name"="displayName", "dataType"="string", "required"=true, "description"="Display name"},
     *      {"name"="id", "dataType"="string", "required"=true, "description"="Google ID"},
     *      {"name"="photoUrl", "dataType"="string", "required"=false, "description"="Photo URL"},
     *  },
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function googleCreateCustomer(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        $p = array_map('trim', $p);

        if (!isset($p["is_legal_entity"]) || empty($p["is_legal_entity"])) {
            $p["is_legal_entity"] = 0;
        }
        if (!isset($p["email"]) || empty($p["email"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing email")));
        }
        if (!filter_var($p["email"], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid email")));
        }
        if (!isset($p["displayName"]) || empty($p["displayName"])) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Missing displayName"))
            );
        } else {
            $p["displayName"] = explode(" ", $p["displayName"]);
            $p["displayName"] = array_map('trim', $p["displayName"]);

            $p["first_name"] = $p["displayName"][0];
            unset($p["displayName"][0]);
            $p["last_name"] = implode(" ", $p["displayName"]);

            $p["password"] = $p["repeat_password"] = StringHelper::generateRandomString(6);

        }
        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans("Missing google ID"))
            );
        }
        $p["is_active"] = 1;

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $coreUser = $this->accountManager->getCoreUserByEmail($p["email"]);
        if (!empty($coreUser)) {

            $res = $this->helperManager->loginAnonymus($request, $p["email"]);
            if (!$res) {
                return new JsonResponse(
                    array('error' => true, 'message' => $this->translator->trans('Username or password incorrect'))
                );
            }

            /** @var CoreUserEntity $user */
            $user = $this->helperManager->getCurrentCoreUser();
            if (empty($user)) {
                return new JsonResponse(
                    array('error' => true, 'message' => $this->translator->trans('User does not exist'))
                );
            }

            /** @var ApiAccessEntity $token */
            $token = $this->helperManager->getTokenByUser($user);
            if (empty($token)) {
                return new JsonResponse(
                    array('error' => true, 'message' => $this->translator->trans('Token does not exist'))
                );
            }

            $ret = array();

            $ret["token"] = $token->getToken();
            $ret["refresh_token"] = $token->getRefreshToken();

            return new JsonResponse(
                array(
                    'error' => false,
                    'data' => $ret,
                    'message' => $this->translator->trans('Google login successful'),
                )
            );
        }

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->getContactByEmail($p["email"]);
        if (!empty($contact)) {

            //todo fix google id
            unset($p["id"]);

            $contactData = $p;
            if (isset($contactData["password"])) {
                unset($contactData["password"]);
            }

            /** @var ContactEntity $contact */
            $contact = $this->accountManager->insertContact($contactData);
            if (empty($contact)) {
                return new JsonResponse(
                    array(
                        'error' => true,
                        'message' => $this->translator->trans('Error creating contact, please try again'),
                    )
                );
            }

            $ret = $this->accountManager->createUser($p, false, true);
            if ($ret["error"]) {
                return new JsonResponse(array('error' => true, 'message' => $ret["message"]));
            }

            $contact->setCoreUser($ret["core_user"]);
            $this->accountManager->save($contact);

            $res = $this->helperManager->loginAnonymus($request, $p["email"]);
            if (!$res) {
                return new JsonResponse(
                    array('error' => true, 'message' => $this->translator->trans('Username or password incorrect'))
                );
            }

            /** @var CoreUserEntity $user */
            $user = $this->helperManager->getCurrentCoreUser();
            if (empty($user)) {
                return new JsonResponse(
                    array('error' => true, 'message' => $this->translator->trans('User does not exist'))
                );
            }

            /** @var ApiAccessEntity $token */
            $token = $this->helperManager->getTokenByUser($user);
            if (empty($token)) {
                return new JsonResponse(
                    array('error' => true, 'message' => $this->translator->trans('Token does not exist'))
                );
            }

            $ret = array();

            $ret["token"] = $token->getToken();
            $ret["refresh_token"] = $token->getRefreshToken();

            return new JsonResponse(
                array(
                    'error' => false,
                    'data' => $ret,
                    'message' => $this->translator->trans('Your user has been created'),
                )
            );
        }

        $p["last_contact_date"] = new \DateTime();

        $p["lead_source"] = $this->accountManager->getLeadSourceById(CrmConstants::EXTERAL_REFERAL);
        $p["lead_status"] = $this->accountManager->getLeadStatusById(CrmConstants::LEAD_STATUS_NOT_CONTACTED);

        /** @var AccountEntity $account */
        $account = $this->accountManager->insertAccount("lead", $p);
        if (empty($account)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'message' => $this->translator->trans('Error creating account, please try again'),
                )
            );
        }

        $p["account"] = $account;

        $contacts = $this->accountManager->getContactsByAccount($account);
        /** @var ContactEntity $contact */
        $contact = $contacts[0];
        if (empty($contact)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'message' => $this->translator->trans('Error creating contact, please try again'),
                )
            );
        }

        if (!empty($p["date_of_birth"])) {
            $contact->setDateOfBirth($p["date_of_birth"]);
            $contact->setIsActive(1);
            $this->accountManager->save($contact);
        }

        $ret = $this->accountManager->createUser($p, false, true);
        if ($ret["error"]) {
            return new JsonResponse(array('error' => true, 'message' => $ret["message"]));
        }

        $contact->setCoreUser($ret["core_user"]);
        $this->accountManager->save($contact);

        $res = $this->helperManager->loginAnonymus($request, $p["email"]);
        if (!$res) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('Username or password incorrect'))
            );
        }

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();
        if (empty($user)) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('User does not exist'))
            );
        }

        /** @var ApiAccessEntity $token */
        $token = $this->helperManager->getTokenByUser($user);
        if (empty($token)) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('Token does not exist'))
            );
        }

        $ret = array();

        $ret["token"] = $token->getToken();
        $ret["refresh_token"] = $token->getRefreshToken();

        return new JsonResponse(
            array('error' => false, 'data' => $ret, 'message' => $this->translator->trans('Your user has been created'))
        );
    }

    /**
     * @Route("/mobile_api/get_user_data", name="mobile_get_user_data")
     * @Method("POST")
     * @ApiDoc(
     *  resource=true,
     *  section=" Mobile Api",
     *  description="Get user data by current token",
     *  filters={},
     *  requirements={},
     *  parameters={
     *      {"name"="token", "dataType"="string", "required"=true, "description"="User token"},
     *  },
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function mobileGetUserData(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["token"]) || empty($p["token"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Token is empty')));
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getUserByToken($request, $p["token"]);
        if (empty($coreUser)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'token_rebuild' => true,
                    'message' => $this->translator->trans('Token not valid'),
                )
            );
        }

        /** @var ContactEntity $contact */
        $contact = $coreUser->getDefaultContact();
        if (empty($contact)) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('User does not exist'))
            );
        }

        if (empty($this->apiMobileManager)) {
            $this->apiMobileManager = $this->container->get("api_mobile_manager");
        }

        $ret = $this->apiMobileManager->getContactDataArray($contact);

        return new JsonResponse(array('error' => false, 'data' => $ret));
    }

    /**
     * @Route("/mobile_api/get_menu", name="mobile_api_get_menu")
     * @Method("POST")
     */
    public function getMenuAction(Request $request)
    {

        $this->initialize();

        $p = $_POST;

        if (!isset($p["store_id"]) || empty($p["store_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Store id missing')));
        }
        $coreUser = null;
        if (isset($p["token"]) && !empty($p["token"])) {
            /** @var CoreUserEntity $coreUser */
            $coreUser = $this->helperManager->getUserByToken($request, $p["token"]);
            if (empty($coreUser)) {
                return new JsonResponse(
                    array(
                        'error' => true,
                        'token_rebuild' => true,
                        'message' => $this->translator->trans('Token not valid'),
                    )
                );
            }
        }

        if (empty($this->menuManager)) {
            $this->menuManager = $this->container->get("menu_manager");
        }

        //todo ovo treba rjesiti drugaciej
        $code = "main-menu-igralista";

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        /** @var SStoreEntity $store */
        $store = $this->routeManager->getStoreById($p["store_id"]);
        if (empty($store)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'message' => $this->translator->trans('Missing store'),
                )
            );
        }

        /** @var SMenuEntity $menu */
        $menu = $this->menuManager->getMenuByCode($code, $store);

        if (empty($menu)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'message' => $this->translator->trans('Menu does not exist'),
                )
            );
        }

        $menuItems = $this->menuManager->getMenuItemsArray($menu);


        return new JsonResponse(
            array(
                'error' => false,
                'data' => $menuItems,
            )
        );
    }

    /**
     * @Route("/mobile_api/get_page", name="mobile_api_get_page")
     * @Method("POST")
     */
    public function getPageAction(Request $request)
    {

        $this->initialize();

        $p = $_POST;

        if (!isset($p["url"]) || empty($p["url"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Url is empty')));
        }

        $coreUser = null;
        if (isset($p["token"]) && !empty($p["token"])) {
            /** @var CoreUserEntity $coreUser */
            $coreUser = $this->helperManager->getUserByToken($request, $p["token"]);
            if (empty($coreUser)) {
                return new JsonResponse(
                    array(
                        'error' => true,
                        'token_rebuild' => true,
                        'message' => $this->translator->trans('Token not valid'),
                    )
                );
            }
        }

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        $_SERVER["REQUEST_URI"] = $p["url"];
        $newRequest = $request->duplicate($request->getQueryString(), null, null, null, null, $_SERVER);

        $route = $this->routeManager->prepareRoute($newRequest);

        $data = $route["data"];

        if (isset($route["redirect_type"])) {
            return new JsonResponse(
                array(
                    'error' => false,
                    'redirect' => true,
                    'redirect_url' => $route["redirect_url"],
                    'message' => $this->translator->trans('Missing page'),
                )
            );
        } elseif (isset($route["not_found_exception"]) || !isset($data["page"])) {
            return new JsonResponse(
                array(
                    'error' => false,
                    'redirect' => true,
                    'redirect_url' => '404',
                    'message' => $this->translator->trans('Missing page'),
                )
            );
        }

        $content = json_decode($data["page"]->getTemplateType()->getContent(), true);
        $data["content"] = $this->helperManager->prepareBlockGrid($content);

        return new JsonResponse(
            array(
                'error' => false,
                'redirect' => false,
                'redirect_url' => false,
                'data' => $data
            )
        );
    }

    /**
     * @Route("/mobile_api/get_products", name="mobile_api_get_products")
     * @Method("POST")
     */
    public function getProductsAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["store_id"]) || empty($p["store_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Store id missing')));
        }
        /** @var CoreUserEntity $coreUser */
        $coreUser = null;
        /** @var AccountEntity $account */
        $account = null;
        if (isset($p["token"]) && !empty($p["token"])) {
            /** @var CoreUserEntity $coreUser */
            $coreUser = $this->helperManager->getUserByToken($request, $p["token"]);
            if (empty($coreUser)) {
                return new JsonResponse(
                    array(
                        'error' => true,
                        'token_rebuild' => true,
                        'message' => $this->translator->trans('Token not valid'),
                    )
                );
            }

            $account = $coreUser->getDefaultAccount();
        }


        $session = $request->getSession();
        $session->set("store", $p["store_id"]);

        $noIndex = FALSE;

        /** @var ProductGroupManager $productGroupManager */
        $productGroupManager = $this->container->get("product_group_manager");

        $isSearch = false;
        $productGroup = null;
        $sessionSufix = "";

        if (isset($p["s"]) && !empty($p["s"])) {
            $isSearch = true;
            $sessionSufix = "search";
        } else if (isset($p["product_group"]) && !empty($p["product_group"])) {
            $sessionSufix = $p["product_group"];
        } else {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid query")));
        }

        if (!isset($p["product_group"])) {
            $p["product_group"] = null;
        }

        if (!isset($p["get_all_products"])) {
            $p["get_all_products"] = 1;
        }

        /** Defaults */
        if (!isset($p["page_number"]) || empty($p["page_number"])) {
            $p["page_number"] = 1;
        }

        if (!isset($p["sort_dir"]) || empty($p["sort_dir"])) {
            $p["sort_dir"] = "desc";
        }

        $p["filter"] = null;

        if (!isset($p["f"])) {
            $p["f"] = null;
        } else {
            $p["filter"] = $productGroupManager->prepareFilterParams($p, "f");
        }

        if (!isset($p["sort"])) {
            $p["sort"] = null;
        }

        /**
         * Get default page sizes
         */
        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $defaultPageSize = $_ENV["PRODUCT_GRID_DEFAULT_PAGE_SIZE"];
        $availablePageSizes = explode(",", $_ENV["PRODUCT_GRID_AVAILABLE_PAGE_SIZE"]);

        if (!isset($p["page_size"]) || empty($p["page_size"])) {
            $p["page_size"] = $defaultPageSize;
        } else {
            if (!in_array($defaultPageSize, $availablePageSizes)) {
                $p["page_size"] = $defaultPageSize;
            }
        }

        $p["get_filter"] = true;
        $originalQuery = null;

        if ($isSearch) {

            $searchProductGroup = $productGroupManager->getProductGroupByUrl("search-results-placeholder");

            $sortOptions = $productGroupManager->getSortOptions($p["sort"], $searchProductGroup);
            foreach ($sortOptions as $sortOption) {
                if ($sortOption["selected"]) {
                    $p["sort"] = $sortOption["sort"];
                    break;
                }
            }

            $params = $productGroupManager->prepareFilterParams($p, "s", "f");

            $p["ids"] = null;
            if (array_key_exists("keyword", $params)) {

                /** @var SsearchManager $sSearchManager */
                $sSearchManager = $this->container->get("s_search_manager");

                $originalQuery = $productGroupManager->cleanSearchParams($params["keyword"][0]);
                $query = $sSearchManager->prepareQuery($params["keyword"][0]);
                $query = $productGroupManager->cleanSearchParams($query);

                if (empty($query)) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Empty query")));
                }

                /** @var SProductSearchResultsEntity $search */
                $search = $sSearchManager->getExistingSearchResult($query, $session->get("current_store_id"));

                $date = new \DateTime();
                $date->modify("-1 day");

                if (!empty($search) && $search->getLastRegenerateDate() > $date) {
                    $p["ids"] = json_decode($search->getListOfResults(), true);
                } else {
                    $p["ids"] = $productGroupManager->searchProducts($query, $p);

                    /** @var SProductSearchResultsEntity $search */
                    $search = $sSearchManager->createSearch($originalQuery, $query, $session->get("current_store_id"), $search);
                }

                $search->setTimesUsed(intval($search->getTimesUsed()) + 1);
                $search->setNumberOfResults(count($p["ids"]));
                $search->setListOfResults(json_encode($p["ids"]));

                $sSearchManager->save($search);

                unset($params["keyword"]);
            }

            if (!empty($params)) {
                //todo params presloiz u prefilter i posearhcat tako
                //$p["ids"] = $productGroupManager->advancedSearchProducts($params, $p);
            }

            $ret = array();
            $ret["error"] = false;
            $ret["total"] = 0;
            $ret["filter_data"] = array();
            $ret['entities'] = array();

            if (!empty($p["ids"])) {
                $ret = $productGroupManager->getFilteredProducts($p);
            }
        } else {
            /** @var ProductGroupEntity $productGroup */
            $productGroup = $productGroupManager->getProductGroupById($p["product_group"]);
            if (empty($productGroup)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product group does not exist")));
            }

            $sortOptions = $productGroupManager->getSortOptions($p["sort"], $productGroup);
            foreach ($sortOptions as $sortOption) {
                if ($sortOption["selected"]) {
                    $p["sort"] = $sortOption["sort"];
                    break;
                }
            }

            $ret = $productGroupManager->getFilteredProducts($p);
        }

        if (!isset($ret["show_index"])) {
            $ret["show_index"] = false;
        }
        if (!isset($ret["index"]) || $noIndex) {
            $ret["index"] = 0;
        }

        $filter = array(
            'primary_filters' => $ret["filter_data"]['primary'] ?? NULL,
            'secondary_filters' => $ret["filter_data"]['secondary'] ?? NULL,
            'index' => $ret["index"]
        );

        /*if(isset($filter['primary_filters']) && !empty($filter['primary_filters'])){
            foreach ($filter['primary_filters'] as $fkey => $filterData){
                $tmp = Array();
                $tmp["id"] = null;
                $tmp["name"] = $filterData["attribute_configuration"]->getFrontendLabel();
                $tmp["remoteId"] = null;
                $tmp["prefix"] = null;
                $tmp["sufix"] = null;
                $tmp["is_active"] = 1;
                $tmp["show_in_filter"] = 1;
                $tmp["show_in_list"] = 0;
                $tmp["filter_template"] = "default";
                $tmp["list_view_template"] = "default";
                $tmp["filter_key"] = $filterData["attribute_configuration"]->getAttributeCode();
                $tmp["additional_params"] = null;
                $tmp["s_product_attribute_configuration_type_id"] = 1;

                $filter['primary_filters'][$fkey]["attribute_configuration"] = $tmp;
            }
        }*/

        if (isset($filter['secondary_filters']) && !empty($filter['secondary_filters'])) {
            foreach ($filter['secondary_filters'] as $fkey => $filterData) {

                if (!$filterData["attribute_configuration"]["is_active"] || !$filterData["attribute_configuration"]["show_in_filter"]) {
                    unset($filter['secondary_filters'][$fkey]);
                    continue;
                }

                $tmp = array();
                $tmp["id"] = $filterData["attribute_configuration"]["id"];
                $tmp["name"] = $filterData["attribute_configuration"]["name"];
                $tmp["remoteId"] = $filterData["attribute_configuration"]["remote_id"];
                $tmp["prefix"] = $filterData["attribute_configuration"]["prefix"];
                $tmp["sufix"] = $filterData["attribute_configuration"]["sufix"];
                $tmp["is_active"] = $filterData["attribute_configuration"]["is_active"];
                $tmp["show_in_filter"] = $filterData["attribute_configuration"]["show_in_filter"];
                $tmp["show_in_list"] = $filterData["attribute_configuration"]["show_in_list"];
                $tmp["filter_template"] = $filterData["attribute_configuration"]["filter_template"];
                $tmp["list_view_template"] = $filterData["attribute_configuration"]["list_view_template"];
                $tmp["filter_key"] = $filterData["attribute_configuration"]["filter_key"];
                $tmp["additional_params"] = $filterData["attribute_configuration"]["additional_params"];
                $tmp["s_product_attribute_configuration_type_id"] = $filterData["attribute_configuration"]["s_product_attribute_configuration_type_id"];

                $filter['secondary_filters'][$fkey]["attribute_configuration"] = $tmp;
            }
        }

        $pager = array();
        $sort = array();
        $hasNextPage = FALSE;
        $products = array();

        if (empty($this->routeManager)) {
            $this->routeManager = $this->getContainer()->get("route_manager");
        }

        /** @var SStoreEntity $store */
        $store = $this->routeManager->getStoreById($p["store_id"]);
        /** @var SWebsiteEntity $website */
        $website = $store->getWebsite();

        $settings["base_url"] = $baseUrl = $_ENV["SSL"] . "://" . $website->getBaseUrl() . $_ENV["FRONTEND_URL_PORT"];
        $settings["web_path"] = $basePath = $_ENV["WEB_PATH"];

        $referer["page"]["url"] = $request->headers->get('referer');

        if (isset($referer["page"]["url"]) && !empty($referer["page"]["url"])) {
            $referer["page"]["url"] = explode(".hr/", $referer["page"]["url"]);
            if (isset($referer["page"]["url"][1])) {
                $referer["page"]["url"] = $referer["page"]["url"][1];
                $referer["page"]["url"] = str_replace('&', '|||', $referer["page"]["url"]);
            } else {
                $referer["page"]["url"] = null;
            }
        }

        if (!empty($ret['entities'])) {
            if (!isset($sortOptions) || empty($sortOptions)) {
                $sortOptions = $productGroupManager->getSortOptions($p["sort"] . "_" . $p["sort_dir"], $productGroup);
            }
            $pageSizeOptions = $productGroupManager->preparePageSizeOptions($availablePageSizes, $p["page_size"]);
            $hasNextPage = $productGroupManager->calculateIfNextPageExists($p, $ret["total"]);

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }
            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $products = array();
            /** @var ProductEntity $entity */
            foreach ($ret['entities'] as $entity) {
                $tmp = $this->entityManager->entityToArray($entity, false);
                $tmp["attributes"] = array();
                $tmpAttributes = $entity->getPreparedProductAttributes();
                if (EntityHelper::isCountable($tmpAttributes) && count($tmpAttributes) > 0) {
                    foreach ($tmpAttributes as $tmpAttribute) {
                        $tmpAttribute["attribute"] = $this->entityManager->entityToArray($tmpAttribute["attribute"], false);
                        $tmp["attributes"][] = $tmpAttribute;
                    }
                }
                $tmp["qty"] = $entity->getPreparedQty();
                $tmp["brand"] = null;
                if (!empty($entity->getBrand())) {
                    $tmp["brand"] = $entity->getBrand()->getName();
                }
                $tmp["prices"] = $this->crmProcessManager->getProductPrices($entity, $account);
                $tmp["warehouses"] = array();
                $tmpProductWarehouses = $entity->getProductWarehouses();
                if (EntityHelper::isCountable($tmpProductWarehouses) && count($tmpProductWarehouses) > 0) {
                    /** @var ProductWarehouseLinkEntity $tmpProductWarehouse */
                    foreach ($tmpProductWarehouses as $tmpProductWarehouse) {
                        $tmpWarehouse = array();
                        $tmpWarehouse["warehouse"] = $this->entityManager->entityToArray($tmpProductWarehouse->getWarehouse(), false);
                        $tmpWarehouse["qty"] = $tmpProductWarehouse->getQty();
                        $tmp["warehouses"][] = $tmpWarehouse;
                    }
                }

                $tmp["images"] = array();
                $images = $entity->getImages();
                if (EntityHelper::isCountable($images) && count($images) > 0) {
                    /** @var ProductImagesEntity $image */
                    foreach ($images as $image) {
                        $tmpImage = array();
                        if (file_exists($basePath . "/image_style/product_image/Documents/Products/" . $image->getFile())) {
                            $tmpImage["src"] = $baseUrl . "/image_style/product_image/Documents/Products/" . $image->getFile();
                        } else {
                            $tmpImage["src"] = $baseUrl . "/Documents/Products/" . $image->getFile();
                        }
                        $tmpImage["ord"] = $image->getOrd();
                        $tmp["images"][] = $tmpImage;
                    }
                }

                if (empty($this->defaultScommerceManager)) {
                    $this->defaultScommerceManager = $this->getContainer()->get("scommerce_manager");
                }

                $tmp = $this->defaultScommerceManager->apiProductExtension($entity, $tmp, $settings);

                $products[] = $tmp;
            }
            /*$html = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:product_list.html.twig"), [
                'products' => $ret['entities'],
                'data' => $referer
            ]);*/
            $pager = array(
                'page_size_options' => $pageSizeOptions,
                'total' => $ret["total"],
                'page_number' => $p["page_number"],
            );
            $sort = array(
                'sort_options' => $sortOptions,
                'page_size_options' => $pageSizeOptions,
                'is_search' => $isSearch,
                'keyword' => $originalQuery,
                'total' => $ret["total"],
            );
        } else {

            $products = array();
        }

        //ako je $ret["error"] = true
        //todo $html staviti error html
        //ovo se moze dogoditi samo ako netko proba otici na page 100 koji ne postoji

        return new JsonResponse(array(
            'error' => $ret["error"],
            'total' => $ret["total"],
            'products' => $products,
            'pager_html' => $pager,
            'filter_html' => $filter,
            'sort_html' => $sort,
            'has_next_page' => $hasNextPage,
            'keyword' => $originalQuery,
            'show_index' => $ret["show_index"],
            'index' => $ret["index"]
        ));
    }

    public function addToCartAction()
    {

    }

    public function getCartAction()
    {

    }


}
