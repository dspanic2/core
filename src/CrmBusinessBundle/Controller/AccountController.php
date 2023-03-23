<?php

namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Managers\AccountManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AccountController extends AbstractController
{
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var FormManager $formManager */
    protected $formManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;

    protected function initialize()
    {
        parent::initialize();
    }

    protected function initializeForm($type)
    {
        $factoryManager = $this->getContainer()->get('factory_manager');
        $this->formManager = $factoryManager->loadFormManager($type);
    }

    /**
     * @Route("/account/save", name="account_save_form")
     * @Method("POST")
     */
    public function saveAction(Request $request)
    {
        $this->initialize();

        $type = "account";

        $this->initializeForm($type);

        if(isset($_POST["is_legal_entity"]) && $_POST["is_legal_entity"] == 1){
            if(!isset($_POST["oib"]) || empty($_POST["oib"])){
                return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('OIB cannot be empty')));
            }
        }

        if(!isset($_POST["id"]) || empty($_POST["id"]) && isset($_POST["is_legal_entity"]) && $_POST["is_legal_entity"] == 1){
            if(empty($this->accountManager)){
                $this->accountManager = $this->getContainer()->get("account_manager");
            }

            /** @var AccountEntity $account */
            $account = $this->accountManager->getAccountByFilter("oib",$_POST["oib"]);

            if(!empty($account)){
                return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Account with this oib already exists with id: ').$account->getId()));
            }
        }

        /** @var AccountEntity $entity */
        $entity = $this->formManager->saveFormModel($type, $_POST);

        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('There has been an error')));
        }

        if(empty($this->entityManager)){
            $this->entityManager = $this->getContainer()->get("entity_manager");
        }

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Success'), 'message' => $this->translator->trans('Form has been submitted'), 'entity' => $this->entityManager->entityToArray($entity)));
    }
}
