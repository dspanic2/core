<?php

namespace PaymentProvidersBusinessBundle\Controller;

use AppBundle\Managers\CacheManager;
use AppBundle\Managers\EntityManager;
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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;

class CorvuspayController extends AbstractScommerceController
{
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;

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
     * @Route("/api/corvus_cancel", name="corvus_cancel")
     * @Method("POST")
     */
    public function corvusCancelAction(Request $request)
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
            $this->logger->error("CORVUS ERROR: quote does not exist cancel payway");
            return $this->redirect($quoteErrorUrl, 301);
        }

        $data = array();
        $data["is_locked"] = 0;
        $this->quoteManager->updateQuote($quote, $data);

        return $this->redirect($quoteUrl, 301);
    }

    /**
     * @Route("/api/corvus_confirm", name="corvus_confirm")
     * @Method("POST")
     */
    public function corvusConfirmAction(Request $request)
    {
        $this->initialize($request);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        if (empty($this->orderManager)) {
            $this->orderManager = $this->getContainer()->get("order_manager");
        }

        $ret = $this->crmProcessManager->getPaymentProviderUrls();

        $quoteUrl = $ret["quoteUrl"];
        $quoteErrorUrl = $ret["quoteErrorUrl"];
        $quoteSuccessUrl = $ret["quoteSuccessUrl"];

        $p = $_POST;

        $session = $request->getSession();

        if (!isset($p["order_number"]) || empty($p["order_number"])) {
            $this->logger->error("CORVUS ERROR: order_number empty");
            $session->set("quote_error", $this->translator->trans('Transaction failed. Please try again or use a different payment method.'));
            return $this->redirect($quoteUrl, 301);
        }
        if (!isset($p["language"]) || empty($p["language"])) {
            $this->logger->error("CORVUS ERROR: language empty");
            $session->set("quote_error", $this->translator->trans('Transaction failed. Please try again or use a different payment method.'));
            return $this->redirect($quoteUrl, 301);
        }
        if (!isset($p["approval_code"]) || empty($p["approval_code"])) {
            $this->logger->error("CORVUS ERROR: approval_code empty");
            $session->set("quote_error", $this->translator->trans('Transaction failed. Please try again or use a different payment method.'));
            return $this->redirect($quoteUrl, 301);
        }
        if (!isset($p["signature"]) || empty($p["signature"])) {
            $this->logger->error("CORVUS ERROR: signature empty");
            $session->set("quote_error", $this->translator->trans('Transaction failed. Please try again or use a different payment method.'));
            return $this->redirect($quoteUrl, 301);
        }

        $p["order_number"] = explode("_", $p["order_number"])[1];

        if (isset($_ENV["CREATE_ORDER_BEFORE_CARD_PAYMENT"]) && $_ENV["CREATE_ORDER_BEFORE_CARD_PAYMENT"] == 1) {
            if (empty($this->entityManager)) {
                $this->entityManager = $this->getContainer()->get("entity_manager");
            }

            /** @var OrderEntity $order */
            $order = $this->orderManager->getOrderByIncrementId($p["order_number"]);

            if (empty($order)) {
                $this->logger->error("CORVUS ERROR: order does not exist: " . $p["order_number"]);
                return $this->redirect($quoteErrorUrl, 301);
            }

            $order->setOrderState($this->orderManager->getOrderStateById(CrmConstants::ORDER_STATE_IN_PROCESS));
            $this->entityManager->saveEntityWithoutLog($order);

            $quote = $order->getQuote();
        } else {
            /** @var QuoteEntity $quote */
            $quote = $this->quoteManager->getQuoteByIncrementId($p["order_number"]);

            if (empty($quote)) {
                $this->logger->error("CORVUS ERROR: quote does not exist: " . $p["order_number"]);
                return $this->redirect($quoteErrorUrl, 301);
            }

            $data = array();
            $data["is_locked"] = 0;
            $quote = $this->quoteManager->updateQuote($quote, $data, true);

            if (!in_array($quote->getQuoteStatusId(), array(CrmConstants::QUOTE_STATUS_NEW, CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT, CrmConstants::QUOTE_STATUS_ACCEPTED))) {
                $this->logger->error("CORVUS ERROR: quote status not QUOTE_STATUS_NEW: " . $p["order_number"]);
                return $this->redirect($quoteErrorUrl, 301);
            }

            $quoteStatus = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_ACCEPTED);

            $this->quoteManager->changeQuoteStatus($quote, $quoteStatus);

            /** @var OrderEntity $order */
            $order = $this->orderManager->getOrderByQuoteId($quote->getId());
            if (empty($order)) {
                $this->logger->error("CORVUS ERROR: order not created, quote: " . $p["order_number"]);
                return $this->redirect($quoteUrl, 301);
            }
        }

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        /** @var PaymentTransactionStatusEntity $paymentTransactionStatus */
        $paymentTransactionStatus = $this->paymentTransactionManager->getPaymentTransactionStatusById(CrmConstants::PAYMENT_TRANSACTION_STATUS_PREAUTHORISED);

        $transactionData = array();
        $transactionData["order"] = $order;
        $transactionData["provider"] = "corvuspay_provider";
        $transactionData["signature"] = $p["signature"];
        $transactionData["transaction_identifier"] = $p["approval_code"];
        $transactionData["amount"] = $quote->getBasePriceTotal();
        $transactionData["currency"] = $quote->getCurrency();
        $transactionData["transaction_status"] = $paymentTransactionStatus;

        /** @var PaymentTransactionEntity $paymentTransaction */
        $paymentTransaction = $this->paymentTransactionManager->createUpdatePaymentTransaction($transactionData);

        if (empty($paymentTransaction)) {
            $this->logger->error("CORVUS ERROR: transaction not saved, order_id: " . $order->getId());
        }

        $session->set("order_id", $order->getId());

        return $this->redirect($quoteSuccessUrl, 301);
    }

}
