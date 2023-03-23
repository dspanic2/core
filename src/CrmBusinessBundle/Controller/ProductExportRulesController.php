<?php


namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;
use CrmBusinessBundle\Entity\ProductExportRuleEntity;
use CrmBusinessBundle\Entity\ProductExportRuleTypeEntity;
use CrmBusinessBundle\Managers\ProductExportRulesManager;
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

class ProductExportRulesController extends AbstractController
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var ProductExportRulesManager $productExportRulesManager */
    protected $productExportRulesManager;
    /** @var FormManager $formManager */
    protected $formManager;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->get("entity_manager");
        $this->productExportRulesManager = $this->get("product_export_rules_manager");
    }

    protected function initializeForm($type)
    {
        $factoryManager = $this->get('factory_manager');
        $this->formManager = $factoryManager->loadFormManager($type);
    }

    /**
     * @Route("/product_export_rule/save", name="product_export_rule_save_form")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $type = "product_export_rule";

        $this->initializeForm($type);

        if(empty($_POST["rules"]) || empty(json_decode($_POST["rules"]))){
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Product export rules cannot be empty')));
        }

        /** @var ProductExportRuleEntity $entity */
        $entity = $this->formManager->saveFormModel($type, $_POST);

        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('There has been an error')));
        }

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Success'), 'message' => $this->translator->trans('Form has been submitted'), 'entity' => $this->entityManager->entityToArray($entity)));
    }

    /**
     * @Route("/product_export_rule/product_export_generate", name="product_export_generate")
     * @Method("POST")
     */
    public function productExportGenerateAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;
        $p = array_map('trim', $p);

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Rule type id is empty')));
        }

        /** @var ProductExportRuleTypeEntity $exportRuleType */
        $exportRuleType = $this->productExportRulesManager->getRuleTypeById($p["id"]);

        if(empty($exportRuleType)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Rule type is empty')));
        }

        try{
            $this->productExportRulesManager->runExportRule($exportRuleType);
        }
        catch (\Exception $e){
            return new JsonResponse(array('error' => true, 'message' => $e->getMessage()));
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Export generated')));
    }

}
