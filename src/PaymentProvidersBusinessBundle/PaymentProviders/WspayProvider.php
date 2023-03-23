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
use PaymentProvidersBusinessBundle\Interfaces\PaymentProviderInterface;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\RouteManager;

class WspayProvider extends AbstractPaymentProvider implements PaymentProviderInterface
{

    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var RestManager $restManager */
    protected $restManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;

    public function renderTemplateFromOrder(OrderEntity $order)
    {

        return false;
    }

    /**
     * @param QuoteEntity $quote
     * @return bool|null
     */
    public function renderTemplateFromQuote(QuoteEntity $quote)
    {
        $ret = array();
        $ret["forms"] = array();
        $ret["buttons"] = array();
        $ret["signature"] = null;

        $paymentType = $quote->getPaymentType();

        $configPerCurrency = json_decode($paymentType->getConfiguration(), true);

        $currencyCode = $quote->getCurrency()->getCode();
        if (!isset($configPerCurrency[$currencyCode])) {
            $config = $configPerCurrency[$configPerCurrency["default_currency_code"]];
        } else {
            $config = $configPerCurrency[$currencyCode];
        }

        if (empty($config)) {
            return false;
        }

        $config["action"] = $configPerCurrency["action"];
        $config["checkout_button"] = $configPerCurrency["checkout_button"];

        $totalUnpaid = array();
        $totalUnpaid["total"] = $quote->getBasePriceTotal();
        $totalUnpaid["hash"] = $quote->getPreviewHash();

        if (empty($totalUnpaid)) {
            return $ret;
        }

        /** @var ContactEntity $contact */
        $contact = $quote->getContact();

        $base_payment_data = [
            "ShopID" => $config["shop_id"],
            "Action" => $config["action"],
            "CustomerFirstName" => $contact->getFirstName(),
            "CustomerLastName" => $contact->getLastName(),
            "CustomerPhone" => $contact->getPhone(),
            "CustomerEmail" => $contact->getEmail(),
        ];

        if (EntityHelper::checkIfMethodExists($contact, 'getLanguage')) {
            if (!empty($contact->getLanguage())) {
                $base_payment_data["Lang"] = strtoupper($contact->getLanguage()->getCode());
            }
        }
        if (!isset($base_payment_data["Lang"]) || empty($base_payment_data["Lang"])) {
            $base_payment_data["Lang"] = $config["default_lang"];
        }

        if (!empty($quote->getAccountBillingCity())) {
            $base_payment_data["CustomerCity"] = $quote->getAccountBillingCity()->getName();
            $base_payment_data["CustomerAddress"] = $quote->getAccountBillingStreet();
            $base_payment_data["CustomerZIP"] = $quote->getAccountBillingCity()->getPostalCode();
            $base_payment_data["CustomerCountry"] = $quote->getAccountBillingCity()->getCountry()->getName();
        }

        $amount = number_format($totalUnpaid["total"], 2, ",", "");
        $amount = str_replace(".", "", $amount);

        $base_payment_data["TotalAmount"] = $amount;
        $base_payment_data["cart"] = $base_payment_data["ShoppingCartID"] = $totalUnpaid["hash"];

        $base_payment_data["Signature"] = $this->generateSignature($config, $totalUnpaid["hash"], $amount);

        /** @var SStoreEntity $store */
        $store = $quote->getStore();

        if (empty($store)) {
            $session = $this->container->get("session");

            $websiteId = $session->get("current_website_id");

            if (empty($websiteId)) {
                $this->logger->error("Missing website id for quote: " . $quote->getId());
                $websiteId = $_ENV["DEFAULT_WEBSITE_ID"] ?? 1;
            }

            /** @var RouteManager $routeManager */
            $routeManager = $this->container->get("route_manager");

            $websiteData = $routeManager->getWebsiteDataById($websiteId);
            if (empty($websiteData)) {
                $this->logger->error("Missing website data for website: " . $websiteId);
                $base_payment_data["site_base_url"] = $_ENV["SSL"] . "://" . $_ENV["FRONTEND_URL"];
            } else {
                $base_payment_data["site_base_url"] = $_ENV["SSL"] . "://" . $websiteData["base_url"];
            }
        } else {
            $base_payment_data["site_base_url"] = $_ENV["SSL"] . "://" . $store->getWebsite()->getBaseUrl();
        }

        $ret["forms"][] = $this->twig->render(
            'PaymentProvidersBusinessBundle:PaymentProviders:WsPay/form.html.twig',
            array("data" => $base_payment_data)
        );
        $ret["buttons"][] = $this->getPaymentButtonsHtml($base_payment_data, $config);
        $ret["signature"] = $base_payment_data["Signature"];

        return $ret;

    }

    /**
     * @param $config
     * @param $hash
     * @param $amount
     * @return string
     */
    public function generateSignature($config, $hash, $amount)
    {
        $amount = str_replace(".", "", $amount);
        $amount = str_replace(",", "", $amount);

        return md5($config["shop_id"] . $config["key"] . $hash . $config["key"] . $amount . $config["key"]);
    }

    public function generateSignatureComplete($config, $data)
    {
        $data["Amount"] = str_replace(",", "", $data["Amount"]);
        $data["Amount"] = str_replace(".", "", $data["Amount"]);

        return md5(
            $config["shop_id"] . $data["ShoppingCartID"] . $config["key"] . $data["STAN"] . $config["key"] . $data["ApprovalCode"] . $config["key"] . $data["Amount"] . $config["key"] . $data["ShoppingCartID"]
        );
    }

