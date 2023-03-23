<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\RestManager;
use AppBundle\Models\InsertModel;
use AppBundle\Models\UpdateModel;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\OrderEntity;
use Symfony\Component\Console\Helper\ProgressBar;

class TiipsImportManager extends DefaultIntegrationImportManager
{
    /** @var AttributeSet $asProduct */
    public $asProduct;
    /** @var AttributeSet $asSRoute */
    public $asSRoute;
    /** @var RestManager $restManager */
    public $restManager;
    /** @var string $apiUrl */
    private $apiUrl;
    /** @var AttributeSet $asProductWarehouseLink */
    protected $asProductWarehouseLink;

    private $id1;
    private $id2;

    public function initialize()
    {
        parent::initialize();

        $this->asProduct = $this->entityManager->getAttributeSetByCode("product");
        $this->asSRoute = $this->entityManager->getAttributeSetByCode("s_route");
        $this->asProductWarehouseLink = $this->entityManager->getAttributeSetByCode("product_warehouse_link");

        $this->restManager = new RestManager();
        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";

        $this->apiUrl = $_ENV["TIIPS_API_URL"];
        $this->id1 = $_ENV["TIIPS_API_ID1"];
        $this->id2 = $_ENV["TIIPS_API_ID2"];

        $this->setRemoteSource("tiips");
    }


