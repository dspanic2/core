<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Helpers\XmlHelper;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductImagesEntity;
use CrmBusinessBundle\Managers\DefaultExportManager;

class ExportGoogleXmlManager extends DefaultExportManager
{
    const GOOGLE_MERCHANT_EXPORT_TITLE_MAX_LENGTH = 150;
    const GOOGLE_MERCHANT_EXPORT_DESCRIPTION_MAX_LENGTH = 9999;
    const AVAILABILITY_MAP = [0 => "out of stock", 1 => "in stock"];
    const STATUS_MAP = [0 => "archived", 1 => "active"];

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

        $xml = new XmlHelper('<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0" encoding="utf-8"/>');

        $xmlConfig = $xml->addChild('config');
        $xmlChannel = $xml->addChild('channel');

        $xmlStore = $xmlConfig->addChild('system', null, "http://base.google.com/ns/1.0");
        $siteName = $_ENV["SITE_NAME"] ?? "Store name";
        $xmlStore->addCData($siteName);

        $xmlUrl = $xmlConfig->addChild('url', null, "http://base.google.com/ns/1.0");
        $xmlUrl->addCData($this->baseUrl);

        $xmlGenerated = $xmlConfig->addChild('generated', null, "http://base.google.com/ns/1.0");
        $xmlGenerated->addCData((new \DateTime())->format("Y-m-d H:i:s"));

        /** @var ProductEntity $product */
        foreach ($products as $product) {

            $title = StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "name"));
            if (empty($title)) {
                continue;
            }

            $description = StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "description"));
            if (!empty($description)) {
                if (mb_strlen($title) > self::GOOGLE_MERCHANT_EXPORT_TITLE_MAX_LENGTH) {
                    $title = mb_substr($title, 0, self::GOOGLE_MERCHANT_EXPORT_TITLE_MAX_LENGTH - 3) . "...";
                }
                if (mb_strlen($description) > self::GOOGLE_MERCHANT_EXPORT_DESCRIPTION_MAX_LENGTH) {
                    $description = mb_substr($description, 0, self::GOOGLE_MERCHANT_EXPORT_DESCRIPTION_MAX_LENGTH - 3) . "...";
                }
            }

            /** @var ProductImagesEntity $mainImage */
            $mainImage = $product->getSelectedImage();
            if (empty($mainImage)) {
                continue;
            }

            $xmlItem = $xmlChannel->addChild('item');

            $xmlItemId = $xmlItem->addChild('id', null, "http://base.google.com/ns/1.0");
            $xmlItemId->addCData($product->getId());

            $xmlItemTitle = $xmlItem->addChild('title', null, "http://base.google.com/ns/1.0");
            $xmlItemTitle->addCData($title);

            $xmlItemDescription = $xmlItem->addChild('description', null, "http://base.google.com/ns/1.0");
            $xmlItemDescription->addCData($description);

            $xmlItemLink = $xmlItem->addChild('link', null, "http://base.google.com/ns/1.0");
            $xmlItemLink->addCData($this->baseUrl . "/" . $this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "url"));

            $xmlItemImageLink = $xmlItem->addChild('image_link', null, "http://base.google.com/ns/1.0");
            $xmlItemImageLink->addCData($this->baseDocumentUrl . "/Documents/Products/" . $mainImage->getFile());

            if (EntityHelper::isCountable($product->getImages()) && count($product->getImages()) > 1) {
                $total = 0;
                /** @var ProductImagesEntity $image */
                foreach ($product->getImages() as $image) {
                    if ($image->getId() == $mainImage->getId()) {
                        continue;
                    }
                    if (++$total >= 10) { // up to 10 additional_image_link attributes allowed
                        break;
                    }
                    $xmlItemAdditionalImageLink = $xmlItem->addChild('additional_image_link', null, "http://base.google.com/ns/1.0");
                    $xmlItemAdditionalImageLink->addCData($this->baseDocumentUrl . "/Documents/Products/" . $image->getFile());
                }
            }

            $xmlItemAvailability = $xmlItem->addChild('availability', null, "http://base.google.com/ns/1.0");
            $xmlItemAvailability->addCData(self::AVAILABILITY_MAP[$product->getIsSaleable()]);

            $xmlItemStatus = $xmlItem->addChild('status', null, "http://base.google.com/ns/1.0");
            $xmlItemStatus->addCData(self::STATUS_MAP[$product->getActive()]);

            $prices = $this->crmProcessManager->getProductPrices($product);

            $xmlItemPrice = $xmlItem->addChild('price', null, "http://base.google.com/ns/1.0");
            $xmlItemPrice->addCData(number_format($prices["tracking_price"], "2", ".", "") . " " . $product->getCurrency()->getCode());

            if (floatval($prices["discount_tracking_price"]) > 0) {
                $xmlItemSalePrice = $xmlItem->addChild('sale_price', null, "http://base.google.com/ns/1.0");
                $xmlItemSalePrice->addCData(number_format($prices["discount_tracking_price"], "2", ".", "") . " " . $product->getCurrency()->getCode());
                if (!empty($product->getDateDiscountFrom()) && !empty($product->getDateDiscountTo())) {
                    $xmlItemSalePriceEffectiveDate = $xmlItem->addChild('sale_price_effective_date', null, "http://base.google.com/ns/1.0");
                    $xmlItemSalePriceEffectiveDate->addCData($product->getDateDiscountFrom()->format(\DateTime::ATOM) . " / " . $product->getDateDiscountTo()->format(\DateTime::ATOM));
                }
            }

            $xmlItem->addChild('google_product_category', null, "http://base.google.com/ns/1.0");
            $xmlItem->addChild('product_type', null, "http://base.google.com/ns/1.0");

            if (!empty($product->getBrand())) {
                $brandName = $this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product->getBrand(), "name");
                if (!empty($brandName)) {
                    $xmlItemBrand = $xmlItem->addChild('brand', null, "http://base.google.com/ns/1.0");
                    $xmlItemBrand->addCData($brandName);
                }
            } else {
                $xmlItemIdentifierExists = $xmlItem->addChild('identifier_exists', null, "http://base.google.com/ns/1.0");
                $xmlItemIdentifierExists->addCData("no");
            }

            $xmlItemCondition = $xmlItem->addChild('condition', null, "http://base.google.com/ns/1.0");
            $xmlItemCondition->addCData("new");

            $this->entityManager->detach($product);
            unset($product);
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
