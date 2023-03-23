<?php

namespace AppBundle\Abstracts;

use AppBundle\Helpers\TrafficHelper;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\HelperManager;
use Monolog\Logger;
use ScommerceBusinessBundle\Entity\SRouteEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Tests\Fixtures\Entity;

abstract class AbstractController extends Controller
{
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var Logger */
    protected $logger;
    /** @var Translator */
    protected $translator;
    protected $twig;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    protected $user;

    protected function initialize()
    {
        $this->helperManager = $this->getContainer()->get('helper_manager');
        $this->cacheManager = $this->getContainer()->get("cache_manager");
        $this->translator = $this->getContainer()->get("translator");
        $this->logger = $this->getContainer()->get("logger");
        $this->twig = $this->getContainer()->get("templating");
        $this->user = $this->getUser();
    }

    protected function authenticateAdministrator()
    {

        if (!$this->getContainer()->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
            $this->logger->error("User cannot access administration");
            $this->getContainer()->get('security.token_storage')->setToken(null);
            $this->getContainer()->get('session')->invalidate();
            return $this->redirect("/login", 301);
        }
    }

    /**
     * @param $request
     * @return bool
     */
    protected function initializeTwigVariables($request, $currentEntity = null)
    {

        if (empty($this->twigBase)) {
            $this->twigBase = $this->getContainer()->get('twig');
        }

        $session = $request->getSession();
        $request->setLocale($session->get("current_language"));
        $session->set('_locale', $session->get("current_language"));
        if (empty($this->translator)) {
            $this->translator = $this->getContainer()->get("translator");
        }
        $this->translator->setLocale($session->get("current_language"));

        $this->twigBase->addGlobal('languages', $session->get("languages"));
        $this->twigBase->addGlobal('current_language', $session->get("current_language"));
        $this->twigBase->addGlobal('current_language_url', $session->get("current_language_url"));
        $this->twigBase->addGlobal('current_store_id', $session->get("current_store_id"));
        $this->twigBase->addGlobal('current_website_id', $session->get("current_website_id"));
        $this->twigBase->addGlobal('current_website_name', $session->get("current_website_name"));

        $globals = $this->twigBase->getGlobals();

        if (empty($currentEntity) && (!isset($globals["current_entity"]) || empty($globals["current_entity"]))) {
            /** @var RouteManager $routeManager */
            $routeManager = $this->getContainer()->get('route_manager');

            $parsedUrl = parse_url($request->headers->get('referer'));

            if (strlen($parsedUrl["path"]) > 1) {
                $pieces = explode("/", ltrim($parsedUrl["path"], '/'));
                $parsedUrl["path"] = end($pieces);
            }
            $urlParts = [
                "request_url" => $parsedUrl["path"]
            ];

            $route = $routeManager->getRouteByUrl($urlParts, $session->get("current_store_id"));

            if (!empty($route)) {
                $destination = $routeManager->getDestinationByRoute($route);

                if (!empty($destination)) {
                    $currentEntity = $destination;
                }
            }
        }

        if (!empty($currentEntity)) {
            $this->twigBase->addGlobal('current_entity', $currentEntity);
        }

        $clientIp = $_SERVER["HTTP_X_REAL_IP"] ?? null;
        if (empty($clientIp)) {
            $clientIp = $request->getClientIp();
        }
        $this->twigBase->addGlobal('is_pagespeed', TrafficHelper::ipInRange($clientIp, "66.249.64.0/19") || TrafficHelper::detectBot() || TrafficHelper::detectPageSpeed());

        return true;
    }

    public function getContainer(){

        return $this;
    }
}
