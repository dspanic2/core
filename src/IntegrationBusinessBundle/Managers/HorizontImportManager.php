<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\ExcelManager;
use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Constants\CrmConstants;
use AppBundle\Models\InsertModel;
use AppBundle\Models\UpdateModel;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderItemEntity;
use Symfony\Component\Console\Helper\ProgressBar;

class HorizontImportManager extends DefaultIntegrationImportManager
{
    private $apiUrl;
    private $apiUsername;
    private $apiPassword;
    private $supplierId;
    private $companyVatId;
    private $deliveryProductRemoteCode;
    private $sampleProductRemoteCode;

    /** @var ExcelManager $excelManager */
    protected $excelManager;
    /** @var AttributeSet $asCurrency */
    protected $asCurrency;
    /** @var AttributeSet $asCountry */
    protected $asCountry;
    /** @var AttributeSet $asCity */
    protected $asCity;
    /** @var AttributeSet $asSProductAttributeConfigurationOptions */
    protected $asSProductAttributeConfigurationOptions;
    /** @var AttributeSet $asSProductAttributesLink */
    protected $asSProductAttributesLink;
    /** @var AttributeSet $asProduct */
    protected $asProduct;
    /** @var AttributeSet $asAccount */
    protected $asAccount;
    /** @var AttributeSet $asAccountBank */
    protected $asAccountBank;
    /** @var AttributeSet $asContact */
    protected $asContact;
    /** @var AttributeSet $asAddress */
    protected $asAddress;
    /** @var AttributeSet $asSRoute */
    protected $asSRoute;

    private $productInsertAttributes;
    private $productUpdateAttributes;
    private $productCustomAttributes;
    private $accountInsertAttributes;
    private $accountUpdateAttributes;
    private $contactInsertAttributes;
    private $contactUpdateAttributes;
    private $addressInsertAttributes;
    private $addressUpdateAttributes;

    const STOCK_TYPE_VELEPRODAJA = 3;
    const STOCK_TYPE_MALOPRODAJA = 5;
    const STOCK_TYPE_WEBSHOP = 8;

    public function initialize()
    {
        parent::initialize();

        $this->apiUrl = $_ENV["HORIZONT_API_URL"];
        $this->apiUsername = $_ENV["HORIZONT_API_USERNAME"];
        $this->apiPassword = $_ENV["HORIZONT_API_PASSWORD"];
        $this->supplierId = $_ENV["HORIZONT_SUPPLIER_ID"];
        $this->companyVatId = $_ENV["HORIZONT_VAT_ID"];
        $this->deliveryProductRemoteCode = $_ENV["HORIZONT_DELIVERY_PRODUCT_REMOTE_CODE"];
        $this->sampleProductRemoteCode = $_ENV["HORIZONT_SAMPLE_PRODUCT_REMOTE_CODE"];

        $this->productInsertAttributes = $this->getAttributesFromEnv("HORIZONT_PRODUCT_INSERT_ATTRIBUTES");
        $this->productUpdateAttributes = $this->getAttributesFromEnv("HORIZONT_PRODUCT_UPDATE_ATTRIBUTES");
        $this->productCustomAttributes = $this->getAttributesFromEnv("HORIZONT_PRODUCT_CUSTOM_ATTRIBUTES", false);

        $this->accountInsertAttributes = $this->getAttributesFromEnv("HORIZONT_ACCOUNT_INSERT_ATTRIBUTES");
        $this->accountUpdateAttributes = $this->getAttributesFromEnv("HORIZONT_ACCOUNT_UPDATE_ATTRIBUTES");
        $this->contactInsertAttributes = $this->getAttributesFromEnv("HORIZONT_CONTACT_INSERT_ATTRIBUTES");
        $this->contactUpdateAttributes = $this->getAttributesFromEnv("HORIZONT_CONTACT_UPDATE_ATTRIBUTES");
        $this->addressInsertAttributes = $this->getAttributesFromEnv("HORIZONT_ADDRESS_INSERT_ATTRIBUTES");
        $this->addressUpdateAttributes = $this->getAttributesFromEnv("HORIZONT_ADDRESS_UPDATE_ATTRIBUTES");

        if (!file_exists($this->getImportDir())) {
            mkdir($this->getImportDir());
        }

        $this->setRemoteSource("horizont");

        $this->asCurrency = $this->entityManager->getAttributeSetByCode("currency");
        $this->asCountry = $this->entityManager->getAttributeSetByCode("country");
        $this->asCity = $this->entityManager->getAttributeSetByCode("city");
        $this->asSProductAttributeConfigurationOptions = $this->entityManager->getAttributeSetByCode("s_product_attribute_configuration_options");
        $this->asSProductAttributesLink = $this->entityManager->getAttributeSetByCode("s_product_attributes_link");
        $this->asProduct = $this->entityManager->getAttributeSetByCode("product");
        $this->asAccount = $this->entityManager->getAttributeSetByCode("account");
        $this->asAccountBank = $this->entityManager->getAttributeSetByCode("account_bank");
        $this->asContact = $this->entityManager->getAttributeSetByCode("contact");
        $this->asAddress = $this->entityManager->getAttributeSetByCode("address");
        $this->asSRoute = $this->entityManager->getAttributeSetByCode("s_route");
    }

