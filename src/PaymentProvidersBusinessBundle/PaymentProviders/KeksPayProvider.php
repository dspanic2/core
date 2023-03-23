<?php

namespace PaymentProvidersBusinessBundle\PaymentProviders;

use AppBundle\Context\DatabaseContext;
use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use mysql_xdevapi\Exception;
use PaymentProvidersBusinessBundle\Abstracts\AbstractPaymentProvider;
use PaymentProvidersBusinessBundle\Constants\PaymentProvidersConstants;
use PaymentProvidersBusinessBundle\Interfaces\PaymentProviderInterface;

class KeksPayProvider extends AbstractPaymentProvider implements PaymentProviderInterface
{
    /** @var RestManager $restManager */
    protected $restManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;

    protected $cid;
    protected $tid;
    protected $secret;
    protected $serviceUrl;
    protected $refundUrl;
    protected $siteName;
    public $auth;

    public function initialize()
    {
        parent::initialize();

        $this->siteName = $_ENV["SITE_NAME"];

        $this->cid = $_ENV["KEKSPAY_CID"];
        $this->tid = $_ENV["KEKSPAY_TID"];
        $this->secret = $_ENV["KEKSPAY_SECRET"];
        $this->serviceUrl = $_ENV["KEKSPAY_SERVICE_URL"];
        $this->refundUrl = $_ENV["KEKSPAY_REFUND_URL"];

        if (isset($_ENV["KEKSPAY_USERNAME"]) && !empty($_ENV["KEKSPAY_USERNAME"]) &&
            isset($_ENV["KEKSPAY_PASSWORD"]) && !empty($_ENV["KEKSPAY_PASSWORD"])) {
            $this->auth = "Basic " . base64_encode($_ENV["KEKSPAY_USERNAME"] . ":" . $_ENV["KEKSPAY_PASSWORD"]);
        } else if (isset($_ENV["KEKSPAY_TOKEN"]) && !empty($_ENV["KEKSPAY_TOKEN"])) {
            $this->auth = "Token " . $_ENV["KEKSPAY_TOKEN"];
        }
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

        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $quote->getPaymentType();

        $config = json_decode($paymentType->getConfiguration(), true);
        if (empty($config)) {
            return $ret;
        }

        $amount = number_format($quote->getBasePriceTotal(), 2, ".", "");

        $successUrl = $_ENV["SSL"] . "://" . $_ENV["FRONTEND_URL"];
        $failUrl = $_ENV["SSL"] . "://" . $_ENV["FRONTEND_URL"];

        $base_payment_data = array(
            "qr_type" => 1,
            "bill_id" => $quote->getPreviewHash(),
            "cid" => $this->cid,
            "tid" => $this->tid,
            "amount" => $amount,
            "currency" => $quote->getCurrency()->getCode(),
            "store" => $this->siteName,
            "campaign" => $this->siteName,
            "success_url" => $successUrl,
            "fail_url" => $failUrl
        );

        $ret["forms"][] = $this->twig->render("PaymentProvidersBusinessBundle:PaymentProviders:KeksPay/form.html.twig",
            array(
                "data" => json_encode($base_payment_data),
                "hash" => $quote->getPreviewHash()
            )
        );

        $ret["buttons"][] = $this->getPaymentButtonsHtml($base_payment_data, $config);
        $ret["signature"] = null;

        /**
         * Insert transaction log
         */
        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $logData = Array();
        $logData["action"] = "prepare";
        $logData["has_error"] = 0;
        $logData["request_data"] = json_encode($base_payment_data);
        $logData["payment_type"] = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::KEKSPAY_PROVIDER_CODE);
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_PREPARE);
        $logData["quote"] = $quote;
        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        return $ret;
    }

    /**
     * @param $data
     * @param $config
     * @return mixed
     */
    public function getPaymentButtonsHtml($data, $config)
    {
        /**
         * Deep link for mobile button
         */
        $url = $this->serviceUrl . "?" . http_build_query($data);

        $button = array(
            "type" => "button",
            "name" => "",
            "class" => "btn-primary btn-blue",
            "url" => $url,
            "action" => "kekspay",
        );

        $button["data"]["hash"] = "kekspay_" . $data["bill_id"];
        $buttonHtml = $this->twig->render($config["checkout_button"], array(
            "data" => $button,
            "hash" => $data["bill_id"],
        ));

        return $buttonHtml;
    }

    /**
     * @param $tid
     * @param $epochTime
     * @param $amount
     * @param $billId
     * @param $desKey
     * @return string
     */
    public function calculateHash($tid, $epochTime, $amount, $billId, $desKey)
    {
        // Concat epochtime + webshop tid + order amount + bill_id for payload.
        $payload = $epochTime . $tid . $amount . $billId;
        // Extract bytes from md5 hex hash.
        $payload_checksum = pack('H*', md5($payload));
        // Create 8 byte binary initialization vector.
        $iv = str_repeat(pack('c', 0), 8);
        // Encrypt data using 3DES CBC algorithm and convert it to hex.
        $hash = bin2hex(openssl_encrypt($payload_checksum, 'des-ede3-cbc', $desKey, OPENSSL_RAW_DATA, $iv));

        return strtoupper($hash);
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @param null $refundAmount
     * @return bool
     * @throws \Exception
     */
    public function refundTransaction(PaymentTransactionEntity $paymentTransaction, $refundAmount = null)
    {
        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $paymentTransaction->getPaymentType();

        if(empty($paymentType) || $paymentType->getProvider() != PaymentProvidersConstants::KEKSPAY_PROVIDER_CODE){

            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $exception = new Exception("KeksPay - check status - payment transaction provider code does not match", "Payment transaction id: {$paymentTransaction->getId()} payment transaction provider code does not match");

            $this->errorLogManager->logExceptionEvent("KeksPay - check status - payment transaction provider code does not match", $exception, true);

            throw $exception;
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        /** @var OrderEntity $order */
        $order = $paymentTransaction->getOrder();

        $logData = Array();
        $logData["action"] = "refund";
        $logData["has_error"] = 1;
        $logData["response_data"] = null;
        $logData["request_data"] = null;
        $logData["payment_type"] = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::KEKSPAY_PROVIDER_CODE);
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_RESPONSE);
        $logData["quote"] = $order->getQuote();

        if(!empty($refundAmount)){
            $amount = number_format($refundAmount, 2, ".", "");
        }
        else{
            $amount = number_format($paymentTransaction->getAmount(), 2, ".", "");
        }
        $time = time();

        $post = array();
        $post["bill_id"] = $order->getPreviewHash();
        $post["tid"] = $this->tid;
        $post["amount"] = $amount;
        $post["epochtime"] = $time;

        $hash = $this->calculateHash(
            $this->tid,
            $time,
            $amount,
            $order->getPreviewHash(),
            $this->secret);

        $post["hash"] = $hash;

        if (empty($this->restManager)) {
            $this->restManager = $this->getContainer()->get("rest_manager");
        }

        $this->restManager->CURLOPT_RETURNTRANSFER = 1;
        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_SSL_VERIFYHOST = 0;
        $this->restManager->CURLOPT_SSL_VERIFYPEER = 0;
        $this->restManager->CURLOPT_POSTFIELDS = json_encode($post);
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";

        $logData["request_data"] = json_encode($post);

        try{
            $res = $this->restManager->get($this->refundUrl);
        }
        catch (\Exception $e){
            $logData["description"] = "KeksPay error: service error: " . $paymentTransaction->getTransactionIdentifier();
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            throw $e;
        }

        $logData["response_data"] = json_encode($res);

        if (empty($res)) {
            $logData["description"] = "KeksPay error: refund data is empty: " . $paymentTransaction->getTransactionIdentifier();
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            throw new \Exception($logData["description"]);
        }

        if (!isset($res["amount"]) || !isset($res["status"]) || !isset($res["keks_refund_id"])) {

            $logData["description"] = "KeksPay error: refund data not valid: " . json_encode($res);
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            throw new \Exception($logData["description"]);
        }

        if ($res["status"] != 0) {

            $logData["description"] = "KeksPay error: refund not approved: " . json_encode($res);
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            throw new \Exception($logData["description"]);
        }

        /**
         * If full refund change status
         */
        $totalRefundAmount = floatval($paymentTransaction->getRefundAmount());
        $totalRefundAmount = $totalRefundAmount+floatval($amount);

        if ($totalRefundAmount >= $paymentTransaction->getAmount()) {
            $paymentTransactionStatusId = PaymentProvidersConstants::PAYMENT_TRANSACTION_STATUS_STORNO;
        }
        else{
            $paymentTransactionStatusId = PaymentProvidersConstants::PAYMENT_TRANSACTION_STATUS_DJELOMICNO_STORNO;
        }

        $paymentTransactionData = array();
        $paymentTransactionData["transaction_status"] = $this->paymentTransactionManager->getPaymentTransactionStatusById($paymentTransactionStatusId);
        $paymentTransactionData["transaction_identifier_third"] = $res["keks_refund_id"];
        $paymentTransactionData["refund_amount"] = $totalRefundAmount;

        $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($paymentTransactionData, $paymentTransaction);

        $logData["has_error"] = 0;
        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $this->crmProcessManager->processRefund($paymentTransaction);

        return $paymentTransaction;
    }

    /**
     * Returns true if the transaction has been completed, false if it has not been completed
     *
     * @param $quoteHash
     * @return bool
     */
    public function checkTransactionStatus($quoteHash)
    {
        $ret = Array();
        $ret["order_created"] = false;

        if (strlen($quoteHash) != 32 || strpos($quoteHash, " ") !== false) {
            return $ret;
        }

        if(empty($this->orderManager)){
            $this->orderManager = $this->container->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderByHash($quoteHash);

        if(empty($order)){
            return $ret;
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        /** @var Session $session */
        $session = $this->container->get("session");
        $session->set("order_id", $order->getId());

        $ret = $this->crmProcessManager->getPaymentProviderUrls();
        $ret["order_created"] = true;

        return $ret;
    }
}
