<?php

namespace PaymentProvidersBusinessBundle\PaymentProviders;

use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\PaymentTransactionStatusEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use PaymentProvidersBusinessBundle\Abstracts\AbstractPaymentProvider;
use PaymentProvidersBusinessBundle\Interfaces\PaymentProviderInterface;

class BankartProvider extends AbstractPaymentProvider implements PaymentProviderInterface
{
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;

    protected $apiUsername;
    protected $apiPassword;
    protected $apiUrl;
    protected $apiKey;
    protected $apiSharedSecret;
    protected $successUrl;
    protected $cancelUrl;
    protected $errorUrl;
    protected $callbackUrl;

    public function initialize()
    {
        parent::initialize();

        $this->apiUsername = $_ENV["BANKART_API_USERNAME"];
        $this->apiPassword = $_ENV["BANKART_API_PASSWORD"];
        $this->apiUrl = $_ENV["BANKART_API_URL"];
        $this->apiKey = $_ENV["BANKART_API_KEY"];
        $this->apiSharedSecret = $_ENV["BANKART_API_SHARED_SECRET"];
    }

    /**
     * @param OrderEntity $order
     * @return false|null
     */
    public function renderTemplateFromOrder(OrderEntity $order)
    {
        return false;
    }

    /**
     * @param QuoteEntity $quote
     * @return array|null
     */
    public function renderTemplateFromQuote(QuoteEntity $quote)
    {
        $ret = array();
        $ret["forms"] = null;
        $ret["buttons"] = array();
        $ret["signature"] = null;

        $paymentType = $quote->getPaymentType();

        $config = json_decode($paymentType->getConfiguration(), true);
        if (empty($config)) {
            return $ret;
        }

        $base_payment_data = array(
            "action" => $config["action"],
            "cart" => $quote->getPreviewHash()
        );

        $ret["forms"][] = $this->twig->render("PaymentProvidersBusinessBundle:PaymentProviders:Bankart/form.html.twig",
            array("data" => $base_payment_data));
        $ret["buttons"][] = $this->getPaymentButtonsHtml($base_payment_data, $config);
        $ret["signature"] = null;

        return $ret;
    }

    /**
     * @param $data
     * @param $config
     * @return mixed
     */
    public function getPaymentButtonsHtml($data, $config)
    {
        $buttonTemplate = array(
            "type" => "button",
            "name" => "",
            "class" => "btn-primary btn-blue",
            "url" => "",
            "action" => "bankart"
        );

        $button = $buttonTemplate;
        $button["data"]["hash"] = "bankart" . $data["cart"];
        $buttonHtml = $this->twig->render($config["checkout_button"], array("data" => $button));

        return $buttonHtml;
    }

    /**
     * @param $amount
     * @return string|string[]
     */
    public function getFormattedAmount($amount)
    {
        $amount = number_format($amount, 2, ".", "");
        //$amount = str_replace(".", "", $amount);

        return $amount;
    }

    /**
     * @param $url
     * @param $method
     * @param $json
     * @param $contentType
     * @param $timestamp
     * @return string
     */
    public function generateSignature($url, $method, $json, $contentType, $timestamp)
    {
        $signatureMessage = join("\n", [$method, hash('sha512', $json), $contentType, $timestamp, $url]);

        $digest = hash_hmac('sha512', $signatureMessage, $this->apiSharedSecret, true);

        return base64_encode($digest);
    }

    /**
     * @param string $url
     * @param string $method
     * @param array $data
     * @return mixed
     * @throws \Exception
     */
    public function getBankartResponse(string $url, string $method, array $data)
    {
        $json = json_encode($data);

        $contentType = 'application/json; charset=utf-8';
        $timestamp = (new \DateTime('now', new \DateTimeZone('UTC')))->format('D, d M Y H:i:s T');
        $signature = $this->generateSignature($url, $method, $json, $contentType, $timestamp);

        $header = array(
            'Authorization: Basic ' . base64_encode($this->apiUsername . ':' . $this->apiPassword),
            'Date: ' . $timestamp,
            'X-Signature: ' . $signature,
            'Content-Type: ' . $contentType
        );

        $restManager = new RestManager();

        $restManager->CURLOPT_CUSTOMREQUEST = $method;
        $restManager->CURLOPT_POSTFIELDS = $json;
        $restManager->CURLOPT_HTTPHEADER = $header;

        return $restManager->get($this->apiUrl . $url);
    }

