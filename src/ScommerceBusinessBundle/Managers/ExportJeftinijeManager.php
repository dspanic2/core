<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\XmlHelper;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductImagesEntity;
use CrmBusinessBundle\Entity\ProductWarehouseLinkEntity;
use CrmBusinessBundle\Entity\WarehouseEntity;
use CrmBusinessBundle\Managers\DefaultExportManager;
use DOMDocument;
use SimpleXMLElement;

class ExportJeftinijeManager extends DefaultExportManager
{
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $productGroups
     * @return array|string|string[]
     */
    public function prepareProductGroupList($productGroups)
    {
        $ret = parent::prepareProductGroupList($productGroups);

        if (stripos($ret, ">") !== false) {
            $ret = str_ireplace(">", "-", $ret);
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
        $xml = new XmlHelper('<?xml version="1.0" encoding="utf-8"?><CNJExport></CNJExport>');

        /** @var ProductEntity $product */
        foreach ($products as $product) {

            $xmlItem = $xml->addChild('Item');

            $xmlItemID = $xmlItem->addChild('ID');
            $xmlItemID->addCData($product->getId());

            $xmlItemName = $xmlItem->addChild('name');
            $xmlItemName->addCData(substr($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "name"), 0, 200));

            /*$shortDescription = json_decode($product->getShortDescription());
            if (isset($shortDescription["hr"])) {
                $shortDescription = $shortDescription["hr"];
            } else if (isset($shortDescription["en"])) {
                $shortDescription = $shortDescription["en"];
            }*/
            $shortDescription = '<p>' . $this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "short_description") . '</p>';

            $xmlItemDescription = $xmlItem->addChild('description');
            $xmlItemDescription->addCData($shortDescription);

            $attributesHtml = null;

            $attributes = $product->getPreparedProductAttributes();
            if (EntityHelper::isCountable($attributes) && count($attributes) > 0) {
                $attributesHtml = '<ul>';
                foreach ($attributes as $attribute) {
                    $attributesHtml .= '<li>' . $this->translator->trans($attribute["attribute"]->getName()) . ': ';
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
                        $attributesHtml .= trim($tmp);
                    }
                    $attributesHtml .= '</li>';
                }
                $attributesHtml .= '</ul>';
            }

            $xmlItemSpecifications = $xmlItem->addChild('specifications');
            $xmlItemSpecifications->addCData($attributesHtml);

            $xmlItemLink = $xmlItem->addChild('link');
            $xmlItemLink->addCData($this->baseUrl . "/" . $this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "url"));

            $xmlItemMainImage = $xmlItem->addChild('mainImage');

            /** @var ProductImagesEntity $mainImage */
            $mainImage = $product->getSelectedImage();
            if (!empty($mainImage)) {
                $xmlItemMainImage->addCData($this->baseDocumentUrl . "/Documents/Products/" . $mainImage->getFile());
            }

            $moreImages = null;

            if (EntityHelper::isCountable($product->getImages()) && count($product->getImages()) > 1) {
                /** @var ProductImagesEntity $image */
                foreach ($product->getImages() as $index => $image) {
                    if ($image->getId() == $mainImage->getId()) {
                        continue;
                    }
                    if ($index > 0) {
                        $moreImages .= ",";
                    }
                    $moreImages .= $this->baseDocumentUrl . "/Documents/Products/" . $image->getFile();
                }
            }

            $xmlItemMoreImages = $xmlItem->addChild('moreImages');
            $xmlItemMoreImages->addCData($moreImages);

            // optional: videoUrl

            $prices = $this->crmProcessManager->getProductPrices($product);

            $price = $regularPrice = floatval($prices["tracking_price"]);

            if (floatval($prices["discount_tracking_price"]) > 0 && $price >= floatval($prices["discount_tracking_price"])) {
                $price = $prices["discount_tracking_price"];
            }

            $xmlItemPrice = $xmlItem->addChild('price');
            $xmlItemPrice->addCData(number_format($price, "2", ",", ""));

            if ($price != $regularPrice) {
                $xmlItemRegularPrice = $xmlItem->addChild('regularPrice');
                $xmlItemRegularPrice->addCData(number_format($price, "2", ",", ""));
            }

            // optional: clubPrice

            $xmlItemCurCode = $xmlItem->addChild('curCode');
            $xmlItemCurCode->addCData($product->getCurrency()->getCode());

            // optional: stockText

            $xmlItemStock = $xmlItem->addChild('stock');
            $stock = "out of stock";
            if ($product->getIsSaleable()) {
                $stock = "in stock";
            }
            $xmlItemStock->addCData($stock);

            $productWarehouses = $product->getProductWarehouses();
            if (EntityHelper::isCountable($productWarehouses) && count($productWarehouses) > 0) {
                /** @var ProductWarehouseLinkEntity $productWarehouse */
                foreach ($productWarehouses as $productWarehouse) {
                    $xmlItemInStoreAvailability = $xmlItem->addChild('inStoreAvailability');

                    $xmlItemInStoreAvailabilityStore = $xmlItemInStoreAvailability->addChild('store');

                    /** @var WarehouseEntity $warehouse */
                    $warehouse = $productWarehouse->getWarehouse();

                    $availabilityText = Array();
                    if(!empty($warehouse->getAddress())){
                        $availabilityText[]=$warehouse->getAddress();
                    }
                    if(!empty($warehouse->getCity())){
                        $availabilityText[]=$warehouse->getCity()->getPostalCode()." ".$warehouse->getCity()->getName();
                    }
                    $availabilityText = implode(", ",$availabilityText);

                    $xmlItemInStoreAvailabilityStore->addCData($availabilityText);

                    $xmlItemInStoreAvailabilityAvailability = $xmlItemInStoreAvailability->addChild('availability');
                    if ($productWarehouse->getQty() > 0) {
                        $xmlItemInStoreAvailabilityAvailability->addCData("today");
                    } else {
                        $xmlItemInStoreAvailabilityAvailability->addCData("no");
                    }

                    $xmlItemInStoreAvailabilityQuantity = $xmlItemInStoreAvailability->addChild('quantity');
                    $xmlItemInStoreAvailabilityQuantity->addCData(number_format($productWarehouse->getQty(), 0, "", ""));
                }
            }

            $productGroupsList = $this->prepareProductGroupList($product->getProductGroups());

            $xmlItemFileUnder = $xmlItem->addChild('fileUnder');
            $xmlItemFileUnder->addCData($productGroupsList);

            // optional: cenCategoryId

            if (!empty($product->getBrand())) {
                $xmlItemBrand = $xmlItem->addChild('brand');
                $xmlItemBrand->addCData($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product->getBrand(), "name"));
            }

            $xmlItemEAN = $xmlItem->addChild('EAN');
            $xmlItemEAN->addCData($product->getEan());

            $xmlItemProductCode = $xmlItem->addChild('productCode');
            $xmlItemProductCode->addCData($product->getCode());

            // optional: CUIN
            // optional: productCode
            // optional: productModel

            $xmlItemCondition = $xmlItem->addChild('condition');
            $xmlItemCondition->addCData("new");

            /*$xmlItemWarranty = $xmlItem->addChild('warranty');
            $xmlItemWarranty->addCData($product->getGuarantee());*/

            // optional: coupon
            // optional: couponCode
            // optional: gift

            //$xmlItemDeliveryCost = $xmlItem->addChild('deliveryCost');
            //$xmlItemDeliveryCost->addCData(0.00);

            // optional: deliveryTimeMin
            // optional: deliveryTimeMax

            /**
             * TODO: majice, obuca
             */

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