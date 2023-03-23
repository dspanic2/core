<?php

namespace AppBundle\Events;

use AppBundle\Entity\TransactionEmailEntity;
use Symfony\Component\EventDispatcher\Event;

class TransactionEmailSentEvent extends Event
{
    const NAME = 'transaction_email.sent';

    private $transactionEmail;
    private $sentMimeMessage;

    /**
     * TransactionEmailSentEvent constructor.
     * @param TransactionEmailEntity $transactionEmail
     * @param $sentMimeMessage
     */
    public function __construct(TransactionEmailEntity $transactionEmail, $sentMimeMessage)
    {
        $this->transactionEmail = $transactionEmail;
        $this->sentMimeMessage = $sentMimeMessage;
    }

    /**
     * @return TransactionEmailEntity
     */
    public function getTransactionEmail()
    {
        return $this->transactionEmail;
    }

    /**
     * @param TransactionEmailEntity $transactionEmail
     */
    public function setTransactionEmail(TransactionEmailEntity $transactionEmail)
    {
        $this->transactionEmail = $transactionEmail;
    }

    /**
     * @return mixed
     */
    public function getSentMimeMessage()
    {
        return $this->sentMimeMessage;
    }
}
