<?php

namespace ScommerceBusinessBundle\Managers;

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
use SimpleXMLElement;

class ExportShopguruManager extends DefaultExportManager
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
        $xml = new XmlHelper('<?xml version="1.0" encoding="UTF-8"?><items></items>');

        /** @var ProductEntity $product */
        foreach ($products as $product) {

            $productGroups = $product->getProductGroups();
            if (empty($productGroups)) {
                continue;
            }
            $prices = $this->crmProcessManager->getProductPrices($product);
            if (empty($prices)) {
                continue;
            }

            $lowestProductGroup = $this->getLowestLevelProductGroup($productGroups);

            $xmlItem = $xml->addChild('item');

            // ID
            $xmlItemID = $xmlItem->addChild('id');
            $xmlItemID->addCData($product->getId());

            // EAN
            $xmlItemEAN = $xmlItem->addChild('ean');
            $xmlItemEAN->addCData($product->getEan());

            // MPN/CODE
            $xmlItemMpn = $xmlItem->addChild('mpn');
            $xmlItemMpn->addCData($product->getCatalogCode());

            // BRAND
            $xmlItemBrand = $xmlItem->addChild('brand');
            $xmlItemBrand->addCData($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product->getBrand(), "name"));

            // CATEGORY
            $xmlItemCategory = $xmlItem->addChild('category');
            $xmlItemCategory->addCData($lowestProductGroup);

            // TITLE
            $title = StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "name"));
            if (empty($title)) {
                continue;
            }
            if (mb_strlen($title) > 200) {
                $title = mb_substr($title, 0, 200 - 3) . "...";
            }

            $xmlItemName = $xmlItem->addChild('title');
            $xmlItemName->addCData($title);

            // DESCRIPTION
            $shortDescription = StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "short_description"));
            if (empty($shortDescription)) {
                continue;
            }

            $xmlItemDescription = $xmlItem->addChild('description');
            $xmlItemDescription->addCData($shortDescription);

            // IMAGE
            /** @var ProductImagesEntity $image */
            $image = $product->getSelectedImage();
            if(empty($image)){
                continue;
            }
            $xmlItemImage = $xmlItem->addChild('image');
            $xmlItemImage->addCData($this->baseDocumentUrl . "/Documents/Products/" . $image->getFile());

            // URL
            $xmlItemUrl = $xmlItem->addChild('url');
            $xmlItemUrl->addCData($this->baseUrl . "/" . $this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "url"));

            // PRICE
            $price = floatval($prices["tracking_price"]);
            if (floatval($prices["discount_tracking_price"]) > 0 && $price >= floatval($prices["discount_tracking_price"])) {
                $price = floatval($prices["discount_tracking_price"]);
            }

            $xmlItemPrice = $xmlItem->addChild('price');
            $xmlItemPrice->addCData(number_format($price, "2", ".", ""));

            //CURRENCY CODE
            $xmlItemCurCode = $xmlItem->addChild('currency-code');
            $xmlItemCurCode->addCData($product->getCurrency()->getCode());

            //AVAILABILITY
            $xmlItemAvailability = $xmlItem->addChild('availability');
            if ($product->getQty() == 0) {
                $xmlItemAvailability->addCData('Po narudžbi');
            } else {
                $xmlItemAvailability->addCData(number_format($product->getQty(), 0, "", ""));
            }

            //STORE AVAILABILITY
            $productWarehouses = $product->getProductWarehouses();
            if (EntityHelper::isCountable($productWarehouses) && count($productWarehouses) > 0) {
                /** @var ProductWarehouseLinkEntity $productWarehouse */
                foreach ($productWarehouses as $productWarehouse) {

                    $qty = number_format($productWarehouse->getQty(), 0, "", "");
                    if (empty($qty)) {
                        continue;
                    }

                    $xmlItemStoreAvailability = $xmlItem->addChild('store-availability');

                    $xmlItemStoreAvailabilityStore = $xmlItemStoreAvailability->addChild('store');

                    /** @var WarehouseEntity $warehouse */
                    $warehouse = $productWarehouse->getWarehouse();

                    $xmlItemStoreAvailabilityStore->addCData($warehouse->getAddress() . ", " . $warehouse->getCity()->getPostalCode() . " " . $warehouse->getCity()->getName());

                    $xmlItemInStoreAvailabilityQuantity = $xmlItemStoreAvailability->addChild('quantity');
                    $xmlItemInStoreAvailabilityQuantity->addCData($qty);
                }
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

                    $xmlAttributeValuesValue = $xmlAttributeValues->addChild('value');

                    $value = "";
                    foreach ($attribute["values"] as $val) {
                        $value .= $val["prefix"] . $val["value"] . $val["sufix"] . ", ";
                    }
                    $value = substr_replace(trim($value), "", -1);
                    $xmlAttributeValuesValue->addCData($value);
                }
            }

            //WARRANTY
            $xmlItemGuarantee = $xmlItem->addChild('warranty');
            $xmlItemGuarantee->addCData($product->getGuarantee());

            // optional: delivery-time-min
            // optional: delivery-time-max
            // optional: coupon-title
            // optional: coupon-code

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

        if ($lowestProductGroup) {
            $ret = $this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $lowestProductGroup, "name");
        }

        return $ret;
    }
}