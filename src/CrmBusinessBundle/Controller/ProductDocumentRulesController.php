<?php


namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;
use CrmBusinessBundle\Entity\ProductDocumentRuleEntity;
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

class ProductDocumentRulesController extends AbstractController
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var FormManager $formManager */
    protected $formManager;

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
     * @Route("/product_document_rule/save", name="product_document_rule_save_form")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $type = "product_document_rule";

        $this->initializeForm($type);

        if(empty($_POST["rules"]) || empty(json_decode($_POST["rules"]))){
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Product document rules cannot be empty')));
        }

        /** @var ProductDocumentRuleEntity $entity */
        $entity = $this->formManager->saveFormModel($type, $_POST);

        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('There has been an error')));
        }

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Success'), 'message' => $this->translator->trans('Form has been submitted'), 'entity' => $this->entityManager->entityToArray($entity)));
    }
}

