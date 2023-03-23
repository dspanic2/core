<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\MailManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\ProductConfigurationProductLinkEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductExportRuleEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use CrmBusinessBundle\Entity\ProductImagesEntity;
use CrmBusinessBundle\Entity\ProductProductGroupLinkEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\NewsletterManager;
use CrmBusinessBundle\Managers\ProductManager;
use CrmBusinessBundle\Managers\QuoteManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\BlogCategoryEntity;
use ScommerceBusinessBundle\Entity\SRouteEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use Symfony\Component\HttpFoundation\Request;

class DefaultScommerceManager extends AbstractScommerceManager
{
    protected $dashboardRoutes;
    protected $anonymusRoutes;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var NewsletterManager $newsletterManager */
    protected $newsletterManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var ProductManager $productMananger */
    protected $productMananger;
    /** @var TemplateManager $templateManager */
    protected $templateManager;
    /** @var ElasticSearchManager $elasticSearchManager */
    protected $elasticSearchManager;

    public function initialize()
    {
        parent::initialize();

        /**
         * Get dashboard_routes
         */
        $dashboardRoutesCacheItem = $this->cacheManager->getCacheGetItem("dashboard_routes");

        if (empty($dashboardRoutesCacheItem)) {

            $this->dashboardRoutes = array();

            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get("route_manager");
            }
            if (empty($this->getPageUrlExtension)) {
                $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
            }

            $stores = $this->routeManager->getStores();

            /** @var SStoreEntity $store */
            foreach ($stores as $store) {
                $this->dashboardRoutes[] = $this->getPageUrlExtension->getPageUrl($store->getId(), 60, "s_page");
                $this->dashboardRoutes[] = $this->getPageUrlExtension->getPageUrl($store->getId(), 61, "s_page");
                $this->dashboardRoutes[] = $this->getPageUrlExtension->getPageUrl($store->getId(), 62, "s_page");
                $this->dashboardRoutes[] = $this->getPageUrlExtension->getPageUrl($store->getId(), 63, "s_page");
                $this->dashboardRoutes[] = $this->getPageUrlExtension->getPageUrl($store->getId(), 64, "s_page");
                $this->dashboardRoutes[] = $this->getPageUrlExtension->getPageUrl($store->getId(), 70, "s_page");
            }

            $this->cacheManager->setCacheItem("dashboard_routes", $this->dashboardRoutes);
        } else {
            $this->dashboardRoutes = $dashboardRoutesCacheItem->get();
        }

        /**
         * Get anonymus_routes
         */
        $anonymusRoutesCacheItem = $this->cacheManager->getCacheGetItem("anonymus_routes");

        if (empty($anonymusRoutesCacheItem)) {

            $this->anonymusRoutes = array();

            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get("route_manager");
            }
            if (empty($this->getPageUrlExtension)) {
                $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
            }

            $stores = $this->routeManager->getStores();

            /** @var SStoreEntity $store */
            foreach ($stores as $store) {
                $this->anonymusRoutes[] = $this->getPageUrlExtension->getPageUrl($store->getId(), 68, "s_page");
                $this->anonymusRoutes[] = $this->getPageUrlExtension->getPageUrl($store->getId(), 58, "s_page");
                $this->anonymusRoutes[] = $this->getPageUrlExtension->getPageUrl($store->getId(), 71, "s_page");
                $this->anonymusRoutes[] = $this->getPageUrlExtension->getPageUrl($store->getId(), 52, "s_page");
            }

            $this->cacheManager->setCacheItem("anonymus_routes", $this->anonymusRoutes);
        } else {
            $this->anonymusRoutes = $anonymusRoutesCacheItem->get();
        }
    }

    public function beforeParseUrl(Request $request)
    {

        $ret = array();
        $ret["data"] = array();

        return $ret;
    }

    /**
     * @param Request $request
     * @param SRouteEntity $route
     * @param $destination
     * @return array
     */
    public function afterParseUrl(Request $request, SRouteEntity $route, $destination)
    {

        $ret = array();
        $ret["data"] = array();

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }
        $session = $request->getSession();

        /**
         * Dashboard security
         */
        if (in_array($route->getRequestUrl(), $this->dashboardRoutes) && !is_object($this->user)) {
            $ret["redirect_type"] = 301;
            $ret["redirect_url"] = $this->getPageUrlExtension->getPageUrl($session->get("current_store_id"), 58, "s_page");
            return $ret;
        }

        /**
         * User should be anonymus to access these pages
         */
        if (in_array($route->getRequestUrl(), $this->anonymusRoutes) && is_object($this->user)) {
            $ret["redirect_type"] = 301;
            $ret["redirect_url"] = "/";
            return $ret;
        }

        /**
         * Success page redirect
         */
