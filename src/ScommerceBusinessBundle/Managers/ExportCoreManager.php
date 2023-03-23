<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Helpers\XmlHelper;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use CrmBusinessBundle\Entity\ProductImagesEntity;
use CrmBusinessBundle\Entity\ProductWarehouseLinkEntity;
use CrmBusinessBundle\Entity\WarehouseEntity;
use CrmBusinessBundle\Managers\DefaultExportManager;
use DOMDocument;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Entity\SWebsiteEntity;
use SimpleXMLElement;

class ExportCoreManager extends DefaultExportManager
{
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $filepath
     * @param $products
     * @return mixed
     * @throws \Exception
     */
    public function formatExport($filepath, $products){

        /** @var SimpleXMLElement $xml */
        $xml = new XmlHelper('<?xml version="1.0" encoding="UTF-8"?><data></data>');

        /**
         * Store
         */
        /** @var SStoreEntity $store */
        $store = $this->routeManager->getStoreById($this->storeId);

        /**
         * Website data
         */
        $xmlWebsiteData = $xml->addChild('website_data');

        /**
         * Website
         */
        /** @var SWebsiteEntity $website */
        $website = $store->getWebsite();

        $xmlWebsite = $xmlWebsiteData->addChild('website');
        $xmlWebsite->addAttribute('id', $website->getId());

        $xmlWebsiteUrl = $xmlWebsite->addChild('url');
        $xmlWebsiteUrl->addCdata($this->baseUrl);

        $stores = $website->getStores();

        $xmlStores = $xmlWebsite->addChild('stores');

        /** @var SStoreEntity $s */
        foreach ($stores as $s){
            $xmlStore = $xmlStores->addChild('store');

            $xmlStoreName = $xmlStore->addChild('name');
            $xmlStoreName->addCdata($s->getName());

            $xmlStoreCurrency = $xmlStore->addChild('default_currency');
            $xmlStoreCurrency->addCdata($s->getDisplayCurrency()->getCode());
            $xmlStoreCurrency->addAttribute('id', $s->getDisplayCurrency()->getId());

            $xmlStoreLanguage = $xmlStore->addChild('default_language');
            $xmlStoreLanguage->addCdata($s->getCoreLanguage()->getName());
            $xmlStoreLanguage->addAttribute('id', $s->getCoreLanguage()->getId());

            $xmlStore->addAttribute('id', $s->getId());
        }

        /**
         * Get default currency
         * Ako ce trebati exportati sve
         */
        $xmlCurrencies = $xmlWebsiteData->addChild('currencies');

        $xmlCurrency = $xmlCurrencies->addChild('currency');
        $xmlCurrency->addCdata($store->getDisplayCurrency()->getCode());
        $xmlCurrency->addAttribute('id', $store->getDisplayCurrency()->getId());

        /**
         * Get warehouses
         */
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));

        $warehouses = $this->productManager->getAllWarehouses($compositeFilter);

        $xmlWarehouses = $xml->addChild('warehouses');

        if(EntityHelper::isCountable($warehouses) && count($warehouses) > 0){

            /** @var WarehouseEntity $warehouse */
            foreach ($warehouses as $warehouse){

                $xmlWarehouse = $xmlWarehouses->addChild('warehouse');
                $xmlWarehouse->addAttribute('id', $warehouse->getId());

                $xmlWarehouseName = $xmlWarehouse->addChild('name');
                $xmlWarehouseName->addCData(StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $warehouse, "name")));

                $xmlWarehouseCity = $xmlWarehouse->addChild('city');
                $xmlWarehouseCountry = $xmlWarehouse->addChild('country');
                if(!empty($warehouse->getCity())){
                    $xmlWarehouseCity->addCData(StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $warehouse->getCity(), "name")));
                    $xmlWarehouseCity->addAttribute('id', $warehouse->getCity()->getId());
                    $xmlWarehouseCountry->addCData(StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $warehouse->getCity()->getCountry(), "name")));
                    $xmlWarehouseCountry->addAttribute('id', $warehouse->getCity()->getCountry()->getId());
                }

                $xmlWarehouseAddress = $xmlWarehouse->addChild('address');
                $xmlWarehouseAddress->addCData($warehouse->getAddress());

                $xmlWarehousePhone = $xmlWarehouse->addChild('phone');
                $xmlWarehousePhone->addCData($warehouse->getPhone());

                $xmlWarehouseEmail = $xmlWarehouse->addChild('email');
                $xmlWarehouseEmail->addCData($warehouse->getEmail());

                $xmlWarehouseLatitude = $xmlWarehouse->addChild('latitude');
                $xmlWarehouseLatitude->addCData($warehouse->getLatitude());

                $xmlWarehouseLongitude = $xmlWarehouse->addChild('longitude');
                $xmlWarehouseLongitude->addCData($warehouse->getLongitude());
            }
        }

        /**
         * Flat product groups
         */
        if(empty($this->productGroupManager)){
            $this->productGroupManager = $this->getContainer()->get("product_group_manager");
        }

        $productGroups = $this->productGroupManager->getProductGroupsByStore($store);

        $xmlCategories = $xml->addChild('categories');

        if(EntityHelper::isCountable($productGroups) && count($productGroups)){

            /** @var ProductGroupEntity $productGroup */
            foreach ($productGroups as $productGroup){

                $xmlCategory = $xmlCategories->addChild('category');
                $xmlCategory->addAttribute('id', $productGroup->getId());

                $xmlCategoryName = $xmlCategory->addChild('name');
                $xmlCategoryName->addCData(StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $productGroup, "name")));

                $xmlCategoryUrl = $xmlCategory->addChild('url');
                $xmlCategoryUrl->addCData($this->baseUrl . "/" . $productGroup->getUrlPath($this->storeId));

                $xmlCategoryParent = $xmlCategory->addChild('parent_id');
                $xmlCategoryParent->addCData($productGroup->getProductGroupId());

                $xmlCategoryLevel = $xmlCategory->addChild('level');
                $xmlCategoryLevel->addCData($productGroup->getLevel());
            }
        }

        $xmlItems = $xml->addChild('items');

        /** @var ProductEntity $product */
        foreach ($products as $product) {

            $prices = $this->crmProcessManager->getProductPrices($product);

            $xmlItem = $xmlItems->addChild('item');

            // ID
            $xmlItemID = $xmlItem->addChild('id');
            $xmlItemID->addCData($product->getId());

            // CREATED
            $xmlItemCreated = $xmlItem->addChild('created');
            $xmlItemCreated->addCData($product->getCreated()->format("Y-m-d H:i:s"));

            // MODIFIED
            $xmlItemModified = $xmlItem->addChild('modified');
            $xmlItemModified->addCData($product->getCreated()->format("Y-m-d H:i:s"));

            // EAN
            $xmlItemEAN = $xmlItem->addChild('ean');
            $xmlItemEAN->addCData($product->getEan());

            // CATALOG_CODE
            $xmlItemMpn = $xmlItem->addChild('catalog_code');
            $xmlItemMpn->addCData($product->getCatalogCode());

            // CODE
            $xmlItemMpn = $xmlItem->addChild('code');
            $xmlItemMpn->addCData($product->getCode());

            // BRAND
            $xmlItemBrand = $xmlItem->addChild('brand');
            if(!empty($product->getBrand())){
                $xmlItemBrand->addCData(StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product->getBrand(), "name")));
                $xmlItemBrand->addAttribute('id', $product->getBrand()->getId());
            }

            // TITLE
            $xmlItemName = $xmlItem->addChild('title');
            $xmlItemName->addCData(StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "name")));

            // SHORT DESCRIPTION
            $xmlItemShortDescription = $xmlItem->addChild('short_description');
            $xmlItemShortDescription->addCData(StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "short_description")));

            // DESCRIPTION
            $xmlItemDescription = $xmlItem->addChild('description');
            $xmlItemDescription->addCData(StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "description")));

            // IMAGES
            $xmlItemImages = $xmlItem->addChild('images');

            $productImages = $product->getImages();
            if(!empty($productImages)){
                /** @var ProductImagesEntity $productImage */
                foreach ($productImages as $productImage){
                    $xmlItemImage = $xmlItemImages->addChild('image');
                    $xmlItemImage->addCData($this->baseDocumentUrl . "/Documents/Products/" . $productImage->getFile());
                    $xmlItemImage->addAttribute('id', $productImage->getId());
                }
            }

            // URL
            $xmlItemUrl = $xmlItem->addChild('url');
            $xmlItemUrl->addCData($this->baseUrl . "/" . $this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "url"));

            // PRICE
            $price = floatval($prices["tracking_price"]);
            $xmlItemPrice = $xmlItem->addChild('price');
            $xmlItemPrice->addCData(number_format($price, "2", ".", ""));
            $xmlItemPrice->addAttribute('currency_id', $product->getCurrency()->getId());

            //DISCOUNT_PRICE
            $discountPrice = floatval($prices["discount_tracking_price"]);
            $xmlItemPriceDiscount = $xmlItem->addChild('discount_price');
            if(floatval($discountPrice) > 0){
                $xmlItemPriceDiscount->addCData(number_format($discountPrice, "2", ".", ""));
                $xmlItemPriceDiscount->addAttribute('currency_id', $product->getCurrency()->getId());
            }

            //AVAILABILITY
            $xmlItemAvailability = $xmlItem->addChild('is_saleable');
            $xmlItemAvailability->addCData($product->getIsSaleable());

            //STORE AVAILABILITY
            $xmlItemStoreAvailability = $xmlItem->addChild('store_availability');

            $productWarehouses = $product->getProductWarehouses();
            if (EntityHelper::isCountable($productWarehouses) && count($productWarehouses) > 0) {
                /** @var ProductWarehouseLinkEntity $productWarehouse */
                foreach ($productWarehouses as $productWarehouse) {

                    $qty = number_format($productWarehouse->getQty(), 0, "", "");
                    if (!$productWarehouse->getWarehouse()->getIsActive()) {
                        continue;
                    }

                    $xmlItemStoreAvailabilityStore = $xmlItemStoreAvailability->addChild('store');
                    $xmlItemStoreAvailabilityStore->addCData($qty);
                    $xmlItemStoreAvailabilityStore->addAttribute('id', $productWarehouse->getWarehouse()->getId());
                }
            }

            // CATEGORY
            $xmlItemCategory = $xmlItem->addChild('category');

            /** @var ProductGroupEntity $lowestProductGroup */
            $lowestProductGroup = $this->getLowestLevelProductGroup($product->getProductGroups());

            if(!empty($lowestProductGroup)){
                $xmlItemCategory->addCData($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $lowestProductGroup, "name"));
                $xmlItemCategory->addAttribute('id', $lowestProductGroup->getId());
            }

            //ATTRIBUTES
            $attributes = $product->getPreparedProductAttributes();
            if (EntityHelper::isCountable($attributes) && count($attributes) > 0) {
                foreach ($attributes as $attribute) {

                    $xmlItemAttributes = $xmlItem->addChild('attributes');

                    $xmlAttributeAttribute = $xmlItemAttributes->addChild('attribute');

                    $xmlAttributeName = $xmlAttributeAttribute->addChild('name');

                    $xmlAttributeName->addCData($attribute["attribute"]->getName());

                    $xmlAttributeValues = $xmlAttributeAttribute->addChild('values');

                    foreach ($attribute["values"] as $val) {
                        $xmlAttributeValuesValue = $xmlAttributeValues->addChild('value');
                        $xmlAttributeValuesValue->addCData($val["prefix"]);
                        $xmlAttributeValuesValue->addCData($val["value"]);
                        $xmlAttributeValuesValue->addCData($val["sufix"]);
                    }
                }
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
     * @param $productGroups
     * @return string
     */
    public function getLowestLevelProductGroup($productGroups)
    {
        $ret = "";

        if (!EntityHelper::isCountable($productGroups) || count($productGroups) == 0) {
            return $ret;
        }

        $lowestProductGroup = null;

        /** @var ProductGroupEntity $productGroupLink */
        foreach ($productGroups as $productGroup) {
            if (empty($lowestProductGroup) || $productGroup->getLevel() > $lowestProductGroup->getLevel()) {
                $lowestProductGroup = $productGroup;
            }
        }

        return $lowestProductGroup;
    }
}