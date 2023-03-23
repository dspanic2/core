<?php

namespace ScommerceBusinessBundle\Controller;

use CrmBusinessBundle\Entity\ProductGroupEntity;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use ScommerceBusinessBundle\Entity\SProductSearchResultsEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ScommerceBusinessBundle\Managers\AlgoliaManager;
use ScommerceBusinessBundle\Managers\BlogManager;
use ScommerceBusinessBundle\Managers\DefaultScommerceManager;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Managers\SsearchManager;
use ScommerceBusinessBundle\Managers\TrackingManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ProductListController extends AbstractScommerceController
{
    /** @var AlgoliaManager $algoliaManager */
    protected $algoliaManager;
    /** @var ProductGroupManager */
    protected $productGroupManager;
    /** @var BlogManager $blogManager */
    protected $blogManager;
    /** @var TrackingManager $trackingManager */
    protected $trackingManager;
    /** @var DefaultScommerceManager $defaultScommerceManager */
    protected $defaultScommerceManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;
    private $searchCacheHours;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->initializeTwigVariables($request);
        $this->searchCacheHours = $_ENV["SEARCH_CACHE_H"] ?? 4;
    }

    /**
     * @Route("/get_products", name="get_products")
     * @Method("POST")
     */
    public function getProductsAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;
        $noIndex = false;

        $session = $request->getSession();

        if (empty($this->productGroupManager)) {
            $this->productGroupManager = $this->getContainer()->get("product_group_manager");
        }

        $isSearch = false;
        $isCustom = false;
        $productGroup = null;
        $sessionSufix = "";

        if (isset($p["s"]) && !empty($p["s"])) {
            $isSearch = true;
            $sessionSufix = "search";
        } elseif (isset($p["product_group"]) && !empty($p["product_group"])) {
            $sessionSufix = $p["product_group"];
        } elseif (isset($p["c"]) && !empty($p["c"])) {
            $isCustom = true;
            $sessionSufix = basename($request->headers->get('referer'));
        } else {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Invalid query")));
        }

        if (!isset($p["product_group"])) {
            $p["product_group"] = null;
        }

        if (!isset($p["get_all_products"])) {
            $p["get_all_products"] = 1;
        }
        /** Defaults */

        if (!isset($p["page_number"]) || empty($p["page_number"])) {
            $p["page_number"] = 1;
        }

        if (!isset($p["sort_dir"]) || empty($p["sort_dir"])) {
            $p["sort_dir"] = "desc";
        }
        if (!empty($session->get("product_group_sort_dir_{$sessionSufix}")) && $session->get("product_group_sort_dir_{$sessionSufix}") !== $p["sort_dir"]) {
            $noIndex = true;
        }
        $session->set("product_group_sort_dir_{$sessionSufix}", $p["sort_dir"]);

        $p["filter"] = null;

        if (!isset($p["f"])) {
            $p["f"] = null;
        } else {
            $p["filter"] = $this->productGroupManager->prepareFilterParams($p, "f");
        }

        /**
         * Kad se sjetimo cemu sluzi
         */
        //$wasSorted = true;
        if (!isset($p["sort"])) {
            $p["sort"] = null;
            /**
             * Kad se sjetimo cemu sluzi
             */
            //$wasSorted = false;
        }
        if (!empty($session->get("product_group_sort_{$sessionSufix}")) && $session->get("product_group_sort_{$sessionSufix}") !== $p["sort"]) {
            $noIndex = true;
        }
        $session->set("product_group_sort_{$sessionSufix}", $p["sort"]);

        /**
         * Get default page sizes
         */
        $defaultPageSize = $_ENV["PRODUCT_GRID_DEFAULT_PAGE_SIZE"];
        $availablePageSizes = explode(",", $_ENV["PRODUCT_GRID_AVAILABLE_PAGE_SIZE"]);

        if (!isset($p["page_size"]) || empty($p["page_size"])) {
            $p["page_size"] = $defaultPageSize;
        } else {
            if (!in_array($defaultPageSize, $availablePageSizes)) {
                $p["page_size"] = $defaultPageSize;
            }
        }
        if (!empty($session->get("product_group_page_size_{$sessionSufix}")) && $session->get("product_group_page_size_{$sessionSufix}") !== $p["page_size"]) {
            $noIndex = true;
        }
        $session->set("product_group_page_size_{$sessionSufix}", $p["page_size"]);

        $p["get_filter"] = true;
        $originalQuery = null;

        $params = $this->productGroupManager->prepareFilterParams($p, "s", "f");

        if (!empty($params)) {
            $p["additional_pre_filter"] = $this->productGroupManager->prepareAdditionalPrefilter($params);
        }

        if ($isSearch) {

            $p["ids"] = null;
            if (array_key_exists("keyword", $params)) {

                $fromCache = 0;

                $searchResultSortIds = json_decode($_ENV["SEARCH_RESULT_SORT_IDS"], true);

                /** @var SsearchManager $sSearchManager */
                $sSearchManager = $this->getContainer()->get("s_search_manager");

                $originalQuery = $this->productGroupManager->cleanSearchParams($params["keyword"][0]);
                $query = $sSearchManager->prepareQuery($params["keyword"][0]);
                $query = $this->productGroupManager->cleanSearchParams($query);

                if (empty($query)) {
                    return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Empty query")));
                }

                /** @var SProductSearchResultsEntity $search */
                $search = $sSearchManager->getExistingSearchResult($query, $session->get("current_store_id"));

                $date = new \DateTime();
                $date->modify("-" . $this->searchCacheHours . " hour");

                if (!empty($search) && $search->getLastRegenerateDate() > $date && floatval($this->searchCacheHours) > 0) {
                    $p["ids"] = json_decode($search->getListOfResults(), true);
                    $fromCache = 1;
                } else {
                    $p["ids"] = $this->productGroupManager->searchProducts($query, $p);

                    /** @var SProductSearchResultsEntity $search */
                    $search = $sSearchManager->createSearch($originalQuery, $query, $session->get("current_store_id"), $search);

                    $search->setTimesUsed(intval($search->getTimesUsed()) + 1);
                    $search->setNumberOfResults(count($p["ids"]));
                    $search->setListOfResults(json_encode($p["ids"]));

                    $sSearchManager->save($search);
                }

                if (empty($this->trackingManager)) {
                    $this->trackingManager = $this->getContainer()->get("tracking_manager");
                }

                $this->trackingManager->insertSearch($originalQuery, $query, count($p["ids"]), json_encode($p["ids"]), $fromCache, $session->getId(), $session->get("current_store_id"));

                unset($params["keyword"]);
            } else {
                if (isset($_ENV["ADVANCED_SEARCH_RESULT_SORT_IDS"]) && !empty($_ENV["ADVANCED_SEARCH_RESULT_SORT_IDS"])) {
                    $searchResultSortIds = json_decode($_ENV["ADVANCED_SEARCH_RESULT_SORT_IDS"], true);
                } else {
                    $searchResultSortIds = json_decode($_ENV["SEARCH_RESULT_SORT_IDS"], true);
                }
            }

            if (!isset($p["sort"]) || empty($p["sort"])) {
                $p["sort"] = $searchResultSortIds[0];
            }

            $sortOptions = $this->productGroupManager->getSortOptions($p["sort"], null, $searchResultSortIds);
            foreach ($sortOptions as $sortOption) {
                if ($sortOption["selected"]) {
                    $p["sort"] = $sortOption["sort"];
                    break;
                }
            }

            /**
             * IF no keyword, only params
             */
            if (!empty($params)) {
                if (!isset($p["filter"])) {
                    $p["filter"] = array();
                }
                $p["filter"] = array_merge($p["filter"], $params);
            }

            $ret = array();
            $ret["error"] = false;
            $ret["total"] = 0;
            $ret["filter_data"] = array();
            $ret['entities'] = array();
            if (!empty($p["ids"]) || (isset($p["additional_pre_filter"]) && !empty($p["additional_pre_filter"]))) {
                $ret = $this->productGroupManager->getFilteredProducts($p);
            }

        } elseif ($isCustom) {
            $sortOptions = $this->productGroupManager->getSortOptions($p["sort"]);
            foreach ($sortOptions as $sortOption) {
                if ($sortOption["selected"]) {
                    $p["sort"] = $sortOption["sort"];
                    break;
                }
            }

            /**
             * Kad se sjetimo cemu sluzi
             */
            //$p["was_sorted"] = $wasSorted;
            $ret = $this->productGroupManager->getFilteredProducts($p);
        } else {
            /** @var ProductGroupEntity $productGroup */
            $productGroup = $this->productGroupManager->getProductGroupById($p["product_group"]);
            if (empty($productGroup)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Product group does not exist")));
            }

            $sortOptions = $this->productGroupManager->getSortOptions($p["sort"], $productGroup);
            foreach ($sortOptions as $sortOption) {
                if ($sortOption["selected"]) {
                    $p["sort"] = $sortOption["sort"];
                    break;
                }
            }

            /**
             * Kad se sjetimo cemu sluzi
             */
            //$p["was_sorted"] = $wasSorted;
            $ret = $this->productGroupManager->getFilteredProducts($p);
        }

        if (!isset($ret["show_index"])) {
            $ret["show_index"] = false;
        }
        if (!isset($ret["index"]) || $noIndex) {
            $ret["index"] = 0;
        }
        if (!isset($ret["facet_title"])) {
            $ret["facet_title"] = "";
        }
        if (!isset($ret["facet_meta_title"])) {
            $ret["facet_meta_title"] = "";
        }
        if (!isset($ret["facet_meta_description"])) {
            $ret["facet_meta_description"] = "";
        }
        if (!isset($ret["facet_canonical"])) {
            $ret["facet_canonical"] = "";
        }

        if (!empty($ret["product_groups"])) {
            $p["product_groups"] = $ret["product_groups"];
        }

        $filterHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:product_list_filters.html.twig", $session->get("current_website_id")), [
            'primary_filters' => $ret["filter_data"]['primary'] ?? null,
            'secondary_filters' => $ret["filter_data"]['secondary'] ?? null,
            'additional' => $ret["filter_data"]['additional'] ?? null,
            'index' => $ret["index"],
            'product_group' => $productGroup ?? null,
            'data' => $p
        ]);

        $pagerHtml = '';
        $sortHtml = '';
        $hasNextPage = false;

        $referer["page"]["url"] = $request->headers->get('referer');

        if (isset($referer["page"]["url"]) && !empty($referer["page"]["url"])) {
            $referer["page"]["url"] = explode(".hr/", $referer["page"]["url"]);
            if (isset($referer["page"]["url"][1])) {
                $referer["page"]["url"] = $referer["page"]["url"][1];
                $referer["page"]["url"] = str_replace('&', '|||', $referer["page"]["url"]);
            } else {
                $referer["page"]["url"] = null;
            }
        }

        $displayList = 0;
        if (!empty($productGroup)) {
            $displayList = $productGroup->getListViewDisplay();
        }

        if (!isset($sortOptions) || empty($sortOptions)) {
            $sortOptions = $this->productGroupManager->getSortOptions($p["sort"] . "_" . $p["sort_dir"], $productGroup);
        }
        $pageSizeOptions = $this->productGroupManager->preparePageSizeOptions($availablePageSizes, $p["page_size"]);
        $sortHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:product_list_sorting.html.twig", $session->get("current_website_id")), [
            'sort_options' => $sortOptions,
            'page_size_options' => $pageSizeOptions,
            'is_search' => $isSearch,
            'keyword' => $originalQuery,
            'total' => $ret["total"],
            'display_list' => $displayList,
            'page_number' => $p["page_number"],
        ]);
        if (!empty($ret['entities'])) {
            $hasNextPage = $this->productGroupManager->calculateIfNextPageExists($p, $ret["total"]);

            $html = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:product_list.html.twig", $session->get("current_website_id")), [
                'products' => $ret['entities'],
                'data' => $referer,
                'product_group' => $productGroup ?? null
            ]);
            $pagerHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:product_list_pager.html.twig", $session->get("current_website_id")), [
                'page_size_options' => $pageSizeOptions,
                'total' => $ret["total"],
                'page_number' => $p["page_number"],
                'has_next_page' => $hasNextPage,
            ]);

            if (empty($this->getPageUrlExtension)) {
                $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
            }

            $returnJavascript = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Tracking:product_group_impressions.html.twig", $session->get("current_website_id")), array(
                'products' => $ret['entities'],
                'list_name' => $isSearch ? $this->translator->trans("Search page") : (!empty($productGroup) ? $this->translator->trans("Category") . " " . $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $productGroup, "name") : ""),
                'list_id' => $isSearch ? 51 : (!empty($productGroup) ? $productGroup->getId() : ""),
            ));
        } else {
            if ($isSearch) {
                $html = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:no_results_search.html.twig", $session->get("current_website_id")), [
                    'term' => $p["keyword"] ?? '',
                ]);
            } else {
                $html = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:no_results_list.html.twig", $session->get("current_website_id")), []);
            }
            $returnJavascript = $this->renderView($this->templateManager->getTemplatePathByBundle("Components/Tracking:no_results.html.twig", $session->get("current_website_id")), array());
        }

        $returnData = array(
            'error' => $ret["error"],
            'total' => $ret["total"],
            'grid_html' => $html,
            'pager_html' => $pagerHtml,
            'filter_html' => $filterHtml,
            'sort_html' => $sortHtml,
            'has_next_page' => $hasNextPage,
            'keyword' => $originalQuery,
            'show_index' => $ret["show_index"],
            'index' => $ret["index"],
            'facet_title' => $ret["facet_title"],
            'facet_meta_title' => $ret["facet_meta_title"],
            'facet_meta_description' => $ret["facet_meta_description"],
            'facet_canonical' => $ret["facet_canonical"],
            'javascript' => $returnJavascript,
        );
        if (isset($_ENV["SHOW_ACTIVE_FILTERS"]) && !empty($_ENV["SHOW_ACTIVE_FILTERS"])) {
            $returnData["active_filters"] = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:product_list_active_filters.html.twig", $session->get("current_website_id")), [
                'primary_filters' => $ret["filter_data"]['primary'] ?? null,
                'secondary_filters' => $ret["filter_data"]['secondary'] ?? null,
                'additional' => $ret["filter_data"]['additional'] ?? null,
                'index' => $ret["index"]
            ]);
        }

        if (empty($this->defaultScommerceManager)) {
            $this->defaultScommerceManager = $this->getContainer()->get("scommerce_manager");
        }

        $additionalReturnData = $this->defaultScommerceManager->additionalProductListControllerReturnData($ret);
        if (!empty($additionalReturnData)) {
            $returnData = array_merge($returnData, $additionalReturnData);
        }

        //ako je $ret["error"] = true
        //todo $html staviti error html
        //ovo se moze dogoditi samo ako netko proba otici na page 100 koji ne postoji
        return new JsonResponse($returnData);
    }

    /**
     * @Route("/search_products_autocomplete", name="search_products_autocomplete")
     * @Method("POST")
     */
    public function searchProductsAutocompleteAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;

        //todo save to session
        $session = $request->getSession();

        if (!isset($p["get_all_products"])) {
            $p["get_all_products"] = 0;
        }

        if (!isset($p["get_posts"])) {
            $p["get_posts"] = null;
        }

        if (!isset($p["get_categories"])) {
            $p["get_categories"] = null;
        }

        if (!isset($p["query"]) || empty($p["query"]) || strlen(trim($p["query"])) < $_ENV["MIN_SEARCH_LENGTH"] ?? 3) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Empty query")));
        }

        /** @var SsearchManager $sSearchManager */
        $sSearchManager = $this->getContainer()->get("s_search_manager");

        if (empty($this->productGroupManager)) {
            $this->productGroupManager = $this->getContainer()->get("product_group_manager");
        }

        $query = trim(urldecode($p["query"]));
        $originalQuery = $this->productGroupManager->cleanSearchParams($query);
        $query = $sSearchManager->prepareQuery($query);
        $query = $this->productGroupManager->cleanSearchParams($query);

        if (empty($query)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Empty query")));
        }

        /** @var SProductSearchResultsEntity $search */
        $search = $sSearchManager->getExistingSearchResult($query, $session->get("current_store_id"));

        $date = new \DateTime();
        $date->modify("-" . $this->searchCacheHours . " hour");

        if (!empty($search) && $search->getLastRegenerateDate() > $date && floatval($this->searchCacheHours) > 0) {
            $p["ids"] = json_decode($search->getListOfResults(), true);
        } else {
            $p["ids"] = $this->productGroupManager->searchProducts($query, $p);

            /** @var SProductSearchResultsEntity $search */
            $search = $sSearchManager->createSearch($originalQuery, $query, $session->get("current_store_id"), $search);
        }

        $search->setTimesUsed(intval($search->getTimesUsed()) + 1);
        $search->setNumberOfResults(count($p["ids"]));
        $search->setListOfResults(json_encode($p["ids"]));

        $sSearchManager->save($search);

        $ret = array();
        $ret["error"] = false;
        $ret["total"] = 0;
        $displayAll = true;

        if (!empty($p["ids"])) {
            if (!isset($p["page_number"]) || empty($p["page_number"])) {
                $p["page_number"] = 1;
            }

            $searchResultSortIds = json_decode($_ENV["SEARCH_RESULT_SORT_IDS"], true);

            if (!isset($p["sort"]) || empty($p["sort"])) {
                $p["sort"] = $searchResultSortIds[0];
            }

            $sortOptions = $this->productGroupManager->getSortOptions($p["sort"], null, $searchResultSortIds);
            foreach ($sortOptions as $sortOption) {
                if ($sortOption["selected"]) {
                    $p["sort"] = $sortOption["sort"];
                    break;
                }
            }

            $p["filter"] = null;

            if (!isset($p["f"])) {
                $p["f"] = null;
            } else {
                $p["filter"] = $this->productGroupManager->prepareFilterParams($p, "f");
            }
            if (!isset($p["page_size"])) {
                $p["page_size"] = 10;
            }
            $p["get_filter"] = false;

            $ret = $this->productGroupManager->getFilteredProducts($p);

            if (!empty($ret["entities"])) {
                $html = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Search:product_search_autocomplete.html.twig", $session->get("current_website_id")), [
                    'products' => $ret['entities'],
                    'query' => $query,
                ]);
            } else {
                $html = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:no_results_autocomplete.html.twig", $session->get("current_website_id")), [
                    'term' => $p["query"] ?? '',
                ]);
                $displayAll = false;
            }
        } else {
            $html = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:no_results_autocomplete.html.twig", $session->get("current_website_id")), [
                'term' => $p["query"] ?? '',
            ]);
            $displayAll = false;
        }

        $ret = array(
            'error' => $ret["error"],
            'total' => $ret["total"],
            'grid_html' => $html,
            'sort_html' => null,
            'query' => $p["query"],
            'display_show_all' => $displayAll,
            'keyword' => $originalQuery
        );

        $postsHtml = null;
        if ($p["get_posts"]) {

            if (empty($this->blogManager)) {
                $this->blogManager = $this->getContainer()->get("blog_manager");
            }

            $posts = $this->blogManager->searchPosts($query);

            $postsHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Search:product_search_autocomplete_connected.html.twig", $session->get("current_website_id")), [
                'posts' => $posts,
            ]);
        }

        $ret['posts_html'] = $postsHtml;

        $retProductGroups = $this->productGroupManager->searchProductGroups($query);

        $productGroupsHtml = null;
        if ($p["get_categories"]) {

            $productGroupsHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Search:product_search_autocomplete_suggestions.html.twig", $session->get("current_website_id")), [
                'brands' => $retProductGroups["brands"], 'product_groups' => $retProductGroups["product_groups"], 'suggestions' => $retProductGroups["suggestions"],
            ]);
        }

        $ret['product_groups_html'] = $productGroupsHtml;

        // split suggestions
        if (isset($p["get_product_groups"]) && $p["get_product_groups"]) {
            $ret['product_groups_html'] = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Search:product_search_autocomplete_product_groups.html.twig", $session->get("current_website_id")), [
                'product_groups' => $retProductGroups["product_groups"]
            ]);
        }
        if (isset($p["get_brands"]) && $p["get_brands"]) {
            $ret['brands_html'] = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Search:product_search_autocomplete_brands.html.twig", $session->get("current_website_id")), [
                'brands' => $retProductGroups["brands"]
            ]);
        }

        return new JsonResponse($ret);
    }
}
