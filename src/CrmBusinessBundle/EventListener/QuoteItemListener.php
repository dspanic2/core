<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\CalculationProviders\DefaultCalculationProvider;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;
use CrmBusinessBundle\Managers\QuoteManager;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class QuoteItemListener implements ContainerAwareInterface
{
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;
    /** @var DefaultCalculationProvider $calculationProvider */
    protected $calculationProvider;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @throws \Exception
     */
    public function onQuoteItemPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var QuoteItemEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "quote_item") {
            if (empty($entity->getTaxType())) {
                $entity->setTaxType($entity->getProduct()->getTaxType());
            }

            if (empty($entity->getName())) {

                if(empty($this->getPageUrlExtension)){
                    $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
                }

                $session = $this->container->get("session");

                $entity->setName($this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"),$entity->getProduct(),"name"));
            }

            if (empty($entity->getCode())) {
                $entity->setCode($entity->getProduct()->getCode());
            }
        }
    }

    /**
     * @param EntityCreatedEvent $event
     * @throws \Exception
     */
    public function onQuoteItemCreated(EntityCreatedEvent $event)
    {
        /** @var QuoteItemEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "quote_item") {

            $currencyRate = $entity->getQuote()->getCurrencyRate();

            if(empty($this->calculationProvider)){
                $this->calculationProvider = $this->container->get($_ENV["CALCULATION_PROVIDER"]);
            }

            $entity = $this->calculationProvider->calculatePriceItem($entity, $currencyRate);

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->saveEntityWithoutLog($entity);

            $this->entityManager->refreshEntity($entity);
            $this->entityManager->refreshEntity($entity->getQuote());

            $this->calculationProvider->recalculateQuoteTotals($entity->getQuote());
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onQuoteItemPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var QuoteItemEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "quote_item") {
            if (empty($entity->getTaxType())) {
                $entity->setTaxType($entity->getProduct()->getTaxType());
            }

            if (empty($entity->getName())) {
                if(empty($this->getPageUrlExtension)){
                    $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
                }

                $session = $this->container->get("session");

                $entity->setName($this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"),$entity->getProduct(),"name"));
            }

            if (empty($entity->getCode())) {
                $entity->setCode($entity->getProduct()->getCode());
            }
        }
    }

    /**
     * @param EntityUpdatedEvent $event
     */
    public function onQuoteItemUpdated(EntityUpdatedEvent $event)
    {
        /** @var QuoteItemEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "quote_item") {

            $currencyRate = $entity->getQuote()->getCurrencyRate();

            if(empty($this->calculationProvider)){
                $this->calculationProvider = $this->container->get($_ENV["CALCULATION_PROVIDER"]);
            }

            if(empty($this->crmProcessManager)){
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            /**
             * Ovo sam ostavio ako ce ista trebati vuci iz POST, da ne zaboravim kako sam to dovukao
             */
            //$data = $event->getData();
            $entity = $this->calculationProvider->calculatePriceItem($entity, $currencyRate);

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->saveEntityWithoutLog($entity);

            $this->entityManager->refreshEntity($entity);

            $this->calculationProvider->recalculateQuoteTotals($entity->getQuote());
        }
    }

    /**
     * @param EntityDeletedEvent $event
     */
    public function onQuoteItemDeleted(EntityDeletedEvent $event)
    {
        /** @var QuoteItemEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "quote_item") {

            $quote = $entity->getQuote();

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->deleteEntityFromDatabase($entity);

            $this->entityManager->refreshEntity($quote);

            if(empty($this->calculationProvider)){
                $this->calculationProvider = $this->container->get($_ENV["CALCULATION_PROVIDER"]);
            }

            $this->calculationProvider->recalculateQuoteTotals($quote);
        }
    }
}
