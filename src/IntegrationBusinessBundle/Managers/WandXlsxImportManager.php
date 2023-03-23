<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\FileHelper;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Abstracts\AbstractImportManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\TaxTypeEntity;
use DateTime;
use Doctrine\ORM\Mapping\AttributeOverride;
use phpDocumentor\Reflection\DocBlock\Tags\Formatter\AlignFormatter;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Entity\STemplateTypeEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Managers\SitemapManager;
use ScommerceBusinessBundle\Managers\SproductManager;
use ScommerceBusinessBundle\Managers\TemplateManager;
use DomDocument;

class WandXlsxImportManager extends AbstractImportManager
{
    const DELIMITER = '##';
    const DELIMITER_TAB = "\t";

    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var STemplateTypeEntity $productTemplate */
    protected $productTemplate;

    /** @var AttributeSet $asAccount */
    protected $asAccount;
    /** @var AttributeSet $asAccountGroup */
    protected $asAccountGroup;
    /** @var AttributeSet $asAddress */
    protected $asAddress;
    /** @var AttributeSet $asAccountLocation */
    protected $asAccountLocation;
    /** @var AttributeSet $asContact */
    protected $asContact;
    /** @var AttributeSet $asProduct */
    protected $asProduct;
    /** @var AttributeSet $asBrand */
    protected $asBrand;
    /** @var AttributeSet $asSProductAttributeConfiguration */
    protected $asSProductAttributeConfiguration;
    /** @var AttributeSet $asSProductAttributeConfigurationOption */
    protected $asSProductAttributeConfigurationOption;
    /** @var AttributeSet $asSProductAttributeLink */
    protected $asSProductAttributeLink;
    /** @var AttributeSet $asAccountTypeLink */
    protected $asAccountTypeLink;
    /** @var AttributeSet $asSRoute */
    protected $asSRoute;
    /** @var AttributeSet $asProductGroup */
    protected $asProductGroup;
    /** @var AttributeSet $asProductProductGroup */
    protected $asProductProductGroup;
    /** @var AttributeSet $asCity */
    protected $asCity;
    /** @var AttributeSet $asHumidor */
    protected $asHumidor;

    /** @var SproductManager $sProductManager */
    protected $sProductManager;
    protected $taxTypes;
    protected $productTypes;
    protected $currency;

    /** @var [] */
    private $errors;

    protected $allStores;

    public function initialize()
    {
        parent::initialize();
        $this->currency = $this->entityManager->getEntityByEntityTypeAndId($this->entityManager->getEntityTypeByCode("currency"), $_ENV["DEFAULT_CURRENCY"]);

        $this->asAccount = $this->entityManager->getAttributeSetByCode("account");
        $this->asAccountGroup = $this->entityManager->getAttributeSetByCode("account_group");
        $this->asAddress = $this->entityManager->getAttributeSetByCode("address");
        $this->asAccountLocation = $this->entityManager->getAttributeSetByCode("account_location");
        $this->asProductImages = $this->entityManager->getAttributeSetByCode("product_images");
        $this->asContact = $this->entityManager->getAttributeSetByCode("contact");
        $this->asAccountTypeLink = $this->entityManager->getAttributeSetByCode("account_type_link");
        $this->asProduct = $this->entityManager->getAttributeSetByCode("product");
        $this->asBrand = $this->entityManager->getAttributeSetByCode("brand");
        $this->asSProductAttributeConfiguration = $this->entityManager->getAttributeSetByCode("s_product_attribute_configuration");
        $this->asSProductAttributeConfigurationOption = $this->entityManager->getAttributeSetByCode("s_product_attribute_configuration_options");
        $this->asSProductAttributeLink = $this->entityManager->getAttributeSetByCode("s_product_attributes_link");
        $this->asSRoute = $this->entityManager->getAttributeSetByCode("s_route");
        $this->asProductGroup = $this->entityManager->getAttributeSetByCode("product_group");
        $this->asProductProductGroup = $this->entityManager->getAttributeSetByCode("product_product_group_link");
        $this->asCity = $this->entityManager->getAttributeSetByCode("city");
        $this->asHumidor = $this->entityManager->getAttributeSetByCode("humidor");

        $this->allStores = $this->getStores();
        $this->webPath = $_ENV["WEB_PATH"];

        $this->debug = true;
    }

    public function logErrors()
    {
        if (!empty($this->errors)) {
            $this->logger->error(implode("\n", $this->errors));
        }
    }

    public function importCamelotDirectoryTest($filePath)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $accountColumns = array(
            "id",
            "remote_id",
            "name",
            "first_name",
            "last_name",
            "phone",
            "email",
            "oib",
            "description"
        );

        $existingAccounts = $this->getExistingAccounts("remote_id", $accountColumns);
        $existingAddresses = $this->getExistingAddresses();
        $existingAccountTypeLinks = $this->getExistingAccountTypeLinks();
        $existingAccountGroups = $this->getExistingAccountGroups();
        $existingAccountLocations = $this->getExistingAccountLocations();
        $existingContacts = $this->getExistingContacts();
        $allCities = $this->getExistingCities();

        print("Starting import!\n");
        $csvData = $this->parseCsv($filePath);

        $loadedCsvValues = array();
        $insertedAccountEmails = array();
        $insertedAccountOibs = array();

        $insertArray = array(
            "account_group_entity" => array()
        );

        $insertArray2 = array(
            "account_entity" => array()
        );

        $insertArray3 = array(
            "address_entity" => array(),
            "account_type_link_entity" => array()
        );

        $insertArray4 = array(
            "account_location_entity" => array(),
            "contact_entity" => array()
        );

        $updateArray = array();

        $csvValues = [1 => "name",
            2 => "address",
            3 => "phone_fax",
            4 => "oib",
            6 => "remote_id",
            7 => "iban",
            8 => "email",
            9 => "email2",
            10 => "account_group",
            11 => "account_group_name",
            12 => "description"];

        foreach ($csvData as $data) {
            // Convert missing characters
            $dataRow = str_replace("\"", "", str_replace(array("æ", "è", "ð", "È", "Æ",), array("ć", "č", "đ", "Ć", "Č"), mb_convert_encoding($data[0], "utf-8", "windows-1252")));

            // Explode dataRow with commas that are not inside quotes
            $dataRowExploaded = preg_split("#\t#i", $dataRow);
            $dataRowValues = array();

            if (strcmp("•", $dataRowExploaded[0])) {
                continue;
            }

            // Add values to the
            foreach ($csvValues as $key => $value) {

                if (!empty($dataRowExploaded[$key])) {
                    if ($value == "account_group") {
                        $dataRowValues[$value] = intval($dataRowExploaded[$key]);
                    } else if ($value == "description") {
                        $dataRowValues[$value] = addslashes($dataRowExploaded[$key]);
                    } else {
                        $dataRowValues[$value] = $dataRowExploaded[$key];
                    }
                } else {
                    $dataRowValues[$value] = null;
                }
            }

            if (!empty($dataRowValues["remote_id"])) {
                $loadedCsvValues[$dataRowValues["remote_id"]] = $dataRowValues;
            }
        }

//        sort($loadedCsvValues);

        /**
         * Begin import
         */
        foreach ($loadedCsvValues as $remoteId => $dataRowValue) {

            $firstName = null;
            $lastName = null;

            $accountEmail = $dataRowValue["email"];
            if (empty($accountEmail)) {
                $accountEmail = $dataRowValue["email2"];
            }

            $accountIsLegalEntity = 1;
            if (empty($dataRowValue["oib"])) {
                $accountIsLegalEntity = 0;
                $name = explode(' ', $dataRowValue["name"]);
                $firstName = $name[0];
                $lastName = (isset($name[count($name) - 1])) ? $name[count($name) - 1] : '';
            }

            /**
             * Import account
             */

            if (!empty($dataRowValue["oib"]) && !isset($insertedAccountOibs[$dataRowValue["oib"]][$dataRowValue["remote_id"]])) {
                $insertedAccountOibs[$dataRowValue["oib"]][$dataRowValue["remote_id"]] = $dataRowValue;
            }

            if (!isset($existingAccounts[$remoteId])) {

                if ((!empty($dataRowValue["oib"]) && count($insertedAccountOibs[$dataRowValue["oib"]]) <= 1) || empty($dataRowValue["oib"])) {

                    if (!empty($accountEmail) && in_array($accountEmail, $insertedAccountEmails)) {
                        echo $accountEmail . " already inserted\n";
                        $accountEmail = null;
                    } else {
                        $insertedAccountEmails[] = $accountEmail;
                    }

                    $accountInsertArray = $this->getEntityDefaults($this->asAccount);

                    $accountInsertArray["name"] = $dataRowValue["name"];
                    $accountInsertArray["first_name"] = $firstName;
                    $accountInsertArray["last_name"] = $lastName;
                    $accountInsertArray["phone"] = $dataRowValue["phone_fax"];
                    $accountInsertArray["oib"] = $dataRowValue["oib"];
                    $accountInsertArray["description"] = $dataRowValue["description"];
                    $accountInsertArray["email"] = $accountEmail;
                    $accountInsertArray["is_legal_entity"] = $accountIsLegalEntity;
                    $accountInsertArray["is_active"] = 1;
                    $accountInsertArray["remote_id"] = $remoteId;

                    if (isset($existingAccountGroups[$dataRowValue["account_group"]])) {
                        $accountInsertArray["account_group_id"] = $existingAccountGroups[$dataRowValue["account_group"]]["id"];
                    } else {
                        $accountInsertArray["filter_insert"]["account_group_remote_id"] = $dataRowValue["account_group"];
                    }

                    $insertArray2["account_entity"][$remoteId] = $accountInsertArray;
                }
            } else {
                $accountUpdateArray = array();

                if (!empty($dataRowValue["name"]) && $existingAccounts[$remoteId]["name"] != $dataRowValue["name"]) {
                    $accountUpdateArray["name"] = $dataRowValue["name"];
                }
                if (!empty($firstName) && $existingAccounts[$remoteId]["first_name"] != $firstName) {
                    $accountUpdateArray["first_name"] = $firstName;
                }
                if (!empty($lastName) && $existingAccounts[$remoteId]["last_name"] != $lastName) {
                    $accountUpdateArray["last_name"] = $lastName;
                }
                if (!empty($description) && $existingAccounts[$remoteId]["description"] != $description) {
                    $accountUpdateArray["description"] = $description;
                }
                if (!empty($dataRowValue["phone_fax"]) && $existingAccounts[$remoteId]["phone"] != $dataRowValue["phone_fax"]) {
                    $accountUpdateArray["phone"] = $dataRowValue["phone_fax"];
                }
                if (!empty($dataRowValue["oib"]) && $existingAccounts[$remoteId]["oib"] != $dataRowValue["oib"]) {
                    $accountUpdateArray["oib"] = $dataRowValue["oib"];
                }

                if (!empty($accountUpdateArray)) {
                    $accountUpdateArray["modified"] = "NOW()";
                    $updateArray["account_entity"][$existingAccounts[$remoteId]["id"]] = $accountUpdateArray;
                }
            }

            /**
             * Import account_type_links
             */

            $accountTypeLinkKey = $remoteId . "_3";

            if (!isset($existingAccountTypeLinks[$accountTypeLinkKey])) {

                if ((!empty($dataRowValue["oib"]) && count($insertedAccountOibs[$dataRowValue["oib"]]) <= 1) || empty($dataRowValue["oib"])) {
                    $accountTypeLinkInsertArray = $this->getEntityDefaults($this->asAccountTypeLink);

                    $accountTypeLinkInsertArray["account_type_id"] = 3;
                    $accountTypeLinkInsertArray["filter_insert"]["account_remote_id"] = $remoteId;

                    $insertArray3["account_type_link_entity"][$accountTypeLinkKey] = $accountTypeLinkInsertArray;
                }
            } else {
                // TODO update
            }

            /**
             * Import addresses
             */
            $addressRemoteId = $dataRowValue["remote_id"];
            $accountRemoteId = $addressRemoteId;

            $headquarters = 1;
            $billing = 1;

            if (!empty($dataRowValue["oib"]) && count($insertedAccountOibs[$dataRowValue["oib"]]) > 1) {
                $accountRemoteId = $insertedAccountOibs[$dataRowValue["oib"]][array_key_first($insertedAccountOibs[$dataRowValue["oib"]])]["remote_id"];
                $headquarters = 0;
                $billing = 0;
            }

            if ($accountIsLegalEntity == false) {
                $headquarters = 0;
                $billing = 0;
            }

            $street = null;
            $city = str_replace("\"", "", $dataRowValue["address"]);

            $n = strrpos($city, ",");
            if ($n !== false) {
                $street = trim(substr($city, 0, $n));
                $city = trim(substr($city, $n + 1));
            }

            $cityId = NULL;
            if (isset($allCities[strtolower($city)])) {
                $cityId = $allCities[strtolower($city)]["id"];
            }

            if (!isset($existingAddresses[$addressRemoteId])) {
                $addressInsertArray = $this->getEntityDefaults($this->asAddress);

                $addressInsertArray["street"] = $street;
                $addressInsertArray["headquarters"] = $headquarters;
                $addressInsertArray["billing"] = $billing;
                $addressInsertArray["city_id"] = $cityId;
                $addressInsertArray["remote_id"] = $addressRemoteId;

                if (isset($existingAccounts[$accountRemoteId])) {
                    $addressInsertArray["account_id"] = $existingAccounts[$accountRemoteId]["id"];
                } else {
                    $addressInsertArray["filter_insert"]["account_remote_id"] = $accountRemoteId;
                }

                $insertArray3["address_entity"][$addressRemoteId] = $addressInsertArray;
            } else {
                $addressUpdateArray = array();

                // TODO: provjera changed values

                if (!empty($addressUpdateArray)) {

                }
            }

            /**
             * Import account_locations
             */
            $accountLocationRemoteId = $dataRowValue["remote_id"];

            if (!isset($existingAccountLocations[$accountLocationRemoteId])) {

                $accountLocationInsertArray = $this->getEntityDefaults($this->asAccountLocation);

                $accountLocationInsertArray["name"] = $dataRowValue["name"];
                $accountLocationInsertArray["remote_id"] = $accountLocationRemoteId;

                if (isset($existingAccounts[$accountRemoteId])) {
                    $accountLocationInsertArray["account_id"] = $existingAccounts[$accountRemoteId]["id"];
                } else {
                    $accountLocationInsertArray["filter_insert"]["account_remote_id"] = $accountRemoteId;
                }

                if (isset($existingAddresses[$addressRemoteId])) {
                    $accountLocationInsertArray["address_id"] = $existingAddresses[$addressRemoteId]["id"];
                } else {
                    $accountLocationInsertArray["filter_insert"]["address_remote_id"] = $addressRemoteId;
                }

                $insertArray4["account_location_entity"][$accountLocationRemoteId] = $accountLocationInsertArray;

            } else {
                // TODO update
            }

            /**
             * Import contacts
             */
            $contactRemoteId = $dataRowValue["remote_id"];

            if (!isset($existingContacts[$contactRemoteId])) {

                $conctactInsertArray = $this->getEntityDefaults($this->asContact);

                $conctactInsertArray["first_name"] = $firstName;
                $conctactInsertArray["last_name"] = $lastName;
                $conctactInsertArray["email"] = $dataRowValue["email"];
                $conctactInsertArray["phone"] = $dataRowValue["phone_fax"];
                $conctactInsertArray["full_name"] = $dataRowValue["name"];
                $conctactInsertArray["is_active"] = 1;
                $conctactInsertArray["remote_id"] = $contactRemoteId;

                if (isset($existingAccounts[$accountRemoteId])) {
                    $conctactInsertArray["account_id"] = $existingAccounts[$accountRemoteId]["id"];
                } else {
                    $conctactInsertArray["filter_insert"]["account_remote_id"] = $accountRemoteId;
                }

                $insertArray4["contact_entity"][$contactRemoteId] = $conctactInsertArray;

            } else {
                // TODO update
            }

            /**
             * Import account groups
             */
            if (!empty($dataRowValue["account_group"]) &&
                !empty($dataRowValue["account_group_name"]) &&
                !isset($existingAccountGroups[$dataRowValue["account_group"]]) &&
                !isset($insertArray["account_group_entity"][$dataRowValue["account_group"]])) {

                $accountGroupInsertArray = $this->getEntityDefaults($this->asAccountGroup);

                $accountGroupInsertArray["name"] = $dataRowValue["account_group_name"];
                $accountGroupInsertArray["remote_id"] = $dataRowValue["account_group"];

                $insertArray["account_group_entity"][$dataRowValue["account_group"]] = $accountGroupInsertArray;
            }
        }

