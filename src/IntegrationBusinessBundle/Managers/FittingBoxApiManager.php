<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Helpers\FileHelper;
use AppBundle\Managers\RestManager;
use AppBundle\Models\InsertModel;
use AppBundle\Models\UpdateModel;
use Symfony\Component\Console\Helper\ProgressBar;

class FittingBoxApiManager extends DefaultIntegrationImportManager
{
    protected $apiUrl;
    protected $clientId;
    protected $clientSecret;

    /** @var AttributeSet $asSProductAttributesLink */
    protected $asSProductAttributesLink;
    /** @var AttributeSet $asProductImages */
    protected $asProductImages;

    public function initialize()
    {
        parent::initialize();

        $this->apiUrl = $_ENV["FITTING_BOX_API_URL"];
        $this->clientId = $_ENV["FITTING_BOX_CLIENT_ID"];
        $this->clientSecret = $_ENV["FITTING_BOX_CLIENT_SECRET"];

        $this->setRemoteSource("fitting_box");

        $this->asSProductAttributesLink = $this->entityManager->getAttributeSetByCode("s_product_attributes_link");
        $this->asProductImages = $this->entityManager->getAttributeSetByCode("product_images");
    }

    /**
     * @return mixed
     */
    private function getVtoSProductAttributeConfigurationId()
    {
        $q = "SELECT id FROM s_product_attribute_configuration_entity WHERE filter_key = 'vto';";

        $data = $this->databaseContext->getSingleEntity($q);

        return $data["id"];
    }

    /**
     * @return array
     */
    private function getVtoSProductAttributeConfigurationOptions()
    {
        $q = "SELECT 
                spaco.id, 
                spaco.configuration_value 
            FROM s_product_attribute_configuration_options_entity spaco
            JOIN s_product_attribute_configuration_entity spac ON spaco.configuration_attribute_id = spac.id
            WHERE spac.filter_key = 'vto';";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["configuration_value"]] = $d["id"];
        }

