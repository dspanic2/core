<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderItemEntity;

class VascoApiManager extends AbstractBaseManager
{
    private $apiUrl;
    private $apiUsername;
    private $apiPassword;
    private $apiTaxNumber;
    private $apiToken;
    private $apiTokenValidUntil;

    public $deliveryProductRemoteCode;
    public $deliveryTaxTypeRemoteId;
    public $defaultOrderCountryRemoteId;

    const UNIT_WAND_TO_VASCO = [
        "HR" => [
            "SI" => [
                "kom" => "KOS",
                "ara" => "POLA",
                "bli" => "KOS",
                "blk" => "BLK",
                "gar" => "SET",
                "kg" => "KG",
                "km" => "KM",
                "kpl" => "KPL",
                "kut" => "KRT",
                "l" => "LIT",
                "m" => "M",
                "m2" => "M2",
                "mil" => "TIS",
                "omo" => "ZAV",
                "pak" => "PAK",
                "pal" => "PAL",
                "par" => "PAR",
                "pau" => "PAV",
                "rol" => "ROLA",
                "sat" => "UR",
                "set" => "SET",
                "str" => "STR",
                "t" => "T"
            ]
        ]
    ];

    public function initialize()
    {
        parent::initialize();

        $this->apiUrl = $_ENV["VASCO_API_URL"];
        $this->apiUsername = $_ENV["VASCO_API_USERNAME"];
        $this->apiPassword = $_ENV["VASCO_API_PASSWORD"];
        $this->apiTaxNumber = $_ENV["VASCO_API_TAX_NUMBER"];
        $this->deliveryProductRemoteCode = $_ENV["VASCO_DELIVERY_PRODUCT_REMOTE_CODE"];
        $this->deliveryTaxTypeRemoteId = $_ENV["VASCO_DELIVERY_TAX_TYPE_REMOTE_ID"]; // 0: 22%, 1: 9,5%, 2: 0%, 3: 5%
        $this->defaultOrderCountryRemoteId = $_ENV["VASCO_DEFAULT_ORDER_COUNTRY_REMOTE_ID"];
    }

    /**
     * @param $accounts
     * @param $oib
     * @return array
     */
    public function getAccountByOib($accounts, $oib)
    {
        $key = array_search($oib, array_column($accounts, "davcna"));
        return ($key ? $accounts[$key] : []);
    }

    /**
     * @param $products
     * @param $code
     * @return array|mixed
     */
    public function getProductByCode($products, $code)
    {
        $key = array_search($code, array_column($products, "sifra"));
        return ($key ? $products[$key] : []);
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
            "username" => $this->apiUsername,
            "password" => $this->apiPassword,
            "taxNumber" => $this->apiTaxNumber
        ];

        $restManager = new RestManager();
        $restManager->CURLOPT_POST = 1;
        $restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $restManager->CURLOPT_POSTFIELDS = json_encode($body);
        $restManager->CURLOPT_HTTPHEADER = [
            "Content-Type: application/json"
        ];

