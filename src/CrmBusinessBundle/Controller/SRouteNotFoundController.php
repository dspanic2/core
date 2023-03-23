<?php

namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\RestManager;
use ScommerceBusinessBundle\Entity\SRouteNotFoundEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Tests\Fixtures\Entity;

class SRouteNotFoundController extends AbstractController
{
    /** @var RestManager $restManager */
    protected $restManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * @Route("/s_route_not_found/not_found_recheck", name="not_found_recheck")
     * @Method("POST")
     */
    public function notFoundRecheckBulk(Request $request)
    {
        $this->initialize();

        $ret = array();
        $ret["error"] = true;

        $p = $_POST;

        if(empty($this->listViewManager)){
            $this->listViewManager = $this->getContainer()->get("list_view_manager");
        }

        $notFoundRoutes = $this->listViewManager->getListViewDataModelEntities($p["list_view_id"],$p);

        if(empty($notFoundRoutes)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Route list is empty")));
        }

        $this->restManager = new RestManager();

        /** @var SRouteNotFoundEntity $notFoundRoute */
        foreach ($notFoundRoutes as $notFoundRoute){
            $this->restManager->get($notFoundRoute->getRequestUri());
        }

        return new JsonResponse(array("error" => false, "message" => $this->translator->trans("Routes rechecked")));
    }

    /**
     * @Route("/s_route_not_found/not_found_url_recheck", name="not_found_url_recheck")
     * @Method("POST")
     */
    public function notFoundRecheck(Request $request)
    {
        $this->initialize();

        $ret = array();
        $ret["error"] = true;

        $p = $_POST;

        if(empty($p["id"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing id")));
        }

        if(empty($this->routeManager)){
            $this->routeManager = $this->getContainer()->get("route_manager");
        }

        /** @var SRouteNotFoundEntity $notFoundRoute */
        $notFoundRoute = $this->routeManager->getRoute404ById($p["id"]);

        if(empty($notFoundRoute)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("404 rout not found")));
        }

        $this->restManager = new RestManager();
        $this->restManager->get($notFoundRoute->getRequestUri());

        return new JsonResponse(array("error" => false, "message" => $this->translator->trans("Route rechecked")));
    }

    /**
     * @Route("/s_route_not_found/bulk_redirect_not_found", name="bulk_redirect_not_found")
     * @Method("POST")
     */
    public function bulkRedirectNotFound(Request $request)
    {
        $this->initialize();

        $ret = array();
        $ret["error"] = true;

        $p = $_POST;

        if(empty($this->listViewManager)){
            $this->listViewManager = $this->getContainer()->get("list_view_manager");
        }

        $notFoundRoutes = $this->listViewManager->getListViewDataModelEntities($p["list_view_id"],$p);

        if(empty($notFoundRoutes)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Route list is empty")));
        }

        $ids = Array();

        /** @var SRouteNotFoundEntity $notFoundRoute */
        foreach ($notFoundRoutes as $notFoundRoute){
            if(!$notFoundRoute->getIsRedirected()){
                $ids[] = $notFoundRoute->getId();
            }
        }

        if(empty($ids)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('All urls already redirected')));
        }

        $data["error"] = false;
        $data["ids"] = implode(",",$ids);
        $data["multiple"] = false;

        $html = $this->renderView('ScommerceBusinessBundle:Includes:s_route_not_found_bulk_redirect_modal.html.twig', Array("data" => $data));

        $html = $this->renderView('AppBundle:Includes:modal.html.twig', Array("html" => $html, "title" => $this->translator->trans("Bulk redirect")));
        if (empty($html)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error opening modal')));
        }

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/s_route_not_found/bulk_redirect_not_found_mass", name="bulk_redirect_not_found_mass")
     * @Method("POST")
     */
    public function bulkRedirectNotFoundMass(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]["s_route_not_found"]) || empty($p["items"]["s_route_not_found"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Ids are missing')));
        }

        $data["error"] = false;
        $data["ids"] = implode(",",$p["items"]["s_route_not_found"]);
        $data["multiple"] = false;

        $html = $this->renderView('ScommerceBusinessBundle:Includes:s_route_not_found_bulk_redirect_modal.html.twig', Array("data" => $data));

        $html = $this->renderView('AppBundle:Includes:modal.html.twig', Array("html" => $html, "title" => $this->translator->trans("Bulk redirect")));
        if (empty($html)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error opening modal')));
        }

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/s_route_not_found/bulk_redirect_not_found_save", name="bulk_redirect_not_found_save")
     * @Method("POST")
     */
    public function bulkRedirectNotFoundSave(Request $request)
    {
        $this->initialize();

        $ret = array();
        $ret["error"] = true;

        $p = $_POST;

        if(!isset($p["ids"]) || empty($p["ids"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Ids are missing')));
        }
        if(!isset($p["redirect_to"]) || empty($p["redirect_to"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Redirect to is not defined')));
        }

        $p["redirect_to"] = str_ireplace($_ENV["SSL"]."://".$_ENV["FRONTEND_URL"],"",trim($p["redirect_to"]));
        if(empty($p["redirect_to"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Please insert correct redirect url')));
        }

        if($p["redirect_to"][0] != "/"){
            $p["redirect_to"] = "/".$p["redirect_to"];
        }

        if(empty($this->routeManager)){
            $this->routeManager = $this->getContainer()->get("route_manager");
        }

        try{
            $this->routeManager->bulkUpdateRoute404RedirectByIds(explode(",",$p["ids"]),$p["redirect_to"]);
        }
        catch (\Exception $e){
            return new JsonResponse(array('error' => true, 'message' => $e->getMessage()));
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Routes redirected')));
    }

}