        return $ret;
    }

    /**
     * @return array
     */
    private function getVtoSProductAttributeLinks()
    {
        $q = "SELECT
                spal.id,
                spal.s_product_attribute_configuration_id,
                spal.configuration_option,
                spal.product_id,
                spal.attribute_value
            FROM s_product_attributes_link_entity spal
            JOIN s_product_attribute_configuration_entity spac ON spal.s_product_attribute_configuration_id = spac.id
            WHERE spac.filter_key = 'vto';";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["product_id"]][$d["configuration_option"]] = [
                "id" => $d["id"],
                "configuration_option" => $d["configuration_option"],
                "attribute_value" => $d["attribute_value"]
            ];
        }

        return $ret;
    }

    /**
     * @return array
     */
    private function getFittingBoxExistingProducts()
    {
        $q = "SELECT 
                p.id, 
                p.ean, 
                p.fitting_box_remote_id,
                pi.id IS NOT NULL AS has_image
            FROM product_entity p
            LEFT JOIN product_images_entity pi ON p.id = pi.product_id
            AND pi.entity_state_id = 1
            AND p.entity_state_id = 1
            AND p.ean IS NOT NULL 
            AND p.ean != '' 
            AND p.product_type_id = 1
            GROUP BY p.id;";

        $data = $this->databaseContext->getAll($q);

        $ret = [];
        foreach ($data as $d) {
            $ret[$d["ean"]] = [
                "id" => $d["id"],
                "fitting_box_remote_id" => $d["fitting_box_remote_id"],
                "has_image" => $d["has_image"]
            ];
        }

        return $ret;
    }

    /**
     * @param $endpoint
     * @param $body
     * @param $headers
     * @param null $filePath
     * @return mixed
     * @throws \Exception
     */
    private function getFittingBoxApiData($endpoint, $body, $headers, $filePath = null)
    {
        if ($filePath) {
            $filePath = $this->getImportDir() . $filePath;
            if ($this->getDebug() && file_exists($filePath)) {
                $data = file_get_contents($filePath);
            }
        }

        if (!isset($data)) {
            $restManager = new RestManager();
            $restManager->CURLOPT_CUSTOMREQUEST = "POST";
            $restManager->CURLOPT_POSTFIELDS = json_encode($body);
            $restManager->CURLOPT_HTTPHEADER = $headers;
            $data = $restManager->get($this->apiUrl . $endpoint, false);
            if ($this->getDebug() && !empty($data) && $filePath) {
                file_put_contents($filePath, $data);
            }
        }

        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        $json = $data;
        $data = json_decode($json, true);

        if ($endpoint == "oauth/token" && !isset($data["access_token"])) {
            throw new \Exception("Could not get access token/data: " . $json . " " . json_encode(func_get_args()));
        }

        if (isset($data["access_token"])) {
            $data = $data["access_token"];
        } else if (isset($data["data"])) {
            $data = $data["data"];
        } else {
            return array();
        }

        return $data;
    }

    /**
     * @param $url
     * @return mixed
     */
    private function getFittingBoxImageNameFromUrl($url)
    {
        $imageNamePath = parse_url($url, PHP_URL_PATH);
        $imgDataBase64 = str_replace("/", "", $imageNamePath);
        $imgDataJson = base64_decode($imgDataBase64);
        $imgData = json_decode($imgDataJson, true);

        return $imgData["key"];
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    private function getFittingBoxAccessToken()
    {
        return $this->getFittingBoxApiData("oauth/token", [
            "grant_type" => "client_credentials",
            "client_id" => $this->clientId,
            "client_secret" => $this->clientSecret
        ], ["Content-Type: application/json"]);
    }

    /**
     * @param $args
     * @return array
     * @throws \Exception
     */
    public function importProductImages($args)
    {
        echo "Importing fitting box product images...\n";

        $accessToken = $this->getFittingBoxAccessToken();

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $accessToken
        ];
        $body = [
            "barcodeList" => [],
            "angles" => $args["angles"]
        ];

        $vtoConfigurationId = $this->getVtoSProductAttributeConfigurationId();
        $vtoConfigurationOptions = $this->getVtoSProductAttributeConfigurationOptions();
        $vtoConfigurationLinks = $this->getVtoSProductAttributeLinks();
        $existingProducts = $this->getFittingBoxExistingProducts();

        $insertArray = [
            // s_product_attributes_link_entity
            // product_images_entity
        ];
        $updateArray = [
            // product_entity
        ];
        $deleteArray = [
            // s_product_attributes_link_entity
        ];

        $insertProductIds = [];
        $deleteProductIds = [];

        foreach ($vtoConfigurationLinks as $productId => $vtoConfigurationOptionsTemp) {
            foreach ($vtoConfigurationOptionsTemp as $vtoConfigurationLink) {
                $deleteArray["s_product_attributes_link_entity"][$vtoConfigurationLink["id"]] = [
                    "id" => $vtoConfigurationLink["id"]
                ];
            }
            $deleteProductIds[] = $productId;
        }

        $i = 0;

        $progressBar = new ProgressBar($this->getConsoleOutput(), count($existingProducts));

        foreach ($existingProducts as $ean => $existingProduct) {

            $body["barcodeList"][] = (string)$ean;

            if (++$i == count($existingProducts) || count($body["barcodeList"]) >= 100) {

                $data = $this->getFittingBoxApiData("api/MyFbGlassesMetaData/infoByReference", $body, $headers);
                if (!empty($data)) {

                    foreach ($data as $d) {

                        $progressBar->advance();

                        if (!isset($d["EAN"]) || empty($d["EAN"])) {
                            continue;
                        }

                        $remoteEan = $d["EAN"];
                        $productId = $existingProducts[$remoteEan]["id"];

                        if (isset($d["GLASSESID"]) && !empty($d["GLASSESID"])) {
                            $productUpdate = new UpdateModel($existingProducts[$remoteEan]);
                            $productUpdate->add("fitting_box_remote_id", $d["GLASSESID"]);
                            if (!empty($productUpdate->getArray())) {
                                $productUpdate->add("date_synced", "NOW()", false);
                                $updateArray["product_entity"][$productId] = $productUpdate->getArray();
                            }
                        }

                        /**
                         * Pravilo je da ne skidamo Fitting Box slike za one proizvode kojima su već (ručno) uploadane slike
                         */
                        if ($existingProducts[$remoteEan]["has_image"] == 0 && isset($d["URLS"]) && !empty($d["URLS"])) {
                            foreach ($d["URLS"] as $ord => $du) {
                                if (!empty($du["url"])) {

                                    $filename = $this->getFittingBoxImageNameFromUrl($du["url"]);
                                    $filename = $this->helperManager->getFilenameFromUrl($filename);
                                    $extension = $this->helperManager->getFileExtension($filename);
                                    $filename = $this->helperManager->getFilenameWithoutExtension($filename);
                                    $file = $productId . "/" . $filename . "." . $extension;

                                    //echo $file . "\n";

                                    if (!file_exists($this->getProductImagesDir() . $productId)) {
                                        mkdir($this->getProductImagesDir() . $productId, 0777, true);
                                    }

                                    $bytes = $this->helperManager->saveRemoteFileToDisk($du["url"], $this->getProductImagesDir() . $file);
                                    if ($bytes) {
                                        $productImageInsert = new InsertModel($this->asProductImages);
                                        $productImageInsert->add("file", $file)
                                            ->add("filename", $filename)
                                            ->add("file_type", $extension)
                                            ->add("size", FileHelper::formatSizeUnits($bytes))
                                            ->add("product_id", $productId)
                                            ->add("is_optimised", 0)
                                            ->add("selected", ($ord == 0))
                                            ->add("ord", $ord + 1)
                                            ->add("file_source", $this->getRemoteSource());
                                        $insertArray["product_images_entity"][] = $productImageInsert->getArray();
                                        $insertProductIds[] = $productId;
                                    }
                                }
                            }
                        }

                        $vtoValue = [
                            false => "No",
                            true => "Yes"
                        ][isset($d["VTO_FLAG"]) && !empty($d["VTO_FLAG"])];

                        $attributeValueKey = md5($productId . $vtoConfigurationId . $vtoConfigurationOptions[$vtoValue]);
                        if (isset($vtoConfigurationLinks[$productId])) {
                            /**
                             * Link postoji
                             */
                            if (!isset($vtoConfigurationLinks[$productId][$vtoConfigurationOptions[$vtoValue]])) {
                                /**
                                 * Vrijednost se promijenila
                                 */
                                if (!isset($insertArray["s_product_attributes_link_entity"][$attributeValueKey])) {
                                    $sProductAttributesLinkInsert = new InsertModel($this->asSProductAttributesLink);
                                    $sProductAttributesLinkInsert->add("product_id", $productId)
                                        ->add("s_product_attribute_configuration_id", $vtoConfigurationId)
                                        ->add("attribute_value", $vtoValue)
                                        ->add("configuration_option", $vtoConfigurationOptions[$vtoValue])
                                        ->add("attribute_value_key", $attributeValueKey);
                                    $insertArray["s_product_attributes_link_entity"][$attributeValueKey] = $sProductAttributesLinkInsert->getArray();
                                    $insertProductIds[] = $productId;
                                }
                            } else {
                                /**
                                 * Vrijednost je ista
                                 */
                                $attributeLink = $vtoConfigurationLinks[$productId][$vtoConfigurationOptions[$vtoValue]];
                                unset($deleteArray["s_product_attributes_link_entity"][$attributeLink["id"]]);
                                $k = array_search($productId, $deleteProductIds);
                                if ($k !== false) {
                                    unset($deleteProductIds[$k]);
                                }
                            }
                        } else {
                            if (!isset($insertArray["s_product_attributes_link_entity"][$attributeValueKey])) {
                                $sProductAttributesLinkInsert = new InsertModel($this->asSProductAttributesLink);
                                $sProductAttributesLinkInsert->add("product_id", $productId)
                                    ->add("s_product_attribute_configuration_id", $vtoConfigurationId)
                                    ->add("attribute_value", $vtoValue)
                                    ->add("configuration_option", $vtoConfigurationOptions[$vtoValue])
                                    ->add("attribute_value_key", $attributeValueKey);
                                $insertArray["s_product_attributes_link_entity"][$attributeValueKey] = $sProductAttributesLinkInsert->getArray();
                                $insertProductIds[] = $productId;
                            }
                        }
                    }
                }

                $body["barcodeList"] = [];
            }
        }

        $progressBar->finish();
        echo "\n";

        unset($vtoConfigurationOptions);
        unset($vtoConfigurationLinks);
        unset($existingProducts);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $this->executeDeleteQuery($deleteArray);
        unset($deleteArray);

        $ret = [];
        $productIds = array_unique(array_merge($insertProductIds, $deleteProductIds));
        if (!empty($productIds)) {
            $ret["product_ids"] = $productIds;
        }

        echo "Importing fitting box product images complete\n";

        return $ret;
    }
}