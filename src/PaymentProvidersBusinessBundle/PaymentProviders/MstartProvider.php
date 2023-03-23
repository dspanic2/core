<?php

namespace PaymentProvidersBusinessBundle\PaymentProviders;

use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\ErrorLogManager;
use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\DiscountCouponEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\QuoteManager;
use mysql_xdevapi\Exception;
use PaymentProvidersBusinessBundle\Abstracts\AbstractPaymentProvider;
use PaymentProvidersBusinessBundle\Constants\PaymentProvidersConstants;
use PaymentProvidersBusinessBundle\Interfaces\PaymentProviderInterface;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use SimpleXMLElement;

class MstartProvider extends AbstractPaymentProvider implements PaymentProviderInterface
{
    /** @var RestManager $restManager */
    protected $restManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;

    const TRAN_TYPE_PAY = 'preauth';
    const TRAN_TYPE_CAPTURE = 'completion';
    const TRAN_TYPE_STATUS = 'preauth';
    const TRAN_TYPE_REVERSAL = 'reversal';
    const SUBMIT_TYPE_PAY = 'manual';
    const SUBMIT_TYPE_CAPTURE = 'auto';
    const SUBMIT_TYPE_STATUS = 'autocheckoutservice';
    const SUBMIT_TYPE_REVERSAL = 'auto';
    const REQUEST_TYPE_PAY = 'transaction';
    const REQUEST_TYPE_CAPTURE = 'completion';
    const REQUEST_TYPE_STATUS = 'checkstatus';
    const REQUEST_TYPE_REVERSAL = 'reversal';

    /**
     * @param $currencyCode
     * @return string
     */
    public function getCurrency($currencyCode)
    {
        $currencyId = '191';
        if ($currencyCode == 'EUR') {
            $currencyId = '978';
        }

        return $currencyId;
    }

    /**
     * @param $type
     * @return null|string
     */
    protected function getTranType($type)
    {
        $tranType = null;
        switch ($type) {
            case "payment":
                $tranType = self::TRAN_TYPE_PAY;
                break;
            case "capture":
                $tranType = self::TRAN_TYPE_CAPTURE;
                break;
            case "checkstatus":
                $tranType = self::TRAN_TYPE_STATUS;
                break;
            case "reversal":
                $tranType = self::TRAN_TYPE_REVERSAL;
                break;
            default :
                $tranType = null;
                break;
        }

        return $tranType;
    }

    /**
     * @deprecated
     * @param $type
     * @return null|string
     */
    public function getSubmitType($type)
    {
        $submitType = null;

        switch ($type) {
            case "payment":
                $submitType = self::SUBMIT_TYPE_PAY;
                break;
            case "capture":
                $submitType = self::SUBMIT_TYPE_CAPTURE;
                break;
            case "checkstatus" :
                $submitType = self::SUBMIT_TYPE_STATUS;
                break;
            case "reversal":
                $submitType = self::SUBMIT_TYPE_REVERSAL;
                break;
            default:
                $submitType = null;
                break;
        }

        return $submitType;
    }

    /**
     * @param string $type
     * @return string
     */
    public function getRequestType($type)
    {
        switch ($type) {
            case "payment":
                $requestType = self::REQUEST_TYPE_PAY;
                break;
            case "capture":
                $requestType = self::REQUEST_TYPE_CAPTURE;
                break;
            case "checkstatus":
                $requestType = self::REQUEST_TYPE_STATUS;
                break;
            case "reversal":
                $requestType = self::REQUEST_TYPE_REVERSAL;
                break;
            default:
                $requestType = null;
                break;
        }

        return $requestType;
    }

    public function renderTemplateFromOrder(OrderEntity $order)
    {
        return false;
    }