        $data = $restManager->get($this->apiUrl . "Avtentikacija");
        if (!is_array($data)) {
            throw new \Exception($data);
        }
        if (!isset($data["apiKey"])) {
            throw new \Exception("apiKey is not set: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        }
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        $this->apiTokenValidUntil = (new \DateTime())->add(new \DateInterval("PT29M"));

        return $data["apiKey"];
    }

    /**
     * @param array $params
     * @return array|bool|string
     * @throws \Exception
     */
    public function getAccounts($params = [])
    {
        $this->apiToken = $this->getToken();

        $restManager = new RestManager();
        $restManager->CURLOPT_HTTPHEADER = [
            "Authorization: Bearer " . $this->apiToken
        ];

        $url = $this->apiUrl . "SkupniSifranti/partner";
        if (!empty($params)) {
            $url .= "?" . http_build_query($params);
        }

        $data = $restManager->get($url);
        if (!is_array($data)) {
            throw new \Exception($data);
        }

        return $data;
    }

    /**
     * @param array $params
     * @return array|bool|string
     * @throws \Exception
     */
    public function getProducts($params = [])
    {
        $this->apiToken = $this->getToken();

        $restManager = new RestManager();
        $restManager->CURLOPT_HTTPHEADER = [
            "Authorization: Bearer " . $this->apiToken
        ];

        $params = array_replace([
            "Segment" => 1,
            "SegmentSize" => 5000
        ], $params);

        $results = [];

        do {
            $data = $restManager->get($this->apiUrl . "FASifranti/artikel?" . http_build_query($params));
            if (!is_array($data)) {
                throw new \Exception($data);
            }
            if (!empty($data)) {
                $results = array_merge($results, $data);
            }
            $params["Segment"]++;

        } while (!empty($data) && count($results) >= $params["SegmentSize"]);

        return $results;
    }

    /**
     * @param array $params
     * @return array|bool|string
     * @throws \Exception
     */
    public function getOrders($params = [])
    {
        $this->apiToken = $this->getToken();

        $restManager = new RestManager();
        $restManager->CURLOPT_HTTPHEADER = [
            "Authorization: Bearer " . $this->apiToken
        ];

        $url = $this->apiUrl . "FA/narociloKupca";
        if (!empty($params)) {
            $url .= "?" . http_build_query($params);
        }

        $data = $restManager->get($url);
        if (!is_array($data)) {
            throw new \Exception($data);
        }

        return $data;
    }

    /**
     * @return array|bool|string
     * @throws \Exception
     */
    public function getCountries()
    {
        $this->apiToken = $this->getToken();

        $restManager = new RestManager();
        $restManager->CURLOPT_HTTPHEADER = [
            "Authorization: Bearer " . $this->apiToken
        ];

        $data = $restManager->get($this->apiUrl . "SkupniSifranti/drzava");
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }
        if (!is_array($data)) {
            throw new \Exception($data);
        }

        return $data;
    }

    /**
     * @param $body
     * @return array|bool|string
     * @throws \Exception
     */
    public function sendOrder($body)
    {
        $this->apiToken = $this->getToken();

        $restManager = new RestManager();
        $restManager->CURLOPT_POST = 1;
        $restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $restManager->CURLOPT_POSTFIELDS = json_encode($body);
        $restManager->CURLOPT_HTTPHEADER = [
            "Authorization: Bearer " . $this->apiToken,
            "Content-Type: application/json"
        ];

        $data = $restManager->get($this->apiUrl . "FA/narociloKupca");
        if ($restManager->code != 201) {
            throw new \Exception(sprintf("Failed to send order: %u, %s, %s", $restManager->code, json_encode($data, JSON_UNESCAPED_UNICODE), $restManager->CURLOPT_POSTFIELDS));
        }
        if (!is_array($data)) {
            throw new \Exception($data);
        }
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        return $data;
    }

    /**
     * @param $body
     * @return array|bool|string
     * @throws \Exception
     */
    public function sendAccount($body)
    {
        $this->apiToken = $this->getToken();

        $restManager = new RestManager();
        $restManager->CURLOPT_POST = 1;
        $restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $restManager->CURLOPT_POSTFIELDS = json_encode($body);
        $restManager->CURLOPT_HTTPHEADER = [
            "Authorization: Bearer " . $this->apiToken,
            "Content-Type: application/json"
        ];

        $data = $restManager->get($this->apiUrl . "SkupniSifranti/partner");
        if ($restManager->code != 201) {
            throw new \Exception(sprintf("Failed to send account: %u, %s, %s", $restManager->code, json_encode($data, JSON_UNESCAPED_UNICODE), $restManager->CURLOPT_POSTFIELDS));
        }
        if (!is_array($data)) {
            throw new \Exception($data);
        }
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        return $data;
    }

    /**
     * @param $body
     * @return array|bool|string
     * @throws \Exception
     */
    public function sendProduct($body)
    {
        $this->apiToken = $this->getToken();

        $restManager = new RestManager();
        $restManager->CURLOPT_POST = 1;
        $restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $restManager->CURLOPT_POSTFIELDS = json_encode($body);
        $restManager->CURLOPT_HTTPHEADER = [
            "Authorization: Bearer " . $this->apiToken,
            "Content-Type: application/json"
        ];

        $data = $restManager->get($this->apiUrl . "FASifranti/artikel");
        if ($restManager->code != 201) {
            throw new \Exception(sprintf("Failed to send product: %u, %s, %s", $restManager->code, json_encode($data, JSON_UNESCAPED_UNICODE), $restManager->CURLOPT_POSTFIELDS));
        }
        if (!is_array($data)) {
            throw new \Exception($data);
        }
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        return $data;
    }

