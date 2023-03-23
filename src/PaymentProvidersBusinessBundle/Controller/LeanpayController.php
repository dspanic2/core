<?php

namespace PaymentProvidersBusinessBundle\Controller;

use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\OrderManager;
use CrmBusinessBundle\Managers\QuoteManager;
use PaymentProvidersBusinessBundle\Constants\PaymentProvidersConstants;
use PaymentProvidersBusinessBundle\Managers\PaymentTransactionManager;
use PaymentProvidersBusinessBundle\PaymentProviders\LeanpayProvider;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;

class LeanpayController extends AbstractScommerceController
{
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var PaymentTransactionManager $paymentTransactionManager */
    protected $paymentTransactionManager;
    /** @var LeanpayProvider $leanpayProvider */
    protected $leanpayProvider;

    protected function initialize($request = null)
    {
        parent::initialize($request);
        $this->initializeTwigVariables($request);
        $this->quoteManager = $this->getContainer()->get("quote_manager");
    }

    /**
     * @Route("/api/leanpay_status", name="leanpay_status")
     * @Method("POST")
     */
    public function leanpayStatus(Request $request)
    {
        $this->initialize($request);

        $postParams = json_decode(file_get_contents('php://input'));

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }
        if (empty($this->orderManager)) {
            $this->orderManager = $this->getContainer()->get("order_manager");
        }

