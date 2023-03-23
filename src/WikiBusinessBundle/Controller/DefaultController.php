<?php

namespace WikiBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use WikiBusinessBundle\Entity\WikiRouteEntity;
use WikiBusinessBundle\Managers\WikiManager;
use WikiBusinessBundle\Managers\WikiRouteManager;

class DefaultController extends AbstractController
{
    /** @var WikiRouteManager $wikiRouteManager */
    protected $wikiRouteManager;
    /** @var WikiManager $wikiManager */
    protected $wikiManager;

    protected function initialize()
    {
        parent::initialize();
        $this->wikiRouteManager = $this->container->get("wiki_route_manager");
        $this->wikiManager = $this->container->get("wiki_manager");
    }

    /**
     * @Route("/wiki/{url}", defaults={"url"=null}, name="wiki_route_page", requirements={"url"=".+"})
     * @Method("GET")
     * @param Request $request
     * @return Response
     */
    public function pageAction(Request $request)
    {
        $this->initialize();

        /**
         * TODO: privremena implementacija
         */

        $url = $request->get("url");
        if (empty($url)) {
            $url = $request->getRequestUri();
            throw $this->createNotFoundException("The page " . $url . " does not exist");
        }

        /** @var WikiRouteEntity $wikiRouteEntity */
        $wikiRouteEntity = $this->wikiRouteManager->getRouteByUrl($url);
        if (empty($wikiRouteEntity)) {
            $url = $request->getRequestUri();
            throw $this->createNotFoundException("The page " . $url . " does not exist");
        }

        $destinationEntity = $this->wikiRouteManager->getDestinationByRoute($wikiRouteEntity);

        $data["subtype"] = "view";
        $data["model"]["entity"] = $destinationEntity;
        $data["model"]["path"] = $this->wikiManager->getWikiPath($destinationEntity->getId(), $destinationEntity->getEntityType());

        return new Response($this->renderView("WikiBusinessBundle:Block:wiki_content.html.twig", array(
            "data" => $data
        )));
    }
}