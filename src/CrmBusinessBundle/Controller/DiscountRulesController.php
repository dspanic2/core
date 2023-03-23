<?php


namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;
use CrmBusinessBundle\Entity\DiscountCatalogEntity;
use CrmBusinessBundle\Managers\DiscountRulesManager;
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

class DiscountRulesController extends AbstractController
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var DiscountRulesManager $discountRulesManager */
    protected $discountRulesManager;
    /** @var FormManager $formManager */
    protected $formManager;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->discountRulesManager = $this->getContainer()->get("discount_rules_manager");
    }

    protected function initializeForm($type)
    {
        $factoryManager = $this->getContainer()->get('factory_manager');
        $this->formManager = $factoryManager->loadFormManager($type);
    }

    /**
     * @Route("/discount_catalog/save", name="discount_catalog_save_form")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $type = "discount_catalog";

        $this->initializeForm($type);

        if(empty($_POST["rules"]) || empty(json_decode($_POST["rules"]))){
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Discount rules cannot be empty')));
        }

        /** @var DiscountCatalogEntity $entity */
        $entity = $this->formManager->saveFormModel($type, $_POST);

        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('There has been an error')));
        }

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Success'), 'message' => $this->translator->trans('Form has been submitted'), 'entity' => $this->entityManager->entityToArray($entity)));
    }

    /**
     * @Route("/discount_catalog/recalculate_discounts", name="recalculate_discounts")
     * @Method("POST")
     */
    public function recalculateDiscountsAction(Request $request)
    {
        $this->initialize();

        $this->discountRulesManager->recalculateDiscountRules(true);

        return new JsonResponse(array(
            "error" => false,
            "title" => $this->translator->trans("Recalculate discounts"),
            "message" => $this->translator->trans("Discounts successfully recalculated")
        ));
    }

}
