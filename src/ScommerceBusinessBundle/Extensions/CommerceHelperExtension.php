<?php

namespace ScommerceBusinessBundle\Extensions;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\DiscountCouponEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderItemEntity;
use CrmBusinessBundle\Entity\ProductDocumentEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\DiscountCouponManager;
use CrmBusinessBundle\Managers\NewsletterManager;
use CrmBusinessBundle\Managers\OrderComplaintManager;
use CrmBusinessBundle\Managers\OrderReturnManager;
use CrmBusinessBundle\Managers\ProductDocumentRulesManager;
use CrmBusinessBundle\Managers\ProductManager;
use CrmBusinessBundle\Managers\QuoteManager;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Managers\DefaultScommerceManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Managers\ScommerceHelperManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class CommerceHelperExtension extends \Twig_Extension
{

    /**
     * @var ContainerInterface
     */
    protected $container;

    /** @var QuoteManager $quoteManager */
    protected $quoteManager;

    /** @var ProductManager $productManager */
    protected $productManager;

    /** @var AccountManager $accountManager */
    protected $accountManager;

    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;

    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;

    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;

    /** @var DefaultScommerceManager $scommerceManager */
    protected $scommerceManager;

    /** @var NewsletterManager $newsletterManager */
    protected $newsletterManager;

    /** @var RouteManager $routeManager */
    protected $routeManager;

    /** @var OrderReturnManager $orderReturnManager */
    protected $orderReturnManager;

    /** @var DiscountCouponManager $discountCouponManager */
    protected $discountCouponManager;

    /** @var ProductDocumentRulesManager $productDocumentRulesManager */
    protected $productDocumentRulesManager;
    protected $twig;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('is_legal_account', array($this, 'getIsLegalAccount')),
            new \Twig_SimpleFunction('get_bundle_savings_calculation', array($this, 'getSavingsCalculation')),
            new \Twig_SimpleFunction('get_real_product', array($this, 'getRealProduct')),
            new \Twig_SimpleFunction('is_configurable', array($this, 'productIsConfigurable')),
            new \Twig_SimpleFunction('is_configurable_bundle', array($this, 'productIsConfigurableBundle')),
            new \Twig_SimpleFunction('is_bundle', array($this, 'productIsBundle')),
            new \Twig_SimpleFunction('is_child_in_bundle', array($this, 'productIsChildInBundle')),
            new \Twig_SimpleFunction('get_product_title', array($this, 'getProductTitle')),
            new \Twig_SimpleFunction('get_product_details', array($this, 'getProductDetails')),
            new \Twig_SimpleFunction('get_query_parameters', array($this, 'getQueryParameters')),
            new \Twig_SimpleFunction('get_quote_item_by_id', array($this, 'getQuoteItemById')),
            new \Twig_SimpleFunction('get_product_by_id', array($this, 'getProductById')),
            new \Twig_SimpleFunction('get_selected_configurable_bundle_options', array($this, 'getSelectedConfigurableBundleOptions')),
            new \Twig_SimpleFunction('get_selected_configurable_options', array($this, 'getSelectedConfigurableOptions')),
            new \Twig_SimpleFunction('get_is_admin', array($this, 'getIsAdmin')),
            new \Twig_SimpleFunction('free_delivery_calculate', array($this, 'getFreeDeliveryCalculate')),
            new \Twig_SimpleFunction('free_delivery_min_price', array($this, 'getFreeDeliveryMinPrice')),
            new \Twig_SimpleFunction('delivery_min_price', array($this, 'getDeliveryPrice')),
            new \Twig_SimpleFunction('get_entity_frontend_url', array($this, 'getEntityFrontendUrl')),
            new \Twig_SimpleFunction('get_product_group_entity', array($this, 'getProductGroupEntity')),
            new \Twig_SimpleFunction('get_product_group_children', array($this, 'getProductGroupChildren')),
            new \Twig_SimpleFunction('order_item_return_enabled', array($this, 'orderItemReturnEnabled')),
            new \Twig_SimpleFunction('order_return_enabled', array($this, 'orderReturnEnabled')),
            new \Twig_SimpleFunction('prepare_qty', array($this, 'prepareQty')),
            new \Twig_SimpleFunction('is_user_subscribed_to_newsletter', array($this, 'isUserSubscribedToNewsletter')),
            new \Twig_SimpleFunction('is_user_logged_in', array($this, 'isUserLoggedIn')),
            new \Twig_SimpleFunction('current_currency_code', array($this, 'getCurrentCurrencyCode')),
            new \Twig_SimpleFunction('get_active_quote', array($this, 'getActiveQuote')),

            // Dohvaca product labele po grupama
            new \Twig_SimpleFunction('product_labels', array($this, 'getProductLabels')),

            // Loading via extension as block doesn't work when loading from ajax.
            new \Twig_SimpleFunction('get_faq_for_entity', array($this, 'getFaqForEntity')),

            // Used for overriding is saleable
            new \Twig_SimpleFunction('get_product_is_saleable', array($this, 'getProductIsSaleable')),

            // Used for overriding get product qty
            new \Twig_SimpleFunction('get_product_qty', array($this, 'getProductQty')),

            // Used in minicart if amount is visible
            new \Twig_SimpleFunction('get_cart_total', array($this, 'getCartTotal')),

            // Used for bulk prices on checkout
            new \Twig_SimpleFunction('get_quote_item_bulk_price_options', array($this, 'getQuoteItemBulkPriceOptions')),

            // Used in email templates
            new \Twig_SimpleFunction('generate_cart_discount_coupon', array($this, 'generateCartDiscountCoupon')),

            // Used in product details
            new \Twig_SimpleFunction('get_product_number_of_orders_in_hours', array($this, 'getProductNumberOfOrdersInHours')),
            new \Twig_SimpleFunction('get_number_of_users_on_page', array($this, 'getNumberOfUsersOnPageInLastMinutes')),

            // Used in return modal is simple select is active
            new \Twig_SimpleFunction('get_bank_accounts', array($this, 'getBankAccounts')),
            new \Twig_SimpleFunction('get_addresses', array($this, 'getAddresses')),

            // Get coupon to show on product
            new \Twig_SimpleFunction('get_product_coupon', array($this, 'getProductCoupon')),

            // Get coupon to show on product
            new \Twig_SimpleFunction('get_checkout_card_coupons', array($this, 'getCheckoutCardCoupons')),
            new \Twig_SimpleFunction('get_product_card_coupons', array($this, 'getProductCardCoupons')),

            // Get active coupons
            new \Twig_SimpleFunction('get_active_coupons', array($this, 'getActiveCoupons')),

            // Complaints
            new \Twig_SimpleFunction('order_item_complaint_enabled', array($this, 'orderItemComplaintEnabled')),
            new \Twig_SimpleFunction('order_complaint_enabled', array($this, 'orderComplaintEnabled')),

            new \Twig_SimpleFunction('get_product_documents', array($this, 'getProductDocuments')),
        ];
    }

    /**
     * @param ProductEntity $product
     * @param array $lablePositionCodes
     * @param bool $isProductPage
     * @return array|void
     */
    public function getProductLabels(ProductEntity $product, array $lablePositionCodes = array(), bool $isProductPage = false)
    {
        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $session = $this->container->get("session");

        return $this->crmProcessManager->getProductLabels($product, $session->get("current_store_id"), $lablePositionCodes, $isProductPage);
    }

    /**
     * @param $entity
     * @return array
     */
    public function getFaqForEntity($entity)
    {
        $session = $this->container->get("session");
        $faqManager = $this->container->get("faq_manager");
        return $faqManager->getFaqByRelatedEntityTypeAndId($session->get("current_store_id"), $entity);
    }

    /**
     * @param ProductEntity $product
     * @return bool|int
     */
    public function getProductIsSaleable(ProductEntity $product)
    {
        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        return $this->crmProcessManager->getCustomProductIsSaleable($product);
    }

    /**
     * @param ProductEntity $product
     * @return bool|int
     */
    public function getProductQty(ProductEntity $product)
    {
        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        return $this->crmProcessManager->getCustomProductQty($product);
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getCurrentCurrencyCode()
    {
        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");

        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        /** @var SStoreEntity $store */
        $store = $this->routeManager->getStoreById($storeId);

        if (!empty($store)) {
            return $store->getDisplayCurrency()->getSign();
        }

        return "";
    }

    /**
     * @param QuoteItemEntity|null $quoteItem
     * @return array
     */
    public function getQuoteItemBulkPriceOptions(QuoteItemEntity $quoteItem = null)
    {

        if (empty($quoteItem)) {
            return array();
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        /** @var Session $session */
        $session = $this->container->get("session");
        /** @var AccountEntity $account */
        $account = null;
        if (!empty($session->get("account"))) {
            $account = $session->get("account");
        }

        $parentProduct = null;
        if (!empty($quoteItem->getParentItem())) {
            $parentProduct = $quoteItem->getParentItem()->getProduct();
        }

        $prices = $this->crmProcessManager->getProductPrices($quoteItem->getProduct(), $account, $parentProduct);

        $ret = array();

        if (isset($prices["bulk_prices"]) && !empty($prices["bulk_prices"])) {
            $ret["currency_code"] = $quoteItem->getQuote()->getCurrency()->getSign();
            foreach ($prices["bulk_prices"] as $bulk_price) {
                if (floatval($bulk_price["min_qty"]) > $quoteItem->getQty()) {
                    $ret["next_bulk_qty"] = intval($bulk_price["min_qty"] - $quoteItem->getQty());
                    $ret["next_bulk_price"] = $bulk_price["bulk_price_item_final"];
                    break;
                }
            }
        }
        return $ret;
        //TREBA PISATI: Dodaj jos {{ $ret["next_bulk_qty"] }} komad i kupi ih po cijeni {{ $ret["next_bulk_price"] }} {{ currency code }}
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getProductDetails(ProductEntity $product)
    {
        if (empty($this->productManager)) {
            $this->productManager = $this->container->get("product_manager");
        }

        return $this->productManager->getProductDetails($product);
    }

    /**
     * @return QuoteEntity|null
     * @throws \Exception
     */
    function getActiveQuote()
    {
        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        return $this->quoteManager->getActiveQuote();
    }

    /**
     * @return bool|float|int|null
     * @throws \Exception
     */
    function getFreeDeliveryCalculate()
    {
        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        $quote = $this->quoteManager->getActiveQuote();
        return $this->quoteManager->calculateAmountToFreeDelivery($quote);
    }

    /**
     * @return bool|float|int|null
     * @throws \Exception
     */
    function getDeliveryPrice()
    {
        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        $quote = $this->quoteManager->getActiveQuote();
        return $this->quoteManager->calculateAmountToFreeDelivery($quote);
    }

    /**
     * @return bool|float|int|null
     * @throws \Exception
     */
    function getFreeDeliveryMinPrice()
    {
        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        $quote = $this->quoteManager->getActiveQuote(false);

        return $this->quoteManager->getFreeDeliveryMinimumPrice($quote);
    }

    /**
     * @return array
     */
    function getIsLegalAccount($account = null)
    {
        if (!empty($account)) {
            return $account->getIsLegalEntity();
        }

        $session = $this->container->get('session');

        /** @var AccountEntity $account */
        $account = $session->get("account");

        if (empty($account)) {
            return false;
        }

        return $account->getIsLegalEntity();
    }

    /**
     * @param ProductEntity $product
     * @param array $include
     * @return array
     */
    function getSavingsCalculation(ProductEntity $product, $include = [])
    {
        if (empty($this->productManager)) {
            $this->productManager = $this->container->get("product_manager");
        }

        return $this->productManager->getBundleProductSavingCalculations($product, $include);
    }

    /**
     * @param ProductEntity $product
     * @param array $queryParameters
     * @return bool
     */
    function getRealProduct($product, $queryParameters)
    {
        if (empty($this->productManager)) {
            $this->productManager = $this->container->get("product_manager");
        }
        if ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {

            if (isset($queryParameters["qi"])) {
                /**
                 * PRODUCT_TYPE_CONFIGURABLE - kada se dođe na proizvod preko quote_item-a
                 */
                /** @var QuoteItemEntity $quote */
                $quote = $this->getQuoteItemById($queryParameters["qi"]);
                if (!empty($quote)) {
                    $product = $quote->getProduct();
                    if ($this->productIsConfigurable($product)) {
                        $children = $quote->getChildItems();
                        if (!empty($children)) {
                            $product = $children[0]->getProduct();
                        }
                    }
                }
            } elseif (isset($queryParameters["configurable"])) {
                /**
                 * PRODUCT_TYPE_CONFIGURABLE - kada se klika konfiguracija pa se otvori preko linka
                 */
                if (is_array($queryParameters["configurable"])) {
                    $configurableProduct = $this->productManager->getSimpleProductFromConfiguration($product, $queryParameters["configurable"]);
                    if (!empty($configurableProduct)) {
                        $product = $configurableProduct;
                    }
                }
            } else {

                /**
                 * PRODUCT_TYPE_CONFIGURABLE - defaultni proizvod
                 */
                $product_details = $this->getProductDetails($product);
                if (isset($product_details["default_product"]) && !empty($product_details["default_product"])) {
                    $product = $product_details["default_product"];
                }
            }
        }
        return $product;
    }

    /**
     * @param ProductEntity $product
     * @param array $queryParameters
     * @return bool
     */
    function getProductTitle($product, $queryParameters)
    {
        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }

        $session = $this->container->get('session');
        return $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $this->getRealProduct($product, $queryParameters), "name");
    }

    /**
     * @param $coreUser
     * @return bool
     */
    function getIsAdmin($coreUser)
    {

        if (empty($coreUser) || !is_object($coreUser)) {
            return false;
        }

        $frontendAdminAccountRoles = $_ENV["FRONTEND_ADMIN_ACCOUNT_ROLES"] ?? 0;
        if (empty($frontendAdminAccountRoles)) {
            return false;
        }
        $frontendAdminAccountRoles = json_decode($frontendAdminAccountRoles, true);

        if (empty($frontendAdminAccountRoles)) {
            return false;
        }

        $roleCodes = $coreUser->getUserRoleCodes();
        if (!EntityHelper::isCountable($roleCodes) || count($roleCodes) < 1) {
            return false;
        }

        if (count(array_intersect($frontendAdminAccountRoles, $roleCodes)) === 0) {
            return false;
        }

        if (empty($this->twigBase)) {
            $this->twigBase = $this->container->get('twig');
        }

        $globals = $this->twigBase->getGlobals();

        // Entity permissions check
        if (isset($globals["current_entity"]) && !empty($globals["current_entity"])) {
            $entity = $globals["current_entity"];
            if (!empty($entity)) {
                /** @var EntityType $entityType */
                $entityType = $entity->getEntityType();
                if (EntityHelper::checkIfMethodExists($entityType, "getCheckPrivileges")) {
                    $hasPrivilege = true;
                    if (!$coreUser->hasPrivilege(3, $entity->getAttributeSet()->getUid())) {
                        $hasPrivilege = false;
                    }
                    if (!$hasPrivilege) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param $quoteItemId
     * @return array
     */
    public function getSelectedConfigurableBundleOptions($quoteItemId)
    {
        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        /** @var QuoteItemEntity $quoteItem */
        $quoteItem = $this->getQuoteItemById($quoteItemId);

        if (empty($quoteItem)) {
            return [];
        }

        $options = $this->quoteManager->prepareOptionsForQuoteItem($quoteItem);

        if (!isset($options["configurable_bundle"]) || empty($options["configurable_bundle"])) {
            return [];
        }

        return json_decode($options["configurable_bundle"], true);
    }

    /**
     * @param $quoteItemId
     * @return array
     */
    public function getSelectedConfigurableOptions($quoteItemId)
    {
        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        /** @var QuoteItemEntity $quoteItem */
        $quoteItem = $this->getQuoteItemById($quoteItemId);

        if (empty($quoteItem)) {
            return [];
        }

        $options = $this->quoteManager->prepareOptionsForQuoteItem($quoteItem);

        if (!isset($options["configurable"]) || empty($options["configurable"])) {
            return [];
        }

        return json_decode($options["configurable"], true);
    }

    /**
     * @param $id
     * @return ProductEntity
     */
    public function getProductById($id)
    {
        if (empty($this->productManager)) {
            $this->productManager = $this->container->get("product_manager");
        }
        return $this->productManager->getProductById($id);
    }

    /**
     * @param $id
     * @return QuoteItemEntity|null
     */
    public function getQuoteItemById($id)
    {
        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        $session = $this->container->get('session');

        /** @var AccountEntity $account */
        $account = null;
        if (!empty($session->get("account"))) {
            $account = $session->get("account");
        }

        /** @var QuoteItemEntity $quoteItem */
        $quoteItem = $this->quoteManager->getQuoteItemById($id);
        if (empty($quoteItem)) {
            return null;
        }

        if (!empty($quoteItem->getQuote()->getAccount()) && (empty($account) || $account->getId() != $quoteItem->getQuote()->getAccount()->getId())) {
            return null;
        } elseif ($quoteItem->getQuote()->getSessionId() != $session->getId()) {
            return null;
        }

        return $quoteItem;
    }

    /**
     * @return array
     */
    public function getQueryParameters()
    {
        $params = $_GET;

        if (empty($params)) {
            $request = $this->container->get('request_stack')->getCurrentRequest();
            $params = $request->request->all();
        }

        return $params;
    }

    /**
     * @param ProductEntity $product
     * @param array $parameters
     * @return array
     */
    public function getConfigurablePrice(ProductEntity $product, $parameters)
    {
        $session = $this->container->get('session');

        /** @var AccountEntity $account */
        $account = null;
        if (!empty($session->get("account"))) {
            $account = $session->get("account");
        }

        $product = $this->getRealProduct($product, $parameters);

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        return $this->crmProcessManager->getProductPrices($product, $account);
    }

    /**
     * @param ProductEntity $product
     * @return bool
     */
    public function productIsConfigurableBundle($product)
    {
        return $product->getEntityType()->getEntityTypeCode() == "product" && $product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE;
    }

    /**
     * @param ProductEntity $product
     * @return bool
     */
    public function productIsBundle($product)
    {
        /**
         * Zakomentirao sam ovu provjeru, ne znam zasto postoji
         */
        //if ($product->getEntityType()->getEntityTypeCode() == "product") {
        if ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_BUNDLE) {
            return $product;
        }
        //}

        return false;
    }

    /**
     * @param ProductEntity $product
     * @return bool
     */
    public function productIsChildInBundle($product)
    {
        if ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_SIMPLE) {

            if (empty($this->productManager)) {
                $this->productManager = $this->container->get("product_manager");
            }

            $parentProducts = $this->productManager->getParentBundleProducts($product);
            if (EntityHelper::isCountable($parentProducts) && count($parentProducts)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ProductEntity $product
     * @return bool
     */
    public function productIsConfigurable($product)
    {
        return $product->getEntityType()->getEntityTypeCode() == "product" && $product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE;
    }

    /**
     * @param $entity
     * @return []
     */
    public function getEntityFrontendUrl($entity)
    {
        $urls = [];
        if (method_exists($entity, "getUrl")) {
            $url = $entity->getUrl();
            if (is_array($url)) {
                if (empty($this->getPageUrlExtension)) {
                    $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
                }

                if (method_exists($entity, "getShowOnStore")) {
                    $stores = $entity->getShowOnStore();
                    if (!empty($stores)) {
                        /** @var RouteManager $routeManager */
                        $routeManager = $this->container->get("route_manager");
                        foreach ($entity->getShowOnStore() as $storeId => $value) {
                            if ($value == 1 && isset($url[$storeId])) {
                                /** @var SStoreEntity $store */
                                $store = $routeManager->getStoreById($storeId);
                                if (!empty($store) && !isset($urls[$store->getWebsite()->getName()])) {
                                    $baseUrl = $store->getWebsite()->getBaseUrl();
                                    $urls[$store->getWebsite()->getName()] = $_ENV["SSL"] . "://" . $baseUrl . $_ENV["FRONTEND_URL_PORT"] . "/" . $store->getCoreLanguage()->getCode() . "/" . $url[$storeId] ?? null;
                                }
                            }
                        }
                    }
                } else {
                    $session = $this->container->get('session');
                    $storeId = $session->get("current_store_id") ?? $_ENV["DEFAULT_STORE_ID"];
                    $urls["default"] = $_ENV["SSL"] . "://" . $_ENV["FRONTEND_URL"] . $_ENV["FRONTEND_URL_PORT"] . "/" . $url[$storeId] ?? null;
                }
            } else {
                $urls["default"] = $_ENV["SSL"] . "://" . $_ENV["FRONTEND_URL"] . $_ENV["FRONTEND_URL_PORT"] . "/" . $url;
            }
        }
        return $urls;
    }

    /**
     * @param $id
     * @return ProductGroupEntity|null
     */
    public function getProductGroupEntity($id)
    {
        if (empty($this->productGroupManager)) {
            $this->productGroupManager = $this->container->get("product_group_manager");
        }

        return $this->productGroupManager->getProductGroupById($id);
    }

    /**
     * @param ProductGroupEntity $productGroup
     * @return ProductGroupEntity|null
     */
    public function getProductGroupChildren(ProductGroupEntity $productGroup)
    {
        if (empty($this->productGroupManager)) {
            $this->productGroupManager = $this->container->get("product_group_manager");
        }

        return $this->productGroupManager->getChildProductGroups($productGroup);
    }

    /**
     * @param OrderItemEntity $orderItem
     * @return string|null
     */
    public function orderItemReturnEnabled(OrderItemEntity $orderItem)
    {
        if (empty($this->orderReturnManager)) {
            $this->orderReturnManager = $this->container->get("order_return_manager");
        }
        return $this->orderReturnManager->orderItemReturnEnabled($orderItem);
    }

    /**
     * @param OrderEntity $order
     * @return string|null
     */
    public function orderReturnEnabled(OrderEntity $order)
    {
        if (empty($this->orderReturnManager)) {
            $this->orderReturnManager = $this->container->get("order_return_manager");
        }
        return $this->orderReturnManager->orderReturnEnabled($order);
    }

    /**
     * @param $qty
     * @param $qtyStep
     * @param $item
     * @return int,
     */
    public function prepareQty($qty, $qtyStep = null, $item = null)
    {
        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }
        return $this->crmProcessManager->prepareQty($qty, $qtyStep, $item);
    }

    /**
     * @return bool
     */
    public function isUserSubscribedToNewsletter()
    {
        if (isset($_COOKIE["newsletter_subscribed"]) && $_COOKIE["newsletter_subscribed"] == 1) {
            return true;
        }

        $session = $this->container->get('session');

        /** @var AccountEntity $account */
        $account = $session->get("account");

        if (!empty($account)) {
            if (empty($this->newsletterManager)) {
                $this->newsletterManager = $this->container->get("newsletter_manager");
            }

            return $this->newsletterManager->userIsSubscribed($account->getEmail(), $session->get("current_store_id"));
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isUserLoggedIn()
    {
        $session = $this->container->get('session');

        /** @var AccountEntity $account */
        $account = $session->get("account");

        return !empty($account);
    }

    /**
     * @return string
     */
    public function generateCartDiscountCoupon($templateCode)
    {
        /** @var DiscountCouponManager $discountCouponManager */
        $discountCouponManager = $this->container->get("discount_coupon_manager");

        /** @var DiscountCouponEntity $coupon */
        $coupon = $discountCouponManager->generateCouponFromTemplate($templateCode);

        return $coupon->getCouponCode();
    }

    /**
     * @return \CrmBusinessBundle\Entity\decimal|int
     * @throws \Exception
     */
    public function getCartTotal()
    {
        /** @var QuoteManager $quoteManager */
        $quoteManager = $this->container->get("quote_manager");

        /** @var QuoteEntity $quote */
        $quote = $quoteManager->getActiveQuote(false);

        if (empty($quote)) {
            return 0;
        }

        return $quote->getPriceTotal();
    }

    /**
     * @param ProductEntity $product
     * @param $hours
     * @return mixed
     */
    public function getProductNumberOfOrdersInHours(ProductEntity $product, $hours)
    {
        /** @var ScommerceHelperManager $scommerceHelperManager */
        $scommerceHelperManager = $this->container->get("scommerce_helper_manager");

        return $scommerceHelperManager->getProductNumberOfOrdersInHours($product, $hours);
    }

    /**
     * @param $entity
     * @param $minutes
     * @return mixed
     */
    public function getNumberOfUsersOnPageInLastMinutes($entity, $minutes)
    {
        /** @var ScommerceHelperManager $scommerceHelperManager */
        $scommerceHelperManager = $this->container->get("scommerce_helper_manager");

        return $scommerceHelperManager->getNumberOfUsersOnPageInLastMinutes($entity, $minutes);
    }

    /**
     * @return mixed
     */
    public function getBankAccounts()
    {
        /** @var ScommerceHelperManager $scommerceHelperManager */
        $scommerceHelperManager = $this->container->get("scommerce_helper_manager");

        return $scommerceHelperManager->getBankAccounts();
    }

    /**
     * @return mixed
     */
    public function getAddresses()
    {
        /** @var ScommerceHelperManager $scommerceHelperManager */
        $scommerceHelperManager = $this->container->get("scommerce_helper_manager");

        return $scommerceHelperManager->getAddresses();
    }

    /**
     * @param ProductEntity $product
     * @return DiscountCouponEntity|null
     */
    public function getProductCoupon(ProductEntity $product)
    {
        if (empty($this->discountCouponManager)) {
            $this->discountCouponManager = $this->container->get("discount_coupon_manager");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("showOnProduct", "eq", 1));

        $coupons = $this->discountCouponManager->getCouponsForProduct($product, $compositeFilter);

        if (!empty($coupons)) {
            return $coupons[0]["coupon"];
        }

        return null;
    }

    /**
     * @param QuoteEntity|null $quote
     * @return array
     */
    public function getCheckoutCardCoupons(QuoteEntity $quote = null)
    {
        $ret = array();

        if (empty($quote)) {
            return $ret;
        }

        if (empty($this->discountCouponManager)) {
            $this->discountCouponManager = $this->container->get("discount_coupon_manager");
        }

        $now = new \DateTime("now");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isTemplate", "eq", 0));
        $compositeFilter->addFilter(new SearchFilter("dateValidFrom", "le", $now->format("Y-m-d H:i:s")));
        $compositeFilter->addFilter(new SearchFilter("dateValidTo", "gt", $now->format("Y-m-d H:i:s")));
        $compositeFilter->addFilter(new SearchFilter("showOnCheckout", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("allowedCards", "nn", null));

        $coupons = $this->discountCouponManager->getFilteredDiscountCoupons($compositeFilter);

        if (!EntityHelper::isCountable($coupons) || count($coupons) == 0) {
            return $ret;
        }

        $quoteItems = $quote->getQuoteItems();

        if (!EntityHelper::isCountable($quoteItems) || count($quoteItems) == 0) {
            return $ret;
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        /**
         * First check if coupon is applicable
         */
        /** @var DiscountCouponEntity $coupon */
        foreach ($coupons as $key => $coupon) {
            if (!$this->crmProcessManager->checkIfDiscountCouponCanBeApplied($quote, $coupon)) {
                unset($coupons[$key]);
            }
        }

        if (empty($coupons)) {
            return $ret;
        }

        /** @var QuoteItemEntity $quoteItem */
        foreach ($quoteItems as $quoteItem) {

            /** @var ProductEntity $parentProduct */
            $parentProduct = null;
            if (!empty($quoteItem->getParentItem())) {
                $parentProduct = $quoteItem->getParentItem()->getProduct();
            }

            /** @var DiscountCouponEntity $coupon */
            foreach ($coupons as $coupon) {
                $discountPercent = $this->crmProcessManager->getApplicableDiscountCouponPercentForProduct($coupon, $quoteItem->getProduct(), $parentProduct, $quote->getAccount(), array(), $quote->getBasePriceItemsTotal());
                if (floatval($discountPercent) > 0 && !isset($ret[$coupon->getId()])) {
                    $ret[$coupon->getId()] = array("coupon" => $coupon, "saved" => $discountPercent);
                }
            }
        }

        return $ret;
    }

    /**
     * @param ProductEntity|null $product
     * @return array
     */
    public function getProductCardCoupons(ProductEntity $product)
    {
        if (empty($this->discountCouponManager)) {
            $this->discountCouponManager = $this->container->get("discount_coupon_manager");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("showOnProduct", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("allowedCards", "nn", null));

        $coupons = $this->discountCouponManager->getCouponsForProduct($product, $compositeFilter);

        $ret = array();

        if (!empty($coupons)) {

            /** @var DiscountCouponEntity $coupon */
            foreach ($coupons as $coupon) {
                $ret[$coupon["coupon"]->getId()] = array("coupon" => $coupon["coupon"], "saved" => $coupon["discount_percent"]);
            }
        }

        return $ret;
    }

    /**
     * @param OrderItemEntity $orderItem
     * @return bool
     */
    public function orderItemComplaintEnabled(OrderItemEntity $orderItem)
    {
        if (isset($_ENV["ENABLE_ORDER_COMPLAINTS"]) && $_ENV["ENABLE_ORDER_COMPLAINTS"] == 1) {
            /** @var OrderComplaintManager $orderComplaintManager */
            $orderComplaintManager = $this->container->get("order_complaint_manager");

            $orderComplaintItem = $orderComplaintManager->getOrderComplaintItemByOrderItem($orderItem);
            if (empty($orderComplaintItem)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param OrderEntity $order
     * @return bool
     */
    public function orderComplaintEnabled(OrderEntity $order)
    {
        if (isset($_ENV["ENABLE_ORDER_COMPLAINTS"]) && $_ENV["ENABLE_ORDER_COMPLAINTS"] == 1) {
            /** @var OrderComplaintManager $orderComplaintManager */
            $orderComplaintManager = $this->container->get("order_complaint_manager");

            $orderComplaint = $orderComplaintManager->getOrderComplaintByOrder($order);
            if (empty($orderComplaint)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array
     */
    public function getActiveCoupons()
    {

        //todo dodati quote

        if (empty($this->discountCouponManager)) {
            $this->discountCouponManager = $this->container->get("discount_coupon_manager");
        }

        $now = new \DateTime();

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isTemplate", "eq", 0));
        $compositeFilter->addFilter(new SearchFilter("dateValidFrom", "le", $now->format("Y-m-d H:i:s")));
        $compositeFilter->addFilter(new SearchFilter("dateValidTo", "gt", $now->format("Y-m-d H:i:s")));

        $discountCoupons = $this->discountCouponManager->getFilteredDiscountCoupons($compositeFilter);

        return $discountCoupons;
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getProductDocuments(ProductEntity $product)
    {

        $documentsArray = $product->getProductDocuments();

        if (empty($this->productDocumentRulesManager)) {
            $this->productDocumentRulesManager = $this->container->get("product_document_rules_manager");
        }

        $additionalDocuments = $this->productDocumentRulesManager->getDocumentsForProducts(array($product->getId()));

        if (!empty($additionalDocuments)) {

            /** @var ProductDocumentEntity $additionalDocument */
            foreach ($additionalDocuments as $additionalDocument) {

                if (!empty($additionalDocument->getProductDocumentType())) {
                    $documentsArray[$additionalDocument->getProductDocumentTypeId()]["type"] = $additionalDocument->getProductDocumentType();
                    $documentsArray[$additionalDocument->getProductDocumentTypeId()]["documents"][] = $additionalDocument;
                } else {
                    $documentsArray[0]["type"] = null;
                    $documentsArray[0]["documents"][] = $additionalDocument;
                }
            }
        }

        return $documentsArray;
    }
}