    /**
     * @param $method
     * @param $id
     * @param $upit
     * @param $parameters
     * @return \SimpleXMLElement
     * @throws \Exception
     */
    public function getApiResponse($method, $id, $upit, $parameters = [])
    {
        $post = [];
        $post["ID1"] = $this->id1;
        $post["ID2"] = $this->id2;
        $post["ID"] = $id;
        $post["UPIT"] = $upit;

        foreach ($parameters as $key => $param) {
            $post[$key] = $param;
        }

        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_POSTFIELDS = http_build_query($post);

        $response = $this->restManager->get($this->apiUrl . "/" . $method, false);

        $xml = simplexml_load_string($response);
        if (empty($xml)) {
            throw new \Exception("Response xml is empty");
        }
        if (isset($xml->GRESKE)) {
            throw new \Exception((string)$xml->GRESKE->GRESKA[0]->OPIS);
        }

        return $xml;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importProducts()
    {
        $data = $this->getApiResponse("Artikl", 2, 1);
        if (empty($data)) {
            throw new \Exception("Products response is empty");
        }

        if (!isset($data->ARTIKLI->ARTIKL)) {
            throw new \Exception("Products response is invalid");
        }

        $data = $data->ARTIKLI->ARTIKL;

        $productSelectColumns = [
            "id",
            "name",
            "code",
            "ean",
            "active",
            "weight",
            "qty",
            "price_base",
            "price_retail",
            "tax_type_id",
            'remote_id'
        ];

        $existingProducts = $this->getEntitiesArray($productSelectColumns, "product_entity", ["remote_id"]);
        $existingSRoutes = $this->getEntitiesArray(["request_url", "store_id", "destination_type"], "s_route_entity", ["store_id", "request_url"]);
        $existingTaxTypes = $this->getEntitiesArray(["id", "name"], "tax_type_entity", ["name"]);

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

        $progressBar = new ProgressBar($this->getConsoleOutput(), count($data));

        foreach ($data as $d) {
            $progressBar->advance();

            $d = (array)$d;

            $internaSifra = (string)$d["INTERNASIFRA"];
            $sifraArtikla = (string)$d["SIFRAARTIKLA"];
            $ean = (string)$d["EAN13"];
            $name = (string)$d["NAZIVARTIKLA"];
            $unit = (string)$d["JEDINICAMJERE"];
            $weight = (string)$d["NETOTEZINA"];
            $taxPercent = (int)$d["STOPAPDV"];
            $priceBase = (string)$d["VPC"];
            $priceRetail = (string)$d["MPC"];
            $qty = (string)$d["STANJE"];

            $currencyId = $_ENV["DEFAULT_CURRENCY"];
            $taxTypeId = null;

            if (isset($existingTaxTypes["PDV" . $taxPercent])) {
                $taxTypeId = $existingTaxTypes["PDV" . $taxPercent]["id"];
            }

            if (!$taxTypeId) {
                continue;
            }

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

                if (!isset($existingProducts[$internaSifra])) {
                    $i = 1;
                    $url = $key = $this->routeManager->prepareUrl($name);
                    while (isset($existingSRoutes[$storeId . "_" . $url]) || isset($insertArray2["s_route_entity"][$storeId . "_" . $url])) {
                        $url = $key . "-" . $i++;
                    }
                    $urlArray[$storeId] = $url;

                    $sRouteInsert = new InsertModel($this->asSRoute);
                    $sRouteInsert->add("request_url", $url)
                        ->add("destination_type", "product")
                        ->add("store_id", $storeId)
                        ->addLookup("destination_id", $internaSifra, "product_entity");

                    $insertArray2["s_route_entity"][$storeId . "_" . $url] = $sRouteInsert;
                }
            }

            $nameJson = json_encode($nameArray, JSON_UNESCAPED_UNICODE);
            $descriptionJson = json_encode($descriptionArray, JSON_UNESCAPED_UNICODE);
            $metaKeywordsJson = json_encode($metaKeywordsArray, JSON_UNESCAPED_UNICODE);
            $showOnStoreJson = json_encode($showOnStoreArray, JSON_UNESCAPED_UNICODE);
            $urlJson = json_encode($urlArray, JSON_UNESCAPED_UNICODE);

            if (!isset($existingProducts[$internaSifra])) {
                $productInsert = new InsertModel($this->asProduct);
                $productInsert->add("date_synced", "NOW()")
                    ->add("code", $sifraArtikla)
                    ->add("remote_id", $internaSifra)
                    ->add("remote_source", $this->getRemoteSource())
                    ->add("name", $nameJson)
                    ->add("ean", $ean)
                    ->add("meta_title", $nameJson)
                    ->add("meta_description", $nameJson)
                    ->add("description", $descriptionJson)
                    ->add("meta_keywords", $metaKeywordsJson)
                    ->add("show_on_store", $showOnStoreJson)
                    ->add("price_base", $priceBase)
                    ->add("price_retail", $priceRetail)
                    ->add("active", true)
                    ->add("url", $urlJson)
                    ->add("qty", $qty)
                    ->add("qty_step", 1)
                    ->add("tax_type_id", $taxTypeId)
                    ->add("currency_id", $currencyId)
                    ->add("product_type_id", CrmConstants::PRODUCT_TYPE_SIMPLE)
                    ->add("ord", 100)
                    ->add("is_visible", true)
//                    ->add("is_saleable", true)
                    ->add("template_type_id", 5)
                    ->add("auto_generate_url", true)
                    ->add("keep_url", true)
                    ->add("show_on_homepage", false)
                    ->add("weight", $weight)
                    ->add("content_changed", true)
                    ->add("measure", $unit);

                $insertArray["product_entity"][$internaSifra] = $productInsert->getArray();

                $productRemoteIds[] = $internaSifra;
            } else {
                $productUpdate = new UpdateModel($existingProducts[$internaSifra]);

                unset($updateArray["product_entity"][$productUpdate->getEntityId()]);

                $k = array_search($productUpdate->getEntityId(), $productIds);
                if ($k !== false) {
                    unset($productIds[$k]);
                }

                // iskljuceno zato sto se kod postojecih proizvoda ne updejta "name"
                //if ($nameArray != json_decode($existingProducts[$internaSifra]["name"], true)) {
                //    $productUpdate->add("name", $nameJson, false)
                //        ->add("meta_title", $nameJson, false)
                //        ->add("meta_description", $nameJson, false);
                //}

                // active
                $productUpdate->add("ean", $ean)
                    ->add("active", 1)
                    ->addFloat("weight", $weight)
                    ->addFloat("qty", $qty)
                    ->addFloat("price_base", $priceBase)
                    ->addFloat("price_retail", $priceRetail)
                    ->add("tax_type_id", $taxTypeId)
                    ->add("code", $sifraArtikla);

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

        $progressBar->finish();
        echo "\n";

        unset($existingProducts);
        unset($existingSRoutes);
        unset($existingTaxTypes);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $reselectArray["product_entity"] = $this->getEntitiesArray(["id", "remote_id"], "product_entity", ["remote_id"]);
        $insertArray2 = $this->resolveImportArray($insertArray2, $reselectArray);

        $this->executeInsertQuery($insertArray2);
        unset($insertArray2);

//        dump($updateArray);die;

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $ret = [];
        if (!empty($productIds)) {
            $ret["product_ids"] = $this->resolveChangedProducts($productIds, $productRemoteIds, $reselectArray["product_entity"]);
        }

        return $ret;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function importWarehouseStock()
    {
        $data = $this->getApiResponse("Artikl", 2, 1);
        if (empty($data)) {
            throw new \Exception("Warehouse stock response is empty");
        }

        if (!isset($data->ARTIKLI->ARTIKL)) {
            throw new \Exception("Warehouse stock response is invalid");
        }

        $data = $data->ARTIKLI->ARTIKL;

        $existingWarehouses = $this->getExistingWarehouses("id", ["id"]);
        $existingProducts = $this->getExistingProducts("remote_id", ["id"], "AND product_type_id = 1 AND remote_source = '{$this->getRemoteSource()}'");
        $existingProductWarehouseLinks = $this->getExistingProductWarehouseLinks();

        $insertArray = [
            // product_warehouse_link_entity
        ];
        $updateArray = [
            // product_warehouse_link_entity
        ];
        $deleteArray = [
            // product_warehouse_link_entity
        ];

        $productIds = [];

        foreach ($existingProductWarehouseLinks as $key => $existingProductWarehouseLink) {
            $deleteArray["product_warehouse_link_entity"][$key] = [
                "id" => $existingProductWarehouseLink["id"] // delete where id == ...
            ];
        }

        $progressBar = new ProgressBar($this->getConsoleOutput(), count($data));

        foreach ($data as $d) {
            $progressBar->advance();

            $d = (array)$d;

            $internaSifra = (string)$d["INTERNASIFRA"];
            $qty = (string)$d["STANJE"];

            if (!isset($existingProducts[$internaSifra])) {
                continue;
            }

            if ($qty <= 0) {
                continue;
            }

            $productWarehouseLinkKey = $existingProducts[$internaSifra]["id"] . "_" . current($existingWarehouses)["id"];
            unset($deleteArray["product_warehouse_link_entity"][$productWarehouseLinkKey]);

            if (!isset($existingProductWarehouseLinks[$productWarehouseLinkKey])) {
                $productWarehouseLinkInsert = new InsertModel($this->asProductWarehouseLink);
                $productWarehouseLinkInsert->add("product_id", $existingProducts[$internaSifra]["id"])
                    ->add("warehouse_id", current($existingWarehouses)["id"])
                    ->add("qty", $qty);
                $insertArray["product_warehouse_link_entity"][$productWarehouseLinkKey] = $productWarehouseLinkInsert->getArray();
                $productIds[] = $existingProducts[$internaSifra]["id"];
            } else {
                $productWarehouseLinkUpdate = new UpdateModel($existingProductWarehouseLinks[$productWarehouseLinkKey]);
                $productWarehouseLinkUpdate->addFloat("qty", $qty);
                if (!empty($productWarehouseLinkUpdate->getArray())) {
                    $updateArray["product_warehouse_link_entity"][$productWarehouseLinkUpdate->getEntityId()] = $productWarehouseLinkUpdate->getArray();
                    $productIds[] = $existingProducts[$internaSifra]["id"];
                }
            }
        }

        $progressBar->finish();
        echo "\n";

        unset($existingWarehouses);
        unset($existingProducts);
        unset($existingProductWarehouseLinks);

        $this->executeInsertQuery($insertArray);
        unset($insertArray);

        $this->executeUpdateQuery($updateArray);
        unset($updateArray);

        $this->executeDeleteQuery($deleteArray);
        unset($deleteArray);

        $ret = [];
        if (!empty($productIds)) {
            $ret["product_ids"] = array_unique($productIds);
        }

        return $ret;
    }

    /**
     * @param OrderEntity $order
     * @return array
     */
    public function getAccountData(OrderEntity $order)
    {
        return [
            'KUPAC_PB' => $order->getAccountOib(),
            'KUPAC_NAZIV' => $order->getAccountName(),
            'KUPAC_ADRESA' => $order->getAccountBillingStreet(),
            'KUPAC_GRAD' => $order->getAccountBillingCity()->getName(),
            'KUPAC_TELEFON' => $order->getAccountPhone(),
            'KUPAC_EMAIL' => $order->getAccountEmail(),
            'KUPAC_NAPOMENA' => $order->getMessage()
        ];
    }

    /**
     * @param $data
     * @return \SimpleXMLElement
     * @throws \Exception
     */
    public function sendAccount($data)
    {
        $data = $this->getApiResponse("Kupac", 5000, 1000, $data);
        if (empty($data)) {
            throw new \Exception("Send account response is empty");
        }

        if (!isset($data->KUPAC)) {
            throw new \Exception("Send account response is not valid");
        }

        return $data->KUPAC;
    }

    /**
     * @param OrderEntity $order
     * @return array
     */
    public function getOrderData(OrderEntity $order)
    {
        $articles = [];
        foreach ($order->getOrderItems() as $item) {
            $articles[] = $item->getCode() . ':' . intval($item->getQty());
        }

        return [
            'LISTAARTIKALA' => implode(';', $articles),
            'NARUDZBAKUPAC' => $order->getAccountName(),
            'NARUDZBALOKACIJA' => $order->getAccountBillingCity()->getName(),
            'NARUDZBAADRESA' => $order->getAccountBillingStreet(),
            'NARUDZBAOIB' => $order->getAccountOib(),
            'NARUDZBATELEFON' => $order->getAccountPhone(),
            'NARUDZBAEMAIL' => $order->getAccountEmail(),
            'NARUDZBANAPOMENA' => $order->getMessage()
        ];
    }

    /**
     * @param $data
     * @return \SimpleXMLElement
     * @throws \Exception
     */
    public function sendOrder($data)
    {
        $data = $this->getApiResponse("Narudzba", 2005, 2000, $data);
        if (empty($data)) {
            throw new \Exception("Send order response is empty");
        }

        if (!isset($data->BROJNARUDZBE)) {
            throw new \Exception("Send order response is not valid");
        }

        return $data->BROJNARUDZBE;
    }
}
