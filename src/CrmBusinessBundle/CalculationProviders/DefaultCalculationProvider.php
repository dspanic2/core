<?php

namespace CrmBusinessBundle\CalculationProviders;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\BulkPriceOptionEntity;
use CrmBusinessBundle\Entity\DiscountCartRuleEntity;
use CrmBusinessBundle\Entity\DiscountCouponEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderItemEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\ProductAccountGroupPriceEntity;
use CrmBusinessBundle\Entity\ProductAccountPriceEntity;
use CrmBusinessBundle\Entity\ProductConfigurationProductLinkEntity;
use CrmBusinessBundle\Entity\ProductDiscountCatalogPriceEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;

class DefaultCalculationProvider extends AbstractBaseManager {

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

        /**
         * Ako je proizvod konfigurabilni nemoj nista zbrajati
         * Konfigurabilni proizvod nema cijenu, on je samo placeholder
         */
        if (in_array($entity->getProduct()->getProductTypeId(), array(CrmConstants::PRODUCT_TYPE_CONFIGURABLE)) && empty($entity->getParentItem())) {
            return $entity;
        }

        /** @var AccountEntity $account */
        $account = null;
        if (!empty($entity->getQuote()->getAccount())) {
            $account = $entity->getQuote()->getAccount();
        }

        $parentProduct = null;
        if (!empty($entity->getParentItem())) {
            $parentProduct = $entity->getParentItem()->getProduct();
        }
        /**
         * Moramo znati da li se uzmia bundle cijena ili neka druga pa bolje da parent posalje sam sebe nego prazno
         */
        elseif ($entity->getIsPartOfBundle()){
            $parentProduct = $entity->getProduct();
        }

        if(empty($this->crmProcessManager)){
            $this->crmProcessManager = $this->getContainer()->get("crm_process_manager");
        }

        $baseMethod = $this->crmProcessManager->getCalculationMethod($entity->getProduct(), $account, $parentProduct);

        $getPricesMethod = "getProductPrices".$baseMethod;
        $calculationMethod = "calculation".$baseMethod;

        $prices = $this->{$getPricesMethod}($entity->getProduct(), $account, $parentProduct, false);

        return $this->{$calculationMethod}($entity,$currencyRate,$prices,$account,$parentProduct);
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
        $totalBaseReturnPrice = 0;
        $discountCartRuleBasePriceTotal = 0;
        $basePriceFeeWithoutTax = 0;

        $currencyRate = $quote->getCurrencyRate();
        if (empty(floatval($currencyRate))) {
            $currencyRate = 1;
        }

        $totalsPerTaxType = array();

        if (EntityHelper::isCountable($quoteItems) && count($quoteItems)) {

            /** @var QuoteItemEntity $quoteItem */
            foreach ($quoteItems as $quoteItem) {

                if ($quoteItem->getProduct()->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
                    continue;
                }

                $quote->setCalculationType($quoteItem->getCalculationType());

                if (!isset($totalsPerTaxType[$quoteItem->getTaxTypeId()])) {
                    $totalsPerTaxType[$quoteItem->getTaxTypeId()] = array(
                        "totalBasePriceWithoutTax" => 0,
                        "taxPercent" => $quoteItem->getTaxType()->getPercent(),
                    );
                }

                $totalsPerTaxType[$quoteItem->getTaxTypeId()]["totalBasePriceWithoutTax"] = $totalsPerTaxType[$quoteItem->getTaxTypeId()]["totalBasePriceWithoutTax"] + $quoteItem->getBasePriceWithoutTax();

                /**
                 * Sum totals
                 */
                $totalQty = $totalQty + floatval($quoteItem->getQty());

                $totalBasePriceWithoutTax = $totalBasePriceWithoutTax + floatval($quoteItem->getBasePriceWithoutTax());
                $totalBasePriceTax = $totalBasePriceTax + floatval($quoteItem->getBasePriceTax());
                $totalBasePriceTotal = $totalBasePriceTotal + floatval($quoteItem->getBasePriceTotal());

                $discountBasePriceTotal = $discountBasePriceTotal + floatval($quoteItem->getBasePriceDiscountTotal());
                $discountCouponBasePriceTotal = $discountCouponBasePriceTotal + floatval($quoteItem->getBasePriceDiscountCouponTotal());
                $discountLoyaltyBasePriceTotal = $discountLoyaltyBasePriceTotal + floatval($quoteItem->getBasePriceLoyaltyDiscountTotal());
                $discountCartRuleBasePriceTotal = $discountCartRuleBasePriceTotal + floatval($quoteItem->getBasePriceDiscountCartRuleTotal());

                $totalBaseReturnPrice = $totalBaseReturnPrice + $quoteItem->getPriceReturnTotal();
            }
        }

        /**
         * Iznos manipulativnih troskova ako postoje
         */
        if (!empty($quote->getPaymentType())) {
            $tmp = $quote->getPaymentType()->getPaymentFee();
            if (!empty($tmp) && isset($tmp[$quote->getStoreId()])) {
                $basePriceFeeWithoutTax = floatval($tmp[$quote->getStoreId()]);
            }
        }
        $quote->setBasePriceFeeWithoutTax($basePriceFeeWithoutTax );
        $quote->setPriceFeeWithoutTax($basePriceFeeWithoutTax / $currencyRate);
        $quote->setBasePriceFeeTax($basePriceFeeWithoutTax*0.25);
        $quote->setPriceFeeTax($basePriceFeeWithoutTax*0.25 / $currencyRate);
        $quote->setBasePriceFee($basePriceFeeWithoutTax*1.25);
        $quote->setPriceFee($basePriceFeeWithoutTax*1.25 / $currencyRate);

        /**
         * Ukupni iznos povratne naknade
         */
        $quote->setPriceReturnTotal($totalBaseReturnPrice / $currencyRate);

        $quote->setBasePriceDiscount($discountBasePriceTotal);
        $quote->setPriceDiscount($discountBasePriceTotal / $currencyRate);
        $quote->setDiscountCouponBasePriceTotal($discountCouponBasePriceTotal);
        $quote->setDiscountCouponPriceTotal($discountCouponBasePriceTotal / $currencyRate);
        $quote->setDiscountLoyaltyBasePriceTotal($discountLoyaltyBasePriceTotal);
        $quote->setDiscountLoyaltyPriceTotal($discountLoyaltyBasePriceTotal / $currencyRate);
        $quote->setDiscountCartRuleBasePriceTotal($discountCartRuleBasePriceTotal);
        $quote->setDiscountCartRulePriceTotal($discountCartRuleBasePriceTotal / $currencyRate);
        $quote->setTotalsPerTaxType(json_encode($totalsPerTaxType));


        $quote->setBasePriceItemsWithoutTax($totalBasePriceWithoutTax);
        $quote->setBasePriceItemsTax($totalBasePriceTax);
        $quote->setBasePriceItemsTotal($totalBasePriceTotal);

        $quote->setPriceItemsWithoutTax($totalBasePriceWithoutTax / $currencyRate);
        $quote->setPriceItemsTax($totalBasePriceTax / $currencyRate);
        $quote->setPriceItemsTotal($totalBasePriceTotal / $currencyRate);

        $totalBasePriceWithoutTax = $totalBasePriceWithoutTax + floatval($quote->getBasePriceDeliveryWithoutTax()) + floatval($quote->getBasePriceFeeWithoutTax());
        $totalBasePriceTax = $totalBasePriceTax + floatval($quote->getBasePriceDeliveryTax()) + floatval($quote->getBasePriceFeeTax());
        
        $totalBasePriceTotal = $totalBasePriceTotal + $totalBaseReturnPrice + floatval($quote->getBasePriceDeliveryTotal()) + floatval($quote->getBasePriceFee());

        $quote->setBasePriceWithoutTax($totalBasePriceWithoutTax);
        $quote->setBasePriceTax($totalBasePriceTax);
        $quote->setBasePriceTotal($totalBasePriceTotal);

        $quote->setPriceWithoutTax($totalBasePriceWithoutTax / $currencyRate);
        $quote->setPriceTax($totalBasePriceTax / $currencyRate);
        $quote->setPriceTotal($totalBasePriceTotal / $currencyRate);

        $quote->setTotalQty($totalQty);

        $this->entityManager->saveEntityWithoutLog($quote);
        $this->entityManager->refreshEntity($quote);