//        dump($session->get("order_id"));die;
//        if (in_array($destination->getTemplateType()->getCode(), array("success_page")) && !$session->get("order_id")) {
//            $ret["redirect_type"] = 301;
//            $ret["redirect_url"] = "/404";
//            return $ret;
//        }

        $ret["data"]["environment"] = $_ENV["IS_PRODUCTION"];

        if (!empty($destination) && $destination->getEntityType()->getEntityTypeCode() == "product_group") {
            /** @var ProductGroupManager $productGroupManager */
            $productGroupManager = $this->container->get("product_group_manager");
            $facetOverrides = $productGroupManager->getProductGroupFacetOverrides($destination->getId(), $request->query->all(), $route->getStore()->getId());
            if (!empty($facetOverrides)) {
                $twigBase = $this->container->get('twig');
                if (!empty($facetOverrides["facet_title"])) {
                    $twigBase->addGlobal('facet_title', $facetOverrides["facet_title"]);
                }
                if (!empty($facetOverrides["facet_meta_title"])) {
                    $twigBase->addGlobal('facet_meta_title', $facetOverrides["facet_meta_title"]);
                }
                if (!empty($facetOverrides["facet_meta_description"])) {
                    $twigBase->addGlobal('facet_meta_description', $facetOverrides["facet_meta_description"]);
                }
                if (!empty($facetOverrides["facet_canonical"])) {
                    $twigBase->addGlobal('facet_canonical', $facetOverrides["facet_canonical"]);
                }
            }
        }

        return $ret;
    }

    /**
     * @param $urlParts
     * @return mixed
     */
    public function customParseUrl($urlParts)
    {

        return $urlParts;
    }

    /**
     * @param $entity
     * @return |null
     */
    public function getBreadcrumbs($entity)
    {
        return null;
    }

    /**
     * @return string
     */
    public function getFilteredProductsCustomFilter()
    {

        $ret["join"] = "";
        $ret["where"] = "";
        if (isset($_ENV["USE_READY_FOR_WEBSHOP"]) && $_ENV["USE_READY_FOR_WEBSHOP"]) {
            $ret["where"] = " AND p.ready_for_webshop = 1 ";
        }

        return $ret;
    }

    /**
     * @param $query
     * @param int $type
     * @return string
     */
    public function getProductSearchCompositeFilterForCode($query, $type = 1)
    {
        $addonQuery = "";

        if ($type == 1) {

            if (!preg_match('#[^0-9x\-]#', $query)) {
                $addonQuery = " AND (p.ean LIKE '{$query}%' OR p.code LIKE '{$query}%' OR p.catalog_code LIKE '{$query}%') ";
                if (isset($_ENV["USE_READY_FOR_WEBSHOP"]) && $_ENV["USE_READY_FOR_WEBSHOP"]) {
                    $addonQuery .= " AND p.ready_for_webshop = 1 ";
                }
            }
        }

        return $addonQuery;
    }

    /**
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public function updateSessionData($data)
    {
        $session = $this->container->get('session');
        /** @var Request $request */
        $request = $this->container->get('request_stack')->getCurrentRequest();

        /** @var AccountEntity $account */
        $account = $data["account"];

        /** @var ContactEntity $contact */
        $contact = $data["contact"];

        $session->set('account', $account);
        $session->set('contact', $contact);

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(true);
        if (!empty($quote)) {

            if (!empty($quote->getAccount())) {
                if ($quote->getAccount()->getId() != $account->getId()) {
                    $this->logger->error("UPDATE SESSION DATA: different account on quote. Previous account: {$quote->getAccount()->getId()}, new account: {$account->getId()} on quote: {$quote->getId()}, session: {$session->getId()}");
                }
                //todo eventualno ako ovdje vidimo problem sloziti da se napravi novi cart
            }

            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get("route_manager");
            }

            $quoteData = array();
            $quoteData["account"] = $account;
            $quoteData["contact"] = $contact;
            $quoteData["account_name"] = $account->getName();
            $quoteData["account_oib"] = $account->getOib();
            $quoteData["account_phone"] = $contact->getPhone();
            $quoteData["account_email"] = $contact->getEmail();
            $quoteData["ip_address"] = $request->getClientIp();
            $quoteData["store"] = $this->routeManager->getStoreById($session->get("current_store_id"));
            $loyaltyCard = $contact->getLoyaltyCard();
            if (empty($loyaltyCard)) {
                $loyaltyCard = $contact->getAccount()->getLoyaltyCard();
            }
            $quoteData["loyalty_card"] = $loyaltyCard;

            if (empty($quote->getAccountBillingAddress())) {
                /** @var AddressEntity $billingAddress */
                $billingAddress = $account->getBillingAddress();
                if (!empty($billingAddress)) {
                    $quoteData["account_billing_address"] = $billingAddress;
                    $quoteData["account_billing_street"] = $billingAddress->getStreet();
                    $quoteData["account_billing_city"] = $billingAddress->getCity();
                }
            }

            $this->quoteManager->updateQuote($quote, $quoteData);

            $this->quoteManager->recalculateQuoteItems($quote);
        }

        return true;
    }

    /**
     * @param ContactEntity $contact
     * @return bool
     * @throws \Exception
     */
    public function removeContactFromNewsletter(ContactEntity $contact)
    {

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $contact->setNewsletterSignup(0);
        $this->entityManager->saveEntityWithoutLog($contact);
        $this->entityManager->refreshEntity($contact);

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM newsletter_entity WHERE email = '{$contact->getEmail()}';";
        $subscriptions = $this->databaseContext->getAll($q);

        if (!empty($subscriptions)) {

            if (empty($this->newsletterManager)) {
                $this->newsletterManager = $this->container->get("newsletter_manager");
            }

            $data = array();
            $data["email"] = $contact->getEmail();
            $data["active"] = 0;

            foreach ($subscriptions as $subscription) {

                $data["store"] = $subscription["store_id"];
                $this->newsletterManager->insertUpdateNewsletterSubscriber($data);
            }

            $q = "DELETE FROM newsletter_transaction_email_entity WHERE email = '{$contact->getEmail()}' AND (entity_state_id = 1 OR error = 1);";
            $this->databaseContext->executeNonQuery($q);
        }

        return true;
    }

    /**
     * @param $email
     * @return bool
     */
    public function gdprAnonymize($email)
    {

        if (empty($email)) {
            return false;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "DELETE FROM newsletter_entity WHERE email = '{$email}';";
        $this->databaseContext->executeNonQuery($q);

        $q = "DELETE FROM newsletter_transaction_email_entity WHERE email = '{$email}';";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param $entityTypeCode
     * @param $fromAttributeCode
     * @param $toAttributeCode
     * @param $storeId
     * @param $products
     * @return string
     */
    public function setDefaultSeoData($entityTypeCode, $fromAttributeCode, $toAttributeCode, $storeId, $products)
    {

        $updateQuery = array();

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        /** EXAMPLE */
        switch ($entityTypeCode) {
            case "product":
                break;
            default:
                // Code to be executed if n is different from all labels
        }

        return $updateQuery;
    }


    /**
     * @param ProductGroupEntity $destination
     * @param $storeId
     * @return array
     */
    public function validateDestination($destination, $storeId, $isMultilang = false)
    {
        $ret = array();

        $entityTypeCode = $destination->getEntityType()->getEntityTypeCode();

        $session = $this->container->get('session');

        $isAdmin = false;
        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();

        if (!empty($coreUser)) {
            $roleCodes = $coreUser->getUserRoleCodes();
            if (in_array("ROLE_COMMERCE_ADMIN", $roleCodes)) {
                $isAdmin = true;
            }
            if (!$isAdmin) {
                $frontendAdminRoles = json_decode($_ENV["FRONTEND_ADMIN_ACCOUNT_ROLES"], true);
                foreach ($frontendAdminRoles as $frontendAdminRole) {
                    if (in_array($frontendAdminRole, $roleCodes)) {
                        $isAdmin = true;
                        break;
                    }
                }
            }
        }

        if ($entityTypeCode == "product") {
            $hasParent = false;

            $additionalFilter = 1;
            if (isset($_ENV["USE_READY_FOR_WEBSHOP"]) && $_ENV["USE_READY_FOR_WEBSHOP"] == 1) {
                if (!$destination->getReadyForWebshop()) {
                    $additionalFilter = 0;
                }
            }

            if (($isAdmin && (!$destination->getIsVisible() || !$destination->getActive() || !$additionalFilter)) && $destination->getProductTypeId() == CrmConstants::PRODUCT_TYPE_SIMPLE) {
                if (empty($this->productMananger)) {
                    $this->productMananger = $this->container->get("product_manager");
                }

                $additionalCompositeFilter = new CompositeFilter();
                $additionalCompositeFilter->setConnector("and");
                $additionalCompositeFilter->addFilter(new SearchFilter("childProduct.id", "eq", $destination->getId()));

                /** @var ProductConfigurationProductLinkEntity $productConfigurationProductLinks */
                $productConfigurationProductLinks = $this->productMananger->getProductConfigurationProductLink($additionalCompositeFilter);

                if (!empty($productConfigurationProductLinks)) {
                    $hasParent = true;
                    $ret["redirect_type"] = 301;
                    // todo dodati ?configurable ta otvori pravu varijantu
                    $ret["redirect_url"] = "/" . $productConfigurationProductLinks->getProduct()->getUrlPath($storeId);
                }
            }
            if (!$isAdmin && !$hasParent && (!$destination->getIsVisible() || !$destination->getActive() || !$additionalFilter)) {
                $productGroupLinks = $destination->getProductGroups();
                if (EntityHelper::isCountable($productGroupLinks) && count($productGroupLinks) > 0) {

                    /** @var ProductGroupEntity $productGroup */
                    $productGroup = $productGroupLinks->last();
                    if (!empty($productGroup)) {
                        $ret["redirect_type"] = 301;
                        $ret["redirect_url"] = "/" . $productGroup->getUrlPath($storeId);
                    } else {
                        $ret["redirect_type"] = 404;
                    }
                } else {
                    $ret["redirect_type"] = 404;
                }
            }
        } elseif ($entityTypeCode == "s_page") {
            if (!$isAdmin && !$destination->getActive()) {
                $ret["redirect_type"] = 404;
            }
        } elseif ($entityTypeCode == "blog_category") {
            if (!$isAdmin && !$destination->getActive()) {
                $ret["redirect_type"] = 404;
            }
        } elseif ($entityTypeCode == "blog_post") {
            if (!$isAdmin && !$destination->getActive()) {
                /** @var BlogCategoryEntity $blogCategory */
                $blogCategory = $destination->getBlogCategory();
                if (!empty($blogCategory)) {
                    $ret["redirect_type"] = 301;
                    $ret["redirect_url"] = "/" . $blogCategory->getUrlPath($storeId);
                } else {
                    //$ret["redirect_type"] = 404;
                }
            }
        } elseif ($entityTypeCode == "product_group") {
            if (!$isAdmin && !$destination->getIsActive()) {
                if (!empty($destination->getProductGroup())) {
                    $ret["redirect_type"] = 301;
                    $ret["redirect_url"] = "/" . $destination->getProductGroup()->getUrlPath($storeId);
                } else {
                    $ret["redirect_type"] = 404;
                }
            }
        } elseif ($entityTypeCode == "warehouse") {
            if (!$isAdmin && !$destination->getIsActive()) {
                $ret["redirect_type"] = 404;
            }
        } elseif ($entityTypeCode == "brand") {
            if (!$isAdmin && (!$destination->getIsActive())) {
                $ret["redirect_type"] = 404;
            }
        }

        if ($isMultilang && isset($ret["redirect_url"])) {
            $ret["redirect_url"] = "/" . $session->get("current_language") . $ret["redirect_url"];
        }

        return $ret;
    }

    /**
     * @param array $ids
     * @return array
     */
    public function assignParentGroupsForProducts($ids = [])
    {
        if(empty($ids)){
            if(empty($this->databaseContext)){
                $this->databaseContext = $this->container->get("database_context");
            }

            $q = "SELECT id FROM product_entity AS p WHERE p.entity_state_id = 1 AND p.active = 1 and
                p.id not in (SELECT DISTINCT(ppgl.product_id) FROM product_product_group_link_entity as ppgl LEFT JOIN product_group_entity as pg on ppgl.product_group_id = pg.id WHERE pg.level = 1)
                and p.id in (SELECT DISTINCT(ppgl.product_id) FROM product_product_group_link_entity as ppgl);";
            $data = $this->databaseContext->getAll($q);

            if(!empty($data)){
                $ids = array_column($data,"id");
            }
        }

        if(empty($ids)){
            return null;
        }

        $entityType = $this->entityManager->getEntityTypeByCode("product");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",", $ids)));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param ProductEntity $entity
     * @param $tmp
     * @param $settings
     * @return mixed
     */
    public function apiProductExtension(ProductEntity $entity, $tmp, $settings)
    {
        return $tmp;
    }

    /**
     * @param ProductEntity $product
     * @param array $data
     * @return array
     */
    public function replaceProductDetails(ProductEntity $product, $data = [])
    {
        if (empty($this->productMananger)) {
            $this->productMananger = $this->container->get("product_manager");
        }
        if (empty($this->templateManager)) {
            $this->templateManager = $this->container->get("template_manager");
        }

        /**
         * PRODUCT_TYPE_CONFIGURABLE
         */
        if ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
            /** @var ProductEntity $childProduct */
            $childProduct = $this->productMananger->getSimpleProductFromConfiguration($product, $data["configurable"] ?? []);
            if (empty($childProduct)) {
                return array(
                    'error' => true,
                    'message' => $this->translator->trans("Product not found")
                );
            }

            $titleHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product/ProductPieces:title.html.twig"), ['product' => $childProduct]);
            $pricesHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product/ProductPieces:prices.html.twig"), ['product' => $childProduct, 'data' => $data]);
            if (!empty($childProduct->getSelectedImage())) {
                $galleryHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product/ProductPieces:gallery.html.twig"), ['product' => $childProduct]);
            } else {
                $galleryHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product/ProductPieces:gallery.html.twig"), ['product' => $product, 'loadRealProduct' => false]);
            }
            $cartFormHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product/ProductPieces:add_to_cart_form.html.twig"), ['product' => $product, 'child' => $childProduct]);
            $availabilityHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product/ProductPieces:availability.html.twig"), ['product' => $childProduct]);

            $javascript = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Tracking:product_details.html.twig"), ['product' => $childProduct]);

            return array(
                'error' => false,
                'javascript' => $javascript,
                'gallery_html' => $galleryHtml,
                'prices_html' => $pricesHtml,
                'title_html' => $titleHtml,
                'cart_form_html' => $cartFormHtml,
                'availability_html' => $availabilityHtml,
                'pid' => $childProduct->getId()
            );
        } /**
         * PRODUCT_TYPE_CONFIGURABLE_BUNDLE
         */
        elseif ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE) {
            $pricesHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product/ProductPieces:prices.html.twig"), ['product' => $product, 'data' => $data]);
            return array(
                'error' => false,
                'prices_html' => $pricesHtml
            );
        } /**
         * OTHER
         */
        else {
            $pricesHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product/ProductPieces:prices.html.twig"), ['product' => $product, 'data' => $data]);
            return array(
                'error' => false,
                'prices_html' => $pricesHtml
            );
        }
    }

    /**
     * @param $entityTypeCode
     * @param $storeId
     * @param $columns
     * @param null $additionalFilter
     * @return array
     * @throws \Exception
     */
    public function getElasticDataForReindex($entityTypeCode,$storeId,$columns,$additionalFilter = null){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        switch ($entityTypeCode) {
            case "product":
                $where = "";
                if(!empty($additionalFilter)){
                    $where = " WHERE {$additionalFilter} ";
                }
                $select = Array();
                foreach ($columns as $column => $config){
                    if(isset($config["select"])){
                        $select[]=$config["select"];
                    }
                    else{
                        $select[]="p.".$column;
                    }
                }

                $attributesQuery = "";

                $q = "SELECT count(*) as count FROM s_product_attribute_configuration_entity WHERE entity_state_id = 1 and is_active = 1 and use_in_search = 1;";
                $numberOfAttributes = intval($this->databaseContext->getSingleResult($q));

                if($numberOfAttributes){
                    $select[] = "a.attributes";
                    $attributesQuery = "LEFT JOIN (
                        SELECT
                            GROUP_CONCAT(
                                CONCAT(
                                    c.filter_key,
                                    '###',
                                    IF (
                                        LOWER(s.attribute_value) = 'ne' OR LOWER(s.attribute_value) = 'da' OR s.attribute_value = '0' OR s.attribute_value = '1',
                                        '',
                                        CONCAT(
                                            '',
                                            IF (
                                                s.prefix IS NOT NULL,
                                                CONCAT(s.prefix, ' '),
                                                ''
                                            ),
                                            s.attribute_value,
                                            IF (
                                                s.sufix IS NOT NULL,
                                                CONCAT(' ', s.sufix),
                                                ''
                                            )
                                        )
                                    )
                                ) SEPARATOR '####'
                            ) AS attributes,
                            s.product_id
                        FROM s_product_attributes_link_entity s
                        JOIN s_product_attribute_configuration_entity c ON s.s_product_attribute_configuration_id = c.id
                        WHERE c.use_in_search = 1
                        AND c.entity_state_id = 1
                        AND c.is_active = 1
                        AND s.entity_state_id = 1
                        GROUP BY s.product_id
                    ) a ON p.id = a.product_id";
                }

                $select = implode(",",$select);

                $q = "SELECT {$select}, GROUP_CONCAT(ppg.product_group_id) as product_group_ids, (SELECT SUM(qty) FROM shape_track_order_item_fact WHERE product_id = p.id GROUP BY product_id) as sold FROM {$entityTypeCode}_entity as p LEFT JOIN product_product_group_link_entity AS ppg on p.id = ppg.product_id {$attributesQuery} {$where} GROUP BY p.id ;";
                break;
            default:
                $where = "";
                if(!empty($additionalFilter)){
                    $where = " WHERE {$additionalFilter} ";
                }
                $select = Array();
                foreach ($columns as $column => $config){
                    if(isset($config["select"])){
                        $select[]=$config["select"];
                    }
                    else{
                        $select[]=$column;
                    }
                }
                $select = implode(",",$select);
                $q = "SELECT {$select} FROM {$entityTypeCode}_entity {$where};";
                break;
        }

        $data = $this->databaseContext->getAll($q);

        switch ($entityTypeCode) {
            case "product":

                if($numberOfAttributes){
                    $tmp = $data;

                    foreach ($tmp as $key => $t){
                        $data[$key] = $t;
                        if(isset($t["attributes"]) && !empty($t["attributes"])){
                            $attributes = explode("####",$t["attributes"]);
                            foreach ($attributes as $attribute){
                                $attribute = explode("###",$attribute);
                                if(isset($attribute[0]) && !empty($attribute[0]) && isset($attribute[1]) && !empty($attribute[1])){
                                    $data[$key]["s_product_attribute_configuration.".$attribute[0]] = $attribute[1];
                                }
                            }
                        }
                        unset($data[$key]["attributes"]);
                    }

                    unset($tmp);
                }
                foreach ($data as $key => $d){
                    if(empty($d["sold"])){
                        $data[$key]["sold"] = 0;
                    }
                    if(!empty($d["product_group_ids"])){
                        $data[$key]["product_group_ids"] = explode(",",$d["product_group_ids"]);
                    }

                }
                break;
            default:
                break;
        }

        return $data;
    }

    /**
     * @param $entityTypeCode
     * @param $mappings
     * @return mixed
     */
    public function getElasticCustomMappingsForEntityType($entityTypeCode,$mappings){

        //ako je potreban kakav custom mapping po entity tipu
        //paziti da ne bude duplih

        if($entityTypeCode == "product"){

            if(empty($this->databaseContext)){
                $this->databaseContext = $this->container->get("database_context");
            }

            $q = "SELECT * FROM s_product_attribute_configuration_entity WHERE entity_state_id = 1 and is_active = 1 and use_in_search = 1;";
            $data = $this->databaseContext->getAll($q);

            if(!empty($data)){
                foreach ($data as $d){
                    $mappings["s_product_attribute_configuration.".$d["filter_key"]] = Array(
                        "search_analyzer" => "standard",
                        "type" => "text",
                        "analyzer" => "autocomplete"
                    );
                }
            }

            $mappings["product_group_ids"] = Array(
                "type" => "integer",
            );
            $mappings["sold"] = Array(
                "type" => "integer",
            );
        }


        return $mappings;
    }

    /**
     * @param $entityTypeCode
     * @return array
     */
    public function getElasticSettingsForEntityType($entityTypeCode){

        //ako je potrebno, moze se overrideati i vratit settings za odredjeni entity type

        $defaultSettings = [
            'number_of_shards' => 1,
            'number_of_replicas' => 1,
            "analysis" => [
                "analyzer" => [
                    "autocomplete" => [
                        "tokenizer" => "autocomplete",
                        "filter" => ["lowercase"]
                    ]
                ],
                "tokenizer" => [
                    "autocomplete" => [
                        "type" => "edge_ngram",
                        "min_gram" => 2,
                        "max_gram" => 20,
                        "token_chars" => [
                            "letter",
                            "digit"
                        ]
                    ]
                ]
            ]
        ];

        return $defaultSettings;
    }

    /**
     * @param $term
     * @param $storeId
     * @return array|int[]|string[]
     * @throws \Exception
     */
    public function elasticSearchBlogPosts($term, $storeId){

        if (!$_ENV["USE_ELASTIC"]) {
            throw new \Exception("Elastic not in use");
        }

        if(empty($term)){
            return array();
        }

        if(!isset($_ENV["BLOG_POST_COLUMNS"])){
            throw new \Exception("Index does not exist");
        }

        $this->indexDefinition = Array();
        $this->indexDefinition["index"] = strtolower($_ENV["INDEX_PREFIX"])."_blog_post_".$storeId;

        $term = StringHelper::removeStopwords($term);

        if(empty($term)){
            return array();
        }

        if(empty($this->elasticSearchManager)){
            $this->elasticSearchManager = $this->container->get("elastic_search_manager");
        }

        $blogPostIds = Array();

        /**
         * Define base query and base filter
         */
        $baseDefaultFilter = $defaultFilter = [
               [
                  "bool" => [
                     "must" => [
                        [
                           "match" => [
                              "entity_state_id" => 1
                           ]
                        ]
                     ]
                  ]
               ],
               [
                 "bool" => [
                    "must" => [
                       [
                          "match" => [
                             "active" => 1
                          ]
                       ]
                    ]
                 ]
              ]
        ];

        $baseQuery = array_merge(
                $this->indexDefinition,
                ['body' => [
                   "query" => [
                         "bool" => [
                            "should" => [],
                        "filter" => [
                            [
                                "bool" => [
                                    "must" => $baseDefaultFilter
                                 ]
                            ]
                        ]
                    ]
              ],
            "size" => 20
            ]]
        );

        /**
         * Columns to search
         */
        $columnsToSearch[] = Array("name","content");

        $terms = explode(" ",$term);
        foreach ($columnsToSearch as $key => $columns){
            foreach ($columns as $column){
                $q = Array();
                foreach ($terms as $t){
                    $q[] = [
                        "bool" => [
                          "must" => [
                             [
                                "fuzzy" => [
                                   $column => $t
                                ]
                             ]
                          ]
                       ]
                    ];
                }
                $queryPart[] = [
                    "bool" => [
                        "should" => $q
                    ]
                ];
            }
        }

        /**
         * Put in columns to search
         */
        $baseQuery["body"]["query"]["bool"]["should"] = $queryPart;

        /**
         * Execute searches
         */

        $query = $baseQuery;

        $data = $this->elasticSearchManager->getSearchResults($term,$storeId,"blog_post",$query);

        if(!empty($data)){
            foreach ($data as $d){
                if($d["value"]["_score"] > $_ENV["MIN_SCORE_BLOG_POST"]){
                    $blogPostIds[$d["value"]["_source"]["id"]] = $d;
                }
            }
        }

        /**
         * Return blog_post ids
         */
        if(!empty($blogPostIds)){
            $blogPostIds = array_keys($blogPostIds);
        }

        return $blogPostIds;
    }

    /**
     * @param $term
     * @param $storeId
     * @return array|void
     * @throws \Exception
     */
    public function elasticSearchBrands($term, $storeId){

        if (!$_ENV["USE_ELASTIC"]) {
            throw new \Exception("Elastic not in use");
        }

        if(empty($term)){
            return array();
        }

        if(!isset($_ENV["BRAND_COLUMNS"])){
            throw new \Exception("Index does not exist");
        }

        $term = StringHelper::removeStopwords($term);

        if(empty($term)){
            return array();
        }

        if(empty($this->elasticSearchManager)){
            $this->elasticSearchManager = $this->container->get("elastic_search_manager");
        }

        $terms = explode(" ",$term);

        /**
         * Search for brands in string
         */
        $brandIds = Array();
        foreach ($terms as $t){
            if(strlen($t) < 2){
                continue;
            }
            $data = $this->elasticSearchManager->getSearchResults($t,$storeId,"brand");

            if(!empty($data)){
                foreach ($data as $d){
                    if($d["value"]["_score"] > $_ENV["MIN_SCORE_BRAND"]){
                        $brandIds[$d["value"]["_source"]["id"]] = $d;
                    }
                }
            }
        }

        if(!empty($brandIds)){
            $brandIds = array_keys($brandIds);
        }

        return $brandIds;
    }


    /**
     * @param $term
     * @param $storeId
     * @return array|void
     * @throws \Exception
     */
    public function elasticSearchProductGroups($term, $storeId){

        if (!$_ENV["USE_ELASTIC"]) {
            throw new \Exception("Elastic not in use");
        }

        if(empty($term)){
            return array();
        }

        if(!isset($_ENV["PRODUCT_GROUP_COLUMNS"])){
            throw new \Exception("Index does not exist");
        }

        $term = StringHelper::removeStopwords($term);

        if(empty($term)){
            return array();
        }

        if(empty($this->elasticSearchManager)){
            $this->elasticSearchManager = $this->container->get("elastic_search_manager");
        }

        //$terms = explode(" ",$term);

        /**
         * Search for product group ids
         */
        $productGroupIds = Array();
        $data = $this->elasticSearchManager->getSearchResults($term,$storeId,"product_group");
        if(!empty($data)){
            foreach ($data as $d){
                if(floatval($d["value"]["_score"]) >= $_ENV["MIN_SCORE_PRODUCT_GROUP"]){
                    $productGroupIds[$d["value"]["_source"]["id"]] = $d;
                }
            }
        }

        if(!empty($productGroupIds)){
            $productGroupIds = array_keys($productGroupIds);
        }

        return $productGroupIds;
    }

    /**
     * @param $term
     * @param $storeId
     * @return int[]|void
     * @throws \Exception
     */
    public function elasticSearchProducts($term, $storeId)
    {
        if (!$_ENV["USE_ELASTIC"]) {
            throw new \Exception("Elastic not in use");
        }

        if(empty($term)){
            return array();
        }

        if(!isset($_ENV["PRODUCT_COLUMNS"])){
            throw new \Exception("Index does not exist");
        }

        $this->indexDefinition = Array();
        $this->indexDefinition["index"] = strtolower($_ENV["INDEX_PREFIX"])."_product_".$storeId;

        //TODO dohvatiti store id i language iz njega

        $originalTerm = $term;
        $term = StringHelper::removeStopwords($term);

        if(empty($term)){
            return array();
        }

        if(empty($this->elasticSearchManager)){
            $this->elasticSearchManager = $this->container->get("elastic_search_manager");
        }

        $terms = explode(" ",$term);

        /**
         * Search for brands in string
         */
        $brandIds = Array();
        foreach ($terms as $t){
            if(strlen($t) < 2){
                continue;
            }
            $data = $this->elasticSearchManager->getSearchResults($t,$storeId,"brand");

            $found = false;
            if(!empty($data)){
                foreach ($data as $d){
                    if($d["value"]["_score"] > $_ENV["MIN_SCORE_BRAND"]){
                        $found = true;
                        $brandIds[$d["value"]["_source"]["id"]] = $d;
                    }
                }
            }

            /*if($found){
                $term = StringHelper::removeWordFromString($t,$term);
            }*/
        }

        /**
         * Search for product group ids
         */
        $productGroupIds = Array();
        $data = $this->elasticSearchManager->getSearchResults($term,$storeId,"product_group");
        $found = false;
        if(!empty($data)){
            foreach ($data as $d){
                if(floatval($d["value"]["_score"]) >= $_ENV["MIN_SCORE_PRODUCT_GROUP"]){
                    $found = true;
                    $productGroupIds[$d["value"]["_source"]["id"]] = $d;
                }
            }
        }

        if ($_ENV["USE_ALGOLIA"] ?? 0) {
            if(empty($productGroupIds)){
                return Array();
            }
        }

        /**
         * Define base query and base filter
         */
        $baseDefaultFilter = $defaultFilter = [
               [
                  "bool" => [
                     "must" => [
                        [
                           "match" => [
                              "entity_state_id" => 1
                           ]
                        ]
                     ]
                  ]
               ],
               [
                 "bool" => [
                    "must" => [
                       [
                          "match" => [
                             "active" => 1
                          ]
                       ]
                    ]
                 ]
              ],
                [
                 "bool" => [
                    "must" => [
                       [
                          "match" => [
                             "ready_for_webshop" => 1
                          ]
                       ]
                    ]
                 ]
              ]
        ];

        $sort = [
            "_score",
             [
             "is_saleable" => [
                 "order" => "desc"
              ]],
             [
             "sold" => [
                 "order" => "desc"
              ]]
        ];

        $baseQuery = array_merge(
                $this->indexDefinition,
                ['body' => [
                   "query" => [
                         "bool" => [
                            "should" => [],
                        "filter" => [
                            [
                                "bool" => [
                                    "must" => $baseDefaultFilter
                                 ]
                            ]
                        ]
                    ]
              ],
            "size" => 1000,
            "sort" => $sort
            ]]
        );

        $productIds = Array();
        $brandsIdsFilter = Array();
        $productGroupIdsFilter = Array();

        if(!empty($brandIds)){
            $brandIds = array_keys($brandIds);

            foreach ($brandIds as $brandId){
                $brandsIdsFilter[] = [
                  "bool" => [
                     "must" => [
                        [
                           "match" => [
                              "brand_id" => $brandId
                           ]
                        ]
                     ]
                  ]
               ];
            }

            $brandsIdsFilter = [
                  "bool" => [
                     "should" => $brandsIdsFilter
                  ]
            ];
        }

        if(!empty($productGroupIds)){
            $productGroupIds = array_keys($productGroupIds);

            foreach ($productGroupIds as $productGroupId){

                $productGroupIdsFilter[] = [
                  "bool" => [
                     "must" => [
                        [
                           "match" => [
                              "product_group_ids" => $productGroupId
                           ]
                        ]
                     ]
                  ]
               ];
            }

            $productGroupIdsFilter = [
                  "bool" => [
                     "should" => $productGroupIdsFilter
                  ]
            ];
        }

        /**
         * Columns to search
         */
        $columnsToSearch[] = Array("name","description","meta_description");

        $terms = explode(" ",$term);
        foreach ($columnsToSearch as $key => $columns){
            foreach ($columns as $column){
                $q = Array();
                foreach ($terms as $t){
                    $q[] = [
                        "bool" => [
                          "must" => [
                             [
                                "fuzzy" => [
                                   $column => $t
                                ]
                             ]
                          ]
                       ]
                    ];
                }
                $queryPart[] = [
                    "bool" => [
                        "should" => $q
                    ]
                ];
            }
        }

        /**
         * Put in columns to search
         */
        $baseQuery["body"]["query"]["bool"]["should"] = $queryPart;

        /**
         * Execute searches
         */

        /**
         * First we add brand and product group filter
         */
        if(!empty($brandsIdsFilter) && !empty($productGroupIdsFilter)){

            $query = $baseQuery;

            $query["body"]["query"]["bool"]["filter"][0]["bool"]["must"][] = $brandsIdsFilter;
            $query["body"]["query"]["bool"]["filter"][0]["bool"]["must"][] = $productGroupIdsFilter;

            $data = $this->elasticSearchManager->getSearchResults($term,$storeId,"product",$query);

            if(!empty($data)){
                foreach ($data as $d){
                    if($d["value"]["_score"] > $_ENV["MIN_SCORE_PRODUCT"]){
                        $productIds[$d["value"]["_source"]["id"]] = $d;
                    }
                }
            }
        }

        /**
         * Try only with product group filter
         */
        if(empty($productIds) && !empty($productGroupIdsFilter)){

            $query = $baseQuery;

            $query["body"]["query"]["bool"]["filter"][0]["bool"]["must"][] = $productGroupIdsFilter;

            $data = $this->elasticSearchManager->getSearchResults($term,$storeId,"product",$query);

            if(!empty($data)){
                foreach ($data as $d){
                    if($d["value"]["_score"] > $_ENV["MIN_SCORE_PRODUCT"]){
                        $productIds[$d["value"]["_source"]["id"]] = $d;
                    }
                }
            }
        }

        /**
         * Search only products
         */
        if(empty($productIds)){

            $query = $baseQuery;

            $data = $this->elasticSearchManager->getSearchResults($term,$storeId,"product",$query);

            if(!empty($data)){
                foreach ($data as $d){
                    if($d["value"]["_score"] > $_ENV["MIN_SCORE_PRODUCT"]){
                        $productIds[$d["value"]["_source"]["id"]] = $d;
                    }
                }
            }
        }

        /**
         * Return product ids
         */
        if(!empty($productIds)){
            $productIds = array_keys($productIds);
        }

        return $productIds;
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function getAlgoliaProductsArray($storeId)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $attributesQuery = "";
        $attributesSelector = "";
        $brandSelector = " JSON_UNQUOTE(JSON_EXTRACT(b.name, '$.\"{$storeId}\"')) AS brand, ";
        $additionalJoin = " LEFT JOIN brand_entity as b on p.brand_id = b.id ";

        if ($_ENV["ALGOLIA_ENABLE_ATTRIBUTES"] ?? 0) {
            $attributesSelector = "a.attributes,";
            $attributesQuery = "LEFT JOIN (
                SELECT
                    GROUP_CONCAT(
                        CONCAT(
                            IF (
                                LOWER(s.attribute_value) = 'ne' OR s.attribute_value = '0',
                                NULL,
                                c.name
                            ),
                            IF (
                                LOWER(s.attribute_value) = 'ne' OR LOWER(s.attribute_value) = 'da' OR s.attribute_value = '0' OR s.attribute_value = '1',
                                '',
                                CONCAT(
                                    ' ',
                                    IF (
                                        s.prefix IS NOT NULL,
                                        CONCAT(s.prefix, ' '),
                                        ''
                                    ),
                                    s.attribute_value,
                                    IF (
                                        s.sufix IS NOT NULL,
                                        CONCAT(' ', s.sufix),
                                        ''
                                    )
                                )
                            )
                        ) SEPARATOR ', '
                    ) AS attributes,
                    s.product_id
                FROM s_product_attributes_link_entity s
                JOIN s_product_attribute_configuration_entity c ON s.s_product_attribute_configuration_id = c.id
                WHERE c.show_in_filter = 1
                AND c.entity_state_id = 1
                AND c.is_active = 1
                AND s.entity_state_id = 1
                GROUP BY s.product_id
            ) a ON p.id = a.product_id";
        }

        $additionaFilter = "";
        if (!empty($_ENV["ALGOLIA_ADDITIONAL_FILTER"])) {
            $additionaFilter = $_ENV["ALGOLIA_ADDITIONAL_FILTER"];
        }

        $readyForWebshopFilter = "";
        if (isset($_ENV["USE_READY_FOR_WEBSHOP"]) && $_ENV["USE_READY_FOR_WEBSHOP"]) {
            if (stripos($additionaFilter, "ready_for_webshop") === false) {
                $readyForWebshopFilter = " AND p.ready_for_webshop = 1 ";
            }
        }

        $additionaFields = "";
        if (!empty(trim($_ENV["ALGOLIA_ADDITIONAL_FIELDS"]))) {
            $additionaFields = $_ENV["ALGOLIA_ADDITIONAL_FIELDS"];
        }

        $q = "SELECT
                p.id,
                JSON_UNQUOTE(JSON_EXTRACT(p.name, '$.\"{$storeId}\"')) AS name,
                JSON_UNQUOTE(JSON_EXTRACT(p.meta_title, '$.\"{$storeId}\"')) AS meta_title,
                JSON_UNQUOTE(JSON_EXTRACT(p.meta_description, '$.\"{$storeId}\"')) AS meta_description,
                JSON_UNQUOTE(JSON_EXTRACT(p.description, '$.\"{$storeId}\"')) AS description,
                JSON_UNQUOTE(JSON_EXTRACT(p.short_description, '$.\"{$storeId}\"')) AS short_description,
                p.ean,
                p.code,
                p.catalog_code,
                p.remote_id,
                p.ord,
                {$additionaFields}
                {$attributesSelector}
                {$brandSelector}
                {$storeId} AS store_id
            FROM product_entity p
            {$attributesQuery}
            {$additionalJoin}
            WHERE p.active = 1
            {$additionaFilter}
            {$readyForWebshopFilter}
            AND p.content_changed = 1
            AND p.entity_state_id = 1;";

        $data = $this->databaseContext->getAll($q);

        return $data;
    }

    /**
     * @param ProductEntity $product
     */
    public function getProductDeliveryDate(ProductEntity $product)
    {

        return null;
    }

    /**
     * @param array $ret
     * @return array
     */
    public function additionalProductListControllerReturnData($ret)
    {
        return [];
    }

    /**
     * @param $destinationTypes
     * @return mixed
     */
    public function filterCustomDestinationTypes($destinationTypes)
    {

        return $destinationTypes;
    }
}
