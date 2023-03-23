<?php

namespace CrmBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Helpers\NumberHelper;
use AppBundle\Managers\ErrorLogManager;
use AppBundle\Managers\MailManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderItemEntity;
use CrmBusinessBundle\Entity\OrderStateEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Managers\BarcodeManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\EmailTemplateManager;
use CrmBusinessBundle\Managers\OrderManager;
use CrmBusinessBundle\Managers\QuoteManager;
use JMS\Serializer\Tests\Fixtures\Order;
use Monolog\Logger;
use PaymentProvidersBusinessBundle\Managers\PaymentTransactionManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use AppBundle\Factory\FactoryEntityType;
use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use AppBundle\Managers\EntityManager;
use Symfony\Component\Config\Definition\Exception\Exception;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;

class OrderController extends AbstractController
{
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /**@var QuoteManager $quoteManager */
    protected $quoteManager;
    /**@var OrderManager $orderManager */
    protected $orderManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var BarcodeManager $barcodeManager */
    protected $barcodeManager;
    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;
    /** @var PaymentTransactionManager $paymentTransactionManager */
    protected $paymentTransactionManager;

    protected function initialize()
    {
        parent::initialize();
        $this->quoteManager = $this->getContainer()->get('quote_manager');
        $this->orderManager = $this->getContainer()->get('order_manager');
        $this->entityManager = $this->getContainer()->get('entity_manager');
    }

    /**
     * @param OrderEntity $order
     * @return mixed
     */
    public function generateMoneyTransferPaymentSlipAction(OrderEntity $order)
    {

        if (empty($this->barcodeManager)) {
            $this->barcodeManager = $this->getContainer()->get("barcode_manager");
        }

        return new Response($this->barcodeManager->generatePDF417Barcode($order));
    }

    /**
     * @Route("/order/order_generate_pdf", name="order_generate_pdf")
     * @Method("POST")
     */
    public function orderGeneratePdf()
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(
                array(
                    "error" => true,
                    "title" => $this->translator->trans("Error"),
                    "message" => $this->translator->trans("id not received"),
                )
            );
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderById($p["id"]);
        if (empty($order)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Order not found")));
        }

        $file = $this->orderManager->generateOrderFile($order);

