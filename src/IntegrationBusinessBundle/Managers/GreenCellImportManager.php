<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Helpers\FileHelper;
use AppBundle\Models\InsertModel;
use AppBundle\Models\UpdateModel;
use CrmBusinessBundle\Constants\CrmConstants;
use IntegrationBusinessBundle\Models\ProductGroupModel;
use Symfony\Component\Console\Helper\ProgressBar;

class GreenCellImportManager extends DefaultIntegrationImportManager
{
    /** @var string $apiUrl */
    private $apiUrl;
    /** @var string $supplierId */
    private $supplierId;
    /** @var string $currencyFrom */
    private $currencyFrom;
    /** @var string $currencyTo */
    private $currencyTo;

    /** @var AttributeSet $asProduct */
    protected $asProduct;
    /** @var AttributeSet $asSRoute */
    protected $asSRoute;
    /** @var AttributeSet $asProductImages */
    protected $asProductImages;
    /** @var AttributeSet $asProductProductGroupLink */
    protected $asProductProductGroupLink;
    /** @var AttributeSet $asIntGreenCellCategory */
    protected $asIntGreenCellCategory;

    protected $productInsertAttributes;
    protected $productUpdateAttributes;
    protected $productCustomAttributes;

    public function initialize()
    {
        parent::initialize();

        $this->apiUrl = $_ENV["GREENCELL_API_URL"];
        $this->supplierId = $_ENV["GREENCELL_SUPPLIER_ID"];
        $this->currencyFrom = $_ENV["GREENCELL_CURRENCY_FROM"];
        $this->currencyTo = $_ENV["GREENCELL_CURRENCY_TO"];

        $this->productInsertAttributes = $this->getAttributesFromEnv("GREENCELL_PRODUCT_INSERT_ATTRIBUTES");
        $this->productUpdateAttributes = $this->getAttributesFromEnv("GREENCELL_PRODUCT_UPDATE_ATTRIBUTES");
        $this->productCustomAttributes = $this->getAttributesFromEnv("GREENCELL_PRODUCT_CUSTOM_ATTRIBUTES", false);

        if (!file_exists($this->getImportDir())) {
            mkdir($this->getImportDir());
        }

        $this->setRemoteSource("greencell");

        $this->asProduct = $this->entityManager->getAttributeSetByCode("product");
        $this->asSRoute = $this->entityManager->getAttributeSetByCode("s_route");
        $this->asProductImages = $this->entityManager->getAttributeSetByCode("product_images");
        $this->asProductProductGroupLink = $this->entityManager->getAttributeSetByCode("product_product_group_link");
        $this->asIntGreenCellCategory = $this->entityManager->getAttributeSetByCode("int_greencell_category");
    }

    /**
     * @param $entityArray
     * @return mixed
     */
    protected function getProductImageFilename($entityArray)
    {
        $imageUrl = $entityArray["url"];
        $imageId = $entityArray["image_id"];
        unset($entityArray["image_id"]);

        $filename = $imageId . "-" . $this->helperManager->getFilenameFromUrl($imageUrl);
        $extension = $this->helperManager->getFileExtension($filename);
        $filename = $this->helperManager->getFilenameWithoutExtension($filename);
        $file = $entityArray["product_id"] . "/" . $filename . "." . $extension;

        $entityArray["file"] = $file;
        $entityArray["filename"] = $filename;
        $entityArray["file_type"] = $extension;

        return $entityArray;
    }

    /**
     * @param $entityArray
     * @return array
     */
    protected function downloadProductImage($entityArray)
    {
        $imageUrl = $entityArray["url"];
        unset($entityArray["url"]);

        if (!file_exists($this->getProductImagesDir() . $entityArray["product_id"])) {
            mkdir($this->getProductImagesDir() . $entityArray["product_id"], 0777, true);
        }

        echo $imageUrl . "\n";

		//if (!file_exists($this->getProductImagesDir() . $entityArray["file"])) {
			$bytes = $this->helperManager->saveRemoteFileToDisk($imageUrl, $this->getProductImagesDir() . $entityArray["file"]);
			if (!$bytes) {
				return [];
			}
		//} else {
			//$bytes = filesize($this->getProductImagesDir() . $entityArray["file"]);
		//}

        $entityArray["size"] = FileHelper::formatSizeUnits($bytes);

        return $entityArray;
    }