        $insertQuery = $this->getInsertQuery($insertArray);
        if (!empty($insertQuery)) {
            $this->logQueryString($insertQuery);
            $this->databaseContext->executeNonQuery($insertQuery);
        }

        $reselectArray["account_group_entity"] = $this->getExistingAccountGroups();
        $insertArray2 = $this->filterImportArray($insertArray2, $reselectArray);

        $insertQuery2 = $this->getInsertQuery($insertArray2);
        if (!empty($insertQuery2)) {
            $this->logQueryString($insertQuery2);
            $this->databaseContext->executeNonQuery($insertQuery2);
        }

        $reselectArray["account_entity"] = $this->getExistingAccounts("remote_id", $accountColumns);
        $insertArray3 = $this->filterImportArray($insertArray3, $reselectArray);

        $insertQuery3 = $this->getInsertQuery($insertArray3);

        if (!empty($insertQuery3)) {
            $this->logQueryString($insertQuery3);
            $this->databaseContext->executeNonQuery($insertQuery3);
        }

        $reselectArray["account_entity"] = $this->getExistingAccounts("remote_id", $accountColumns);
        $reselectArray["address_entity"] = $this->getExistingAddresses();
        $insertArray4 = $this->filterImportArray($insertArray4, $reselectArray);

        $insertArray4 = $this->getInsertQuery($insertArray4);

        if (!empty($insertArray4)) {
            $this->logQueryString($insertArray4);
            $this->databaseContext->executeNonQuery($insertArray4);
        }

        echo "Import finished\n";

        return true;
    }

    public function importCamelotProducts($filePath)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $accountColumns = array(
            "id",
            "remote_id",
            "name",
            "first_name",
            "last_name",
            "phone",
            "email",
            "oib",
            "description"
        );

        $sRouteEntityColumns = [
            "id",
            "request_url",
            "destination_type",
            "destination_id"
        ];

        $existingBrands = $this->getExistingBrands();
        $existingProducts = $this->getExistingProducts();
        $existingSProductAttributeData["s_product_attribute_configuration"] = $this->getExistingSProductAttributeConfigurations();
        $existingSProductAttributeData["s_product_attribute_configuration_options"] = $this->getExistingSProductAttributeConfigurationOptions();
        $existingSProductAttributeData["s_product_attribute_link"] = $this->getExistingSProductAttributeLinks();
        $existingAccounts = $this->getExistingAccounts("remote_id", $accountColumns);
        $existingAccountTypeLinks = $this->getExistingAccountTypeLinks();
        $existingSRouteUrls = $this->getExistingSRouteUrls("request_url", $sRouteEntityColumns);
