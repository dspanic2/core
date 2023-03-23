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
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;

class WSPayController extends AbstractScommerceController
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
    }

    /**
     * @Route("/api/wspay_cancel", name="wspay_cancel")
     * @Method("GET")
     */
    public function wspayCancelAction(Request $request)
    {
        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $ret = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $ret["quoteUrl"];
        $quoteErrorUrl = $ret["quoteErrorUrl"];
        $quoteSuccessUrl = $ret["quoteSuccessUrl"];

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);
        if (empty($quote)) {
            $this->logger->error("WSpay ERROR: quote does not exist cancel wspay");

            return $this->redirect($quoteErrorUrl, 301);
        }

        $data = array();
        $data["is_locked"] = 0;
        $this->quoteManager->updateQuote($quote, $data);

        return $this->redirect($quoteUrl, 301);
    }


    /**
     * @Route("/api/wspay_confirm", name="wspay_confirm")
     * @Method("GET")
     */
    public function wspayConfirmAction(Request $request)
    {
        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $ret = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $ret["quoteUrl"];
        $quoteErrorUrl = $ret["quoteErrorUrl"];
        $quoteSuccessUrl = $ret["quoteSuccessUrl"];

        $session = $request->getSession();

        $requestUrl = $_SERVER["REQUEST_URI"];
        $requestUrl = explode("?", $requestUrl);
        if (count($requestUrl) < 2) {
            $this->logger->error("WSpay ERROR: Success empty");
            $session->set(
                "quote_error",
                $this->translator->trans('Transaction failed. Please try again or use a different payment method.')
            );

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

        if (!isset($p["Success"])) {
            $this->logger->error("WSpay ERROR: Success empty");
            $session->set(
                "quote_error",
                $this->translator->trans('Transaction failed. Please try again or use a different payment method.')
            );

            return $this->redirect($quoteUrl, 301);
        }
        if (!isset($p["ShoppingCartID"]) || empty($p["ShoppingCartID"])) {
            $this->logger->error("WSpay ERROR: ShoppingCartID empty");
            $session->set(
                "quote_error",
                $this->translator->trans('Transaction failed. Please try again or use a different payment method.')
            );

            return $this->redirect($quoteUrl, 301);
        }
        if (!isset($p["Signature"]) || empty($p["Signature"])) {
            $this->logger->error("WSpay ERROR: Signature empty");
            $session->set(
                "quote_error",
                $this->translator->trans('Transaction failed. Please try again or use a different payment method.')
            );

            return $this->redirect($quoteUrl, 301);
        }
        if (!isset($p["ApprovalCode"])) {
            $this->logger->error("WSpay ERROR: ApprovalCode empty");
            $session->set(
                "quote_error",
                $this->translator->trans('Transaction failed. Please try again or use a different payment method.')
            );

            return $this->redirect($quoteUrl, 301);
        }
        if (!isset($p["Amount"]) || empty($p["Amount"])) {
            $this->logger->error("WSpay ERROR: Amount empty");
            $session->set(
                "quote_error",
                $this->translator->trans('Transaction failed. Please try again or use a different payment method.')
            );

            return $this->redirect($quoteUrl, 301);
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($p["ShoppingCartID"]);

        if (empty($quote)) {
            $this->logger->error("WSpay ERROR: quote does not exist: " . $p["ShoppingCartID"]);

            return $this->redirect($quoteErrorUrl, 301);
        }

        $data = array();
        $data["is_locked"] = 0;
        $quote = $this->quoteManager->updateQuote($quote, $data, true);

        if ($p["Success"] != 1) {
            if (!isset($p["ErrorMessage"])) {
                $this->logger->error("WSpay ERROR: ErrorMessage empty");
                $session->set(
                    "quote_error",
                    $this->translator->trans('Transaction failed. Please try again or use a different payment method.')
                );

                return $this->redirect($quoteUrl, 301);
            }
            $this->logger->error("WSpay ERROR: Success != 1 " . $p["ErrorMessage"]);
            $session->set(
                "quote_error",
                $p["ErrorMessage"] . ". " . $this->translator->trans(
                    'Transaction failed. Please try again or use a different payment method.'
                )
            );

            return $this->redirect($quoteUrl, 301);
        }

        if (!in_array($quote->getQuoteStatusId(), array(CrmConstants::QUOTE_STATUS_NEW, CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT, CrmConstants::QUOTE_STATUS_ACCEPTED))) {
            $this->logger->error("WSpay ERROR: quote status not QUOTE_STATUS_NEW: " . $p["ShoppingCartID"]);
            return $this->redirect($quoteErrorUrl, 301);
        }

        $amount = $quote->getBasePriceTotal();
        $amount = number_format($amount, 2, ",", "");
        $amount = str_replace(".", "", $amount);

        /**
         * FIX jer kreteni ne vracaju ,00 ako je cjelobrojni iznos
         */
        if (!stripos($p["Amount"], ",") !== false) {
            $amount = substr($amount, 0, -3);
        }

        if (abs(intval($p["Amount"]) - intval($amount)) > 10) {

            $cc[] = array(
                'email' => $_ENV["ORDER_EMAIL_RECIPIENT"],
                'name' => $_ENV["ORDER_EMAIL_RECIPIENT"],
            );

            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            $this->errorLogManager->logErrorEvent("WSpay ERROR: amount not correct", "quote: {$p["ShoppingCartID"]}", true, $cc);

            $this->logger->error("WSpay ERROR: amount not correct, quote: " . $p["ShoppingCartID"]);
            $session->set(
                "quote_error",
                $this->translator->trans('Transaction failed. Please try again or use a different payment method.')
            );

            return $this->redirect($quoteUrl, 301);
        }

        /**
         * Izbaceno zbog prevelike kolicine gresaka koja se stvara nakon potvrde narudžbe plaćenje karticom
         */
        /*$ret = $this->crmProcessManager->validateQuote($quote);

        if ($ret["error"]) {
            $this->redirect($ret["redirect_url"], 301);
        } elseif ($ret["changed"]) {
            $this->redirect($ret["redirect_url"], 301);
        } elseif (isset($ret["redirect_url"]) && !empty($ret["redirect_url"])) {
            $this->redirect($ret["redirect_url"], 301);
        }*/

        $quoteStatus = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_ACCEPTED);

        $this->quoteManager->changeQuoteStatus($quote, $quoteStatus);

        if (empty($this->orderManager)) {
            $this->orderManager = $this->getContainer()->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderByQuoteId($quote->getId());
        if (empty($order)) {
            $this->logger->error("WSpay ERROR: order not created, quote: " . $p["ShoppingCartID"]);

            return $this->redirect($quoteErrorUrl, 301);
        }

        $config = json_decode($order->getPaymentType()->getConfiguration(), true);

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        /** @var PaymentTransactionStatusEntity $paymentTransactionStatus */
        $paymentTransactionStatus = $this->paymentTransactionManager->getPaymentTransactionStatusById(
            $config["default_payment_transaction_status"]
        );

        $transactionData = array();
        $transactionData["order"] = $order;
        $transactionData["provider"] = "wspay_provider";
        $transactionData["signature"] = $p["Signature"];
        $transactionData["transaction_identifier"] = $p["ApprovalCode"];
        $transactionData["amount"] = $quote->getBasePriceTotal();
        $transactionData["currency"] = $quote->getCurrency();
        $transactionData["transaction_status"] = $paymentTransactionStatus;
        $transactionData["payment_type"] = $order->getPaymentType();
        $transactionData["transaction_identifier_second"] = $p["STAN"];
        $transactionData["transaction_identifier_third"] = $p["WsPayOrderId"];

        /** @var PaymentTransactionEntity $paymentTransaction */
        $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($transactionData);

        if (empty($paymentTransaction)) {
            $this->logger->error("WSpay ERROR: transaction not saved, order_id: " . $order->getId());
        }

        $session->set("order_id", $order->getId());

        return $this->redirect($quoteSuccessUrl, 301);
    }
}
