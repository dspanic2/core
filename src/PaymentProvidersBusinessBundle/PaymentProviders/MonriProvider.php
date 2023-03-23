<?php

namespace PaymentProvidersBusinessBundle\PaymentProviders;

use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use PaymentProvidersBusinessBundle\Abstracts\AbstractPaymentProvider;
use PaymentProvidersBusinessBundle\Interfaces\PaymentProviderInterface;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use SimpleXMLElement;

class MonriProvider extends AbstractPaymentProvider implements PaymentProviderInterface
{
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
     * @return array
     */
    public function renderTemplateFromQuote(QuoteEntity $quote)
    {
        $ret = array();
        $ret["forms"] = array();
        $ret["buttons"] = array();
        $ret["signature"] = null;

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        /**
         * Create new hash because bankart has unique cart id requirement
         */
        $updateData = array();
        $updateData["preview_hash"] = StringHelper::generateHash($quote->getId(), time());
        $quote = $this->quoteManager->updateQuote($quote, $updateData);

        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $quote->getPaymentType();

        $config = json_decode($paymentType->getConfiguration(), true);
        if (empty($config)) {
            return $ret;
        }

        /** @var ContactEntity $contact */
        $contact = $quote->getContact();

        if (EntityHelper::checkIfMethodExists($contact, "getLanguage") && !empty($contact->getLanguage())) {
            $language = $contact->getLanguage()->getCode();
        } else {
            $language = $config["default_lang"];
        }

        $amount = number_format($quote->getBasePriceTotal(), 2, ",", "");
        $amount = str_replace(".", "", $amount);
        $amount = str_replace(",", "", $amount);

        $digest = $this->generateSignature($config, $quote->getPreviewHash(), $amount, $quote->getCurrency()->getCode());

        $base_payment_data = array(
            // Customer details
            "ch_full_name" => $contact->getFullName(),
            "ch_phone" => "",
            "ch_email" => "",
            // Order details
            "order_info" => "",
            "order_number" => $quote->getPreviewHash(),
            "amount" => $amount,
            "currency" => $quote->getCurrency()->getCode(),
            "number_of_installments" => "",
            // Processing data
            "language" => $language,
            "transaction_type" => $config["transaction_type"],
            "authenticity_token" => $config["authenticity_token"],
            "digest" => $digest,
            "moto" => true,
            "action" => $config["action"],
            "cart" => $quote->getPreviewHash()
        );

        $quoteItems = $quote->getQuoteItems();
        if (EntityHelper::isCountable($quoteItems) && count($quoteItems)) {
            $tmpOrderInfo = "";
            /** @var QuoteItemEntity $quoteItem */
            foreach ($quoteItems as $quoteItem) {
                if ($quoteItem->getProduct()->getProductTypeId() != CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
                    $tmpOrderInfo .= $quoteItem->getProduct()->getCode() . "_" . number_format($quoteItem->getQty(), "2", ",", "") . "  ";
                }
            }
            $tmpOrderInfo = strlen($tmpOrderInfo) > 100 ? substr($tmpOrderInfo, 0, 99) : $tmpOrderInfo;
            $base_payment_data["order_info"] = $tmpOrderInfo;
        }

        if (!empty($quote->getAccountPhone()) && strlen($quote->getAccountPhone()) >= 3 && strlen($quote->getAccountPhone()) <= 30) {
            $base_payment_data["ch_phone"] = $quote->getAccountPhone();
        }
        if (!empty($quote->getAccountEmail()) && strlen($quote->getAccountEmail()) >= 3 && strlen($quote->getAccountEmail()) <= 100) {
            $base_payment_data["ch_email"] = $quote->getAccountEmail();
        }

        if (!empty($quote->getAccountBillingCity())) {
            $base_payment_data["ch_address"] = $quote->getAccountBillingStreet();
            $base_payment_data["ch_city"] = $quote->getAccountBillingCity()->getName();
            $base_payment_data["ch_zip"] = $quote->getAccountBillingCity()->getPostalCode();
            $base_payment_data["ch_country"] = $quote->getAccountBillingCity()->getCountry()->getCode();
        }

        /**
         * Length and type checks
         */
        if (strlen($base_payment_data["ch_full_name"]) < 3 || strlen($base_payment_data["ch_full_name"]) > 30) {
            $base_payment_data["ch_full_name"] = "";
        }
        if (strlen($base_payment_data["ch_address"]) < 3 || strlen($base_payment_data["ch_address"]) > 100) {
            $base_payment_data["ch_address"] = "";
        }
        if (strlen($base_payment_data["ch_city"]) < 3 || strlen($base_payment_data["ch_city"]) > 30) {
            $base_payment_data["ch_city"] = "";
        }
        if (strlen($base_payment_data["ch_zip"]) < 3 || strlen($base_payment_data["ch_zip"]) > 9) {
            $base_payment_data["ch_zip"] = "";
        }
        if (strlen($base_payment_data["ch_country"]) < 2 || strlen($base_payment_data["ch_country"]) > 3) {
            $base_payment_data["ch_country"] = "";
        }
        if (strlen($base_payment_data["order_info"]) < 3 || strlen($base_payment_data["order_info"]) > 100) {
            return $ret;
        }
        if (strlen($base_payment_data["order_number"]) < 1 || strlen($base_payment_data["order_number"]) > 40) {
            return $ret;
        }
        if (!isset($base_payment_data["amount"]) ||
            empty($base_payment_data["amount"]) ||
            strlen((string)$base_payment_data["amount"]) < 3 ||
            strlen((string)$base_payment_data["amount"]) > 11) {
            return $ret;
        }

        $ret["forms"][] = $this->twig->render("PaymentProvidersBusinessBundle:PaymentProviders:Monri/form.html.twig",
            array("data" => $base_payment_data));
        $ret["buttons"][] = $this->getPaymentButtonsHtml($base_payment_data, $config);
        $ret["signature"] = $base_payment_data["digest"];

        return $ret;
    }

