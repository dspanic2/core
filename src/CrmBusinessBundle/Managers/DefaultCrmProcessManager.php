<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\NumberHelper;
use AppBundle\Managers\ApplicationSettingsManager;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\MailManager;
use CrmBusinessBundle\CalculationProviders\DefaultCalculationProvider;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\AccountGroupEntity;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Entity\BulkPriceOptionEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\DeliveryPricesEntity;
use CrmBusinessBundle\Entity\DeliveryTypeEntity;
use CrmBusinessBundle\Entity\DiscountCouponEntity;
use CrmBusinessBundle\Entity\DiscountCouponRangeEntity;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Entity\InvoiceEntity;
use CrmBusinessBundle\Entity\InvoiceItemEntity;
use CrmBusinessBundle\Entity\LoyaltyCardEntity;
use CrmBusinessBundle\Entity\NewsletterEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderItemEntity;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\PaymentTypeRuleEntity;
use CrmBusinessBundle\Entity\ProductAccountGroupPriceEntity;
use CrmBusinessBundle\Entity\ProductAccountPriceEntity;
use CrmBusinessBundle\Entity\ProductConfigurationBundleOptionEntity;
use CrmBusinessBundle\Entity\ProductConfigurationProductLinkEntity;
use CrmBusinessBundle\Entity\ProductDiscountCatalogPriceEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;
use DPDBusinessBundle\Entity\DpdParcelEntity;
use GLSBusinessBundle\Entity\GlsParcelEntity;
use HrBusinessBundle\Entity\CityEntity;
use JMS\Serializer\Tests\Fixtures\Order;
use PaymentProvidersBusinessBundle\PaymentProviders\MonriProvider;
use ScommerceBusinessBundle\Entity\OrderReturnEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Entity\WebformEntity;
use ScommerceBusinessBundle\Entity\WebformFieldEntity;
use ScommerceBusinessBundle\Entity\WebformFieldOptionEntity;
use ScommerceBusinessBundle\Entity\WebformSubmissionEntity;
use ScommerceBusinessBundle\Entity\WebformSubmissionValueEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ScommerceBusinessBundle\Managers\BrandsManager;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Managers\SitemapManager;
use ScommerceBusinessBundle\Managers\SproductManager;
use ScommerceBusinessBundle\Managers\TemplateManager;
use Skies\QRcodeBundle\Twig\Extensions\Barcode;
use Symfony\Component\HttpFoundation\JsonResponse;

