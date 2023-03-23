<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Managers\CacheManager;

class StatisticsManager extends AbstractBaseManager
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    /**
     * @param $fromDateTime
     * @return bool
     */
    public function regenerateStatistics($fromDateTime)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $this->updateShapeTrackProductImpressionTransaction($fromDateTime);

        $this->insertUpdateOrderItemFact($fromDateTime);

        $q = "SELECT date_dim_id, store_id, GROUP_CONCAT(product_groups,'#') as product_groups FROM shape_track_order_item_fact WHERE modified >= '{$fromDateTime}' GROUP BY date_dim_id, store_id;";
        $data = $this->databaseContext->getAll($q);

        if (empty($data)) {
            return false;
        }

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }
        $this->cacheManager->invalidateCacheByTag("general_statistics");

        foreach ($data as $d) {
            $this->insertTotalsFact($d["store_id"], $d["date_dim_id"], "'{$_ENV["ANALYTICS_SUCCESS_ORDER_STATE_IDS"]}'", "'{$_ENV["ANALYTICS_CANCELED_ORDER_STATE_IDS"]}'", "'{$_ENV["ANALYTICS_QUOTE_ORDER_STATE_IDS"]}'");

            $tmp_product_groups = str_ireplace("#,", "#", $d["product_groups"]);
            $tmp_product_groups = str_ireplace("##", "#", $tmp_product_groups);
            $tmp_product_groups = ltrim($tmp_product_groups, "#");
            $tmp_product_groups = rtrim($tmp_product_groups, "#");
            $tmp_product_groups = explode("#", $tmp_product_groups);

            if (!empty($tmp_product_groups)) {

                $product_groups = array();

                foreach ($tmp_product_groups as $tmp_product_group) {
                    if (!empty($tmp_product_group)) {
                        $product_groups[] = $tmp_product_group;
                    }
                }

                $product_groups = array_unique($product_groups);

                foreach ($product_groups as $product_group_id) {
                    $this->insertProductGroupFact($d["store_id"], $d["date_dim_id"], $product_group_id, "'{$_ENV["ANALYTICS_SUCCESS_ORDER_STATE_IDS"]}'", "'{$_ENV["ANALYTICS_CANCELED_ORDER_STATE_IDS"]}'", "'{$_ENV["ANALYTICS_QUOTE_ORDER_STATE_IDS"]}'");
                }
            }
        }


        return true;
    }

    /**
     * @param $fromDateTime
     * @return bool
     */
    public function updateShapeTrackProductImpressionTransaction($fromDateTime)
    {

        $q = "UPDATE shape_track_product_impressions_transaction as stp 
        LEFT JOIN tracking_entity as t ON stp.session_id = t.session_id
        SET stp.email = t.email, stp.first_name = t.first_name, stp.last_name = t.last_name, stp.contact_id = t.contact_id
        WHERE stp.email is null and t.email is not null and stp.created >= '{$fromDateTime}';";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /** PROCEDURES CALL */
    /**
     * @param $store_id
     * @param $date_dim_id
     * @param $success_order_state_ids
     * @param $canceled_order_state_ids
     * @param $quote_state_ids
     * @return bool
     */
    public function insertTotalsFact($store_id, $date_dim_id, $success_order_state_ids, $canceled_order_state_ids, $quote_state_ids)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        return $this->databaseContext->executeNonQuery("CALL sp_insert_totals_fact(" . $store_id . "," . $date_dim_id . "," . $success_order_state_ids . "," . $canceled_order_state_ids . "," . $quote_state_ids . ")");
    }

    /**
     * @param $store_id
     * @param $date_dim_id
     * @param $product_group_id
     * @param $success_order_state_ids
     * @param $canceled_order_state_ids
     * @param $quote_state_ids
     * @return bool
     */
    public function insertProductGroupFact($store_id, $date_dim_id, $product_group_id, $success_order_state_ids, $canceled_order_state_ids, $quote_state_ids)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        return $this->databaseContext->executeNonQuery("CALL sp_insert_product_group_fact(" . $store_id . "," . $date_dim_id . "," . $product_group_id . "," . $success_order_state_ids . "," . $canceled_order_state_ids . "," . $quote_state_ids . ")");
    }

    /**
     * @param $fromDateTime
     * @return bool
     */
    public function insertUpdateOrderItemFact($fromDateTime)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        return $this->databaseContext->executeNonQuery("CALL sp_insert_order_item_fact('" . $fromDateTime . "')");
    }
}