    /**
     * Default implementation to get product array from order entity
     *
     * @param OrderItemEntity $orderItem
     * @return array
     */
    public function prepareProductFromOrderItem(OrderItemEntity $orderItem)
    {
        $product = [
            "tipStevilcenja" => 0,
            "sifra" => (string)$orderItem->getCode(),
            "naziv" => mb_substr($orderItem->getName(), 0, 40),
            "naziv2" => "",
            "enota" => $this->getWandToVascoUnit("HR", "SI", $orderItem->getProduct()->getMeasure()),
            "grupa" => "100",
            "stopnjaDDV" => $orderItem->getProduct()->getTaxType()->getRemoteId(),
            "karticaArtikla" => 1,
            "prodajnaCena" => $orderItem->getProduct()->getPriceRetail(),
            "objavaB2B" => 0,
            "objavaB2C" => 0,
            "blagovnaSkupina" => 0,
            "dobavitelj" => 0,
            "dodatnaPolja" => []
        ];

        return $product;
    }

    /**
     * Default implementation to get account array from order entity
     *
     * @param OrderEntity $order
     * @return array
     */
    public function prepareAccountFromOrder(OrderEntity $order)
    {
//        tipStevilcenja (vrsta numeriranja):
//        0 - unos sa zadanom šifrom
//        1 - unos bez zadane šifre (šifra = 0), automatski generira sljedeći slobodni broj
//        2 - unos bez zadane šifre, automatski generira sljedeći slobodni broj iz postavljenog raspona šifri (JOŠ NIJE PODRŽAN!)

//        tip:
//        1 - Domaći kupac
//        2 - Strani kupac - unutar EU
//        3 - Strani kupac - izvan EU
//        4 - Povezana pravna osoba
//        5 - Izravni proračun. korisnik
//        6 - Neizravni korisnik proračuna

//        davcniZavezanec (porezni obveznik):
//        0 - Nema podataka
//        1 - Je porezni obveznik
//        2 - Nije porezni obveznik
//        3 - Izvoz ili EU
//        4 - Mali porezni obveznik

        $sifra = 0;
        $tipStevilcenja = 1;
        if (!empty($order->getAccount()->getIsLegalEntity())) {
            $sifra = $order->getAccount()->getOib();
            $tipStevilcenja = 0;
        }

        $account = [
            "tipStevilcenja" => $tipStevilcenja,
            "sifra" => $sifra,
            "naziv" => mb_substr($order->getAccountName(), 0, 40),
            "naziv2" => "",
            "naslov" => mb_substr($order->getAccountBillingStreet(), 0, 30),
            "posta" => $order->getAccountBillingCity()->getPostalCode(),
            "drzava" => $this->defaultOrderCountryRemoteId,
            "maticna" => $order->getAccount()->getMbr(),
            "tip" => 1,
            "davcna" => $order->getAccount()->getOib(),
            "davcniZavezanec" => 0,
            "ident" => "SI" . $order->getAccount()->getOib(),
            "telefon" => $order->getAccountPhone(),
            "telefaks" => $order->getAccount()->getFax(),
            "gsm" => $order->getAccount()->getPhone2(),
            "email" => $order->getAccountEmail(),
            "tujeSifre" => null,
            "dodatnaPolja" => []
        ];

        return $account;
    }

    /**
     * @param $sourceLang
     * @param $targetLang
     * @param $sourceValue
     * @return string
     */
    public function getWandToVascoUnit($sourceLang, $targetLang, $sourceValue)
    {
        if (!isset(self::UNIT_WAND_TO_VASCO[$sourceLang][$targetLang][$sourceValue])) {
            return "KOS";
        }

        return self::UNIT_WAND_TO_VASCO[$sourceLang][$targetLang][$sourceValue];
    }
}