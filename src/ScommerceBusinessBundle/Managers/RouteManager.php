<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\PagingFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\ApplicationSettingsManager;
use AppBundle\Models\UpdateModel;
use CrmBusinessBundle\Abstracts\AbstractImportManager;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Managers\DefaultImportManager;
use CrmBusinessBundle\Managers\QuoteManager;
use ImageOptimizationBusinessBundle\Managers\ImageStyleManager;
use IntegrationBusinessBundle\Managers\DefaultIntegrationImportManager;
use IntegrationBusinessBundle\Managers\GoogleApiManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Constants\ScommerceConstants;
use ScommerceBusinessBundle\Entity\SPageEntity;
use ScommerceBusinessBundle\Entity\SRedirectTypeEntity;
use ScommerceBusinessBundle\Entity\SRouteEntity;
use ScommerceBusinessBundle\Entity\SRouteNotFoundEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Entity\SWebsiteEntity;
use Symfony\Component\HttpFoundation\Request;

class RouteManager extends AbstractScommerceManager
{
    /** @var DefaultScommerceManager $defaultScommerceManager */
    protected $defaultScommerceManager;
    /** @var ImageStyleManager $imageStyleManager */
    protected $imageStyleManager;
    protected $languages;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var TrackingManager $trackingManager */
    protected $trackingManager;
    /** @var ScommerceHelperManager $sCommerceHelperManager */
    protected $sCommerceHelperManager;
    /** @var DefaultIntegrationImportManager $importManager */
    protected $importManager;
    /** @var GoogleApiManager $googleApiManager */
    protected $googleApiManager;
    /** @var ApplicationSettingsManager $applicationSettingsManager */
    protected $applicationSettingsManager;

    private $destinationTypes;

    public function initialize()
    {
        parent::initialize();
        $this->languages = $this->getLanguages();
    }

    /**
     * @param Request $request
     * @param null $urlParts
     * @return array|null
     * @throws \Exception
     */
    public function prepareRoute(Request $request, $urlParts = null)
    {
        $ret = array();
        $ret["data"] = array();

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manger");
        }

        $clientIp = $_SERVER["HTTP_X_REAL_IP"] ?? null;
        if (empty($clientIp)) {
            $clientIp = $request->getClientIp();
        }

        if (!empty($clientIp)) {

            if (empty($this->sCommerceHelperManager)) {
                $this->sCommerceHelperManager = $this->container->get("scommerce_helper_manager");
            }

            if ($this->sCommerceHelperManager->checkIfIpInBlockedIps($clientIp)) {
                $ret["redirect_type"] = 301;
                $ret["redirect_url"] = "http://hackertyper.com/";

                return $ret;
            }
        }

        if (empty($this->defaultScommerceManager)) {
            $this->defaultScommerceManager = $this->container->get("scommerce_manager");
        }

        $query = null;
        if (empty($urlParts)) {
            $requestUri = $request->getSchemeAndHttpHost() . $request->getRequestUri();
            // Remove port
            $urlParts = parse_url($requestUri);
            if (isset($urlParts["query"])) {
                $query = $urlParts["query"];
            }
        }
        $requestUri = $urlParts["scheme"] . "://" . $urlParts["host"] . $urlParts["path"];
        $scheme = $urlParts["scheme"] . "://";

        /**
         * Define store id
         */
        $session = $request->getSession();



        $urlParts = $this->parseUrl($requestUri);
        /**
         * Custom url parser project based
         */
        $urlParts = $this->defaultScommerceManager->customParseUrl($urlParts);

        $currentWebsiteId = null;
        $currentWebsiteName = "";
        $isMultilang = false;

        $websites = $this->getWebsitesArray();

        if (isset($websites[$urlParts["base_url"]])) {
            $currentWebsiteId = $websites[$urlParts["base_url"]]["id"];
            $currentWebsiteName = $websites[$urlParts["base_url"]]["name"];
            $isMultilang = $websites[$urlParts["base_url"]]["is_multilang"];
        } else {
            $currentWebsiteId = $_ENV["DEFAULT_WEBSITE_ID"];
        }

        $session->set("current_website_id", $currentWebsiteId);
        $session->set("current_website_name", $currentWebsiteName);
        $session->set("languages", array());

        $previousStoreId = $session->get("previous_store_id");

        if ($isMultilang) {
            $firstParam = reset($urlParts['url_parts']);

            if (!empty($firstParam) && array_key_exists($firstParam, $this->languages[$currentWebsiteId])) {
                array_shift($urlParts['url_parts']);

                $session->set("languages", $this->languages[$currentWebsiteId]);
                $session->set("current_language", $firstParam);
                $session->set("current_language_url", "/" . $firstParam);
                $session->set("current_store_id", $this->languages[$currentWebsiteId][$firstParam]);
                $session->set("previous_store_id", $this->languages[$currentWebsiteId][$firstParam]);
            } else {
                $ret["redirect_type"] = 301;
                $ret["redirect_url"] = "/" . implode("/", array_merge([$_ENV["DEFAULT_LANG"]], $urlParts["url_parts"]));

                if (!empty($_GET)) {
                    $parameters = [];
                    foreach ($_GET as $key => $value) {
                        $parameters[] = "$key=$value";
                    }

                    $ret["redirect_url"] .= "?" . implode("&", $parameters);
                }

                return $ret;
            }
        } else {
            $firstParam = reset($urlParts['url_parts']);

            if (!empty($firstParam) && array_key_exists($firstParam, $this->languages[$currentWebsiteId])) {
                $ret["redirect_type"] = 301;
                unset($urlParts['url_parts'][1]);
                $ret["redirect_url"] = "/" . implode("/", $urlParts["url_parts"]);

                if (!empty($_GET)) {
                    $parameters = [];
                    foreach ($_GET as $key => $value) {
                        $parameters[] = "$key=$value";
                    }

                    $ret["redirect_url"] .= "?" . implode("&", $parameters);
                }

                return $ret;
            }

            $storeId = array_values($this->languages[$currentWebsiteId])[0];

            /** @var SStoreEntity $store */
            $store = $this->getStoreById($storeId);

            $session->set("current_language", $store->getCoreLanguage()->getCode());
            $session->set("current_language_url", null);
            $session->set("current_store_id", $storeId);
            $session->set("previous_store_id", $storeId);
        }

        $result = $this->checkDefaultRedirect($request->getUri());

        /**
         * New default redirect rules
         */
        /** @var SRouteNotFoundEntity $route404 */
        $route404 = $this->getRoute404($request->getUri(),$session->get("current_store_id"));

        if(!empty($route404) && $route404->getIsRedirected() && !empty($route404->getRedirectTo())){

            /** @var SRouteEntity $route */
            $route = $this->getRouteByUrl($urlParts, $session->get("current_store_id"));

            if(!empty($route)){
                $this->deleteRoute404($route404);
                $route404 = null;
            }
            else{
                $result["redirect_type"] = 301;
                $result["redirect_url"] = str_ireplace("//","/","/".$route404->getRedirectTo());
                $result["query"] = "";
            }
        }
        elseif(empty($result)){
            $result = $this->defaultScommerceManager->beforeParseUrl($request);
        }

        if (isset($result["data"])) {
            $ret["data"] = $result["data"];
        }
        $ret["data"]["query"] = $query;

        if (isset($result["redirect_type"])) {

            if(!empty($route404) && !$route404->getIsRedirected()){
                $route404data = Array();
                $route404data["is_redirected"] = 1;
                $route404data["redirect_to"] = str_ireplace("//","/","/".$result["redirect_url"]);

                $this->insertUpdateRoute404($route404data, $route404);
            }

            $ret["redirect_type"] = $result["redirect_type"];
            $ret["redirect_url"] = $result["redirect_url"];
            if (isset($result["query"])) {
                $ret["data"]["query"] = $result["query"];
            }

            return $ret;
        } elseif (isset($result["not_found_exception"])) {
            return $ret;
        }

