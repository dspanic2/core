<?php


namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\LoyaltyCardEntity;
use CrmBusinessBundle\Entity\LoyaltyEarningsConfigurationEntity;
use CrmBusinessBundle\Managers\LoyaltyManager;
use Doctrine\Common\Inflector\Inflector;
use Exception;
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

class LoyaltyController extends AbstractController
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var LoyaltyManager $loyaltyManager */
    protected $loyaltyManager;
    /** @var FormManager $formManager */
    protected $formManager;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->loyaltyManager = $this->getContainer()->get("loyalty_manager");
    }

    protected function initializeForm($type)
    {
        $factoryManager = $this->getContainer()->get('factory_manager');
        $this->formManager = $factoryManager->loadFormManager($type);
    }

    /**
     * @Route("/loyalty_earnings_configuration/save", name="loyalty_earnings_save_form")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $type = "loyalty_earnings_configuration";

        $this->initializeForm($type);

        /*if(empty($_POST["rules"]) || empty(json_decode($_POST["rules"]))){
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Loyalty earning rules cannot be empty')));
        }*/

        /** @var LoyaltyEarningsConfigurationEntity $entity */
        $entity = $this->formManager->saveFormModel($type, $_POST);

        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('There has been an error')));
        }

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Success'), 'message' => $this->translator->trans('Form has been submitted'), 'entity' => $this->entityManager->entityToArray($entity)));
    }

    /**
     * @Route("/loyalty_earnings_configuration/recalculate_loyalty_earnings", name="recalculate_loyalty_earnings")
     * @Method("POST")
     */
    public function recalculateLoyaltyEarningsAction(Request $request)
    {
        $this->initialize();

        $this->loyaltyManager->recalculateLoyaltyEarningsConfiguration();

        return new JsonResponse(array(
            "error" => false,
            "title" => $this->translator->trans("Recalculate discounts"),
            "message" => $this->translator->trans("Discounts successfully recalculated")
        ));
    }

    /**
     * @Route("/loyalty/contact_loyalty_form_action", name="contact_loyalty_form_action")
     * @Method("POST")
     */
    public function contactLoyaltyFormAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Contact id is not defined')));
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->getContactById($p["id"]);

        if (empty($contact)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Contact does not exist')));
        }

        /** @var LoyaltyCardEntity $loyaltyCard */
        $loyaltyCard = $contact->getLoyaltyCard();

        if (empty($loyaltyCard)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Loyalty does does not exist for this contact')));
        }

        $points = $this->loyaltyManager->getAvailableLoyaltyPoints($loyaltyCard);

        if(empty($this->templateManager)){
            $this->templateManager = $this->getContainer()->get("template_manager");
        }

        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Includes:contact_add_remove_loyalty_points_modal.html.twig", $_ENV["DEFAULT_WEBSITE_ID"]), array(
            'loyaltyCard' => $loyaltyCard,
            'points' => $points
        ));

        $html = $this->renderView('AppBundle:Includes:modal.html.twig', Array("html" => $html, "title" => $this->translator->trans("Edit")));

        if (empty($html)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error opening modal')));
        }

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/loyalty/add_remove_loyalty_points", name="add_remove_loyalty_points")
     * @Method("POST")
     * @throws Exception
     */
    public function addRemoveLoyaltyPointsThroughAdmin(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        $loyaltyCardId = $p["loyalty_card_id"];
        $loyaltyPoints = $p["loyalty_points"];

        /** @var LoyaltyCardEntity $loyaltyCard */
        $loyaltyCard = $this->loyaltyManager->getLoyaltyCardById($loyaltyCardId);

        if ($loyaltyPoints < 0) {
            try {
                $this->loyaltyManager->subtractPointsManually($loyaltyCard, $loyaltyPoints);
            } catch (\Exception $e) {
                return new JsonResponse(array('error' => true, 'message' => $e->getMessage()));
            }
        } elseif ($loyaltyPoints > 0) {
            try {
                $this->loyaltyManager->addPointsManually($loyaltyCard, $loyaltyPoints);
            } catch (\Exception $e) {
                return new JsonResponse(array('error' => true, 'message' => $e->getMessage()));
            }
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Saved')));
    }
}
