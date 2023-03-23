<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Managers\AppTemplateManager;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\FileManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\PageManager;
use CrmBusinessBundle\CalculationProviders\DefaultCalculationProvider;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\CurrencyEntity;
use CrmBusinessBundle\Entity\DeliveryPricesEntity;
use CrmBusinessBundle\Entity\DeliveryTypeEntity;
use CrmBusinessBundle\Entity\DiscountCouponEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\PaymentTypeEntity;
use CrmBusinessBundle\Entity\ProductConfigurationBundleOptionEntity;
use CrmBusinessBundle\Entity\QuoteStatusEntity;
use CrmBusinessBundle\Events\QuoteAcceptedEvent;
use CrmBusinessBundle\Events\QuoteCanceledEvent;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;
use CrmBusinessBundle\Events\QuoteSentEvent;
use CrmBusinessBundle\Events\QuoteViewedEvent;
use Doctrine\Common\Util\Inflector;
use PevexBusinessBundle\Constants\ScommerceConstants;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ScommerceBusinessBundle\Managers\DefaultScommerceManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\EventDispatcher\EventDispatcher;

class QuoteManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var RouteManager $routeManager */
    protected $routeManager;

    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;

    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var DefaultScommerceManager $defaultScommerceManager */
    protected $defaultScommerceManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;

    /** @var EntityType $quoteEntityType */
    protected $quoteEntityType;
    /** @var AppTemplateManager $templateManager */
    protected $templateManager;

    /** @var HelperManager $helperManager */
    protected $helperManager;

    /** @var ProductManager $productManager */
    protected $productManager;
    /** @var DefaultCalculationProvider $calculationProvider */
    protected $calculationProvider;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @param $entity
     */
    public function save($entity)
    {
        $this->entityManager->saveEntity($entity);
    }


    /**
     * @param array $products
     * @param QuoteEntity $quote
     * @return array
     */
    public function cloneQuoteItemsFromProducts(array $products, QuoteEntity $quote)
    {
        $quoteItems = [];
        foreach ($products as $product) {
            $quoteItem = $this->entityManager->cloneEntity($product, "quote_item", ['qty' => 1], true);
            $quoteItem->setProduct($product);
            $quoteItem->setQuote($quote);
            $this->entityManager->saveEntity($quoteItem);

            $quoteItems[] = $quoteItem;
        }

        return $quoteItems;
    }

    /**
     * @param $id
     * @return |null
     */
    public function getQuoteStatusById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(QuoteStatusEntity::class);
        return $repository->find($id);

        /*$entityType = $this->entityManager->getEntityTypeByCode("quote_status");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);*/
    }

    /**
     * @param $id
     * @return |null
     */
    public function getDeliveryTypeById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(DeliveryTypeEntity::class);
        return $repository->find($id);
        /*$entityType = $this->entityManager->getEntityTypeByCode("delivery_type");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);*/
    }

    /**
     * @param $id
     * @return |null
     */
    public function getDeliveryPriceById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(DeliveryPricesEntity::class);
        return $repository->find($id);
        /*$entityType = $this->entityManager->getEntityTypeByCode("delivery_type");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);*/
    }

    /**
     * @param $id
     * @return |null
     */
    public function getPaymentTypeById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(PaymentTypeEntity::class);
        return $repository->find($id);
        /*$entityType = $this->entityManager->getEntityTypeByCode("payment_type");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);*/
    }

    /**
     * @param $id
     * @return |null
     */
    public function getCurrencyById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(CurrencyEntity::class);
        return $repository->find($id);
        /*$entityType = $this->entityManager->getEntityTypeByCode("currency");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);*/
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getQuoteById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(QuoteEntity::class);
        return $repository->find($id);
        /*if (empty($this->quoteEntityType)) {
            $this->quoteEntityType = $this->entityManager->getEntityTypeByCode("quote");
        }

        return $this->entityManager->getEntityByEntityTypeAndId($this->quoteEntityType, $id);*/
    }

    /**
     * @param null $additionalFilter
     * @return null
     */
    public function getFilteredQuotes($additionalFilter = null)
    {

        $et = $this->entityManager->getEntityTypeByCode("quote");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalFilter)) {
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param null $additionalFilter
     * @return null
     */
    public function getFilteredQuoteItems($additionalFilter = null)
    {

        $et = $this->entityManager->getEntityTypeByCode("quote_item");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalFilter)) {
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param null $additionalFilter
     * @return null
     */
    public function getFilteredQuoteItem($additionalFilter = null)
    {

        $et = $this->entityManager->getEntityTypeByCode("quote_item");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalFilter)) {
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "desc"));

        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param null $additionalFilter
     * @return null
     */
    public function getFilteredPaymentTypes($additionalFilter = null)
    {

        $et = $this->entityManager->getEntityTypeByCode("payment_type");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalFilter)) {
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param ContactEntity $contact
     * @param $currentWebsiteId
     * @param $currentStoreId
     * @return bool|QuoteEntity|null
     * @throws \Exception
     */
    public function createEmptyAdminQuote(ContactEntity $contact, $currentWebsiteId, $currentStoreId)
    {

        $session = $this->container->get('session');
        $sessionId = $session->getId();

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $cacheItem = $this->cacheManager->getCacheGetItem("exchange_rates");
        if (!empty($cacheItem)) {
            $exchangeRates = $cacheItem->get();
        } else {
            $this->routeManager = $this->container->get("route_manager");
            /**
             * Regenerate cache for exchange rates
             */
            $exchangeRates = $this->routeManager->getCurrencies();
        }

        if (!isset($exchangeRates[$currentWebsiteId][$currentStoreId])) {
            $this->logger->error("CREATE QUOTE: missing exchange rate for website {$currentWebsiteId} and store {$currentStoreId}");
            return null;
        }

        $currency = $this->getCurrencyById($exchangeRates[$currentWebsiteId][$currentStoreId]["currency_id"]);
        $exchangeRate = $exchangeRates[$currentWebsiteId][$currentStoreId]["exchange_rate"];

        /** @var QuoteEntity $quote */
        $quote = $this->entityManager->getNewEntityByAttributSetName("quote");

        /** @var QuoteStatusEntity $quoteStatus */
        $quoteStatus = $this->getQuoteStatusById(CrmConstants::QUOTE_STATUS_NEW);

        $quote->setSessionId($sessionId);
        $quote->setQuoteStatus($quoteStatus);
        $quote->setCurrency($currency);
        $quote->setCurrencyRate($exchangeRate);
        $quote->setEnableSale(1);
        $quote->setLastContactDate(new \DateTime());

        if (empty($this->accountManager)) {
            $this->accountManager = $this->getContainer()->get("account_manager");
        }

        if (empty($contact)) {
            return false;
        }

        /** @var AccountEntity $account */
        $account = $contact->getAccount();

        if (empty($account)) {
            return false;
        }

        $quote->setAccount($account);
        $quote->setAccountEmail($account->getEmail());
        $quote->setAccountPhone($account->getPhone());
        $quote->setAccountName($account->getName());
        $quote->setAccountOib($account->getOib());
        $quote->setContact($contact);

        $this->entityManager->saveEntity($quote);
        $this->entityManager->refreshEntity($quote);

        return $quote;
    }

    /**
     * @return QuoteEntity|null
     * @throws \Exception
     */
    public function createEmptyQuote()
    {
        $session = $this->container->get('session');
        $sessionId = $session->getId();

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        /** @var SStoreEntity $store */
        $store = $this->routeManager->getStoreById($storeId);

        $websiteId = $session->get("current_website_id");
        if (empty($websiteId)) {
            $websiteId = $store->getWebsiteId();
        }

        $exchangeRates = array();

        $cacheItem = $this->cacheManager->getCacheGetItem("exchange_rates");
        if (!empty($cacheItem)) {
            $exchangeRates = $cacheItem->get();
        } else {
            $this->routeManager = $this->container->get("route_manager");
            /**
             * Regenerate cache for exchange rates
             */
            $exchangeRates = $this->routeManager->getCurrencies();
        }

        if (!isset($exchangeRates[$websiteId][$storeId])) {
            $this->logger->error("CREATE QUOTE: missing exchange rate for website {$websiteId} and store {$storeId}");
            return null;
        }

        $currency = $this->getCurrencyById($exchangeRates[$websiteId][$storeId]["currency_id"]);
        $exchangeRate = $exchangeRates[$websiteId][$storeId]["exchange_rate"];

        /** @var QuoteEntity $quote */
        $quote = $this->entityManager->getNewEntityByAttributSetName("quote");

        /** @var QuoteStatusEntity $quoteStatus */
        $quoteStatus = $this->getQuoteStatusById(CrmConstants::QUOTE_STATUS_NEW);

        $quote->setSessionId($sessionId);
        $quote->setQuoteStatus($quoteStatus);
        $quote->setCurrency($currency);
        $quote->setCurrencyRate($exchangeRate);
        $quote->setEnableSale(1);
        $quote->setLastContactDate(new \DateTime());
        $quote->setStore($store);

        if (empty($this->accountManager)) {
            $this->accountManager = $this->getContainer()->get("account_manager");
        }

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->getDefaultContact();
        if (!empty($contact)) {

            /** @var AccountEntity $account */
            $account = $contact->getAccount();

            if (!empty($account)) {
                $quote->setAccount($account);
                $quote->setAccountEmail($account->getEmail());
                $quote->setAccountPhone($account->getPhone());
                $quote->setAccountName($account->getName());
                $quote->setAccountOib($account->getOib());
                $quote->setContact($contact);
            }
        }

        $this->entityManager->saveEntity($quote);
        $this->entityManager->refreshEntity($quote);

        return $quote;
    }

    /**
     * @param QuoteEntity $quote
     * @param $data
     * @return QuoteEntity
     */
    public function updateQuote(QuoteEntity $quote, $data, $skipLog = false)
    {
        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($quote, $setter)) {
                $quote->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($quote);
        } else {
            $this->entityManager->saveEntity($quote);
        }

        $this->entityManager->refreshEntity($quote);

        return $quote;
    }

    /**
     * @param QuoteItemEntity $quoteItem
     * @param $data
     * @return QuoteEntity
     */
    public function updateQuoteItem(QuoteItemEntity $quoteItem, $data, $skipLog = false)
    {
        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($quoteItem, $setter)) {
                $quoteItem->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($quoteItem);
        } else {
            $this->entityManager->saveEntity($quoteItem);
        }

        $this->entityManager->refreshEntity($quoteItem);

        return $quoteItem;
    }

    /**
     * @param QuoteEntity $quote
     * @param QuoteStatusEntity $quoteStatus
     * @return bool
     * @throws \Exception
     */
    public function changeQuoteStatus(QuoteEntity $quote, QuoteStatusEntity $quoteStatus, $skipRecalculation = true)
    {
        if (!$skipRecalculation && !$quote->getSkipQtyCheck()) {
            $quote = $this->recalculateQuoteItems($quote);
        }

        $quote->setQuoteStatus($quoteStatus);
        $this->entityManager->saveEntityWithoutLog($quote);
        $this->entityManager->refreshEntity($quote);

        if ($quoteStatus->getId() == 2) {
            $this->dispatchQuoteCanceled($quote);
        } elseif ($quoteStatus->getId() == 3) {
            $this->dispatchQuoteAccepted($quote);
        }

        $this->entityManager->refreshEntity($quote);

        return true;
    }

    /**
     * @param QuoteEntity $quote
     * @return QuoteEntity
     */
    public function cleanQuoteCustomerData(QuoteEntity $quote)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $this->entityManager->refreshEntity($quote);

        $data = array();
        $data["account_shipping_address"] = null;
        $data["account_billing_address"] = null;
        $data["account_shipping_city"] = null;
        $data["account_shipping_street"] = null;
        $data["account_billing_city"] = null;
        $data["account_billing_street"] = null;
        $data["additional_data"] = null;
        $data["payment_type"] = null;
        $data["delivery_type"] = null;
        $data["account_oib"] = null;
        $data["account_phone"] = null;
        $data["account_email"] = null;
        $data["account_name"] = null;
        $data["account"] = null;
        $data["contact"] = null;
        $data["loyalty_card"] = null;
        $data["discount_coupon"] = null;

        $this->updateQuote($quote, $data);

        $this->recalculateQuoteItems($quote);

        return $quote;
    }

    /**
     * @param $quoteHash
     * @return \CrmBusinessBundle\Entity\Quote|QuoteEntity|null
     */
    public function getQuoteByHash($quoteHash)
    {
        if (empty($this->quoteEntityType)) {
            $this->quoteEntityType = $this->entityManager->getEntityTypeByCode("quote");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("previewHash", "eq", $quoteHash));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var QuoteEntity $quote */
        $quote = $this->entityManager->getEntityByEntityTypeAndFilter($this->quoteEntityType, $compositeFilters);

        return $quote;
    }

    /**
     * @param bool $createQuoteIfEmpty
     * @return QuoteEntity|null
     * @throws \Exception
     */
    public function getActiveQuote($createQuoteIfEmpty = true)
    {
        $session = $this->container->get('session');
        $sessionId = $session->getId();

        if (isset($_GET["coupon"]) && isset($_GET["quote"])) {
            /** @var QuoteEntity $quote */
            $quote = $this->getQuoteByHash($_GET["quote"]);

            if (!empty($quote) && $quote->getSessionId() != $sessionId) {
                $quote->setSessionId($sessionId);
                $quote->setDiscountCoupon(null);
                $this->entityManager->saveEntityWithoutLog($quote);
            }
        }

        if (empty($quote)) {
            /** @var QuoteStatusEntity $quoteStatus */
            $quoteStatus = $this->getQuoteStatusById(CrmConstants::QUOTE_STATUS_NEW);

            /** @var QuoteEntity $quote */
            $quote = $this->getQuoteBySessionId($sessionId, $quoteStatus);
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->getContainer()->get("account_manager");
        }

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->getDefaultContact();

        if (empty($quote) && $createQuoteIfEmpty) {

            $quote = $this->createEmptyQuote();

            if (!empty($contact)) {
                if (empty($this->defaultScommerceManager)) {
                    $this->defaultScommerceManager = $this->container->get("scommerce_manager");
                }

                $sessionData["account"] = $contact->getAccount();
                $sessionData["contact"] = $contact;

                $this->defaultScommerceManager->updateSessionData($sessionData);
            }
        }

        if (isset($_GET["coupon"])) {
            if (empty($this->discountCouponManager)) {
                $this->discountCouponManager = $this->container->get("discount_coupon_manager");
            }

            /** @var DiscountCouponEntity $discountCoupon */
            $discountCoupon = $this->discountCouponManager->getDiscountCouponByCode($_GET["coupon"]);

            if (!empty($discountCoupon)) {
                if (empty($this->crmProcessManager)) {
                    $this->crmProcessManager = $this->container->get("crm_process_manager");
                }
                $isValid = $this->crmProcessManager->checkIfDiscountCouponCanBeApplied($quote, $discountCoupon);
                if ($isValid) {
                    $this->crmProcessManager->applyDiscountCoupon($quote, $discountCoupon);
                }
            }
        }

        return $quote;
    }

    /**
     * @param $sessionId
     * @param QuoteStatusEntity $quoteStatus
     * @return QuoteEntity|null
     */
    public function getQuoteBySessionId($sessionId, QuoteStatusEntity $quoteStatus)
    {
        if (empty($this->quoteEntityType)) {
            $this->quoteEntityType = $this->entityManager->getEntityTypeByCode("quote");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("sessionId", "eq", $sessionId));
        if (!empty($quoteStatus)) {
            $compositeFilter->addFilter(new SearchFilter("quoteStatus", "eq", $quoteStatus->getId()));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var QuoteEntity $quote */
        $quote = $this->entityManager->getEntityByEntityTypeAndFilter($this->quoteEntityType, $compositeFilters);

        return $quote;
    }

    /**
     * @param QuoteEntity $quote
     * @return QuoteEntity
     * @throws \Exception
     */
    public function recalculateQuoteItems(QuoteEntity $quote)
    {
        $quoteItems = $quote->getQuoteItems();
        $saveArray = array();

        if(empty($this->calculationProvider)){
            $this->calculationProvider = $this->container->get($_ENV["CALCULATION_PROVIDER"]);
        }

        /** @var QuoteItemEntity $quoteItem */
        foreach ($quoteItems as $quoteItem) {
            $quoteItem = $this->calculationProvider->calculatePriceItem($quoteItem, $quote->getCurrencyRate());
            $saveArray[] = $quoteItem;
        }

        $this->entityManager->saveArrayEntities($saveArray, $this->entityManager->getEntityTypeByCode("quote_item"));

        $this->calculationProvider->recalculateQuoteTotals($quote);
        return $quote;
    }

    /**
     * @param QuoteEntity $quote
     * @param ProductEntity $product
     * @param null $parentProduct
     * @return |null
     */
    public function getQuoteItemByQuoteAndProduct(QuoteEntity $quote, ProductEntity $product, $parentProduct = null)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("quote_item");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("quote", "eq", $quote->getId()));
        $compositeFilter->addFilter(new SearchFilter("product", "eq", $product->getId()));
        if (!empty($parentProduct)) {
            $compositeFilter->addFilter(new SearchFilter("parentItem", "eq", $parentProduct->getId()));
            $compositeFilter->addFilter(new SearchFilter("parentItem", "nn", null));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getQuoteItemById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(QuoteItemEntity::class);
        return $repository->find($id);

        /*$entityType = $this->entityManager->getEntityTypeByCode("quote_item");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);*/
    }

    /**
     * @param QuoteEntity $quote
     * @param ProductEntity $product
     * @param array $childIds
     * @return null
     */
    public function getQuoteItemForBundleProduct(QuoteEntity $quote, ProductEntity $product, $childIds = array())
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT id,product_id,parent_item_id FROM quote_item_entity WHERE quote_id = {$quote->getId()} AND product_id = {$product->getId()};";
        $data = $this->databaseContext->getAll($q);

        if (empty($data)) {
            return null;
        }

        $q = "SELECT id,product_id,parent_item_id FROM quote_item_entity WHERE quote_id = {$quote->getId()} AND parent_item_id IN (" . implode(",", array_column($data, "id")) . ");";
        $data2 = $this->databaseContext->getAll($q);

        $data = array_merge($data, $data2);

        $preparedGroups = array();

        foreach ($data as $d) {
            if (empty($d["parent_item_id"])) {
                $preparedGroups[$d["id"]]["parent_product_id"] = $d["product_id"];
                if (!isset($preparedGroups[$d["id"]]["child_product_ids"])) {
                    $preparedGroups[$d["id"]]["child_product_ids"] = array();
                }
            } else {
                if (!isset($preparedGroups[$d["parent_item_id"]]["child_product_ids"])) {
                    $preparedGroups[$d["parent_item_id"]]["child_product_ids"] = array();
                }
                $preparedGroups[$d["parent_item_id"]]["child_product_ids"][] = $d["product_id"];
            }
        }

        $selectedQuoteItemId = null;
        foreach ($preparedGroups as $quoteItemId => $quoteGroupData) {
            if (count($childIds) != count($quoteGroupData["child_product_ids"])) {
                continue;
            } elseif (!array_diff($childIds, $quoteGroupData["child_product_ids"])) {
                $selectedQuoteItemId = $quoteItemId;
                break;
            }
        }

        if (!empty($selectedQuoteItemId)) {
            return $this->getQuoteItemById($selectedQuoteItemId);
        }

        return null;
    }

    /**
     * @param QuoteEntity $quote
     * @param ProductEntity $product
     * @param $options
     * @return bool|null
     */
    public function getQuoteItemForCombinedProduct(QuoteEntity $quote, ProductEntity $product, $options)
    {

        $productIds = array_filter(array_column($options, "product_id"));

        if (!empty($productIds)) {

            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->container->get("database_context");
            }

            sort($productIds);

            $q = "SELECT parent_item_id FROM (SELECT qie.parent_item_id, GROUP_CONCAT(qie.product_id ORDER BY qie.product_id ASC) AS child_products FROM quote_item_entity AS qie
            LEFT JOIN quote_item_entity as pqie ON qie.parent_item_id = pqie.id
            WHERE qie.quote_id = {$quote->getId()} AND pqie.product_id = {$product->getId()} GROUP BY qie.parent_item_id) s
            WHERE child_products = '" . implode(",", $productIds) . "';";
            $id = $this->databaseContext->getSingleEntity($q);

            if (!empty($id)) {
                return $this->getQuoteItemById($id["parent_item_id"]);
            }

        } else {
            return $this->getQuoteItemByQuoteAndProduct($quote, $product);
        }

        return null;
    }

    /**
     * @param ProductEntity $product
     * @param QuoteEntity $quote
     * @param $qty
     * @param bool $add
     * @param null $options
     * @param null $percentageDiscount
     * @return array
     * @throws \Exception
     */
    public function addUpdateProductInQuote(ProductEntity $product, QuoteEntity $quote, $qty, $add = false, $options = null)
    {
        $ret = array();
        $ret["error"] = false;
        $quoteItem = null;

        /**
         * Validate if qty is devideable by min qty step
         */
        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $session = $this->container->get("session");
        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }

        $productIsBundle = false;
        if (isset($options["bundle"]) && !empty($options["bundle"])) {
            $productIsBundle = true;
        }

        /**
         * PRODUCT_TYPE_CONFIGURABLE_BUNDLE
         */
        if ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE) {

            if (isset($options["configurable_bundle"])) {
                $options["configurable_bundle"] = json_decode($options["configurable_bundle"], true);
                foreach ($options["configurable_bundle"] as $key => $value) {
                    if (empty($value["product_id"])) {
                        unset($options["configurable_bundle"][$key]);
                    }
                }
            } else {
                $ret["message"] = $this->translator->trans("Missing product configuration");
                return $ret;
            }
            /** Validate configurable product options */
            $ret = $this->crmProcessManager->validateConfigurableProductOptions($product, $options);
            if ($ret["error"]) {
                return $ret;
            }

            /** @var QuoteItemEntity $quoteItem */
            $quoteItem = $this->getQuoteItemForCombinedProduct($quote, $product, $options["configurable_bundle"]);

            if ($qty > 0) {
                $ret = $this->crmProcessManager->validateQuoteItemQty($product, $quote, $qty, $add, $quoteItem);
                $qty = $ret["qty"];
                $add = $ret["add"];

                /** Validate  */
                if (!empty($options["configurable_bundle"])) {

                    if (empty($this->productManager)) {
                        $this->productManager = $this->container->get("product_manager");
                    }

                    foreach ($options["configurable_bundle"] as $configurableOptionId => $configurableProductData) {

                        /** @var ProductEntity $configurableProduct */
                        $configurableProduct = $this->productManager->getProductById($configurableProductData["product_id"]);

                        /** @var QuoteItemEntity $configurableQuoteItem */
                        $configurableQuoteItem = null;
                        if (!empty($quoteItem)) {
                            $configurableQuoteItem = $this->getQuoteItemByQuoteAndProduct($quote, $configurableProduct, $quoteItem);
                        }

                        /** @var ProductConfigurationBundleOptionEntity $productConfigurationBundleOption */
                        $productConfigurationBundleOption = $this->productManager->getProductConfigurationBundleOptionById($configurableOptionId);

                        $retTmp = array();
                        if ($qty > 0) {
                            $retTmp = $this->crmProcessManager->validateQuoteItemQty($configurableProduct, $quote, $qty, $add, $configurableQuoteItem);
                            if (floatval($retTmp["qty"]) < floatval($qty) || $retTmp["error"]) {
                                $qty = $retTmp["qty"];

                                if (!empty($retTmp["message"])) {
                                    $ret["message"] .= $retTmp["message"];
                                }
                                if ($ret["reload"] != $retTmp["reload"]) {
                                    $ret["reload"] = $retTmp["reload"];
                                }
                                if ($add && $add != $retTmp["add"]) {
                                    $add = $retTmp["add"];
                                }
                                /*if($retTmp["error"]){
                                    $ret["error"] = $retTmp["error"];
                                }*/
                            }
                        }

                        $options["configurable_bundle"][$configurableOptionId]["ret"] = $retTmp;
                        $options["configurable_bundle"][$configurableOptionId]["quote_item"] = $configurableQuoteItem;
                        $options["configurable_bundle"][$configurableOptionId]["product"] = $configurableProduct;
                        $options["configurable_bundle"][$configurableOptionId]["product_configuration_bundle_option"] = $productConfigurationBundleOption;
                    }
                }
            }

            if ($qty == 0 && !empty($quoteItem)) {
                $this->removeItemFromQuote($quoteItem);
            } elseif ($qty > 0) {
                if ($add && !empty($quoteItem)) {
                    $qty = $quoteItem->getQty() + floatval($qty);
                }

                $quoteItem = $this->addItemToQuote($quote, $product, $qty, $session->get("current_store_id"), null, $quoteItem);

                if (!empty($options["configurable_bundle"])) {
                    foreach ($options["configurable_bundle"] as $configurableOptionId => $configurableProductData) {
                        $this->addItemToQuote($quote, $configurableProductData["product"], $qty, $session->get("current_store_id"), $quoteItem, $configurableProductData["quote_item"], $configurableProductData["product_configuration_bundle_option"]);
                    }
                    unset($options["configurable_bundle"]);
                }
            }
        } /**
         * PRODUCT_TYPE_CONFIGURABLE
         * U product se salje parent product_id i odabrane opcije
         */
        elseif ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
            if (isset($options["configurable"])) {
                $options["configurable"] = json_decode($options["configurable"], true);
                $options["configurable"] = array_filter($options["configurable"]);
            } else {
                $ret["message"] = $this->translator->trans("Missing product options");
                return $ret;
            }

            if (empty($this->productManager)) {
                $this->productManager = $this->container->get("product_manager");
            }

            /** @var ProductEntity $simpleProduct */
            $simpleProduct = $this->productManager->getSimpleProductFromConfiguration($product, $options["configurable"]);

            if (empty($simpleProduct)) {

                if (empty($this->crmProcessManager)) {
                    $this->crmProcessManager = $this->container->get("crm_process_manager");
                }

                $this->crmProcessManager->addToCartError($_POST);

                $ret["error"] = true;
                $ret["message"] = $this->translator->trans("Please check configurable product options");
                return $ret;
            }

            /** @var QuoteItemEntity $quoteItem */
            $quoteItem = $this->getQuoteItemForCombinedProduct($quote, $product, array(array("product_id" => $simpleProduct->getId())));

            if ($qty > 0) {
                $ret = $this->crmProcessManager->validateQuoteItemQty($simpleProduct, $quote, $qty, $add, $quoteItem);
                $qty = $ret["qty"];
                $add = $ret["add"];
            }

            if ($qty == 0 && !empty($quoteItem)) {
                $this->removeItemFromQuote($quoteItem);
            } elseif ($qty > 0) {
                if ($add && !empty($quoteItem)) {
                    $qty = $quoteItem->getQty() + floatval($qty);
                }

                $quoteItem = $this->addItemToQuote($quote, $product, $qty, $session->get("current_store_id"), null, $quoteItem, null, json_encode($options["configurable"]));

                /** @var QuoteItemEntity $configurableQuoteItem */
                $simpleQuoteItem = null;
                if (!empty($quoteItem)) {
                    $simpleQuoteItem = $this->getQuoteItemByQuoteAndProduct($quote, $simpleProduct, $quoteItem);
                }

                $this->addItemToQuote($quote, $simpleProduct, $qty, $session->get("current_store_id"), $quoteItem, $simpleQuoteItem);
            }
        } /**
         * PRODUCT_TYPE_BUNDLE
         */
        elseif ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_BUNDLE && $productIsBundle) {

            if (isset($options["bundle"])) {
                $options["bundle"] = json_decode($options["bundle"], true);
                $options["bundle"] = array_filter($options["bundle"]);
            } else {
                $ret["message"] = $this->translator->trans("Missing product options");
                return $ret;
            }

            if (empty($this->productManager)) {
                $this->productManager = $this->container->get("product_manager");
            }

            /** Validate configurable product options */
            $ret = $this->crmProcessManager->validateBundleProductOptions($product, $options);
            if ($ret["error"]) {
                return $ret;
            }

            $bundleProductLinks = $ret["bundle_product_links"];

            /**
             * Prepare array to find if parent quote item with same configuration exists
             */
            $productIds = array();
            foreach ($bundleProductLinks as $productId => $productData) {
                if (!$productData["isParent"]) {
                    $productIds[] = $productId;
                }
            }

            /** @var QuoteItemEntity $quoteItem */
            $quoteItem = $this->getQuoteItemForBundleProduct($quote, $product, $productIds);

            $parentMinQty = floatval($ret["bundle_product_links"][$product->getId()]["minQty"]);

            $baseQty = floatval($qty);
            if ($add && !empty($quoteItem)) {
                $baseQty = ($quoteItem->getQty() + (floatval($baseQty) * $parentMinQty)) / $parentMinQty;
            }

            $parentTotalQty = $baseQty * $parentMinQty;

            if ($baseQty > 0) {
                $ret = $this->crmProcessManager->validateQuoteItemQty($product, $quote, $parentTotalQty, $add, $quoteItem);
                $baseQty = floor($ret["qty"] / $parentMinQty);
                $parentTotalQty = $baseQty * $parentMinQty;
                $add = $ret["add"];

                /** Validate  */
                if (!empty($options["bundle"])) {

                    if (empty($this->productManager)) {
                        $this->productManager = $this->container->get("product_manager");
                    }

                    foreach ($options["bundle"] as $childProductId) {

                        /**
                         * Da se sam bundle ne dodaje 2 puta kao quote item
                         */
                        if ($childProductId == $product->getId()) {
                            continue;
                        }

                        /** @var ProductEntity $childProduct */
                        $childProduct = $this->productManager->getProductById($childProductId);

                        $childQuoteItem = null;
                        if (!empty($quoteItem)) {
                            $compositeFilter = new CompositeFilter();
                            $compositeFilter->setConnector("and");
                            $compositeFilter->addFilter(new SearchFilter("quote", "eq", $quote->getId()));
                            $compositeFilter->addFilter(new SearchFilter("product", "eq", $childProductId));
                            $compositeFilter->addFilter(new SearchFilter("parentItem", "eq", $quoteItem->getId()));

                            /** @var QuoteItemEntity $childQuoteItem */
                            $childQuoteItem = $this->getFilteredQuoteItem($compositeFilter);
                        }

                        $qty = floatval($bundleProductLinks[$childProductId]["minQty"]) * $baseQty;

                        $retTmp = array();
                        if ($qty > 0) {
                            $retTmp = $this->crmProcessManager->validateQuoteItemQty($childProduct, $quote, $qty, $add, $childQuoteItem);

                            if (floatval($retTmp["qty"]) < floatval($qty) || $retTmp["error"]) {

                                /**
                                 * Ako je kolicina manja od trazene, treba cijeli qty smanjiti na dostupnu velicinu ili 0
                                 */
                                if ($qty > $retTmp["qty"]) {
                                    $baseQty = floor($retTmp["qty"] / $bundleProductLinks[$childProductId]["minQty"]);
                                    $parentTotalQty = $baseQty * $parentMinQty;
                                    $qty = floatval($bundleProductLinks[$childProductId]["minQty"]) * $baseQty;
                                }
                                if (!empty($retTmp["message"])) {
                                    $ret["message"] .= "Proizvodu je već dodana maksimalna količina";
                                }
                                if ($ret["reload"] != $retTmp["reload"]) {
                                    $ret["reload"] = $retTmp["reload"];
                                }
                                if ($add && $add != $retTmp["add"]) {

                                    $add = $retTmp["add"];
                                }
                                if ($retTmp["error"]) {
                                    $ret["error"] = $retTmp["error"];
                                }
                            } else {
                                $qty = floatval($bundleProductLinks[$childProductId]["minQty"]) * $baseQty;
                            }
                        }

                        $bundleProductLinks[$childProduct->getId()]["ret"] = $retTmp;
                        $bundleProductLinks[$childProduct->getId()]["quote_item"] = $childQuoteItem;
                        $bundleProductLinks[$childProduct->getId()]["product"] = $childProduct;
                        $bundleProductLinks[$childProduct->getId()]["qty"] = $qty;
                    }

                    foreach ($options["bundle"] as $childProductId) {
                        $bundleProductLinks[$childProductId]["qty"] = floatval($bundleProductLinks[$childProductId]["minQty"]) * $baseQty;
                    }
                }
            }

            if ($baseQty == 0 && !empty($quoteItem)) {
                $this->removeItemFromQuote($quoteItem);
            } elseif ($baseQty > 0) {

                $quoteItem = $this->addItemToQuote($quote, $product, $parentTotalQty, $session->get("current_store_id"), null, $quoteItem, null, null, true);

                if (!empty($options["bundle"])) {
                    foreach ($options["bundle"] as $childProductId) {

                        /**
                         * Da se sam bundle ne dodaje 2 puta kao quote item
                         */
                        if ($childProductId == $product->getId()) {
                            continue;
                        }

                        $this->addItemToQuote($quote, $bundleProductLinks[$childProductId]["product"], $bundleProductLinks[$childProductId]["qty"], $session->get("current_store_id"), $quoteItem, $bundleProductLinks[$childProductId]["quote_item"], null, null, true);
                    }
                    unset($bundleProductLinks);
                }

                $this->entityManager->refreshEntity($quoteItem);
            }
        } /** Any other product type */
        else {

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("quote", "eq", $quote->getId()));
            $compositeFilter->addFilter(new SearchFilter("product", "eq", $product->getId()));
            $compositeFilter->addFilter(new SearchFilter("parentItem", "nu", null));

            $compositeSubFilter = new CompositeFilter();
            $compositeSubFilter->setConnector("or");
            $compositeSubFilter->addFilter(new SearchFilter("isPartOfBundle", "eq", 0));
            $compositeSubFilter->addFilter(new SearchFilter("isPartOfBundle", "nu", null));
            $compositeFilter->addFilter($compositeSubFilter);

            /** @var QuoteItemEntity $quoteItem */
            $quoteItem = $this->getFilteredQuoteItem($compositeFilter);

            if ($qty > 0) {
                $ret = $this->crmProcessManager->validateQuoteItemQty($product, $quote, $qty, $add, $quoteItem);
                $qty = $ret["qty"];
                $add = $ret["add"];
            }

            if ($qty == 0 && !empty($quoteItem)) {
                $this->removeItemFromQuote($quoteItem);
            } elseif ($qty > 0) {
                if ($add && !empty($quoteItem)) {
                    $qty = $quoteItem->getQty() + floatval($qty);
                }

                $quoteItem = $this->addItemToQuote($quote, $product, $qty, $session->get("current_store_id"), null, $quoteItem);
            }
        }

        $this->entityManager->refreshEntity($quote);

        if (isset($quoteItem)) {
            $ret["quote_item"] = $quoteItem;
        }

        return $ret;
    }

    /**
     * @param QuoteEntity $quote
     * @param ProductEntity $product
     * @param $qty
     * @param $storeId
     * @param null $parentItem
     * @param QuoteItemEntity|null $quoteItem
     * @param ProductConfigurationBundleOptionEntity|null $productConfigurationBundleOption
     * @param null $configurableProductOptions
     * @param false $isPartOfBundle
     * @return QuoteItemEntity|null
     * @throws \Exception
     */
    public function addItemToQuote(QuoteEntity $quote, ProductEntity $product, $qty, $storeId, $parentItem = null, QuoteItemEntity $quoteItem = null, ProductConfigurationBundleOptionEntity $productConfigurationBundleOption = null, $configurableProductOptions = null, $isPartOfBundle = false)
    {

        if (empty($quoteItem)) {
            /** @var QuoteItemEntity $quoteItem */
            $quoteItem = $this->entityManager->getNewEntityByAttributSetName("quote_item");
        }

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }

        $quoteItem->setCode($product->getCode());
        $quoteItem->setQuote($quote);
        $quoteItem->setProduct($product);
        $quoteItem->setName($this->getPageUrlExtension->getEntityStoreAttribute($storeId, $product, "name"));
        $quoteItem->setTaxType($product->getTaxType());
        $quoteItem->setQty(floatval($qty));
        $quoteItem->setParentItem($parentItem);
        $quoteItem->setProductConfigurationBundleOption($productConfigurationBundleOption);
        $quoteItem->setConfigurableProductOptions($configurableProductOptions);
        $quoteItem->setIsGift($product->getIsGift());
        $quoteItem->setIsPartOfBundle($isPartOfBundle);

        $this->entityManager->saveEntity($quoteItem);
        $this->entityManager->refreshEntity($quoteItem);

        return $quoteItem;
    }

    /**
     * @param QuoteEntity $quote
     * @return void
     */
    public function removeAllItemsFromQuote(QuoteEntity $quote)
    {
        $items = $quote->getQuoteItems();
        if (EntityHelper::isCountable($items) && count($items)) {
            /** @var QuoteItemEntity $item */
            foreach ($items as $item) {
                $this->removeItemFromQuote($item);
            }
        }
    }

    /**
     * @param QuoteItemEntity $quoteItem
     * @return bool
     */
    public function removeItemFromQuote(QuoteItemEntity $quoteItem)
    {
        if ($quoteItem->getProduct()->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE) {
            $childItems = $quoteItem->getChildItems();
            if (EntityHelper::isCountable($childItems) && count($childItems)) {
                /** @var QuoteItemEntity $childItem */
                foreach ($childItems as $childItem) {
                    $this->entityManager->deleteEntity($childItem);
                }
            }
        }

        $this->entityManager->deleteEntity($quoteItem);

        return true;
    }

    /**
     * @param $price
     * @param $currencyRate
     * @return float|int
     */
    public function calculateBasePriceFromPrice($price, $currencyRate)
    {
        if (empty(floatval($currencyRate))) {
            $currencyRate = 1;
        }

        return $price * $currencyRate;
    }

    // DISPATCH EVENTS

    /**
     * @param $quote
     */
    public function dispatchQuoteViewed($quote)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->container->get("event_dispatcher");
        $eventDispatcher->dispatch(QuoteViewedEvent::NAME, new QuoteViewedEvent($quote));
    }

    /**
     * @param $quote
     */
    public function dispatchQuoteAccepted($quote)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->container->get("event_dispatcher");
        $eventDispatcher->dispatch(QuoteAcceptedEvent::NAME, new QuoteAcceptedEvent($quote));
    }

    /**
     * @param $quote
     */
    public function dispatchQuoteCanceled($quote)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->container->get("event_dispatcher");
        $eventDispatcher->dispatch(QuoteCanceledEvent::NAME, new QuoteCanceledEvent($quote));
    }

    /**
     * @param QuoteEntity $quote
     */
    public function dispatchQuoteSentEvent(QuoteEntity $quote)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->container->get("event_dispatcher");
        $eventDispatcher->dispatch(QuoteSentEvent::NAME, new QuoteSentEvent($quote));
    }

    /**
     * @deprecated
     * Obrisati kada budemo psiholoski spremni
     */
    /**
     * @return int
     */
    /*public function getNextIncrementId()
    {
        $currentIncrementId = 1;

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT MAX(increment_id) as increment_id FROM quote_entity;";
        $result = $this->databaseContext->getSingleEntity($q);

        if (!empty($result) && isset($result["increment_id"])) {
            $currentIncrementId = $result["increment_id"];
        }

        return intval($currentIncrementId) + 1;
    }*/

    /**
     * @param $incrementId
     * @return |null
     */
    public function getQuoteByIncrementId($incrementId)
    {
        if (empty($this->quoteEntityType)) {
            $this->quoteEntityType = $this->entityManager->getEntityTypeByCode("quote");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("incrementId", "eq", $incrementId));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "desc"));

        /** @var QuoteEntity $quote */
        return $this->entityManager->getEntityByEntityTypeAndFilter($this->quoteEntityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param QuoteEntity $quote
     * @param $description
     * @return bool|mixed
     */
    public function generateQuoteActivity(QuoteEntity $quote, $description)
    {
        /** @var  $quoteActivity */
        $quoteActivity = $this->entityManager->getNewEntityByAttributSetName("quote_activity");
        $quoteActivity->setQuote($quote);
        $quoteActivity->setDescription($description);

        $this->entityManager->saveEntityWithoutLog($quoteActivity);

        return $quoteActivity;
    }


    /**
     * PRESLOZIT -------------------------------------------
     */


    // PRESLOZIT

    /**
     * @param $quoteId
     * @param string $state
     * @return array
     */
    public function getQuotePreviewData($quoteId, $state = "")
    {
        $data = array();

        /** @var QuoteEntity $quote */
        $quote = $this->getQuoteById($quoteId);

        if (empty($quote)) {
            $data["error"] = $this->translator->trans('Invalid quote hash');
            return $data;
        }

        /** @var PageManager $pageManager */
        $pageManager = $this->container->get('page_manager');

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $data["quote"] = $quote;
        $data["buttons"] = null;
        $data["message"] = null;
        $data["quote_status"] = null;

        /**
         * If quote accepted and order exists, redirect to order
         */
        if ($quote->getQuoteStatusId() == CrmConstants::QUOTE_STATUS_ACCEPTED) {

            if (empty($this->orderManager)) {
                $this->orderManager = $this->container->get("order_manager");
            }

            /** @var OrderEntity $order */
            $order = $this->orderManager->getOrderByQuoteId($quote->getId());
            if (!empty($order)) {
                $urlArray = array('q' => $order->getPreviewHash());
                $url = $this->container->get('router')->generate('order_preview', $urlArray);

                $data = array();
                $data["redirect"] = true;
                $data["redirect_url"] = $url;
                $data["redirect_type"] = 301;

                return $data;
            }
        }

        if (!isset($state) || empty($state)) {
            $state = "viewed";
            $this->dispatchQuoteViewed($quote);
        }

        /**
         * PREPARE BUTTONS
         */
        $previewPage = $pageManager->loadPage("dashboard", "quote_preview");
        $buttons = json_decode($previewPage->getButtons());
        $tmp = $this->crmProcessManager->getQuoteButtons($quote, $buttons, $quote->getPreviewHash());
        $data["buttons"] = $tmp["buttons"];

        if (!empty($tmp["message"])) {
            $data["message"] = $tmp["message"];
        }

        $data["quote_status"] = $state;

        if ($quote->getQuoteStatusId() == CrmConstants::QUOTE_STATUS_CANCELED) {
            // If quote is canceled
            $data["message"][] = array("type" => "info", "content" => $this->translator->trans("This quote has been canceled."));
        } elseif ($quote->getQuoteStatusId() == CrmConstants::QUOTE_STATUS_ACCEPTED) {
            // If quote is accepted
            $data["message"][] = array("type" => "info", "content" => $this->translator->trans("This quote has been accepted"));
        }

        $data["quote"] = $quote;
        $data["quote_items"] = $quote->getQuoteItems();

        return $data;
    }

    /**
     * @param CurrencyEntity $currency
     * @return int
     */
    public function getExchangeRateForCurrency(CurrencyEntity $currency)
    {
        $exchange = 1;

        $exchangeRates = $this->getLastExchangeRates();

        $defaultExchangeRate = $_ENV["DEFAULT_CURRENCY_EXCHANGE"];

        foreach ($exchangeRates as $exchangeRate) {
            if ($exchangeRate["currency_from_id"] == $currency->getId()) {
                $exchange = $exchangeRate["{$defaultExchangeRate}_rate"];
                break;
            }
        }

        return $exchange;
    }

    /**
     * @return mixed[]
     */
    public function getLastExchangeRates()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM `exchange_rate_entity` WHERE exchange_rate_id = (SELECT MAX(exchange_rate_id) FROM exchange_rate_entity);";
        return $this->databaseContext->getAll($q);
    }

    /**
     * @param DeliveryTypeEntity $deliveryType
     * @param $countryId
     * @param $postalCode
     * @param $size
     * @return DeliveryPricesEntity|null
     */
    public function getDeliveryPrice(DeliveryTypeEntity $deliveryType, $countryId, $postalCode, $size)
    {
        /** @var DeliveryPricesEntity $selectedDeliveryPrice */
        $selectedDeliveryPrice = null;

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $additionalQuery = "";
        if (!empty($postalCode)) {
            $postalCode = str_replace(" ", "", $postalCode);
            if (is_numeric($postalCode)) {
                $additionalQuery = "AND dp.id NOT IN (SELECT id FROM delivery_prices_entity WHERE exclude_postal_codes LIKE '%{$postalCode}%') AND
                CASE WHEN dp.postal_code_from IS NOT NULL AND dp.postal_code_to is not NULL AND dp.postal_code_from != '' AND dp.postal_code_to != ''
                    THEN dp.postal_code_from <= {$postalCode} AND dp.postal_code_to >= {$postalCode}
                    ELSE 1
                END";
            }
        }

        $q = "SELECT dp.id FROM delivery_prices_country_link_entity as dpcl LEFT JOIN delivery_prices_entity as dp ON dpcl.delivery_prices_id = dp.id WHERE dp.delivery_id = {$deliveryType->getId()} AND dp.entity_state_id = 1 AND dpcl.country_id = {$countryId}
            AND dp.size_from <= '{$size}' AND dp.size_to > '{$size}' {$additionalQuery}";
        $deliveryPriceIds = $this->databaseContext->getAll($q);

        if (!empty($deliveryPriceIds)) {
            $deliveryPriceIds = array_column($deliveryPriceIds, "id");
            $selectedDeliveryPrice = $this->getDeliveryPriceById($deliveryPriceIds[0]);
        }

        return $selectedDeliveryPrice;
    }

    /**
     * @param QuoteEntity $quote
     * @return bool|mixed|string|null
     */
    public function generateQuoteFile(QuoteEntity $quote)
    {
        if (empty($this->templateManager)) {
            $this->templateManager = $this->container->get("app_template_manager");
        }

        /** @var SStoreEntity $store */
        $store = $quote->getStore();
        if (empty($store)) {
            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get("route_manager");
            }
            $store = $this->routeManager->getStoreById($_ENV["DEFAULT_STORE_ID"]);
        }

        $data = array(
            "quote" => $quote,
            "current_language" => $store->getCoreLanguage()->getCode(),
            "quoteWebsiteId" => $store->getWebsiteId()
        );

        $header = $this->twig->render($this->templateManager->getTemplatePathByBundle("PDF:memo_header.html.twig", $store->getWebsiteId()), array("data" => $data));
        $footer = $this->twig->render($this->templateManager->getTemplatePathByBundle("PDF:memo_footer.html.twig", $store->getWebsiteId()), array("data" => $data));
        $body = $this->twig->render($this->templateManager->getTemplatePathByBundle("PDF:quote_html.html.twig", $store->getWebsiteId()), array("data" => $data));

        /** @var FileManager $fileManager */
        $fileManager = $this->container->get("file_manager");

        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }

        $quoteName = $this->helperManager->nameToFilename($quote->getName());

        $file = $fileManager->saveFileWithPDF(
            $quoteName,
            true,
            $body,
            $header,
            $footer,
            null,
            null,
            null,
            true,
            "Portrait"
        );

        return $file;
    }

    /**
     * @param $product
     * @param $account
     * @param array $configurableProductIds
     * @return array
     */
    public function getConfigurableBundleProductPrices($product, $account, $simpleProductIds = array())
    {

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $totalPrices = $this->crmProcessManager->getProductPrices($product, $account);
        $keys = array_keys($totalPrices);
        $skipArray = array("discount_percentage", "rebate", "discount_percentage_base", "cash_percentage", "number_of_installments", "vat_type", "currency_code", "exchange_rate");

        if (!empty($simpleProductIds)) {

            if (empty($this->productManager)) {
                $this->productManager = $this->container->get("product_manager");
            }

            foreach ($simpleProductIds as $simpleProductId) {
                /** @var ProductEntity $product */
                $product = $this->productManager->getProductById($simpleProductId);

                $prices = $this->crmProcessManager->getProductPrices($product, $account);

                foreach ($keys as $v) {
                    if (in_array($v, $skipArray)) {
                        continue;
                    }
                    if (!empty($prices[$v])) {
                        $totalPrices[$v] = $totalPrices[$v] + $prices[$v];
                    } else {
                        if (!empty($totalPrices[$v]) && stripos($v, "discount_") !== false) {
                            $noDiscountKey = str_ireplace("discount_", "", $v);
                            if (isset($prices[$noDiscountKey]) && !empty($prices[$noDiscountKey])) {
                                $totalPrices[$v] = $totalPrices[$v] + $prices[$noDiscountKey];
                            }
                        }
                    }
                }
            }
        }

        return $totalPrices;
    }

    /**
     * @param QuoteItemEntity $quoteItem
     * @return array
     */
    public function prepareOptionsForQuoteItem(QuoteItemEntity $quoteItem)
    {

        $options = array();

        if ($quoteItem->getProduct()->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE) {

            $options["configurable_bundle"] = array();

            $childItems = $quoteItem->getChildItems();

            if (EntityHelper::isCountable($childItems) && count($childItems)) {
                /** @var QuoteItemEntity $childItem */
                foreach ($childItems as $childItem) {
                    $options["configurable_bundle"][$childItem->getProductConfigurationBundleOptionId()] = array("product_id" => $childItem->getProductId());
                }
            }

            $options["configurable_bundle"] = json_encode($options["configurable_bundle"]);
        } elseif ($quoteItem->getProduct()->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {

            $options["configurable"] = $quoteItem->getConfigurableProductOptions();
        }

        return $options;
    }

    /**
     * @return null|float
     */
    public function getFreeDeliveryMinimumPrice(QuoteEntity $quote = null)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (!empty($quote) && !empty($quote->getAccountShippingCity())) {

            $postalCode = $quote->getAccountShippingCity()->getPostalCode();
            $countryId = $quote->getAccountShippingCity()->getCountryId();

            $additionalQuery = "";
            if (!empty($postalCode)) {
                $postalCode = str_replace(" ", "", $postalCode);
                if (is_numeric($postalCode)) {
                    $additionalQuery = "AND dp.id NOT IN (SELECT id FROM delivery_prices_entity WHERE exclude_postal_codes LIKE '%{$postalCode}%') AND
                    CASE WHEN dp.postal_code_from IS NOT NULL AND dp.postal_code_to is not NULL AND dp.postal_code_from != '' AND dp.postal_code_to != ''
                        THEN dp.postal_code_from <= {$postalCode} AND dp.postal_code_to >= {$postalCode}
                        ELSE 1
                    END";
                }
            }

            $q = "SELECT dp.id FROM delivery_prices_country_link_entity as dpcl LEFT JOIN delivery_prices_entity as dp ON dpcl.delivery_prices_id = dp.id WHERE dp.entity_state_id = 1 AND dpcl.country_id = {$countryId}
                AND dp.price_base = 0 {$additionalQuery}";
            $deliveryPriceIds = $this->databaseContext->getAll($q);

            if (!empty($deliveryPriceIds)) {
                $deliveryPriceIds = array_column($deliveryPriceIds, "id");
                /** @var DeliveryPricesEntity $selectedDeliveryPrice */
                $selectedDeliveryPrice = $this->getDeliveryPriceById($deliveryPriceIds[0]);

                return $selectedDeliveryPrice->getSizeFrom();
            }
        }

        $q = "SELECT * FROM delivery_prices_entity WHERE entity_state_id = 1 AND price_base = 0 LIMIT 1;";
        $deliveryPrice = $this->databaseContext->getAll($q);

        if (!empty($deliveryPrice)) {
            return $deliveryPrice[0]["size_from"];
        }
        return null;
    }

    /**
     * @param QuoteEntity $quote
     * @return bool|float
     */
    public function calculateAmountToFreeDelivery(QuoteEntity $quote)
    {
        $deliveryPrice = $this->getFreeDeliveryMinimumPrice($quote);

        if (!empty($deliveryPrice)) {

            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            // Per project ability to override
            $customConditionValue = $this->crmProcessManager->calculateAmountToFreeDeliveryConditions($quote, $deliveryPrice);
            if ($customConditionValue !== null) {
                return $customConditionValue;
            }

            if ($quote->getPriceItemsTotal() >= 0) {

//                $priceTotal = floatval($quote->getPriceItemsTotal()) - floatval($quote->getDiscountLoyaltyPriceTotal()) - floatval($quote->getDiscountCouponPriceTotal());
                $priceTotal = floatval($quote->getPriceItemsTotal()) - floatval($quote->getDiscountCouponPriceTotal());

                // Ako je ukupna cijena quote-a manja od ulaznog praga za besplatnu dostavu
                if ($priceTotal < $deliveryPrice) {
                    return $deliveryPrice - $priceTotal;
                } else {
                    return 0;
                }
            }
        }

        return null;
    }

    /**
     * @param ContactEntity $contact
     * @return bool
     */
    public function updateContactOnQuotes(ContactEntity $contact)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE quote_entity AS q
            LEFT JOIN contact_entity AS c ON q.contact_id = c.id LEFT JOIN account_entity AS a ON c.account_id = a.id SET q.account_email = a.email, q.account_name = a.`name`, q.account_oib = a.oib, q.account_phone = a.phone WHERE q.contact_id = {$contact->getId()};";

        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param $data
     * @param QuoteItemEntity|null $quoteItem
     * @param false $skipLog
     * @return QuoteItemEntity|null
     */
    public function createUpdateQuoteItem($data, QuoteItemEntity $quoteItem = null, $skipLog = false)
    {
        if (empty($quoteItem)) {
            /** @var QuoteItemEntity $quoteItem */
            $quoteItem = $this->entityManager->getNewEntityByAttributSetName("quote_item");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($quoteItem, $setter)) {
                $quoteItem->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($quoteItem);
        } else {
            $this->entityManager->saveEntity($quoteItem);
        }
        $this->entityManager->refreshEntity($quoteItem);

        return $quoteItem;
    }
}
