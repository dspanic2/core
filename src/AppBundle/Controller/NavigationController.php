<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\NavigationLinkManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class NavigationController extends AbstractController
{
    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * @Route("/navigation/main", name="get_main_navigation")
     */
    public function mainNavigationAction(Request $request)
    {
        $this->initialize();

        $type = $request->get('type');

        if(empty($this->cacheManager)){
            $this->cacheManager = $this->getContainer()->get("cache_manager");
        }

        $cacheItem = $this->cacheManager->getCacheGetItem("admin_menu_data");

        if (empty($cacheItem) || isset($_GET["rebuild_menu"])) {
            /** @var NavigationLinkManager $navigationLinkManager */
            $navigationLinkManager = $this->getContainer()->get("navigation_link_manager");
            $links = json_decode($navigationLinkManager->getNavigationJson(false));

            $this->cacheManager->setCacheItem("admin_menu_data", $links, Array("navigation_link","page","entity_type","attribute_set"));
        }
        else {
            $links = $cacheItem->get();
        }

        return new Response($this->renderView("AppBundle:Navigation:{$type}.html.twig", array("links" => $links)));
    }

    /**
     * @Route("/navigation/url", name="get_navigation_url_by_id")
     */
    public function getNavigationUrlByIdAction(Request $request)
    {

        $this->initialize();

        $id = $request->get('id');

        $link["url"] = "";

        if (preg_match('/^\d+$/', $id)) {
            $navigationLinkContext = $this->getContainer()->get("navigation_link_context");

            $link = $navigationLinkContext->getById($id);

            if (empty($link)) {
                try {
                    $link["url"] = $this->getContainer()->get('router')->generate($id);
                } catch (RouteNotFoundException $e) {
                    $this->logger->error($e->getMessage());
                }
            }
        } else {
            if (!empty($id)) {
                try {
                    $link["url"] = $this->generateUrl($id, array(), UrlGeneratorInterface::ABSOLUTE_PATH);
                } catch (RouteNotFoundException $e) {
                    $this->logger->error($e->getMessage());
                }
            }
        }

        return new Response($this->renderView("AppBundle:Navigation:url.html.twig", array("link" => $link)));
    }
}
