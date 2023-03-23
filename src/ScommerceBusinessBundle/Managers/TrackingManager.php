<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Helpers\TrafficHelper;
use AppBundle\Managers\DatabaseManager;
use Symfony\Component\HttpFoundation\Request;

class TrackingManager extends AbstractBaseManager
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var DatabaseManager $databaseManager */
    protected $databaseManager;
    /** @var ScommerceHelperManager $sCommerceHelperManager */
    protected $sCommerceHelperManager;

    /**
     * @param Request $request
     * @param $entityId
     * @param $entityTypeCode
     * @param $eventType
     * @param $eventName
     * @return bool
     */
    public function insertTrackingEvent(Request $request, $entityId, $entityTypeCode, $eventType, $eventName)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (empty($request->headers->get('User-Agent'))) {
            return true;
        }

        $clientIp = $_SERVER["HTTP_X_REAL_IP"] ?? null;
        if (empty($clientIp)) {
            $clientIp = $request->getClientIp();
        }

        if (TrafficHelper::detectBot($request->headers->get('User-Agent'))) {
            return true;
        }
        if (TrafficHelper::ipInRange($clientIp, "66.249.64.0/19")) {
            return true;
        }

        if (TrafficHelper::detectSqlInjection($request->headers->get('User-Agent'), "user_agent") || TrafficHelper::detectSqlInjection($request->server->get('HTTP_REFERER'), "user_agent")) {

            if (!empty($clientIp)) {
                if (empty($this->sCommerceHelperManager)) {
                    $this->sCommerceHelperManager = $this->container->get("scommerce_helper_manager");
                }

                $this->sCommerceHelperManager->addIpToBlockedIps($clientIp, 1, "sql injection");
            }

            return true;
        }
        //todo avoid other ip
        //commoncrawl
        //Applebot
        //PetalBot

        $sessionId = null;
        if (!empty($request->getSession())) {
            $sessionId = $request->getSession()->getId();
        }

        $userId = null;
        if (!empty($this->user) && is_object($this->user)) {
            $userId = $this->user->getId();
        }

        $storeId = 0;
        if (!empty($request->getSession()->get("current_store_id"))) {
            $storeId = $request->getSession()->get("current_store_id");
        }

        $refferer = addslashes($request->server->get('HTTP_REFERER'));
        $pathinfo = addslashes($request->getPathInfo());
        $uri = addslashes($request->getRequestUri());

        $q = "INSERT IGNORE INTO shape_track (session_id, event_time, url, full_url, page_id, page_type, previous, event_type, event_name, user_id, origin, source, useragent, http_status, ip_address, store_id) 
        VALUES ('{$sessionId}',NOW(),'{$pathinfo}','{$uri}','{$entityId}','{$entityTypeCode}','{$refferer}','{$eventType}','{$eventName}','{$userId}','{$request->headers->get('host')}',null,'{$request->headers->get('User-Agent')}','{$request->server->get('REDIRECT_STATUS')}', '{$request->getClientIp()}', '{$storeId}')";
        $q = str_ireplace("''", "NULL", $q);
        try {
            $this->databaseContext->executeNonQuery($q);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        if (!empty($sessionId) && $entityTypeCode == "product") {
            $this->insertProductImpression($entityId, $sessionId, $eventType, $eventName, $request->server->get('HTTP_REFERER'), $storeId);
        }

        return true;
    }

    /**
     * @param $entityId
     * @param $sessionId
     * @param $eventType
     * @param $eventName
     * @param $previous
     * @param $storeId
     * @return bool
     */
    public function insertProductImpression($entityId, $sessionId, $eventType, $eventName, $previous, $storeId)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "INSERT IGNORE INTO shape_track_product_impressions_transaction (product_id, session_id, email, first_name, last_name, contact_id, event_type, event_name, previous, store_id, created) 
        VALUES ('{$entityId}','{$sessionId}','','','','','{$eventType}','{$eventName}','{$previous}','{$storeId}',NOW())";
        $q = str_ireplace("''", "NULL", $q);
        try {
            $this->databaseContext->executeNonQuery($q);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param $originalQuery
     * @param $usedQuery
     * @param $numberOfResults
     * @param $listOfResults
     * @param $fromCache
     * @param $sessionId
     * @param $storeId
     * @return bool
     */
    public function insertSearch($originalQuery, $usedQuery, $numberOfResults, $listOfResults, $fromCache, $sessionId, $storeId)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "INSERT IGNORE INTO shape_track_search_transaction (original_query, used_query, list_of_results, from_cache, session_id, email, first_name, last_name, contact_id, number_of_results, store_id, created) 
        VALUES ('{$originalQuery}','{$usedQuery}','{$listOfResults}','{$fromCache}','{$sessionId}','','','','','{$numberOfResults}','{$storeId}',NOW())";
        $q = str_ireplace("''", "NULL", $q);
        try {
            $this->databaseContext->executeNonQuery($q);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param $numberOfDays
     * @return bool
     */
    public function cleanShapeTrack($numberOfDays = 1)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "DELETE FROM shape_track WHERE event_time < DATE_SUB(CURRENT_DATE(), INTERVAL {$numberOfDays} DAY) AND page_type = 'product';";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @return bool
     */
    public function updateShapeTrackTable()
    {

        if (empty($this->databaseManager)) {
            $this->databaseManager = $this->container->get("database_manager");
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (!$this->databaseManager->checkIfTableExists("shape_track")) {
            return false;
        }

        $q = $this->databaseManager->addColumnQuery("shape_track", "ip_address", "varchar(50)");
        $this->databaseContext->executeNonQuery($q);

        $q = $this->databaseManager->addColumnQuery("shape_track", "store_id", "tinyint(2) NOT NULL default '0'");
        $this->databaseContext->executeNonQuery($q);

        $q = $this->databaseManager->addColumnQuery("shape_track", "event_time", "DATETIME NOT NULL");
        $this->databaseContext->executeNonQuery($q);

        $q = $this->databaseManager->addColumnQuery("shape_track", "session_id", "varchar(50)");
        $this->databaseContext->executeNonQuery($q);

        $q = $this->databaseManager->addColumnQuery("shape_track", "user_id", "int(11)");
        $this->databaseContext->executeNonQuery($q);

        /**
         * remove user column
         */
        if ($this->databaseManager->checkIfFieldExists("shape_track", "user")) {
            $q = "SET FOREIGN_KEY_CHECKS=0; ALTER TABLE shape_track DROP COLUMN user; SET FOREIGN_KEY_CHECKS=1;";
            $this->databaseContext->executeNonQuery($q);
        }

        /**
         * remove time column
         */
        if ($this->databaseManager->checkIfFieldExists("shape_track", "time")) {
            $q = "SET FOREIGN_KEY_CHECKS=0; ALTER TABLE shape_track DROP COLUMN time; SET FOREIGN_KEY_CHECKS=1;";
            $this->databaseContext->executeNonQuery($q);
        }

        /**
         * remove cookie_id column
         */
        if ($this->databaseManager->checkIfFieldExists("shape_track", "cookie_id")) {
            $q = "SET FOREIGN_KEY_CHECKS=0; ALTER TABLE shape_track DROP COLUMN cookie_id; SET FOREIGN_KEY_CHECKS=1;";
            $this->databaseContext->executeNonQuery($q);
        }

        return true;
    }

    /**
     * @param string $filter
     * @param string $groupBy
     * @param string $orderBy
     * @return bool
     */
    public function getProductImpressions($filter = "", $groupBy = "", $orderBy = "")
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT product_id FROM shape_track_product_impressions_transaction {$filter} {$groupBy} {$orderBy};";
        return $this->databaseContext->getAll($q);
    }

    /**
     * @param string $filter
     * @param string $groupBy
     * @param string $orderBy
     * @return bool
     */
    public function getPageImpressions($filter = "", $groupBy = "", $orderBy = "")
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT DISTINCT page_id AS id FROM shape_track {$filter} {$groupBy} {$orderBy};";
        return $this->databaseContext->getAll($q);
    }

}
