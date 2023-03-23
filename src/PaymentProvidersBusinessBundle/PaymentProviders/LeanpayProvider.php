<?php

namespace PaymentProvidersBusinessBundle\PaymentProviders;

use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\ApplicationSettingsManager;
use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;
use CrmBusinessBundle\Managers\QuoteManager;
use PaymentProvidersBusinessBundle\Abstracts\AbstractPaymentProvider;
use PaymentProvidersBusinessBundle\Constants\PaymentProvidersConstants;
use PaymentProvidersBusinessBundle\Interfaces\PaymentProviderInterface;
use PaymentProvidersBusinessBundle\Managers\PaymentTransactionManager;

class LeanpayProvider extends AbstractPaymentProvider implements PaymentProviderInterface
{
    /** @var RestManager $restManager */
    protected $restManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var PaymentTransactionManager $paymentTransactionManager */
    protected $paymentTransactionManager;
    /** @var ApplicationSettingsManager $applicationSettingsManager */
    private $applicationSettingsManager;

    /**
     * @param QuoteEntity $quote
     * @return array
     */
    public function renderTemplateFromQuote(QuoteEntity $quote)
    {
        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $ret = array();
        $ret["forms"] = array();
        $ret["buttons"] = array();
        $ret["signature"] = null;

        $logData = Array();
        $logData["action"] = "prepare";
        $logData["has_error"] = 0;
        $logData["payment_type"] = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::LEANPAY_PROVIDER_CODE);
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_PREPARE);
        $logData["name"] = "Leanpay render template";
        $logData["quote"] = $quote;

        /**
         * Check Quote status
         */
        if(!in_array($quote->getQuoteStatusId(),Array(CrmConstants::QUOTE_STATUS_NEW,CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT))) {
            $logData["has_error"] = 1;
            $logData["description"] = "Quote id: {$quote->getId()} not in status QUOTE_STATUS_NEW or QUOTE_STATUS_WAITING_FOR_CLIENT";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            return $ret;
        }

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        /**
         * Create new hash because Leanpay has unique cart id requirement
         */
        $updateData = array();
        $updateData["preview_hash"] = StringHelper::generateHash($quote->getId(), time());

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->updateQuote($quote, $updateData);

        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $quote->getPaymentType();

        /** @var ContactEntity $contact */
        $contact = $quote->getContact();

        /** @var AddressEntity $address */
        $address = $quote->getAccountBillingAddress();

        $config = $this->getConfig($paymentType);

        if (EntityHelper::checkIfMethodExists($contact, "getLanguage") && !empty($contact->getLanguage())) {
            $language = $contact->getLanguage()->getCode();
        } else {
            $language = $config["default_lang"];
        }

        $tokenData = $this->prepareTokenData($quote, $config, $contact, $address, $language);
        try {
            $token = $this->tokenRequest($tokenData, $config["token_request"]);
        }
        catch (\Exception $e){
            $logData["has_error"] = 1;
            $logData["description"] = "Leanpay Token request failed";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
        }

        if (!$token) {
            return $ret;
        }

        $ret["forms"][] = $this->twig->render("PaymentProvidersBusinessBundle:PaymentProviders:Leanpay/form.html.twig", [
            "orderNumber" => $quote->getPreviewHash(),
            "url" => $config['customer_checkout'],
            "token" => $token
        ]);
        $ret["buttons"][] = $this->getPaymentButtonsHtml($quote->getPreviewHash(), $config);

        $logData["request_data"] = json_encode($tokenData);
        $logData["response_data"] = json_encode($token);
        $logData["description"] = "Successful token request";
        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        return $ret;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function importInstallmentPlans()
    {
        if(empty($this->restManager)){
            $this->restManager = $this->getContainer()->get("rest_manager");
        }
        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $paymentType = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::LEANPAY_PROVIDER_CODE);
        $config = $this->getConfig($paymentType);

        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array(
            "Content-Type: application/json",
            "Accept: application/json"
        );
        $this->restManager->CURLOPT_POSTFIELDS = json_encode(['vendorApiKey' => $config["api_key"]]);

        $response = $this->restManager->get($config["installment_plans_url"]);

        if (!$response) {
            return false;
        }

        $filePath = $_ENV["WEB_PATH"] . "/Documents/leanpay/installment-plans.json";

        if(!is_dir($_ENV["WEB_PATH"] . "/Documents/leanpay")) {
            mkdir($_ENV["WEB_PATH"] . "/Documents/leanpay");
        }

        file_put_contents($filePath, json_encode($response));

        return true;
    }

    /**
     * @param $price
     * @return array
     */
    public function getInstallmentPlansForPrice($price)
    {
        $filePath = $_ENV["WEB_PATH"] . "/Documents/leanpay/installment-plans.json";

        if (!file_exists($filePath)) {
            return [];
        }

        $file = file_get_contents($filePath);

        $allInstallments = null;
        foreach(json_decode($file, true)["groups"] as $group) {
            if ($group) {
                $allInstallments = $group["loanAmounts"];
            }
        }

        $matchingInstallment = [];

        // get matching installments
        if (ceil($price) >= $allInstallments[0]["loanAmount"] && ceil($price) <= end($allInstallments)["loanAmount"]) {
            foreach ($allInstallments as $installment) {
                if ($installment["loanAmount"] == ceil($price)) {
                    $matchingInstallment[] = $installment["possibleInstallments"][0];
                }
            }
        }

        usort($matchingInstallment, function($a, $b) {
            return $a["numberOfMonths"] <=> $b["numberOfMonths"];
        });

        return $matchingInstallment;
    }

    private function prepareTokenData($quote, $config, $contact, $address, $language)
    {
        $cartItems = [];
        $quoteItems = $quote->getQuoteItems();

        if (EntityHelper::isCountable($quoteItems) && count($quoteItems)) {
            /** @var QuoteItemEntity $quoteItem */
            foreach ($quoteItems as $quoteItem) {
                if ($quoteItem->getProduct()->getProductTypeId() != CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
                    $cartItem = [];
                    $cartItem["name"] = $quoteItem->getProduct()->getName()[$_ENV["DEFAULT_STORE_ID"]];
                    $cartItem["sku"] = $quoteItem->getProduct()->getCode();
                    $cartItem["price"] = sprintf("%g", round($quoteItem->getBasePriceTotal(), 2));
                    $cartItem["qty"] = sprintf("%g", round($quoteItem->getQty(), 2));
                    $cartItem["lpProdCode"] = null;
                    $cartItems[] = $cartItem;
                }
            }
        }

        $tokenData = [];
        $tokenData['vendorApiKey'] = $config["api_key"];
        $tokenData['vendorTransactionId'] = $quote->getPreviewHash();
        $tokenData['amount'] = sprintf("%g", round($quote->getBasePriceTotal(), 2));
        $tokenData['successUrl'] = $_ENV["SSL"]."://" . $_ENV["FRONTEND_URL"] . $config["confirm_url"] . "?preview_hash=" . $quote->getPreviewHash();
        $tokenData['errorUrl'] = $_ENV["SSL"]."://" . $_ENV["FRONTEND_URL"] . $config["cancel_url"] . "?preview_hash=" . $quote->getPreviewHash();
        $tokenData['vendorPhoneNumber'] = $contact->getPhone();
        $tokenData['vendorFirstName'] = $contact->getFirstName();
        $tokenData['vendorLastName'] = $contact->getLastName();
        $tokenData['vendorAddress'] = $address->getStreet();
        $tokenData['vendorZip'] = $address->getCity()->getPostalCode();
        $tokenData['vendorCity'] = $address->getCity()->getName();
        $tokenData['language'] = $language;
        $tokenData['vendorProductCode'] = null;
        $tokenData['cartItems'] = $cartItems;

        return $tokenData;
    }

    private function tokenRequest($tokenData, $url)
    {
        if(empty($this->restManager)){
            $this->restManager = $this->getContainer()->get("rest_manager");
        }

        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array(
            "Content-Type: application/json",
            "Accept: application/json"
        );
        $this->restManager->CURLOPT_POSTFIELDS = json_encode($tokenData);

        $response = $this->restManager->get($url);

        if (!isset($response["token"])) {
            return null;
        }

        return $response["token"];
    }

    /**
     * @param $orderNumber
     * @param $config
     * @return array|bool
     */
    private function getPaymentButtonsHtml($orderNumber, $config)
    {
        $buttonTemplate = array(
            "type" => "button",
            "name" => "",
            "class" => "btn-primary btn-blue",
            "url" => "",
            "action" => "leanpay",
        );

        $button = $buttonTemplate;
        $button["data"]["hash"] = "leanpay" . $orderNumber;
        $buttonHtml = $this->twig->render($config["checkout_button"], array("data" => $button));

        return $buttonHtml;
    }
}

