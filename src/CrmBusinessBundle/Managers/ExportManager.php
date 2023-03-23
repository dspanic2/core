<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Helpers\XmlHelper;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductExportEntity;
use CrmBusinessBundle\Entity\ProductImagesEntity;
use DOMDocument;
use SimpleXMLElement;

class ExportManager extends DefaultExportManager
{
    protected $productExportEntityType;
    /** @var ProductManager $productManager */
    /** @var AccountEntity $account */
    protected $account;

    public function initialize()
    {
        parent::initialize();
        if(empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }
    }

    /**
     * @param $id
     * @return |null
     */
    public function getProductExportById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(ProductExportEntity::class);
        return $repository->find($id);
    }

    /**
     * @return |null
     */
    public function getProductExports()
    {

        if (empty($this->productExportEntityType)) {
            $this->productExportEntityType = $this->entityManager->getEntityTypeByCode("product_export");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($this->productExportEntityType, $compositeFilters);
    }

    /**
     * @param ProductExportEntity $productExport
     * @param $storeId
     * @return bool
     * @throws \Exception
     */
    public function generateProductExport(ProductExportEntity $productExport, $storeId = null)
    {
        if(empty($storeId)){
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        //TODO eventualno product ids, kopirati iz parenta koji poziva generateExport
        $productIds = Array();

        $this->account = $productExport->getAccount();

        $this->generateExport($storeId,$productExport->getSecretKey(),$productIds);

        $productExport->setDateRegenerated(new \DateTime());
        $this->entityManager->saveEntityWithoutLog($productExport);

        return true;
    }

    /**
     * @param $secretKey
     * @param $password
     * @return bool|string
     */
    public function getExportLocation($secretKey, $password)
    {

        if (empty($this->productExportEntityType)) {
            $this->productExportEntityType = $this->entityManager->getEntityTypeByCode("product_export");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("password", "eq", $password));
        $compositeFilter->addFilter(new SearchFilter("secretKey", "eq", $secretKey));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var ProductExportEntity $productExport */
        $productExport = $this->entityManager->getEntityByEntityTypeAndFilter($this->productExportEntityType, $compositeFilters);

        if (empty($productExport)) {
            return false;
        }

        return $productExport->getAccessUrl();
    }

    /**
     * @param $filepath
     * @param $products
     * @return mixed
     * @throws \Exception
     */
    public function formatExport($filepath, $products){

        /**@var SimpleXMLElement $xml */
        $xml = new XmlHelper('<?xml version="1.0" encoding="utf-8"?><products></products>');

        /** @var ProductEntity $product */
        foreach ($products as $product) {

            $xmlProduct = $xml->addChild('product');

            $xmlProductId = $xmlProduct->addChild('ID');
            $xmlProductId->addCData($product->getId());

            if (EntityHelper::checkIfPropertyExists($product, "remoteId")) {
                $xmlProductId = $xmlProduct->addChild('productId');
                $xmlProductId->addCData($product->getRemoteId());
            }

            $xmlProductName = $xmlProduct->addChild('name');
            $xmlProductName->addCData(StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "name")));

            $xmlProductCode = $xmlProduct->addChild('productCode');
            $xmlProductCode->addCData($product->getCode());

            $xmlProductDescription = $xmlProduct->addChild('description');
            $xmlProductDescription->addCData(StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "description")));

            $xmlProductLink = $xmlProduct->addChild('link');
            $xmlProductLink->addCData($this->baseUrl."/".$this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "url"));

            $xmlProductMainImage = $xmlProduct->addChild('mainImage');

            /** @var ProductImagesEntity $mainImage */
            $mainImage = $product->getSelectedImage();
            if (!empty($mainImage)) {
                $xmlProductMainImage->addCData($this->baseDocumentUrl."/Documents/Products/".$mainImage->getFile());
            }

            $xmlImages = $xmlProduct->addChild('moreImages');
            if (EntityHelper::isCountable($product->getImages()) && count($product->getImages()) > 1) {
                /** @var ProductImagesEntity $image */
                foreach ($product->getImages() as $image) {
                    if ($image->getId() == $mainImage->getId()) {
                        continue;
                    }
                    $xmlImage = $xmlImages->addChild('image');
                    $xmlImage->addCData($this->baseDocumentUrl."/Documents/Products/".$image->getFile());
                }
            }

            $xmlProduct = $this->exportManagerAddPrices($xmlProduct, $product, $this->account);

            $xmlProductStock = $xmlProduct->addChild('stock');
            $xmlProductStock->addCData($product->getPreparedQty());

            /*$productWarehouses = $product->getProductWarehouses();
            $xmlStores = $xmlProduct->addChild('inStoreAvailability');
            if (EntityHelper::isCountable($productWarehouses) && count($productWarehouses) > 0) {
                foreach ($productWarehouses as $productWarehouse) {
                    $xmlStore = $xmlStores->addChild('store');

                    $xmlStoreName = $xmlStore->addChild('name');
                    $xmlStoreName->addCData($productWarehouse->getWarehouse()->getName());

                    $xmlStoreQty = $xmlStore->addChild('quantity');
                    $xmlStoreQty->addCData($productWarehouse->getQty());
                }
            }*/

            $xmlProductBrand = $xmlProduct->addChild('brand');
            if (!empty($product->getBrand())) {
                $xmlProductBrand->addCData(StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product->getBrand(), "name")));
            }

            $xmlProductEan = $xmlProduct->addChild('ean');
            $xmlProductEan->addCData($product->getEan());

            //$xmlProductGuarantee = $xmlProduct->addChild('warranty');
            //$xmlProductGuarantee->addCData($product->getGuarantee());

            $productGroupsList = $this->prepareProductGroupList($product->getProductGroups());
            $xmlProductGroups = $xmlProduct->addChild('product_groups');
            $xmlProductGroups->addCData($productGroupsList);

            $xmlAttributes = $xmlProduct->addChild('attributes');
            $attributes = $product->getPreparedProductAttributes();
            if (EntityHelper::isCountable($attributes) && count($attributes) > 0) {
                foreach ($attributes as $attribute) {
                    $xmlAttribute = $xmlAttributes->addChild('attributes');
                    $xmlAttributeName = $xmlAttribute->addChild('name');
                    $xmlAttributeName->addCData($attribute["attribute"]->getName());
                    $xmlAttributeValues = $xmlAttribute->addChild('values');
                    foreach ($attribute["values"] as $value) {
                        $xmlAttributeData = $xmlAttributeValues->addChild('value');

                        $xmlAttributeValue = $xmlAttributeData->addChild('data');
                        $xmlAttributeValue->addCData($value["value"]);

                        $xmlAttributePrefix = $xmlAttributeData->addChild('prefix');
                        $xmlAttributePrefix->addCData($value["prefix"]);

                        $xmlAttributeSufix = $xmlAttributeData->addChild('sufix');
                        $xmlAttributeSufix->addCData($value["sufix"]);
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

    /**
     * @param $xmlProduct
     * @param ProductEntity $product
     * @param AccountEntity|null $account
     * @return mixed
     * @throws \Exception
     */
    public function exportManagerAddPrices($xmlProduct, ProductEntity $product, AccountEntity $account = null)
    {

        $prices = $this->crmProcessManager->getProductPrices($product, $account);

        /**
         * VPC
         */
        $xmlProductPrice = $xmlProduct->addChild('price');
        $xmlProductPrice->addCData(number_format($prices["price"], "2", ",", ""));

        $xmlProductDiscountPrice = $xmlProduct->addChild('discountPrice');
        $xmlProductDiscountFrom = $xmlProduct->addChild('discountFrom');
        $xmlProductDiscountTo = $xmlProduct->addChild('discountTo');

        if (!empty($prices["discount_price"]) && $prices["discount_price"] > 0) {
            $xmlProductDiscountPrice->addCData(number_format($prices["discount_price"], "2", ",", ""));
            if (!empty($product->getDateDiscountBaseFrom())) {
                $xmlProductDiscountFrom->addCData($product->getDateDiscountBaseFrom()->format("d.m.Y"));
            }
            if (!empty($product->getDateDiscountBaseTo())) {
                $xmlProductDiscountTo->addCData($product->getDateDiscountBaseTo()->format("d.m.Y"));
            }
        }

        $xmlProductPrice = $xmlProduct->addChild('originalPrice');
        $xmlProductPrice->addCData(number_format($prices["price"], "2", ",", ""));

        $xmlProductPrice = $xmlProduct->addChild('rebate');
        $xmlProductPrice->addCData(number_format($prices["rebate"], "2", ",", ""));

        /**
         * MPC
         */
        $xmlProductPrice = $xmlProduct->addChild('mpcPrice');
        $xmlProductPrice->addCData(number_format($prices["original_price"], "2", ",", ""));

        $xmlProductDiscountPrice = $xmlProduct->addChild('mpcDiscountPrice');
        $xmlProductDiscountFrom = $xmlProduct->addChild('mpcDiscountFrom');
        $xmlProductDiscountTo = $xmlProduct->addChild('mpcDiscountTo');

        if (!empty($prices["discount_price"]) && $prices["discount_price"] > 0) {
            $xmlProductDiscountPrice->addCData(number_format($prices["discount_price"], "2", ",", ""));
            if (!empty($product->getDateDiscountFrom())) {
                $xmlProductDiscountFrom->addCData($product->getDateDiscountFrom()->format("d.m.Y"));
            }
            if (!empty($product->getDateDiscountTo())) {
                $xmlProductDiscountTo->addCData($product->getDateDiscountTo()->format("d.m.Y"));
            }
        }

        $xmlProductCurCode = $xmlProduct->addChild('curCode');
        $xmlProductCurCode->addCData($product->getCurrency()->getCode());

        return $xmlProduct;
    }
}