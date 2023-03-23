<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\RestManager;
use AppBundle\Models\InsertModel;
use AppBundle\Models\UpdateModel;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderItemEntity;
use Symfony\Component\Console\Helper\ProgressBar;

class MinimaxImportManager extends DefaultIntegrationImportManager
{
    private $clientId;
    private $clientSecret;
    private $apiUsername;
    private $apiPassword;
    private $apiUrl;
    private $apiToken;
    private $apiTokenValidUntil;
    private $organisationId;
    private $productInsertAttributes;
    private $productUpdateAttributes;

    /** @var RestManager $minimaxRestManager */
    private $minimaxRestManager;

    /** @var AttributeSet $asSRoute */
    protected $asSRoute;
    /** @var AttributeSet $asProduct */
    protected $asProduct;

    public function initialize()
    {
        parent::initialize();

        $this->clientId = $_ENV["MINIMAX_CLIENT_ID"];
        $this->clientSecret = $_ENV["MINIMAX_CLIENT_SECRET"];
        $this->apiUsername = $_ENV["MINIMAX_API_USERNAME"];
        $this->apiPassword = $_ENV["MINIMAX_API_PASSWORD"];
        $this->apiUrl = $_ENV["MINIMAX_API_URL"];
        $this->organisationId = $_ENV["MINIMAX_ORGANISATION_ID"];

        $this->productInsertAttributes = $this->getAttributesFromEnv("MINIMAX_PRODUCT_INSERT_ATTRIBUTES");
        $this->productUpdateAttributes = $this->getAttributesFromEnv("MINIMAX_PRODUCT_UPDATE_ATTRIBUTES");

        $this->minimaxRestManager = new RestManager();

        $this->setRemoteSource("minimax");

        $this->asSRoute = $this->entityManager->getAttributeSetByCode("s_route");
        $this->asProduct = $this->entityManager->getAttributeSetByCode("product");
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    private function getToken()
    {
        if (!empty($this->apiToken) && (new \DateTime() < $this->apiTokenValidUntil)) {
            return $this->apiToken;
        }

        $body = [
            "grant_type" => "password",
            "client_id" => $this->clientId,
            "client_secret" => $this->clientSecret,
            "username" => $this->apiUsername,
            "password" => $this->apiPassword,
            "scope" => "minimax.si"
        ];

        $this->minimaxRestManager->CURLOPT_POST = 1;
        $this->minimaxRestManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->minimaxRestManager->CURLOPT_POSTFIELDS = http_build_query($body);
        $this->minimaxRestManager->CURLOPT_HTTPHEADER = [
            "Content-Type: application/x-www-form-urlencoded"
        ];

        $data = $this->minimaxRestManager->get($this->apiUrl . "AUT/oauth20/token");
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }
        if (!is_array($data)) {
            throw new \Exception($data);
        }
        if (!isset($data["access_token"])) {
            throw new \Exception("access_token is not set: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        $this->apiTokenValidUntil = (new \DateTime())->add(new \DateInterval("PT1H")); // $data["expires_in"]

        return $data["access_token"];
    }

    /**
     * @param $endpoint
     * @return mixed
     * @throws \Exception
     */
    private function getApiResponse($endpoint, $params)
    {
        $this->apiToken = $this->getToken();

        $this->minimaxRestManager->CURLOPT_CUSTOMREQUEST = "GET";
        $this->minimaxRestManager->CURLOPT_HTTPHEADER = [
            "Authorization: Bearer " . $this->apiToken
        ];

        $url = $this->apiUrl . "API/api/orgs/" . $this->organisationId . "/" . $endpoint;
        if (!empty($params)) {
            $url .= "?" . http_build_query($params);
        }

        $data = $this->minimaxRestManager->get($url);
        if (isset($data["Rows"])) {
            $data = $data["Rows"];
        }

        return $data;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importProducts()
    {
        echo "Importing products...\n";

        $vatRates = $this->getVatRates();

        $productSelectColumns = [
            "id",
            "code",
            "name",
            "active",
            "price_base",
            "price_retail",
            "measure",
            "remote_id",
            "qty"
        ];

        $existingProducts = $this->getEntitiesArray($productSelectColumns, "product_entity", ["code"], "", "WHERE entity_state_id = 1 AND product_type_id = 1 AND remote_source = '{$this->getRemoteSource()}'");
        $existingSRoutes = $this->getEntitiesArray(["request_url", "store_id", "destination_type"], "s_route_entity", ["store_id", "request_url"]);

        $insertArray = [
            // product_entity
        ];
        $insertArray2 = [
            // s_route_entity
        ];
        $updateArray = [
            // product_entity
        ];

        $productIds = [];
        $productCodes = [];

        foreach ($existingProducts as $existingProduct) {
            if ($existingProduct["active"]) {
                $productUpdate = new UpdateModel($existingProduct);
                $productUpdate->add("active", false, false)
                    ->add("date_synced", "NOW()", false);
                $updateArray["product_entity"][$productUpdate->getEntityId()] = $productUpdate->getArray();
                $productIds[] = $productUpdate->getEntityId();
            }
        }

        $params = [];
        $params["CurrentPage"] = 1;
        $params["PageSize"] = 1000;

        $progressBar = new ProgressBar($this->getConsoleOutput());

        do {
            $data = $this->getApiResponse("items", $params);
            if (!empty($data)) {

                $progressBar->advance();

                foreach ($data as $d) {

                    $code = $d["Code"];
                    if (empty($code)) {
                        continue;
                    }

                    $remoteId = $d["ItemId"];
                    $name = $d["Title"];

                    $vatRateId = $d["VatRate"]["ID"];
                    $taxRatio = bcdiv(bcadd("100", (int)$vatRates[$vatRateId], 2), "100", 2);

                    $priceBase = $d["Price"]; // VPC
                    $priceRetail = bcmul($priceBase, $taxRatio, 2); // MPC

                    $measure = $d["UnitOfMeasurement"];
                    $qty = 0.0;

                    $nameArray = [];
                    $descriptionArray = [];
                    $metaKeywordsArray = [];
                    $showOnStoreArray = [];
                    $urlArray = [];

                    foreach ($this->getStores() as $storeId) {

                        $nameArray[$storeId] = $name;
                        $descriptionArray[$storeId] = "";
                        $metaKeywordsArray[$storeId] = "";
                        $showOnStoreArray[$storeId] = 1;

                        if (!isset($existingProducts[$code])) {

                            $i = 1;
                            $url = $key = $this->routeManager->prepareUrl($name);
                            while (isset($existingSRoutes[$storeId . "_" . $url]) || isset($insertArray2["s_route_entity"][$storeId . "_" . $url])) {
                                $url = $key . "-" . $i++;
                            }
                            $urlArray[$storeId] = $url;

                            $insertArray2["s_route_entity"][$storeId . "_" . $url] =
                                $this->getSRouteInsertEntity($url, "product", $storeId, $code); // remote_id
                        }
                    }

                    $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);
                    $descriptionJson = json_encode($descriptionArray, JSON_UNESCAPED_UNICODE);
                    $metaKeywordsJson = json_encode($metaKeywordsArray, JSON_UNESCAPED_UNICODE);
                    $showOnStoreJson = json_encode($showOnStoreArray, JSON_UNESCAPED_UNICODE);
                    $urlJson = json_encode($urlArray, JSON_UNESCAPED_UNICODE);

                    if (!isset($existingProducts[$code])) {

                        $productInsert = new InsertModel($this->asProduct,
                            $this->productInsertAttributes);

                        $productInsert->add("date_synced", "NOW()")
                            ->add("remote_id", $remoteId)
                            ->add("remote_source", $this->getRemoteSource())
                            ->add("name", $nameJson)
                            ->add("code", $code)
                            ->add("meta_title", $nameJson)
                            ->add("meta_description", $nameJson)
                            ->add("description", $descriptionJson)
                            ->add("meta_keywords", $metaKeywordsJson)
                            ->add("show_on_store", $showOnStoreJson)
                            ->add("price_base", $priceBase)
                            ->add("price_retail", $priceRetail)
                            ->add("active", 1)
                            ->add("url", $urlJson)
                            ->add("qty", $qty)
                            ->add("qty_step", 1)
                            ->add("tax_type_id", 3)
                            ->add("currency_id", $_ENV["DEFAULT_CURRENCY"])
                            ->add("product_type_id", CrmConstants::PRODUCT_TYPE_SIMPLE)
                            ->add("ord", 100)
                            ->add("measure", $measure)
                            ->add("is_visible", true)
                            ->add("template_type_id", 5)
                            ->add("auto_generate_url", true)
                            ->add("keep_url", true)
                            ->add("show_on_homepage", false)
                            ->add("content_changed", true);

                        $insertArray["product_entity"][$code] = $productInsert->getArray();
                        $productCodes[] = $code;

                    } else {

                        $productUpdate = new UpdateModel($existingProducts[$code],
                            $this->productUpdateAttributes);

                        unset($updateArray["product_entity"][$productUpdate->getEntityId()]);

                        $k = array_search($productUpdate->getEntityId(), $productIds);
                        if ($k !== false) {
                            unset($productIds[$k]);
                        }

                        if (isset($this->productUpdateAttributes["name"]) &&
                            $nameArray != json_decode($existingProducts[$code]["name"], true)) {
                            $productUpdate->add("name", $nameJson, false)
                                ->add("meta_title", $nameJson, false)
                                ->add("meta_description", $nameJson, false);
                        }

                        $productUpdate->add("active", 1)
                            ->add("qty", $qty)
                            ->addFloat("price_base", $priceBase)
                            ->addFloat("price_retail", $priceRetail);

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
                }
            }

            $this->databaseContext->reconnectToDatabase();

            $params["CurrentPage"]++;

        } while (!empty($data) && count($data) >= 1000);

        $progressBar->finish();
        echo "\n";

        unset($existingProducts);
        unset($existingSRoutes);

        $reselectArray = [];

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $reselectArray["product_entity"] = $this->getEntitiesArray($productSelectColumns, "product_entity", ["code"], "", "WHERE entity_state_id = 1 AND product_type_id = 1 AND remote_source = '{$this->getRemoteSource()}'");
        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);
        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $ret = [];
        if (!empty($productIds)) {
            $ret["product_ids"] = $this->resolveChangedProducts($productIds, $productCodes, $reselectArray["product_entity"]);
        }

        unset($reselectArray);

        echo "Importing products complete\n";

        return $ret;
    }

    /**
     * @param $countryId
     * @param $attributeCode
     * @return int|string|null
     */
    public function getCountryApiAttribute($countryId, $attributeCode)
    {
        if ($countryId == 3) {
            if ($attributeCode == "remote_id") {
                return 192;
            } else if ($attributeCode == "name") {
                return "Slovenija";
            } else if ($attributeCode == "currency_id") {
                return 7;
            }
        } else if ($countryId == 1) {
            if ($attributeCode == "remote_id") {
                return 95;
            } else if ($attributeCode == "name") {
                return "Hrvatska";
            } else if ($attributeCode == "currency_id") {
                return 9;
            }
        }

        return null;
    }

    /**
     * @return array|mixed
     * @throws \Exception
     */
    public function getVatRates()
    {
        $ret = [];

        $data = $this->getApiResponse("vatrates", []);
        foreach ($data as $d) {
            $ret[$d["VatRateId"]] = $d["Percent"];
        }

        return $ret;
    }

    /**
     * @param $code
     * @return array|mixed
     * @throws \Exception
     */
    public function getCustomerByCode($code)
    {
        $data = $this->getApiResponse("customers/code(" . $code . ")", []);
        if (empty($data)) {
            return [];
        }
        if (!isset($data["CustomerId"])) {
            return [];
        }

        return $data;
    }

    /**
     * @param AccountEntity $account
     * @return string
     */
    public function getPreparedCustomer(AccountEntity $account)
    {
        return '{
            Name: "' . $account->getName() . '",
            Code: "' . $account->getCode() . '",
            Address: "' . $account->getBillingAddress()->getStreet() . '",
            PostalCode: "' . $account->getBillingAddress()->getCity()->getPostalCode() . '",
            City: "' . $account->getBillingAddress()->getCity()->getName() . '",
            Country: {ID: ' . $this->getCountryApiAttribute($account->getBillingAddress()->getCity()->getCountryId(), "remote_id") . '},
            Currency: {ID: ' . $this->getCountryApiAttribute($account->getBillingAddress()->getCity()->getCountryId(), "currency_id") . '},
            SubjectToVAT: "N",
            EInvoiceIssuing: "N"
        }';
    }

    /**
     * @param OrderItemEntity $orderItem
     * @return string
     */
    public function getPreparedOrderItem(OrderItemEntity $orderItem)
    {
        return '{
            Item: {ID: ' . $orderItem->getProduct()->getRemoteId() . '},
            ItemName: "' . $orderItem->getName() . '",
            Quantity: ' . $orderItem->getQty() . ',
            Price: ' . $orderItem->getPriceTotal() . '
        },';
    }

