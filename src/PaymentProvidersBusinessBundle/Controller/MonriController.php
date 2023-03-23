<?php

namespace PaymentProvidersBusinessBundle\Controller;

use AppBundle\Managers\CacheManager;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\PaymentTransactionStatusEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\OrderManager;
use CrmBusinessBundle\Managers\QuoteManager;
use PaymentProvidersBusinessBundle\PaymentProviders\MonriProvider;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;

class MonriController extends AbstractScommerceController
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
    /** @var MonriProvider $monriProvider */
    protected $monriProvider;

    protected $translator;
    protected $logger;
    protected $tokenStorage;
    protected $user;

    protected function initialize($request = null)
    {
        parent::initialize($request);
        $this->helperManager = $this->getContainer()->get("helper_manager");
        $this->quoteManager = $this->getContainer()->get("quote_manager");
        $this->translator = $this->getContainer()->get("translator");
        $this->logger = $this->getContainer()->get("logger");
        $this->user = $this->helperManager->getCurrentUser();
    }

    /**
     * @Route("/api/monri_cancel", name="monri_cancel")
     * @Method("GET")
     */
    public function monriCancelAction(Request $request)
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
            $this->logger->error("Monri ERROR: quote does not exist cancel monri");

            return $this->redirect($quoteErrorUrl, 301);
        }

        $data = array();
        $data["is_locked"] = 0;
        $this->quoteManager->updateQuote($quote, $data);

        return $this->redirect($quoteUrl, 301);
    }

    /**
     * @Route("/api/monri_confirm", name="monri_confirm")
     * @Method("GET")
     */
    public function monriConfirmAction(Request $request)
    {
        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }
        if (empty($this->monriProvider)) {
            $this->monriProvider = $this->getContainer()->get("monri_provider");
        }
        if (empty($this->orderManager)) {
            $this->orderManager = $this->getContainer()->get("order_manager");
        }

        $ret = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $ret["quoteUrl"];
        $quoteErrorUrl = $ret["quoteErrorUrl"];
        $quoteSuccessUrl = $ret["quoteSuccessUrl"];

        $session = $request->getSession();

        $requestUrl = $_SERVER["REQUEST_URI"];
        $requestUrl = explode("?", $requestUrl);
        if (count($requestUrl) < 2) {
            $this->logger->error("Monri ERROR: Success empty");
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

        if (!isset($p["response_code"])) {
            $this->logger->error("Monri ERROR: Response code empty");
            $session->set(
                "quote_error",
                $this->translator->trans("Transaction failed. Please try again or use a different payment method.")
            );
            return $this->redirect($quoteUrl, 301);
        }

        if ($p["response_code"] != "0000") {
            $this->logger->error("Monri ERROR: Response code not 0000");
            $session->set(
                "quote_error",
                $this->translator->trans("Transaction failed. Please try again or use a different payment method.")
            );
            return $this->redirect($quoteUrl, 301);
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getQuoteByHash($p["order_number"]);
        if (empty($quote)) {
            $this->logger->error("Monri ERROR: quote does not exist: " . $p["order_number"]);
            return $this->redirect($quoteErrorUrl, 301);
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderByQuoteId($quote->getId());
        if (!empty($order)) {
            return $this->redirect($quoteSuccessUrl, 301);
        }

        /** @var PaymentTypeEntity $paymentType */
        $paymentType = $quote->getPaymentType();

        $config = json_decode($paymentType->getConfiguration(), true);
        if (empty($config)) {
            $this->logger->error("Monri ERROR: config is empty");
            return $this->redirect($quoteErrorUrl, 301);
        }

        $requestUrlWithoutDigest = $_SERVER["REQUEST_URI"];
        $requestUrlWithoutDigest = explode("&digest=", $requestUrlWithoutDigest);

        $digest = $this->monriProvider->generateSignatureComplete($config, $requestUrlWithoutDigest[0], $quote);
        if ($digest != $p["digest"]) {
            $this->logger->error("Monri ERROR: digest is invalid: " . $p["digest"] . " -> " . $digest);
            return $this->redirect($quoteErrorUrl, 301);
        }

        $data = array();
        $data["is_locked"] = 0;
        $quote = $this->quoteManager->updateQuote($quote, $data, true);

        if (!in_array($quote->getQuoteStatusId(), array(
            CrmConstants::QUOTE_STATUS_NEW,
            CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT,
            CrmConstants::QUOTE_STATUS_ACCEPTED))) {
            $this->logger->error("Monri ERROR: quote status not QUOTE_STATUS_NEW: " . $p["order_number"]);
            return $this->redirect($quoteErrorUrl, 301);
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

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderByQuoteId($quote->getId());
        if (empty($order)) {
            $this->logger->error("Monri ERROR: order not created, quote: " . $p["order_number"]);
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
        $transactionData["provider"] = "monri_provider";
        $transactionData["signature"] = $p["digest"];
        $transactionData["transaction_identifier"] = $p["approval_code"];
        $transactionData["amount"] = $quote->getBasePriceTotal();
        $transactionData["currency"] = $quote->getCurrency();
        $transactionData["transaction_status"] = $paymentTransactionStatus;
        $transactionData["payment_type"] = $order->getPaymentType();
        $transactionData["transaction_identifier_second"] = $p["reference_number"];

        /** @var PaymentTransactionEntity $paymentTransaction */
        $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($transactionData);

        if (empty($paymentTransaction)) {
            $this->logger->error("Monri ERROR: transaction not saved, order_id: " . $order->getId());
        }

        $session->set("order_id", $order->getId());

        return $this->redirect($quoteSuccessUrl, 301);
    }
}