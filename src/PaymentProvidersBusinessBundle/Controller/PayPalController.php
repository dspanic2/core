<?php

namespace PaymentProvidersBusinessBundle\Controller;

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
use PaymentProvidersBusinessBundle\PaymentProviders\PayPalProvider;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class PayPalController extends AbstractScommerceController
{
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var PayPalProvider $paypalProvider */
    protected $paypalProvider;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;

    protected $translator;
    protected $logger;

    protected function initialize($request = null)
    {
        parent::initialize($request);
        $this->quoteManager = $this->getContainer()->get("quote_manager");
        $this->orderManager = $this->getContainer()->get("order_manager");
        $this->paypalProvider = $this->getContainer()->get("paypal_provider");
        $this->translator = $this->getContainer()->get("translator");
        $this->logger = $this->getContainer()->get("logger");
    }

    /**
     * @Route("/api/paypal_create", name="paypal_create")
     * @Method("POST")
     */
    public function paypalCreateAction(Request $request)
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
            $this->logger->error("PayPal error: cart is empty");
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($p["cart"]);
        if (empty($quote)) {
            $this->logger->error("PayPal error: quote does not exist: " . $p["cart"]);
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $quote->getPaymentType();

        $config = json_decode($paymentType->getConfiguration(), true);
        if (empty($config)) {
            $this->logger->error("PayPal error: config is empty");
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        if (!isset($config["transaction_type"]) || empty($config["transaction_type"])) {
            $this->logger->error("PayPal error: transaction_type is empty");
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        $transactionType = strtoupper($config["transaction_type"]);

        $accessToken = $this->paypalProvider->getAccessToken();
        if (empty($accessToken)) {
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        $purchaseUnits = $this->paypalProvider->getPurchaseUnits($quote);
        if (empty($purchaseUnits)) {
            $this->logger->error("PayPal error: purchaseUnits are empty, quote id: " . $quote->getId());
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        $url = $this->paypalProvider->createOrder($accessToken, $transactionType, $purchaseUnits, $p["cart"]);
        if (empty($url)) {
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        return $this->redirect($url, 301);
    }

    /**
     * @Route("/api/paypal_confirm", name="paypal_confirm")
     * @Method("GET")
     */
    public function paypalConfirmAction(Request $request)
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

        if (!isset($g["intent"]) || empty($g["intent"])) {
            $this->logger->error("PayPal error: intent is empty: " . json_encode($g));
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        /**
         * This parameter is appended by PayPal
         */
        if (!isset($g["token"]) || empty($g["token"])) {
            $this->logger->error("PayPal error: token is empty: " . json_encode($g));
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        $accessToken = $this->paypalProvider->getAccessToken();

        /**
         * Optional step to check once more if customer has approved the order
         */
        $status = $this->paypalProvider->validateOrder($accessToken, $g["token"]);
        if (empty($status)) {
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        $res = $this->paypalProvider->completeOrder($accessToken, $g["intent"], $g["token"]);
        if (empty($res)) {
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($res["preview_hash"]);
        if (empty($quote)) {
            $this->logger->error("PayPal error: quote does not exist: " . $res["preview_hash"]);
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $quote->getPaymentType();

        $config = json_decode($paymentType->getConfiguration(), true);
        if (empty($config)) {
            $this->logger->error("PayPal error: config is empty");
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        $data = array();
        $data["is_locked"] = 0;
        $quote = $this->quoteManager->updateQuote($quote, $data, true);

        if (!in_array($quote->getQuoteStatusId(), array(
            CrmConstants::QUOTE_STATUS_NEW,
            CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT,
            CrmConstants::QUOTE_STATUS_ACCEPTED))) {
            $this->logger->error("PayPal error: quote status not QUOTE_STATUS_NEW: " . $res["preview_hash"]);
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        /**
         * Izbaceno zbog prevelike kolicine gresaka koja se stvara nakon potvrde narudžbe plaćenje karticom
         */
        /*$ret = $this->crmProcessManager->validateQuote($quote);

        if ($ret["error"]) {
            $this->redirect($ret["redirect_url"], 301);
        } else if ($ret["changed"]) {
            $this->redirect($ret["redirect_url"], 301);
        } else if (isset($ret["redirect_url"]) && !empty($ret["redirect_url"])) {
            $this->redirect($ret["redirect_url"], 301);
        }*/

        /** @var QuoteStatusEntity $quoteStatus */
        $quoteStatus = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_ACCEPTED);

        $this->quoteManager->changeQuoteStatus($quote, $quoteStatus);

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderByQuoteId($quote->getId());
        if (empty($order)) {
            $this->logger->error("PayPal error: order not created, quote: " . $res["preview_hash"]);
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        $paymentTransactionStatusId = CrmConstants::PAYMENT_TRANSACTION_STATUS_PREAUTHORISED;
        if (!empty($res["transaction_identifier_third"])) {
            $paymentTransactionStatusId = CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED;
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        /** @var PaymentTransactionStatusEntity $paymentTransactionStatus */
        $paymentTransactionStatus = $this->paymentTransactionManager->getPaymentTransactionStatusById($paymentTransactionStatusId);

        $transactionData = array();
        $transactionData["order"] = $order;
        $transactionData["provider"] = "paypal_provider";
        $transactionData["signature"] = null;
        $transactionData["transaction_identifier"] = $res["transaction_identifier"]; // orderId
        $transactionData["transaction_identifier_second"] = $res["transaction_identifier_second"]; // authorizationId
        $transactionData["transaction_identifier_third"] = $res["transaction_identifier_third"]; // captureId
        $transactionData["amount"] = $quote->getPriceTotal();
        $transactionData["currency"] = $quote->getCurrency();
        $transactionData["transaction_status"] = $paymentTransactionStatus;
        $transactionData["payment_type"] = $order->getPaymentType();

        /** @var PaymentTransactionEntity $paymentTransaction */
        $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($transactionData);
        if (empty($paymentTransaction)) {
            $this->logger->error("PayPal error: transaction not saved, order_id: " . $order->getId());
        }

        $session->set("order_id", $order->getId());

        return $this->redirect($quoteSuccessUrl, 301);
    }

    /**
     * @Route("/api/paypal_cancel", name="paypal_cancel")
     * @Method("GET")
     */
    public function paypalCancelAction(Request $request)
    {
        $g = $_GET;

        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $urls = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $urls["quoteUrl"];
        $quoteErrorUrl = $urls["quoteErrorUrl"];
        $quoteSuccessUrl = $urls["quoteSuccessUrl"];

//        if (!isset($g["reference_id"]) || empty($g["reference_id"])) {
//            $this->logger->error("PayPal error: reference_id is empty: " . json_encode($g));
//            return $this->redirect($quoteErrorUrl, 301);
//        }

        /**
         * This parameter is appended by PayPal
         */
        if (!isset($g["token"]) || empty($g["token"])) {
            $this->logger->error("PayPal error: token is empty: " . json_encode($g));
            return $this->redirect($quoteErrorUrl, 301);
        }

//        /** @var QuoteEntity $quote */
//        $quote = $this->quoteManager->getQuoteByHash($g["reference_id"]);
//        if (empty($quote)) {
//            $this->logger->error("PayPal error: quote does not exist: " . $g["reference_id"]);
//            return $this->redirect($quoteErrorUrl, 301);
//        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);
        if (empty($quote)) {
            $this->logger->error("PayPal error: active quote not found");
            return $this->redirect($quoteErrorUrl, 301);
        }

        $data = array();
        $data["is_locked"] = 0;
        $this->quoteManager->updateQuote($quote, $data);

        return $this->redirect($quoteUrl, 301);
    }
}
