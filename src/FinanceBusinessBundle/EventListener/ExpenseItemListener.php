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
use FinanceBusinessBundle\Entity\ExpenseItemEntity;
use FinanceBusinessBundle\Managers\ExpenseManager;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


class ExpenseItemListener implements ContainerAwareInterface
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

    public function onExpenseItemCreated(EntityCreatedEvent $event)
    {
        /** @var ExpenseItemEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "expense_item"){

            $success = $this->expenseManager->recalculateExpenseItem($entity);
            if(empty($success)){
                $this->expenseManager->setErrorOnExpense($entity->getExpense());
                return false;
            }

            $this->expenseManager->recalculateExpense($entity->getExpense());
        }
    }

    public function onExpenseItemUpdated(EntityUpdatedEvent $event)
    {
        /** @var ExpenseItemEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "expense_item") {

            $success = $this->expenseManager->recalculateExpenseItem($entity);
            if(empty($success)){
                $this->expenseManager->setErrorOnExpense($entity->getExpense());
                return false;
            }

            $this->expenseManager->recalculateExpense($entity->getExpense());
        }
    }

    public function onExpenseItemDeleted(EntityDeletedEvent $event)
    {
        /** @var ExpenseItemEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "expense_item") {

            $success = $this->expenseManager->recalculateExpenseItem($entity);
            if(empty($success)){
                $this->expenseManager->setErrorOnExpense($entity->getExpense());
                return false;
            }

            $this->expenseManager->recalculateExpense($entity->getExpense());
        }
    }

}