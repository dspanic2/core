<?php

namespace CrmBusinessBundle\Events;

use AppBundle\Abstracts\AbstractEntity;
use CrmBusinessBundle\Entity\PaymentEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use Symfony\Component\EventDispatcher\Event;

class PaymentCreatedEvent extends Event
{

    const NAME = 'payment.created';

    private $payment;

    /**
     * PaymentCreatedEvent constructor.
     * @param $payment
     */
    public function __construct($payment)
    {
        $this->payment = $payment;
    }

    /**
     * @return PaymentEntity
     */
    public function getPayment()
    {
        return $this->payment;
    }

    /**
     * @param PaymentEntity $payment
     */
    public function setPayment($payment)
    {
        $this->payment = $payment;
    }
}
