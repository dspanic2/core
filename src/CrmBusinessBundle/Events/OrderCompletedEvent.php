<?php

namespace CrmBusinessBundle\Events;

use AppBundle\Abstracts\AbstractEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use Symfony\Component\EventDispatcher\Event;

class OrderCompletedEvent extends Event
{

    const NAME = 'order.completed';

    private $order;

    /**
     * QuoteStatusChangedEvent constructor.
     * @param $order
     */
    public function __construct($order)
    {
        $this->order = $order;
    }

    /**
     * @return OrderEntity
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param OrderEntity $order
     */
    public function setOrder($order)
    {
        $this->order = $order;
    }
}