    /**
     * @param $p
     * @param $session
     * @return mixed|string
     * @throws \Exception
     */
    public function confirmMstartAction($p,$session){

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $logData = Array();
        $logData["action"] = "success";
        $logData["has_error"] = 1;
        $logData["response_data"] = json_encode($p);
        $logData["payment_type"] = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::MSTART_PROVIDER_CODE);
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_RESPONSE);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }
        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }
        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }
        if (empty($this->errorLogManager)) {
            $this->errorLogManager = $this->container->get("error_log_manager");
        }

        $ret = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $ret["quoteUrl"];
        $quoteErrorUrl = $ret["quoteErrorUrl"];
        $quoteSuccessUrl = $ret["quoteSuccessUrl"];

        if(!isset($p["order_number"]) || empty($p["order_number"])){
            $logData["description"] = "Missing order_number in response";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            $session->set("quote_error",$this->translator->trans("Mstart error."));

            return $quoteErrorUrl;
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($p["order_number"]);

        /**
         * Ako ne postoji quote baci error
         */
        if (empty($quote)) {
            $logData["description"] = "Quote does not exist for order_number {$p["order_number"]}";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            $session->set("quote_error",$this->translator->trans("Quote does not exist."));

            return $quoteErrorUrl;
        }
        $logData["quote"] = $quote;

        /**
         * Ako je quote vec prihvacen baci error
         */
        if (!in_array($quote->getQuoteStatusId(), array(
            CrmConstants::QUOTE_STATUS_NEW,
            CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT))) {
            $logData["description"] = "Quote id {$quote->getId()} already accepted or canceled";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            $this->errorLogManager->logErrorEvent("Mstart success - quote not in status NEW","Quote id {$quote->getId()} not in status QUOTE_STATUS_NEW or QUOTE_STATUS_WAITING_FOR_CLIENT",true);

            $session->set("quote_error",$this->translator->trans("Quote status not valid."));

            return $quoteErrorUrl;
        }

        /**
         * Sad provjeravamo da li je uplata prosla dobro
         */
        if($p["response_result"] != "000"){

            $logData["description"] = "Mstart error: {$p["response_message"]}";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            //todo message translation
            $session->set("quote_error",$this->translator->trans($p["response_message"]));

            return $quoteErrorUrl;
        }

        /**
         * Set Order items
         */
        $quoteItems = $quote->getQuoteItems();

        $basePriceTotal = 0;
        if(EntityHelper::isCountable($quoteItems) && count($quoteItems)){

            $deliveryPriceAdded = false;

            /** @var QuoteItemEntity $quoteItem */
            foreach ($quoteItems as $quoteItem){
                $basePriceTotal = $basePriceTotal + floatval($quoteItem->getBasePriceTotal());

                if ($quoteItem->getDeliveryType()->getIsDelivery() && !$deliveryPriceAdded){
                    $basePriceTotal = $basePriceTotal + floatval($quote->getBasePriceDeliveryTotal());
                    $deliveryPriceAdded = true;
                }
            }
        }


        /**
         * Check if discount coupon has card types defined
         * Ovo je ponovno ugaseno jer je mStart popravio
         */
        /*$totalWithoutDiscount = 0;
        if(!empty($quote->getDiscountCoupon()) && !empty($quote->getDiscountCoupon()->getAllowedCards()) && $quote->getDiscountCoupon()->getShowOnCheckout() && floatval($quote->getDiscountCouponPriceTotal()) > 0){
            $totalWithoutDiscount = $basePriceTotal + $quote->getDiscountCouponPriceTotal();
        }

        if(floatval($totalWithoutDiscount) > 0 && isset($p['discount_amount']) && $p['discount_amount'] != ""){
            if((floatval($p['purchase_amount']) - $basePriceTotal) > 5){
                $session->set("disable_force_account_from_session",1);
                $this->crmProcessManager->applyDiscountCoupon($quote, null);
            }
        }*/

        /**
         * Ovdje treba provjeriti koja je finalna cijena i da li treba skinuti popust
         */
        if(isset($p['discount_amount']) && $p['discount_amount'] != "" && floatval($p['discount_amount']) == 0){
            $this->crmProcessManager->applyDiscountCoupon($quote, null);
        }

        /**
         * Kreirati order
         */
        $data = array();
        $data["is_locked"] = 0;
        $quote = $this->quoteManager->updateQuote($quote, $data, true);

        $quoteStatus = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_ACCEPTED);

        $this->quoteManager->changeQuoteStatus($quote, $quoteStatus);

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderByQuoteId($quote->getId());
        if (empty($order)) {

            $logData["description"] = "Cannot create order for quote id {$quote->getId()}";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            $session->set("quote_error",$this->translator->trans("Error creating order, please try again."));

            $this->errorLogManager->logErrorEvent("Mstart success - cannot create order","Cannot create order for quote id {$quote->getId()}",true);

            return $quoteErrorUrl;
        }

        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::MSTART_PROVIDER_CODE);

        $config = json_decode($paymentType->getConfiguration(), true);
        $paymentTransactionStatusId = PaymentProvidersConstants::PAYMENT_TRANSACTION_STATUS_PREAUTORIZIRANO;
        if(isset($config["default_payment_transaction_status"]) && !empty($config["default_payment_transaction_status"])){
            $paymentTransactionStatusId = $config["default_payment_transaction_status"];
        }

        try{
            $transactionData = array();
            $transactionData["order"] = $order;
            $transactionData["provider"] = PaymentProvidersConstants::MSTART_PROVIDER_CODE;
            $transactionData["signature"] = $p["response_hash"];
            $transactionData["transaction_identifier"] = trim($p["response_appcode"]);
            $transactionData["amount"] = floatval($p["purchase_amount"]);
            $transactionData["currency"] = $order->getCurrency();
            $transactionData["transaction_status"] = $this->paymentTransactionManager->getPaymentTransactionStatusById($paymentTransactionStatusId);
            $transactionData["payment_type"] = $paymentType;
            $transactionData["transaction_identifier_second"] = $p["response_systan"];

            //$transactionData["request_type"] = $p["request_type"];
            //$transactionData["transaction_type"] = $p["transaction_type"];
            $transactionData["request_type"] = "transaction";
            $transactionData["transaction_type"] = "preauth";
            if(isset($p["acquirer"])){
                $transactionData["acquirer"] = $p["acquirer"];
            }
            if(isset($p["purchase_installments"])){
                $transactionData["purchase_installments"] = $p["purchase_installments"];
            }
            $transactionData["response_result"] = $p["response_result"];

            if(isset($p["response_random_number"])){
                $transactionData["response_random_number"] = $p["response_random_number"];
            }

            $transactionData["masked_pan"] = $p["masked_pan"];
            $transactionData["response_message"] = $p["response_message"];

            $transactionData["card_type"] = $p["card_type"];
            if(isset($p["payment_method"])){
                $transactionData["payment_method"] = $p["payment_method"];
            }
            else{
                $transactionData["payment_method"] = "card";
            }

            /** @var PaymentTransactionEntity $paymentTransaction */
            $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($transactionData);
        }
        catch (\Exception $e){
            $this->errorLogManager->logExceptionEvent("Mstart success - cannot create payment transaction",$e,true);
        }

        $logData["has_error"] = 0;
        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        $quoteSuccessUrl = $quoteSuccessUrl."?q=".StringHelper::encrypt($order->getId());

        return $quoteSuccessUrl;
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

        if (empty($this->errorLogManager)) {
            $this->errorLogManager = $this->getContainer()->get("error_log_manager");
        }

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        /**
         * Additional check to see if order already exists before we change hash
         * Ovo stavljamo na stranu dok se ne pojavi situacija da se ceka 3D secure potvrda
         */
        $data['purchase_amount'] = round($quote->getBasePriceTotal(),2);
        $data['purchase_currency'] = $this->getCurrency($quote->getCurrency()->getCode());
        $data['order_number'] = $quote->getPreviewHash();
        $data['quote'] = $quote;

        if($_ENV["MSTART_CHECK_BEFORE_PREPARE"]){
            try {
                $res = $this->checkStatus($quote->getPaymentType(), $data, true);

                    /*"card_type" => "Visa"
                  "masked_pan" => "462765******2518"
                  "order_date" => "2022-10-10 14:52:52.344689"
                  "order_number" => "953134d0cd5a15b19e2ce3832de489c3"
                  "response_appcode" => "O47312  "
                  "response_hash" => "ed01921bd60a333d714869612b8393b1bf9c8d7bdbb4077debca762257af1f59175616198201cced0ac290c3190242e7da7980182a8bc4e6bbc2a2c54d399d26"
                  "response_message" => "ODOBRENO"
                  "response_result" => "000"
                  "response_systan" => "376253"*/
                if(isset($res["card_type"]) && !empty($res["card_type"]) && isset($res["response_systan"]) && !empty($res["response_systan"]) && isset($res["response_result"]) && $res["response_result"] == "000"){

                    $session = $this->container->get("session");
                    $redirectUrl = $this->confirmMstartAction($res,$session);

                    $ret = array(
                        'error' => false,
                        'message' => "",
                        'forms' => false,
                        'buttons' => false,
                        'redirect_url' => $redirectUrl
                    );

                    return $ret;
                }
            }
            catch (\Exception $e){
                //do nothing, this order does not exist on mstart
            }
        }

        /**
         * Check Quote status
         */
        if(!in_array($quote->getQuoteStatusId(),Array(CrmConstants::QUOTE_STATUS_NEW,CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT))){
            $this->errorLogManager->logErrorEvent("Mstart - prepare - quote not in status QUOTE_STATUS_NEW or QUOTE_STATUS_WAITING_FOR_CLIENT", "Quote id: {$quote->getId()} not in status QUOTE_STATUS_NEW or QUOTE_STATUS_WAITING_FOR_CLIENT", true);
            return $ret;
        }

        /**
         * Create new hash because mstart has unique cart id requirement
         */
        $updateData = array();
        $updateData["preview_hash"] = StringHelper::generateHash($quote->getId(), time());

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->updateQuote($quote, $updateData);

        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $quote->getPaymentType();

        $config = $this->getConfig($paymentType);

        /** @var ContactEntity $contact */
        $contact = $quote->getContact();

        if (EntityHelper::checkIfMethodExists($contact, "getLanguage") && !empty($contact->getLanguage())) {
            $language = $contact->getLanguage()->getCode();
        } else {
            $language = $config["default_lang"];
        }

        /**
         * Only for pevex
         */
        $basePriceTotal = 0;

        /**
         * Set Order items
         */
        $quoteItems = $quote->getQuoteItems();

        if(EntityHelper::isCountable($quoteItems) && count($quoteItems)){

            $deliveryPriceAdded = false;

            /** @var QuoteItemEntity $quoteItem */
            foreach ($quoteItems as $quoteItem){
                $basePriceTotal = $basePriceTotal + floatval($quoteItem->getBasePriceTotal());

                if ($quoteItem->getDeliveryType()->getIsDelivery() && !$deliveryPriceAdded){
                    $basePriceTotal = $basePriceTotal + floatval($quote->getBasePriceDeliveryTotal());
                    $deliveryPriceAdded = true;
                }
            }
        }

        if($basePriceTotal == 0){
            return $ret;
        }

        //$amount = number_format($quote->getBasePriceTotal(), 2, ".", "");
        $amount = number_format($basePriceTotal, 2, ".", "");

        $paymentData = [];
        $paymentData['request_type'] = $this->getRequestType('payment');
        $paymentData['trantype'] = $this->getTranType('payment');
        $paymentData['purchase_amount'] = round($amount, 2);
        $paymentData['merchant_approve_url'] = $_ENV["SSL"] . "://" . $_ENV["FRONTEND_URL"]."/api/mstart_confirm";
        $paymentData['merchant_decline_url'] = $_ENV["SSL"] . "://" . $_ENV["FRONTEND_URL"]."/api/mstart_cancel";

        /**
         * Check if discount coupon has card types defined
         */
        if(!empty($quote->getDiscountCoupon()) && !empty($quote->getDiscountCoupon()->getAllowedCards()) && $quote->getDiscountCoupon()->getShowOnCheckout() && floatval($quote->getDiscountCouponPriceTotal()) > 0){

            $totalWithoutDiscount = $amount + $quote->getDiscountCouponPriceTotal();
            $totalWithoutDiscount = number_format($totalWithoutDiscount, 2, ".", "");

            $paymentData['purchase_amount'] = round($totalWithoutDiscount, 2);
            $paymentData['discounted_card_types'] = trim($quote->getDiscountCoupon()->getAllowedCards());
            $paymentData['discounted_amount'] = round($amount, 2);
        }

        $paymentData['purchase_currency'] = $this->getCurrency($quote->getCurrency()->getCode());

        $paymentData['purchase_description'] = null;
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
            $paymentData['purchase_description'] = trim($tmpOrderInfo);
        }

        $paymentData['customer_lang'] = $language;
        $paymentData['customer_name'] = $contact->getFirstName();
        $paymentData['customer_surname'] = $contact->getLastName();

        /** @var AddressEntity $address */
        $address = $quote->getAccountBillingAddress();

        $paymentData['customer_address'] = $address->getStreet();
        $paymentData['customer_country'] = $address->getCity()->getCountry()->getName()[$_ENV["DEFAULT_STORE_ID"]];
        $paymentData['customer_city'] = $address->getCity()->getName();
        $paymentData['customer_zip'] = $address->getCity()->getPostalCode();
        $paymentData['customer_phone'] = $contact->getPhone();
        $paymentData['customer_email'] = $contact->getEmail();
        $paymentData['merchant_id'] = $config['merchant_id'];
        $paymentData['order_number'] = $quote->getPreviewHash();
        //$paymentData['proxy'] = 'false';
        //$paymentData['pay_method'] = '0';
        //$paymentData['purchase_installments'] = '0';
        //$paymentData['purchase_differperiod'] = '0';
        //$paymentData['terminal_id'] = '0';
        //$paymentData['transaction_id'] = '0';
        //$paymentData['iopg_id'] = '0';
        //$paymentData['discounted_max_installments'] = '0';
        $paymentData['terminal_id'] = $config["terminal_id"];

        $paymentData['request_hash'] = $this->getHash512($paymentData, $config['key']);

        $url = $config['action'];

        $ret["forms"][] = $this->twig->render("PaymentProvidersBusinessBundle:PaymentProviders:Mstart/form.html.twig",
            array("data" => $paymentData, "url" => $url));
        $ret["buttons"][] = $this->getPaymentButtonsHtml($paymentData, $config);
        $ret["signature"] = $paymentData["request_hash"];

        /**
         * Insert transaction log
         */
        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $logData = Array();
        $logData["action"] = "prepare";
        $logData["has_error"] = 0;
        $logData["request_data"] = json_encode($paymentData);
        $logData["payment_type"] = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::MSTART_PROVIDER_CODE);
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_PREPARE);
        $logData["quote"] = $quote;
        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        return $ret;
    }

    /**
     * @param $data
     * @param $secretKey
     * @return string
     */
    protected function getHash512($data,$secretKey){

        ksort($data);

        $preparedArray = Array();

        foreach ($data as $key => $value){
            if(empty($value) && $value != "0"){
                //todo potencijalni problem sa 0
                unset($data[$key]);
            }
            else{
                $preparedArray[]=$key."=".$value;
            }
        }

        $preparedArray[]="secret_key={$secretKey}";

        $string = implode("&",$preparedArray);

        $string = hash("sha512", $string);

        return $string;
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
            "action" => "mstart",
        );

        $button = $buttonTemplate;
        $button["data"]["hash"] = "mstart" . $data["order_number"];
        $buttonHtml = $this->twig->render($config["checkout_button"], array("data" => $button));

        return $buttonHtml;
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return false|string
     * @throws \Exception
     */
    public function checkStatusByPaymentTransaction(PaymentTransactionEntity $paymentTransaction){

        $data = Array();

        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $paymentTransaction->getPaymentType();

        if(empty($paymentType) || $paymentType->getProvider() != PaymentProvidersConstants::MSTART_PROVIDER_CODE){

            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $exception = new Exception("Mstart - check status - payment transaction provider code does not match", "Payment transaction id: {$paymentTransaction->getId()} payment transaction provider code does not match");

            $this->errorLogManager->logExceptionEvent("Mstart - check status - payment transaction provider code does not match", $exception, true);

            throw $exception;
        }

        $data['purchase_amount'] = round($paymentTransaction->getAmount(),2);
        $data['purchase_currency'] = $this->getCurrency($paymentTransaction->getCurrency()->getCode());
        $data['order_number'] = $paymentTransaction->getOrder()->getQuote()->getPreviewHash();
        $data['quote'] = $paymentTransaction->getOrder()->getQuote();

        $ret = $this->checkStatus($paymentType,$data);

        return $ret;
    }

    /**
     * @param $paymentType
     * @param $data
     * @return false|string
     * @throws \Exception
     */
    public function checkStatus($paymentType, $data, $testIfOrderAlreadyExists = false){

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $logData = Array();
        $logData["action"] = "check_status";
        $logData["has_error"] = 0;
        $logData["payment_type"] = $paymentType;
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_REQUEST);
        $logData["quote"] = $data["quote"];

        $config = $this->getConfig($paymentType);

        $paymentData = [];

        $paymentData['merchant_id'] = $config['merchant_id'];
        $paymentData['purchase_amount'] = $data['purchase_amount'];
        $paymentData['purchase_currency'] = $data['purchase_currency'];
        $paymentData['order_number'] = $data['order_number'];
        $paymentData['request_type'] = $this->getRequestType('checkstatus');
        $paymentData['trantype'] = $this->getTranType('checkstatus');
        $paymentData['terminal_id'] = $config["terminal_id"];

        $paymentData['request_hash'] = $this->getHash512($paymentData, $config['key']);

        $logData["request_data"] = json_encode($paymentData);

        /** @var RestManager $restManager */
        $restManager = new RestManager();

        try {
            $response = $this->getMstartApiData($restManager, $config, null, $paymentData);
        }
        catch (\Exception $e){
            $logData["description"] = $e->getMessage();
            $logData["has_error"] = 1;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            throw $e;
        }

        try{
            $xml = simplexml_load_string($response);
        }
        catch (\Exception $e){
            $logData["description"] = $e->getMessage();
            $logData["has_error"] = 1;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            throw $e;
        }

        $logData["response_data"] = json_encode($xml);

        if(!isset($xml->response_result) || !isset($xml->response_message)){

            $logData["description"] = "mStart - check status - invalid response";
            $logData["has_error"] = 1;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            throw new \Exception("mStart - check status - invalid response, check log for details");
        }

        if($testIfOrderAlreadyExists){
            return json_decode(json_encode($xml),true);
        }

        if((string)$xml->response_result != "000"){

            $logData["description"] = "mStart - check status - response result not 000";
            $logData["has_error"] = 1;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            throw new \Exception("mStart - check status - response result not 000, check log for details");
        }

        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        return json_decode(json_encode($xml),true);
    }

    /**
     * @param RestManager $restManager
     * @param $config
     * @param array $params
     * @param array $body
     * @return bool|mixed|string
     * @throws \Exception
     */
    private function getMstartApiData(RestManager $restManager, $config, $params = [], $body = [])
    {
        $url = $config["action"];

        if (!empty($params)) {
            $url .= "&" . http_build_query($params);
        }

        if (!empty($body)) {
            $restManager->CURLOPT_POST = 1;
            $restManager->CURLOPT_POSTFIELDS = http_build_query($body);
            $restManager->CURLOPT_CUSTOMREQUEST = "POST";
        }

        $path = $_ENV["WEB_PATH"]."Documents/cert/{$config["certificate_name"]}";
        if(!file_exists($path)){
            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $error = new \Exception("Missing certificate on path: {$path}");
            $this->errorLogManager->logExceptionEvent("mStart - api call - missing certificate",$error,true);
            throw $error;
        }

        $restManager->CURLOPT_RETURNTRANSFER = 1;
        $restManager->CURLOPT_SSL_VERIFYHOST = 0;
        $restManager->CURLOPT_SSL_VERIFYPEER = 0;
        $restManager->CURLOPT_FOLLOWLOCATION = 0;
        $restManager->CURLOPT_ENCODING = 'UTF-8';
        $restManager->CURLOPT_CAINFO = $path;
        $restManager->CURLOPT_CAPATH = $path;
        $restManager->CURLOPT_TIMEOUT = 300;

        try {
            $data = $restManager->get($url, false);
        } catch (\Exception $e) {
            throw $e;
        }

        if (empty($data)) {
            throw new \Exception("Response is empty ".json_encode($body));
        }

        return $data;
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return bool|PaymentTransactionEntity
     * @throws \Exception
     */
    public function completeTransaction(PaymentTransactionEntity $paymentTransaction)
    {
        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $paymentTransaction->getPaymentType();

        if(empty($paymentType) || $paymentType->getProvider() != PaymentProvidersConstants::MSTART_PROVIDER_CODE){

            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $exception = new Exception("Mstart - check status - payment transaction provider code does not match", "Payment transaction id: {$paymentTransaction->getId()} payment transaction provider code does not match");

            $this->errorLogManager->logExceptionEvent("Mstart - check status - payment transaction provider code does not match", $exception, true);

            throw $exception;
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $logData = Array();
        $logData["action"] = "capture";
        $logData["has_error"] = 0;
        $logData["payment_type"] = $paymentType;
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_REQUEST);
        $logData["quote"] = $paymentTransaction->getOrder()->getQuote();

        $config = $this->getConfig($paymentType);

        $paymentData = [];

        $paymentData['merchant_id'] = $config['merchant_id'];
        $paymentData['purchase_amount'] = round($paymentTransaction->getAmount(),2);
        $paymentData['purchase_currency'] = $this->getCurrency($paymentTransaction->getCurrency()->getCode());
        $paymentData['order_number'] = $paymentTransaction->getOrder()->getQuote()->getPreviewHash();
        $paymentData['request_type'] = $this->getRequestType('capture');
        $paymentData['trantype'] = $this->getTranType('capture');
        $paymentData['terminal_id'] = $config["terminal_id"];

        $paymentData['request_hash'] = $this->getHash512($paymentData, $config['key']);

        $logData["request_data"] = json_encode($paymentData);

        /** @var RestManager $restManager */
        $restManager = new RestManager();

        $config["action"] = str_replace('icheckout', 'legacy/autocheckout', $config["action"]);

        try {
            $response = $this->getMstartApiData($restManager, $config, null, $paymentData);
        }
        catch (\Exception $e){
            $logData["description"] = $e->getMessage();
            $logData["has_error"] = 1;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            throw $e;
        }

        try{
            $xml = simplexml_load_string($response);
        }
        catch (\Exception $e){
            $logData["description"] = $e->getMessage();
            $logData["has_error"] = 1;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            throw $e;
        }

        $logData["response_data"] = json_encode($xml);

        if(!isset($xml->response_result) || !isset($xml->response_message)){

            $logData["description"] = "mStart - check status - invalid response";
            $logData["has_error"] = 1;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            throw new \Exception("mStart - check status - invalid response, check log for details");
        }

        if((string)$xml->response_result != "000" && (string)$xml->response_message != "TRANSACTION ALREADY PROCESSED" && (string)$xml->response_message != "PREAUTH ALREADY COMPLETED"){ //&& (string)$xml->response_message != "HOST LINK DOWN"

            $logData["description"] = "mStart - check status - response result not 000";
            $logData["has_error"] = 1;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            throw new \Exception("mStart - check status - response result not 000, check log for details");
        }

        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        if($paymentTransaction->getTransactionStatusId() != PaymentProvidersConstants::PAYMENT_TRANSACTION_STATUS_NAPLACENO && in_array((string)$xml->response_message,Array("TRANSACTION ALREADY PROCESSED","ODOBRENO"))){
            $data = array();
            $data["transaction_status"] = $this->paymentTransactionManager->getPaymentTransactionStatusById(PaymentProvidersConstants::PAYMENT_TRANSACTION_STATUS_NAPLACENO);

            $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($data, $paymentTransaction);

            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $this->crmProcessManager->processPayment($paymentTransaction);
        }

        return json_decode(json_encode($xml),true);
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return bool|PaymentTransactionEntity
     * @throws \Exception
     */
    public function refundTransaction(PaymentTransactionEntity $paymentTransaction)
    {
        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $paymentTransaction->getPaymentType();

        if(empty($paymentType) || $paymentType->getProvider() != PaymentProvidersConstants::MSTART_PROVIDER_CODE){

            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $exception = new Exception("Mstart - check status - payment transaction provider code does not match", "Payment transaction id: {$paymentTransaction->getId()} payment transaction provider code does not match");

            $this->errorLogManager->logExceptionEvent("Mstart - check status - payment transaction provider code does not match", $exception, true);

            throw $exception;
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $logData = Array();
        $logData["action"] = "reversal";
        $logData["has_error"] = 0;
        $logData["payment_type"] = $paymentType;
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_REQUEST);
        $logData["quote"] = $paymentTransaction->getOrder()->getQuote();

        $config = $this->getConfig($paymentType);

        $paymentData = [];

        $paymentData['merchant_id'] = $config['merchant_id'];
        $paymentData['purchase_amount'] = round($paymentTransaction->getAmount(),2);
        $paymentData['purchase_currency'] = $this->getCurrency($paymentTransaction->getCurrency()->getCode());
        $paymentData['order_number'] = $paymentTransaction->getOrder()->getQuote()->getPreviewHash();
        $paymentData['request_type'] = $this->getRequestType('reversal');
        $paymentData['trantype'] = $this->getTranType('reversal');
        $paymentData['terminal_id'] = $config["terminal_id"];

        $paymentData['request_hash'] = $this->getHash512($paymentData, $config['key']);

        $logData["request_data"] = json_encode($paymentData);

        /** @var RestManager $restManager */
        $restManager = new RestManager();

        $config["action"] = str_replace('icheckout', 'legacy/autocheckout', $config["action"]);

        try {
            $response = $this->getMstartApiData($restManager, $config, null, $paymentData);
        }
        catch (\Exception $e){
            $logData["description"] = $e->getMessage();
            $logData["has_error"] = 1;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            throw $e;
        }

        try{
            $xml = simplexml_load_string($response);
        }
        catch (\Exception $e){
            $logData["description"] = $e->getMessage();
            $logData["has_error"] = 1;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            throw $e;
        }

        $logData["response_data"] = json_encode($xml);

        if(!isset($xml->response_result) || !isset($xml->response_message)){

            $logData["description"] = "mStart - check status - invalid response";
            $logData["has_error"] = 1;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            throw new \Exception("mStart - check status - invalid response, check log for details");
        }

        if((string)$xml->response_result != "400" && (string)$xml->response_message != "STORNIRANO"){

            $logData["description"] = "mStart - check status - response result not 400";
            $logData["has_error"] = 1;
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            throw new \Exception("mStart - check status - response result not 400, check log for details");
        }

        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        if($paymentTransaction->getTransactionStatusId() != PaymentProvidersConstants::PAYMENT_TRANSACTION_STATUS_STORNO && in_array((string)$xml->response_message,Array("STORNIRANO"))){
            $data = array();
            $data["transaction_status"] = $this->paymentTransactionManager->getPaymentTransactionStatusById(PaymentProvidersConstants::PAYMENT_TRANSACTION_STATUS_STORNO);

            $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($data, $paymentTransaction);

            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $this->crmProcessManager->processRefund($paymentTransaction);
        }

        return json_decode(json_encode($xml),true);
    }
}