    /**
     * @return \SimpleXMLElement
     * @throws \Exception
     */
    private function getGreenCellXml()
    {
        $targetPath = $this->getImportDir() . "greencell.xml";
        if (!file_exists($targetPath) || !$this->getDebug()) {
            $bytes = $this->helperManager->saveRemoteFileToDisk($this->apiUrl, $targetPath);
            if (empty($bytes)) {
                throw new \Exception("Failed to get XML");
            }
        }

        $xmlContents = simplexml_load_file($targetPath);
        if (empty($xmlContents)) {
            throw new \Exception("XML is not valid");
        }

        return $xmlContents;
    }

    /**
     * @param $description
     * @return string
     */
    private function getGreenCellDescription($description)
    {
        $description = str_replace('Description: ', '', (string)$description);
        $description = str_replace(';', '<br>', $description);

        return (string)$description;
    }

    /**
     * @param $categories
     * @return array
     */
    private function getGreenCellCategories($categories)
    {
        $ret = [];

        /**
         * Kategorije su odvojene znakom slash, ali postoje i kategorije koje i u imenu imaju slash okružen razmacima
         * Zato prvo zamijenimo slash koji ne označava podjelu sa html encodanom verzijom, zatim explodamo na grupe i na kraju ga decodeamo
         */

        $productGroups = explode("/", str_replace(" / ", "&#47;", (string)$categories));
        foreach ($productGroups as $level => $productGroup) {
            $productGroup = html_entity_decode($productGroup);
            $ret[$level] = new ProductGroupModel(
                $productGroup,
                isset($ret[$level - 1]) ? $ret[$level - 1]->getCode() . "_" . $productGroup : $productGroup,
                isset($ret[$level - 1]) ? $ret[$level - 1]->getCode() : NULL
            );
        }

        return $ret;
    }

