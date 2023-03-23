<?php

namespace SanitarijeAdminBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Entity\ApiAccessEntity;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ErrorLogManager;
use CrmBusinessBundle\Managers\ProductManager;
use SanitarijeBusinessBundle\Managers\SanitarijeHelperManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

class MobileApiController extends AbstractController
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;
    /** @var SanitarijeHelperManager $sanitarijeHelperManager */
    protected $sanitarijeHelperManager;
    /** @var ProductManager $productManager */
    protected $productManager;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->get("entity_manager");
        $this->errorLogManager = $this->getContainer()->get("error_log_manager");
    }

    /**
     * @Route("/service/api/ping", name="mobile_ping")
     * @Method("GET")
     * @ApiDoc(
     *  resource=true,
     *  section="Mobile Api",
     *  description="Ping api",
     *  filters={},
     *  requirements={},
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function mobilePing()
    {
        return new JsonResponse(array("error" => false, "data" => "pong"));
    }

    /**
     * @Route("/service/api/login", name="mobile_login")
     * @Method("POST")
     * @ApiDoc(
     *  resource=true,
     *  section="Mobile Api",
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
    public function mobileLogin(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["username"]) || empty($p["username"])) {
            return new JsonResponse(array("error" => true, "code" => 101, "message" => $this->translator->trans("Username is empty")));
        }
        if (!isset($p["password"]) || empty($p["password"])) {
            return new JsonResponse(array("error" => true, "code" => 102, "message" => $this->translator->trans("Password is empty")));
        }

        $res = $this->helperManager->loginAnonymus($request, $p["username"], $p["password"]);
        if (!$res) {
            return new JsonResponse(array("error" => true, "code" => 103, "message" => $this->translator->trans("Username or password incorrect")), Response::HTTP_UNAUTHORIZED);
        }

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();
        if (empty($user)) {
            return new JsonResponse(array("error" => true, "code" => 104, "message" => $this->translator->trans("User does not exist")), Response::HTTP_UNAUTHORIZED);
        }

        if (!empty($user->getLocked())) {
            return new JsonResponse(array("error" => true, "code" => 105, "message" => $this->translator->trans("User account is locked")), Response::HTTP_UNAUTHORIZED);
        }

        /** @var ApiAccessEntity $token */
        $token = $this->helperManager->getTokenByUser($user);
        if (empty($token)) {
            return new JsonResponse(array("error" => true, "code" => 106, "message" => $this->translator->trans("Token does not exist")), Response::HTTP_UNAUTHORIZED);
        }

        /** @var ApiAccessEntity $token */
        $token = $this->helperManager->regenerateToken($token->getRefreshToken());
        if (empty($token)) {
            return new JsonResponse(array("error" => true, "code" => 106, "message" => $this->translator->trans("Token does not exist")), Response::HTTP_UNAUTHORIZED);
        }

        $ret = array();

        $ret["token"] = $token->getToken();
        $ret["refresh_token"] = $token->getRefreshToken();

        return new JsonResponse(array("error" => false, "data" => $ret));
    }

    /**
     * @Route("/service/api/refresh_token", name="mobile_refresh_token")
     * @Method("POST")
     * @ApiDoc(
     *  resource=true,
     *  section="Mobile Api",
     *  description="Get new token using refresh token",
     *  filters={},
     *  requirements={},
     *  parameters={
     *      {"name"="refresh_token", "dataType"="string", "required"=true, "description"="Refresh token used to create new token"}
     *  },
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function mobileRefreshToken(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["refresh_token"]) || empty($p["refresh_token"])) {
            return new JsonResponse(array("error" => true, "code" => 107, "message" => $this->translator->trans("Refresh token is empty")));
        }

        /** @var ApiAccessEntity $token */
        $token = $this->helperManager->regenerateToken($p["refresh_token"]);
        if (empty($token)) {
            return new JsonResponse(array("error" => true, "code" => 108, "message" => $this->translator->trans("Refresh token does not exist")), Response::HTTP_UNAUTHORIZED);
        }

        $ret = array();

        $ret["token"] = $token->getToken();
        $ret["refresh_token"] = $token->getRefreshToken();

        return new JsonResponse(array("error" => false, "data" => $ret));
    }

    /**
     * @Route("/service/api/trigger_get_product_data", name="mobile_trigger_get_product_data")
     * @Method("POST")
     * @ApiDoc(
     *  resource=true,
     *  section="Mobile Api",
     *  description="Narudzba status",
     *  filters={},
     *  requirements={},
     *  parameters={
     *      {"name"="token", "dataType"="string", "required"=true, "description"="User token"},
     *      {"name"="remote_id", "dataType"="string", "required"=true, "description"=""},
     *  },
     *  output={"collection"=true, "collectionName"="JSON", "class"="JSON"}
     * )
     */
    public function mobileGetProductData(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["token"]) || empty($p["token"])) {
            return new JsonResponse(array("error" => true, "code" => 110, "message" => $this->translator->trans("Token is empty")));
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getUserByToken($request, $p["token"]);
        if (empty($coreUser)) {
            return new JsonResponse(array("error" => true, "code" => 111, "message" => $this->translator->trans("Token is not valid")), Response::HTTP_UNAUTHORIZED);
        }

        $importLogData = array();
        $importLogData['completed'] = 0;
        $importLogData['error_log'] = "";
        $importLogData['params'] = $request->getContent();
        $importLogData['name'] = 'get_product_data';

        $data = [];

        if (!isset($p["remote_id"])) {
            $data[] = "remote_id";
        }

        if (!empty($data)) {
            $importLogData['error_log'] = $this->translator->trans("The following parameters are required");
            //$this->errorLogManager->insertImportLog($importLogData,false);
            return new JsonResponse(array("error" => true, "code" => 112, "message" => $this->translator->trans("The following parameters are required"), "data" => $data));
        }

        if(empty($this->sanitarijeHelperManager)){
            $this->sanitarijeHelperManager = $this->getContainer()->get("sanitarije_helper_manager");
        }

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT id FROM product_entity WHERE remote_id = {$p["remote_id"]};";
        $data = $this->databaseContext->getAll($q);

        if(empty($data)){
            $importLogData['error_log'] = $this->translator->trans("Product does not exist");
            //$this->errorLogManager->insertImportLog($importLogData,false);
            return new JsonResponse(array("error" => true, "code" => 113, "message" => $this->translator->trans("Product does not exist"), "data" => $data));
        }

        if(empty($this->productManager)){
            $this->productManager = $this->getContainer()->get("product_manager");
        }

        $product = $this->productManager->getProductById($data[0]["id"]);

        try {
            $this->sanitarijeHelperManager->updateProductData($product);
        }
        catch (\Exception $e){
            $importLogData['error_log'] = $this->translator->trans("Product does not exist");
            //$this->errorLogManager->insertImportLog($importLogData,false);
            return new JsonResponse(array("error" => true, "code" => 114, "message" => $e->getMessage(), "data" => $data));
        }

        $importLogData['completed'] = 1;
        //$this->errorLogManager->insertImportLog($importLogData,false);

        return new JsonResponse(array("error" => false, "data" => $data));
    }


}