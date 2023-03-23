<?php

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\CalculationProviders\DefaultCalculationProvider;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\DiscountCouponEntity;
use CrmBusinessBundle\Entity\ProductAccountGroupPriceEntity;
use CrmBusinessBundle\Entity\ProductAccountPriceEntity;
use CrmBusinessBundle\Entity\ProductConfigurationProductLinkEntity;
use CrmBusinessBundle\Entity\ProductDiscountCatalogPriceEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;


class VisakProvider extends DefaultCalculationProvider
{
    /**
     * @param ProductEntity $product
     * @param AccountEntity|null $account
     * @return array|null
     * @throws \Exception
     */
    public function getProductPrices(ProductEntity $product, AccountEntity $account = null, ProductEntity $parentProduct = null, $forceAccountFromSession = true)
    {
        $session = $this->container->get('session');

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $exchangeRates = array();

        $cacheItem = $this->cacheManager->getCacheGetItem("exchange_rates");
        if (!empty($cacheItem)) {
            $exchangeRates = $cacheItem->get();
        }

        $ret = array();

        /** PRIKAZ cijena u valuti koju korisnik gleda */
        $ret["price"] = null;
        /** VPC cijena u valuti koju korisnik gleda */
        $ret["price_base"] = null;
        /** MPC cijena u valuti koju korisnik gleda */
        $ret["price_retail"] = null;
        /** VPC cijena u osnovnoj valuti */
        $ret["price_base_currency"] = null;
        /** MPC cijena u osnovnoj valuti */
        $ret["price_retail_currency"] = null;
        $ret["rebate"] = null;
        /** PRIKAZ cijena u valuti koju korisnik gleda - osnovna cijena proizvoda bez rabata na kupcu ili grupi */
        $ret["original_price"] = null;
        /** VPC cijena u valuti koju korisnik gleda - osnovna cijena proizvoda bez rabata na kupcu ili grupi */
        $ret["original_price_base"] = null;
        /** MPC cijena u valuti koju korisnik gleda - osnovna cijena proizvoda bez rabata na kupcu ili grupi */
        $ret["original_price_retail"] = null;
        /** VPC cijena u osnovnoj valuti - osnovna cijena proizvoda bez rabata na kupcu ili grupi */
        $ret["original_price_base_currency"] = null;
        /** MPC cijena u osnovnoj valuti - osnovna cijena proizvoda bez rabata na kupcu ili grupi */
        $ret["original_price_retail_currency"] = null;
        /** PRIKAZ cijena s popustom u valuti koju korisnik gleda */
        $ret["discount_price"] = null;
        /** VPC cijena s popustom u valuti koju korisnik gleda */
        $ret["discount_price_base"] = null;
        /** MPC cijena s popustom u valuti koju korisnik gleda */
        $ret["discount_price_retail"] = null;
        /** VPC cijena s popustom u osnovnoj valuti */
        $ret["discount_price_base_currency"] = null;
        /** MPC cijena s popustom u osnovnoj valuti */
        $ret["discount_price_retail_currency"] = null;
        /** PRIKAZ postotak popusta */
        $ret["discount_percentage"] = null;
        /** VPC postotak popusta */
        $ret["discount_percentage_base"] = null;
        /** MPC postotak popusta */
        $ret["discount_percentage_retail"] = null;
        /** Postotak popusta za keš */
        $ret["cash_percentage"] = null;
        /** MPC cijena  za keš sa PDV */
        $ret["cash_price_retail"] = null;
        /** VPC cijena  za keš bez PDV */
        $ret["cash_price_base"] = null;
        /** PRIKAZ Cijena za keš za prikaz */
        $ret["cash_price"] = null;
        /** Broj rata */
        $ret["number_of_installments"] = null;
        /** Cijena po rati sa PDV */
        $ret["installment_price_retail"] = null;
        /** Cijena po rati bez PDV */
        $ret["installment_price_base"] = null;
        /** PRIKAZ Cijena po rati bez PDV */
        $ret["installment_price"] = null;
        /** GET FINAL PRICE */
        $ret["final_price"] = null;
        /** POVRATNA NAKNADA */
        $ret["return_price"] = null;
        /** NAJNIZA CIJENA ZADNJIH 30 DANA U VALUTI KOJU KUPAC GLEDA */
        $ret["lowest_price"] = null;
        /** NAJNIZA CIJENA ZADNJIH 30 DANA U OSNOVNOJ VALUTI */
        $ret["lowest_price_currency"] = null;
        /** Ako je proizvod na popustu tu ce pisati kojeg je tipa popust, product, katalosti, po accountu, po account grupi */
        $ret["discount_type"] = "";
        /** AKO discount cijena dolazi sa grupe, tu ce pisati sa koje grupe */
        $ret["discount_account_group_id"] = null;
        /** TIP CJENIKA */
        $ret["price_list_type"] = $product->getPriceListType();
        $ret["discount_catalog_rule_id"] = null;

        if (isset($exchangeRates[$session->get("current_website_id")][$session->get("current_store_id")])) {
            $ret["currency_code"] = $exchangeRates[$session->get("current_website_id")][$session->get("current_store_id")]["currency_sign"];
            $ret["exchange_rate"] = $exchangeRates[$session->get("current_website_id")][$session->get("current_store_id")]["exchange_rate"];
        } else {
            $ret["currency_code"] = "";
            if (!empty($product->getCurrency())) {
                $ret["currency_code"] = $product->getCurrency()->getSign();
            }
            $ret["exchange_rate"] = 1;
        }

        $ret["return_price"] = $product->getPriceReturn();

        $now = new \DateTime();

        if ($forceAccountFromSession && !$session->get("disable_force_account_from_session")) {
            /** @var AccountEntity $account */
            $account = $session->get('account');
        } elseif (empty($account)) {
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

        $ret["price_base_currency"] = $product->getPriceBase();
        $ret["price_retail_currency"] = $product->getPriceRetail();
        $ret["original_price_base_currency"] = $product->getPriceBase();
        $ret["original_price_retail_currency"] = $product->getPriceRetail();

        $ret["original_price_base"] = $ret["price_base"] = $product->getPriceBase() / $ret["exchange_rate"];
        $ret["original_price_retail"] = $ret["price_retail"] = $product->getPriceRetail() / $ret["exchange_rate"];

        $discountStartDate = null;

        $isPartOfBundle = false;
        if (!empty($parentProduct) && $parentProduct->getProductTypeId() == CrmConstants::PRODUCT_TYPE_BUNDLE) {
            $isPartOfBundle = true;
        }

        /**
         * Check discounts
         */
        if (!$disableDiscounts) {
            if (!empty($product->getDiscountPriceBase()) && $product->getDiscountPriceBase() > 0 && $product->getDiscountPriceBase() < $product->getPriceBase() && ((empty($product->getDateDiscountBaseFrom()) || $product->getDateDiscountBaseFrom() < $now) && ((empty($product->getDateDiscountBaseTo()) || $product->getDateDiscountBaseTo() > $now)))) {
                $ret["discount_price_base_currency"] = $product->getDiscountPriceBase();
                $ret["discount_price_base"] = $product->getDiscountPriceBase() / $ret["exchange_rate"];
                $ret["discount_percentage_base"] = $product->getDiscountPercentageBase();
                $discountStartDate = $product->getDiscountPriceBase();
                $ret["discount_type"] = "product";
            } else {
                /** @var ProductDiscountCatalogPriceEntity $productDiscountPrice */
                $productDiscountPrice = $product->getDiscountCatalogPrices();
                if (!empty($productDiscountPrice)) {
                    if ((empty($productDiscountPrice->getDateValidFrom()) || $productDiscountPrice->getDateValidFrom() < $now) &&
                        ((empty($productDiscountPrice->getDateValidTo()) || $productDiscountPrice->getDateValidTo() > $now))) {
                        $ret["discount_price_base_currency"] = $productDiscountPrice->getDiscountPriceBase();
                        $ret["discount_price_base"] = $productDiscountPrice->getDiscountPriceBase() / $ret["exchange_rate"];
                        $ret["discount_percentage_base"] = $productDiscountPrice->getRebate();
                        $discountStartDate = $productDiscountPrice->getDateValidFrom();
                        $ret["discount_catalog_rule_id"] = $productDiscountPrice->getType();
                        $ret["discount_type"] = "discount_catalog";
                    }
                }
            }

            if (!empty($product->getDiscountPriceRetail()) && $product->getDiscountPriceRetail() > 0 && $product->getDiscountPriceRetail() < $product->getPriceRetail() && ((empty($product->getDateDiscountFrom()) || $product->getDateDiscountFrom() < $now) && ((empty($product->getDateDiscountTo()) || $product->getDateDiscountTo() > $now)))) {
                $ret["discount_price_retail_currency"] = $product->getDiscountPriceRetail();
                $ret["discount_price_base_currency"] = $product->getDiscountPriceRetail() / (1 + $product->getTaxType()->getPercent() / 100);
                $ret["discount_price_retail"] = $product->getDiscountPriceRetail() / $ret["exchange_rate"];
                $ret["discount_price_base"] = $ret["discount_price_base_currency"] / $ret["exchange_rate"];
                $ret["discount_percentage_retail"] = $product->getDiscountPercentage();
                $discountStartDate = $product->getDateDiscountFrom();
                $ret["discount_type"] = "product";
            } else {
                /** @var ProductDiscountCatalogPriceEntity $productDiscountPrice */
                $productDiscountPrice = $product->getDiscountCatalogPrices();
                if (!empty($productDiscountPrice) && !$isPartOfBundle) {
                    if ((empty($productDiscountPrice->getDateValidFrom()) || $productDiscountPrice->getDateValidFrom() < $now) &&
                        ((empty($productDiscountPrice->getDateValidTo()) || $productDiscountPrice->getDateValidTo() > $now))) {
                        $ret["discount_price_retail_currency"] = $productDiscountPrice->getDiscountPriceRetail();
                        $ret["discount_price_base_currency"] = $productDiscountPrice->getDiscountPriceRetail() / (1 + $product->getTaxType()->getPercent() / 100);
                        $ret["discount_price_retail"] = $productDiscountPrice->getDiscountPriceRetail() / $ret["exchange_rate"];
                        $ret["discount_price_base"] = $ret["discount_price_base_currency"] / $ret["exchange_rate"];;
                        $ret["discount_percentage_retail"] = $productDiscountPrice->getRebate();
                        $discountStartDate = $productDiscountPrice->getDateValidFrom();
                        $ret["discount_catalog_rule_id"] = $productDiscountPrice->getType();
                        $ret["discount_type"] = "discount_catalog";
                    }
                }
            }
        }

        if ($_ENV["MPC_OLD_IS_MINIMAL_PRICE"]) {
            if (!empty($ret["discount_price_retail_currency"]) && floatval($product->getMinPriceRetail()) > 0) {
                $ret["lowest_price_currency"] = floatval($product->getMinPriceRetail());
                $ret["lowest_price"] = $ret["lowest_price_currency"] / $ret["exchange_rate"];
            }
        } else {
            if (!empty($ret["discount_price_retail_currency"]) || in_array($ret["price_list_type"], array("KAT", "RAS", "AKC", "WEB"))) {

                $ret["lowest_price_currency"] = null;
                if (empty($ret["discount_price_retail_currency"])) {
                    $discountStartDate = $product->getDateDiscountFrom();
                }

                if (empty($this->productManager)) {
                    $this->productManager = $this->container->get("product_manager");
                }
                $lowestPrice = $this->productManager->getSingleProductHistoryPrice($product, 30, $discountStartDate);

                if (empty($lowestPrice) && $ret["price_list_type"] != "RAS") {
                    $lowestPrice = $product->getPriceRetail();
                } /**
                 * Druga cijena se dohvaća isključivo za RAS
                 */
                elseif (empty($lowestPrice)) { // &&
                    $lowestPrice = $this->productManager->getSingleProductHistoryPrice($product, 700, $discountStartDate);
                }

                if (!empty($lowestPrice)) {
                    if (!empty($ret["discount_price_retail_currency"])) {
                        if ($ret["discount_price_retail_currency"] < $lowestPrice) {
                            $ret["lowest_price_currency"] = $lowestPrice + floatval($product->getPriceReturn());
                        }
                    } else {
                        if ($ret["price_retail_currency"] < $lowestPrice) {
                            $ret["lowest_price_currency"] = $lowestPrice + floatval($product->getPriceReturn());
                        }
                    }
                }

                if (floatval($ret["lowest_price_currency"]) > 0) {
                    $ret["lowest_price"] = $ret["lowest_price_currency"] / $ret["exchange_rate"];
                }
            }
        }

        /**
         * Get account group prices if exist
         */
        if (!empty($accountGroup) && floatval($ret["discount_price_retail_currency"]) == 0 && !$isPartOfBundle) {
            /** @var ProductAccountGroupPriceEntity $accountGroupPrice */
            $accountGroupPrice = $product->getAccountGroupPrices($accountGroup);

            if (!empty($accountGroupPrice)) {

                if ((empty($accountGroupPrice->getDateValidFrom()) || $accountGroupPrice->getDateValidFrom() < $now) && ((empty($accountGroupPrice->getDateValidTo()) || $accountGroupPrice->getDateValidTo() > $now))) {

                    if (!$disableRebate) {
                        $ret["price_base"] = $accountGroupPrice->getPriceBase() / $ret["exchange_rate"];
                        $ret["price_base_currency"] = $accountGroupPrice->getPriceBase();
                    }

                    $ret["price_retail_currency"] = $accountGroupPrice->getPriceBase() * (1 + $product->getTaxType()->getPercent() / 100);
                    $ret["price_retail"] = $ret["price_retail_currency"] / $ret["exchange_rate"];

                    $ret["rebate"] = $accountGroupPrice->getRebate();

                    if (!$disableDiscounts) {
                        if (!empty($accountGroupPrice->getDiscountPriceBase()) && !$disableRebate) {
                            $ret["discount_price_base_currency"] = $accountGroupPrice->getDiscountPriceBase();
                            $ret["discount_price_base"] = $accountGroupPrice->getDiscountPriceBase() / $ret["exchange_rate"];
                            $ret["discount_type"] = "discount_account_group";
                            $ret["discount_catalog_rule_id"] = $accountGroupPrice->getType();
                            $ret["discount_account_group_id"] = $accountGroupPrice->getAccountGroupId();
                        }

                        if (!empty($accountGroupPrice->getDiscountPriceRetail())) {
                            $ret["discount_price_retail_currency"] = $accountGroupPrice->getDiscountPriceRetail();
                            $ret["discount_price_base_currency"] = $accountGroupPrice->getDiscountPriceRetail() / (1 + $product->getTaxType()->getPercent() / 100);
                            $ret["discount_price_retail"] = $accountGroupPrice->getDiscountPriceRetail() / $ret["exchange_rate"];
                            $ret["discount_type"] = "discount_account_group";
                            $ret["discount_catalog_rule_id"] = $accountGroupPrice->getType();
                            $ret["discount_account_group_id"] = $accountGroupPrice->getAccountGroupId();
                        }
                    }
                }
            }
        }

        /*$showAccountGroupPrices = false;

        if(!empty($account)){
            $contact = $account->getDefaultContact();

            if(!empty($contact->getLoyaltyCard())){
                $loyaltyCard = $contact->getLoyaltyCard();

                if(empty($loyaltyCard->getAktivnoDo()) && $loyaltyCard->getVrstaOsobe() == "F"){
                    $showAccountGroupPrices = true;
                }
            }
        }*/

        /**
         * Check account price
         */
        if (!empty($account) && floatval($ret["discount_price_retail_currency"]) == 0 && !$isPartOfBundle) {
            /** @var ProductAccountPriceEntity $accountPrice */
            $accountPrice = $product->getAccountPrices($account);

            if (!empty($accountPrice)) {
                if ((empty($accountPrice->getDateValidFrom()) || $accountPrice->getDateValidFrom() < $now) && ((empty($accountPrice->getDateValidTo()) || $accountPrice->getDateValidTo() > $now))) {

                    if (!$disableRebate) {
                        $ret["price_base"] = $accountPrice->getPriceBase() / $ret["exchange_rate"];
                        $ret["price_base_currency"] = $accountPrice->getPriceBase();
                    }

                    $ret["price_retail_currency"] = $accountPrice->getPriceBase() * (1 + $product->getTaxType()->getPercent() / 100);
                    $ret["price"] = $ret["price_retail_currency"] / $ret["exchange_rate"];
                    $ret["discount_percentage"] = $ret["discount_percentage_retail"] = $ret["discount_percentage_base"] = $accountPrice->getRebate();

                    $ret["rebate"] = $accountPrice->getRebate();

                    if (!$disableDiscounts) {
                        if (!empty($accountPrice->getDiscountPriceBase()) && !$disableRebate) {
                            $ret["discount_price_base_currency"] = $accountPrice->getDiscountPriceBase();
                            $ret["discount_price_base"] = $accountPrice->getDiscountPriceBase() / $ret["exchange_rate"];
                            $ret["discount_percentage"] = $ret["discount_percentage_retail"] = $ret["discount_percentage_base"] = $accountPrice->getRebate();
                            $ret["discount_catalog_rule_id"] = $accountPrice->getType();
                            $ret["discount_type"] = "discount_account";
                        }

                        if (!empty($accountPrice->getDiscountPriceRetail())) {
                            $ret["discount_price_retail_currency"] = $accountPrice->getDiscountPriceRetail();
                            $ret["discount_price_base_currency"] = $accountPrice->getDiscountPriceRetail() / (1 + $product->getTaxType()->getPercent() / 100);
                            $ret["discount_price_retail"] = $accountPrice->getDiscountPriceRetail() / $ret["exchange_rate"];
                            $ret["discount_percentage"] = $ret["discount_percentage_retail"] = $ret["discount_percentage_base"] = $accountPrice->getRebate();
                            $ret["discount_catalog_rule_id"] = $accountPrice->getType();
                            $ret["discount_type"] = "discount_account";
                        }
                    }
                }
            }
        }

        /**
         * Ako je legal entity i ako se koriste base_price_cijene kao i za prikaz
         */
        if ($ret["is_legal_entity"] && $_ENV["PRICE_USE_PRICES_WITHOUT_VAT_FOR_LEGAL_PERSONS"]) {
            $ret["final_price"] = $ret["price"] = $ret["price_base"] + $ret["return_price"];
            $ret["vat_type"] = "without VAT";
        } else {
            $ret["final_price"] = $ret["price"] = $ret["price_retail"] + $ret["return_price"];
        }

        /**
         * Select discount price to show
         */
        /**
         * Ako je legal entity i ako se koriste base_price_cijene kao i za prikaz
         */
        if ($ret["is_legal_entity"] && $_ENV["PRICE_USE_PRICES_WITHOUT_VAT_FOR_LEGAL_PERSONS"]) {
            if (!empty($ret["discount_price_base"])) {
                $ret["final_price"] = $ret["discount_price"] = $ret["discount_price_base"] + $ret["return_price"];
                $ret["discount_percentage"] = floatval($ret["discount_percentage_base"]);
            }
        } else {
            if (!empty($ret["discount_price_retail"])) {
                $ret["final_price"] = $ret["discount_price"] = $ret["discount_price_retail"] + $ret["return_price"];
                $ret["discount_percentage"] = floatval($ret["discount_percentage_retail"]);
            }
        }

        /**
         * If no discount fill in cash price
         */
        /*if (empty($ret["discount_price"])) {
            if (!empty($product->getCashPercentage()) && floatval($product->getCashPercentage()) > 0) {
                $ret["cash_percentage"] = $product->getCashPercentage();
                $ret["cash_price_retail"] = $product->getCashPriceRetail();
                $ret["cash_price_base"] = $product->getCashPriceBase();
                if ($ret["is_legal_entity"] && $_ENV["PRICE_USE_PRICES_WITHOUT_VAT_FOR_LEGAL_PERSONS"]) {
                    $ret["final_price"] = $ret["cash_price"] = $product->getCashPriceBase() + $ret["return_price"];
                } else {
                    $ret["final_price"] = $ret["cash_price"] = $product->getCashPriceRetail() + $ret["return_price"];
                }
            }
        }*/


        /**
         * Get bulk prices
         */
        if ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_SIMPLE && !empty($product->getBulkPriceRule())) {
            $ret["bulk_prices"] = $this->getBulkPricesForProduct($product, $ret);
        }

        /**
         * Dohvat cijena za bundle i sl
         */
        if (!empty($parentProduct)) {
            $ret = $this->getProductPricesOfCombinedProduct($product, $ret, $parentProduct);
        }

        /**
         * Set installment prices
         */
        $ret["number_of_installments"] = intval($product->getNumberOfInstallments());
        if (!empty($product->getNumberOfInstallments()) && intval($product->getNumberOfInstallments() > 1)) {
            if (!empty($ret["discount_price_retail"])) {
                $ret["installment_price_retail"] = floatval($ret["discount_price_retail"]) / intval($product->getNumberOfInstallments());
            } else {
                $ret["installment_price_retail"] = floatval($ret["price_retail"]) / intval($product->getNumberOfInstallments());
            }
            if (!empty($ret["discount_price_base"])) {
                $ret["installment_price_base"] = floatval($ret["discount_price_base"]) / intval($product->getNumberOfInstallments());
            } else {
                $ret["installment_price_base"] = floatval($ret["price_base"]) / intval($product->getNumberOfInstallments());
            }

            /**
             * Ako je legal entity i ako se koriste base_price_cijene kao i za prikaz
             */
            if ($ret["is_legal_entity"] && $_ENV["PRICE_USE_PRICES_WITHOUT_VAT_FOR_LEGAL_PERSONS"]) {
                $ret["installment_price"] = $ret["installment_price_base"];
            } else {
                $ret["installment_price"] = $ret["installment_price_retail"];
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
    public function getProductPricesOfCombinedProduct(ProductEntity $product, $ret, ProductEntity $parentProduct)
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

                $priceForCalculation = $ret["price"];
                if (isset($ret["final_price"]) && !empty($ret["final_price"])) {
                    $priceForCalculation = $ret["final_price"];
                }

                $fixedDiscountAmount = floatval($relation->getDiscountPriceBase());
                if ($fixedDiscountAmount > 0) {
                    $ret["discount_price"] = $ret["discount_price_retail"] = floatval($priceForCalculation) - $fixedDiscountAmount;
                } elseif ($relation->getDiscountPercentage() > 0 && $relation->getDiscountPercentage() < 100) {
                    $ret["discount_percentage"] = $ret["discount_percentage_retail"] = $relation->getDiscountPercentage();
                    $ret["discount_price"] = $ret["discount_price_retail"] = floatval($priceForCalculation) - floatval($priceForCalculation) * $relation->getDiscountPercentage() / 100;
                }

                $ret["discount_price_base_currency"] = $ret["discount_price"] / (1 + $product->getTaxType()->getPercent() / 100);
                $ret["discount_price_retail_currency"] = $ret["discount_price"];
            }
        }

        return $ret;
    }

