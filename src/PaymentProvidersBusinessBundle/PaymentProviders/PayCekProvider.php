<?php

namespace PaymentProvidersBusinessBundle\PaymentProviders;

use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use PaymentProvidersBusinessBundle\Abstracts\AbstractPaymentProvider;
use PaymentProvidersBusinessBundle\Interfaces\PaymentProviderInterface;

class PayCekProvider extends AbstractPaymentProvider implements PaymentProviderInterface
{
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;

    protected $profileCode;
    protected $profileSecret;
    protected $apiKey;
    protected $apiSecret;

    protected $successUrl;
    protected $failUrl;
    protected $backUrl;
    protected $callbackUrl;

    public function initialize()
    {
        parent::initialize();
        $this->profileCode = $_ENV["PAYCEK_PROFILE_CODE"];
        $this->profileSecret = $_ENV["PAYCEK_PROFILE_SECRET"];
        $this->apiKey = $_ENV["PAYCEK_API_KEY"];
        $this->apiSecret = $_ENV["PAYCEK_API_SECRET"];
    }

    /**
     * @return mixed
     */
    public function getProfileCode()
    {
        return $this->profileCode;
    }

    /**
     * @return mixed
     */
    public function getProfileSecret()
    {
        return $this->profileSecret;
    }

    /**
     * @return mixed
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @return mixed
     */
    public function getApiSecret()
    {
        return $this->apiSecret;
    }

