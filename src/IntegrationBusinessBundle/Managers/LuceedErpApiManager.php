<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Entity\CountryEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderReturnStateEntity;
use CrmBusinessBundle\Entity\OrderStateEntity;
use HrBusinessBundle\Entity\CityEntity;
use ScommerceBusinessBundle\Entity\OrderReturnEntity;

class LuceedErpApiManager extends DefaultIntegrationImportManager
{
    /** @var $httpAuth */
    protected $httpAuth;
    /** @var $httpContext */
    protected $httpContext;
    /** @var $httpAuth */
    protected $httpOptions;

    /** @var $luceedDefaultUrl */
    protected $luceedDefaultUrl;

    protected $data;

    /** @var RestManager $restManager */
    protected $restManager;

    public function initialize()
    {
        parent::initialize();

        $this->httpAuth = base64_encode($_ENV['LUCEED_ERP_USERNAME'] . ':' . $_ENV['LUCEED_ERP_PASSWORD']);
        $this->httpContext = stream_context_create($this->httpOptions);
        $this->luceedDefaultUrl = $_ENV['LUCEED_ERP_URL'];

        if (empty($this->restManager)) {
            $this->restManager = $this->getContainer()->get("rest_manager");
        }
    }

    /**
     * @param $api
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function put($api,$data)
    {
        $data = json_encode(array_filter($data));
        if (empty($data)) {
            throw new \Exception("Put data is empty");
        }

        $url = $this->luceedDefaultUrl . $api;

        $this->restManager->CURLOPT_CUSTOMREQUEST = "PUT";
        $this->restManager->CURLOPT_HTTPHEADER = [
            'Authorization: Basic ' . $this->httpAuth
        ];
        $this->restManager->CURLOPT_POSTFIELDS = $data;

        $response = $this->restManager->get($url);

        if (isset($response["error"])) {
            throw new \Exception($response["error"]);
        }

        $result = $response["result"];

        if (empty($result) || !isset($result[0])) {
            return Array();
        }

        return $result[0];
    }

    /**
     * @param $api
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function post($api,$data)
    {
        $data = json_encode(array_filter($data));
        if (empty($data)) {
            throw new \Exception("Post data is empty");
        }

        $url = $this->luceedDefaultUrl . $api;

        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = [
            'Authorization: Basic ' . $this->httpAuth
        ];
        $this->restManager->CURLOPT_POSTFIELDS = $data;

        $response = $this->restManager->get($url);

        if (isset($response["error"])) {
            throw new \Exception($response["error"]);
        }

        $result = $response["result"];

        if (empty($result) || !isset($result[0])) {
            return Array();
        }

        return $result[0];
    }

    /**
     * @param $api
     * @param string $value
     * @return array|string|null
     * @throws \Exception
     */
    public function get($api, string $value = '')
    {
        if(empty($value)){
            throw new \Exception("Get data is empty");
        }

        $url = $this->luceedDefaultUrl . $api . "/" . $value;

        $this->restManager->CURLOPT_CUSTOMREQUEST = "GET";
        $this->restManager->CURLOPT_HTTPHEADER = [
            'Authorization: Basic ' . $this->httpAuth
        ];

        $response = $this->restManager->get($url);

        if (isset($response["error"])) {
            throw new \Exception($response["error"]);
        }

        $exploded = (explode("/", $api));

        $returnString = self::getReturnString($exploded);

        /**
         * Response će vratiti prazan array ako ne postoji
         */
        $result = $response["result"][0][$returnString];
        if (empty($result) || !isset($result[0])) {
            return Array();
        }

        if ($api === "users/listaprezime" || $api === "mpracuni/pdf") {
            return $result;
        } else {
            return $result[0];
        }

        return $result;
    }