//        $existingSRouteProducts = $this->getExistingSRouteUrls("destination_id", $sRouteEntityColumns, "AND destination_type LIKE 'product'");
        $existingSRouteProducts = $this->getExistingSRouteProducts();
        $existingTaxTypes = $this->getTaxTypes("percent", ["id, percent"]);

        // Array which will store all values from the import file
        $loadedCsvValues = [];

        print("Starting import!\n");
        $csvData = $this->parseCsv($filePath);

        $insertArray = [
            "brand_entity" => []
        ];

        $insertArray2 = [
            "product_entity" => []
        ];

        $insertArray3 = [
            "s_product_attributes_link_entity" => [],
            "account_type_link_entity" => [],
            "s_route_entity" => []
        ];

        $csvValues = [
            1 => "code",
            2 => "name",
            3 => "catalog_number",
            4 => "unit_of_measure",
            13 => "qty",
            17 => "guarantee",
            19 => "max_rebate",
            20 => "ean",
            43 => "country_of_manufacture",
            48 => "created",
            49 => "modified",
            51 => "price_base",
            52 => "price_retail",
            64 => "tax_amount",
            75 => "ullage",
            76 => "brand_remote_id",
            77 => "brand",
            78 => "supplier_remote_id",
            79 => "supplier",
            83 => "weight",
            84 => "weight_type"
        ];

        foreach ($csvData as $data) {
            // Convert missing characters
            $dataRow = str_replace("\"", "", str_replace(array("æ", "è", "ð", "È", "Æ",), array("ć", "č", "đ", "Ć", "Č"), mb_convert_encoding($data[0], "utf-8", "windows-1252")));

            // Explode dataRow with commas that are not inside quotes
            $dataRowExploaded = preg_split("#\t#i", $dataRow);

            // Array that will contain single xlsx data row
            $dataRowValue = array();

            if (strcmp("•", $dataRowExploaded[0])) {
                continue;
            }

            $showOnStore = [];
            foreach ($this->allStores as $store) {
                $showOnStore[$store] = 1;
            }

            // Add values to the data row array
            foreach ($csvValues as $key => $value) {

                if (!empty($dataRowExploaded[$key])) {
                    if ($value == "code") {
                        $dataRowValue[$value] = $dataRowExploaded[$key];
//                        $dataRowValue["remote_id"] = $dataRowExploaded[$key];
                    } else if (in_array($value, ["price_retail", "price_base", "qty", "max_rebate", "ullage", "tax_amount", "weight"])) {
                        $number = preg_replace("#,#i", ".", $dataRowExploaded[$key]);
                        $dataRowValue[$value] = floatval($number);
                    } else if (in_array($value, ["created", "modified"])) {
                        $dataRowValue[$value] = (date("Y-m-d", strtotime(preg_replace("#/#i", "-", $dataRowExploaded[$key]))));
                    } else {
                        $dataRowValue[$value] = $dataRowExploaded[$key];
                    }
                } else {
                    $dataRowValue[$value] = null;
                }
            }

            if (!empty($dataRowValue["code"])) {
                $loadedCsvValues[$dataRowValue["code"]] = $dataRowValue;
            }

            if (isset($dataRowValue["brand_remote_id"]) && !empty($dataRowValue["brand_remote_id"])) {

                if (!isset($existingBrands[$dataRowValue["brand_remote_id"]])) {
                    $brandInsertArray = $this->getEntityDefaults($this->asBrand);

                    $brandInsertArray["name"] = $this->parseValueToJson($dataRowValue["brand"]);
                    $brandInsertArray["show_on_store"] = json_encode($showOnStore);
                    $brandInsertArray["remote_id"] = $dataRowValue["brand_remote_id"];

                    $insertArray["brand_entity"][$brandInsertArray["remote_id"]] = $brandInsertArray;
                }
            }
        }

        /**
         * Begin import
         */
        foreach ($loadedCsvValues as $productCode => $data) {

            if (empty($data["name"])) {
                continue;
            }

            $counter = 1;
            $url = $this->routeManager->prepareUrl(trim($data["name"]));
            $urlBase = $url;
            while (isset($existingSRouteUrls[$url]) || isset($insertArray3["s_route_entity"][$url])) {
                $url = $urlBase . "_" . $counter;
                $counter++;
            }

            $taxTypeId = null;
            if (!empty($data["tax_amount"])) {
                if (isset($existingTaxTypes[$data["tax_amount"]])) {
                    $taxTypeId = $existingTaxTypes[$data["tax_amount"]]["id"];
                }
            }

            $weight = null;
            if (!empty($data["weight"])) {
                $weight = $data["weight"];

                if (!empty($data["weight_type"])) {
                    if (trim($data["weight_type"]) == "g") {
                        $weight /= 1000;
                    }
                }
            }
            $weight = $data["weight"];

            $productInsertArray = $this->getEntityDefaults($this->asProduct);

            $productInsertArray["code"] = $data["code"];
            $productInsertArray["name"] = $this->parseValueToJson($data["name"]);
            $productInsertArray["meta_title"] = $productInsertArray["name"];
            $productInsertArray["meta_keywords"] = $productInsertArray["name"];
            $productInsertArray["qty"] = $data["qty"];
            $productInsertArray["max_rebate"] = $data["max_rebate"];
            $productInsertArray["ean"] = $data["ean"];
            $productInsertArray["price_retail"] = $data["price_retail"];
            $productInsertArray["price_base"] = $data["price_base"];
            $productInsertArray["ullage"] = $data["ullage"];
            $productInsertArray["active"] = 1;
            $productInsertArray["is_visible"] = 1;
            $productInsertArray["currency_id"] = $_ENV["DEFAULT_CURRENCY"];
            $productInsertArray["is_saleable"] = 0;
            $productInsertArray["manufacturer_remote_id"] = $data["brand_remote_id"];
            $productInsertArray["url"] = $this->parseValueToJson($url);
            $productInsertArray["modified"] = $data["modified"];
            $productInsertArray["created"] = $data["created"];
            $productInsertArray["tax_type_id"] = $taxTypeId;
            $productInsertArray["weight"] = $weight;
            $productInsertArray["show_on_store"] = json_encode($showOnStore);
            $productInsertArray["ord"] = 100;
            $productInsertArray["template_type_id"] = 5;
            $productInsertArray["keep_url"] = 1;
            $productInsertArray["auto_generate_url"] = 0;
            $productInsertArray["remote_source"] = "XML file";
            if (empty($data["created"])) {
                $productInsertArray["created"] = $productInsertArray["modified"];
            }

            if (!empty($data["supplier"])) {
                $sProductAttributeLinkValue = $this->insertAttributeIntoProductAttributeLinks("Supplier", $data["supplier"], 1, $productCode, $existingSProductAttributeData);

                if (!empty($sProductAttributeLinkValue)) {
                    $insertArray3["s_product_attributes_link_entity"][$sProductAttributeLinkValue["wand_attribute_value_id"]] = $sProductAttributeLinkValue;
                }
            }

            $productInsertArray["supplier_id"] = NULL;
            if (isset($data["supplier_remote_id"]) && !empty($data["supplier_remote_id"])) {

                $productInsertArray["filter_insert"]["supplier_remote_id"] = $data["supplier_remote_id"];

                if (isset($existingAccounts[$data["supplier_remote_id"]])) {
                    $accountRemoteId = $existingAccounts[$data["supplier_remote_id"]]["remote_id"];
                    $accountTypeLinkKey = $accountRemoteId . "_10";

                    if (!isset($existingAccountTypeLinks[$accountTypeLinkKey])) {
                        $accountTypeLinkInsertArray = $this->getEntityDefaults($this->asAccountTypeLink);

                        $accountTypeLinkInsertArray["account_type_id"] = 10;
                        $accountTypeLinkInsertArray["filter_insert"]["account_remote_id"] = $accountRemoteId;

                        $insertArray3["account_type_link_entity"][$accountTypeLinkKey] = $accountTypeLinkInsertArray;
                    }
                }
            }

            if (!empty($data["country_of_manufacture"])) {
                $sProductAttributeLinkValue = $this->insertAttributeIntoProductAttributeLinks("Country of manufacture", $data["country_of_manufacture"], 1, $productCode, $existingSProductAttributeData);

                if (!empty($sProductAttributeLinkValue)) {
                    $insertArray3["s_product_attributes_link_entity"][$sProductAttributeLinkValue["wand_attribute_value_id"]] = $sProductAttributeLinkValue;
                }
            }

            if (!empty($data["unit_of_measure"])) {
                $sProductAttributeLinkValue = $this->insertAttributeIntoProductAttributeLinks("Unit of measure", $data["unit_of_measure"], 1, $productCode, $existingSProductAttributeData);

                if (!empty($sProductAttributeLinkValue)) {
                    $insertArray3["s_product_attributes_link_entity"][$sProductAttributeLinkValue["wand_attribute_value_id"]] = $sProductAttributeLinkValue;
                }
            }

            if (!empty($data["brand_remote_id"])) {

                if (isset($existingBrands[$data["brand_remote_id"]])) {
                    $productInsertArray["brand_id"] = $existingBrands[$data["brand_remote_id"]]["id"];
                } else {
                    $productInsertArray["filter_insert"]["brand_remote_id"] = $data["brand_remote_id"];
                }

                $sProductAttributeLinkValue = $this->insertAttributeIntoProductAttributeLinks("Brand", $data["brand"], 1, $productCode, $existingSProductAttributeData);
                $insertArray3["s_product_attributes_link_entity"][$sProductAttributeLinkValue["wand_attribute_value_id"]] = $sProductAttributeLinkValue;
            } else {
                $productInsertArray["brand_id"] = NULL;
            }

            if (!isset($existingSRouteProducts[$data["code"]])) {

                foreach ($this->allStores as $store) {
                    $sRouteInsertArray = $this->getEntityDefaults($this->asSRoute);

                    $sRouteInsertArray["request_url"] = $url;
                    $sRouteInsertArray["destination_type"] = "product";
                    $sRouteInsertArray["filter_insert"]["product_code"] = $productInsertArray["code"];
                    $sRouteInsertArray["store_id"] = $store;

                    $insertArray3["s_route_entity"][$url] = $sRouteInsertArray;
                }

            } else {
                // TODO update
            }

            if (!isset($existingProducts[$productCode])) {
                $insertArray2["product_entity"][$productCode] = $productInsertArray;
            } else {
                // todo update
            }
        }

        $insertQuery = $this->getInsertQuery($insertArray);
        if (!empty($insertQuery)) {
            $this->logQueryString($insertQuery);
            $this->databaseContext->executeNonQuery($insertQuery);
        }

        $reselectArray["brand_entity"] = $this->getExistingBrands();
        $reselectArray["account_entity"] = $this->getExistingAccounts("remote_id", $accountColumns);
        $insertArray2 = $this->filterImportArray($insertArray2, $reselectArray);

        $insertQuery2 = $this->getInsertQuery($insertArray2);
        if (!empty($insertQuery2)) {
            $this->logQueryString($insertQuery2);
            $this->databaseContext->executeNonQuery($insertQuery2);
        }

        $reselectArray["product_entity"] = $this->getExistingProducts();
        $reselectArray["account_entity"] = $this->getExistingAccounts("remote_id", $accountColumns);
        $insertArray3 = $this->filterImportArray($insertArray3, $reselectArray);
        $insertQuery3 = $this->getInsertQuery($insertArray3);
        if (!empty($insertQuery3)) {
            $this->logQueryString($insertQuery3);
            $this->databaseContext->executeNonQuery($insertQuery3);
        }

    }

    public function importCamelotProductGroupsFromXml($filePath)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $productGroupColumns = [
            "id",
            "name",
            "url",
            "product_group_id",
            "product_group_code",
            "remote_id"
        ];

        $sRouteEntityColumns = [
            "id",
            "request_url",
            "destination_type",
            "destination_id"
        ];

        $insertArray = array(
            "product_group_entity" => array()
        );

        $insertArray2 = array(
            "s_route_entity" => array()
        );

        $existingProductGroups = $this->getExistingProductGroups("remote_id", $productGroupColumns);
        $existingSRouteUrls = $this->getExistingSRouteUrls("request_url", $sRouteEntityColumns);

        $xmlData = json_decode(json_encode(simplexml_load_file($filePath)), true);
        $loadedXmlData = [];

        $showOnStore = [];
        foreach ($this->allStores as $store) {
            $showOnStore[$store] = 1;
        }

        foreach ($xmlData["Registar"] as $data) {

            if (!isset($loadedXmlData[$data["Šifra"]])) {
                $loadedXmlData[$data["Šifra"]] = $data;
            }
        }

        // Sort data array to make sure that lower count remote_id-s are first
        asort($loadedXmlData);

        $insertedProductGroupUrls = array();
        foreach ($loadedXmlData as $data) {

            $existingProductGroups = $this->getExistingProductGroups("product_group_code", $productGroupColumns);
            $existingSRouteUrls = $this->getExistingProductGroupSRoutes();

            $prodGrpRemoteId = $data["Šifra"];
            $prodGrpName = $data["Naziv"];
            $prodGrpLength = 2;

            if (strlen($prodGrpRemoteId) > $prodGrpLength) {
                $prodGrpParentCategoryId = substr($prodGrpRemoteId, 0, -($prodGrpLength));
            } else {
                $prodGrpParentCategoryId = NULL;
            }

            $counter = 1;
            $prodGrpUrl = $this->routeManager->prepareUrl(trim($prodGrpName));
            $baseUrl = $prodGrpUrl;
            while (isset($existingSRouteUrls[$prodGrpUrl]) || isset($insertedProductGroupUrls[$prodGrpUrl])) {
                $prodGrpUrl = $baseUrl . "-" . $counter;
                $counter++;
            }

            $insertedProductGroupUrls[$prodGrpUrl] = true;

            if (!isset($existingProductGroups[$data["Šifra"]])) {
                $productGroupInsertArray = $this->getEntityDefaults($this->asProductGroup);

                $productGroupInsertArray["name"] = $this->parseValueToJson($prodGrpName);
                $productGroupInsertArray["product_group_code"] = $prodGrpRemoteId;
                $productGroupInsertArray["template_type_id"] = 4;
                $productGroupInsertArray["is_active"] = 1;
                $productGroupInsertArray["auto_generate_url"] = 1;
                $productGroupInsertArray["keep_url"] = 1;
                $productGroupInsertArray["meta_title"] = $productGroupInsertArray["name"];
                $productGroupInsertArray["meta_keywords"] = $productGroupInsertArray["name"];
                $productGroupInsertArray["url"] = $this->parseValueToJson($prodGrpUrl);
                $productGroupInsertArray["show_on_store"] = json_encode($showOnStore);
                $productGroupInsertArray["remote_source"] = "Wand";

                if (strlen($prodGrpParentCategoryId) == 0) {
                    $productGroupInsertArray["product_group_id"] = NULL;
                } else {
                    if (isset($existingProductGroups[$prodGrpParentCategoryId])) {
                        $productGroupInsertArray["product_group_id"] = $existingProductGroups[$prodGrpParentCategoryId]["id"];
                    } else {
//                        $productGroupInsertArray["product_group_id"] = $prodGrpParentCategoryId;
                        throw new \Exception("Parent product id '{$prodGrpParentCategoryId}' doesn't exist within the databse");
                    }
                }

//                foreach($this->allStores as $store){
//                    $urlKey = $prodGrpUrl . "_" . $store;
//
//                    if(!isset($existingProductGroupUrls[$urlKey])){
//                        $sRouteInsertArray = $this->getEntityDefaults($this->asSRoute);
//
//                        $sRouteInsertArray["request_url"] = $productGroupInsertArray["url"];
//                        $sRouteInsertArray["destination_type"] = "product_group";
//                        $sRouteInsertArray["filter_insert"]["product_group_remote_id"] = $prodGrpRemoteId;
//                        $sRouteInsertArray["store_id"] = $store;
//
//                        $insertArray2["s_route_entity"][] = $sRouteInsertArray;
//                    } else {
//                        // TODO update
//                    }
//                }

                // Ubacivanje jednog reda podataka, ovo je odrađeno na ovaj način jer mi je potreban parent_product_group_id koji za vrijeme import-a nebi bio u bazi, redak može sadržavati vezu na drugi redak u istoj tablici!
                $insertArray["product_group_entity"][$prodGrpRemoteId] = $productGroupInsertArray;
                $insertQuery = $this->getInsertQuery($insertArray);

                if (!empty($insertQuery)) {
                    $this->logQueryString($insertQuery);
                    $this->databaseContext->executeNonQuery($insertQuery);
                }
                $insertArray["product_group_entity"] = array();
            }
        }

        $reselectArray["product_group_entity"] = $this->getExistingProductGroups("remote_id", $productGroupColumns);
        $insertArray2 = $this->filterImportArray($insertArray2, $reselectArray);
        $insertQuery2 = $this->getInsertQuery($insertArray2);
        if (!empty($insertQuery2)) {
            $this->logQueryString($insertQuery2);
            $this->databaseContext->executeNonQuery($insertQuery2);
        }
    }

    public function importCamelotProductsFromCsv($filePath)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        // Length of the product group remote ids
        $baseSingleProductGroupRemoteIdLength = 2;

        // Array which will store all values from the import file
        $loadedCsvValues = [];

        print("Starting import!\n");
        $csvData = $this->parseCsv($filePath);

        $insertArray = [
            "brand_entity" => []
        ];

        $insertArray2 = [
            "product_entity" => []
        ];

        $insertArray3 = [
            "s_product_attributes_link_entity" => [],
            "account_type_link_entity" => [],
            "s_route_entity" => [],
            "product_product_group_link_entity" => []
        ];

        $updateArray = [];

        $accountColumns = array(
            "id",
            "remote_id",
            "name",
            "first_name",
            "last_name",
            "phone",
            "email",
            "oib",
            "description"
        );

        $sRouteEntityColumns = [
            "id",
            "request_url",
            "destination_type",
            "destination_id"
        ];

        $productGroupColumns = [
            "id",
            "name",
            "url",
            "product_group_id",
            "product_group_code",
            "remote_id"
        ];

        $existingBrands = $this->getExistingBrands();
        $existingProducts = $this->getExistingProducts();
        $existingSProductAttributeData["s_product_attribute_configuration"] = $this->getExistingSProductAttributeConfigurations();
        $existingSProductAttributeData["s_product_attribute_configuration_options"] = $this->getExistingSProductAttributeConfigurationOptions();
        $existingSProductAttributeData["s_product_attribute_link"] = $this->getExistingSProductAttributeLinks();
        $existingAccounts = $this->getExistingAccounts("remote_id", $accountColumns);
        $existingAccountTypeLinks = $this->getExistingAccountTypeLinks();
        $existingSRouteUrls = $this->getExistingSRouteUrls("request_url", $sRouteEntityColumns);
        $existingSRouteProducts = $this->getExistingSRouteProducts();
        $existingTaxTypes = $this->getTaxTypes("percent", ["id, percent"]);
        $existingProductProductGroupLinks = $this->getExistingProductProductGroupLinks();
        $existingProductGroups = $this->getExistingProductGroups("product_group_code", $productGroupColumns);;

        $csvValues = [
            0 => "remote_id",
            1 => "product_or_service",
//            2 => " Tip",
            3 => "code",
            4 => "product_groups",
            5 => "ean",
            6 => "name",
//            7 => " Drugi naziv",
//            8 => " Treći naziv",
//            9 => " WWW",
//            10 => " Slika",
//            11 => " SlikaW",
//            12 => " SlikaH",
//            13 => " Jedinica mjere",
//            14 => " Osnovno pak.",
            15 => "weight",
//            16 => "Bruto težina po jedinici mjere",
            17 => "weight_measurement_type",
//            18 => " Druga jedninica mjere",
//            19 => " Međuodnos jedinica mjere",
            20 => "dimensions",
//            21 => " Zapremnina",
            22 => "brand_remote_id",
            23 => "supplier_remote_id",
//            24 => " Datum promjene VPC",
//            25 => " DatumKom",
//            26 => " DatumUlaza",
//            27 => " DatumIzlaza",
//            28 => " Atribut 1",
//            29 => " Atribut 2",
//            30 => " Atribut 3",
//            31 => " Atribut 4",
//            32 => " Grupa stavki",
//            33 => " Ambalaža",
//            34 => " Ulazna trošarina",
//            35 => " Rezerva1",
//            36 => " Rezerva2",
//            37 => " Porezna tarifa",
//            38 => " Carinska tarifa",
            39 => "country_of_origin",
            40 => "country_of_origin_remote_id",
//            41 => " Atestni broj",
//            42 => " Atestni datum",
            43 => "guarantee",
//            44 => " Skladisno mjesto",
//            45 => " Konto",
//            46 => " Dozv. kalo",
//            47 => " Ostalo - ne koristi se",
//            48 => " Cjenik - ne koristi se",
//            49 => " PPOM",
//            50 => " Trigonik - ne koristi se",
//            51 => " Unos Serijskog broja",
//            52 => " GPP - ne koristi se",
//            53 => "Proizvod",
//            54 => " ByteRezerva1",
//            55 => " ByteRezerva2",
//            56 => " ByteRezerva3",
//            57 => " ByteRezerva4",
//            58 => " ByteRezerva5",
//            59 => " Bar code",
//            60 => " Minimalna količina",
//            61 => " Optimalna količina",
//            62 => " Maksimalna količina",
//            63 => " Bruto devizna cijena",
            64 => " Rabat dobavljača",
//            65 => " Neto devizna cijena dobavljača",
//            66 => " Cijena dobavljača",
//            67 => " Troškovi prije carine",
//            68 => " Carinski troskovi",
//            69 => " Ostali troskovi",
//            70 => " Nabavna cijena",
//            71 => " StaraCijena - ne koristi se",
//            72 => " Marza",
//            73 => " Planska veleprodajna marža",
//            74 => " Planska maloprodajna marža",
//            75 => " Minimalna marža",
//            76 => " Maksimalna marža",
            77 => "price_base",
//            78 => " Komisijska veleprodajna cijena",
//            79 => " VPCijenaDealer - ne koristi se",
//            80 => " PPT",
            81 => "tax_amount",
//            82 => " PPU",
//            83 => " PPG",
            84 => "price_retail",
//            85 => " KomMPCijena",
//            86 => " TestVPCijena / Planska VP cijena",
//            87 => " TestMPCijena / Planska MP cijena",
//            88 => " Rabat - ne koristi se",
//            89 => " Šifra devize",
//            90 => " Tečaj planske kalkulacije",
//            91 => " Kreirao",
//            92 => " Mijenjao",
//            93 => " DatumUnosa",
//            94 => " DatumIzmjene",
            95 => " Hide",
//            96 => " Export",
            97 => "additional_information",
//            98 => "\r\n"
        ];


        foreach ($csvData as $data) {
            // Convert missing characters
            $dataRow = str_replace("\"", "", str_replace(array("æ", "è", "ð", "È", "Æ",), array("ć", "č", "đ", "Ć", "Č"), mb_convert_encoding($data[0], "utf-8", "windows-1252")));

            // Explode dataRow with commas that are not inside quotes
            $dataRowExploaded = preg_split("#;#i", $dataRow);

            // Array that will contain single xlsx data row
            $dataRowValue = array();

            if (!preg_match("#^[0-9]+$#i", $dataRowExploaded[0])) {
                continue;
            }

            $showOnStore = [];
            foreach ($this->allStores as $store) {
                $showOnStore[$store] = 1;
            }

            // Add values to the data row array
            foreach ($csvValues as $key => $value) {
                if (!empty($dataRowExploaded[$key])) {
                    if ($value == "code") {
                        $dataRowValue[$value] = $dataRowExploaded[$key];
//                        $dataRowValue["remote_id"] = $dataRowExploaded[$key];
                    } else if (in_array($value, ["price_retail", "price_base", "qty", "max_rebate", "ullage", "tax_amount", "weight"])) {
                        $number = preg_replace("#,#i", ".", $dataRowExploaded[$key]);
                        $dataRowValue[$value] = floatval($number);
                    } else if (in_array($value, ["created", "modified"])) {
                        $dataRowValue[$value] = (date("Y-m-d", strtotime(preg_replace("#/#i", "-", $dataRowExploaded[$key]))));
                    } else {
                        $dataRowValue[$value] = $dataRowExploaded[$key];
                    }
                } else {
                    $dataRowValue[$value] = null;
                }

                if (!empty($dataRowValue["code"])) {
                    $loadedCsvValues[$dataRowValue["code"]] = $dataRowValue;
                }

                if (isset($dataRowValue["brand_remote_id"]) && !empty($dataRowValue["brand_remote_id"])) {

                    if (!isset($existingBrands[$dataRowValue["brand_remote_id"]])) {
                        $brandInsertArray = $this->getEntityDefaults($this->asBrand);

//                        $brandInsertArray["name"] = $this->parseValueToJson($dataRowValue["brand"]);
                        $brandInsertArray["show_on_store"] = json_encode($showOnStore);
                        $brandInsertArray["remote_id"] = $dataRowValue["brand_remote_id"];
                        $brandInsertArray["filter_insert"]["name"] = $dataRowValue["brand_remote_id"];

                        $insertArray["brand_entity"][$brandInsertArray["remote_id"]] = $brandInsertArray;
                    }
                }

            }
        }


        // Begin import
        foreach ($loadedCsvValues as $productCode => $data) {

            //If remote_id is empty, continue
            if (empty($data["remote_id"])) {
                continue;
            }

            $counter = 1;
            $url = $this->routeManager->prepareUrl(trim($data["name"]));
            $urlBase = $url;
            while (isset($existingSRouteUrls[$url]) || isset($insertArray3["s_route_entity"][$url])) {
                $url = $urlBase . "_" . $counter;
                $counter++;
            }

            $taxTypeId = null;
            if (!empty($data["tax_amount"])) {
                if (isset($existingTaxTypes[$data["tax_amount"]])) {
                    $taxTypeId = $existingTaxTypes[$data["tax_amount"]]["id"];
                }
            }

            $weight = null;
            if (!empty($data["weight"])) {
                $weight = $data["weight"];

                if (!empty($data["weight_measurement_type"])) {
                    if (trim($data["weight_measurement_type"]) == "g") {
                        $weight /= 1000;
                    }
                }
            }
            $weight = $data["weight"];

            $productInsertArray = $this->getEntityDefaults($this->asProduct);
            $nameArray = $this->parseValueToAssocArray($data["name"]);

            $productInsertArray["code"] = $data["code"];
            $productInsertArray["remote_id"] = $data["remote_id"];
            $productInsertArray["name"] = json_encode($this->parseValueToAssocArray($data["name"]));
            $productInsertArray["meta_title"] = $productInsertArray["name"];
            $productInsertArray["meta_keywords"] = $productInsertArray["name"];
//            $productInsertArray["qty"] = $data["qty"];
//            $productInsertArray["max_rebate"] = $data["max_rebate"];
            $productInsertArray["ean"] = $data["ean"];
            $productInsertArray["price_retail"] = $data["price_retail"];
            $productInsertArray["price_base"] = $data["price_base"];
            $productInsertArray["active"] = 1;
            $productInsertArray["is_visible"] = 1;
            $productInsertArray["currency_id"] = $_ENV["DEFAULT_CURRENCY"];
            $productInsertArray["is_saleable"] = 1;
            $productInsertArray["manufacturer_remote_id"] = $data["brand_remote_id"];
//            $productInsertArray["supplier_remote_id"] = $data["supplier_remote_id"];
            $productInsertArray["url"] = $this->parseValueToJson($url);
            $productInsertArray["tax_type_id"] = $taxTypeId;
            $productInsertArray["weight"] = $weight;
            $productInsertArray["show_on_store"] = json_encode($showOnStore);
            $productInsertArray["ord"] = 100;
            $productInsertArray["template_type_id"] = 5;
            $productInsertArray["keep_url"] = 1;
            $productInsertArray["auto_generate_url"] = 1;
//            $productInsertArray["additional_information"] = $data["additional_information"];
            $productInsertArray["remote_source"] = "Wand";

            $productInsertArray["supplier_id"] = NULL;
            if (isset($data["supplier_remote_id"]) && !empty($data["supplier_remote_id"])) {

                $productInsertArray["filter_insert"]["supplier_remote_id"] = $data["supplier_remote_id"];

                if (isset($existingAccounts[$data["supplier_remote_id"]])) {
                    $accountRemoteId = $existingAccounts[$data["supplier_remote_id"]]["remote_id"];
                    $accountTypeLinkKey = $accountRemoteId . "_10";

                    if (!isset($existingAccountTypeLinks[$accountTypeLinkKey])) {
                        $accountTypeLinkInsertArray = $this->getEntityDefaults($this->asAccountTypeLink);

                        $accountTypeLinkInsertArray["account_type_id"] = 10;
                        $accountTypeLinkInsertArray["filter_insert"]["account_remote_id"] = $accountRemoteId;

                        $insertArray3["account_type_link_entity"][$accountTypeLinkKey] = $accountTypeLinkInsertArray;
                    }
                }
            }

            if (!empty($data["country_of_manufacture"])) {
                $sProductAttributeLinkValue = $this->insertAttributeIntoProductAttributeLinks("Country of manufacture", $data["country_of_manufacture"], 1, $productCode, $existingSProductAttributeData);

                if (!empty($sProductAttributeLinkValue)) {
                    $insertArray3["s_product_attributes_link_entity"][$sProductAttributeLinkValue["wand_attribute_value_id"]] = $sProductAttributeLinkValue;
                }
            }

            if (!empty($data["brand_remote_id"])) {

                if (isset($existingBrands[$data["brand_remote_id"]])) {
                    $productInsertArray["brand_id"] = $existingBrands[$data["brand_remote_id"]]["id"];
                } else {
                    $productInsertArray["filter_insert"]["brand_remote_id"] = $data["brand_remote_id"];
                }

//                $sProductAttributeLinkValue = $this->insertAttributeIntoProductAttributeLinks("Brand", $data["brand"], 1, $productCode, $existingSProductAttributeData);
//                $insertArray3["s_product_attributes_link_entity"][$sProductAttributeLinkValue["wand_attribute_value_id"]] = $sProductAttributeLinkValue;
            } else {
                $productInsertArray["brand_id"] = NULL;
            }

            if (!empty($data["product_groups"])) {

                $productGroupRemoteId = "";
                $productGroupRemoteIdLength = 0;
                while (strlen($productGroupRemoteId) != strlen($data["product_groups"])) {
                    $productGroupRemoteIdLength += $baseSingleProductGroupRemoteIdLength;
                    $productGroupRemoteId = substr($data["product_groups"], 0, $productGroupRemoteIdLength);

                    if (isset($existingProductGroups[$productGroupRemoteId])) {
                        $productGroupId = $existingProductGroups[$productGroupRemoteId]["id"];

                        $productProductGroupInsertArray = $this->getEntityDefaults($this->asProductProductGroup);

                        $productProductGroupInsertArray["product_group_id"] = $productGroupId;

                        $productProductGroupLinkKey = $productGroupId . "-" . $productCode;

                        // TODO mislim da će raditi insertanje productProductGroupLink-ova ali ujutro još ovo trebam testirati

                        if (!isset($existingProductProductGroupLinks[$productProductGroupLinkKey])) {

                            if (isset($existingProducts[$productCode])) {
                                $productProductGroupInsertArray["product_id"] = $existingProducts[$productCode]["id"];
                            } else {
                                $productProductGroupInsertArray["filter_insert"]["product_code"] = $productCode;
                            }

                            $insertArray3["product_product_group_link_entity"][$productProductGroupLinkKey] = $productProductGroupInsertArray;
                        }
                    }
                }
            }

            if (!isset($existingSRouteProducts[$data["code"]])) {

                foreach ($this->allStores as $store) {
                    $sRouteInsertArray = $this->getEntityDefaults($this->asSRoute);

                    $sRouteInsertArray["request_url"] = $url;
                    $sRouteInsertArray["destination_type"] = "product";
                    $sRouteInsertArray["filter_insert"]["product_code"] = $productInsertArray["code"];
                    $sRouteInsertArray["store_id"] = $store;

                    $insertArray3["s_route_entity"][$url] = $sRouteInsertArray;
                }
            } else {
                // TODO update
            }

            if (!isset($existingProducts[$productCode])) {
                $insertArray2["product_entity"][$productCode] = $productInsertArray;
            } else {
                $existingProduct = $existingProducts[$productCode];
                $productUpdateArray = [];

                if ($existingProduct["remote_id"] != $productInsertArray["remote_id"]) {
                    $productUpdateArray["remote_id"] = $productInsertArray["remote_id"];
                }

                if (array_diff_assoc(json_decode($existingProduct["name"], true), $nameArray)) {
                    $productUpdateArray["name"] = $productInsertArray["name"];
                    $productUpdateArray["meta_title"] = $productInsertArray["name"];
                    $productUpdateArray["meta_keywords"] = $productInsertArray["name"];
                }

                if (($existingProduct["ean"] != $productInsertArray["ean"])) {
                    $productUpdateArray["ean"] = $productInsertArray["ean"];
                }

                if ($existingProduct["price_retail"] != $productInsertArray["price_retail"]) {
                    $productUpdateArray["price_retail"] = $productInsertArray["price_retail"];
                }

                if ($existingProduct["price_base"] != $productInsertArray["price_base"]) {
                    $productUpdateArray["price_base"] = $productInsertArray["price_base"];
                }

                if (!empty($productUpdateArray)) {

                    $productUpdateArray["modified"] = "NOW()";
                    $updateArray["product_entity"][$existingProduct["id"]] = $productUpdateArray;
                }
            }
        }

        $reselectArray["account_entity"] = $this->getExistingAccounts("remote_id", $accountColumns);
        $insertArray = $this->filterImportArray($insertArray, $reselectArray);
        $insertQuery = $this->getInsertQuery($insertArray);
        if (!empty($insertQuery)) {
            $this->logQueryString($insertQuery);
            $this->databaseContext->executeNonQuery($insertQuery);
        }

        $reselectArray["brand_entity"] = $this->getExistingBrands();
        $reselectArray["account_entity"] = $this->getExistingAccounts("remote_id", $accountColumns);
        $insertArray2 = $this->filterImportArray($insertArray2, $reselectArray);

        $insertQuery2 = $this->getInsertQuery($insertArray2);
        if (!empty($insertQuery2)) {
            $this->logQueryString($insertQuery2);
            $this->databaseContext->executeNonQuery($insertQuery2);
        }

        $reselectArray["product_entity"] = $this->getExistingProducts();
        $reselectArray["account_entity"] = $this->getExistingAccounts("remote_id", $accountColumns);
        $insertArray3 = $this->filterImportArray($insertArray3, $reselectArray);
        $insertQuery3 = $this->getInsertQuery($insertArray3);
        if (!empty($insertQuery3)) {
            $this->logQueryString($insertQuery3);
            $this->databaseContext->executeNonQuery($insertQuery3);
        }

        /**
         * Update products
         */
        $updateQuery = $this->getUpdateQuery($updateArray);
        if (!empty($updateQuery)) {
            $this->logQueryString($updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }
        unset($updateArray);
    }

    public function importCamelotAccountsFromCsv($filePath)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        print("Starting import!\n");
        $csvData = $this->parseCsv($filePath);

        $showOnStore = [];
        foreach ($this->allStores as $store) {
            $showOnStore[$store] = 1;
        }

        //Columns which will be expored from the database
        $accountColumns = array(
            "id",
            "remote_id",
            "name",
            "first_name",
            "last_name",
            "phone",
            "email",
            "oib",
            "description",
            "code",
            "fax",
            "is_legal_entity"
        );

        $countryColumns = array(
            "id",
            "name",
            "uid",
            "code"
        );

        $cityColumns = array(
            "id",
            "name",
            "postal_code",
            "region_id",
            "country_id"
        );

        $existingAccounts = $this->getExistingAccounts("remote_id", $accountColumns);
        $existingAccountLocations = $this->getExistingAccountLocations();
        $existingAddresses = $this->getExistingAddresses();
        $existingAccountGroups = $this->getExistingAccountGroups();
        $existingAccountTypeLinks = $this->getExistingAccountTypeLinks();
        $existingRegions = $this->getExistingRegions();
        $existingCities = $this->getExistingEntity("city_entity", "postal_code", $cityColumns);
        $existingCountries = $this->getExistingEntity("country_entity", "code", $countryColumns);

        $camelotCsvCities = $this->getCamelotCitiesFromCsv("/home/shipshape/www/camelot/web/Documents/import/new/camelot_cities.csv");

        // Array which will store all values from the import file
        $loadedCsvValues = [];

        $insertArray = array(
            "account_entity" => array(),
            "city_entity" => array()
        );

        $insertArray2 = array(
            "address_entity" => array()
        );

        $insertArray3 = array(
            "account_location_entity" => array(),
            "account_type_link_entity" => array()
        );

        $updateArray = array();

        $csvValues = [
            0 => "account_remote_id",
            1 => "address_account_remote_id",
            2 => "account_is_legal_entity",
//            3 => "PAR:BROJ",
            4 => "account_oib",
            5 => "account_desc",
//            6 => "PAR:OPIS2",
            7 => "account_name",
            8 => "account_address",
            9 => "account_address2",
            10 => "account_city",
            11 => "account_group",
//            12 => "PAR:MBS",
//            13 => "PAR:NASBROJ",
            14 => "account_iban",
//            15 => "PAR:ZIRORACUN2",
//            16 => "PAR:ZIRORACUN3",
//            17 => "PAR:ZIRORACUN4",
//            18 => "PAR:ZIRORACUNDEVIZNI",
//            19 => "PAR:IBAN1",
//            20 => "PAR:IBAN2",
//            21 => "PAR:MODELPLACANJA",
//            22 => "PAR:NACINPLACANJA",
//            23 => "PAR:POZIVNABROJ",
            24 => "account_phone",
            25 => "account_fax",
//            26 => "PAR:ZAP",
//            27 => "PAR:BANKA",
//            28 => "PAR:WWW",
            29 => "account_email",
//            30 => "PAR:RABAT",
//            31 => "PAR:RABATDOBAVLJACA",
//            32 => "PAR:FAKTOR",
//            33 => "PAR:ROKPLACANJA",
//            34 => "PAR:ROKPLACANJADOBAVLJACU",
//            35 => "PAR:UDALJENOST",
//            36 => "PAR:LOZINKA",
//            37 => "PAR:KONTOK",
//            38 => "PAR:KONTOD",
//            39 => "PAR:KONTOT",
//            40 => "PAR:KONTOP",
//            41 => "PAR:CASSASCONTO",
//            42 => "PAR:MJESTOTROSKA",
//            43 => "PAR:D1",
//            44 => "PAR:D2",
//            45 => "PAR:D3",
//            46 => "PAR:LIMITPARTNERA",
//            47 => "PAR:NACINOTPREME",
//            48 => "PAR:WEB",
//            49 => "PAR:EDI",
//            50 => "PAR:EDISKLADISTE",
            51 => "address_headquarters",
            52 => "account_is_customer",
            53 => "account_is_supplier",
            54 => "account_is_manufacturer",
//            55 => "PAR:TIPUF",
//            56 => "PAR:TIPIF",
//            57 => "PAR:CJENIK",
//            58 => "PAR:BEZAKCIJE",
//            59 => "PAR:KOMERCIJALIST",
//            60 => "PAR:LIMIT1",
//            61 => "PAR:LIMIT2",
//            62 => "PAR:LIMITDANA1",
//            63 => "PAR:LIMITDANA2",
//            64 => "PAR:OTVORENO1",
//            65 => "PAR:OTVORENO2",
//            66 => "PAR:OTVORENO3",
//            67 => "PAR:OTVORENO4",
//            68 => "PAR:KASNIODDATUMA",
//            69 => "PAR:LONGITUDA",
//            70 => "PAR:LATITUDA",
//            71 => "PAR:HIDE",
//            72 => "PAR:SKRIVENIKOMITENT",
//            73 => "PAR:WEBKUPAC",
//            74 => "PAR:STATUS",
//            75 => "PAR:USERSTRING01",
//            76 => "PAR:USERSTRING02",
//            77 => "PAR:REZERVAIZNOS01",
//            78 => "PAR:REZERVAIZNOS02",
//            79 => "PAR:REZERVAIZNOS03",
//            80 => "PAR:REZERVAIZNOS04",
//            81 => "PAR:REZERVALONG01",
//            82 => "PAR:DEVIZA",
//            83 => "PAR:JAVNANABAVA",
//            84 => "PAR:REZERVASTRING01",
//            85 => "PAR:REZERVASTRING02",
//            86 => "PAR:REZERVASTRING03",
//            87 => "PAR:KREIRAO",
//            88 => "PAR:MIJENJAO",
//            89 => "PAR:DATUMKREIRANJA",
//            90 => "PAR:VRIJEMEKREIRANJA",
//            91 => "PAR:DATUMIZMJENE",
//            92 => "PAR:VRIJEMEIZMJENE",
//            93 => "PAR:NAPOMENA"
        ];

        foreach ($csvData as $data) {
            // Convert missing characters
            $dataRow = str_replace("\"", "", str_replace(array("æ", "è", "ð", "È", "Æ",), array("ć", "č", "đ", "Ć", "Č"), mb_convert_encoding($data[0], "utf-8", "windows-1252")));

            // Explode dataRow with commas that are not inside quotes
            $dataRowExploaded = preg_split("#;#i", $dataRow);

            // Array that will contain single xlsx data row
            $dataRowValue = array();

            $insertedAccountEmails = array();

            if (!preg_match("#^[0-9]+$#i", $dataRowExploaded[0])) {
                continue;
            }

            // Add values to the data row array
            foreach ($csvValues as $key => $value) {

                if (!empty($dataRowExploaded[$key])) {
                    if (in_array($value, [])) {
                        $number = preg_replace("#,#i", ".", $dataRowExploaded[$key]);
                        $dataRowValue[$value] = floatval($number);
                    } else if (in_array($value, ["account_group"])) {
                        $dataRowValue[$value] = intval($dataRowExploaded[$key]);
                    } else if (in_array($value, ["created", "modified"])) {
                        $dataRowValue[$value] = (date("Y-m-d", strtotime(preg_replace("#/#i", "-", $dataRowExploaded[$key]))));
                    } else {
                        $dataRowValue[$value] = $dataRowExploaded[$key];
                    }
                } else {
                    $dataRowValue[$value] = null;
                }
            }

            if (!empty($dataRowValue["account_remote_id"])) {
                $loadedCsvValues[$dataRowValue["account_remote_id"]] = $dataRowValue;
            }
        }

        foreach ($loadedCsvValues as $rowCode => $data) {

            $accRemoteId = $data["account_remote_id"];
            $accAddressAccountRemoteId = $data["address_account_remote_id"];
            $accisLegal = $data["account_is_legal_entity"];
            $accOib = $data["account_oib"];
            $accAccGroup = $data["account_group"];
            $accDescription = $data["account_desc"];
            $accName = $data["account_name"];
            $accAddress1 = $data["account_address"];
            $accAddress2 = $data["account_address2"];
            $accIban = $data["account_iban"];
            $accPhone = $data["account_phone"];
            $accFax = $data["account_fax"];
            $accEmail = $data["account_email"];
            $accHeadquarters = $data["address_headquarters"];
            $accIsCustomer = $data["account_is_customer"];
            $accIsSupplier = $data["account_is_supplier"];
            $accIsManufacturer = $data["account_is_manufacturer"];
            $accCityRemoteId = $data["account_city"];

            $name = explode(' ', $accName);
            $firstName = $name[0];
            $lastName = (isset($name[count($name) - 1])) ? $name[count($name) - 1] : '';

            $isLegalEntity = 0;
            $billing = 0;

            // This is an old way od deciding if the address for given is headquarters or not
//            if (!empty($accisLegal)) {
//                $isLegalEntity = 1;
//                $billing = 1;
//            }

//            if(!isset($accHeadquarters) && !empty($accHeadquarters)){
//                $isLegalEntity = 1;
//                $billing = 1;
//            }

            if (!empty($accisLegal)) {
                $isLegalEntity = 0;
            } else {
                $isLegalEntity = 1;
            }

            // If row has empty $accAddressAccountRemoteId then add it to the account_entity and address_entity (this includes account_location)
            if (empty($accAddressAccountRemoteId) || $accAddressAccountRemoteId == 0) {

                /**
                 * account_entity insert
                 */
                $accountInserArray = $this->getEntityDefaults($this->asAccount);

//                $accountInserArray["code"] = $rowCode;
                $accountInserArray["name"] = $accName;
                $accountInserArray["phone"] = $accPhone;
                $accountInserArray["fax"] = $accFax;
                $accountInserArray["oib"] = $accOib;
                $accountInserArray["description"] = $accDescription;
                $accountInserArray["email"] = $accEmail;
                $accountInserArray["is_active"] = 1;
                $accountInserArray["is_legal_entity"] = $isLegalEntity;
                $accountInserArray["remote_id"] = $rowCode;

                $accountInserArray["first_name"] = null;
                $accountInserArray["last_name"] = null;
                if (empty($isLegalEntity) || $isLegalEntity == 0) {
                    $accountInserArray["first_name"] = $firstName;
                    $accountInserArray["last_name"] = $lastName;
                }

                $accountInserArray["account_group_id"] = null;
                if (!empty($accAccGroup)) {
                    if (isset($existingAccountGroups[$accAccGroup])) {
                        $accAccGroupId = $existingAccountGroups[$accAccGroup]["id"];
                        $accountInserArray["account_group_id"] = $accAccGroupId;
                    }
                }

                if (!isset($existingAccounts[$rowCode])) {
                    $insertArray["account_entity"][$rowCode] = $accountInserArray;
                } else {
                    // Updating account_entity
                    $existingAccount = $existingAccounts[$accRemoteId];
                    $accountUpdateArray = [];

                    if ($existingAccount["name"] != $accountInserArray["name"]) {
                        $accountUpdateArray["name"] = $accountInserArray["name"];

                        if (empty($isLegalEntity) || $isLegalEntity == 0) {
                            $name = explode(' ', $accountUpdateArray["name"]);
                            $firstName = $name[0];
                            $lastName = (isset($name[count($name) - 1])) ? $name[count($name) - 1] : '';

                            $accountUpdateArray["first_name"] = $firstName;
                            $accountUpdateArray["last_name"] = $lastName;
                        }
                    }

                    if ($existingAccount["phone"] != $accountInserArray["phone"]) {
                        $accountUpdateArray["phone"] = $accountInserArray["phone"];
                    }

                    if ($existingAccount["fax"] != $accountInserArray["fax"]) {
                        $accountUpdateArray["fax"] = $accountInserArray["fax"];
                    }

                    if ($existingAccount["oib"] != $accountInserArray["oib"]) {
                        $accountUpdateArray["oib"] = $accountInserArray["oib"];
                    }

                    if ($existingAccount["is_legal_entity"] != $accountInserArray["is_legal_entity"]) {
                        $accountUpdateArray["is_legal_entity"] = $accountInserArray["is_legal_entity"];
                    }

                    if (!isset($accountInserArray["account_group_id"]) && !empty($accountInserArray["account_group_id"])) {
                        if ($existingAccount["account_group_id"] != $accountInserArray["account_group_id"]) {
                            $accountUpdateArray["account_group_id"] = $accountInserArray["account_group_id"];
                        }
                    }

                    if (!empty($accountUpdateArray)) {
                        $accountUpdateArray["modified"] = "NOW()";
                        $updateArray["account_entity"][$existingAccount["id"]] = $accountUpdateArray;
                    }
                }
            }

            if (!empty($accAddressAccountRemoteId) && $accAddressAccountRemoteId != 0) {
                $accRemoteId = $accAddressAccountRemoteId;
            }

            /**
             * address_entity insert
             */
            $accountAddress = NULL;
            if (!empty($accAddress1)) {
                $accountAddress = $accAddress1;
            } else if (empty($accAddress1) && !empty($accAddress2)) {
                $accountAddress = $accAddress2;
            }

            $headquarters = 0;
//                $billing = 0;
            if (!empty($accHeadquarters)) {
                $headquarters = 1;
//                    $billing = 1;
            }

            $addressInsertArray = $this->getEntityDefaults($this->asAddress);
            $addressInsertArray["headquarters"] = $headquarters;
            $addressInsertArray["billing"] = $headquarters;
            // WE NEED THEIR CITIES TABLE EXPORT TO LINK city_id IN THE EXPORT FILE AND cities TABLE
//            $addressInsertArray["city_id"] = $cityId;
            $addressInsertArray["street"] = $accountAddress;
            $addressInsertArray["phone"] = $accPhone;
            $addressInsertArray["name"] = $accName;
            $addressInsertArray["remote_id"] = $rowCode;

            if (empty($isLegalEntity) || $isLegalEntity == 0) {
                $accountInserArray["first_name"] = $firstName;
                $accountInserArray["last_name"] = $lastName;
            }

            if (isset($existingAccounts[$accRemoteId])) {
                $accountId = $existingAccounts[$accRemoteId]["id"];
                $addressInsertArray["account_id"] = $accountId;
            } else {
                $addressInsertArray["filter_insert"]["account_remote_id"] = $accRemoteId;
            }

            // If city_remote_id is not empty
            if (!empty($camelotCsvCities["city"][$accCityRemoteId]) && isset($camelotCsvCities["city"][$accCityRemoteId])) {

                // If postal_code exists within the database
                if (isset($existingCities[$camelotCsvCities["city"][$accCityRemoteId]["postal_code"]])) {
                    $addressInsertArray["city_id"] = $existingCities[$camelotCsvCities["city"][$accCityRemoteId]["postal_code"]]["id"];
                } else {
                    $cityInsertArray = $this->getEntityDefaults($this->asCity);

                    $cityInsertArray["name"] = $camelotCsvCities["city"][$accCityRemoteId]["name"];
                    $cityInsertArray["postal_code"] = $camelotCsvCities["city"][$accCityRemoteId]["postal_code"];
//
                    $cityInsertArray["country_id"] = null;
                    if (isset($camelotCsvCities["country"][substr($accCityRemoteId, 0, 2)]["postal_code"])) {

                        if (isset($existingCountries[$camelotCsvCities["country"][substr($accCityRemoteId, 0, 2)]["postal_code"]])) {
                            $cityInsertArray["country_id"] = $existingCountries[$camelotCsvCities["country"][substr($accCityRemoteId, 0, 2)]["postal_code"]]["id"];
                        }
                    }

                    $cityInsertArray["region_id"] = null;
                    if (isset($camelotCsvCities["region"][substr($accCityRemoteId, 0, 4)])) {
                        $regionKey = preg_replace("# #i", "", preg_replace("#županija|zupanija|(\.)*žup(\.)*|(\.)*zup(\.)*#i", "", strtolower($camelotCsvCities["region"][substr($accCityRemoteId, 0, 4)]["name"])));

                        if ($regionKey == "zadarsko-kninska") {
                            $regionKey = "zadarska";
                        } else if ($regionKey == "sisačko-moslovačka") {
                            $regionKey = "sisačko-moslavačka";
                        } else if ($regionKey == "Šibenska") {
                            $regionKey = "Šibensko-kninska";
                        }

                        if (isset($existingRegions[$regionKey])) {
                            $cityInsertArray["region_id"] = $existingRegions[$regionKey]["id"];
                        }
                    }

                    $addressInsertArray["filter_insert"]["city_postal_code"] = $camelotCsvCities["city"][$accCityRemoteId]["postal_code"];

                    $insertArray["city_entity"][$camelotCsvCities["city"][$accCityRemoteId]["postal_code"]] = $cityInsertArray;
                }
            }  else {
                $addressInsertArray["city_id"] = NULL;
            }

            if (!isset($existingAddresses[$rowCode])) {
                $insertArray2["address_entity"][$rowCode] = $addressInsertArray;
            } else {
                // Updating address_entity
                $existingAddress = $existingAddresses[$rowCode];
                $addressUpdateArray = [];

                if ($existingAddress["name"] != $addressInsertArray["name"]) {
                    $addressUpdateArray["name"] = $addressInsertArray["name"];

                    if (empty($isLegalEntity) || $isLegalEntity == 0) {
                        $name = explode(' ', $addressInsertArray["name"]);
                        $firstName = $name[0];
                        $lastName = (isset($name[count($name) - 1])) ? $name[count($name) - 1] : '';

                        $addressUpdateArray["first_name"] = $firstName;
                        $addressUpdateArray["last_name"] = $lastName;
                    }
                }

                if ($existingAddress["headquarters"] != $addressInsertArray["headquarters"]) {
                    $addressUpdateArray["headquarters"] = $addressInsertArray["headquarters"];
                    $addressUpdateArray["billing"] = $addressInsertArray["headquarters"];
                }

                if ($existingAddress["street"] != $addressInsertArray["street"]) {
                    $addressUpdateArray["street"] = $addressInsertArray["street"];
                }

                if ($existingAddress["phone"] != $addressInsertArray["phone"]) {
                    $addressUpdateArray["phone"] = $addressInsertArray["phone"];
                }

                if (!empty($addressInsertArray["city_id"])) {
                    if ($existingAddress["city_id"] != $addressInsertArray["city_id"]) {
                        $addressUpdateArray["city_id"] = $addressInsertArray["city_id"];
                    }
                }

                if (!empty($addressUpdateArray)) {
                    $addressUpdateArray["modified"] = "NOW()";
                    $updateArray["address_entity"][$existingAddress["id"]] = $addressUpdateArray;
                }
            }

            /**
             * account_location_entity insert
             */
            $accountLocationsInsertArray = $this->getEntityDefaults($this->asAccountLocation);

            $accountLocationsInsertArray["name"] = $accName;
            $accountLocationsInsertArray["remote_id"] = $rowCode;

            if (isset($existingAccounts[$accRemoteId])) {
                $accountLocationsInsertArray["account_id"] = $existingAccounts[$accRemoteId]["id"];
            } else {
                $accountLocationsInsertArray["filter_insert"]["account_remote_id"] = $accRemoteId;
            }

            if (isset($existingAccountLocations[$rowCode])) {
                $accountLocationsInsertArray["address_id"] = $existingAccountLocations[$rowCode]["id"];
            } else {
                $accountLocationsInsertArray["filter_insert"]["address_remote_id"] = $rowCode;
            }

            if (!isset($existingAccountLocations[$rowCode])) {

                $insertArray3["account_location_entity"][$rowCode] = $accountLocationsInsertArray;
            } else {
                // Updating account_location_entity
                $existingAccountLocation = $existingAccountLocations[$rowCode];
                $accountLocationUpdateArray = [];

//                if (isset($accountLocationsInsertArray["account_id"])) {
//                    if ($existingAccountLocation["account_id"] != $accountLocationsInsertArray["account_id"]) {
//                        $accountLocationUpdateArray["account_id"] = $accountLocationsInsertArray["account_id"];
//                    }
//                }
//
//                if (isset($accountLocationsInsertArray["address_id"])) {
//                    if ($existingAccountLocation["address_id"] != $accountLocationsInsertArray["address_id"]) {
//                        $accountLocationUpdateArray["address_id"] = $accountLocationsInsertArray["address_id"];
//                    }
//                }
//
//                if (!empty($accountLocationUpdateArray)) {
//                    $accountLocationsInsertArray["modified"] = "NOW()";
//                    $updateArray["account_location_entity"][$existingAccountLocation["id"]] = $accountLocationUpdateArray;
//                }
            }

            /**
             * account_type_link_entity insert
             */
            $accountTypeLinksToBeInserted = array();

            if (isset($accIsCustomer) && !empty($accIsCustomer)) {
                $accountTypeLinkKey = $accRemoteId . "_3";
                $accountTypeLinksToBeInserted[$accountTypeLinkKey] = array("accountRemoteId" => $accRemoteId, "accountType" => 3);
            }

            if (isset($accIsSupplier) && !empty($accIsSupplier)) {
                $accountTypeLinkKey = $accRemoteId . "_10";
                $accountTypeLinksToBeInserted[$accountTypeLinkKey] = array("accountRemoteId" => $accRemoteId, "accountType" => 10);
            }

            if (isset($accIsManufacturer) && !empty($accIsManufacturer)) {
                $accountTypeLinkKey = $accRemoteId . "_13";
                $accountTypeLinksToBeInserted[$accountTypeLinkKey] = array("accountRemoteId" => $accRemoteId, "accountType" => 13);
            }

            foreach ($accountTypeLinksToBeInserted as $accountTypeLinkKey => $accountTypeLinkValues) {

                if (!isset($existingAccountTypeLinks[$accountTypeLinkKey])) {
                    $accountTypeLinkInsertArray = $this->getEntityDefaults($this->asAccountTypeLink);
                    $accountTypeLinkInsertArray["account_type_id"] = $accountTypeLinkValues["accountType"];

                    if (isset($existingAccounts[$accountTypeLinkValues["accountRemoteId"]])) {
                        $accountTypeLinkInsertArray["account_id"] = $existingAccounts[$accountTypeLinkValues["accountRemoteId"]]["id"];
                    } else {
                        $accountTypeLinkInsertArray["filter_insert"]["account_remote_id"] = $accountTypeLinkValues["accountRemoteId"];
                    }

                    $insertArray3["account_type_link_entity"][$accountTypeLinkKey] = $accountTypeLinkInsertArray;
                }
            }
        }

        /**
         * Inserting accounts
         */
        $insertQuery = $this->getInsertQuery($insertArray);
        if (!empty($insertQuery)) {
            $this->logQueryString($insertQuery);
            $this->databaseContext->executeNonQuery($insertQuery);
        }

        /**
         * Inserting addresses
         */
        $reselectArray["account_entity"] = $this->getExistingAccounts("remote_id", $accountColumns);
        $insertArray2 = $this->filterImportArray($insertArray2, $reselectArray);
        $insertQuery2 = $this->getInsertQuery($insertArray2);
        if (!empty($insertQuery2)) {
            $this->logQueryString($insertQuery2);
            $this->databaseContext->executeNonQuery($insertQuery2);
        }

        /**
         * Inserting account_locations,
         *          account_type_links
         */
        $reselectArray["account_entity"] = $this->getExistingAccounts("remote_id", $accountColumns);
        $reselectArray["address_entity"] = $this->getExistingAddresses();
        $insertArray3 = $this->filterImportArray($insertArray3, $reselectArray);
        $insertQuery3 = $this->getInsertQuery($insertArray3);
        if (!empty($insertQuery3)) {
            $this->logQueryString($insertQuery3);
            $this->databaseContext->executeNonQuery($insertQuery3);
        }

        /**
         * Update entities
         */
        dump($updateArray);
        $updateQuery = $this->getUpdateQuery($updateArray);
        if (!empty($updateQuery)) {
            $this->logQueryString($updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }
        unset($updateArray);

        print("Finished import!\n");
    }

    public function importCamelotContactsFromCsv($filePath)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $showOnStore = [];
        foreach ($this->allStores as $store) {
            $showOnStore[$store] = 1;
        }

        //Columns which will be expored from the database
        $accountColumns = array(
            "id",
            "remote_id",
            "name",
            "first_name",
            "last_name",
            "phone",
            "email",
            "oib",
            "description",
            "fax",
            "is_legal_entity"
        );

        $existingAccounts = $this->getExistingAccounts("remote_id", $accountColumns);
        $existingAddresses = $this->getExistingAddresses();
        $existingContacts = $this->getExistingContacts();
        $allCities = $this->getExistingCities();

        $insertArray = array(
            "contact_entity" => array()
        );

        $updateArray = array();

        print("Starting import!\n");
        $csvData = $this->parseCsv($filePath);

        $csvValues = [
            0 => "contact_remote_id",
            1 => "contact_account_remote_id",
//                2 => "IME:BROJ",
//                3 => "IME:KORISNIK",
//                4 => "IME:TELEFON",
            5 => "contact_first_name",
            6 => "contact_last_name",
//                7 => "IME:INICIJAL",
//                8 => "IME:NADIMAK",
//                9 => "IME:SPOL",
//                10 => "IME:ZANIMANJE",
//                11 => "IME:PRIORITET",
//                12 => "IME:FUNKCIJA",
            13 => "contact_address1",
            14 => "contact_address2",
            15 => "contact_city",
            16 => "contact_postal_code",
//                17 => "IME:WWW",
            18 => "contact_email",
//                19 => "IME:TITULA",
            20 => "contact_jmbg",
            21 => "contact_oib",
//                22 => "IME:OSOBNAISKAZNICA",
//                23 => "IME:PUTOVNICA",
//                24 => "IME:VOZACKADOZVOLA",
//                25 => "IME:LOYALTYKARTICA",
//                26 => "IME:IZNOSPROMETAKARTICE",
//                27 => "IME:BODOVISTANJE",
//                28 => "IME:BODOVIUKUPNOSKUPLJENI",
//                29 => "IME:BODOVIZADNJEISKORISTENI",
//                30 => "IME:DATUMZADNJEKUPOVINE",
//                31 => "IME:DATUMKORISTENJABODOVA",
//                32 => "IME:DATUMIZDAVANJAKARTICE",
//                33 => "IME:TIPKARTICE",
//                34 => "IME:ZABRANAKORISTENJAKARTICE",
//                35 => "IME:ISKAZNICAPESTICIDA",
//                36 => "IME:REZERVA02",
//                37 => "IME:REZERVA03",
//                38 => "IME:REZERVA04",
//                39 => "IME:REZERVA05",
//                40 => "IME:PRIMAERACUN",
//                41 => "IME:POSTOTAKLOYALTYPOPUSTA",
//                42 => "IME:MJESTORODJENJA",
            43 => "contact_date_of_birth",
            44 => "contact_created",
            45 => "contact_modified",
//                46 => "IME:LOZINKA",
//                47 => "IME:KOMERCIJALIST",
//                48 => "IME:HIDE",
//                49 => "IME:WEB",
//                50 => "IME:WEBKUPAC",
//                51 => "IME:KREIRAO",
//                52 => "IME:MIJENJAO",
//                53 => "IME:NAPOMENA"
        ];

        // Array which will store all values from the import file
        $loadedCsvValues = [];

        foreach ($csvData as $data) {
            // Convert missing characters
            $dataRow = str_replace("\"", "", str_replace(array("æ", "è", "ð", "È", "Æ",), array("ć", "č", "đ", "Ć", "Č"), mb_convert_encoding($data[0], "utf-8", "windows-1252")));

            // Explode dataRow with commas that are not inside quotes
            $dataRowExploaded = preg_split("#;#i", $dataRow);

            // Array that will contain single xlsx data row
            $dataRowValue = array();

            if (!preg_match("#^[0-9]+$#i", $dataRowExploaded[0])) {
                continue;
            }

            // Add values to the data row array
            foreach ($csvValues as $key => $value) {

                if (!empty($dataRowExploaded[$key])) {
                    if (in_array($value, [])) {
                        $number = preg_replace("#,#i", ".", $dataRowExploaded[$key]);
                        $dataRowValue[$value] = floatval($number);
                    } else if (in_array($value, ["contact_remote_id", "contact_account_remote_id"])) {
                        $dataRowValue[$value] = intval($dataRowExploaded[$key]);
                    } else if (in_array($value, ["contact_created", "contact_modified", "contact_date_of_birth"])) {

                        if (preg_match('~[0-9]+~', $dataRowExploaded[$key]) && strtotime($dataRowExploaded[$key])) {
                            $dataRowValue[$value] = (date("Y-m-d", strtotime(preg_replace("#/#i", "-", $dataRowExploaded[$key]))));
                        } else {
                            $dataRowValue[$value] = null;
                        }
                    } else {
                        $dataRowValue[$value] = $dataRowExploaded[$key];
                    }
                } else {
                    $dataRowValue[$value] = null;
                }
            }

            if (!empty($dataRowValue["contact_remote_id"])) {
                $loadedCsvValues[$dataRowValue["contact_remote_id"]] = $dataRowValue;
            }
        }

        foreach ($loadedCsvValues as $rowCode => $data) {

            $conRemoteId = $data["contact_remote_id"];
            $conAccountRemoteId = $data["contact_account_remote_id"];
            $conCityRemoteId = $data["contact_city"];
            $conPostalCode = $data["contact_postal_code"];
            $conJmbg = $data["contact_jmbg"];
            $conOib = $data["contact_oib"];
            $conCreated = $data["contact_created"];
            $conModified = $data["contact_modified"];
            $conDateOfBirth = $data["contact_date_of_birth"];
            $conFirstName = null;
            $conLastName = null;

            if (preg_match("/[a-žA-Ž]/i", $data["contact_first_name"])) {
                $conFirstName = $data["contact_first_name"];
            }

            if (preg_match("/[a-žA-Ž]/i", $data["contact_last_name"])) {
                $conLastName = $data["contact_last_name"];
            }

            $conEmail = null;
            if (filter_var($data["contact_email"], FILTER_VALIDATE_EMAIL)) {
                $conEmail = $data["contact_email"];
            }

            $conAddress = $data["contact_address1"];
            if (empty($conAddress) && !empty($data["contact_address2"])) {
                $conAddress = $data["contact_address2"];
            }

            /**
             * insert contact_entity
             */
            $contactInsertArray = $this->getEntityDefaults($this->asContact);

            $contactInsertArray["first_name"] = $conFirstName;
            $contactInsertArray["last_name"] = $conLastName;
//            $contactInsertArray["city_id"] = $conCityRemoteId;
            $contactInsertArray["email"] = $conEmail;
//            $contactInsertArray["oib"] = $conOib;
            $contactInsertArray["date_of_birth"] = $conDateOfBirth;
            $contactInsertArray["is_active"] = 1;
            $contactInsertArray["remote_id"] = $rowCode;
            $contactInsertArray["full_name"] = $contactInsertArray["first_name"] . " " . $contactInsertArray["last_name"];

            $contactInsertArray["created"] = $conCreated;
            if (strtotime($conModified) !== false) {
                $contactInsertArray["modified"] = $contactInsertArray["created"];
            }

            if (isset($existingAccounts[$conAccountRemoteId])) {
                $contactInsertArray["account_id"] = $existingAccounts[$conAccountRemoteId]["id"];
            } else {
                continue;

                // If account_entity doesn't exist it wont be added
                // I can make an account from a contact row but it will be missing some attribues
                $contactInsertArray["filter_insert"]["account_remote_id"] = $conAccountRemoteId;
            }

            if (!isset($existingContacts[$rowCode])) {
                $insertArray["contact_entity"][$rowCode] = $contactInsertArray;
            } else {

                // Updating contact_entity
                $existingContact = $existingContacts[$rowCode];
                $contactUpdateArray = [];

                if (isset($contactInsertArray["account_id"])) {
                    if ($existingContact["account_id"] != $contactInsertArray["account_id"]) {
                        $contactUpdateArray["account_id"] = $contactInsertArray["account_id"];
                    }
                }

                if ($existingContact["first_name"] != $contactInsertArray["first_name"]) {
                    $contactUpdateArray["first_name"] = $contactInsertArray["first_name"];

                }

                if ($existingContact["last_name"] != $contactInsertArray["last_name"]) {
                    $contactUpdateArray["last_name"] = $contactInsertArray["last_name"];
                }

                if ($existingContact["full_name"] != $contactInsertArray["full_name"]) {
                    $contactUpdateArray["full_name"] = $contactInsertArray["full_name"];
                }

                if ($existingContact["email"] != $contactInsertArray["email"]) {
                    $contactUpdateArray["email"] = $contactInsertArray["email"];
                }

                if (!empty($contactUpdateArray)) {
                    $contactUpdateArray["modified"] = "NOW()";
                    $updateArray["contact_entity"][$existingContact["id"]] = $contactUpdateArray;
                }
            }
        }

        /**
         * Inserting contacts
         */
        $reselectArray["account_entity"] = $this->getExistingAccounts("remote_id", $accountColumns);
        $insertArray = $this->filterImportArray($insertArray, $reselectArray);
        $insertQuery = $this->getInsertQuery($insertArray);
        if (!empty($insertQuery)) {
            $this->logQueryString($insertQuery);
            $this->databaseContext->executeNonQuery($insertQuery);
        }

        /**
         * Update entities
         */
        dump($updateArray);
        $updateQuery = $this->getUpdateQuery($updateArray);
        if (!empty($updateQuery)) {
            $this->logQueryString($updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }
        unset($updateArray);

        print("Finished import");
    }

    public function importCamelotReversesFromCsv($filePath)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $showOnStore = [];
        foreach ($this->allStores as $store) {
            $showOnStore[$store] = 1;
        }

        $accountColumns = array(
            "id",
            "remote_id",
            "name",
            "first_name",
            "last_name",
            "phone",
            "email",
            "oib",
            "description",
            "code",
            "fax",
            "is_legal_entity"
        );

        $humidorColumns = array(
            "id",
            "code",
            "name",
            "account_location_id",
            "product_id",
            "date_of_setup",
            "remote_id"
        );

        $existingHumidors = $this->getExistingEntity("humidor_entity", "code", $humidorColumns);
        $existingAccountLocations = $this->getExistingAccountLocationsByName();
