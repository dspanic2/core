<?php

namespace PaymentProvidersBusinessBundle\Controller;

use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ErrorLogManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\PaymentTransactionStatusEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteStatusEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\OrderManager;
use CrmBusinessBundle\Managers\QuoteManager;
use PaymentProvidersBusinessBundle\Constants\PaymentProvidersConstants;
use PaymentProvidersBusinessBundle\PaymentProviders\PayCekProvider;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PayCekController extends AbstractScommerceController
{
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var PayCekProvider $paycekProvider */
    protected $paycekProvider;
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
        $this->paycekProvider = $this->getContainer()->get("paycek_provider");
        $this->errorLogManager = $this->getContainer()->get("error_log_manager");
        $this->translator = $this->getContainer()->get("translator");
        $this->logger = $this->getContainer()->get("logger");
        $this->initializeTwigVariables($request);
    }

    /**
     * @Route("/api/paycek_create", name="paycek_create")
     * @Method("POST")
     */
    public function paycekCreateAction(Request $request)
    {
        $p = $_POST;

        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $session = $request->getSession();

        $urls = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $urls["quoteUrl"];
        $quoteErrorUrl = $urls["quoteErrorUrl"];
        $quoteSuccessUrl = $urls["quoteSuccessUrl"];

        if (!isset($p["cart"]) || empty($p["cart"])) {
            $this->logger->error("Paycek error: cart is empty");
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($p["cart"]);
        if (empty($quote)) {
            $this->logger->error("Paycek error: quote does not exist: " . $p["cart"]);
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        $url = $this->paycekProvider->generatePaymentUrlForQuote($quote);
        if (empty($url)) {
            $this->logger->error("Paycek error: payment url is empty, quote id: " . $quote->getId());
            $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));
            return $this->redirect($quoteErrorUrl, 301);
        }

        return $this->redirect($url, 301);
    }

    /**
     * @Route("/api/paycek_callback", name="paycek_callback")
     * @Method("GET")
     */
    public function payCekCallbackAction(Request $request)
    {
        $g = $_GET;

        $this->initialize($request);

        $requestProfileSecretKey = $request->headers->get("Profile-Secret-Key");
        if (empty($requestProfileSecretKey)) {
            $this->logger->error("Paycek error: requestProfileSecretKey is empty: " . json_encode($request->headers->all()));
            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
        }
        $apiKeyAuthNonce = $request->headers->get("Apikeyauth-Nonce");
        if (empty($apiKeyAuthNonce)) {
            $this->logger->error("Paycek error: apiKeyAuthNonce is empty: " . json_encode($request->headers->all()));
            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
        }
        $apiKeyAuthMac = $request->headers->get("Apikeyauth-Mac");
        if (empty($apiKeyAuthMac)) {
            $this->logger->error("Paycek error: apiKeyAuthMac is empty: " . json_encode($request->headers->all()));
            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
        }
        $apiKeyAuthKey = $request->headers->get("Apikeyauth-Key");
        if (empty($apiKeyAuthKey)) {
            $this->logger->error("Paycek error: apiKeyAuthKey is empty: " . json_encode($request->headers->all()));
            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
        }

        /**
         * Ako koristimo API metodu za generiranje payment URL-a, onda gledamo PAYCEK_API_KEY,
         * u suprotnom gledamo PAYCEK_PROFILE_CODE
         */
//        if ($this->paycekProvider->getApiKey() != $apiKeyAuthKey) {
//            $this->logger->error("Paycek error: PAYCEK_API_KEY does not match apiKeyAuthKey: " . $apiKeyAuthKey);
//            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
//        }
        if ($this->paycekProvider->getProfileCode() != $apiKeyAuthKey) {
            $this->logger->error("Paycek error: PAYCEK_PROFILE_CODE does not match apiKeyAuthKey: " . $apiKeyAuthKey);
            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
        }
        if ($this->paycekProvider->getProfileSecret() != $requestProfileSecretKey) {
            $this->logger->error("Paycek error: PAYCEK_PROFILE_SECRET does not match requestProfileSecretKey: " . $requestProfileSecretKey);
            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
        }

        $apiMac = $this->paycekProvider->getApiMac(
            $apiKeyAuthKey,
            $requestProfileSecretKey,
            $apiKeyAuthNonce,
            "GET",
            "/api/paycek_callback",
            "",
            "");

        if ($apiMac != $apiKeyAuthMac) {
            $this->logger->error("Paycek error: generated apiMac does not match apiKeyAuthMac: " . $apiMac . " != " . $apiKeyAuthMac);
            return new Response("Bad Request", Response::HTTP_BAD_REQUEST);
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($g["id"]);
        if (empty($quote)) {
            $this->logger->error("Paycek error: quote does not exist: " . $g["id"]);
            return new Response("Internal Server Error", Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $paymentTransactionStatusId = null;

        switch ($g["status"]) {

            case PaymentProvidersConstants::PAYCEK_CALLBACK_STATUS_CREATED:
            case PaymentProvidersConstants::PAYCEK_CALLBACK_STATUS_WAITING_TRANSACTION:
            case PaymentProvidersConstants::PAYCEK_CALLBACK_STATUS_WAITING_CONFIRMATIONS:
                $paymentTransactionStatusId = CrmConstants::PAYMENT_TRANSACTION_STATUS_PREAUTHORISED;
                break;
            case PaymentProvidersConstants::PAYCEK_CALLBACK_STATUS_SUCCESSFUL:
                $paymentTransactionStatusId = CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED;
                break;
            case PaymentProvidersConstants::PAYCEK_CALLBACK_STATUS_UNDERPAID:
            case PaymentProvidersConstants::PAYCEK_CALLBACK_STATUS_EXPIRED:
            case PaymentProvidersConstants::PAYCEK_CALLBACK_STATUS_CANCELED:
                $paymentTransactionStatusId = CrmConstants::PAYMENT_TRANSACTION_STATUS_CANCELED;
                if (empty($this->errorLogManager)) {
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }
                $this->errorLogManager->logErrorEvent("Paycek: transaction unsuccessful", json_encode($g), true);
                break;
        }

        $data = array();
        $data["is_locked"] = 0;
        $quote = $this->quoteManager->updateQuote($quote, $data, true);

        if (!in_array($quote->getQuoteStatusId(), array(
            CrmConstants::QUOTE_STATUS_NEW,
            CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT,
            CrmConstants::QUOTE_STATUS_ACCEPTED))) {
            $this->logger->error("Paycek error: quote status not QUOTE_STATUS_NEW: " . $g["id"]);
            return new Response("Internal Server Error", Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        /** @var QuoteStatusEntity $quoteStatus */
        $quoteStatus = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_ACCEPTED);

        $this->quoteManager->changeQuoteStatus($quote, $quoteStatus);

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderByQuoteId($quote->getId());
        if (empty($order)) {
            $this->logger->error("Paycek error: order not created, quote: " . $g["id"]);
            return new Response("Internal Server Error", Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        /** @var PaymentTransactionStatusEntity $paymentTransactionStatus */
        $paymentTransactionStatus = $this->paymentTransactionManager->getPaymentTransactionStatusById($paymentTransactionStatusId);

        $transactionData = array();
        $transactionData["order"] = $order;
        $transactionData["provider"] = "paycek_provider";
        $transactionData["signature"] = null;
        $transactionData["transaction_identifier"] = $g["payment_code"];
        $transactionData["transaction_identifier_second"] = $g["profile_code"];
        $transactionData["amount"] = $quote->getPriceTotal();
        $transactionData["currency"] = $quote->getCurrency();
        $transactionData["transaction_status"] = $paymentTransactionStatus;
        $transactionData["payment_type"] = $order->getPaymentType();

        /** @var PaymentTransactionEntity $paymentTransaction */
        $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($transactionData, $paymentTransaction);
        if (empty($paymentTransaction)) {
            $this->logger->error("Paycek error: transaction not saved, order_id: " . $order->getId());
        }

        return new Response("OK", Response::HTTP_OK);
    }

    /**
     * @Route("/api/paycek_success", name="paycek_success")
     * @Method("GET")
     */
    public function paycekSuccessAction(Request $request)
    {
        $g = $_GET;

        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
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
     * @Route("/api/paycek_cancel", name="paycek_cancel")
     * @Method("GET")
     */
    public function paycekCancelAction(Request $request)
    {
        $g = $_GET;

        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $session = $request->getSession();

        $urls = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $urls["quoteUrl"];
        $quoteErrorUrl = $urls["quoteErrorUrl"];
        $quoteSuccessUrl = $urls["quoteSuccessUrl"];

        $session->set("quote_error", $this->translator->trans("Transaction successfully canceled"));

        return $this->redirect($quoteUrl, 301);
    }

    /**
     * @Route("/api/paycek_error", name="paycek_error")
     * @Method("GET")
     */
    public function paycekErrorAction(Request $request)
    {
        $g = $_GET;

        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $session = $request->getSession();

        $urls = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $urls["quoteUrl"];
        $quoteErrorUrl = $urls["quoteErrorUrl"];
        $quoteSuccessUrl = $urls["quoteSuccessUrl"];

        $session->set("quote_error", $this->translator->trans("Transaction failed. Please try again or use a different payment method."));

        return $this->redirect($quoteUrl, 301);
    }
}