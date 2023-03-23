<?php

namespace ScommerceBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\ProductManager;
use CrmBusinessBundle\Managers\QuoteManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use ScommerceBusinessBundle\Entity\WebformEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ScommerceBusinessBundle\Managers\WebformManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Tests\Fixtures\Entity;

class TrackingController extends AbstractScommerceController
{
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->initializeTwigVariables($request);
        $this->quoteManager = $this->container->get("quote_manager");
    }

    /**
     * @Route("/api/tracking/gtag_begin_checkout", name="gtag_begin_checkout")
     * @Method("POST")
     */
    public function gtagBeginCheckoutAction(Request $request)
    {
        $this->initialize($request);

        $session = $request->getSession();

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(true);

        return new JsonResponse(array("javascript" => $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Tracking:begin_checkout.html.twig", $session->get("current_website_id")), array('quote' => $quote))));
    }

    /**
     * @Route("/api/tracking/gtag_select_promotion", name="gtag_select_promotion")
     * @Method("POST")
     */
    public function gtagSelectPromotionAction(Request $request)
    {
        $this->initialize($request);

        $session = $request->getSession();

        return new JsonResponse(array("javascript" => $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Tracking:select_promotion.html.twig", $session->get("current_website_id")), $_POST)));
    }
}
