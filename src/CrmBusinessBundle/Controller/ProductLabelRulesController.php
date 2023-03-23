<?php


namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;
use CrmBusinessBundle\Entity\ProductLabelEntity;
use CrmBusinessBundle\Managers\ProductLabelRulesManager;
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

class ProductLabelRulesController extends AbstractController
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var FormManager $formManager */
    protected $formManager;
    /** @var ProductLabelRulesManager $productLabelRulesManager */
    protected $productLabelRulesManager;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->get("entity_manager");
    }

    protected function initializeForm($type)
    {
        $factoryManager = $this->get('factory_manager');
        $this->formManager = $factoryManager->loadFormManager($type);
    }

    /**
     * @Route("/product_label/save", name="product_label_save_form")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $type = "product_label";

        $this->initializeForm($type);

        if(empty($_POST["rules"]) || empty(json_decode($_POST["rules"]))){
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Product label rules cannot be empty')));
        }

        /** @var ProductLabelEntity $entity */
        $entity = $this->formManager->saveFormModel($type, $_POST);

        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('There has been an error')));
        }

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Success'), 'message' => $this->translator->trans('Form has been submitted'), 'entity' => $this->entityManager->entityToArray($entity)));
    }

    /**
     * @Route("/product_label/recalculate_product_labels", name="recalculate_product_labels")
     * @Method("POST")
     */
    public function recalculateProductLabelsAction(Request $request)
    {
        $this->initialize();

        if(empty($this->productLabelRulesManager)){
            $this->productLabelRulesManager = $this->getContainer()->get("product_label_rules_manager");
        }

        $this->productLabelRulesManager->recalculateProductLabelRules();

        return new JsonResponse(array(
            "error" => false,
            "title" => $this->translator->trans("Recalculate product labels"),
            "message" => $this->translator->trans("Product labels recalculated")
        ));
    }
}
