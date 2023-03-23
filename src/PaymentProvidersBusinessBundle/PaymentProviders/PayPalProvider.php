<?php

namespace PaymentProvidersBusinessBundle\PaymentProviders;

use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\PaymentTransactionStatusEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use PaymentProvidersBusinessBundle\Abstracts\AbstractPaymentProvider;
use PaymentProvidersBusinessBundle\Constants\PaymentProvidersConstants;
use PaymentProvidersBusinessBundle\Interfaces\PaymentProviderInterface;

class PayPalProvider extends AbstractPaymentProvider implements PaymentProviderInterface
{
    /** @var RestManager $restManager */
    protected $restManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;

    /** @var string $apiAuth */
    protected $apiAuth;
    /** @var string $apiUrl */
    protected $apiUrl;
    /** @var string $siteUrl */
    protected $siteUrl;
    /** @var string $siteName */
    protected $siteName;

    public function initialize()
    {
        parent::initialize();

        $this->restManager = $this->getContainer()->get("rest_manager");

        $this->apiAuth = base64_encode($_ENV["PAYPAL_USERNAME"] . ":" . $_ENV["PAYPAL_PASSWORD"]);
        $this->apiUrl = $_ENV["PAYPAL_API_URL"];
        $this->siteUrl = $_ENV["SSL"] . "://" . $_ENV["FRONTEND_URL"] . $_ENV["FRONTEND_URL_PORT"];
        $this->siteName = $_ENV["SITE_NAME"];
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

        $ret["forms"][] = $this->twig->render("PaymentProvidersBusinessBundle:PaymentProviders:PayPal/form.html.twig",
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
            "action" => "paypal"
        );

        $button = $buttonTemplate;
        $button["data"]["hash"] = "paypal" . $data["cart"];
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
     * @param $currencyCode
     * @param $amount
     * @return array
     */
    public function getPurchaseAmount($currencyCode, $amount)
    {
        return array(
            "currency_code" => $currencyCode,
            "value" => $this->getFormattedAmount($amount)
        );
    }

    /**
     * @param QuoteEntity $quote
     * @return array|null
     */
    public function getPurchaseUnits(QuoteEntity $quote)
    {
        $quoteItems = $quote->getQuoteItems();
        if (empty($quoteItems)) {
            return null;
        }

        $purchaseUnits = array();

        /** @var QuoteItemEntity $quoteItem */
        foreach ($quoteItems as $quoteItem) {

            if (empty($quoteItem->getPriceTotal())) {
                continue;
            }

            $purchaseAmount = $this->getPurchaseAmount($quote->getCurrency()->getCode(), $quoteItem->getPriceTotal());
//            $purchaseAmount["breakdown"]["item_total"] = $this->getPurchaseAmount($quote->getCurrency()->getCode(), $quoteItem->getPriceWithoutTax());
//            $purchaseAmount["breakdown"]["tax_total"] = $this->getPurchaseAmount($quote->getCurrency()->getCode(), $quoteItem->getPriceTax());

            $purchaseUnits[] = array(
                "reference_id" => $quote->getPreviewHash(),
                "amount" => $purchaseAmount
            );
        }

        return $purchaseUnits;
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return PaymentTransactionEntity|false|null
     * @throws \Exception
     */
    public function completeTransaction(PaymentTransactionEntity $paymentTransaction)
    {
        if ($paymentTransaction->getProvider() != "paypal_provider") {
            return false;
        }
        if ($paymentTransaction->getTransactionStatusId() != CrmConstants::PAYMENT_TRANSACTION_STATUS_PREAUTHORISED) {
            return false;
        }

        $accessToken = $this->getAccessToken();

        $response = $this->getAuthorizedPaymentDetails($accessToken, $paymentTransaction->getTransactionIdentifierSecond());
        if (!isset($response["status"])) {
            $this->logger->error("PayPal completeTransaction error: " . json_encode($response));
            return false;
        }

        $this->reauthorizeAuthorizedPayment($accessToken, $paymentTransaction->getTransactionIdentifierSecond());

        $transactionIdentifierThird = $this->captureAuthorizedPayment($accessToken, $paymentTransaction->getTransactionIdentifierSecond());
        if (empty($transactionIdentifierThird)) {
            $this->logger->error("PayPal completeTransaction error: " . $paymentTransaction->getId());
            return false;
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $paymentTransactionStatus = $this->paymentTransactionManager->getPaymentTransactionStatusById(CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED);

        $data = array();
        $data["transaction_status"] = $paymentTransactionStatus;
        $data["transaction_identifier_third"] = $transactionIdentifierThird;

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
        if ($paymentTransaction->getProvider() != "paypal_provider") {
            return false;
        }
        if ($paymentTransaction->getTransactionStatusId() != CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED) {
            return false;
        }

        $accessToken = $this->getAccessToken();

        $noteToPayer = "Order #" . $paymentTransaction->getOrder()->getIncrementId();

        $refundId = $this->refundCapturedPayment($accessToken, $paymentTransaction->getTransactionIdentifierThird(), $noteToPayer);
        if (empty($refundId)) {
            $this->logger->error("PayPal refundTransaction error: " . $paymentTransaction->getId());
            return false;
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $paymentTransactionStatus = $this->paymentTransactionManager->getPaymentTransactionStatusById(CrmConstants::PAYMENT_TRANSACTION_STATUS_REVERSAL);

        $data = array();
        $data["transaction_status"] = $paymentTransactionStatus;

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
        if ($paymentTransaction->getProvider() != "paypal_provider") {
            return false;
        }
        if ($paymentTransaction->getTransactionStatusId() != CrmConstants::PAYMENT_TRANSACTION_STATUS_PREAUTHORISED) {
            return false;
        }

        $accessToken = $this->getAccessToken();

        $res = $this->voidAuthorizedPayment($accessToken, $paymentTransaction->getTransactionIdentifierSecond());
        if (empty($res)) {
            $this->logger->error("PayPal voidTransaction error: " . $paymentTransaction->getId());
            return false;
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        /** @var PaymentTransactionStatusEntity $paymentTransactionStatus */
        $paymentTransactionStatus = $this->paymentTransactionManager->getPaymentTransactionStatusById(CrmConstants::PAYMENT_TRANSACTION_STATUS_CANCELED);

        $data = array();
        $data["transaction_status"] = $paymentTransactionStatus;

        $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($data, $paymentTransaction);

        return $paymentTransaction;
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function getAccessToken()
    {
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_POSTFIELDS = "grant_type=client_credentials";
        $this->restManager->CURLOPT_HTTPHEADER = array(
            "Authorization: Basic " . $this->apiAuth,
            "Content-Type: application/x-www-form-urlencoded"
        );

        $response = $this->restManager->get($this->apiUrl . "/v1/oauth2/token");

        if (!isset($response["access_token"])) {
            $this->logger->error("PayPal getAccessToken error: " . json_encode($response));
            return null;
        }

        return $response["access_token"];
    }

    /**
     * @param $accessToken
     * @param $orderId
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function getOrderDetails($accessToken, $orderId)
    {
        $header = array(
            "Authorization: Bearer " . $accessToken,
            "Content-Type: application/json"
        );

        $this->restManager->CURLOPT_CUSTOMREQUEST = "GET";
        $this->restManager->CURLOPT_HTTPHEADER = $header;

        return $this->restManager->get($this->apiUrl . "/v2/checkout/orders/" . $orderId);
    }

    /**
     * @param $accessToken
     * @param $authorizationId
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function getAuthorizedPaymentDetails($accessToken, $authorizationId)
    {
        $header = array(
            "Authorization: Bearer " . $accessToken,
            "Content-Type: application/json"
        );

        $this->restManager->CURLOPT_CUSTOMREQUEST = "GET";
        $this->restManager->CURLOPT_HTTPHEADER = $header;

        return $this->restManager->get($this->apiUrl . "/v2/payments/authorizations/" . $authorizationId);
    }

    /**
     * @param $accessToken
     * @param $intent
     * @param $purchaseUnits
     * @param $previewHash
     * @return mixed|null
     * @throws \Exception
     */
    public function createOrder($accessToken, $intent, $purchaseUnits, $previewHash)
    {
        $header = array(
            "Authorization: Bearer " . $accessToken,
            "Content-Type: application/json"
        );

        $data = array(
            "intent" => $intent,
            "application_context" => array(
                "brand_name" => $this->siteName,
                "locale" => "en-US",
                "user_action" => PaymentProvidersConstants::PAYPAL_USER_ACTION_PAY_NOW,
                "return_url" => $this->siteUrl . "/api/paypal_confirm?intent=" . $intent,
                "cancel_url" => $this->siteUrl . "/api/paypal_cancel" /* . "?reference_id=" . $previewHash */
            ),
            "purchase_units" => $purchaseUnits
        );

        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_POSTFIELDS = json_encode($data);
        $this->restManager->CURLOPT_HTTPHEADER = $header;

        $response = $this->restManager->get($this->apiUrl . "/v2/checkout/orders");

        $url = null;

        if (isset($response["id"]) &&
            isset($response["links"]) &&
            isset($response["status"]) && $response["status"] == "CREATED") {
            foreach ($response["links"] as $link) {
                if ($link["rel"] == "approve") {
                    $url = $link["href"];
                }
            }
        }

        if (empty($url)) {
            $this->logger->error("PayPal createOrder error: " . json_encode($response));
        }

        return $url;
    }

    /**
     * @param $accessToken
     * @param $orderId
     * @return bool
     * @throws \Exception
     */
    public function validateOrder($accessToken, $orderId)
    {
        $response = $this->getOrderDetails($accessToken, $orderId);

        if (!isset($response["status"]) || $response["status"] != "APPROVED") {
            $this->logger->error("PayPal validateOrder error: " . json_encode($response));
            return false;
        }

        return true;
    }

    /**
     * @param $accessToken
     * @param $intent
     * @param $orderId
     * @return array|null
     * @throws \Exception
     */
    public function completeOrder($accessToken, $intent, $orderId)
    {
        $header = array(
            "Authorization: Bearer " . $accessToken,
            "Content-Type: application/json"
        );

        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = $header;
        $this->restManager->CURLOPT_POSTFIELDS = "";

        $intent = strtolower($intent);

        $response = $this->restManager->get($this->apiUrl . "/v2/checkout/orders/" . $orderId . "/" . $intent);

        $ret = array();

        if (isset($response["status"]) && $response["status"] == "COMPLETED") {

            $ret = array(
                "preview_hash" => $response["purchase_units"][0]["reference_id"],
                "transaction_identifier" => $orderId,
                "transaction_identifier_second" => null,
                "transaction_identifier_third" => null
            );
            if (isset($response["purchase_units"][0]["payments"]["authorizations"])) {
                $ret["transaction_identifier_second"] = $response["purchase_units"][0]["payments"]["authorizations"][0]["id"];
            }
            if (isset($response["purchase_units"][0]["payments"]["captures"])) {
                $ret["transaction_identifier_third"] = $response["purchase_units"][0]["payments"]["captures"][0]["id"];
            }

        } else {
            $this->logger->error("PayPal completeOrder error: " . json_encode($response));
        }

        return $ret;
    }

    /**
     * @param $accessToken
     * @param $authorizationId
     * @return mixed|null
     * @throws \Exception
     */
    public function captureAuthorizedPayment($accessToken, $authorizationId)
    {
        $data = array(
            "final_capture" => true
        );

        $header = array(
            "Authorization: Bearer " . $accessToken,
            "Content-Type: application/json"
        );

        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_POSTFIELDS = json_encode($data);
        $this->restManager->CURLOPT_HTTPHEADER = $header;

        $response = $this->restManager->get($this->apiUrl . "/v2/payments/authorizations/" . $authorizationId . "/capture");

        $newId = null;
        if (isset($response["status"]) && $response["status"] == "COMPLETED") {
            $newId = $response["id"];
        } else {
            $this->logger->error("PayPal captureAuthorizedPayment error: " . json_encode($response));
        }

        return $newId;
    }

    /**
     * @param $accessToken
     * @param $captureId
     * @param $noteToPayer
     * @return mixed|null
     * @throws \Exception
     */
    public function refundCapturedPayment($accessToken, $captureId, $noteToPayer)
    {
        $data = array(
            "note_to_payer" => $noteToPayer
        );

        $header = array(
            "Authorization: Bearer " . $accessToken,
            "Content-Type: application/json"
        );

        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_POSTFIELDS = json_encode($data);
        $this->restManager->CURLOPT_HTTPHEADER = $header;

        $response = $this->restManager->get($this->apiUrl . "/v2/payments/captures/" . $captureId . "/refund");

        $refundId = null;
        if (isset($response["status"]) && $response["status"] == "COMPLETED") {
            $refundId = $response["id"];
        } else {
            $this->logger->error("PayPal refundCapturedPayment error: " . json_encode($response));
        }

        return $refundId;
    }

    /**
     * Reauthorizes an authorized PayPal account payment, by ID. To ensure that funds are still available, reauthorize
     * a payment after its initial three-day honor period expires. You can reauthorize a payment only once from days
     * four to 29.
     *
     * If 30 days have transpired since the date of the original authorization, you must create an authorized payment
     * instead of reauthorizing the original authorized payment.
     *
     * @param $accessToken
     * @param $authorizationId
     * @return mixed|null
     * @throws \Exception
     */
    public function reauthorizeAuthorizedPayment($accessToken, $authorizationId)
    {
        $header = array(
            "Authorization: Bearer " . $accessToken,
            "Content-Type: application/json"
        );

        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = $header;
        $this->restManager->CURLOPT_POSTFIELDS = "";

        return $this->restManager->get($this->apiUrl . "/v2/payments/authorizations/" . $authorizationId . "/reauthorize");
    }

    /**
     * @param $accessToken
     * @param $authorizationId
     * @return bool
     * @throws \Exception
     */
    public function voidAuthorizedPayment($accessToken, $authorizationId)
    {
        $header = array(
            "Authorization: Bearer " . $accessToken,
            "Content-Type: application/json"
        );

        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = $header;

        $this->restManager->get($this->apiUrl . "/v2/payments/authorizations/" . $authorizationId . "/void");

        if ($this->restManager->code == 204) {
            return true;
        }

        return false;
    }
}