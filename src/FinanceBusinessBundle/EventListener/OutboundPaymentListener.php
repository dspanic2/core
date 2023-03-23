<?php


namespace FinanceBusinessBundle\EventListener;

use AppBundle\Context\EntityContext;
use AppBundle\Context\EntityLinkContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\EntityLogContext;
use AppBundle\Entity\UserEntity;
use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Factory\FactoryContext;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use FinanceBusinessBundle\Entity\ExpenseEntity;
use FinanceBusinessBundle\Entity\OutboundPaymentEntity;
use FinanceBusinessBundle\Managers\ExpenseManager;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


class OutboundPaymentListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var ExpenseManager $expenseManager */
    protected $expenseManager;


    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
        $this->entityManager = $this->container->get("entity_manager");
        $this->helperManager = $this->container->get("helper_manager");
        $this->expenseManager = $this->container->get("expense_manager");
    }

    public function onOutboundPaymentPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var OutboundPaymentEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "outbound_payment"){

            /** Set currency rate automaticaly */
            if($entity->getManualCurrencyRate() == 0){
                if($entity->getCurrencyRate() == 0 && !empty($entity->getPaymentDate()) && !empty($entity->getCurrency())){
                    $currencyRate = $this->expenseManager->getCurrencyRate($entity,$entity->getPaymentDate()->format("Y-m-d"));
                    if(!empty($currencyRate)){
                        $entity->setCurrencyRate($currencyRate);
                    }
                }
            }

            $entity->setHasError(0);
            $entity->setCurrencyChanged(0);
            if($entity->getCurrencyRate() == 0){
                $entity->setCurrencyChanged(1);
                $entity->setHasError(1);
            }
        }
    }

    public function onOutboundPaymentCreated(EntityCreatedEvent $event){

        /** @var OutboundPaymentEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "outbound_payment"){
            if($entity->getHasError() == 0){
                $this->expenseManager->recalculateOutboundPayment($entity);
            }
            $this->expenseManager->recalculateExpensePaid($entity->getExpense());
        }
    }

    public function onOutboundPaymentPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var OutboundPaymentEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "outbound_payment") {

            /** Set currency rate automaticaly */
            if($entity->getManualCurrencyRate() == 0){
                /** If currency changed or empty */
                if(($entity->getCurrencyRate() == 0 || $entity->getCurrencyChanged() == 1) && !empty($entity->getPaymentDate()) && !empty($entity->getCurrency())){
                    $currencyRate = $this->expenseManager->getCurrencyRate($entity,$entity->getPaymentDate()->format("Y-m-d"));
                    if(!empty($currencyRate) && $entity->getCurrencyRate() != $currencyRate){
                        $entity->setCurrencyRate($currencyRate);
                    }
                }
            }

            $entity->setHasError(0);
            $entity->setCurrencyChanged(0);
            if($entity->getCurrencyRate() == 0){
                $entity->setCurrencyChanged(1);
                $entity->setHasError(1);
            }
        }
    }

    public function onOutboundPaymentUpdated(EntityUpdatedEvent $event){

        /** @var OutboundPaymentEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "outbound_payment"){

            if($entity->getHasError() == 0){
                $this->expenseManager->recalculateOutboundPayment($entity);
            }
            $this->expenseManager->recalculateExpensePaid($entity->getExpense());

        }
    }

    public function onOutboundPaymentDeleted(EntityDeletedEvent $event)
    {
        /** @var OutboundPaymentEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "outbound_payment") {

            $this->expenseManager->recalculateExpensePaid($entity->getExpense());

            $this->entityManager->saveEntityWithoutLog($entity);
        }
    }
}