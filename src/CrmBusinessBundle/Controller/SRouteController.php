<?php

namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;
use ScommerceBusinessBundle\Entity\SPageEntity;
use ScommerceBusinessBundle\Entity\SRouteEntity;
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

class SRouteController extends AbstractController
{
    /** @var FormManager $formManager */
    protected $formManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
        $this->routeManager = $this->container->get("route_manager");
    }

    protected function initializeForm($type)
    {
        $factoryManager = $this->container->get('factory_manager');
        $this->formManager = $factoryManager->loadFormManager($type);
    }

    /**
     * @Route("/s_route/save", name="s_route_save_form")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $type = "s_route";

        $this->initializeForm($type);

        if (!isset($_POST["request_url"]) || empty($_POST["request_url"])) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Error occurred'),
                    'message' => $this->translator->trans('Request URL missing'),
                )
            );
        }

        /** @var SRouteEntity $existingRoute */
        $existingRoute = $this->routeManager->getRouteByUrl(["request_url" => $_POST["request_url"]]);

        if (empty($existingRoute)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Error occurred'),
                    'message' => $this->translator->trans('Page for existing URL was not found. Please check you entered correct URL'),
                )
            );
        }

        $_POST["id"] = $existingRoute->getId();
        $_POST["destination_id"] = $existingRoute->getDestinationId();
        $_POST["destination_type"] = $existingRoute->getDestinationType();
        if (!empty($_POST["redirect_to"]) && empty($_POST["redirect_type_id"])) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Redirect type missing')));
        }
        if (empty($_POST["redirect_to"])) {
            $_POST["redirect_type_id"] = "";
        }
        /** @var SPageEntity $entity */
        $existingRoute = $this->formManager->saveFormModel($type, $_POST);
        if (empty($existingRoute)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('There has been an error')));
        }
        return new JsonResponse(
            array(
                'error' => false,
                'title' => $this->translator->trans('Success'),
                'message' => $this->translator->trans('Form has been submitted'),
                'entity' => $this->entityManager->entityToArray($existingRoute)
            )
        );


    }
}