//        $existingAccounts = $this->getExistingEntity("account_entity", "name", $accountColumns, " AND name IS NOT NULL");

        $loadedCsvValues = array();

        $dataRows = [
            ["Broj"],
            ["Datum"],
            ["Komitent"],
            ["Poziv na broj"]
        ];

        $insertArray = array(
            "humidor_entity" => array()
        );

        $updateArray = array();

        print("Starting import!\n");
        $csvData = $this->parseCsv($filePath);

        $csvDataHeadersArray = array_flip(preg_split("#\t#i", str_replace("\"", "", str_replace(array("æ", "è", "ð", "È", "Æ",), array("ć", "č", "đ", "Ć", "Č"), mb_convert_encoding($csvData[0][0], "utf-8", "windows-1252")))));

        $csvValues = [];

        for ($counter = 0; $counter < count($dataRows); $counter++) {
            foreach ($dataRows[$counter] as $header) {

                if (isset($csvDataHeadersArray[$header])) {
                    if ($counter == 0) {
                        $csvValues[$csvDataHeadersArray[$header]] = "humidor_remote_id";
                    } else if ($counter == 1) {
                        $csvValues[$csvDataHeadersArray[$header]] = "humidor_date_of_setup";
                    } else if ($counter == 2) {
                        $csvValues[$csvDataHeadersArray[$header]] = "humidor_account_name";
                    } else if ($counter == 3) {
                        $csvValues[$csvDataHeadersArray[$header]] = "humidor_contact_code";
                    }
                    break;
                }
            }
        }

