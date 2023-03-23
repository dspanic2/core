<?php

namespace PaymentProvidersBusinessBundle\Controller;

use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\OrderManager;
use CrmBusinessBundle\Managers\QuoteManager;
use PaymentProvidersBusinessBundle\Constants\PaymentProvidersConstants;
use PaymentProvidersBusinessBundle\PaymentProviders\KeksPayProvider;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class KeksPayController extends AbstractScommerceController
{
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var KeksPayProvider $keksPayProvider */
    protected $keksPayProvider;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->keksPayProvider = $this->getContainer()->get("kekspay_provider");
        //$this->initializeTwigVariables($request);
    }

    /**
     * @Route("/api/kekspay", name="kekspay")
     * @Method("POST")
     */
    public function keksPayAdviceAction(Request $request)
    {
        $this->initialize();

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }
        if (empty($this->errorLogManager)) {
            $this->errorLogManager = $this->getContainer()->get("error_log_manager");
        }

        $logData = Array();
        $logData["action"] = "success";
        $logData["has_error"] = 1;
        $logData["response_data"] = $request->getContent();
        $logData["payment_type"] = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::KEKSPAY_PROVIDER_CODE);
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_RESPONSE);

        $authorization = $request->headers->get("Authorization");
        if (empty($authorization)) {
            $logData["description"] = "KeksPay error: advice auth is empty";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            return new JsonResponse(array("status" => 1, "message" => "Error"));
        }

        if ($authorization !== $this->keksPayProvider->auth) {
            $logData["description"] = "KeksPay error: advice auth not matched: " . $authorization;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            return new JsonResponse(array("status" => 2, "message" => "Error"));
        }

        $content = $request->getContent();
        if (empty($content)) {
            $logData["description"] = "KeksPay error: advice content is empty";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            return new JsonResponse(array("status" => 3, "message" => "Error"));
        }

        $p = json_decode($content, true);
        if (empty($p)) {
            $logData["description"] = "KeksPay error: advice data is empty";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            return new JsonResponse(array("status" => 4, "message" => "Error"));
        }

        if (!isset($p["bill_id"])) {
            $logData["description"] = "KeksPay error: advice data not matched: " . $content;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            return new JsonResponse(array("status" => 5, "message" => "Error"));
        }

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }
        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($p["bill_id"]);
        if (empty($quote)) {
            $logData["description"] = "KeksPay error: quote does not exist: " . $content;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            return new JsonResponse(array("status" => 6, "message" => "Error"));
        }
        $logData["quote"] = $quote;

        $amount = number_format($quote->getBasePriceTotal(), 2, ".", "");
        if ($amount != $p["amount"]) {
            $logData["description"] = "KeksPay error: amount not valid: " . $content;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            return new JsonResponse(array("status" => 7, "message" => "Error"));
        }

        $data = array();
        $data["is_locked"] = 0;
        $quote = $this->quoteManager->updateQuote($quote, $data, true);

        if ($p["status"] != 0) {
            $logData["description"] = "KeksPay error: payment cancelled: " . $content;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            return new JsonResponse(array("status" => 8, "message" => "Cancelled"));
        }

        if (!in_array($quote->getQuoteStatusId(), array(
            CrmConstants::QUOTE_STATUS_NEW,
            CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT,
            CrmConstants::QUOTE_STATUS_ACCEPTED))) {

            $logData["description"] = "KeksPay error: quote status not QUOTE_STATUS_NEW: " . $content;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            return new JsonResponse(array("status" => 9, "message" => "Error"));
        }

        $quoteStatus = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_ACCEPTED);

        $this->quoteManager->changeQuoteStatus($quote, $quoteStatus);

        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderByQuoteId($quote->getId());
        if (empty($order)) {
            $logData["description"] = "KeksPay error: order not created: " . $content;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            $this->errorLogManager->logErrorEvent("Kekspay success - cannot create order","Cannot create order for quote id {$quote->getId()}",true);

            return new JsonResponse(array("status" => 13, "message" => "Error"));
        }

        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::KEKSPAY_PROVIDER_CODE);

        try{
            $transactionData = array();
            $transactionData["order"] = $order;
            $transactionData["provider"] = PaymentProvidersConstants::KEKSPAY_PROVIDER_CODE;
            $transactionData["signature"] = null;
            $transactionData["transaction_identifier"] = $p["keks_id"];
            $transactionData["amount"] = floatval($p["amount"]);
            $transactionData["currency"] = $order->getCurrency();
            $transactionData["transaction_status"] = $this->paymentTransactionManager->getPaymentTransactionStatusById(PaymentProvidersConstants::PAYMENT_TRANSACTION_STATUS_NAPLACENO);
            $transactionData["payment_type"] = $paymentType;
            $transactionData["transaction_identifier_second"] = $p["tid"];

            /**
             * Nema na keks pay
             */
            /*$transactionData["request_type"] = $p["request_type"]; //todo
            $transactionData["transaction_type"] = $p["transaction_type"]; //todo
            $transactionData["acquirer"] = $p["acquirer"]; //todo
            $transactionData["purchase_installments"] = $p["purchase_installments"]; //todo
            $transactionData["response_result"] = $p["response_result"]; //todo
            $transactionData["response_random_number"] = $p["response_random_number"]; //todo
            $transactionData["masked_pan"] = $p["masked_pan"]; //todo
            $transactionData["response_message"] = $p["response_message"]; //todo
            $transactionData["card_type"] = $p["card_type"]; //todo
            $transactionData["payment_method"] = $p["payment_method"]; //todo*/

            /** @var PaymentTransactionEntity $paymentTransaction */
            $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($transactionData);

            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
            }

            $this->crmProcessManager->processPayment($paymentTransaction);

        }
        catch (\Exception $e){
            $this->errorLogManager->logExceptionEvent("KeksPay success - cannot create payment transaction",$e,true);
        }

        $logData["has_error"] = 0;
        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        return new JsonResponse(array("status" => 0, "message" => "Accepted"));
    }

    /**
     * @Route("/api/kekspay_check_transaction", name="kekspay_check_transaction")
     * @Method("POST")
     */
    public function keksPayCheckTransactionAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["quote_hash"]) || empty($p["quote_hash"])) {
            return new JsonResponse(array("order_created" => false));
        }

        $ret = $this->keksPayProvider->checkTransactionStatus($p["quote_hash"]);

        return new JsonResponse($ret);
    }
}