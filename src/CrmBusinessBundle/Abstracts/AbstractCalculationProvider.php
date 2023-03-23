<?php

namespace CrmBusinessBundle\Abstracts;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\DiscountCouponEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;

class AbstractCalculationProvider extends AbstractBaseManager {

    /** @var EntityManager $entityManager */
    protected $entityManager;

    public function initialize()
    {
        parent::initialize();

        $this->entityManager = $this->getContainer()->get("entity_manager");
    }

    /**
     * @param QuoteItemEntity $entity
     * @param int $currencyRate
     * @return QuoteItemEntity
     * @throws \Exception
     */
    public function calculatePriceItem(QuoteItemEntity $entity, $currencyRate = CrmConstants::DEFAULT_CURRENCY_RATE_ID)
    {
        if (empty(floatval($currencyRate))) {
            $currencyRate = 1;
        }

        if (in_array($entity->getProduct()->getProductTypeId(), array(CrmConstants::PRODUCT_TYPE_CONFIGURABLE)) && empty($entity->getParentItem())) {
            return $entity;
        }

        $taxPercent = $entity->getProduct()->getTaxType()->getPercent();
        $discountBasePriceItem = 0;
        $discountCouponBasePriceItem = 0;
        $discountCouponPercent = 0;
        $appliedDiscountCouponPercent = 0;
        $appliedDiscountPercent = 0;

        $discountLoyaltyPercent = 0;
        if (!empty($entity->getQuote()->getLoyaltyCard()) && !$entity->getProduct()->getDisableLoyaltyDiscount()) {
            $discountLoyaltyPercent = floatval($entity->getQuote()->getLoyaltyCard()->getPercentDiscount());
        }
        $appliedDiscountLoyaltyPercent = 0;

        $account = null;
        if (!empty($entity->getQuote()->getAccount())) {
            $account = $entity->getQuote()->getAccount();
        }

        /** @var DiscountCouponEntity $discountCoupon */
        $discountCoupon = $entity->getQuote()->getDiscountCoupon();

        $parentProduct = null;
        if (!empty($entity->getParentItem())) {
            $parentProduct = $entity->getParentItem()->getProduct();
        }

        $prices = $this->getProductPrices($entity->getProduct(), $account, $parentProduct);

        if (!empty($discountCoupon)) {
            $discountCouponPercent = $this->getApplicableDiscountCouponPercentForProduct($discountCoupon, $entity->getProduct(), $parentProduct, $account, $prices, $entity->getQuote()->getBasePriceItemsTotal());
            /**
             * @deprecated
             */
            /*if ($this->isProductOnDiscountCoupon($discountCoupon, $entity, $account, $prices)) {
                $discountCouponPercent = $this->getDiscountCouponPercent($discountCoupon, $entity->getQuote());
            }*/
        }

        /**
         * OVDJE SE RIJESAVA PITANJE POPUSTA ZA KES I SL
         */
        if (floatval($prices["discount_price_base_currency"]) > 0) {
            $basePriceItemWithoutTax = floatval($prices["discount_price_base_currency"]);
            $discountBasePriceItem = floatval($prices["price_retail_currency"]) - floatval($prices["discount_price_retail_currency"]);
            $appliedDiscountPercent = floatval($prices["discount_percentage"]);
        } else {
            $basePriceItemWithoutTax = floatval($prices["price_base_currency"]);
        }

        /**
         * Set admin discount percentage
         */
        if (floatval($entity->getPercentageDiscountFixed()) > 0) {
            $basePriceItemWithoutTax = floatval($prices["price_base_currency"]) - (floatval($prices["price_base_currency"]) * $entity->getPercentageDiscountFixed() / 100);
            $discountBasePriceItem = (floatval($prices["price_base_currency"]) * $entity->getPercentageDiscountFixed() / 100) * $taxPercent / 100;
            $appliedDiscountPercent = $entity->getPercentageDiscountFixed();
        }

        /**
         * Check bulk price, do not applay if discount exists
         */
        if (isset($prices["bulk_prices"]) && $discountBasePriceItem == 0) {

            /**
             * For 1+1 free
             */
            if (isset($prices["bulk_prices"][0]["bulk_price_type"]) && $prices["bulk_prices"][0]["bulk_price_type"] == 2) {
                $bulk_price = $prices["bulk_prices"][0];

                if ($entity->getQty() >= floatval($bulk_price["min_qty"])) {

                    $step = floatval($bulk_price["min_qty"]);
                    $times = floor($entity->getQty() / $step);
                    $totalBasePriceWithDiscount = intval($times) * $step * $bulk_price["bulk_price_base_item"] + ($entity->getQty() - (intval($times) * $step)) * $basePriceItemWithoutTax;

                    $basePriceItemWithoutTax = $totalBasePriceWithDiscount / $entity->getQty();
                    $discountBasePriceItem = $prices["price_base_currency"] - $basePriceItemWithoutTax;
                    $appliedDiscountPercent = $discountBasePriceItem / $prices["price_base_currency"] * 100;
                }
            } /**
             * For standard bulk price items
             */
            else {
                foreach ($prices["bulk_prices"] as $bulk_price) {
                    if ($entity->getQty() >= floatval($bulk_price["min_qty"]) && $entity->getQty() <= floatval($bulk_price["max_qty"])) {
                        $basePriceItemWithoutTax = $bulk_price["bulk_price_base_item"];
                        $discountBasePriceItem = $prices["price_base_currency"] - $bulk_price["bulk_price_base_item"];
                        $appliedDiscountPercent = $bulk_price["bulk_price_percentage"];
                        break;
                    }
                }
            }
        }

        /**
         * Override da je poklon uvijek 0 - ovo se moglo ugraditi i u getProductPrices, budemo vidjli di je bolje
         */
        if ($entity->getIsGift()) {
            $basePriceItemWithoutTax = 0;
        }


        /**
         * Primjeni popust sa KUPONA ako odgovara pravilima
         */
        if (floatval($discountCouponPercent) > 0) {

            if ($discountCoupon->getApplicationTypeId() == CrmConstants::DISCOUNT_COUPON_APPLY_ON_DISCOUNT_PRICE) {
                $discountCouponBasePriceItem = ($basePriceItemWithoutTax * (1 + $taxPercent / 100)) * $discountCouponPercent / 100;
            } elseif ($discountCoupon->getApplicationTypeId() == CrmConstants::DISCOUNT_COUPON_APPLY_ON_ORIGINAL_PRICE || $discountCoupon->getApplicationTypeId() == CrmConstants::DISCOUNT_COUPON_APPLY_BIGGER_DISCOUNT) {
                $discountCouponBasePriceItem = (floatval($prices["price_base_currency"]) * (1 + $taxPercent / 100)) * $discountCouponPercent / 100;
                $appliedDiscountPercent = $discountBasePriceItem = 0;
                $basePriceItemWithoutTax = floatval($prices["price_base_currency"]);
            } else {
                $discountCouponBasePriceItem = (floatval($prices["price_base_currency"]) * (1 + $taxPercent / 100)) * $discountCouponPercent / 100;
                $appliedDiscountPercent = $discountBasePriceItem = 0;
                $basePriceItemWithoutTax = floatval($prices["price_base_currency"]);
            }
            $appliedDiscountCouponPercent = $discountCouponPercent;
        }

        /**
         * Ovdje postavi loyalty popust ako nema nista drugo
         */
        $discountBaseLoyaltyPriceItem = 0;

        if (floatval($discountLoyaltyPercent) > 0) {
            $tmpDiscountLoyaltyPriceItem = $discountBaseLoyaltyPriceItem = (floatval($prices["price_retail_currency"]) * $discountLoyaltyPercent / 100);
            if ($tmpDiscountLoyaltyPriceItem > $discountCouponBasePriceItem && $tmpDiscountLoyaltyPriceItem > $discountBasePriceItem) {
                $appliedDiscountLoyaltyPercent = $discountLoyaltyPercent;
                if ($appliedDiscountLoyaltyPercent > 0) {
                    $discountBaseLoyaltyPriceItem = ($prices["price_base_currency"] * $appliedDiscountLoyaltyPercent / 100);
                    $appliedDiscountPercent = $discountBasePriceItem = 0;
                    $appliedDiscountCouponPercent = $discountCouponBasePriceItem = 0;
                    $basePriceItemWithoutTax = floatval($prices["price_base_currency"]);
                }
            }
        }

        $entity->setPercentageDiscount($appliedDiscountPercent);
        $entity->setPercentageLoyalty($appliedDiscountLoyaltyPercent);
        $entity->setPercentageDiscountCoupon($appliedDiscountCouponPercent);

        $entity->setBasePriceDiscountCouponItem($discountCouponBasePriceItem);
        $entity->setBasePriceDiscountCouponTotal($discountCouponBasePriceItem * floatval($entity->getQty()));

        $entity->setPriceDiscountCouponItem($discountCouponBasePriceItem / $currencyRate);
        $entity->setPriceDiscountCouponTotal($entity->getBasePriceDiscountCouponTotal() / $currencyRate);

        $entity->setBasePriceDiscountItem($discountBasePriceItem);
        $entity->setBasePriceDiscountTotal($discountBasePriceItem * floatval($entity->getQty()));

        $entity->setPriceDiscountItem($discountBasePriceItem / $currencyRate);
        $entity->setPriceDiscountTotal($entity->getBasePriceDiscountTotal() / $currencyRate);

        $entity->setBasePriceLoyaltyDiscountItem($discountBaseLoyaltyPriceItem);
        $entity->setBasePriceLoyaltyDiscountTotal($discountBaseLoyaltyPriceItem * floatval($entity->getQty()));

        $entity->setPriceLoyaltyDiscountItem($discountBaseLoyaltyPriceItem / $currencyRate);
        $entity->setPriceLoyaltyDiscountTotal($entity->getBasePriceLoyaltyDiscountTotal() / $currencyRate);

        $entity->setOriginalBasePriceItemWithoutTax($prices["original_price_base_currency"]);
        $entity->setOriginalBasePriceItemTax(
            floatval($prices["original_price_base_currency"]) * $taxPercent / 100
        );
        $entity->setOriginalBasePriceItem(
            $entity->getOriginalBasePriceItemWithoutTax() + $entity->getOriginalBasePriceItemTax()
        );
        $entity->setOriginalRebate($prices["rebate"]);

        if (floatval($entity->getPriceFixedDiscount()) > 0) {
            $entity->setBasePriceFixedDiscount($entity->getPriceFixedDiscount() / $currencyRate);
        }

        $entity->setBasePriceItemWithoutTax($basePriceItemWithoutTax);
        $entity->setBasePriceItemTax($entity->getBasePriceItemWithoutTax() * $taxPercent / 100);
        $entity->setBasePriceItem($entity->getBasePriceItemWithoutTax() + $entity->getBasePriceItemTax());

        $entity->setBasePriceWithoutTax($entity->getBasePriceItemWithoutTax() * $entity->getQty());
        $entity->setBasePriceTax($entity->getBasePriceItemTax() * $entity->getQty());

        $entity->setBasePriceTotal($entity->getBasePriceItem() * $entity->getQty());

        $entity->setOriginalPriceItemWithoutTax($entity->getOriginalBasePriceItemWithoutTax() / $currencyRate);
        $entity->setOriginalPriceItemTax($entity->getOriginalBasePriceItemTax() / $currencyRate);
        $entity->setOriginalPriceItem($entity->getOriginalBasePriceItem() / $currencyRate);

        $entity->setOriginalBasePriceWithoutTax($entity->getOriginalPriceItemWithoutTax() * $entity->getQty());
        $entity->setOriginalBasePriceTax($entity->getOriginalPriceItemTax() * $entity->getQty());
        $entity->setOriginalBasePriceTotal($entity->getOriginalPriceItem() * $entity->getQty());

        $entity->setPriceItemWithoutTax($entity->getBasePriceItemWithoutTax() / $currencyRate);
        $entity->setPriceItemTax($entity->getBasePriceItemTax() / $currencyRate);
        $entity->setPriceItem($entity->getBasePriceItem() / $currencyRate);

        $entity->setPriceWithoutTax($entity->getPriceItemWithoutTax() * $entity->getQty());
        $entity->setPriceTax($entity->getPriceItemTax() * $entity->getQty());

        $entity->setPriceItemReturn($prices["return_price"]);
        $entity->setPriceReturnTotal($prices["return_price"] * $entity->getQty());

        $total = ($entity->getPriceItem() * $entity->getQty()) + $entity->getPriceReturnTotal();

        $entity->setPriceTotal($total);

        return $entity;
    }

