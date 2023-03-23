<?php

namespace CrmBusinessBundle\Events;

use AppBundle\Abstracts\AbstractEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use Symfony\Component\EventDispatcher\Event;

class QuoteSentEvent extends Event
{

    const NAME = 'quote.sent';

    private $quote;

    /**
     * QuoteSentEvent constructor.
     * @param $quote
     */
    public function __construct($quote)
    {
        $this->quote = $quote;
    }

    /**
     * @return QuoteEntity
     */
    public function getQuote()
    {
        return $this->quote;
    }

    /**
     * @param QuoteEntity $quote
     */
    public function setQuote($quote)
    {
        $this->quote = $quote;
    }
}
