<?php

namespace ScommerceBusinessBundle\EventListener;

use AppBundle\Managers\AppTemplateManager;
use AppBundle\Managers\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class MaintenanceListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $maintenance = $this->container->hasParameter('maintenance') ? $this->container->getParameter('maintenance') : 0;

        if (!empty($maintenance) && $maintenance == 1) {
            /** @var Request $request */
            $request = $event->getRequest();
            $session = $request->getSession();

            if (empty($session->get('override_maintenance'))) {
                if ($request->query->has('override_maintenance')) {
                    $session->set('override_maintenance', 1);
                } else {
                    /** @var AppTemplateManager $templateManager */
                    $templateManager = $this->container->get('app_template_manager');
                    $twig = $this->container->get('templating');
                    $content = $twig->render($templateManager->getTemplatePathByBundle("Components:maintenance.html.twig", $session->get("current_website_id")), []);;
                    $event->setResponse(new Response($content, 503));
                    $event->stopPropagation();
                }
            }
        }
    }
}
