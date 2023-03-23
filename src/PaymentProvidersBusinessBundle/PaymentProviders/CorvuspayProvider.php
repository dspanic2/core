<?php

namespace PaymentProvidersBusinessBundle\PaymentProviders;

use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\CurrencyEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\OrderManager;
use CrmBusinessBundle\Managers\QuoteManager;
use PaymentProvidersBusinessBundle\Abstracts\AbstractPaymentProvider;
use PaymentProvidersBusinessBundle\Interfaces\PaymentProviderInterface;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;

class CorvuspayProvider extends AbstractPaymentProvider implements PaymentProviderInterface
{

    public function renderTemplateFromOrder(OrderEntity $order)
    {
        return false;
    }

    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;

    /**
     * @param QuoteEntity $quote
     * @return array|null
     */
    public function renderTemplateFromQuote(QuoteEntity $quote)
    {
        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }

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

        $requireComplete = "true";
        if (isset($_ENV["CORVUS_COMPLETE_SALE"]) && $_ENV["CORVUS_COMPLETE_SALE"] == 1) {
            $requireComplete = "false";
        }

        $base_payment_data = [
            "store_id" => $config["shop_id"],
            "version" => "1.3",
            "require_complete" => $requireComplete,
            "cardholder_name" => $contact->getFirstName(),
            "cardholder_surname" => $contact->getLastName(),
            "cardholder_city" => $quote->getAccountBillingCity()->getName(),
            "cardholder_address" => $quote->getAccountBillingStreet(),
            "cardholder_zip_code" => $quote->getAccountBillingCity()->getPostalCode(),
            "cardholder_phone" => $contact->getPhone(),
            "cardholder_email" => $contact->getEmail(),
            "cardholder_country" => $this->getPageUrlExtension->getArrayStoreAttribute($this->container->get("session")->get("current_store_id"), $quote->getAccountBillingCity()->getCountry()->getName())
//            "cardholder_country" => $quote->getAccountBillingCity()->getCountry()->getName(),
        ];
        if (EntityHelper::checkIfMethodExists($contact, 'getLanguage')) {
            if (!empty($contact->getLanguage())) {
                $base_payment_data["language"] = strtoupper($contact->getLanguage()->getCode());
            }
        }
        if (!isset($base_payment_data["language"]) || empty($base_payment_data["language"])) {
            $base_payment_data["language"] = $config["default_lang"];
        }

        $base_payment_data["payment_all"] = "Y0299";

        $amount = number_format($totalUnpaid["total"], 2, ",", "");
        $amount = str_replace(",", ".", $amount);

        /*$items = $quote->getQuoteItems();
        $itemText = "";
        if(EntityHelper::isCountable($items) && count($items) > 0){
            foreach ($items as $item){
                $itemText = $itemText."<br>".$item->getProduct()->getName()." - ".$item->getQty();
            }
        }*/

        $base_payment_data["amount"] = $amount;
        //$base_payment_data["currency"] = $quote->getCurrency()->getCode();

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        /** @var CurrencyEntity $currency */
        $currency = $this->quoteManager->getCurrencyById($_ENV["DEFAULT_CURRENCY"]);

        $base_payment_data["currency"] = $currency->getCode();
        $base_payment_data["cart"] = $totalUnpaid["hash"];

        $prefix = "scom_";
        if (isset($_ENV["PAYMENT_PROVIDER_ORDER_PREFIX"]) && !empty($_ENV["PAYMENT_PROVIDER_ORDER_PREFIX"])) {
            $prefix = $_ENV["PAYMENT_PROVIDER_ORDER_PREFIX"];
        }

        if (isset($_ENV["CREATE_ORDER_BEFORE_CARD_PAYMENT"]) && $_ENV["CREATE_ORDER_BEFORE_CARD_PAYMENT"] == 1) {
            /** @var OrderManager $orderManager */
            $orderManager = $this->container->get("order_manager");
            /** @var OrderEntity $order */
            $order = $orderManager->getOrderByQuoteId($quote->getId());

            $base_payment_data["cart"] = $order->getIncrementId();
            $base_payment_data["order_number"] = $prefix . $order->getIncrementId();
        } else {
            $base_payment_data["order_number"] = $prefix . $quote->getIncrementId();
        }

        $base_payment_data["signature"] = $this->generateSignature($config, $totalUnpaid["hash"], $base_payment_data);

        /*$base_payment_data["cardholder_name"] = $contact->getFirstName();
        $base_payment_data["cardholder_surname"] = $contact->getLastName();
        $base_payment_data["cardholder_city"] = $quote->getAccountBillingCity()->getName();
        $base_payment_data["cardholder_address"] = $quote->getAccountBillingStreet();
        $base_payment_data["cardholder_zip_code"] = $quote->getAccountBillingCity()->getPostalCode();
        $base_payment_data["cardholder_phone"] = $contact->getPhone();
        $base_payment_data["cardholder_email"] = $contact->getEmail();
        $base_payment_data["cardholder_country"] = $quote->getAccountBillingCity()->getCountry()->getName();*/

        $base_payment_data["action"] = $config["action"];

        $ret["forms"][] = $this->twig->render('PaymentProvidersBusinessBundle:PaymentProviders:CorvusPay/form.html.twig', array("data" => $base_payment_data));
        $ret["buttons"][] = $this->getPaymentButtonsHtml($base_payment_data, $config);
        $ret["signature"] = $base_payment_data["signature"];

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

        ksort($data);

        $string = "";

        foreach ($data as $key => $value) {
            $string = $string . $key . $value;
        }

        return hash_hmac('sha256', $string, $config["key"]);
    }

    /**
     * @param $data
     * @param $config
     * @return array|bool
     */
    public function getPaymentButtonsHtml($data, $config)
    {

        $buttonTemplate = array("type" => "button", "name" => "", "class" => "btn-primary btn-blue", "url" => "", "action" => "corvus_pay");

        $button = $buttonTemplate;
        $button["data"]["hash"] = "corvus" . $data["cart"];

        $buttonHtml = $this->twig->render($config["checkout_button"], array("data" => $button));

        return $buttonHtml;
    }
}