    /**
     * @param $nameArray
     * @return string
     */
    private static function getReturnString($nameArray)
    {
        /**
         * Ovo uopce ne treba postojati, samo smeta
         */
        $name = $nameArray[0];
        $nameTmp = implode("/",$nameArray);

        $definition = Array(
            "users" => "users",
            "partneri" => "partner",
            "NaloziProdaje" => "nalozi_prodaje",
            "mpracuni" => "mprac",
            "mpracuni/pdf" => "pdf"
        );

        if(isset($definition[$nameTmp])){
            return $definition[$nameTmp];
        }
        elseif (isset($definition[$name])){
            return $definition[$name];
        }

        return $name;
    }






    /**
     * @return bool
     * @throws \Exception
     */
    public function updateOrderStatuses(){

        if(empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }

        if(empty($this->orderManager)){
            $this->orderManager = $this->container->get("order_manager");
        }

        $entityType = $this->entityManager->getEntityTypeByCode("order");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("sentToErp", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("remoteUid", "nn", null));
        $compositeFilter->addFilter(new SearchFilter("orderState.isFinalState", "ne", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "asc"));

        $orders = $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);

        if(!EntityHelper::isCountable($orders) || count($orders) == 0){
            return true;
        }

        /** @var OrderEntity $order */
        foreach ($orders as $order) {

            try {

                if(empty($order->getRemoteUid())) {
                    throw new \Exception("Missing uid on order id {$order->getId()}");
                }

                $orderResponse = $this->get("NaloziProdaje/uid", $order->getRemoteUid());

                if(empty($orderResponse)){
                    throw new \Exception("No info for order id {$order->getId()}");
                }

                $lastState = end($orderResponse["statusi"]);

                if(empty($this->orderStatuses)){
                    $this->orderStatuses = $this->getOrderStatusesByUid();
                }

                if(!isset($this->orderStatuses[$lastState["status_uid"]])){
                    continue;
                }

                if($order->getOrderState()->getId() != $this->orderStatuses[$lastState["status_uid"]]->getId()){
                    $data = array();
                    $data["order_state"] = $this->orderStatuses[$lastState["status_uid"]];

                    if(EntityHelper::checkIfMethodExists($order,"getDeliveryReference")){
                        if(isset($orderResponse["isporuke"]) && isset($orderResponse["isporuke"][0]) && isset($orderResponse["isporuke"][0]["prijevoznice"][0]["referenca_isporuke"]) && !empty($orderResponse["isporuke"][0]["prijevoznice"][0]["referenca_isporuke"]) && empty($order->getDeliveryReference())){
                            $data["delivery_reference"] = $orderResponse["isporuke"][0]["prijevoznice"][0]["referenca_isporuke"];
                            $data["delivery_reference_mail_send"] = 1;
                        }
                    }

                    $this->orderManager->updateOrder($order, $data);
                }
            } catch (\Exception $e) {

                if(empty($this->errorLogManager)){
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }
                $this->errorLogManager->logExceptionEvent($e->getMessage(), $e, true);
            }
        }

        return true;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function updateOrderReturnStatuses(){

        if(empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }

        if(empty($this->orderReturnManager)){
            $this->orderReturnManager = $this->container->get("order_return_manager");
        }

        $entityType = $this->entityManager->getEntityTypeByCode("order_return");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("sentToErp", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("orderReturnState.isFinalState", "ne", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "asc"));

        $orders = $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);

        if(!EntityHelper::isCountable($orders) || count($orders) == 0){
            return true;
        }

        /** @var OrderReturnEntity $order */
        foreach ($orders as $order) {

            try {

                if(empty($order->getRemoteUid())) {
                    throw new \Exception("Missing uid on order id {$order->getId()}");
                }

                $orderResponse = $this->get("NaloziProdaje/uid", $order->getRemoteUid());

                if(empty($orderResponse)){
                    throw new \Exception("No info for order id {$order->getId()}");
                }

                $lastState = end($orderResponse["statusi"]);

                if(empty($this->orderReturnStatuses)){
                    $this->orderReturnStatuses = $this->getOrderReturnStatusesByUid();
                }

                if(!isset($this->orderReturnStatuses[$lastState["status_uid"]])){
                    continue;
                }

                if($order->getOrderReturnStateId() != $this->orderReturnStatuses[$lastState["status_uid"]]->getId()){
                    $data = array();
                    $data["order_state"] = $this->orderReturnStatuses[$lastState["status_uid"]];
                    $this->orderReturnManager->updateOrderReturn($order, $data);
                }
            } catch (\Exception $e) {

                if(empty($this->errorLogManager)){
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }
                $this->errorLogManager->logExceptionEvent($e->getMessage(), $e, true);
            }
        }

