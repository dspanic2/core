<?php

namespace PaymentProvidersBusinessBundle\Controller;

use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ErrorLogManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\PaymentTransactionStatusEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteStatusEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\OrderManager;
use CrmBusinessBundle\Managers\QuoteManager;
use PaymentProvidersBusinessBundle\Constants\PaymentProvidersConstants;
use PaymentProvidersBusinessBundle\PaymentProviders\BankartProvider;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class BankartController extends AbstractScommerceController
{
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var BankartProvider $bankartProvider */
    protected $bankartProvider;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->quoteManager = $this->getContainer()->get("quote_manager");
        $this->orderManager = $this->getContainer()->get("order_manager");
        $this->bankartProvider = $this->getContainer()->get("bankart_provider");
        $this->errorLogManager = $this->getContainer()->get("error_log_manager");
        $this->translator = $this->getContainer()->get("translator");
        $this->logger = $this->getContainer()->get("logger");

        /**
         * Bez ovoga se dobije error na success order-u, ne micati jer nam se order fail-a napraviti.
         */
        if (!isset($_SERVER["HTTP_USER_AGENT"])) {
            $_SERVER["HTTP_USER_AGENT"] = "";
        }

        $this->initializeTwigVariables($request);
    }

    /**
     * @Route("/api/bankart_create", name="bankart_create")
     * @Method("POST")
     */
    public function bankartCreateAction(Request $request)
    {
        $p = $_POST;

        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $session = $request->getSession();

        $urls = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $urls["quoteUrl"];
        $quoteErrorUrl = $urls["quoteErrorUrl"];
        $quoteSuccessUrl = $urls["quoteSuccessUrl"];

        if (!isset($p["cart"]) || empty($p["cart"])) {
            $this->logger->error("Bankart error: cart is empty");
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($p["cart"]);
        if (empty($quote)) {
            $this->logger->error("Bankart error: quote does not exist: " . $p["cart"]);
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $quote->getPaymentType();

        $config = json_decode($paymentType->getConfiguration(), true);
        if (empty($config)) {
            $this->logger->error("Bankart error: config is empty");
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        if (!isset($config["transaction_type"]) || empty($config["transaction_type"])) {
            $this->logger->error("Bankart error: transaction_type is empty");
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        /**
         * Create new hash because bankart has unique cart id requirement
         */
        $updateData = array();
        $updateData["preview_hash"] = StringHelper::generateHash($quote->getId(), time());
        $quote = $this->quoteManager->updateQuote($quote, $updateData, true);

        $transactionType = $config["transaction_type"];
        if (strcasecmp($transactionType, PaymentProvidersConstants::BANKART_TRANSACTION_TYPE_DEBIT) == 0) {
            $ret = $this->bankartProvider->apiDebitPayment($quote);
        } else {
            $ret = $this->bankartProvider->apiPreauthorizePayment($quote);
        }

        if (empty($ret)) {
            $this->logger->error("Bankart error: result is empty, quote id: " . $quote->getId());
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }
        if (!isset($ret["success"]) || empty($ret["success"])) {
            $this->logger->error("Bankart error: success is empty, quote id: " . $quote->getId());
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }
        if (!isset($ret["redirectUrl"]) || empty($ret["redirectUrl"])) {
            $this->logger->error("Bankart error: redirectUrl is empty, quote id: " . $quote->getId());
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        $uuid = $ret["uuid"];
        $purchaseId = $ret["purchaseId"];
        $url = $ret["redirectUrl"];

        return $this->redirect($url, 301);
    }

    /**
     * @Route("/api/bankart_callback", name="bankart_callback")
     * @Method("POST")
     */
    public function bankartCallbackAction(Request $request)
    {
        $this->initialize($request);

        $requestSignature = $request->headers->get("X-Signature");
        if (empty($requestSignature)) {
            $this->logger->error("Bankart error: signature is empty: " . json_encode($request->headers->all()));
            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
        }

        $contentType = $request->headers->get("Content-Type");
        if (empty($contentType)) {
            $this->logger->error("Bankart error: contentType is empty: " . json_encode($request->headers->all()));
            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
        }

        $date = $request->headers->get("Date");
        if (empty($date)) {
            $this->logger->error("Bankart error: date is empty: " . json_encode($request->headers->all()));
            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
        }

        /**
         * TODO: You should also verify that the Date header is within a reasonable deviation of the current timestamp (e.g. 60 seconds)
         */

        $json = $request->getContent();
        if (empty($json)) {
            $this->logger->error("Bankart error: json is empty: " . json_encode($request->headers->all()));
            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
        }

        /**
         * TODO: maknut ovo kad bankart proradi
         */
        $this->logger->error("data: " . $json . " " . json_encode($request->headers->all()));

        $signature = $this->bankartProvider->generateSignature(
            $request->getRequestUri(), // /api/bankart_callback
            $request->getMethod(), // POST
            $json,
            $contentType,
            $date);

        $input = json_decode($json, true);

        if ($requestSignature != $signature) {
            $tmp = array($request->getRequestUri(), $request->getMethod(), $json, $contentType, $date);
            $this->logger->error("Bankart error: signatures do not match: " . $requestSignature . " != " . json_encode($tmp));
            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
        }
        if (!isset($input["result"])) {
            $this->logger->error("Bankart error: result is empty: " . $json);
            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
        }
        if (!isset($input["merchantTransactionId"])) {
            $this->logger->error("Bankart error: merchantTransactionId is empty: " . $json);
            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($input["merchantTransactionId"]);
        if (empty($quote)) {
            $this->logger->error("Bankart error: quote does not exist: " . $input["merchantTransactionId"]);
//            return new Response("Internal Server Error", Response::HTTP_INTERNAL_SERVER_ERROR);
            return new Response("OK", Response::HTTP_OK);
        }

        if ($input["result"] != "OK") {
            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
        }

        $data = array();
        $data["is_locked"] = 0;
        $quote = $this->quoteManager->updateQuote($quote, $data, true);

        $paymentTransaction = null;
        $paymentTransactionStatusId = null;

        $transactionType = $input["transactionType"];
        if ($transactionType == PaymentProvidersConstants::BANKART_TRANSACTION_TYPE_DEBIT) {


            $paymentTransactionStatusId = CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED;

        } else if ($transactionType == PaymentProvidersConstants::BANKART_TRANSACTION_TYPE_PREAUTHORIZE) {

            //todo ovdje se radi order

            $paymentTransactionStatusId = CrmConstants::PAYMENT_TRANSACTION_STATUS_PREAUTHORISED;

        } else if ($transactionType == PaymentProvidersConstants::BANKART_TRANSACTION_TYPE_CAPTURE) {

            //todo ovdje se uzmu pare

            return new Response("OK", Response::HTTP_OK);

        } else if ($transactionType == PaymentProvidersConstants::BANKART_TRANSACTION_TYPE_REFUND) {

            return new Response("OK", Response::HTTP_OK);

        } else if ($transactionType == PaymentProvidersConstants::BANKART_TRANSACTION_TYPE_VOID) {

            return new Response("OK", Response::HTTP_OK);

        } else if ($transactionType == PaymentProvidersConstants::BANKART_TRANSACTION_TYPE_CHARGEBACK) {

            //todo ovdje se vrate pare

            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $this->errorLogManager->logErrorEvent("Bankart: transaction chargeback alert", $json, true);

            return new Response("OK", Response::HTTP_OK);

        } else if ($transactionType == PaymentProvidersConstants::BANKART_TRANSACTION_TYPE_CHARGEBACK_REVERSAL) {

            //todo ovdje se vrate pare

            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $this->errorLogManager->logErrorEvent("Bankart: transaction chargeback reversal alert", $json, true);

            return new Response("OK", Response::HTTP_OK);

        } else {
            $tmp = array_merge($request->headers->all(), $input);
            $this->logger->error("Bankart error: unsupported transaction type: " . json_encode($tmp));
            return new Response("Internal Server Error", Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (!in_array($quote->getQuoteStatusId(), array(
            CrmConstants::QUOTE_STATUS_NEW,
            CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT))) {
            $this->logger->error("Bankart error: quote status not QUOTE_STATUS_NEW: " . $input["merchantTransactionId"]);
            return new Response("Internal Server Error", Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        /** @var QuoteStatusEntity $quoteStatus */
        $quoteStatus = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_ACCEPTED);

        $this->quoteManager->changeQuoteStatus($quote, $quoteStatus);

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderByQuoteId($quote->getId());
        if (empty($order)) {
            $this->logger->error("Bankart error: order not created, quote: " . $input["merchantTransactionId"]);
            return new Response("Internal Server Error", Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        /** @var PaymentTransactionStatusEntity $paymentTransactionStatus */
        $paymentTransactionStatus = $this->orderManager->getPaymentTransactionStatusById($paymentTransactionStatusId);

        $transactionData = array();
        $transactionData["order"] = $order;
        $transactionData["provider"] = "bankart_provider";
        $transactionData["signature"] = $signature;
        $transactionData["transaction_identifier"] = $input["uuid"];
        $transactionData["transaction_identifier_second"] = $input["purchaseId"];
        $transactionData["amount"] = $quote->getPriceTotal();
        $transactionData["currency"] = $quote->getCurrency();
        $transactionData["transaction_status"] = $paymentTransactionStatus;
        $transactionData["payment_type"] = $order->getPaymentType();

        /** @var PaymentTransactionEntity $paymentTransaction */
        $paymentTransaction = $this->orderManager->createUpdatePaymentTransaction($transactionData, $paymentTransaction);
        if (empty($paymentTransaction)) {
            $this->logger->error("Bankart error: transaction not saved, order_id: " . $order->getId());
        }

        return new Response("OK", Response::HTTP_OK);
    }

    /**
     * @Route("/api/bankart_success", name="bankart_success")
     * @Method("GET")
     */
    public function bankartSuccessAction(Request $request)
    {
        $g = $_GET;

        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $session = $request->getSession();

        $urls = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $urls["quoteUrl"];
        $quoteErrorUrl = $urls["quoteErrorUrl"];
        $quoteSuccessUrl = $urls["quoteSuccessUrl"];

        if (!isset($g["hash"]) || empty($g["hash"])) {
            //$session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderByHash($g["hash"]);
        if (empty($order)) {
            //$session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        $session->set("order_id", $order->getId());

        return $this->redirect($quoteSuccessUrl, 301);
    }

    /**
     * @Route("/api/bankart_cancel", name="bankart_cancel")
     * @Method("GET")
     */
    public function bankartCancelAction(Request $request)
    {
        $g = $_GET;

        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $session = $request->getSession();

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);
        if (!empty($quote)) {
            $data = array();
            $data["is_locked"] = 0;
            $quote = $this->quoteManager->updateQuote($quote, $data, true);
        }

        $urls = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $urls["quoteUrl"];
        $quoteErrorUrl = $urls["quoteErrorUrl"];
        $quoteSuccessUrl = $urls["quoteSuccessUrl"];

        $session->set("quote_error", $this->translator->trans("Transaction successfully canceled"));

        return $this->redirect($quoteUrl, 301);
    }

    /**
     * @Route("/api/bankart_error", name="bankart_error")
     * @Method("GET")
     */
    public function bankartErrorAction(Request $request)
    {
        $g = $_GET;

        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $session = $request->getSession();

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);
        if (!empty($quote)) {
            $data = array();
            $data["is_locked"] = 0;
            $quote = $this->quoteManager->updateQuote($quote, $data, true);
        }

        $urls = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $urls["quoteUrl"];
        $quoteErrorUrl = $urls["quoteErrorUrl"];
        $quoteSuccessUrl = $urls["quoteSuccessUrl"];

        $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));

        return $this->redirect($quoteUrl, 301);
    }
}