    /**
     * @param $config
     * @param $order_number
     * @param $amount
     * @param $currency
     * @return string
     */
    public function generateSignature($config, $order_number, $amount, $currency)
    {
        $amount = str_replace(".", "", $amount);
        $amount = str_replace(",", "", $amount);

        return openssl_digest($config["key"] . $order_number . $amount . $currency, "sha512", false);
    }

    /**
     * @param $config
     * @param $data
     * @param QuoteEntity $quote
     * @return false|string
     */
    public function generateSignatureComplete($config, $data, QuoteEntity $quote)
    {

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
                $baseUrl = $_ENV["SSL"] . "://" . $_ENV["FRONTEND_URL"] . $_ENV["FRONTEND_URL_PORT"];
            } else {
                $baseUrl = $_ENV["SSL"] . "://" . $websiteData["base_url"] . $_ENV["FRONTEND_URL_PORT"];
            }
        } else {
            $baseUrl = $_ENV["SSL"] . "://" . $store->getWebsite()->getBaseUrl() . $_ENV["FRONTEND_URL_PORT"];
        }

        return openssl_digest($config["key"] . $baseUrl . $data, "sha512", false);
    }

    /**
     * @param $config
     * @param $order_number
     * @param $amount
     * @param $currency
     * @return false|string
     */
    public function generateSignatureCapture($config, $order_number, $amount, $currency)
    {
        return openssl_digest($config["key"] . $order_number . $amount . $currency, "sha1", false);
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
            "action" => "monri",
        );

        $button = $buttonTemplate;
        $button["data"]["hash"] = "monri" . $data["cart"];
        $buttonHtml = $this->twig->render($config["checkout_button"], array("data" => $button));

        return $buttonHtml;
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return bool|PaymentTransactionEntity
     * @throws \Exception
     */
    public function completeTransaction(PaymentTransactionEntity $paymentTransaction)
    {
        if ($paymentTransaction->getProvider() != "monri_provider") {
            return false;
        }

        if ($paymentTransaction->getTransactionStatusId() != CrmConstants::PAYMENT_TRANSACTION_STATUS_PREAUTHORISED) {
            return false;
        }

        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $paymentTransaction->getPaymentType();

        $config = json_decode($paymentType->getConfiguration(), true);
        if (empty($config)) {
            return false;
        }

        $amount = number_format($paymentTransaction->getAmount(), 2, ",", "");
        $amount = str_replace(".", "", $amount);
        $amount = str_replace(",", "", $amount);

        $currency = $paymentTransaction->getCurrency()->getCode();
        $order_number = $paymentTransaction->getOrder()->getPreviewHash();
        $digest = $this->generateSignatureCapture($config, $order_number, $amount, $currency);

        /** @var SimpleXMLElement $xml */
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><transaction></transaction>');

        $xml->addChild('amount', $amount);
        $xml->addChild('currency', $currency);
        $xml->addChild('digest', $digest);
        $xml->addChild('authenticity-token', $config["authenticity_token"]);
        $xml->addChild('order-number', $order_number);

        $post = $xml->asXML();

        $this->restManager = $this->container->get("rest_manager");
        $this->restManager->CURLOPT_RETURNTRANSFER = 1;
        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_POSTFIELDS = $post;
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array(
            "Content-Type: application/xml",
            "Accept: application/xml"
        );

        $res = $this->restManager->get($config["service_url"] . $order_number . "/capture.xml", false);

        $xmlRes = simplexml_load_string($res);
        if (empty($xmlRes)) {
            return false;
        }

        if ($xmlRes->status != "approved") {
            $this->logger->error(
                "MONRI ERROR - complete transaction: " . $paymentTransaction->getTransactionIdentifier() . " - {$res}"
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
     * @throws \Exception
     */
    public function refundTransaction(PaymentTransactionEntity $paymentTransaction)
    {
        if ($paymentTransaction->getProvider() != "monri_provider") {
            return false;
        }

        if ($paymentTransaction->getTransactionStatusId() != CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED) {
            return false;
        }

        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $paymentTransaction->getPaymentType();

        $config = json_decode($paymentType->getConfiguration(), true);
        if (empty($config)) {
            return false;
        }

        $amount = number_format($paymentTransaction->getAmount(), 2, ",", "");
        $amount = str_replace(".", "", $amount);
        $amount = str_replace(",", "", $amount);

        $currency = $paymentTransaction->getCurrency()->getCode();
        $order_number = $paymentTransaction->getOrder()->getPreviewHash();
        $digest = $this->generateSignatureCapture($config, $order_number, $amount, $currency);

        /** @var SimpleXMLElement $xml */
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><transaction></transaction>');

        $xml->addChild('amount', $amount);
        $xml->addChild('currency', $currency);
        $xml->addChild('digest', $digest);
        $xml->addChild('authenticity-token', $config["authenticity_token"]);
        $xml->addChild('order-number', $order_number);

        $post = $xml->asXML();

        $this->restManager = $this->container->get("rest_manager");
        $this->restManager->CURLOPT_RETURNTRANSFER = 1;
        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_POSTFIELDS = $post;
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array(
            "Content-Type: application/xml",
            "Accept: application/xml"
        );

        $res = $this->restManager->get($config["service_url"] . $order_number . "/refund.xml", false);

        $xmlRes = simplexml_load_string($res);
        if (empty($xmlRes)) {
            return false;
        }

        if ($xmlRes->status != "approved") {
            $this->logger->error(
                "MONRI ERROR - refund transaction: " . $paymentTransaction->getTransactionIdentifier() . " - {$res}"
            );
            return false;
        }

        if (empty($this->paymentTransactionManager)) {
            $this->paymentTransactionManager = $this->container->get("payment_transaction_manager");
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
}
