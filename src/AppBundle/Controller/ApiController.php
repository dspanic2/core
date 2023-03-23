<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\ListViewContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\ApiAccessEntity;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\NoteEntity;
use AppBundle\Entity\Page;
use AppBundle\Entity\UserEntity;
use AppBundle\Managers\ApiManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ListViewManager;
use AppBundle\Managers\MailManager;
use AppBundle\Managers\NoteManager;
use CrmBusinessBundle\Managers\AccountManager;
use Exception;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\RestBundle\View\View;
use FOS\UserBundle\Model\UserInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ApiController extends AbstractController
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var ApiManager $apiManager */
    protected $apiManager;
    /** @var ListViewManager $listViewManager */
    protected $listViewManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var NoteManager $noteManager */
    protected $noteManager;
    /** @var ListViewContext $listViewContext */
    protected $listViewContext;
    /** @var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->apiManager = $this->getContainer()->get("api_manager");
        /*$this->listViewManager = $this->getContainer()->get("list_view_manager");
        $this->noteManager = $this->getContainer()->get("note_manager");
        $this->attributeSetContext = $this->getContainer()->get("attribute_set_context");
        $this->listViewContext = $this->getContainer()->get("list_view_context");*/
    }

    /**
     * @Route("api/ping", name="core_mobile_ping")
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
    public function ping()
    {
        return new JsonResponse(array('error' => false, 'data' => "pong"));
    }

    /**
     * @Route("/api/login", name="core_mobile_login")
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
     * )
     */
    public function login(Request $request)
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
     * @Route("/api/reset_user_password", name="core_mobile_reset_user_password")
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

        /** @var $user UserInterface */
        $user = $this->container->get('fos_user.user_manager')->findUserByUsernameOrEmail($p["username"]);

        $data = array("user" => $user, "confirmationUrl" => $confirmationUrl);

        if (empty($this->mailManager)) {
            $this->mailManager = $this->container->get("mail_manager");
        }
        $res = $this->mailManager->sendEmail(
            array('email' => $user->getEmail(), 'name' => $user->getEmail()),
            null,
            null,
            null,
            $this->translator->trans("Reset password"),
            "",
            "mobile_reset_password",
            $data
        );

        if (!$res) {
            return new JsonResponse(
                array('error' => true, 'message' => $this->translator->trans('Username or email incorrect'))
            );
        }

        return new JsonResponse(
            array(
                'error' => false,
                'data' => array(),
                'message' => $this->translator->trans("You will receive an email with a password reset link shortly")
            )
        );
    }

    /**
     * @Route("/api/refresh_token", name="core_mobile_refresh_token")
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
     * @Route("/api/{type}/save/{id}", name="core_api_save_entity")
     */
    public function saveEntity($type, $id = null)
    {
        $this->initialize();

        $array = $_POST;

        foreach ($array as $key => $value) {
            if (stripos($key, "_json") !== false) {
                $newKey = str_ireplace("_json", "", $key);
                $newValue = json_decode($value, true);
                unset($array[$key]);
                $array[$newKey] = $newValue;
            }
        }

        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->entityManager->getAttributeSetByCode($type);

        try {
            if (isset($array['id'])) {
                $entity_id = $array['id'];
            } else {
                $entity_id = null;
            }

            if ($entity_id == "") {
                $entity = $this->entityManager->getNewEntityByAttributSetName($attributeSet);
            } else {
                $entity = $this->entityManager->getEntityByEntityTypeAndId($attributeSet->getEntityType(), $entity_id);
            }

            $entity = $this->entityManager->arrayToEntity($entity, $array);
            $entity = $this->entityManager->saveEntity($entity);

        } catch (Exception $e) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Exception occurred")));
        }

        return new JsonResponse(array("error" => false, "entity" => $entity));
    }

    /**
     * @Route("/api/{type}/delete/{id}", name="core_api_delete_entity")
     */
    public function deleteEntity($type, $id = null)
    {
        $this->initialize();

        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->entityManager->getAttributeSetByCode($type);
        $entity = $this->entityManager->getEntityByEntityTypeAndId($attributeSet->getEntityType(), $id);

        try {
            $this->entityManager->deleteEntity($entity);

        } catch (Exception $e) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Exception occurred")));
        }

        return new JsonResponse(array("error" => false, "message" => $this->translator->trans("Entity successfully deleted")));
    }

    /**
     * @Route("/api/{type}/list/{view}/data", name="core_api_get_list_data")
     */
    public function getViewData(Request $request, $type, $view)
    {
        $this->initialize();

        $listView = $this->listViewContext->getOneBy(array("name" => $view));

        $pager = new DataTablePager();
        $pager->setLenght(100);
        $pager->setStart(0);

        try {
            if (isset($_POST["data"])) {
                $pager->setFromPost($_POST);
            }
            $data = $this->listViewManager->getListViewDataModel($listView, $pager);

            $returnArray = array();

            foreach ($data ?? [] as $item) {
                $returnArray[] = $this->entityManager->entityToArray($item);
            }

        } catch (Exception $e) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Exception occurred")));
        }

        return new JsonResponse(array("error" => false, "data" => $returnArray));
    }

    /**
     * @Route("/api/get/single_entity_data", name="core_get_single_entity")
     * @Method("POST")
     */
    public function getSingleEntityDataAction()
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["token"]) || empty($p["token"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Token is empty")));
        }
        if (!isset($p["entity_type"]) || empty($p["entity_type"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Entity type is empty")));
        }
        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Entity id is empty")));
        }

        $entityType = $this->entityManager->getEntityTypeByCode($p["entity_type"]);
        if (empty($entityType)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Entity type does not exist")));
        }

        $entity = $this->entityManager->getEntityByEntityTypeAndId($entityType, $p["id"]);
        if (empty($entity)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Entity does not exist")));
        }

        $entity = $this->entityManager->entityToArray($entity);

        return new JsonResponse(array("error" => false, "data" => $entity));
    }


    /**
     * @Route("/api/get/page", name="core_get_page")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function getPageAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["token"]) || empty($p["token"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Token is empty")));
        }
        if (!isset($p["page"]) || empty($p["page"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Page is empty")));
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getUserByToken($request, $p["token"]);
        if (empty($coreUser)) {
            return new JsonResponse(
                array(
                    "error" => true,
                    "token_rebuild" => true,
                    "message" => $this->translator->trans("Token not valid")
                )
            );
        }

        /** @var UserEntity $user */
        $user = $this->helperManager->getCurrentUser();

        /** @var Page $page */
        $page = $this->apiManager->getPageById($p["page"]);
        if (empty($page)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Page does not exist")));
        }

        if (!$user->hasPrivilege(5, $page->getUid())) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Permission denied")));
        }

        $pageBlocksArray = $this->apiManager->getPageBlocksArray();

        $blocksContentTree = $this->apiManager->getBlocksContentTree($pageBlocksArray, $page);

        return new JsonResponse(array("error" => false, "data" => $blocksContentTree));
    }

    /**
     * @Route("/api/get/menu", name="core_get_menu")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function getMenuAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["token"]) || empty($p["token"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Token is empty")));
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getUserByToken($request, $p["token"]);
        if (empty($coreUser)) {
            return new JsonResponse(
                array(
                    "error" => true,
                    "token_rebuild" => true,
                    "message" => $this->translator->trans("Token not valid")
                )
            );
        }

        /** @var UserEntity $user */
        $user = $this->helperManager->getCurrentUser();

        $navigationLinkChildrenArray = array();

        $navigationLinksArray = $this->apiManager->getNavigationLinksArray();
        if (!empty($navigationLinksArray)) {
            foreach ($navigationLinksArray as $key => $navigationLink) {
                if (!empty($navigationLink["parent_id"])) {
                    if ($navigationLink["parent_id"] != 999) {
                        $navigationLinkChildrenArray[$navigationLink["parent_id"]][$navigationLink["id"]] = $navigationLink;
                    }
                    unset($navigationLinksArray[$key]);
                }
            }
        }

        $navigationLinksTree = $this->apiManager->getNavigationLinksTree($user, $navigationLinksArray, $navigationLinkChildrenArray);

        return new JsonResponse(array("error" => false, "data" => $navigationLinksTree));
    }

    /**
     * @Route("/api/get/list_view", name="core_get_list_view")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function getListViewAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["token"]) || empty($p["token"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Token is empty")));
        }
        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Listview id is empty")));
        }

        $start = 0;
        if (isset($p["start"]) && !empty($p["start"])) {
            $start = $p["start"];
        }

        $length = 100;
        if (isset($p["length"]) && !empty($p["length"])) {
            $length = $p["length"];
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getUserByToken($request, $p["token"]);
        if (empty($coreUser)) {
            return new JsonResponse(
                array(
                    "error" => true,
                    "token_rebuild" => true,
                    "message" => $this->translator->trans("Token not valid")
                )
            );
        }

        $data = $this->apiManager->getListViewEntities($p, $start, $length);

        return new JsonResponse(array("error" => false, "data" => $data));
    }

    /**
     * @Route("/api/get/notes", name="core_get_notes")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function getNotesAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["token"]) || empty($p["token"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Token is empty")));
        }
        if (!isset($p["related_entity_id"]) || empty($p["related_entity_id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Related entity id is empty")));
        }
        if (!isset($p["related_entity_type"]) || empty($p["related_entity_type"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Related entity type is empty")));
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getUserByToken($request, $p["token"]);
        if (empty($coreUser)) {
            return new JsonResponse(
                array(
                    "error" => true,
                    "token_rebuild" => true,
                    "message" => $this->translator->trans("Token not valid")
                )
            );
        }

        $notes = $this->noteManager->getNotesForEntity($p["related_entity_type"], $p["related_entity_id"]);
        if (empty($notes)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Notes not found")));
        }

        $notesArray = array();

        /**
         * @var $key
         * @var NoteEntity $note
         */
        foreach ($notes as $key => $note) {
            $notesArray[$key] = $this->entityManager->entityToArray($note, false);
        }

        return new JsonResponse(array("error" => false, "data" => $notesArray));
    }
}
