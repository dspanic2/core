<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;

class AlgoliaManager extends AbstractBaseManager
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var DefaultScommerceManager $scommerceManager */
    protected $scommerceManager;

    protected $client;
    protected $index;

    protected $admin_api_key;
    public $application_id;
    public $search_api_key;

    public function initialize()
    {
        parent::initialize();

        $this->application_id = $_ENV["APPLICATION_ID"] ?? null;
        $this->admin_api_key = $_ENV["ADMIN_API_KEY"] ?? null;
        $this->search_api_key = $_ENV["SEARCH_ONLY_API_KEY"] ?? null;
    }

    /**
     * @return array
     */
    public function getAlgoliaProductOrdersArray()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT
            SUM(qty) AS orders,
            product_id
            FROM order_item_entity
            WHERE entity_state_id = 1
            GROUP BY product_id;";

        $ret = array();

        $data = $this->databaseContext->getAll($q);
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d["product_id"]] = $d["orders"];
            }
        }

        return $ret;
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getAlgoliaProductGroupsArray($storeId)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT
            id,
            JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"{$storeId}\"')) AS name
            FROM product_group_entity
            WHERE entity_state_id = 1;";

        $ret = array();

        $data = $this->databaseContext->getAll($q);
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d["id"]] = $d["name"];
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getAlgoliaProductGroupLinksArray()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT
            product_id,
            product_group_id
            FROM product_product_group_link_entity
            WHERE entity_state_id = 1;";

        $ret = array();

        $data = $this->databaseContext->getAll($q);
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d["product_id"]][] = $d["product_group_id"];
            }
        }

        return $ret;
    }

    /**
     * @param $storeId
     * @return false
     */
    public function getAlgoliaRecords($storeId)
    {
        if (empty($this->scommerceManager)) {
            $this->scommerceManager = $this->container->get("scommerce_manager");
        }

        $productsArray = $this->scommerceManager->getAlgoliaProductsArray($storeId);
        $productOrdersArray = $this->getAlgoliaProductOrdersArray();
        $productGroupsArray = $this->getAlgoliaProductGroupsArray($storeId);
        $productGroupLinksArray = $this->getAlgoliaProductGroupLinksArray();

        if (!empty($productsArray)) {
            foreach ($productsArray as $key => $product) {

                $orders = $categories = null;

                if (isset($productOrdersArray[$product["id"]])) {
                    $orders = $productOrdersArray[$product["id"]];
                }
                if (isset($productGroupLinksArray[$product["id"]])) {
                    $productGroupIds = $productGroupLinksArray[$product["id"]];
                    if (!empty($productGroupIds)) {
                        foreach ($productGroupIds as $productGroupId) {
                            if (isset($productGroupsArray[$productGroupId])) {
                                $categories[] = $productGroupsArray[$productGroupId];
                            }
                        }
                    }
                }

                $productsArray[$key]["orders"] = $orders;
                $productsArray[$key]["categories"] = $categories;
            }
        }

        return $productsArray;
    }

    /**
     * @param $name
     * @param $data
     * @throws \Algolia\AlgoliaSearch\Exceptions\MissingObjectId
     */
    public function createUpdateIndex($name, $data)
    {
        if (!$_ENV["USE_ALGOLIA"]) {
            return false;
        }

        if (!empty($this->application_id) &&
            !empty($this->admin_api_key) &&
            !empty($this->search_api_key)) {

            $this->client = \Algolia\AlgoliaSearch\SearchClient::create($this->application_id, $this->admin_api_key);

            $this->index = $this->client->initIndex($name);

            if (!empty($data)) {
                $this->index->saveObjects($data, ["objectIDKey" => "id"]);
                $this->index->setSettings([
                    "customRanking" => [
                        "asc(ord)",
                        "desc(orders)"
                    ],
                    "searchableAttributes" => [
                        "id",
                        "ean",
                        "code",
                        "catalog_code",
                        "name",
                        "meta_title",
                        "short_description",
                        "meta_description",
                        "description",
                        "categories",
                        "store_id",
                        "brand"
                    ]
                ]);

                if (empty($this->databaseContext)) {
                    $this->databaseContext = $this->container->get("database_context");
                }

                $ids = implode(",", array_column($data, "id"));
                $q = "UPDATE product_entity SET content_changed = 0 WHERE id in ({$ids});";

                $this->databaseContext->executeNonQuery($q);
            }
        }

        return true;
    }

    /**
     * @param $term
     * @param $storeId
     * @return array
     */
    public function getSearchResults($term, $storeId)
    {
        if (!$_ENV["USE_ALGOLIA"]) {
            return array();
        }

        $this->client = \Algolia\AlgoliaSearch\SearchClient::create($this->application_id, $this->admin_api_key);

        $this->index = $this->client->initIndex($_ENV["ALGOLIA_INDEX_NAME"]);

        $this->index->setSettings([
            'attributesForFaceting' => [
                "store_id" // or "filterOnly(brand)" for filtering purposes only
            ]
        ]);

        $results = $this->index->search($term, [
            'filters' => 'store_id:' . $storeId
        ]);

        return $results;
    }

    /**
     * @param $query
     * @param $p
     * @return array
     */
    public function searchProducts($query)
    {

        $query = strip_tags($query);

        /**
         * Get store from session
         */
        $session = $this->container->get('session');
        $storeId = $session->get("current_store_id");

        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"] ?? null;
        }

        $results = $this->getSearchResults($query, $storeId);

        $ids = array();

        if (!empty($results) && isset($results["hits"]) && !empty($results["hits"])) {
            $ids = array_column($results["hits"], "id");
        }

        return $ids;
    }
}