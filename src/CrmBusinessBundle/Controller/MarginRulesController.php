<?php


namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\FormManager;
use CrmBusinessBundle\Managers\MarginRulesManager;
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

class MarginRulesController extends AbstractController
{
    /** @var MarginRulesManager $marginRulesManager */
    protected $marginRulesManager;

    protected function initialize()
    {
        parent::initialize();
        $this->marginRulesManager = $this->getContainer()->get("margin_rules_manager");
    }

    /**
     * @Route("/margin_rule/recalculate_margin_rules", name="recalculate_margin_rules")
     * @Method("POST")
     */
    public function recalculateMarginRulesAction(Request $request)
    {
        $this->initialize();

        $this->marginRulesManager->recalculateMarginRules();

        return new JsonResponse(array(
            "error" => false,
            "title" => $this->translator->trans("Recalculate margin rules"),
            "message" => $this->translator->trans("Margin rules successfully recalculated")
        ));
    }

}