        if (isset($_GET["s"]) && $_GET["s"] == 1 && isset($_GET["keyword"]) && !empty($_GET["keyword"])) {
            if (isset($_ENV["EXTERNAL_SITE_REDIRECT"]) && !empty($_ENV["EXTERNAL_SITE_REDIRECT"])) {
                $redirects = json_decode($_ENV["EXTERNAL_SITE_REDIRECT"], true);
                if (!empty($redirects)) {
                    foreach ($redirects as $redirect) {
                        $redirectKeywords = explode(",", $redirect["keywords"]);
                        if (in_array(strtolower($_GET["keyword"]), $redirectKeywords)) {
                            $ret["data"] = [];
                            $ret["redirect_type"] = 301;
                            $ret["redirect_url"] = $redirect["url"];
                            return $ret;
                        }
                    }
                }
            }
        }

        $request->setLocale($session->get("current_language"));
        $session->set('_locale', $session->get("current_language"));

        /** @var SRouteEntity $route */
        $route = $this->getRouteByUrl($urlParts, $session->get("current_store_id"));

        if (empty($route)) {

            if (isset($_ENV["REMOTE_URL"]) && !empty($_ENV["REMOTE_URL"]) && strpos($urlParts["request_url"], ".") !== false) {
                $filePath = parse_url($requestUri);

                if (empty($this->imageStyleManager)) {
                    $this->imageStyleManager = $this->container->get("image_style_manager");
                }

                $this->imageStyleManager->getRemoteImage($_ENV["REMOTE_URL"], $filePath["path"]);
            }

            $ret["not_found_exception"] = true;

            return $ret;
        } elseif (!empty($route->getRedirectTo())) {
            if (empty($route->getRedirectTypeId()) || $route->getRedirectTypeId() == 2) {
                $ret["not_found_exception"] = true;

                return $ret;
            }

            if(!empty($route404) && !$route404->getIsRedirected()){

                $route404data = Array();
                $route404data["is_redirected"] = 1;
                $route404data["redirect_to"] = str_ireplace("//","/","/".$route->getRedirectTo());

                $this->insertUpdateRoute404($route404data, $route404);
            }

            $ret["redirect_type"] = 301;
            $ret["redirect_url"] = "/" . $route->getRedirectTo();

            return $ret;
        }

        /** @var SPageEntity $destination */
        $destination = $this->getDestinationByRoute($route);
        if (empty($destination)) {
            $ret["not_found_exception"] = true;

            return $ret;
        }
        $destinationCheck = $this->defaultScommerceManager->validateDestination($destination, $session->get("current_store_id"), $isMultilang);
        if (isset($destinationCheck["redirect_type"])) {
            if ($destinationCheck["redirect_type"] == 301) {
                $ret["redirect_type"] = $destinationCheck["redirect_type"];
                $ret["redirect_url"] = $destinationCheck["redirect_url"];
            } else {
                $ret["not_found_exception"] = true;
            }

            return $ret;
        }

        if(!empty($route404) && !$route404->getIsRedirected() && $route->getRequestUrl() != "404"){

            $route404data = Array();
            $route404data["is_redirected"] = 1;
            $route404data["redirect_to"] = str_ireplace("//","/","/".$route->getRequestUrl());

            $this->insertUpdateRoute404($route404data, $route404);
        }

        $ret["data"]["page"] = $destination;
        $ret["data"]["id"] = $destination->getId();

        /**
         * If category check if full path
         */

        if (isset($urlParts['url_parts']) && !empty($urlParts['url_parts']) && EntityHelper::checkIfMethodExists($destination, "getUrlPath")) {
            $fullUrl = $destination->getUrlPath($session->get("current_store_id"));

            if ($fullUrl != implode("/", $urlParts["url_parts"])) {
                if ($session->get("current_language_url") . "/" . $fullUrl != implode("/", $urlParts["url_parts"])) {
                    $ret["redirect_type"] = 301;
                    if (!empty($session->get("current_language_url"))) {
                        $ret["redirect_url"] = "/" . $session->get("current_language_url") . "/" . $fullUrl;
                    } else {
                        $ret["redirect_url"] = "/" . $fullUrl;
                    }

                    $ret["redirect_url"] = str_replace("//", "/", $ret["redirect_url"]);

                    return $ret;
                }
            }
        }

        $result = $this->defaultScommerceManager->afterParseUrl($request, $route, $destination);

        if (isset($result["data"])) {
            $ret["data"] = array_merge($ret["data"], $result["data"]);
        }

        if (isset($result["redirect_type"])) {
            $ret["redirect_type"] = $result["redirect_type"];
            $ret["redirect_url"] = $result["redirect_url"];

            return $ret;
        }

        $ret["data"]["recaptcha_site_key"] = $_ENV["GOOGLE_RECAPTCHA_V3_KEY_FRONT"] ?? null;
        $ret["data"]["environment"] = $_ENV["IS_PRODUCTION"];
        $ret["data"]["site_base_data"] = $this->prepareSiteBaseData($session->get("current_website_id"));
        $ret["data"]["money_transfer_payment_slip"] = $this->prepareMoneyTransferPaymentSlip($session->get("current_store_id"));

        $ret["data"]["site_base_data"]["site_base_url"] = $scheme . $urlParts["base_url"] . "/";
        $ret["data"]["site_base_data"]["site_base_url_language"] = $scheme . $urlParts["base_url"] . $session->get("current_language_url") . "/";
        $ret["data"]["default_canonical"] = $scheme . $urlParts["base_url"] . $session->get("current_language_url") . "/" . implode("/", $urlParts["url_parts"]);
        // Uklonjeno 26.9.2022 - da canonical uvijek bude cist
//        if (isset($ret["data"]["query"]) && !empty($ret["data"]["query"])) {
//            $ret["data"]["default_canonical"] .= "?" . $ret["data"]["query"];
//        }

        $session->set("site_base_data", $ret["data"]["site_base_data"]);

        /**
         * If store changed recalculate quote
         */
        if ($previousStoreId != $session->get("current_store_id")) {

            if (empty($this->quoteManager)) {
                $this->quoteManager = $this->container->get("quote_manager");
            }

            /** @var QuoteEntity $quote */
            $quote = $this->quoteManager->getActiveQuote(false);
            if (!empty($quote)) {

                if (empty($this->cacheManager)) {
                    $this->cacheManager = $this->container->get("cache_manager");
                }

                $exchangeRates = array();

                $cacheItem = $this->cacheManager->getCacheGetItem("exchange_rates");
                if (!empty($cacheItem)) {
                    $exchangeRates = $cacheItem->get();
                }

                if (!isset($exchangeRates[$session->get("current_website_id")][$session->get("current_store_id")])) {
                    $this->logger->error("CREATE QUOTE: missing exchange rate for website {$session->get("current_website_id")} and store {$session->get("current_store_id")} - {$request->headers->get('referer')}");
                    return null;
                }

                $data = array();
                $data["currency"] = $this->quoteManager->getCurrencyById($exchangeRates[$session->get("current_website_id")][$session->get("current_store_id")]["currency_id"]);
                $data["currency_rate"] = $exchangeRates[$session->get("current_website_id")][$session->get("current_store_id")]["exchange_rate"];
                $data["store"] = $this->getStoreById($session->get("current_store_id"));

                $this->quoteManager->updateQuote($quote, $data);

                $this->quoteManager->recalculateQuoteItems($quote);
            }
        }

        if ($_ENV["SHAPE_TRACK"] ?? 0) {
            /** Insert tracking event */
            if (!empty($destination)) {
                if (empty($this->trackingManager)) {
                    $this->trackingManager = $this->container->get("tracking_manager");
                }

                $this->trackingManager->insertTrackingEvent($request, $destination->getId(), $destination->getEntityType()->getEntityTypeCode(), ScommerceConstants::EVENT_TYPE_PAGE_VIEW, ScommerceConstants::EVENT_NAME_PAGE_VIEWED);
            }
        }