class DefaultCrmProcessManager extends AbstractBaseManager
{
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var DiscountCouponManager $discountCouponManager */
    protected $discountCouponManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var BarcodeManager $barcodeManager */
    protected $barcodeManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var ProductManager $productManager */
    protected $productManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var DiscountRulesManager $discountRulesManager */
    protected $discountRulesManager;
    /** @var MarginRulesManager $marginRuleManager */
    protected $marginRuleManager;
    /** @var BulkPriceManager $bulkPriceManager */
    protected $bulkPriceManager;
    /** @var BrandsManager $brandsManager */
    protected $brandsManager;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;
    /** @var SitemapManager $sitemapManager */
    protected $sitemapManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var ApplicationSettingsManager $applicationSettingsManager */
    protected $applicationSettingsManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var OrderReturnManager $orderReturnManager */
    protected $orderReturnManager;
    /** @var ProductLabelRulesManager */
    protected $productLabelRuleManager;
    /** @var PaymentTypeRulesManager $paymentTypeRulesManager */
    protected $paymentTypeRulesManager;
    /** @var CampaignManager $campaignManager */
    protected $campaignManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var DefaultCalculationProvider $calculationProvider */
    protected $calculationProvider;

    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }
    }

    /**
     * @param $order
     * @return mixed
     */
    public function getOrderContent($order)
    {

        /** @var TemplateManager $templateManager */
        $templateManager = $this->container->get("template_manager");

        $session = $this->container->get("session");

        $data = array();

        return $this->twig->render(
            $templateManager->getTemplatePathByBundle("Order:order_content.html.twig", $session->get("current_website_id")),
            array("data" => $data)
        );
    }

    /**
     * @param $quote
     * @param array $buttons
     * @param null $quoteHash
     * @return array|bool|null
     */
    public function getQuoteButtons(QuoteEntity $quote, $buttons = array(), $quoteHash = null)
    {

        $ajaxLoadPaymentForm = ((isset($_ENV["CREATE_ORDER_BEFORE_CARD_PAYMENT"]) && $_ENV["CREATE_ORDER_BEFORE_CARD_PAYMENT"] == 1) && ($quote->getPaymentTypeId() == CrmConstants::PAYMENT_TYPE_CARD || $quote->getPaymentTypeId() == CrmConstants::PAYMENT_TYPE_PAYPAL));

        $ret = array();
        $ret["buttons"] = array();
        $ret["forms"] = array();
        $ret["message"] = array();

        /** @var TemplateManager $templateManager */
        $templateManager = $this->container->get("template_manager");

        $session = $this->container->get("session");

        $ret["buttons"][] = $this->twig->render(
            $templateManager->getTemplatePathByBundle("Components/Cart:cart_finish_default.html.twig", $session->get("current_website_id")), array("ajax_load_payment_form" => $ajaxLoadPaymentForm)
        );

        if (!$ajaxLoadPaymentForm) {
            if (!empty($quote->getPaymentType()) && !empty($quote->getPaymentType()->getProvider())) {
                $ret["buttons"] = array();
                $ret["forms"] = array();

                /** @var MonriProvider $monriProvider */
                $monriProvider = $this->container->get($quote->getPaymentType()->getProvider());

                $providerData = $monriProvider->renderTemplateFromQuote($quote);

                $ret["forms"] = $providerData["forms"];
                $ret["buttons"] = $providerData["buttons"];
                if (isset($providerData["redirect_url"])) {
                    $ret["redirect_url"] = $providerData["redirect_url"];
                }
            }
        }

        return $ret;
    }

    /**
     * @param $quote
     * @return mixed
     */
    /*public function getQuoteContent($quote)
    {

        $quoteItemEntityType = $this->entityManager->getEntityTypeByCode("quote_item");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("quote", "eq", $quote->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $quoteItems = $this->entityManager->getEntitiesByEntityTypeAndFilter($quoteItemEntityType, $compositeFilters);

        return $this->renderView(
            'CrmBusinessBundle:Quote:quote_html.html.twig',
            [
                'quote' => $quote,
                'items' => $quoteItems,
                'before_title' => $this->extractH1($quote->getHtmlBeforeItems()),
                'after_title' => $this->extractH1($quote->getHtmlAfterItems()),
            ]
        );
    }*/

    /**
     * @param CompositeFilter $compositeFilter
     * @return CompositeFilter
     */
    public function getOrderDeliveryCompositeFilter(CompositeFilter $compositeFilter)
    {
        $compositeFilter->addFilter(new SearchFilter("orderState.id", "in", implode(",", array(CrmConstants::ORDER_STATE_NEW, CrmConstants::ORDER_STATE_IN_PROCESS))));
        $compositeFilter->addFilter(new SearchFilter("deliveryType.isDelivery", "eq", 1));

        return $compositeFilter;
    }

    /**
     * @param $data
     * @return null
     */
    public function getDeliveryCountryIdFromData($data)
    {

        $countryId = null;
        if (isset($data["account_shipping_address_id"]) && !empty($data["account_shipping_address_id"])) {
            if (empty($this->accountManager)) {
                $this->accountManager = $this->container->get("account_manager");
            }

            $address = $this->accountManager->getAddressById($data["account_shipping_address_id"]);
            $countryId = $address->getCity()->getCountryId();
        } elseif (isset($data["shipping_country_id"]) && !empty($data["shipping_country_id"])) {
            $countryId = $data["shipping_country_id"];
        } elseif (isset($data["country_id"]) && !empty($data["country_id"])) {
            $countryId = $data["country_id"];
        }

        return $countryId;
    }

    /**
     * @param QuoteEntity $quote
     * @param DeliveryTypeEntity $deliveryType
     * @param $countryId
     * @param $postalCode
     * @return \CrmBusinessBundle\Entity\decimal|float|int
     */
    public function calculateDelivery(QuoteEntity $quote, DeliveryTypeEntity $deliveryType, $countryId, $postalCode)
    {

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        $totalBasePriceDeliveryWithoutTax = 0;

        if (EntityHelper::isCountable($quote->getQuoteItems()) && count($quote->getQuoteItems()) == 0) {
            return $totalBasePriceDeliveryWithoutTax;
        }

        $size = 0;
        $applyFreeDelivery = false;

        /**
         * Apply coupon free delivery if coupon exists
         */
        if (!empty($quote->getDiscountCoupon()) && $quote->getDiscountCoupon()->getForceFreeDelivery()) {
            return $totalBasePriceDeliveryWithoutTax;
        }

        /**
         * If price
         */
        $size = floatval($quote->getBasePriceItemsTotal()) - floatval($quote->getDiscountLoyaltyBasePriceTotal()) - floatval($quote->getDiscountCouponPriceTotal());
        /**
         * If weight
         */
        /*$quoteItems = $quote->getQuoteItems();
        if (EntityHelper::isCountable($quoteItems) && count($quoteItems) > 0) {
            foreach ($quoteItems as $quoteItem) {
                $size = $size + ($quoteItem->getQty() * $quoteItem->getProduct()->getWeight());
            }
        }*/

        /** @var DeliveryPricesEntity $deliveryPrice */
        $deliveryPrice = $this->quoteManager->getDeliveryPrice($deliveryType, $countryId, $postalCode, $size);

        if (!empty($deliveryPrice) && !$applyFreeDelivery) {
            $totalBasePriceDeliveryWithoutTax = $deliveryPrice->getPriceBase();

            if ($deliveryPrice->getPriceBaseStep() > 0 && $deliveryPrice->getForEveryNextSize() > 0 && $deliveryPrice->getStepStartsAt() && $deliveryPrice->getStepStartsAt() < floatval($size)) {
                $size = floatval($size) - floatval($deliveryPrice->getStepStartsAt());

                $steps = ceil($size / floatval($deliveryPrice->getForEveryNextSize())) - 1;

                $totalBasePriceDeliveryWithoutTax = $totalBasePriceDeliveryWithoutTax + $deliveryPrice->getPriceBaseStep() * $steps;
            }
        }

        return $totalBasePriceDeliveryWithoutTax;
    }


    /**
     * @param QuoteEntity $quote
     * @param bool $saveDeliveryPrice
     * @param array $data
     * @return \CrmBusinessBundle\Entity\decimal|QuoteEntity|float|int
     */
    public function calculateQuoteDeliveryPrice(QuoteEntity $quote, $saveDeliveryPrice = true, $data = array())
    {
        $changed = false;
        $ret = array();

        $totalBasePriceDeliveryWithoutTax = 0; //0 by default
        $totalBasePriceDeliveryTax = 0;
        $totalBasePriceDeliveryTotal = 0;

        $countryId = null;
        $postalCode = null;

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        if (isset($data["delivery_type_id"]) && !empty($data["delivery_type_id"])) {

            /** @var DeliveryTypeEntity $deliveryType */
            $deliveryType = $this->quoteManager->getDeliveryTypeById($data["delivery_type_id"]);

            if ($deliveryType->getIsDelivery()) {
                if (!isset($data["shipping_address_same"])) {
                    $data["shipping_address_same"] = 1;
                } else {
                    $data["shipping_address_same"] = 0;
                }

                if (empty($this->accountManager)) {
                    $this->accountManager = $this->container->get("account_manager");
                }

                if (isset($data["city_id"]) && !empty($data["city_id"])) {
                    /** @var CityEntity $city */
                    $city = $this->accountManager->getCityById($data["city_id"]);
                    if (!empty($city)) {
                        $data["postal_code"] = $city->getPostalCode();
                    }
                }
                if (isset($data["shipping_city_id"]) && !empty($data["shipping_city_id"])) {
                    /** @var CityEntity $shippingCity */
                    $shippingCity = $this->accountManager->getCityById($data["shipping_city_id"]);
                    if (!empty($shippingCity)) {
                        $data["shipping_postal_code"] = $shippingCity->getPostalCode();
                    }
                }

                if (isset($data["account_shipping_address_id"]) && !empty($data["account_shipping_address_id"])) {
                    /** @var AddressEntity $address */
                    $address = $this->accountManager->getAddressById($data["account_shipping_address_id"]);

                    $countryId = $address->getCity()->getCountryId();
                    $postalCode = $address->getCity()->getPostalCode();
                } elseif (!$data["shipping_address_same"] && isset($data["shipping_country_id"]) && !empty($data["shipping_country_id"]) && isset($data["shipping_postal_code"]) && !empty($data["shipping_postal_code"])) {
                    $countryId = $data["shipping_country_id"];
                    $postalCode = $data["shipping_postal_code"];
                } elseif (isset($data["country_id"]) && !empty($data["country_id"]) && isset($data["postal_code"]) && !empty($data["postal_code"])) {
                    $countryId = $data["country_id"];
                    $postalCode = $data["postal_code"];
                }

                if (empty($countryId) || empty($postalCode)) {
                    $data["delivery_type_id"] = null;
                }
            }
        } else {
            if (!empty($quote->getDeliveryType())) {
                $data["delivery_type_id"] = $quote->getDeliveryType()->getId();
                if ($quote->getDeliveryType()->getIsDelivery()) {
                    $deliveryType = $quote->getDeliveryType();
                    if (!empty($quote->getAccountShippingAddress())) {
                        $countryId = $quote->getAccountShippingAddress()->getCity()->getCountryId();
                        $postalCode = $quote->getAccountShippingAddress()->getCity()->getPostalCode();
                    } else {
                        $data["delivery_type_id"] = null;
                    }
                }
            } else {
                $data["delivery_type_id"] = null;
            }
        }

        if (!empty($countryId)) {
            $totalBasePriceDeliveryWithoutTax = $this->calculateDelivery($quote, $deliveryType, $countryId, $postalCode);
        }

        /** @var AccountEntity $account */
        $account = $quote->getAccount();

        if (!empty($account) && $account->getFreeDelivery()) {
            $totalBasePriceDeliveryWithoutTax = 0;
        }

        $currencyRate = $quote->getCurrencyRate();
        if (empty(floatval($currencyRate))) {
            $currencyRate = 1;
        }

        $totalBasePriceDeliveryTax = $totalBasePriceDeliveryWithoutTax * 25 / 100;
        $totalBasePriceDeliveryTotal = $totalBasePriceDeliveryWithoutTax + $totalBasePriceDeliveryTax;

        $totalPriceDeliveryWithoutTax = $ret["totalPriceDeliveryWithoutTax"] = $totalBasePriceDeliveryWithoutTax / $currencyRate;
        $totalPriceDeliveryTax = $ret["totalPriceDeliveryTax"] = $totalBasePriceDeliveryTax / $currencyRate;
        $totalPriceDeliveryTotal = $ret["totalPriceDeliveryTotal"] = $totalBasePriceDeliveryTotal / $currencyRate;

        if (!$saveDeliveryPrice) {
            return $ret;
        }

        if ($totalBasePriceDeliveryTotal != $quote->getBasePriceDeliveryTotal()) {
            $quote->setBasePriceDeliveryTotal($totalBasePriceDeliveryTotal);
            $changed = true;
        }

        if ($totalBasePriceDeliveryTax != $quote->getBasePriceDeliveryTax()) {
            $quote->setBasePriceDeliveryTax($totalBasePriceDeliveryTax);
            $changed = true;
        }

        if ($totalBasePriceDeliveryWithoutTax != $quote->getBasePriceDeliveryWithoutTax()) {
            $quote->setBasePriceDeliveryWithoutTax($totalBasePriceDeliveryWithoutTax);
            $changed = true;
        }

        if ($totalPriceDeliveryTotal != $quote->getPriceDeliveryTotal()) {
            $quote->setPriceDeliveryTotal($totalPriceDeliveryTotal);
            $changed = true;
        }

        if ($totalPriceDeliveryTax != $quote->getPriceDeliveryTax()) {
            $quote->setPriceDeliveryTax($totalPriceDeliveryTax);
            $changed = true;
        }

        if ($totalPriceDeliveryWithoutTax != $quote->getPriceDeliveryWithoutTax()) {
            $quote->setPriceDeliveryWithoutTax($totalPriceDeliveryWithoutTax);
            $changed = true;
        }

        if ($changed) {

            if(empty($this->calculationProvider)){
                $this->calculationProvider = $this->container->get($_ENV["CALCULATION_PROVIDER"]);
            }

            $quote = $this->calculationProvider->recalculateQuoteTotals($quote);
        }

        return $quote;
    }

    /**
     * @return PaymentTypeEntity
     */
    public function getDefaultPaymentType()
    {
        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        return $this->quoteManager->getPaymentTypeById(1);
    }

    /**
     * @param QuoteEntity $quote
     * @param $data
     * @return mixed
     */
    public function getAvailablePaymentTypes(QuoteEntity $quote, $data = [])
    {
        $storeId = $_ENV["DEFAULT_STORE_ID"];
        $websiteId = $_ENV["DEFAULT_WEBSITE_ID"];
        if (!empty($quote) && !empty($quote->getStoreId())) {
            $storeId = $quote->getStoreId();
            $websiteId = $quote->getStore()->getWebsiteId();
        }

        $entityType = $this->entityManager->getEntityTypeByCode("payment_type");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("active", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        /**
         * If COUPON CODE with card payment remove everything except MONRI
         */
        if (!empty($quote->getDiscountCoupon()) && !empty($quote->getDiscountCoupon()->getAllowedCards())) {
            $compositeFilter->addFilter(new SearchFilter("id", "eq", CrmConstants::PAYMENT_TYPE_CARD));
        }

        if (!empty($quote) && $quote->getBasePriceTotal() > 0) {
            $compositeFilterSub = new CompositeFilter();
            $compositeFilterSub->setConnector("or");
            $compositeFilterSub->addFilter(new SearchFilter("maxCartTotal", "json_ge", json_encode(array($quote->getBasePriceTotal(), '$."' . $websiteId . '"'))));
            $compositeFilterSub->addFilter(new SearchFilter("maxCartTotal", "nu", null));
            $compositeFilterSub->addFilter(new SearchFilter("maxCartTotal", "json_lt", json_encode(array(2, '$."' . $websiteId . '"'))));
            $compositeFilter->addFilter($compositeFilterSub);

            $compositeFilterSub = new CompositeFilter();
            $compositeFilterSub->setConnector("or");
            $compositeFilterSub->addFilter(new SearchFilter("minCartTotal", "json_le", json_encode(array($quote->getBasePriceTotal(), '$."' . $websiteId . '"'))));
            $compositeFilterSub->addFilter(new SearchFilter("minCartTotal", "nu", null));
            $compositeFilterSub->addFilter(new SearchFilter("minCartTotal", "json_lt", json_encode(array(1, '$."' . $websiteId . '"'))));
            $compositeFilter->addFilter($compositeFilterSub);
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("name", "asc"));

        $paymentTypes = $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);

        if (EntityHelper::isCountable($paymentTypes) && count($paymentTypes) > 0) {
            /**
             * @var  $key
             * @var PaymentTypeEntity $paymentType
             */
            foreach ($paymentTypes as $key => $paymentType) {
                $rules = $paymentType->getActivePaymentTypeRules();
                if (!empty($rules)) {

                    $productIds = $quote->getProductIds();

                    if (empty($productIds)) {
                        return $paymentTypes;
                    }

                    if (empty($this->paymentTypeRulesManager)) {
                        $this->paymentTypeRulesManager = $this->container->get("payment_type_rules_manager");
                    }

                    /** @var PaymentTypeRuleEntity $rule */
                    foreach ($rules as $rule) {
                        if ($this->paymentTypeRulesManager->checkIfProductInRule($rule, $productIds)) {
                            unset($paymentTypes[$key]);
                        }
                    }
                }
            }
        }

        return $paymentTypes;
    }

    /**
     * @return DeliveryTypeEntity
     */
    public function getDefaultDeliveryType()
    {
        return null;
    }

    /**
     * @param QuoteEntity $quote
     * @param $data
     * @return mixed
     */
    public function getAvailableDeliveryTypes(QuoteEntity $quote, $data)
    {
        $storeId = $_ENV["DEFAULT_STORE_ID"];
        if (!empty($quote) && !empty($quote->getStoreId())) {
            $storeId = $quote->getStoreId();
        }

        $entityType = $this->entityManager->getEntityTypeByCode("delivery_type");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("active", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("ord", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param ProductEntity|null $product
     * @param $account
     * @param $parentProduct
     * @return string
     */
    public function getCalculationMethod(ProductEntity $product = null, $account = null, $parentProduct = null){

        $method = "Mpc";

        /*if(!empty($account) && $account->getIsLegalEntity()){
            $method = "Vpc";
        }*/

        return $method;
    }

    /**
     * @param ProductEntity $product
     * @param AccountEntity|null $account
     * @param ProductEntity|null $parentProduct
     * @return mixed
     */
    public function getProductPrices(ProductEntity $product, AccountEntity $account = null, ProductEntity $parentProduct = null)
    {
        if(empty($this->calculationProvider)){
            $this->calculationProvider = $this->container->get($_ENV["CALCULATION_PROVIDER"]);
        }

        $method = "getProductPrices".$this->getCalculationMethod($product, $account, $parentProduct);

        return $this->calculationProvider->{$method}($product, $account, $parentProduct);
    }

    /**
     * @param OrderEntity $order
     * @return bool|null
     * @throws \Exception
     */
    public function afterOrderCreated(OrderEntity $order)
    {

        $isProduction = $_ENV["ENABLE_OUTGOING_EMAIL"] ?? 0;

        if (isset($_ENV["ORDER_REQUEST_OFFER"]) && $_ENV["ORDER_REQUEST_OFFER"] == 1) {
            $mailTemplate = "order_request_offer";
        } else {
            $mailTemplate = "order_confirmation";
        }

        if ($isProduction) {

            /** @var ContactEntity $contact */
            $contact = $order->getContact();

            if (empty($this->mailManager)) {
                $this->mailManager = $this->container->get("mail_manager");
            }

            $bcc = array(
                'email' => $_ENV["ORDER_EMAIL_RECIPIENT"],
                'name' => $_ENV["ORDER_EMAIL_RECIPIENT"],
            );
            $attachments = array();

            /*if($order->getPaymentTypeId() == CrmConstants::PAYMENT_VIRMAN){
                if(empty($this->barcodeManager)){
                    $this->barcodeManager = $this->container->get("barcode_manager");
                }

                $targetPath = $this->barcodeManager->generatePDF417Barcode($order);
                $targetPath = str_ireplace(".jpeg",".pdf",$targetPath);
                $targetPath = str_ireplace("//","/",$targetPath);

                $webPath = $_ENV["WEB_PATH"];

                if (file_exists($webPath . $targetPath)) {
                    unlink($webPath . $targetPath);
                }

                $attachments = array($targetPath);
            }*/

            /** @var EmailTemplateManager $emailTemplateManager */
            $emailTemplateManager = $this->container->get('email_template_manager');
            /** @var EmailTemplateEntity $template */
            $template = $emailTemplateManager->getEmailTemplateByCode($mailTemplate);
            if (!empty($template)) {
                $templateData = $emailTemplateManager->renderEmailTemplate($order, $template);
                $templateAttachments = $template->getAttachments();
                if (!empty($templateAttachments)) {
                    $attachments = array_merge($attachments, $template->getPreparedAttachments());
                }
                $this->mailManager->sendEmail(
                    array('email' => $contact->getEmail(), 'name' => $contact->getEmail()),
                    null,
                    $bcc,
                    null,
                    $templateData["subject"],
                    "",
                    null,
                    [],
                    $templateData["content"],
                    $attachments,
                    $order->getStoreId()
                );
            } else {
                $this->mailManager->sendEmail(
                    array('email' => $contact->getEmail(), 'name' => $contact->getEmail()),
                    null,
                    $bcc,
                    null,
                    $this->translator->trans(
                        'Order confirmation'
                    ) . " {$order->getIncrementId()} - {$order->getAccountName()}",
                    "",
                    $mailTemplate,
                    array("order" => $order),
                    null,
                    $attachments,
                    $order->getStoreId()
                );
            }

            if (empty($this->orderManager)) {
                $this->orderManager = $this->container->get("order_manager");
            }

            $orderState = $this->orderManager->getOrderStateById(CrmConstants::ORDER_STATE_IN_PROCESS);

            $data = array();
            $data["orderState"] = $orderState;
            $data["sentToErp"] = 1;
            $data["dateSentToErp"] = new \DateTime();
            $this->orderManager->updateOrder($order, $data);

        } else {
            //ovo je za dev ako bude trebalo
        }

        return false;
    }

    /**
     * @param QuoteEntity $quote
     * @return array|null
     */
    public function getAdditionalQuoteData(QuoteEntity $quote)
    {

        $ret = array();
        $ret["min_order"] = array();
        $ret["min_order"]["disable_cart"] = false;
        $ret["min_order"]["diff_to_min_order"] = 0;
        $ret["min_order"]["warning"] = null;

        return $ret;
    }

    /**
     * @return array
     */
    public function getPaymentProviderUrls()
    {
        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }

        $session = $this->container->get('session');

        $ret = array();
        $ret["cartUrl"] = $session->get("current_language_url") . "/" . $this->getPageUrlExtension->getPageUrl($session->get("current_store_id"), 56, "s_page") . "?step=1";
        $ret["quoteUrl"] = $session->get("current_language_url") . "/" . $this->getPageUrlExtension->getPageUrl($session->get("current_store_id"), 56, "s_page") . "?step=2";
        $ret["quoteErrorUrl"] = $session->get("current_language_url") . "/" . $this->getPageUrlExtension->getPageUrl($session->get("current_store_id"), 55, "s_page");
        $ret["quoteSuccessUrl"] = $session->get("current_language_url") . "/" . $this->getPageUrlExtension->getPageUrl($session->get("current_store_id"), 54, "s_page");

        return $ret;
    }

    /**
     * @param QuoteEntity $quote
     * @return array
     */
    public function validateQuotePaymentDelivery(QuoteEntity $quote)
    {

        $ret = array();
        $ret["error"] = false;

        return $ret;
    }

    /**
     * @param QuoteEntity $quote
     * @return array
     */
    public function validateCustomAdminQuote(QuoteEntity $quote)
    {

        $ret = array();
        $ret["error"] = false;

        if (!EntityHelper::isCountable($quote->getQuoteItems()) || count($quote->getQuoteItems()) == 0) {
            $ret["message"] = $this->translator->trans("Missing items on quote");
            return $ret;
        }

        if (empty($quote->getAccountBillingAddress())) {
            $ret["message"] = $this->translator->trans("Missing billing address");
            return $ret;
        }

        if (empty($quote->getDeliveryType())) {
            $ret["message"] = $this->translator->trans("Missing delivery type");
            return $ret;
        }

        if (empty($quote->getPaymentType())) {
            $ret["message"] = $this->translator->trans("Missing payment type");
            return $ret;
        }

        if ($quote->getDeliveryType()->getIsDelivery() && empty($quote->getAccountShippingAddress())) {
            $ret["message"] = $this->translator->trans("Missing delivery address");
            return $ret;
        }

        return $ret;
    }

    /**
     * @return bool
     */
    public function unlockQuotes()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE quote_entity SET is_locked = 0 WHERE is_locked = 1 AND modified < DATE_SUB(NOW(),INTERVAL 15 MINUTE);";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param QuoteEntity $quote
     * @return array|null
     * @throws \Exception
     */
    public function validateQuote(QuoteEntity $quote)
    {

        $ret = $this->getPaymentProviderUrls();

        $cartUrl = $ret["cartUrl"];
        $quoteUrl = $ret["quoteUrl"];
        $quoteErrorUrl = $ret["quoteErrorUrl"];
        $quoteSuccessUrl = $ret["quoteSuccessUrl"];

        $ret = array();
        $ret["error"] = false;
        $ret["changed"] = false;

        $session = $this->container->get('session');

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        /**
         * Validate account
         */
        if (empty($quote->getAccount())) {
            $ret["error"] = true;
            $ret["redirect_url"] = $cartUrl;
            $session->set("quote_error", $this->translator->trans('Your account is empty. Please fill in personal data.'));
            return $ret;
        }

        /**
         * Validate contact
         */
        if (empty($quote->getContact())) {
            $ret["error"] = true;
            $ret["redirect_url"] = $cartUrl;
            $session->set("quote_error", $this->translator->trans('Your contact is empty. Please fill in personal data.'));
            return $ret;
        }

        if (empty($quote->getAccountBillingAddress())) {

            /** @var AddressEntity $address */
            $address = $quote->getAccount()->getBillingAddress();

            if (empty($address)) {
                $ret["error"] = true;
                $ret["redirect_url"] = $cartUrl;
                $session->set("quote_error", $this->translator->trans('Please fill in billing address.'));
                return $ret;
            }

            $quoteData = array();
            $quoteData["account_billing_address"] = $address;
            $quoteData["account_billing_street"] = $address->getStreet();
            $quoteData["account_billing_city"] = $address->getCity();
            $this->quoteManager->updateQuote($quote, $quoteData, true);
        }

        if ((isset($_ENV["ORDER_REQUEST_OFFER"]) && $_ENV["ORDER_REQUEST_OFFER"] != 1) && empty($quote->getDeliveryType())) {
            $ret["error"] = true;
            $ret["redirect_url"] = $cartUrl;
            $session->set("quote_error", $this->translator->trans('Please select delivery type.'));
            return $ret;
        }

        if (empty($quote->getPaymentType()) && $_ENV["ORDER_REQUEST_OFFER"] != 1) {
            $ret["error"] = true;
            $ret["redirect_url"] = $cartUrl;
            $session->set("quote_error", $this->translator->trans('Please select payment type.'));
            return $ret;
        }

        if (empty($quote->getAccountBillingAddress())) {
            $ret["error"] = true;
            $ret["redirect_url"] = $cartUrl;
            $session->set("quote_error", $this->translator->trans('Please fill in billing address.'));
            return $ret;
        }

        if ((isset($_ENV["ORDER_REQUEST_OFFER"]) && $_ENV["ORDER_REQUEST_OFFER"] != 1) && $quote->getDeliveryType()->getIsDelivery() && empty($quote->getAccountShippingAddress())) {
            $ret["error"] = true;
            $ret["redirect_url"] = $cartUrl;
            $session->set("quote_error", $this->translator->trans('Please fill in shipping address.'));
            return $ret;
        }

        /**
         * Check for empty quote items
         */
        $quoteItems = $quote->getQuoteItems();

        if (!EntityHelper::isCountable($quoteItems) || count($quoteItems) == 0) {
            $ret["error"] = true;
            $ret["redirect_url"] = $cartUrl;
            $session->set("quote_error", $this->translator->trans('Order failed. Your quote is empty.'));
            //$this->logger->error("VALIDATE QUOTE: empty items " . $quote->getIncrementId());

            return $ret;
        }

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }

        $changed = false;

        /**
         * Check if quote item is saleable
         */
        /** @var QuoteItemEntity $quoteItem */
        foreach ($quoteItems as $quoteItem) {

            /**
             * Potrebno je avoidati configurable
             */
            if ($quoteItem->getProduct()->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
                continue;
            }

            $retItem = $this->validateQuoteItemQty($quoteItem->getProduct(), $quote, $quoteItem->getQty(), false, $quoteItem);

            if (isset($retItem["error"]) && $retItem["error"]) {
                $changed = true;
                $this->quoteManager->removeItemFromQuote($quoteItem);
                $session->set(
                    "quote_error",
                    $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $quoteItem->getProduct(), "name") . " " . $this->translator->trans('is not available any more.')
                //$retItem["message"]
                );
            }

            /**
             * Izmijenjeno na v 1.7. Kada se ustabili, ukloniti
             * Testirano na Unimar, Anda
             * Potrebno potvrditi na Gardenu
             */
            /*if ($quoteItem->getQty() == 0) {
                $changed = true;
                $this->quoteManager->removeItemFromQuote($quoteItem);
                $this->logger->error("VALIDATE QUOTE: quote item 0 qty " . $quote->getIncrementId());
            } else {
                $product = $quoteItem->getProduct();

                if (!$product->getIsSaleable()) {
                    $changed = true;
                    $this->quoteManager->removeItemFromQuote($quoteItem);
                    $session->set(
                        "quote_error",
                        $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $product, "name") . " " . $this->translator->trans('is not available any more.')
                    );
                }
            }*/
        }

        /**
         * Check if discount coupon is still valid
         */
        if (!empty($quote->getDiscountCoupon())) {

            $isValid = $this->checkIfDiscountCouponCanBeApplied($quote, $quote->getDiscountCoupon());
            if (!$isValid) {

                $session->set(
                    "quote_error",
                    $this->translator->trans("Discount coupon cannot be applied or is not valid any more")
                );

                $this->applyDiscountCoupon($quote, null);
                $ret["redirect_url"] = $cartUrl;
                $ret["changed"] = true;

                return $ret;
            } elseif (!empty($quote->getDiscountCoupon()->getAllowedCards()) && $quote->getPaymentType()->getId() != CrmConstants::PAYMENT_TYPE_CARD) {

                $session->set(
                    "quote_error",
                    $this->translator->trans("Payment type not valid for applied coupon. Please remove the coupon or change the payment type.")
                );

                $ret["redirect_url"] = $cartUrl;
                $ret["changed"] = true;

                return $ret;
            }
        }

        if ($changed) {


            $ret["redirect_url"] = $cartUrl;
            $ret["changed"] = true;

            return $ret;
        }

        $this->entityManager->refreshEntity($quote);

        /**
         * Check if quote is now empty
         */
        $quoteItems = $quote->getQuoteItems();

        if (!EntityHelper::isCountable($quoteItems) || count($quoteItems) == 0) {
            $ret["error"] = true;
            $ret["redirect_url"] = $cartUrl;
            $session->set("quote_error", $this->translator->trans('Order failed. Your quote is empty.'));
            //$this->logger->error("VALIDATE QUOTE: empty items " . $quote->getIncrementId());

            return $ret;
        }

        $quote = $this->quoteManager->recalculateQuoteItems($quote);

        return $ret;
    }

    /**
     * @param ProductEntity $product
     * @param null $options
     * @return array
     * @throws \Exception
     */
    public function validateBundleProductOptions(ProductEntity $product, $options = null)
    {

        $ret = array();
        $ret["error"] = false;
        $ret["message"] = "";

        if (empty($this->productManager)) {
            $this->productManager = $this->container->get("product_manager");
        }
        $relations = $this->productManager->getBundleProductDetails($product);

        if (!isset($relations["bundle_product"]) && empty($relations["bundle_product"])) {
            $ret["error"] = true;
            $ret["message"] = $this->translator->trans("Missing bundle products");
            return $ret;
        }

        $mandatoryProducts = array();
        foreach ($relations["bundle_product"] as $relation) {
            if (floatval($relation["minQty"]) > 0) {
                $mandatoryProducts[] = $relation["childProduct"];
            }
        }

        if (!empty($mandatoryProducts)) {
            if (!isset($options["bundle"]) || empty($options["bundle"])) {
                $ret["error"] = true;
                $ret["message"] = $this->translator->trans("Please select mandatory product in bundle");
                return $ret;
            }

            if (empty($this->getPageUrlExtension)) {
                $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
            }

            /** @var ProductEntity $mandatoryProductId */
            foreach ($mandatoryProducts as $mandatoryProduct) {
                if (!in_array($mandatoryProduct->getId(), $options["bundle"])) {
                    $ret["error"] = true;
                    $ret["message"] = $ret["message"] . $this->translator->trans("Missing mandatory configuration") . " " . $this->getPageUrlExtension->getEntityStoreAttribute(null, $mandatoryProduct, "name") . "<br>";
                }
            }
        }

        $ret["bundle_product_links"] = array();
        foreach ($relations["bundle_product"] as $relation) {
            if (!in_array($relation["childProduct"]->getId(), $options["bundle"])) {
                continue;
            }
            $ret["bundle_product_links"][$relation["childProduct"]->getId()] = $relation;
            if ($ret["bundle_product_links"][$relation["childProduct"]->getId()]["minQty"] == 0) {
                $ret["bundle_product_links"][$relation["childProduct"]->getId()]["minQty"] = 1;
            }
        }

        return $ret;
    }

    /**
     * @param ProductEntity $product
     * @param null $options
     * @return array
     * @throws \Exception
     */
    public function validateConfigurableProductOptions(ProductEntity $product, $options = null)
    {
        $ret = array();
        $ret["error"] = false;
        $ret["message"] = "";

        $configurableOptions = $product->getProductConfigurations();

        if (!EntityHelper::isCountable($configurableOptions) || count($configurableOptions) == 0) {
            $ret["error"] = true;
            $ret["message"] = $this->translator->trans("Missing product configurations");
            return $ret;
        }

        $mandatoryConfigurations = array();
        /** @var ProductConfigurationProductLinkEntity $configurableOption */
        foreach ($configurableOptions as $configurableOption) {
            if ($configurableOption->getConfigurableBundleOption()->getIsMandatory()) {

                /**
                 * Check if at least one product is saleble to be mandatory
                 */
                $childProducts = $configurableOption->getConfigurableBundleOption()->getProducts();

                $hasAvailableProduct = false;
                if (EntityHelper::isCountable($childProducts) && count($childProducts)) {
                    foreach ($childProducts as $childProduct) {
                        if ($this->isProductValid($childProduct)) {
                            $hasAvailableProduct = true;
                            break;
                        }
                    }
                }

                if ($hasAvailableProduct) {
                    $mandatoryConfigurations[] = $configurableOption->getConfigurableBundleOption();
                }
            }
        }

        if (!empty($mandatoryConfigurations)) {
            if (!isset($options["configurable_bundle"]) || empty($options["configurable_bundle"])) {
                $ret["error"] = true;
                $ret["message"] = $this->translator->trans("Please select mandatory configurations");
                return $ret;
            }

            if (empty($this->getPageUrlExtension)) {
                $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
            }

            /** @var ProductConfigurationBundleOptionEntity $mandatoryConfiguration */
            foreach ($mandatoryConfigurations as $mandatoryConfiguration) {

                if (!array_key_exists($mandatoryConfiguration->getId(), $options["configurable_bundle"]) || empty($options["configurable_bundle"][$mandatoryConfiguration->getId()])) {
                    $ret["error"] = true;
                    $ret["message"] = $ret["message"] . $this->translator->trans("Missing mandatory configuration") . " " . $this->getPageUrlExtension->getEntityStoreAttribute(null, $mandatoryConfiguration, "title_for_web") . "<br>";
                }
            }
        }

        return $ret;
    }

    /**
     * @param ProductEntity $product
     * @param QuoteEntity $quote
     * @param $requestedQty
     * @param $add
     * @param QuoteItemEntity|null $quoteItem
     * @return array
     */
    public function validateQuoteItemQty(ProductEntity $product, QuoteEntity $quote, $requestedQty, $add, QuoteItemEntity $quoteItem = null)
    {
        $requestedQty = floatval($requestedQty);

        $ret = array();
        $ret["error"] = false;
        $ret["message"] = null;
        $ret["reload"] = false;
        $ret["qty"] = $requestedQty;
        $ret["add"] = $add;

        if ($product->getQtyStep() > 0 && $product->getQtyStep() != 1) {
            if (NumberHelper::modulo($requestedQty, $product->getQtyStep()) != 0) {

                $ret["qty"] = $product->getQtyStep();
                $ret["add"] = false;
            }
        } elseif ($product->getQtyStep() == 1) {
            $ret["qty"] = intval($ret["qty"]);
        }

        /**
         * If has coupon with limited number of products per coupon check if allowed to add to cart
         */
        if (!empty($quote->getDiscountCoupon()) && intval($quote->getDiscountCoupon()->getMaxNumberOfProductsInCart()) > 0) {
            if ($this->couponIsApplicableOnProduct($quote->getDiscountCoupon(), $product)) {
                /*dump(1);
                die;*/
            }
            //todo check if product on discount coupon
            //check if contencts of quote
        }

        /** Qty of same product in other quote items if exists */
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }
        $additionaWhere = "";
        if (!empty($quoteItem)) {
            /**
             *  Provjera stanja za configurabilne
             */
            if (!empty($quoteItem->getConfigurableProductOptions())) {
                $childItems = $quoteItem->getChildItems();
                if (EntityHelper::isCountable($childItems) && count($childItems)) {
                    $additionaWhere = " AND id != {$childItems[0]->getId()} ";
                }
            } else {
                $additionaWhere = " AND id != {$quoteItem->getId()} ";
            }
        }
        $q = "SELECT SUM(qty) as total_qty FROM quote_item_entity WHERE quote_id = {$quote->getId()} AND product_id = {$product->getId()} {$additionaWhere};";
        $totalCurrentQtyInQuote = $this->databaseContext->getSingleEntity($q);
        if (empty($totalCurrentQtyInQuote)) {
            $totalCurrentQtyInQuote = 0;
        } else {
            $totalCurrentQtyInQuote = $totalCurrentQtyInQuote["total_qty"];
        }

        if (empty($totalCurrentQtyInQuote)) {
            $totalCurrentQtyInQuote = 0;
        }

        $currentQuoteItemQty = 0;
        if (!empty($quoteItem)) {
            $currentQuoteItemQty = $this->prepareQty($quoteItem->getQty(), $product->getQtyStep());
        }

        /**
         * If product is not saleable return error
         */
        if (!$product->getIsSaleable() && !$quote->getSkipQtyCheck()) {
            $ret["qty"] = 0;
            $ret["add"] = false;
            $ret["error"] = true;
            $ret["message"] = $ret["message"] . $this->translator->trans("This product is not available any more");

            return $ret;
        }

        $maxQty = $this->prepareQty($product->getQty(), $product->getQtyStep());
        if ($quote->getSkipQtyCheck()) {
            $maxQty = 999999;
        }

        if (method_exists($product, "getQuoteItemLimit") && !empty($product->getQuoteItemLimit()) && $product->getQuoteItemLimit() > 0) {
            $maxQty = $product->getQuoteItemLimit();
        }

        /**
         * If add, max available qty includes current qty in quote item
         */
        /*if ($add) {
            $availableQty = $maxQty - ($totalCurrentQtyInQuote + $currentQuoteItemQty);
        }*/ /**
     * If update, max available qty does not include current qty in quote item
     */
        /*else {
            $availableQty = $maxQty - ($totalCurrentQtyInQuote + $currentQuoteItemQty);
        }*/

        $availableQty = $maxQty - ($totalCurrentQtyInQuote + $currentQuoteItemQty);

        $availableQty = $this->prepareQty($availableQty, $product->getQtyStep());

        /* if($product->getId() == 23055){
             dump($maxQty);
             dump($maxQty);
             dump($retTmp["qty"]);
         }*/

        /**
         * If quote item exists and product is being added
         */
        if (!empty($quoteItem)) {

            /**
             * If we are adding new qty
             */
            if ($add) {
                if ($requestedQty > $availableQty) {
                    if ($availableQty < 0) {
                        $ret["qty"] = $currentQuoteItemQty;
                        $ret["add"] = false;
                        $ret["error"] = true;
                        $ret["message"] = $ret["message"] . $this->translator->trans("Current quantity in cart is not available any more");
                        return $ret;
                    } elseif ($availableQty == 0) {
                        $ret["qty"] = $currentQuoteItemQty;
                        $ret["add"] = false;
                        $ret["error"] = true;
                        $ret["message"] = $ret["message"] . $this->translator->trans("You have already added max quantity") . ". " . $this->translator->trans("Max quantity is") . ": " . $currentQuoteItemQty;
                        return $ret;
                    } else {
                        $ret["qty"] = $availableQty;
                        $ret["add"] = true;
                        $ret["error"] = false;
                        $ret["message"] = $ret["message"] . $this->translator->trans("Requested quantity is not available any more") . ". " . $this->translator->trans("Max quantity is") . ": " . $maxQty;
                        return $ret;
                    }
                } /**
                 * If $availableQty > $requestedQty add requestedQty
                 */
                else {
                    return $ret;
                }
            } /**
             * If we are checking qty
             */
            else {

                if ($availableQty < 0) {
                    $ret["qty"] = $maxQty;
                    $ret["add"] = false;
                    $ret["error"] = true;
                    $ret["message"] = $ret["message"] . $this->translator->trans("Requested quantity is not available any more") . ". " . $this->translator->trans("Max quantity is") . ": " . $maxQty;
                    return $ret;
                } elseif ($requestedQty <= $currentQuoteItemQty) {
                    return $ret;
                } elseif ($availableQty == 0) {
                    $ret["qty"] = $currentQuoteItemQty;
                    $ret["add"] = false;
                    $ret["error"] = true;
                    $ret["message"] = $ret["message"] . $this->translator->trans("You have already added max quantity") . ". " . $this->translator->trans("Max quantity is") . ": " . $currentQuoteItemQty;
                    return $ret;
                } elseif ($requestedQty > $availableQty) {
                    $ret["qty"] = $availableQty;
                    $ret["add"] = true;
                    $ret["error"] = false;
                    $ret["message"] = $ret["message"] . $this->translator->trans("Max quantity is") . ": " . $maxQty;
                    return $ret;
                }

                return $ret;
            }
        } /**
         * if quote item does not exist
         */
        else {
            if ($add) {
                if ($requestedQty > $availableQty) {
                    $ret["qty"] = $availableQty;
                    $ret["message"] = $ret["message"] . $this->translator->trans("Requested quantity is not available any more") . ". " . $this->translator->trans("Max quantity is") . ": " . $availableQty;
                }
            } else {
                throw new \Exception("Update quote item on none existing quote");
            }
        }

        return $ret;
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return |null
     */
    public function processPayment(PaymentTransactionEntity $paymentTransaction)
    {
        return null;
    }

    /**
     * @param PaymentTransactionEntity $paymentTransaction
     * @return |null
     */
    public function processRefund(PaymentTransactionEntity $paymentTransaction)
    {
        return null;
    }

    /**
     * @param QuoteEntity $quote
     * @param DiscountCouponEntity $discountCoupon
     * @return bool
     * @throws \Exception
     */
    public function checkIfDiscountCouponCanBeApplied(QuoteEntity $quote, DiscountCouponEntity $discountCoupon)
    {

        if (!$discountCoupon->getIsActive() || $discountCoupon->getNumberOfUsagePerCustomer() == 0) {
            return false;
        }

        $now = new \DateTime("now");

        if ($discountCoupon->getDateValidFrom() > $now || $discountCoupon->getDateValidTo() <= $now) {
            return false;
        }

        /**
         * If discount coupon is fixed check the total_price_items greater than min coupon price
         */
        if ($discountCoupon->getIsFixed()) {
            if ($quote->getPriceItemsTotal() < $discountCoupon->getFixedDiscount() || $quote->getPriceItemsTotal() < $discountCoupon->getMinCartPrice()) {
                return false;
            }
        }

        /**
         * Na koga se primjenjuje
         * 1. na sve
         * 2. samo sve registrirane kupce
         * 3. samo na registrirane kupce koji nisu pravne osobe
         * 4. samo na registrirane kupce koji jesu pravne osobe
         */
        if(!empty($discountCoupon->getDiscountAppliedTo()) && $discountCoupon->getDiscountAppliedTo()->getId() != CrmConstants::SVI_KUPCI){

            if(in_array($discountCoupon->getDiscountAppliedTo()->getId(),Array(CrmConstants::SAMO_REGISTRIRANI_KUPCI,CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_JESU_PRAVNE_OSOBE,CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_NISU_PRAVNE_OSOBE))){

                if(empty($quote->getAccount()) || empty($quote->getContact()) || empty($quote->getContact()->getCoreUser())){
                    return false;
                }

                if($discountCoupon->getDiscountAppliedTo()->getId() == CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_JESU_PRAVNE_OSOBE){
                    if(!$quote->getAccount()->getIsLegalEntity()){
                        return false;
                    }
                }
                elseif($discountCoupon->getDiscountAppliedTo()->getId() == CrmConstants::SAMO_REGISTRIRANI_KUPCI_KOJI_NISU_PRAVNE_OSOBE){
                    if($quote->getAccount()->getIsLegalEntity()){
                        return false;
                    }
                }
            }
        }

        /**
         * Exclude account grupa
         */
        $accountGroupsExcluded = $discountCoupon->getAccountGroupExcluded();

        if (EntityHelper::isCountable($accountGroupsExcluded) && count($accountGroupsExcluded)) {
            if (!empty($quote->getAccount()) && !empty($quote->getAccount()->getAccountGroup())) {
                $accountGroupIds = array();
                /** @var AccountGroupEntity $accountGroup */
                foreach ($accountGroupsExcluded as $accountGroup) {
                    $accountGroupIds[] = $accountGroup->getId();
                }
                if (in_array($quote->getAccount()->getAccountGroup()->getId(), $accountGroupIds)) {
                    return false;
                }
            }
        }

        /**
         * Check if discount coupon has account group link
         */
        $accountGroups = $discountCoupon->getAccountGroups();

        if (EntityHelper::isCountable($accountGroups) && count($accountGroups)) {
            if (empty($quote->getAccount()) || empty($quote->getAccount()->getAccountGroup())) {
                return false;
            }

            $accountGroupIds = array();
            /** @var AccountGroupEntity $accountGroup */
            foreach ($accountGroups as $accountGroup) {
                $accountGroupIds[] = $accountGroup->getId();
            }

            if (!in_array($quote->getAccount()->getAccountGroup()->getId(), $accountGroupIds)) {
                return false;
            }
        }

        /**
         * Check if discount coupon has email
         */
        if (!empty($discountCoupon->getEmail()) && (empty($quote->getContact()) || $discountCoupon->getEmail() != $quote->getContact()->getEmail())) {
            return false;
        }

        /**
         * Check number od coupons used
         */
        if ($discountCoupon->getNumberOfUsagePerCustomer() != -1) {

            if (empty($this->discountCouponManager)) {
                $this->discountCouponManager = $this->container->get("discount_coupon_manager");
            }

            $numberOfCouponsUsed = 0;

            if (!empty($quote->getAccount())) {
                $numberOfCouponsUsed = $this->discountCouponManager->getNumberOfCouponsUsed($discountCoupon, $quote->getAccount());
            }

            if ($numberOfCouponsUsed >= $discountCoupon->getNumberOfUsagePerCustomer()) {
                return false;
            }
        }

        if($discountCoupon->getForceFreeDelivery() && floatval($discountCoupon->getDiscountPercent()) == 0){
            return true;
        }

        /**
         * Check if has product on which coupon can be applied
         */
        $canBeApplied = false;

        $quoteItems = $quote->getQuoteItems();
        if (EntityHelper::isCountable($quoteItems) && count($quoteItems) > 0) {

            /** @var QuoteItemEntity $quoteItem */
            foreach ($quoteItems as $quoteItem) {

                /** @var ProductEntity $parentProduct */
                $parentProduct = null;
                if (!empty($quoteItem->getParentItem())) {
                    $parentProduct = $quoteItem->getParentItem()->getProduct();
                }

                /** @var ProductEntity $product */
                $product = $quoteItem->getProduct();

                if ($this->getApplicableDiscountCouponPercentForProduct($discountCoupon, $product, $parentProduct, $quote->getAccount(), array(), $quoteItem->getQuote()->getBasePriceTotal())) {
                    $canBeApplied = true;
                    break;
                }
            }
        }

        return $canBeApplied;
    }

    /**
     * @param DiscountCouponEntity $discountCoupon
     * @param $item
     * @param $account
     * @param $prices
     * @return float|int
     * @throws \Exception
     *
     * @deprecated
     * ovo smo ostavili samo kao wrapper da ne pucaju custom crmProcessManageri
     * Treba ih izbaciti i staviti getApplicableDiscountCouponPercentForProduct
     */
    public function isProductOnDiscountCoupon(DiscountCouponEntity $discountCoupon, $item, $account = null, $prices = array())
    {
        /** @var ProductEntity $parentProduct */
        $parentProduct = null;
        if (!empty($item->getParentItem())) {
            $parentProduct = $item->getParentItem()->getProduct();
        }

        /** @var ProductEntity $product */
        $product = $item->getProduct();

        return $this->getApplicableDiscountCouponPercentForProduct($discountCoupon, $product, $parentProduct, $account, $prices);
    }

    /**
     * @param DiscountCouponEntity $discountCoupon
     * @param ProductEntity $product
     * @param null $parentProduct
     * @param null $account
     * @param array $prices
     * @param int $totalAmount
     * @return float|int
     * @throws \Exception
     */
    public function getApplicableDiscountCouponPercentForProduct(DiscountCouponEntity $discountCoupon, ProductEntity $product, $parentProduct = null, $account = null, $prices = array(), $totalAmount = 0)
    {
        if (!$this->couponIsApplicableOnProduct($discountCoupon, $product)) {
            return 0;
        }

        if (empty($prices)) {
            $prices = $this->getProductPrices($product, $account, $parentProduct);
        }

        $discountCouponPercent = floatval($this->getDiscountCouponPercent($discountCoupon, $totalAmount));

        //TODO ovdje ce biti prioblem kod izmjene ordera. Dakle, ako se order mijenja za 1 mjesec nakon sto proizvod dobio neki novi popust, ova funkcija ce vratiti razlicite rezulatate od onog dana kada je bio popust na kosarici

        if ($discountCoupon->getAllowOnDiscountProducts()) {
            if ($discountCoupon->getApplicationTypeId() == CrmConstants::DISCOUNT_COUPON_APPLY_BIGGER_DISCOUNT) {
                if (floatval($prices["discount_price"]) > 0 && floatval($prices["discount_percentage"]) >= floatval($discountCouponPercent)) {
                    return 0;
                }
            }
        } else {
            if (floatval($prices["discount_price"]) > 0) {
                return 0;
            }
        }

        return $discountCouponPercent;
    }

    /**
     * @param DiscountCouponEntity $discountCoupon
     * @param ProductEntity $product
     * @return bool
     */
    public function couponIsApplicableOnProduct(DiscountCouponEntity $discountCoupon, ProductEntity $product)
    {
        if (empty($discountCoupon->getRules())) {
            return true;
        }

        if (empty($this->productAttributeFilterRulesManager)) {
            $this->productAttributeFilterRulesManager = $this->container->get("product_attribute_filter_rules_manager");
        }

        if ($this->productAttributeFilterRulesManager->productMatchesRules($product, $discountCoupon->getRules(),$discountCoupon)) {
            return true;
        }

        return false;
    }

    /**
     * @param DiscountCouponEntity $discountCoupon
     * @param int $total
     * @return float
     */
    public function getDiscountCouponPercent(DiscountCouponEntity $discountCoupon, $total = 0)
    {

        $percent = floatval($discountCoupon->getDiscountPercent());

        $discountCouponRanges = $discountCoupon->getDiscountCouponRanges();
        if (EntityHelper::isCountable($discountCouponRanges) && count($discountCouponRanges)) {

            $total = floatval($total);
            $found = false;

            /** @var DiscountCouponRangeEntity $discountCouponRange */
            foreach ($discountCouponRanges as $discountCouponRange) {
                if ($total >= floatval($discountCouponRange->getBasePriceFrom()) && $total < floatval($discountCouponRange->getBasePriceTo())) {
                    $percent = floatval($discountCouponRange->getDiscountPercent());
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $percent = floatval($discountCoupon->getDiscountPercent());
            }
        }

        return $percent;
    }

    /**
     * @param QuoteEntity $quote
     * @param DiscountCouponEntity|null $discountCoupon
     * @return QuoteEntity
     * @throws \Exception
     */
    public function applyDiscountCoupon(QuoteEntity $quote, DiscountCouponEntity $discountCoupon = null)
    {

        $quote->setDiscountCoupon($discountCoupon);

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        $quote = $this->quoteManager->recalculateQuoteItems($quote);

        return $quote;
    }

    /**
     * @param InvoiceEntity $invoice
     * @return array
     */
    public function prepareTotalsByTaxType(InvoiceEntity $invoice)
    {

        $taxTypeTotals = array();

        $invoiceItems = $invoice->getInvoiceItems();

        if (!EntityHelper::isCountable($invoiceItems) || count($invoiceItems) == 0) {
            return $taxTypeTotals;
        }

        /**@var InvoiceItemEntity $invoiceItem */
        foreach ($invoice->getInvoiceItems() as $invoiceItem) {

            $taxPercent = intval($invoiceItem->getTaxType()->getPercent());

            if (!isset($taxTypeTotals[$taxPercent])) {
                $taxTypeTotals[$taxPercent]["base_price_without_tax"] = 0;
                $taxTypeTotals[$taxPercent]["base_price_tax"] = 0;
            }

            $taxTypeTotals[$taxPercent]["base_price_without_tax"] = $taxTypeTotals[$taxPercent]["base_price_without_tax"] + $invoiceItem->getBasePriceWithoutTax();
            $taxTypeTotals[$taxPercent]["base_price_tax"] = $taxTypeTotals[$taxPercent]["base_price_tax"] + $invoiceItem->getBasePriceTax();

        }

        return $taxTypeTotals;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function afterDiscountsApplied($data = array())
    {

        return true;
    }

    /**
     * @param ProductEntity $product
     * @param $storeId
     * @param array $lablePositionCodes
     * @param $isProductPage
     * @return array|void
     */
    public function getProductLabels(ProductEntity $product, $storeId = null, array $lablePositionCodes = array(), $isProductPage = false)
    {

        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        if (empty($this->productLabelRuleManager)) {
            $this->productLabelRuleManager = $this->container->get("product_label_rules_manager");
        }

        $productLabels = $this->productLabelRuleManager->getLabelsForProduct($product, $storeId, $lablePositionCodes, $isProductPage);

        return $productLabels;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function afterProductLabelsApplied($data = array())
    {

        return true;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function afterMarginRulesApplied($data = array())
    {

        /**
         * Check if product_ids is empty
         */
        if (!isset($data["product_ids"]) || empty($data["product_ids"])) {
            return true;
        }


        return true;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function afterBulkPriceRulesApplied($data = array())
    {

        /**
         * Check if product_ids is empty
         */
        if (!isset($data["product_ids"]) || empty($data["product_ids"])) {
            return true;
        }


        return true;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function afterLoyaltyEarningsConfigurationApplied($data = array())
    {

        /**
         * Check if product_ids is empty
         */
        if (!isset($data["product_ids"]) || empty($data["product_ids"])) {
            return true;
        }


        return true;
    }

    /**
     * @param ProductEntity $product
     * @return bool
     */
    public function isProductValid(ProductEntity $product)
    {

        if ($product->getEntityStateId() != 1 || $product->getActive() != 1) {
            return false;
        }

        if ($_ENV["USE_READY_FOR_WEBSHOP"]) {
            if (!$product->getReadyForWebshop()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param GlsParcelEntity $glsParcel
     * @param OrderEntity|null $order
     * @return bool
     */
    public function setGlsParcelData(GlsParcelEntity $glsParcel, OrderEntity $order = null)
    {

        return true;
    }

    /**
     * @param $overseasExpressParcel
     * @param OrderEntity|null $order
     * @return bool
     */
    public function setOverseasExpressParcelData($overseasExpressParcel, OrderEntity $order = null)
    {
        return true;
    }

    /**
     * @param $inTimeParcel
     * @param OrderEntity|null $order
     * @return bool
     */
    public function setIntimeParcelData($inTimeParcel, OrderEntity $order = null)
    {
        if (!empty($order)) {
            $inTimeParcel->setGoodsValueCurrency($order->getCurrency());
            $inTimeParcel->setGoodsValue($order->getPriceTotal());
        }

        if (!empty($inTimeParcel->getShipToContactType())) {
            $inTimeParcel->setShipToContactTypeCode($inTimeParcel->getShipToContactType()->getTypeCode());
        }
        if (!empty($inTimeParcel->getShipFromContactType())) {
            $inTimeParcel->setShipFromContactTypeCode($inTimeParcel->getShipFromContactType()->getTypeCode());
        }
        if (!empty($inTimeParcel->getInTimeBillingType())) {
            $inTimeParcel->setBillingCode($inTimeParcel->getInTimeBillingType()->getTypeCode());
        }
        if (!empty($inTimeParcel->getInTimeServiceType())) {
            $inTimeParcel->setServiceTypeCode($inTimeParcel->getInTimeServiceType()->getTypeCode());
        }

        if (empty($inTimeParcel->getCollectionDateTimeFrom())) {
            $inTimeParcel->setCollectionDateTimeFrom(new \DateTime());
        }
        if (empty($inTimeParcel->getCollectionDateTimeTo())) {
            $inTimeParcel->setCollectionDateTimeTo(new \DateTime());
        }
        if (empty($inTimeParcel->getDeliveryDateTimeFrom())) {
            $inTimeParcel->setDeliveryDateTimeFrom(new \DateTime());
        }
        if (empty($inTimeParcel->getDeliveryDateTimeTo())) {
            $inTimeParcel->setDeliveryDateTimeTo(new \DateTime());
        }

        return true;
    }

    /**
     * @param DpdParcelEntity $dpdParcel
     * @param OrderEntity|null $order
     * @return bool
     */
    public function setDpdParcelData(DpdParcelEntity $dpdParcel, OrderEntity $order = null)
    {

        return true;
    }

    /**
     * @param ProductEntity $product
     * @param null $data
     * @return bool
     */
    public function recalculateProductPrices(ProductEntity $product, $data = null)
    {

        /**
         * Configurable product does not have price
         */
        if (in_array($product->getProductTypeId(), array(CrmConstants::PRODUCT_TYPE_CONFIGURABLE))) {
            return true;
        }

        $data["product_ids"] = array($product->getId());
        if (!empty($product->getSupplierId())) {
            $data["supplier_ids"] = array($product->getSupplierId());
        }

        $this->recalculateProductsPrices($data);

        return true;
    }

    /**
     * @param null $data
     * @return bool
     */
    public function recalculateProductsPrices($data = null)
    {

        /**
         * Check if product_ids is empty
         */
        if (!isset($data["product_ids"]) || empty($data["product_ids"])) {
            return true;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        /**
         * Ako se koriste marže prvo izvrtiti marže
         */
        if (isset($data["supplier_ids"]) && !empty($data["supplier_ids"])) {
            if (empty($this->marginRuleManager)) {
                $this->marginRuleManager = $this->container->get("margin_rules_manager");
            }

            $this->marginRuleManager->recalculateMarginRules($data);
        }

        $this->recalculateSecondaryProductsPrices($data);

        /**
         * Paljenje i gasenje proizvoda
         */
        $this->refreshActiveSaleable($data);

        return true;
    }

    /**
     * @param null $data
     * @return bool
     */
    public function recalculateSecondaryProductsPrices($data = null)
    {
        /**
         * Check if product_ids is empty
         */
        if (!isset($data["product_ids"]) || empty($data["product_ids"])) {
            return true;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        /**
         * Recalculate discounts on products
         */
        $where = " id IN (" . implode(",", $data["product_ids"]) . ") AND ";

        $q = "UPDATE product_entity SET date_discount_base_to = date_discount_to, date_discount_base_from = date_discount_from, discount_price_base = price_base - (price_base * discount_percentage / 100.0), discount_diff_base = price_base - (price_base - (price_base * discount_percentage / 100.0)), discount_price_retail = price_retail - (price_retail * discount_percentage / 100.0), discount_diff = price_retail - (price_retail - (price_retail * discount_percentage / 100.0)) WHERE {$where} discount_percentage > 0 AND (exclude_from_discounts is null or exclude_from_discounts = 0);";
        $this->databaseContext->executeNonQuery($q);

        /**
         * Setting qty = fixed_qty on a product if the field 'fixed_qty' > 0 AND if product is active
         */
        $q = "UPDATE product_entity SET qty = fixed_qty WHERE fixed_qty > 0 AND (qty != fixed_qty OR qty IS NULL) AND active = 1;";
        $this->databaseContext->executeNonQuery($q);

        /**
         * Recalculate discounts on catalog rule
         */
        if (empty($this->discountRulesManager)) {
            $this->discountRulesManager = $this->container->get("discount_rules_manager");
        }
        $this->discountRulesManager->recalculateDiscountRules($data);

        /**
         * Remove expired discounts
         */
        $this->removeOldDiscountOnProducts();

        //TODO na ovom mjestu ubaciti loyalty kada treba

        /**
         * Recalculate bulk price rules
         */
        if (empty($this->bulkPriceManager)) {
            $this->bulkPriceManager = $this->container->get("bulk_price_manager");
        }
        $this->bulkPriceManager->recalculateBulkPriceRules($data["product_ids"]);

        /** @var SproductManager $sProductManager */
        $sProductManager = $this->getContainer()->get("s_product_manager");
        $sProductManager->setProductSortPrices($data["product_ids"]);

        return true;
    }

    /**
     * @return bool
     */
    public function removeOldDiscountOnProducts()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT id FROM product_entity WHERE (date_discount_to IS NOT NULL AND (date_discount_to < NOW() OR exclude_from_discounts = 1)) OR (discount_price_retail != 0 AND (discount_percentage IS NULL OR discount_percentage = 0));";
        $data = $this->databaseContext->getAll($q);

        if (empty($data)) {
            return true;
        }

        $ids = array_column($data, "id");

        $q = "UPDATE product_entity SET discount_price_retail = null, discount_price_base = null, discount_diff = null, discount_diff_base = null, discount_type = null, discount_type_base = null, date_discount_from = null, date_discount_base_from = null, date_discount_to = null, date_discount_base_to = null, discount_percentage = null, discount_percentage_base = null WHERE id IN (" . implode(",", $ids) . ")";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function refreshActiveSaleable($data = array())
    {
        $additionalWhere = "";
        $additionalWhereConfiguration = "";
        if (isset($data["product_ids"]) && !empty($data["product_ids"])) {
            $whereIds = implode(",", $data["product_ids"]);
            $additionalWhere = " AND p.id IN ({$whereIds}) ";
            $additionalWhereConfiguration = " AND (pcple.product_id IN ({$whereIds}) OR pcple.child_product_id IN ({$whereIds})) ";
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $additionaFilterPositive = "";
        $additionaFilterNegative = "";
        if (isset($_ENV["USE_READY_FOR_WEBSHOP"]) && $_ENV["USE_READY_FOR_WEBSHOP"] == 1) {
            $additionaFilterPositive = " AND p.ready_for_webshop = 1 ";
            $additionaFilterNegative = " OR p.ready_for_webshop = 0 ";
        }

        /**
         * UPDATE ACTIVE & IS_SALEABLE FOR SIMPLE PRODUCTS
         */
        $q = "UPDATE product_entity as p SET p.is_saleable = 1, p.content_changed = 1 WHERE (p.is_saleable = 0 OR p.is_saleable is null) and p.active = 1 and p.entity_state_id = 1 {$additionaFilterPositive} and p.qty > 0 and p.product_type_id NOT IN (" . CrmConstants::PRODUCT_TYPE_CONFIGURABLE . ") {$additionalWhere};";
        $this->databaseContext->executeNonQuery($q);

        $q = "UPDATE product_entity as p SET p.is_saleable = 0, p.content_changed = 1 WHERE (p.is_saleable = 1 OR p.is_saleable is null) and (p.entity_state_id = 2 {$additionaFilterNegative} or p.active = 0 or (p.qty = 0 or p.qty is NULL)) and p.product_type_id NOT IN (" . CrmConstants::PRODUCT_TYPE_CONFIGURABLE . ") {$additionalWhere};";
        $this->databaseContext->executeNonQuery($q);

        /**
         * UPDATE ACTIVE FOR CONFIGURABLE
         */
        $q = "SELECT id FROM product_entity WHERE id in (SELECT pcple.product_id FROM product_configuration_product_link_entity as pcple LEFT JOIN product_entity as p ON pcple.child_product_id = p.id WHERE pcple.entity_state_id = 1 AND p.active = 0 {$additionaFilterNegative} {$additionalWhereConfiguration}) AND id not in (SELECT pcple.product_id FROM product_configuration_product_link_entity as pcple LEFT JOIN product_entity as p ON pcple.child_product_id = p.id WHERE  pcple.entity_state_id = 1 AND p.active = 1 and p.entity_state_id = 1 {$additionaFilterPositive}) and active = 1 and product_type_id = 2 UNION SELECT id FROM product_entity WHERE active = 1 and product_type_id = 2 and id NOT IN (SELECT product_id FROM product_configuration_product_link_entity);";
        $ids = $this->databaseContext->getAll($q);

        if (!empty($ids)) {
            $ids = array_column($ids, "id");
            $q = "UPDATE product_entity SET active = 0 WHERE id in (" . implode(",", $ids) . ");";
            $this->databaseContext->executeNonQuery($q);
        }

        $q = "SELECT id FROM product_entity WHERE active = 0 and product_type_id = 2 and id in (SELECT pcple.product_id FROM product_configuration_product_link_entity as pcple LEFT JOIN product_entity as p ON pcple.child_product_id = p.id WHERE pcple.entity_state_id = 1 AND p.active = 1 and p.entity_state_id = 1 {$additionaFilterPositive} {$additionalWhereConfiguration});";
        $ids = $this->databaseContext->getAll($q);

        if (!empty($ids)) {
            $ids = array_column($ids, "id");
            $q = "UPDATE product_entity SET active = 1 WHERE id in (" . implode(",", $ids) . ");";
            $this->databaseContext->executeNonQuery($q);
        }

        /**
         * UPDATE SALEABLE FOR CONFIGURABLE
         */
        $q = "SELECT id FROM product_entity
                WHERE id in (SELECT pcple.product_id FROM product_configuration_product_link_entity as pcple LEFT JOIN product_entity as p ON pcple.child_product_id = p.id WHERE pcple.entity_state_id = 1 AND p.is_saleable = 0 {$additionalWhereConfiguration})
                AND id not in (SELECT pcple.product_id FROM product_configuration_product_link_entity as pcple LEFT JOIN product_entity as p ON pcple.child_product_id = p.id WHERE pcple.entity_state_id = 1 AND p.is_saleable = 1 and p.entity_state_id = 1)
                and active = 1 and product_type_id = 2 and is_saleable = 1;";
        $ids = $this->databaseContext->getAll($q);

        if (!empty($ids)) {
            $ids = array_column($ids, "id");
            $q = "UPDATE product_entity SET is_saleable = 0 WHERE id in (" . implode(",", $ids) . ");";
            $this->databaseContext->executeNonQuery($q);
        }

        $q = "SELECT id FROM product_entity WHERE is_saleable = 0 and product_type_id = 2 and entity_state_id = 1 and active = 1 and id in (SELECT pcple.product_id FROM product_configuration_product_link_entity as pcple LEFT JOIN product_entity as p ON pcple.child_product_id = p.id WHERE pcple.entity_state_id = 1 AND p.is_saleable = 1 and p.entity_state_id = 1 {$additionalWhereConfiguration});";
        $ids = $this->databaseContext->getAll($q);

        if (!empty($ids)) {
            $ids = array_column($ids, "id");
            $q = "UPDATE product_entity SET is_saleable = 1 WHERE id in (" . implode(",", $ids) . ");";
            $this->databaseContext->executeNonQuery($q);
        }

        return true;
    }

    /**
     * @param QuoteEntity $quote
     * @return int
     */
    public function getMinicartQuantity(QuoteEntity $quote)
    {
        return $quote->getTotalQty();
    }

    /**
     * @param QuoteEntity $quote
     * @param array $p
     * @param null $quoteItemData
     * @return bool
     * @throws \Exception
     */
    public function additionalAddToCartAction(QuoteEntity $quote, $p = array(), $quoteItemData = null)
    {
        /**
         * Remove gifts if over total_available_gifts
         */
        $ret = $this->getNumberOfAvailableGifts($quote);

        $quoteItems = $quote->getQuoteItems();

        if (EntityHelper::isCountable($quoteItems) && count($quoteItems) > 0) {

            /**
             * Check for gifts
             */
            if (!empty($ret)) {
                if (empty($this->quoteManager)) {
                    $this->quoteManager = $this->container->get("quote_manager");
                }

                /** @var QuoteItemEntity $quoteItem */
                foreach ($quoteItems as $quoteItem) {
                    if ($quoteItem->getIsGift()) {
                        if (intval($ret["total_available_gifts"]) < 1) {
                            $this->quoteManager->addUpdateProductInQuote($quoteItem->getProduct(), $quote, 0, false);
                        } else {
                            $ret["total_available_gifts"] = intval($ret["total_available_gifts"]) - 1;
                        }
                    }
                }
            }

            /**
             * Validate coupon and remove it if does not exist
             */
            if (!empty($quote->getDiscountCoupon())) {
                $isValid = $this->checkIfDiscountCouponCanBeApplied($quote, $quote->getDiscountCoupon());
                if (!$isValid) {
                    $this->applyDiscountCoupon($quote, null);
                }
            }

        } else {
            /**
             * Remove coupon
             */
            if (!empty($quote->getDiscountCoupon())) {
                $this->applyDiscountCoupon($quote, null);
            }


        }

        return true;
    }

    /**
     * @param QuoteEntity|null $quote
     * @return array|bool
     */
    public function getNumberOfAvailableGifts(QuoteEntity $quote = null)
    {

        $ret = array();
        $ret["total_available_gifts"] = 0;
        $ret["limit"] = 0;
        $ret["show"] = false;
        $ret["max"] = 0;
        $ret["price_for_next_step"] = 0;

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->container->get("application_settings_manager");
        }

        $session = $this->container->get('session');

        $limitSettings = $this->applicationSettingsManager->getApplicationSettingByCode("gift_limit");
        if (!empty($limitSettings) && isset($limitSettings[$session->get("current_store_id")])) {
            $ret["limit"] = floatval($limitSettings[$session->get("current_store_id")]);
            $ret["show"] = true;

            $maxSettings = $this->applicationSettingsManager->getApplicationSettingByCode("gift_max");
            if (!empty($maxSettings) && isset($maxSettings[$session->get("current_store_id")])) {
                $ret["max"] = floatval($maxSettings[$session->get("current_store_id")]);
            } else {
                $ret["max"] = 1000;
            }
        }

        if (!$ret["show"] || empty($quote)) {
            return $ret;
        }

        $priceTotal = $quote->getPriceItemsTotal();

        if ($priceTotal > $ret["limit"]) {
            $ret["total_available_gifts"] = intval(($priceTotal / $ret["limit"]));
            if ($ret["total_available_gifts"] > $ret["max"]) {
                $ret["total_available_gifts"] = $ret["max"];
            } else {
                $ret["price_for_next_step"] = ($ret["total_available_gifts"] + 1) * $ret["limit"] - $priceTotal;
            }
        } else {
            $ret["price_for_next_step"] = $ret["limit"] - $priceTotal;
        }

        return $ret;
    }

    /**
     * @param int $qty
     * @param $qtyStep
     * @return int
     */
    public function prepareQty($qty, $qtyStep = null, $quoteItem = null)
    {
        /**
         * Ovo sluzi zato da kada se parentu u bundleu postavi min qty > 1 da u kosarici bude broj koliko bundlova je korisnik dodao a ne broj komada parenta
         */
        if (!empty($quoteItem)) {
            if ($quoteItem->getIsPartOfBundle()) {

                $productConfigurations = $quoteItem->getProduct()->getProductConfigurations();
                if (EntityHelper::isCountable($productConfigurations) && count($productConfigurations)) {
                    /** @var ProductConfigurationProductLinkEntity $productConfiguration */
                    foreach ($productConfigurations as $productConfiguration) {
                        if ($productConfiguration->getChildProduct()->getId() == $quoteItem->getProduct()->getId()) {
                            if (floatval($productConfiguration->getMinQty()) > 1) {
                                $qty = $qty / floatval($productConfiguration->getMinQty());
                            }
                            break;
                        }
                    }
                }
            }
        }

        return intval($qty);
    }

    /**
     * @param $changedProducts
     * @param $importType
     * @return bool
     */
    public function afterImportCompleted($changedProducts, $importType)
    {
        if (empty($this->brandsManager)) {
            $this->brandsManager = $this->container->get("brands_manager");
        }

        $changedProductBrandIds = $this->brandsManager->syncBrandsWithSProductAttributeConfigurationOptions();

        if (!empty($changedProductBrandIds)) {
            $changedProducts["product_ids"] = array_merge($changedProducts["product_ids"], $changedProductBrandIds);
            $changedProducts["product_ids"] = array_unique($changedProducts["product_ids"]);
        }

        if (!empty($changedProducts)) {
            $this->recalculateProductsPrices($changedProducts);
        }

        if (empty($this->productGroupManager)) {
            $this->productGroupManager = $this->container->get("product_group_manager");
        }

        $this->productGroupManager->setNumberOfProductsInProductGroups();

        if (empty($this->sitemapManager)) {
            $this->sitemapManager = $this->container->get("sitemap_manager");
        }
        $this->sitemapManager->generateSitemapXML();

        if ((!isset($_ENV["DISABLE_FRONT_BLOCK_HTML_CACHE"]) || $_ENV["DISABLE_FRONT_BLOCK_HTML_CACHE"] != 1) && isset($changedProducts["product_ids"]) && !empty($changedProducts["product_ids"])) {
            $this->invalidateProductCacheForRecentlyModifiedProducts($changedProducts["product_ids"]);
        }

        return true;
    }

    /**
     * @param $changedIds
     * @return bool
     */
    public function afterDocumentImportCompleted($changedIds = array())
    {

        return true;
    }

    /**
     * @param NewsletterEntity $subscription
     * @param $isNew
     * @return bool
     */
    public function afterNewsletterSubscriptionChanged(NewsletterEntity $subscription, $isNew)
    {

        return true;
    }

    /**
     * @param $importType
     * @param $insertUpdate
     * @param $partner
     * @param $dataArray
     * @param array $existingEntity
     * @param $insertArray2
     * @return mixed
     */
    public function customWandToXMLImport($importType, $insertUpdate, $partner, $dataArray, $existingEntity = array(), &$insertArray2)
    {
        return $dataArray;
    }

    /**
     * @param bool $debug
     * @return bool
     */
    public function triggerErpToPullOrders($debug = false)
    {
        return false;
    }

    /**
     * Invalidates cache for products that were updated in the past 1 hour
     * @return bool
     */
    public function invalidateProductCacheForRecentlyModifiedProducts($productIds = array())
    {
        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->getContainer()->get("cache_manager");
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        if (empty($productIds)) {
            $query = "SELECT id FROM product_entity WHERE modified > DATE_SUB(NOW(), INTERVAL 1 HOUR);";
            $data = $this->databaseContext->getAll($query);

            if (!empty($data)) {
                $productIds = array_column($data, "id");
            }
        }

        $this->cacheManager->invalidateCacheByTag("product");
        if (!empty($productIds)) {
            foreach ($productIds as $productId) {
                $this->cacheManager->invalidateCacheByTag("product_" . $productId);
            }
        }

        return true;
    }

    /**
     * Generate array of additional product IDs
     * @return array
     */
    public function getAdditionalProductDiscountIds()
    {
        return [];
    }

    /**
     * @param QuoteEntity $quote
     * @param null $deliveryPrice
     * @return null
     */
    public function calculateAmountToFreeDeliveryConditions(QuoteEntity $quote, $deliveryPrice = null)
    {
        return null;
    }

    /**
     * ORDER RETURN SECTION
     */
    /**
     * @param OrderItemEntity $orderItem
     * @param $ret
     * @return mixed
     */
    public function orderItemReturnEnabledCustom(OrderItemEntity $orderItem, $ret)
    {

        return $ret;
    }

    /**
     * @param DiscountCouponEntity $discountCoupon
     * @return DiscountCouponEntity
     */
    public function setDiscountCouponUsed(DiscountCouponEntity $discountCoupon)
    {

        $turnOffCampaign = false;

        $currentUsages = intval($discountCoupon->getNumberOfUsagePerCustomer());
        if ($currentUsages == -1) {

            /**
             * Provjera prema iznosu da li je iskoristen kupon
             */
            if (floatval($discountCoupon->getMaxDiscountAmountToSpend()) > 0) {

                if (empty($this->databaseContext)) {
                    $this->databaseContext = $this->getContainer()->get("database_context");
                }

                $q = "SELECT SUM(discount_coupon_price_total) as count FROM order_entity WHERE entity_state_id = 1 and discount_coupon_id = {$discountCoupon->getId()};";
                $total = $this->databaseContext->getSingleResult($q);

                $number1 = floatval($discountCoupon->getMaxDiscountAmountToSpend());
                $number2 = floatval($total);

                if ($number1 < $number2) {
                    $tmp = $number1;
                    $number1 = $number2;
                    $number2 = $tmp;
                }

                $percentageUsed = 100 - (($number1 - $number2) / $number2 * 100);

                if ($discountCoupon->getPercentageWarning() > 0 && $discountCoupon->getPercentageWarning() <= $percentageUsed) {
                    $turnOffCampaign = true;
                }

                if (floatval($total) >= $discountCoupon->getMaxDiscountAmountToSpend()) {
                    $discountCoupon->setIsActive(0);
                }
            }
        } else {

            $usagesLeft = $currentUsages - 1;

            $discountCoupon->setNumberOfUsagePerCustomer($usagesLeft);

            if ($usagesLeft == 0) {
                $discountCoupon->setIsActive(0);
            }
        }

        if ($discountCoupon->getIsActive()) {
            $turnOffCampaign = true;
        }

        /**
         * Gašenje kampanje
         */
        if ($turnOffCampaign) {

            if (empty($this->campaignManager)) {
                $this->campaignManager = $this->container->get("campaign_manager");
            }

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("discountCoupon", "eq", $discountCoupon->getId()));
            $compositeFilter->addFilter(new SearchFilter("goalReached", "eq", 0));

            $campaigns = $this->campaignManager->getFilteredCampaigns($compositeFilter);

            if (EntityHelper::isCountable($campaigns) && count($campaigns)) {
                foreach ($campaigns as $campaign) {
                    $this->campaignManager->setGoalReached($campaign);
                }
            }
        }

        $this->entityManager->saveEntity($discountCoupon);

        return $discountCoupon;
    }

    /**
     * @param OrderReturnEntity $orderReturn
     * @return OrderReturnEntity
     */
    public function recalculateOrderReturnTotals(OrderReturnEntity $orderReturn)
    {
        $orderReturnItems = $orderReturn->getOrderReturnItems();

        $totalQty = 0;

        $totalBasePriceWithoutTax = 0;
        $totalBasePriceTax = 0;
        $totalBasePriceTotal = 0;
        $discountBasePriceTotal = 0;
        $discountLoyaltyBasePriceTotal = 0;
        $discountCouponBasePriceTotal = 0;
        $couponIsFixedPrice = false;
        $discountLoyaltyPercent = 0;

        $currencyRate = $orderReturn->getCurrencyRate();
        if (empty(floatval($currencyRate))) {
            $currencyRate = 1;
        }

        if (EntityHelper::isCountable($orderReturnItems) && count($orderReturnItems)) {

            $totalsPerTaxType = array();

            /** @var OrderItemEntity $orderItem */
            foreach ($orderReturnItems as $orderItem) {

                if (!isset($totalsPerTaxType[$orderItem->getTaxTypeId()])) {
                    $totalsPerTaxType[$orderItem->getTaxTypeId()] = array(
                        "totalBasePriceWithoutTax" => 0,
                        "taxPercent" => $orderItem->getTaxType()->getPercent(),
                    );
                }

                $totalsPerTaxType[$orderItem->getTaxTypeId()]["totalBasePriceWithoutTax"] = $totalsPerTaxType[$orderItem->getTaxTypeId()]["totalBasePriceWithoutTax"] + $orderItem->getBasePriceWithoutTax();

                $totalBasePriceWithoutTax = $totalBasePriceWithoutTax + $orderItem->getBasePriceWithoutTax();
                if (empty($orderItem->getParentItem())) {
                    $totalQty = $totalQty + floatval($orderItem->getQty());
                }

                $discountBasePriceTotal = $discountBasePriceTotal + $orderItem->getBasePriceDiscountTotal();

                /**
                 * Applay discount coupon
                 */
                if (floatval($orderItem->getBasePriceDiscountCouponTotal()) > 0) {
                    $discountCouponBasePriceTotal = $discountCouponBasePriceTotal + $orderItem->getBasePriceDiscountCouponTotal();
                } /**
                 * Loyalty discount after tax
                 * Do not add if item already on discount or coupon applied
                 */
                elseif (!$couponIsFixedPrice) {
                    if (floatval($orderItem->getBasePriceDiscountTotal()) == 0 && $discountLoyaltyPercent > 0) {
                        $discountLoyaltyBasePriceTotal = $discountLoyaltyBasePriceTotal + (floatval($orderItem->getBasePriceTotal()) * $discountLoyaltyPercent / 100);
                    }
                }
            }

            foreach ($totalsPerTaxType as $taxTypeId => $totalPerTaxType) {
                $totalBasePriceTax = $totalBasePriceTax + ($totalPerTaxType["totalBasePriceWithoutTax"] * $totalPerTaxType["taxPercent"] / 100);
            }
        }

        $totalBasePriceTotal = $totalBasePriceWithoutTax + $totalBasePriceTax;

        $orderReturn->setBasePriceDiscount($discountBasePriceTotal);
        $orderReturn->setPriceDiscount($discountBasePriceTotal / $currencyRate);
        $orderReturn->setDiscountCouponPriceTotal($discountCouponBasePriceTotal / $currencyRate);

        $orderReturn->setBasePriceItemsWithoutTax($totalBasePriceWithoutTax);
        $orderReturn->setBasePriceItemsTax($totalBasePriceTax);
        $orderReturn->setBasePriceItemsTotal($totalBasePriceTotal);

        $orderReturn->setPriceItemsWithoutTax($totalBasePriceWithoutTax / $currencyRate);
        $orderReturn->setPriceItemsTax($totalBasePriceTax / $currencyRate);
        $orderReturn->setPriceItemsTotal($totalBasePriceTotal / $currencyRate);

        $totalBasePriceWithoutTax = $totalBasePriceWithoutTax + floatval($orderReturn->getBasePriceDeliveryWithoutTax());
        $totalBasePriceTax = $totalBasePriceTax + floatval($orderReturn->getBasePriceDeliveryTax());

        $totalBasePriceTotal = $totalBasePriceWithoutTax + $totalBasePriceTax;

        /**
         * Substract coupon code discount
         */
        $totalBasePriceTotal = $totalBasePriceTotal - $discountCouponBasePriceTotal;

        /**
         * Substract loyalty discount
         */
        $totalBasePriceTotal = $totalBasePriceTotal - $discountLoyaltyBasePriceTotal;

        $orderReturn->setBasePriceWithoutTax($totalBasePriceWithoutTax);
        $orderReturn->setBasePriceTax($totalBasePriceTax);
        $orderReturn->setBasePriceTotal($totalBasePriceTotal);

        $orderReturn->setPriceWithoutTax($totalBasePriceWithoutTax / $currencyRate);
        $orderReturn->setPriceTax($totalBasePriceTax / $currencyRate);
        $orderReturn->setPriceTotal($totalBasePriceTotal / $currencyRate);

        $orderReturn->setTotalQty($totalQty);

        $orderReturn->setDiscountLoyaltyBasePriceTotal($discountLoyaltyBasePriceTotal);
        $orderReturn->setDiscountLoyaltyPriceTotal($discountLoyaltyBasePriceTotal / $currencyRate);

        $this->entityManager->saveEntityWithoutLog($orderReturn);
        $this->entityManager->refreshEntity($orderReturn);

        return $orderReturn;
    }

    /**
     * @param OrderReturnEntity $orderReturn
     * @return bool
     */
    public function afterOrderReturnCreated(OrderReturnEntity $orderReturn)
    {

        $mailTemplate = "order_return";

        /** @var SStoreEntity $store */
        $store = $orderReturn->getStore();
        if (empty($store)) {
            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get('route_manager');
            }
            $store = $this->routeManager->getStoreById($_ENV["DEFAULT_STORE_ID"]);
        }

        $orderWebsiteid = $store->getWebsiteId();

        /** @var ContactEntity $contact */
        $contact = $orderReturn->getContact();

        if (empty($this->mailManager)) {
            $this->mailManager = $this->container->get("mail_manager");
        }

        $bcc[] = array(
            'email' => $_ENV["ORDER_EMAIL_RECIPIENT"],
            'name' => $_ENV["ORDER_EMAIL_RECIPIENT"],
        );

        $attachments = array();

        if (empty($this->mailManager)) {
            $this->mailManager = $this->container->get("mail_manager");
        }

        if ($_ENV["ENABLE_OUTGOING_EMAIL"]) {
            /** @var EmailTemplateManager $emailTemplateManager */
            $emailTemplateManager = $this->container->get('email_template_manager');
            /** @var EmailTemplateEntity $template */
            $template = $emailTemplateManager->getEmailTemplateByCode($mailTemplate);
            if (!empty($template)) {
                $templateData = $emailTemplateManager->renderEmailTemplate($orderReturn, $template);
                $templateAttachments = $template->getAttachments();
                if (!empty($templateAttachments)) {
                    $attachments = array_merge($attachments, $template->getPreparedAttachments());
                }
                $this->mailManager->sendEmail(
                    array('email' => $contact->getEmail(), 'name' => $contact->getEmail()),
                    null,
                    $bcc,
                    null,
                    $templateData["subject"],
                    "",
                    null,
                    [],
                    $templateData["content"],
                    $attachments,
                    $orderReturn->getStoreId()
                );
            } else {
                $this->mailManager->sendEmail(
                    array('email' => $contact->getEmail(), 'name' => $contact->getEmail()),
                    null,
                    $bcc,
                    null,
                    $this->translator->trans(
                        'Order return confirmation'
                    ) . " {$orderReturn->getIncrementId()} - {$orderReturn->getAccountName()}",
                    "",
                    $mailTemplate,
                    array("orderReturn" => $orderReturn, "orderWebsiteId" => $orderWebsiteid),
                    null,
                    $attachments,
                    $orderReturn->getStoreId()
                );
            }
        }

        if (empty($this->orderReturnManager)) {
            $this->orderReturnManager = $this->container->get("order_return_manager");
        }

        $data = array();
        $data["orderState"] = $this->orderReturnManager->getOrderReturnStateById(CrmConstants::ORDER_RETURN_STATE_IN_PROCESS);
        $this->orderReturnManager->updateOrderReturn($orderReturn, $data);

        return true;
    }

    /**
     * @return bool
     */
    public function sendOrdersToErp()
    {


        $entityType = $this->entityManager->getEntityTypeByCode("order");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("sentToErp", "nu", null));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("or");
        $compositeFilter->addFilter(new SearchFilter("numberOfTries", "nu", null));
        $compositeFilter->addFilter(new SearchFilter("numberOfTries", "le", $_ENV["EMAIL_ERROR_NUMBER_OF_TRIES"]));

        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "asc"));

        $orders = $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);

        if (!EntityHelper::isCountable($orders) || count($orders) == 0) {
            return true;
        }

        /** @var OrderEntity $order */
        foreach ($orders as $order) {
            $this->sendOrderWrapper($order);
        }

        return true;
    }

    public function sendOrderWrapper(OrderEntity $order)
    {
        return true;
    }

    /**
     * @return bool
     */
    public function sendReturnOrdersToErp()
    {

        $entityType = $this->entityManager->getEntityTypeByCode("order_return");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("sentToErp", "nu", null));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "asc"));

        $orders = $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);

        if (!EntityHelper::isCountable($orders) || count($orders) == 0) {
            return true;
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        /** @var OrderReturnEntity $order */
        foreach ($orders as $order) {
            $this->sendOrderReturnWrapper($order);
        }

        return true;
    }

    public function sendOrderReturnWrapper(OrderReturnEntity $order)
    {
        return true;
    }

    /**
     * @param WebformSubmissionEntity $submission
     * @param $postData
     * @return array
     * @throws \Exception
     */
    public function afterWebformSubmitted(WebformSubmissionEntity $submission, $postData)
    {
        $ret = [];

        // Save webform_submission_values
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        foreach ($postData as $key => $value) {
            $data = explode("-", $key);
            if ($data[0] == "field") {
                $fieldId = $data[1];

                /** @var WebformFieldEntity $field */
                $field = $this->entityManager->getEntityByEntityTypeCodeAndId("webform_field", $fieldId);

                if (is_array($value)) {
                    foreach ($value as $fieldOptionId) {
                        /** @var WebformSubmissionValueEntity $submissionValue */
                        $submissionValue = $this->entityManager->getNewEntityByAttributSetName("webform_submission_value");
                        $submissionValue->setSubmission($submission);
                        $submissionValue->setField($field);

                        /** @var WebformFieldOptionEntity $fieldOption */
                        $fieldOption = $this->entityManager->getEntityByEntityTypeCodeAndId("webform_field_option", $fieldOptionId);
                        $submissionValue->setFieldOption($fieldOption);

                        $this->entityManager->saveEntity($submissionValue);
                    }
                } else {
                    /** @var WebformSubmissionValueEntity $submissionValue */
                    $submissionValue = $this->entityManager->getNewEntityByAttributSetName("webform_submission_value");
                    $submissionValue->setSubmission($submission);
                    $submissionValue->setField($field);
                    $submissionValue->setSubmissionValue($value);

                    $this->entityManager->saveEntity($submissionValue);
                }
            } elseif ($key == "files") {
                foreach ($value as $fileFieldKey => $fileFieldValue) {
                    if (empty($fileFieldValue["name"])) {
                        continue;
                    }
                    $fileFieldData = explode("-", $fileFieldKey);
                    if ($fileFieldData[0] == "field") {
                        if (empty($this->helperManager)) {
                            $this->helperManager = $this->getContainer()->get('helper_manager');
                        }

                        $targetPath = $_ENV["WEB_PATH"] . "Documents/webform_submission_files/{$submission->getWebform()->getId()}/{$submission->getId()}/";
                        if (!file_exists($targetPath)) {
                            mkdir($targetPath, 0777, true);
                        }

                        /**
                         * Clean filename
                         */
                        $basename = $this->helperManager->getFilenameWithoutExtension($fileFieldValue["name"]);
                        $extension = strtolower($this->helperManager->getFileExtension($fileFieldValue["name"]));

                        $filename = $this->helperManager->nameToFilename($basename);
                        $filename = $filename . "." . $extension;

                        $filename = $this->helperManager->incrementFileName($targetPath, $filename);
                        $targetFile = $targetPath . $filename;

                        if (!move_uploaded_file($fileFieldValue["tmp_name"], $targetFile)) {
                            throw new \Exception("There has been an error with submission file saving");
                        }

                        /** @var WebformFieldEntity $field */
                        $fileField = $this->entityManager->getEntityByEntityTypeCodeAndId("webform_field", $fileFieldData[1]);

                        /** @var WebformSubmissionValueEntity $submissionValue */
                        $submissionValue = $this->entityManager->getNewEntityByAttributSetName("webform_submission_value");
                        $submissionValue->setSubmission($submission);
                        $submissionValue->setField($fileField);
                        $submissionValue->setSubmissionValue($filename);

                        $this->entityManager->saveEntity($submissionValue);
                    }
                }
            }
        }

        $this->entityManager->refreshEntity($submission);

        $session = $this->container->get('session');

        /** @var WebformEntity $webform */
        $webform = $submission->getWebform();

        $sendToEmail = $webform->getSendToEmail();

        if (!empty($sendToEmail)) {
            /** @var EmailTemplateManager $emailTemplateManager */
            $emailTemplateManager = $this->container->get('email_template_manager');
            $emailTemplateManager->sendEmail("webform_submission", $submission, $session->get("current_store_id"), ["email" => $sendToEmail, "name" => $sendToEmail]);
        }

        $backendMethod = $webform->getBackendMethod();
        if (!empty($backendMethod)) {
            $pieces = explode(":", $backendMethod);
            if (!$this->container->has($pieces[0])) {
                throw new \Exception("Missing service: {$pieces[0]}");
            }

            $service = $this->container->get($pieces[0]);
            if (!EntityHelper::checkIfMethodExists($service, $pieces[1])) {
                throw new \Exception("Missing method: {$pieces[1]}");
            }

            $data = $service->{$pieces[1]}($submission, $postData);
            if (!empty($data)) {
                $ret = array_merge($ret, $data);
            }
        }

        return $ret;
    }

    /**
     * Preradi podatke po potrebi.
     *
     * @param array $ret
     * @return array
     * @throws \Exception
     */
    public function afterAddToCartAction(array $ret)
    {
        return $ret;
    }

    /**
     * Preradi podatke po potrebi.
     *
     * @param array $ret
     * @return array
     * @throws \Exception
     */
    public function afterRemoveFromCartAction(array $ret)
    {
        return $ret;
    }

    /**
     * Preradi podatke po potrebi.
     *
     * @param array $ret
     * @return array
     * @throws \Exception
     */
    public function afterUpdateCartAction(array $ret)
    {
        return $ret;
    }

    /**
     * @param LoyaltyCardEntity $loyaltyCard
     * @return null
     */
    public function calculateLoyaltyPointsOnCard(LoyaltyCardEntity $loyaltyCard)
    {

        return null;
    }

    /**
     * @param $data
     * @return null
     */
    public function addToCartError($data)
    {

        if (empty($this->errorLogManager)) {
            $this->errorLogManager = $this->container->get("error_log_manager");
        }

        $this->errorLogManager->logErrorEvent("Add to cart configurable failed", json_encode($data), true);

        return null;
    }

    /**
     * @param ProductEntity $product
     * @return \CrmBusinessBundle\Entity\decimal
     */
    public function getCustomProductQty(ProductEntity $product)
    {

        return $product->getQty();
    }

    /**
     * @param ProductEntity $product
     * @return bool
     */
    public function getCustomProductIsSaleable(ProductEntity $product)
    {

        return $product->getIsSaleable();
    }

    public function modifyMoneyTransferPaymentSlipData(OrderEntity $order, $data)
    {

        return $data;
    }

    /**
     * @return array
     */
    public function getProductExportDefaultAttributes()
    {

        $attributes = array();
        $attributes["code"] = array("enable_import" => true, "is_master" => true);
        $attributes["created"] = array("enable_import" => false);
        $attributes["modified"] = array("enable_import" => false);
        $attributes["ean"] = array("enable_import" => true);
        $attributes["name"] = array("enable_import" => true);
        $attributes["price_base"] = array("enable_import" => true);
        $attributes["price_retail"] = array("enable_import" => true);
        $attributes["qty"] = array("enable_import" => true);
        $attributes["fixed_qty"] = array("enable_import" => true);
        $attributes["short_description"] = array("enable_import" => true);
        $attributes["description"] = array("enable_import" => true);
        $attributes["old_url"] = array("enable_import" => true);
        $attributes["active"] = array("enable_import" => true);
        $attributes["remote_source"] = array("enable_import" => false);
        $attributes["is_visible"] = array("enable_import" => true);
        $attributes["meta_description"] = array("enable_import" => true);
        $attributes["meta_title"] = array("enable_import" => true);
        $attributes["ord"] = array("enable_import" => true);
        $attributes["qty_step"] = array("enable_import" => true);
        $attributes["url"] = array("enable_import" => false);
        $attributes["is_saleable"] = array("enable_import" => false);
        if ($_ENV["USE_READY_FOR_WEBSHOP"]) {
            $attributes["ready_for_webshop"] = array("enable_import" => true);
        }
        $attributes["weight"] = array("enable_import" => true);
        $attributes["weight"] = array("enable_import" => true);
        $attributes["measure"] = array("enable_import" => true);

        return $attributes;
    }

    /**
     * @return array
     */
    public function getProductGroupExportDefaultAttributes()
    {

        $attributes = array();
        $attributes["id"] = array("enable_import" => true, "is_master" => true);
        $attributes["product_group_code"] = array("enable_import" => false);
        $attributes["created"] = array("enable_import" => false);
        $attributes["modified"] = array("enable_import" => false);
        $attributes["name"] = array("enable_import" => true);
        $attributes["url"] = array("enable_import" => false);
        $attributes["product_group.name"] = array("enable_import" => false);
        $attributes["meta_title"] = array("enable_import" => true);
        $attributes["meta_description"] = array("enable_import" => true);
        $attributes["description"] = array("enable_import" => true);
        $attributes["show_on_homepage"] = array("enable_import" => true);
        $attributes["show_as_action"] = array("enable_import" => true);
        $attributes["show_as_bundle"] = array("enable_import" => true);
        $attributes["exclude_from_statistics"] = array("enable_import" => true);
        $attributes["include_in_custom_statistics"] = array("enable_import" => true);

        return $attributes;
    }

    /**
     * @param null $entity
     * @return array
     */
    public function getMessagesForEntity($entity = null)
    {

        return array();
    }

    /**
     * @param null $startFrom
     * @return bool
     */
    public function transferToEur($startFrom = null){

        if(empty($startFrom)){
            $startFrom = 1;
            if(isset($_ENV["TRANSFER_EUR_START_FROM_POSITION"])){
                $startFrom = $_ENV["TRANSFER_EUR_START_FROM_POSITION"];
            }
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE cron_job_entity SET is_active = 0 WHERE method like '%transfer_to_eur%';";
        $this->databaseContext->executeNonQuery($q);

        try {

            $transferCurrency = 7.5345;

            if ($_ENV["DEFAULT_CURRENCY"] != 1) {
                return true;
            }

            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get("route_manager");
            }

            $stores = $this->routeManager->getStores();

            $storesInKn = array();
            $websitesInKn = array();

            /** @var SStoreEntity $store */
            foreach ($stores as $store) {
                if ($store->getDisplayCurrency()->getId() == 1) {
                    $storesInKn[] = $store->getId();
                    $websitesInKn[] = $store->getWebsite()->getId();
                }
            }

            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->container->get("error_log_manager");
            }

            if (empty($this->helperManager)) {
                $this->helperManager = $this->container->get("helper_manager");
            }

            /**
             * Upali maintenance mode
             */
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../.env");
            if (stripos($contents, "MAINTENANCE=0") !== false) {
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "MAINTENANCE=0", "MAINTENANCE=1");
            }

            if($startFrom < 2) {
                /**
                 * Turn off cron jobs
                 */
                $q = "SELECT id FROM cron_job_entity WHERE (name like '%order%' or name like '%import%' or name like '%products%' or name like '%recalculate%' or name like '%statistics%' or name like '%wand%') AND is_active = 1;";
                $jobs = $this->databaseContext->getAll($q);

                if (!empty($jobs)) {
                    $jobIds = array_column($jobs, "id");

                    $q = "UPDATE cron_job_entity SET is_active = 0 WHERE id in (" . implode(",", $jobIds) . ");";
                    $this->databaseContext->executeNonQuery($q);

                    $this->errorLogManager->logErrorEvent("EUR - cron job ids turned off", implode(",", $jobIds), true);
                }
                $startFrom = 2;
                $this->helperManager->addLineToEndOfFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 3) {
                /**
                 * Update currency
                 */
                $q = "UPDATE currency_entity SET rate = 1 WHERE id = 2;";
                $this->databaseContext->executeNonQuery($q);
                $startFrom = 3;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=2", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 4) {
                $q = "UPDATE currency_entity SET rate = 0.1327 WHERE id = 1;";
                $this->databaseContext->executeNonQuery($q);
                $startFrom = 4;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=3", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            /**
             * Drop triggers
             */
            $q = "DROP TRIGGER IF EXISTS product_discount_catalog_price_history_insert;";
            $this->databaseContext->executeNonQuery($q);

            $q = "DROP TRIGGER IF EXISTS product_price_history;";
            $this->databaseContext->executeNonQuery($q);

            $q = "DROP TRIGGER IF EXISTS product_discount_catalog_price_history;";
            $this->databaseContext->executeNonQuery($q);

            $q = "DROP TRIGGER IF EXISTS product_price_history_insert;";
            $this->databaseContext->executeNonQuery($q);

            if($startFrom < 6) {
                /**
                 * Update products
                 */
                $additinalQueryProduct = $this->getCustomQueryTransferEur("product", $transferCurrency);

                $q = "UPDATE product_entity SET
                      {$additinalQueryProduct}
                      price_base = price_base/{$transferCurrency},
                      price_retail = price_retail/{$transferCurrency},
                      discount_price_base = discount_price_base/{$transferCurrency},
                      discount_price_retail = discount_price_retail/{$transferCurrency},
                      discount_diff_base = discount_diff_base/{$transferCurrency},
                      discount_diff = discount_diff/{$transferCurrency},
                      price_purchase = price_purchase/{$transferCurrency},
                      price_return = price_return/{$transferCurrency},
                      currency_id = 2
                WHERE currency_id = 1;";
                $this->databaseContext->executeNonQuery($q);

                $startFrom = 6;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=4", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 7) {
                /**
                 * Update product_account_group_price_entity
                 */
                $q = "UPDATE product_account_group_price_entity SET
                  price_base = price_base/{$transferCurrency},
                  price_retail = price_retail/{$transferCurrency},
                  discount_price_base = discount_price_base/{$transferCurrency},
                  discount_price_retail = discount_price_retail/{$transferCurrency};";
                $this->databaseContext->executeNonQuery($q);

                $startFrom = 7;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=6", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }


            if($startFrom < 8) {
                /**
                 * Update product_account_price_entity
                 */
                $q = "UPDATE product_account_price_entity SET
                  price_base = price_base/{$transferCurrency},
                  price_retail = price_retail/{$transferCurrency},
                  discount_price_base = discount_price_base/{$transferCurrency},
                  discount_price_retail = discount_price_retail/{$transferCurrency};";
                $this->databaseContext->executeNonQuery($q);

                $startFrom = 8;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=7", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 9) {
                /**
                 * Update product_discount_catalog_price_entity
                 */
                $q = "UPDATE product_discount_catalog_price_entity SET
                  price_base = price_base/{$transferCurrency},
                  price_retail = price_retail/{$transferCurrency},
                  discount_price_base = discount_price_base/{$transferCurrency},
                  discount_price_retail = discount_price_retail/{$transferCurrency};";
                $this->databaseContext->executeNonQuery($q);

                $startFrom = 9;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=8", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 10) {
                /**
                 * Update product prices history
                 */
                $q = "UPDATE product_price_history_entity SET price = price/{$transferCurrency}, product_currency_id = 2 WHERE product_currency_id = 1;";
                $this->databaseContext->executeNonQuery($q);

                $startFrom = 10;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=9", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 11) {
                /**
                 * Update discount cart rule entity
                 */
                $q = "UPDATE discount_cart_rule_entity SET min_price = min_price/{$transferCurrency};";
                $this->databaseContext->executeNonQuery($q);

                $startFrom = 11;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=10", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 12) {
                /**
                 * Update discount coupon entity
                 */
                $q = "UPDATE discount_coupon_entity SET min_cart_price = min_cart_price/{$transferCurrency};";
                $this->databaseContext->executeNonQuery($q);

                $startFrom = 12;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=11", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 13) {
                /**
                 * Update quote item entity
                 */
                $additinalQueryQuoteItem = $this->getCustomQueryTransferEur("quote_item", $transferCurrency);

                $q = "UPDATE quote_item_entity SET
                {$additinalQueryQuoteItem}
                base_price_fixed_discount = base_price_fixed_discount/{$transferCurrency},
                base_price_item = base_price_item/{$transferCurrency},
                base_price_item_tax = base_price_item_tax/{$transferCurrency},
                base_price_item_without_tax = base_price_item_without_tax/{$transferCurrency},
                base_price_tax = base_price_tax/{$transferCurrency},
                base_price_total = base_price_total/{$transferCurrency},
                base_price_without_tax = base_price_without_tax/{$transferCurrency},
                price_fixed_discount = price_fixed_discount/{$transferCurrency},
                price_item = price_item/{$transferCurrency},
                price_item_tax = price_item_tax/{$transferCurrency},
                price_item_without_tax = price_item_without_tax/{$transferCurrency},
                price_tax = price_tax/{$transferCurrency},
                price_total = price_total/{$transferCurrency},
                price_without_tax = price_without_tax/{$transferCurrency},
                original_price_item = original_price_item/{$transferCurrency},
                original_price_item_tax = original_price_item_tax/{$transferCurrency},
                original_price_item_without_tax = original_price_item_without_tax/{$transferCurrency},
                original_base_price_item = original_base_price_item/{$transferCurrency},
                original_base_price_item_tax = original_base_price_item_tax/{$transferCurrency},
                original_base_price_item_without_tax = original_base_price_item_without_tax/{$transferCurrency},
                price_discount_total = price_discount_total/{$transferCurrency},
                base_price_discount_total = base_price_discount_total/{$transferCurrency},
                base_price_discount_coupon_item = base_price_discount_coupon_item/{$transferCurrency},
                base_price_discount_coupon_total = base_price_discount_coupon_total/{$transferCurrency},
                price_discount_coupon_item = price_discount_coupon_item/{$transferCurrency},
                price_discount_coupon_total = price_discount_coupon_total/{$transferCurrency},
                base_price_discount_item = base_price_discount_item/{$transferCurrency},
                price_discount_item = price_discount_item/{$transferCurrency},
                price_item_return = price_item_return/{$transferCurrency},
                price_return_total = price_return_total/{$transferCurrency},
                base_price_loyalty_discount_item = base_price_loyalty_discount_item/{$transferCurrency},
                base_price_loyalty_discount_total = base_price_loyalty_discount_total/{$transferCurrency},
                price_loyalty_discount_item = price_loyalty_discount_item/{$transferCurrency},
                price_loyalty_discount_total = price_loyalty_discount_total/{$transferCurrency},
                original_base_price_without_tax = original_base_price_without_tax/{$transferCurrency},
                original_base_price_tax = original_base_price_tax/{$transferCurrency},
                original_base_price_total = original_base_price_total/{$transferCurrency}
                WHERE quote_id IN (SELECT id FROM quote_entity WHERE currency_id = 1 AND quote_status_id IN (1,5,6));";
                $this->databaseContext->executeNonQuery($q);

                $startFrom = 13;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=12", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 14) {
                $q = "UPDATE quote_item_entity SET
                {$additinalQueryQuoteItem}
                base_price_fixed_discount = base_price_fixed_discount/{$transferCurrency},
                base_price_item = base_price_item/{$transferCurrency},
                base_price_item_tax = base_price_item_tax/{$transferCurrency},
                base_price_item_without_tax = base_price_item_without_tax/{$transferCurrency},
                base_price_tax = base_price_tax/{$transferCurrency},
                base_price_total = base_price_total/{$transferCurrency},
                base_price_without_tax = base_price_without_tax/{$transferCurrency},
                original_base_price_item = original_base_price_item/{$transferCurrency},
                original_base_price_item_tax = original_base_price_item_tax/{$transferCurrency},
                original_base_price_item_without_tax = original_base_price_item_without_tax/{$transferCurrency},
                base_price_discount_total = base_price_discount_total/{$transferCurrency},
                base_price_discount_coupon_item = base_price_discount_coupon_item/{$transferCurrency},
                base_price_discount_coupon_total = base_price_discount_coupon_total/{$transferCurrency},
                base_price_discount_item = base_price_discount_item/{$transferCurrency},
                base_price_loyalty_discount_item = base_price_loyalty_discount_item/{$transferCurrency},
                base_price_loyalty_discount_total = base_price_loyalty_discount_total/{$transferCurrency},
                original_base_price_without_tax = original_base_price_without_tax/{$transferCurrency},
                original_base_price_tax = original_base_price_tax/{$transferCurrency},
                original_base_price_total = original_base_price_total/{$transferCurrency}
                WHERE quote_id IN (SELECT id FROM quote_entity WHERE currency_id = 2 AND quote_status_id IN (1,5,6));";
                $this->databaseContext->executeNonQuery($q);

                $startFrom = 14;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=13", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 15) {
                /**
                 * Update quote entity
                 */
                $additinalQueryQuote = $this->getCustomQueryTransferEur("quote", $transferCurrency);

                $q = "UPDATE quote_entity SET
                {$additinalQueryQuote}
                base_price_total = base_price_total/{$transferCurrency},
                base_price_tax = base_price_tax/{$transferCurrency},
                base_price_without_tax = base_price_without_tax/{$transferCurrency},
                base_price_delivery_total = base_price_delivery_total/{$transferCurrency},
                base_price_delivery_tax = base_price_delivery_tax/{$transferCurrency},
                base_price_delivery_without_tax = base_price_delivery_without_tax/{$transferCurrency},
                base_price_items_total = base_price_items_total/{$transferCurrency},
                base_price_items_tax = base_price_items_tax/{$transferCurrency},
                base_price_items_without_tax = base_price_items_without_tax/{$transferCurrency},
                base_price_discount = base_price_discount/{$transferCurrency},
                discount_loyalty_base_price_total = discount_loyalty_base_price_total/{$transferCurrency},
                base_price_fee = base_price_fee/{$transferCurrency},
                currency_rate = 1
                WHERE currency_id = 2 AND quote_status_id IN (1,5,6);";
                $this->databaseContext->executeNonQuery($q);

                $startFrom = 15;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=14", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 16) {
                /**
                 * Update quote entity
                 */
                $additinalQueryQuote = $this->getCustomQueryTransferEur("quote", $transferCurrency);

                $q = "UPDATE quote_entity SET
                {$additinalQueryQuote}
                currency_id = 2,
                base_price_total = base_price_total/{$transferCurrency},
                base_price_tax = base_price_tax/{$transferCurrency},
                base_price_without_tax = base_price_without_tax/{$transferCurrency},
                price_total = price_total/{$transferCurrency},
                price_tax = price_tax/{$transferCurrency},
                price_without_tax = price_without_tax/{$transferCurrency},
                base_price_delivery_total = base_price_delivery_total/{$transferCurrency},
                base_price_delivery_tax = base_price_delivery_tax/{$transferCurrency},
                base_price_delivery_without_tax = base_price_delivery_without_tax/{$transferCurrency},
                price_delivery_total = price_delivery_total/{$transferCurrency},
                price_delivery_tax = price_delivery_tax/{$transferCurrency},
                price_delivery_without_tax = price_delivery_without_tax/{$transferCurrency},
                base_price_items_total = base_price_items_total/{$transferCurrency},
                base_price_items_tax = base_price_items_tax/{$transferCurrency},
                base_price_items_without_tax = base_price_items_without_tax/{$transferCurrency},
                price_items_without_tax = price_items_without_tax/{$transferCurrency},
                price_items_tax = price_items_tax/{$transferCurrency},
                price_items_total = price_items_total/{$transferCurrency},
                base_price_discount = base_price_discount/{$transferCurrency},
                price_discount = price_discount/{$transferCurrency},
                discount_coupon_price_total = discount_coupon_price_total/{$transferCurrency},
                discount_loyalty_price_total = discount_loyalty_price_total/{$transferCurrency},
                discount_loyalty_base_price_total = discount_loyalty_base_price_total/{$transferCurrency},
                price_return_total = price_return_total/{$transferCurrency},
                base_price_fee = base_price_fee/{$transferCurrency},
                price_fee = price_fee/{$transferCurrency}
                WHERE currency_id = 1 AND quote_status_id IN (1,5,6);";
                $this->databaseContext->executeNonQuery($q);

                $startFrom = 16;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=15", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 17) {
                /**
                 * Update analitike
                 */
                $q = "UPDATE shape_track_order_item_fact SET
                base_price_total = base_price_total/{$transferCurrency},
                base_price_discount_total = base_price_discount_total/{$transferCurrency},
                base_price_tax = base_price_tax/{$transferCurrency},
                base_price_item_tax = base_price_item_tax/{$transferCurrency},
                base_price_item = base_price_item/{$transferCurrency},
                order_base_price_total = order_base_price_total/{$transferCurrency},
                order_base_price_tax = order_base_price_tax/{$transferCurrency},
                order_base_price_items_total = order_base_price_items_total/{$transferCurrency},
                order_base_price_items_tax = order_base_price_items_tax/{$transferCurrency},
                order_base_price_delivery_total = order_base_price_delivery_total/{$transferCurrency},
                order_base_price_delivery_tax = order_base_price_delivery_tax/{$transferCurrency},
                currency_id = 2,
                currency_rate = 1,
                discount_coupon_price_total = discount_coupon_price_total/{$transferCurrency}
                ;";
                $this->databaseContext->executeNonQuery($q);

                $startFrom = 17;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=16", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 18) {
                $q = "UPDATE shape_track_product_group_fact SET
                order_total_amount = order_total_amount/{$transferCurrency},
                order_success_amount = order_success_amount/{$transferCurrency},
                order_canceled_amount = order_canceled_amount/{$transferCurrency},
                order_in_process_amount = order_in_process_amount/{$transferCurrency}
                ;";
                $this->databaseContext->executeNonQuery($q);

                $startFrom = 18;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=17", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 19) {
                $q = "UPDATE shape_track_totals_fact SET
                order_total_amount = order_total_amount/{$transferCurrency},
                order_success_amount = order_success_amount/{$transferCurrency},
                order_canceled_amount = order_canceled_amount/{$transferCurrency},
                order_in_process_amount = order_in_process_amount/{$transferCurrency},
                quote_total_amount = quote_total_amount/{$transferCurrency}
                ;";
                $this->databaseContext->executeNonQuery($q);

                $startFrom = 19;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=18", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if (empty($this->quoteManager)) {
                $this->quoteManager = $this->container->get("quote_manager");
            }

            if($startFrom < 20) {

                $paymentTypes = $this->quoteManager->getFilteredPaymentTypes();

                /** @var PaymentTypeEntity $paymentType */
                foreach ($paymentTypes as $paymentType) {

                    $paymentFee = $paymentType->getPaymentFee();
                    $changed = false;

                    if (!empty($paymentFee)) {
                        foreach ($paymentFee as $key => $value) {
                            if (in_array($key, $storesInKn)) {
                                $paymentFee[$key] = floatval($value) / $transferCurrency;
                                $changed = true;
                            }
                        }
                        $paymentType->setPaymentFee($paymentFee);
                    }

                    $maxCartTotal = $paymentType->getMaxCartTotal();
                    if (!empty($maxCartTotal)) {
                        foreach ($maxCartTotal as $key => $value) {
                            if (in_array($key, $websitesInKn)) {
                                $maxCartTotal[$key] = floatval($value) / $transferCurrency;
                                $changed = true;
                            }
                        }
                        $paymentType->setMaxCartTotal($maxCartTotal);
                    }

                    $minCartTotal = $paymentType->getMinCartTotal();
                    if (!empty($minCartTotal)) {
                        foreach ($minCartTotal as $key => $value) {
                            if (in_array($key, $websitesInKn)) {
                                $minCartTotal[$key] = floatval($value) / $transferCurrency;
                                $changed = true;
                            }
                        }
                        $paymentType->setMinCartTotal($minCartTotal);
                    }

                    if ($changed) {
                        $this->entityManager->saveEntity($paymentType);
                    }
                }

                $startFrom = 20;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=19", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 21) {
                /**
                 * Update stores
                 */
                $q = "UPDATE s_store_entity SET display_currency_id = 2 WHERE id in (" . implode(",", $storesInKn) . ");";
                $this->databaseContext->executeNonQuery($q);
                $startFrom = 21;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=20", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 22) {

                if (!isset($_ENV["DO_NOT_UPDATE_DELIVERY_PRICES_EUR"]) || $_ENV["DO_NOT_UPDATE_DELIVERY_PRICES_EUR"] == 0) {
                    /**
                     * Update delivery prices
                     */
                    $q = "UPDATE delivery_prices_entity SET
                            size_from = size_from/{$transferCurrency},
                            size_to = size_to/{$transferCurrency},
                            price_base = price_base/{$transferCurrency},
                            for_every_next_size = for_every_next_size/{$transferCurrency},
                            price_base_step = price_base_step/{$transferCurrency},
                            step_starts_at = step_starts_at/{$transferCurrency}
                        ;";
                    $this->databaseContext->executeNonQuery($q);
                }
                else{
                    /**
                     * Update delivery prices
                     */
                    $q = "UPDATE delivery_prices_entity SET
                            price_base = price_base/{$transferCurrency},
                            price_base_step = price_base_step/{$transferCurrency}
                        ;";
                    $this->databaseContext->executeNonQuery($q);
                }

                $startFrom = 22;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=21", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 23) {
                /**
                 * Custom per project
                 */
                $q = $this->getCustomQueryTransferEur("custom", $transferCurrency);
                if(!empty(trim($q))){
                    $this->databaseContext->executeNonQuery($q);
                }

                $startFrom = 23;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=22", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 24) {
                /**
                 * Update configuration.env
                 */
                $contentExists = false;
                $contents = file_get_contents($_ENV["WEB_PATH"] . "../configuration.env");
                if (stripos($contents, "HRK") !== false) {
                    $contentExists = true;
                }

                if ($contentExists) {
                    $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../configuration.env", "HRK", "EUR");
                }

                /**
                 * Update env
                 */
                $contentExists = false;
                $contents = file_get_contents($_ENV["WEB_PATH"] . "../.env");
                if (stripos($contents, "DEFAULT_CURRENCY=1") !== false) {
                    $contentExists = true;
                }

                if ($contentExists) {
                    $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "DEFAULT_CURRENCY=1", "DEFAULT_CURRENCY=2");
                }

                $contentExists = false;
                if (stripos($contents, "TRANSFER_TO_CURRENCY_SIGN=€") !== false) {
                    $contentExists = true;
                }
                if ($contentExists) {
                    $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_TO_CURRENCY_SIGN=€", "TRANSFER_TO_CURRENCY_SIGN=kn");
                }

                $contentExists = false;
                if (stripos($contents, "TRANSFER_TO_CURRENCY_CODE=EUR") !== false) {
                    $contentExists = true;
                }
                if ($contentExists) {
                    $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_TO_CURRENCY_CODE=EUR", "TRANSFER_TO_CURRENCY_CODE=HRK");
                }

                $contentExists = false;
                if (stripos($contents, "CURRENT_CURRENCY_CODE=HRK") !== false) {
                    $contentExists = true;
                }
                if ($contentExists) {
                    $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "CURRENT_CURRENCY_CODE=HRK", "CURRENT_CURRENCY_CODE=EUR");
                }

                $startFrom = 24;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=23", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            if($startFrom < 25) {
                shell_exec("rm -rf var/cache/_sp");
                shell_exec("mv var/cache/sp var/cache/_sp");
                shell_exec("rm -rf var/cache/_sp");
                shell_exec("php bin/console cache:clear");
                shell_exec("php bin/console cache:clear --env=prod");
                shell_exec("php bin/console admin:entity clear_backend_cache");

                $startFrom = 25;
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "TRANSFER_EUR_START_FROM_POSITION=24", "TRANSFER_EUR_START_FROM_POSITION={$startFrom}");
            }

            /**
             * Ugasi maintenance mode
             */
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../.env");
            if (stripos($contents, "MAINTENANCE=1") !== false) {
                $this->helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.env", "MAINTENANCE=1", "MAINTENANCE=0");
            }

            $this->errorLogManager->logErrorEvent("EUR - done {$_ENV["FRONTEND_URL"]}", "{$_ENV["FRONTEND_URL"]}", true);
        }
        catch (\Exception $e) {
            $this->errorLogManager->logExceptionEvent("EUR - UPDATE currency_entity - last successuful point - {$startFrom}", $e, true);
        }

        return true;
    }

    public function getCustomQueryTransferEur($type,$transferCurrency){
        return "";
    }
}
