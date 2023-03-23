<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Helpers\FileHelper;
use AppBundle\Managers\CacheManager;
use AppBundle\Models\InsertModel;
use AppBundle\Models\UpdateModel;
use CrmBusinessBundle\Abstracts\AbstractImportManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Managers\AccountManager;
use IntegrationBusinessBundle\Models\ImportError;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Managers\SproductManager;

class WandImportManager extends AbstractImportManager
{
    /** @var SproductManager $sProductManager */
    protected $sProductManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;

    /** @var AttributeSet $asAccount */
    protected $asAccount;
    /** @var AttributeSet $asAccountGroup */
    protected $asAccountGroup;
    /** @var AttributeSet $asContact */
    protected $asContact;
    /** @var AttributeSet $asProduct */
    protected $asProduct;
    /** @var AttributeSet $asAddress */
    protected $asAddress;
    /** @var AttributeSet $asSRoute */
    protected $asSRoute;
    /** @var AttributeSet $asProductImages */
    protected $asProductImages;
    /** @var AttributeSet $asProductDocuments */
    protected $asProductDocuments;
    /** @var AttributeSet $asProductWarehouseLink */
    protected $asProductWarehouseLink;
    /** @var AttributeSet $asWarehouse */
    protected $asWarehouse;
    /** @var AttributeSet $asCity */
    protected $asCity;
    /** @var AttributeSet $asCountry */
    protected $asCountry;
    /** @var AttributeSet $asProductGroup */
    protected $asProductGroup;
    /** @var AttributeSet $asProductProductGroupLink */
    protected $asProductProductGroupLink;
    /** @var AttributeSet $asPaymentType */
    protected $asPaymentType;
    /** @var AttributeSet $asSProductAttributeConfiguration */
    protected $asSProductAttributeConfiguration;
    /** @var AttributeSet $asSProductAttributeConfigurationOptions */
    protected $asSProductAttributeConfigurationOptions;
    /** @var AttributeSet $asSProductAttributesLink */
    protected $asSProductAttributesLink;
    /** @var AttributeSet $asWandDocument */
    protected $asWandDocument;
    /** @var AttributeSet $asWandDocumentItem */
    protected $asWandDocumentItem;
    /** @var AttributeSet $asDeliveryType */
    protected $asDeliveryType;
    /** @var AttributeSet $asProductConfigurationProductLink */
    protected $asProductConfigurationProductLink;
    /** @var AttributeSet $asProductConfigurableAttribute */
    protected $asProductConfigurableAttribute;
    /** @var AttributeSet $asLoyaltyCard */
    protected $asLoyaltyCard;
    /** @var AttributeSet $asAccountTypeLink */
    protected $asAccountTypeLink;

    protected $defaultStoreId;

    /** @var string $apiUrl */
    protected $apiUrl;
    /** @var string $imagesFolder */
    protected $imagesFolder;
    /** @var string $imagesFolderBig */
    protected $imagesFolderBig;
    /** @var string $ftpHostname */
    protected $ftpHostname;
    /** @var string $ftpUsername */
    protected $ftpUsername;
    /** @var string $ftpPassword */
    protected $ftpPassword;

    protected $getSastavnice;
    protected $getProductImages;
    protected $getProductGroups;
    protected $useProductImagesSmall;
    protected $useProductImagesRobaDocs;
    protected $getProductSAttributes;
    protected $getProductClassification;

    protected $naturalPersons;
    protected $skipNaturalPersons;
    protected $importAllAddresses;
    protected $inactiveAccounts;
    protected $inactiveContacts;

    protected $insertProductAttributes;
    protected $updateProductAttributes;
    protected $customProductAttributes;

    protected $saveAccountPricesArray;
    protected $saveAccountGroupPricesArray;

    protected $taxTypesById;
    protected $excludeDiscountTypes;

    protected $productColumns;

    public function initialize()
    {
        parent::initialize();

        $this->sProductManager = $this->getContainer()->get("s_product_manager");
        $this->getPageUrlExtension = $this->getContainer()->get("get_page_url_extension");
        $this->cacheManager = $this->getContainer()->get("cache_manager");
        $this->asAccount = $this->entityManager->getAttributeSetByCode("account");
        $this->asAccountGroup = $this->entityManager->getAttributeSetByCode("account_group");
        $this->asContact = $this->entityManager->getAttributeSetByCode("contact");
        $this->asProduct = $this->entityManager->getAttributeSetByCode("product");
        $this->asAddress = $this->entityManager->getAttributeSetByCode("address");
        $this->asSRoute = $this->entityManager->getAttributeSetByCode("s_route");
        $this->asProductImages = $this->entityManager->getAttributeSetByCode("product_images");
        $this->asProductDocuments = $this->entityManager->getAttributeSetByCode("product_document");
        $this->asProductWarehouseLink = $this->entityManager->getAttributeSetByCode("product_warehouse_link");
        $this->asWarehouse = $this->entityManager->getAttributeSetByCode("warehouse");
        $this->asCity = $this->entityManager->getAttributeSetByCode("city");
        $this->asCountry = $this->entityManager->getAttributeSetByCode("country");
        $this->asProductGroup = $this->entityManager->getAttributeSetByCode("product_group");
        $this->asProductProductGroupLink = $this->entityManager->getAttributeSetByCode("product_product_group_link");
        $this->asPaymentType = $this->entityManager->getAttributeSetByCode("payment_type");
        $this->asSProductAttributeConfiguration = $this->entityManager->getAttributeSetByCode("s_product_attribute_configuration");
        $this->asSProductAttributeConfigurationOptions = $this->entityManager->getAttributeSetByCode("s_product_attribute_configuration_options");
        $this->asSProductAttributesLink = $this->entityManager->getAttributeSetByCode("s_product_attributes_link");
        $this->asWandDocument = $this->entityManager->getAttributeSetByCode("wand_document");
        $this->asWandDocumentItem = $this->entityManager->getAttributeSetByCode("wand_document_item");
        $this->asDeliveryType = $this->entityManager->getAttributeSetByCode("delivery_type");
        $this->asProductConfigurationProductLink = $this->entityManager->getAttributeSetByCode("product_configuration_product_link");
        $this->asProductConfigurableAttribute = $this->entityManager->getAttributeSetByCode("product_configurable_attribute");
        $this->asLoyaltyCard = $this->entityManager->getAttributeSetByCode("loyalty_card");
        $this->asAccountTypeLink = $this->entityManager->getAttributeSetByCode("account_type_link");

        $this->defaultStoreId = $_ENV["DEFAULT_STORE_ID"];

        $this->apiUrl = $_ENV["WAND_URL"];
        $this->imagesFolder = $_ENV["WAND_IMAGES_FOLDER"];
        $this->imagesFolderBig = $_ENV["WAND_IMAGES_FOLDER_BIG"];
        $this->ftpHostname = $_ENV["WAND_FTP_HOSTNAME"];
        $this->ftpUsername = $_ENV["WAND_FTP_USERNAME"];
        $this->ftpPassword = $_ENV["WAND_FTP_PASSWORD"];
        $this->getSastavnice = $_ENV["WAND_GET_SASTAVNICE"];
        $this->getProductImages = $_ENV["WAND_GET_PRODUCT_IMAGES"];
        $this->useProductImagesSmall = $_ENV["WAND_USE_PRODUCT_IMAGES_SMALL"];
        $this->useProductImagesRobaDocs = $_ENV["WAND_USE_PRODUCT_IMAGES_ROBA_DOCS"];
        $this->getProductGroups = $_ENV["WAND_GET_PRODUCT_GROUPS"];
        $this->getProductSAttributes = $_ENV["WAND_GET_PRODUCT_S_ATTRIBUTES"];
        $this->getProductClassification = $_ENV["WAND_GET_PRODUCT_CLASSIFICATION"];
        $this->naturalPersons = json_decode($_ENV["WAND_NATURAL_PERSONS"], true);
        $this->skipNaturalPersons = $_ENV["WAND_SKIP_NATURAL_PERSONS"];
        $this->importAllAddresses = $_ENV["WAND_IMPORT_ALL_ADDRESSES"];
        $this->inactiveAccounts = json_decode($_ENV["WAND_ACCOUNT_TURN_OFF"], true);
        $this->inactiveContacts = json_decode($_ENV["WAND_CONTACT_TURN_OFF"], true);

        if ($this->skipNaturalPersons < self::SKIP_NATURAL_PERSONS_NONE ||
            $this->skipNaturalPersons > self::SKIP_NATURAL_PERSONS_ALL_EXCEPT_SELECTED) {
            throw new \Exception("WAND_SKIP_NATURAL_PERSONS value is not in valid range");
        }

        $insertProductAttributes = json_decode($_ENV["WAND_INSERT_PRODUCT_ATTRIBUTES"], true);
        if (empty($insertProductAttributes)) {
            throw new \Exception("WAND_INSERT_PRODUCT_ATTRIBUTES is empty");
        }
        $updateProductAttributes = json_decode($_ENV["WAND_UPDATE_PRODUCT_ATTRIBUTES"], true);
        if (empty($updateProductAttributes)) {
            throw new \Exception("WAND_UPDATE_PRODUCT_ATTRIBUTES is empty");
        }

        $this->insertProductAttributes = array_flip($insertProductAttributes);
        $this->updateProductAttributes = array_flip($updateProductAttributes);
        $this->customProductAttributes = json_decode($_ENV["WAND_CUSTOM_PRODUCT_ATTRIBUTES"], true);

        $this->setRemoteSource("wand");
    }

    /**
     * @param $productInsertArray
     * @param $attribute
     * @param $value
     * @return mixed
     */
    public function addToProduct($productInsertArray, $attribute, $value)
    {
        if (isset($this->insertProductAttributes[$attribute])) {
            $productInsertArray[$attribute] = $value;
        }

        return $productInsertArray;
    }

    function account_entity_filter($accountEntity, $reselectArray)
    {
        if (isset($accountEntity["filter_insert"])) {
            if (isset($accountEntity["filter_insert"]["account_group_name"])) {
                $value = NULL;
                if (isset($reselectArray["account_group_entity"][$accountEntity["filter_insert"]["account_group_name"]])) {
                    $value = $reselectArray["account_group_entity"][$accountEntity["filter_insert"]["account_group_name"]]["id"];
                }
                $accountEntity["account_group_id"] = $value;
            }
            unset($accountEntity["filter_insert"]);
        }

        return $accountEntity;
    }

    function contact_entity_filter($contactEntity, $reselectArray)
    {
        if (isset($contactEntity["filter_insert"])) {
            if (isset($contactEntity["filter_insert"]["account_remote_id"])) {
                $contactEntity["account_id"] =
                    $reselectArray["account_entity"][$contactEntity["filter_insert"]["account_remote_id"]]["id"];
            }
            unset($contactEntity["filter_insert"]);
        }

        return $contactEntity;
    }

    function address_entity_filter($addressEntity, $reselectArray)
    {
        if (isset($addressEntity["filter_insert"])) {
            if (isset($addressEntity["filter_insert"]["account_remote_id"])) {
                $addressEntity["account_id"] =
                    $reselectArray["account_entity"][$addressEntity["filter_insert"]["account_remote_id"]]["id"];
            }
            if (isset($addressEntity["filter_insert"]["contact_remote_id"])) {
                $addressEntity["contact_id"] =
                    $reselectArray["contact_entity"][$addressEntity["filter_insert"]["contact_remote_id"]]["id"];
            }
            if (isset($addressEntity["filter_insert"]["city_postal_code"])) {
                $addressEntity["city_id"] =
                    $reselectArray["city_entity"][$addressEntity["filter_insert"]["city_postal_code"]]["id"];
            }
            unset($addressEntity["filter_insert"]);
        }

        return $addressEntity;
    }

    function s_route_entity_filter($sRouteEntity, $reselectArray)
    {
        if (isset($sRouteEntity["filter_insert"])) {
            if (isset($sRouteEntity["filter_insert"]["product_remote_id"])) {
                $sRouteEntity["destination_id"] =
                    $reselectArray["product_entity"][$sRouteEntity["filter_insert"]["product_remote_id"]]["id"];
            }
            if (isset($sRouteEntity["filter_insert"]["product_group_remote_id"])) {
                $sRouteEntity["destination_id"] =
                    $reselectArray["product_group_entity"][$sRouteEntity["filter_insert"]["product_group_remote_id"]]["id"];
            }
            unset($sRouteEntity["filter_insert"]);
            if (isset($sRouteEntity["filter_insert"]["product_name"])) {
                $sRouteEntity["product_name"] =
                    $reselectArray[$sRouteEntity["filter_insert"]["product_name"]]["id"];
            }
            unset($sRouteEntity["filter_insert"]);
        }

        return $sRouteEntity;
    }

    function product_images_entity_filter($productImagesEntity, $reselectArray, $params)
    {
        $productImagesEntity["product_id"] =
            $reselectArray["product_entity"][$productImagesEntity["filter_insert"]["product_remote_id"]]["id"];

        $productId = $productImagesEntity["product_id"];
        $filename = $productImagesEntity["filename"];
        $extension = $productImagesEntity["file_type"];

        $sourceFile = $productImagesEntity["filter_insert"]["source_file"];

        if (ftp_size($params["connection"], $this->imagesFolder . $sourceFile) == -1) {
            return false;
        }

        /**
         * Unset filter values
         */
        unset($productImagesEntity["filter_insert"]);

        /**
         * Generate strings and save file
         */
        $productDir = $productId . "/";

        $targetPath = $this->getProductImagesDir() . $productDir;
        if (!file_exists($targetPath)) {
            mkdir($targetPath, 0777, true);
        }

        $targetFile = $targetPath . $filename . "." . $extension;

        if (!ftp_get($params["connection"], $targetFile, $this->imagesFolder . $sourceFile, FTP_BINARY)) {
            return false;
        }

        echo "Downloaded " . $this->getProductImagesDir() . $productDir . $filename . "." . $extension . "\n";

        $filesize = filesize($targetFile);

        /**
         * Generate table fields
         */
        $productImagesEntity["file"] = $productDir . $filename . "." . $extension;
        $productImagesEntity["size"] = FileHelper::formatSizeUnits($filesize);
        //$productImagesEntity["file_hash"] = hash_file("md5", $targetFile);
        $productImagesEntity["filename_hash"] = md5($filename);
        $productImagesEntity["file_source"] = $this->getRemoteSource();

        $productImagesEntity["is_optimised"] = false;
        $productImagesEntity["selected"] = false;
        if ($productImagesEntity["ord"] == 1) {
            $productImagesEntity["selected"] = true;
        }

        return $productImagesEntity;
    }

    function product_document_entity_filter($productDocumentEntity, $reselectArray, $params)
    {
        $productDocumentEntity["product_id"] = $reselectArray["product_entity"][$productDocumentEntity["filter_insert"]["product_remote_id"]]["id"];

        $productId = $productDocumentEntity["product_id"];
        $filename = $productDocumentEntity["filename"];
        $extension = $productDocumentEntity["file_type"];

        $sourceFile = $productDocumentEntity["filter_insert"]["source_file"];

        if (ftp_size($params["connection"], $this->imagesFolderBig . $sourceFile) == -1) {
            return false;
        }

        /**
         * Unset filter values
         */
        unset($productDocumentEntity["filter_insert"]);

        /**
         * Generate strings and save file
         */
        $productDir = $productId . "/";

        $targetPath = $this->getProductDocumentsDir() . $productDir;
        if (!file_exists($targetPath)) {
            mkdir($targetPath, 0777, true);
        }

        $targetFile = $targetPath . $filename . "." . $extension;

        if (!ftp_get($params["connection"], $targetFile, $this->imagesFolderBig . $sourceFile, FTP_BINARY)) {
            return false;
        }

        echo "Downloaded " . $this->getProductDocumentsDir() . $productDir . $filename . "." . $extension . "\n";

        $filesize = filesize($targetFile);

        /**
         * Generate table fields
         */
        $productDocumentEntity["file"] = $productDir . $filename . "." . $extension;
        $productDocumentEntity["size"] = FileHelper::formatSizeUnits($filesize);
        //$productDocumentEntity["file_hash"] = hash_file("md5", $targetFile);
        $productDocumentEntity["filename_hash"] = md5($filename);
        $productDocumentEntity["file_source"] = $this->getRemoteSource();

        return $productDocumentEntity;
    }

    function city_entity_filter($cityEntity, $reselectArray)
    {
        if (isset($cityEntity["filter_insert"])) {
            if (isset($cityEntity["filter_insert"]["country_name"])) {
                $cityEntity["country_id"] =
                    $reselectArray["country_entity"][$cityEntity["filter_insert"]["country_name"]]["id"];
            }
            unset($cityEntity["filter_insert"]);
        }

        return $cityEntity;
    }

    function product_group_entity_filter($productGroupEntity, $reselectArray)
    {
        if (isset($productGroupEntity["filter_insert"])) {
            if (isset($productGroupEntity["filter_insert"]["product_group_remote_id"])) {
                $productGroupEntity["product_group_id"] =
                    $reselectArray["product_group_entity"][$productGroupEntity["filter_insert"]["product_group_remote_id"]]["id"];
            }
            unset($productGroupEntity["filter_insert"]);
        }

        return $productGroupEntity;
    }

    function product_product_group_link_entity_filter($productProductGroupLinkEntity, $reselectArray)
    {
        if (isset($productProductGroupLinkEntity["filter_insert"])) {
            if (isset($productProductGroupLinkEntity["filter_insert"]["product_remote_id"])) {
                $productProductGroupLinkEntity["product_id"] =
                    $reselectArray["product_entity"][$productProductGroupLinkEntity["filter_insert"]["product_remote_id"]]["id"];
            }
            if (isset($productProductGroupLinkEntity["filter_insert"]["product_group_code"])) {
                if (!isset($reselectArray["product_group_entity"][$productProductGroupLinkEntity["filter_insert"]["product_group_code"]])) {
                    return null;
                }
                $productProductGroupLinkEntity["product_group_id"] =
                    $reselectArray["product_group_entity"][$productProductGroupLinkEntity["filter_insert"]["product_group_code"]]["id"];
            } else if (isset($productProductGroupLinkEntity["filter_insert"]["product_group_remote_id"])) {
                $productProductGroupLinkEntity["product_group_id"] =
                    $reselectArray["product_group_entity"][$productProductGroupLinkEntity["filter_insert"]["product_group_remote_id"]]["id"];
            }
            unset($productProductGroupLinkEntity["filter_insert"]);
        }

        return $productProductGroupLinkEntity;
    }

    function s_product_attribute_configuration_options_entity_filter($sProductAttributeConfigurationOptionsEntity, $reselectArray)
    {
        if (isset($sProductAttributeConfigurationOptionsEntity["filter_insert"])) {
            if (isset($sProductAttributeConfigurationOptionsEntity["filter_insert"]["configuration_attribute_remote_id"])) {
                $sProductAttributeConfigurationOptionsEntity["configuration_attribute_id"] =
                    $reselectArray["s_product_attribute_configuration_entity"][$sProductAttributeConfigurationOptionsEntity["filter_insert"]["configuration_attribute_remote_id"]]["id"];
            }
            unset($sProductAttributeConfigurationOptionsEntity["filter_insert"]);
        }

        return $sProductAttributeConfigurationOptionsEntity;
    }

    function s_product_attributes_link_entity_filter($sProductAttributesLinkEntity, $reselectArray)
    {
        if (isset($sProductAttributesLinkEntity["filter_insert"])) {
            if (isset($sProductAttributesLinkEntity["filter_insert"]["product_remote_id"])) {
                $sProductAttributesLinkEntity["product_id"] =
                    $reselectArray["product_entity"][$sProductAttributesLinkEntity["filter_insert"]["product_remote_id"]]["id"];
            }
            if (isset($sProductAttributesLinkEntity["filter_insert"]["configuration_remote_id"])) {
                $sProductAttributesLinkEntity["s_product_attribute_configuration_id"] =
                    $reselectArray["s_product_attribute_configuration_entity"][$sProductAttributesLinkEntity["filter_insert"]["configuration_remote_id"]]["id"];
            }
            if (isset($sProductAttributesLinkEntity["filter_insert"]["configuration_option_remote_id"])) {
                $sProductAttributesLinkEntity["configuration_option"] =
                    $reselectArray["s_product_attribute_configuration_options_entity"][$sProductAttributesLinkEntity["filter_insert"]["configuration_option_remote_id"]]["id"];
            }

            unset($sProductAttributesLinkEntity["filter_insert"]);
        }

        $sProductAttributesLinkEntity["attribute_value_key"] = md5($sProductAttributesLinkEntity["product_id"] .
            $sProductAttributesLinkEntity["s_product_attribute_configuration_id"] .
            $sProductAttributesLinkEntity["configuration_option"]);

        return $sProductAttributesLinkEntity;
    }

    function wand_document_entity_filter($wandDocumentEntity, $reselectArray)
    {
        if (isset($wandDocumentEntity["filter_insert"])) {
            if (isset($wandDocumentEntity["filter_insert"]["wand_document_remote_id"])) {
                /**
                 * Dodatan check jer nekada parent nije ni prisutan u wand arrayu
                 */
                if (isset($reselectArray["wand_document_entity"][$wandDocumentEntity["filter_insert"]["wand_document_remote_id"]])) {
                    $wandDocumentEntity["wand_document_id"] =
                        $reselectArray["wand_document_entity"][$wandDocumentEntity["filter_insert"]["wand_document_remote_id"]]["id"];
                } else {
                    $wandDocumentEntity["wand_document_id"] = NULL;
                }
            }
            unset($wandDocumentEntity["filter_insert"]);
        }

        return $wandDocumentEntity;
    }

    function wand_document_item_entity_filter($wandDocumentItemEntity, $reselectArray)
    {
        if (isset($wandDocumentItemEntity["filter_insert"])) {
            /**
             * Dodatan check jer nekada parent nije ni prisutan u wand arrayu
             */
            if (isset($wandDocumentItemEntity["filter_insert"]["wand_document_item_remote_id"]) &&
                isset($reselectArray["wand_document_item_entity"][$wandDocumentItemEntity["filter_insert"]["wand_document_item_remote_id"]])) {
                $wandDocumentItemEntity["wand_document_item_id"] =
                    $reselectArray["wand_document_item_entity"][$wandDocumentItemEntity["filter_insert"]["wand_document_item_remote_id"]]["id"];
            } else {
                $wandDocumentItemEntity["wand_document_item_id"] = NULL;
            }
            if (isset($wandDocumentItemEntity["filter_insert"]["wand_document_remote_id"]) &&
                isset($reselectArray["wand_document_entity"][$wandDocumentItemEntity["filter_insert"]["wand_document_remote_id"]])) {
                $wandDocumentItemEntity["wand_document_id"] =
                    $reselectArray["wand_document_entity"][$wandDocumentItemEntity["filter_insert"]["wand_document_remote_id"]]["id"];
            } else {
                $wandDocumentItemEntity["wand_document_id"] = NULL;
            }
            unset($wandDocumentItemEntity["filter_insert"]);
        }

        return $wandDocumentItemEntity;
    }

