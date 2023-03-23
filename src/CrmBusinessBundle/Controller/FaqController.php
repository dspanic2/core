<?php

namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;
use ScommerceBusinessBundle\Entity\BlogPostEntity;
use ScommerceBusinessBundle\Managers\FaqManager;
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

class FaqController extends AbstractController
{
    /** @var FormManager $formManager */
    protected $formManager;
    /** @var FaqManager $faqManager */
    protected $faqManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    protected function initializeForm($type)
    {
        $factoryManager = $this->container->get('factory_manager');
        $this->formManager = $factoryManager->loadFormManager($type);
    }

    /**
     * @Route("/faq/save", name="faq_save_form")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $type = "faq";

        $this->initializeForm($type);

        /**
         * Fallback na starije verzije gdje nema multilang
         */
        $hasMultilang = false;

        if(isset($_POST["show_on_store"])){
            $hasMultilang = true;
        }

        if(!isset($_POST["name"]) || empty($_POST["name"])){
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Name cannot be empty')));
        }

        if($hasMultilang){
            $_POST["name"] = array_map('trim', $_POST["name"]);
            $_POST["name"] = array_filter($_POST["name"]);
            if(empty($_POST["name"])){
                return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Name cannot be empty')));
            }
            $_POST["description"] = array_map('trim', $_POST["description"]);
            $_POST["description"] = array_filter($_POST["description"]);
            if(empty($_POST["description"])){
                return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Description cannot be empty')));
            }
            if(empty($_POST["show_on_store_checkbox"])){
                return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Please add at least one store')));
            }
        }

        /** @var BlogPostEntity $entity */
        $entity = $this->formManager->saveFormModel($type, $_POST);
        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('There has been an error')));
        }

        if($hasMultilang){
            if(empty($this->blogManager)){
                $this->faqManager = $this->container->get("faq_manager");
            }

            $this->entityManager->refreshEntity($entity);
            $this->faqManager->insertUpdateFaqLanguages($entity,$_POST["id"]);
        }

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Success'), 'message' => $this->translator->trans('Form has been submitted'), 'entity' => $this->entityManager->entityToArray($entity)));
    }
}
