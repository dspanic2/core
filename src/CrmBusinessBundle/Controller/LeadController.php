<?php

namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Managers\AccountManager;
use Monolog\Logger;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\TranslatorInterface;
use AppBundle\Managers\EntityManager;

class LeadController extends AbstractController
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
        $this->accountManager = $this->container->get("account_manager");
    }

    /**
     * @Route("/lead_convert", name="lead_convert")
     * @param Request $request
     * @return JsonResponse
     */
    public function leadConvertAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["lead_id"]) || empty($p["lead_id"])) {
            return new JsonResponse(array(
                "error" => true,
                "title" => $this->translator->trans("Error occurred"),
                "message" => $this->translator->trans("Lead id is empty")
            ));
        }

        /** @var AccountEntity $account */
        $account = $this->accountManager->getAccountById($p["lead_id"]);
        if (empty($account)) {
            return new JsonResponse(array(
                "error" => true,
                "title" => $this->translator->trans("Error occurred"),
                "message" => $this->translator->trans("Lead not found")
            ));
        }

        $asAccount = $this->entityManager->getAttributeSetByCode("account");

        $account->setAttributeSet($asAccount);

        $this->entityManager->saveEntity($account);

        $url = "/page/account/form/" . $account->getId();

        return new JsonResponse(array(
            "error" => false,
            "redirect_url" => $url
        ));
    }
}