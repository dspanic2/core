<?php

namespace PaymentProvidersBusinessBundle\Interfaces;

use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\QuoteEntity;

interface PaymentProviderInterface
{
    public function renderTemplateFromQuote(QuoteEntity $quote);

    public function renderTemplateFromOrder(OrderEntity $order);
}