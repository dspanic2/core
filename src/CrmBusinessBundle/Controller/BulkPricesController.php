<?php


namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\FormManager;
use CrmBusinessBundle\Managers\BulkPriceManager;
use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\VarDumper\VarDumper;

class BulkPricesController extends AbstractController
{
    /** @var BulkPriceManager $bulkPriceManager */
    protected $bulkPriceManager;
    /** @var FormManager $formManager */
    protected $formManager;

    protected function initialize()
    {
        parent::initialize();
        $this->bulkPriceManager = $this->container->get("bulk_price_manager");
    }

    /**
     * @Route("/bulk_price/recalculate_bulk_prices", name="recalculate_bulk_prices")
     * @Method("POST")
     */
    public function recalculateBulkPricesAction(Request $request)
    {
        $this->initialize();

        $this->bulkPriceManager->recalculateBulkPriceRules();

        return new JsonResponse(array(
            "error" => false,
            "title" => $this->translator->trans("Recalculate bulk prices"),
            "message" => $this->translator->trans("Bulk prices successfully recalculated")
        ));
    }

}
