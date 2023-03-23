<?php

namespace CrmBusinessBundle\Events;

use AppBundle\Abstracts\AbstractEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use Symfony\Component\EventDispatcher\Event;

class QuoteAcceptedEvent extends Event
{

    const NAME = 'quote.accepted';

    private $quote;

    /**
     * QuoteCreatedEvent constructor.
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