    /**
     * @param $method
     * @param $endpoint
     * @param $body
     * @param $filePath
     * @return mixed
     * @throws \Exception
     */
    private function getApiResponse($method, $endpoint, $body, $filePath)
    {
        if ($filePath) {
            $filePath = $this->getImportDir() . $filePath;
            if ($this->getDebug() && file_exists($filePath)) {
                $data = file_get_contents($filePath);
            }
        }

        $code = 200;

        if (!isset($data)) {
            $restManager = new RestManager();
            $restManager->CURLOPT_HTTPHEADER = [
                "Authorization: Basic " . base64_encode($this->apiUsername . ":" . $this->apiPassword)
            ];
            $restManager->CURLOPT_CUSTOMREQUEST = $method;
            $restManager->CURLOPT_POST = ($method == "POST");
            if (!empty($body)) {
                $restManager->CURLOPT_POSTFIELDS = json_encode($body);
                $restManager->CURLOPT_HTTPHEADER[] = "Content-Type: application/json";
            }
            $data = $restManager->get($this->apiUrl . $endpoint, false);
            $code = $restManager->code;
            if (!empty($data) && $filePath) {
                //file_put_contents($filePath, $data);
            }
        }

        if (empty($data)) {
            throw new \Exception("Response is empty");
        }
        if ($code != 200) {
            throw new \Exception(sprintf("%s request error: %u, %s", $endpoint, $code, $data));
        }

        $data = json_decode($data, true);

        return $data;
    }

    /**
     * @return mixed
     */
    public function getCompanyVatId()
    {
//        $data = $this->getApiResponse("GET", "companies", null, null);
//
//        return $data[0]["VAT_ID"];

        return $this->companyVatId;
    }

    /**
     * @param $orderData
     * @return bool
     * @throws \Exception
     */
    public function sendOrder($orderData)
    {
        $response = $this->getApiResponse("POST", "order/send", $orderData, null);
        if ($response != "Success") {
            throw new \Exception("Response code is 200 but response is: " . $response);
        }

        return true;
    }