    /**
     * @param QuoteEntity $quote
     * @return bool
     */
    public function getPaycekUrls(QuoteEntity $quote)
    {
        $baseUrl = $_ENV["SSL"] . "://" . $quote->getStore()->getWebsite()->getBaseUrl();

        $this->successUrl = $baseUrl . "/api/paycek_success?hash=" . $quote->getPreviewHash();
        $this->failUrl = $baseUrl . "/api/paycek_error";
        $this->backUrl = $baseUrl . "/api/paycek_cancel";
        $this->callbackUrl = "/api/paycek_callback";

        return true;
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
     * @param $data
     * @return string
     */
    private function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @param string $profileId can be found in profile settings (https://paycek.io)
     * @param string $secretKey can be found in profile settings (https://paycek.io)
     * @param string $paymentId unique payment id (id that you are using on your website to uniquely describe the purchase)
     * @param string $totalAmount total price (example "100.00")
     * @param array $items array of arrays (this is used to display purchased items list to customer)
     *        example: [["name" => "smartphone","units" => "1","amount" => "999.00"],["name" => "cable","units" => "1","amount" => "29.00"]]
     * @param string $email email of your customer
     * @param string $successUrl URL of a web page to go to after a successful payment
     * @param string $failUrl URL of a web page to go to after a failed payment
     * @param string $backUrl URL for client to go to if he wants to get back to your shop
     * @param string $successUrlCallback URL of an API that paycek.io will call after successful payment
     * @param string $failUrlCallback URL of an API that paycek.io will call after failed payment
     * @param string $statusUrlCallback URL of an API that paycek.io will call after each payment status change (advanced)
     *      This callback will be called with an optional argument ?status=<status>&id=<id>
     *      <status> options are listed bellow:
     *          created - payment has been created
     *          waiting_transaction - waiting for the amount to appear on blockchain
     *          waiting_confirmations - waiting for the right amount of confirmations
     *          underpaid - an insufficient amount detected on blockchain
     *          successful - right amount detected and confirmed on blockchain
     *          expired - time for this payment has run out
     *          canceled - the payment has been manually canceled by paycek operations
     *      <id> is the $paymentId you provided when you generated the URL
     * @param string $description payment description (max length 100 characters)
     * @param string $language in which the payment will be shown to the customer ('en', 'hr')
     * @return string URL for starting a payment process on https://paycek.io
     */
    private function generatePaymentUrl($profileId, $secretKey, $paymentId, $totalAmount, $items = [], $email = "", $successUrl = "", $failUrl = "", $backUrl = "", $successUrlCallback = "", $failUrlCallback = "", $statusUrlCallback = "", $description = "", $language = "")
    {
        $formattedItems = array();

        foreach ($items as $item) {
            $newItem = [
                "n" => $item["name"],
                "u" => $item["units"],
                "a" => $item["amount"]
            ];
            array_push($formattedItems, $newItem);
        }

        $data = [
            "p" => $totalAmount,
            "id" => $paymentId,
            "e" => $email,
            "i" => $formattedItems,
            "s" => $successUrl,
            "f" => $failUrl,
            "b" => $backUrl,
            "sc" => $successUrlCallback,
            "fc" => $failUrlCallback,
            "stc" => $statusUrlCallback,
            "d" => $description,
            "l" => $language
        ];

        $dataJson = json_encode($data);
        $dataBase64 = $this->base64url_encode($dataJson);
        $dataHash = $this->base64url_encode(hex2bin(hash("sha256", $dataBase64 . "\x00" . $profileId . "\x00" . $secretKey)));
        $paymentUrl = "https://paycek.io/processing/checkout/payment_create?d=" . $dataBase64 . "&c=" . $profileId . "&h=" . $dataHash;

        return $paymentUrl;
    }

    /**
     * @param QuoteEntity $quote
     * @return mixed|string|null
     * @throws \Exception
     */
    public function generatePaymentUrlForQuote(QuoteEntity $quote)
    {
        $this->getPaycekUrls($quote);

        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $quote->getPaymentType();

        $config = json_decode($paymentType->getConfiguration(), true);
        if (empty($config)) {
            return null;
        }

        /**
         * PayCek items array
         */
        $items = array();

        $quoteItems = $quote->getQuoteItems();
        /** @var QuoteItemEntity $quoteItem */
        foreach ($quoteItems as $quoteItem) {
            if ($quoteItem->getEntityStateId() != 1) {
                continue;
            }
            $items[] = array(
                "name" => $quoteItem->getName(),
                "units" => $quoteItem->getQty(),
                "amount" => $quoteItem->getPriceTotal()
            );
        }

        /** @var ContactEntity $contact */
        $contact = $quote->getContact();

        if (EntityHelper::checkIfMethodExists($contact, "getLanguage") &&
            in_array($contact->getLanguage(), array("en", "hr"))) {
            $language = $contact->getLanguage()->getCode();
        } else {
            $language = $config["default_lang"];
        }

        $description = substr($quote->getName(), 0, 100);

//        $data = array(
//            "profile_code" => $this->getProfileCode(),
//            "dst_amount" => $this->getFormattedAmount($quote->getPriceTotal()),
//            "payment_id" => $quote->getPreviewHash(),
//            "items" => $items,
//            "email" => $contact->getEmail(),
//            "success_url" => $this->successUrl,
//            "fail_url" => $this->failUrl,
//            "back_url" => $this->backUrl,
//            "success_url_callback" => "",
//            "fail_url_callback" => "",
//            "status_url_callback" => $this->callbackUrl,
//            "description" => $description,
//            "language" => $language
//        );
//
//        $res = $this->api_call(
//            "POST",
//            "/processing/api/payment/open",
//            "application/json",
//            json_encode($data),
//            "https://paycek.io",
//            $this->getApiKey(),
//            $this->getApiSecret());
//
//        $url = null;
//        if (isset($res["status"])) {
//            if ($res["status"] == PaymentProvidersConstants::PAYCEK_TRANSACTION_STATUS_PAYMENT_EXISTS) {
//                $url = "https://paycek.io/processing/checkout/payment/" . $res["code"];
//            } else if ($res["status"] == PaymentProvidersConstants::PAYCEK_TRANSACTION_STATUS_OK) {
//                $url = $res["data"]["payment_url"];
//            }
//        }
//
//        return $url;

        /**
         * Alternativna verzija bez korištenja API-a
         */
        return $this->generatePaymentUrl(
            $this->getProfileCode(),
            $this->getProfileSecret(),
            $quote->getPreviewHash(),
            $this->getFormattedAmount($quote->getPriceTotal()),
            $items,
            $contact->getEmail(),
            $this->successUrl,
            $this->failUrl,
            $this->backUrl,
            "",
            "",
            $this->callbackUrl,
            $description,
            $language);
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
            "action" => "/api/paycek_create",
            "cart" => $quote->getPreviewHash()
        );

        $ret["forms"][] = $this->twig->render("PaymentProvidersBusinessBundle:PaymentProviders:PayCek/form.html.twig",
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
            "action" => "paycek",
        );

        $button = $buttonTemplate;
        $button["data"]["hash"] = "paycek" . $data["cart"];
        $buttonHtml = $this->twig->render($config["checkout_button"], array("data" => $button));

        return $buttonHtml;
    }

    /**
     * @param $api_key
     * @param $api_secret
     * @param $nonce_str
     * @param $http_method
     * @param $endpoint
     * @param $content_type
     * @param $body_bytes
     * @return string
     */
    public function getApiMac($api_key, $api_secret, $nonce_str, $http_method, $endpoint, $content_type, $body_bytes)
    {
        return hash('sha3-512',
            "\0" .
            $api_key . "\0" .
            $api_secret . "\0" .
            $nonce_str . "\0" .
            $http_method . "\0" .
            $endpoint . "\0" .
            $content_type . "\0" .
            $body_bytes . "\0",
            false);
    }

    /**
     * @param $http_method
     * @param $endpoint
     * @param $content_type
     * @param $body_bytes
     * @param $api_host
     * @param $api_key
     * @param $api_secret
     * @param bool $https_cert_verify
     * @return bool|mixed|string
     * @throws \Exception
     */
    private function api_call($http_method, $endpoint, $content_type, $body_bytes, $api_host, $api_key, $api_secret, $https_cert_verify = true)
    {
        $nonce_str = strval(time() * 1000);

        $headers = array(
            "Content-Type: " . $content_type,
            "ApiKeyAuth-Key: " . $api_key,
            "ApiKeyAuth-Nonce: " . $nonce_str,
            "ApiKeyAuth-MAC: " . $this->getApiMac(
                $api_key,
                $api_secret,
                $nonce_str,
                $http_method,
                $endpoint,
                $content_type,
                $body_bytes
            )
        );

        $restManager = new RestManager();

        $restManager->CURLOPT_HTTPHEADER = $headers;
        $restManager->CURLOPT_RETURNTRANSFER = 1;
        $restManager->CURLOPT_POST = 1;
        $restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $restManager->CURLOPT_POSTFIELDS = $body_bytes;

        return $restManager->get($api_host . $endpoint);
    }

    /**
     * @param $paymentCode
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function getPaymentByPaymentCode($paymentCode)
    {
        $data = array(
            "payment_code" => $paymentCode
        );

        $res = $this->api_call(
            "POST",
            "/processing/api/payment/get",
            "application/json",
            json_encode($data),
            "https://paycek.io",
            $this->getApiKey(),
            $this->getApiSecret());

        return $res;
    }

    /**
     * @param $paymentCode
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function cancelPaymentByPaymentCode($paymentCode)
    {
        $data = array(
            "payment_code" => $paymentCode
        );

        $res = $this->api_call(
            "POST",
            "/processing/api/payment/cancel",
            "application/json",
            json_encode($data),
            "https://paycek.io",
            $this->getApiKey(),
            $this->getApiSecret());

        return $res;
    }
}
