<?php

namespace PaymentProvidersBusinessBundle\PaymentProviders;

use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use PaymentProvidersBusinessBundle\Abstracts\AbstractPaymentProvider;
use PaymentProvidersBusinessBundle\Constants\PaymentProvidersConstants;
use PaymentProvidersBusinessBundle\Interfaces\PaymentProviderInterface;
use PaymentProvidersBusinessBundle\Managers\PaymentTransactionManager;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\RouteManager;

class PaywayProvider extends AbstractPaymentProvider implements PaymentProviderInterface
{
    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var RestManager $restManager */
    protected $restManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var PaymentTransactionManager $paymentTransactionManager */
    protected $paymentTransactionManager;

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
        $ret["forms"] = array();
        $ret["buttons"] = array();
        $ret["signature"] = null;

        $paymentType = $quote->getPaymentType();

        $config = json_decode($paymentType->getConfiguration(), true);

        if (empty($config)) {
            return $ret;
        }

        $totalUnpaid = array();
        $totalUnpaid["total"] = $quote->getBasePriceTotal();
        $totalUnpaid["hash"] = $quote->getPreviewHash();

        if (empty($totalUnpaid)) {
            return $ret;
        }

        /** @var ContactEntity $contact */
        $contact = $quote->getContact();

        try {

            $base_payment_data = [
                "ShopID" => $config["shop_id"],
                "CustomerFirstName" => $contact->getFirstName(),
                "CustomerLastName" => $contact->getLastName(),
                "CustomerCity" => $quote->getAccountBillingCity()->getName(),
                "CustomerAddress" => $quote->getAccountBillingStreet(),
                "CustomerZIP" => $quote->getAccountBillingCity()->getPostalCode(),
                "CustomerPhone" => $contact->getPhone(),
                "CustomerEmail" => $contact->getEmail(),
                "CustomerCountry" => $quote->getAccountBillingCity()->getCountry()->getName() ?? "",
            ];

        } catch (\Exception $e) {
            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }
            $this->errorLogManager->logExceptionEvent($e->getMessage(), $e, true);
            return $ret;
        }

        if (EntityHelper::checkIfMethodExists($contact, 'getLanguage')) {
            if (!empty($contact->getLanguage())) {
                $base_payment_data["Lang"] = strtoupper($contact->getLanguage()->getCode());
            }
        }
        if (!isset($base_payment_data["Lang"]) || empty($base_payment_data["Lang"])) {
            $base_payment_data["Lang"] = $config["default_lang"];
        }

        $amount = number_format($totalUnpaid["total"], 2, ",", "");
        $amount = str_replace(".", "", $amount);

        $base_payment_data["TotalAmount"] = $amount;
        $base_payment_data["cart"] = $base_payment_data["ShoppingCartID"] = $totalUnpaid["hash"];
        $base_payment_data["Signature"] = $this->generateSignature($config, $totalUnpaid["hash"], $base_payment_data);
        $base_payment_data["action"] = $config["action"];

        /** @var SStoreEntity $store */
        $store = $quote->getStore();

        if (empty($store)) {
            $session = $this->container->get("session");

            $websiteId = $session->get("current_website_id");

            if (empty($websiteId)) {
                $websiteId = $_ENV["DEFAULT_WEBSITE_ID"] ?? 1;
            }

            /** @var RouteManager $routeManager */
            $routeManager = $this->container->get("route_manager");

            $websiteData = $routeManager->getWebsiteDataById($websiteId);

            if (empty($websiteData)) {
                $base_payment_data["site_base_url"] = $_ENV["SSL"] . "://" . $_ENV["FRONTEND_URL"];
            } else {
                $base_payment_data["site_base_url"] = $_ENV["SSL"] . "://" . $websiteData["base_url"];
            }
        } else {
            $base_payment_data["site_base_url"] = $_ENV["SSL"] . "://" . $store->getWebsite()->getBaseUrl();
        }

        $ret["forms"][] = $this->twig->render('PaymentProvidersBusinessBundle:PaymentProviders:Payway/form.html.twig', array("data" => $base_payment_data));
        $ret["buttons"][] = $this->getPaymentButtonsHtml($base_payment_data, $config);
        $ret["signature"] = $base_payment_data["Signature"];

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $logData = Array();
        $logData["action"] = "prepare";
        $logData["has_error"] = 0;
        $logData["request_data"] = json_encode($base_payment_data);
        $logData["name"] = "Payway prepare";
        $logData["payment_type"] = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::PAYWAY_PROVIDER_CODE);
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_PREPARE);
        $logData["quote"] = $quote;
        $logData["description"] = "Successful render template";
        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        return $ret;
    }

    /**
     * @param $config
     * @param $hash
     * @param $data
     * @return string
     */
    public function generateSignature($config, $hash, $data)
    {
        $data["TotalAmount"] = str_replace(",", "", $data["TotalAmount"]);
        return md5($config["shop_id"] . $config["key"] . $hash . $config["key"] . $data["TotalAmount"] . $config["key"]);
    }

    public function generateSignatureComplete($config, $data)
    {
        $data["Amount"] = str_replace(",", "", $data["Amount"]);
        dump($config["shop_id"]);
        dump($data["WsPayOrderID"]);
        dump($config["key"]);
        dump($data["STAN"]);
        dump($config["key"]);
        dump($data["ApprovalCode"]);
        dump($config["key"]);
        dump($data["Amount"]);
        dump($config["key"]);
        dump($data["WsPayOrderID"]);
        //ShopID + SecretKey + ShoppingCartID + SecretKey + TotalAmount + SecretKey
        dump($config["shop_id"] . $data["WsPayOrderID"] . $config["key"] . $data["STAN"] . $config["key"] . $data["ApprovalCode"] . $config["key"] . $data["Amount"] . $config["key"] . $data["WsPayOrderID"]);
        return md5($config["shop_id"] . $data["WsPayOrderID"] . $config["key"] . $data["STAN"] . $config["key"] . $data["ApprovalCode"] . $config["key"] . $data["Amount"] . $config["key"] . $data["WsPayOrderID"]);

        return md5($config["shop_id"] . $config["key"] . $data["ShoppingCartID"] . $config["key"] . $data["Amount"] . $config["key"]);
    }

    /**
     * @param $data
     * @param $config
     * @return array|bool
     */
    public function getPaymentButtonsHtml($data, $config)
    {

        $buttonTemplate = array("type" => "button", "name" => "", "class" => "btn-primary btn-blue", "url" => "", "action" => "payway");

        $button = $buttonTemplate;
        $button["data"]["hash"] = "payway" . $data["cart"];
        $buttonHtml = $this->twig->render($config["checkout_button"], array("data" => $button));

        return $buttonHtml;
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return bool|PaymentTransactionEntity
     */
    public function completeTransaction(PaymentTransactionEntity $paymentTransaction)
    {

        if ($paymentTransaction->getProvider() != "payway_provider") {
            return false;
        }

        if ($paymentTransaction->getTransactionStatusId() != CrmConstants::PAYMENT_TRANSACTION_STATUS_PREAUTHORISED) {
            return false;
        }

        $config = json_decode($paymentTransaction->getPaymentType()->getConfiguration(), true);

        $post = array();
        $post["WsPayOrderID"] = $paymentTransaction->getTransactionIdentifierThird();
        $post["ShopID"] = $config["shop_id"];
        $post["ApprovalCode"] = $paymentTransaction->getTransactionIdentifier();
        $post["STAN"] = $paymentTransaction->getTransactionIdentifierSecond();
        $post["ShoppingCartID"] = $paymentTransaction->getOrder()->getQuote()->getPreviewHash();

        $amount = $paymentTransaction->getAmount();
        $amount = number_format($amount, 2, ",", "");
        $amount = str_replace(".", "", $amount);
        $post["Amount"] = str_replace(",", "", $amount);

        $post["Signature"] = $this->generateSignatureComplete($config, $post);

        $post = json_encode($post);

        $this->restManager = $this->container->get("rest_manager");
        $this->restManager->CURLOPT_TIMEOUT = 90;
        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_POSTFIELDS = $post;
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array("Content-Type: application/json");

        $res = $this->restManager->get($config["complete_action"]);

        if (empty($res) || !is_array($res)) {
            return false;
        }

        if (!isset($res["ActionSuccess"])) {
            return false;
        }
        if (!isset($res["Signature"]) || empty($res["Signature"])) {
            return false;
        }
        if (!isset($res["ApprovalCode"]) || empty($res["ApprovalCode"])) {
            return false;
        }

        if ($res["ActionSuccess"] != 1) {
            $this->logger->error("PAYWAY ERROR - complete transaction: " . $paymentTransaction->getTransactionIdentifier() . " - {$res["ErrorMessage"]}");
            return false;
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $paymentTransactionStatus = $this->paymentTransactionManager->getPaymentTransactionStatusById(CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED);

        $data = array();
        $data["transaction_status"] = $paymentTransactionStatus;

        $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($data, $paymentTransaction);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $this->crmProcessManager->processPayment($paymentTransaction);

        return $paymentTransaction;
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return bool|PaymentTransactionEntity
     */
    public function refundTransaction(PaymentTransactionEntity $paymentTransaction)
    {

        if ($paymentTransaction->getProvider() != "payway_provider") {
            return false;
        }

        if ($paymentTransaction->getTransactionStatusId() != CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED) {
            return false;
        }

        $config = json_decode($paymentTransaction->getPaymentType()->getConfiguration(), true);

        $post = array();
        $post["WsPayOrderID"] = $paymentTransaction->getTransactionIdentifierThird();
        $post["ShopID"] = $config["shop_id"];
        $post["ApprovalCode"] = $paymentTransaction->getTransactionIdentifier();
        $post["STAN"] = $paymentTransaction->getTransactionIdentifierSecond();
        $post["ShoppingCartID"] = $paymentTransaction->getOrder()->getQuote()->getPreviewHash();

        $amount = $paymentTransaction->getAmount();
        $amount = number_format($amount, 2, ",", "");
        $amount = str_replace(".", "", $amount);
        $post["Amount"] = str_replace(",", "", $amount);

        $post["Signature"] = $this->generateSignatureComplete($config, $post);

        $post = json_encode($post);

        $this->restManager = $this->container->get("rest_manager");
        $this->restManager->CURLOPT_TIMEOUT = 90;
        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_POSTFIELDS = $post;
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array("Content-Type: application/json");

        $res = $this->restManager->get($config["complete_action"]);

        if (empty($res) || !is_array($res)) {
            return false;
        }

        if (!isset($res["ActionSuccess"])) {
            return false;
        }
        if (!isset($res["Signature"]) || empty($res["Signature"])) {
            return false;
        }
        if (!isset($res["ApprovalCode"]) || empty($res["ApprovalCode"])) {
            return false;
        }

        if ($res["ActionSuccess"] != 1) {
            $this->logger->error("PAYWAY ERROR - refund transaction: " . $paymentTransaction->getTransactionIdentifier());
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
}