    /**
     * @param QuoteEntity $quote
     * @return QuoteEntity
     */
    public function recalculateQuoteTotals(QuoteEntity $quote)
    {
        $quoteItems = $quote->getQuoteItems();

        $totalQty = 0;

        $totalBasePriceWithoutTax = 0;
        $totalBasePriceTax = 0;
        $totalBasePriceTotal = 0;
        $discountBasePriceTotal = 0;
        $discountLoyaltyBasePriceTotal = 0;
        $discountCouponBasePriceTotal = 0;
        $couponIsFixedPrice = false;
        $totalBaseReturnPrice = 0;
        $basePriceFee = 0;

        /**
         * Check if discount coupon is fixed price and applicable
         */
        if (!empty($quote->getDiscountCoupon()) && $quote->getDiscountCoupon()->getIsFixed()) {
            $discountCouponBasePriceTotal = $quote->getDiscountCoupon()->getFixedDiscount();
            $couponIsFixedPrice = true;
        }

        /**
         * Ovo ce mozda trebati prebaciti u custom, vidjeti cemo na sljedecem loyalty
         */
        $discountLoyaltyPercent = 0;
        if (!empty($quote->getLoyaltyCard())) {
            $discountLoyaltyPercent = floatval($quote->getLoyaltyCard()->getPercentDiscount());
        }

        $currencyRate = $quote->getCurrencyRate();
        if (empty(floatval($currencyRate))) {
            $currencyRate = 1;
        }

        if (EntityHelper::isCountable($quoteItems) && count($quoteItems)) {

            $totalsPerTaxType = array();

            /** @var QuoteItemEntity $quoteItem */
            foreach ($quoteItems as $quoteItem) {

                if ($quoteItem->getProduct()->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
                    continue;
                }

                if (!isset($totalsPerTaxType[$quoteItem->getTaxTypeId()])) {
                    $totalsPerTaxType[$quoteItem->getTaxTypeId()] = array(
                        "totalBasePriceWithoutTax" => 0,
                        "taxPercent" => $quoteItem->getTaxType()->getPercent(),
                    );
                }

                $totalsPerTaxType[$quoteItem->getTaxTypeId()]["totalBasePriceWithoutTax"] = $totalsPerTaxType[$quoteItem->getTaxTypeId()]["totalBasePriceWithoutTax"] + $quoteItem->getBasePriceWithoutTax();

                $totalBasePriceWithoutTax = $totalBasePriceWithoutTax + $quoteItem->getBasePriceWithoutTax();
                //if (empty($quoteItem->getParentItem())) {
                $totalQty = $totalQty + floatval($quoteItem->getQty());
                //}

                $discountBasePriceTotal = $discountBasePriceTotal + $quoteItem->getBasePriceDiscountTotal();

                /**
                 * Applay discount coupon
                 */
                if (floatval($quoteItem->getBasePriceDiscountCouponTotal()) > 0) {
                    $discountCouponBasePriceTotal = $discountCouponBasePriceTotal + $quoteItem->getBasePriceDiscountCouponTotal();
                } /**
                 * Loyalty discount after tax
                 * Do not add if item already on discount or coupon applied
                 */
                elseif (!$couponIsFixedPrice) {
                    if (floatval($quoteItem->getBasePriceDiscountTotal()) == 0 && $discountLoyaltyPercent > 0) {
                        $discountLoyaltyBasePriceTotal = $discountLoyaltyBasePriceTotal + (floatval($quoteItem->getBasePriceTotal()) * $discountLoyaltyPercent / 100);
                    }
                }

                $totalBaseReturnPrice = $totalBaseReturnPrice + $quoteItem->getPriceReturnTotal();
            }

            foreach ($totalsPerTaxType as $taxTypeId => $totalPerTaxType) {
                $totalBasePriceTax = $totalBasePriceTax + ($totalPerTaxType["totalBasePriceWithoutTax"] * $totalPerTaxType["taxPercent"] / 100);
            }
        }

        /**
         * Iznos manipulativnih troskova ako postoje
         */
        if (!empty($quote->getPaymentType())) {
            $tmp = $quote->getPaymentType()->getPaymentFee();
            if (!empty($tmp) && isset($tmp[$quote->getStoreId()])) {
                $basePriceFee = floatval($tmp[$quote->getStoreId()]);
            }
        }
        $quote->setBasePriceFee($basePriceFee);
        $quote->setPriceFee($basePriceFee / $currencyRate);

        /**
         * Ukupni iznos povratne naknade
         */
        $quote->setPriceReturnTotal($totalBaseReturnPrice / $currencyRate);

        $totalBasePriceTotal = $totalBasePriceWithoutTax + $totalBasePriceTax;

        $quote->setBasePriceDiscount($discountBasePriceTotal);
        $quote->setPriceDiscount($discountBasePriceTotal / $currencyRate);
        $quote->setDiscountCouponPriceTotal($discountCouponBasePriceTotal / $currencyRate);

        $quote->setBasePriceItemsWithoutTax($totalBasePriceWithoutTax);
        $quote->setBasePriceItemsTax($totalBasePriceTax);
        $quote->setBasePriceItemsTotal($totalBasePriceTotal);

        $quote->setPriceItemsWithoutTax($totalBasePriceWithoutTax / $currencyRate);
        $quote->setPriceItemsTax($totalBasePriceTax / $currencyRate);
        $quote->setPriceItemsTotal($totalBasePriceTotal / $currencyRate);

        $totalBasePriceWithoutTax = $totalBasePriceWithoutTax + floatval($quote->getBasePriceDeliveryWithoutTax());
        $totalBasePriceTax = $totalBasePriceTax + floatval($quote->getBasePriceDeliveryTax());

        $totalBasePriceTotal = $totalBasePriceWithoutTax + $totalBasePriceTax + $totalBaseReturnPrice + $basePriceFee;

        /**
         * Substract coupon code discount
         */
        $totalBasePriceTotal = $totalBasePriceTotal - $discountCouponBasePriceTotal;

        /**
         * Substract loyalty discount
         */
        $totalBasePriceTotal = $totalBasePriceTotal - $discountLoyaltyBasePriceTotal;

        $quote->setBasePriceWithoutTax($totalBasePriceWithoutTax);
        $quote->setBasePriceTax($totalBasePriceTax);
        $quote->setBasePriceTotal($totalBasePriceTotal);

        $quote->setPriceWithoutTax($totalBasePriceWithoutTax / $currencyRate);
        $quote->setPriceTax($totalBasePriceTax / $currencyRate);
        $quote->setPriceTotal($totalBasePriceTotal / $currencyRate);

        $quote->setTotalQty($totalQty);

        $quote->setDiscountLoyaltyBasePriceTotal($discountLoyaltyBasePriceTotal);
        $quote->setDiscountLoyaltyPriceTotal($discountLoyaltyBasePriceTotal / $currencyRate);

        $this->entityManager->saveEntityWithoutLog($quote);
        $this->entityManager->refreshEntity($quote);

        return $quote;
    }

}


?>