        $logData = Array();
        $logData["action"] = "status";
        $logData["has_error"] = 0;
        $logData["response_data"] = json_encode($postParams);
        $logData["payment_type"] = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::LEANPAY_PROVIDER_CODE);
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_RESPONSE);
        $logData["name"] = "Leanpay status route";

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($postParams->vendorTransactionId);
        if (empty($quote)) {
            $logData["has_error"] = 1;
            $logData["description"] = "Quote does not exist for preview_hash {$postParams->vendorTransactionId}";

            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            return new Response();
        }
        $logData["quote"] = $quote;

        /**
         * Status check
         */
        if ($postParams->status === "SUCCESS") {

            /**
             * Provjerava da li je transakcija završena prije, preko /api/leanpay_confirm
             * Ako nije napravi ju ovdje
             */
            $succesfulPaymentTransaction = $this->paymentTransactionManager->getPaymentTransactionByQuoteId($quote->getId());

            if (!$succesfulPaymentTransaction) {
                $quoteStatus = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_ACCEPTED);
                $this->quoteManager->changeQuoteStatus($quote, $quoteStatus);

                /** @var OrderEntity $order */
                $order = $this->orderManager->getOrderByQuoteId($quote->getId());

                if (empty($order)) {
                    $logData["has_error"] = 1;
                    $logData["description"] = "Cannot create order for quote id {$quote->getId()}";
                    $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

                    return new Response();
                }

                /** @var PaymentTypeEntity $paymentType */
                $paymentType = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::LEANPAY_PROVIDER_CODE);

                $config = json_decode($paymentType->getConfiguration(), true);
                $paymentTransactionStatusId = PaymentProvidersConstants::PAYMENT_TRANSACTION_STATUS_PREAUTORIZIRANO;
                if (isset($config["default_payment_transaction_status"]) && !empty($config["default_payment_transaction_status"])) {
                    $paymentTransactionStatusId = $config["default_payment_transaction_status"];
                }

                try {
                    $transactionData = array();
                    $transactionData["order"] = $order;
                    $transactionData["quote"] = $quote;
                    $transactionData["provider"] = "leanpay_provider";
                    $transactionData["amount"] = $quote->getBasePriceTotal();
                    $transactionData["currency"] = $quote->getCurrency();
                    $transactionData["transaction_status"] = $this->paymentTransactionManager->getPaymentTransactionStatusById($paymentTransactionStatusId);
                    $transactionData["payment_type"] = $order->getPaymentType();
                    $transactionData["signature"] = $postParams->md5Signature;
                    $transactionData["transaction_identifier"] = $postParams->leanPayTransactionId;
                    $transactionData["response_message"] = $postParams->status;
                    $transactionData["request_type"] = "transaction";
                    $transactionData["transaction_type"] = "preauth";
                    $transactionData["payment_method"] = "Leanpay";

                    $this->paymentTransactionManager->createUpdatePaymentTransaction($transactionData);

                    $logData["description"] = "Transaction confirmed in Status route";
                } catch (\Exception $e) {
                    $logData["has_error"] = 1;
                    $logData["description"] = "Leanpay status route - cannot create payment transaction";
                }
            } else {
                /**
                 * Update postojecu transakciju sa dodatnim podatcima
                 */
                $transactionData = array();
                $transactionData["signature"] = $postParams->md5Signature;
                $transactionData["transaction_identifier"] = $postParams->leanPayTransactionId;
                $transactionData["response_message"] = $postParams->status;

                $this->paymentTransactionManager->createUpdatePaymentTransaction($transactionData, $succesfulPaymentTransaction);

                $logData["description"] = "Transaction already confirmed in Confirm route. Added additional data in Status route";
            }

        } else if ($postParams->status === "CANCELED") {

            $logData["description"] = "CANCELED status - Customer canceled the transaction by closing application window";

        } else if ($postParams->status === "EXPIRED") {

            $logData["description"] = "EXPIRED status - Customer didn't finish application in 2 hours after starting application or was redirected to /vendor/checkout";

        } else if ($postParams->status === "FAILED") {

            /** @var OrderEntity $order */
            $order = $this->orderManager->getOrderByQuoteId($quote->getId());

            if (!empty($order)) {
                // check if order is already canceled
                if ($order->getOrderStateId() == CrmConstants::ORDER_STATE_CANCELED) {
                    $logData["description"] = "FAILED status - Customers application was rejected or failed. Order already canceled. Order id: {$order->getId()}";
                }
                // cancel order
                else {
                    $data = array();
                    $data["order_state"] = $this->orderManager->getOrderStateById(CrmConstants::ORDER_STATE_CANCELED);

                    $this->orderManager->updateOrder($order, $data);
                    $this->orderManager->dispatchOrderCanceled($order);

                    $logData["description"] = "FAILED status - Customers application was rejected or failed. Order was created before but canceled on Status route. Order id: {$order->getId()}";
                }
            } else {
                $logData["description"] = "FAILED status - Customers application was rejected or failed";
            }
        }

        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        return new Response();
    }

    /**
     * @Route("/api/leanpay_cancel", name="leanpay_cancel")
     * @Method("GET")
     */
    public function leanpayCancelAction(Request $request)
    {
        $this->initialize($request);

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $logData = Array();
        $logData["action"] = "cancel";
        $logData["payment_type"] = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::LEANPAY_PROVIDER_CODE);
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_RESPONSE);
        $logData["response_data"] = json_encode($_GET);
        $logData["name"] = "Leanpay cancel route";
        $logData["has_error"] = 0;

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $ret = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $ret["quoteUrl"];
        $quoteErrorUrl = $ret["quoteErrorUrl"];

        if(!isset($_GET["preview_hash"]) || empty($_GET["preview_hash"])){
            $logData["has_error"] = 1;
            $logData["description"] = "Missing preview_hash in response";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            return $this->redirect($quoteErrorUrl, 301);
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($_GET['preview_hash']);
        if (empty($quote)) {
            $logData["has_error"] = 1;
            $logData["description"] = "Quote does not exist for preview_hash {$_GET["preview_hash"]}";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            return $this->redirect($quoteErrorUrl, 301);
        }

        $data = array();
        $data["is_locked"] = 0;
        $this->quoteManager->updateQuote($quote, $data);

        $logData["quote"] = $quote;
        $logData["description"] = "Transaction canceled";
        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        return $this->redirect($quoteUrl, 301);
    }

    /**
     * @Route("/api/leanpay_confirm", name="leanpay_confirm")
     * @Method("GET")
     */
    public function leanpayConfirmAction(Request $request)
    {
        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }
        if (empty($this->leanpayProvider)) {
            $this->leanpayProvider = $this->getContainer()->get("leanpay_provider");
        }
        if (empty($this->orderManager)) {
            $this->orderManager = $this->getContainer()->get("order_manager");
        }
        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $logData = Array();
        $logData["action"] = "success";
        $logData["has_error"] = 0;
        $logData["response_data"] = json_encode($_GET);
        $logData["payment_type"] = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::LEANPAY_PROVIDER_CODE);
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_RESPONSE);
        $logData["name"] = "Leanpay confirm route";

        $session = $request->getSession();

        $ret = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteErrorUrl = $ret["quoteErrorUrl"];
        $quoteSuccessUrl = $ret["quoteSuccessUrl"];

        if(!isset($_GET["preview_hash"]) || empty($_GET["preview_hash"])){
            $logData["has_error"] = 1;
            $logData["description"] = "Missing preview_hash in response";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            return $this->redirect($quoteErrorUrl, 301);
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($_GET['preview_hash']);
        if (empty($quote)) {
            $logData["has_error"] = 1;
            $logData["description"] = "Quote does not exist for preview_hash {$_GET["preview_hash"]}";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            $session->set("quote_error", $this->translator->trans("Quote does not exist."));

            return $this->redirect($quoteErrorUrl, 301);
        }

        $logData["quote"] = $quote;

        /*
         * Provjerava da li je transakcija završena prije, preko /api/leanpay_status
         * Ako nije napravi ju ovdje
         */
        $succesfulPaymentTransaction = $this->paymentTransactionManager->getPaymentTransactionByQuoteId($quote->getId());

        if (!$succesfulPaymentTransaction) {
            $quoteStatus = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_ACCEPTED);
            $this->quoteManager->changeQuoteStatus($quote, $quoteStatus);

            /** @var OrderEntity $order */
            $order = $this->orderManager->getOrderByQuoteId($quote->getId());

            if (empty($order)) {
                $logData["has_error"] = 1;
                $logData["description"] = "Cannot create order for quote id {$quote->getId()}";
                $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

                $session->set("quote_error",$this->translator->trans("Error creating order, please try again."));
                return $this->redirect($quoteErrorUrl, 301);
            }

            /** @var PaymentTypeEntity $paymentType */
            $paymentType = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::LEANPAY_PROVIDER_CODE);

            $config = json_decode($paymentType->getConfiguration(), true);
            $paymentTransactionStatusId = PaymentProvidersConstants::PAYMENT_TRANSACTION_STATUS_PREAUTORIZIRANO;
            if(isset($config["default_payment_transaction_status"]) && !empty($config["default_payment_transaction_status"])){
                $paymentTransactionStatusId = $config["default_payment_transaction_status"];
            }

            try {
                $transactionData = array();
                $transactionData["order"] = $order;
                $transactionData["quote"] = $quote;
                $transactionData["provider"] = "leanpay_provider";
                $transactionData["amount"] = $quote->getBasePriceTotal();
                $transactionData["currency"] = $quote->getCurrency();
                $transactionData["transaction_status"] = $this->paymentTransactionManager->getPaymentTransactionStatusById($paymentTransactionStatusId);
                $transactionData["payment_type"] = $order->getPaymentType();
                $transactionData["request_type"] = "transaction";
                $transactionData["transaction_type"] = "preauth";
                $transactionData["payment_method"] = "Leanpay";

                $this->paymentTransactionManager->createUpdatePaymentTransaction($transactionData);
                $logData["description"] = "Transaction confirmed in Confirm route";
            }
            catch (\Exception $e) {
                $logData["has_error"] = 1;
                $logData["description"] = "Leanpay Confirm route - cannot create payment transaction";
            }
        } else {
            /** @var OrderEntity $order */
            $order = $this->orderManager->getOrderByQuoteId($quote->getId());

            $logData["description"] = "Transaction already confirmed in Status route";
        }

        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        $session->set("order_id", $order->getId());

        return $this->redirect($quoteSuccessUrl, 301);
    }
}
