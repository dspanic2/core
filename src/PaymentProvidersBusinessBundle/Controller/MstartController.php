<?php

namespace PaymentProvidersBusinessBundle\Controller;

use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\ErrorLogManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\OrderManager;
use CrmBusinessBundle\Managers\QuoteManager;
use PaymentProvidersBusinessBundle\Constants\PaymentProvidersConstants;
use PaymentProvidersBusinessBundle\Managers\PaymentTransactionManager;
use PaymentProvidersBusinessBundle\PaymentProviders\MstartProvider;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class MstartController extends AbstractScommerceController
{
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var PaymentTransactionManager $paymentTransactionManager */
    protected $paymentTransactionManager;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;
    /** @var MstartProvider $mstartProvider */
    protected $mstartProvider;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->initializeTwigVariables($request);
    }

    /**
     * @Route("/api/mstart_cancel", name="mstart_cancel")
     * @Method("POST")
     */
    public function mstartCancelAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $logData = Array();
        $logData["action"] = "cancel";
        $logData["has_error"] = 1;
        $logData["response_data"] = json_encode($p);
        $logData["payment_type"] = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::MSTART_PROVIDER_CODE);
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_RESPONSE);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $ret = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $ret["quoteUrl"];
        $quoteErrorUrl = $ret["quoteErrorUrl"];
        $quoteSuccessUrl = $ret["quoteSuccessUrl"];

        if(empty($this->quoteManager)){
            $this->quoteManager = $this->getContainer()->get("quote_manager");
        }

        if(!isset($p["order_number"]) || empty($p["order_number"])){
            $logData["description"] = "Missing order_number in response";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            return $this->redirect($quoteErrorUrl, 301);
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($p["order_number"]);

        if (empty($quote)) {
            $logData["description"] = "Quote does not exist for order_number {$p["order_number"]}";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            return $this->redirect($quoteErrorUrl, 301);
        }

        $logData["has_error"] = 0;
        $logData["quote"] = $quote;
        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        $data = array();
        $data["is_locked"] = 0;
        $this->quoteManager->updateQuote($quote, $data);

        return $this->redirect($quoteUrl, 301);
    }

    /**
     * @Route("/api/mstart_confirm", name="mstart_confirm")
     * @Method("POST")
     */
    public function mstartConfirmAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        if(empty($this->mstartProvider)){
            $this->mstartProvider = $this->getContainer()->get("mstart_provider");
        }

        $redirectUrl = $this->mstartProvider->confirmMstartAction($p,$request->getSession());

        return $this->redirect($redirectUrl, 301);
    }
}
