<?php

namespace PaymentProvidersBusinessBundle\Abstracts;

use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ErrorLogManager;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\OrderManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AbstractPaymentProvider implements ContainerAwareInterface
{
    protected $container;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    protected $twig;
    protected $router;
    protected $translator;
    protected $logger;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param PaymentTypeEntity $paymentType
     * @return mixed
     * @throws \Exception
     */
    public function getConfig(PaymentTypeEntity $paymentType){

        if (empty($this->errorLogManager)) {
            $this->errorLogManager = $this->getContainer()->get("error_log_manager");
        }

        $config = json_decode($paymentType->getConfiguration(), true);
        if (empty($config)) {
            $this->errorLogManager->logErrorEvent("Missing configuration for {$paymentType->getProvider()} payment provider", "Missing configuration for {$paymentType->getProvider()} payment provider", true);
            throw new \Exception("Missing configuration for {$paymentType->getProvider()} payment provider");
        }

        return $config;
    }

    /**
     * Override this method to initialize all services you will require
     */
    public function initialize()
    {
        $this->translator = $this->getContainer()->get("translator");
        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->orderManager = $this->getContainer()->get("order_manager");
        $this->twig = $this->getContainer()->get("templating");
        $this->router = $this->getContainer()->get("router");
        $this->logger = $this->getContainer()->get("logger");
    }

    public function renderTemplateFromOrder(OrderEntity $order)
    {
        return null;
    }

    public function renderTemplateFromQuote(QuoteEntity $quote)
    {
        return null;
    }

    public function voidTransaction(PaymentTransactionEntity $paymentTransaction)
    {
        return true;
    }

    public function refundTransaction(PaymentTransactionEntity $paymentTransaction)
    {
        return true;
    }
}