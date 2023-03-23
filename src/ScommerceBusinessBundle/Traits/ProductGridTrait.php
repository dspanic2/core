<?php

namespace ScommerceBusinessBundle\Traits;

use CrmBusinessBundle\Entity\ProductGroupEntity;
use ScommerceBusinessBundle\Entity\SProductSearchResultsEntity;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Managers\SsearchManager;
use ScommerceBusinessBundle\Managers\TrackingManager;

trait ProductGridTrait
{
    /** @var ProductGroupManager */
    private $productGroupManager;
    /** @var SsearchManager */
    private $sSearchManager;
    /** @var TrackingManager */
    private $trackingManager;
    private $twig;
    private $searchCacheHours = 4;

    /**
     * @param array $p
     * @param ProductGroupEntity|null $productGroup
     * @param bool $getFiltersOnly
     * @param bool $getFacetDataOnly
     * @return array
     * @throws \Exception
     */
    protected function getProductGridHtmlData($p, ProductGroupEntity $productGroup = null, $getFiltersOnly = false, $getFacetDataOnly = false)
    {
        $data = [];

        $session = $this->getContainer()->get('session');

        if (empty($this->productGroupManager)) {
            $this->productGroupManager = $this->getContainer()->get("product_group_manager");
        }
        if (empty($this->twig)) {
            $this->twig = $this->getContainer()->get("templating");
        }

        if (isset($_ENV["SEARCH_CACHE_H"]) && !empty($_ENV["SEARCH_CACHE_H"])) {
            $this->searchCacheHours = $_ENV["SEARCH_CACHE_H"];
        }

        $defaultPageSize = $_ENV["PRODUCT_GRID_DEFAULT_PAGE_SIZE"];
        $availablePageSizes = explode(",", $_ENV["PRODUCT_GRID_AVAILABLE_PAGE_SIZE"]);
        $isSearch = false;
        $originalQuery = null;
        $displayList = 0;
        $noIndex = false;
        $isCustom = false;

        if (isset($p["s"]) && !empty($p["s"])) {
            $isSearch = true;
            $sessionSufix = "search";
        } elseif (!empty($productGroup)) {
            $p["product_group"] = $productGroup->getId();
            $sessionSufix = $p["product_group"];
        } else {
            $isCustom = true;
            $request = $this->container->get('request_stack')->getCurrentRequest();
            $sessionSufix = basename($request->headers->get('referer'));
        }

        // Default page number
        if (!isset($p["page_number"]) || empty($p["page_number"])) {
            $p["page_number"] = 1;
        }

        // Default sort dir
        if (!isset($p["sort_dir"]) || empty($p["sort_dir"])) {
            $p["sort_dir"] = "desc";
        }
        if (!empty($session->get("product_group_sort_dir_{$sessionSufix}")) && $session->get("product_group_sort_dir_{$sessionSufix}") !== $p["sort_dir"]) {
            $noIndex = true;
        }
        $session->set("product_group_sort_dir_{$sessionSufix}", $p["sort_dir"]);

        // Default sort
        if (!isset($p["sort"])) {
            $p["sort"] = null;
        }
        if (!empty($session->get("product_group_sort_{$sessionSufix}")) && $session->get("product_group_sort_{$sessionSufix}") !== $p["sort"]) {
            $noIndex = true;
        }
        $session->set("product_group_sort_{$sessionSufix}", $p["sort"]);

        // Default filter
        if (!isset($p["f"])) {
            $p["f"] = null;
        } else {
            $p["filter"] = $this->productGroupManager->prepareFilterParams($p, "f");
        }

        // Default page size
        if (empty($p["page_size"])) {
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

        $params = $this->productGroupManager->prepareFilterParams($p, "s", "f");
        if (!empty($params)) {
            $p["additional_pre_filter"] = $this->productGroupManager->prepareAdditionalPrefilter($params);
        }

        if (!empty($productGroup)) {
            $displayList = $productGroup->getListViewDisplay();
        }

        if ($isSearch) {
            $p["ids"] = null;
            if (array_key_exists("keyword", $params)) {
                $fromCache = 0;

                $searchResultSortIds = json_decode($_ENV["SEARCH_RESULT_SORT_IDS"], true);

                if (empty($this->sSearchManager)) {
                    $this->sSearchManager = $this->getContainer()->get("s_search_manager");
                }

                $originalQuery = $this->productGroupManager->cleanSearchParams($params["keyword"][0]);
                $query = $this->sSearchManager->prepareQuery($params["keyword"][0]);
                $query = $this->productGroupManager->cleanSearchParams($query);

                if (empty($query)) {
                    return $data;
                }

                /** @var SProductSearchResultsEntity $search */
                $search = $this->sSearchManager->getExistingSearchResult($query, $session->get("current_store_id"));

                $date = new \DateTime();
                $date->modify("-" . $this->searchCacheHours . " hour");

                if (!empty($search) && $search->getLastRegenerateDate() > $date) {
                    $p["ids"] = json_decode($search->getListOfResults(), true);
                    $fromCache = 1;
                } else {
                    $p["ids"] = $this->productGroupManager->searchProducts($query, $p);

                    /** @var SProductSearchResultsEntity $search */
                    $search = $this->sSearchManager->createSearch($originalQuery, $query, $session->get("current_store_id"), $search);

                    $search->setTimesUsed(intval($search->getTimesUsed()) + 1);
                    $search->setNumberOfResults(count($p["ids"]));
                    $search->setListOfResults(json_encode($p["ids"]));

                    $this->sSearchManager->save($search);
                }

                /**
                 * Ovo je za preko searcha
                 */
                /*$retProductGroups = $this->productGroupManager->searchProductGroups($query);
                dump($retProductGroups);
                die;*/

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
            if (isset($p["default_sort"]) && empty($p["sort"])) {
                $p["sort"] = $p["default_sort"];
            }

            $sortOptions = $this->productGroupManager->getSortOptions($p["sort"]);
            foreach ($sortOptions as $sortOption) {
                if ($sortOption["selected"]) {
                    $p["sort"] = $sortOption["sort"];
                    break;
                }
            }

            $ret = $this->productGroupManager->getFilteredProducts($p);
        } else {
            if (empty($productGroup)) {
                return $data;
            }

            $sortOptions = $this->productGroupManager->getSortOptions($p["sort"], $productGroup);
            foreach ($sortOptions as $sortOption) {
                if ($sortOption["selected"]) {
                    $p["sort"] = $sortOption["sort"];
                    break;
                }
            }

            $ret = $this->productGroupManager->getFilteredProducts($p);
        }

        if (!isset($ret["show_index"])) {
            $ret["show_index"] = false;
        }
        if (!isset($ret["index"]) || $noIndex) {
            $ret["index"] = 0;
        }

        if (!isset($ret["index"]) || $noIndex) {
            $ret["index"] = 0;
        }

        $data["entities"] = $ret['entities'] ?? [];
        $data["total"] = $ret["total"] ?? 0;

        if (!empty($ret["product_groups"])) {
            $data["product_groups"] = $ret["product_groups"];
        }

        if ($getFiltersOnly) {
            $filterHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:product_list_filters.html.twig", $session->get("current_website_id")), [
                'primary_filters' => $ret["filter_data"]['primary'] ?? null,
                'secondary_filters' => $ret["filter_data"]['secondary'] ?? null,
                'additional' => $ret["filter_data"]['additional'] ?? null,
                'index' => $ret["index"],
                'product_group' => $productGroup,
                'data' => $data
            ]);
            $data["filter_html"] = $filterHtml;
        } elseif ($getFacetDataOnly) {
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

            return [
                "facet_title" => $ret["facet_title"],
                "facet_meta_title" => $ret["facet_meta_title"],
                "facet_meta_description" => $ret["facet_meta_description"],
                "facet_canonical" => $ret["facet_canonical"],
            ];
        } else {
            // Filter html
            $filterHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:product_list_filters.html.twig", $session->get("current_website_id")), [
                'primary_filters' => $ret["filter_data"]['primary'] ?? null,
                'secondary_filters' => $ret["filter_data"]['secondary'] ?? null,
                'additional' => $ret["filter_data"]['additional'] ?? null,
                'index' => $ret["index"],
                'product_group' => $productGroup,
                'data' => $data
            ]);
            $data["filter_html"] = $filterHtml;