    /**
     * @param int $startPage
     * @return array
     * @throws \Exception
     */
    public function getWandPartneri($startPage = 0)
    {
        $wandPartneri = [];

        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "partneri?pageNumber=" . $page . "&fromId=0");
            if (!empty($items)) {
                foreach ($items as $item) {
                    $wandPartneri[$item["partnerID"]] = $item;
                }
            }
            $page++;
        } while (count($items) > 0);

        return $wandPartneri;
    }

    /**
     * @param int $startPage
     * @return array
     * @throws \Exception
     */
    public function getWandOsobe($startPage = 0)
    {
        $wandOsobe = [];

        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "osobe?pageNumber=" . $page . "&fromId=0");
            if (!empty($items)) {
                foreach ($items as $item) {
                    $wandOsobe[$item["osobaID"]] = $item;
                }
            }
            $page++;
        } while (count($items) > 0);

        return $wandOsobe;
    }

    /**
     * @param int $startPage
     * @return array
     * @throws \Exception
     */
    public function getWandSastavniceBundle($startPage = 0)
    {
        $sastavniceBundleArray = array();

        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "sastavnice?pageNumber=" . $page . "&fromid=0");
            if (!empty($items)) {
                foreach ($items as $item) {
                    if (isset($sastavniceBundleArray[$item["proizvod"]])) {
                        $sastavniceBundleArray[$item["proizvod"]] = $sastavniceBundleArray[$item["proizvod"]] . "," . $item["robaID"];
                    } else {
                        $sastavniceBundleArray[$item["proizvod"]] = $item["robaID"];
                    }
                }
            }
            $page++;
        } while (count($items) > 0);

        return $sastavniceBundleArray;
    }

    /**
     * @param int $startPage
     * @return array
     * @throws \Exception
     */
    public function getWandProductGroups($startPage = 0)
    {
        $productGroupsArray = array();

        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "grupa?pageNumber=" . $page . "&fromid=0");
            if (!empty($items)) {
                foreach ($items as $item) {
                    $productGroupsArray[$item["grupaID"]] = $item;
                }
            }
            $page++;
        } while (count($items) > 0);

        return $productGroupsArray;
    }

    /**
     * @param int $startPage
     * @param int $fromId
     * @return array
     * @throws \Exception
     */
    public function getWandAtributVrste($startPage = 0, $fromId = 0)
    {
        $wandAtributVrste = array();

        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "atributvrste?pageNumber=" . $page . "&fromId=" . $fromId);
            if (!empty($items)) {
                foreach ($items as $item) {
                    $wandAtributVrste[$item["atributVrstaID"]] = $item;
                }
            }
            $page++;
        } while (count($items) > 0);

        return $wandAtributVrste;
    }

    /**
     * @param int $startPage
     * @param int $fromId
     * @return array
     * @throws \Exception
     */
    public function getWandAtributVrijednosti($startPage = 0, $fromId = 0)
    {
        $wandAtributVrijednosti = array();

        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "atributvrijednosti?pageNumber=" . $page . "&fromId=" . $fromId);
            if (!empty($items)) {
                foreach ($items as $item) {
                    if(isset($item["atributJezikID"]) && $item["atributJezikID"] != 1){
                        continue;
                    }
                    $wandAtributVrijednosti[$item["atributID"]] = $item;
                }
            }
            $page++;
        } while (count($items) > 0);

        return $wandAtributVrijednosti;
    }

    /**
     * @param int $startPage
     * @param int $fromId
     * @return array
     * @throws \Exception
     */
    public function getWandAtribut($startPage = 0, $fromId = 0)
    {
        $wandAtribut = array();

        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "atribut?pageNumber=" . $page . "&fromId=" . $fromId);
            if (!empty($items)) {
                foreach ($items as $item) {
                    $wandAtribut[$item["atributID"]] = $item;
                }
            }
            $page++;
        } while (count($items) > 0);

        return $wandAtribut;
    }

    /**
     * @param int $startPage
     * @param int $fromId
     * @return array
     * @throws \Exception
     */
    public function getWandDokStavke($startPage = 0, $fromId = 0)
    {
        $wandDokStavke = array();

        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "dokstavke?pageNumber=" . $page . "&fromId=" . $fromId);
            if (!empty($items)) {
                foreach ($items as $item) {
                    $wandDokStavke[$item["dokumentID"]][$item["stavkaID"]] = $item;
                }
            } else {
                $items = array();
            }
            $page++;
        } while (count($items) > 0);

        return $wandDokStavke;
    }

    /**
     * @param $wandDocuments
     * @param $remoteId
     * @param int $level
     * @return int|mixed
     */
    public function getWandDocumentLevel($wandDocuments, $remoteId, $level = 0)
    {
        if (isset($wandDocuments[$remoteId]["filter_insert"]["wand_document_remote_id"])) {
            return $this->getWandDocumentLevel(
                $wandDocuments,
                $wandDocuments[$remoteId]["filter_insert"]["wand_document_remote_id"],
                $level + 1);
        }

        return $level;
    }

    /**
     * @param $wandDocumentItems
     * @param $remoteId
     * @param int $level
     * @return int|mixed
     */
    public function getWandDocumentItemLevel($wandDocumentItems, $remoteId, $level = 0)
    {
        if (isset($wandDocumentItems[$remoteId]["filter_insert"]["wand_document_item_remote_id"])) {
            return $this->getWandDocumentItemLevel(
                $wandDocumentItems,
                $wandDocumentItems[$remoteId]["filter_insert"]["wand_document_item_remote_id"],
                $level + 1);
        }

        return $level;
    }

    /**
     * @param int $startPage
     * @param int $fromId
     * @return bool
     * @throws \Exception
     */
    public function importDokumenti($startPage = 0, $fromId = 0)
    {
        echo "Starting import dokumenti...\n";

        $wandDokStavke = $this->getWandDokStavke();
        if (empty($wandDokStavke)) {
            echo "Import dokumenti: dok stavke is empty!\n";
            //return false;
        }

        $existingWandDocuments = $this->getExistingWandDocuments();
        $existingWandDocumentItems = $this->getExistingWandDocumentItems();
        $existingWarehouses = $this->getExistingWarehouses("code");
        $existingOrders = $this->getExistingOrders();
        $existingAccounts = $this->getExistingAccounts("remote_id", array("id", "remote_id"));
        $existingContacts = $this->getExistingContacts("remote_id", array("id", "remote_id"));
        $existingProducts = $this->getExistingProducts("remote_id", array("id", "remote_id"));

        $insertArray = array("wand_document_entity" => array());
        $insertArray2 = array("wand_document_item_entity" => array());
        $updateArray = array();
        $reselectArray = array();

        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "dokument?pageNumber=" . $page . "&fromId=" . $fromId);
            if (!empty($items)) {
                foreach ($items as $item) {

                    $docRemoteId = $item["dokumentID"];
                    $date = explode("T", $item["datum"]); // Y-m-d

                    if (!isset($existingWandDocuments[$docRemoteId])) {
                        $wandDocumentInsertArray = $this->getEntityDefaults($this->asWandDocument);

                        if (!empty($item["dokumentRoditeljID"])) {
                            if (!isset($existingWandDocuments[$item["dokumentRoditeljID"]])) {
                                $wandDocumentInsertArray["filter_insert"]["wand_document_remote_id"] = $item["dokumentRoditeljID"];
                            } else {
                                $wandDocumentInsertArray["wand_document_id"] = $existingWandDocuments[$item["dokumentRoditeljID"]]["id"];
                            }
                        } else {
                            $wandDocumentInsertArray["wand_document_id"] = NULL;
                        }

                        $warehouseId = NULL;
                        if (isset($existingWarehouses[$item["skladiste"]])) {
                            $warehouseId = $existingWarehouses[$item["skladiste"]]["id"];
                        }
                        $orderId = NULL;
                        if (isset($existingOrders[$item["webNarudzbaID"]])) {
                            $orderId = $existingOrders[$item["webNarudzbaID"]]["id"];
                        }
                        $accountId = NULL;
                        if (isset($existingAccounts[$item["partnerID"]])) {
                            $accountId = $existingAccounts[$item["partnerID"]]["id"];
                        }
                        $contactId = NULL;
                        if (isset($existingContacts[$item["osobaID"]])) {
                            $contactId = $existingContacts[$item["osobaID"]]["id"];
                        }

                        $wandDocumentInsertArray["remote_id"] = $docRemoteId;
                        $wandDocumentInsertArray["type"] = $item["tip"];
                        $wandDocumentInsertArray["class"] = $item["klasa"];
                        $wandDocumentInsertArray["number"] = $item["broj"];
                        $wandDocumentInsertArray["date"] = $date[0];
                        $wandDocumentInsertArray["warehouse_id"] = $warehouseId;
                        $wandDocumentInsertArray["order_id"] = $orderId;
                        $wandDocumentInsertArray["account_id"] = $accountId;
                        $wandDocumentInsertArray["contact_id"] = $contactId;
                        $wandDocumentInsertArray["work_status"] = $item["radniStatus"];
                        $wandDocumentInsertArray["user_status"] = $item["korisnickiStatus"];

                        $insertArray["wand_document_entity"][$docRemoteId] = $wandDocumentInsertArray;
                    } else {

                        $wandDocumentUpdateArray = array();

                        if ($item["tip"] != $existingWandDocuments[$docRemoteId]["type"]) {
                            $wandDocumentUpdateArray["type"] = $item["tip"];
                        }
                        if ($item["klasa"] != $existingWandDocuments[$docRemoteId]["class"]) {
                            $wandDocumentUpdateArray["class"] = $item["klasa"];
                        }
                        if ($item["broj"] != $existingWandDocuments[$docRemoteId]["number"]) {
                            $wandDocumentUpdateArray["number"] = $item["broj"];
                        }
                        if ($date[0] != $existingWandDocuments[$docRemoteId]["date"]) {
                            $wandDocumentUpdateArray["date"] = $date[0];
                        }
                        if ($item["radniStatus"] != $existingWandDocuments[$docRemoteId]["work_status"]) {
                            $wandDocumentUpdateArray["work_status"] = $item["radniStatus"];
                        }
                        if ($item["korisnickiStatus"] != $existingWandDocuments[$docRemoteId]["user_status"]) {
                            $wandDocumentUpdateArray["user_status"] = $item["korisnickiStatus"];
                        }

                        if (!empty($wandDocumentUpdateArray)) {
                            $wandDocumentUpdateArray["modified"] = "NOW()";
                            $updateArray["wand_document_entity"][$existingWandDocuments[$docRemoteId]["id"]] = $wandDocumentUpdateArray;
                        }
                    }

                    if (isset($wandDokStavke[$docRemoteId])) {
                        foreach ($wandDokStavke[$docRemoteId] as $docItemRemoteId => $wandDokStavka) {

                            $date1 = explode("T", $wandDokStavka["datum1"]); // Y-m-d
                            $date2 = explode("T", $wandDokStavka["datum2"]); // Y-m-d

                            if (!isset($existingWandDocumentItems[$docItemRemoteId])) {
                                $wandDocumentItemInsertArray = $this->getEntityDefaults($this->asWandDocumentItem);

                                if (!empty($wandDokStavka["stavkaRoditeljID"])) {
                                    if (!isset($existingWandDocumentItems[$wandDokStavka["stavkaRoditeljID"]])) {
                                        $wandDocumentItemInsertArray["filter_insert"]["wand_document_item_remote_id"] = $wandDokStavka["stavkaRoditeljID"];
                                    } else {
                                        $wandDocumentItemInsertArray["wand_document_item_id"] = $existingWandDocumentItems[$wandDokStavka["stavkaRoditeljID"]]["id"];
                                    }
                                } else {
                                    $wandDocumentItemInsertArray["wand_document_item_id"] = NULL;
                                }

                                if (!isset($existingWandDocuments[$docRemoteId])) {
                                    $wandDocumentItemInsertArray["filter_insert"]["wand_document_remote_id"] = $docRemoteId;
                                } else {
                                    $wandDocumentItemInsertArray["wand_document_id"] = $existingWandDocuments[$docRemoteId]["id"];
                                }

                                $productId = NULL;
                                if (isset($existingProducts[$wandDokStavka["robaID"]])) {
                                    $productId = $existingProducts[$wandDokStavka["robaID"]]["id"];
                                }

                                $wandDocumentItemInsertArray["remote_id"] = $docItemRemoteId;
                                $wandDocumentItemInsertArray["product_id"] = $productId;
                                $wandDocumentItemInsertArray["qty"] = $wandDokStavka["kolicina"];
                                $wandDocumentItemInsertArray["price_base"] = $wandDokStavka["vpIznos"];
                                $wandDocumentItemInsertArray["price_retail"] = $wandDokStavka["mpIznos"];
                                $wandDocumentItemInsertArray["date_1"] = $date1[0];
                                $wandDocumentItemInsertArray["date_2"] = $date2[0];

                                for ($i = 1; $i <= 6; $i++) {
                                    $wandDocumentItemInsertArray["info_qty_" . $i] = $wandDokStavka["infoKolicina" . $i];
                                }

                                $insertArray2["wand_document_item_entity"][$docItemRemoteId] = $wandDocumentItemInsertArray;
                            } else {

                                $wandDocumentItemUpdateArray = array();

                                if ((string)floatval($existingWandDocumentItems[$docItemRemoteId]["qty"]) != (string)floatval($wandDokStavka["kolicina"])) {
                                    $wandDocumentItemUpdateArray["qty"] = $wandDokStavka["kolicina"];
                                }
                                if ((string)floatval($existingWandDocumentItems[$docItemRemoteId]["price_base"]) != (string)floatval($wandDokStavka["vpIznos"])) {
                                    $wandDocumentItemUpdateArray["price_base"] = $wandDokStavka["vpIznos"];
                                }
                                if ((string)floatval($existingWandDocumentItems[$docItemRemoteId]["price_retail"]) != (string)floatval($wandDokStavka["mpIznos"])) {
                                    $wandDocumentItemUpdateArray["price_retail"] = $wandDokStavka["mpIznos"];
                                }
                                if ($existingWandDocumentItems[$docItemRemoteId]["date_1"] != $date1[0]) {
                                    $wandDocumentItemUpdateArray["date_1"] = $date1[0];
                                }
                                if ($existingWandDocumentItems[$docItemRemoteId]["date_2"] != $date2[0]) {
                                    $wandDocumentItemUpdateArray["date_2"] = $date2[0];
                                }
                                for ($i = 1; $i <= 6; $i++) {
                                    if ((string)floatval($existingWandDocumentItems[$docItemRemoteId]["info_qty_" . $i]) != (string)floatval($wandDokStavka["infoKolicina" . $i])) {
                                        $wandDocumentItemUpdateArray["info_qty_" . $i] = $wandDokStavka["infoKolicina" . $i];
                                    }
                                }

                                if (!empty($wandDocumentItemUpdateArray)) {
                                    $wandDocumentItemUpdateArray["modified"] = "NOW()";
                                    $updateArray["wand_document_item_entity"][$existingWandDocumentItems[$docItemRemoteId]["id"]] = $wandDocumentItemUpdateArray;
                                }
                            }
                        }
                    }
                }
            }

            echo "Fetched dokument page: " . $page . "\n";
            $page++;
        } while (count($items) > 0);

        if (!empty($insertArray)) {

            $insertWandDocumentsArray = array();

            foreach ($insertArray["wand_document_entity"] as $remoteId => $wandDocument) {
                $level = $this->getWandDocumentLevel($insertArray["wand_document_entity"], $remoteId);
                $insertWandDocumentsArray[$level][$remoteId] = $wandDocument;
            }
            unset($insertArray);

            if (!empty($insertWandDocumentsArray)) {
                ksort($insertWandDocumentsArray);
                foreach ($insertWandDocumentsArray as $level => $wandDocuments) {
                    $wandDocuments = array("wand_document_entity" => $wandDocuments);
                    if ($level > 0) {
                        $wandDocuments = $this->filterImportArray($wandDocuments, $reselectArray);
                    }
                    $insertWandDocumentsQuery = $this->getInsertQuery($wandDocuments);
                    if (!empty($insertWandDocumentsQuery)) {
                        $this->logQueryString($insertWandDocumentsQuery);
                        $this->databaseContext->executeNonQuery($insertWandDocumentsQuery);
                    }
                    $reselectArray["wand_document_entity"] = $this->getExistingWandDocuments();
                }
                unset($insertWandDocumentsArray);
            }
        }

        if (!empty($insertArray2)) {

            $insertWandDocumentItemsArray = array();

            foreach ($insertArray2["wand_document_item_entity"] as $remoteId => $wandDocumentItem) {
                $level = $this->getWandDocumentItemLevel($insertArray2["wand_document_item_entity"], $remoteId);
                $insertWandDocumentItemsArray[$level][$remoteId] = $wandDocumentItem;
            }
            unset($insertArray2);

            if (!empty($insertWandDocumentItemsArray)) {
                ksort($insertWandDocumentItemsArray);
                foreach ($insertWandDocumentItemsArray as $level => $wandDocumentItems) {
                    $wandDocumentItems = array("wand_document_item_entity" => $wandDocumentItems);
                    $wandDocumentItems = $this->filterImportArray($wandDocumentItems, $reselectArray);

                    $insertWandDocumentItemsQuery = $this->getInsertQuery($wandDocumentItems);
                    if (!empty($insertWandDocumentItemsQuery)) {
                        $this->logQueryString($insertWandDocumentItemsQuery);
                        $this->databaseContext->executeNonQuery($insertWandDocumentItemsQuery);
                    }
                    $reselectArray["wand_document_item_entity"] = $this->getExistingWandDocumentItems();
                }
                unset($insertWandDocumentItemsArray);
            }
        }

        unset($reselectArray);

        /**
         * Update
         */
        $updateQuery = $this->getUpdateQuery($updateArray);
        if (!empty($updateQuery)) {
            $this->logQueryString($updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }
        unset($updateArray);

        echo "Import dokumenti complete\n";

        return true;
    }

    /**
     * @param int $startPage
     * @return bool
     * @throws \Exception
     */
    public function importPlacanja($startPage = 0)
    {
        echo "Starting import placanja...\n";

        /**
         * Get existing items
         */
        $existingPaymentTypes = $this->getExistingPaymentTypes();

        /**
         * Prepare import arrays
         */
        $insertArray = array("payment_type_entity" => array());
        $updateArray = array();

        /**
         * Begin import
         */
        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "placanja?pageNumber=" . $page);
            if (!empty($items)) {
                foreach ($items as $item) {

                    $name = trim($item["naziv"]);
                    $remoteCode = trim($item["sifra"]);
                    $remoteId = trim($item["nacinPlacanjaID"]);

                    $nameArray = array();
                    foreach ($this->getStores() as $storeId) {
                        $nameArray[$storeId] = $name;
                    }
                    $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);

                    if (!isset($existingPaymentTypes[$remoteCode])) {
                        $paymentTypeInsertArray = $this->getEntityDefaults($this->asPaymentType);
                        $paymentTypeInsertArray["remote_code"] = $remoteCode;
                        $paymentTypeInsertArray["remote_id"] = $remoteId;
                        $paymentTypeInsertArray["name"] = $nameJson;
                        $insertArray["payment_type_entity"][$remoteCode] = $paymentTypeInsertArray;
                    } else {
                        $paymentTypeUpdateArray = array();
                        if ($existingPaymentTypes[$remoteCode]["remote_id"] != $remoteId) {
                            $paymentTypeUpdateArray["remote_id"] = $remoteId;
                        }
                        if (!empty($paymentTypeUpdateArray)) {
                            $paymentTypeUpdateArray["modified"] = "NOW()";
                            $updateArray["payment_type_entity"][$existingPaymentTypes[$remoteCode]["id"]] = $paymentTypeUpdateArray;
                        }
                    }
                }
            }

            echo "Fetched placanja page: " . $page . "\n";
            $page++;
        } while (count($items) > 0);

        /**
         * Insert payment types
         */
        $insertQuery = $this->getInsertQuery($insertArray);
        if (!empty($insertQuery)) {
            $this->logQueryString($insertQuery);
            $this->databaseContext->executeNonQuery($insertQuery);
        }

        /**
         * Update payment types
         */
        $updateQuery = $this->getUpdateQuery($updateArray);
        if (!empty($updateQuery)) {
            $this->logQueryString($updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }

        unset($existingPaymentTypes);
        unset($insertArray);

        echo "Import placanja complete\n";

        return true;
    }

    /**
     * @param int $startPage
     * @return bool
     * @throws \Exception
     */
    public function importOtprema($startPage = 0)
    {
        echo "Starting import otprema...\n";

        /**
         * Get existing items
         */
        $existingDeliveryTypes = $this->getExistingDeliveryTypes();

        /**
         * Prepare import arrays
         */
        $insertArray = array("delivery_type_entity" => array());
        $updateArray = array();

        /**
         * Begin import
         */
        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "otprema?pageNumber=" . $page);
            if (!empty($items)) {
                foreach ($items as $item) {

                    $name = trim($item["naziv"]);
                    $remoteCode = trim($item["sifra"]);
                    $remoteId = trim($item["sifra"]);

                    $nameArray = array();
                    foreach ($this->getStores() as $storeId) {
                        $nameArray[$storeId] = $name;
                    }
                    $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);

                    if (!isset($existingDeliveryTypes[$remoteCode])) {
                        $deliveryTypeInsertArray = $this->getEntityDefaults($this->asDeliveryType);
                        $deliveryTypeInsertArray["remote_code"] = $remoteCode;
                        $deliveryTypeInsertArray["name"] = $nameJson;
                        $insertArray["delivery_type_entity"][$remoteCode] = $deliveryTypeInsertArray;
                    }
                }
            }

            echo "Fetched otprema page: " . $page . "\n";
            $page++;
        } while (count($items) > 0);

        /**
         * Insert delivery types
         */
        $insertQuery = $this->getInsertQuery($insertArray);
        if (!empty($insertQuery)) {
            $this->logQueryString($insertQuery);
            $this->databaseContext->executeNonQuery($insertQuery);
        }

        unset($existingDeliveryTypes);
        unset($insertArray);

        echo "Import otprema complete\n";

        return true;
    }

    /**
     * @param int $startPage
     * @param string $fromDate
     * @return bool
     * @throws \Exception
     */
    public function importRobe($startPage = 0, $fromDate = "1979-01-01")
    {
        echo "Starting import robe...\n";

        $this->productColumns = array(
            "id",
            "remote_id",
            "name",
            "price_retail",
            "discount_price_retail",
            "discount_diff",
            "price_base",
            "code",
            "ean",
            "catalog_code",
            "tax_type_id",
            "active",
            "short_description",
            "brand_id",
            "product_type_id",
            "remote_source",
            "url",
            "manufacturer_remote_id",
            "manufacturer_remote_name",
            "qty",
            "description",
            "wand_name_2",
            "weight",
            "qty_step",
            "dimensions"
        );

        if(!empty($this->updateProductAttributes)){
            foreach ($this->updateProductAttributes as $column => $key){
                if(!in_array($column,$this->productColumns)){
                    $this->productColumns[] = $column;
                }
            }
        }

        $deleteColumns = array(
            "id"
        );

        /**
         * Get existing items
         */
        $existingProducts = $this->getExistingProducts("remote_id", $this->productColumns);
        $existingProductsCode = $this->getExistingProducts("code", $this->productColumns);
        $existingSRoutes = $this->getExistingSRoutes();
        $existingProductGroups = array();
        $existingProductGroupsCode = array();
        $existingProductGroupLinks = array();
        $existingSAttributeConfigurations = array();
        $existingSAttributeConfigurationsFilterKey = array();
        $existingSAttributeConfigurationOptions = array();
        $existingSAttributeLinks = array();
        $existingProductImages = array();
        $existingProductDocuments = array();

        $changedIds = array("product_ids" => array(), "supplier_ids" => array());
        $changedRemoteIds = array();

        if ($this->getProductGroups == 1 /* && $this->getProductClassification == 2 */ /* ovo se podrazumijeva */) {
            $existingProductGroups = $this->getExistingProductGroups("remote_id");
        }
        if ($this->getProductClassification == 1) {
            $existingProductGroupsCode = $this->getExistingProductGroups("product_group_code");
        }
        if ($this->getProductClassification == 1 || $this->getProductClassification == 2) {
            $existingProductGroupLinks = $this->getExistingProductGroupLinks();
        }
        if ($this->getProductSAttributes == 1) {
            $existingSAttributeConfigurations = $this->getSProductAttributeConfigurations("remote_id");
            $existingSAttributeConfigurationsFilterKey = $this->getSProductAttributeConfigurations("filter_key");
            $existingSAttributeConfigurationOptions = $this->getSProductAttributeConfigurationOptions();
            $existingSAttributeLinks = $this->getSProductAttributeLinks("wand_attribute_value_id");
            $existingSAttributeLinksByKey = $this->getSProductAttributeLinks("attribute_value_key");
        }
        if ($this->getProductImages == 1) {
            $existingProductImages = $this->getExistingProductImages();
            $existingProductDocuments = $this->getExistingProductDocuments();
        }

        $taxTypes = $this->getTaxTypes();
        $productTypes = $this->getProductTypes();
        $currencyId = $_ENV["DEFAULT_CURRENCY"];

        /**
         * Prepare import arrays
         */
        $insertArray = array(
            "s_product_attribute_configuration_entity" => array()
        );
        $insertArray2 = array(
            "product_entity" => array(),
            "s_product_attribute_configuration_options_entity" => array()
        );
        $insertArray3 = array(
            "s_product_attributes_link_entity" => array(),
            "s_route_entity" => array()
        );
        $insertArray4 = array(
            "product_product_group_link_entity" => array()
        );
        $updateArray = array();
        $updateArray2 = array();
        $deleteArray = array();
        $reselectArray = array();

        if ($this->getProductClassification == 1 || $this->getProductClassification == 2) {
            $deleteArray["product_product_group_link_entity"] = $this->getDeleteConditions($existingProductGroupLinks, $deleteColumns);
        }

        /**
         * Temporary arrays
         */
        $insertImagesArray = array();
        $insertDocumentsArray = array();
        $insertGroupsArray = array();
        $wandSastavniceBundle = array();
        $wandProductGroups = array();
        $wandProducts = array();

        if ($this->getSastavnice == 1) {
            $wandSastavniceBundle = $this->getWandSastavniceBundle();
            if (empty($wandSastavniceBundle)) {
                echo "Import robe failed: sastavnice is empty!\n";
                return false;
            }
        }
        if ($this->getProductGroups == 1) {
            $wandProductGroups = $this->getWandProductGroups();
            if (empty($wandProductGroups)) {
                echo "Import robe failed: grupe is empty!\n";
                return false;
            }
        }

        if ($this->getProductSAttributes == 1 || $this->getProductClassification == 1) {
            $wandAtributVrste = $this->getWandAtributVrste();
            if (empty($wandAtributVrste)) {
                echo "Import robe failed: atribut vrste is empty!\n";
                return false;
            }
            $wandAtributVrijednosti = $this->getWandAtributVrijednosti();
            if (empty($wandAtributVrijednosti)) {
                echo "Import robe failed: atribut vrijednosti is empty!\n";
                return false;
            }
            $wandAtribut = $this->getWandAtribut();
            if (empty($wandAtribut)) {
                echo "Import robe failed: atribut is empty!\n";
                return false;
            }
        }

        $customFilterProductsManager = null;
        $customFilterProductsMethod = null;
        /**
         * 4.7.2022 dodana mogucnost da se kroz env postavi da se preskacu proizvodi ukoliko nemaju postavljeno neko polje tipa cjenik4
         */
        if(isset($_ENV["WAND_CUSTOM_FILTER_PRODUCTS"]) && !empty(json_decode($_ENV["WAND_CUSTOM_FILTER_PRODUCTS"],true))){
            $tmp = json_decode($_ENV["WAND_CUSTOM_FILTER_PRODUCTS"],true);
            if(isset($tmp["service"]) && !empty($tmp["service"]) && isset($tmp["method"]) && !empty($tmp["method"])){
                $customFilterProductsManager = $this->container->get("{$tmp["service"]}");
                $customFilterProductsMethod = $tmp["method"];
            }
        }

        /**
         * Begin import
         */
        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "robe?pageNumber=" . $page . "&fromDate=" . $fromDate);

            if (!empty($items)) {
                foreach ($items as $item) {

                    if(!empty($customFilterProductsManager)){
                        $item = $customFilterProductsManager->{$customFilterProductsMethod}($item);
                    }

                    $remoteId = trim($item["robaID"]);
                    if (empty($remoteId)) {
                        continue;
                    }

                    if(empty(trim($item["naziv"]))){
                        continue;
                    }

                    $code = trim($item["sifra"]);

                    /**
                     * Ako je proizvod pronađen pomoću code-a, a nema remote_id, updataj remote_id
                     */
                    if (!isset($existingProducts[$remoteId])) {
                        if (empty($code)) {
                            continue;
                        }
                        if (isset($existingProductsCode[$code])) {
                            $updateArray["product_entity"][$existingProductsCode[$code]["id"]]["remote_id"] = $remoteId;
                            $updateArray["product_entity"][$existingProductsCode[$code]["id"]]["modified"] = "NOW()";
//                            $updateArray["product_entity"][$existingProductsCode[$code]["id"]]["modified_by"] = "system";
                        }
                    }

                    $wandProducts[] = $item;
                }
            }

            echo "Fetched robe page: " . $page . "\n";
            $page++;
        } while (!empty($items));

        /**
         * Update first pass
         */
        $updateQuery = $this->getUpdateQuery($updateArray);
        if (!empty($updateQuery)) {
            $this->logQueryString($updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }

        unset($updateArray);
        unset($existingProductsCode);
        unset($existingProducts);

        if (empty($wandProducts)) {
            echo "Import robe failed: proizvodi is empty!\n";
            return false;
        }

        $existingProducts = $this->getExistingProducts("remote_id", $this->productColumns);

        /**
         * By default set all products to inactive then as each wand product is iterated, unset it
         * Products not found in wand will remain inactive
         */
        foreach ($existingProducts as $existingProduct) {
            if ($existingProduct["active"] != 0) {
                $updateArray2["product_entity"][$existingProduct["id"]] = array(
                    "active" => 0,
                    "date_synced" => "NOW()",
                    "modified" => "NOW()",
                    //                "modified_by" => "system"
                );
            }
        }

        foreach ($wandProducts as $item) {

            $remoteId = trim($item["robaID"]);
            $code = trim($item["sifra"]);
            if (empty($code)) {
                $code = NULL;
            }
            $ean = trim($item["barKod"]);
            if (empty($ean)) {
                $ean = NULL;
            }
            $grupaId = trim($item["grupaID"]);

            $wandGrupa = trim($item["a1"]);
            $brandName = trim($item["a4"]);

            $tip = CrmConstants::PRODUCT_TYPE_SIMPLE;
            if (trim($item["tip"]) == 1) {
                $tip = CrmConstants::PRODUCT_TYPE_BUNDLE_WAND;
            }
            $catalogCode = trim($item["katBroj"]);
            if (empty($catalogCode)) {
                $catalogCode = NULL;
            }
            $guarantee = trim($item["jamstvo"]);
            if (empty($guarantee)) {
                $guarantee = NULL;
            }
            $name = trim($item["naziv"]);

            $name2 = trim($item["naziv2"]);
            $manufacturerId = trim($item["proizvodjacID"]);
            if (empty($manufacturerId)) {
                $manufacturerId = NULL;
            }

            $manufacturerRemoteName = trim($item["proizvodjac"]);
            if (empty($manufacturerRemoteName)) {
                $manufacturerRemoteName = NULL;
            }

            $warehousePosition = trim($item["skladisnoMjesto"]);
            if (empty($warehousePosition)) {
                $warehousePosition = NULL;
            }

            $qtyStep = 1;
            if($_ENV["WAND_USE_QTY_STEP"]){
                if(!empty(trim($item["oblPoPak"])) && trim($item["oblPoPak"]) > 0){
                    $qtyStep = trim($item["oblPoPak"]);
                }
            }

            $pdv = trim($item["pdv"]);
            $priceReturn = floatval(trim($item["ambalaza"]));
            $vpc = floatval(trim($item["originalnaVPCijena"]));
            $mpc = floatval(trim($item["originalnaMPCijena"]));
            if ($priceReturn > 0) {
                $mpc = $mpc - $priceReturn;
            }
            $mpcDiscount = 0;
            $mpcOld = floatval(trim($item["mpCijena"]));
            if ($priceReturn > 0) {
                $mpcOld = $mpcOld - $priceReturn;
            }
            if (!empty($mpcOld) && $mpcOld > $mpc) {
                $mpcDiscount = $mpc;
                $mpc = $mpcOld;
            }

            $discountType = $discountDiff = 0;
            if (!empty($mpcDiscount)) {
                $discountType = 1;
                $discountDiff = $mpc - $mpcDiscount;
            }

            $active = 1;
            $isVisible = 1;

            $qty = floatval(trim($item["raspolozivo"]));

            if(isset($item["jmTezine"])) {
                if (trim($item["jmTezine"]) == "g") {
                    $weight = floatval(trim($item["tezinaPak"])) / 1000;
                } elseif (trim($item["jmTezine"]) == "t") {
                    $weight = floatval(trim($item["tezinaPak"])) * 1000;
                } else {
                    $weight = floatval(trim($item["tezinaPak"]));
                }
            } else {
                $weight = floatval(trim($item["tezinaPak"]));
            }

            $measure = trim($item["jm"]);
            $taxType = $taxTypes[intval($pdv)];
            $productType = $productTypes[$tip];

            $wandDescription = $this->getWandDescription(trim($item["opis"]));

            $www = $item["www"];
            $dimensions = $item["doza"];

            /**
             * Prepare json strings
             */
            $nameArray = array();
            $wandDescriptionArray = array();
            $showOnStoreArray = array();
            $urlArray = array();

            foreach ($this->getStores() as $storeId) {
                $nameArray[$storeId] = $name;
                $wandDescriptionArray[$storeId] = $wandDescription;
                $showOnStoreArray[$storeId] = 1;

                /**
                 * Insert product s route
                 */
                if (!isset($existingProducts[$remoteId])) {
                    $i = 1;
                    $url = $key = $this->routeManager->prepareUrl($name);
                    while (isset($existingSRoutes[$storeId . "_" . $url]) || isset($insertArray3["s_route_entity"][$storeId . "_" . $url])) {
                        $url = $key . "-" . $i++;
                    }

                    $insertArray3["s_route_entity"][$storeId . "_" . $url] = $this->getInsertSRoute(
                        $url,
                        "product",
                        $storeId,
                        array("product_remote_id" => $remoteId));

                    $urlArray[$storeId] = $url;
                }
            }

            $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);
            $wandDescriptionJson = json_encode($wandDescriptionArray, JSON_UNESCAPED_UNICODE);
            $showOnStoreJson = json_encode($showOnStoreArray, JSON_UNESCAPED_UNICODE);
            $urlJson = json_encode($urlArray, JSON_UNESCAPED_UNICODE);

            /**
             * Insert/update product
             */
            if (!isset($existingProducts[$remoteId])) {

                $changedRemoteIds[] = $remoteId;

                $productInsertArray = $this->getEntityDefaults($this->asProduct);

                $productInsertArray = $this->addToProduct($productInsertArray, "date_synced", "NOW()");
                $productInsertArray = $this->addToProduct($productInsertArray, "remote_id", $remoteId);
                $productInsertArray = $this->addToProduct($productInsertArray, "remote_source", $this->getRemoteSource());
                $productInsertArray = $this->addToProduct($productInsertArray, "price_base", $vpc);
                $productInsertArray = $this->addToProduct($productInsertArray, "price_retail", $mpc);
                $productInsertArray = $this->addToProduct($productInsertArray, "price_return", $priceReturn);
                $productInsertArray = $this->addToProduct($productInsertArray, "discount_price_retail", $mpcDiscount);
                $productInsertArray = $this->addToProduct($productInsertArray, "discount_diff", $discountDiff);
                $productInsertArray = $this->addToProduct($productInsertArray, "discount_type", $discountType);
                $productInsertArray = $this->addToProduct($productInsertArray, "tax_type_id", $taxType["id"]);
                $productInsertArray = $this->addToProduct($productInsertArray, "currency_id", $currencyId);
                $productInsertArray = $this->addToProduct($productInsertArray, "name", $nameJson);
                $productInsertArray = $this->addToProduct($productInsertArray, "meta_title", $nameJson);
                $productInsertArray = $this->addToProduct($productInsertArray, "meta_description", $nameJson);
                $productInsertArray = $this->addToProduct($productInsertArray, "code", $code);
                $productInsertArray = $this->addToProduct($productInsertArray, "manufacturer_remote_id", $manufacturerId);
                $productInsertArray = $this->addToProduct($productInsertArray, "manufacturer_remote_name", $manufacturerRemoteName);
                if (isset($this->insertProductAttributes["warehouse_position"])){
                    $productInsertArray = $this->addToProduct($productInsertArray, "warehouse_position", $warehousePosition);
                }
                $productInsertArray = $this->addToProduct($productInsertArray, "ean", $ean);
                $productInsertArray = $this->addToProduct($productInsertArray, "catalog_code", $catalogCode);
                $productInsertArray = $this->addToProduct($productInsertArray, "guarantee", $guarantee);
                $productInsertArray = $this->addToProduct($productInsertArray, "measure", $measure);
                $productInsertArray = $this->addToProduct($productInsertArray, "active", $active);
                $productInsertArray = $this->addToProduct($productInsertArray, "is_visible", $isVisible);
                $productInsertArray = $this->addToProduct($productInsertArray, "product_type_id", $productType["id"]);
                $productInsertArray = $this->addToProduct($productInsertArray, "qty", $qty);
                $productInsertArray = $this->addToProduct($productInsertArray, $_ENV["WAND_DESCRIPTION_ATTRIBUTE"], $wandDescriptionJson);
                $productInsertArray = $this->addToProduct($productInsertArray, "qty_step", $qtyStep);
                $productInsertArray = $this->addToProduct($productInsertArray, "ord", 100);
                $productInsertArray = $this->addToProduct($productInsertArray, "is_saleable", 1);
                $productInsertArray = $this->addToProduct($productInsertArray, "show_on_store", $showOnStoreJson);
                $productInsertArray = $this->addToProduct($productInsertArray, "url", $urlJson);
                $productInsertArray = $this->addToProduct($productInsertArray, "keep_url", 1);
                $productInsertArray = $this->addToProduct($productInsertArray, "auto_generate_url", 1);
                $productInsertArray = $this->addToProduct($productInsertArray, "template_type_id", 5);
                $productInsertArray = $this->addToProduct($productInsertArray, "content_changed", 1);
                $productInsertArray = $this->addToProduct($productInsertArray, "wand_name_2", $name2);
                $productInsertArray = $this->addToProduct($productInsertArray, "weight", $weight);
                if (isset($this->insertProductAttributes["www"])){
                    $productInsertArray = $this->addToProduct($productInsertArray, "www", $www);
                }
                if (isset($this->insertProductAttributes["dimensions"])){
                    $productInsertArray = $this->addToProduct($productInsertArray, "dimensions", $dimensions);
                }

                if (!empty($this->customProductAttributes)) {
                    foreach ($this->customProductAttributes as $customAttribute => $customAttributeValue) {
                        $productInsertArray[$customAttribute] = $customAttributeValue;
                    }
                }

                $insertArray2["product_entity"][$remoteId] = $productInsertArray;

            } else {
                unset($updateArray2["product_entity"][$existingProducts[$remoteId]["id"]]);

                $productUpdateArray = array();

                if (isset($this->updateProductAttributes["name"]) && !empty($nameJson) &&
                    $nameArray != json_decode($existingProducts[$remoteId]["name"], true)) {
                    $productUpdateArray["name"] = $nameJson;
                    $productUpdateArray["meta_title"] = $nameJson;
                    $productUpdateArray["meta_description"] = $nameJson;
                    $productUpdateArray["content_changed"] = 1;
                }
                if (isset($this->updateProductAttributes["price_retail"]) && !empty($mpc) &&
                    (string)floatval($existingProducts[$remoteId]["price_retail"]) != (string)floatval($mpc)) {
                    $productUpdateArray["price_retail"] = $mpc;
                }
                if (isset($this->updateProductAttributes["price_base"]) && !empty($vpc) &&
                    (string)floatval($existingProducts[$remoteId]["price_base"]) != (string)floatval($vpc)) {
                    $productUpdateArray["price_base"] = $vpc;
                }
                if (isset($this->updateProductAttributes["discount_price_retail"]) &&
                    (string)floatval($existingProducts[$remoteId]["discount_price_retail"]) != (string)floatval($mpcDiscount)) {
                    $productUpdateArray["discount_price_retail"] = $mpcDiscount;
                }
                if (isset($this->updateProductAttributes["discount_diff"]) &&
                    (string)floatval($existingProducts[$remoteId]["discount_diff"]) != (string)floatval($discountDiff)) {
                    $productUpdateArray["discount_diff"] = $discountDiff;
                }
                if (isset($this->updateProductAttributes["product_type_id"]) && $existingProducts[$remoteId]["product_type_id"] != $productType["id"]) {
                    $productUpdateArray["product_type_id"] = $productType["id"];
                }
                if (isset($this->updateProductAttributes["ean"]) && $existingProducts[$remoteId]["ean"] != $ean) {
                    $productUpdateArray["ean"] = $ean;
                }
                if (isset($this->updateProductAttributes["code"]) && $existingProducts[$remoteId]["code"] != $code) {
                    $productUpdateArray["code"] = $code;
                }
                if (isset($this->updateProductAttributes["qty_step"]) && !empty($qtyStep) &&
                    (string)floatval($existingProducts[$remoteId]["qty_step"]) != (string)floatval($qtyStep)) {
                    $productUpdateArray["qty_step"] = $qtyStep;
                }
                if (isset($this->updateProductAttributes["qty"]) &&
                    (string)floatval($existingProducts[$remoteId]["qty"]) != (string)floatval($qty)) {
                    $productUpdateArray["qty"] = $qty;
                }
                if (isset($this->updateProductAttributes["weight"]) && $existingProducts[$remoteId]["weight"] != $weight) {
                    $productUpdateArray["weight"] = $weight;
                }
                if (isset($this->updateProductAttributes["catalog_code"]) && $existingProducts[$remoteId]["catalog_code"] != $catalogCode) {
                    $productUpdateArray["catalog_code"] = $catalogCode;
                }
                if (isset($this->updateProductAttributes["tax_type_id"]) && $existingProducts[$remoteId]["tax_type_id"] != $taxType["id"]) {
                    $productUpdateArray["tax_type_id"] = $taxType["id"];
                }
                if (isset($this->updateProductAttributes["active"]) && $existingProducts[$remoteId]["active"] != $active) {
                    $productUpdateArray["active"] = $active;
                }
                if (isset($this->updateProductAttributes["manufacturer_remote_id"]) && $existingProducts[$remoteId]["manufacturer_remote_id"] != $manufacturerId) {
                    $productUpdateArray["manufacturer_remote_id"] = $manufacturerId;
                }
                if (isset($this->updateProductAttributes["manufacturer_remote_name"]) && $existingProducts[$remoteId]["manufacturer_remote_name"] != $manufacturerRemoteName) {
                    $productUpdateArray["manufacturer_remote_name"] = $manufacturerRemoteName;
                }
                if (isset($this->updateProductAttributes["warehouse_position"]) && $existingProducts[$remoteId]["warehouse_position"] != $warehousePosition) {
                    $productUpdateArray["warehouse_position"] = $warehousePosition;
                }
                if (isset($this->updateProductAttributes["wand_name_2"]) && $existingProducts[$remoteId]["wand_name_2"] != $name2) {
                    $productUpdateArray["wand_name_2"] = $name2;
                }
                if (isset($this->updateProductAttributes[$_ENV["WAND_DESCRIPTION_ATTRIBUTE"]]) && !empty($wandDescriptionJson) &&
                    $wandDescriptionArray != json_decode($existingProducts[$remoteId][$_ENV["WAND_DESCRIPTION_ATTRIBUTE"]], true)) {
                    $productUpdateArray[$_ENV["WAND_DESCRIPTION_ATTRIBUTE"]] = $wandDescriptionJson;
                    $productUpdateArray["content_changed"] = 1;
                }
                if (isset($this->updateProductAttributes["www"]) && $existingProducts[$remoteId]["www"] != $www) {
                    $productUpdateArray["www"] = $www;
                }
                if (isset($this->updateProductAttributes["measure"]) && $existingProducts[$remoteId]["measure"] != $measure) {
                    $productUpdateArray["measure"] = $measure;
                }
                if (isset($this->updateProductAttributes["price_return"]) && $existingProducts[$remoteId]["price_return"] != $priceReturn) {
                    $productUpdateArray["price_return"] = $priceReturn;
                }
                if (isset($this->updateProductAttributes["dimensions"]) && $existingProducts[$remoteId]["dimensions"] != $dimensions) {
                    $productUpdateArray["dimensions"] = $dimensions;
                }

                if (!empty($productUpdateArray)) {
                    $productUpdateArray["modified"] = "NOW()";
                    $productUpdateArray["date_synced"] = "NOW()";
//                    $productUpdateArray["modified_by"] = "system";
                    $updateArray2["product_entity"][$existingProducts[$remoteId]["id"]] = $productUpdateArray;
                    if(!empty(array_intersect(array_keys($productUpdateArray), $this->triggerChangesArray))){
                        $changedIds["product_ids"][] = $existingProducts[$remoteId]["id"];
                    }
                }
            }

            if ($this->getProductGroups == 1) {

                /**
                 * Insert product groups
                 */
                while (!empty($grupaId) && isset($wandProductGroups[$grupaId])) {

                    $wandProductGroup = $wandProductGroups[$grupaId];
                    $parentGroupId = trim($wandProductGroup["roditelj"]);

                    if (!isset($existingProductGroups[$grupaId])) {

                        $groupCode = null;
                        $groupName = trim($wandProductGroup["naziv"]);
                        if ($this->getProductClassification == 1) {
                            $groupNamePos = strpos($groupName, " ");
                            $groupCode = mb_substr($groupName, 0, $groupNamePos);
                            $groupName = mb_substr($groupName, $groupNamePos + 1);
                        }
                        $groupMetaDescription = trim($wandProductGroup["opis"]);
                        $groupLevel = trim($wandProductGroup["nivo"]);

                        $groupNameArray = array();
                        $groupMetaDescriptionArray = array();
                        $groupShowOnStoreArray = array();
                        $groupUrlArray = array();

                        foreach ($this->getStores() as $storeId) {
                            $groupNameArray[$storeId] = $groupName;
                            $groupMetaDescriptionArray[$storeId] = $groupMetaDescription;
                            $groupShowOnStoreArray[$storeId] = 1;

                            $i = 1;
                            $url = $key = $this->routeManager->prepareUrl($groupName);
                            while (isset($existingSRoutes[$storeId . "_" . $url]) && $existingSRoutes[$storeId . "_" . $url]["destination_type"] != "product_group") {
                                $url = $key . "-" . $i++;
                            }

                            if (!isset($insertArray3["s_route_entity"][$storeId . "_" . $url])) {
                                $insertArray3["s_route_entity"][$storeId . "_" . $url] = $this->getInsertSRoute(
                                    $url,
                                    "product_group",
                                    $storeId,
                                    array("product_group_remote_id" => $grupaId));
                            }

                            $groupUrlArray[$storeId] = $url;
                        }

                        $groupNameJson = json_encode($groupNameArray, JSON_UNESCAPED_UNICODE);
                        $groupMetaDescriptionJson = json_encode($groupMetaDescriptionArray, JSON_UNESCAPED_UNICODE);
                        $groupUrlJson = json_encode($groupUrlArray, JSON_UNESCAPED_UNICODE);
                        $groupShowOnStoreJson = json_encode($groupShowOnStoreArray, JSON_UNESCAPED_UNICODE);

                        $productGroupInsertArray = $this->getEntityDefaults($this->asProductGroup);

                        $productGroupInsertArray["remote_id"] = $grupaId;
                        $productGroupInsertArray["remote_source"] = $this->getRemoteSource();
                        $productGroupInsertArray["product_group_code"] = $groupCode;
                        $productGroupInsertArray["name"] = $groupNameJson;
                        $productGroupInsertArray["meta_title"] = $groupNameJson;
                        $productGroupInsertArray["meta_description"] = $groupMetaDescriptionJson;
                        $productGroupInsertArray["url"] = $groupUrlJson;
                        $productGroupInsertArray["template_type_id"] = 4;
                        //$productGroupInsertArray["level"] = $groupLevel;
                        //$productGroupInsertArray["products_in_group"] = trim($wandProductGroup["artikala"]);
                        $productGroupInsertArray["show_on_store"] = $groupShowOnStoreJson;
                        $productGroupInsertArray["is_active"] = 1;
                        $productGroupInsertArray["keep_url"] = 1;
                        $productGroupInsertArray["auto_generate_url"] = 1;

                        if (!empty($parentGroupId)) {
                            if (!isset($existingProductGroups[$parentGroupId])) {
                                $productGroupInsertArray["filter_insert"]["product_group_remote_id"] = $parentGroupId;
                            } else {
                                $productGroupInsertArray["product_group_id"] = $existingProductGroups[$parentGroupId]["id"];
                            }
                        } else {
                            $productGroupInsertArray["product_group_id"] = NULL;
                        }

                        $insertGroupsArray[$groupLevel][$grupaId] = $productGroupInsertArray;
                    }

                    if ($this->getProductClassification == 2) {

                        if (isset($existingProducts[$remoteId]) && isset($existingProductGroups[$grupaId])) {
                            $productGroupLinkKey = $existingProducts[$remoteId]["id"] . "_" . $existingProductGroups[$grupaId]["id"];
                            if (!isset($existingProductGroupLinks[$productGroupLinkKey])) {
                                $productGroupLinkInsertArray = $this->getEntityDefaults($this->asProductProductGroupLink);
                                $productGroupLinkInsertArray["product_id"] = $existingProducts[$remoteId]["id"];
                                $productGroupLinkInsertArray["product_group_id"] = $existingProductGroups[$grupaId]["id"];
                                $insertArray4["product_product_group_link_entity"][$remoteId . "_" . $grupaId] = $productGroupLinkInsertArray;
                                /**
                                 * Add to changed array
                                 */
                                if (isset($existingProducts[$remoteId])) {
                                    $changedIds["product_ids"][] = $existingProducts[$remoteId]["id"];
                                }
                            } else {
                                unset($deleteArray["product_product_group_link_entity"][$existingProductGroupLinks[$productGroupLinkKey]["id"]]);
                            }
                        } else {
                            $productGroupLinkInsertArray = $this->getEntityDefaults($this->asProductProductGroupLink);
                            if (isset($existingProducts[$remoteId])) {
                                $productGroupLinkInsertArray["product_id"] = $existingProducts[$remoteId]["id"];
                            } else {
                                $productGroupLinkInsertArray["filter_insert"]["product_remote_id"] = $remoteId;
                            }
                            if (isset($existingProductGroups[$grupaId])) {
                                $productGroupLinkInsertArray["product_group_id"] = $existingProductGroups[$grupaId]["id"];
                            } else {
                                $productGroupLinkInsertArray["filter_insert"]["product_group_remote_id"] = $grupaId;
                            }
                            $insertArray4["product_product_group_link_entity"][$remoteId . "_" . $grupaId] = $productGroupLinkInsertArray;
                            /**
                             * Add to changed array
                             */
                            if (isset($existingProducts[$remoteId])) {
                                $changedIds["product_ids"][] = $existingProducts[$remoteId]["id"];
                            }
                        }
                    }

                    // Follow parent group
                    $grupaId = $parentGroupId;
                }
            }

            /**
             * Insert product images
             */
            if ($this->getProductImages == 1) {
                $ord = 0;
                if (isset($existingProductImages[$remoteId])) {
                    $ord = count($existingProductImages[$remoteId]);
                }

                if($this->useProductImagesSmall){
                    /**
                     * Wand slike
                     */
                    for ($i = 1; $i <= 6; $i++) {
                        $key = "slika";
                        if ($i > 1) {
                            $key .= strval($i);
                        }
                        if (isset($item[$key]) && !empty($item[$key])) {
                            $sourceFile = strtolower(trim($item[$key]));
                            if (!empty($sourceFile)) {
                                $productImageInsertArray = $this->getInsertProductImages($existingProductImages, $sourceFile, $remoteId);
                                if (!empty($productImageInsertArray) && !isset($insertImagesArray[$remoteId . "_" . $sourceFile])) {
                                    $productImageInsertArray["ord"] = ++$ord;
                                    $insertImagesArray[$remoteId . "_" . $sourceFile] = $productImageInsertArray;
                                }
                            }
                        }
                    }
                }

                /**
                 * Wand robDocs
                 */
                if ($this->useProductImagesRobaDocs && isset($item["robDocs"]) && !empty($item["robDocs"])) {
                    foreach ($item["robDocs"] as $robDoc) {
                        $sourceFile = strtolower(trim($robDoc["link"]));
                        if (!empty($sourceFile)) {
                            if ($robDoc["vrsta"] == "Slike") {
                                $productImageInsertArray = $this->getInsertProductImages($existingProductImages, $sourceFile, $remoteId);
                                if (!empty($productImageInsertArray) && !isset($insertImagesArray[$remoteId . "_" . $sourceFile])) {
                                    $productImageInsertArray["ord"] = ++$ord;
                                    $insertImagesArray[$remoteId . "_" . $sourceFile] = $productImageInsertArray;
                                }
                            } else {
                                $productDocumentInsertArray = $this->getInsertProductDocuments($existingProductDocuments, $sourceFile, $remoteId);
                                if (!empty($productDocumentInsertArray) && !isset($insertDocumentsArray[$remoteId . "_" . $sourceFile])) {
                                    $productDocumentInsertArray["name"] = "";
                                    if (isset($robDoc["nazivVrste"])) {
                                        $productDocumentInsertArray["name"] = $robDoc["nazivVrste"];
                                    }
                                    $insertDocumentsArray[$remoteId . "_" . $sourceFile] = $productDocumentInsertArray;
                                }
                            }
                        }
                    }
                }
            }

            /**
             * Insert product attributes
             */
            if (($this->getProductSAttributes == 1 || $this->getProductClassification == 1) && !empty($item["robaAtributi"])) {

                foreach ($item["robaAtributi"] as $robaAtribut) {

                    if (isset($wandAtribut[$robaAtribut["atributID"]]) &&
                        isset($wandAtributVrijednosti[$robaAtribut["atributID"]]) &&
                        isset($wandAtributVrste[$wandAtribut[$robaAtribut["atributID"]]["atributVrstaID"]])) {

                        $wandAtributVrijednost = $wandAtributVrijednosti[$robaAtribut["atributID"]];
                        $wandAtributVrsta = $wandAtributVrste[$wandAtribut[$robaAtribut["atributID"]]["atributVrstaID"]];

                        if ($wandAtributVrsta["opis"] == "Web klasifikacija") {

                            if ($this->getProductClassification == 1) {

                                $groupName = trim($wandAtributVrijednost["opis"]);
                                $groupNamePos = strpos($groupName, " ");
                                $groupCode = substr($groupName, 0, $groupNamePos);

                                if (isset($existingProducts[$remoteId]) && isset($existingProductGroupsCode[$groupCode])) {
                                    $productGroupLinkKey = $existingProducts[$remoteId]["id"] . "_" . $existingProductGroupsCode[$groupCode]["id"];
                                    if (!isset($existingProductGroupLinks[$productGroupLinkKey])) {
                                        $productGroupLinkInsertArray = $this->getEntityDefaults($this->asProductProductGroupLink);
                                        $productGroupLinkInsertArray["product_id"] = $existingProducts[$remoteId]["id"];
                                        $productGroupLinkInsertArray["product_group_id"] = $existingProductGroupsCode[$groupCode]["id"];
                                        $insertArray4["product_product_group_link_entity"][$remoteId . "_" . $grupaId] = $productGroupLinkInsertArray;
                                        /**
                                         * Add to changed array
                                         */
                                        if (isset($existingProducts[$remoteId])) {
                                            $changedIds["product_ids"][] = $existingProducts[$remoteId]["id"];
                                        }
                                    } else {
                                        unset($deleteArray["product_product_group_link_entity"][$existingProductGroupLinks[$productGroupLinkKey]["id"]]);
                                    }
                                } else {
                                    $productGroupLinkInsertArray = $this->getEntityDefaults($this->asProductProductGroupLink);
                                    if (isset($existingProducts[$remoteId])) {
                                        $productGroupLinkInsertArray["product_id"] = $existingProducts[$remoteId]["id"];
                                    } else {
                                        $productGroupLinkInsertArray["filter_insert"]["product_remote_id"] = $remoteId;
                                    }
                                    if (isset($existingProductGroupsCode[$groupCode])) {
                                        $productGroupLinkInsertArray["product_group_id"] = $existingProductGroupsCode[$groupCode]["id"];
                                    } else {
                                        $productGroupLinkInsertArray["filter_insert"]["product_group_code"] = $groupCode;
                                    }
                                    $insertArray4["product_product_group_link_entity"][$remoteId . "_" . $grupaId] = $productGroupLinkInsertArray;
                                    /**
                                     * Add to changed array
                                     */
                                    if (isset($existingProducts[$remoteId])) {
                                        $changedIds["product_ids"][] = $existingProducts[$remoteId]["id"];
                                    }
                                }
                            }
                        } else {

                            if ($this->getProductSAttributes == 1) {

                                $configurationRemoteId = $wandAtributVrsta["atributVrstaID"];

                                /**
                                 * Check if configuration exists
                                 */
                                if (!isset($existingSAttributeConfigurations[$configurationRemoteId])) {

                                    $i = 1;
                                    $url = $key = $this->routeManager->prepareUrl(trim($wandAtributVrsta["opis"]));
                                    while (isset($existingSAttributeConfigurationsFilterKey[$url]) ||
                                        (isset($insertArray["s_product_attribute_configuration_entity"][$url]) &&
                                            $insertArray["s_product_attribute_configuration_entity"][$url]["remote_id"] != $configurationRemoteId)) {
                                        $url = $key . "-" . $i++;
                                    }

                                    $insertArray["s_product_attribute_configuration_entity"][$url] =
                                        $this->getInsertSProductAttributeConfiguration($wandAtributVrsta, $url);
                                }

                                $sProductAttributesLinkInsertArray = array();

                                /**
                                 * Check if link exists
                                 */
                                if (!isset($existingSAttributeLinks[$robaAtribut["robaAtributID"]])) {

                                    $sProductAttributesLinkInsertArray = $this->getEntityDefaults($this->asSProductAttributesLink);

                                    if (!isset($existingProducts[$remoteId])) {
                                        $sProductAttributesLinkInsertArray["filter_insert"]["product_remote_id"] = $remoteId;
                                    } else {
                                        $sProductAttributesLinkInsertArray["product_id"] = $existingProducts[$remoteId]["id"];
                                    }
                                    if (!isset($existingSAttributeConfigurations[$configurationRemoteId])) {
                                        $sProductAttributesLinkInsertArray["filter_insert"]["configuration_remote_id"] = $configurationRemoteId;
                                    } else {
                                        $sProductAttributesLinkInsertArray["s_product_attribute_configuration_id"] =
                                            $existingSAttributeConfigurations[$configurationRemoteId]["id"];
                                    }

                                    $sProductAttributesLinkInsertArray["attribute_value"] = trim($wandAtributVrijednost["opis"]);
                                    $sProductAttributesLinkInsertArray["wand_attribute_value_id"] = $robaAtribut["robaAtributID"];
                                    $sProductAttributesLinkInsertArray["configuration_option"] = NULL;
                                }

                                /**
                                 * Insert option for autocomplete and multiselect
                                 */
                                if ($wandAtributVrsta["tipAtributa"] == 1 || $wandAtributVrsta["tipAtributa"] == 2) {

                                    if (!isset($existingSAttributeConfigurationOptions[$wandAtributVrijednost["atributID"]])) {

                                        $sProductAttributeConfigurationOptionsInsertArray = $this->getEntityDefaults($this->asSProductAttributeConfigurationOptions);

                                        $sProductAttributeConfigurationOptionsInsertArray["configuration_value"] = trim($wandAtributVrijednost["opis"]);
                                        $sProductAttributeConfigurationOptionsInsertArray["remote_id"] = $wandAtributVrijednost["atributID"];

                                        if (!isset($existingSAttributeConfigurations[$configurationRemoteId])) {
                                            $sProductAttributeConfigurationOptionsInsertArray["filter_insert"]["configuration_attribute_remote_id"] =
                                                $configurationRemoteId;
                                        } else {
                                            $sProductAttributeConfigurationOptionsInsertArray["configuration_attribute_id"] =
                                                $existingSAttributeConfigurations[$configurationRemoteId]["id"];
                                        }

                                        $insertArray2["s_product_attribute_configuration_options_entity"][$robaAtribut["atributID"]] =
                                            $sProductAttributeConfigurationOptionsInsertArray;

                                        if (!empty($sProductAttributesLinkInsertArray)) {
                                            $sProductAttributesLinkInsertArray["filter_insert"]["configuration_option_remote_id"] =
                                                $wandAtributVrijednost["atributID"];
                                        }
                                    } else {
                                        if (!empty($sProductAttributesLinkInsertArray)) {
                                            $sProductAttributesLinkInsertArray["configuration_option"] =
                                                $existingSAttributeConfigurationOptions[$wandAtributVrijednost["atributID"]]["id"];
                                        }
                                    }
                                }

                                if (!empty($sProductAttributesLinkInsertArray)) {
                                    $insertArray3["s_product_attributes_link_entity"][$robaAtribut["robaAtributID"]] = $sProductAttributesLinkInsertArray;
                                }
                            }
                        }
                    }
                }
            }
        }

        /**
         * Insert attribute configurations
         */
        if ($this->getProductSAttributes == 1) {
            $insertQuery = $this->getInsertQuery($insertArray);
            if (!empty($insertQuery)) {
                $this->logQueryString($insertQuery);
                $this->databaseContext->executeNonQuery($insertQuery);
            }
        }

        /**
         * Custom product group insert order implementation
         */
        if ($this->getProductGroups == 1 && !empty($insertGroupsArray)) {
            ksort($insertGroupsArray);
            foreach ($insertGroupsArray as $level => $productGroups) {
                $productGroups = array("product_group_entity" => $productGroups);
                if ($level > 0) {
                    $productGroups = $this->filterImportArray($productGroups, $reselectArray);
                }
                $insertGroupsQuery = $this->getInsertQuery($productGroups);
                if (!empty($insertGroupsQuery)) {
                    $this->logQueryString($insertGroupsQuery);
                    $this->databaseContext->executeNonQuery($insertGroupsQuery);
                }
                $reselectArray["product_group_entity"] = $this->getExistingProductGroups("remote_id");
            }
            unset($insertGroupsArray);
        }

        /**
         * Reselect attribute configurations in order to insert attribute configuration options
         */
        if ($this->getProductSAttributes == 1) {
            $reselectArray["s_product_attribute_configuration_entity"] = $this->getSProductAttributeConfigurations("remote_id");
        }

        /**
         * Insert products
         */
        $insertArray2 = $this->filterImportArray($insertArray2, $reselectArray);
        $insertQuery2 = $this->getInsertQuery($insertArray2);
        if (!empty($insertQuery2)) {
            $this->logQueryString($insertQuery2);
            $this->databaseContext->executeNonQuery($insertQuery2);

            if (!empty($changedRemoteIds)) {
                $changedRemoteIds = implode(",", $changedRemoteIds);
                $q = "SELECT id FROM product_entity WHERE remote_id IN ({$changedRemoteIds}) AND remote_source = 'wand';";
                $tmp = $this->databaseContext->getAll($q);
                if (!empty($tmp)) {
                    $changedIds["product_ids"] = array_merge($changedIds["product_ids"], array_column($tmp, "id"));
                }
            }
        }

        /**
         * Reselect products in order to insert product groups and routes
         * and attribute configuration options in order to insert attribute links
         */
        if ($this->getProductSAttributes == 1) {
            $reselectArray["s_product_attribute_configuration_options_entity"] = $this->getSProductAttributeConfigurationOptions();
        }
        $reselectArray["product_entity"] = $this->getExistingProducts("remote_id", $this->productColumns);


        $insertArray3 = $this->filterImportArray($insertArray3, $reselectArray);
        /**
         * Insert and update attribute links
         * Ovo mora biti odvojeno jer je ovdje potrebno jos jednom provjeriti da li postoji takav MD5 jer kreteni unose i na webu i u wandu isto
         */
        if(isset($insertArray3["s_product_attributes_link_entity"]) && !empty($insertArray3["s_product_attributes_link_entity"])){
            $existingSAttributeLinksByKeyQuery = Array();

            $insertQuery4["s_product_attributes_link_entity"] = $insertArray3["s_product_attributes_link_entity"];
            unset($insertArray3["s_product_attributes_link_entity"]);
            foreach ($insertQuery4["s_product_attributes_link_entity"] as $tmpKey => $sProductAttributeLinkToInsert){
                if(isset($existingSAttributeLinksByKey[$sProductAttributeLinkToInsert["attribute_value_key"]])){
                    $existingSAttributeLinksByKeyQuery[] = "UPDATE s_product_attributes_link_entity SET wand_attribute_value_id = {$sProductAttributeLinkToInsert["wand_attribute_value_id"]} WHERE attribute_value_key = '{$sProductAttributeLinkToInsert["attribute_value_key"]}';";
                    unset($insertQuery4["s_product_attributes_link_entity"][$tmpKey]);
                }
            }
            $insertQuery4 = $this->getInsertQuery($insertQuery4);
            if (!empty($insertQuery4)) {
                $this->logQueryString($insertQuery4);
                $this->databaseContext->executeNonQuery($insertQuery4);
            }
            if(!empty($existingSAttributeLinksByKeyQuery)){
                $existingSAttributeLinksByKeyQuery = implode(" ",$existingSAttributeLinksByKeyQuery);
                $this->logQueryString($existingSAttributeLinksByKeyQuery);
                $this->databaseContext->executeNonQuery($existingSAttributeLinksByKeyQuery);
            }
        }

        /**
         * Insert routes
         */
        $insertArray3 = $this->filterImportArray($insertArray3, $reselectArray);
        $insertQuery3 = $this->getInsertQuery($insertArray3);
        if (!empty($insertQuery3)) {
            $this->logQueryString($insertQuery3);
            $this->databaseContext->executeNonQuery($insertQuery3);
        }

        /**
         * Reselect product groups using different keys in order to insert product group links
         */
        if ($this->getProductClassification == 1) {
            /**
             * Classification 1 requires sorting by product_group_code
             */
            $reselectArray["product_group_entity"] = $this->getExistingProductGroups("product_group_code");

        } else if ($this->getProductClassification == 2) {
            /**
             * Classification 2 requires sorting by remote_id
             */
            $reselectArray["product_group_entity"] = $this->getExistingProductGroups("remote_id");
        }

        /**
         * Insert product group links
         */
        $insertArray4 = $this->filterImportArray($insertArray4, $reselectArray);
        $insertQuery4 = $this->getInsertQuery($insertArray4);
        if (!empty($insertQuery4)) {
            $this->logQueryString($insertQuery4);
            $this->databaseContext->executeNonQuery($insertQuery4);
        }

        /**
         * Custom product images filter with FTP implementation
         */
        if ($this->getProductImages == 1 && (!empty($insertImagesArray) || !empty($insertDocumentsArray))) {
            $ftp = ftp_connect($this->ftpHostname);
            if ($ftp !== false) {
                $res = ftp_login($ftp, $this->ftpUsername, $this->ftpPassword);
                if ($res !== false) {
                    ftp_pasv($ftp, true);
                    if (!empty($insertImagesArray)) {
                        $insertImagesArray = $this->filterTableData($insertImagesArray,
                            $reselectArray,
                            "product_images_entity_filter",
                            array("connection" => $ftp));

                        $insertImagesQuery = $this->getInsertQuery(array("product_images_entity" => $insertImagesArray));
                        if (!empty($insertImagesQuery)) {
                            $this->logQueryString($insertImagesQuery);
                            $this->databaseContext->executeNonQuery($insertImagesQuery);
                        }
                    }
                    if (!empty($insertDocumentsArray)) {
                        $insertDocumentsArray = $this->filterTableData($insertDocumentsArray,
                            $reselectArray,
                            "product_document_entity_filter",
                            array("connection" => $ftp));

                        $insertDocumentsQuery = $this->getInsertQuery(array("product_document_entity" => $insertDocumentsArray));
                        if (!empty($insertDocumentsQuery)) {
                            $this->logQueryString($insertDocumentsQuery);
                            $this->databaseContext->executeNonQuery($insertDocumentsQuery);
                        }
                    }
                }
                ftp_close($ftp);
            }
            unset($insertImagesArray);
        }

        unset($insertArray);
        unset($insertArray2);
        unset($insertArray3);
        unset($reselectArray);

        /**
         * Update products
         */
        $updateQuery2 = $this->getUpdateQuery($updateArray2);
        if (!empty($updateQuery2)) {
            $this->logQueryString($updateQuery2);
            $this->databaseContext->executeNonQuery($updateQuery2);
        }
        unset($updateArray2);

        if ($this->getProductGroups == 1) {
            if (empty($this->productGroupManager)) {
                $this->productGroupManager = $this->container->get("product_group_manager");
            }
            $this->productGroupManager->setProductGroupLevels();
        }

        /**
         * Delete product group links
         */
        if ($this->getProductClassification == 1 || $this->getProductClassification == 2) {
            if (isset($deleteArray["product_product_group_link_entity"])) {
                $existingProductGroupLinks = $this->getExistingProductGroupLinks(true);
                if (!empty($existingProductGroupLinks)) {
                    foreach ($deleteArray["product_product_group_link_entity"] as $id => $columns) {
                        if (isset($existingProductGroupLinks[$id])) {
                            $changedIds["product_ids"][] = $existingProductGroupLinks[$id]["product_id"];
                        }
                    }
                    unset($existingProductGroupLinks);
                }
            }

            $deleteQuery = $this->getDeleteQuery($deleteArray);
            if (!empty($deleteQuery)) {
                $this->logQueryString($deleteQuery);
                $this->databaseContext->executeNonQuery($deleteQuery);
            }
            unset($deleteArray);
        }

        echo "Import robe complete\n";

        $changedIds["product_ids"] = array_unique($changedIds["product_ids"]);
        $changedIds["supplier_ids"] = array_unique($changedIds["supplier_ids"]);

        if (!empty($changedIds["product_ids"])) {
            $this->cacheManager->invalidateCacheByTag("product");
        }

        return $changedIds;
    }

    /**
     * @param $startPage
     * @param $fromId
     * @return bool
     * @throws \Exception
     */
    public function importRabati($startPage = 0, $fromId = 0)
    {
        echo "Starting import rabati...\n";

        $changedIds = Array();

        $this->productColumns = array(
            "id",
            "remote_id",
            "active",
            "manufacturer_remote_id",
            "code",
            "discount_diff",
            "discount_diff_base",
            "discount_percentage",
            "discount_percentage_base",
            "discount_type",
            "discount_type_base",
            "date_discount_to",
            "date_discount_base_to",
            "date_discount_from",
            "date_discount_base_from",
            "discount_price_retail",
            "discount_price_base",
            "price_retail",
            "price_base",
            "tax_type_id"
        );
        $accountColumns = array(
            "id",
            "remote_id"
        );
        $accountWhere = "AND is_active = 1
            AND attribute_set_id IN (2, 14)
            AND is_legal_entity = 1
            AND remote_id != ''
            AND remote_id IS NOT NULL";

        $existingProducts = $this->getExistingProducts("remote_id", $this->productColumns);
        $existingProductsByManufacturerId = $this->getExistingProductsByManufacturerId($this->productColumns);
        $existingAccounts = $this->getExistingAccounts("remote_id", $accountColumns, $accountWhere);
        $existingAccountGroups = $this->getExistingAccountGroups();
        $this->taxTypesById = $this->getTaxTypesById();

        $updateArrayVp = array();
        $updateArrayMp = array();
        $insertArray = array();
        $this->excludeDiscountTypes = Array();
        if(isset($_ENV["WAND_EXCLUDE_DISCOUNT_TYPES"]) && !empty($_ENV["WAND_EXCLUDE_DISCOUNT_TYPES"])){
            $this->excludeDiscountTypes = json_decode($_ENV["WAND_EXCLUDE_DISCOUNT_TYPES"],true);
        }

        /**
         * Default set all VP and MP attributes to NULL
         */
        $productsWithDiscountsVp = $this->getDiscountedProductsVpArray();
        foreach ($productsWithDiscountsVp as $productWithDiscountVp) {
            $updateArrayVp["product_entity"][$productWithDiscountVp["id"]] = array(
                "date_discount_base_from" => NULL,
                "date_discount_base_to" => NULL,
                "discount_type_base" => NULL,
                "discount_percentage_base" => NULL,
                "discount_diff_base" => NULL,
                "discount_price_base" => NULL,
                "modified" => "NOW()",
//                "modified_by" => "system"
            );
        }
        unset($productsWithDiscountsVp);

        $productsWithDiscountsMp = $this->getDiscountedProductsMpArray();
        foreach ($productsWithDiscountsMp as $productWithDiscountMp) {
            $updateArrayMp["product_entity"][$productWithDiscountMp["id"]] = array(
                "date_discount_from" => NULL,
                "date_discount_to" => NULL,
                "discount_type" => NULL,
                "discount_percentage" => NULL,
                "discount_diff" => NULL,
                "discount_price_retail" => NULL,
                "modified" => "NOW()",
//                "modified_by" => "system"
            );
        }
        unset($productsWithDiscountsMp);

        /**
         * Begin import
         */
        $q = "TRUNCATE TABLE product_account_price_staging;";
        $this->databaseContext->executeNonQuery($q);

        $q = "TRUNCATE TABLE product_account_group_price_staging;";
        $this->databaseContext->executeNonQuery($q);

        $startDate = \DateTime::createFromFormat("Y-m-d", "1800-12-28");
        $now = new \DateTime();

        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "rabati?pageNumber=" . $page . "&fromId=" . $fromId);

            if ($page == 0 && empty($items)) {
                echo "Import rabati failed: proizvodi is empty!\n";
                return false;
            }

            if (!empty($items)) {
                foreach ($items as $item) {

                    $dateStart = null;
                    $dateEnd = null;

                    $accountRemoteId = trim($item["partnerID"]);
                    $proizvodjacId = trim($item["proizvodjacID"]);
                    $robaId = trim($item["robaID"]);
                    $partnerGrupa = trim($item["partnerGrupa"]);
                    $klasifikacija = trim($item["klasifikacija"]);
                    $postotak = floatval(trim($item["postotak"]));
                    $cijena = floatval(trim($item["cijena"]));
                    $datumStartInt = trim($item["datumStart"]);
                    $datumEndInt = trim($item["datumEnd"]);

                    if (!empty($datumStartInt)) {
                        $dateStart = clone $startDate;
                        $dateStart->add(new \DateInterval("P{$datumStartInt}D"));
                        $dateStart->setTime(0, 0, 0);
                    }
                    if (!empty($datumEndInt)) {
                        $datumEndInt = intval($datumEndInt) + 1;
                        $dateEnd = clone $startDate;
                        $dateEnd->add(new \DateInterval("P{$datumEndInt}D"));
                        $dateEnd->setTime(0, 0, 0);
                    }

                    /**
                     * Skip past rabats
                     */
                    if (!empty($dateEnd) && $dateEnd < $now) {
                        continue;
                    }
                    if (!empty($dateStart) && $dateStart > $now) {
                        continue;
                    }
                    if (empty($postotak) && empty($cijena)) {
                        continue;
                    }

                    /** Discount for MP only for web buyers */
                    if ($accountRemoteId == $_ENV["MP_WEB_DISCOUNT"]) {
                        if (!empty($robaId)) {

                            $product = null;
                            if (isset($existingProducts[$robaId]) && $existingProducts[$robaId]["active"]) {
                                $product = $existingProducts[$robaId];
                            }

                            $type = 2;

                            $tmp = $this->setProductDiscountMp($product, $postotak, $cijena, $dateStart, $dateEnd, $type);
                            if (!empty($tmp)) {
                                $productUpdateArray = array(
                                    "discount_diff" => $tmp["discount_diff"],
                                    "discount_percentage" => $tmp["discount_percentage"],
                                    "discount_type" => $tmp["discount_type"],
                                    "date_discount_to" => $tmp["date_discount_to"],
                                    "date_discount_from" => $tmp["date_discount_from"],
                                    "discount_price_retail" => $tmp["discount_price_retail"],
                                    "modified" => "NOW()",
//                                    "modified_by" => "system"
                                );
                                $updateArrayMp["product_entity"][$tmp["id"]] = $productUpdateArray;
                            }
                            unset($tmp);
                            continue;

                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 6 && !empty($proizvodjacId)) {
                            $type = 12;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 4 && !empty($proizvodjacId)) {
                            $type = 13;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 2 && !empty($proizvodjacId)) {
                            $type = 14;
                        } else if (!empty($proizvodjacId)) {
                            $type = 15;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 6) {
                            $type = 16;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 4) {
                            $type = 17;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 2) {
                            $type = 18;
                        } else {
                            $this->logger->error("Import rabati errorMP2");
                            continue;
                        }

                        $products = $this->getProductsByCodePartArray($existingProductsByManufacturerId, $proizvodjacId, $klasifikacija);
                        if (empty($products)) {
                            continue;
                        }

                        foreach ($products as $productCode => $product) {
                            $tmp = $this->setProductDiscountMp($product, $postotak, $cijena, $dateStart, $dateEnd, $type);
                            if (!empty($tmp)) {
                                $productUpdateArray = array(
                                    "discount_diff" => $tmp["discount_diff"],
                                    "discount_percentage" => $tmp["discount_percentage"],
                                    "discount_type" => $tmp["discount_type"],
                                    "date_discount_to" => $tmp["date_discount_to"],
                                    "date_discount_from" => $tmp["date_discount_from"],
                                    "discount_price_retail" => $tmp["discount_price_retail"],
                                    "modified" => "NOW()",
//                                    "modified_by" => "system"
                                );
                                $updateArrayMp["product_entity"][$tmp["id"]] = $productUpdateArray;
                            }
                            unset($tmp);
                        }
                        unset($products);

                    } /** Discount for MP also for stores buyers */
                    else if ($accountRemoteId == $_ENV["MP_ALL_DISCOUNT"]) {
                        if (!empty($robaId)) {

                            $product = null;
                            if (isset($existingProducts[$robaId]) && $existingProducts[$robaId]["active"]) {
                                $product = $existingProducts[$robaId];
                            }

                            $type = 11;

                            $tmp = $this->setProductDiscountMp($product, $postotak, $cijena, $dateStart, $dateEnd, $type);
                            if (!empty($tmp)) {
                                $productUpdateArray = array(
                                    "discount_diff" => $tmp["discount_diff"],
                                    "discount_percentage" => $tmp["discount_percentage"],
                                    "discount_type" => $tmp["discount_type"],
                                    "date_discount_to" => $tmp["date_discount_to"],
                                    "date_discount_from" => $tmp["date_discount_from"],
                                    "discount_price_retail" => $tmp["discount_price_retail"],
                                    "modified" => "NOW()",
//                                    "modified_by" => "system"
                                );
                                $updateArrayMp["product_entity"][$tmp["id"]] = $productUpdateArray;
                            }
                            unset($tmp);
                            continue;

                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 6 && !empty($proizvodjacId)) {
                            $type = 12;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 4 && !empty($proizvodjacId)) {
                            $type = 13;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 2 && !empty($proizvodjacId)) {
                            $type = 14;
                        } else if (!empty($proizvodjacId)) {
                            $type = 15;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 6) {
                            $type = 16;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 4) {
                            $type = 17;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 2) {
                            $type = 18;
                        } else {
                            $this->logger->error("Import rabati errorMP2");
                            continue;
                        }

                        $products = $this->getProductsByCodePartArray($existingProductsByManufacturerId, $proizvodjacId, $klasifikacija);
                        if (empty($products)) {
                            continue;
                        }

                        foreach ($products as $product) {
                            $tmp = $this->setProductDiscountMp($product, $postotak, $cijena, $dateStart, $dateEnd, $type);
                            if (!empty($tmp)) {
                                $productUpdateArray = array(
                                    "discount_diff" => $tmp["discount_diff"],
                                    "discount_percentage" => $tmp["discount_percentage"],
                                    "discount_type" => $tmp["discount_type"],
                                    "date_discount_to" => $tmp["date_discount_to"],
                                    "date_discount_from" => $tmp["date_discount_from"],
                                    "discount_price_retail" => $tmp["discount_price_retail"],
                                    "modified" => "NOW()",
//                                    "modified_by" => "system"
                                );
                                $updateArrayMp["product_entity"][$tmp["id"]] = $productUpdateArray;
                            }
                            unset($tmp);
                        }
                        unset($products);

                    } /** Discount for VP buyers */
                    else if ($accountRemoteId == $_ENV["VP_WEB_DISCOUNT"]) {

                        if (!empty($robaId)) {

                            $product = null;
                            if (isset($existingProducts[$robaId]) && $existingProducts[$robaId]["active"]) {
                                $product = $existingProducts[$robaId];
                            }

                            $type = 1;

                            $tmp = $this->setProductDiscountVp($product, $postotak, $cijena, $dateStart, $dateEnd, $type);
                            if (!empty($tmp)) {
                                $productUpdateArray = array(
                                    "discount_diff_base" => $tmp["discount_diff_base"],
                                    "discount_percentage_base" => $tmp["discount_percentage_base"],
                                    "discount_type_base" => $tmp["discount_type_base"],
                                    "date_discount_base_to" => $tmp["date_discount_base_to"],
                                    "date_discount_base_from" => $tmp["date_discount_base_from"],
                                    "discount_price_base" => $tmp["discount_price_base"],
                                    "modified" => "NOW()",
//                                    "modified_by" => "system"
                                );
                                $updateArrayVp["product_entity"][$tmp["id"]] = $productUpdateArray;
                            }
                            unset($tmp);
                            continue;

                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 6 && !empty($proizvodjacId)) {
                            $type = 2;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 4 && !empty($proizvodjacId)) {
                            $type = 3;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 2 && !empty($proizvodjacId)) {
                            $type = 4;
                        } else if (!empty($proizvodjacId)) {
                            $type = 5;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 6) {
                            $type = 6;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 4) {
                            $type = 7;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 2) {
                            $type = 8;
                        } else {
                            $this->logger->error("Import rabati error-1");
                            continue;
                        }

                        $products = $this->getProductsByCodePartArray($existingProductsByManufacturerId, $proizvodjacId, $klasifikacija);
                        if (empty($products)) {
                            continue;
                        }

                        foreach ($products as $product) {
                            $tmp = $this->setProductDiscountVp($product, $postotak, $cijena, $dateStart, $dateEnd, $type);
                            if (!empty($tmp)) {
                                $productUpdateArray = array(
                                    "discount_diff_base" => $tmp["discount_diff_base"],
                                    "discount_percentage_base" => $tmp["discount_percentage_base"],
                                    "discount_type_base" => $tmp["discount_type_base"],
                                    "date_discount_base_to" => $tmp["date_discount_base_to"],
                                    "date_discount_base_from" => $tmp["date_discount_base_from"],
                                    "discount_price_base" => $tmp["discount_price_base"],
                                    "modified" => "NOW()",
//                                    "modified_by" => "system"
                                );
                                $updateArrayVp["product_entity"][$tmp["id"]] = $productUpdateArray;
                            }
                            unset($tmp);
                        }
                        unset($products);

                    } /** Standard discount */
                    else if (empty($accountRemoteId) && empty($partnerGrupa)) {

                        if (!empty($robaId)) {

                            $product = null;
                            if (isset($existingProducts[$robaId]) && $existingProducts[$robaId]["active"]) {
                                $product = $existingProducts[$robaId];
                            }

                            $type = 11;

                            if ($_ENV["WAND_STANDARD_DISCOUNT"] == "VP") {
                                $tmp = $this->setProductDiscountVp($product, $postotak, $cijena, $dateStart, $dateEnd, $type);
                                if (!empty($tmp)) {
                                    $productUpdateArray = array(
                                        "discount_diff_base" => $tmp["discount_diff_base"],
                                        "discount_percentage_base" => $tmp["discount_percentage_base"],
                                        "discount_type_base" => $tmp["discount_type_base"],
                                        "date_discount_base_to" => $tmp["date_discount_base_to"],
                                        "date_discount_base_from" => $tmp["date_discount_base_from"],
                                        "discount_price_base" => $tmp["discount_price_base"],
                                        "modified" => "NOW()",
                                        //                                    "modified_by" => "system"
                                    );
                                    $updateArrayVp["product_entity"][$tmp["id"]] = $productUpdateArray;
                                }
                            } else {
                                $tmp = $this->setProductDiscountMp($product, $postotak, $cijena, $dateStart, $dateEnd, $type, true);
                                if (!empty($tmp)) {
                                    $productUpdateArray = array(
                                        "discount_diff" => $tmp["discount_diff"],
                                        "discount_percentage" => $tmp["discount_percentage"],
                                        "discount_type" => $tmp["discount_type"],
                                        "date_discount_to" => $tmp["date_discount_to"],
                                        "date_discount_from" => $tmp["date_discount_from"],
                                        "discount_price_retail" => $tmp["discount_price_retail"],
                                        "modified" => "NOW()",
                                        //                                    "modified_by" => "system"
                                    );
                                    $updateArrayMp["product_entity"][$tmp["id"]] = $productUpdateArray;
                                }
                            }

                            unset($tmp);
                            continue;

                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 6 && !empty($proizvodjacId)) {
                            $type = 12;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 4 && !empty($proizvodjacId)) {
                            $type = 13;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 2 && !empty($proizvodjacId)) {
                            $type = 14;
                        } else if (!empty($proizvodjacId)) {
                            $type = 15;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 6) {
                            $type = 16;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 4) {
                            $type = 17;
                        } else if (!empty($klasifikacija) && strlen($klasifikacija) == 2) {
                            $type = 18;
                        } else {
                            $this->logger->error("Import rabati error0");
                            continue;
                        }

                        $products = $this->getProductsByCodePartArray($existingProductsByManufacturerId, $proizvodjacId, $klasifikacija);
                        if (empty($products)) {
                            continue;
                        }

                        foreach ($products as $product) {
                            if ($_ENV["WAND_STANDARD_DISCOUNT"] == "VP") {
                                $tmp = $this->setProductDiscountVp($product, $postotak, $cijena, $dateStart, $dateEnd, $type);
                                if (!empty($tmp)) {
                                    $productUpdateArray = array(
                                        "discount_diff_base" => $tmp["discount_diff_base"],
                                        "discount_percentage_base" => $tmp["discount_percentage_base"],
                                        "discount_type_base" => $tmp["discount_type_base"],
                                        "date_discount_base_to" => $tmp["date_discount_base_to"],
                                        "date_discount_base_from" => $tmp["date_discount_base_from"],
                                        "discount_price_base" => $tmp["discount_price_base"],
                                        "modified" => "NOW()",
                                        //                                    "modified_by" => "system"
                                    );
                                    $updateArrayVp["product_entity"][$tmp["id"]] = $productUpdateArray;
                                }
                            } else {
                                $tmp = $this->setProductDiscountMp($product, $postotak, $cijena, $dateStart, $dateEnd, $type, true);
                                if (!empty($tmp)) {
                                    $productUpdateArray = array(
                                        "discount_diff" => $tmp["discount_diff"],
                                        "discount_percentage" => $tmp["discount_percentage"],
                                        "discount_type" => $tmp["discount_type"],
                                        "date_discount_to" => $tmp["date_discount_to"],
                                        "date_discount_from" => $tmp["date_discount_from"],
                                        "discount_price_retail" => $tmp["discount_price_retail"],
                                        "modified" => "NOW()",
                                        //                                    "modified_by" => "system"
                                    );
                                    $updateArrayMp["product_entity"][$tmp["id"]] = $productUpdateArray;
                                }
                            }
                            unset($tmp);
                        }
                        unset($products);

                    } /** Rabat po korisniku */
                    else if (!empty($accountRemoteId)) {

                        if (!isset($existingAccounts[$accountRemoteId])) {
                            continue;
                        }

                        /** Rabat na explicitnu robu */
                        if (!empty($robaId)) {
                            $type = 21;

                            if (!isset($existingProducts[$robaId])) {
                                continue;
                            }
                            $this->setAccountPriceArray($existingProducts[$robaId], $existingAccounts[$accountRemoteId]["id"], $postotak, $cijena, $dateStart, $dateEnd, $type);
                            continue;

                        } /** Rabat na klasifikaciju 6 i proizvodjaca */
                        else if (!empty($klasifikacija) && strlen($klasifikacija) == 6 && !empty($proizvodjacId)) {
                            $type = 22;
                        } /** Rabat na klasifikaciju 4 i proizvodjaca */
                        else if (!empty($klasifikacija) && strlen($klasifikacija) == 4 && !empty($proizvodjacId)) {
                            $type = 23;
                        } /** Rabat na klasifikaciju 2 i proizvodjaca */
                        else if (!empty($klasifikacija) && strlen($klasifikacija) == 2 && !empty($proizvodjacId)) {
                            $type = 24;
                        } /** Rabat na proizvodjaca */
                        else if (!empty($proizvodjacId)) {
                            $type = 25;
                        } /** Rabat na klasifikaciju 6 */
                        else if (!empty($klasifikacija) && strlen($klasifikacija) == 6) {
                            $type = 26;
                        } /** Rabat na klasifikaciju 4 */
                        else if (!empty($klasifikacija) && strlen($klasifikacija) == 4) {
                            $type = 27;
                        } /** Rabat na klasifikaciju 2 */
                        else if (!empty($klasifikacija) && strlen($klasifikacija) == 2) {
                            $type = 28;
                        } else {
                            $this->logger->error("Import rabati error1");
                            continue;
                        }



                        $products = $this->getProductsByCodePartArray($existingProductsByManufacturerId, $proizvodjacId, $klasifikacija);
                        if (empty($products)) {
                            continue;
                        }

                        foreach ($products as $product) {
                            $this->setAccountPriceArray($product, $existingAccounts[$accountRemoteId]["id"], $postotak, $cijena, $dateStart, $dateEnd, $type);
                        }
                        unset($products);

                    } /** Rabat po grupi korisnika */
                    else if (!empty($partnerGrupa)) {

                        if (!isset($existingAccountGroups[$partnerGrupa])) {
                            continue;
                        }

                        /** Rabat na explicitnu robu */
                        if (!empty($robaId)) {
                            $type = 31;

                            if (!isset($existingProducts[$robaId])) {
                                continue;
                            }
                            $this->setAccountGroupPriceArray($existingProducts[$robaId], $existingAccountGroups[$partnerGrupa]["id"], $postotak, $cijena, $dateStart, $dateEnd, $type);
                            continue;

                        } /** Rabat na klasifikaciju 6 i proizvodjaca */
                        else if (!empty($klasifikacija) && strlen($klasifikacija) == 6 && !empty($proizvodjacId)) {
                            $type = 32;
                        } /** Rabat na klasifikaciju 4 i proizvodjaca */
                        else if (!empty($klasifikacija) && strlen($klasifikacija) == 4 && !empty($proizvodjacId)) {
                            $type = 33;
                        } /** Rabat na klasifikaciju 2 i proizvodjaca */
                        else if (!empty($klasifikacija) && strlen($klasifikacija) == 2 && !empty($proizvodjacId)) {
                            $type = 34;
                        } /** Rabat na proizvodjaca */
                        else if (!empty($proizvodjacId)) {
                            $type = 35;
                        } /** Rabat na klasifikaciju 6 */
                        else if (!empty($klasifikacija) && strlen($klasifikacija) == 6) {
                            $type = 36;
                        } /** Rabat na klasifikaciju 4 */
                        else if (!empty($klasifikacija) && strlen($klasifikacija) == 4) {
                            $type = 37;
                        } /** Rabat na klasifikaciju 2 */
                        else if (!empty($klasifikacija) && strlen($klasifikacija) == 2) {
                            $type = 38;
                        } else {
                            $this->logger->error("Import rabati error3");
                            continue;
                        }

                        $products = $this->getProductsByCodePartArray($existingProductsByManufacturerId, $proizvodjacId, $klasifikacija);
                        if (empty($products)) {
                            continue;
                        }

                        foreach ($products as $product) {
                            $this->setAccountGroupPriceArray($product, $existingAccountGroups[$partnerGrupa]["id"], $postotak, $cijena, $dateStart, $dateEnd, $type);
                        }
                        unset($products);

                    } else {
                        $this->logger->error("Import rabati error4");
                        continue;
                    }
                }
            }

            echo "Fetched rabati page: " . $page . "\n";
            $page++;
        } while (count($items) > 0);

        /**
         * Update
         */

        /**
         * Update VP
         */
        $updateQuery = $this->getUpdateQuery($updateArrayVp);
        if (!empty($updateQuery)) {
            $changedIds = array_merge($changedIds,array_keys($updateArrayVp["product_entity"]));
            $this->logQueryString($updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }

        /**
         * Update MP
         */
        $updateQuery = $this->getUpdateQuery($updateArrayMp);
        if (!empty($updateQuery)) {
            $changedIds = array_merge($changedIds,array_keys($updateArrayMp["product_entity"]));
            $this->logQueryString($updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }

        /**
         * Insert
         */
        $tmp = Array();

        if (!empty($this->saveAccountPricesArray)) {
            foreach ($this->saveAccountPricesArray as $productDataArray) {
                $counter = 0;
                $tmp["product_account_price_staging"] = Array();
                foreach ($productDataArray as $productData) {
                    $counter++;
                    $tmp["product_account_price_staging"][] = $productData;
                    if($counter > 30000){
                        $counter = 0;
                        $insertQuery = $this->getInsertQuery($tmp);
                        if (!empty($insertQuery)) {
                            $this->logQueryString($insertQuery);
                            $this->databaseContext->executeNonQuery($insertQuery);
                        }

                        $tmp["product_account_price_staging"] = Array();
                    }
                }

                if(!empty($tmp["product_account_price_staging"])){
                    $insertQuery = $this->getInsertQuery($tmp);
                    if (!empty($insertQuery)) {
                        $this->logQueryString($insertQuery);
                        $this->databaseContext->executeNonQuery($insertQuery);
                    }
                }
            }
        }

        $tmp = Array();

        if (!empty($this->saveAccountGroupPricesArray)) {
            foreach ($this->saveAccountGroupPricesArray as $productDataArray) {
                $counter = 0;
                $tmp["product_account_group_price_staging"] = Array();
                foreach ($productDataArray as $productData) {
                    $counter++;
                    $tmp["product_account_group_price_staging"][] = $productData;
                    if($counter > 30000){
                        $counter = 0;
                        $insertQuery = $this->getInsertQuery($tmp);
                        if (!empty($insertQuery)) {
                            $this->logQueryString($insertQuery);
                            $this->databaseContext->executeNonQuery($insertQuery);
                        }

                        $tmp["product_account_group_price_staging"] = Array();
                    }
                }

                if(!empty($tmp["product_account_group_price_staging"])){
                    $insertQuery = $this->getInsertQuery($tmp);
                    if (!empty($insertQuery)) {
                        $this->logQueryString($insertQuery);
                        $this->databaseContext->executeNonQuery($insertQuery);
                    }
                }
            }
        }

        echo "Calling sp_import_partner_rabats...\n";
        $q = "CALL sp_import_partner_rabats()";
        $this->databaseContext->executeNonQuery($q);

        echo "Calling sp_import_account_groups_rabats...\n";
        $q = "CALL sp_import_account_groups_rabats()";
        $this->databaseContext->executeNonQuery($q);

        echo "Import rabati complete\n";

        $changedIds = array_unique($changedIds);

        return $changedIds;
    }

    /**
     * @param $startPage
     * @return bool
     * @throws \Exception
     */
    public function importStanja($startPage = 0)
    {
        echo "Starting import stanja...\n";

        $changedIds = Array();

        /**
         * Get existing arrays
         */
        $existingWarehouseLinks = $this->getExistingWarehouseLinks();
        $existingProducts = $this->getExistingProducts("remote_id", array("id", "remote_id"), true);
        $existingWarehouses = $this->getExistingWarehouses("remote_id");

        /**
         * Prepare import arrays
         */
        $insertArray = array("product_warehouse_link_entity" => array());
        $updateArray = array();
        $deleteArray = array();

        /**
         * Fill delete array
         */
        foreach ($existingWarehouseLinks as $productRemoteId => $product) {
            foreach ($product as $warehouseId => $warehouse) {
                $deleteArray["product_warehouse_link_entity"][$warehouse["id"]] = array("id" => $warehouse["id"]);
            }
        }

        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "stanja?pageNumber=" . $page);
            if (!empty($items)) {
                foreach ($items as $item) {

                    $linkRemoteId = trim($item["stanjeID"]);
                    $productRemoteId = trim($item["robaID"]);
                    $warehouseRemoteId = trim($item["skladisteID"]);
                    $qty = intval(trim($item["raspolozivo"]));
                    $min = intval(trim($item["kolicinaMin"]));

                    if (!isset($existingProducts[$productRemoteId])) {
                        continue;
                    }
                    if (!isset($existingWarehouses[$warehouseRemoteId])) {
                        continue;
                    }

                    $productId = $existingProducts[$productRemoteId]["id"];
                    $warehouseId = $existingWarehouses[$warehouseRemoteId]["id"];

                    if (isset($existingWarehouseLinks[$productRemoteId]) && isset($existingWarehouseLinks[$productRemoteId][$warehouseRemoteId])) {
                        unset($deleteArray["product_warehouse_link_entity"][$existingWarehouseLinks[$productRemoteId][$warehouseRemoteId]["id"]]);

                        if ($existingWarehouseLinks[$productRemoteId][$warehouseRemoteId]["qty"] != $qty) {
                            $updateArray["product_warehouse_link_entity"][$existingWarehouseLinks[$productRemoteId][$warehouseRemoteId]["id"]]["qty"] = $qty;
                            $updateArray["product_warehouse_link_entity"][$existingWarehouseLinks[$productRemoteId][$warehouseRemoteId]["id"]]["modified"] = "NOW()";
//                            $updateArray["product_warehouse_link_entity"][$existingWarehouseLinks[$productRemoteId][$warehouseRemoteId]["id"]]["modified_by"] = "system";

                            $changedIds[] = $productId;
                        }
                        if ($existingWarehouseLinks[$productRemoteId][$warehouseRemoteId]["min_qty"] != $min) {
                            $updateArray["product_warehouse_link_entity"][$existingWarehouseLinks[$productRemoteId][$warehouseRemoteId]["id"]]["min_qty"] = $min;
                            $updateArray["product_warehouse_link_entity"][$existingWarehouseLinks[$productRemoteId][$warehouseRemoteId]["id"]]["modified"] = "NOW()";

                            $changedIds[] = $productId;
                        }
                    } else {
                        $insertArray["product_warehouse_link_entity"][$linkRemoteId] = $this->getEntityDefaults($this->asProductWarehouseLink);

                        $insertArray["product_warehouse_link_entity"][$linkRemoteId]["product_id"] = $productId;
                        $insertArray["product_warehouse_link_entity"][$linkRemoteId]["warehouse_id"] = $warehouseId;
                        $insertArray["product_warehouse_link_entity"][$linkRemoteId]["qty"] = $qty;
                        $insertArray["product_warehouse_link_entity"][$linkRemoteId]["min_qty"] = $min;
                        $insertArray["product_warehouse_link_entity"][$linkRemoteId]["remote_id"] = $linkRemoteId;

                        $changedIds[] = $productId;
                    }
                }
            }

            echo "Fetched stanja page: " . $page . "\n";
            $page++;
        } while (count($items) > 0);

        /**
         * Insert
         */
        $insertQuery = $this->getInsertQuery($insertArray);
        if (!empty($insertQuery)) {
            $this->logQueryString($insertQuery);
            $this->databaseContext->executeNonQuery($insertQuery);
        }
        unset($insertArray);

        /**
         * Update
         */
        $updateQuery = $this->getUpdateQuery($updateArray);
        if (!empty($updateQuery)) {
            $this->logQueryString($updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }
        unset($updateArray);

        /**
         * Delete
         */
        $deleteQuery = $this->getDeleteQuery($deleteArray);
        if (!empty($deleteQuery)) {
            $this->logQueryString($deleteQuery);
            $this->databaseContext->executeNonQuery($deleteQuery);
        }
        unset($deleteArray);

        echo "Import stanja complete\n";

        return $changedIds;
    }

    /**
     * @param $startPage
     * @param $fromId
     * @return bool
     * @throws \Exception
     */
    public function importSkladista($startPage = 0, $fromId = 0)
    {
        echo "Starting import skladista...\n";

        /**
         * Get existing arrays
         */
        $existingWarehouses = $this->getExistingWarehouses("remote_id");

        /**
         * Prepare import arrays
         */
        $insertArray = array();
        $updateArray = array();
        $deleteArray["warehouse_entity"] = $this->getDeleteConditions($existingWarehouses, array("id"));

        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "skladista?pageNumber=" . $page . "&fromId=" . $fromId);
            if (!empty($items)) {

                foreach ($items as $item) {

                    $remoteId = trim($item["skladisteID"]);
                    $code = trim($item["skladiste"]);
                    $name = trim($item["naziv"]);
                    $address = trim($item["adresa"]);

                    $nameArray = array();

                    foreach ($this->getStores() as $store) {
                        $nameArray[$store] = $name;
                    }

                    $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);

                    if (!isset($existingWarehouses[$remoteId])) {

                        $warehouseInsertArray = $this->getEntityDefaults($this->asWarehouse);

                        $warehouseInsertArray["remote_id"] = $remoteId;
                        $warehouseInsertArray["code"] = $code;
                        $warehouseInsertArray["name"] = $nameJson;
                        $warehouseInsertArray["address"] = $address;

                        $insertArray["warehouse_entity"][$remoteId] = $warehouseInsertArray;

                    } else {

                        $warehouseUpdateArray = array();

                        if (!empty($nameJson) &&
                            $nameArray != json_decode($existingWarehouses[$remoteId]["name"], true)) {
                            $warehouseUpdateArray["name"] = $nameJson;
                        }
                        if ($existingWarehouses[$remoteId]["code"] != $code) {
                            $warehouseUpdateArray["code"] = $code;
                        }
                        if ($existingWarehouses[$remoteId]["address"] != $address) {
                            $warehouseUpdateArray["address"] = $address;
                        }
                        if (!empty($warehouseUpdateArray)) {
                            $warehouseUpdateArray["modified"] = "NOW()";
//                            $warehouseUpdateArray["modified_by"] = "system";
                            $updateArray["warehouse_entity"][$existingWarehouses[$remoteId]["id"]] = $warehouseUpdateArray;
                        }

                        unset($deleteArray["warehouse_entity"][$existingWarehouses[$remoteId]["id"]]);
                    }
                }
            }

            echo "Fetched skladista page: " . $page . "\n";
            $page++;
        } while (count($items) > 0);

        /**
         * Insert
         */
        $insertQuery = $this->getInsertQuery($insertArray);
        if (!empty($insertQuery)) {
            $this->logQueryString($insertQuery);
            $this->databaseContext->executeNonQuery($insertQuery);
        }
        unset($insertArray);

        /**
         * Update
         */
        $updateQuery = $this->getUpdateQuery($updateArray);
        if (!empty($updateQuery)) {
            $this->logQueryString($updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }
        unset($updateArray);

        /**
         * Delete
         */
        $deleteQuery = $this->getDeleteQuery($deleteArray);
        if (!empty($deleteQuery)) {
            $this->logQueryString($deleteQuery);
            $this->databaseContext->executeNonQuery($deleteQuery);
        }
        unset($deleteArray);

        echo "Import skladista complete\n";

        return true;
    }

    /**
     * @param int $startPage
     * @param string $fromDate
     * @return bool
     * @throws \Exception
     */
    public function importConfigurableRobe($startPage = 0, $fromDate = "1979-01-01")
    {
        print("Starting configurable import robe...\n");

        $this->productColumns = array(
            "id",
            "remote_id",
            "product_type_id"
        );

        /**
         * Get existing items
         */
        $existingProducts = $this->getExistingProducts("remote_id", $this->productColumns);

        $insertArray = array("product_entity" => array());
        $insertArray2 = array("product_configurable_attribute_entity" => array());
        $insertArray3 = array("s_route_entity" => array(), "product_product_group_link_entity" => array());
        $updateArray = array();

        /**
         * Begin import
         */
        $page = $startPage;
        do {
            $items = $this->restManager->get($this->apiUrl . "robe?pageNumber=" . $page . "&fromDate=" . $fromDate);
            if (!empty($items)) {
                foreach ($items as $item) {
                    $remoteId = trim($item["robaID"]);
                    if (empty($remoteId)) {
                        continue;
                    }
                    $name = trim($item["naziv2"]);
                    if (empty($name)) {
                        continue;
                    }

                    if (isset($existingProducts[$remoteId])) {
                        if ($existingProducts[$remoteId]["product_type_id"] != CrmConstants::PRODUCT_TYPE_SIMPLE) {
                            $updateArray["product_entity"][$existingProducts[$remoteId]["id"]] = array(
                                "modified" => "NOW()",
                                "product_type_id" => CrmConstants::PRODUCT_TYPE_SIMPLE
                            );
                        }

                        $wandProducts[] = $item;
                    }
                }
            }

            echo "Fetched robe page: " . $page . "\n";
            $page++;
        } while (count($items) > 0);

        /**
         * Update first pass
         */
        $updateQuery = $this->getUpdateQuery($updateArray);
        if (!empty($updateQuery)) {

            $this->logQueryString($updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }

        unset($updateArray);
        unset($existingProducts);

        $updateArray = array();

        if (empty($wandProducts)) {
            echo "Import configurabile robe failed: proizvodi is empty!\n";
            return false;
        }

        $productManager = $this->container->get("product_manager");

        $productTypes = $this->getProductTypes();
        $existingSimpleProducts = $this->getExistingSimpleProducts();
//        $existingProductConfigurationLinks = $this->getExistingProductConfigurationLinks();
        $existingConfigurableProducts = $this->getExistingConfigurableProductsName();
        $existingSProductAttributeLinks = $this->getExistingSProductAttributeLinks();
        $existingSProductAttributeConfigurations = $this->getExistingSProductAttributeConfigurations();
        $existingProductGroupsPerProduct = $this->getExistingProductGroupsPerProduct();
        $existingSRoutes = $this->getExistingSRoutes();
        $existingSProductAttributeConfigurationsByRemoteId = $this->getExistingSProductAttributeConfigurationsByRemoteId();
        $existingConfigurableProductAttributes = $this->getExistingProductConfigurableAttributes();
        $wandAtributVrijednosti = $this->getWandAtributVrijednosti();
        $wandAtribut = $this->getWandAtribut();
        $wandAtributVrste = $this->getWandAtributVrste();

        $existingUpdatedConfigurableProducts = $this->getUpdatedConfigurableProducts();

        foreach ($wandProducts as $item) {

            $remoteId = trim($item["robaID"]);
            $name = trim($item["naziv2"]);
            $description = trim($item["opis"]);

            /**
             * Prepare json strings
             */
            $nameArray = array();
            $showOnStoreArray = array();
            $descriptionArray = array();
            $storeId = null;

            foreach ($this->getStores() as $store) {
                $nameArray[$store] = $name;
                $showOnStoreArray[$store] = 1;
                $descriptionArray[$store] = $description;

                if (isset($existingConfigurableProducts[$store][$name]) && empty($storeId)) {
                    $storeId = $store;
                }
            }

            $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);
            $showOnStoreJson = json_encode($showOnStoreArray, JSON_UNESCAPED_UNICODE);
            $descriptionJson = json_encode($descriptionArray, JSON_UNESCAPED_UNICODE);

            if (isset($existingSimpleProducts[$remoteId])) {

                $childId = $existingSimpleProducts[$remoteId]["id"];

                // If product doesn't exists
                if (!isset($existingConfigurableProducts[$storeId][$name])) {
                    $productConfigurableInsertArray = $this->getEntityDefaults($this->asProduct);

                    $productConfigurableInsertArray["name"] = $nameJson;
                    $productConfigurableInsertArray["product_type_id"] = $productTypes[CrmConstants::PRODUCT_TYPE_CONFIGURABLE]["id"];
                    $productConfigurableInsertArray["show_on_store"] = $showOnStoreJson;
                    $productConfigurableInsertArray["keep_url"] = 1;
                    $productConfigurableInsertArray[$_ENV["WAND_DESCRIPTION_ATTRIBUTE"]] = $descriptionJson;
                    $productConfigurableInsertArray["template_type_id"] = 5;
                    $productConfigurableInsertArray["active"] = 1;
                    $productConfigurableInsertArray["is_visible"] = 1;
                    $productConfigurableInsertArray["ready_for_webshop"] = 1;
                    $productConfigurableInsertArray["is_saleable"] = 1;
                    $productConfigurableInsertArray["qty"] = 1;
                    $productConfigurableInsertArray["qty_step"] = 1;
                    $productConfigurableInsertArray["tax_type_id"] = 1;
                    $productConfigurableInsertArray["currency_id"] = $_ENV["DEFAULT_CURRENCY"];

                    $insertArray["product_entity"][$name] = $productConfigurableInsertArray;

                    $insertQuery = $this->getInsertQuery($insertArray);
                    $this->databaseContext->executeNonQuery($insertQuery);
                    print("Inserting configurable product '" . $name . "'!\n");

                    $existingUpdatedConfigurableProducts[$name] = true;

                    $updateArray["product_entity"][$existingSimpleProducts[$remoteId]["id"]] = array(
                        "modified" => "NOW()",
                        "is_visible" => 0
                    );

                    unset($insertArray["product_entity"][$name]);

                    $existingConfigurableProducts = $this->getExistingConfigurableProductsName();

                    foreach ($this->getStores() as $store) {
                        $nameArray[$store] = $name;
                        $showOnStoreArray[$store] = 1;

                        if (isset($existingConfigurableProducts[$store][$name]) && empty($storeId)) {
                            $storeId = $store;
                        }
                    }

                    $parentId = $existingConfigurableProducts[$storeId][$name]["id"];

                    // Generate url
                    $urlArray = [];
                    foreach ($this->getStores() as $store) {
                        if (isset($existingSimpleProducts[$remoteId]) && !empty($storeId)) {
                            $i = 1;
                            $url = $key = $this->routeManager->prepareUrl($name);
                            while (isset($existingSRoutes[$store . "_" . $url]) || isset($insertArray3["s_route_entity"][$store . "_" . $url])) {
                                $url = $key . "-" . $i++;
                            }

                            $sRouteInsertArray = $this->getEntityDefaults($this->asSRoute);

                            $sRouteInsertArray["destination_type"] = "product";
                            $sRouteInsertArray["destination_id"] = $parentId;
                            $sRouteInsertArray["request_url"] = $url;
                            $sRouteInsertArray["store_id"] = $store;

                            $insertArray3["s_route_entity"][$store . "_" . $url] = $sRouteInsertArray;

                            $urlArray[$store] = $url;
                        }
                    }

                    $urlJson = json_encode($urlArray, JSON_UNESCAPED_UNICODE);

                    $updateArray["product_entity"][$parentId] = array(
                        "url" => $urlJson
                    );

                    if (isset($existingProductGroupsPerProduct[$childId])) {

                        foreach ($existingProductGroupsPerProduct[$childId] as $productGroupId) {

                            $productProductGroupLinkInsertArray = $this->getEntityDefaults($this->asProductProductGroupLink);

                            $productProductGroupLinkInsertArray["product_id"] = $parentId;
                            $productProductGroupLinkInsertArray["product_group_id"] = $productGroupId;

                            $insertArray3["product_product_group_link_entity"][$parentId . "_" . $productGroupId] = $productProductGroupLinkInsertArray;
                        }
                    }

                    if (isset($existingSProductAttributeLinks[$childId]) && !empty($existingSProductAttributeLinks[$childId])) {

                        foreach ($existingSProductAttributeLinks[$childId] as $sProductAttributeConfiguration) {

                            if (isset($existingSProductAttributeConfigurations[$sProductAttributeConfiguration])) {

                                if (!empty($existingSProductAttributeConfigurations[$sProductAttributeConfiguration]["use_in_configurable_products"])) {
                                    $productConfigurableAttributeArray = $this->getEntityDefaults($this->asProductConfigurableAttribute);

                                    $productConfigurableAttributeArray["product_id"] = $parentId;
                                    $productConfigurableAttributeArray["s_product_attribute_configuration_id"] = $sProductAttributeConfiguration;

                                    $insertArray2["product_configurable_attribute_entity"][] = $productConfigurableAttributeArray;
                                    $insertQuery2 = $this->getInsertQuery($insertArray2);
                                    $this->databaseContext->executeNonQuery($insertQuery2);

                                    print("     -> Added attribute '" . $existingSProductAttributeConfigurations[$sProductAttributeConfiguration]["name"] . "' to the configurable product '" . $name . "'!\n");

                                    $insertArray2 = array("product_configurable_attribute_entity" => array());
                                }
                            }
                        }
                    }

                    $configurationAddedToTheConfigurableProduct = $productManager->insertProductConfigurationProductLink($parentId, $childId);
                    if ($configurationAddedToTheConfigurableProduct) {
                        print("     -> Added configuration '" . $item["naziv"] . "' to the configurable product '" . $name . "'!\n");
                    }
                } else {
                    $parentId = $existingConfigurableProducts[$storeId][$name]["id"];

                    // Check if configuration already exists on the product
                    $existingProductConfigurationLinks = $this->getExistingProductConfigurationLinks();

                    if (!isset($existingProductConfigurationLinks[$parentId][$childId])) {
                        $configurationAddedToTheConfigurableProduct = $productManager->insertProductConfigurationProductLink($parentId, $childId);
                        if ($configurationAddedToTheConfigurableProduct) {
                            print("     -> Added configuration '" . $item["naziv"] . "' to the configurable product '" . $name . "'!\n");
                        }
                    }

                    // Check if configurable products attributes match first configuration from the import
                    if ($existingUpdatedConfigurableProducts[$name] == false) {

                        foreach ($item["robaAtributi"] as $sProductAttributeConfiguration) {

                            if (isset($wandAtribut[$sProductAttributeConfiguration["atributID"]]) &&
                                isset($wandAtributVrijednosti[$sProductAttributeConfiguration["atributID"]]) &&
                                isset($wandAtributVrste[$wandAtribut[$sProductAttributeConfiguration["atributID"]]["atributVrstaID"]]) &&
                                isset($existingSProductAttributeConfigurationsByRemoteId[$wandAtributVrste[$wandAtribut[$sProductAttributeConfiguration["atributID"]]["atributVrstaID"]]["atributVrstaID"]])) {

                                $configurationAttribute = $existingSProductAttributeConfigurationsByRemoteId[$wandAtributVrste[$wandAtribut[$sProductAttributeConfiguration["atributID"]]["atributVrstaID"]]["atributVrstaID"]];

                                $configurableProductAttributeKey = $parentId . "_" . $configurationAttribute["id"];

                                if (!isset($existingConfigurableProductAttributes[$configurableProductAttributeKey]) && $configurationAttribute["use_in_configurable_products"]) {
                                    // Insert product configuration attribute
                                    $productConfigurableAttributeArray = $this->getEntityDefaults($this->asProductConfigurableAttribute);

                                    $productConfigurableAttributeArray["product_id"] = $parentId;
                                    $productConfigurableAttributeArray["s_product_attribute_configuration_id"] = $configurationAttribute["id"];

                                    $insertArray2["product_configurable_attribute_entity"][] = $productConfigurableAttributeArray;
                                    $insertQuery2 = $this->getInsertQuery($insertArray2);
                                    $this->databaseContext->executeNonQuery($insertQuery2);

                                    print("     -> Added attribute '" . $configurationAttribute["name"] . "' to the configurable product '" . $name . "'!\n");

                                    $existingConfigurableProductAttributes[$configurableProductAttributeKey] = true;
                                    $insertArray2 = array("product_configurable_attribute_entity" => array());
                                }
                            }
                        }

                        $existingUpdatedConfigurableProducts[$name] = true;
                    }
                }
            }
        }

        /**
         * Set configurations is_visible = 0
         */
        $updateQuery = $this->getUpdateQuery($updateArray);
        if (!empty($updateQuery)) {

            $this->logQueryString($updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }

        /**
         * Insert routes and product groups
         */
        $insertQuery3 = $this->getInsertQuery($insertArray3);
        if (!empty($insertQuery3)) {
            $this->logQueryString($insertQuery3);
            $this->databaseContext->executeNonQuery($insertQuery3);
        }

        return true;
    }

    /**
     * @param $products
     * @param $proizvodjacId
     * @param $klasifikacija
     * @return array
     */
    public function getProductsByCodePartArray($products, $proizvodjacId, $klasifikacija)
    {
        $ret = array();

        if(!empty($klasifikacija) && empty($proizvodjacId)){

            $ret = $this->getExistingProductsByClassification($this->productColumns,$klasifikacija);

            /*$codeParts = str_split($klasifikacija, 2);
            if(isset($codeParts[0]) && isset($codeParts[1]) && isset($codeParts[2]) && isset($products["classification"][$codeParts[0]]["classification"][$codeParts[1]]["classification"][$codeParts[2]])){
                $ret = $products["classification"][$codeParts[0]]["classification"][$codeParts[1]]["classification"][$codeParts[2]]["products"];
            }
            elseif(isset($codeParts[0]) && isset($codeParts[1]) && isset($products["classification"][$codeParts[0]]["classification"][$codeParts[1]])){
                $ret = $products["classification"][$codeParts[0]]["classification"][$codeParts[1]]["products"];
            }
            elseif(isset($codeParts[0]) && isset($products["classification"][$codeParts[0]])){
                $ret = $products["classification"][$codeParts[0]]["products"];
            }*/
        }
        elseif (!empty($proizvodjacId) && isset($products[$proizvodjacId])) {
            if(empty($klasifikacija)){
                $ret = $products[$proizvodjacId];
            }
            else{
                foreach ($products[$proizvodjacId] as $productCode => $product) {
                    if (strcmp(substr($productCode, 0, strlen($klasifikacija)), $klasifikacija) == 0) {
                        $ret[$productCode] = $product;
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * @param $product
     * @param $postotak
     * @param $cijena
     * @param $dateStart
     * @param $dateEnd
     * @param $type
     * @return |null
     */
    public function setProductDiscountVp($product, $postotak, $cijena, $dateStart, $dateEnd, $type)
    {
        if (empty($product)) {
            return null;
        }

        if ($product["discount_type_base"] != 0 && $product["discount_type_base"] < $type) {
            return null;
        }

        if(!empty($this->excludeDiscountTypes) && in_array($type,$this->excludeDiscountTypes)){
            return null;
        }

        $dateEndText = null;
        if (!empty($dateEnd)) {
            $dateEndText = $dateEnd->format("Y-m-d H:i:s");
        }

        if (!empty($cijena)) {

//            if($product["remote_id"] == 58674) {
//                dump($product);die;
//
//            }


//            if (floatval($product["discount_price_base"]) != floatval($cijena) || $dateEndText != $product["date_discount_base_to"]) {

            $product["discount_price_base"] = $cijena;
            if (!empty($dateStart)) {
                $product["date_discount_base_from"] = $dateStart->format("Y-m-d H:i:s");
            } else {
                $product["date_discount_base_from"] = null;
            }
            if (!empty($dateEnd)) {
                $product["date_discount_base_to"] = $dateEnd->format("Y-m-d H:i:s");
            } else {
                $product["date_discount_base_to"] = null;
            }
            $product["discount_type_base"] = $type;
            $product["discount_percentage_base"] = 0;
            $product["discount_diff_base"] = floatval($product["price_base"]) - floatval($cijena);

            /*if (empty($postotak)) {
                $product["discount_percentage"] = round(($product["price_retail"] - $cijena) / $product["price_retail"] * 100);
            }*/

            return $product;
//                }
        } else {
            $save = false;
            /** Calculate VPC */
            $cijena = $product["price_base"] - round($product["price_base"] * floatval($postotak) / 100, 4);
            if (floatval($product["discount_price_base"]) != floatval($cijena) || $dateEndText != $product["date_discount_base_to"]) {
                $product["discount_price_base"] = $cijena;
                $product["discount_percentage_base"] = $postotak;
                $product["discount_diff_base"] = 0;
                $save = true;
            }
            /** Calculate MPC */
            /*$cijena = $product->getPriceRetail() - round($product->getPriceRetail()*floatval($postotak)/100,2);
            if(floatval($product->getDiscountPriceRetail()) != floatval($cijena)){
                $product->setDiscountPriceRetail($cijena);
                $save = true;
            }*/

            if ($save) {
                if (!empty($dateStart)) {
                    $product["date_discount_base_from"] = $dateStart->format("Y-m-d H:i:s");
                } else {
                    $product["date_discount_base_from"] = null;
                }
                if (!empty($dateEnd)) {
                    $product["date_discount_base_to"] = $dateEnd->format("Y-m-d H:i:s");
                } else {
                    $product["date_discount_base_to"] = null;
                }
                $product["discount_type_base"] = $type;
                return $product;
            }
        }

        return null;
    }

    /**
     * @param $product
     * @param $postotak
     * @param $cijena
     * @param $dateStart
     * @param $dateEnd
     * @param $type
     * @param bool $addTax
     * @return |null
     */
    public function setProductDiscountMp($product, $postotak, $cijena, $dateStart, $dateEnd, $type, $addTax = false)
    {
        if (empty($product)) {
            return null;
        }

        if ($product["discount_type"] != 0 && $product["discount_type"] < $type) {
            return null;
        }

        if(!empty($this->excludeDiscountTypes) && in_array($type,$this->excludeDiscountTypes)){
            return null;
        }

        $dateEndText = null;
        if (!empty($dateEnd)) {
            $dateEndText = $dateEnd->format("Y-m-d H:i:s");
        }

        if (!empty($cijena)) {

            if ($addTax) {
                $cijena = floatval($cijena) * (1 + (floatval($this->taxTypesById[$product["tax_type_id"]]["percent"]) / 100));
            }

            //if ((floatval($product["discount_price_retail"]) != floatval($cijena) || $dateEndText != $product["date_discount_to"]) && ($cijena < $product["price_retail"])) {
            $product["discount_price_retail"] = $cijena;
            if (!empty($dateStart)) {
                $product["date_discount_from"] = $dateStart->format("Y-m-d H:i:s");
            } else {
                $product["date_discount_from"] = null;
            }
            if (!empty($dateEnd)) {
                $product["date_discount_to"] = $dateEnd->format("Y-m-d H:i:s");
            } else {
                $product["date_discount_to"] = null;
            }
            $product["discount_type"] = $type;
            $product["discount_percentage"] = 0;
            $product["discount_diff"] = floatval($product["price_retail"]) - floatval($cijena);

            /*if (empty($postotak)) {
                $product["discount_percentage"] = round(($product["price_retail"] - $cijena) / $product["price_retail"] * 100);
            }*/

            return $product;
            //}
        } else {
            $save = false;
            /** Calculate MPC */
            $cijena = $product["price_retail"] - round($product["price_retail"] * floatval($postotak) / 100, 4);
            if (floatval($product["discount_price_retail"]) != floatval($cijena) || $dateEndText != $product["date_discount_to"]) {
                $product["discount_price_retail"] = $cijena;
                $product["discount_percentage"] = $postotak;
                $product["discount_diff"] = 0;
                $save = true;
            }
            /** Calculate MPC */
            /*$cijena = $product->getPriceRetail() - round($product->getPriceRetail()*floatval($postotak)/100,2);
            if(floatval($product->getDiscountPriceRetail()) != floatval($cijena)){
                $product->setDiscountPriceRetail($cijena);
                $save = true;
            }*/

            if ($save) {
                if (!empty($dateStart)) {
                    $product["date_discount_from"] = $dateStart->format("Y-m-d H:i:s");
                } else {
                    $product["date_discount_from"] = null;
                }
                if (!empty($dateEnd)) {
                    $product["date_discount_to"] = $dateEnd->format("Y-m-d H:i:s");
                } else {
                    $product["date_discount_to"] = null;
                }
                $product["discount_type"] = $type;
                return $product;
            }
        }

        return null;
    }

    /**
     * @param $product
     * @param $accountRemoteId
     * @param $postotak
     * @param $cijena
     * @param $dateStart
     * @param $dateEnd
     * @param $type
     * @return |null
     */
    public function setAccountPriceArray($product, $accountRemoteId, $postotak, $cijena, $dateStart, $dateEnd, $type)
    {
        $accountPrice = NULL;

        $productId = $product["id"];

        if (!empty($this->saveAccountPricesArray) && isset($this->saveAccountPricesArray[$accountRemoteId]) && isset($this->saveAccountPricesArray[$accountRemoteId][$productId])) {
            $accountPrice = $this->saveAccountPricesArray[$accountRemoteId][$productId];
        }

        if (!empty($accountPrice)) {

            $basePrice = $product["price_base"];
            if (!empty($cijena)) {
                $basePrice = $cijena;
            }
            if (empty($cijena) && !empty($postotak)) {
                $basePrice = $basePrice - round($basePrice * floatval($postotak) / 100, 4);
            }

            if ($accountPrice["type"] >= $type && ($accountPrice["rebate"] != $postotak || $accountPrice["price_base"] != $basePrice)) {

                $accountPrice["type"] = $type;

                $accountPrice["date_valid_from"] = NULL;
                if (!empty($dateStart)) {
                    $accountPrice["date_valid_from"] = $dateStart->format("Y-m-d");
                }
                $accountPrice["date_valid_to"] = NULL;
                if (!empty($dateEnd)) {
                    $accountPrice["date_valid_to"] = $dateEnd->format("Y-m-d");
                }

                if (!empty($postotak)) {
                    $accountPrice["rebate"] = $postotak;
                } else {
                    $accountPrice["rebate"] = 0;
                }

                $accountPrice["price_base"] = $basePrice;

                $this->saveAccountPricesArray[$accountRemoteId][$productId] = $accountPrice;

                return NULL;
            }
        } else {
            $accountPrice["product_id"] = $productId;
            $accountPrice["account_id"] = $accountRemoteId;
            $accountPrice["type"] = $type;

            $accountPrice["date_valid_from"] = NULL;
            if (!empty($dateStart)) {
                $accountPrice["date_valid_from"] = $dateStart->format("Y-m-d");
            }
            $accountPrice["date_valid_to"] = NULL;
            if (!empty($dateEnd)) {
                $accountPrice["date_valid_to"] = $dateEnd->format("Y-m-d");
            }

            $accountPrice["type"] = $type;
            if (!empty($postotak)) {
                $accountPrice["rebate"] = $postotak;
            } else {
                $accountPrice["rebate"] = 0;
            }

            $basePrice = $product["price_base"];
            if (!empty($cijena)) {
                $basePrice = $cijena;
            }

            if (empty($cijena) && !empty($postotak)) {
                $basePrice = $basePrice - round($basePrice * floatval($postotak) / 100, 4);
            }

            $accountPrice["price_base"] = $basePrice;

            $this->saveAccountPricesArray[$accountRemoteId][$productId] = $accountPrice;

            return NULL;
        }

        return NULL;
    }

    /**
     * @param $product
     * @param $accountGroupId
     * @param $postotak
     * @param $cijena
     * @param $dateStart
     * @param $dateEnd
     * @param $type
     * @return |null
     */
    public function setAccountGroupPriceArray($product, $accountGroupId, $postotak, $cijena, $dateStart, $dateEnd, $type)
    {
        $accountPrice = NULL;

        $productId = $product["id"];

        if (!empty($this->saveAccountGroupPricesArray) && isset($this->saveAccountGroupPricesArray[$accountGroupId]) && isset($this->saveAccountGroupPricesArray[$accountGroupId][$productId])) {
            $accountPrice = $this->saveAccountGroupPricesArray[$accountGroupId][$productId];
        }

        if (!empty($accountPrice)) {

            $basePrice = $product["price_base"];
            if (!empty($cijena)) {
                $basePrice = $cijena;
            }

            if (empty($cijena) && !empty($postotak)) {
                $basePrice = $basePrice - round($basePrice * floatval($postotak) / 100, 4);
            }

            if ($accountPrice["type"] >= $type && ($accountPrice["rebate"] != $postotak || $accountPrice["price_base"] != $basePrice)) {

                $accountPrice["type"] = $type;

                $accountPrice["date_valid_from"] = NULL;
                if (!empty($dateStart)) {
                    $accountPrice["date_valid_from"] = $dateStart->format("Y-m-d");
                }
                $accountPrice["date_valid_to"] = NULL;
                if (!empty($dateEnd)) {
                    $accountPrice["date_valid_to"] = $dateEnd->format("Y-m-d");
                }

                if (!empty($postotak)) {
                    $accountPrice["rebate"] = $postotak;
                } else {
                    $accountPrice["rebate"] = 0;
                }

                $accountPrice["price_base"] = $basePrice;

                $this->saveAccountGroupPricesArray[$accountGroupId][$productId] = $accountPrice;

                return NULL;
            }
        } else {
            $accountPrice["product_id"] = $productId;
            $accountPrice["account_group_id"] = $accountGroupId;
            $accountPrice["type"] = $type;

            $accountPrice["date_valid_from"] = NULL;
            if (!empty($dateStart)) {
                $accountPrice["date_valid_from"] = $dateStart->format("Y-m-d");
            }
            $accountPrice["date_valid_to"] = NULL;
            if (!empty($dateEnd)) {
                $accountPrice["date_valid_to"] = $dateEnd->format("Y-m-d");
            }

            $accountPrice["type"] = $type;
            if (!empty($postotak)) {
                $accountPrice["rebate"] = $postotak;
            } else {
                $accountPrice["rebate"] = 0;
            }

            $basePrice = $product["price_base"];
            if (!empty($cijena)) {
                $basePrice = $cijena;
            }

            if (empty($cijena) && !empty($postotak)) {
                $basePrice = $basePrice - round($basePrice * floatval($postotak) / 100, 4);
            }

            $accountPrice["price_base"] = $basePrice;

            $this->saveAccountGroupPricesArray[$accountGroupId][$productId] = $accountPrice;

            return NULL;
        }

        return NULL;
    }

    /**
     * ONE TIME QUICK FIX
     * @return bool
     * @throws \Exception
     */
    public function updateProductGroupRemoteIds()
    {
        $wandProductGroups = $this->getWandProductGroups();
        if (empty($wandProductGroups)) {
            return false;
        }

        $existingProductGroups = $this->getExistingProductGroups("product_group_code");

        $updateArray = array();

        foreach ($wandProductGroups as $wandProductGroupId => $wandProductGroup) {
            $groupCode = trim($wandProductGroup["sifra"]);
            if ($this->getProductClassification == 1) {
                $groupName = trim($wandProductGroup["naziv"]);
                $groupNamePos = strpos($groupName, " ");
                $groupCode = substr($groupName, 0, $groupNamePos);
            }

            if (isset($existingProductGroups[$groupCode])) {

                $productGroupId = $existingProductGroups[$groupCode]["id"];

                if (empty($existingProductGroups[$groupCode]["remote_id"])) {
                    $updateArray["product_group_entity"][$productGroupId]["remote_id"] = $wandProductGroupId;
                }
                if (empty($existingProductGroups[$groupCode]["remote_source"])) {
                    $updateArray["product_group_entity"][$productGroupId]["remote_source"] = $this->getRemoteSource();
                }
            }
        }

        $updateQuery = $this->getUpdateQuery($updateArray);
        if (!empty($updateQuery)) {
            $this->logQueryString($updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }

        return true;
    }

    /**
     * @param $wandDescription
     * @return string
     */
    public function getWandDescription($wandDescription)
    {

        $wandDescription = nl2br($wandDescription);

//        $wandDescription = preg_split("/\r\n|\n|\r|\<br\>/", $wandDescription);
//        $wandDescription = array_map('trim', $wandDescription);
//        $wandDescription = array_filter($wandDescription);
//        $wandDescription = array_slice($wandDescription, 0, 5);
//        $wandDescription = implode("<br>", $wandDescription);

        return $wandDescription;
    }

    /**
     * @param $url
     * @param $type
     * @param $storeId
     * @param $filterInsert
     * @return array
     */
    public function getInsertSRoute($url, $type, $storeId, $filterInsert)
    {
        $sRouteEntityInsertArray = $this->getEntityDefaults($this->asSRoute);

        $sRouteEntityInsertArray["request_url"] = $url;
        $sRouteEntityInsertArray["destination_type"] = $type;
        $sRouteEntityInsertArray["store_id"] = $storeId;
        $sRouteEntityInsertArray["filter_insert"] = $filterInsert;

        return $sRouteEntityInsertArray;
    }

    /**
     * @param $existingProductImages
     * @param $sourceFile
     * @param $productRemoteId
     * @return array|false
     */
    public function getInsertProductImages($existingProductImages, $sourceFile, $productRemoteId)
    {
        if (stripos($sourceFile, ".jpeg") === false &&
            stripos($sourceFile, ".jpg") === false &&
            stripos($sourceFile, ".png") === false &&
            stripos($sourceFile, ".webp") === false) {
            return false;
        }

        $extension = $this->helperManager->getFileExtension($sourceFile);

        $filename = $this->helperManager->getFilenameWithoutExtension($sourceFile);
        $filename = $this->helperManager->nameToFilename($filename);

        if (isset($existingProductImages[$productRemoteId][$filename . "." . $extension])) {
            return false;
        }

        $productImagesInsertArray = $this->getEntityDefaults($this->asProductImages);

        $productImagesInsertArray["filename"] = $filename;
        $productImagesInsertArray["file_type"] = $extension;

        $productImagesInsertArray["filter_insert"]["source_file"] = $sourceFile;
        $productImagesInsertArray["filter_insert"]["product_remote_id"] = $productRemoteId;

        return $productImagesInsertArray;
    }

    /**
     * @param $existingProductDocuments
     * @param $sourceFile
     * @param $productRemoteId
     * @return array|false
     */
    public function getInsertProductDocuments($existingProductDocuments, $sourceFile, $productRemoteId)
    {
        if (stripos($sourceFile, ".pdf") === false &&
            stripos($sourceFile, ".docx") === false &&
            stripos($sourceFile, ".xlsx") === false) {
            return false;
        }

        $extension = $this->helperManager->getFileExtension($sourceFile);

        $filename = $this->helperManager->getFilenameWithoutExtension($sourceFile);
        $filename = $this->helperManager->nameToFilename($filename);

        if (isset($existingProductDocuments[$productRemoteId][$filename . "." . $extension])) {
            return false;
        }

        $productDocumentsInsertArray = $this->getEntityDefaults($this->asProductDocuments);

        $productDocumentsInsertArray["filename"] = $filename;
        $productDocumentsInsertArray["file_type"] = $extension;

        $productDocumentsInsertArray["filter_insert"]["source_file"] = $sourceFile;
        $productDocumentsInsertArray["filter_insert"]["product_remote_id"] = $productRemoteId;

        return $productDocumentsInsertArray;
    }

    /**
     * @param $wandAtributVrsta
     * @param $filterKey
     * @return array
     */
    public function getInsertSProductAttributeConfiguration($wandAtributVrsta, $filterKey)
    {
        $sProductAttributeConfigurationInsertArray = $this->getEntityDefaults($this->asSProductAttributeConfiguration);

        $sProductAttributeConfigurationInsertArray["name"] = trim($wandAtributVrsta["opis"]);
        $sProductAttributeConfigurationInsertArray["remote_id"] = $wandAtributVrsta["atributVrstaID"];
        $sProductAttributeConfigurationInsertArray["s_product_attribute_configuration_type_id"] = $wandAtributVrsta["tipAtributa"];
        $sProductAttributeConfigurationInsertArray["is_active"] = true;
        $sProductAttributeConfigurationInsertArray["ord"] = 100;
        $sProductAttributeConfigurationInsertArray["show_in_filter"] = false;
        $sProductAttributeConfigurationInsertArray["show_in_list"] = false;
        $sProductAttributeConfigurationInsertArray["filter_template"] = "default";
        $sProductAttributeConfigurationInsertArray["list_view_template"] = "default";
        $sProductAttributeConfigurationInsertArray["filter_key"] = $filterKey;

        return $sProductAttributeConfigurationInsertArray;
    }

    /**
     * @param $configurationRemoteId
     * @param $configurationValue
     * @param $remoteId
     * @return array
     */
    public function getInsertSProductAttributeConfigurationOptions($configurationRemoteId, $configurationValue, $remoteId)
    {
        $sProductAttributeConfigurationOptionsInsertArray = $this->getEntityDefaults($this->asSProductAttributeConfigurationOptions);

        $sProductAttributeConfigurationOptionsInsertArray["filter_insert"]["configuration_attribute_remote_id"] = $configurationRemoteId;
        $sProductAttributeConfigurationOptionsInsertArray["configuration_value"] = $configurationValue;
        $sProductAttributeConfigurationOptionsInsertArray["remote_id"] = $remoteId;

        return $sProductAttributeConfigurationOptionsInsertArray;
    }

    /**
     * @return array
     */
    public function getExistingDeliveryTypes()
    {
        $ret = array();

        $q = "SELECT
                id,
                name,
                remote_code
              FROM delivery_type_entity
              WHERE entity_state_id = 1;";

        $data = $this->databaseContext->getAll($q);
        foreach ($data as $d) {
            $ret[$d["remote_code"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingPaymentTypes()
    {
        $ret = array();

        $q = "SELECT
                id,
                name,
                remote_code,
                remote_id
              FROM payment_type_entity
              WHERE entity_state_id = 1;";

        $data = $this->databaseContext->getAll($q);
        foreach ($data as $d) {
            $ret[$d["remote_code"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingWarehouseLinks()
    {
        $ret = array();

        $q = "SELECT
                pw.id,
                w.remote_id AS warehouse_remote_id,
                p.remote_id AS product_remote_id,
                pw.qty,
                pw.min_qty
            FROM product_warehouse_link_entity AS pw
            LEFT JOIN product_entity AS p ON pw.product_id = p.id
            LEFT JOIN warehouse_entity AS w ON pw.warehouse_id = w.id
            WHERE pw.warehouse_id != 999
            AND pw.warehouse_id != 998
            AND p.entity_state_id = 1;";

        $data = $this->databaseContext->getAll($q);
        foreach ($data as $d) {
            $ret[$d["product_remote_id"]][$d["warehouse_remote_id"]] = array("qty" => intval($d["qty"]), "min_qty" => floatval($d["min_qty"]), "id" => $d["id"]);
        }

        return $ret;
    }

    /**
     * @param $sortKey
     * @return array
     */
    public function getExistingWarehouses($sortKey)
    {
        $ret = array();

        $q = "SELECT
                id,
                remote_id,
                code,
                name,
                address
            FROM warehouse_entity
            WHERE entity_state_id = 1;";

        $data = $this->databaseContext->getAll($q);
        foreach ($data as $d) {
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    /**
     * @param $columnKeys
     * @return array
     */
    public function getExistingProductsByManufacturerId($columnKeys)
    {
        if (!empty($columnKeys)) {
            $columnKeys = implode(",", $columnKeys);
        } else {
            $columnKeys = "*";
        }

        $ret = array();

        $q = "SELECT
                {$columnKeys}
            FROM product_entity
            WHERE entity_state_id = 1
            AND active = 1
            AND manufacturer_remote_id IS NOT NULL
            AND manufacturer_remote_id != 0;";
        $data = $this->databaseContext->getAll($q);

        if(!empty($data)){
            foreach ($data as $d) {
                $ret[$d["manufacturer_remote_id"]][$d["code"]] = $d;
                /*$codeParts = substr($d["code"], 0, -3);
                $codeParts = str_split($codeParts, 2);
                $ret["classification"][$codeParts[0]]["classification"][$codeParts[1]]["products"][$d["code"]] = $d;
                $ret["classification"][$codeParts[0]]["products"][$d["code"]] = $d;*/
            }
        }

        return $ret;
    }

    /**
     * @param $columnKeys
     * @param $codePart
     * @return array
     */
    public function getExistingProductsByClassification($columnKeys, $codePart)
    {
        if (!empty($columnKeys)) {
            $columnKeys = implode(",", $columnKeys);
        } else {
            $columnKeys = "*";
        }

        $ret = array();

        $q = "SELECT
                {$columnKeys}
            FROM product_entity
            WHERE entity_state_id = 1
            AND remote_source = 'wand'
            AND code LIKE '{$codePart}%'
            AND active = 1;";
        $data = $this->databaseContext->getAll($q);

        if(!empty($data)){
            foreach ($data as $d) {
                $ret[$d["code"]] = $d;
                /*$codeParts = substr($d["code"], 0, -3);
                $codeParts = str_split($codeParts, 2);
                $ret["classification"][$codeParts[0]]["classification"][$codeParts[1]]["products"][$d["code"]] = $d;
                $ret["classification"][$codeParts[0]]["products"][$d["code"]] = $d;*/
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getDiscountedProductsVpArray()
    {
        $q = "SELECT id
            FROM product_entity
            WHERE entity_state_id = 1 AND (
                date_discount_base_from IS NOT NULL OR
                date_discount_base_to IS NOT NULL OR (
                    discount_price_base IS NOT NULL AND
                    discount_price_base != 0
                )
            );";
        $results = $this->databaseContext->getAll($q);

        $ret = array();

        if (!empty($results)) {
            foreach ($results as $r) {
                $ret[$r["id"]] = $r;
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getDiscountedProductsMpArray()
    {
        $q = "SELECT id
            FROM product_entity
            WHERE entity_state_id = 1 AND (
                date_discount_from IS NOT NULL OR
                date_discount_to IS NOT NULL OR (
                    discount_price_retail IS NOT NULL AND
                    discount_price_retail != 0
                )
            ) AND (
                discount_type > 1 OR
                discount_type IS NULL OR
                discount_type = 0
            );";
        $results = $this->databaseContext->getAll($q);

        $ret = array();

        if (!empty($results)) {
            foreach ($results as $r) {
                $ret[$r["id"]] = $r;
            }
        }

        return $ret;
    }

    /**
     * @param $sortKey
     * @param $columnKeys
     * @param string $additionalWhere
     * @return array
     */
    public function getExistingAccounts($sortKey, $columnKeys, $additionalWhere = "")
    {
        if (!empty($columnKeys)) {
            $columnKeys = implode(",", $columnKeys);
        } else {
            $columnKeys = "*";
        }

        $q = "SELECT
                {$columnKeys}
            FROM account_entity
            WHERE entity_state_id = 1
                {$additionalWhere};";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingAccountGroups()
    {
        $q = "SELECT
                id,
                name
            FROM account_group_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d["name"]] = $d;
        }

        return $ret;
    }

    /**
     * @param $sortKey
     * @param $columnKeys
     * @param string $additionalWhere
     * @return array
     */
    public function getExistingContacts($sortKey, $columnKeys, $additionalWhere = "")
    {
        if (!empty($columnKeys)) {
            $columnKeys = implode(",", $columnKeys);
        } else {
            $columnKeys = "*";
        }

        $q = "SELECT
                {$columnKeys}
            FROM contact_entity
            WHERE entity_state_id = 1
                {$additionalWhere};";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingAddresses()
    {
        $q = "SELECT
                id,
                account_id,
                contact_id,
                first_name,
                last_name,
                street,
                remote_id,
                IFNULL(headquarters, 0) AS headquarters,
                IFNULL(billing, 0) AS billing
            FROM address_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d["remote_id"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingCities()
    {
        $q = "SELECT
                id,
                postal_code
            FROM city_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d["postal_code"]] = array("id" => $d["id"]);
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingCountries()
    {
        $q = "SELECT
                id,
                name
            FROM country_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $key = json_decode($d["name"], true);
            if (isset($key["3"])) {
                $ret[$key["3"]] = array("id" => $d["id"]);
            }
        }

        return $ret;
    }

    /**
     * @param $sortKey
     * @param array $columnKeys
     * @param false $onlyActive
     * @return array
     */
    public function getExistingProducts($sortKey, $columnKeys = array(), $onlyActive = false)
    {
        $ret = array();

        if (!empty($columnKeys)) {
            $columnKeys = implode(",", $columnKeys);
        } else {
            $columnKeys = "*";
        }

        $additionalWhere = " AND remote_source = 'wand' ";
        if ($onlyActive) {
            $additionalWhere .= " AND active = 1  ";
        }

        $q = "SELECT
                {$columnKeys}
            FROM product_entity
            WHERE entity_state_id = 1
            {$additionalWhere}
            AND {$sortKey} IS NOT NULL;";

        $data = $this->databaseContext->getAll($q);
        foreach ($data as $d) {
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingBrands()
    {
        $ret = array();

        $q = "SELECT
                id,
                wand_attribute_value_id
            FROM brand_entity
            WHERE entity_state_id = 1;";

        $data = $this->databaseContext->getAll($q);
        foreach ($data as $d) {
            $ret[$d["wand_attribute_value_id"]] = array("id" => $d["id"]);
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingSRoutes()
    {
        $ret = array();

        $q = "SELECT
                request_url,
                store_id,
                destination_type
            FROM s_route_entity
            WHERE entity_state_id = 1;";

        $data = $this->databaseContext->getAll($q);
        foreach ($data as $d) {
            $ret[$d["store_id"] . "_" . $d["request_url"]] = $d;
        }

        return $ret;
    }

    /**
     * @param $sortKey
     * @return array
     */
    public function getExistingProductGroups($sortKey)
    {
        $q = "SELECT
                id,
                name,
                url,
                product_group_id,
                remote_id,
                remote_source,
                product_group_code
            FROM product_group_entity
            WHERE entity_state_id = 1
            AND {$sortKey} IS NOT NULL
            AND {$sortKey} != '';";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    /**
     * @param $sortById
     * @return array
     */
    public function getExistingProductGroupLinks($sortById = false)
    {
        $q = "SELECT
                ppgl.id,
                ppgl.product_id,
                ppgl.product_group_id
            FROM product_product_group_link_entity ppgl
            JOIN product_group_entity pg ON ppgl.product_group_id = pg.id
            JOIN product_entity p ON ppgl.product_id = p.id
            WHERE pg.remote_id IS NOT NULL
            AND pg.remote_id != '' AND p.remote_source = 'wand';";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            if ($sortById) {
                $ret[$d["id"]] = $d;
            } else {
                $ret[$d["product_id"] . "_" . $d["product_group_id"]] = $d;
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingWandDocuments()
    {
        $q = "SELECT
                id,
                remote_id,
                type,
                class,
                number,
                DATE(date) AS date,
                work_status,
                user_status
            FROM wand_document_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d["remote_id"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingWandDocumentItems()
    {
        $q = "SELECT
                id,
                remote_id,
                product_id,
                qty,
                price_base,
                price_retail,
                DATE(date_1) AS date_1,
                DATE(date_2) AS date_2,
                info_qty_1,
                info_qty_2,
                info_qty_3,
                info_qty_4,
                info_qty_5,
                info_qty_6
            FROM wand_document_item_entity;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d["remote_id"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingOrders()
    {
        $q = "SELECT
                id
            FROM order_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d["id"]] = $d;
        }

        return $ret;
    }

    /**
     * @param $sortKey
     * @return array
     */
    public function getSProductAttributeConfigurations($sortKey)
    {
        $q = "SELECT
                id,
                {$sortKey}
            FROM s_product_attribute_configuration_entity;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getSProductAttributeConfigurationOptions()
    {
        $q = "SELECT
                id,
                remote_id
            FROM s_product_attribute_configuration_options_entity;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d["remote_id"]] = $d;
        }

        return $ret;
    }

    /**
     * @param $sortKey
     * @return array
     */
    public function getSProductAttributeLinks($sortKey)
    {
        $q = "SELECT
                id,
                wand_attribute_value_id,
                attribute_value_key
            FROM s_product_attributes_link_entity;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d[$sortKey]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getTaxTypes()
    {
        $ret = array();

        $q = "SELECT
                id,
                percent
            FROM tax_type_entity
            WHERE entity_state_id = 1;";
        $taxTypes = $this->databaseContext->getAll($q);

        foreach ($taxTypes as $taxType) {
            $ret[intval($taxType["percent"])] = $taxType;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getTaxTypesById()
    {
        $ret = array();

        $q = "SELECT
                id,
                percent
            FROM tax_type_entity
            WHERE entity_state_id = 1;";
        $taxTypes = $this->databaseContext->getAll($q);

        foreach ($taxTypes as $taxType) {
            $ret[intval($taxType["id"])] = $taxType;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getProductTypes()
    {
        $ret = array();

        $q = "SELECT id
            FROM product_type_entity
            WHERE entity_state_id = 1;";
        $productTypes = $this->databaseContext->getAll($q);

        foreach ($productTypes as $productType) {
            $ret[$productType["id"]] = $productType;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingProductImages()
    {
        $q = "SELECT
                pi.id,
                pi.filename,
                pi.file_type,
                p.remote_id
            FROM product_images_entity pi
            JOIN product_entity p ON pi.product_id = p.id
            WHERE remote_source = '{$this->getRemoteSource()}'
            AND remote_id IS NOT NULL
            AND remote_id != '';";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d["remote_id"]][$d["filename"] . "." . $d["file_type"]] = $d["id"];
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingProductDocuments()
    {
        $q = "SELECT
                pd.id,
                pd.filename,
                pd.file_type,
                p.remote_id
            FROM product_document_entity pd
            JOIN product_entity p ON pd.product_id = p.id
            WHERE remote_source = '{$this->getRemoteSource()}'
            AND remote_id IS NOT NULL
            AND remote_id != '';";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d["remote_id"]][$d["filename"] . "." . $d["file_type"]] = $d["id"];
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingProductConfigurationLinks()
    {
        $ret = array();

        $q = "SELECT
                id,
                child_product_id,
                product_id
            FROM product_configuration_product_link_entity
            WHERE entity_state_id = 1;";

        $data = $this->databaseContext->getAll($q);
        foreach ($data as $d) {
            $ret[$d["product_id"]][$d["child_product_id"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingSimpleProducts()
    {
        $ret = array();

        $productTypeId = CrmConstants::PRODUCT_TYPE_SIMPLE;

        $q = "SELECT
                id,
                remote_id,
                tax_type_id,
                short_description
            FROM product_entity
            WHERE entity_state_id = 1
            AND remote_id IS NOT NULL
            AND remote_source = '{$this->getRemoteSource()}'
            AND product_type_id = '{$productTypeId}';";

        $data = $this->databaseContext->getAll($q);
        foreach ($data as $d) {
            $ret[$d["remote_id"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingConfigurableProductsName()
    {
        $ret = array();

        $productTypeId = CrmConstants::PRODUCT_TYPE_CONFIGURABLE;

        foreach ($this->getStores() as $storeId) {

            $q = "SELECT
                id,
                name
            FROM product_entity
            WHERE entity_state_id = 1
            AND (remote_id IS NULL OR remote_id = 0)
            AND product_type_id = '{$productTypeId}'
            AND JSON_EXTRACT(show_on_store, '$.\"{$storeId}\"') = 1;";

            $data = $this->databaseContext->getAll($q);
            foreach ($data as $d) {
                $ret[$storeId][json_decode($d["name"], true)[$storeId]] = $d;
            }
        }

        return $ret;
    }

    public function getUpdatedConfigurableProducts()
    {
        $ret = array();

        $productTypeId = CrmConstants::PRODUCT_TYPE_CONFIGURABLE;

        $q = "SELECT
                id,
                name
            FROM product_entity
            WHERE entity_state_id = 1
            AND (remote_id IS NULL OR remote_id = 0)
            AND product_type_id = '{$productTypeId}';";

        $data = $this->databaseContext->getAll($q);
        foreach ($data as $d) {
            foreach ($this->getStores() as $storeId) {
                if (!isset($ret[json_decode($d["name"], true)[$storeId]])) {
                    $ret[json_decode($d["name"], true)[$storeId]] = false;
                }
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingSProductAttributeLinks()
    {
        $ret = array();

        $q = "SELECT
                product_id,
                s_product_attribute_configuration_id
            FROM s_product_attributes_link_entity
            WHERE entity_state_id = 1";

        $data = $this->databaseContext->getAll($q);

        foreach ($data as $d) {
            $ret[$d["product_id"]][] = $d["s_product_attribute_configuration_id"];
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingSProductAttributeConfigurations()
    {
        $ret = array();

        $q = "SELECT
                id,
                name,
                use_in_configurable_products
            FROM s_product_attribute_configuration_entity
            WHERE entity_state_id = 1";

        $data = $this->databaseContext->getAll($q);

        foreach ($data as $d) {
            $ret[$d["id"]] = $d;
        }

        return $ret;
    }

    public function getExistingSProductAttributeConfigurationsByRemoteId()
    {
        $ret = array();

        $q = "SELECT
                id,
                name,
                use_in_configurable_products,
                remote_id
            FROM s_product_attribute_configuration_entity
            WHERE entity_state_id = 1";

        $data = $this->databaseContext->getAll($q);

        foreach ($data as $d) {
            $ret[$d["remote_id"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingProductGroupsPerProduct()
    {
        $ret = array();

        $q = "SELECT
                    product_id,
                    product_group_id
            FROM product_product_group_link_entity
            WHERE entity_state_id = 1";

        $data = $this->databaseContext->getAll($q);

        foreach ($data as $d) {
            $ret[$d["product_id"]][] = $d["product_group_id"];
        }

        return $ret;
    }

    public function getExistingProductConfigurableAttributes()
    {
        $ret = array();

        $q = "SELECT
                    product_id,
                    s_product_attribute_configuration_id
            FROM product_configurable_attribute_entity
            WHERE entity_state_id = 1";

        $data = $this->databaseContext->getAll($q);

        foreach ($data as $d) {
            $ret[$d["product_id"] . "_" . $d["s_product_attribute_configuration_id"]][] = true;
        }

        return $ret;
    }






    //
    // 2.X
    //

    public $errors = [];

    public function addError($function, $code, $message, $data = [])
    {
        $this->errors[] = new ImportError($function, $code, $message, $data);
    }

    public function getErrors()
    {
        return $this->errors;
    }

    private $isVerbose;

    /**
     * @return bool
     */
    public function isVerbose()
    {
        return $this->isVerbose;
    }

    /**
     * @param bool $isVerbose
     */
    public function setIsVerbose(bool $isVerbose)
    {
        $this->isVerbose = $isVerbose;
    }

    /**
     * @param string $string
     */
    public function echo(string $string)
    {
        if ($this->isVerbose()) {
            printf($string);
        }
    }

    const SKIP_NATURAL_PERSONS_NONE = 0; // Ne preskačemo fizičke osobe
    const SKIP_NATURAL_PERSONS_SELECTED = 1; // Preskačemo odabrane fizičke osobe
    const SKIP_NATURAL_PERSONS_ALL_EXCEPT_SELECTED = 2; // Preskačemo sve fizičke osobe osim odabranih

    const ACCOUNT_SELECT_FIELDS = [
        "id",
        "name",
        "description",
        "phone",
        "fax",
        "LOWER(email) AS email",
        "oib",
        "remote_id",
        "remote_id_2",
        "disable_discounts",
        "disable_rebate",
        "is_active",
        "is_legal_entity",
        "account_group_id"
    ];

    /**
     * @param int $startPage
     * @param int $fromId
     * @return array
     * @throws \Exception
     */
    public function importPartneri($startPage = 0, $fromId = 0)
    {
        $this->echo("Starting import partneri...\n");

        $data = $this->getWandPartneri();

        $existingCountries = $this->getExistingWandCountries();
        $existingAccounts = $this->getExistingWandAccounts();
        $existingAccountGroups = $this->getExistingWandAccountGroups();
        $existingAccountsByEmail = $this->getExistingWandAccountsByEmail();
        $existingAccountsByOib = $this->getExistingWandAccountsByOib();
        $existingCities = $this->getExistingWandCities();
        $existingAddresses = $this->getExistingWandAddresses();

        $insertArray = [
            // city_entity
            // account_group_entity
        ];
        $insertArray2 = [
            // account_entity
        ];
        $insertArray3 = [
            // address_entity
            // account_type_link_entity
        ];
        $updateArray = [
            // account_entity
        ];
        $updateArray2 = [
            // address_entity
        ];

        $usedAccountEmails = [];
        $usedAccountOibs = [];

        foreach ($data as $d) {

            $remoteId = $d["partnerID"];
            if ($this->skipNaturalPersons == self::SKIP_NATURAL_PERSONS_SELECTED && in_array($remoteId, $this->naturalPersons)) {
                continue;
            }

            $cityName = trim($d["mjesto"]);
            if (empty($cityName)) {
                $this->addError("importPartneri", $remoteId, $this->translator->trans("mjesto is empty"));
                continue;
            }

            $postalCode = trim($d["postBroj"]);
            if (empty($postalCode)) {
                $this->addError("importPartneri", $remoteId, $this->translator->trans("postBroj is empty"));
                continue;
            }

            $countryName = trim($d["drzava"]);
            if (empty($countryName)) {
                $this->addError("importPartneri", $remoteId, $this->translator->trans("drzava is empty"));
                continue;
            }

            $cityNameLower = mb_strtolower($cityName);
            $countryNameLower = mb_strtolower($countryName);
            if (!isset($existingCountries[$countryNameLower])) {
                $this->addError("importPartneri", $remoteId, sprintf($this->translator->trans("Country %s was not found"), $countryName));
                continue;
            }

            $parentRemoteId = $d["centralaID"];
            $accountGroupName = trim($d["partnerGrupa"]);
            $name = trim($d["naziv"]);
            $description = trim($d["opis"]);
            $address = trim($d["adresa"]);
            $phone = trim($d["telefon"]);
            $fax = trim($d["fax"]);
            $email = strtolower(trim($d["eMail"]));
            $disableDiscounts = $d["zanemariAkciju"];
            $disableRebate = $d["zanemariRabatnu"];
            $isActive = true;
            if (!empty($this->inactiveAccounts)) {
                $isActive = !in_array($remoteId, $this->inactiveAccounts);
            }

            $cityCode = $cityNameLower . "_" . $postalCode . "_" . $countryNameLower;
            if (!isset($existingCities[$cityCode])) {

                $cityInsert = new InsertModel($this->asCity);
                $cityInsert->add("name", $cityName)
                    ->add("postal_code", $postalCode)
                    ->add("country_id", $existingCountries[$countryNameLower]["id"]);

                $insertArray["city_entity"][$cityCode] = $cityInsert->getArray();
            }

            if (!empty($parentRemoteId)) {

                if (empty($this->importAllAddresses)) {
                    continue;
                }

                $remoteId = $parentRemoteId;

                if (!isset($existingAddresses[$remoteId])) {

                    $addressInsert = new InsertModel($this->asAddress);
                    $addressInsert->add("remote_id", $remoteId)
                        ->add("street", $address)
                        ->add("account_id", null)
                        ->add("headquarters", false)
                        ->add("billing", false);

                    if (!isset($existingAccounts[$remoteId])) {
                        $addressInsert->addLookup("account_id", $remoteId, "account_entity");
                    } else {
                        $addressInsert->add("account_id", $existingAccounts[$remoteId]["id"]);
                    }

                    if (!isset($existingCities[$cityCode])) {
                        $addressInsert->addLookup("city_id", $cityCode, "city_entity");
                    } else {
                        $addressInsert->add("city_id", $existingCities[$cityCode]["id"]);
                    }

                    $insertArray3["address_entity"][$remoteId] = $addressInsert;

                } else {

                    $addressUpdate = new UpdateModel($existingAddresses[$remoteId]);
                    $addressUpdate->add("street", $address);

                    if (!isset($existingCities[$cityCode])) {
                        $addressUpdate->addLookup("city_id", $cityCode, "city_entity");
                    } else {
                        $addressUpdate->add("city_id", $existingCities[$cityCode]["id"]);
                    }

                    if (!empty($addressUpdate->getArray())) {
                        $updateArray2["address_entity"][$addressUpdate->getEntityId()] = $addressUpdate;
                    }
                }

            } else {

                $oib = trim($d["oib"]);
                if (empty($oib)) {
                    $this->addError("importPartneri", $remoteId, $this->translator->trans("oib is empty"));
                    continue;
                }

                $skipAccountGroup = true;
                if (!empty($accountGroupName)) {
                    $skipAccountGroup = false;
                    if (!isset($existingAccountGroups[$accountGroupName]) && !isset($insertArray["account_group_entity"][$accountGroupName])) {
                        $accountGroupInsert = new InsertModel($this->asAccountGroup);
                        $accountGroupInsert->add("name", $accountGroupName);
                        $insertArray["account_group_entity"][$accountGroupName] = $accountGroupInsert->getArray();
                    }
                }

                if ((isset($existingAccountsByOib[$oib]) && !empty($existingAccountsByOib[$oib]["remote_id"]) && $existingAccountsByOib[$oib]["remote_id"] != $remoteId) || in_array($oib, $usedAccountOibs)) {
                    $this->addError("importPartneri", $remoteId, sprintf($this->translator->trans("Account with oib %s already exists"), $oib));
                    continue;
                }
                if (!empty($email) && ((isset($existingAccountsByEmail[$email]) && $existingAccountsByEmail[$email]["remote_id"] != $remoteId) || in_array($email, $usedAccountEmails))) {
                    $this->addError("importPartneri", $remoteId, sprintf($this->translator->trans("Account with email %s already exists"), $email));
                    continue;
                }

                if (!isset($existingAccounts[$remoteId])) {

                    if (isset($existingAccountsByOib[$oib]) && empty($existingAccountsByOib[$oib]["remote_id"])) {

                        $accountUpdate = new UpdateModel($existingAccountsByOib[$oib]);
                        $accountUpdate->add("remote_id", $remoteId)
                            ->add("name", $name)
                            ->add("description", $description)
                            ->add("phone", $phone)
                            ->add("fax", $fax)
                            ->add("email", $email)
                            ->add("disable_discounts", $disableDiscounts)
                            ->add("disable_rebate", $disableRebate)
                            ->add("is_active", $isActive);

                        if (!$skipAccountGroup) {
                            if (!isset($existingAccountGroups[$accountGroupName])) {
                                $accountUpdate->addLookup("account_group_id", $accountGroupName, "account_group_entity");
                            } else {
                                $accountUpdate->add("account_group_id", $existingAccountGroups[$accountGroupName]["id"]);
                            }
                        }

                        $accountUpdateArray = $accountUpdate->getArray();
                        if (!empty($accountUpdateArray)) {
                            $updateArray["account_entity"][$accountUpdate->getEntityId()] = $accountUpdate;
                            if (isset($accountUpdateArray["email"])) {
                                $usedAccountEmails[] = $email;
                            }
                        }

                        $usedAccountOibs[] = $oib;

                    } else {

                        $accountTypeLinkInsert = new InsertModel($this->asAccountTypeLink);
                        $accountTypeLinkInsert->add("account_type_id", CrmConstants::ACCOUNT_TYPE_CUSTOMER)
                            ->addLookup("account_id", $remoteId, "account_entity");

                        $accountInsert = new InsertModel($this->asAccount);
                        $accountInsert->add("remote_id", $remoteId)
                            ->add("name", $name)
                            ->add("description", $description)
                            ->add("phone", $phone)
                            ->add("fax", $fax)
                            ->add("email", $email)
                            ->add("oib", $oib)
                            ->add("disable_discounts", $disableDiscounts)
                            ->add("disable_rebate", $disableRebate)
                            ->add("is_active", $isActive)
                            ->add("is_legal_entity", true)
                            ->add("account_group_id", null);

                        if (!$skipAccountGroup) {
                            if (!isset($existingAccountGroups[$accountGroupName])) {
                                $accountInsert->addLookup("account_group_id", $accountGroupName, "account_group_entity");
                            } else {
                                $accountInsert->add("account_group_id", $existingAccountGroups[$accountGroupName]["id"]);
                            }
                        }

                        $insertArray2["account_entity"][$remoteId] = $accountInsert;
                        $insertArray3["account_type_link_entity"][$remoteId] = $accountTypeLinkInsert;
                        $usedAccountEmails[] = $email;
                        $usedAccountOibs[] = $oib;
                    }

                } else {

                    $accountUpdate = new UpdateModel($existingAccounts[$remoteId]);
                    $accountUpdate->add("name", $name)
                        ->add("description", $description)
                        ->add("phone", $phone)
                        ->add("fax", $fax)
                        ->add("email", $email)
                        ->add("oib", $oib)
                        ->add("disable_discounts", $disableDiscounts)
                        ->add("disable_rebate", $disableRebate)
                        ->add("is_active", $isActive);

                    if (!$skipAccountGroup) {
                        if (!isset($existingAccountGroups[$accountGroupName])) {
                            $accountUpdate->addLookup("account_group_id", $accountGroupName, "account_group_entity");
                        } else {
                            $accountUpdate->add("account_group_id", $existingAccountGroups[$accountGroupName]["id"]);
                        }
                    }

                    $accountUpdateArray = $accountUpdate->getArray();
                    if (!empty($accountUpdateArray)) {
                        $updateArray["account_entity"][$accountUpdate->getEntityId()] = $accountUpdate;
                        if (isset($accountUpdateArray["email"])) {
                            $usedAccountEmails[] = $email;
                        }
                        if (isset($accountUpdateArray["oib"])) {
                            $usedAccountOibs[] = $oib;
                        }
                    }
                }

                if (!isset($existingAddresses[$remoteId])) {

                    $addressInsert = new InsertModel($this->asAddress);
                    $addressInsert->add("remote_id", $remoteId)
                        ->add("street", $address)
                        ->add("account_id", null)
                        ->add("headquarters", true)
                        ->add("billing", true);

                    if (!isset($existingAccounts[$remoteId])) {
                        $addressInsert->addLookup("account_id", $remoteId, "account_entity");
                    } else {
                        $addressInsert->add("account_id", $existingAccounts[$remoteId]["id"]);
                    }

                    if (!isset($existingCities[$cityCode])) {
                        $addressInsert->addLookup("city_id", $cityCode, "city_entity");
                    } else {
                        $addressInsert->add("city_id", $existingCities[$cityCode]["id"]);
                    }

                    $insertArray3["address_entity"][$remoteId] = $addressInsert;

                } else {

                    $addressUpdate = new UpdateModel($existingAddresses[$remoteId]);
                    $addressUpdate->add("street", $address);

                    if (!isset($existingCities[$cityCode])) {
                        $addressUpdate->addLookup("city_id", $cityCode, "city_entity");
                    } else {
                        $addressUpdate->add("city_id", $existingCities[$cityCode]["id"]);
                    }

                    if (!empty($addressUpdate->getArray())) {
                        $updateArray2["address_entity"][$addressUpdate->getEntityId()] = $addressUpdate;
                    }
                }
            }
        }

        unset($data);
        unset($existingCountries);
        unset($existingAccounts);
        unset($existingAccountGroups);
        unset($existingAccountsByEmail);
        unset($existingAccountsByOib);
        unset($existingCities);
        unset($existingAddresses);

        $reselectArray = [];

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $reselectArray["account_group_entity"] = $this->getExistingWandAccountGroups();
        $reselectArray["city_entity"] = $this->getExistingWandCities();

        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);

        $updateArray = $this->resolveImportArray($updateArray, $reselectArray);
        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $reselectArray["account_entity"] = $this->getExistingWandAccounts();

        $insertArray3 = $this->resolveImportArray($insertArray3, $reselectArray);
        $this->executeInsertQuery($insertArray3);
        unset($insertArray3);

        $updateArray2 = $this->resolveImportArray($updateArray2, $reselectArray);
        $this->executeUpdateQuery($updateArray2);
        unset($updateArray2);

        unset($reselectArray);

        $this->echo("Import partneri complete\n");

        return [];
    }

    /**
     * @param int $startPage
     * @param int $fromId
     * @return array
     * @throws \Exception
     */
    public function importOsobe($startPage = 0, $fromId = 0)
    {
        $this->echo("Starting import osobe...\n");

        $data = $this->getWandOsobe();

        $existingCountries = $this->getExistingWandCountries();
        $existingContacts = $this->getExistingWandContacts();
        $existingContactsByEmail = $this->getExistingWandContactsByEmail();
        $existingAccounts = $this->getExistingWandAccounts();
        $existingAccountsByRemoteId2 = $this->getExistingWandAccountsByRemoteId2();
        $existingAccountsByEmail = $this->getExistingWandAccountsByEmail();
        $existingCities = $this->getExistingWandCities();
        $existingAddressesByRemoteId2 = $this->getExistingWandAddressesByRemoteId2();
        $existingLoyaltyCards = $this->getExistingWandLoyaltyCards();

        $insertArray = [
            // loyalty_card_entity
            // account_entity
            // city_entity
        ];
        $updateArray = [
            // loyalty_card_entity
            // account_entity
        ];
        $insertArray2a = [
            // contact_entity
        ];
        $insertArray2b = [
            // account_type_link_entity
            // contact_entity
        ];
        $updateArray2 = [
            // contact_entity
        ];
        $insertArray3 = [
            // address_entity
        ];
        $updateArray3 = [
            // address_entity
        ];

        $usedAccountEmails = [];
        $usedContactEmails = [];

        foreach ($data as $d) {

            $remoteId = $d["osobaID"];
            $partnerId = $d["partnerID"];
            if (empty($partnerId)) {
                $this->addError("importOsobe", $remoteId, $this->translator->trans("partnerID is empty"));
                continue;
            }

            $partnerCentralaId = $d["partnerCentralaID"];
            $isInNaturalPersons = in_array($partnerId, $this->naturalPersons);

            if ($this->skipNaturalPersons == self::SKIP_NATURAL_PERSONS_SELECTED && $isInNaturalPersons) {
                continue;
            }
            if ($this->skipNaturalPersons == self::SKIP_NATURAL_PERSONS_ALL_EXCEPT_SELECTED && !$isInNaturalPersons) {
                continue;
            }

            $firstName = trim($d["ime"]);
            if (strlen($firstName) < 2) {
                $this->addError("importOsobe", $remoteId, $this->translator->trans("ime is shorter than 2 characters"));
                continue;
            }
            $lastName = trim($d["prezime"]);
            if (strlen($lastName) < 2) {
                $this->addError("importOsobe", $remoteId, $this->translator->trans("prezime is shorter than 2 characters"));
                continue;
            }
            $fullName = $firstName . " " . $lastName;

            $email = strtolower(trim($d["eMail"]));
            if (empty($email)) {
                continue;
            }

            $password = trim($d["lozinka"]);
            $address = trim($d["adresa"]);
            $postalCode = trim($d["postBroj"]);
            $cityName = trim($d["mjesto"]);
            $countryName = trim($d["drzava"]);
            $phone = trim($d["telefon"]);
            $fax = trim($d["fax"]);
            $oib = trim($d["oib"]);
            $loyaltyCardNumber = $d["loyaltyKartica"];
            $loyaltyCardPoints = $d["bodoviStanje"];
            if ($loyaltyCardPoints < 0) {
                $loyaltyCardPoints = 0;
            }
            $isActiveAccount = true;
            if (!empty($this->inactiveAccounts)) {
                $isActiveAccount = !in_array($remoteId, $this->inactiveAccounts);
            }
            $isActiveContact = true;
            if (!empty($this->inactiveContacts)) {
                $isActiveContact = !in_array($remoteId, $this->inactiveContacts);
            }

            if (!empty($loyaltyCardNumber) && !empty($password)) {
                if (!isset($existingLoyaltyCards[$loyaltyCardNumber])) {
                    $loyaltyCardInsert = new InsertModel($this->asLoyaltyCard);
                    $loyaltyCardInsert->add("card_number", $loyaltyCardNumber)
                        ->add("points", $loyaltyCardPoints);

                    $insertArray["loyalty_card_entity"][$loyaltyCardNumber] = $loyaltyCardInsert->getArray();
                } else {
                    $loyaltyCardUpdate = new UpdateModel($existingLoyaltyCards[$loyaltyCardNumber]);
                    $loyaltyCardUpdate->add("points", $loyaltyCardPoints);
                    if (!empty($loyaltyCardUpdate->getArray())) {
                        $updateArray["loyalty_card_entity"][$loyaltyCardUpdate->getEntityId()] = $loyaltyCardUpdate->getArray();
                    }
                }
            }

            if ((isset($existingContactsByEmail[$email]) && !empty($existingContactsByEmail[$email]["remote_id"]) && $existingContactsByEmail[$email]["remote_id"] != $remoteId) || in_array($email, $usedContactEmails)) {
                $this->addError("importOsobe", $remoteId, sprintf($this->translator->trans("Contact with email %s already exists"), $email));
                continue;
            }

            if ($isInNaturalPersons) {

                if ((isset($existingAccountsByEmail[$email]) && !empty($existingAccountsByEmail[$email]["remote_id_2"]) && $existingAccountsByEmail[$email]["remote_id_2"] != $remoteId) || in_array($email, $usedAccountEmails)) {
                    $this->addError("importOsobe", $remoteId, sprintf($this->translator->trans("Account with email %s already exists"), $email));
                    continue;
                }

                if (empty($cityName)) {
                    $this->addError("importOsobe", $remoteId, $this->translator->trans("mjesto is empty"));
                    continue;
                }
                if (empty($postalCode)) {
                    $this->addError("importOsobe", $remoteId, $this->translator->trans("postBroj is empty"));
                    continue;
                }
                if (empty($countryName)) {
                    $this->addError("importOsobe", $remoteId, $this->translator->trans("drzava is empty"));
                    continue;
                }

                $cityNameLower = mb_strtolower($cityName);
                $countryNameLower = mb_strtolower($countryName);
                if (!isset($existingCountries[$countryNameLower])) {
                    $this->addError("importOsobe", $remoteId, sprintf($this->translator->trans("Country %s was not found"), $countryName));
                    continue;
                }

                $cityCode = $cityNameLower . "_" . $postalCode . "_" . $countryNameLower;
                if (!isset($existingCities[$cityCode])) {

                    $cityInsert = new InsertModel($this->asCity);
                    $cityInsert->add("name", $cityName)
                        ->add("postal_code", $postalCode)
                        ->add("country_id", $existingCountries[$countryNameLower]["id"]);

                    $insertArray["city_entity"][$cityCode] = $cityInsert->getArray();
                }

                if (!isset($existingAccountsByRemoteId2[$remoteId])) {

                    if (isset($existingAccountsByEmail[$email]) && empty($existingAccountsByEmail[$email]["remote_id_2"])) {

                        $accountUpdate = new UpdateModel($existingAccountsByEmail[$email]);
                        $accountUpdate->add("remote_id_2", $remoteId)
                            ->add("name", $fullName)
                            ->add("phone", $phone)
                            //->add("oib", $oib)
                            ->add("fax", $fax)
                            ->add("is_active", $isActiveAccount)
                            ->add("is_legal_entity", false);

                        if (!empty($accountUpdate->getArray())) {
                            $updateArray["account_entity"][$accountUpdate->getEntityId()] = $accountUpdate->getArray();
                        }

                        $usedAccountEmails[] = $email;

                    } else {

                        if (!empty($password)) {

                            $accountTypeLinkInsert = new InsertModel($this->asAccountTypeLink);
                            $accountTypeLinkInsert->add("account_type_id", CrmConstants::ACCOUNT_TYPE_CUSTOMER)
                                ->addLookup("account_id", $remoteId, "account_entity");

                            $accountInsert = new InsertModel($this->asAccount);
                            $accountInsert->add("remote_id_2", $remoteId)
                                ->add("name", $fullName)
                                ->add("phone", $phone)
                                //->add("oib", $oib)
                                ->add("fax", $fax)
                                ->add("email", $email)
                                ->add("is_active", $isActiveAccount)
                                ->add("is_legal_entity", false);

                            $insertArray["account_entity"][$remoteId] = $accountInsert->getArray();
                            $insertArray2b["account_type_link_entity"][$remoteId] = $accountTypeLinkInsert;
                            $usedAccountEmails[] = $email;
                        }
                    }

                } else {

                    $accountUpdate = new UpdateModel($existingAccountsByRemoteId2[$remoteId]);
                    $accountUpdate->add("name", $fullName)
                        ->add("phone", $phone)
                        //->add("oib", $oib)
                        ->add("fax", $fax)
                        ->add("email", $email)
                        ->add("is_active", $isActiveAccount)
                        ->add("is_legal_entity", false);

                    $accountUpdateArray = $accountUpdate->getArray();
                    if (!empty($accountUpdateArray)) {
                        $updateArray["account_entity"][$accountUpdate->getEntityId()] = $accountUpdateArray;
                        if (isset($accountUpdateArray["email"])) {
                            $usedAccountEmails[] = $email;
                        }
                    }
                }

                if (!isset($existingAddressesByRemoteId2[$remoteId])) {

                    if (!empty($password)) {

                        $addressInsert = new InsertModel($this->asAddress);
                        $addressInsert->add("remote_id_2", $remoteId)
                            ->add("street", $address)
                            ->add("headquarters", true)
                            ->add("billing", true);

                        if (!isset($existingAccountsByRemoteId2[$remoteId])) {
                            $addressInsert->addLookup("account_id", $remoteId, "account_entity");
                        } else {
                            $addressInsert->add("account_id", $existingAccountsByRemoteId2[$remoteId]["id"]);
                        }

//                    if (!isset($existingContacts[$remoteId])) {
//                        $addressInsert->addLookup("contact_id", $remoteId, "contact_entity");
//                    } else {
//                        $addressInsert->add("contact_id", $existingContacts[$remoteId]["id"]);
//                    }

                        if (!isset($existingCities[$cityCode])) {
                            $addressInsert->addLookup("city_id", $cityCode, "city_entity");
                        } else {
                            $addressInsert->add("city_id", $existingCities[$cityCode]["id"]);
                        }

                        $insertArray3["address_entity"][$remoteId] = $addressInsert;
                    }

                } else {

                    $addressUpdate = new UpdateModel($existingAddressesByRemoteId2[$remoteId]);
                    $addressUpdate->add("street", $address);

                    if (!isset($existingCities[$cityCode])) {
                        $addressUpdate->addLookup("city_id", $cityCode, "city_entity");
                    } else {
                        $addressUpdate->add("city_id", $existingCities[$cityCode]["id"]);
                    }

                    if (!empty($addressUpdate->getArray())) {
                        $updateArray3["address_entity"][$addressUpdate->getEntityId()] = $addressUpdate;
                    }
                }

            } else {

                if (!empty($partnerCentralaId)) {
                    $partnerId = $partnerCentralaId;
                }
            }

            if (!isset($existingContacts[$remoteId])) {

                if (isset($existingContactsByEmail[$email]) && empty($existingContactsByEmail[$email]["remote_id"])) {

                    $contactUpdate = new UpdateModel($existingContactsByEmail[$email]);
                    $contactUpdate->add("remote_id", $remoteId)
                        ->add("first_name", $firstName)
                        ->add("last_name", $lastName)
                        ->add("full_name", $fullName)
                        ->add("phone", $phone)
                        ->add("fax", $fax)
                        ->add("is_active", $isActiveContact);

                    if (!empty($loyaltyCardNumber) && !empty($password)) {
                        if (!isset($existingLoyaltyCards[$loyaltyCardNumber])) {
                            $contactUpdate->addLookup("loyalty_card_id", $loyaltyCardNumber, "loyalty_card_entity");
                        } else {
                            $contactUpdate->add("loyalty_card_id", $existingLoyaltyCards[$loyaltyCardNumber]["id"]);
                        }
                    }

                    if (!empty($contactUpdate->getArray())) {
                        $updateArray2["contact_entity"][$contactUpdate->getEntityId()] = $contactUpdate;
                    }

                    $usedContactEmails[] = $email;

                } else {

                    if (!empty($password)) {

                        $contactInsert = new InsertModel($this->asContact);
                        $contactInsert->add("remote_id", $remoteId)
                            ->add("password", $password)
                            ->add("first_name", $firstName)
                            ->add("last_name", $lastName)
                            ->add("full_name", $fullName)
                            ->add("phone", $phone)
                            ->add("fax", $fax)
                            ->add("email", $email)
                            ->add("is_active", $isActiveContact)
                            ->add("loyalty_card_id", null);

                        if (!empty($loyaltyCardNumber)) {
                            if (!isset($existingLoyaltyCards[$loyaltyCardNumber])) {
                                $contactInsert->addLookup("loyalty_card_id", $loyaltyCardNumber, "loyalty_card_entity");
                            } else {
                                $contactInsert->add("loyalty_card_id", $existingLoyaltyCards[$loyaltyCardNumber]["id"]);
                            }
                        }

                        if (!$isInNaturalPersons) {
                            if (!isset($existingAccounts[$partnerId])) {
                                $contactInsert->addLookup("account_id", $partnerId, "account_entity");
                            } else {
                                $contactInsert->add("account_id", $existingAccounts[$partnerId]["id"]);
                            }
                            $insertArray2a["contact_entity"][$remoteId] = $contactInsert;
                        } else {
                            if (!isset($existingAccountsByRemoteId2[$remoteId])) {
                                $contactInsert->addLookup("account_id", $remoteId, "account_entity");
                            } else {
                                $contactInsert->add("account_id", $existingAccountsByRemoteId2[$remoteId]["id"]);
                            }
                            $insertArray2b["contact_entity"][$remoteId] = $contactInsert;
                        }

                        $usedContactEmails[] = $email;
                    }
                }

            } else {

                $contactUpdate = new UpdateModel($existingContacts[$remoteId]);
                $contactUpdate->add("first_name", $firstName)
                    ->add("last_name", $lastName)
                    ->add("full_name", $fullName)
                    ->add("phone", $phone)
                    ->add("fax", $fax)
                    ->add("email", $email)
                    ->add("is_active", $isActiveContact);

                if (!empty($loyaltyCardNumber) && !empty($password)) {
                    if (!isset($existingLoyaltyCards[$loyaltyCardNumber])) {
                        $contactUpdate->addLookup("loyalty_card_id", $loyaltyCardNumber, "loyalty_card_entity");
                    } else {
                        $contactUpdate->add("loyalty_card_id", $existingLoyaltyCards[$loyaltyCardNumber]["id"]);
                    }
                }

                $contactUpdateArray = $contactUpdate->getArray();
                if (!empty($contactUpdateArray)) {
                    $updateArray2["contact_entity"][$contactUpdate->getEntityId()] = $contactUpdate;
                    if (isset($contactUpdateArray["email"])) {
                        $usedContactEmails[] = $email;
                    }
                }
            }
        }

        unset($data);
        unset($existingCountries);
        unset($existingContacts);
        unset($existingContactsByEmail);
        unset($existingAccounts);
        unset($existingAccountsByRemoteId2);
        unset($existingAccountsByEmail);
        unset($existingCities);
        unset($existingAddressesByRemoteId2);
        unset($existingLoyaltyCards);

        $reselectArray = [];

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $reselectArray["account_entity"] = $this->getExistingWandAccounts();
        $reselectArray["loyalty_card_entity"] = $this->getExistingWandLoyaltyCards();
        $reselectArray["city_entity"] = $this->getExistingWandCities();

        $insertArray2a = $this->resolveImportArray($insertArray2a, $reselectArray);
        $this->executeInsertQuery($insertArray2a);
        unset($insertArray2a);

        $reselectArray["account_entity"] = $this->getExistingWandAccountsByRemoteId2();

        $insertArray2b = $this->resolveImportArray($insertArray2b, $reselectArray);
        $this->executeInsertQuery($insertArray2b);
        unset($insertArray2b);

        $updateArray2 = $this->resolveImportArray($updateArray2, $reselectArray);
        $this->executeUpdateQuery($updateArray2);
        unset($updateArray2);

        $reselectArray["contact_entity"] = $this->getExistingWandContacts();

        $insertArray3 = $this->resolveImportArray($insertArray3, $reselectArray);
        $this->executeInsertQuery($insertArray3);
        unset($insertArray3);

        $updateArray3 = $this->resolveImportArray($updateArray3, $reselectArray);
        $this->executeUpdateQuery($updateArray3);
        unset($updateArray3);

        unset($reselectArray);

        $this->echo("Import osobe complete\n");

        return [];
    }

    /**
     * @return array
     */
    public function generateCoreUsers()
    {
        $existingContacts = $this->getContactsWithoutUsers();

        if (!empty($existingContacts)) {

            $existingUsersByEmail = $this->getExistingWandUsersByEmail();

            $session = $this->container->get("session");
            $session->set("current_store_id", $this->defaultStoreId);

            if (empty($this->accountManager)) {
                $this->accountManager = $this->container->get("account_manager");
            }

            $updateArray = [
                // contact_entity
            ];

            foreach ($existingContacts as $existingContact) {

                $contactId = $existingContact["id"];
                unset($existingContact["id"]);

                $remoteId = $existingContact["remote_id"];
                unset($existingContact["remote_id"]);

                $this->echo(sprintf("Generating core user for contact %u\n", $contactId));

                if (isset($existingUsersByEmail[$existingContact["email"]])) {
                    $this->addError("generateCoreUsers", $remoteId, $this->translator->trans("Core user with this email already exists"));
                    continue;
                }

                $ret = $this->accountManager->createUser($existingContact, true, true);
                if (isset($ret["error"]) && !empty($ret["error"])) {
                    $this->addError("generateCoreUsers", $remoteId, $this->translator->trans("Error generating core user"));
                    continue;
                }
                if (!isset($ret["core_user"]) || empty($ret["core_user"])) {
                    $this->addError("generateCoreUsers", $remoteId, $this->translator->trans("Error generating core user"));
                    continue;
                }

                // Clear password after generating user_entity and set core_user_id
                $updateArray["contact_entity"][$contactId] = [
                    "password" => null,
                    "core_user_id" => $ret["core_user"]->getId()
                ];
            }

            unset($existingUsersByEmail);
            unset($existingContacts);

            $this->executeUpdateQuery($updateArray);
            unset($updateArray);
        }

        return [];
    }

    /**
     * @return array
     */
    private function getExistingWandAccounts()
    {
        return $this->getEntitiesArray(self::ACCOUNT_SELECT_FIELDS, "account_entity", ["remote_id"], "", "WHERE remote_id IS NOT NULL AND remote_id != '' AND (remote_id_2 IS NULL OR remote_id_2 = '')");
    }

    /**
     * @return array
     */
    private function getExistingWandAccountsByRemoteId2()
    {
        return $this->getEntitiesArray(self::ACCOUNT_SELECT_FIELDS, "account_entity", ["remote_id_2"], "", "WHERE remote_id_2 IS NOT NULL AND remote_id_2 != '' AND (remote_id IS NULL OR remote_id = '')");
    }

    /**
     * @return array
     */
    private function getExistingWandAccountsByEmail()
    {
        return $this->getEntitiesArray(self::ACCOUNT_SELECT_FIELDS, "account_entity", ["email"], "", "WHERE email IS NOT NULL AND email != ''");
    }

    /**
     * @return array
     */
    private function getExistingWandAccountsByOib()
    {
        return $this->getEntitiesArray(self::ACCOUNT_SELECT_FIELDS, "account_entity", ["oib"], "", "WHERE oib IS NOT NULL AND oib != ''");
    }

    /**
     * @return array
     */
    private function getExistingWandContacts()
    {
        return $this->getEntitiesArray([
            "id",
            "first_name",
            "last_name",
            "full_name",
            "phone",
            "fax",
            "LOWER(email) AS email",
            "is_active",
            "remote_id",
            "loyalty_card_id"
        ], "contact_entity", ["remote_id"], "", "WHERE remote_id IS NOT NULL AND remote_id != ''");
    }

    /**
     * @return array
     */
    private function getExistingWandContactsByEmail()
    {
        return $this->getEntitiesArray([
            "id",
            "first_name",
            "last_name",
            "full_name",
            "phone",
            "fax",
            "LOWER(email) AS email",
            "is_active",
            "remote_id",
            "loyalty_card_id"
        ], "contact_entity", ["email"], "", "WHERE email IS NOT NULL AND email != ''");
    }

    /**
     * @return array
     */
    private function getExistingWandUsersByEmail()
    {
        return $this->getEntitiesArray(["id", "email"], "user_entity", ["email"]);
    }

    /**
     * @return array
     */
    private function getContactsWithoutUsers()
    {
        $q = "SELECT
                c.id,
                a.is_legal_entity,
                c.first_name,
                c.last_name,
                c.password,
                c.email,
                c.email AS username,
                c.remote_id
            FROM contact_entity c
            JOIN account_entity a ON c.account_id = a.id
            WHERE c.remote_id IS NOT NULL
            AND c.remote_id != ''
            AND c.is_active = 1
            AND c.core_user_id IS NULL
            AND c.email IS NOT NULL
            AND c.email != ''
            AND c.password IS NOT NULL
            AND c.password != ''
            AND c.entity_state_id = 1;";

        return $this->databaseContext->getAll($q);
    }

    /**
     * @return array
     */
    private function getExistingWandAddresses()
    {
        return $this->getEntitiesArray([
            "id",
            "street",
            "city_id",
            "remote_id"
        ], "address_entity", ["remote_id"], "", "WHERE remote_id IS NOT NULL AND remote_id != ''");
    }

    /**
     * @return array
     */
    private function getExistingWandAddressesByRemoteId2()
    {
        return $this->getEntitiesArray([
            "id",
            "street",
            "city_id",
            "remote_id_2"
        ], "address_entity", ["remote_id_2"], "", "WHERE remote_id_2 IS NOT NULL AND remote_id_2 != ''");
    }

    /**
     * @return array
     */
    private function getExistingWandAccountGroups()
    {
        return $this->getEntitiesArray([
            "id",
            "name"
        ], "account_group_entity", ["name"]);
    }

    /**
     * @return array
     */
    private function getExistingWandCities()
    {
        $ret = [];

        $q = "SELECT
                c.id,
                c.name,
                c.postal_code,
                c2.name AS country_name
            FROM city_entity c
            JOIN country_entity c2 ON c.country_id = c2.id
            WHERE c2.entity_state_id = 1;";

        $existingCities = $this->databaseContext->getAll($q);

        foreach ($existingCities as $existingCity) {
            $countryNameArray = json_decode($existingCity["country_name"], JSON_UNESCAPED_UNICODE);
            if (isset($countryNameArray[3]) && !empty($countryNameArray[3])) {
                $ret[mb_strtolower($existingCity["name"]) . "_" . $existingCity["postal_code"] . "_" . mb_strtolower($countryNameArray[3])] = $existingCity;
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    private function getExistingWandLoyaltyCards()
    {
        return $this->getEntitiesArray([
            "id",
            "points",
            "card_number"
        ], "loyalty_card_entity", ["card_number"], "", "WHERE card_number IS NOT NULL AND card_number != ''");
    }

    /**
     * @return array
     */
    private function getExistingWandCountries()
    {
        $ret = [];

        $existingCountries = $this->getEntitiesArray(["id", "name"], "country_entity", ["name"], "", "WHERE entity_state_id = 1");

        foreach ($existingCountries as $nameJson => $existingCountry) {
            $nameArray = json_decode($nameJson, JSON_UNESCAPED_UNICODE);
            if (isset($nameArray[3]) && !empty($nameArray[3])) {
                $ret[mb_strtolower($nameArray[3])] = $existingCountry;
            }
        }

        return $ret;
    }
}