//        dump($csvValues);die;

        foreach ($csvData as $data) {
            // Convert missing characters
            $dataRow = str_replace("\"", "", str_replace(array("æ", "è", "ð", "È", "Æ",), array("ć", "č", "đ", "Ć", "Č"), mb_convert_encoding($data[0], "utf-8", "windows-1252")));

            // Explode dataRow with commas that are not inside quotes
            $dataRowExploaded = preg_split("#\t#i", $dataRow);

            // Array that will contain single xlsx data row
            $dataRowValue = array();

            if (!preg_match("#•#i", $dataRowExploaded[0])) {
                continue;
            }

            // Add values to the data row array
            foreach ($csvValues as $key => $value) {

                if (!empty($dataRowExploaded[$key])) {
                    if (in_array($value, [])) {
                        $number = preg_replace("#,#i", ".", $dataRowExploaded[$key]);
                        $dataRowValue[$value] = floatval($number);
                    } else if (in_array($value, ["humidor_remote_id"])) {
                        $dataRowValue[$value] = intval($dataRowExploaded[$key]);
                    } else if (in_array($value, ["humidor_date_of_setup"])) {
                        $dataRowValue[$value] = (date("Y-m-d", strtotime(preg_replace("#/#i", "-", $dataRowExploaded[$key]))));
                    } else {
                        $dataRowValue[$value] = $dataRowExploaded[$key];
                    }
                } else {
                    $dataRowValue[$value] = null;
                }
            }

            if (!empty($dataRowValue["humidor_remote_id"])) {
                $loadedCsvValues[$dataRowValue["humidor_remote_id"]] = $dataRowValue;
            }
        }

        foreach ($loadedCsvValues as $data) {

            $humRemoteId = $data["humidor_remote_id"];
            $humDateOfSetup = $data["humidor_date_of_setup"];
            $humAccountName = trim($data["humidor_account_name"]);
            $humRemoteCode = $data["humidor_contact_code"];

            if (isset($existingAccountLocations[strtolower($humAccountName)])) {
                $existingAccountLocationsId = $existingAccountLocations[strtolower($humAccountName)]["id"];

                $humidorInsertArray = $this->getEntityDefaults($this->asHumidor);

                $humidorInsertArray["name"] = $humAccountName;
                $humidorInsertArray["account_location_id"] = $existingAccountLocationsId;
                $humidorInsertArray["date_of_setup"] = $humDateOfSetup;
                $humidorInsertArray["code"] = $humRemoteCode;
                $humidorInsertArray["remote_id"] = $humRemoteId;

                if (!isset($existingHumidors[$humRemoteCode])) {
                    $insertArray["humidor_entity"][$humRemoteCode] = $humidorInsertArray;
                } else {

                    $existingHumidor = $existingHumidors[$humRemoteCode];
                    $humidorUpdateArray = [];

                    if ($existingHumidor["account_location_id"] != $humidorInsertArray["account_location_id"]) {
                        $humidorUpdateArray["account_location_id"] = $humidorInsertArray["account_location_id"];
                    }

                    if ($existingHumidor["name"] != $humidorInsertArray["name"]) {
                        $humidorUpdateArray["name"] = $humidorInsertArray["name"];
                    }

                    if (!empty($humidorInsertArray["date_of_setup"])) {
                        $existingDatebaseDate = (new DateTime($existingHumidor["date_of_setup"]))->format("Y-m-d");

                        if (strval($existingDatebaseDate) != strval($humidorInsertArray["date_of_setup"])) {
                            $humidorUpdateArray["date_of_setup"] = $humidorInsertArray["date_of_setup"];
                        }
                    }

                    if ($existingHumidor["remote_id"] != $humidorInsertArray["remote_id"]) {
                        $humidorUpdateArray["remote_id"] = $humidorInsertArray["remote_id"];
                    }

                    if (!empty($humidorUpdateArray)) {
                        $humidorUpdateArray["modified"] = "NOW()";
                        $updateArray["humidor_entity"][$existingHumidor["id"]] = $humidorUpdateArray;
                    }
                }
            } else {
                dump($humAccountName);
            }
        }

        /**
         * Inserting humidors
         */
        $insertQuery = $this->getInsertQuery($insertArray);
        if (!empty($insertQuery)) {
            $this->logQueryString($insertQuery);
            $this->databaseContext->executeNonQuery($insertQuery);
        }

        /**
         * Update entities
         */
        dump($updateArray);
        $updateQuery = $this->getUpdateQuery($updateArray);
        if (!empty($updateQuery)) {
            $this->logQueryString($updateQuery);
            $this->databaseContext->executeNonQuery($updateQuery);
        }
        unset($updateArray);

        print("Finished import!\n");
    }

    public function getCamelotCitiesFromCsv($filePath)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $showOnStore = [];
        foreach ($this->allStores as $store) {
            $showOnStore[$store] = 1;
        }

        $allCities = $this->getExistingCitiesByPostalCode();

        $loadedCsvValues = array();

        $csvValues = [
//            0 => "Baza",
            1 => "level",
            2 => "remote_id",
            3 => "name",
//            4 => "Opis 1",
//            5 => "Opis 2",
            6 => "postal_code",
//            7 => "Iznos 1",
//            8 => "Iznos 2",
//            9 => "Iznos 3",
//            10 => "Datum",
//            11 => "Kolicina"
        ];

        $insertArray = array(
            "city_entity" => array()
        );

        $updateArray = array();

        print("Starting import!\n");
        $csvData = $this->parseCsv($filePath);

        foreach ($csvData as $data) {
            // Convert missing characters
            $dataRow = str_replace("\"", "", str_replace(array("æ", "è", "ð", "È", "Æ",), array("ć", "č", "đ", "Ć", "Č"), mb_convert_encoding($data[0], "utf-8", "windows-1252")));

            // Explode dataRow with commas that are not inside quotes
            $dataRowExploaded = preg_split("#;#i", $dataRow);

            // Array that will contain single xlsx data row
            $dataRowValue = array();

//            if (!preg_match("#^[0-9]+$#i", $dataRowExploaded[0])) {
//                continue;
//            }

            // Add values to the data row array
            foreach ($csvValues as $key => $value) {
                if (!empty($dataRowExploaded[$key])) {
                    if (in_array($value, ["level"])) {
                        $dataRowValue[$value] = intval($dataRowExploaded[$key]);
                    } else {
                        $dataRowValue[$value] = $dataRowExploaded[$key];
                    }
                } else {
                    $dataRowValue[$value] = null;
                }
            }

            if ($dataRowValue["level"] == 2) {
                $loadedCsvValues["country"][$dataRowValue["remote_id"]] = $dataRowValue;
            } else if ($dataRowValue["level"] == 4 && substr($dataRowValue["remote_id"], 0, 2) == "01") {
                $loadedCsvValues["region"][$dataRowValue["remote_id"]] = $dataRowValue;
            } else if ($dataRowValue["level"] == 6) {
                $loadedCsvValues["city"][$dataRowValue["remote_id"]] = $dataRowValue;
            }
//            if (substr($dataRowValue["remote_id"], 0, 2) == "01") {
//                if (!empty($dataRowValue["remote_id"])) {
//                    $loadedCsvValues[$dataRowValue["remote_id"]] = $dataRowValue;
//                }
//            }
        }

        return $loadedCsvValues;

