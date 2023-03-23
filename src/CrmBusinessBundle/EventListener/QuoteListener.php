<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\CoreUserRoleLinkEntity;
use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderStateEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteStatusEntity;
use CrmBusinessBundle\Events\QuoteAcceptedEvent;
use CrmBusinessBundle\Events\QuoteCanceledEvent;
use CrmBusinessBundle\Events\QuoteSentEvent;
use CrmBusinessBundle\Events\QuoteViewedEvent;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\OrderManager;
use CrmBusinessBundle\Managers\QuoteManager;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Translation\Translator;

class QuoteListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var Translator $translator */
    protected $translator;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;

    protected $container;
    protected $logger;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param QuoteViewedEvent $event
     * @throws \Exception
     */
    public function onQuoteViewed(QuoteViewedEvent $event)
    {
        /** @var QuoteEntity $quote */
        $quote = $event->getQuote();

        if (empty($this->translator)) {
            $this->translator = $this->container->get("translator");
        }

        $description = $this->translator->trans("Quote viewed");

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        $this->quoteManager->generateQuoteActivity($quote, $description);
    }

    /**
     * @param EntityCreatedEvent $event
     * @throws \Exception
     */
    public function onQuoteCreated(EntityCreatedEvent $event)
    {
        /** @var QuoteEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "quote") {
            if (empty($this->quoteManager)) {
                $this->quoteManager = $this->container->get("quote_manager");
            }

            /**
             * @deprecated
             * Obrisati kada budemo psiholoski spremni
             */
            /*if (empty($entity->getIncrementId())) {
                $incrementId = $this->quoteManager->getNextIncrementId();
                $entity->setIncrementId($incrementId);
            }*/

            if (empty($entity->getPreviewHash())) {
                $hash = StringHelper::generateHash($entity->getId(), time());
                $entity->setPreviewHash($hash);
            }

            if (empty($entity->getCurrencyRate())) {
                if ($this->container->getParameter('default_currency') == $entity->getCurrency()->getId()) {
                    $entity->setCurrencyRate(1);
                } else {
                    $exchangeRate = $this->quoteManager->getExchangeRateForCurrency($entity->getCurrency());
                    $entity->setCurrencyRate($exchangeRate);
                }
            }

            if (empty($entity->getStore())) {

                if (empty($this->routeManager)) {
                    $this->routeManager = $this->container->get("route_manager");
                }

                /** @var SStoreEntity $store */
                $store = $this->routeManager->getStoreById($_ENV["DEFAULT_STORE_ID"]);

                $entity->setStore($store);
            }

            if (empty($entity->getIpAddress())) {
                $clientIp = $_SERVER["HTTP_X_REAL_IP"] ?? null;
                $entity->setIpAddress($clientIp);
            }

            /**
             * Set quote date
             */
            $now = new \DateTime();
            $entity->setQuoteDate($now);

            /**
             * Set quote status
             */

            /** @var QuoteStatusEntity $status */
            $status = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_NEW);
            $entity->setQuoteStatus($status);

            /** @var AccountEntity $account */
            $account = $entity->getAccount();

            if (!empty($account)) {
                /**
                 * Set quote name if empty
                 */
                if (empty($entity->getName())) {
                    $name = $account->getName() . " " . $now->format("d.m.Y.");
                    $entity->setName($name);
                }

                /**
                 * Set quote email and phone if empty
                 */
                if (empty($entity->getAccountEmail())) {
                    $entity->setAccountEmail($account->getEmail());
                }

                if (empty($entity->getAccountPhone())) {
                    $entity->setAccountPhone($account->getPhone());
                }

                if (empty($entity->getAccountName())) {
                    $entity->setAccountName($account->getName());
                }

                if (empty($entity->getAccountOib())) {
                    $entity->setAccountOib($account->getOib());
                }

                if (empty($entity->getContact())) {
                    $entity->setContact($account->getDefaultContact());
                }
            }

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->saveEntityWithoutLog($entity);
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onQuotePreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var QuoteEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "quote") {

            if (!empty($entity->getAccount()) && empty($entity->getContact())) {
                $entity->setContact($entity->getAccount()->getDefaultContact());
            }

        }
    }

    /**
     * @param EntityUpdatedEvent $event
     * @throws \Exception
     */
    public function onQuoteUpdated(EntityUpdatedEvent $event)
    {
        /** @var QuoteEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "quote") {

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            if(empty($this->calculationProvider)){
                $this->calculationProvider = $this->container->get($_ENV["CALCULATION_PROVIDER"]);
            }

            $this->calculationProvider->recalculateQuoteTotals($entity);

            $changed = false;

            if (!empty($entity->getAccountBillingAddress())) {
                /** @var AddressEntity $address */
                $address = $entity->getAccountBillingAddress();

                if ($address->getStreet() != $entity->getAccountBillingStreet()) {
                    $entity->setAccountBillingStreet($address->getStreet());
                    $changed = true;
                }
                if ($address->getCityId() != $entity->getAccountBillingCityId()) {
                    $entity->setAccountBillingCity($address->getCity());
                    $changed = true;
                }
            }

            if (!empty($entity->getAccountShippingAddress())) {
                /** @var AddressEntity $address */
                $address = $entity->getAccountShippingAddress();

                if ($address->getStreet() != $entity->getAccountShippingStreet()) {
                    $entity->setAccountShippingStreet($address->getStreet());
                    $changed = true;
                }
                if ($address->getCityId() != $entity->getAccountShippingCityId()) {
                    $entity->setAccountShippingCity($address->getCity());
                    $changed = true;
                }
            }

            if ($changed) {
                $this->entityManager->saveEntityWithoutLog($entity);
            }

            $this->entityManager->refreshEntity($entity);

            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $this->crmProcessManager->calculateQuoteDeliveryPrice($entity);
        }
    }

    /**
     * @param QuoteAcceptedEvent $event
     * @throws \Exception
     */
    public function onQuoteAccepted(QuoteAcceptedEvent $event)
    {
        /** @var QuoteEntity $quote */
        $quote = $event->getQuote();

        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->generateOrderFromQuote($quote, CrmConstants::ORDER_STATE_NEW);

        if (empty($this->translator)) {
            $this->translator = $this->container->get("translator");
        }

        $description = $this->translator->trans("Quote accepted");

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        $this->quoteManager->generateQuoteActivity($quote, $description);

        /** @var QuoteStatusEntity $status */
        $status = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_ACCEPTED);
        $quote->setQuoteStatus($status);

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $this->entityManager->saveEntityWithoutLog($quote);
    }

    /**
     * @param QuoteCanceledEvent $event
     * @throws \Exception
     */
    public function onQuoteCanceled(QuoteCanceledEvent $event)
    {
        /** @var QuoteEntity $quote */
        $quote = $event->getQuote();

        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        /** @var OrderEntity $order */
        $order = $this->orderManager->getOrderByQuoteId($quote->getId());
        if ($order) {

            /** @var OrderStateEntity $state */
            $state = $this->orderManager->getOrderStateById(CrmConstants::ORDER_STATE_CANCELED);

            $order->setOrderState($state);

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->saveEntityWithoutLog($order);
        }

        if (empty($this->translator)) {
            $this->translator = $this->container->get("translator");
        }

        $description = $this->translator->trans("Quote canceled");

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        $this->quoteManager->generateQuoteActivity($quote, $description);
    }

    /**
     * @param QuoteSentEvent $event
     * @throws \Exception
     */
    public function onQuoteSent(QuoteSentEvent $event)
    {
        /** @var QuoteEntity $entity */
        $entity = $event->getQuote();

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        /** @var QuoteStatusEntity $status */
        $status = $this->quoteManager->getQuoteStatusById(CrmConstants::QUOTE_STATUS_WAITING_FOR_CLIENT);

        $entity->setLastContactDate(new \DateTime());
        $entity->setQuoteStatus($status);

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $this->entityManager->saveEntityWithoutLog($entity);
    }
}