    /**
     * @param QuoteEntity $quote
     * @return bool
     */
    public function getBankartUrls(QuoteEntity $quote)
    {

        $baseUrl = $_ENV["SSL"] . "://" . $quote->getStore()->getWebsite()->getBaseUrl();

        $this->cancelUrl = $baseUrl . "/api/bankart_cancel";
        $this->errorUrl = $baseUrl . "/api/bankart_error";
        $this->successUrl = $baseUrl . "/api/bankart_success?hash=";
        $this->callbackUrl = $baseUrl . "/api/bankart_callback";

        return true;
    }

    /**
     * @param QuoteEntity $quote
     * @return mixed
     * @throws \Exception
     */
    public function apiPreauthorizePayment(QuoteEntity $quote)
    {
        $this->getBankartUrls($quote);

        $data = array(
            "merchantTransactionId" => $quote->getPreviewHash(),
            "amount" => $this->getFormattedAmount($quote->getPriceTotal()),
            "currency" => $quote->getCurrency()->getCode(),
            "customer" => array(
                "billingAddress1" => $quote->getAccountBillingStreet(),
                "billingCity" => $quote->getAccountBillingCity()->getName(),
                "billingCountry" => $quote->getAccountBillingCity()->getCountry()->getCode(),
                "billingPostcode" => $quote->getAccountBillingCity()->getPostalCode(),
                "email" => $quote->getAccountEmail()
            ),
            "successUrl" => $this->successUrl . $quote->getPreviewHash(),
            "cancelUrl" => $this->cancelUrl,
            "errorUrl" => $this->errorUrl,
            "callbackUrl" => $this->callbackUrl
        );

        return $this->getBankartResponse("/api/v3/transaction/" . $this->apiKey . "/preauthorize", "POST", $data);
    }

