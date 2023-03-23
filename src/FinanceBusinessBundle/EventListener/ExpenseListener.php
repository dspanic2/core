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
use FinanceBusinessBundle\Managers\ExpenseManager;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


class ExpenseListener implements ContainerAwareInterface
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

    public function onExpensePreCreated(EntityPreCreatedEvent $event)
    {
        /** @var ExpenseEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "expense"){

            $entity->setIsPaid(0);
            $entity->setIsOverpaid(0);

            /** Set currency rate automaticaly */
            if($entity->getManualCurrencyRate() == 0){
                if($entity->getCurrencyRate() == 0 && !empty($entity->getPaymentDueDate()) && !empty($entity->getCurrency())){
                    $currencyRate = $this->expenseManager->getCurrencyRate($entity,$entity->getPaymentDueDate()->format("Y-m-d"));
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
            else{
                $this->expenseManager->recalculateAllExpenseItemsOnExpense($entity);
            }
        }
    }

    public function onExpenseCreated(EntityCreatedEvent $event){

        /** @var ExpenseEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "expense"){
            $this->expenseManager->recalculateExpense($entity);
            $this->expenseManager->recalculateExpensePaid($entity);
        }
    }

    public function onExpensePreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var ExpenseEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "expense") {

            /** Set currency rate automaticaly */
            if($entity->getManualCurrencyRate() == 0){
                /** If currency changed or empty */
                if(($entity->getCurrencyRate() == 0 || $entity->getCurrencyChanged() == 1) && !empty($entity->getPaymentDueDate()) && !empty($entity->getCurrency())){
                    $currencyRate = $this->expenseManager->getCurrencyRate($entity,$entity->getPaymentDueDate()->format("Y-m-d"));
                    if(!empty($currencyRate) && $entity->getCurrencyRate() != $currencyRate){
                        $entity->setCurrencyRate($currencyRate);
                        $this->expenseManager->recalculateAllExpenseItemsOnExpense($entity);
                    }
                }
            }
            elseif ($entity->getManualCurrencyRate() == 1 && $entity->getCurrencyChanged() == 1){
                if($entity->getCurrencyRate() > 0){
                    $this->expenseManager->recalculateAllExpenseItemsOnExpense($entity);
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

    public function onExpenseUpdated(EntityUpdatedEvent $event){

        /** @var ExpenseEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "expense"){

            $this->expenseManager->recalculateExpense($entity);
            $this->expenseManager->recalculateExpensePaid($entity);

        }
    }

    public function onExpenseDeleted(EntityDeletedEvent $event)
    {
        /** @var ExpenseEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "expense") {

            $entity->setCronRecalculate(1);

            $this->entityManager->saveEntityWithoutLog($entity);
        }
    }
}