    /**
     * @param OrderEntity $order
     * @return string
     */
    public function getPreparedOrder(OrderEntity $order)
    {
        $issuedInvoiceRows = "";

        $accountCode = $order->getAccount()->getCode();
        if (empty($accountCode)) {
            $accountCode = "SHIPSHAPE-TEST"; // SIFRA FIZICKE OSOBE
        }

        /** @var OrderItemEntity $orderItem */
        foreach ($order->getOrderItems() as $orderItem) {

            if ($orderItem->getProduct()->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
                continue;
            }

            $issuedInvoiceRows .= $this->getPreparedOrderItem($orderItem);
        }

        return '{
            ReceivedIssued: "P",
            Date: "' . (new \DateTime())->format("j.n.Y") . '",
            Currency: "' . $this->getCountryApiAttribute($order->getAccountBillingCity()->getCountryId(), "currency_id") . '",
            ReportTemplate: "' . "ne znam" . '",
            Customer: {ID: ' . $accountCode . '},
            CustomerName: "' . $order->getAccountName() . '",
            IssuedInvoiceRows: [
                ' . substr($issuedInvoiceRows, 0, -1) . '
            ]
        }';
    }

    /**
     * @param $data
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function sendCustomer($data)
    {
        $this->apiToken = $this->getToken();

        $this->minimaxRestManager->CURLOPT_POST = 1;
        $this->minimaxRestManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->minimaxRestManager->CURLOPT_HTTPHEADER = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->apiToken
        ];
        $this->minimaxRestManager->CURLOPT_POSTFIELDS = $data;

        $data = $this->minimaxRestManager->get($this->apiUrl . "API/api/orgs/" . $this->organisationId . "/customers");
        if (!empty($data)) {
            throw new \Exception("Failed to send customer: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        return $data;
    }

    /**
     * @param $data
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function sendOrder($data)
    {
        $this->apiToken = $this->getToken();

        $this->minimaxRestManager->CURLOPT_POST = 1;
        $this->minimaxRestManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->minimaxRestManager->CURLOPT_HTTPHEADER = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->apiToken
        ];
        $this->minimaxRestManager->CURLOPT_POSTFIELDS = $data;

        $data = $this->minimaxRestManager->get($this->apiUrl . "API/api/orgs/" . $this->organisationId . "/orders");
        if (!empty($data)) {
            throw new \Exception("Failed to send order: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        return $data;
    }
}
