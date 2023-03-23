<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractChartBlock;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\CacheManager;

class StackedBarChartBlock extends AbstractChartBlock
{
    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    protected function GetChartData()
    {
        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $settings = $this->pageBlock->getContent();
        $groups = array();
        $columns = array();
        $categories = array();
        $selectValues = "";
        $filters = array();
        $orderby = "";
        $condition = "";
        $parameters = array();
        $this->pageBlockData["is_filtered"] = false;
        $currentDate = new \DateTime();
        $page_id = 0;

        if (isset($this->pageBlockData["id"])) {
            $page_id = $this->pageBlockData["id"];
        }


        if (isset($_SESSION["filter_block_" . $this->pageBlock->getId()])) {
            $filters = ($_SESSION["filter_block_" . $this->pageBlock->getId()]);
            if (count($filters) > 0)
                $this->pageBlockData["is_filtered"] = true;
        }


        if (isset($this->pageBlockData["id"])) {
            $parameters[] = array("key" => "id", "values" => $this->pageBlockData["id"], "type" => \PDO::PARAM_STR);
        }

        if (!is_array($settings))
            $settings = json_decode($settings, true);

        $values = $settings["yaxis"];
        $levels = $settings["xaxis"];

        if (isset($settings["orderby"]))
            $orderby = "ORDER BY " . implode($settings["orderby"]);


        foreach ($values as $value) {
            $selectValues = StringHelper::format("{0},{1}({2}) as {2}", $selectValues, $value["aggregate"], $value["field"]);
            $this->pageBlockData["format"] = $value["format"] ?? "default";
            $this->pageBlockData["currency_code"] = $value["currency_code"] ?? "kn";
        }

        if (count($filters) > 0) {
            $condition = " WHERE ";
            foreach ($filters as $key => $filter) {
                $parameters[] = array("key" => $key, "values" => $filter, "type" => Connection::PARAM_STR_ARRAY);
                $condition .= StringHelper::format(" {0} IN (:{0}) AND", $key);
            }
            $condition = substr($condition, 0, -3);
        }

        if (!empty($settings)) {
            $query = $settings["source"];
            $query = StringHelper::format("SELECT {0}{1} FROM({2})s {3} GROUP BY {0} {4}", implode(",", $levels), $selectValues, $query, $condition, $orderby);
            $query = preg_replace('/(\v|\s)+/', ' ', $query);

            $data = Array();
            $data["page_id"] = $page_id;
            if(empty($this->analyticsManager)){
                $this->analyticsManager = $this->container->get("analytics_manager");
            }
            $query = $this->analyticsManager->prepareAnalyticsQuery($query,$data);

            $queryHash = md5($query).$page_id;
            if (empty($this->cacheManager)) {
                $this->cacheManager = $this->container->get("cache_manager");
            }

            $cacheItem = $this->cacheManager->getCacheGetItem("stacked_bar_chart_{$queryHash}");

            if (empty($cacheItem) || isset($_GET["rebuild_analytics"])) {
                $rawArray = $this->databaseContext->executeQueryWithParameters($query, $parameters);

                $cacheTag = Array();
                if (isset($settings["cache_tag"])){
                    $cacheTag = explode(",",$settings["cache_tag"]);
                }

                $this->cacheManager->setCacheItem("stacked_bar_chart_{$queryHash}", $rawArray, $cacheTag, "30 days");
            } else {
                $rawArray = $cacheItem->get();
            }

            foreach ($rawArray as $item) {
                if (!in_array($item[$levels[1]], $categories)) {
                    if ($item[$levels[1]] != "")
                        $categories[] = $item[$levels[1]];

                }

                if (!in_array($item[$levels[0]], $groups))
                    if ($item[$levels[0]] != "")
                        $groups[] = $item[$levels[0]];
            }


            foreach ($groups as $group) {
                $points = array();
                $points[] = $group;

                foreach ($categories as $category) {

                    $value = null;

                    foreach ($rawArray as $item) {

                        if ($item[$levels[0]] == $group && $item[$levels[1]] == $category) {
                            $value = $item[$values[0]["field"]];
                        }
                    }

                    $points[] = $value;
                }
                $columns[] = $points;
            }


            $groups = json_encode(array($groups), JSON_NUMERIC_CHECK);
            $columns = json_encode($columns, JSON_NUMERIC_CHECK);
            $categories = json_encode($categories, JSON_NUMERIC_CHECK);

            $this->pageBlockData["columns"] = $columns;
            $this->pageBlockData["groups"] = $groups;

            $this->pageBlockData["categories"] = $categories;

            if (isset($settings["tooltip"]))
                $this->pageBlockData["tooltip"] = $settings["tooltip"];
        }
    }


}