    /**
     * @param ProductEntity $product
     * @param AccountEntity|null $account
     * @param ProductEntity|null $parentProduct
     * @param $forceAccountFromSession
     * @return mixed
     */
    public function getProductPricesFromDefault(ProductEntity $product, AccountEntity $account = null, ProductEntity $parentProduct = null, $forceAccountFromSession = true)
    {
        $session = $this->container->get('session');

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $exchangeRates = array();

        $cacheItem = $this->cacheManager->getCacheGetItem("exchange_rates");
        if (!empty($cacheItem)) {
            $exchangeRates = $cacheItem->get();
        }

        $ret = array();

        /** PRIKAZ cijena u valuti koju korisnik gleda */
        $ret["price"] = null;
        /** VPC cijena u valuti koju korisnik gleda */
        $ret["price_base"] = null;
        /** MPC cijena u valuti koju korisnik gleda */
        $ret["price_retail"] = null;
        /** VPC cijena u osnovnoj valuti */
        $ret["price_base_currency"] = null;
        /** MPC cijena u osnovnoj valuti */
        $ret["price_retail_currency"] = null;
        $ret["rebate"] = null;
        /** PRIKAZ cijena u valuti koju korisnik gleda - osnovna cijena proizvoda bez rabata na kupcu ili grupi */
        $ret["original_price"] = null;
        /** VPC cijena u valuti koju korisnik gleda - osnovna cijena proizvoda bez rabata na kupcu ili grupi */
        $ret["original_price_base"] = null;
        /** MPC cijena u valuti koju korisnik gleda - osnovna cijena proizvoda bez rabata na kupcu ili grupi */
        $ret["original_price_retail"] = null;
        /** VPC cijena u osnovnoj valuti - osnovna cijena proizvoda bez rabata na kupcu ili grupi */
        $ret["original_price_base_currency"] = null;
        /** MPC cijena u osnovnoj valuti - osnovna cijena proizvoda bez rabata na kupcu ili grupi */
        $ret["original_price_retail_currency"] = null;
        /** PRIKAZ cijena s popustom u valuti koju korisnik gleda */
        $ret["discount_price"] = null;
        /** VPC cijena s popustom u valuti koju korisnik gleda */
        $ret["discount_price_base"] = null;
        /** MPC cijena s popustom u valuti koju korisnik gleda */
        $ret["discount_price_retail"] = null;
        /** VPC cijena s popustom u osnovnoj valuti */
        $ret["discount_price_base_currency"] = null;
        /** MPC cijena s popustom u osnovnoj valuti */
        $ret["discount_price_retail_currency"] = null;
        /** PRIKAZ postotak popusta */
        $ret["discount_percentage"] = null;
        /** VPC postotak popusta */
        $ret["discount_percentage_base"] = null;
        /** MPC postotak popusta */
        $ret["discount_percentage_retail"] = null;
        /** Postotak popusta za keš */
        $ret["cash_percentage"] = null;
        /** MPC cijena  za keš sa PDV */
        $ret["cash_price_retail"] = null;
        /** VPC cijena  za keš bez PDV */
        $ret["cash_price_base"] = null;
        /** PRIKAZ Cijena za keš za prikaz */
        $ret["cash_price"] = null;
        /** Broj rata */
        $ret["number_of_installments"] = null;
        /** Cijena po rati sa PDV */
        $ret["installment_price_retail"] = null;
        /** Cijena po rati bez PDV */
        $ret["installment_price_base"] = null;
        /** PRIKAZ Cijena po rati bez PDV */
        $ret["installment_price"] = null;
        /** GET FINAL PRICE */
        $ret["final_price"] = null;
        /** POVRATNA NAKNADA */
        $ret["return_price"] = null;
        /** NAJNIZA CIJENA ZADNJIH 30 DANA U VALUTI KOJU KUPAC GLEDA */
        $ret["lowest_price"] = null;
        /** NAJNIZA CIJENA ZADNJIH 30 DANA U OSNOVNOJ VALUTI */
        $ret["lowest_price_currency"] = null;
        /** Ako je proizvod na popustu tu ce pisati kojeg je tipa popust, product, katalosti, po accountu, po account grupi */
        $ret["discount_type"] = "";
        /** AKO discount cijena dolazi sa grupe, tu ce pisati sa koje grupe */
        $ret["discount_account_group_id"] = null;


        if (isset($exchangeRates[$session->get("current_website_id")][$session->get("current_store_id")])) {
            $ret["currency_code"] = $exchangeRates[$session->get("current_website_id")][$session->get("current_store_id")]["currency_sign"];
            $ret["exchange_rate"] = $exchangeRates[$session->get("current_website_id")][$session->get("current_store_id")]["exchange_rate"];
        } else {
            $ret["currency_code"] = $product->getCurrency()->getSign();
            $ret["exchange_rate"] = 1;
        }

        $ret["return_price"] = $product->getPriceReturn();

        $now = new \DateTime();

        /** @var AccountEntity $account */
        $account = $session->get('account');

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

        $ret["price_base_currency"] = $product->getPriceBase();
        $ret["price_retail_currency"] = $product->getPriceRetail();
        $ret["original_price_base_currency"] = $product->getPriceBase();
        $ret["original_price_retail_currency"] = $product->getPriceRetail();

        $ret["original_price_base"] = $ret["price_base"] = $product->getPriceBase() / $ret["exchange_rate"];
        $ret["original_price_retail"] = $ret["price_retail"] = $product->getPriceRetail() / $ret["exchange_rate"];

        $discountStartDate = null;

        /**
         * Check discounts
         */
        if (!$disableDiscounts) {
            if (!$product->getExcludeFromDiscounts() && !empty($product->getDiscountPriceBase()) && $product->getDiscountPriceBase() > 0 && $product->getDiscountPriceBase() < $product->getPriceBase() && ((empty($product->getDateDiscountBaseFrom()) || $product->getDateDiscountBaseFrom() < $now) && ((empty($product->getDateDiscountBaseTo()) || $product->getDateDiscountBaseTo() > $now)))) {
                $ret["discount_price_base_currency"] = $product->getDiscountPriceBase();
                $ret["discount_price_base"] = $product->getDiscountPriceBase() / $ret["exchange_rate"];
                $ret["discount_percentage_base"] = $product->getDiscountPercentageBase();
                $discountStartDate = $product->getDiscountPriceBase();
                $ret["discount_type"] = "product";
            } else {
                /** @var ProductDiscountCatalogPriceEntity $productDiscountPrice */
                $productDiscountPrice = $product->getDiscountCatalogPrices();
                if (!$product->getExcludeFromDiscounts() && !empty($productDiscountPrice)) {
                    if ((empty($productDiscountPrice->getDateValidFrom()) || $productDiscountPrice->getDateValidFrom() < $now) &&
                        ((empty($productDiscountPrice->getDateValidTo()) || $productDiscountPrice->getDateValidTo() > $now))) {
                        $ret["discount_price_base_currency"] = $productDiscountPrice->getDiscountPriceBase();
                        $ret["discount_price_base"] = $productDiscountPrice->getDiscountPriceBase() / $ret["exchange_rate"];
                        $ret["discount_percentage_base"] = $productDiscountPrice->getRebate();
                        $discountStartDate = $productDiscountPrice->getDateValidFrom();
                        $ret["discount_type"] = "discount_catalog";
                    }
                }
            }

            if (!empty($product->getDiscountPriceRetail()) && $product->getDiscountPriceRetail() > 0 && $product->getDiscountPriceRetail() < $product->getPriceRetail() && ((empty($product->getDateDiscountFrom()) || $product->getDateDiscountFrom() < $now) && ((empty($product->getDateDiscountTo()) || $product->getDateDiscountTo() > $now)))) {
                $ret["discount_price_retail_currency"] = $product->getDiscountPriceRetail();
                $ret["discount_price_base_currency"] = $product->getDiscountPriceRetail() / (1 + $product->getTaxType()->getPercent() / 100);
                $ret["discount_price_retail"] = $product->getDiscountPriceRetail() / $ret["exchange_rate"];
                $ret["discount_price_base"] = $ret["discount_price_base_currency"] / $ret["exchange_rate"];
                $ret["discount_percentage_retail"] = $product->getDiscountPercentage();
                $discountStartDate = $product->getDateDiscountFrom();
                $ret["discount_type"] = "product";
            } else {
                /** @var ProductDiscountCatalogPriceEntity $productDiscountPrice */
                $productDiscountPrice = $product->getDiscountCatalogPrices();
                if (!empty($productDiscountPrice)) {
                    if ((empty($productDiscountPrice->getDateValidFrom()) || $productDiscountPrice->getDateValidFrom() < $now) &&
                        ((empty($productDiscountPrice->getDateValidTo()) || $productDiscountPrice->getDateValidTo() > $now))) {
                        $ret["discount_price_retail_currency"] = $productDiscountPrice->getDiscountPriceRetail();
                        $ret["discount_price_base_currency"] = $productDiscountPrice->getDiscountPriceRetail() / (1 + $product->getTaxType()->getPercent() / 100);
                        $ret["discount_price_retail"] = $productDiscountPrice->getDiscountPriceRetail() / $ret["exchange_rate"];
                        $ret["discount_price_base"] = $ret["discount_price_base_currency"] / $ret["exchange_rate"];;
                        $ret["discount_percentage_retail"] = $productDiscountPrice->getRebate();
                        $discountStartDate = $productDiscountPrice->getDateValidFrom();
                        $ret["discount_type"] = "discount_catalog";
                    }
                }
            }
        }

        if (!empty($ret["discount_price_retail_currency"])) {

            $ret["lowest_price_currency"] = null;

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
                    $ret["lowest_price_currency"] = $lowestPrice;
                    $ret["lowest_price_currency"] = $ret["lowest_price_currency"] + floatval($product->getPriceReturn());
                    $ret["lowest_price"] = $ret["lowest_price_currency"] / $ret["exchange_rate"];
                }
            }
        }

        /**
         * Get account group prices if exist
         */
        if (!empty($accountGroup) && floatval($ret["discount_price_retail_currency"]) == 0) {
            /** @var ProductAccountGroupPriceEntity $accountGroupPrice */
            $accountGroupPrice = $product->getAccountGroupPrices($accountGroup);

            if (!empty($accountGroupPrice)) {

                if ((empty($accountGroupPrice->getDateValidFrom()) || $accountGroupPrice->getDateValidFrom() < $now) && ((empty($accountGroupPrice->getDateValidTo()) || $accountGroupPrice->getDateValidTo() > $now))) {

                    if (!$disableRebate) {
                        $ret["price_base"] = $accountGroupPrice->getPriceBase() / $ret["exchange_rate"];
                        $ret["price_base_currency"] = $accountGroupPrice->getPriceBase();
                    }

                    $ret["price_retail_currency"] = $accountGroupPrice->getPriceBase() * (1 + $product->getTaxType()->getPercent() / 100);
                    $ret["price_retail"] = $ret["price_retail_currency"] / $ret["exchange_rate"];

                    $ret["rebate"] = $accountGroupPrice->getRebate();

                    if (!$disableDiscounts) {
                        if (!empty($accountGroupPrice->getDiscountPriceBase()) && !$disableRebate) {
                            $ret["discount_price_base_currency"] = $accountGroupPrice->getDiscountPriceBase();
                            $ret["discount_price_base"] = $accountGroupPrice->getDiscountPriceBase() / $ret["exchange_rate"];
                            $ret["discount_type"] = "discount_account_group";
                            $ret["discount_account_group_id"] = $accountGroupPrice->getAccountGroupId();
                        }

                        if (!empty($accountGroupPrice->getDiscountPriceRetail())) {
                            $ret["discount_price_retail_currency"] = $accountGroupPrice->getDiscountPriceRetail();
                            $ret["discount_price_base_currency"] = $accountGroupPrice->getDiscountPriceRetail() / (1 + $product->getTaxType()->getPercent() / 100);
                            $ret["discount_price_retail"] = $accountGroupPrice->getDiscountPriceRetail() / $ret["exchange_rate"];
                            $ret["discount_type"] = "discount_account_group";
                            $ret["discount_account_group_id"] = $accountGroupPrice->getAccountGroupId();
                        }
                    }
                }
            }
        }

        /**
         * Check account price
         */
        if (!empty($account) && floatval($ret["discount_price_retail_currency"]) == 0) {
            /** @var ProductAccountPriceEntity $accountPrice */
            $accountPrice = $product->getAccountPrices($account);

            if (!empty($accountPrice)) {
                if ((empty($accountPrice->getDateValidFrom()) || $accountPrice->getDateValidFrom() < $now) && ((empty($accountPrice->getDateValidTo()) || $accountPrice->getDateValidTo() > $now))) {

                    if (!$disableRebate) {
                        $ret["price_base"] = $accountPrice->getPriceBase() / $ret["exchange_rate"];
                        $ret["price_base_currency"] = $accountPrice->getPriceBase();
                    }

                    $ret["price_retail_currency"] = $accountPrice->getPriceBase() * (1 + $product->getTaxType()->getPercent() / 100);
                    $ret["price"] = $ret["price_retail_currency"] / $ret["exchange_rate"];
                    $ret["discount_percentage"] = $ret["discount_percentage_retail"] = $ret["discount_percentage_base"] = $accountPrice->getRebate();

                    $ret["rebate"] = $accountPrice->getRebate();

                    if (!$disableDiscounts) {
                        if (!empty($accountPrice->getDiscountPriceBase()) && !$disableRebate) {
                            $ret["discount_price_base_currency"] = $accountPrice->getDiscountPriceBase();
                            $ret["discount_price_base"] = $accountPrice->getDiscountPriceBase() / $ret["exchange_rate"];
                            $ret["discount_percentage"] = $ret["discount_percentage_retail"] = $ret["discount_percentage_base"] = $accountPrice->getRebate();
                            $ret["discount_type"] = "discount_account";
                        }

                        if (!empty($accountPrice->getDiscountPriceRetail())) {
                            $ret["discount_price_retail_currency"] = $accountPrice->getDiscountPriceRetail();
                            $ret["discount_price_base_currency"] = $accountPrice->getDiscountPriceRetail() / (1 + $product->getTaxType()->getPercent() / 100);
                            $ret["discount_price_retail"] = $accountPrice->getDiscountPriceRetail() / $ret["exchange_rate"];
                            $ret["discount_percentage"] = $ret["discount_percentage_retail"] = $ret["discount_percentage_base"] = $accountPrice->getRebate();
                            $ret["discount_type"] = "discount_account";
                        }
                    }
                }
            }
        }

        /**
         * Ako je legal entity i ako se koriste base_price_cijene kao i za prikaz
         */
        if ($ret["is_legal_entity"] && $_ENV["PRICE_USE_PRICES_WITHOUT_VAT_FOR_LEGAL_PERSONS"]) {
            $ret["final_price"] = $ret["price"] = $ret["price_base"] + $ret["return_price"];
            $ret["vat_type"] = "without VAT";
        } else {
            $ret["final_price"] = $ret["price"] = $ret["price_retail"] + $ret["return_price"];
        }

        /**
         * Select discount price to show
         */
        /**
         * Ako je legal entity i ako se koriste base_price_cijene kao i za prikaz
         */
        if ($ret["is_legal_entity"] && $_ENV["PRICE_USE_PRICES_WITHOUT_VAT_FOR_LEGAL_PERSONS"]) {
            if (!empty($ret["discount_price_base"])) {
                $ret["final_price"] = $ret["discount_price"] = $ret["discount_price_base"] + $ret["return_price"];
                $ret["discount_percentage"] = floatval($ret["discount_percentage_base"]);
            }
        } else {
            if (!empty($ret["discount_price_retail"])) {
                $ret["final_price"] = $ret["discount_price"] = $ret["discount_price_retail"] + $ret["return_price"];
                $ret["discount_percentage"] = floatval($ret["discount_percentage_retail"]);
            }
        }

        /**
         * If no discount fill in cash price
         */
        if (empty($ret["discount_price"])) {
            if (!empty($product->getCashPercentage()) && floatval($product->getCashPercentage()) > 0) {
                $ret["cash_percentage"] = $product->getCashPercentage();
                $ret["cash_price_retail"] = $product->getCashPriceRetail();
                $ret["cash_price_base"] = $product->getCashPriceBase();
                /**
                 * Select discount price to show
                 */
                /**
                 * Ako je legal entity i ako se koriste base_price_cijene kao i za prikaz
                 */
                if ($ret["is_legal_entity"] && $_ENV["PRICE_USE_PRICES_WITHOUT_VAT_FOR_LEGAL_PERSONS"]) {
                    $ret["final_price"] = $ret["cash_price"] = $product->getCashPriceBase() + $ret["return_price"];
                } else {
                    $ret["final_price"] = $ret["cash_price"] = $product->getCashPriceRetail() + $ret["return_price"];
                }
            }
        }


        /**
         * Get bulk prices
         */
        if ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_SIMPLE && !empty($product->getBulkPriceRule())) {
            $ret["bulk_prices"] = $this->getBulkPricesForProduct($product, $ret);
        }

        /**
         * Dohvat cijena za bundle i sl
         */
        if (!empty($parentProduct)) {
            $ret = $this->getProductPricesOfCombinedProduct($product, $ret, $parentProduct);
        }

        /**
         * Set installment prices
         */
        $ret["number_of_installments"] = intval($product->getNumberOfInstallments());
        if (!empty($product->getNumberOfInstallments()) && intval($product->getNumberOfInstallments() > 1)) {
            if (!empty($ret["discount_price_retail"])) {
                $ret["installment_price_retail"] = floatval($ret["discount_price_retail"]) / intval($product->getNumberOfInstallments());
            } else {
                $ret["installment_price_retail"] = floatval($ret["price_retail"]) / intval($product->getNumberOfInstallments());
            }
            if (!empty($ret["discount_price_base"])) {
                $ret["installment_price_base"] = floatval($ret["discount_price_base"]) / intval($product->getNumberOfInstallments());
            } else {
                $ret["installment_price_base"] = floatval($ret["price_base"]) / intval($product->getNumberOfInstallments());
            }

            /**
             * Ako je legal entity i ako se koriste base_price_cijene kao i za prikaz
             */
            if ($ret["is_legal_entity"] && $_ENV["PRICE_USE_PRICES_WITHOUT_VAT_FOR_LEGAL_PERSONS"]) {
                $ret["installment_price"] = $ret["installment_price_base"];
            } else {
                $ret["installment_price"] = $ret["installment_price_retail"];
            }
        }

        return $ret;
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

    /**
     * @param QuoteItemEntity $entity
     * @param int $currencyRate
     * @return QuoteItemEntity
     * @throws \Exception
     */
    public function calculatePriceItem2(QuoteItemEntity $entity, $currencyRate = CrmConstants::DEFAULT_CURRENCY_RATE_ID)
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
     * Ovo dolje zelimo izbaciti
     */


}

?>