<?php

namespace PaymentProvidersBusinessBundle\Controller;

use AppBundle\Managers\CacheManager;
use AppBundle\Managers\ErrorLogManager;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\PaymentTransactionStatusEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\OrderManager;
use CrmBusinessBundle\Managers\QuoteManager;
use PaymentProvidersBusinessBundle\Constants\PaymentProvidersConstants;
use PaymentProvidersBusinessBundle\Managers\PaymentTransactionManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;

class PaywayController extends AbstractScommerceController
{
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;
    /** @var PaymentTransactionManager $paymentTransactionManager */
    protected $paymentTransactionManager;

    protected $translator;
    protected $logger;
    protected $tokenStorage;
    protected $user;

    protected function initialize($request = null)
    {
        parent::initialize($request);
        $this->helperManager = $this->getContainer()->get('helper_manager');
        $this->quoteManager = $this->getContainer()->get("quote_manager");
        $this->translator = $this->getContainer()->get("translator");
        $this->logger = $this->getContainer()->get("logger");
        $this->user = $this->helperManager->getCurrentUser();
        $this->initializeTwigVariables($request);
    }

    /**
     * @Route("/api/payway_cancel", name="payway_cancel")
     * @Method("GET")
     */
    public function paywayCancelAction(Request $request)
    {
        $this->initialize($request);

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $ret = $this->crmProcessManager->getPaymentProviderUrls();
        $quoteUrl = $ret["quoteUrl"];
        $quoteErrorUrl = $ret["quoteErrorUrl"];

        $logData = Array();
        $logData["action"] = "cancel";
        $logData["payment_type"] = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::PAYWAY_PROVIDER_CODE);
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_RESPONSE);
        $logData["response_data"] = json_encode($_GET);
        $logData["name"] = "Payway cancel";

        if (!isset($_GET["ShoppingCartID"]) || empty($_GET["ShoppingCartID"])) {
            $logData["description"] = "Transaction failed. ShoppingCartID empty";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            return $this->redirect($quoteUrl, 301);
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($_GET["ShoppingCartID"]);

        if (empty($quote)) {
            $logData["has_error"] = 1;
            $logData["description"] = "Quote does not exist";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            return $this->redirect($quoteErrorUrl, 301);
        }

        $data = array();
        $data["is_locked"] = 0;
        $this->quoteManager->updateQuote($quote, $data);

        $logData["quote"] = $quote;
        $logData["has_error"] = 0;
        $logData["description"] = "Transaction canceled";
        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        return $this->redirect($quoteUrl, 301);
    }


    /**
     * @Route("/api/payway_confirm", name="payway_confirm")
     * @Method("GET")
     */
    public function paywayConfirmAction(Request $request)
    {
        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        $ret = $this->crmProcessManager->getPaymentProviderUrls();
        $quoteUrl = $ret["quoteUrl"];
        $quoteErrorUrl = $ret["quoteErrorUrl"];
        $quoteSuccessUrl = $ret["quoteSuccessUrl"];
        $session = $request->getSession();
        $requestUrl = $_SERVER["REQUEST_URI"];
        $requestUrl = explode("?", $requestUrl);

        $logData = Array();
        $logData["action"] = "confirm";
        $logData["payment_type"] = $this->paymentTransactionManager->getPaymentTypeByProviderCode(PaymentProvidersConstants::PAYWAY_PROVIDER_CODE);
        $logData["payment_transaction_log_type"] = $this->paymentTransactionManager->getPaymentTransactionLogTypeById(PaymentProvidersConstants::PAYMENT_TRANSACTION_LOG_TYPE_RESPONSE);
        $logData["has_error"] = 1;
        $logData["name"] = "Payway confirm";

        if (count($requestUrl) < 2) {
            $logData["description"] = "Transaction failed. Success empty";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            $session->set("quote_error", $this->translator->trans('Transaction failed. Please try again or use a different payment method.'));
            return $this->redirect($quoteUrl, 301);
        }

        $requestUrl = $requestUrl[1];
        $requestUrlParts = explode("&", $requestUrl);

        foreach ($requestUrlParts as $requestUrlPart) {
            $requestUrlPart = explode("=", $requestUrlPart);
            $p[$requestUrlPart[0]] = null;
            if (isset($requestUrlPart[1])) {
                $p[$requestUrlPart[0]] = $requestUrlPart[1];
            }
        }

        $logData["response_data"] = json_encode($p);

        if (!isset($p["Success"])) {$logData["description"] = "Transaction failed. Success empty";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            $session->set("quote_error", $this->translator->trans('Transaction failed. Please try again or use a different payment method.'));
            return $this->redirect($quoteUrl, 301);
        }

        if (!isset($p["ShoppingCartID"]) || empty($p["ShoppingCartID"])) {
            $logData["description"] = "Transaction failed. ShoppingCartID empty";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            $session->set("quote_error", $this->translator->trans('Transaction failed. Please try again or use a different payment method.'));
            return $this->redirect($quoteUrl, 301);
        }

        if (!isset($p["Signature"]) || empty($p["Signature"])) {
            $logData["description"] = "Transaction failed. Signature empty";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            $session->set("quote_error", $this->translator->trans('Transaction failed. Please try again or use a different payment method.'));
            return $this->redirect($quoteUrl, 301);
        }

        if (!isset($p["ApprovalCode"])) {
            $logData["description"] = "Transaction failed. ApprovalCode empty";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            $session->set("quote_error", $this->translator->trans('Transaction failed. Please try again or use a different payment method.'));
            return $this->redirect($quoteUrl, 301);
        }

        if (!isset($p["Amount"]) || empty($p["Amount"])) {
            $logData["description"] = "Transaction failed. Amount empty";
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            $session->set("quote_error", $this->translator->trans('Transaction failed. Please try again or use a different payment method.'));
            return $this->redirect($quoteUrl, 301);
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($p["ShoppingCartID"]);

        if (empty($quote)) {
            $logData["description"] = "Transaction failed. Quote does not exist: " . $p["ShoppingCartID"];
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            return $this->redirect($quoteErrorUrl, 301);
        }

        $logData["quote"] = $quote;

        $data = array();
        $data["is_locked"] = 0;
        $quote = $this->quoteManager->updateQuote($quote, $data, true);

        if ($p["Success"] != 1) {
            if (!isset($p["ErrorMessage"])) {$logData["description"] = "Transaction failed. ErrorMessage empty";
                $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
                $session->set("quote_error", $this->translator->trans('Transaction failed. Please try again or use a different payment method.'));
                return $this->redirect($quoteUrl, 301);
            }
            $logData["description"] = "Transaction failed. Success != 1 " . $p["ErrorMessage"];
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            $session->set("quote_error", $p["ErrorMessage"] . ". " . $this->translator->trans('Transaction failed. Please try again or use a different payment method.'));
            return $this->redirect($quoteUrl, 301);
        }

        if (!in_array($quote->getQuoteStatusId(), array(CrmConstants::QUOTE_STATUS_NEW, CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT, CrmConstants::QUOTE_STATUS_ACCEPTED))) {
            $logData["description"] = "Transaction failed. Quote status not QUOTE_STATUS_NEW: " . $p["ShoppingCartID"];
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            return $this->redirect($quoteErrorUrl, 301);
        }

        $amount = $quote->getBasePriceTotal();
        $amount = number_format($amount, 2, ",", "");
        $amount = str_replace(".", "", $amount);

        if (abs(intval($p["Amount"]) - intval($amount)) > 10) {

            $cc[] = array(
                'email' => $_ENV["ORDER_EMAIL_RECIPIENT"],
                'name' => $_ENV["ORDER_EMAIL_RECIPIENT"],
            );

            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $this->errorLogManager->logErrorEvent("PAYWAY ERROR: amount not correct", "quote: {$p["ShoppingCartID"]}", true, $cc);

            $logData["description"] = "Transaction failed. Amount not correct, quote: " . $p["ShoppingCartID"];
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

            $session->set("quote_error", $this->translator->trans('Transaction failed. Please try again or use a different payment method.'));
            return $this->redirect($quoteUrl, 301);
        }

        $quoteStatus = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_ACCEPTED);
        $this->quoteManager->changeQuoteStatus($quote, $quoteStatus);

        if (empty($this->orderManager)) {
            $this->orderManager = $this->getContainer()->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderByQuoteId($quote->getId());

        if (empty($order)) {
            $logData["description"] = "Transaction failed. Order not created, quote: " . $p["ShoppingCartID"];
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
            return $this->redirect($quoteErrorUrl, 301);
        }

        $config = json_decode($order->getPaymentType()->getConfiguration(), true);

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        /** @var PaymentTransactionStatusEntity $paymentTransactionStatus */
        $paymentTransactionStatus = $this->paymentTransactionManager->getPaymentTransactionStatusById($config["default_payment_transaction_status"]);

        $transactionData = array();
        $transactionData["order"] = $order;
        $transactionData["provider"] = "payway_provider";
        $transactionData["signature"] = $p["Signature"];
        $transactionData["transaction_identifier"] = $p["ApprovalCode"];
        $transactionData["amount"] = $quote->getBasePriceTotal();
        $transactionData["currency"] = $quote->getCurrency();
        $transactionData["transaction_status"] = $paymentTransactionStatus;
        $transactionData["payment_type"] = $order->getPaymentType();
        $transactionData["transaction_identifier_second"] = $p["STAN"];
        $transactionData["transaction_identifier_third"] = $p["WsPayOrderId"];
        $transactionData["request_type"] = "transaction";
        $transactionData["transaction_type"] = "preauth";
        $transactionData["acquirer"] = $p["Partner"];
        $transactionData["purchase_installments"] = $p["PaymentPlan"];
        $transactionData["masked_pan"] = $p["CreditCardNumber"];
        $transactionData["response_message"] = $p["Success"];
        $transactionData["card_type"] = $p["PaymentType"];
        $transactionData["payment_method"] = "Card";

        /** @var PaymentTransactionEntity $paymentTransaction */
        $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($transactionData);

        if (empty($paymentTransaction)) {
            $logData["description"] = "Transaction failed. Transaction not saved, order_id: " . $order->getId();
            $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);
        }

        $logData["has_error"] = 0;
        $logData["description"] = "Transaction confirmed";
        $this->paymentTransactionManager->createUpdatePaymentTransactionLog($logData);

        $session->set("order_id", $order->getId());

        return $this->redirect($quoteSuccessUrl, 301);
    }
}
