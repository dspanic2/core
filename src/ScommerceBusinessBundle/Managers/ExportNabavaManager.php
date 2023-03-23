<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\XmlHelper;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductImagesEntity;
use CrmBusinessBundle\Managers\DefaultExportManager;
use DOMDocument;
use SimpleXMLElement;

class ExportNabavaManager extends DefaultExportManager
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
        $xml = new XmlHelper('<?xml version="1.0" encoding="utf-8"?><products></products>');

        /** @var ProductEntity $product */
        foreach ($products as $product) {

            $xmlProduct = $xml->addChild('product');

            $xmlProductName = $xmlProduct->addChild('name');
            $xmlProductName->addCData($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "name"));

            $prices = $this->crmProcessManager->getProductPrices($product);

            $price = $regularPrice = floatval($prices["tracking_price"]);

            if (floatval($prices["discount_tracking_price"]) > 0 && $price >= floatval($prices["discount_tracking_price"])) {
                $price = $prices["discount_tracking_price"];
            }

            $xmlProductPrice = $xmlProduct->addChild('price');
            $xmlProductPrice->addCData(number_format($price, "2", ",", ""));

            $xmlProductUrl = $xmlProduct->addChild('url');
            $xmlProductUrl->addCData($this->baseUrl . "/" . $this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "url"));

            /**
             * TODO: ne hardcodano
             */
            $xmlProductAvailability = $xmlProduct->addChild('availability');
            if ($product->getIsSaleable()) {
                $xmlProductAvailability->addCData("1 / da / raspoloživo odmah");
            } else {
                $xmlProductAvailability->addCData("0 / ne / nije raspoloživo");
            }
            /*2 / raspoloživo, isporuka nakon uplate
            3 / stiže za X dana*/

            $xmlProductInternalId = $xmlProduct->addChild('internal_product_id');
            $xmlProductInternalId->addCData($product->getId());

            $productGroups = $product->getProductGroups();
            $productGroupsList = $this->prepareProductGroupList($productGroups);

            $xmlProductCategory = $xmlProduct->addChild('category');
            $xmlProductCategory->addCData($productGroupsList);

            $xmlProductImageUrl = $xmlProduct->addChild('image_url');

            /** @var ProductImagesEntity $mainImage */
            $mainImage = $product->getSelectedImage();
            if (!empty($mainImage)) {
                $xmlProductImageUrl->addCData($this->baseDocumentUrl . "/Documents/Products/" . $mainImage->getFile());
            }

            $xmlProductDescription = $xmlProduct->addChild('description');
            /*$shortDescription = json_decode($product->getShortDescription());
            if (isset($shortDescription)) {
                $shortDescription = $shortDescription;
            } else if (isset($shortDescription["en"])) {
                $shortDescription = $shortDescription["en"];
            }*/
            $xmlProductDescription->addCData($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "short_description"));

            /**
             * TODO: shipping_cost
             * TODO: gtin
             * TODO: mpn
             * TODO: price_credit_cards
             */

            if ($price != $regularPrice) {
                $xmlProductRegularPrice = $xmlProduct->addChild('regular_price');
                $xmlProductRegularPrice->addCData(number_format($regularPrice, "2", ",", ""));
            }

            /**
             * TODO: mobile_url
             * TODO: comment
             * TODO: shipping_info
             */

            $xmlProductBrand = $xmlProduct->addChild('brand');
            if (!empty($product->getBrand())) {
                $xmlProductBrand->addCData($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product->getBrand(), "name"));
            }

            /**
             * TODO: brand_product_url
             */

            //$xmlProductGuarantee = $xmlProduct->addChild('warranty');
            //$xmlProductGuarantee->addCData($product->getGuarantee());

            $attributes = $product->getPreparedProductAttributes();
            if (EntityHelper::isCountable($attributes) && count($attributes) > 0) {
                foreach ($attributes as $attribute) {
                    $xmlProductSpecification = $xmlProduct->addChild('specification');
                    $xmlAttributeKey = $xmlProductSpecification->addChild('key');
                    $xmlAttributeKey->addCData($this->translator->trans($attribute["attribute"]->getName()));
                    $xmlAttributeValue = $xmlProductSpecification->addChild('value');
                    $value = array();
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
                        $value[] = trim($tmp);
                    }
                    $value = implode(", ", $value);
                    $xmlAttributeValue->addCData($value);
                }
            }

            if (EntityHelper::isCountable($product->getImages()) && count($product->getImages()) > 1) {
                /** @var ProductImagesEntity $image */
                foreach ($product->getImages() as $image) {
                    if ($image->getId() == $mainImage->getId()) {
                        continue;
                    }
                    $xmlProductAdditionalImageUrl = $xmlProduct->addChild('additional_image_url');
                    $xmlProductAdditionalImageUrl->addCData($this->baseDocumentUrl . "/Documents/Products/" . $image->getFile());
                }
            }

            /**
             * TODO: parent_id
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