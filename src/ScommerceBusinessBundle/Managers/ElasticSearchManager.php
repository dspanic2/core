<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\EntityType;
use AppBundle\Managers\EntityManager;
use Elasticsearch\Client;
use Elasticsearch\Transport;
use ScommerceBusinessBundle\Entity\SStoreEntity;

class ElasticSearchManager extends AbstractBaseManager
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var DefaultScommerceManager $scommerceManager */
    protected $scommerceManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;

    protected $client;
    protected $indexDefinition;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $entityTypeCode
     * @return bool
     * @throws \Exception
     */
    public function createIndex($entityTypeCode){

        if(empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }

        /** @var EntityType $entityType */
        $entityType = $this->entityManager->getEntityTypeByCode($entityTypeCode);
        if(empty($entityType)){
            throw new \Exception("Missing entity type: ".$entityTypeCode);
        }

        $this->client = $this->container->get("Elasticsearch\Client");

        if(!isset($_ENV[strtoupper($entityTypeCode)."_COLUMNS"])){
            throw new \Exception("Missing columns to index");
        }
        $columnsToIndex = json_decode($_ENV[strtoupper($entityTypeCode)."_COLUMNS"],true);

        if(empty($columnsToIndex)){
            throw new \Exception("Columns to index are empty");
        }

        $attributes = $this->entityManager->getAttributesOfEntityTypeByKey($entityType);

        $mappings = Array();

        /**
         * Add entity_state_id
         */
        $mappings["entity_state_id"] = Array(
            "type" => "byte"
        );

        /**
         * Add show on store by default
         * Premjesteno na razinu indexa
         */
        /*if(!in_array("show_on_store",$columnsToIndex)){
            foreach (array_keys($attributes) as $attributeCode) {
                if ($attributeCode == "show_on_store") {
                    $mappings["show_on_store"] = Array(
                        "type" => "byte",
                        "search_analyzer" => "standard"
                    );
                    break;
                }
            }
        }*/

        foreach ($columnsToIndex as $attributeCode){
            if(!isset($attributes[$attributeCode])){
                dump($attributeCode);
                continue;
            }

            $mappingsTmp = Array();

            $backendType = $attributes[$attributeCode]["frontend_type"];
            switch ($backendType) {
                case "integer":
                    $mappingsTmp["type"] = "integer";
                    break;
                case "autocomplete":
                    $mappingsTmp["type"] = "integer";
                    break;
                case "checkbox":
                    $mappingsTmp["type"] = "byte";
                    break;
                case "checkbox_store":
                    $mappingsTmp["type"] = "byte";
                    break;
                case "decimal":
                    $mappingsTmp["type"] = "float";
                    break;
                case "text_store":
                    $mappingsTmp["type"] = "text";
                    $mappingsTmp["analyzer"] = "autocomplete";
                    $mappingsTmp["search_analyzer"] = "standard";
                    break;
                case "text":
                    $mappingsTmp["type"] = "text";
                    $mappingsTmp["analyzer"] = "autocomplete";
                    $mappingsTmp["search_analyzer"] = "standard";
                    break;
                case "textarea":
                    $mappingsTmp["type"] = "text";
                    $mappingsTmp["analyzer"] = "autocomplete";
                    $mappingsTmp["search_analyzer"] = "standard";
                    break;
                case "textarea_store":
                    $mappingsTmp["type"] = "text";
                    $mappingsTmp["analyzer"] = "autocomplete";
                    $mappingsTmp["search_analyzer"] = "standard";
                    break;
                case "ckeditor_store":
                    $mappingsTmp["type"] = "text";
                    $mappingsTmp["analyzer"] = "autocomplete";
                    $mappingsTmp["search_analyzer"] = "standard";
                    break;
                default:
                    throw new \Exception("Missing elastic mapping for: ".$backendType);
                    //You can check options here: https://www.elastic.co/guide/en/elasticsearch/reference/current/number.html
                    break;
            }

            $mappings[$attributeCode] = $mappingsTmp;
        }

        if(empty($this->scommerceManager)){
            $this->scommerceManager = $this->container->get("scommerce_manager");
        }

        /**
         * Get custom mappings
         */
        $mappings = $this->scommerceManager->getElasticCustomMappingsForEntityType($entityTypeCode,$mappings);

        /**
         * Get settings for index
         */
        $settings = $this->scommerceManager->getElasticSettingsForEntityType($entityTypeCode);

        if(empty($this->routeManager)){
            $this->routeManager = $this->container->get("route_manager");
        }

        $stores = $this->routeManager->getStores();

        /** @var SStoreEntity $store */
        foreach ($stores as $store){

            $this->indexDefinition = Array();
            $this->indexDefinition["index"] = strtolower($_ENV["INDEX_PREFIX"])."_".strtolower($entityTypeCode)."_".$store->getId();

            $indexCreate = array_merge($this->indexDefinition,Array(
                'body' => Array(
                    'settings' => $settings,
                    'mappings' => [
                        "properties" => $mappings
                    ]
                ),
            ));

            if ($this->client->indices()->exists($this->indexDefinition)){
                $this->client->indices()->delete($this->indexDefinition);
            }

            $this->client->indices()->create($indexCreate);
        }

        return true;
    }

    /**
     * @param $entityTypeCode
     * @param $storeId
     * @param null $additionalFilter
     * @return bool
     * @throws \Exception
     */
    public function reindex($entityTypeCode,$storeId,$additionalFilter = null){

        if(!isset($_ENV[strtoupper($entityTypeCode)."_COLUMNS"])){
            throw new \Exception("Missing columns to index");
        }
        $columnsToIndex = json_decode($_ENV[strtoupper($entityTypeCode)."_COLUMNS"],true);

        if(empty($columnsToIndex)){
            throw new \Exception("Columns to index are empty");
        }

        if(empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }

        /** @var EntityType $entityType */
        $entityType = $this->entityManager->getEntityTypeByCode($entityTypeCode);
        if(empty($entityType)){
            throw new \Exception("Missing entity type: ".$entityTypeCode);
        }

        $attributes = $this->entityManager->getAttributesOfEntityTypeByKey($entityType);

        $this->client = $this->container->get("Elasticsearch\Client");

        $this->indexDefinition = Array();
        $this->indexDefinition["index"] = strtolower($_ENV["INDEX_PREFIX"])."_".strtolower($entityTypeCode)."_".$storeId;

        if(empty($this->scommerceManager)){
            $this->scommerceManager = $this->container->get("scommerce_manager");
        }

        $columns = Array();
        $columns["id"] = Array();
        $columns["entity_state_id"] = Array();
        foreach ($columnsToIndex as $attributeCode){
            $columns[$attributeCode] = Array();
            if(isset($attributes[$attributeCode])){
                if(in_array($attributes[$attributeCode]["frontend_type"],Array("text_store","textarea_store","decimal_store","ckeditor_store"))){
                    $columns[$attributeCode]["select"] = "JSON_UNQUOTE(JSON_EXTRACT({$attributeCode},'$.\"{$storeId}\"')) AS {$attributeCode}";
                }
            }
        }
        /**
         * Filter by store if exists
         */
        if(isset($attributes["show_on_store"])){
            if(!empty($additionalFilter)){
                $additionalFilter.=" AND ";
            }
            $additionalFilter.= " JSON_CONTAINS(show_on_store, '1', '$.\"{$storeId}\"') = '1' ";
        }

        $data = $this->scommerceManager->getElasticDataForReindex($entityTypeCode,$storeId,$columns,$additionalFilter);

        if(empty($data)){
            return true;
        }

        foreach ($data as $d){

            $body = array();
            foreach ($d as $key => $value){
                $body[$key] = $value;
            }

            $doc = array_merge(
                $this->indexDefinition,
                [
                    'id' => $d["id"],
                    'body' => $body
                ]
            );
            $this->client->index($doc);
        }

       /* "id" => "1003"
  "name" => "KOSILICA ELEKTRIČNA SMART 32E 1000 W 32 cm MTD"
  "meta_title" => "KOSILICA ELEKTRIČNA SMART 32E 1000 W 32 cm MTD"
  "meta_description" => "KOSILICA ELEKTRIČNA SMART 32E 1000 W 32 cm MTD"
  "description" => ""
  "short_description" => null
  "ean" => "4008423861198"
  "code" => "001039"
  "catalog_code" => null
  "remote_id" => null
  "ord" => "100"
  "meta_keywords" => ""
  "brand" => "MTD"
  "store_id" => "3"
  "orders" => null
  "categories" => array:4 [
    0 => "Vrt i sezona"
    1 => "Mehanizacija"
    2 => "Kosilica"
    3 => "Električna kosilica"
  ]*/

        return true;
    }

    /**
     * @param $term
     * @param $storeId
     * @param string $entityTypeCode
     * @return array
     * @throws \Exception
     */
    public function getSearchResults($term, $storeId, $entityTypeCode = "product", $query = null)
    {
        if (!$_ENV["USE_ELASTIC"]) {
            throw new \Exception("Elastic not in use");
        }

        if(!isset($_ENV[strtoupper($entityTypeCode)."_COLUMNS"])){
            throw new \Exception("Index does not exist");
        }

        $this->indexDefinition = Array();
        $this->indexDefinition["index"] = strtolower($_ENV["INDEX_PREFIX"])."_".strtolower($entityTypeCode)."_".$storeId;

        switch ($entityTypeCode) {
            case "blog_post":
                break;
            case "brand":

                if(empty($query)){
                    $query = array_merge(
                        $this->indexDefinition,
                        ['body' => [
                            "query" => [
                                "bool" => [
                                    "must" => [
                                        "fuzzy" => [
                                            "name" => $term
                                        ]
                                    ],
                                    "filter" => [
                                         [
                                            "bool" => [
                                               "must" => [
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
                                                                "show_on_brand_page" => 1
                                                             ]
                                                          ]
                                                       ]
                                                    ]
                                                 ]
                                               ]
                                            ]
                                       ]
                                   ]
                                ]
                            ],
                            'size' => 10
                        ]]
                    );
                }

                break;
            case "product_group":

                if(empty($query)) {
                    $terms = explode(" ", $term);

                    $synonyms = $names = array();
                    foreach ($terms as $t) {
                        $synonyms[] = [
                            "bool" => [
                                "must" => [
                                    [
                                        "fuzzy" => [
                                            "synonyms" => $t
                                        ]
                                    ]
                                ]
                            ]
                        ];

                        $names[] = [
                            "bool" => [
                                "must" => [
                                    [
                                        "fuzzy" => [
                                            "name" => $t
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    }

                    $query = array_merge(
                        $this->indexDefinition,
                        ['body' => [
                            "query" => [
                                "bool" => [
                                    "should" => [
                                        [
                                            "bool" => [
                                                "should" => $synonyms
                                            ]
                                        ],
                                        [
                                            "bool" => [
                                                "should" => $names
                                            ]
                                        ]
                                    ],
                                    "filter" => [
                                        [
                                            "bool" => [
                                                "must" => [
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
                                                                        "is_active" => 1
                                                                    ]
                                                                ]
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            "size" => 5
                        ]]
                    );
                }

                break;
            case "product":
                break;
            default:
                break;
        }

        if(empty($query)){
            throw new \Exception("Empty query for entity type: {$entityTypeCode}");
        }

        /*dump(json_encode($query));
        die;*/

        $this->client = $this->container->get("Elasticsearch\Client");


        $result = $this->client->search($query);

        $data = array_map(function ($item) {
            return ['value' => $item];
        }, $result['hits']['hits']);

        return $data;
    }
}