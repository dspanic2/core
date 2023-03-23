<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use IntegrationBusinessBundle\Managers\OpenaiApiManager;

class OpenaiManager extends AbstractBaseManager
{
    /** @var OpenaiApiManager $openaiApiManager */
    protected $openaiApiManager;

    public function initialize()
    {
        parent::initialize();
    }

    public function uploadProductFiles(){

        $storeId = $_ENV["DEFAULT_STORE_ID"];

        if (empty($this->scommerceManager)) {
            $this->scommerceManager = $this->container->get("scommerce_manager");
        }

        if(empty($this->openaiApiManager)){
            $this->openaiApiManager = $this->getContainer()->get("openai_api_manager");
        }

        if(empty($this->helperManager)){
            $this->helperManager = $this->getContainer()->get("helper_manager");
        }

        $productsArray = $this->scommerceManager->getAlgoliaProductsArray($storeId);

        $i = 0;
        foreach ($productsArray as $product){

            $i++;

            if($i > 300){
                break;
            }

            $filepath = $_ENV["WEB_PATH"]."Documents/test2.jsonl";

            $fileData = Array();
            $fileData["completion"] = $product["meta_title"];
            unset($product["meta_title"]);
            $tmp = Array();
            foreach ($product as $key => $value){
                if(empty($value)){
                    continue;
                }
                $tmp[] = $key."=".$value;
            }
            $fileData["prompt"] = implode("\n\n###\n\n",$tmp);

            $this->helperManager->saveRawDataToFile(json_encode($fileData), $filepath);

            $data = Array();
            $data["purpose"] = "fine-tune";
            $data["file"] = new \CurlFile($filepath, 'text/plain' /* MIME-Type */, 'test2.jsonl');

            $ret = $this->openaiApiManager->uploadFile($data);
            dump($ret);
            die;


            dump($product);
            die;


        }
    }

    //todo insert update onaj kurac

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