    /**
     * @param $attributes
     * @return string|null
     */
    private function getGreenCellEan($attributes)
    {
        if (!empty($attributes)) {
            foreach ($attributes->a as $item) {
                if ($item["name"] == "EAN") {
                    return (string)$item;
                }
            }
        }

        return null;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importProducts()
    {
        $data = $this->getGreenCellXml();

        $productSelectColumns = [
            "id",
            "remote_id",
            "ean",
            "name",
            "description",
            "price_purchase",
            "description",
            "qty",
            "weight",
            "active"
        ];

        $existingProducts = $this->getEntitiesArray($productSelectColumns, "product_entity", ["remote_id"], "", "WHERE entity_state_id = 1 AND remote_source = '{$this->getRemoteSource()}' AND remote_id IS NOT NULL");
        $existingIntGreenCellCategories = $this->getEntitiesArray(["id", "code", "product_group_id"], "int_greencell_category_entity", ["code"]);
        $existingProductProductGroupLinks = $this->getEntitiesArray(["a1.product_group_id", "a2.remote_id"], "product_product_group_link_entity", ["remote_id", "product_group_id"], "JOIN product_entity a2 ON a1.product_id = a2.id", "WHERE a2.remote_id IS NOT NULL AND a2.remote_id != '' AND a2.remote_source = '{$this->getRemoteSource()}'");
        $existingSRoutes = $this->getEntitiesArray(["request_url", "store_id", "destination_type"], "s_route_entity", ["store_id", "request_url"]);
        $existingProductImages = $this->getEntitiesArray(["id", "file"], "product_images_entity", ["file"]);
        $existingCurrencies = $this->getEntitiesArray(["id", "code", "rate"], "currency_entity", ["code"]);

        $insertArray = [
            // product_entity
        ];
        $insertArray2 = [
            // s_route_entity
            // product_images_entity
            // product_product_group_link_entity
        ];
        $updateArray = [
            // product_entity
        ];
        $insertIntGreenCellCategoriesArray = [];

        $productIds = [];
        $productRemoteIds = [];

        foreach ($existingProducts as $existingProduct) {
            if ($existingProduct["active"]) {
                $productUpdate = new UpdateModel($existingProduct);
                $productUpdate->add("active", false, false)
                    ->add("date_synced", "NOW()", false);
                $updateArray["product_entity"][$productUpdate->getEntityId()] = $productUpdate->getArray();
                $productIds[] = $productUpdate->getEntityId();
            }
        }

        $progressBar = new ProgressBar($this->getConsoleOutput(), $data->count());

        foreach ($data as $d) {

            $progressBar->advance();

            $remoteId = (string)$d["id"];
            $name = (string)$d->name;
            $description = $this->getGreenCellDescription($d->desc);
            $priceBase = (string)$d["price"]; // vpc
            $weight = (string)$d["weight"];
            $qty = (string)$d["stock"];
            $ean = $this->getGreenCellEan($d->attrs);
            $productGroups = $this->getGreenCellCategories($d->cat);

            $currencyRate = $existingCurrencies[$this->currencyFrom]["rate"];
            $currencyId = $existingCurrencies[$this->currencyTo]["id"];

            $priceBase = bcmul($priceBase, $currencyRate, 2);

            $nameArray = [];
            $descriptionArray = [];
            $metaKeywordsArray = [];
            $showOnStoreArray = [];
            $urlArray = [];

            foreach ($this->getStores() as $storeId) {

                $nameArray[$storeId] = $name;
                $descriptionArray[$storeId] = $description;
                $metaKeywordsArray[$storeId] = "";
                $showOnStoreArray[$storeId] = 1;

                if (!isset($existingProducts[$remoteId])) {

                    $i = 1;
                    $url = $key = $this->routeManager->prepareUrl($name);
                    while (isset($existingSRoutes[$storeId . "_" . $url]) || isset($insertArray2["s_route_entity"][$storeId . "_" . $url])) {
                        $url = $key . "-" . $i++;
                    }
                    $urlArray[$storeId] = $url;

                    $insertArray2["s_route_entity"][$storeId . "_" . $url] =
                        $this->getSRouteInsertEntity($url, "product", $storeId, $remoteId); // remote_id
                }
            }

            $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);
            $descriptionJson = json_encode($descriptionArray, JSON_UNESCAPED_UNICODE);
            $metaKeywordsJson = json_encode($metaKeywordsArray, JSON_UNESCAPED_UNICODE);
            $showOnStoreJson = json_encode($showOnStoreArray, JSON_UNESCAPED_UNICODE);
            $urlJson = json_encode($urlArray, JSON_UNESCAPED_UNICODE);

            if (!isset($existingProducts[$remoteId])) {

                $productInsert = new InsertModel($this->asProduct,
                    $this->productInsertAttributes,
                    $this->productCustomAttributes);

                $productInsert->add("date_synced", "NOW()")
                    ->add("remote_id", $remoteId)
                    ->add("remote_source", $this->getRemoteSource())
                    ->add("name", $nameJson)
                    ->add("ean", $ean)
                    ->add("meta_title", $nameJson)
                    ->add("meta_description", $nameJson)
                    ->add("description", $descriptionJson)
                    ->add("meta_keywords", $metaKeywordsJson)
                    ->add("show_on_store", $showOnStoreJson)
                    ->add("price_purchase", $priceBase)
                    ->add("active", true)
                    ->add("url", $urlJson)
                    ->add("supplier_id", $this->supplierId)
                    ->add("qty", $qty)
                    ->add("qty_step", 1)
                    ->add("tax_type_id", 3)
                    ->add("currency_id", $currencyId)
                    ->add("product_type_id", CrmConstants::PRODUCT_TYPE_SIMPLE)
                    ->add("ord", 100)
                    ->add("is_visible", true)
                    ->add("template_type_id", 5)
                    ->add("auto_generate_url", true)
                    ->add("keep_url", true)
                    ->add("show_on_homepage", false)
                    ->add("weight", $weight)
                    ->add("content_changed", true);

                $insertArray["product_entity"][$remoteId] = $productInsert->getArray();

                $productRemoteIds[] = $remoteId;

            } else {

                $productUpdate = new UpdateModel($existingProducts[$remoteId],
                    $this->productUpdateAttributes);

                unset($updateArray["product_entity"][$productUpdate->getEntityId()]);

                $k = array_search($productUpdate->getEntityId(), $productIds);
                if ($k !== false) {
                    unset($productIds[$k]);
                }

                if (isset($this->productUpdateAttributes["name"]) &&
                    $nameArray != json_decode($existingProducts[$remoteId]["name"], true)) {
                    $productUpdate->add("name", $nameJson, false)
                        ->add("meta_title", $nameJson, false)
                        ->add("meta_description", $nameJson, false);
                }
                if (isset($this->productUpdateAttributes["description"]) &&
                    $descriptionArray != json_decode($existingProducts[$remoteId]["description"], true)) {
                    $productUpdate->add("description", $descriptionJson, false);
                }

                $productUpdate->add("ean", $ean)
                    ->add("active", 1)
                    ->addFloat("weight", $weight)
                    ->addFloat("qty", $qty)
                    ->addFloat("price_purchase", $priceBase);

                if (!empty($productUpdate->getArray())) {
                    $productUpdate->add("date_synced", "NOW()", false);
                    if (!empty(array_intersect(array_keys($productUpdate->getArray()), $this->triggerContentChangesArray))) {
                        $productUpdate->add("content_changed", true, false);
                    }
                    $updateArray["product_entity"][$productUpdate->getEntityId()] = $productUpdate->getArray();
                    if (!empty(array_intersect(array_keys($productUpdate->getArray()), $this->triggerChangesArray))) {
                        $productIds[] = $productUpdate->getEntityId();
                    }
                }
            }

            $ord = 0;
            foreach ($d->imgs->children() as $key => $image) {
                $imageUrl = (string)$image["url"];
                if (!empty($imageUrl)) {
                    $urlParts = explode("/", $imageUrl);
                    if (isset($urlParts[count($urlParts) - 2])) {
                        /**
                         * Greencell koristi isto ime za sve slike, pa uzimamo parent directory iz url-a za prefix
                         */
                        $imageId = $urlParts[count($urlParts) - 2];
                        if (isset($existingProducts[$remoteId])) {
                            /**
                             * Ovdje provjeriti postoje li već slike u bazi
                             */
                            $filename = $imageId . "-" . $this->helperManager->getFilenameFromUrl($imageUrl);
                            $extension = $this->helperManager->getFileExtension($filename);
                            $filename = $this->helperManager->getFilenameWithoutExtension($filename);
                            $file = $existingProducts[$remoteId]["id"] . "/" . $filename . "." . $extension;

                            if (!isset($existingProductImages[$file])) {
                                $productImagesInsert = new InsertModel($this->asProductImages);
                                $productImagesInsert->add("url", $imageUrl)
                                    ->add("file", $file)
                                    ->add("filename", $filename)
                                    ->add("file_type", $extension)
                                    ->add("selected", ($key == "main"))
                                    ->add("ord", ++$ord)
                                    ->add("is_optimised", false)
                                    ->add("file_source", $this->getRemoteSource())
                                    ->add("product_id", $existingProducts[$remoteId]["id"])
                                    ->addFunction([$this, "downloadProductImage"]);
                                $insertArray2["product_images_entity"][$remoteId . "_" . $imageId] = $productImagesInsert;
                            }
                        } else {
                            $productImagesInsert = new InsertModel($this->asProductImages);
                            $productImagesInsert->add("url", $imageUrl)
                                ->add("image_id", $imageId)
                                ->add("selected", ($key == "main"))
                                ->add("ord", ++$ord)
                                ->add("is_optimised", false)
                                ->add("file_source", $this->getRemoteSource())
                                ->addLookup("product_id", $remoteId, "product_entity")
                                ->addFunction([$this, "getProductImageFilename"])
                                ->addFunction([$this, "downloadProductImage"]);
                            $insertArray2["product_images_entity"][$remoteId . "_" . $imageId] = $productImagesInsert;
                        }
                    }
                }
            }

            /** @var ProductGroupModel $productGroup */
            foreach ($productGroups as $level => $productGroup) {
                if (!isset($existingIntGreenCellCategories[$productGroup->getCode()])) {
                    if (!isset($insertIntGreenCellCategoriesArray[$level][$productGroup->getCode()])) {
                        $intGreenCellCategoryInsert = new InsertModel($this->asIntGreenCellCategory);
                        $intGreenCellCategoryInsert->add("name", $productGroup->getName())
                            ->add("code", $productGroup->getCode());
                        if (!empty($productGroup->getParent())) {
                            $intGreenCellCategoryInsert->addLookup("parent_category_id", $productGroup->getParent(), "int_greencell_category_entity");
                        } else {
                            $intGreenCellCategoryInsert->add("parent_category_id", null);
                        }
                        $insertIntGreenCellCategoriesArray[$level][$productGroup->getCode()] = $intGreenCellCategoryInsert;
                    }
                } else {
                    $productGroupId = $existingIntGreenCellCategories[$productGroup->getCode()]["product_group_id"];
                    if (!empty($productGroupId) && !isset($existingProductProductGroupLinks[$remoteId . "_" . $productGroupId])) {
                        $productProductGroupLinkInsert = new InsertModel($this->asProductProductGroupLink);
                        $productProductGroupLinkInsert->add("product_group_id", $productGroupId)
                            ->add("ord", 100);
                        if (!isset($existingProducts[$remoteId])) {
                            $productProductGroupLinkInsert->addLookup("product_id", $remoteId, "product_entity");
                        } else {
                            $productProductGroupLinkInsert->add("product_id", $existingProducts[$remoteId]["id"]);
                        }
                        $insertArray2["product_product_group_link_entity"][$remoteId . "_" . $productGroupId] = $productProductGroupLinkInsert;
                    }
                }
            }
        }

        $progressBar->finish();
        echo "\n";

        unset($existingProducts);
        unset($existingIntGreenCellCategories);
        unset($existingProductProductGroupLinks);
        unset($existingSRoutes);

        $reselectArray = [];

        if (!empty($insertIntGreenCellCategoriesArray)) {
            ksort($insertIntGreenCellCategoriesArray);
            foreach ($insertIntGreenCellCategoriesArray as $level => $intGreenCellCategories) {
                $intGreenCellCategories = $this->resolveImportArray(["int_greencell_category_entity" => $intGreenCellCategories], $reselectArray);
                $this->executeInsertQuery($intGreenCellCategories);
                $reselectArray["int_greencell_category_entity"] = $this->getEntitiesArray(["id", "code", "product_group_id"], "int_greencell_category_entity", ["code"]);
            }
            unset($insertIntGreenCellCategoriesArray);
        }

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $reselectArray["product_entity"] = $this->getEntitiesArray($productSelectColumns, "product_entity", ["remote_id"], "", "WHERE entity_state_id = 1 AND remote_source = '{$this->getRemoteSource()}' AND remote_id IS NOT NULL");
        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);

        $ret = [];
        if (!empty($productIds)) {
            $ret["product_ids"] = $this->resolveChangedProducts($productIds, $productRemoteIds, $reselectArray["product_entity"]);
            $ret["supplier_ids"] = [
                $this->supplierId
            ];
        }

        unset($reselectArray);

        echo "Importing products complete\n";

        return $ret;
    }
}