        return true;
    }

    /**
     * @return array
     */
    public function getOrderStatusesByUid(){

        $ret = Array();

        if(empty($this->orderManager)){
            $this->orderManager = $this->container->get("order_manager");
        }

        $orderStatuses = $this->orderManager->getOrderStatuses();

        if(EntityHelper::isCountable($orderStatuses) && count($orderStatuses)){
            /** @var OrderStateEntity $orderStatus */
            foreach ($orderStatuses as $orderStatus){
                $ret[$orderStatus->getCode()."-".$_ENV["LUCEED_ERP_SERVER_ID"]] = $orderStatus;
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getOrderReturnStatusesByUid(){

        $ret = Array();

        if(empty($this->orderReturnManager)){
            $this->orderReturnManager = $this->container->get("order_return_manager");
        }

        $orderReturnStatuses = $this->orderReturnManager->getOrderReturnStatuses();

        if(EntityHelper::isCountable($orderReturnStatuses) && count($orderReturnStatuses)){
            /** @var OrderReturnStateEntity $orderReturnStatus */
            foreach ($orderReturnStatuses as $orderReturnStatus){
                $ret[$orderReturnStatus->getCode()."-".$_ENV["LUCEED_ERP_SERVER_ID"]] = $orderReturnStatus;
            }
        }

        return $ret;
    }

    /**
     * @param $uid
     * @return array|string
     * @throws \Exception
     */
    public function getInvoiceDataByOrderUid($uid){

        $mpRacData = $this->get("mpracuni/nalogprodaje", $uid);

        if(empty($mpRacData)){
            throw new \Exception("No info for order uid {$uid}");
        }

        return $mpRacData;
    }

    /**
     * @param $uid
     * @return array
     * @throws \Exception
     */
    public function getInvoicePdfByUid($uid){

        $mpRacData = $this->get("mpracuni/pdf", $uid);

        if(empty($mpRacData)){
            throw new \Exception("No info for invoic uid {$uid}");
        }

        return $mpRacData;
    }

    /**
     * @param $postalCode
     * @return array
     * @throws \Exception
     */
    public function getCityByPostalCode($postalCode){

        $ret = $this->get("mjesta/postanskibroj", $postalCode);

        return $ret;
    }

    /**
     * @param $code
     * @return array
     * @throws \Exception
     */
    public function getCityByCode($code){

        $ret = $this->get("mjesta/sifra", $code);

        return $ret;
    }

    /**
     * @param CityEntity $city
     */
    public function createCity(CityEntity $city){

        /** @var CountryEntity $country */
        $country = $city->getCountry();
        if(empty($country)){
            throw new \Exception("Missing country for city ".$city->getId());
        }

        $data = array();
        $data["mjesto_b2b"] = $city->getId();
        $data["naziv"] = $city->getName();
        $data["postanski_broj"] = $city->getPostalCode();
        $data["mjesto"] = "web_".$city->getId();
        $data["drzava"] = $country->getCode();
        $data["drzava_uid"] = $country->getRemoteUid();
        $data["drzava_b2b"] = $city->getCountryId();

        $post = array();
        $post["mjesta"][0] = $data;

        $uid = $this->post("mjesta/snimi", $post);

        if(empty($uid)){
            throw new \Exception("Cannot create city ".$city->getId());
        }

        return $uid;
    }

    /**
     * @param $postalCode
     * @return array
     * @throws \Exception
     */
    public function getCountryByCode($postalCode){

        //http://apidemo.luceed.hr/datasnap/rest/sifrarnici/list
        $ret = $this->get("sifrarnici/list", "drzava");

        return $ret;
    }
}