            // Grid html
            if (!empty($ret['entities'])) {
                $gridHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:product_list.html.twig", $session->get("current_website_id")), [
                    'products' => $ret['entities'],
                    'data' => [],
                    'product_group' => $productGroup
                ]);
            } else {
                if ($isSearch) {
                    $gridHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:no_results_search.html.twig", $session->get("current_website_id")), [
                        'term' => $p["keyword"] ?? '',
                    ]);
                } else {
                    $gridHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:no_results_list.html.twig", $session->get("current_website_id")), []);
                }
            }
            $data["grid_html"] = $gridHtml;

            // Sort html
            if (!isset($sortOptions) || empty($sortOptions)) {
                $sortOptions = $this->productGroupManager->getSortOptions($p["sort"] . "_" . $p["sort_dir"], $productGroup);
            }
            $pageSizeOptions = $this->productGroupManager->preparePageSizeOptions($availablePageSizes, $p["page_size"]);

            $hasNextPage = $this->productGroupManager->calculateIfNextPageExists($p, $ret["total"]);
            $data["has_next_page"] = $hasNextPage;

            // Pager html
            $pagerHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:product_list_pager.html.twig", $session->get("current_website_id")), [
                'page_size_options' => $pageSizeOptions,
                'total' => $ret["total"],
                'page_number' => $p["page_number"],
                'has_next_page' => $hasNextPage,
            ]);
            $data["pager_html"] = $pagerHtml;

            // Sort html
            $sortHtml = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:product_list_sorting.html.twig", $session->get("current_website_id")), [
                'sort_options' => $sortOptions,
                'page_size_options' => $pageSizeOptions,
                'is_search' => $isSearch,
                'keyword' => $originalQuery,
                'total' => $ret["total"],
                'display_list' => $displayList,
                'pager_html' => $data["pager_html"],
                'page_number' => $p["page_number"],
            ]);
            $data["sort_html"] = $sortHtml;

            if (isset($_ENV["SHOW_ACTIVE_FILTERS"]) && !empty($_ENV["SHOW_ACTIVE_FILTERS"])) {
                $data["active_filters"] = $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:product_list_active_filters.html.twig", $session->get("current_website_id")), [
                    'primary_filters' => $ret["filter_data"]['primary'] ?? null,
                    'secondary_filters' => $ret["filter_data"]['secondary'] ?? null,
                    'additional' => $ret["filter_data"]['additional'] ?? null,
                    'index' => $ret["index"]
                ]);
            }
        }

        return $data;
    }
}