    public function generateSignatureStatusCheck($config, $data)
    {
        return md5(
            $config["shop_id"] . $config["key"] . $data["ShoppingCartID"] . $config["key"] . $config["shop_id"] . $config["ShoppingCartID"]
        );
    }

    /**
     * @param $data
     * @param $config
     * @return array|bool
     */
    public function getPaymentButtonsHtml($data, $config)
    {

        $buttonTemplate = array(
            "type" => "button",
            "name" => "",
            "class" => "btn-primary btn-blue",
            "url" => "",
            "action" => "wspay",
        );

        $button = $buttonTemplate;
        $button["data"]["hash"] = "wspay" . $data["cart"];
        $buttonHtml = $this->twig->render($config["checkout_button"], array("data" => $button));

        return $buttonHtml;
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return bool|PaymentTransactionEntity
     */
    public function completeTransaction(PaymentTransactionEntity $paymentTransaction)
    {

        if ($paymentTransaction->getProvider() != "wspay_provider") {
            return false;
        }

        if ($paymentTransaction->getTransactionStatusId() != CrmConstants::PAYMENT_TRANSACTION_STATUS_PREAUTHORISED) {
            return false;
        }

        $configPerCurrency = json_decode($paymentTransaction->getPaymentType()->getConfiguration(), true);

        $currencyCode = $paymentTransaction->getOrder()->getCurrency()->getCode();

        if (!isset($configPerCurrency[$currencyCode])) {
            $config = $configPerCurrency[$configPerCurrency["default_currency_code"]];
        } else {
            $config = $configPerCurrency[$currencyCode];
        }

        if (empty($config)) {
            return false;
        }

        $config["action"] = $configPerCurrency["action"];
        $config["checkout_button"] = $configPerCurrency["checkout_button"];
        $config["service_url"] = $configPerCurrency["service_url"];

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

        $res = $this->restManager->get($config["service_url"] . "Completion");

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
            $this->logger->error(
                "WSPAY ERROR - complete transaction: " . $paymentTransaction->getTransactionIdentifier() . " - {$res["ErrorMessage"]}"
            );

            return false;
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $paymentTransactionStatus = $this->paymentTransactionManager->getPaymentTransactionStatusById(
            CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED
        );

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

        if ($paymentTransaction->getProvider() != "wspay_provider") {
            return false;
        }

        if ($paymentTransaction->getTransactionStatusId() != CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED) {
            return false;
        }

        $configPerCurrency = json_decode($paymentTransaction->getPaymentType()->getConfiguration(), true);

        $currencyCode = $paymentTransaction->getOrder()->getCurrency()->getCode();

        if (!isset($configPerCurrency[$currencyCode])) {
            $config = $configPerCurrency[$configPerCurrency["default_currency_code"]];
        } else {
            $config = $configPerCurrency[$currencyCode];
        }

        if (empty($config)) {
            return false;
        }

        $config["action"] = $configPerCurrency["action"];
        $config["checkout_button"] = $configPerCurrency["checkout_button"];
        $config["service_url"] = $configPerCurrency["service_url"];

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

        $res = $this->restManager->get($config["service_url"] . "Refund");

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
            $this->logger->error("WSPAY ERROR - refund transaction: " . $paymentTransaction->getTransactionIdentifier());

            return false;
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $paymentTransactionStatus = $this->paymentTransactionManager->getPaymentTransactionStatusById(
            CrmConstants::PAYMENT_TRANSACTION_STATUS_REVERSAL
        );

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
     * @return bool|PaymentTransactionEntity
     */
    public function checkTransaction(PaymentTransactionEntity $paymentTransaction)
    {

        if ($paymentTransaction->getProvider() != "wspay_provider") {
            return false;
        }

        $configPerCurrency = json_decode($paymentTransaction->getPaymentType()->getConfiguration(), true);

        $currencyCode = $paymentTransaction->getOrder()->getCurrency()->getCode();

        if (!isset($configPerCurrency[$currencyCode])) {
            $config = $configPerCurrency[$configPerCurrency["default_currency_code"]];
        } else {
            $config = $configPerCurrency[$currencyCode];
        }

        if (empty($config)) {
            return false;
        }

        $config["action"] = $configPerCurrency["action"];
        $config["checkout_button"] = $configPerCurrency["checkout_button"];
        $config["service_url"] = $configPerCurrency["service_url"];

        $post = array();
        $post["ShopID"] = $config["shop_id"];
        $post["ShoppingCartID"] = $paymentTransaction->getOrder()->getQuote()->getPreviewHash();

        $post["Signature"] = $this->generateSignatureStatusCheck($config, $post);

        $post = json_encode($post);

        $this->restManager = $this->container->get("rest_manager");
        $this->restManager->CURLOPT_TIMEOUT = 90;
        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_POSTFIELDS = $post;
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array("Content-Type: application/json");

        $res = $this->restManager->get($config["service_url"] . "Completion");

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
            $this->logger->error(
                "WSPAY ERROR - complete transaction: " . $paymentTransaction->getTransactionIdentifier() . " - {$res["ErrorMessage"]}"
            );

            return false;
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $paymentTransactionStatus = $this->paymentTransactionManager->getPaymentTransactionStatusById(
            CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED
        );

        $data = array();
        $data["transaction_status"] = $paymentTransactionStatus;

        $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($data, $paymentTransaction);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $this->crmProcessManager->processPayment($paymentTransaction);

        return $paymentTransaction;
    }
}