    /**
     * @param QuoteEntity $quote
     * @return mixed
     * @throws \Exception
     */
    public function apiDebitPayment(QuoteEntity $quote)
    {
        $this->getBankartUrls($quote);

        $data = array(
            "merchantTransactionId" => $quote->getPreviewHash(),
            "amount" => $this->getFormattedAmount($quote->getPriceTotal()),
            "currency" => $quote->getCurrency()->getCode(),
            "customer" => array(
                "billingAddress1" => $quote->getAccountBillingStreet(),
                "billingCity" => $quote->getAccountBillingCity()->getName(),
                "billingCountry" => $quote->getAccountBillingCity()->getCountry()->getCode(),
                "billingPostcode" => $quote->getAccountBillingCity()->getPostalCode(),
                "email" => $quote->getAccountEmail()
            ),
            "successUrl" => $this->successUrl . $quote->getPreviewHash(),
            "cancelUrl" => $this->cancelUrl,
            "errorUrl" => $this->errorUrl,
            "callbackUrl" => $this->callbackUrl
        );

        /**
         * array:6 [
         * "success" => true
         * "uuid" => "f0a98e5ce7927d9428ef"
         * "purchaseId" => "20211125-f0a98e5ce7927d9428ef"
         * "returnType" => "REDIRECT"
         * "redirectUrl" => "https://gateway.bankart.si/redirect/f0a98e5ce7927d9428ef/Njk3NTUzNjkzZjI4NGQzYTIzY2Y3NzRhMTFlOWU0NTdlYTAwNmRiZGZiZjJhNTZmMWJjOWQzZTY2ZTg0YzFlYWJiODg4NTc4ODA1NWI4YTE4NGZlNTQ1OWUxOWE2ZWY3YmI4N2U2ZGMxNmZhM2FhMThiYTIxODM4YTEzZmI2ZGQ="
         * "paymentMethod" => "Creditcard"
         * ]
         */

        return $this->getBankartResponse("/api/v3/transaction/" . $this->apiKey . "/debit", "POST", $data);
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return mixed
     * @throws \Exception
     */
    public function apiCapturePayment(PaymentTransactionEntity $paymentTransaction)
    {
        /** @var QuoteEntity $quote */
        $quote = $paymentTransaction->getOrder()->getQuote();

        $data = array(
            "merchantTransactionId" => $paymentTransaction->getTransactionIdentifierSecond(),
            "amount" => $this->getFormattedAmount($quote->getPriceTotal()),
            "currency" => $quote->getCurrency()->getCode(),
            "referenceUuid" => $paymentTransaction->getTransactionIdentifier()
        );

        return $this->getBankartResponse("/api/v3/transaction/" . $this->apiKey . "/capture", "POST", $data);
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return mixed
     * @throws \Exception
     */
    public function apiRefundPayment(PaymentTransactionEntity $paymentTransaction)
    {
        /** @var QuoteEntity $quote */
        $quote = $paymentTransaction->getOrder()->getQuote();

        $data = array(
            "merchantTransactionId" => $paymentTransaction->getTransactionIdentifierSecond(),
            "amount" => $this->getFormattedAmount($quote->getPriceTotal()),
            "currency" => $quote->getCurrency()->getCode(),
            "referenceUuid" => $paymentTransaction->getTransactionIdentifier()
        );

        return $this->getBankartResponse("/api/v3/transaction/" . $this->apiKey . "/refund", "POST", $data);
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return mixed
     * @throws \Exception
     */
    public function apiVoidPayment(PaymentTransactionEntity $paymentTransaction)
    {
        $data = array(
            "merchantTransactionId" => $paymentTransaction->getTransactionIdentifierSecond(),
            "referenceUuid" => $paymentTransaction->getTransactionIdentifier()
        );

        return $this->getBankartResponse("/api/v3/transaction/" . $this->apiKey . "/void", "POST", $data);
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return mixed
     * @throws \Exception
     */
    public function apiGetPaymentStatus($hash)
    {
        return $this->getBankartResponse("/api/v3/status/" . $this->apiKey . "/getByMerchantTransactionId/" . $hash, "GET", array());
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return PaymentTransactionEntity|false|null
     * @throws \Exception
     */
    public function completeTransaction(PaymentTransactionEntity $paymentTransaction)
    {
        if ($paymentTransaction->getProvider() != "bankart_provider") {
            return false;
        }
        if ($paymentTransaction->getTransactionStatusId() != CrmConstants::PAYMENT_TRANSACTION_STATUS_PREAUTHORISED) {
            return false;
        }

        $ret = $this->apiCapturePayment($paymentTransaction);
        if (empty($ret)) {
            $this->logger->error("Bankart completeTransaction error: " . $paymentTransaction->getId());
            return false;
        }
        if (!isset($ret["success"]) || empty($ret["success"])) {
            $this->logger->error("Bankart completeTransaction error: " . $paymentTransaction->getId());
            return false;
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $paymentTransactionStatus = $this->paymentTransactionManager->getPaymentTransactionStatusById(CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED);

        $data = array();
        $data["transaction_status"] = $paymentTransactionStatus;
        $data["transaction_identifier_third"] = $ret["purchaseId"];

        $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($data, $paymentTransaction);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $this->crmProcessManager->processPayment($paymentTransaction);

        return $paymentTransaction;
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return PaymentTransactionEntity|false|null
     * @throws \Exception
     */
    public function refundTransaction(PaymentTransactionEntity $paymentTransaction)
    {
        if ($paymentTransaction->getProvider() != "bankart_provider") {
            return false;
        }
        if ($paymentTransaction->getTransactionStatusId() != CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED) {
            return false;
        }

        $ret = $this->apiRefundPayment($paymentTransaction);
        if (empty($ret)) {
            $this->logger->error("Bankart refundTransaction error: " . $paymentTransaction->getId());
            return false;
        }
        if (!isset($ret["success"]) || empty($ret["success"])) {
            $this->logger->error("Bankart refundTransaction error: " . $paymentTransaction->getId());
            return false;
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $paymentTransactionStatus = $this->paymentTransactionManager->getPaymentTransactionStatusById(CrmConstants::PAYMENT_TRANSACTION_STATUS_REVERSAL);

        $data = array();
        $data["transaction_status"] = $paymentTransactionStatus;
        $data["transaction_identifier_third"] = $ret["purchaseId"];

        $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($data, $paymentTransaction);

        /*if(empty($this->crmProcessManager)){
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }*/

        //$this->crmProcessManager->processRefund($paymentTransaction);

        return $paymentTransaction;
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return PaymentTransactionEntity|false|null
     * @throws \Exception
     */
    public function voidTransaction(PaymentTransactionEntity $paymentTransaction)
    {
        if ($paymentTransaction->getProvider() != "bankart_provider") {
            return false;
        }
        if ($paymentTransaction->getTransactionStatusId() != CrmConstants::PAYMENT_TRANSACTION_STATUS_PREAUTHORISED) {
            return false;
        }

        $ret = $this->apiVoidPayment($paymentTransaction);
        if (empty($ret)) {
            $this->logger->error("Bankart voidTransaction error: " . $paymentTransaction->getId());
            return false;
        }
        if (!isset($ret["success"]) || empty($ret["success"])) {
            $this->logger->error("Bankart voidTransaction error: " . $paymentTransaction->getId());
            return false;
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        /** @var PaymentTransactionStatusEntity $paymentTransactionStatus */
        $paymentTransactionStatus = $this->paymentTransactionManager->getPaymentTransactionStatusById(CrmConstants::PAYMENT_TRANSACTION_STATUS_CANCELED);

        $data = array();
        $data["transaction_status"] = $paymentTransactionStatus;
        $data["transaction_identifier_third"] = $ret["purchaseId"];

        $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($data, $paymentTransaction);

        return $paymentTransaction;
    }
}