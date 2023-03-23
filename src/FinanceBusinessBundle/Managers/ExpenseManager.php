<?php

namespace FinanceBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\EntityManager;
use FinanceBusinessBundle\Entity\ExpenseEntity;
use FinanceBusinessBundle\Entity\ExpenseItemEntity;
use FinanceBusinessBundle\Entity\OutboundPaymentEntity;
use JMS\Serializer\Tests\Fixtures\Order;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Constraints\DateTime;


class ExpenseManager extends AbstractBaseManager
{

    protected $translator;
    /** @var  EntityManager $entityManager */
    protected $entityManager;
    /** @var  HelperManager $helperManager */
    protected $helperManager;
    protected $twig;


    public function initialize()
    {
        $this->twig = $this->container->get("templating");
        $this->translator = $this->container->get("translator");
        $this->entityManager = $this->container->get("entity_manager");
        $this->helperManager = $this->container->get("helper_manager");
    }

    /**
     * @return int
     */
    public function getBaseCurrencyId(){

        $baseCurrencyId = 1;

        return $baseCurrencyId;
    }

    /**
     * @param ExpenseEntity $expense
     * @return int
     */
    public function getCurrencyRate($expense, $date){

        $currencyRate = 1;
        $baseCurrencyId = $this->getBaseCurrencyId();

        if($expense->getCurrency()->getId() != $baseCurrencyId){
            $currencyOnDate = $this->helperManager->getHNBCurrencyForDate($date,$expense->getCurrency()->getCode());

            if(empty($currencyOnDate)){
                return null;
            }

            $currencyRate = $currencyOnDate[0]["Srednji za devize"];
            $currencyRate = str_replace(",",".",$currencyRate);
        }

        return $currencyRate;
    }

    /**
     * @param OutboundPaymentEntity $outboundPayment
     * @return bool|OutboundPaymentEntity
     */
    public function recalculateOutboundPayment(OutboundPaymentEntity $outboundPayment){

        $currencyRate = floatval($outboundPayment->getCurrencyRate());
        if($currencyRate == 0){
            return false;
        }

        $priceTotal = floatval($outboundPayment->getPriceTotal());

        $basePriceTotal = $priceTotal*$currencyRate;

        $outboundPayment->setBasePriceTotal($basePriceTotal);

        $this->entityManager->saveEntityWithoutLog($outboundPayment);

        return $outboundPayment;
    }

    /**
     * @param ExpenseItemEntity $expenseItem
     * @return bool|ExpenseItemEntity
     */
    public function recalculateExpenseItem(ExpenseItemEntity $expenseItem){

        $currencyRate = floatval($expenseItem->getExpense()->getCurrencyRate());
        if($currencyRate == 0){
            return false;
        }

        $priceTotal = floatval($expenseItem->getPriceTotal());

        $basePriceTotal = $priceTotal*$currencyRate;

        $taxPercent = $expenseItem->getTaxType()->getPercent();
        $taxPercent = floatval($taxPercent)/100;
        $wTaxPercent = 1 + $taxPercent;

        $expenseItem->setPriceWithoutTax($priceTotal/$wTaxPercent);
        $expenseItem->setPriceTax($priceTotal - $priceTotal/$wTaxPercent);
        $expenseItem->setBasePriceTotal($basePriceTotal);
        $expenseItem->setBasePriceTax($basePriceTotal - $basePriceTotal/$wTaxPercent);
        $expenseItem->setBasePriceWithoutTax($basePriceTotal/$wTaxPercent);

        $this->entityManager->saveEntityWithoutLog($expenseItem);

        return $expenseItem;
    }

    /**
     * @param ExpenseEntity $expense
     * @return bool
     */
    public function recalculateExpensePaid(ExpenseEntity $expense){

        $totalPaid = 0;

        $items = $this->getOutboundPaymentsForExpense($expense);

        if(!empty($items)){
            /** @var OutboundPaymentEntity $item */
            foreach ($items as $item){
                $totalPaid = $totalPaid + floatval($item->getBasePriceTotal());
            }
        }

        $totalLeftToPay = floatval($expense->getBasePriceTotal()) - $totalPaid;

        $expense->setTotalLeftToPay($totalLeftToPay);
        $expense->setTotalPaid($totalPaid);

        $expense->setIsOverpaid(0);
        $expense->setIsPaid(0);

        if($totalLeftToPay <= 0){
            $expense->setIsPaid(1);
            if($totalLeftToPay < 0){
                $expense->setIsOverpaid(1);
            }
        }

        $this->entityManager->saveEntityWithoutLog($expense);

        return true;
    }

    /**
     * @param ExpenseEntity $expense
     * @return bool
     */
    public function recalculateExpense(ExpenseEntity $expense){

        $priceTotal = 0;
        $priceTax = 0;
        $priceWithoutTax = 0;
        $basePriceTotal = 0;
        $basePriceTax = 0;
        $basePriceWithoutTax = 0;

        $items = $this->getExpenseItemsForExpense($expense);

        if(!empty($items)){
            /** @var ExpenseItemEntity $item */
            foreach ($items as $item){

                $priceTotal = $priceTotal + floatval($item->getPriceTotal());
                $priceTax = $priceTax + floatval($item->getPriceTax());
                $priceWithoutTax = $priceWithoutTax + floatval($item->getPriceWithoutTax());

                $basePriceTotal = $basePriceTotal + floatval($item->getBasePriceTotal());
                $basePriceTax = $basePriceTax + floatval($item->getBasePriceTax());
                $basePriceWithoutTax = $basePriceWithoutTax + floatval($item->getBasePriceWithoutTax());
            }
        }

        $expense->setPriceTotal($priceTotal);
        $expense->setPriceTax($priceTax);
        $expense->setPriceWithoutTax($priceWithoutTax);
        $expense->setBasePriceTotal($basePriceTotal);
        $expense->setBasePriceTax($basePriceTax);
        $expense->setBasePriceWithoutTax($basePriceWithoutTax);
        $expense->setHasError(0);
        $expense->setCronRecalculate(1);

        $this->entityManager->saveEntityWithoutLog($expense);

        return true;
    }

    /**
     * @param ExpenseEntity $expense
     * @return mixed
     */
    public function getExpenseItemsForExpense(ExpenseEntity $expense){

        $expenseItemEntityType = $this->entityManager->getEntityTypeByCode("expense_item");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("expense", "eq", $expense->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($expenseItemEntityType, $compositeFilters);
    }

    /**
     * @param ExpenseEntity $expense
     * @return mixed
     */
    public function getOutboundPaymentsForExpense(ExpenseEntity $expense){

        $expenseItemEntityType = $this->entityManager->getEntityTypeByCode("outbound_payment");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("expense", "eq", $expense->getId()));
        $compositeFilter->addFilter(new SearchFilter("hasError", "eq", 0));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($expenseItemEntityType, $compositeFilters);
    }

    /**
     * @param ExpenseEntity $expense
     * @return bool
     */
    public function setErrorOnExpense(ExpenseEntity $expense){

        $expense->getCronRecalculate(1);
        $expense->setHasError(1);

        $this->entityManager->saveEntityWithoutLog($expense);

        return true;
    }

    /**
     * @param ExpenseEntity $expense
     * @return bool
     */
    public function recalculateAllExpenseItemsOnExpense(ExpenseEntity $expense){

        $items = $this->getExpenseItemsForExpense($expense);

        if(!empty($items)){

            /** @var ExpenseItemEntity $item */
            foreach ($items as $item){
                $this->recalculateExpenseItem($item);
            }
        }

        return true;
    }

}