<?php

namespace ScommerceBusinessBundle\Controller;

use AppBundle\Helpers\TrafficHelper;
use AppBundle\Managers\EntityManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use ScommerceBusinessBundle\Entity\SRouteEntity;
use ScommerceBusinessBundle\Entity\SRouteNotFoundEntity;
use ScommerceBusinessBundle\Entity\STemplateTypeEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ScommerceBusinessBundle\Managers\RouteManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DefaultController extends AbstractScommerceController
{
    /**@var RouteManager $routeManager */
    protected $routeManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;
    protected $twigBase;

    protected function initialize($request = null)
    {
        parent::initialize();
    }

    /**
     * @Route("/{url}", defaults={"url"=null}, name="s_route_page", requirements={"url"=".+"})
     * @Method("GET")
     */
    public function pageAction(Request $request)
    {
        $this->initialize();
        $session = $request->getSession();

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get('route_manager');
        }

        /** @var SRouteEntity $route */
        $route = $this->routeManager->prepareRoute($request);

        $data = $route["data"];
        if (isset($route["redirect_type"])) {
            $url = $route["redirect_url"];
            if (stripos($url, "//") !== false) {
                $url = str_ireplace("//", "/", $url);
            }
            if (!empty($route["data"]) && !empty($route["data"]["query"])) {
                $url .= "?" . $route["data"]["query"];
            }
            return $this->redirect($url, $route["redirect_type"]);
        } elseif (isset($route["not_found_exception"]) || !isset($data["page"])) {
            $url404 = $request->getRequestUri();
            throw $this->createNotFoundException('The page ' . $url404 . ' does not exist');
        }

        $this->initializeTwigVariables($request, $data["page"]);

        if (isset($_ENV["REQUIRED_LOGIN"]) && $_ENV["REQUIRED_LOGIN"] == 1) {
            if (empty($session->get("account"))) {
                if (empty($this->getPageUrlExtension)) {
                    $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
                }

                if (empty($this->twigBase)) {
                    $this->twigBase = $this->container->get('twig');
                }

                $globals = $this->twigBase->getGlobals();

                if (isset($globals["current_entity"]) && !empty($globals["current_entity"])) {
                    $currentStoreId = $session->get("current_store_id");
                    $limitLoginByStores = [];
                    if (isset($_ENV["REQUIRED_LOGIN_STORES"]) && !empty($_ENV["REQUIRED_LOGIN_STORES"])) {
                        $limitLoginByStores = explode(",", $_ENV["REQUIRED_LOGIN_STORES"]);
                    }
                    if (empty($limitLoginByStores) || in_array($currentStoreId, $limitLoginByStores)) {
                        $currentPageUrl = $this->getPageUrlExtension->getPageUrl($currentStoreId, $globals["current_entity"]->getId(), "s_page");
                        if (isset($_ENV["REQUIRED_LOGIN_ALLOWED_PAGES"]) && !empty($_ENV["REQUIRED_LOGIN_ALLOWED_PAGES"])) {
                            $ids = explode(",", $_ENV["REQUIRED_LOGIN_ALLOWED_PAGES"]);
                            $loginPages = [];
                            foreach ($ids as $id) {
                                $loginPages[] = $this->getPageUrlExtension->getPageUrl($currentStoreId, intval($id), "s_page");
                            }
                        } else {
                            $loginPages = [
                                $this->getPageUrlExtension->getPageUrl($currentStoreId, 58, "s_page"),
                                $this->getPageUrlExtension->getPageUrl($currentStoreId, 68, "s_page"),
                                $this->getPageUrlExtension->getPageUrl($currentStoreId, 71, "s_page"),
                            ];
                        }
                        if (!in_array($currentPageUrl, $loginPages)) {
                            return new RedirectResponse($loginPages[0]);
                        }
                    }
                }
            }
        }

        $session = $request->getSession();

        $data["content"] = $this->helperManager->prepareBlockGrid($this->routeManager->getPageBlockLayout($data["page"]));
        $data["base_template"] = $this->templateManager->getBaseTemplateBundle($session->get("current_website_id"));

        if ($this->container->has('rules_manager')) {
            $rulesManager = $this->container->get("rules_manager");
            $rulesManager->dispatchRules($data['page'], "entity_is_viewed_rule_event");
        }

        $session->set("last_url", $request->getUri());

        $response = new Response($this->renderView($this->templateManager->getTemplatePathByBundle("Page:{$data["page"]->getTemplateType()->getCode()}.html.twig", $session->get("current_website_id")), array('data' => $data)));
        if ($data["page"]->getTemplateType()->getCode() == "404_page") {
            $response->setStatusCode(404);
        }
        return $response;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function errorAction(Request $request)
    {
        $this->initialize();

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get('route_manager');
        }

        $session = $request->getSession();

        $requestUri = $request->getUri();

        /**
         * Manager route 404
         */
        if(stripos($requestUri,$_ENV["BACKEND_URL"]) === false && $request->get('exception')->getStatusCode() == "404"){
            $storeId = $session->get("current_store_id");
            if(empty($storeId)){
                $storeId = $_ENV["DEFAULT_STORE_ID"];
            }

            $route404data = Array();

            /** @var SRouteNotFoundEntity $route404 */
            $route404 = $this->routeManager->getRoute404($requestUri,$storeId);

            if(!empty($route404)){
                $route404data["number_of_requests"] = intval($route404->getNumberOfRequests())+1;
                $route404data["is_redirected"] = 0;
            }
            else{
                $refferer = $request->server->get('HTTP_REFERER');

                $route404data["request_uri"] = $requestUri;
                $route404data["number_of_requests"] = 1;
                $route404data["is_redirected"] = 0;
                $route404data["google_search_console_processed"] = 0;
                $route404data["referer"] = $refferer;
                $route404data["url_type"] = TrafficHelper::getUrlFileExtension($requestUri);
                $route404data["store"] = $this->routeManager->getStoreById($storeId);
            }

            $this->routeManager->insertUpdateRoute404($route404data,$route404);
        }
        /**
         * End manage route 404
         */

        // Remove port
        $urlPartsTmp = parse_url($requestUri);

        $urlParts = $this->routeManager->parseUrl($requestUri);
        $urlParts["scheme"] = $urlPartsTmp["scheme"];
        $urlParts["host"] = $urlPartsTmp["host"];
        $urlParts["path"] = $session->get("current_language_url") . "/404";

        $route = $this->routeManager->prepareRoute($request, $urlParts);

        $data = $route["data"];

        $this->initializeTwigVariables($request);

        $session = $request->getSession();

        if (empty($data["page"]) || empty($data["page"]->getTemplateType())) {
            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get('entity_manager');
            }
            /** @var STemplateTypeEntity $template */
            $template = $this->entityManager->getEntityByEntityTypeCodeAndId("s_template_type", 1);
        } else {
            /** @var STemplateTypeEntity $template */
            $template = $data["page"]->getTemplateType() ?? null;
        }

        $content = json_decode($template->getContent(), true);
        $data["content"] = $this->helperManager->prepareBlockGrid($content);
        $data["base_template"] = $this->templateManager->getBaseTemplateBundle($session->get("current_website_id"));

        return new Response($this->renderView($this->templateManager->getTemplatePathByBundle("Page:{$template->getCode()}.html.twig", $session->get("current_website_id")), array('data' => $data)));
    }
}
