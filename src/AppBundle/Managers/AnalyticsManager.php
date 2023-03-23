<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use CrmBusinessBundle\Entity\CurrencyEntity;
use CrmBusinessBundle\Managers\QuoteManager;
use Doctrine\Common\Inflector\Inflector;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\RouteManager;

class AnalyticsManager extends AbstractBaseManager
{
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;

    public function initialize()
    {
        parent::initialize();
    }

    public function prepareAnalyticsQuery($query,$data)
    {
        $currentDate = new \DateTime();

        /**
         * Set basic variables
         */
        if(isset($data["page_id"])){
            $query = str_replace("{id}", $data["page_id"], $query);
        }
        if(isset($data["group_ids"])){
            $query = str_replace("{group_ids}", $data["group_ids"], $query);
        }
        $query = str_replace("{now}", $currentDate->format("Y-m-d H:i:s"), $query);
        //TODO ovdje dodajemo dalje

        /**
         * Set store id
         */
        if(stripos($query,"{store_id}") !== false){
            if(isset($_COOKIE["report_store"]) && !empty($_COOKIE["report_store"])){
                $query = str_replace("{store_id}", urldecode($_COOKIE["report_store"]), $query);
            }
            elseif(isset($data["store_id"])){
                $query = str_replace("{store_id}", $data["store_id"], $query);
            }
            else{
                if(empty($this->routeManager)){
                    $this->routeManager = $this->container->get("route_manager");
                }
                $stores = $this->routeManager->getStores();

                $storeIds = Array();
                /** @var SStoreEntity $store */
                foreach ($stores as $store){
                    $storeIds[] = $store->getId();
                }

                $query = str_replace("{store_id}", implode(",",$storeIds), $query);
            }
        }

        /**
         * Set default store Id
         */
        if(stripos($query,"{default_store_id}") !== false){
            if(isset($_COOKIE["report_store"]) && !empty($_COOKIE["report_store"])){
                $tmp = explode(",",urldecode($_COOKIE["report_store"]));
                $query = str_replace("{default_store_id}", $tmp[0], $query);
            }
            else{
                $query = str_replace("{default_store_id}", $ENV["DEFAULT_STORE_ID"] ?? 3, $query);
            }
        }

        /**
         * Set default currency code
         */
        if(stripos($query,"{default_currency_code}") !== false){

            if(empty($this->quoteManager)){
                $this->quoteManager = $this->container->get("quote_manager");
            }

            /** @var CurrencyEntity $currency */
            $currency = $this->quoteManager->getCurrencyById($_ENV["DEFAULT_CURRENCY"] ?? 1);
            $currencyCode = "Missing currency code";

            if(!empty($currency)){
                $currencyCode = $currency->getSign();
            }

            $query = str_replace("{default_currency_code}", $currencyCode, $query);
        }

        /**
         * Set analytics range
         */
        if(stripos($query,"{date_from}") !== false) {
            $date_to = $currentDate->format("Y-m-d");
            $date_from_obj = $currentDate->sub(new \DateInterval('P1M'));

            if (isset($_COOKIE["report_range"])) {
                $tmp = explode(" - ", $_COOKIE["report_range"]);
                $date_from_obj = \DateTime::createFromFormat("d/m/Y", $tmp[0]);
                $date_from = $date_from_obj->format("Y-m-d");
                $date_to_obj = \DateTime::createFromFormat("d/m/Y", $tmp[1]);
                $date_to = $date_to_obj->format("Y-m-d");
            }
            else{
                $date_from = $date_from_obj->format("Y-m-d");
            }

            $query = str_replace("{date_from}", "'" . $date_from . "'", $query);
            $query = str_replace("{date_to}", "'" . $date_to . "'", $query);
        }

        return $query;
    }

    /**
     * @param $numberOfDays
     * @return bool
     */
    public function cleanAnalytics($numberOfDays){

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "DELETE FROM s_product_search_results_entity WHERE created < DATE_SUB(CURRENT_DATE(), INTERVAL {$numberOfDays} DAY);";
        $this->databaseContext->executeNonQuery($q);

        $q = "DELETE FROM shape_track WHERE event_time < DATE_SUB(CURRENT_DATE(), INTERVAL {$numberOfDays} DAY);";
        $this->databaseContext->executeNonQuery($q);

        $q = "DELETE FROM shape_track_search_transaction WHERE created < DATE_SUB(CURRENT_DATE(), INTERVAL {$numberOfDays} DAY);";
        $this->databaseContext->executeNonQuery($q);

        $q = "DELETE FROM shape_track_product_impressions_transaction WHERE created < DATE_SUB(CURRENT_DATE(), INTERVAL {$numberOfDays} DAY);";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }
}
