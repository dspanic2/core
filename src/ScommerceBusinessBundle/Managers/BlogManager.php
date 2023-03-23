<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SearchFilterHelper;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\Entity\ProductEntity;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\BlogCategoryEntity;
use ScommerceBusinessBundle\Entity\BlogPostEntity;
use ScommerceBusinessBundle\Entity\SRouteEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;

class BlogManager extends AbstractScommerceManager
{
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var EntityType $blogPostEntityType */
    protected $blogPostEntityType;
    /** @var DefaultScommerceManager $scommerceManager */
    protected $scommerceManager;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $id
     * @return |null
     */
    public function getBlogCategoryById($id)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("blog_category");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param SStoreEntity $storeEntity
     * @return mixed
     */
    public function getBlogCategoriesByStore(SStoreEntity $storeEntity)
    {

        $productGroupEntityType = $this->entityManager->getEntityTypeByCode("blog_category");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeEntity->getId() . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("name", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($productGroupEntityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param $data
     * @param false $loadAll
     * @return array
     */
    public function getFilteredBlogPosts($data, $loadAll = false)
    {
        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $ret = array();

        $ret["total"] = 0;
        $ret["entities"] = array();
        $ret["filter_data"] = array();
        $ret["error"] = false;

        if (empty($this->blogPostEntityType)) {
            $this->blogPostEntityType = $this->entityManager->getEntityTypeByCode("blog_post");
        }

        /*$compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("active", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        if (isset($data["blog_category"]) && !empty($data["blog_category"]) && !$loadAll) {
            $compositeFilter->addFilter(new SearchFilter("blogCategory", "in", $data["blog_category"]));
        }
        if (isset($data["show_on_homepage"]) && !empty($data["show_on_homepage"])) {
            $compositeFilter->addFilter(new SearchFilter("showOnHomepage", "eq", 1));
        }*/

        /** Defaults */
        if (!isset($data["page_number"]) || empty($data["page_number"])) {
            $data["page_number"] = 1;
        }

        /**
         * Start empty queries
         */
        $joinQuery = "";
        $sortQuery = "";

        /**
         * Standard queries
         */
        $additionalFilter = "";
        if(isset($_ENV["ADDITIONAL_BLOG_POST_FILTER"]) && !empty($_ENV["ADDITIONAL_BLOG_POST_FILTER"])){
            $additionalFilter = $_ENV["ADDITIONAL_BLOG_POST_FILTER"];
        }
        $whereQuery = "WHERE bp.entity_state_id = 1 and bp.active = 1 {$additionalFilter} and JSON_CONTAINS(bp.show_on_store, '1', '$.\"{$storeId}\"') = '1'";
        if (isset($data["blog_category"]) && !empty($data["blog_category"])) {
            $whereQuery .= " AND (bp.blog_category_id IN ({$data["blog_category"]}) OR {$data["blog_category"]} IN (SELECT blog_category_id FROM blog_post_blog_category_link_entity WHERE blog_post_id = bp.id)) ";
        }
        if (isset($data["ids"]) && !empty($data["ids"])) {
            $whereQuery .= " AND bp.id in (" . implode(",", $data["ids"]) . ") ";
        }

        if (isset($data["pre_filter"]) && !empty($data["pre_filter"])) {
            if (is_string($data["pre_filter"])) {
                $data["pre_filter"] = json_decode($data["pre_filter"], true);
            }

            /**
             * Prepare prefilter
             */
            $preparedFilter = SearchFilterHelper::parseProductGroupFilter($data["pre_filter"], $joinQuery);
            if (isset($preparedFilter["where_query"])) {
                $whereQuery .= " AND ({$preparedFilter["where_query"]}) ";
            }
            if (isset($preparedFilter["join_query"])) {
                $joinQuery = $preparedFilter["join_query"];
            }

            /*dump($whereQuery);
            die;*/
        }

        /*if (isset($data["pre_filter"])) {
            if (is_string($data["pre_filter"])) {
                $data["pre_filter"] = json_decode($data["pre_filter"], true);
            }
            foreach ($data["pre_filter"] as $filterKey => $values) {
                if (!is_array($values)) {
                    $values = [$values];
                }
                $compositeFilter->addFilter(new SearchFilter($filterKey, "in", implode(",", $values)));
            }
        }*/

        /*$compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);*/

        /**
         * Sort by
         */
        if (isset($data["sort"]) && !empty($data["sort"])) {
            $sortQuery .= " ORDER BY ";
            $data["sort"] = json_decode($data["sort"], true);
            foreach ($data["sort"] as $sort) {
                if ($sort["sort_by"] == "custom" && $sort["sort_dir"] == "ids" && isset($data["ids"]) && !empty($data["ids"])) {
                    $sortQuery .= " FIELD(bp.id, " . implode(",", $data["ids"]) . " ),";
                } else {
                    $sortQuery .= " bp." . EntityHelper::makeAttributeCode($sort["sort_by"]) . " {$sort["sort_dir"]},";
                }

            }
            $sortQuery = substr($sortQuery, 0, -1);
        }

        if (isset($data["was_sorted"]) && $data["was_sorted"]) {
            $sortQuery = " ORDER BY bp.ord ASC";
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $entityQuery = "SELECT DISTINCT(bp.id) FROM blog_post_entity bp {$joinQuery} {$whereQuery} {$sortQuery};";
        $entities = $this->databaseContext->getAll($entityQuery);

        /*$sortFilters = new SortFilterCollection();
        if (isset($data["sort"]) && !empty($data["sort"])) {
            $data["sort"] = json_decode($data["sort"], true);
            foreach ($data["sort"] as $sort) {
                if ($sort["sort_by"] == "custom" && $sort["sort_dir"] == "ids") {

                } else {
                    $sortFilters->addSortFilter(new SortFilter($sort["sort_by"], $sort["sort_dir"]));
                }
            }
        }*/

        $ret["total"] = count($entities);

        if ($ret["total"] > 0) {
            if (isset($data["get_all_blog_posts"]) && $data["get_all_blog_posts"]) {
                $from = 0;
                $limit = $data["page_size"] * $data["page_number"];
            } else {
                $from = (($data["page_number"] - 1) * $data["page_size"]);
                $limit = $data["page_size"];
            }

            $ids = array_unique(array_column($entities, 'id'));
            $ret["entities"] = array_slice($ids, $from, $limit);

            if (empty($ret["entities"])) {
                $ret["error"] = true;
            } else {
                $compositeFilter = new CompositeFilter();
                $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",", $ret["entities"])));
                $compositeFilters = new CompositeFilterCollection();
                $compositeFilters->addCompositeFilter($compositeFilter);

                $sortFilters = new SortFilterCollection();
                if (isset($data["sort"]) && !empty($data["sort"])) {
                    foreach ($data["sort"] as $sort) {
                        if ($sort["sort_by"] == "custom" && $sort["sort_dir"] == "ids") {

                        } else {
                            $sortFilters->addSortFilter(new SortFilter($sort["sort_by"], $sort["sort_dir"]));
                        }
                    }
                }

                if (isset($data["was_sorted"]) && $data["was_sorted"]) {
                    $sortFilters->addSortFilter(new SortFilter("ord", "asc"));
                }

                $ret["entities"] = $this->entityManager->getEntitiesByEntityTypeAndFilter($this->blogPostEntityType, $compositeFilters, $sortFilters);

                /**
                 * Custom sort by
                 */
                if (isset($data["sort"]) && !empty($data["sort"])) {
                    foreach ($data["sort"] as $sort) {
                        if ($sort["sort_by"] == "custom" && $sort["sort_dir"] == "ids" && isset($data["ids"]) && !empty($data["ids"])) {

                            $tmpProducts = $ret["entities"];
                            $orderArray = array_flip($data["ids"]);
                            $tmpEntitiesArray = array();
                            foreach ($tmpProducts as $entity) {
                                if (isset($orderArray[$entity->getId()])) {
                                    $tmpEntitiesArray[$orderArray[$entity->getId()]] = $entity;
                                }
                            }

                            ksort($tmpEntitiesArray);
                            $ret["entities"] = $tmpEntitiesArray;

                            break;
                        }
                        if (isset($sort["offset"]) && is_numeric($sort["offset"]) && $sort["offset"] > 0) {
                            for ($i = 0; $i < $sort["offset"]; $i++) {
                                unset($ret["entities"][$i]);
                            }
                        }
                    }
                }
            }
        }

        /*$entities = $this->entityManager->getEntitiesByEntityTypeAndFilter($this->blogPostEntityType, $compositeFilters, $sortFilters);

        $ret["total"] = count($entities);

        if (!isset($data["page_number"])) {
            $data["page_number"] = 1;
        }
        if (!isset($data["page_size"])) {
            $data["page_size"] = 100;
        }

        if ($ret["total"] > 0) {
            if (isset($data["get_all_blog_posts"]) && $data["get_all_blog_posts"]) {
                $from = 0;
                $limit = $data["page_size"] * $data["page_number"];
            } else {
                $from = (($data["page_number"] - 1) * $data["page_size"]);
                $limit = $data["page_size"];
            }
            $ret["entities"] = array_slice($entities, $from, $limit);

            if (empty($ret["entities"])) {
                $ret["error"] = true;
            }
        }*/

        return $ret;
    }

    /**
     * @param $data
     * @param $total
     * @return bool
     */
    public function calculateIfNextPageExists($data, $total)
    {

        if ((intval($data["page_number"]) * intval($data["page_size"])) >= intval($total)) {
            return false;
        }

        return true;
    }

    /**
     * @param $query
     * @return mixed
     */
    public function searchPosts($query)
    {
        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");

        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $entityType = $this->entityManager->getEntityTypeByCode("blog_post");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("active", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /**
         * If use elastic search elastic
         */
        if ($_ENV["USE_ELASTIC"] ?? 0) {

            if(empty($this->scommerceManager)){
                $this->scommerceManager = $this->container->get("scommerce_manager");
            }

            $ids = $this->scommerceManager->elasticSearchBlogPosts($query,$storeId);
        }

        /**
         * Perform search
         */
        if(!empty($ids)){
            $compositeFilter = new CompositeFilter();
            $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",",$ids)));
            $compositeFilters->addCompositeFilter($compositeFilter);
        }
        else{
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("or");

            $terms = explode(" ", $query);
            foreach ($terms as $term) {
                $compositeFilter->addFilter(new SearchFilter("name", "bw", $term));
                $compositeFilter->addFilter(new SearchFilter("content", "bw", $term));
            }

            $compositeFilters->addCompositeFilter($compositeFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("created", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getProductRelatedBlogPosts(ProductEntity $product)
    {
        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $productGroupEntityType = $this->entityManager->getEntityTypeByCode("blog_post");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));
        $compositeFilter->addFilter(new SearchFilter("products.id", "in", $product->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("created", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($productGroupEntityType, $compositeFilters, $sortFilters);
    }
}
