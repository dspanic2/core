<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Context\DatabaseContext;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\XmlHelper;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use DateTime;
use DOMDocument;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Constants\ScommerceConstants;
use ScommerceBusinessBundle\Entity\BlogPostEntity;
use ScommerceBusinessBundle\Entity\FacetsEntity;
use ScommerceBusinessBundle\Entity\SProductAttributeConfigurationEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Entity\SWebsiteEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;

class SitemapManager extends AbstractScommerceManager
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;
    /** @var FacetManager $facetManager */
    protected $facetManager;

    protected $session;


    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $store_id
     * @return array|mixed[]
     */
    public function getRoutes($store_id)
    {
        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        return $this->routeManager->getRoutesForSitemap($store_id);
    }

    /**
     * @return bool
     */
    public function generateSitemapXML()
    {
        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }
        $etProductGroup = $this->entityManager->getEntityTypeByCode("product_group");
        $etBlogPost = $this->entityManager->getEntityTypeByCode("blog_post");
        //$etSPage = $this->entityManager->getEntityTypeByCode("s_page");
        //$etProduct = $this->entityManager->getEntityTypeByCode("product");
        //$etBlogCategory = $this->entityManager->getEntityTypeByCode("blog_category");

        if(empty($this->facetManager)){
            $this->facetManager = $this->getContainer()->get("facet_manager");
        }

        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }

        if (empty($this->productGroupManager)) {
            $this->productGroupManager = $this->container->get("product_group_manager");
        }
        $dir = $_ENV["WEB_PATH"] . "/xml/";

        if (!file_exists($dir)) {
            if (!mkdir($dir, 0777, true)) {
                return false;
            }
        }

        $now = new DateTime();

        $websites = $this->routeManager->getWebsites();

        if (empty($websites)) {
            return true;
        }

        /** @var SWebsiteEntity $website */
        foreach ($websites as $website) {

            $stores = $website->getStores();
            $websiteData = $this->routeManager->getWebsiteDataById($website->getId());

            if (!EntityHelper::isCountable($stores) || count($stores) == 0) {
                continue;
            }

            /** @var SStoreEntity $store */
            foreach ($stores as $store) {

                $groupFile = "sitemap_{$store->getId()}.xml";

                $groupXml = new XmlHelper('<?xml version="1.0" encoding="utf-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>');

                $base = $_ENV["SSL"] . "://" . $websiteData["base_url"] . $this->routeManager->getLanguageUrl($store) . "/";
                $baseToXml = $_ENV["SSL"] . "://" . $websiteData["base_url"] . "/xml/";

                $routes = $this->getRoutes($store->getId());

                $xml = new XmlHelper('<?xml version="1.0" encoding="utf-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

                $count = 0;
                $limit = 30000;
                $file_addon = 1;

                $file = "sitemap_{$store->getId()}_{$file_addon}.xml";

                /**
                 * Generate ursl for facets
                 */

                $facets = $this->facetManager->getAllFacets();

                if (EntityHelper::isCountable($facets) && count($facets)) {
                    /** @var FacetsEntity $facet */
                    foreach ($facets as $facet) {
                        $facetConfigurations = $this->facetManager->generateFacetUrls($facet, $store);

                        if (!empty($facetConfigurations)) {
                            $changefreq = "daily";
                            $priority = 1.0;
                            //$loc = $route["request_url"];

                            foreach ($facetConfigurations as $facetConfiguration) {
                                if(!$facetConfiguration["is_valid"]){
                                    continue;
                                }
                                $xmlUrl = $xml->addChild('url');
                                $child = $xmlUrl->addChild('loc');
                                $url = str_ireplace("%26", "&", $facetConfiguration["url"]);
                                $child->addCData($base . $url);
                                /*$xmlUrl->addChild('changefreq', $changefreq);
                                $xmlUrl->addChild('priority', $priority);*/
                            }
                        }
                    }
                }

                /**
                 * @var int $key
                 * @var array $route
                 */
                foreach ($routes as $route) {
                    $aditionalLoc = [];

                    /**
                     * Defaults
                     */
                    $changefreq = "monthly";
                    $priority = 0.5;
                    $loc = $route["request_url"];

                    /**
                     * Avoid double slash on base url
                     */
                    if (strcmp(substr($loc, 0, 1), "/") == 0) {
                        $loc = "";
                    }


                    if ($route["destination_type"] == "product_group") {
                        /** @var ProductGroupEntity $productGroup */
                        $productGroup = $this->entityManager->getEntityByEntityTypeAndId($etProductGroup, $route["destination_id"]);
                        /*if (empty($productGroup) || $productGroup->getEntityStateId() != 1) {
                            continue;
                        }*/
                        $parents = $this->productGroupManager->getParentsTree($productGroup);

                        /** @var ProductGroupEntity $parent */
                        foreach ($parents as $parent) {
                            $loc = $this->getPageUrlExtension->getEntityStoreAttribute($store->getId(), $parent, "url") . "/" . $loc;
                        }

                        if ($productGroup->getProductsInGroup() > $_ENV["PRODUCT_GRID_DEFAULT_PAGE_SIZE"] && $productGroup->getTemplateTypeId() == ScommerceConstants::DEFAULT_PRODUCT_GROUP_TEMPLATE_ID) {
                            for ($i = 2; $i <= round($productGroup->getProductsInGroup() / $_ENV["PRODUCT_GRID_DEFAULT_PAGE_SIZE"]); $i++) {
                                $aditionalLoc[] = $loc . "?page_number=" . $i;
                            }
                        }

                        $changefreq = "daily";
                        $priority = 1.0;
                    } elseif ($route["destination_type"] == "blog_post") {

                        /** @var BlogPostEntity $blogPost */
                        $blogPost = $this->entityManager->getEntityByEntityTypeAndId($etBlogPost, $route["destination_id"]);

                        if (empty($blogPost) || $blogPost->getEntityStateId() != 1) {
                            continue;
                        }

                        $loc = $this->getPageUrlExtension->getEntityStoreAttribute($store->getId(), $blogPost->getBlogCategory(), "url") . "/" . $loc;

                        $changefreq = "monthly";
                        $priority = 0.5;
                    } elseif ($route["destination_type"] == "s_page") {
                        /*$sPage = $this->entityManager->getEntityByEntityTypeAndId($etSPage, $route["destination_id"]);

                        if (empty($sPage) || $sPage->getEntityStateId() != 1) {
                            continue;
                        }*/
                        $changefreq = "yearly";
                        $priority = 0.2;
                    } elseif ($route["destination_type"] == "product") {
                        /*$product = $this->entityManager->getEntityByEntityTypeAndId($etProduct, $route["destination_id"]);

                        if (empty($product) || $product->getEntityStateId() != 1) {
                            continue;
                        }*/
                        $changefreq = "weekly";
                        $priority = 1.0;
                    } elseif ($route["destination_type"] == "blog_category") {
                        /*$blogCategory = $this->entityManager->getEntityByEntityTypeAndId($etBlogCategory, $route["destination_id"]);

                        if (empty($blogCategory) || $blogCategory->getEntityStateId() != 1) {
                            continue;
                        }*/
                        $changefreq = "weekly";
                        $priority = 0.5;
                    }

                    $count++;

                    $xmlUrl = $xml->addChild('url');
                    $xmlUrl->addChild('loc', $base . $loc);
                    //$xmlUrl->addChild('changefreq', $changefreq);
                    //$xmlUrl->addChild('priority', $priority);
                    //$xmlUrl->addChild('lastmod', $route["modified"]->getModified()->format(DateTime::W3C));

                    if (!empty($aditionalLoc)) {
                        foreach ($aditionalLoc as $page) {
                            $xmlUrl = $xml->addChild('url');
                            //$xmlUrl->addChild('loc', htmlspecialchars($base . $page));
                            $child = $xmlUrl->addChild('loc');
                            $child->addCData($base . $page);
                            //$xmlUrl->addChild('loc', $base . $page);
                            //$xmlUrl->addChild('changefreq', $changefreq);
                            //$xmlUrl->addChild('priority', $priority);
                            //$xmlUrl->addChild('lastmod', $route["modified"]->format(DateTime::W3C));
                            $count++;
                        }
                    }

                    if ($count >= $limit) {
                        /**
                         * Beautify XML using DOM
                         */
                        $dom = new DOMDocument("1.0");
                        $dom->preserveWhiteSpace = false;
                        $dom->formatOutput = true;
                        $dom->loadXML($xml->asXML());
                        $count = 0;

                        if (file_exists($dir . $file)) {
                            unlink($dir . $file);
                        }

                        $this->helperManager->saveRawDataToFile($dom->saveXML(), $dir . $file);

                        /**
                         * Add to grouped file
                         */
                        $sitemap = $groupXml->addChild('sitemap');
                        $sitemap->addChild('loc', $baseToXml . $file);
                        $sitemap->addChild('lastmod', $now->format("Y-m-d"));

                        /**
                         * Start new batch
                         */
                        $xml = new XmlHelper('<?xml version="1.0" encoding="utf-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

                        $file_addon++;
                        $file = "sitemap_{$store->getId()}_{$file_addon}.xml";
                    }
                }

                if ($count > 0) {
                    /**
                     * Beautify XML using DOM
                     */
                    $dom = new DOMDocument("1.0");
                    $dom->preserveWhiteSpace = false;
                    $dom->formatOutput = true;
                    $dom->loadXML($xml->asXML());
                    $count = 0;

                    if (file_exists($dir . $file)) {
                        unlink($dir . $file);
                    }

                    $this->helperManager->saveRawDataToFile($dom->saveXML(), $dir . $file);

                    $sitemap = $groupXml->addChild('sitemap');
                    $sitemap->addChild('loc', $baseToXml . $file);
                    $sitemap->addChild('lastmod', $now->format("Y-m-d"));
                }

                /**
                 * Save grouped XML
                 */

                /**
                 * Beautify XML using DOM
                 */
                $dom = new DOMDocument("1.0");
                $dom->preserveWhiteSpace = false;
                $dom->formatOutput = true;
                $dom->loadXML($groupXml->asXML());

                if (file_exists($dir . $groupFile)) {
                    unlink($dir . $groupFile);
                }

                $this->helperManager->saveRawDataToFile($dom->saveXML(), $dir . $groupFile);
            }
        }

        return true;
    }
}
