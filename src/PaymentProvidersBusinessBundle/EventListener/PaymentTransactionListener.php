<?php

namespace PaymentProvidersBusinessBundle\EventListener;

use AppBundle\Managers\ErrorLogManager;
use AppBundle\Managers\MailManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Events\OrderCanceledEvent;
use CrmBusinessBundle\Events\OrderReversedEvent;
use CrmBusinessBundle\Managers\OrderManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PaymentTransactionListener implements ContainerAwareInterface
{
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;

    protected $translator;
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param $subject
     * @param $message
     */
    public function sendOrderEventEmail($subject, $message)
    {
        if (empty($this->errorLogManager)) {
            $this->errorLogManager = $this->container->get("error_log_manager");
        }

        $this->errorLogManager->logErrorEvent($subject, $message, true, null);
    }

    /**
     * @param OrderReversedEvent $event
     */
    public function onOrderReversed(OrderReversedEvent $event)
    {
        /** @var OrderEntity $order */
        $order = $event->getOrder();

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        /** @var PaymentTransactionEntity $paymentTransaction */
        $paymentTransaction = $this->paymentTransactionManager->getPaymentTransactionByOrderId($order->getId());
        if (!empty($paymentTransaction)) {
            $provider = $this->container->get($paymentTransaction->getProvider());
            if ($paymentTransaction->getTransactionStatusId() == CrmConstants::PAYMENT_TRANSACTION_STATUS_PREAUTHORISED) {
                $paymentTransaction = $provider->voidTransaction($paymentTransaction);
                if (empty($paymentTransaction)) {
                    if (empty($this->translator)) {
                        $this->translator = $this->container->get("translator");
                    }
                    $this->sendOrderEventEmail("Order reversed event", $this->translator->trans("Transaction cannot be canceled, please contact the payment provider"));
                }
            } else if ($paymentTransaction->getTransactionStatusId() == CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED) {
                $paymentTransaction = $provider->refundTransaction($paymentTransaction);
                if (empty($paymentTransaction)) {
                    if (empty($this->translator)) {
                        $this->translator = $this->container->get("translator");
                    }
                    $this->sendOrderEventEmail("Order reversed event", $this->translator->trans("Transaction cannot be refunded, please contact the payment provider"));
                }
            }
        }
    }

    /**
     * @param OrderCanceledEvent $event
     */
    public function onOrderCanceled(OrderCanceledEvent $event)
    {
        /** @var OrderEntity $order */
        $order = $event->getOrder();

        if(empty($this->paymentTransactionManager)){
            $this->paymentTransactionManager = $this->getContainer()->get("payment_transaction_manager");
        }

        /** @var PaymentTransactionEntity $paymentTransaction */
        $paymentTransaction = $this->paymentTransactionManager->getPaymentTransactionByOrderId($order->getId());
        if (!empty($paymentTransaction)) {
            $provider = $this->container->get($paymentTransaction->getProvider());
            if ($paymentTransaction->getTransactionStatusId() == CrmConstants::PAYMENT_TRANSACTION_STATUS_PREAUTHORISED) {
                $paymentTransaction = $provider->voidTransaction($paymentTransaction);
                if (empty($paymentTransaction)) {
                    if (empty($this->translator)) {
                        $this->translator = $this->container->get("translator");
                    }
                    $this->sendOrderEventEmail("Order canceled event", $this->translator->trans("Transaction cannot be canceled, please contact the payment provider"));
                }
            } else if ($paymentTransaction->getTransactionStatusId() == CrmConstants::PAYMENT_TRANSACTION_STATUS_COMPLETED) {
                $paymentTransaction = $provider->refundTransaction($paymentTransaction);
                if (empty($paymentTransaction)) {
                    if (empty($this->translator)) {
                        $this->translator = $this->container->get("translator");
                    }
                    $this->sendOrderEventEmail("Order canceled event", $this->translator->trans("Transaction cannot be refunded, please contact the payment provider"));
                }
            }
        }
    }
}
