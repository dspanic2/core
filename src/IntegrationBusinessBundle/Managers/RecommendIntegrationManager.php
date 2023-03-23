<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\XmlHelper;
use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductImagesEntity;
use CrmBusinessBundle\Entity\ProductProductGroupLinkEntity;
use CrmBusinessBundle\Managers\DefaultExportManager;
use DOMDocument;
use SimpleXMLElement;
use AppBundle\Abstracts\AbstractBaseManager;

class RecommendIntegrationManager extends DefaultExportManager
{
    /**
     * UPUTE
     */
    public function upute(){

        /**
         * DODATI U CRMPROCESSMANAGER afterOrderCompleted na kraj
         */
        $session = $this->container->get('session');

        if (!empty($session->get('recommend_code'))) {

            if (empty($this->recommendIntegrationManager)) {
                $this->recommendIntegrationManager = $this->container->get("recommend_integration_manager");
            }
            $this->recommendIntegrationManager->sendRecommend($session->get('recommend_code'));

            $session->set("recommend_code", "");
        }

        /**
         * DODATI U SCOMMERCE u beforParseUrl na kraj
         */
        if (isset($_GET["rcmndref"]) && !empty($_GET["rcmndref"])) {
            $session = $request->getSession();
            $session->set("recommend_code", $_GET["rcmndref"]);
        }
    }


    /** @var RestManager $recommendRestManager */
    private $recommendRestManager;

    public function initialize()
    {
        parent::initialize();

        $this->recommendRestManager = new RestManager();

        $this->recommendRestManager->CURLOPT_POST = 1;
        $this->recommendRestManager->CURLOPT_RETURNTRANSFER = 1;
        $this->recommendRestManager->CURLOPT_ENCODING = "";
        $this->recommendRestManager->CURLOPT_MAXREDIRS = 10;
        $this->recommendRestManager->CURLOPT_TIMEOUT = 300;
        $this->recommendRestManager->CURLOPT_SSL_VERIFYHOST = 0;
        $this->recommendRestManager->CURLOPT_SSL_VERIFYPEER = 0;
        $this->recommendRestManager->CURLOPT_HTTP_VERSION = "CURL_HTTP_VERSION_1_1";
        $this->recommendRestManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->recommendRestManager->CURLOPT_HTTPHEADER = Array('Content-Type:application/json');

        $this->apiUrl = "https://api.recommend.co/";
    }

    protected $apiUrl;

    public function sendRecommend($code, $email = null, $phone = null){

        $data = Array();
        $data["apiToken"] = $_ENV["RECOMMEND_API_KEY"];
        $data["code"] = $code;
        $data["email"] = $email;
        $data["phone"] = $phone;

        try{
            $this->getRecommendApiData($this->recommendRestManager,"apikeys",null,$data);
        }
        catch (\Exception $e){
            return false;
        }

        return true;
    }

    /**
     * @param RestManager $restManager
     * @param $endpoint
     * @param array $params
     * @param array $body
     * @return mixed
     * @throws \Exception
     */
    private function getRecommendApiData(RestManager $restManager, $endpoint, $params = [], $body = [])
    {
        $url = $this->apiUrl . $endpoint;
        if (!empty($params)) {
            $url .= "&" . http_build_query($params);
        }

        if (!empty($body)) {
            $restManager->CURLOPT_POSTFIELDS = json_encode($body);
        }

        try {
            $data = $restManager->get($url, false);
        } catch (\Exception $e) {
            throw $e;
        }

        if (empty($data)) {
            throw new \Exception("{$endpoint} Response is empty");
        }

        $data = json_decode($data, true);

        if (!isset($data["statusCode"])) {
            throw new \Exception("{$endpoint} Result is empty");
        }
        elseif ($data["statusCode"] != "200"){
            throw new \Exception($data["message"]);
        }

        return $data;
    }

    /**
     * @param $productGroupLinks
     * @param $storeId
     * @return mixed|string
     */
    public function prepareProductGroupList($productGroupLinks)
    {
        $ret = Array();
        $ret["path"] = null;
        $ret["recommend_category_code"] = null;

        if (!EntityHelper::isCountable($productGroupLinks) || count($productGroupLinks) == 0) {
            return $ret;
        }

        $lowestProductGroup = null;
        $lowestProductGroupUrl = null;

        /** @var ProductProductGroupLinkEntity $productGroupLink */
        foreach ($productGroupLinks as $productGroup) {
            $url = $productGroup->getUrlPath($this->storeId);

            if (empty($lowestProductGroup) || strlen($url) > strlen($lowestProductGroupUrl)) {
                $lowestProductGroup = $productGroup;
            }
        }

        if (!empty($lowestProductGroup)) {
            $ret["path"] = $this->productGroupManager->getProductGroupNameList($lowestProductGroup, $this->storeId);
            $ret["recommend_category_code"] = $lowestProductGroup->getRecommendCategoryCode();
        }

        return $ret;
    }