        return new JsonResponse(array("error" => false, "file" => $file, "message" => $this->translator->trans("Document generated")));
    }

    /**
     * @Route("/order/mass_completed", name="mass_completed")
     * @Method("POST")
     */
    public function massCompletedAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order ids are missing')));
        }

        $keys = array_keys($p["items"]);
        if (empty($keys)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Entity type code is missing')));
        }

        /** @var OrderStateEntity $orderState */
        $orderState = $this->orderManager->getOrderStateById(CrmConstants::ORDER_STATE_COMPLETED);

        foreach ($p["items"][$keys[0]] as $id) {

            /** @var OrderEntity $order */
            $order = $this->orderManager->getOrderById($id);

            if (empty($order)) {
                continue;
            }

            if ($order->getOrderStateId() != CrmConstants::ORDER_STATE_COMPLETED) {

                $data = array();
                $data["order_state"] = $orderState;

                $this->orderManager->updateOrder($order, $data);
            }
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Order status successfully changed')));
    }

    /**
     * @Route("/order/complete_transaction", name="complete_transaction")
     * @Method("POST")
     */
    public function completeTransactionAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Payment transaction id is missing")));
        }

        $ptm = $this->getContainer()->get("payment_transaction_manager");

        /** @var PaymentTransactionEntity $paymentTransaction */
        $paymentTransaction = $ptm->getPaymentTransactionById($p["id"]);
        if (empty($paymentTransaction)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Payment transaction does not exist")));
        }

        if ($paymentTransaction->getTransactionStatusId() != CrmConstants::PAYMENT_TRANSACTION_STATUS_PREAUTHORISED) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("This transaction is already completed")));
        }

        $provider = $this->getContainer()->get($paymentTransaction->getProvider());

        $paymentTransaction = $provider->completeTransaction($paymentTransaction);
        if (empty($paymentTransaction)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Transaction cannot be charged, please contact the payment provider")));
        }

        return new JsonResponse(array("error" => false, "message" => $this->translator->trans("Transaction successfully charged")));
    }

    /**
     * @Route("/order/refund_transaction", name="refund_transaction")
     * @Method("POST")
     */
    public function refundTransactionAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Payment transaction id is missing")));
        }

        $ptm = $this->getContainer()->get("payment_transaction_manager");

        /** @var PaymentTransactionEntity $paymentTransaction */
        $paymentTransaction = $ptm->getPaymentTransactionById($p["id"]);
        if (empty($paymentTransaction)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Payment transaction does not exist")));
        }

        if ($paymentTransaction->getTransactionStatusId() != CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("This transaction cannot be refunded")));
        }

        $provider = $this->getContainer()->get($paymentTransaction->getProvider());

        $paymentTransaction = $provider->refundTransaction($paymentTransaction);
        if (empty($paymentTransaction)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Transaction cannot be refunded, please contact the payment provider")));
        }

        return new JsonResponse(array("error" => false, "message" => $this->translator->trans("Transaction successfully refunded")));
    }

    /**
     * @Route("/order/cancel_transaction", name="cancel_transaction")
     * @Method("POST")
     */
    public function cancelTransactionAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Payment transaction id is missing")));
        }

        $ptm = $this->getContainer()->get("payment_transaction_manager");

        /** @var PaymentTransactionEntity $paymentTransaction */
        $paymentTransaction = $ptm->getPaymentTransactionById($p["id"]);
        if (empty($paymentTransaction)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Payment transaction does not exist")));
        }

        if ($paymentTransaction->getTransactionStatusId() != CrmConstants::PAYMENT_TRANSACTION_STATUS_PREAUTHORISED) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("This transaction cannot be canceled")));
        }

        $provider = $this->getContainer()->get($paymentTransaction->getProvider());

        $paymentTransaction = $provider->voidTransaction($paymentTransaction);
        if (empty($paymentTransaction)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Transaction cannot be canceled, please contact the payment provider")));
        }

        return new JsonResponse(array("error" => false, "message" => $this->translator->trans("Transaction successfully canceled")));
    }

    /**
     * @Route("/order/order_state_in_process", name="order_state_in_process")
     * @Method("POST")
     */
    public function orderStateInProcessAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order id is not defined')));
        }

        if (empty($this->orderManager)) {
            $this->orderManager = $this->getContainer()->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderById($p["id"]);

        if ($order->getOrderStateId() != CrmConstants::ORDER_STATE_NEW) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order is not new')));
        }

        $data = array();
        $data["order_state"] = $this->orderManager->getOrderStateById(CrmConstants::ORDER_STATE_IN_PROCESS);

        $this->orderManager->updateOrder($order, $data);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Order state updated')));
    }

    /**
     * @Route("/order/order_state_completed", name="order_state_completed")
     * @Method("POST")
     */
    public function orderStateCompletedAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order id is not defined')));
        }

        if (empty($this->orderManager)) {
            $this->orderManager = $this->getContainer()->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderById($p["id"]);

        if (!in_array($order->getOrderStateId(), array(CrmConstants::ORDER_STATE_READY_FOR_PICKUP, CrmConstants::ORDER_STATE_IN_PROCESS))) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order is not in state ready for pickup')));
        }

        $data = array();
        $data["order_state"] = $this->orderManager->getOrderStateById(CrmConstants::ORDER_STATE_COMPLETED);

        $this->orderManager->updateOrder($order, $data);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Order state updated')));
    }

    /**
     * @Route("/order/order_state_reversal", name="order_state_reversal")
     * @Method("POST")
     */
    public function orderStateReversalAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order id is not defined')));
        }

        if (empty($this->orderManager)) {
            $this->orderManager = $this->getContainer()->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderById($p["id"]);

        if ($order->getOrderStateId() != CrmConstants::ORDER_STATE_COMPLETED) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order is not in state completed')));
        }

        $data = array();
        $data["order_state"] = $this->orderManager->getOrderStateById(CrmConstants::ORDER_STATE_REVERSAL);

        $this->orderManager->updateOrder($order, $data);

        $this->orderManager->dispatchOrderReversed($order);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Order state updated')));
    }

    /**
     * @Route("/order/order_state_canceled", name="order_state_canceled")
     * @Method("POST")
     */
    public function orderStateCanceledAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order id is not defined')));
        }

        if (empty($this->orderManager)) {
            $this->orderManager = $this->getContainer()->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderById($p["id"]);

        $data = array();
        $data["order_state"] = $this->orderManager->getOrderStateById(CrmConstants::ORDER_STATE_CANCELED);

        $this->orderManager->updateOrder($order, $data);

        $this->orderManager->dispatchOrderCanceled($order);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Order state updated')));
    }

    /**
     * @Route("/order/order_ready_for_pickup", name="order_ready_for_pickup")
     * @Method("POST")
     */
    public function orderReadyForPickupAction(Request $request)
    {
        $this->initialize();

        if (!isset($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order id is not defined')));
        }

        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderById($p["id"]);

        if ($order->getOrderStateId() != CrmConstants::ORDER_STATE_IN_PROCESS) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order is not in state sent to bookstore')));
        }

        $data = array();
        $data["order_state"] = $this->orderManager->getOrderStateById(CrmConstants::ORDER_STATE_READY_FOR_PICKUP);

        $this->orderManager->updateOrder($order, $data);

        $contact = $order->getContact();

        /** @var EmailTemplateManager $emailTemplateManager */
        $emailTemplateManager = $this->container->get('email_template_manager');
        /** @var EmailTemplateEntity $template */
        $template = $emailTemplateManager->getEmailTemplateByCode("order_ready_for_pickup");
        if (!empty($template)) {
            $emailTemplateManager->sendEmail(
                "order_ready_for_pickup",
                $order,
                $order->getStoreId(),
                ['email' => $contact->getEmail(), 'name' => $contact->getEmail()],
                null,
                null,
                null
            );
        } else {
            $orderStore = $this->routeManager->getStoreById($order->getStoreId());
            $orderWebsiteId = $orderStore->getWebsiteId();

            $this->mailManager->sendEmail(
                array('email' => $contact->getEmail(), 'name' => $contact->getEmail()),
                null,
                [],
                null,
                $this->translator->trans(
                    'Order ready for pickup'
                ) . " {$order->getIncrementId()} - {$order->getAccountName()}",
                "",
                "order_ready_for_pickup",
                array("order" => $order, "orderStoreId" => $order->getStoreId(), "orderWebsiteId" => $orderWebsiteId),
                null,
                [],
                $order->getStoreId()
            );
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Order state updated')));
    }

    /**
     * @Route("/order/send_to_erp", name="send_to_erp")
     * @Method("POST")
     */
    public function sendOrderToErpAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order id is not defined')));
        }

        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderById($p["id"]);

        if (!empty($order->getSentToErp())) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order already sent to ERP')));
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        try {
            $ret = $this->crmProcessManager->sendOrderWrapper($order);
            if (isset($ret["error"]) && $ret["error"]) {

                if (empty($this->errorLogManager)) {
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }
                $this->errorLogManager->logErrorEvent(sprintf("Error sending order %u to ERP: ", $order->getId()), null, true);

                if (isset($ret["message"]) && !empty($ret["message"])) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans($ret["message"])));
                } else {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error sending order to ERP. Support has been notified.')));
                }
            }
        } catch (\Exception $e) {

            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }
            $this->errorLogManager->logExceptionEvent(sprintf("Error sending order %u to ERP: ", $order->getId()), $e, true);
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error sending order to ERP. Support has been notified.')));
        }

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Send to ERP'), 'message' => $this->translator->trans('Order sent to ERP')));
    }

    /**
     * @Route("/order/keks_pay_details", name="keks_pay_details")
     * @Method("POST")
     */
    public function keksPayDetails(Request $request)
    {

        $this->initialize();

        $p = $_POST;
        $p = array_map('trim', $p);

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order item id is empty')));
        }

        if (empty($this->paymentTransactionManager)) {
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        /** @var PaymentTransactionEntity $paymentTransaction */
        $paymentTransaction = $this->paymentTransactionManager->getPaymentTransactionByOrderId($p["id"]);

        if (empty($paymentTransaction)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Payment transaction missing')));
        }

        $data["error"] = false;
        $data["multiple"] = false;
        $data["payment_transaction"] = $paymentTransaction;

        $html = $this->renderView('CrmBusinessBundle:Includes:keks_pay_details_modal.html.twig', array("data" => $data));

        $html = $this->renderView('AppBundle:Includes:modal.html.twig', array("html" => $html, "title" => $this->translator->trans("KeksPay details")));
        if (empty($html)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error opening modal')));
        }

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/order/keks_pay_refund", name="keks_pay_refund")
     * @Method("POST")
     */
    public function keksPayDetailsRefund()
    {

        $this->initialize();

        $p = $_POST;
        $p = array_map('trim', $p);

        if (!isset($p["order_id"]) || empty($p["order_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order id missing')));
        }

        if (!isset($p["payment_transaction_id"]) || empty($p["payment_transaction_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Payment transaction id missing')));
        }

        if (!isset($p["amount_to_refund"]) || empty($p["amount_to_refund"]) || NumberHelper::cleanDecimal($p["amount_to_refund"]) <= 0) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Amount to refund is missing')));
        }

        if (empty($this->paymentTransactionManager)) {
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        /** @var PaymentTransactionEntity $paymentTransaction */
        $paymentTransaction = $this->paymentTransactionManager->getPaymentTransactionById($p["payment_transaction_id"]);

        if (empty($paymentTransaction)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Payment transaction missing')));
        }

        $totalAmount = floatval($paymentTransaction->getAmount());
        $refundedAmount = floatval($paymentTransaction->getRefundAmount());
        $refund = NumberHelper::cleanDecimal($p["amount_to_refund"]);

        if ($refund > ($totalAmount - $refundedAmount)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Refund amount to large')));
        }

        if (empty($this->keksPayProvider)) {
            $this->keksPayProvider = $this->getContainer()->get("kekspay_provider");
        }

        try {
            $this->keksPayProvider->refundTransaction($paymentTransaction, $refund);
        } catch (\Exception $e) {
            throw new \Exception("Unable to refund transaction for order id: {$paymentTransaction->getOrderId()} - {$e->getMessage()}");
        }

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Refunded' . " " . $refund . " " . $paymentTransaction->getCurrency()->getSign())));
    }

    /**
     * @Route("/order/order_item_change", name="order_item_change")
     * @Method("POST")
     */
    public function orderItemChangeModal(){

        $this->initialize();

        $p = $_POST;
        $p = array_map('trim', $p);

        if(!isset($p["order_item_id"]) || empty($p["order_item_id"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order item id missing')));
        }

        /** @var OrderItemEntity $orderItem */
        $orderItem = $this->orderManager->getOrderItemById($p["order_item_id"]);

        if(empty($orderItem)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order item does not exist')));
        }

        $data["error"] = false;
        $data["multiple"] = false;
        $data["order_item"] = $orderItem;
        $data["calculation_type"] = strtolower($orderItem->getCalculationType());

        $data["start_price"] = floatval($orderItem->getOriginalBasePriceItem());
        $data["final_item_price"] = floatval($orderItem->getBasePriceItem());
        if($data["calculation_type"] == "vpc"){
            $data["start_price"] = floatval($orderItem->getOriginalBasePriceItemWithoutTax());
            $data["final_item_price"] = floatval($orderItem->getBasePriceItemWithoutTax());
        }
        if(floatval($orderItem->getBasePriceFixedDiscount()) > 0){
            $data["start_price"] = floatval($orderItem->getBasePriceFixedDiscount());
        }

        if(empty($this->templateManager)){
            $this->templateManager = $this->getContainer()->get("template_manager");
        }

        $html = $this->renderView($this->templateManager->getTemplatePathByBundle("Order:order_item_modal.html.twig", $_ENV["DEFAULT_WEBSITE_ID"]), array(
            'data' => $data
        ));

        $html = $this->renderView('AppBundle:Includes:modal.html.twig', Array("html" => $html, "title" => $this->translator->trans("Update order item prices")));
        if (empty($html)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error opening modal')));
        }

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/order/order_item_change_save", name="order_item_change_save")
     * @Method("POST")
     */
    public function orderItemChangeModalSave(){

        $this->initialize();

        $p = $_POST;
        $p = array_map('trim', $p);

        if(!isset($p["id"]) || empty($p["id"])){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order item id missing')));
        }

        /** @var OrderItemEntity $orderItem */
        $orderItem = $this->orderManager->getOrderItemById($p["id"]);

        if(empty($orderItem)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Order item does not exist')));
        }

        $p["qty"] = NumberHelper::cleanDecimal($p["qty"]);

        if(empty($this->crmProcessManager)){
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $p["percentage_discount_fixed"] = floatval(NumberHelper::cleanDecimal($p["percentage_discount_fixed"]));

        $p["base_price_fixed_discount"] = floatval(NumberHelper::cleanDecimal($p["base_price_fixed_discount"]));

        $account = $orderItem->getOrder()->getAccount();
        $parentProduct = null;
        if(!empty($orderItem->getParentItem())){
            $parentProduct = $orderItem->getParentItem()->getProduct();
        }

        $baseMethod = $orderItem->getOrder()->getCalculationType();
        $getPricesMethod = "getProductPrices".$baseMethod;

        $prices = json_decode($orderItem->getCalculationPrices(),true);
        if(empty($prices)){
            if(empty($this->calculationProvider)){
                $this->calculationProvider = $this->getContainer()->get($_ENV["CALCULATION_PROVIDER"]);
            }

            $prices = $this->calculationProvider->{$getPricesMethod}($orderItem->getProduct(), $account, $parentProduct, false);
        }

        if(floatval($prices["price"]) == floatval($p["base_price_fixed_discount"])){
            $p["base_price_fixed_discount"] = 0;
        }

        $this->orderManager->createUpdateOrderItem($p,$orderItem,false);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Order item updated')));
    }
}