        return $quote;
    }

    /**
     * @param OrderItemEntity $entity
     * @param $currencyRate
     * @return OrderItemEntity
     */
    public function calculatePriceOrderItem(OrderItemEntity $entity, $currencyRate = CrmConstants::DEFAULT_CURRENCY_RATE_ID)
    {
        if (empty(floatval($currencyRate))) {
            $currencyRate = 1;
        }

        /**
         * Ako je proizvod konfigurabilni nemoj nista zbrajati
         * Konfigurabilni proizvod nema cijenu, on je samo placeholder
         */
        if (in_array($entity->getProduct()->getProductTypeId(), array(CrmConstants::PRODUCT_TYPE_CONFIGURABLE)) && empty($entity->getParentItem())) {
            return $entity;
        }

        /** @var AccountEntity $account */
        $account = $entity->getOrder()->getAccount();

        $parentProduct = null;
        if (!empty($entity->getParentItem())) {
            $parentProduct = $entity->getParentItem()->getProduct();
        }
        /**
         * Moramo znati da li se uzmia bundle cijena ili neka druga pa bolje da parent posalje sam sebe nego prazno
         */
        elseif ($entity->getIsPartOfBundle()){
            $parentProduct = $entity->getProduct();
        }

        $baseMethod = $entity->getOrder()->getCalculationType();

        $getPricesMethod = "getProductPrices".$baseMethod;
        $calculationMethod = "calculationOrderItem".$baseMethod;

        $prices = json_decode($entity->getCalculationPrices(),true);
        if(empty($prices)){
            $prices = $this->{$getPricesMethod}($entity->getProduct(), $account, $parentProduct, false);
        }

        return $this->{$calculationMethod}($entity,$currencyRate,$prices,$account,$parentProduct);
    }

    /**
     * @param OrderEntity $order
     * @return OrderEntity
     */
    public function recalculateOrderTotals(OrderEntity $order)
    {
        $orderItems = $order->getOrderItems();

        $totalQty = 0;

        $totalBasePriceWithoutTax = 0;
        $totalBasePriceTax = 0;
        $totalBasePriceTotal = 0;
        $discountBasePriceTotal = 0;
        $discountLoyaltyBasePriceTotal = 0;
        $discountCouponBasePriceTotal = 0;
        $totalBaseReturnPrice = 0;
        $discountCartRuleBasePriceTotal = 0;
        $basePriceFeeWithoutTax = 0;

        $currencyRate = $order->getCurrencyRate();
        if (empty(floatval($currencyRate))) {
            $currencyRate = 1;
        }

        $totalsPerTaxType = array();

        if (EntityHelper::isCountable($orderItems) && count($orderItems)) {

            /** @var OrderItemEntity $orderItem */
            foreach ($orderItems as $orderItem) {

                if ($orderItem->getProduct()->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
                    continue;
                }

                if (!isset($totalsPerTaxType[$orderItem->getTaxTypeId()])) {
                    $totalsPerTaxType[$orderItem->getTaxTypeId()] = array(
                        "totalBasePriceWithoutTax" => 0,
                        "taxPercent" => $orderItem->getTaxType()->getPercent(),
                    );
                }

                $totalsPerTaxType[$orderItem->getTaxTypeId()]["totalBasePriceWithoutTax"] = $totalsPerTaxType[$orderItem->getTaxTypeId()]["totalBasePriceWithoutTax"] + $orderItem->getBasePriceWithoutTax();

                /**
                 * Sum totals
                 */
                $totalQty = $totalQty + floatval($orderItem->getQty());

                $totalBasePriceWithoutTax = $totalBasePriceWithoutTax + floatval($orderItem->getBasePriceWithoutTax());
                $totalBasePriceTax = $totalBasePriceTax + floatval($orderItem->getBasePriceTax());
                $totalBasePriceTotal = $totalBasePriceTotal + floatval($orderItem->getBasePriceTotal());

                $discountBasePriceTotal = $discountBasePriceTotal + floatval($orderItem->getBasePriceDiscountTotal());
                $discountCouponBasePriceTotal = $discountCouponBasePriceTotal + floatval($orderItem->getBasePriceDiscountCouponTotal());
                $discountLoyaltyBasePriceTotal = $discountLoyaltyBasePriceTotal + floatval($orderItem->getBasePriceLoyaltyDiscountTotal());
                $discountCartRuleBasePriceTotal = $discountCartRuleBasePriceTotal + floatval($orderItem->getBasePriceDiscountCartRuleTotal());

                $totalBaseReturnPrice = $totalBaseReturnPrice + $orderItem->getPriceReturnTotal();
            }
        }

        /**
         * Iznos manipulativnih troskova ako postoje
         */
        if (!empty($order->getPaymentType())) {
            $tmp = $order->getPaymentType()->getPaymentFee();
            if (!empty($tmp) && isset($tmp[$order->getStoreId()])) {
                $basePriceFeeWithoutTax = floatval($tmp[$order->getStoreId()]);
            }
        }
        $order->setBasePriceFeeWithoutTax($basePriceFeeWithoutTax);
        $order->setPriceFeeWithoutTax($basePriceFeeWithoutTax / $currencyRate);
        $order->setBasePriceFeeTax($basePriceFeeWithoutTax*0.25);
        $order->setPriceFeeTax($basePriceFeeWithoutTax*0.25/$currencyRate);
        $order->setBasePriceFee($basePriceFeeWithoutTax*1.25);
        $order->setPriceFee($basePriceFeeWithoutTax*1.25 / $currencyRate);

        /**
         * Ukupni iznos povratne naknade
         */
        $order->setPriceReturnTotal($totalBaseReturnPrice / $currencyRate);

        $order->setBasePriceDiscount($discountBasePriceTotal);
        $order->setPriceDiscount($discountBasePriceTotal / $currencyRate);
        $order->setDiscountCouponBasePriceTotal($discountCouponBasePriceTotal);
        $order->setDiscountCouponPriceTotal($discountCouponBasePriceTotal / $currencyRate);
        $order->setDiscountLoyaltyBasePriceTotal($discountLoyaltyBasePriceTotal);
        $order->setDiscountLoyaltyPriceTotal($discountLoyaltyBasePriceTotal / $currencyRate);
        $order->setDiscountCartRuleBasePriceTotal($discountCartRuleBasePriceTotal);
        $order->setDiscountCartRulePriceTotal($discountCartRuleBasePriceTotal / $currencyRate);
        $order->setTotalsPerTaxType(json_encode($totalsPerTaxType));


        $order->setBasePriceItemsWithoutTax($totalBasePriceWithoutTax);
        $order->setBasePriceItemsTax($totalBasePriceTax);
        $order->setBasePriceItemsTotal($totalBasePriceTotal);

        $order->setPriceItemsWithoutTax($totalBasePriceWithoutTax / $currencyRate);
        $order->setPriceItemsTax($totalBasePriceTax / $currencyRate);
        $order->setPriceItemsTotal($totalBasePriceTotal / $currencyRate);

        $totalBasePriceWithoutTax = $totalBasePriceWithoutTax + floatval($order->getBasePriceDeliveryWithoutTax()) + floatval($order->getBasePriceFeeWithoutTax());
        $totalBasePriceTax = $totalBasePriceTax + floatval($order->getBasePriceDeliveryTax()) + floatval($order->getBasePriceFeeTax());

        $totalBasePriceTotal = $totalBasePriceTotal + $totalBaseReturnPrice + floatval($order->getBasePriceDeliveryTotal()) + floatval($order->getBasePriceFee());

        $order->setBasePriceWithoutTax($totalBasePriceWithoutTax);
        $order->setBasePriceTax($totalBasePriceTax);
        $order->setBasePriceTotal($totalBasePriceTotal);

        $order->setPriceWithoutTax($totalBasePriceWithoutTax / $currencyRate);
        $order->setPriceTax($totalBasePriceTax / $currencyRate);
        $order->setPriceTotal($totalBasePriceTotal / $currencyRate);

        $order->setTotalQty($totalQty);

        $this->entityManager->saveEntityWithoutLog($order);
        $this->entityManager->refreshEntity($order);

        return $order;
    }

    /**
     * CALCULATE PRICE ITEMS
     */

    /**
     * @param QuoteItemEntity $entity
     * @param $currencyRate
     * @param $prices
     * @param AccountEntity|null $account
     * @param $parentProduct
     * @return QuoteItemEntity
     * @throws \Exception
     */
    public function calculationVpc(QuoteItemEntity $entity, $currencyRate, $prices, AccountEntity $account = null, $parentProduct = null){

        $taxPercent = $entity->getProduct()->getTaxType()->getPercent();

        /**
         * Popis postotaka koji se sprema u bazu i izracunatih popusta u apsolutnom iznosu
         */
        $appliedDiscountPercent = 0;
        $basePriceDiscountItem = 0;
        $basePriceDiscountTotal = 0;

        $appliedDiscountCartRulePercent = 0;
        $basePriceDiscountCartRuleItem = 0;
        $basePriceDiscountCartRuleTotal = 0;

        $appliedDiscountCouponPercent = 0;
        $basePriceDiscountCouponItem = 0;
        $basePriceDiscountCouponTotal = 0;

        $appliedDiscountLoyaltyPercent = 0;
        $basePriceDiscountLoyaltyItem = 0;
        $basePriceDiscountLoyaltyTotal = 0;

        $entity->setCalculationType($prices["calculation_type"]);
        $entity->setAppliedDiscountCatalogRule(null);
        $entity->setAppliedDiscountCartRule(null);

        $qty = floatval($entity->getQty());

        /**
         * Applied discounts list
         */
        $catalogDiscountApplied = false;
        $bundleDiscountApplied = false;
        $bulkDiscountApplied = false;
        $couponDiscountApplied = false;
        $cartRuleDiscountApplied = false;
        $manualDiscountApplied = false;

        /**
         * Osnovica stavke za izracun
         */
        $osnovicaStavkeZaIzracun = floatval($prices["price"]);

        /**
         * Override da je poklon uvijek 0
         */
        if ($entity->getIsGift()) {
            $osnovicaStavkeZaIzracun = 0;
        }

        $entity->setOriginalBasePriceItemWithoutTax($osnovicaStavkeZaIzracun);
        $entity->setOriginalBasePriceItemTax(round($osnovicaStavkeZaIzracun*$taxPercent/100,2));
        $entity->setOriginalBasePriceItem(round($osnovicaStavkeZaIzracun*(100+$taxPercent)/100,2));

        $originalBasePriceWithoutTax = round($entity->getOriginalBasePriceItemWithoutTax() * $qty,2);

        $entity->setOriginalBasePriceWithoutTax($originalBasePriceWithoutTax);
        $entity->setOriginalBasePriceTax(round($entity->getOriginalBasePriceWithoutTax()*$taxPercent/100,2));
        $entity->setOriginalBasePriceTotal(round($entity->getOriginalBasePriceWithoutTax()*(100+$taxPercent)/100,2));


        /**
         * Samo kada admin kroz kreiranje ponude postavi neku fiksnu cijenu proizvoda onda se ta cijena koristi kao osnovica za sve
         * Uklanjamo bulk price
         */
        if(floatval($entity->getBasePriceFixedDiscount()) > 0){
            $prices["price"] = floatval($entity->getBasePriceFixedDiscount());
            $prices["discount_price"] = null;
            $prices["bulk_prices"] = null;
        }

        /**
         * Ako je dio bundlea primjeni taj popust
         */
        if ($entity->getIsPartOfBundle()){
            if(floatval($prices["discount_price"]) > 0){
                $osnovicaStavkeZaIzracun = round(floatval($prices["discount_price"]),2);
            }
            $osnovicaTotalaZaIzracun = $osnovicaStavkeZaIzracun * $qty;

            /**
             * Izracunaj postotak popusta
             */
            $appliedDiscountPercent = ($entity->getOriginalBasePriceItemWithoutTax()-$osnovicaStavkeZaIzracun)/$entity->getOriginalBasePriceItemWithoutTax() * 100;
            /**
             * Izracunaj iznos popusta na stavci i totalu
             */
            $basePriceDiscountItem = ($entity->getOriginalBasePriceItemWithoutTax()-$osnovicaStavkeZaIzracun);
            $basePriceDiscountTotal = $entity->getOriginalBasePriceWithoutTax() - $osnovicaTotalaZaIzracun;

            $bundleDiscountApplied = true;
        }

        /**
         * Primjena popusta na katalog ili popusta sa proizvoda
         * Ako je bundle preskoci primjenu
         */
        if (floatval($prices["discount_price"]) > 0 && !$bundleDiscountApplied) {

            $osnovicaStavkeZaIzracun = floatval($prices["discount_price"]);
            if(isset($prices["discount_catalog_rule_id"]) && !empty($prices["discount_catalog_rule_id"])){
                if(empty($this->discountRulesManager)){
                    $this->discountRulesManager = $this->container->get("discount_rules_manager");
                }
                $entity->setAppliedDiscountCatalogRule($this->discountRulesManager->getDiscountCatalogById($prices["discount_catalog_rule_id"]));
            }
            $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun*$qty,2);

            /**
             * Izracunaj postotak popusta
             */
            $appliedDiscountPercent = ($entity->getOriginalBasePriceItemWithoutTax()-$osnovicaStavkeZaIzracun)/$entity->getOriginalBasePriceItemWithoutTax() * 100;

            /**
             * Izracunaj iznos popusta na stavci i totalu
             */
            $basePriceDiscountItem = ($entity->getOriginalBasePriceItemWithoutTax()-$osnovicaStavkeZaIzracun);
            $basePriceDiscountTotal = $entity->getOriginalBasePriceWithoutTax()-$osnovicaTotalaZaIzracun;

            $catalogDiscountApplied = true;
        }

        /**
         * Ako je admin postavio % popusta kroz kreiranje ponude onda pregazi postojeci postotak popusta
         */
        if (floatval($entity->getPercentageDiscountFixed()) > 0) {
            $appliedDiscountPercent = floatval($entity->getPercentageDiscountFixed());

            /**
             * Iznos sa uracunatim popustom
             */
            $osnovicaStavkeZaIzracun = round($entity->getOriginalBasePriceItem()*(100-$appliedDiscountPercent)/100,2);
            $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun*$qty,2);

            /**
             * Izracunaj iznos popusta na stavci i totalu
             */
            $basePriceDiscountItem = $entity->getOriginalBasePriceItemWithoutTax()-$osnovicaStavkeZaIzracun;
            $basePriceDiscountTotal = $entity->getOriginalBasePriceWithoutTax()-$osnovicaTotalaZaIzracun;

            $manualDiscountApplied = true;

            /**
             * Ocisti visak popusta
             */
            $bundleDiscountApplied = false;
            $catalogDiscountApplied = false;
            $entity->setAppliedDiscountCatalogRule(null);
            $prices["bulk_prices"] = null;
        }

        /**
         * Osnovica stavke za izracun * kolicina
         */
        $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun * $qty,2);

        /**
         * Primjena bulk popusta
         * Ako je bundle preskoci primjenu
         */
        if (isset($prices["bulk_prices"]) && !$bundleDiscountApplied) {

            /**
             * For 1+1 free
             */
            if (isset($prices["bulk_prices"][0]["bulk_price_type"]) && $prices["bulk_prices"][0]["bulk_price_type"] == 2) {
                $bulk_price = $prices["bulk_prices"][0];

                if ($qty >= floatval($bulk_price["min_qty"])) {

                    $step = floatval($bulk_price["min_qty"]);
                    $times = floor($qty / $step);

                    /**
                     * Varijanta 2
                     */
                    $osnovicaTotalaZaIzracun = ($qty * $osnovicaStavkeZaIzracun) - ($times * $osnovicaStavkeZaIzracun);

                    $osnovicaStavkeZaIzracun = round($osnovicaTotalaZaIzracun / $qty,2);

                    $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun * $qty,2);

                    /**
                     * Postavi postotak popusta
                     */
                    $appliedDiscountPercent = floatval($bulk_price["bulk_price_percentage"]);

                    /**
                     * Izracunaj iznos popusta na stavci i totalu
                     */
                    $basePriceDiscountItem = ($entity->getOriginalBasePriceItemWithoutTax()-$osnovicaStavkeZaIzracun);
                    $basePriceDiscountTotal = $entity->getOriginalBasePriceWithoutTax() - $osnovicaTotalaZaIzracun;

                    $bulkDiscountApplied = true;
                }
            }
            /**
             * For standard bulk price items
             */
            else {
                foreach ($prices["bulk_prices"] as $bulk_price) {
                    if ($entity->getQty() >= floatval($bulk_price["min_qty"]) && $entity->getQty() <= floatval($bulk_price["max_qty"])) {

                        $osnovicaStavkeZaIzracun = $bulk_price["bulk_price_item"];
                        $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun * $qty,2);

                        /**
                         * Postavi postotak popusta
                         */
                        $appliedDiscountPercent = ($entity->getOriginalBasePriceItemWithoutTax()-$osnovicaStavkeZaIzracun)/$entity->getOriginalBasePriceItem() * 100;

                        /**
                         * Izracunaj iznos popusta na stavci i totalu
                         */
                        $basePriceDiscountItem = ($entity->getOriginalBasePriceItemWithoutTax()-$osnovicaStavkeZaIzracun);
                        $basePriceDiscountTotal = $entity->getOriginalBasePriceWithoutTax() - $osnovicaTotalaZaIzracun;

                        $bulkDiscountApplied = true;

                        break;
                    }
                }
            }
        }

        /**
         * Apply discount cart rules
         */
        /**
         * Postavljanje popusta na košaricu
         * Ne primjenjuje se ako je vec primjenjen popust po grupi kupca
         * Ne primjenjuje se ako je na kosarici disejblan cart rules
         * Ne primjenjuje se ako je proizvod dodan kao dio bundle-a
         * Ne primjenjuje se ako je proizvodu vec primjenjen bulk popust
         */
        if((!isset($prices["discount_type"]) || empty($prices["discount_type"]) || $prices["discount_type"] != "discount_account_group") && !$entity->getQuote()->getDisableCartRule() && !$bundleDiscountApplied && !$bulkDiscountApplied && !$manualDiscountApplied){

            if(empty($this->productAttributeFilterRulesManager)){
                $this->productAttributeFilterRulesManager = $this->container->get("product_attribute_filter_rules_manager");
            }

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("isApplied", "eq", 1));
            $compositeFilter->addFilter(new SearchFilter("discountPercent", "gt", 0));

            $sortFilters = new SortFilterCollection();
            $sortFilters->addSortFilter(new SortFilter("priority", "desc"));

            $rules = $this->productAttributeFilterRulesManager->getRulesByEntityTypeCode("discount_cart_rule",$compositeFilter,$sortFilters);

            if(EntityHelper::isCountable($rules) && count($rules)){

                if(empty($this->databaseContext)){
                    $this->databaseContext = $this->container->get("database_context");
                }

                /** @var DiscountCartRuleEntity $rule */
                foreach ($rules as $rule){

                    /**
                     * Check if has excluded payment type
                     */
                    if(EntityHelper::isCountable($rule->getExcludedPaymentTypes()) && count($rule->getExcludedPaymentTypes())){
                        if(empty($entity->getQuote()->getPaymentType())){
                            continue;
                        }

                        $hasExcludedPaymentType = false;

                        /** @var PaymentTypeEntity $paymentType */
                        foreach ($rule->getExcludedPaymentTypes() as $paymentType){

                            if($entity->getQuote()->getPaymentType()->getId() == $paymentType->getId()){
                                $hasExcludedPaymentType = true;
                                break;
                            }
                        }

                        if($hasExcludedPaymentType){
                            continue;
                        }
                    }

                    /**
                     * Na koga se primjenjuje
                     * 1. na sve
                     * 2. samo sve registrirane kupce
                     * 3. samo na registrirane kupce koji nisu pravne osobe
                     * 4. samo na registrirane kupce koji jesu pravne osobe
                     */
                    if(!empty($rule->getDiscountAppliedTo()) && $rule->getDiscountAppliedTo()->getId() != CrmConstants::SVI_KUPCI){

                        if(in_array($rule->getDiscountAppliedTo()->getId(),Array(CrmConstants::SAMO_REGISTRIRANI_KUPCI,CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_JESU_PRAVNE_OSOBE,CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_NISU_PRAVNE_OSOBE))){

                            if(empty($entity->getQuote()->getAccount()) || empty($entity->getQuote()->getContact()) || empty($entity->getQuote()->getContact()->getCoreUser())){
                                continue;
                            }

                            if($rule->getDiscountAppliedTo()->getId() == CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_JESU_PRAVNE_OSOBE){
                                if(!$entity->getQuote()->getAccount()->getIsLegalEntity()){
                                    continue;
                                }
                            }
                            elseif($rule->getDiscountAppliedTo()->getId() == CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_NISU_PRAVNE_OSOBE){
                                if($entity->getQuote()->getAccount()->getIsLegalEntity()){
                                    continue;
                                }
                            }
                        }
                    }

                    if(floatval($rule->getMinPrice()) > 0){

                        $q = "SELECT SUM(original_base_price_item * qty) AS count FROM quote_item_entity WHERE quote_id = {$entity->getQuote()->getId()} AND id != {$entity->getId()};";
                        $total = floatval($this->databaseContext->getSingleResult($q));

                        $total = $total + (floatval($entity->getOriginalBasePriceItemWithoutTax()) * $qty);

                        if($total < floatval($rule->getMinPrice())){
                            continue;
                        }
                    }

                    /**
                     * Check if product in rule
                     * if not go to next rule
                     */
                    if(!$this->productAttributeFilterRulesManager->productMatchesRules($entity->getProduct(),$rule->getRules(),$rule)){
                        continue;
                    }

                    $entity->setAppliedDiscountCartRule($rule);

                    /**
                     * Postavi discount percent
                     */
                    $appliedDiscountCartRulePercent = floatval($rule->getDiscountPercent());

                    $osnovicaStavkeZaIzracun = round($entity->getOriginalBasePriceItemWithoutTax()*(100-$appliedDiscountCartRulePercent)/100,2);
                    $osnovicaTotalaZaIzracun = $osnovicaStavkeZaIzracun*$qty;

                    /**
                     * Izracunaj iznos popusta na stavci i totalu
                     */
                    $basePriceDiscountCartRuleItem = ($entity->getOriginalBasePriceItemWithoutTax()-$osnovicaStavkeZaIzracun);
                    $basePriceDiscountCartRuleTotal = $entity->getOriginalBasePriceWithoutTax()-$osnovicaTotalaZaIzracun;

                    /**
                     * Ocisti visak postotaka popusta
                     */
                    $appliedDiscountPercent = 0;
                    $entity->setAppliedDiscountCatalogRule(null);

                    $cartRuleDiscountApplied = true;

                    break;
                }
            }
        }

        /**
         * Dohvat postotka kuponskog koda
         */
        /** @var DiscountCouponEntity $discountCoupon */
        $discountCoupon = $entity->getQuote()->getDiscountCoupon();
        $discountCouponPercent = 0;

        if (!empty($discountCoupon) && !$bulkDiscountApplied && !$manualDiscountApplied) {
            $discountCouponPercent = floatval($this->crmProcessManager->getApplicableDiscountCouponPercentForProduct($discountCoupon, $entity->getProduct(), $parentProduct, $account, $prices, $entity->getQuote()->getBasePriceTotal()));
        }

        /**
         * Primjena kuponskog popusta
         * Ne primjenjuje se ako je proizvod dodan kao dio bundle-a
         * Ne primjenjuje se ako je proizvodu vec primjenjen bulk popust
         */
        if ($discountCouponPercent > 0) {

            $appliedDiscountCouponPercent = $discountCouponPercent;
            $couponDiscountApplied = true;

            /**
             * Primjeni na snizenu cijenu
             */
            if ($discountCoupon->getApplicationTypeId() == CrmConstants::DISCOUNT_COUPON_APPLY_ON_DISCOUNT_PRICE) {

                /**
                 * Iznos sa uracunatim popustom
                 */
                $basePriceDiscountCouponItem = $osnovicaStavkeZaIzracun * $appliedDiscountCouponPercent / 100;
                $basePriceDiscountCouponTotal = $osnovicaTotalaZaIzracun;

                /**
                 * TRS - Ostavljamo staru varijantu da lako vratimo
                 */
                $osnovicaStavkeZaIzracun = round($osnovicaStavkeZaIzracun*(100-$appliedDiscountCouponPercent)/100,2);
                $osnovicaTotalaZaIzracun = round($osnovicaTotalaZaIzracun*(100-$appliedDiscountCouponPercent)/100,2);

                $basePriceDiscountCouponTotal = $basePriceDiscountCouponTotal - $osnovicaTotalaZaIzracun;
            }
            /**
             * Primjeni veci popust izmedju snizene cijene i kupona
             */
            elseif ($discountCoupon->getApplicationTypeId() == CrmConstants::DISCOUNT_COUPON_APPLY_ON_ORIGINAL_PRICE || $discountCoupon->getApplicationTypeId() == CrmConstants::DISCOUNT_COUPON_APPLY_BIGGER_DISCOUNT) {

                /**
                 * Ukloni popust i izracunaj ponovo base price
                 */
                $basePriceDiscountCouponItem = $entity->getOriginalBasePriceItemWithoutTax() * $appliedDiscountCouponPercent / 100;
                $basePriceDiscountCouponTotal = $entity->getOriginalBasePriceWithoutTax();

                /**
                 * Iznos sa uracunatim popustom
                 */
                $osnovicaStavkeZaIzracun = round($entity->getOriginalBasePriceItemWithoutTax()*(100-$appliedDiscountCouponPercent)/100,2);
                $osnovicaTotalaZaIzracun = round($entity->getOriginalBasePriceWithoutTax()*(100-$appliedDiscountCouponPercent)/100,2);

                $basePriceDiscountCouponTotal = $basePriceDiscountCouponTotal - $osnovicaTotalaZaIzracun;

                /**
                 * Ocisti visak postotaka popusta
                 */
                $entity->setAppliedDiscountCatalogRule(null);
                $entity->setAppliedDiscountCartRule(null);
                $appliedDiscountPercent = 0;
                $appliedDiscountCartRulePercent = 0;
            }
            /**
             * Primjeni na osnovnu cijenu proizvoda
             */
            else {

                /**
                 * Ukloni popust i izracunaj ponovo base price
                 */
                $basePriceDiscountCouponItem = $entity->getOriginalBasePriceItemWithoutTax() * $appliedDiscountCouponPercent / 100;
                $basePriceDiscountCouponTotal = $entity->getOriginalBasePriceWithoutTax();

                /**
                 * Iznos sa uracunatim popustom
                 */
                $osnovicaStavkeZaIzracun = round($entity->getOriginalBasePriceItemWithoutTax()*(100-$appliedDiscountCouponPercent)/100,2);
                $osnovicaTotalaZaIzracun = round($entity->getOriginalBasePriceWithoutTax()*(100-$appliedDiscountCouponPercent)/100,2);

                $basePriceDiscountCouponTotal = $basePriceDiscountCouponTotal - $osnovicaTotalaZaIzracun;

                /**
                 * Ocisti visak postotaka popusta
                 */
                $entity->setAppliedDiscountCatalogRule(null);
                $entity->setAppliedDiscountCartRule(null);
                $appliedDiscountPercent = 0;
                $appliedDiscountCartRulePercent = 0;
            }
        }

        /**
         * Primjena loyalty popusta
         */
        if (!empty($entity->getQuote()->getLoyaltyCard()) && !$entity->getProduct()->getDisableLoyaltyDiscount() && floatval($entity->getQuote()->getLoyaltyCard()->getPercentDiscount()) > 0 && !$bundleDiscountApplied && !$bulkDiscountApplied && !$catalogDiscountApplied && !$cartRuleDiscountApplied && !$couponDiscountApplied && !$manualDiscountApplied) {

            $discountLoyaltyPercent = floatval($entity->getQuote()->getLoyaltyCard()->getPercentDiscount());

            $appliedDiscountLoyaltyPercent = $discountLoyaltyPercent;

            $basePriceDiscountLoyaltyItem = $osnovicaStavkeZaIzracun * $appliedDiscountLoyaltyPercent / 100;
            $basePriceDiscountLoyaltyTotal = $osnovicaTotalaZaIzracun;

            /**
             * Iznos sa uracunatim popustom
             */
            $osnovicaStavkeZaIzracun = round($osnovicaStavkeZaIzracun*(100-$appliedDiscountLoyaltyPercent)/100,2);
            $osnovicaTotalaZaIzracun = round($osnovicaTotalaZaIzracun*(100-$appliedDiscountLoyaltyPercent)/100,2);

            $basePriceDiscountLoyaltyTotal = $basePriceDiscountLoyaltyTotal - $osnovicaTotalaZaIzracun;

            /**
             * Ocisti visak postotaka popusta
             */
            $appliedDiscountPercent = 0;
            $appliedDiscountCouponPercent = 0;
            $appliedDiscountCartRulePercent = 0;
        }

        $entity->setPercentageDiscount($appliedDiscountPercent);
        $entity->setPercentageLoyalty($appliedDiscountLoyaltyPercent);
        $entity->setPercentageDiscountCoupon($appliedDiscountCouponPercent);
        $entity->setPercentageDiscountCartRule($appliedDiscountCartRulePercent);

        /**
         * Apsolutni iznosi popusta
         */
        $entity->setBasePriceDiscountItem(round($basePriceDiscountItem*(100+$taxPercent)/100,2));
        $entity->setBasePriceDiscountTotal(round($basePriceDiscountTotal*(100+$taxPercent)/100,2));

        $entity->setBasePriceDiscountCartRuleItem(round($basePriceDiscountCartRuleItem*(100+$taxPercent)/100,2));
        $entity->setBasePriceDiscountCartRuleTotal(round($basePriceDiscountCartRuleTotal*(100+$taxPercent)/100,2));

        $entity->setBasePriceDiscountCouponItem(round($basePriceDiscountCouponItem*(100+$taxPercent)/100,2));
        $entity->setBasePriceDiscountCouponTotal(round($basePriceDiscountCouponTotal*(100+$taxPercent)/100,2));

        $entity->setBasePriceLoyaltyDiscountItem(round($basePriceDiscountLoyaltyItem*(100+$taxPercent)/100,2));
        $entity->setBasePriceLoyaltyDiscountTotal(round($basePriceDiscountLoyaltyTotal*(100+$taxPercent)/100,2));

        $entity->setOriginalRebate($prices["rebate"]);

        /**
         * Uracunati popusti
         */
        $entity->setBasePriceItemWithoutTax($osnovicaStavkeZaIzracun);
        $entity->setBasePriceItemTax(round($osnovicaStavkeZaIzracun*$taxPercent/100,2));
        $entity->setBasePriceItem(round($osnovicaStavkeZaIzracun*(100+$taxPercent)/100,2));

        $entity->setBasePriceWithoutTax($osnovicaTotalaZaIzracun);
        $entity->setBasePriceTax(round($osnovicaTotalaZaIzracun*$taxPercent/100,2));
        $entity->setBasePriceTotal(round($osnovicaTotalaZaIzracun*(100+$taxPercent)/100,2));

        $entity->setOriginalPriceItemWithoutTax($entity->getOriginalBasePriceItemWithoutTax() / $currencyRate);
        $entity->setOriginalPriceItemTax($entity->getOriginalBasePriceItemTax() / $currencyRate);
        $entity->setOriginalPriceItem($entity->getOriginalBasePriceItem() / $currencyRate);

        $entity->setPriceItemWithoutTax($entity->getBasePriceItemWithoutTax() / $currencyRate);
        $entity->setPriceItemTax($entity->getBasePriceItemTax() / $currencyRate);
        $entity->setPriceItem($entity->getBasePriceItem() / $currencyRate);

        $entity->setPriceWithoutTax($entity->getBasePriceWithoutTax()  / $currencyRate);
        $entity->setPriceTax($entity->getBasePriceTax()  / $currencyRate);

        $entity->setPriceItemReturn(floatval($prices["return_price"]));
        $entity->setPriceReturnTotal(floatval($prices["return_price"]) * $qty);

        $entity->setPriceTotal($entity->getBasePriceTotal() + $entity->getPriceReturnTotal());

        $entity->setCalculationPrices(json_encode($prices));

        return $entity;
    }

    /**
     * @param QuoteItemEntity $entity
     * @param $currencyRate
     * @param $prices
     * @param AccountEntity|null $account
     * @param $parentProduct
     * @return QuoteItemEntity
     * @throws \Exception
     */
    public function calculationMpc(QuoteItemEntity $entity, $currencyRate, $prices, AccountEntity $account = null, $parentProduct = null){

        $taxPercent = $entity->getProduct()->getTaxType()->getPercent();

        /**
         * Popis postotaka koji se sprema u bazu i izracunatih popusta u apsolutnom iznosu
         */
        $appliedDiscountPercent = 0;
        $basePriceDiscountItem = 0;
        $basePriceDiscountTotal = 0;

        $appliedDiscountCartRulePercent = 0;
        $basePriceDiscountCartRuleItem = 0;
        $basePriceDiscountCartRuleTotal = 0;

        $appliedDiscountCouponPercent = 0;
        $basePriceDiscountCouponItem = 0;
        $basePriceDiscountCouponTotal = 0;

        $appliedDiscountLoyaltyPercent = 0;
        $basePriceDiscountLoyaltyItem = 0;
        $basePriceDiscountLoyaltyTotal = 0;

        $entity->setCalculationType($prices["calculation_type"]);
        $entity->setAppliedDiscountCatalogRule(null);
        $entity->setAppliedDiscountCartRule(null);

        $qty = floatval($entity->getQty());

        /**
         * Applied discounts list
         */
        $catalogDiscountApplied = false;
        $bundleDiscountApplied = false;
        $bulkDiscountApplied = false;
        $couponDiscountApplied = false;
        $cartRuleDiscountApplied = false;
        $manualDiscountApplied = false;

        /**
         * Samo kada admin kroz kreiranje ponude postavi neku fiksnu cijenu proizvoda onda se ta cijena koristi kao osnovica za sve
         * Uklanjamo bulk price
         */
        if(floatval($entity->getBasePriceFixedDiscount()) > 0){
            $prices["price"] = floatval($entity->getBasePriceFixedDiscount());
            $prices["discount_price"] = null;
            $prices["bulk_prices"] = null;
        }

        /**
         * Osnovica stavke za izracun
         */
        $osnovicaStavkeZaIzracun = floatval($prices["price"]);

        /**
         * Override da je poklon uvijek 0
         */
        if ($entity->getIsGift()) {
            $osnovicaStavkeZaIzracun = 0;
        }

        $entity->setOriginalBasePriceItemWithoutTax(round($osnovicaStavkeZaIzracun*100/(100+$taxPercent),6));
        $entity->setOriginalBasePriceItemTax(round($osnovicaStavkeZaIzracun*$taxPercent/(100+$taxPercent),6));
        $entity->setOriginalBasePriceItem(round($osnovicaStavkeZaIzracun,2));

        $originalBasePriceTotal = round($osnovicaStavkeZaIzracun * $qty,2);

        $entity->setOriginalBasePriceWithoutTax(round($originalBasePriceTotal*100/(100+$taxPercent),6));
        $entity->setOriginalBasePriceTax(round($originalBasePriceTotal*$taxPercent/(100+$taxPercent),6));
        $entity->setOriginalBasePriceTotal(round($originalBasePriceTotal,2));

        /**
         * Ako je dio bundlea primjeni taj popust
         */
        if ($entity->getIsPartOfBundle()){
            if(floatval($prices["discount_price"]) > 0){
                $osnovicaStavkeZaIzracun = round(floatval($prices["discount_price"]),2);
            }

            /**
             * Izracunaj postotak popusta
             */
            $appliedDiscountPercent = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun)/$entity->getOriginalBasePriceItem() * 100;
            /**
             * Izracunaj iznos popusta na stavci i totalu
             */
            $basePriceDiscountItem = $entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun;
            $basePriceDiscountTotal = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun)*$qty;

            $bundleDiscountApplied = true;
        }

        /**
         * Primjena popusta na katalog ili popusta sa proizvoda
         * Ako je bundle preskoci primjenu
         */
        if (floatval($prices["discount_price"]) > 0 && !$bundleDiscountApplied) {

            $osnovicaStavkeZaIzracun = floatval($prices["discount_price"]);
            if(isset($prices["discount_catalog_rule_id"]) && !empty($prices["discount_catalog_rule_id"])){
                if(empty($this->discountRulesManager)){
                    $this->discountRulesManager = $this->container->get("discount_rules_manager");
                }
                $entity->setAppliedDiscountCatalogRule($this->discountRulesManager->getDiscountCatalogById($prices["discount_catalog_rule_id"]));
            }
            $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun*$qty,2);

            /**
             * Izracunaj postotak popusta
             */
            $appliedDiscountPercent = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun)/$entity->getOriginalBasePriceItem() * 100;

            /**
             * Izracunaj iznos popusta na stavci i totalu
             */
            $basePriceDiscountItem = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun);
            $basePriceDiscountTotal = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun)*$qty;

            $catalogDiscountApplied = true;
        }

        /**
         * Ako je admin postavio % popusta kroz kreiranje ponude onda pregazi postojeci postotak popusta
         */
        if (floatval($entity->getPercentageDiscountFixed()) > 0) {
            $appliedDiscountPercent = floatval($entity->getPercentageDiscountFixed());

            /**
             * Iznos sa uracunatim popustom
             */
            $osnovicaStavkeZaIzracun = round($entity->getOriginalBasePriceItem()*(100-$appliedDiscountPercent)/100,2);
            $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun*$qty,2);

            /**
             * Izracunaj iznos popusta na stavci i totalu
             */
            $basePriceDiscountItem = $entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun;
            $basePriceDiscountTotal = $entity->getOriginalBasePriceTotal()-$osnovicaTotalaZaIzracun;

            $manualDiscountApplied = true;

            /**
             * Ocisti visak popusta
             */
            $bundleDiscountApplied = false;
            $catalogDiscountApplied = false;
            $entity->setAppliedDiscountCatalogRule(null);
            $prices["bulk_prices"] = null;
        }

        /**
         * Osnovica stavke za izracun * kolicina
         */
        $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun * $qty,2);

        /**
         * Primjena bulk popusta
         * Ako je bundle preskoci primjenu
         */
        if (isset($prices["bulk_prices"]) && !$bundleDiscountApplied) {

            /**
             * For 1+1 free
             */
            if (isset($prices["bulk_prices"][0]["bulk_price_type"]) && $prices["bulk_prices"][0]["bulk_price_type"] == 2) {
                $bulk_price = $prices["bulk_prices"][0];

                if ($qty >= floatval($bulk_price["min_qty"])) {

                    $step = floatval($bulk_price["min_qty"]);
                    $times = floor($qty / $step);

                    /**
                     * Varijanta 2
                     */
                    $osnovicaTotalaZaIzracun = ($qty * $osnovicaStavkeZaIzracun) - ($times * $osnovicaStavkeZaIzracun);

                    $osnovicaStavkeZaIzracun = round($osnovicaTotalaZaIzracun / $qty,2);

                    $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun * $qty,2);

                    /**
                     * Postavi postotak popusta
                     */
                    $appliedDiscountPercent = floatval($bulk_price["bulk_price_percentage"]);

                    /**
                     * Izracunaj iznos popusta na stavci i totalu
                     */
                    $basePriceDiscountItem = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun);
                    $basePriceDiscountTotal = $entity->getOriginalBasePriceTotal()-$osnovicaTotalaZaIzracun;

                    $bulkDiscountApplied = true;
                }
            }
            /**
             * For standard bulk price items
             */
            else {
                foreach ($prices["bulk_prices"] as $bulk_price) {
                    if ($entity->getQty() >= floatval($bulk_price["min_qty"]) && $entity->getQty() <= floatval($bulk_price["max_qty"])) {

                        $osnovicaStavkeZaIzracun = $bulk_price["bulk_price_item"];
                        $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun * $qty,2);

                        /**
                         * Postavi postotak popusta
                         */
                        $appliedDiscountPercent = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun)/$entity->getOriginalBasePriceItem() * 100;

                        /**
                         * Izracunaj iznos popusta na stavci i totalu
                         */
                        $basePriceDiscountItem = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun);
                        $basePriceDiscountTotal = $entity->getOriginalBasePriceTotal()-$osnovicaTotalaZaIzracun;

                        $bulkDiscountApplied = true;

                        break;
                    }
                }
            }
        }

        /**
         * Apply discount cart rules
         */
        /**
         * Postavljanje popusta na košaricu
         * Ne primjenjuje se ako je vec primjenjen popust po grupi kupca
         * Ne primjenjuje se ako je na kosarici disejblan cart rules
         * Ne primjenjuje se ako je proizvod dodan kao dio bundle-a
         * Ne primjenjuje se ako je proizvodu vec primjenjen bulk popust
         */
        if((!isset($prices["discount_type"]) || empty($prices["discount_type"]) || $prices["discount_type"] != "discount_account_group") && !$entity->getQuote()->getDisableCartRule() && !$bundleDiscountApplied && !$bulkDiscountApplied && !$manualDiscountApplied){

            if(empty($this->productAttributeFilterRulesManager)){
                $this->productAttributeFilterRulesManager = $this->container->get("product_attribute_filter_rules_manager");
            }

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("isApplied", "eq", 1));
            $compositeFilter->addFilter(new SearchFilter("discountPercent", "gt", 0));

            $sortFilters = new SortFilterCollection();
            $sortFilters->addSortFilter(new SortFilter("priority", "desc"));

            $rules = $this->productAttributeFilterRulesManager->getRulesByEntityTypeCode("discount_cart_rule",$compositeFilter,$sortFilters);

            if(EntityHelper::isCountable($rules) && count($rules)){

                if(empty($this->databaseContext)){
                    $this->databaseContext = $this->container->get("database_context");
                }

                /** @var DiscountCartRuleEntity $rule */
                foreach ($rules as $rule){

                    /**
                     * Check if has excluded payment type
                     */
                    if(EntityHelper::isCountable($rule->getExcludedPaymentTypes()) && count($rule->getExcludedPaymentTypes())){
                        if(empty($entity->getQuote()->getPaymentType())){
                            continue;
                        }

                        $hasExcludedPaymentType = false;

                        /** @var PaymentTypeEntity $paymentType */
                        foreach ($rule->getExcludedPaymentTypes() as $paymentType){

                            if($entity->getQuote()->getPaymentType()->getId() == $paymentType->getId()){
                                $hasExcludedPaymentType = true;
                                break;
                            }
                        }

                        if($hasExcludedPaymentType){
                            continue;
                        }
                    }

                    /**
                     * Na koga se primjenjuje
                     * 1. na sve
                     * 2. samo sve registrirane kupce
                     * 3. samo na registrirane kupce koji nisu pravne osobe
                     * 4. samo na registrirane kupce koji jesu pravne osobe
                     */
                    if(!empty($rule->getDiscountAppliedTo()) && $rule->getDiscountAppliedTo()->getId() != CrmConstants::SVI_KUPCI){

                        if(in_array($rule->getDiscountAppliedTo()->getId(),Array(CrmConstants::SAMO_REGISTRIRANI_KUPCI,CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_JESU_PRAVNE_OSOBE,CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_NISU_PRAVNE_OSOBE))){

                            if(empty($entity->getQuote()->getAccount()) || empty($entity->getQuote()->getContact()) || empty($entity->getQuote()->getContact()->getCoreUser())){
                                continue;
                            }

                            if($rule->getDiscountAppliedTo()->getId() == CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_JESU_PRAVNE_OSOBE){
                                if(!$entity->getQuote()->getAccount()->getIsLegalEntity()){
                                    continue;
                                }
                            }
                            elseif($rule->getDiscountAppliedTo()->getId() == CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_NISU_PRAVNE_OSOBE){
                                if($entity->getQuote()->getAccount()->getIsLegalEntity()){
                                    continue;
                                }
                            }
                        }
                    }

                    if(floatval($rule->getMinPrice()) > 0){

                        $q = "SELECT SUM(original_base_price_item * qty) AS count FROM quote_item_entity WHERE quote_id = {$entity->getQuote()->getId()} AND id != {$entity->getId()};";
                        $total = floatval($this->databaseContext->getSingleResult($q));

                        $total = $total + (floatval($entity->getOriginalBasePriceItem()) * $qty);

                        if($total < floatval($rule->getMinPrice())){
                            continue;
                        }
                    }

                    /**
                     * Check if product in rule
                     * if not go to next rule
                     */
                    if(!$this->productAttributeFilterRulesManager->productMatchesRules($entity->getProduct(),$rule->getRules(),$rule)){
                        continue;
                    }

                    $entity->setAppliedDiscountCartRule($rule);


                    /**
                     * Postavi discount percent
                     */
                    $appliedDiscountCartRulePercent = floatval($rule->getDiscountPercent());

                    $osnovicaStavkeZaIzracun = round($entity->getOriginalBasePriceItem()*(100-$appliedDiscountCartRulePercent)/100,2);
                    $osnovicaTotalaZaIzracun = $osnovicaStavkeZaIzracun*$qty;

                    /**
                     * Izracunaj iznos popusta na stavci i totalu
                     */
                    $basePriceDiscountCartRuleItem = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun);
                    $basePriceDiscountCartRuleTotal = $entity->getOriginalBasePriceTotal()-$osnovicaTotalaZaIzracun;

                    /**
                     * Ocisti visak postotaka popusta
                     */
                    $appliedDiscountPercent = 0;
                    $entity->setAppliedDiscountCatalogRule(null);

                    $cartRuleDiscountApplied = true;

                    break;
                }
            }
        }

        /**
         * Dohvat postotka kuponskog koda
         */
        /** @var DiscountCouponEntity $discountCoupon */
        $discountCoupon = $entity->getQuote()->getDiscountCoupon();
        $discountCouponPercent = 0;

        if (!empty($discountCoupon) && !$bulkDiscountApplied && !$manualDiscountApplied) {
            $discountCouponPercent = floatval($this->crmProcessManager->getApplicableDiscountCouponPercentForProduct($discountCoupon, $entity->getProduct(), $parentProduct, $account, $prices, $entity->getQuote()->getBasePriceTotal()));
        }

        /**
         * Primjena kuponskog popusta
         * Ne primjenjuje se ako je proizvod dodan kao dio bundle-a
         * Ne primjenjuje se ako je proizvodu vec primjenjen bulk popust
         */
        if ($discountCouponPercent > 0) {

            $appliedDiscountCouponPercent = $discountCouponPercent;
            $couponDiscountApplied = true;

            /**
             * Primjeni na snizenu cijenu
             */
            if ($discountCoupon->getApplicationTypeId() == CrmConstants::DISCOUNT_COUPON_APPLY_ON_DISCOUNT_PRICE) {

                /**
                 * Iznos sa uracunatim popustom
                 */
                $basePriceDiscountCouponItem = $osnovicaStavkeZaIzracun * $appliedDiscountCouponPercent / 100;
                $basePriceDiscountCouponTotal = $osnovicaTotalaZaIzracun;

                /**
                 * TRS - Ostavljamo staru varijantu da lako vratimo
                 */
                $osnovicaStavkeZaIzracun = round($osnovicaStavkeZaIzracun*(100-$appliedDiscountCouponPercent)/100,2);
                $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun*$qty,2);

                $basePriceDiscountCouponTotal = $basePriceDiscountCouponTotal - $osnovicaTotalaZaIzracun;
            }
            /**
             * Primjeni veci popust izmedju snizene cijene i kupona
             */
            elseif ($discountCoupon->getApplicationTypeId() == CrmConstants::DISCOUNT_COUPON_APPLY_ON_ORIGINAL_PRICE || $discountCoupon->getApplicationTypeId() == CrmConstants::DISCOUNT_COUPON_APPLY_BIGGER_DISCOUNT) {

                /**
                 * Ukloni popust i izracunaj ponovo base price
                 */
                $basePriceDiscountCouponItem = $entity->getOriginalBasePriceItem() * $appliedDiscountCouponPercent / 100;
                $basePriceDiscountCouponTotal = $entity->getOriginalBasePriceTotal();

                /**
                 * Iznos sa uracunatim popustom
                 */
                $osnovicaStavkeZaIzracun = round($entity->getOriginalBasePriceItem()*(100-$appliedDiscountCouponPercent)/100,2);
                $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun*$qty,2);

                $basePriceDiscountCouponTotal = $basePriceDiscountCouponTotal - $osnovicaTotalaZaIzracun;

                /**
                 * Ocisti visak postotaka popusta
                 */
                $entity->setAppliedDiscountCatalogRule(null);
                $entity->setAppliedDiscountCartRule(null);
                $appliedDiscountPercent = 0;
                $appliedDiscountCartRulePercent = 0;
            }
            /**
             * Primjeni na osnovnu cijenu proizvoda
             */
            else {
                /**
                 * Ukloni popust i izracunaj ponovo base price
                 */
                $basePriceDiscountCouponItem = $entity->getOriginalBasePriceItem() * $appliedDiscountCouponPercent / 100;
                $basePriceDiscountCouponTotal = $entity->getOriginalBasePriceTotal();

                /**
                 * Iznos sa uracunatim popustom
                 */
                $osnovicaStavkeZaIzracun = round($entity->getOriginalBasePriceItem()*(100-$appliedDiscountCouponPercent)/100,2);
                $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun*$qty,2);

                $basePriceDiscountCouponTotal = $basePriceDiscountCouponTotal - $osnovicaTotalaZaIzracun;

                /**
                 * Ocisti visak postotaka popusta
                 */
                $entity->setAppliedDiscountCatalogRule(null);
                $entity->setAppliedDiscountCartRule(null);
                $appliedDiscountPercent = 0;
                $appliedDiscountCartRulePercent = 0;
            }
        }

        /**
         * Primjena loyalty popusta
         */
        if (!empty($entity->getQuote()->getLoyaltyCard()) && !$entity->getProduct()->getDisableLoyaltyDiscount() && floatval($entity->getQuote()->getLoyaltyCard()->getPercentDiscount()) > 0 && !$bundleDiscountApplied && !$bulkDiscountApplied && !$catalogDiscountApplied && !$cartRuleDiscountApplied && !$couponDiscountApplied && !$manualDiscountApplied) {

            $discountLoyaltyPercent = floatval($entity->getQuote()->getLoyaltyCard()->getPercentDiscount());

            $appliedDiscountLoyaltyPercent = $discountLoyaltyPercent;

            $basePriceDiscountLoyaltyItem = $osnovicaStavkeZaIzracun * $appliedDiscountLoyaltyPercent / 100;
            $basePriceDiscountLoyaltyTotal = $osnovicaTotalaZaIzracun;

            /**
             * Iznos sa uracunatim popustom
             */
            $osnovicaStavkeZaIzracun = round($osnovicaStavkeZaIzracun*(100-$appliedDiscountLoyaltyPercent)/100,2);
            $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun*$qty,2);

            $basePriceDiscountLoyaltyTotal = $basePriceDiscountLoyaltyTotal - $osnovicaTotalaZaIzracun;

            /**
             * Ocisti visak postotaka popusta
             */
            $appliedDiscountCouponPercent = 0;
            $appliedDiscountCartRulePercent = 0;
        }

        $entity->setPercentageDiscount($appliedDiscountPercent);
        $entity->setPercentageLoyalty($appliedDiscountLoyaltyPercent);
        $entity->setPercentageDiscountCoupon($appliedDiscountCouponPercent);
        $entity->setPercentageDiscountCartRule($appliedDiscountCartRulePercent);

        /**
         * Apsolutni iznosi popusta
         */
        $entity->setBasePriceDiscountItem(round($basePriceDiscountItem,2));
        $entity->setBasePriceDiscountTotal(round($basePriceDiscountTotal,2));

        $entity->setBasePriceDiscountCartRuleItem(round($basePriceDiscountCartRuleItem,2));
        $entity->setBasePriceDiscountCartRuleTotal(round($basePriceDiscountCartRuleTotal,2));

        $entity->setBasePriceDiscountCouponItem(round($basePriceDiscountCouponItem,2));
        $entity->setBasePriceDiscountCouponTotal(round($basePriceDiscountCouponTotal,2));

        $entity->setBasePriceLoyaltyDiscountItem(round($basePriceDiscountLoyaltyItem,2));
        $entity->setBasePriceLoyaltyDiscountTotal(round($basePriceDiscountLoyaltyTotal,2));

        /**
         * Jedini spas je da ovdje uguramo cijene prije popuzsta
         */

        $entity->setOriginalRebate($prices["rebate"]);

        /**
         * Uracunati popusti
         */
        $entity->setBasePriceItemWithoutTax(round($osnovicaStavkeZaIzracun*100/(100+$taxPercent),6));
        $entity->setBasePriceItemTax(round($osnovicaStavkeZaIzracun*$taxPercent/(100+$taxPercent),6));
        $entity->setBasePriceItem(round($osnovicaStavkeZaIzracun,6));

        $entity->setBasePriceWithoutTax(round($osnovicaTotalaZaIzracun*100/(100+$taxPercent),6));
        $entity->setBasePriceTax(round($osnovicaTotalaZaIzracun*$taxPercent/(100+$taxPercent),6));
        $entity->setBasePriceTotal(round($osnovicaTotalaZaIzracun,6));

        $entity->setOriginalPriceItemWithoutTax($entity->getOriginalBasePriceItemWithoutTax() / $currencyRate);
        $entity->setOriginalPriceItemTax($entity->getOriginalBasePriceItemTax() / $currencyRate);
        $entity->setOriginalPriceItem($entity->getOriginalBasePriceItem() / $currencyRate);

        $entity->setPriceItemWithoutTax($entity->getBasePriceItemWithoutTax() / $currencyRate);
        $entity->setPriceItemTax($entity->getBasePriceItemTax() / $currencyRate);
        $entity->setPriceItem($entity->getBasePriceItem() / $currencyRate);

        $entity->setPriceWithoutTax($entity->getBasePriceWithoutTax()  / $currencyRate);
        $entity->setPriceTax($entity->getBasePriceTax()  / $currencyRate);

        $entity->setPriceItemReturn(floatval($prices["return_price"]));
        $entity->setPriceReturnTotal(floatval($prices["return_price"]) * $qty);

        $entity->setPriceTotal($entity->getBasePriceTotal() + $entity->getPriceReturnTotal());

        $entity->setCalculationPrices(json_encode($prices));

        return $entity;
    }



    /**
     * @param OrderItemEntity $entity
     * @param $currencyRate
     * @param $prices
     * @param AccountEntity|null $account
     * @param $parentProduct
     * @return OrderItemEntity
     * @throws \Exception
     */
    public function calculationOrderItemMpc(OrderItemEntity $entity, $currencyRate, $prices, AccountEntity $account = null, $parentProduct = null){

        $taxPercent = $entity->getProduct()->getTaxType()->getPercent();

        /**
         * Popis postotaka koji se sprema u bazu i izracunatih popusta u apsolutnom iznosu
         */
        $appliedDiscountPercent = 0;
        $basePriceDiscountItem = 0;
        $basePriceDiscountTotal = 0;

        $appliedDiscountCartRulePercent = 0;
        $basePriceDiscountCartRuleItem = 0;
        $basePriceDiscountCartRuleTotal = 0;

        $appliedDiscountCouponPercent = 0;
        $basePriceDiscountCouponItem = 0;
        $basePriceDiscountCouponTotal = 0;

        $appliedDiscountLoyaltyPercent = 0;
        $basePriceDiscountLoyaltyItem = 0;
        $basePriceDiscountLoyaltyTotal = 0;

        $entity->setAppliedDiscountCatalogRule(null);
        $entity->setAppliedDiscountCartRule(null);

        $qty = floatval($entity->getQty());

        /**
         * Applied discounts list
         */
        $catalogDiscountApplied = false;
        $bundleDiscountApplied = false;
        $bulkDiscountApplied = false;
        $couponDiscountApplied = false;
        $cartRuleDiscountApplied = false;
        $manualDiscountApplied = false;

        /**
         * Samo kada admin kroz kreiranje ponude postavi neku fiksnu cijenu proizvoda onda se ta cijena koristi kao osnovica za sve
         * Uklanjamo bulk price
         */
        if(floatval($entity->getBasePriceFixedDiscount()) > 0){
            $prices["price"] = floatval($entity->getBasePriceFixedDiscount());
            $prices["discount_price"] = null;
            $prices["bulk_prices"] = null;
        }

        /**
         * Osnovica stavke za izracun
         */
        $osnovicaStavkeZaIzracun = floatval($prices["price"]);

        /**
         * Override da je poklon uvijek 0
         */
        if ($entity->getIsGift()) {
            $osnovicaStavkeZaIzracun = 0;
        }

        $entity->setOriginalBasePriceItemWithoutTax(round($osnovicaStavkeZaIzracun*100/(100+$taxPercent),6));
        $entity->setOriginalBasePriceItemTax(round($osnovicaStavkeZaIzracun*$taxPercent/(100+$taxPercent),6));
        $entity->setOriginalBasePriceItem(round($osnovicaStavkeZaIzracun,2));

        $originalBasePriceTotal = round($osnovicaStavkeZaIzracun * $qty,2);

        $entity->setOriginalBasePriceWithoutTax(round($originalBasePriceTotal*100/(100+$taxPercent),6));
        $entity->setOriginalBasePriceTax(round($originalBasePriceTotal*$taxPercent/(100+$taxPercent),6));
        $entity->setOriginalBasePriceTotal(round($originalBasePriceTotal,2));

        /**
         * Ako je dio bundlea primjeni taj popust
         */
        if ($entity->getIsPartOfBundle()){
            if(floatval($prices["discount_price"]) > 0){
                $osnovicaStavkeZaIzracun = round(floatval($prices["discount_price"]),2);
            }

            /**
             * Izracunaj postotak popusta
             */
            $appliedDiscountPercent = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun)/$entity->getOriginalBasePriceItem() * 100;
            /**
             * Izracunaj iznos popusta na stavci i totalu
             */
            $basePriceDiscountItem = $entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun;
            $basePriceDiscountTotal = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun)*$qty;

            $bundleDiscountApplied = true;
        }

        /**
         * Primjena popusta na katalog ili popusta sa proizvoda
         * Ako je bundle preskoci primjenu
         */
        if (floatval($prices["discount_price"]) > 0 && !$bundleDiscountApplied) {

            $osnovicaStavkeZaIzracun = floatval($prices["discount_price"]);
            if(isset($prices["discount_catalog_rule_id"]) && !empty($prices["discount_catalog_rule_id"])){
                if(empty($this->discountRulesManager)){
                    $this->discountRulesManager = $this->container->get("discount_rules_manager");
                }
                $entity->setAppliedDiscountCatalogRule($this->discountRulesManager->getDiscountCatalogById($prices["discount_catalog_rule_id"]));
            }
            $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun*$qty,2);

            /**
             * Izracunaj postotak popusta
             */
            $appliedDiscountPercent = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun)/$entity->getOriginalBasePriceItem() * 100;

            /**
             * Izracunaj iznos popusta na stavci i totalu
             */
            $basePriceDiscountItem = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun);
            $basePriceDiscountTotal = $entity->getOriginalBasePriceTotal()-$osnovicaTotalaZaIzracun;

            $catalogDiscountApplied = true;
        }

        /**
         * Ako je admin postavio % popusta kroz kreiranje ponude onda pregazi postojeci postotak popusta
         */
        if (floatval($entity->getPercentageDiscountFixed()) > 0) {
            $appliedDiscountPercent = floatval($entity->getPercentageDiscountFixed());

            /**
             * Iznos sa uracunatim popustom
             */
            $osnovicaStavkeZaIzracun = round($entity->getOriginalBasePriceItem()*(100-$appliedDiscountPercent)/100,2);
            $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun*$qty,2);

            /**
             * Izracunaj iznos popusta na stavci i totalu
             */
            $basePriceDiscountItem = $entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun;
            $basePriceDiscountTotal = $entity->getOriginalBasePriceTotal()-$osnovicaTotalaZaIzracun;

            $manualDiscountApplied = true;

            /**
             * Ocisti visak popusta
             */
            $bundleDiscountApplied = false;
            $catalogDiscountApplied = false;
            $entity->setAppliedDiscountCatalogRule(null);
            $prices["bulk_prices"] = null;
        }

        /**
         * Osnovica stavke za izracun * kolicina
         */
        $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun * $qty,2);

        /**
         * Primjena bulk popusta
         * Ako je bundle preskoci primjenu
         */
        if (isset($prices["bulk_prices"]) && !$bundleDiscountApplied) {

            /**
             * For 1+1 free
             */
            if (isset($prices["bulk_prices"][0]["bulk_price_type"]) && $prices["bulk_prices"][0]["bulk_price_type"] == 2) {
                $bulk_price = $prices["bulk_prices"][0];

                if ($qty >= floatval($bulk_price["min_qty"])) {

                    $step = floatval($bulk_price["min_qty"]);
                    $times = floor($qty / $step);

                    /**
                     * Varijanta 2
                     */
                    $osnovicaTotalaZaIzracun = ($qty * $osnovicaStavkeZaIzracun) - ($times * $osnovicaStavkeZaIzracun);

                    $osnovicaStavkeZaIzracun = round($osnovicaTotalaZaIzracun / $qty,2);

                    $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun * $qty,2);

                    /**
                     * Postavi postotak popusta
                     */
                    $appliedDiscountPercent = floatval($bulk_price["bulk_price_percentage"]);

                    /**
                     * Izracunaj iznos popusta na stavci i totalu
                     */
                    $basePriceDiscountItem = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun);
                    $basePriceDiscountTotal = $entity->getOriginalBasePriceTotal() - $osnovicaTotalaZaIzracun;

                    $bulkDiscountApplied = true;
                }
            }
            /**
             * For standard bulk price items
             */
            else {
                foreach ($prices["bulk_prices"] as $bulk_price) {
                    if ($entity->getQty() >= floatval($bulk_price["min_qty"]) && $entity->getQty() <= floatval($bulk_price["max_qty"])) {

                        $osnovicaStavkeZaIzracun = $bulk_price["bulk_price_item"];
                        $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun * $qty,2);

                        /**
                         * Postavi postotak popusta
                         */
                        $appliedDiscountPercent = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun)/$entity->getOriginalBasePriceItem() * 100;

                        /**
                         * Izracunaj iznos popusta na stavci i totalu
                         */
                        $basePriceDiscountItem = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun);
                        $basePriceDiscountTotal = $entity->getOriginalBasePriceTotal() - $osnovicaTotalaZaIzracun;

                        $bulkDiscountApplied = true;

                        break;
                    }
                }
            }
        }

        /**
         * Apply discount cart rules
         */
        /**
         * Postavljanje popusta na košaricu
         * Ne primjenjuje se ako je vec primjenjen popust po grupi kupca
         * Ne primjenjuje se ako je na kosarici disejblan cart rules
         * Ne primjenjuje se ako je proizvod dodan kao dio bundle-a
         * Ne primjenjuje se ako je proizvodu vec primjenjen bulk popust
         */
        if((!isset($prices["discount_type"]) || empty($prices["discount_type"]) || $prices["discount_type"] != "discount_account_group") && !$bundleDiscountApplied && !$bulkDiscountApplied && !$manualDiscountApplied){

            if(empty($this->productAttributeFilterRulesManager)){
                $this->productAttributeFilterRulesManager = $this->container->get("product_attribute_filter_rules_manager");
            }

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("isApplied", "eq", 1));
            $compositeFilter->addFilter(new SearchFilter("discountPercent", "gt", 0));

            $sortFilters = new SortFilterCollection();
            $sortFilters->addSortFilter(new SortFilter("priority", "desc"));

            $rules = $this->productAttributeFilterRulesManager->getRulesByEntityTypeCode("discount_cart_rule",$compositeFilter,$sortFilters);

            if(EntityHelper::isCountable($rules) && count($rules)){

                if(empty($this->databaseContext)){
                    $this->databaseContext = $this->container->get("database_context");
                }

                /** @var DiscountCartRuleEntity $rule */
                foreach ($rules as $rule){

                    /**
                     * Check if has excluded payment type
                     */
                    if(EntityHelper::isCountable($rule->getExcludedPaymentTypes()) && count($rule->getExcludedPaymentTypes())){
                        if(empty($entity->getOrder()->getPaymentType())){
                            continue;
                        }

                        $hasExcludedPaymentType = false;

                        /** @var PaymentTypeEntity $paymentType */
                        foreach ($rule->getExcludedPaymentTypes() as $paymentType){

                            if($entity->getOrder()->getPaymentType()->getId() == $paymentType->getId()){
                                $hasExcludedPaymentType = true;
                                break;
                            }
                        }

                        if($hasExcludedPaymentType){
                            continue;
                        }
                    }

                    /**
                     * Na koga se primjenjuje
                     * 1. na sve
                     * 2. samo sve registrirane kupce
                     * 3. samo na registrirane kupce koji nisu pravne osobe
                     * 4. samo na registrirane kupce koji jesu pravne osobe
                     */
                    if(!empty($rule->getDiscountAppliedTo()) && $rule->getDiscountAppliedTo()->getId() != CrmConstants::SVI_KUPCI){

                        if(in_array($rule->getDiscountAppliedTo()->getId(),Array(CrmConstants::SAMO_REGISTRIRANI_KUPCI,CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_JESU_PRAVNE_OSOBE,CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_NISU_PRAVNE_OSOBE))){

                            if(empty($entity->getOrder()->getAccount()) || empty($entity->getOrder()->getContact()) || empty($entity->getOrder()->getContact()->getCoreUser())){
                                continue;
                            }

                            if($rule->getDiscountAppliedTo()->getId() == CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_JESU_PRAVNE_OSOBE){
                                if(!$entity->getOrder()->getAccount()->getIsLegalEntity()){
                                    continue;
                                }
                            }
                            elseif($rule->getDiscountAppliedTo()->getId() == CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_NISU_PRAVNE_OSOBE){
                                if($entity->getOrder()->getAccount()->getIsLegalEntity()){
                                    continue;
                                }
                            }
                        }
                    }

                    if(floatval($rule->getMinPrice()) > 0){

                        $q = "SELECT SUM(original_base_price_item * qty) AS count FROM order_item_entity WHERE order_id = {$entity->getOrder()->getId()} AND id != {$entity->getId()};";
                        $total = floatval($this->databaseContext->getSingleResult($q));

                        $total = $total + (floatval($entity->getOriginalBasePriceItem()) * $qty);

                        if($total < floatval($rule->getMinPrice())){
                            continue;
                        }
                    }

                    /**
                     * Check if product in rule
                     * if not go to next rule
                     */
                    if(!$this->productAttributeFilterRulesManager->productMatchesRules($entity->getProduct(),$rule->getRules(),$rule)){
                        continue;
                    }

                    $entity->setAppliedDiscountCartRule($rule);


                    /**
                     * Postavi discount percent
                     */
                    $appliedDiscountCartRulePercent = floatval($rule->getDiscountPercent());

                    $osnovicaStavkeZaIzracun = round($entity->getOriginalBasePriceItem()*(100-$appliedDiscountCartRulePercent)/100,2);
                    $osnovicaTotalaZaIzracun = $osnovicaStavkeZaIzracun*$qty;

                    /**
                     * Izracunaj iznos popusta na stavci i totalu
                     */
                    $basePriceDiscountCartRuleItem = ($entity->getOriginalBasePriceItem()-$osnovicaStavkeZaIzracun);
                    $basePriceDiscountCartRuleTotal = $entity->getOriginalBasePriceTotal()-$osnovicaTotalaZaIzracun;

                    /**
                     * Ocisti visak postotaka popusta
                     */
                    $appliedDiscountPercent = 0;
                    $entity->setAppliedDiscountCatalogRule(null);

                    $cartRuleDiscountApplied = true;

                    break;
                }
            }
        }

        /**
         * Dohvat postotka kuponskog koda
         */
        /** @var DiscountCouponEntity $discountCoupon */
        $discountCoupon = $entity->getOrder()->getDiscountCoupon();
        $discountCouponPercent = 0;

        if (!empty($discountCoupon) && !$bulkDiscountApplied && !$manualDiscountApplied) {
            $discountCouponPercent = floatval($this->crmProcessManager->getApplicableDiscountCouponPercentForProduct($discountCoupon, $entity->getProduct(), $parentProduct, $account, $prices, $entity->getOrder()->getBasePriceTotal()));
        }

        /**
         * Primjena kuponskog popusta
         * Ne primjenjuje se ako je proizvod dodan kao dio bundle-a
         * Ne primjenjuje se ako je proizvodu vec primjenjen bulk popust
         */
        if ($discountCouponPercent > 0) {

            $appliedDiscountCouponPercent = $discountCouponPercent;
            $couponDiscountApplied = true;

            /**
             * Primjeni na snizenu cijenu
             */
            if ($discountCoupon->getApplicationTypeId() == CrmConstants::DISCOUNT_COUPON_APPLY_ON_DISCOUNT_PRICE) {

                /**
                 * Iznos sa uracunatim popustom
                 */
                $basePriceDiscountCouponItem = $osnovicaStavkeZaIzracun * $appliedDiscountCouponPercent / 100;
                $basePriceDiscountCouponTotal = $osnovicaTotalaZaIzracun;

                /**
                 * TRS - Ostavljamo staru varijantu da lako vratimo
                 */
                $osnovicaStavkeZaIzracun = round($osnovicaStavkeZaIzracun*(100-$appliedDiscountCouponPercent)/100,2);
                $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun*$qty,2);

                $basePriceDiscountCouponTotal = $basePriceDiscountCouponTotal - $osnovicaTotalaZaIzracun;
            }
            /**
             * Primjeni veci popust izmedju snizene cijene i kupona
             */
            elseif ($discountCoupon->getApplicationTypeId() == CrmConstants::DISCOUNT_COUPON_APPLY_ON_ORIGINAL_PRICE || $discountCoupon->getApplicationTypeId() == CrmConstants::DISCOUNT_COUPON_APPLY_BIGGER_DISCOUNT) {

                /**
                 * Ukloni popust i izracunaj ponovo base price
                 */
                $basePriceDiscountCouponItem = $entity->getOriginalBasePriceItem() * $appliedDiscountCouponPercent / 100;
                $basePriceDiscountCouponTotal = $entity->getOriginalBasePriceTotal();

                /**
                 * Iznos sa uracunatim popustom
                 */
                $osnovicaStavkeZaIzracun = round($entity->getOriginalBasePriceItem()*(100-$appliedDiscountCouponPercent)/100,2);
                $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun*$qty,2);

                $basePriceDiscountCouponTotal = $basePriceDiscountCouponTotal - $osnovicaTotalaZaIzracun;

                /**
                 * Ocisti visak postotaka popusta
                 */
                $entity->setAppliedDiscountCatalogRule(null);
                $entity->setAppliedDiscountCartRule(null);
                $appliedDiscountPercent = 0;
                $appliedDiscountCartRulePercent = 0;
            }
            /**
             * Primjeni na osnovnu cijenu proizvoda
             */
            else {
                /**
                 * Ukloni popust i izracunaj ponovo base price
                 */
                $basePriceDiscountCouponItem = $entity->getOriginalBasePriceItem() * $appliedDiscountCouponPercent / 100;
                $basePriceDiscountCouponTotal = $entity->getOriginalBasePriceTotal();

                /**
                 * Iznos sa uracunatim popustom
                 */
                $osnovicaStavkeZaIzracun = round($entity->getOriginalBasePriceItem()*(100-$appliedDiscountCouponPercent)/100,2);
                $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun*$qty,2);

                $basePriceDiscountCouponTotal = $basePriceDiscountCouponTotal - $osnovicaTotalaZaIzracun;

                /**
                 * Ocisti visak postotaka popusta
                 */
                $entity->setAppliedDiscountCatalogRule(null);
                $entity->setAppliedDiscountCartRule(null);
                $appliedDiscountPercent = 0;
                $appliedDiscountCartRulePercent = 0;
            }
        }

        /**
         * Primjena loyalty popusta
         */
        if (!empty($entity->getOrder()->getLoyaltyCard()) && !$entity->getProduct()->getDisableLoyaltyDiscount() && floatval($entity->getOrder()->getLoyaltyCard()->getPercentDiscount()) > 0 && !$bundleDiscountApplied && !$bulkDiscountApplied && !$catalogDiscountApplied && !$cartRuleDiscountApplied && !$couponDiscountApplied && !$manualDiscountApplied) {

            $discountLoyaltyPercent = floatval($entity->getOrder()->getLoyaltyCard()->getPercentDiscount());

            $appliedDiscountLoyaltyPercent = $discountLoyaltyPercent;

            $basePriceDiscountLoyaltyItem = $osnovicaStavkeZaIzracun * $appliedDiscountLoyaltyPercent / 100;
            $basePriceDiscountLoyaltyTotal = $osnovicaTotalaZaIzracun;

            /**
             * Iznos sa uracunatim popustom
             */
            $osnovicaStavkeZaIzracun = round($osnovicaStavkeZaIzracun*(100-$appliedDiscountLoyaltyPercent)/100,2);
            $osnovicaTotalaZaIzracun = round($osnovicaStavkeZaIzracun*$qty,2);

            $basePriceDiscountLoyaltyTotal = $basePriceDiscountLoyaltyTotal - $osnovicaTotalaZaIzracun;

            /**
             * Ocisti visak postotaka popusta
             */
            $appliedDiscountCouponPercent = 0;
            $appliedDiscountCartRulePercent = 0;
        }

        $entity->setPercentageDiscount($appliedDiscountPercent);
        $entity->setPercentageLoyalty($appliedDiscountLoyaltyPercent);
        $entity->setPercentageDiscountCoupon($appliedDiscountCouponPercent);
        $entity->setPercentageDiscountCartRule($appliedDiscountCartRulePercent);

        /**
         * Apsolutni iznosi popusta
         */
        $entity->setBasePriceDiscountItem(round($basePriceDiscountItem,2));
        $entity->setBasePriceDiscountTotal(round($basePriceDiscountTotal,2));

        $entity->setBasePriceDiscountCartRuleItem(round($basePriceDiscountCartRuleItem,2));
        $entity->setBasePriceDiscountCartRuleTotal(round($basePriceDiscountCartRuleTotal,2));

        $entity->setBasePriceDiscountCouponItem(round($basePriceDiscountCouponItem,2));
        $entity->setBasePriceDiscountCouponTotal(round($basePriceDiscountCouponTotal,2));

        $entity->setBasePriceLoyaltyDiscountItem(round($basePriceDiscountLoyaltyItem,2));
        $entity->setBasePriceLoyaltyDiscountTotal(round($basePriceDiscountLoyaltyTotal,2));

        /**
         * Jedini spas je da ovdje uguramo cijene prije popuzsta
         */

        $entity->setOriginalRebate($prices["rebate"]);

        /**
         * Uracunati popusti
         */
        $entity->setBasePriceItemWithoutTax(round($osnovicaStavkeZaIzracun*100/(100+$taxPercent),6));
        $entity->setBasePriceItemTax(round($osnovicaStavkeZaIzracun*$taxPercent/(100+$taxPercent),6));
        $entity->setBasePriceItem(round($osnovicaStavkeZaIzracun,6));

        $entity->setBasePriceWithoutTax(round($osnovicaTotalaZaIzracun*100/(100+$taxPercent),6));
        $entity->setBasePriceTax(round($osnovicaTotalaZaIzracun*$taxPercent/(100+$taxPercent),6));
        $entity->setBasePriceTotal(round($osnovicaTotalaZaIzracun,6));

        $entity->setOriginalPriceItemWithoutTax($entity->getOriginalBasePriceItemWithoutTax() / $currencyRate);
        $entity->setOriginalPriceItemTax($entity->getOriginalBasePriceItemTax() / $currencyRate);
        $entity->setOriginalPriceItem($entity->getOriginalBasePriceItem() / $currencyRate);

        $entity->setPriceItemWithoutTax($entity->getBasePriceItemWithoutTax() / $currencyRate);
        $entity->setPriceItemTax($entity->getBasePriceItemTax() / $currencyRate);
        $entity->setPriceItem($entity->getBasePriceItem() / $currencyRate);

        $entity->setPriceWithoutTax($entity->getBasePriceWithoutTax()  / $currencyRate);
        $entity->setPriceTax($entity->getBasePriceTax()  / $currencyRate);

        $entity->setPriceItemReturn(floatval($prices["return_price"]));
        $entity->setPriceReturnTotal(floatval($prices["return_price"]) * $qty);

        $entity->setPriceTotal($entity->getBasePriceTotal() + $entity->getPriceReturnTotal());

        return $entity;
    }

    /**
     * GET PRICES
     */

    /**
     * @param ProductEntity $product
     * @param AccountEntity|null $account
     * @param ProductEntity|null $parentProduct
     * @param $includeReturnPrice
     * @return array|mixed
     */
    public function getProductPricesVpc(ProductEntity $product, AccountEntity $account = null, ProductEntity $parentProduct = null, $includeReturnPrice = true){

        $session = $this->container->get('session');

        $ret = array();

        /** Osnovna cijena */
        $ret["price"] = null;
        /** Osnovna cijena sa PDV */
        $ret["price_other"] = null;
        /** Cijena sa popustom */
        $ret["discount_price"] = null;
        /** Cijena sa popustom sa PDV */
        $ret["discount_price_other"] = null;
        /** Postotak popusta */
        $ret["discount_percentage"] = null;
        /** Povratna naknada */
        $ret["return_price"] = null;
        /** Najniza cijena u zadnjih 30 dana */
        $ret["lowest_price"] = null;
        /** Finalna cijena koja ukljucuje povratnu naknadu */
        $ret["final_price"] = null;
        /** Ako je proizvod na popustu tu ce pisati kojeg je tipa popust, product, katalosti, po accountu, po account grupi */
        $ret["discount_type"] = "";
        /** AKO discount cijena dolazi sa grupe, tu ce pisati sa koje grupe */
        $ret["discount_account_group_id"] = null;
        $ret["discount_catalog_rule_id"] = null;
        $ret["currency_code"] = $product->getCurrency()->getSign();
        $ret["currency_short_code"] = $product->getCurrency()->getCode();
        $ret["rebate"] = null;
        $ret["calculation_type"] = "Vpc";

        $taxPercent = floatval($product->getTaxType()->getPercent());

        $ret["return_price"] = floatval($product->getPriceReturn());

        $now = new \DateTime();

        /*if($forceAccountFromSession && !$session->get("disable_force_account_from_session")){
            $account = $session->get('account');
        }
        else*/
        if(empty($account)){
            /** @var AccountEntity $account */
            $account = $session->get('account');
        }

        $ret["vat_type"] = "without VAT";
        $ret["is_legal_entity"] = false;
        $disableDiscounts = false;
        $disableRebate = false;
        $accountGroup = null;
        if (!empty($account)) {
            $ret["is_legal_entity"] = $account->getIsLegalEntity();
            $disableDiscounts = $account->getDisableDiscounts();
            $disableRebate = $account->getDisableRebate();
            $accountGroup = $account->getAccountGroup();
        }

        $ret["price"] = floatval($product->getPriceBase());

        $isPartOfBundle = false;
        if(!empty($parentProduct) && $parentProduct->getProductTypeId() == CrmConstants::PRODUCT_TYPE_BUNDLE){
            $isPartOfBundle = true;
        }

        $discountStartDate = null;

        /**
         * Check discounts
         */
        if (!$disableDiscounts) {

            if (!empty($product->getDiscountPriceBase()) && $product->getDiscountPriceBase() > 0 && $product->getDiscountPriceBase() < $product->getPriceBase() && ((empty($product->getDateDiscountBaseFrom()) || $product->getDateDiscountBaseFrom() < $now) && ((empty($product->getDateDiscountBaseTo()) || $product->getDateDiscountBaseTo() > $now)))) {
                $ret["discount_price"] = floatval($product->getDiscountPriceBase());
                $ret["discount_percentage"] = $product->getDiscountPercentageBase();
                $discountStartDate = $product->getDateDiscountFrom();
                $ret["discount_type"] = "product";
            } else {
                /** @var ProductDiscountCatalogPriceEntity $productDiscountPrice */
                $productDiscountPrice = $product->getDiscountCatalogPrices();
                if (!empty($productDiscountPrice) && floatval($productDiscountPrice->getDiscountPriceBase()) && !$isPartOfBundle && (empty($productDiscountPrice->getDateValidFrom()) || $productDiscountPrice->getDateValidFrom() < $now) &&
                        ((empty($productDiscountPrice->getDateValidTo()) || $productDiscountPrice->getDateValidTo() > $now)) && floatval($productDiscountPrice->getDiscountPriceBase()) > 0) {
                    $ret["discount_price"] = floatval($productDiscountPrice->getDiscountPriceBase());
                    $ret["discount_percentage"] = $productDiscountPrice->getRebate();
                    $ret["discount_catalog_rule_id"] = $productDiscountPrice->getType();
                    $discountStartDate = $productDiscountPrice->getDateValidFrom();
                    $ret["discount_type"] = "discount_catalog";
                }
            }
        }

        if (!empty($ret["discount_price"])) {

            $ret["lowest_price"] = null;

            if (empty($this->applicationSettingsManager)) {
                $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
            }

            $getLowestPrice = intval($this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("prices_display_lowest_price", $session->get("current_store_id")));

            if ($getLowestPrice) {
                if (empty($this->productManager)) {
                    $this->productManager = $this->container->get("product_manager");
                }
                $lowestPrice = $this->productManager->getSingleProductHistoryPrice($product, 30, $discountStartDate);

                if (floatval($lowestPrice) > 0) {
                    $ret["lowest_price"] = $lowestPrice + floatval($product->getPriceReturn());
                }
            }
        }

        /**
         * Get account group prices if exist
         */
        if (!empty($accountGroup) && floatval($ret["discount_price"]) == 0 && !$isPartOfBundle && !$disableDiscounts) {
            /** @var ProductAccountGroupPriceEntity $accountGroupPrice */
            $accountGroupPrice = $product->getAccountGroupPrices($accountGroup);

            if (!empty($accountGroupPrice) && floatval($accountGroupPrice->getDiscountPriceBase()) > 0 && (empty($accountGroupPrice->getDateValidFrom()) || $accountGroupPrice->getDateValidFrom() < $now) && ((empty($accountGroupPrice->getDateValidTo()) || $accountGroupPrice->getDateValidTo() > $now)) && floatval($accountGroupPrice->getDiscountPriceBase()) > 0) {
                $ret["discount_price"] = floatval($accountGroupPrice->getDiscountPriceBase());
                $ret["discount_type"] = "discount_account_group";
                $ret["discount_catalog_rule_id"] = $accountGroupPrice->getType();
                $ret["discount_account_group_id"] = $accountGroupPrice->getAccountGroupId();
                $ret["discount_percentage"] = floatval($accountGroupPrice->getRebate());
            }
        }

        /**
         * Check account price
         */
        if (!empty($account) && floatval($ret["discount_price"]) == 0 && !$isPartOfBundle && !$disableRebate) {
            /** @var ProductAccountPriceEntity $accountPrice */
            $accountPrice = $product->getAccountPrices($account);

            if (!empty($accountPrice) && floatval($accountPrice->getDiscountPriceBase()) > 0 && (empty($accountPrice->getDateValidFrom()) || $accountPrice->getDateValidFrom() < $now) && ((empty($accountPrice->getDateValidTo()) || $accountPrice->getDateValidTo() > $now))) {
                $ret["discount_price"] = floatval($accountPrice->getDiscountPriceBase());
                $ret["discount_percentage"] = floatval($accountPrice->getRebate());
                $ret["discount_catalog_rule_id"] = $accountPrice->getType();
                $ret["discount_type"] = "discount_account";
            }
        }

        /**
         * Get bulk prices
         */
        if ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_SIMPLE && !empty($product->getBulkPriceRule())) {
            $ret["bulk_prices"] = $this->getBulkPricesForProductVpc($product, $ret, $includeReturnPrice);
        }

        /**
         * Dohvat cijena za bundle i sl
         */
        if (!empty($parentProduct)) {
            $ret = $this->getProductPricesOfCombinedProductVpc($product, $ret, $parentProduct);
        }

        /**
         * Definiraj finalnu cijenu koja ukljucuje povratnu naknadu
         */
        $ret["final_price"] = $ret["price"] + $ret["return_price"];
        if(floatval($ret["discount_price"]) > 0){
            $ret["final_price"] = $ret["discount_price"] + $ret["return_price"];
        }

        /**
         * Include return price if neccesary
         */
        if($includeReturnPrice && $ret["return_price"] > 0){
            $ret["price"] = $ret["price"]+$ret["return_price"];
            if($ret["discount_price"] > 0){
                $ret["discount_price"] = $ret["discount_price"]+$ret["return_price"];
            }
        }

        $ret["price_other"] = $ret["price"] * (1 + $taxPercent/100);
        $ret["discount_price_other"] = $ret["discount_price"] * (1 + $taxPercent/100);

        if(empty($ret["discount_price"])){
            $ret["discount_price"] = null;
        }
        if(empty($ret["discount_price_other"])){
            $ret["discount_price_other"] = null;
        }

        $ret["tracking_price"] = $ret["price_other"];
        $ret["discount_tracking_price"] = $ret["discount_price_other"];
        $ret["final_tracking_price"] = $ret["tracking_price"];
        if(floatval($ret["discount_tracking_price"]) > 0){
            $ret["final_tracking_price"] = $ret["discount_price_other"];
        }

        return $ret;
    }

    /**
     * @param ProductEntity $product
     * @param AccountEntity|null $account
     * @param ProductEntity|null $parentProduct
     * @param $includeReturnPrice
     * @return array|mixed
     */
    public function getProductPricesMpc(ProductEntity $product, AccountEntity $account = null, ProductEntity $parentProduct = null, $includeReturnPrice = true){

        $session = $this->container->get('session');

        $ret = array();

        /** Osnovna cijena */
        $ret["price"] = null;
        /** Osnovna cijena bez PDV */
        $ret["price_other"] = null;
        /** Cijena sa popustom */
        $ret["discount_price"] = null;
        /** Cijena sa popustom bez PDV */
        $ret["discount_price_other"] = null;
        /** Postotak popusta */
        $ret["discount_percentage"] = null;
        /** Povratna naknada */
        $ret["return_price"] = null;
        /** Najniza cijena u zadnjih 30 dana */
        $ret["lowest_price"] = null;
        /** Finalna cijena koja ukljucuje povratnu naknadu */
        $ret["final_price"] = null;
        /** Ako je proizvod na popustu tu ce pisati kojeg je tipa popust, product, katalosti, po accountu, po account grupi */
        $ret["discount_type"] = "";
        /** AKO discount cijena dolazi sa grupe, tu ce pisati sa koje grupe */
        $ret["discount_account_group_id"] = null;
        $ret["discount_catalog_rule_id"] = null;
        $ret["currency_code"] = $product->getCurrency()->getSign();
        $ret["currency_short_code"] = $product->getCurrency()->getCode();
        $ret["rebate"] = null;
        $ret["calculation_type"] = "Mpc";

        $taxPercent = floatval($product->getTaxType()->getPercent());

        $ret["return_price"] = floatval($product->getPriceReturn());

        $now = new \DateTime();

        /*if($forceAccountFromSession && !$session->get("disable_force_account_from_session")){
            $account = $session->get('account');
        }
        else*/
        if(empty($account)){
            /** @var AccountEntity $account */
            $account = $session->get('account');
        }

        $ret["vat_type"] = "with VAT";
        $ret["is_legal_entity"] = false;
        $disableDiscounts = false;
        $disableRebate = false;
        $accountGroup = null;
        if (!empty($account)) {
            $ret["is_legal_entity"] = $account->getIsLegalEntity();
            $disableDiscounts = $account->getDisableDiscounts();
            $disableRebate = $account->getDisableRebate();
            $accountGroup = $account->getAccountGroup();
        }

        $ret["price"] = floatval($product->getPriceRetail());

        $discountStartDate = null;

        $isPartOfBundle = false;
        if(!empty($parentProduct) && $parentProduct->getProductTypeId() == CrmConstants::PRODUCT_TYPE_BUNDLE){
            $isPartOfBundle = true;
        }

        /**
         * Check discounts
         */
        if (!$disableDiscounts) {
            if (!empty($product->getDiscountPriceRetail()) && $product->getDiscountPriceRetail() > 0 && $product->getDiscountPriceRetail() < $product->getPriceRetail() && ((empty($product->getDateDiscountFrom()) || $product->getDateDiscountFrom() < $now) && ((empty($product->getDateDiscountTo()) || $product->getDateDiscountTo() > $now)))) {
                $ret["discount_price"] = floatval($product->getDiscountPriceRetail());
                $ret["discount_percentage"] = $product->getDiscountPercentage();
                $discountStartDate = $product->getDateDiscountFrom();
                $ret["discount_type"] = "product";
            } else {
                /** @var ProductDiscountCatalogPriceEntity $productDiscountPrice */
                $productDiscountPrice = $product->getDiscountCatalogPrices();
                if (!empty($productDiscountPrice) && floatval($productDiscountPrice->getDiscountPriceRetail()) > 0 && !$isPartOfBundle && (empty($productDiscountPrice->getDateValidFrom()) || $productDiscountPrice->getDateValidFrom() < $now) &&
                        ((empty($productDiscountPrice->getDateValidTo()) || $productDiscountPrice->getDateValidTo() > $now))) {
                    $ret["discount_price"] = floatval($productDiscountPrice->getDiscountPriceRetail());
                    $ret["discount_percentage"] = $productDiscountPrice->getRebate();
                    $discountStartDate = $productDiscountPrice->getDateValidFrom();
                    $ret["discount_catalog_rule_id"] = $productDiscountPrice->getType();
                    $ret["discount_type"] = "discount_catalog";
                }
            }
        }

        if (!empty($ret["discount_price"])) {

            $ret["lowest_price"] = null;

            if (empty($this->applicationSettingsManager)) {
                $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
            }

            $getLowestPrice = intval($this->applicationSettingsManager->getApplicationSettingByCodeAndStoreId("prices_display_lowest_price", $session->get("current_store_id")));

            if ($getLowestPrice) {
                if (empty($this->productManager)) {
                    $this->productManager = $this->container->get("product_manager");
                }
                $lowestPrice = $this->productManager->getSingleProductHistoryPrice($product, 30, $discountStartDate);

                if (floatval($lowestPrice) > 0) {
                    $ret["lowest_price"] = $lowestPrice + floatval($product->getPriceReturn());
                }
            }
        }

        /**
         * Get account group prices if exist
         */
        if (!empty($accountGroup) && floatval($ret["discount_price"]) == 0 && !$isPartOfBundle && !$disableDiscounts) {
            /** @var ProductAccountGroupPriceEntity $accountGroupPrice */
            $accountGroupPrice = $product->getAccountGroupPrices($accountGroup);

            if (!empty($accountGroupPrice) && floatval($accountGroupPrice->getDiscountPriceRetail()) > 0 && (empty($accountGroupPrice->getDateValidFrom()) || $accountGroupPrice->getDateValidFrom() < $now) && ((empty($accountGroupPrice->getDateValidTo()) || $accountGroupPrice->getDateValidTo() > $now))) {

                $ret["discount_price"] = floatval($accountGroupPrice->getDiscountPriceRetail());
                $ret["discount_type"] = "discount_account_group";
                $ret["discount_catalog_rule_id"] = $accountGroupPrice->getType();
                $ret["discount_account_group_id"] = $accountGroupPrice->getAccountGroupId();
                $ret["discount_percentage"] = floatval($accountGroupPrice->getRebate());
            }
        }

        /**
         * Check account price
         */
        if (!empty($account) && floatval($ret["discount_price"]) == 0 && !$isPartOfBundle && !$disableRebate) {
            /** @var ProductAccountPriceEntity $accountPrice */
            $accountPrice = $product->getAccountPrices($account);

            if (!empty($accountPrice) && floatval($accountPrice->getDiscountPriceRetail()) > 0 && (empty($accountPrice->getDateValidFrom()) || $accountPrice->getDateValidFrom() < $now) && ((empty($accountPrice->getDateValidTo()) || $accountPrice->getDateValidTo() > $now))) {
                $ret["discount_price"] = floatval($accountPrice->getDiscountPriceRetail());
                $ret["discount_percentage"] = floatval($accountPrice->getRebate());
                $ret["discount_catalog_rule_id"] = $accountPrice->getType();
                $ret["discount_type"] = "discount_account";
            }
        }

        /**
         * Get bulk prices
         */
        if ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_SIMPLE && !empty($product->getBulkPriceRule())) {
            $ret["bulk_prices"] = $this->getBulkPricesForProductMpc($product, $ret, $includeReturnPrice);
        }

        /**
         * Dohvat cijena za bundle i sl
         */
        if (!empty($parentProduct)) {
            $ret = $this->getProductPricesOfCombinedProductMpc($product, $ret, $parentProduct);
        }

        /**
         * Definiraj finalnu cijenu koja ukljucuje povratnu naknadu
         */
        $ret["final_price"] = $ret["price"] + $ret["return_price"];
        if(floatval($ret["discount_price"]) > 0){
            $ret["final_price"] = $ret["discount_price"] + $ret["return_price"];
        }

        /**
         * Include return price if neccesary
         */
        if($includeReturnPrice && $ret["return_price"] > 0){
            $ret["price"] = $ret["price"]+$ret["return_price"];
            if($ret["discount_price"] > 0){
                $ret["discount_price"] = $ret["discount_price"]+$ret["return_price"];
            }
            if($ret["lowest_price"] > 0){
                $ret["lowest_price"] = $ret["lowest_price"]+$ret["return_price"];
            }
        }

        $ret["price_other"] = $ret["price"] / (1 + $taxPercent/100);
        $ret["discount_price_other"] = $ret["discount_price"] / (1 + $taxPercent/100);

        if(empty($ret["discount_price"])){
            $ret["discount_price"] = null;
        }
        if(empty($ret["discount_price_other"])){
            $ret["discount_price_other"] = null;
        }

        $ret["tracking_price"] = $ret["price"];
        $ret["discount_tracking_price"] = $ret["discount_price"];
        $ret["final_tracking_price"] = $ret["tracking_price"];
        if(floatval($ret["discount_tracking_price"]) > 0){
            $ret["final_tracking_price"] = $ret["discount_price"];
        }

        return $ret;
    }

    /**
     * @param ProductEntity $product
     * @param $ret
     * @param ProductEntity $parentProduct
     * @return mixed
     */
    public function getProductPricesOfCombinedProductMpc(ProductEntity $product, $ret, ProductEntity $parentProduct)
    {

        if ($parentProduct->getProductTypeId() == CrmConstants::PRODUCT_TYPE_BUNDLE) {
            /**
             * Check if base product is added to bundle
             */
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("childProduct", "eq", $product->getId()));
            $compositeFilter->addFilter(new SearchFilter("product", "eq", $parentProduct->getId()));

            if (empty($this->productManager)) {
                $this->productManager = $this->container->get("product_manager");
            }

            /** @var ProductConfigurationProductLinkEntity $relation */
            $relation = $this->productManager->getProductConfigurationProductLink($compositeFilter);

            if (!empty($relation)) {

                if($relation->getDiscountPercentage() > 0 && $relation->getDiscountPercentage() < 100) {
                    $ret["discount_percentage"] = floatval($relation->getDiscountPercentage());
                    $ret["discount_price"] = $ret["price"] - $ret["price"] * $ret["discount_percentage"] / 100;
                }
            }
        }

        return $ret;
    }

    /**
     * @param ProductEntity $product
     * @param $ret
     * @param ProductEntity $parentProduct
     * @return mixed
     */
    public function getProductPricesOfCombinedProductVpc(ProductEntity $product, $ret, ProductEntity $parentProduct)
    {

        if ($parentProduct->getProductTypeId() == CrmConstants::PRODUCT_TYPE_BUNDLE) {
            /**
             * Check if base product is added to bundle
             */
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("childProduct", "eq", $product->getId()));
            $compositeFilter->addFilter(new SearchFilter("product", "eq", $parentProduct->getId()));

            if (empty($this->productManager)) {
                $this->productManager = $this->container->get("product_manager");
            }

            /** @var ProductConfigurationProductLinkEntity $relation */
            $relation = $this->productManager->getProductConfigurationProductLink($compositeFilter);

            if (!empty($relation)) {

                if($relation->getDiscountPercentageBase() > 0 && $relation->getDiscountPercentageBase() < 100) {
                    $ret["discount_percentage"] = floatval($relation->getDiscountPercentageBase());
                    $ret["discount_price"] = $ret["price"] - $ret["price"] * $ret["discount_percentage"] / 100;
                }
            }
        }

        return $ret;
    }


    /**
     * @param ProductEntity $product
     * @param $prices
     * @return array
     */
    public function getBulkPricesForProductMpc(ProductEntity $product, $prices, $includeReturnPrice)
    {

        $ret = array();

        $bulkPriceOptions = $product->getBulkPriceRule()->getBulkPriceOptions();

        if (empty($bulkPriceOptions)) {
            return $ret;
        }

        /** @var BulkPriceOptionEntity $bulkPriceOption */
        foreach ($bulkPriceOptions as $key => $bulkPriceOption) {
            $tmp = array();
            $tmp["min_qty"] = $bulkPriceOption->getMinQty();
            $tmp["max_qty"] = 10000000;

            /** Postotak popusta */
            $tmp["bulk_price_percentage"] = floatval($bulkPriceOption->getDiscountPercentage());
            /** Cijena po jedinici */
            $tmp["bulk_price_item"] = null;
            /** Cijena total */
            $tmp["bulk_price_total"] = null;
            /** Iznos apsolutne ustede */
            $tmp["bulk_price_total_diff"] = null;

            /** VRSTA PRIKAZA CIJENE PO KOLICINI */
            $tmp["bulk_price_type"] = 1;

            if (!empty($product->getBulkPriceRule()->getDisplayTypeId())) {
                $tmp["bulk_price_type"] = $product->getBulkPriceRule()->getDisplayTypeId();
            }

            /**
             * Ovdje se samo definira koja je pocetna cijena na temelju koje se skida postotak
             */
            $calculationPrice = $prices["price"];
            if($prices["discount_price"] > 0){
                $calculationPrice = $prices["discount_price"];
            }

            /**
             * Izracun
             */
            $tmp["bulk_price_item"] = $calculationPrice - ($calculationPrice * $tmp["bulk_price_percentage"] / 100);
            $tmp["bulk_price_total"] = $tmp["bulk_price_item"] * $tmp["min_qty"];
            $tmp["bulk_price_total_diff"] = ($calculationPrice * $tmp["min_qty"]) - $tmp["bulk_price_total"];
            $tmp["bulk_price_item_final"] = $tmp["bulk_price_item"] + $prices["return_price"];

            if($includeReturnPrice){
                $tmp["bulk_price_item"] = $tmp["bulk_price_item"] + $prices["return_price"];
                $tmp["bulk_price_total"] = $tmp["bulk_price_total"] + $tmp["min_qty"] * $prices["return_price"];
            }

            $ret[] = $tmp;

            if (isset($ret[$key - 1])) {
                $ret[$key - 1]["max_qty"] = $tmp["min_qty"] - 1;
            }
        }

        return $ret;
    }

    /**
     * @param ProductEntity $product
     * @param $prices
     * @return array
     */
    public function getBulkPricesForProductVpc(ProductEntity $product, $prices, $includeReturnPrice)
    {

        $ret = array();

        $bulkPriceOptions = $product->getBulkPriceRule()->getBulkPriceOptions();

        if (empty($bulkPriceOptions)) {
            return $ret;
        }

        /** @var BulkPriceOptionEntity $bulkPriceOption */
        foreach ($bulkPriceOptions as $key => $bulkPriceOption) {
            $tmp = array();
            $tmp["min_qty"] = $bulkPriceOption->getMinQty();
            $tmp["max_qty"] = 10000000;

            /** Postotak popusta */
            $tmp["bulk_price_percentage"] = floatval($bulkPriceOption->getDiscountPercentageBase());
            /** Cijena po jedinici */
            $tmp["bulk_price_item"] = null;
            /** Cijena total */
            $tmp["bulk_price_total"] = null;
            /** Iznos apsolutne ustede */
            $tmp["bulk_price_total_diff"] = null;

            /** VRSTA PRIKAZA CIJENE PO KOLICINI */
            $tmp["bulk_price_type"] = 1;

            if (!empty($product->getBulkPriceRule()->getDisplayTypeId())) {
                $tmp["bulk_price_type"] = $product->getBulkPriceRule()->getDisplayTypeId();
            }

            /**
             * Ovdje se samo definira koja je pocetna cijena na temelju koje se skida postotak
             */
            $calculationPrice = $prices["price"];
            if($prices["discount_price"] > 0){
                $calculationPrice = $prices["discount_price"];
            }

            /**
             * Izracun
             */
            $tmp["bulk_price_item"] = $calculationPrice - ($calculationPrice * $tmp["bulk_price_percentage"] / 100);
            $tmp["bulk_price_total"] = $tmp["bulk_price_item"] * $tmp["min_qty"];
            $tmp["bulk_price_total_diff"] = ($calculationPrice * $tmp["min_qty"]) - $tmp["bulk_price_total"];
            $tmp["bulk_price_item_final"] = $tmp["bulk_price_item"] + $prices["return_price"];

            if($includeReturnPrice){
                $tmp["bulk_price_item"] = $tmp["bulk_price_item"] + $prices["return_price"];
                $tmp["bulk_price_total"] = $tmp["bulk_price_total"] + $tmp["min_qty"] * $prices["return_price"];
            }

            $ret[] = $tmp;

            if (isset($ret[$key - 1])) {
                $ret[$key - 1]["max_qty"] = $tmp["min_qty"] - 1;
            }
        }

        return $ret;
    }

}


?>