    /**
     * @param $filepath
     * @param $products
     * @return mixed
     * @throws \Exception
     */
    public function formatExport($filepath, $products){

        /** @var SimpleXMLElement $xml */
        $xml = new XmlHelper('<?xml version="1.0" encoding="utf-8"?><RIOProducts></RIOProducts>');

        /** @var ProductEntity $product */
        foreach ($products as $product) {

            $xmlItem = $xml->addChild('product');

            $xmlItemID = $xmlItem->addChild('id');
            $xmlItemID->addCData($product->getId());

            /**
             * Name
             */
            $xmlItemName = $xmlItem->addChild('name');
            $xmlItemName->addCData(substr($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "name"), 0, 200));

            /**
             * Description
             */
            $description = '<p>' . substr($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "description"), 0, strpos(wordwrap($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "description"), 750), "\n")) . '</p>';

            $xmlItemDescription = $xmlItem->addChild('description');
            $xmlItemDescription->addCData($description);

            /**
             * Url
             */
            $xmlItemLink = $xmlItem->addChild('url');
            $xmlItemLink->addCData(substr($this->baseUrl . "/" . $this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "url"), 0, 400));

            /**
             * Image
             */
            $xmlItemMainImage = $xmlItem->addChild('image_url');

            /** @var ProductImagesEntity $mainImage */
            $mainImage = $product->getSelectedImage();
            if (!empty($mainImage)) {
                $xmlItemMainImage->addCData(substr($this->baseUrl . "/Documents/Products/" . $mainImage->getFile(), 0, 400));
            }

            /**
             * Prices
             */
            $prices = $this->crmProcessManager->getProductPrices($product);

            $price = $regularPrice = $prices["price"];

            if (!empty($prices["discount_price"]) &&
                $prices["discount_price"] > 0 &&
                $price >= $prices["discount_price"]) {
                $price = $prices["discount_price"];
            }

            $xmlItemPrice = $xmlItem->addChild('price');
            $xmlItemPrice->addCData(number_format($price, "2", ",", ""));

            $xmlItemRegularPrice = $xmlItem->addChild('regular_price');
            $xmlItemRegularPrice->addCData(number_format($regularPrice, "2", ",", ""));

            $preparedProductGroups = $this->prepareProductGroupList($product->getProductGroups());

            $xmlCategoryPath = $xmlItem->addChild('category_path');
            $xmlCategoryPath->addCData($preparedProductGroups["path"]);

            $xmlRioCategoryId = $xmlItem->addChild('RIO_category_id');
            $xmlRioCategoryId->addCData($preparedProductGroups["recommend_category_code"]);

            $xmlAttributes = $xmlItem->addChild('attributes');

            $attributes = $product->getPreparedProductAttributes();
            if (EntityHelper::isCountable($attributes) && count($attributes) > 0) {
                $xmlAttribute = $xmlAttributes->addChild('attribute');
                foreach ($attributes as $attribute) {

                    if($attribute["attribute"]->getHideFromProduct()){
                        continue;
                    }

                    $xmlAttributeName = $xmlAttribute->addChild('name');
                    $xmlAttributeName->addCData($this->translator->trans($attribute["attribute"]->getName()));

                    $xmlAttributeValues = $xmlAttribute->addChild('values');

                    foreach ($attribute["values"] as $val) {
                        $tmp = "";
                        if (!empty($val["prefix"])) {
                            $tmp .= $this->translator->trans($val["prefix"]);
                        }
                        if (!empty($val["value"])) {
                            $tmp .= $this->translator->trans($val["value"]);
                        }
                        if (!empty($val["sufix"])) {
                            $tmp .= $this->translator->trans($val["sufix"]);
                        }

                        $xmlAttributeValue = $xmlAttributeValues->addChild('value');
                        $xmlAttributeValue->addCData(trim($tmp));
                    }
                }
            }

            $this->entityManager->detach($product);
            if (!empty($mainImage)) {
                $this->entityManager->detach($mainImage);
            }

            unset($attributes);
            unset($productGroups);
        }

        /**
         * Beautify XML using DOM
         */
        $dom = new \DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        $this->helperManager->saveRawDataToFile($dom->saveXML(), $filepath);

        return $filepath;
    }
}