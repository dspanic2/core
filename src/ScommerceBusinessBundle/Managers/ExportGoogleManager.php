<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Helpers\StringHelper;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductImagesEntity;
use CrmBusinessBundle\Managers\DefaultExportManager;

class ExportGoogleManager extends DefaultExportManager
{
    public function initialize()
    {
        parent::initialize();

        $this->extension = "csv";
    }

    /**
     * @param $filepath
     * @param $products
     * @return mixed
     * @throws \Exception
     */
    public function formatExport($filepath, $products){

        $fp = fopen($filepath, 'w');

        $productsArray = Array();

        /** @var ProductEntity $product */
        foreach ($products as $product) {
            $productId = $product->getId();

            $prices = $this->crmProcessManager->getProductPrices($product);

            // Get product image, ako proizvod nema glavnu sliku, ne izlazi vani
            /** @var ProductImagesEntity $mainImage */
            $mainImage = $product->getSelectedImage();
            if (empty($mainImage)) {
                continue;
            }

            $title = trim(StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "name")));

            $description = null;
            if(isset($product->getDescription()[$this->storeId])){
                $description = trim(StringHelper::removeNonAsciiCharacters($this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "description")));
            }

            // Title max length is 150
            if (mb_strlen($title) > 150) {
                $title = mb_substr($title, 0, 147) . "...";
            }

            // Description max length is 9999
            if (mb_strlen($description) > 2000) {
                $title = mb_substr($title, 0, 1997) . "...";;
            }

            $productsArray[$productId]["ID"] = $product->getId();
            $productsArray[$productId]["ID2"] = $product->getCode();
            $productsArray[$productId]["Item title"] = $title;
            $productsArray[$productId]["Item description"] = $description;
            $productsArray[$productId]["Price"] = number_format($prices["tracking_price"], "2", ".", "") . " " . $product->getCurrency()->getCode();
            $productsArray[$productId]["Sale price"] = null;
            if(floatval($prices["discount_tracking_price"]) > 0){
                $productsArray[$productId]["Sale price"] = number_format($prices["discount_tracking_price"], "2", ".", "") . " " . $product->getCurrency()->getCode();
            }
            $productsArray[$productId]["Image URL"] = $this->baseDocumentUrl . "/" . "Documents/Products/" . $mainImage->getFile();

            $productsArray[$productId]["Final URL"] = "";
            if (!empty($product->getUrl())) {
                $productsArray[$productId]["Final URL"] = $this->baseUrl . "/" . $this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "url");
            }
            $productsArray[$productId]["Final mobile URL"] = $this->baseUrl. "/" . $this->getPageUrlExtension->getEntityStoreAttribute($this->storeId, $product, "url");

            $productsArray[$productId]["Item subtitle"] = "";
            $productsArray[$productId]["Item category"] = "";

            $productGroups = $product->getProductGroups();
            if (!empty($productGroups)) {
                $productGroupsList = $this->prepareProductGroupList($productGroups);
                $productsArray[$productId]["Item category"] = $productGroupsList;
            }

            $productsArray[$productId]["Item address"] = "";
            $productsArray[$productId]["Custom parameter"] = "";
            $productsArray[$productId]["Contextual keywords"] = "";
            $productsArray[$productId]["Tracking template"] = "";
            $productsArray[$productId]["Android app link"] = "";
            $productsArray[$productId]["iOS app link"] = "";
            $productsArray[$productId]["iOS app store ID"] = "";

            $this->entityManager->detach($product);
            $this->entityManager->detach($mainImage);
        }

        /**
         * Set csv headers from the assoc array keys
         */
        $columns = array_keys($productsArray[array_key_first($productsArray)]);
        fputcsv($fp, $columns, ",", '"');

        /**
         * Set csv body from the assoc array values
         */
        foreach ($productsArray as $productKey => $productAttributes) {

            $row = array();
            foreach ($productAttributes as $attributeKey => $attribute) {
                $row[$attributeKey] = $attribute;
            }

            fputcsv($fp, $row, ",", '"');
        }

        fclose($fp);

        return $filepath;
    }
}