    /**
     * @param $filePath
     * @return array
     */
    private function horizontXlsxToArray($filePath)
    {
        if (empty($this->excelManager)) {
            $this->excelManager = $this->container->get("excel_manager");
        }

        $colNames = [
            "Šifra" => "ID",
            "Naziv" => "Name",
            "Adresa" => "Address",
            "Mjesto" => "City",
            "Pošta" => "Zip_Code",
            "Država" => "Country",
            "OIB" => "VAT_ID",
            "IBAN" => "IBAN",
            "Kontakt" => "Contact_Person",
            "Telefon" => "Phone",
            "email" => "Email"
        ];

        $ret = [];

        $data = $this->excelManager->importEntityArray($filePath);
        if (!empty($data) && isset($data["Sheet1"]) && !empty($data["Sheet1"])) {
            foreach ($data["Sheet1"] as $rowId => $cols) {
                $ret[$rowId] = [
                    "Country_Code" => null,
                    "Delivery_Name" => null,
                    "Delivery_Address" => null,
                    "Delivery_Zip_Code" => null,
                    "Delivery_City" => null,
                    "Delivery_Country" => null,
                    "Delivery_Country_Code" => null,
                    "Notes" => null,
                    "Delivery_Method" => null,
                    "Delivery_Method_Description" => null
                ];
                foreach ($cols as $colName => $value) {
                    $value = trim($value);
                    if ($value == "NULL") {
                        $value = null;
                    }
                    if (isset($colNames[$colName])) {
                        $ret[$rowId][$colNames[$colName]] = $value;
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * @param string $xlsxPath
     * @return array
     * @throws \Exception
     */
    public function importCustomers($xlsxPath = "")
    {
        echo "Importing customers...\n";

        if (!empty($xlsxPath)) {
            $data = $this->horizontXlsxToArray($xlsxPath);
        } else {
            $data = $this->getApiResponse("GET", "customers", null, "horizont_customers.json");
        }

        $accountSelectColumns = [
            "id",
            "name",
            "first_name",
            "last_name",
            "description",
            "phone",
            "phone_2",
            "home_phone",
            "oib",
            "email",
            "code",
            "is_active"
        ];
        $contactSelectColumns = [
            "id",
            "full_name",
            "first_name",
            "last_name",
            "phone",
            "phone_2",
            "home_phone",
            "email",
            "remote_id"
        ];
        $addressSelectColumns = [
            "a1.id",
            "a1.name",
            "a1.first_name",
            "a1.last_name",
            "a1.street",
            "a1.city_id",
            "a2.code AS account_code"
        ];

        /**
         * Get existing items
         */
        $existingAccounts = $this->getEntitiesArray($accountSelectColumns, "account_entity", ["code"]);
        $existingAccountsByEmail = $this->getEntitiesArray($accountSelectColumns, "account_entity", ["email"], "", "WHERE email IS NOT NULL AND email != ''");
        $existingAccountBanks = $this->getEntitiesArray(["id", "iban"], "account_bank_entity", ["iban"]);
        $existingContacts = $this->getEntitiesArray($contactSelectColumns, "contact_entity", ["remote_id"]);
        $existingContactsByEmail = $this->getEntitiesArray($contactSelectColumns, "contact_entity", ["email"], "", "WHERE email IS NOT NULL AND email != ''");
        $existingAddresses = $this->getEntitiesArray($addressSelectColumns, "address_entity", ["account_code"], "JOIN account_entity a2 ON a1.account_id = a2.id", "WHERE a1.entity_state_id = 1 AND a2.entity_state_id = 1 AND a2.code IS NOT NULL AND a2.code != '' AND a1.headquarters = 1 AND a1.billing = 1");
        $existingCities = $this->getEntitiesArray(["id", "name", "postal_code"], "city_entity", ["postal_code"]);
        $existingCountries = $this->getEntitiesArray(["id", "api_id"], "country_entity", ["api_id"]);

        /**
         * Prepare import arrays
         */
        $insertArray = [
            // account_entity
            // city_entity
        ];
        $insertArray2 = [
            // contact_entity
            // account_bank_entity
        ];
        $insertArray3 = [
            // address_entity
        ];
        $updateArray = [
            // account_entity
            // contact_entity
            // address_entity
        ];

        $accountEmails = [];
        $contactEmails = [];

        foreach ($existingAccounts as $existingAccount) {
            if ($existingAccount["is_active"]) {
                $accountUpdate = new UpdateModel($existingAccount);
                $accountUpdate->add("is_active", false, false);
                $updateArray["account_entity"][$accountUpdate->getEntityId()] = $accountUpdate->getArray();
            }
        }

        $progressBar = new ProgressBar($this->getConsoleOutput(), count($data));

        foreach ($data as $d) {

            $progressBar->advance();

            $email = $d["Email"];
            $code = trim($d["ID"]);
            $name = trim($d["Name"]);
            $vatId = trim($d["VAT_ID"]);
            $address = trim($d["Address"]);
            $postalCode = trim($d["Zip_Code"]);
            $city = trim($d["City"]);
            $countryCode = trim($d["Country_Code"]);
            $phone = trim($d["Phone"]);
            $description = trim($d["Notes"]);
            $iban = isset($d["IBAN"]) ? trim($d["IBAN"]) : NULL;

            $isLegalEntity = false;
            if (!empty($vatId)) {
                $isLegalEntity = true;
            }

            $firstName = $lastName = $phone2nd = $homePhone = NULL;

            if (!$isLegalEntity && strpos($name, " ") !== false) {
                $nameArray = explode(" ", $name, 2);
                $firstName = $nameArray[0];
                $lastName = $nameArray[1];
            }
            if (strpos($phone, ",") !== false) {
                $phoneArray = explode(",", $phone);
                $phone = trim($phoneArray[0]);
                $phone2nd = trim($phoneArray[1]);
                $homePhone = trim($phoneArray[2] ?? NULL);
            }

            /**
             * Insert/update account
             */
            if (!isset($existingAccounts[$code])) {

                if (!empty($email) && (isset($existingAccountsByEmail[$email]) || in_array($email, $accountEmails))) {
                    echo sprintf("Skipped account due to duplicate %s value\n", $email);
                    continue;
                }

                $accountInsert = new InsertModel($this->asAccount,
                //$this->accountInsertAttributes
                );

                $accountInsert->add("code", $code)
                    ->add("name", $name)
                    ->add("first_name", $firstName)
                    ->add("last_name", $lastName)
                    ->add("description", $description)
                    ->add("phone", $phone)
                    ->add("phone_2", $phone2nd)
                    ->add("home_phone", $homePhone)
                    ->add("oib", $vatId)
                    ->add("is_active", 1)
                    ->add("is_legal_entity", $isLegalEntity)
                    ->add("email", $email);

                $insertArray["account_entity"][$code] = $accountInsert->getArray();

                if (!empty($email)) {
                    $accountEmails[] = $email;
                }

            } else {

                $accountUpdate = new UpdateModel($existingAccounts[$code],
                //$this->accountUpdateAttributes
                );

                unset($updateArray["account_entity"][$accountUpdate->getEntityId()]);

                $accountUpdate->add("code", $code)
                    ->add("name", $name)
                    ->add("first_name", $firstName)
                    ->add("last_name", $lastName)
                    ->add("description", $description)
                    ->add("phone", $phone)
                    ->add("phone_2", $phone2nd)
                    ->add("home_phone", $homePhone)
                    ->add("oib", $vatId)
                    ->add("is_active", 1);

                if (!empty($accountUpdate->getArray())) {
                    $updateArray["account_entity"][$accountUpdate->getEntityId()] = $accountUpdate->getArray();
                }
            }

            /**
             * Insert/update contact
             */
            if (!$isLegalEntity) {

                if (!isset($existingContacts[$code])) {

                    if (!empty($email) && (isset($existingContactsByEmail[$email]) || in_array($email, $contactEmails))) {
                        echo sprintf("Skipped contact due to duplicate %s value\n", $email);
                        continue;
                    }

                    $contactInsert = new InsertModel($this->asContact,
                    //$this->contactInsertAttributes
                    );

                    $contactInsert->add("full_name", $name)
                        ->add("first_name", $firstName)
                        ->add("last_name", $lastName)
                        ->add("is_active", 1)
                        ->add("phone", $phone)
                        ->add("phone_2", $phone2nd)
                        ->add("home_phone", $homePhone)
                        ->add("email", $email)
                        ->add("remote_id", $code)
                        ->add("account_id", NULL);

                    if (!isset($existingAccounts[$code])) {
                        $contactInsert->addLookup("account_id", $code, "account_entity"); // code
                    } else {
                        $contactInsert->add("account_id", $existingAccounts[$code]["id"]);
                    }

                    $insertArray2["contact_entity"][$code] = $contactInsert;

                    if (!empty($email)) {
                        $contactEmails[] = $email;
                    }

                } else {

                    $contactUpdate = new UpdateModel($existingContacts[$code],
                    //$this->contactUpdateAttributes
                    );

                    $contactUpdate->add("full_name", $name)
                        ->add("first_name", $firstName)
                        ->add("last_name", $lastName)
                        ->add("phone", $phone)
                        ->add("phone_2", $phone2nd)
                        ->add("home_phone", $homePhone);

                    if (!empty($contactUpdate->getArray())) {
                        $updateArray["contact_entity"][$contactUpdate->getEntityId()] = $contactUpdate->getArray();
                    }
                }
            }

            if (!empty($iban) && !isset($existingAccountBanks[$iban])) {

                $accountBankInsert = new InsertModel($this->asAccountBank);
                $accountBankInsert->add("iban", $iban)
                    ->add("account_id", NULL);

                if (!isset($existingAccounts[$code])) {
                    $accountBankInsert->addLookup("account_id", $code, "account_entity");
                } else {
                    $accountBankInsert->add("account_id", $existingAccounts[$code]["id"]);
                }

                $insertArray2["account_bank_entity"][$iban] = $accountBankInsert;
            }

            if (!empty($postalCode) && !isset($existingCities[$postalCode])) {

                $cityInsert = new InsertModel($this->asCity);
                $cityInsert->add("name", $city)
                    ->add("postal_code", $postalCode)
                    ->add("country_id", NULL);

                if (!empty($countryCode) && isset($existingCountries[$countryCode])) {
                    $cityInsert->add("country_id", $existingCountries[$countryCode]["id"]);
                }

                $insertArray["city_entity"][$postalCode] = $cityInsert->getArray();
            }

            if (!isset($existingAddresses[$code])) {

                $addressInsert = new InsertModel($this->asAddress,
                //$this->addressInsertAttributes
                );

                $addressInsert->add("name", $name)
                    ->add("headquarters", 1)
                    ->add("billing", 1)
                    ->add("street", $address)
                    ->add("first_name", $firstName)
                    ->add("last_name", $lastName)
                    ->add("city_id", NULL)
                    ->add("account_id", NULL)
                    ->add("contact_id", NULL);

                if (!empty($postalCode)) {
                    if (!isset($existingCities[$postalCode])) {
                        $addressInsert->addLookup("city_id", $postalCode, "city_entity"); // postal_code
                    } else {
                        $addressInsert->add("city_id", $existingCities[$postalCode]["id"]);
                    }
                }

                if (!isset($existingAccounts[$code])) {
                    $addressInsert->addLookup("account_id", $code, "account_entity"); // code
                } else {
                    $addressInsert->add("account_id", $existingAccounts[$code]["id"]);
                }

                if (!$isLegalEntity) {
                    if (!isset($existingContacts[$code])) {
                        $addressInsert->addLookup("contact_id", $code, "contact_entity"); // code
                    } else {
                        $addressInsert->add("contact_id", $existingContacts[$code]["id"]);
                    }
                }

                $insertArray3["address_entity"][$code] = $addressInsert;

            } else {

                $addressUpdate = new UpdateModel($existingAddresses[$code],
                //$this->addressUpdateAttributes
                );

                $addressUpdate->add("name", $name)
                    ->add("street", $address)
                    ->add("first_name", $firstName)
                    ->add("last_name", $lastName);

                if (!empty($postalCode)) {
                    if (!isset($existingCities[$postalCode])) {
                        $addressUpdate->addLookup("city_id", $postalCode, "city_entity"); // postal_code
                    } else if ($existingAddresses[$code]["city_id"] != $existingCities[$postalCode]["id"]) {
                        $addressUpdate->add("city_id", $existingCities[$postalCode]["id"]);
                    }
                }

                if (!empty($addressUpdate->getArray())) {
                    $updateArray["address_entity"][$addressUpdate->getEntityId()] = $addressUpdate;
                }
            }
        }

        $progressBar->finish();
        echo "\n";

        unset($existingAccounts);
        unset($existingAccountBanks);
        unset($existingContacts);
        unset($existingAddresses);
        unset($existingCities);
        unset($existingCountries);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $reselectArray["account_entity"] = $this->getEntitiesArray($accountSelectColumns, "account_entity", ["code"]);
        $reselectArray["city_entity"] = $this->getEntitiesArray(["id", "name", "postal_code"], "city_entity", ["postal_code"]);

        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);

        $reselectArray["contact_entity"] = $this->getEntitiesArray($contactSelectColumns, "contact_entity", ["remote_id"]);

        $insertArray3 = $this->resolveImportArray($insertArray3, $reselectArray);
        $this->executeInsertQuery($insertArray3);
        unset($insertArray3);

        $updateArray = $this->resolveImportArray($updateArray, $reselectArray);
        $this->executeUpdateQuery($updateArray);
        unset($updateArray);
        unset($reselectArray);

        echo "Importing customers complete\n";

        return [];
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importProducts()
    {
        echo "Importing products...\n";

        $data = $this->getApiResponse("GET", "products/null", null, "horizont_products.json");

        $productSelectArray = [
            "id",
            "name",
            "code",
            "price_base",
            "price_retail",
            "price_purchase",
            "discount_price_base",
            "qty",
            "active",
            "ean",
            "currency_id",
            "tax_type_id"
        ];

        /**
         * Existing arrays
         */
        $existingProducts = $this->getExistingProducts("remote_id", $productSelectArray);
        $existingTaxTypes = $this->getExistingTaxTypes("name", ["id"]);
        $existingCurrencies = $this->getExistingCurrencies("code", ["id"]);
        $existingSRoutes = $this->getExistingSRoutes(" AND destination_type = 'product' ");

        $existingSProductAttributeConfigurationsByFilterKey = $this->getExistingSProductAttributeConfigurations("filter_key", ["id"]);
        $existingSProductAttributeConfigurationOptions = $this->getExistingSProductAttributeConfigurationOptions(null, ["id"], ["configuration_attribute_id", "configuration_value"]);
        $existingSProductLinks = $this->getExistingSProductAttributesLinks("attribute_value_key", ["id"]);

        /**
         * Prepare import arrays
         */
        $insertArray = [
            // product_entity
            // s_product_attribute_configuration_options_entity
        ];
        $insertArray2 = [
            // s_route_entity
            // s_product_attributes_link_entity
        ];
        $updateArray = [
            // product_entity
        ];

        $productIds = [];
        $productRemoteIds = [];

        /**
         * za s product_linkove
         * ručno sam dodao unit u bazu
         */
        $unitConfigurationId = $existingSProductAttributeConfigurationsByFilterKey["unit"]["id"] ?? null;
        unset($existingSProductAttributeConfigurationsByFilterKey);

        foreach ($existingProducts as $existingProduct) {
            if ($existingProduct["active"]) {
                $productUpdate = new UpdateModel($existingProduct);
                $productUpdate->add("active", false, false)
                    ->add("date_synced", "NOW()", false);
                $updateArray["product_entity"][$existingProduct["id"]] = $productUpdate->getArray();
                $productIds[] = $existingProduct["id"];
            }
        }

        /**
         * Filtriranje artikala jer ima duplikata (postoji web, mp i vp proizvod)
         * maloprodaja se ne gleda
         */
        $filteredProducts = array();
        foreach ($data as $d) {
            if ((int)$d["Stock_ID"] !== self::STOCK_TYPE_MALOPRODAJA) {
                $filteredProducts[trim($d["ID"])][(int)$d["Stock_ID"]] = $d;
            }
        }

        $progressBar = new ProgressBar($this->getConsoleOutput(), count($data));

        foreach ($filteredProducts as $remoteId => $productArray) {

            $progressBar->advance();

            if (!isset($productArray[self::STOCK_TYPE_WEBSHOP])) {
                continue;
            }

            /**
             * Podaci o proizvodu
             * Iz emaila: Na shop ćemo učitati sve šifre sa skladišta WebShop (id=8), cijene sa skladišta WebShop (id=8),
             * a njihove količine sa skladišta Veleprodaja (id=3).
             */
            $name = trim($productArray[self::STOCK_TYPE_WEBSHOP]["Name"]);
            $ean = trim($productArray[self::STOCK_TYPE_WEBSHOP]["Barcode"]);

            $qty = 0.0;
            if (isset($productArray[self::STOCK_TYPE_VELEPRODAJA]["Quantity"])) {
                $qty = (float)$productArray[self::STOCK_TYPE_VELEPRODAJA]["Quantity"];
            }
            if ($qty < 0.0) {
                $qty = 0.0;
            }

            $unitOfMeasure = mb_strtolower(trim($productArray[self::STOCK_TYPE_WEBSHOP]["Unit_Of_Measure"]));
            $currencyCode = strtolower(trim($productArray[self::STOCK_TYPE_WEBSHOP]["Currency"]));
            $taxTypeName = "PDV" . (int)$productArray[self::STOCK_TYPE_WEBSHOP]["Tax_Rate"];
            $priceRetail = $productArray[self::STOCK_TYPE_WEBSHOP]["Price"]; // MPC
            $taxRatio = bcdiv(bcadd("100", (int)$productArray[self::STOCK_TYPE_WEBSHOP]["Tax_Rate"], 2), "100", 2);
            $priceBase = bcdiv($priceRetail, $taxRatio, 4); // VPC

            if (!empty($unitConfigurationId) && !empty($unitOfMeasure)) {

                $optionId = NULL;
                $optionSortKey = $unitConfigurationId . "_" . $unitOfMeasure;

                if (!isset($existingSProductAttributeConfigurationOptions[$optionSortKey]) &&
                    !isset($insertArray["s_product_attribute_configuration_options_entity"][$optionSortKey])) {
                    $sProductAttributeConfigurationOptionsInsert = new InsertModel($this->asSProductAttributeConfigurationOptions);
                    $sProductAttributeConfigurationOptionsInsert->add("configuration_attribute_id", $unitConfigurationId)
                        ->add("configuration_value", $unitOfMeasure);
                    $insertArray["s_product_attribute_configuration_options_entity"][$optionSortKey] =
                        $sProductAttributeConfigurationOptionsInsert->getArray();

                } else if (isset($existingSProductAttributeConfigurationOptions[$optionSortKey])) {
                    $optionId = $existingSProductAttributeConfigurationOptions[$optionSortKey]["id"];
                }

                if (!empty($optionId) && isset($existingProducts[$remoteId])) {
                    $attributeValueKey = md5($existingProducts[$remoteId]["id"] . $unitConfigurationId . $optionId);
                    if (!isset($existingSProductLinks[$attributeValueKey])) {
                        $sProductAttributesLinkInsert = new InsertModel($this->asSProductAttributesLink);
                        $sProductAttributesLinkInsert->add("product_id", $existingProducts[$remoteId]["id"])
                            ->add("s_product_attribute_configuration_id", $unitConfigurationId)
                            ->add("attribute_value", $unitOfMeasure)
                            ->add("configuration_option", $optionId)
                            ->add("attribute_value_key", $attributeValueKey);
                        $insertArray2["s_product_attributes_link_entity"][] = $sProductAttributesLinkInsert->getArray();
                    }
                }
            }

            /**
             * Prepare json strings
             */
            $nameArray = array();
            $showOnStoreArray = array();
            $urlArray = array();

            foreach ($this->getStores() as $storeId) {

                $nameArray[$storeId] = $name;
                $showOnStoreArray[$storeId] = 1;

                /**
                 * Insert product s route
                 */
                if (!isset($existingProducts[$remoteId])) {

                    $i = 1;
                    $url = $key = $this->routeManager->prepareUrl($name);
                    while (isset($existingSRoutes[$storeId . "_" . $url]) || isset($insertArray2["s_route_entity"][$storeId . "_" . $url])) {
                        $url = $key . "-" . $i++;
                    }

                    $insertArray2["s_route_entity"][$storeId . "_" . $url] =
                        $this->getSRouteInsertEntity($url, "product", $storeId, $remoteId); // remote_id

                    $urlArray[$storeId] = $url;
                }
            }

            $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);
            $showOnStoreJson = json_encode($showOnStoreArray, JSON_UNESCAPED_UNICODE);
            $urlJson = json_encode($urlArray, JSON_UNESCAPED_UNICODE);

            /**
             * PROIZVOD
             */
            if (!isset($existingProducts[$remoteId])) {

                $productInsert = new InsertModel($this->asProduct,
                    $this->productInsertAttributes,
                    $this->productCustomAttributes);

                $productInsert->add("date_synced", "NOW()")
                    ->add("remote_id", $remoteId) // ovo je duplikat code-a
                    ->add("remote_source", $this->getRemoteSource())
                    ->add("name", $nameJson)
                    ->add("ean", $ean)
                    ->add("code", $remoteId)
                    ->add("meta_title", $nameJson)
                    ->add("meta_description", $nameJson)
                    //->insert("description", $descriptionJson) // prazno
                    //->insert("meta_keywords", $metaKeywordsJson) // prazno
                    ->add("show_on_store", $showOnStoreJson)
                    ->add("price_base", $priceBase)
                    ->add("price_retail", $priceRetail)
                    ->add("active", 1)
                    ->add("url", $urlJson)
                    ->add("supplier_id", $this->supplierId)
                    ->add("qty", $qty)
                    ->add("qty_step", 1)
                    ->add("tax_type_id", 3)
                    ->add("currency_id", $_ENV["DEFAULT_CURRENCY"])
                    ->add("product_type_id", CrmConstants::PRODUCT_TYPE_SIMPLE)
                    ->add("ord", 100)
                    ->add("is_visible", true)
                    ->add("template_type_id", 5)
                    ->add("auto_generate_url", true)
                    ->add("keep_url", true)
                    ->add("show_on_homepage", false)
                    ->add("content_changed", true);

                if (!empty($taxTypeName) && isset($existingTaxTypes[$taxTypeName])) {
                    $productInsert->add("tax_type_id", $existingTaxTypes[$taxTypeName]["id"]);
                }
                if (!empty($currencyCode) && isset($existingCurrencies[$currencyCode])) {
                    $productInsert->add("currency_id", $existingCurrencies[$currencyCode]["id"]);
                }

                $insertArray["product_entity"][$remoteId] = $productInsert->getArray();

                $productRemoteIds[] = $remoteId;

            } else {

                $productUpdate = new UpdateModel($existingProducts[$remoteId],
                    $this->productUpdateAttributes);

                $productId = $existingProducts[$remoteId]["id"];
                unset($updateArray["product_entity"][$productId]);

                $k = array_search($productId, $productIds);
                if ($k !== false) {
                    unset($productIds[$k]);
                }

                $productUpdate->add("name", $nameJson)
                    ->add("meta_title", $nameJson)
                    ->add("meta_description", $nameJson)
                    ->add("code", $remoteId)
                    ->add("ean", $ean)
                    ->add("active", true);

                if (!empty($taxTypeName) && isset($existingTaxTypes[$taxTypeName])) {
                    $productUpdate->add("tax_type_id", $existingTaxTypes[$taxTypeName]["id"]);
                }
                if (!empty($currencyCode) && isset($existingCurrencies[$currencyCode])) {
                    $productUpdate->add("currency_id", $existingCurrencies[$currencyCode]["id"]);
                }

                $productUpdate->addFloat("qty", $qty)
                    ->addFloat("price_base", $priceBase)
                    ->addFloat("price_retail", $priceRetail);

                if (!empty($productUpdate->getArray())) {
                    $productUpdate->add("date_synced", "NOW()", false);
                    if (!empty(array_intersect(array_keys($productUpdate->getArray()), $this->triggerContentChangesArray))) {
                        $productUpdate->add("content_changed", true, false);
                    }
                    $updateArray["product_entity"][$productId] = $productUpdate->getArray();
                    if (!empty(array_intersect(array_keys($productUpdate->getArray()), $this->triggerChangesArray))) {
                        $productIds[] = $productId;
                    }
                }
            }
        }

        $progressBar->finish();
        echo "\n";

        unset($existingProducts);
        unset($existingTaxTypes);
        unset($existingCurrencies);
        unset($existingSRoutes);
        unset($existingSProductAttributeConfigurationsByFilterKey);
        unset($existingSProductAttributeConfigurationOptions);
        unset($existingSProductLinks);

        /**
         * Insert products and s_attribute_configuration_options
         */
        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        /**
         * Update products
         */
        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $reselectArray["product_entity"] = $this->getExistingProducts("remote_id", ["id"]);
        $reselectArray["s_product_attribute_configuration_options_entity"] = $this->getExistingSProductAttributeConfigurationOptions(null, ["id"], ["configuration_attribute_id", "configuration_value"]);

        /**
         * Insert product ruta i s produkt linkova
         */
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

    /**
     * Default implementation which should be overridden in CrmProcessManager
     *
     * @param OrderEntity $order
     * @return array
     * @throws \Exception
     */
    public function prepareOrderPost(OrderEntity $order)
    {
        $data = [
            "Company_VAT_ID" => $this->getCompanyVatId(),
            "Date_Time" => (new \DateTime())->format("Y-m-d\TH:i:s.uP"),
            "Number" => $order->getIncrementId(),
            "Payment_ID" => $order->getPaymentType()->getRemoteCode(),
            "Customer" => $this->getCustomerData($order),
            //"Exchange_Rate" => 4.0,
            //"Discount" => 5.0,
            //"Description" => "sample string 6",
            //"Transaction_Number" => "sample string 7",
            //"Salesperson" => "sample string 8",
            //"Salesperson_VAT_ID" => "sample string 9"
        ];

        /** @var OrderItemEntity $orderItem */
        foreach ($order->getOrderItems() as $orderItem) {
            if (in_array($orderItem->getProduct()->getProductTypeId(), [CrmConstants::PRODUCT_TYPE_CONFIGURABLE, CrmConstants::PRODUCT_TYPE_CONFIGURABLE_BUNDLE]) ||
                (floatval($orderItem->getPriceTotal()) == 0 && substr($orderItem->getCode(), 0, 3) !== "bu-")) {
                continue;
            }
            $data["Order_Items"][] = $this->getOrderItemData($orderItem);
        }

        if ((float)$order->getPriceDeliveryTotal() > 0.0) {
            $data["Order_Items"][] = [
                "Description" => "Dostava",
                "Discount" => "0.0000",
                "ID" => $this->deliveryProductRemoteCode,
                "Name" => "",
                "Barcode" => "",
                "Quantity" => "1.0000",
                "Unit_Of_Measure" => "",
                "Price" => round($orderItem->getOrder()->getPriceDeliveryTotal(), 2),
                "Currency" => $orderItem->getOrder()->getCurrency()->getCode(),
                "Stock_ID" => "",
                "Stock_Name" => "",
                "Stock_Location" => "",
                "Cost_Price" => null,
                "Wholesale_Price" => null,
                "Retail_Price" => null,
                "Tax_Rate" => "0.0000"
            ];
        }

        return $data;
    }

    /**
     * @param OrderItemEntity $orderItem
     * @return array
     */
    private function getOrderItemData(OrderItemEntity $orderItem)
    {
        if (substr($orderItem->getCode(), 0, 3) === "bu-") {
            return [
                "Description" => $orderItem->getName(),
                "Discount" => "1.0000",
                "ID" => $this->sampleProductRemoteCode,
                "Name" => "",
                "Barcode" => "",
                "Quantity" => "1.0000",
                "Unit_Of_Measure" => "",
                "Price" => "1.0000",
                "Currency" => $orderItem->getOrder()->getCurrency()->getCode(),
                "Stock_ID" => "",
                "Stock_Name" => "",
                "Stock_Location" => "",
                "Cost_Price" => null,
                "Wholesale_Price" => null,
                "Retail_Price" => null,
                "Tax_Rate" => $orderItem->getTaxType()->getPercent()
            ];
        }

        $discount = "0.0000";
        if(floatval($orderItem->getPriceDiscountItem()) > 0){
            $discount = floatval($orderItem->getPriceDiscountItem());
        }
        elseif(floatval($orderItem->getPriceDiscountCouponItem()) > 0){
            $discount = floatval($orderItem->getPriceDiscountCouponItem());
        }


        return [
            "Description" => "",
            "Discount" => $discount * $orderItem->getQty(), //$orderItem->getPercentageDiscount(),
            "ID" => (string)$orderItem->getProduct()->getRemoteId(),
            "Name" => $orderItem->getName(),
            "Barcode" => $orderItem->getProduct()->getEan(),
            "Quantity" => $orderItem->getQty(),
            "Unit_Of_Measure" => $this->getProductUnit($orderItem->getProductId()),
            "Price" => round(floatval($orderItem->getOriginalBasePriceItem()), 2),
            "Currency" => $orderItem->getOrder()->getCurrency()->getCode(),
            "Stock_ID" => self::STOCK_TYPE_VELEPRODAJA,
            "Stock_Name" => "Veleprodaja 1", // "WebShop",
            "Stock_Location" => "Branjin Vrh",
            "Cost_Price" => null,
            "Wholesale_Price" => null,
            "Retail_Price" => null,
            "Tax_Rate" => $orderItem->getTaxType()->getPercent()
        ];
    }

    /**
     * @param OrderEntity $order
     * @return array
     * @throws \Exception
     */
    private function getCustomerData(OrderEntity $order)
    {
        $billingCountryApiId = $shippingCountryApiId = null;
        if (!empty($order->getAccountBillingCity()) &&
            !empty($order->getAccountBillingCity()->getCountry()) &&
            $order->getAccountBillingCity()->getCountry()->getApiId() != null) {
            $billingCountryApiId = $order->getAccountBillingCity()->getCountry()->getApiId();
        }

        if ($billingCountryApiId == null) {
            throw new \Exception("API ID for this country is not defined");
        }

        if (!empty($order->getAccountShippingCity()) &&
            !empty($order->getAccountShippingCity()->getCountry()) &&
            $order->getAccountShippingCity()->getCountry()->getApiId() != null) {
            $shippingCountryApiId = $order->getAccountShippingCity()->getCountry()->getApiId();
        } else {
            $shippingCountryApiId = $billingCountryApiId;
        }

        $accountName = $order->getContact()->getFullName();
        if (!empty($order->getAccount()->getIsLegalEntity())) {
            $accountName = $order->getAccount()->getName();
        }

        return [
            "ID" => $order->getContact()->getAccountId(),
            "Name" => $accountName,
            "VAT_ID" => $order->getContact()->getAccount()->getOib(),
            "Address" => $order->getAccountBillingStreet(),
            "Zip_Code" => $order->getAccountBillingCity()->getPostalCode(),
            "City" => $order->getAccountBillingCity()->getName(),
            "Country" => $order->getAccountBillingCity()->getCountry()->getName()[$order->getStoreId()],
            "Country_Code" => $billingCountryApiId,
            "Phone" => $order->getAccountPhone(),
            "Email" => $order->getAccountEmail(),
            "Delivery_Name" => $order->getContact()->getFullName(),
            "Delivery_Address" => $order->getAccountShippingStreet(),
            "Delivery_Zip_Code" => $order->getAccountShippingCity()->getPostalCode(),
            "Delivery_City" => $order->getAccountShippingCity()->getName(),
            "Delivery_Country" => $order->getAccountShippingCity()->getCountry()->getName()[$order->getStoreId()],
            "Delivery_Country_Code" => $shippingCountryApiId,
            "Notes" => $order->getMessage(),
            "Contact_Person" => $order->getContact()->getFullName(),
            "Delivery_Method" => "",//$order->getDeliveryType()->getRemoteId(),
            "Delivery_Method_Description" => $order->getDeliveryType()->getName()[$order->getStoreId()]
        ];
    }

    /**
     * @param $productId
     * @return mixed|string
     */
    private function getProductUnit($productId)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT
                attribute_value
            FROM s_product_attributes_link_entity spal
            JOIN s_product_attribute_configuration_entity spac ON spal.s_product_attribute_configuration_id = spac.id
            AND spac.filter_key = 'unit'
            JOIN product_entity p ON spal.product_id = p.id
            WHERE spal.product_id = '{$productId}';";

        $ret = "";

        $data = $this->databaseContext->getAll($q);
        if (isset($data[0]["attribute_value"]) && !empty($data[0]["attribute_value"])) {
            $ret = $data[0]["attribute_value"];
        }

        return $ret;
    }
}