        return $ret;
    }

    /**
     * @param $websiteId
     * @return mixed
     */
    public function prepareSiteBaseData($websiteId)
    {
        $ret = json_decode($_ENV["SITE_BASE_DATA"], true);
        $siteBaseData = $ret[$websiteId];

        return $siteBaseData;
    }

    /**
     * @param $websiteId
     * @param $storeId
     * @return mixed
     */
    public function prepareMoneyTransferPaymentSlip($storeId)
    {
        $moneyTransferPaymentSlip = array();

        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $ret = json_decode($_ENV["MONEY_TRANSFER_PAYMENT_SLIP"], true);
        if (isset($ret[$storeId])) {
            $moneyTransferPaymentSlip = $ret[$storeId];
        }

        return $moneyTransferPaymentSlip;
    }

    /**
     * @param $url
     * @return array
     */
    public function parseUrl($url)
    {

        $query = null;
        $hash = null;

        $url = str_ireplace($_ENV["SSL"] . "://", "", $url);
        $url = rtrim($url, "/");

        $url_parts = explode("?", $url);
        $query = $url_parts[1] ?? "";
        $url_parts = $url_parts[0];

        $url_parts = explode("#", $url_parts);
        if (isset($url_parts[1])) {
            $hash = $url_parts[1];
        }
        $url_parts = $url_parts[0];
        $url_parts = rtrim($url_parts, "/");

        $url_parts = explode("/", $url_parts);

        $base_url = $url_parts[0];
        unset($url_parts[0]);

        if (empty($url_parts)) {
            $request_url = "/";
        } else {
            //TODO ovo je zakomentirano jer je problem kod kategorija pa je odluka pala da uzima samo zadnji dio url-a
            //$request_url = implode("/",$url_parts);
            $request_url = end($url_parts);
        }

        foreach ($this->languages as $languageArray) {
            foreach ($languageArray as $key => $value) {
                if (strtolower($request_url) == $key) {
                    $request_url = "/";
                    break;
                }
            }
        }

        return array(
            "base_url" => $base_url,
            "request_url" => $request_url,
            "url_parts" => $url_parts,
            "query" => $query,
            "hash" => $hash,
        );
    }

    /**
     * @param $url_parts
     * @param null $store_id
     * @return |null
     */
    public function getRouteByUrl($url_parts, $store_id = null)
    {
        $routeEntityType = $this->entityManager->getEntityTypeByCode("s_route");

        /**
         * Ovo treba izmaknut van
         */
        $url_parts["request_url"] = str_ireplace("'", "", $url_parts["request_url"]);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("requestUrl", "eq", $url_parts["request_url"]));
        if (!empty($store_id)) {
            $compositeFilter->addFilter(new SearchFilter("store", "eq", $store_id));
        }
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($routeEntityType, $compositeFilters);
    }

    /**
     * @param $url
     * @param $store
     * @param null $avoidDestinationId
     * @param null $avoidDestinationType
     * @return |null
     */
    public function getRouteByUrlAndStore(
        $url,
        SStoreEntity $store,
        $avoidDestinationId = null,
        $avoidDestinationType = null,
        $destinationId = null,
        $destinationType = null
    )
    {

        $routeEntityType = $this->entityManager->getEntityTypeByCode("s_route");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("requestUrl", "eq", $url));
        $compositeFilter->addFilter(new SearchFilter("store", "eq", $store->getId()));
        if (!empty($destinationId)) {
            $compositeFilter->addFilter(new SearchFilter("destinationId", "eq", $destinationId));
        }
        if (!empty($destinationType)) {
            $compositeFilter->addFilter(new SearchFilter("destinationType", "eq", $destinationType));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $routes = $this->entityManager->getEntitiesByEntityTypeAndFilter($routeEntityType, $compositeFilters);

        if (empty($routes)) {
            return null;
        }

        if (!empty($avoidDestinationId)) {
            /** @var SRouteEntity $route */
            foreach ($routes as $key => $route) {
                if ($route->getDestinationId() == $avoidDestinationId && $route->getDestinationType() == $avoidDestinationType) {
                    unset($routes[$key]);
                    break;
                }
            }

            if (empty($routes)) {
                return null;
            }
        }

        return $routes[0];
    }

    /**
     * @param SRouteEntity $routeEntity
     * @return |null
     */
    public function getDestinationByRoute(SRouteEntity $routeEntity)
    {
        if(empty($this->et{$routeEntity->getDestinationType()})){
            $this->et{$routeEntity->getDestinationType()} = $this->entityManager->getEntityTypeByCode($routeEntity->getDestinationType());
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("id", "eq", $routeEntity->getDestinationId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($this->et{$routeEntity->getDestinationType()}, $compositeFilters);
    }

    /**
     * @param $destinationId
     * @param $destinationType
     * @param SStoreEntity $store
     * @return |null
     */
    public function getRouteByDestination($destinationId, $destinationType, SStoreEntity $store)
    {

        $routeEntityType = $this->entityManager->getEntityTypeByCode("s_route");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("destinationId", "eq", $destinationId));
        $compositeFilter->addFilter(new SearchFilter("destinationType", "eq", $destinationType));
        $compositeFilter->addFilter(new SearchFilter("redirectType", "nu", null));
        $compositeFilter->addFilter(new SearchFilter("store", "eq", $store->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($routeEntityType, $compositeFilters);
    }

    /**
     * @param SStoreEntity $store
     * @return |null
     */
    public function getNotFoundRouteForStore(SStoreEntity $store)
    {

        $routeEntityType = $this->entityManager->getEntityTypeByCode("s_route");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("requestUrl", "eq", "404"));
        $compositeFilter->addFilter(new SearchFilter("store", "eq", $store->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($routeEntityType, $compositeFilters);
    }

    /**
     * @param $name
     * @return false|mixed|string|string[]|null
     */
    public function prepareUrl($name)
    {

        $url = StringHelper::sanitizeFileName($name);

        return $this->createUrlKey($url);
    }

    /**
     * @param null $entity
     * @param $name
     * @param SStoreEntity $storeEntity
     * @param null $manualUrl
     * @param null $destinationId
     * @param null $destinationEntityTypeCode
     * @return SRouteEntity|null
     */
    public function createNewRoute($entity = null, $name, SStoreEntity $storeEntity, $manualUrl = null, $destinationId = null, $destinationEntityTypeCode = null)
    {
        if (!empty($manualUrl)) {
            $name = $manualUrl;
        } else {
            if (empty($name)) {
                return null;
            }
        }

        if (empty($destinationId) || empty($destinationEntityTypeCode)) {

            if (empty($entity)) {
                return null;
            }

            $destinationId = $entity->getId();
            $destinationEntityTypeCode = $entity->getEntityType()->getEntityTypeCode();
        }

        $urlTmp = $url = $this->prepareUrl($name);

        $i = 1;
        $existingRoute = $this->getRouteByUrlAndStore(
            $urlTmp,
            $storeEntity,
            null,
            null,
            null, //$entity->getId(),
            null //$entity->getEntityType()->getEntityTypeCode()
        );
        while (!empty($existingRoute)) {
            $urlTmp = $url . "-" . $i;
            $existingRoute = $this->getRouteByUrlAndStore(
                $urlTmp,
                $storeEntity,
                null, //$entity->getId(),
                null //$entity->getEntityType()->getEntityTypeCode()
            );
            $i++;
        }

        /** @var SRouteEntity $route */
        $route = $this->entityManager->getNewEntityByAttributSetName("s_route");
        $route->setRequestUrl($urlTmp);
        $route->setDestinationType($destinationEntityTypeCode);
        $route->setDestinationId($destinationId);
        $route->setRedirectTo(null);
        $route->setRedirectType(null);
        $route->setStore($storeEntity);

        $this->entityManager->saveEntityWithoutLog($route);
        $this->entityManager->refreshEntity($route);

        return $route;
    }

    /**
     * @param SRouteEntity $route
     * @return bool
     * Use only if necessary
     */
    public function deleteRoute(SRouteEntity $route)
    {

        $this->entityManager->deleteEntity($route);

        return true;
    }

    /**
     * @param $id
     * @return |null
     */
    public function getRedirectTypeById($id)
    {
        if (empty($id)) {
            $id = $_ENV["DEFAULT_STORE_ID"];
        }

        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(SRedirectTypeEntity::class);
        return $repository->find($id);
    }

    /**
     * @param $name
     * @return |null
     * @deprecated
     */
    public function getRedirectTypeByName($name)
    {

        $redirectTypeEntityType = $this->entityManager->getEntityTypeByCode("s_redirect_type");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("name", "eq", $name));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($redirectTypeEntityType, $compositeFilters);
    }

    /**
     * @param SRouteEntity $oldRoute
     * @param SRouteEntity $newRoute
     * @param SRedirectTypeEntity $redirectTypeEntity
     * @return SRouteEntity
     */
    public function setRedirectRoute(
        SRouteEntity        $oldRoute,
        SRouteEntity        $newRoute,
        SRedirectTypeEntity $redirectTypeEntity
    )
    {

        $oldRoute->setRedirectTo($newRoute->getRequestUrl());
        $oldRoute->setRedirectType($redirectTypeEntity);

        $this->entityManager->saveEntityWithoutLog($oldRoute);

        return $oldRoute;
    }

    /**
     * @param $url
     * @return false|mixed|string|string[]|null
     */
    public function createUrlKey($url)
    {

        $url = trim($url);
        $url = mb_strtolower($url, mb_detect_encoding($url));
        $url = preg_replace('/[^A-Za-z0-9-\s]/', ' ', $url);
        $url = preg_replace('/[\s]+/', '-', $url);
        $url = preg_replace('/[-][-]+/', '-', $url);
        $url = trim($url, '-');

        return $url;
    }

    /**
     * @return |null
     */
    public function getWebsites()
    {

        $entityType = $this->entityManager->getEntityTypeByCode("s_website");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param SWebsiteEntity|null $website
     * @return |null
     */
    public function getStores(SWebsiteEntity $website = null)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("s_store");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        if (!empty($website)) {
            $compositeFilter->addFilter(new SearchFilter("website", "eq", $website->getId()));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getStoreById($id)
    {
        if (empty($id)) {
            $id = $_ENV["DEFAULT_STORE_ID"];
        }

        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(SStoreEntity::class);
        return $repository->find($id);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getWebsiteById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(SWebsiteEntity::class);
        return $repository->find($id);

        /*$entityType = $this->entityManager->getEntityTypeByCode("s_website");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);*/
    }

    /**
     * @param $stores
     * @return array
     */
    public function getCurrencies($stores = null)
    {

        $data = array();

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        if (empty($stores)) {
            $stores = $this->getStores();
        }

        $baseCurrencyId = $_ENV["DEFAULT_CURRENCY"];

        $exchangeRates = array();

        $exists = $this->entityManager->getEntityTypeByCode("exchange_rate");

        if (!empty($exists)) {

            if (empty($this->quoteManager)) {
                $this->quoteManager = $this->container->get("quote_manager");
            }

            $exchangeRates = $this->quoteManager->getLastExchangeRates();
        }

        if (!empty($stores)) {
            /** @var SStoreEntity $store */
            foreach ($stores as $store) {
                $data[$store->getWebsiteId()][$store->getId()]["currency_id"] = $store->getDisplayCurrency()->getId();
                $data[$store->getWebsiteId()][$store->getId()]["currency_code"] = $store->getDisplayCurrency()->getCode();
                $data[$store->getWebsiteId()][$store->getId()]["currency_sign"] = $store->getDisplayCurrency()->getSign();
                $data[$store->getWebsiteId()][$store->getId()]["exchange_rate"] = 1;

                if (isset($exchangeRates[$store->getDisplayCurrency()->getId()]) && $store->getDisplayCurrency()->getId() != $baseCurrencyId) {
                    $defaultExchangeRate = $_ENV["DEFAULT_CURRENCY_EXCHANGE"];

                    foreach ($exchangeRates as $exchangeRate) {
                        if ($exchangeRate["currency_from_id"] == $store->getDisplayCurrency()->getId()) {

                            $exchange = $exchangeRate["{$defaultExchangeRate}_rate"];

                            $data[$store->getWebsiteId()][$store->getId()]["exchange_rate"] = $exchange;

                            break;
                        }
                    }
                }
            }
        }

        $this->cacheManager->setCacheItem("exchange_rates", $data);

        return $data;
    }

    /**
     * @return array
     */
    public function getLanguages()
    {
        $data = [];

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $cacheItem = $this->cacheManager->getCacheGetItem("languages");

        if (empty($cacheItem) || isset($_GET["rebuild_stores"])) {

            $stores = $this->getStores();

            $this->getCurrencies($stores);

            if (!empty($stores)) {
                /** @var SStoreEntity $store */
                foreach ($stores as $store) {
                    $data[$store->getWebsiteId()][$store->getCoreLanguage()->getCode()] = $store->getId();
                }
            }

            $this->cacheManager->setCacheItem("languages", $data);
        } else {
            $data = $cacheItem->get();
        }

        return $data;
    }

    /**
     * @param SStoreEntity $store
     * @return string
     */
    public function getLanguageUrl(SStoreEntity $store)
    {

        $websiteData = $this->getWebsiteDataById($store->getWebsiteId());

        if ($websiteData["is_multilang"]) {
            return "/" . $store->getCoreLanguage()->getCode();
        }

        return "";
    }

    /**
     * @param $websiteId
     * @return mixed|null
     */
    public function getWebsiteDataById($websiteId)
    {

        $websites = $this->getWebsitesArray();

        if (!empty($websites)) {
            foreach ($websites as $url => $values) {
                if ($values["id"] == $websiteId) {
                    $values["base_url"] = $url;
                    return $values;
                }
            }
        }

        return null;
    }

    /**
     * @return array
     */
    public function getWebsitesArray()
    {
        $data = [];

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $cacheItem = $this->cacheManager->getCacheGetItem("websites");

        if (empty($cacheItem) || isset($_GET["rebuild_websites"])) {

            $websites = $this->getWebsites();
            if (!empty($websites)) {
                /** @var SWebsiteEntity $website */
                foreach ($websites as $website) {
                    $data[$website->getBaseUrl()] = [
                        "id" => $website->getId(),
                        "name" => $website->getName(),
                        "is_multilang" => count($website->getStores()) > 1
                    ];
                }
            }


            $this->cacheManager->setCacheItem("websites", $data);
        } else {
            $data = $cacheItem->get();
        }

        return $data;
    }

    /**
     * @return mixed
     */
    public function getPageBlockLayout($page)
    {
        if (method_exists($page, "getLayout")) {
            $layout = $page->getLayout();
            if (!empty($layout)) {
                $layout = json_decode($layout, true);
                if (!empty($layout)) {
                    return $layout;
                }
            }
        }
        return json_decode($page->getTemplateType()->getContent(), true);
    }

    /**
     * @return int|string
     */
    public function getSRouteIdsToNowhere()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $ret = 0;

        $q = "SELECT s1.id FROM s_route_entity as s1 LEFT JOIN s_route_entity as s2 ON s1.redirect_to = s2.request_url AND s1.store_id=s2.store_id AND s2.entity_state_id = 1 WHERE s1.redirect_type_id is not null and s2.request_url is null AND s1.entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        if (!empty($data)) {
            $ret = implode(",", array_column($data, "id"));
        }

        return $ret;
    }

    /**
     * @return array|int|string
     */
    public function getSRouteLeadingToNowhere()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $ret = array();

        $destinationTypes = $this->getDestinationTypes();
        if (empty($destinationTypes)) {
            return 0;
        }


        $stores = $this->getStores();

        foreach ($destinationTypes as $destinationType) {
            foreach ($stores as $store) {
                $q = "SELECT sr.id FROM s_route_entity as sr
                    LEFT JOIN {$destinationType}_entity as p ON sr.destination_id = p.id AND p.entity_state_id = 1 AND JSON_CONTAINS(p.show_on_store, '1',  '$.\"{$store->getId()}\"') = '1'
                    WHERE sr.redirect_type_id is null AND sr.entity_state_id = 1 and sr.store_id = {$store->getId()} and sr.destination_type = '{$destinationType}' AND p.id is null;";
                $data = $this->databaseContext->getAll($q);

                if (!empty($data)) {
                    $ret = array_merge($ret, array_column($data, "id"));
                }
            }
        }

        if (!empty($ret)) {
            $ret = implode(",", $ret);
        } else {
            return 0;
        }

        return $ret;
    }

    /**
     * @return int|string
     */
    public function get404routes()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $ret = 0;

        $q = "SELECT id FROM s_route_entity WHERE redirect_type_id = 2 AND entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        if (!empty($data)) {
            $ret = implode(",", array_column($data, "id"));
        }

        return $ret;
    }

    /**
     * @return array|int|string
     */
    public function getSrouteOfflineProductsWithAutomaticRedirects()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $ret = array();

        $destinationTypes = $this->getDestinationTypes();
        if (empty($destinationTypes)) {
            return 0;
        }

        $stores = $this->getStores();

        foreach ($destinationTypes as $destinationType) {
            foreach ($stores as $store) {

                $additionaFilter = $_ENV["SITEMAP_ADDITIONAL_" . strtoupper($destinationType) . "_FILTER"];
                $additionaFilter = str_ireplace("{storeId}", $store->getId(), $additionaFilter);

                $q = "SELECT sr.id FROM s_route_entity as sr
                    LEFT JOIN {$destinationType}_entity as p ON sr.destination_id = p.id AND p.entity_state_id = 1 AND JSON_CONTAINS(p.show_on_store, '1', '$.\"{$store->getId()}\"') = '1' {$additionaFilter}
                    WHERE sr.redirect_type_id is null AND sr.entity_state_id = 1 and sr.destination_type = '{$destinationType}' AND p.id is null;";
                $data = $this->databaseContext->getAll($q);

                if (!empty($data)) {
                    $ret = array_merge($ret, array_column($data, "id"));
                }
            }
        }

        if (!empty($ret)) {
            $ret = implode(",", $ret);
        } else {
            return 0;
        }

        return $ret;
    }

    /**
     * @param $entityTypeCode
     * @param $store_id
     * @return array
     */
    public function getEntityDataForSitemapByEntityTypeAndStore($entityTypeCode, $store_id)
    {

        $data = array();

        $routes = $this->getRoutesForSitemap($store_id, $entityTypeCode);

        if (!empty($routes)) {

            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->container->get("database_context");
            }

            $routeIds = implode(",", array_column($routes, "id"));

            $q = "SELECT p.id, sr.request_url as url, JSON_UNQUOTE(JSON_EXTRACT(p.name, '$.\"{$store_id}\"')) AS name
                FROM s_route_entity as sr LEFT JOIN {$entityTypeCode}_entity as p ON sr.destination_id = p.id AND JSON_CONTAINS(p.show_on_store, '1',  '$.\"{$store_id}\"') = '1'
                WHERE sr.id IN ({$routeIds});";
            $results = $this->databaseContext->getAll($q);

            if (!empty($results)) {
                foreach ($results as $result) {
                    if (!empty($result)) {
                        $data[$result["id"]] = $result;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param $store_id
     * @param null $limitByType
     * @return array
     */
    public function getRoutesForSitemap($store_id, $limitByType = null)
    {

        $ret = array();

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $sitemapIncludedDestinationTypes = $_ENV["SITEMAP_DESTINATION_TYPES"] ?? '["product","product_group","blog_category","blog_post"]';
        $sitemapIncludedDestinationTypes = json_decode($sitemapIncludedDestinationTypes, true);

        if (!empty($limitByType)) {
            if (!in_array($limitByType, $sitemapIncludedDestinationTypes)) {
                return $ret;
            } else {
                $sitemapIncludedDestinationTypes = array_intersect([$limitByType], $sitemapIncludedDestinationTypes);
            }
        }

        foreach ($sitemapIncludedDestinationTypes as $sitemapIncludedDestinationType) {

            $additionaFilter = $_ENV["SITEMAP_ADDITIONAL_" . strtoupper($sitemapIncludedDestinationType) . "_FILTER"];
            $additionaFilter = str_ireplace("{storeId}", $store_id, $additionaFilter);

            $q = "SELECT sr.* FROM s_route_entity as sr
            LEFT JOIN {$sitemapIncludedDestinationType}_entity as p ON sr.destination_id = p.id AND JSON_CONTAINS(p.show_on_store, '1',  '$.\"{$store_id}\"') = '1'
            WHERE sr.redirect_to is null and sr.destination_type = '{$sitemapIncludedDestinationType}' and sr.entity_state_id = 1 and p.entity_state_id = 1 AND JSON_CONTAINS(show_on_store, '1', '$.\"{$store_id}\"') = '1' AND sr.store_id = {$store_id} {$additionaFilter} ORDER BY sr.id DESC;";
            $data = $this->databaseContext->getAll($q);

            if (!empty($data)) {
                $ret = array_merge($ret, $data);
            }
        }

        return $ret;
    }

    /**
     * @return array|int|string
     */
    public function getSrouteForSitemapIds()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $ret = array();

        $stores = $this->getStores();

        /** @var SStoreEntity $store */
        foreach ($stores as $store) {
            $ret = array_merge($ret, $this->getRoutesForSitemap($store->getId()));
        }

        if (!empty($ret)) {
            $ret = implode(",", array_column($ret, "id"));
        } else {
            return 0;
        }

        return $ret;
    }

    /**
     * @return array|int|string
     */
    public function getSrouteWithdifferentUrslThanParent()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $ret = array();

        $destinationTypes = $this->getDestinationTypes();
        if (empty($destinationTypes)) {
            return 0;
        }

        $stores = $this->getStores();

        foreach ($destinationTypes as $destinationType) {
            foreach ($stores as $store) {
                $q = "SELECT sr.id FROM s_route_entity as sr
                    LEFT JOIN {$destinationType}_entity as p ON sr.destination_id = p.id AND JSON_CONTAINS(p.show_on_store, '1', '$.\"{$store->getId()}\"') = '1'
                    WHERE sr.store_id = {$store->getId()} AND sr.redirect_type_id is null AND sr.entity_state_id = 1 and sr.destination_type = '{$destinationType}' AND sr.request_url != JSON_UNQUOTE(JSON_EXTRACT(p.url, '$.\"{$store->getId()}\"')) AND JSON_UNQUOTE(JSON_EXTRACT(p.url, '$.\"{$store->getId()}\"')) is not null;";
                $data = $this->databaseContext->getAll($q);

                if (!empty($data)) {
                    $ret = array_merge($ret, array_column($data, "id"));
                }
            }
        }

        if (!empty($ret)) {
            $ret = implode(",", $ret);
        } else {
            return 0;
        }

        return $ret;
    }

    /**
     * @return array|int|string
     */
    public function createRoutesForEntitiesWithoutRutes()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $destinationTypes = $this->getDestinationTypes();
        if (empty($destinationTypes)) {
            return true;
        }

        $stores = $this->getStores();

        if (empty($this->importManager)) {
            $this->importManager = new DefaultIntegrationImportManager();
            $this->importManager->setContainer($this->getContainer());
            $this->importManager->initialize();
        }

        foreach ($destinationTypes as $destinationType) {

            /** @var SStoreEntity $store */
            foreach ($stores as $store) {
                $q = "SELECT id, JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"{$store->getId()}\"')) AS name, url FROM {$destinationType}_entity AS p WHERE entity_state_id = 1 AND JSON_CONTAINS(p.show_on_store, '1', '$.\"{$store->getId()}\"') = '1' AND id NOT IN (SELECT destination_id FROM s_route_entity WHERE destination_type = '{$destinationType}' AND store_id = {$store->getId()});";
                $data = $this->databaseContext->getAll($q);

                if (!empty($data)) {

                    $existingSRoutes = $this->importManager->getEntitiesArray(["request_url", "store_id", "destination_type"], "s_route_entity", ["store_id", "request_url"]);
                    $insertArray2["s_route_entity"] = array();
                    $updateArray["{$destinationType}_entity"] = array();

                    foreach ($data as $d) {

                        $urlArray = json_decode($d["url"], true);

                        $i = 1;

                        if(isset($urlArray[$store->getId()]) && !empty($urlArray[$store->getId()])){
                            $url = $key = $urlArray[$store->getId()];
                        }
                        else{
                            $url = $key = $this->prepareUrl($d["name"]);
                        }
                        while (isset($existingSRoutes[$store->getId() . "_" . $url]) || isset($insertArray2["s_route_entity"][$store->getId() . "_" . $url])) {
                            $url = $key . "-" . $i++;
                        }
                        $urlArray[$store->getId()] = $url;
                        $urlJson = json_encode($urlArray, JSON_UNESCAPED_UNICODE);

                        $insertArray2["s_route_entity"][$store->getId() . "_" . $url] = $this->importManager->getSRouteInsertEntity($url, $destinationType, $store->getId(), $d["id"]);

                        $productUpdate = new UpdateModel($d);
                        $productUpdate
                            ->add("url", $urlJson);

                        if (!empty($productUpdate->getArray())) {
                            $updateArray["{$destinationType}_entity"][$productUpdate->getEntityId()] = $productUpdate->getArray();
                        }
                    }
                }

                if (isset($insertArray2["s_route_entity"]) && !empty($insertArray2["s_route_entity"])) {
                    $reselectArray["{$destinationType}_entity"] = $this->importManager->getEntitiesArray(array("id", "url"), "{$destinationType}_entity", ["id"], "");
                    $insertArray2 = $this->importManager->resolveImportArray($insertArray2, $reselectArray);
                    $this->importManager->executeInsertQuery($insertArray2);
                    unset($insertArray2);
                }
                if (isset($updateArray["{$destinationType}_entity"]) && !empty($updateArray["{$destinationType}_entity"])) {
                    $this->importManager->executeUpdateQuery($updateArray);
                    unset($updateArray);
                }
            }
        }

        return true;
    }

    /**
     * @return array
     */
    public function getDestinationTypes()
    {

        if (empty($this->destinationTypes)) {

            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->container->get("database_context");
            }

            $q = "SELECT DISTINCT(destination_type) as destination_type FROM s_route_entity;";
            $data = $this->databaseContext->getAll($q);

            $this->destinationTypes = array_column($data, "destination_type");
            if (!in_array("s_page", $this->destinationTypes)) {
                $this->destinationTypes[] = "s_page";
            }
            if (!in_array("product_group", $this->destinationTypes)) {
                $this->destinationTypes[] = "product_group";
            }
            if (!in_array("blog_post", $this->destinationTypes)) {
                $this->destinationTypes[] = "blog_post";
            }
            if (!in_array("blog_category", $this->destinationTypes)) {
                $this->destinationTypes[] = "blog_category";
            }
            if (!in_array("product", $this->destinationTypes)) {
                $this->destinationTypes[] = "product";
            }

            if (!empty($_ENV["SITEMAP_DESTINATION_TYPES"])) {
                $sitemapDestinationTypes = json_decode($_ENV["SITEMAP_DESTINATION_TYPES"], true);
                if (!empty($sitemapDestinationTypes)) {
                    $this->destinationTypes = array_unique(array_merge($sitemapDestinationTypes, $this->destinationTypes));
                }
            }
        }

        if (empty($this->defaultScommerceManager)) {
            $this->defaultScommerceManager = $this->container->get("scommerce_manager");
        }

        $this->destinationTypes = $this->defaultScommerceManager->filterCustomDestinationTypes($this->destinationTypes);

        return $this->destinationTypes;
    }

    public function automaticallyFixRutes()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        /**
         * Fix redirect chain
         */
        $q = "UPDATE s_route_entity as s1 LEFT JOIN s_route_entity as s2 ON s1.redirect_to = s2.request_url SET s1.redirect_to = s2.redirect_to WHERE s1.redirect_type_id = 1 and s2.redirect_type_id = 1;";
        $this->databaseContext->executeNonQuery($q);

        /**
         * Delete duplicate rows
         */
        $q = "DELETE FROM s_route_entity WHERE id IN(SELECT * FROM (SELECT MIN(id) FROM s_route_entity WHERE redirect_type_id is null GROUP BY store_id, destination_id, destination_type HAVING COUNT(id) > 1) temp);";
        $this->databaseContext->executeNonQuery($q);

        /**
         * Delete bad rutes
         */
        $routeIdsToDelete = array();

        $routeIdsToDeleteTmp = $this->getSRouteLeadingToNowhere();

        if ($routeIdsToDeleteTmp != "0") {
            $routeIdsToDelete = array_merge($routeIdsToDelete, explode(",", $routeIdsToDeleteTmp));
        }

        $routeIdsToDeleteTmp = $this->getSRouteIdsToNowhere();

        if ($routeIdsToDeleteTmp != "0") {
            $routeIdsToDelete = array_merge($routeIdsToDelete, explode(",", $routeIdsToDeleteTmp));
        }

        if (!empty($routeIdsToDelete)) {
            $q = "DELETE FROM s_route_entity WHERE id in (" . implode(",", $routeIdsToDelete) . ");";
            $this->databaseContext->executeNonQuery($q);
        }

        /**
         * Fix routes on entities
         */
        $routesToFix = $this->getSrouteWithdifferentUrslThanParent();

        if ($routesToFix != "0") {
            $routesToFix = explode(",", $routesToFix);
            $this->fixSrouteWithDifferentUrlThanParent($routesToFix);
        }

        /**
         * Create rutes for entities without rutes
         */
        $this->createRoutesForEntitiesWithoutRutes();

        return true;
    }

    /**
     * @param $routeIds
     * @return bool
     */
    public function fixSrouteWithDifferentUrlThanParent($routeIds)
    {

        if (empty($routeIds)) {
            return true;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM s_route_entity WHERE id in (" . implode(",", $routeIds) . ");";
        $routes = $this->databaseContext->getAll($q);

        if (empty($routes)) {
            return false;
        }

        $preparedRoutes = array();
        foreach ($routes as $route) {
            $preparedRoutes[$route["destination_type"]][$route["destination_id"]][$route["store_id"]] = $route;
        }

        $updateArray = array();

        foreach ($preparedRoutes as $destinationType => $routes) {
            $ids = array_keys($routes);

            $q = "SELECT * FROM {$destinationType}_entity WHERE id IN (" . implode(",", $ids) . ");";
            $entities = $this->databaseContext->getAll($q);

            if (!empty($entities)) {
                foreach ($entities as $entity) {
                    $entityUrl = json_decode($entity["url"], true);
                    foreach ($routes[$entity["id"]] as $storeId => $route) {
                        $entityUrl[$storeId] = $route["request_url"];
                    }
                    $entityUrl = json_encode($entityUrl, JSON_UNESCAPED_UNICODE);
                    $updateArray[] = "UPDATE {$destinationType}_entity SET url = '{$entityUrl}' WHERE id = '{$entity["id"]}';";
                    unset($routes[$entity["id"]]);

                    if (count($updateArray) > 1000) {
                        $q = implode("", $updateArray);
                        $this->databaseContext->executeNonQuery($q);
                        $updateArray = array();
                    }
                }
            }

            if (!empty($routes)) {

                $idsToDelete = array();

                foreach ($routes as $storeId => $route) {
                    foreach ($route as $r) {
                        $idsToDelete[] = $r["id"];
                    }
                }

                if (!empty($idsToDelete)) {
                    $q = "DELETE FROM s_route_entity WHERE id IN (" . implode(",", $idsToDelete) . ");";
                    $this->databaseContext->executeNonQuery($q);
                }
            }

            if (!empty($updateArray)) {
                $q = implode("", $updateArray);
                $this->databaseContext->executeNonQuery($q);
                $updateArray = array();
            }
        }

        return true;
    }

    /**
     * @param $entity
     * @param false $isUpdate
     * @return mixed
     */
    public function insertUpdateDefaultLanguages($entity, $isUpdate = false)
    {

        $stores = $entity->getShowOnStore();
        $hasChanges = false;

        $name = $entity->getName();
        $metaTitle = $entity->getMetaTitle();
        $url = $entity->getUrl();

        $baseName = null;
        foreach ($name as $n) {
            if (!empty($n)) {
                $baseName = $n;
                break;
            }
        }

        foreach ($stores as $key => $value) {
            if ($value && (!isset($name[$key]) || empty($name[$key]))) {
                $name[$key] = $baseName;
                $hasChanges = true;
            }
            if ($value && (!isset($metaTitle[$key]) || empty($metaTitle[$key]))) {
                $metaTitle[$key] = $name[$key];
                $hasChanges = true;
            }

            $entity->setName($name);
            /**
             * Check for urls
             */

            /** @var SStoreEntity $store */
            $store = $this->getStoreById($key);

            /** @var SRouteEntity $route */

            $route = $this->getRouteByDestination($entity->getId(), $entity->getEntityType()->getEntityTypeCode(), $store);
            /**
             * If is new
             */
            if (!$isUpdate) {

                if (empty($route)) {
                    if ($value) {
                        $route = $this->createNewRoute($entity, $name[$key], $store);
                    }
                    if (empty($route)) {
                        $url[$key] = null;
                        continue;
                    }
                }
            } else {

                /**
                 * If route does not exist
                 */
                if (empty($route) && $value) {
                    if (!$entity->getAutoGenerateUrl() && isset($url[$key]) && !empty($url[$key])) {
                        $route = $this->createNewRoute($entity, $name[$key], $store, $url[$key]);
                    } else {
                        $route = $this->createNewRoute($entity, $name[$key], $store);
                    }
                } elseif (!empty($route) && $value) {

                    $newRoute = null;
                    $newUrl = null;

                    if ($entity->getKeepUrl()) {
                        continue;
                    }

                    if (!$entity->getAutoGenerateUrl()) {
                        $newUrl = $this->prepareUrl($url[$key]);
                    } else {
                        $newUrl = $this->prepareUrl($name[$key]);
                    }

                    //if (!(stripos($route->getRequestUrl(), $newUrl) === 0)) {
                    //TODO ovo je na productima bilo kao gore, a na ostalim mjestima je uklonjeno. Treba skuziti zasto
                    if ($route->getRequestUrl() != $newUrl) {
                        if (!$entity->getAutoGenerateUrl()) {
                            $newRoute = $this->createNewRoute($entity, $name[$key], $store, $newUrl);
                        } else {
                            $newRoute = $this->createNewRoute($entity, $name[$key], $store);
                        }
                    }

                    if (!empty($newRoute)) {
                        $redirectType = $this->getRedirectTypeById(ScommerceConstants::S_REDIRECT_TYPE_301);
                        $this->setRedirectRoute($route, $newRoute, $redirectType);
                        $route = $newRoute;
                    }
                }
            }

            if (!empty($route) && (!isset($url[$key]) || $url[$key] != $route->getRequestUrl())) {
                $url[$key] = $route->getRequestUrl();
                $hasChanges = true;
            }
        }

        if (!$entity->getAutoGenerateUrl()) {
            $entity->setAutoGenerateUrl(1);
            $hasChanges = 1;
        }
        if (!$entity->getKeepUrl()) {
            $entity->setKeepUrl(1);
            $hasChanges = 1;
        }

        if ($hasChanges) {
            $entity->setName($name);
            $entity->setMetaTitle($metaTitle);
            $entity->setUrl($url);

            $this->entityManager->saveEntityWithoutLog($entity);
            $this->entityManager->refreshEntity($entity);
        }

        return $entity;
    }

    /**
     * @param $data
     * @param SRouteNotFoundEntity|null $sRouteNotFound
     * @param bool $skipLog
     * @return SRouteNotFoundEntity|null
     */
    public function insertUpdateRoute404($data, SRouteNotFoundEntity $sRouteNotFound = null, $skipLog = true){

        if (empty($sRouteNotFound)) {
            /** @var SRouteNotFoundEntity $sRouteNotFound */
            $sRouteNotFound = $this->entityManager->getNewEntityByAttributSetName("s_route_not_found");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($sRouteNotFound, $setter)) {
                $sRouteNotFound->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($sRouteNotFound);
        } else {
            $this->entityManager->saveEntity($sRouteNotFound);
        }
        $this->entityManager->refreshEntity($sRouteNotFound);

        return $sRouteNotFound;
    }

    /**
     * @param $data
     * @param SRouteEntity|null $sRoute
     * @param bool $skipLog
     * @return SRouteEntity|null
     */
    public function insertUpdateRoute($data, SRouteEntity $sRoute = null, $skipLog = true){

        if (empty($sRoute)) {
            /** @var SRouteEntity $sRouteNotFound */
            $sRoute = $this->entityManager->getNewEntityByAttributSetName("s_route");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($sRoute, $setter)) {
                $sRoute->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($sRoute);
        } else {
            $this->entityManager->saveEntity($sRoute);
        }
        $this->entityManager->refreshEntity($sRoute);

        return $sRoute;
    }

    /**
     * @param null $additionalCompositeFilter
     * @param null $sortFilters
     * @return mixed
     */
    public function getFilteredRoutes($additionalCompositeFilter = null, $sortFilters = null, $pagingFilter = null)
    {
        $et = $this->entityManager->getEntityTypeByCode("s_route");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalCompositeFilter)) {
            $compositeFilters->addCompositeFilter($additionalCompositeFilter);
        }



        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, $sortFilters, $pagingFilter);
    }

    /**
     * @param SRouteNotFoundEntity $sRouteNotFound
     * @return bool
     */
    public function deleteRoute404(SRouteNotFoundEntity $sRouteNotFound){

        if(empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }

        $this->entityManager->deleteEntityFromDatabase($sRouteNotFound);

        return true;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getRoute404ById($id){

        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(SRouteNotFoundEntity::class);
        return $repository->find($id);
    }

    /**
     * @param $requestUri
     * @param $storeId
     * @return null
     */
    public function getRoute404($requestUri, $storeId = null){

        $entityType = $this->entityManager->getEntityTypeByCode("s_route_not_found");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("requestUri", "eq", $requestUri));
        if(!empty($storeId)){
            $compositeFilter->addFilter(new SearchFilter("store.id", "eq", $storeId));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);

    }

    /**
     * @param $requestUri
     * @return array
     */
    public function checkDefaultRedirect($requestUri){

        $ret = Array();

        //https://pevex.hr/frontend/images/icons/favicons/android-icon-192x192.png
        if ((stripos($requestUri, "/apple-touch-icon-") !== false || stripos($requestUri, "/android-icon-") !== false) && stripos($requestUri, "icons/favicons") === false) {
            $tmp = explode("/",$requestUri);

            $ret["redirect_type"] = 301;
            $ret["redirect_url"] = "/frontend/images/icons/favicons/".end($tmp);
            $ret["query"] = "";
        }

        return $ret;
    }

    /**
     * @param $ids
     * @param $redirectTo
     * @return bool
     */
    public function bulkUpdateRoute404RedirectByIds($ids, $redirectTo){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE s_route_not_found_entity SET redirect_to = '{$redirectTo}', is_redirected = 1 WHERE id in (".implode(",",$ids).");";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param $ids
     * @return true
     * @throws \Exception
     */
    public function processSRoute404Redirect($ids = Array()){

        $ids = array_filter($ids);

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
        }

        $limitSettings = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode("google_api_limit");
        $limit = intval($limitSettings->getSettingsValue()[$_ENV["DEFAULT_STORE_ID"]]);

        if($limit <= 0){
            throw new \Exception("Google API limit exceeded");
        }

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $additionalWhere = "";
        if(!empty($ids)){
            $additionalWhere = " AND srn.id IN (".implode(",",$ids).") ";
        }

        $q = "SELECT srn.id, srn.request_uri, sw.base_url FROM s_route_not_found_entity as srn LEFT JOIN s_store_entity as ss ON srn.store_id = ss.id LEFT JOIN s_website_entity as sw ON ss.website_id = sw.id WHERE srn.number_of_requests > 1 AND srn.url_type = 'url' AND srn.is_redirected = 0 AND srn.google_search_console_processed = 0 {$additionalWhere} LIMIT {$limit};";
        $data = $this->databaseContext->getAll($q);

        if(empty($data)){
            return true;
        }

        if(empty($this->googleApiManager)){
            $this->googleApiManager = $this->getContainer()->get("google_api_manager");
        }

        $this->googleApiManager->initializeConnection();

        $numberOfQueries = 0;
        foreach ($data as $d){

            $numberOfQueries++;
            $result = null;

            $query = Array();
            $query["site_url"] = $_ENV["SSL"]."://".$d["base_url"]."/";
            $query["site_inspection_url"] = $d["request_uri"];

            try{
                $result = $this->googleApiManager->getGoogleSearchConsoleInspectUrlIndexRequest($query);
            }
            catch (\Exception $e){
                if(empty($this->errorLogManager)){
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }

                $this->errorLogManager->logExceptionEvent("Error getGoogleSearchConsoleInspectUrlIndexRequest",$e,true);
            }

            if(empty($result)){
                continue;
            }

            //if($result->getInspectionResult()->getIndexStatusResult()->getIndexingState() == "INDEXING_ALLOWED" && !empty($result->getInspectionResult()->getIndexStatusResult()->getLastCrawlTime())){
            if($result->getInspectionResult()->getIndexStatusResult()->getVerdict() == "PASS" || $result->getInspectionResult()->getIndexStatusResult()->getVerdict() == "FAIL"){
                $q = "UPDATE s_route_not_found_entity SET date_google_checked = NOW(), google_search_console_processed = 1, google_indexed = 1 WHERE id = {$d["id"]};";
                $this->databaseContext->executeQuery($q);
            }
            else{
                $q = "UPDATE s_route_not_found_entity SET date_google_checked = NOW(), google_search_console_processed = 1, google_indexed = 0 WHERE id = {$d["id"]};";
                $this->databaseContext->executeQuery($q);
            }
        }

        /**
         * Set new limit
         */
        $settingsValueArray[$_ENV["DEFAULT_STORE_ID"]] = $limit-$numberOfQueries;
        $limitSettingsData["settings_value"] = $settingsValueArray;
        $this->applicationSettingsManager->createUpdateSettings($limitSettings,$limitSettingsData);

        return true;
    }

    /**
     * @param $ids
     * @return true|void
     * @throws \Exception
     */
    public function processSRoute($ids = Array()){

        $ids = array_filter($ids);

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
        }

        $limitSettings = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode("google_api_limit");
        $limit = intval($limitSettings->getSettingsValue()[$_ENV["DEFAULT_STORE_ID"]]);

        if($limit <= 0){
            return true;
        }

        /*$additionalWhere = "";
        if(!empty($ids)){
            $additionalWhere = " AND srn.id IN (".implode(",",$ids).") ";
        }*/

        $compositeFilter = null;

        if(!empty($ids)){
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",",$ids)));
        }

        //todo izbit redirectove

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "desc"));

        $pagingFilter = new PagingFilter();
        $pagingFilter->setPageNumber(0);
        $pagingFilter->setPageSize($limit);

        $routes = $this->getFilteredRoutes($compositeFilter, $sortFilters, $pagingFilter);

        /*$q = "SELECT srn.id, srn.request_uri, sw.base_url FROM s_route_entity as srn LEFT JOIN s_store_entity as ss ON srn.store_id = ss.id LEFT JOIN s_website_entity as sw ON ss.website_id = sw.id WHERE srn.redirect_to is null AND srn.google_search_console_processed = 0 {$additionalWhere} ORDER BY id DESC LIMIT {$limit};";
        $data = $this->databaseContext->getAll($q);*/

        if(empty($routes)){
            return true;
        }

        if(empty($this->googleApiManager)){
            $this->googleApiManager = $this->getContainer()->get("google_api_manager");
        }

        $this->googleApiManager->initializeConnection();

        $numberOfQueries = 0;
        $now = new \DateTime();

        if (empty($this->defaultScommerceManager)) {
            $this->defaultScommerceManager = $this->container->get("scommerce_manager");
        }

        /** @var SRouteEntity $sRoute */
        foreach ($routes as $sRoute){

            if(!empty($sRoute->getDateGoogleChecked()) && $sRoute->getDateGoogleChecked()->diff($now)->days < 2){
                continue;
            }

            $destination = $this->getDestinationByRoute($sRoute);
            if(empty($destination)){
                continue;
            }

            $destinationCheck = $this->defaultScommerceManager->validateDestination($destination, $sRoute->getStore()->getId(), false);
            if (isset($destinationCheck["redirect_type"])) {
                continue;
            }

            $numberOfQueries++;
            $result = null;

            //todo ipak moramo znati da li je multilang

            $urlPath = $destination->getUrlPath($sRoute->getStoreId());
            $websiteUrl = $_ENV["SSL"]."://".$sRoute->getStore()->getWebsite()->getBaseUrl()."/";
            //todo fali lang

            $query = Array();
            $query["site_url"] = $websiteUrl;
            $query["site_inspection_url"] = $websiteUrl.$urlPath;

            try{
                $result = $this->googleApiManager->getGoogleSearchConsoleInspectUrlIndexRequest($query);
            }
            catch (\Exception $e){
                if(empty($this->errorLogManager)){
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }

                $this->errorLogManager->logExceptionEvent("Error getGoogleSearchConsoleInspectUrlIndexRequest",$e,true);
            }

            if(empty($result)){
                continue;
            }

            $sRouteData = Array();
            $sRouteData["google_search_console_processed"] = 1;
            $sRouteData["date_google_checked"] = new \DateTime();
            $sRouteData["google_index_verdict"] = $result->getInspectionResult()->getIndexStatusResult()->getVerdict();
            $sRouteData["google_coverage_state"] = $result->getInspectionResult()->getIndexStatusResult()->getCoverageState();
            $sRouteData["google_canonical"] = $result->getInspectionResult()->getIndexStatusResult()->getGoogleCanonical();
            $sRouteData["google_indexing_state"] = $result->getInspectionResult()->getIndexStatusResult()->getIndexingState();
            if(!empty($result->getInspectionResult()->getIndexStatusResult()->getLastCrawlTime())){
                $sRouteData["google_last_crawl_time"] = \DateTime::createFromFormat("Y-m-d\TH:i:s\Z",$result->getInspectionResult()->getIndexStatusResult()->getLastCrawlTime());
            }
            $sRouteData["google_page_fetch_state"] = $result->getInspectionResult()->getIndexStatusResult()->getPageFetchState();
            $sRouteData["google_robots_txt_state"] = $result->getInspectionResult()->getIndexStatusResult()->getRobotsTxtState();
            $sRouteData["google_user_canonical"] = $result->getInspectionResult()->getIndexStatusResult()->getUserCanonical();
            $sRouteData["google_mobile_verdict"] = $result->getInspectionResult()->getMobileUsabilityResult()->getVerdict();
            $sRouteData["google_mobile_issues"] = json_encode($result->getInspectionResult()->getMobileUsabilityResult()->getIssues());
            $sRouteData["google_rich_result_verdict"] = $result->getInspectionResult()->getRichResultsResult()->getVerdict();
            $sRouteData["google_rich_result_issues"] = json_encode($result->getInspectionResult()->getRichResultsResult()->getDetectedItems());

            $this->insertUpdateRoute($sRouteData,$sRoute);
        }

        /**
         * Set new limit
         */
        $settingsValueArray[$_ENV["DEFAULT_STORE_ID"]] = $limit-$numberOfQueries;
        $limitSettingsData["settings_value"] = $settingsValueArray;
        $this->applicationSettingsManager->createUpdateSettings($limitSettings,$limitSettingsData);

        return true;


    }
}
