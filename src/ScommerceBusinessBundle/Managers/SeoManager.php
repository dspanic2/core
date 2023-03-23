<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\RestManager;
use AppBundle\Models\InsertModel;
use CrmBusinessBundle\Abstracts\AbstractImportManager;
use IntegrationBusinessBundle\Managers\ChatgptApiManager;
use ScommerceBusinessBundle\Entity\SStoreEntity;

class SeoManager extends AbstractImportManager
{
    /** @var ChatgptApiManager $chatgptApiManager */
    protected $chatgptApiManager;
    /** @var RestManager $chatgptRestManager */
    protected $chatgptRestManager;

    /** @var SStoreEntity $store */
    protected $store;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $entity
     * @param $type
     * @return string
     * @throws \Exception
     */
    public function prepareBaseQueryForEntity($entity,$type){

        $baseQuery = null;

        switch ($entity->getEntityType()->getEntityTypeCode()) {
          case "product_group":
              switch ($type) {
                case "keyword_suggestion":

                    if(!isset($entity->getName()[$this->store->getId()])){
                        throw new \Exception("Missing name for store");
                    }

                    $name = $entity->getName()[$this->store->getId()];

                    $additionalQuery = ",";
                    if(!empty($entity->getProductGroup())){
                        $additionalQuery = ", parent category \"{$entity->getProductGroup()->getName()[$this->store->getId()]}\",";
                    }

                    $additionalQuery2 = "";
                    $products = $entity->getProductGroupProducts();
                    if(EntityHelper::isCountable($products) && count($products) > 0){
                        $randProductIds = array_rand($products,15);
                        $additionalQuery2.="\r\nSome of the products in this category are: ";
                        $productList = Array();
                        foreach ($randProductIds as $randProductId){
                            $productList[] = $products[$randProductId]->getName()[$this->store->getId()];
                        }
                        $additionalQuery2.=implode(",",$productList).".";
                    }

                    $baseQuery = "Can you suggest top 10 most searched keywords on google search for category \"{$name}\"{$additionalQuery} in croatian, without enumeration, in one row with results delimited by #?{$additionalQuery2}";
                    break;
                default:
                    throw new \Exception("Missing prepare base query for entity type code: {$entity->getEntityType()->getEntityTypeCode()} and type: {$type}");
              }
            break;
          default:
            throw new \Exception("Missing prepare base query for entity type code: {$entity->getEntityType()->getEntityTypeCode()}");
        }

        return $baseQuery;
    }


    /**
     * @param $entity
     * @param SStoreEntity $store
     * @return void
     * @throws \Exception
     */
    public function getSeoKeywordSuggestionForEntity($entity, SStoreEntity $store){

        if(empty($entity)){
            throw new \Exception("Missing entity");
        }

        $suggestedKeywords = Array();

        $this->store = $store;

        $baseQuery = $this->prepareBaseQueryForEntity($entity,"keyword_suggestion");

        print $baseQuery."\r\n";

        if (empty($this->chatgptRestManager)){
            $this->chatgptRestManager = new RestManager();
        }

        $data = Array();
        $data["model"] = "text-davinci-003";
        $data["prompt"] = $baseQuery;
        $data["temperature"] = 0;
        $data["max_tokens"] = 2000;
        $data["top_p"] = 1;
        $data["frequency_penalty"] = 0;
        $data["presence_penalty"] = 0;

        if(empty($this->chatgptApiManager)){
            $this->chatgptApiManager = $this->container->get("chatgpt_api_manager");
        }

        /*$res = $this->chatgptApiManager->getApiData($this->chatgptRestManager,"completions",$data);*/

        $res["choices"][0]["text"] = "Baterije#Punjači#Baterijski uložak#Baterija#Baterijski adapter#Baterijski uložak Blaupunkt#ALK 6LR61 9V#ALK LR06 AA 1,5V#ALK LR03 AAA 1,5V#ZnCl 3R12 4,5V#ZnCl 6F22 9V#ZnCl R20 D 1,5V#Wertor 850mAh AAA#Wertor 2500mAh AA#Punjač baterija 4xAA/AAA MW1282GS";

        if(isset($res["choices"][0]["text"])){
            $suggestedKeywords = explode("#",trim($res["choices"][0]["text"]));
        }
        else{
            return false;
        }

        if(empty($suggestedKeywords)){
            return false;
        }

        $ret["seo_keyword_suggestion_ids"] = Array();
        $existingSeoKeywords = $this->getEntitiesArray(["id","keyword","LOWER(keyword) as code"], "seo_keyword_suggestion_entity", ["code"], "WHERE a1.entity_state_id = 1 AND a1.store_id = {$this->store->getId()}");
        $existingSeoKeywordIds = [];
        $existingSeoKeywordCodes = [];

        $insertArray = [
            // product_entity
            // s_product_attribute_configuration_options_entity
        ];
        $insertArray2 = [
            // s_product_attributes_link_entity
            // product_product_group_link_entity
            // s_route_entity
        ];

        if(empty($this->asSeoKeywordSuggestion)){
            $this->asSeoKeywordSuggestion = $this->entityManager->getAttributeSetByCode("seo_keyword_suggestion");
        }

        foreach ($suggestedKeywords as $suggestedKeyword){

            $key = strtolower($suggestedKeyword);

            if (!isset($existingSeoKeywords[$key])) {
                /**
                 * Fill defaults
                 */
                $seoKeywordInsert = new InsertModel($this->asSeoKeywordSuggestion);
                $seoKeywordInsert
                    ->add("keyword", $key)
                    ->add("is_active", 1)
                    ->add("store_id", $_ENV["DEFAULT_STORE_ID"]);

                if (!empty($seoKeywordInsert->getArray())) {
                    $insertArray["seo_keyword_suggestion_entity"][$key] = $seoKeywordInsert->getArray();
                    $existingSeoKeywordCodes[] = $key;
                }
            }
        }

        if(!empty($insertArray)){
            $this->executeInsertQuery($insertArray);
            unset($insertArray);

            $reselectArray["seo_keyword_suggestion_entity"] = $this->getEntitiesArray(["id","keyword","LOWER(keyword) as code"], "seo_keyword_suggestion_entity", ["code"], "WHERE a1.entity_state_id = 1 AND a1.store_id = {$this->store->getId()}");;

            $ret["seo_keyword_suggestion_ids"] = $this->resolveChangedProducts($existingSeoKeywordIds, $existingSeoKeywordCodes, $reselectArray["seo_keyword_suggestion_entity"]);
        }

        return $ret;
    }

    public function testChatGp($prompt = "text-davinci-003"){

        $data = Array();
        $data["model"] = "text-davinci-003";
        $data["prompt"] = $prompt;
        $data["temperature"] = 0;
        $data["max_tokens"] = 1608;
        $data["top_p"] = 1;
        $data["frequency_penalty"] = 0;
        $data["presence_penalty"] = 0;


        if(empty($this->chatgptApiManager)){
            $this->chatgptApiManager = $this->container->get("chatgpt_api_manager");
        }

        $res = $this->chatgptApiManager->getApiData($this->chatgptRestManager,"completions",$data);

        dump($res);
        die;

        return $res;
    }
}