//        foreach($loadedCsvValues as $data){
//
//            $cityRemoteId = $data["remote_id"];
//            $cityName = $data["name"];
//            $cityPostalCode = $data["postal_code"];
//
//            if(!isset($allCities[$cityPostalCode])){
//                $cityInsertArray = $this->getEntityDefaults($this->asCity);
//
//                $cityInsertArray["name"] = $cityName;
//                $cityPostalCode["postal_code"] = $cityPostalCode;
//            }
//        }
    }

    private
    function insertAttributeIntoProductAttributeLinks($sProductAttributeConfigurationName, $attributeValue, $configurationOptionType, $productEntityRemoteId, $existingSProductAttributeData)
    {

        if (!isset($existingSProductAttributeData["s_product_attribute_configuration"][strtolower($sProductAttributeConfigurationName)])) {

            $existingSProductAttributeData["s_product_attribute_configuration"] = $this->getExistingSProductAttributeConfigurations();

            if (!isset($existingSProductAttributeData["s_product_attribute_configuration"][strtolower($sProductAttributeConfigurationName)])) {
                $sProductAttributeConfigurationArray = $this->getEntityDefaults($this->asSProductAttributeConfiguration);

                $sProductAttributeConfigurationArray["name"] = $sProductAttributeConfigurationName;
                $sProductAttributeConfigurationArray["s_product_attribute_configuration_type_id"] = $configurationOptionType;
                $sProductAttributeConfigurationArray["is_active"] = 1;
                $sProductAttributeConfigurationArray["show_in_filter"] = 1;
                $sProductAttributeConfigurationArray["show_in_list"] = 1;

                $sProductAttributeConfigurationInsertArray["s_product_attribute_configuration_entity"][strtolower($sProductAttributeConfigurationArray["name"])] = $sProductAttributeConfigurationArray;
                $this->databaseContext->executeNonQuery($this->getInsertQuery($sProductAttributeConfigurationInsertArray));
                $sProductAttributeConfigurationInsertArray["s_product_attribute_configuration_entity"] = [];
                $existingSProductAttributeData["s_product_attribute_configuration"] = $this->getExistingSProductAttributeConfigurations();
            }
        }

        $sProductAttributeConfiguration = $existingSProductAttributeData["s_product_attribute_configuration"][strtolower($sProductAttributeConfigurationName)];
        $sProductAttributeConfigurationOption = NULL;

        if (in_array($configurationOptionType, ["1, 2"])) {
            $sProductAttributeConfigurationOptionKey = strtolower($attributeValue) . "_" . $sProductAttributeConfiguration["id"];

            if (!isset($existingSProductAttributeData["s_product_attribute_configuration_options"][$sProductAttributeConfigurationOptionKey])) {

                $existingSProductAttributeData["s_product_attribute_configuration_options"] = $this->getExistingSProductAttributeConfigurationOptions();

                if (!isset($existingSProductAttributeData["s_product_attribute_configuration_options"][$sProductAttributeConfigurationOptionKey])) {
                    $sProductAttributeConfigurationOptionArray = $this->getEntityDefaults($this->asSProductAttributeConfigurationOption);

                    $sProductAttributeConfigurationOptionArray["configuration_value"] = $attributeValue;
                    $sProductAttributeConfigurationOptionArray["configuration_attribute_id"] = $sProductAttributeConfiguration["id"];

                    $sProductAttributeConfigurationOptionInsertArray["s_product_attribute_configuration_options_entity"][$sProductAttributeConfigurationOptionKey] = $sProductAttributeConfigurationOptionArray;
                    $this->databaseContext->executeNonQuery($this->getInsertQuery($sProductAttributeConfigurationOptionInsertArray));
                    $sProductAttributeConfigurationOptionInsertArray["s_product_attribute_configuration_options_entity"] = [];
                }
            }
        }

        $existingSProductAttributeData["s_product_attribute_configuration_options"] = $this->getExistingSProductAttributeConfigurationOptions();

        $sProductAttributeConfigurationOption = $existingSProductAttributeData["s_product_attribute_configuration_options"][$sProductAttributeConfigurationOptionKey];

        if (!empty($sProductAttributeConfigurationOption)) {
            $wandAttributeValueKey = md5($productEntityRemoteId . $sProductAttributeConfiguration["id"] . $sProductAttributeConfigurationOption["id"]);
        } else {
            $wandAttributeValueKey = md5($productEntityRemoteId . $sProductAttributeConfiguration["id"]);
        }

        if (!isset($existingSProductAttributeData["s_product_attribute_link"][$wandAttributeValueKey])) {

            $existingSProductAttributeData["s_product_attribute_link"] = $this->getExistingSProductAttributelinks();

            if (!isset($existingSProductAttributeData["s_product_attribute_link"][$wandAttributeValueKey])) {
                $sProductAttributeLinkArray = $this->getEntityDefaults($this->asSProductAttributeLink);

                $sProductAttributeLinkArray["attribute_value"] = $attributeValue;
                $sProductAttributeLinkArray["configuration_option"] = $sProductAttributeConfigurationOption["id"];
                $sProductAttributeLinkArray["s_product_attribute_configuration_id"] = $sProductAttributeConfiguration["id"];
                $sProductAttributeLinkArray["wand_attribute_value_id"] = $wandAttributeValueKey;
                $sProductAttributeLinkArray["filter_insert"]["product_code"] = $productEntityRemoteId;

                return $sProductAttributeLinkArray;
            }
        }

        return false;
    }


    /**
     * @param string $filePath
     * @return array
     */
    private
    function parseCsv($filePath)
    {
        $data = [];
        $file = fopen($filePath, 'r') or die ("Can't open file {$filePath}");
        while (($line = fgets($file)) !== false) {
            $data[] = explode(self::DELIMITER, $line);
        }
        fclose($file) or die(
        "Can't close file {
                $filePath}"
        );

        return $data;
    }

    /**
     * @param $attributeValue
     * @return json
     */
    private
    function parseValueToJson($attributeValue)
    {
        $attributeArray = array();

        foreach ($this->allStores as $storeId) {
            $attributeArray[$storeId] = $attributeValue;
        }

        return (json_encode($attributeArray, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param $attributeValue
     * @return json
     */
    private
    function parseValueToAssocArray($attributeValue)
    {
        $attributeArray = array();

        foreach ($this->allStores as $storeId) {
            $attributeArray[$storeId] = $attributeValue;
        }

        return $attributeArray;
    }

    /**
     * @param $dbName
     * @param $entity
     * @param $attribute
     * @return array
     */
    private
    function getEntityBySpecificAttribute($dbName, $entity, $attribute)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $query = "SELECT * FROM {$dbName}.{$entity}_entity;";
        $queryData = $this->databaseContext->getAll($query);

        $res = [];
        if (!empty($queryData)) {
            foreach ($queryData as $data) {
                $res[$data[$attribute]] = $data;
            }
        }

        return $res;
    }

    /**
     * @param $dbName
     * @param $entity
     * @param $attribute
     * @return array
     */
    private
    function getEntityBySpecificAttributeReturnLower($dbName, $entity, $attribute)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $query = "SELECT * FROM {$dbName}.{$entity}_entity;";
        $queryData = $this->databaseContext->getAll($query);

        $res = [];
        if (!empty($queryData)) {
            foreach ($queryData as $data) {

                $res[strtolower($data[$attribute])] = $data;
            }
        }

        return $res;
    }

    /**
     * @param string $value
     * @return array
     */
    private
    function returnNullIfEmptyValue($value)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (empty($value)) {
            return "NULL";
        }

        if (is_string($value)) {
            return $this->databaseContext->quote($value);
        } else {
            return $value;
        }
    }

    /**
     * Usage:
     * - for insert queries: provide string with column names and a string with values
     * - for update queries: provide final query as the $values parameter
     *
     * @param $values
     * @param null $columns
     * @return null
     */
    private
    function runImportQuery($values, $columns = NULL)
    {
        if (!empty($values)) {
            $query = $values;
            if (!empty($columns)) {
                $query = $columns . substr($query, 0, -2) . ";\n";
            }

            $this->databaseContext->executeNonQuery($query);
        }
        return null;
    }

    /**
     * @param $dbName
     * @param $entity
     * @param $attribute
     * @return array
     */
    private
    function getAllContacts()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $query = "SELECT
                c.id,
                a.email
            FROM contact_entity c
            JOIN account_entity a ON c.account_id = a.id
            WHERE a.email IS NOT NULL
            AND a.email != '';";
        $queryData = $this->databaseContext->getAll($query);

        $res = [];
        if (!empty($queryData)) {
            foreach ($queryData as $data) {

                $res[$data["email"]] = $data;
            }
        }

        return $res;
    }

    function account_type_link_entity_filter($accountTypeLinkEntity, $reselectArray)
    {
        if (isset($accountTypeLinkEntity["filter_insert"])) {
            if (isset($accountTypeLinkEntity["filter_insert"]["account_remote_id"])) {
                $accountTypeLinkEntity["account_id"] =
                    $reselectArray["account_entity"][$accountTypeLinkEntity["filter_insert"]["account_remote_id"]]["id"];
            }
            unset($accountTypeLinkEntity["filter_insert"]);
        }

        return $accountTypeLinkEntity;
    }

    function account_entity_filter($accountEntity, $reselectArray)
    {
        if (isset($accountEntity["filter_insert"])) {
            if (isset($accountEntity["filter_insert"]["account_group_remote_id"]) && !empty($accountEntity["filter_insert"]["account_group_id"])) {
                $accountEntity["account_group_id"] =
                    $reselectArray["account_group_entity"][$accountEntity["filter_insert"]["account_group_remote_id"]]["id"];
            } else {
                $accountEntity["account_group_id"] = NULL;
            }
            unset($accountEntity["filter_insert"]);
        }

        return $accountEntity;
    }

    function address_entity_filter($addressEntity, $reselectArray)
    {
        if (isset($addressEntity["filter_insert"])) {
            if (isset($addressEntity["filter_insert"]["account_remote_id"])) {
                $addressEntity["account_id"] =
                    $reselectArray["account_entity"][$addressEntity["filter_insert"]["account_remote_id"]]["id"];
            }

            if (isset($addressEntity["filter_insert"]["city_postal_code"])) {
                $addressEntity["city_id"] =
                    $reselectArray["city_entity"][$addressEntity["filter_insert"]["city_postal_code"]]["id"];
            }
            unset($addressEntity["filter_insert"]);
        }

        return $addressEntity;
    }


    function account_location_entity_filter($addressEntity, $reselectArray)
    {
        if (isset($addressEntity["filter_insert"])) {
            if (isset($addressEntity["filter_insert"]["account_remote_id"])) {
                $addressEntity["account_id"] = ($reselectArray["account_entity"][$addressEntity["filter_insert"]["account_remote_id"]])["id"];
            }

            if (isset($addressEntity["filter_insert"]["address_remote_id"])) {
                $addressEntity["address_id"] = ($reselectArray["address_entity"][$addressEntity["filter_insert"]["address_remote_id"]])["id"];
            }
            unset($addressEntity["filter_insert"]);
        }

        return $addressEntity;
    }

    function brand_entity_filter($brandEntity, $reselectArray)
    {

        if (isset($brandEntity["filter_insert"])) {
            if (isset($brandEntity["filter_insert"]["name"])) {
                $brandEntity["name"] = $reselectArray["account_entity"][$brandEntity["filter_insert"]["name"]]["name"];
            }

            unset($brandEntity["filter_insert"]);
        }

        return $brandEntity;
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

    function product_entity_filter($productEntity, $reselectArray)
    {

        if (isset($productEntity["filter_insert"])) {
            if (isset($productEntity["filter_insert"]["brand_remote_id"])) {
                $productEntity["brand_id"] = $reselectArray["brand_entity"][$productEntity["filter_insert"]["brand_remote_id"]]["id"];
            }

            if (isset($productEntity["filter_insert"]["supplier_remote_id"])) {
                if (isset($reselectArray["account_entity"][$productEntity["filter_insert"]["supplier_remote_id"]])) {
                    $productEntity["supplier_id"] = $reselectArray["account_entity"][$productEntity["filter_insert"]["supplier_remote_id"]]["id"];
                }
            }

            unset($productEntity["filter_insert"]);
        }

        return $productEntity;
    }

    function product_group_entity_filter($productGroupEntity, $reselectArray)
    {

        if (isset($productGroupEntity["filter_insert"])) {
            if (isset($productGroupEntity["filter_insert"]["product_group_id"])) {
                $productGroupEntity["brand_id"] = $reselectArray["brand_entity"][$productGroupEntity["filter_insert"]["product_group_id"]]["id"];
            }

            unset($productGroupEntity["filter_insert"]);
        }

        return $productGroupEntity;
    }

    function product_product_group_link_entity_filter($productProductGroupEntity, $reselectArray)
    {

        if (isset($productProductGroupEntity["filter_insert"])) {
            if (isset($productProductGroupEntity["filter_insert"]["product_code"])) {
                $productProductGroupEntity["product_id"] = $reselectArray["product_entity"][$productProductGroupEntity["filter_insert"]["product_code"]]["id"];
            }

            unset($productProductGroupEntity["filter_insert"]);
        }

        return $productProductGroupEntity;
    }

    function s_product_attributes_link_entity_filter($sProductAttributeLinkEntity, $reselectArray)
    {

        if (isset($sProductAttributeLinkEntity["filter_insert"])) {
            if (isset($sProductAttributeLinkEntity["filter_insert"]["product_code"])) {
                $sProductAttributeLinkEntity["product_id"] = $reselectArray["product_entity"][$sProductAttributeLinkEntity["filter_insert"]["product_code"]]["id"];
            }

            unset($sProductAttributeLinkEntity["filter_insert"]);
        }

        return $sProductAttributeLinkEntity;
    }

    function s_route_entity_filter($sProductAttributeLinkEntity, $reselectArray)
    {

        if (isset($sProductAttributeLinkEntity["filter_insert"])) {
            if (isset($sProductAttributeLinkEntity["filter_insert"]["product_code"])) {
                $sProductAttributeLinkEntity["destination_id"] = $reselectArray["product_entity"][$sProductAttributeLinkEntity["filter_insert"]["product_code"]]["id"];
            }

            unset($sProductAttributeLinkEntity["filter_insert"]);
        }

        if (isset($sProductAttributeLinkEntity["filter_insert"])) {
            if (isset($sProductAttributeLinkEntity["filter_insert"]["product_group_remote_id"])) {
                $sProductAttributeLinkEntity["destination_id"] = $reselectArray["product_group_entity"][$sProductAttributeLinkEntity["filter_insert"]["product_group_remote_id"]]["id"];
            }

            unset($sProductAttributeLinkEntity["filter_insert"]);
        }

        return $sProductAttributeLinkEntity;
    }

    public
    function getInsertAddress($accountEmail, $address, $allCities, $remoteId, $isLegal = true)
    {
        $headquarters = 1;
        $billing = 1;

        if ($isLegal == false) {
            $headquarters = 0;
            $billing = 0;
        }

        $street = null;
        $city = str_replace("\"", "", $address);

        $n = strrpos($city, ",");
        if ($n !== false) {
            $street = trim(substr($city, 0, $n));
            $city = trim(substr($city, $n + 1));
        }

        $cityId = NULL;
        if (isset($allCities[strtolower($city)])) {
            $cityId = $allCities[strtolower($city)]["id"];
        }

        $addressInsertArray = $this->getEntityDefaults($this->asAddress);

        $addressInsertArray["street"] = $street;
        $addressInsertArray["filter_insert"]["remoteId"] = $remoteId;
        $addressInsertArray["headquarters"] = $headquarters;
        $addressInsertArray["billing"] = $billing;
        $addressInsertArray["city_id"] = $cityId;
        $addressInsertArray["remote_id"] = $remoteId;

        return $addressInsertArray;
    }

    public
    function getInsertAccountLocation($account, $accountEmail)
    {
        $accountLocationInsertArray = $this->getEntityDefaults($this->asAccountLocation);

        $accountLocationInsertArray["name"] = $account["name"];
        $accountLocationInsertArray["filter_insert"]["account_email"] = $accountEmail;
        $accountLocationInsertArray["filter_insert"]["address_id"] = $account["remote_id"];
        $accountLocationInsertArray["remote_id"] = $account["remote_id"];

        return $accountLocationInsertArray;
    }

    public
    function getInsertAccount($account, $accountEmail, $accountIsLegalEntity)
    {
        $accountInsertArray = $this->getEntityDefaults($this->asAccount);

        $accountInsertArray["phone"] = $account["phone_fax"];
        $accountInsertArray["oib"] = $account["oib"];
        $accountInsertArray["description"] = $account["description"];
        $accountInsertArray["email"] = $accountEmail;
        $accountInsertArray["is_legal_entity"] = $accountIsLegalEntity;
        $accountInsertArray["filter_insert"]["account_group_id"] = $account["account_group"];
        $accountInsertArray["is_active"] = 1;
        $accountInsertArray["name"] = $account["name"];
        $accountInsertArray["remote_id"] = $account["remote_id"];
        $accountInsertArray["first_name"] = null;
        $accountInsertArray["last_name"] = null;

        if (empty($accountInsertArray["oib"])) {
            $name = explode(' ', $accountInsertArray["name"]);
            $firstName = $name[0];
            $lastName = (isset($name[count($name) - 1])) ? $name[count($name) - 1] : '';

            $accountInsertArray["first_name"] = $firstName;
            $accountInsertArray["last_name"] = $lastName;
        }

        return $accountInsertArray;
    }

    /**
     * @param $sortKey
     * @param $columnKeys
     * @param string $additionalWhere
     * @return array
     */
    public
    function getExistingAccounts($sortKey, $columnKeys, $additionalWhere = "")
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
    public
    function getExistingAddresses()
    {
        $q = "SELECT
                id,
                remote_id,
                first_name,
                last_name,
                street,
                name,
                phone,
                city_id,
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
    public
    function getExistingAccountTypeLinks()
    {
        $q = "SELECT
                atl.id,
                atl.account_id,
                atl.account_type_id,
                a.remote_id,
                a.code
            FROM account_type_link_entity AS atl
            INNER JOIN account_entity AS a ON a.id = atl.account_id
            WHERE atl.entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $attributekey = $d["code"] . "_" . $d["account_type_id"];
            $ret[$attributekey] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public
    function getExistingAccountGroups()
    {
        $q = "SELECT
                id,
                remote_id
            FROM account_group_entity
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
    public
    function getExistingAccountLocations()
    {
        $q = "SELECT
                id,
                address_id,
                account_id,
                remote_id
            FROM account_location_entity
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
    public
    function getExistingContacts()
    {
        $q = "SELECT
                id,
                remote_id,
                first_name,
                last_name,
                email,
                full_name,
                account_id
            FROM contact_entity
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
    public
    function getExistingBrands()
    {
        $q = "SELECT
                id,
                name,
                remote_id
            FROM brand_entity
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
    public
    function getExistingProducts()
    {
        $q = "SELECT
                id,
                name,
                price_base,
                price_retail,
                qty,
                currency_id,
                active,
                product_type_id,
                is_visible,
                brand_id,
                code,
                remote_id,
                ean
            FROM product_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d["code"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public
    function getExistingSProductAttributeConfigurations()
    {
        $q = "SELECT
                id,
                name,
                remote_id
            FROM s_product_attribute_configuration_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[strtolower($d["name"])] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public
    function getExistingSProductAttributeConfigurationOptions()
    {
        $q = "SELECT
                id,
                configuration_attribute_id,
                configuration_value,
                remote_id
            FROM s_product_attribute_configuration_options_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[strtolower($d["configuration_value"]) . "_" . $d["configuration_attribute_id"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public
    function getExistingSProductAttributelinks()
    {
        $q = "SELECT
                id,
                s_product_attribute_configuration_id,
                attribute_value,
                configuration_option,
                attribute_value_key,
                product_id,
                wand_attribute_value_id
            FROM s_product_attributes_link_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d["wand_attribute_value_id"]] = $d;
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
                name,
                postal_code
            FROM city_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[strtolower($d["name"])] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingRegions()
    {
        $q = "SELECT
                id,
                name,
                country_id
            FROM region_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[strtolower($d["name"])] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public
    function getExistingCitiesByPostalCode()
    {
        $q = "SELECT
                id,
                name,
                postal_code
            FROM city_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[strtolower($d["postal_code"])] = $d;
        }

        return $ret;
    }


    /**
     * @return array
     */
    public
    function getExistingProductGroupSRoutes()
    {
        $q = "SELECT
                id,
                request_url,
                destination_type,
                destination_id,
                store_id
            FROM s_route_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[strtolower($d["request_url"] . "_" . $d["store_id"])] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingSRouteUrls($sortKey, $columnKeys, $additionalWhere = "")
    {
        if (!empty($columnKeys)) {
            $columnKeys = implode(",", $columnKeys);
        } else {
            $columnKeys = "*";
        }

        $q = "SELECT
                {$columnKeys}
            FROM s_route_entity
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
    public
    function getExistingSRouteProducts()
    {
        $q = "SELECT
                sr.id,
                sr.destination_id,
                p.code
            FROM s_route_entity AS sr
            INNER JOIN product_entity AS p ON sr.destination_id = p.id
            WHERE sr.entity_state_id = 1 AND sr.destination_type LIKE 'product';";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d["code"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public
    function getExistingSRouteProductGroups()
    {
        $q = "SELECT
                sr.id,
                sr.destination_id,
                p.code
            FROM s_route_entity AS sr
            INNER JOIN product_group_entity AS p ON sr.destination_id = p.id
            WHERE sr.entity_state_id = 1 AND sr.destination_type LIKE 'product_group';";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[$d["code"]] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public
    function getExistingProductGroups($sortKey, $columnKeys, $additionalWhere = "")
    {
        if (!empty($columnKeys)) {
            $columnKeys = implode(",", $columnKeys);
        } else {
            $columnKeys = "*";
        }

        $q = "SELECT
                {$columnKeys}
            FROM product_group_entity
            WHERE entity_state_id = 1
                {$additionalWhere};";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[($d[$sortKey])] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public
    function getExistingProductProductGroupLinks()
    {
        $q = "SELECT
                ppgl.id,
                ppgl.product_id,
                ppgl.product_group_id,
                p.code
            FROM product_product_group_link_entity AS ppgl
            INNER JOIN product_entity AS p ON p.id = ppgl.product_id
            WHERE ppgl.entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $key = $d["product_group_id"] . "-" . $d["code"];
            $ret[($key)] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public
    function getTaxTypes($sortKey, $columnKeys, $additionalWhere = "")
    {
        if (!empty($columnKeys)) {
            $columnKeys = implode(",", $columnKeys);
        } else {
            $columnKeys = "*";
        }

        $q = "SELECT
                {$columnKeys}
            FROM tax_type_entity
            WHERE entity_state_id = 1
                {$additionalWhere};";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $ret[intval($d[$sortKey])] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingEntity($entity, $sortKey, $columnKeys, $additionalWhere = "")
    {
        if (!empty($columnKeys)) {
            $columnKeys = implode(",", $columnKeys);
        } else {
            $columnKeys = "*";
        }

        $q = "SELECT
                {$columnKeys}
            FROM {$entity}
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
    public function getExistingHumidors()
    {
        $q = "SELECT
                id,
                name,
                account_location_id,
                date_of_setup,
                product_id
            FROM humidor_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $key = $d["account_location_id"] . "_" . $d["date_of_setup"];
            $ret[($key)] = $d;
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getExistingAccountLocationsByName()
    {
        $q = "SELECT
                id,
                name,
                address_id,
                account_id
            FROM account_location_entity
            WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        $ret = array();
        foreach ($data as $d) {
            $key = strtolower(trim($d["name"]));
            $ret[($key)] = $d;
        }

        return $ret;
    }
}
