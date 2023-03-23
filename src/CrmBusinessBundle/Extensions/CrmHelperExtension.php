<?php

namespace CrmBusinessBundle\Extensions;

use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\HnbApiManager;
use CrmBusinessBundle\Managers\QuoteManager;
use ScommerceBusinessBundle\Managers\FrontProductsRulesManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CrmHelperExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var HnbApiManager $hnbApiManager */
    protected $hnbApiManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_available_countries', array($this, 'getAvailableCountries')),
            new \Twig_SimpleFunction('get_codebook', array($this, 'getCodebook')),
            new \Twig_SimpleFunction('convert_price', array($this, 'convertPrice')),
            new \Twig_SimpleFunction('get_master_product', array($this, 'getMasterProduct')), //OVO JE POJEDNOSTAVLJENI REAL BEZ PARAMETARA, KORISTITI IZNIMNO AKO SE NE MOZE DRUGACIJE

            // Get available paymnet types
            new \Twig_SimpleFunction('get_available_payment_types', array($this, 'getAvailablePaymentTypes')),

            // Dohvaca primjenjeni postotak popusta
            new \Twig_SimpleFunction('get_discount_coupon_percentage', array($this, 'getDiscountCouponPercentage')),

            // Get src/CrmBusinessBundle/Managers/ProductAttributeFilterRulesManager.php:53
            new \Twig_SimpleFunction('product_rules_get_filtered_attributed', array($this, 'productRulesGetFilteredAttributed')),
            new \Twig_SimpleFunction('product_rules_get_rendered_existing_attribute_fields', array($this, 'productRulesGetRenderedExistingAttributeFields')),
        ];
    }

    public function productRulesGetFilteredAttributed()
    {
        /** @var FrontProductsRulesManager $rulesManager */
        $rulesManager = $this->container->get("front_product_rules_manager");
        return $rulesManager->getFilteredAttributes();
    }

    public function productRulesGetRenderedExistingAttributeFields($rules)
    {
        /** @var FrontProductsRulesManager $rulesManager */
        $rulesManager = $this->container->get("front_product_rules_manager");
        return $rulesManager->getRenderedExistingAttributeFields($rules);
    }

    public function getAvailableCountries()
    {
        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        return $this->accountManager->getCountries();
    }

    /**
     * @param $entityTypeCode
     * @return mixed
     */
    public function getCodebook($entityTypeCode)
    {
        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }

        return $this->helperManager->getCodebook($entityTypeCode);
    }

    /**
     * @param $product
     * @return \CrmBusinessBundle\Entity\Product|ProductEntity
     */
    public function getMasterProduct($product)
    {

        if ($product->getIsVisible()) {
            return $product;
        }

        /**
         * Check if product is part of configurable or bundle
         * First result will be returned
         */
        if (empty($this->productManager)) {
            $this->productManager = $this->container->get("product_manager");
        }

        return $this->productManager->getMasterProduct($product);
    }

    /**
     * @param $price
     * @param $currentStoreId
     * @param $currencyCode
     * @return float|int
     */
    public function convertPrice($price, $currentStoreId, $currencyCode)
    {
        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }
        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }
        if (empty($this->hnbApiManager)) {
            $this->hnbApiManager = $this->container->get("hnb_api_manager");
        }

        if (empty($currentStoreId)) {
            $currentStoreId = $_ENV["DEFAULT_STORE_ID"];
        }

        $convertToCurrency = $this->hnbApiManager->getCurrencyByCode($currencyCode);

        if (empty($convertToCurrency)) {
            return $price;
        }

        $exchangeRates = array();

        $cacheItem = $this->cacheManager->getCacheGetItem("exchange_rates");
        if (!empty($cacheItem)) {
            $exchangeRates = $cacheItem->get();
        }

        /** @var SStoreEntity $store */
        $store = $this->routeManager->getStoreById($currentStoreId);

        if (isset($exchangeRates[$store->getWebsiteId()][$store->getId()])) {
            return $price * $exchangeRates[$store->getWebsiteId()][$store->getId()]["exchange_rate"] / $convertToCurrency["rate"];
        }

        return $price;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getAvailablePaymentTypes()
    {
        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }
        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        return $this->crmProcessManager->getAvailablePaymentTypes($this->quoteManager->getActiveQuote());
    }

    /**
     * @param QuoteEntity $quote
     * @return float|null
     */
    public function getDiscountCouponPercentage(QuoteEntity $quote)
    {

        if (empty($quote->getDiscountCoupon())) {
            return null;
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        return $this->crmProcessManager->getDiscountCouponPercent($quote->getDiscountCoupon(), $quote->getBasePriceItemsTotal());
    }
}
