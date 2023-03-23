<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Entity\TransactionEmailSentEntity;
use AppBundle\Managers\MailManager;
use AppBundle\Managers\TransactionEmailManager;
use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Config\Definition\Exception\Exception;

class EmailTemplateController extends AbstractController
{
    /** @var TransactionEmailManager $transactionEmailManager */
    protected $transactionEmailManager;

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * @Route("/email_template/resend_transaction_email_sent", name="resend_transaction_email_sent")
     * @Method("POST")
     */
    public function resendEmailFromTransactionEmailSent(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Id is empty')));
        }

        if(empty($this->transactionEmailManager)){
            $this->transactionEmailManager = $this->container->get("transaction_email_manager");
        }

        /** @var TransactionEmailSentEntity $transactionEmailSent */
        $transactionEmailSent = $this->transactionEmailManager->getTransactionEmailSentById($p["id"]);

        if(empty($transactionEmailSent)){
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Transaction email does not exist')));
        }

        try{
            $this->transactionEmailManager->createUpdateTransactionEmail(json_decode($transactionEmailSent->getContent(),true),null,$transactionEmailSent->getSEntityType(),$transactionEmailSent->getEntityId());
        }
        catch (\Exception $e){
            $ret["title"] = $this->translator->trans("Error");
            $ret["message"] = $e->getMessage();
            return new JsonResponse($ret);
        }

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Resend email'), 'message' => $this->translator->trans('Email added to queue')));
    }

    /**
     * @Route("/email_template/test", name="test_email_template")
     * @Method("POST")
     */
    public function testEmailTemplate(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["template"]) || empty($p["template"])) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Template is empty')));
        }
        if (!isset($p["test_emails"]) || empty($p["test_emails"])) {
            $p["test_emails"] = "";
        }

        $to = array();
        $to["email"] = $this->user->getEmail();
        $to["name"] = $this->user->getEmail();

        $cc = array();

        if (!empty($p["test_emails"])) {
            $p["test_emails"] = explode(",", $p["test_emails"]);
            foreach ($p["test_emails"] as $key => $email) {
                $email = trim($email);
                $cc[$key]["email"] = $email;
                $cc[$key]["name"] = $email;
            }
        }

        /** @var MailManager $mailManager */
        $mailManager = $this->getContainer()->get('mail_manager');

        if ($mailManager->sendEmail($to, $cc, null, null, $this->translator->trans('Test email'), "", null, array(), $p["template"])) {
            return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Send test email'), 'message' => $this->translator->trans('Email template sent')));
        }

        return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Send test email'), 'message' => $this->translator->trans('There was an error sending email, please try again.')));
    }
}
