<?php

namespace AppBundle\Abstracts;

use AppBundle\Context\DatabaseContext;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\AnalyticsManager;
use Doctrine\DBAL\Connection;


/**
 * Base properties and methods that can be used when implementing new chart definitions
 *
 */
abstract class AbstractChartBlock extends AbstractBaseBlock
{

    /**@var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var AnalyticsManager $analyticsManager */
    protected $analyticsManager;

    public function isVisible()
    {
        if ($this->pageBlockData["type"] == "form") {
            if ($this->pageBlockData["id"] == null)
                return false;
        }

        return true;
    }

    public function GetPageBlockTemplate()
    {
        if ($this->pageBlockData["type"] == "filter") {
            return ('AppBundle:Block:filter_data.html.twig');
        }

        return ('AppBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
    }

    public function GetPageBlockData()
    {
        if ($this->pageBlockData["type"] == "filter") {
            $this->GetFilterData();
        } else {
            $this->GetChartData();
        }
        return $this->pageBlockData;
    }

    protected function GetChartData()
    {
        $this->databaseContext = $this->container->get("database_context");

        $settings = $this->pageBlock->getContent();
        $categories = array();
        $columns = array();
        $selectValues = "";
        $orderby = "";
        $filters = array();
        $condition = "";
        $parameters = array();
        $this->pageBlockData["is_filtered"] = false;

        if (isset($_SESSION["filter_block_" . $this->pageBlock->getId()])) {
            $filters = ($_SESSION["filter_block_" . $this->pageBlock->getId()]);
            if (count($filters) > 0)
                $this->pageBlockData["is_filtered"] = true;
        }

        $page_id = 0;

        if (isset($this->pageBlockData["id"])) {
            $parameters[] = array("key" => "id", "values" => $this->pageBlockData["id"], "type" => \PDO::PARAM_STR);
            $page_id = $this->pageBlockData["id"];
        }

        if (!is_array($settings))
            $settings = json_decode($settings, true);

        $values = $settings["yaxis"];
        $levels = $settings["xaxis"];

        if (isset($settings["orderby"]) && !empty($settings["orderby"]))
            $orderby = "ORDER BY " . implode($settings["orderby"]);

        foreach ($values as $value) {
            $selectValues = StringHelper::format("{0},{1}({2}) as {2}", $selectValues, $value["aggregate"], $value["field"]);
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
            $query = StringHelper::format("SELECT {0}{1} FROM({2})s {3} GROUP BY {0} {4}", $levels[0], $selectValues, $query, $condition,$orderby);
            $query = preg_replace('/(\v|\s)+/', ' ', $query);

            $data = Array();
            $data["page_id"] = $page_id;
            if(empty($this->analyticsManager)){
                $this->analyticsManager = $this->container->get("analytics_manager");
            }
            $query = $this->analyticsManager->prepareAnalyticsQuery($query,$data);

            $queryHash = md5($query);
            if (empty($this->cacheManager)) {
                $this->cacheManager = $this->container->get("cache_manager");
            }

            $cacheItem = $this->cacheManager->getCacheGetItem("abstract_chart_{$queryHash}");

            if (empty($cacheItem) || isset($_GET["rebuild_analytics"])) {
                $rawArray = $this->databaseContext->executeQueryWithParameters($query, $parameters);

                $cacheTag = Array();
                if (isset($settings["cache_tag"])){
                    $cacheTag = explode(",",$settings["cache_tag"]);
                }

                $this->cacheManager->setCacheItem("abstract_chart_{$queryHash}", $rawArray, $cacheTag, "30 days");
            } else {
                $rawArray = $cacheItem->get();
            }

            foreach ($rawArray as $item) {
                $column = array();
                $column[] = $item[$levels[0]];

                foreach ($values as $value) {
                    $column[] = $item[$value["field"]];
                }

                $columns[] = $column;
            }

            foreach ($values as $value) {
                $categories[] = $value["label"];
            }

            $categories = json_encode($categories, JSON_NUMERIC_CHECK);
            $columns = json_encode($columns, JSON_NUMERIC_CHECK);


            $this->pageBlockData["columns"] = $columns;
            $this->pageBlockData["categories"] = $categories;
            $this->pageBlockData["currency_code"] = $value["currency_code"] ?? "kn";
            $this->pageBlockData["format"] = $value["format"] ?? "default";
            $this->pageBlockData["query"] = $query;
            if (isset($settings["tooltip"]))
                $this->pageBlockData["tooltip"] = $settings["tooltip"];
        }
    }

    protected function GetFilterData()
    {
        $this->databaseContext = $this->container->get("database_context");
        $parameters = array();
        $settings = $this->pageBlock->getContent();
        $sessionFilters = array();

        if (isset($_SESSION["filter_block_" . $this->pageBlock->getId()])) {
            $sessionFilters = ($_SESSION["filter_block_" . $this->pageBlock->getId()]);
        }

        if (!is_array($settings))
            $settings = json_decode($settings, true);

        $page_id = 0;
        if (isset($this->pageBlockData["id"])) {
            $parameters[] = array("key" => "id", "values" => $this->pageBlockData["id"], "type" => \PDO::PARAM_STR);
            $page_id = $this->pageBlockData["id"];
        }

        if (!empty($settings)) {

            $query = $settings["source"];
            $query = preg_replace('/(\v|\s)+/', ' ', $query);

            $filters = $settings["filters"];

            foreach ($filters as $key => $filter) {
                if ($filter["type"] == "multichoice") {
                    $filterQuery = StringHelper::format("SELECT DISTINCT {0} FROM ({1})s;", $filter["field"], $query);

                    $data = Array();
                    $data["page_id"] = $page_id;
                    if(empty($this->analyticsManager)){
                        $this->analyticsManager = $this->container->get("analytics_manager");
                    }
                    $query = $this->analyticsManager->prepareAnalyticsQuery($query,$data);

                    $filterValues = $this->databaseContext->executeQueryWithParameters($filterQuery, $parameters);

                    foreach ($filterValues as $k => $filterValue) {
                        $is_selected = false;
                        if (array_key_exists($filter["field"], $sessionFilters)) {
                            if (in_array($filterValue[$filter["field"]], $sessionFilters[$filter["field"]])) {
                                $is_selected = true;
                            }
                        }
                        $filters[$key]["values"][] = array("value" => $filterValue[$filter["field"]], "selected" => $is_selected);
                    }
                }
            }

            $this->pageBlockData["filters"] = $filters;
        }
    }

    public function FilterData()
    {
        $this->GetChartData();

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:chart_default.html.twig';
    }


    public function GetPageBlockSetingsData()
    {
        $contentJSON = $this->pageBlock->getContent();

        $content = json_decode($contentJSON);

        $datasource = "";
        $xaxis = "";
        $yaxis = "";
        $filters = "";
        $orderby = "";
        $tooltip = "";
        $cache_tag = "";

        if ($content != null) {
            $datasource = $content->source;
            if (isset($content->xaxis))
                $xaxis = $content->xaxis;
            if (isset($content->yaxis))
                $yaxis = $content->yaxis;
            if (isset($content->filters))
                $filters = $content->filters;
            if (isset($content->orderby))
                $orderby = $content->orderby;
            if (isset($content->tooltip))
                $tooltip = $content->tooltip;
            if (isset($content->cache_tag))
                $cache_tag = $content->cache_tag;
        }


        return array(
            'entity' => $this->pageBlock,
            'datasource' => $datasource,
            'xaxis' => json_encode($xaxis),
            'yaxis' => json_encode($yaxis),
            'filters' => json_encode($filters),
            'orderby' => json_encode($orderby),
            'tooltip' => $tooltip,
            'cache_tag' => $cache_tag
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        $content = [];
        $content["source"] = (preg_replace('/(\v|\s)+/', " ", $data["datasource"]));
        $content["xaxis"] = json_decode($data["xaxis"]);
        $content["yaxis"] = json_decode($data["yaxis"]);
        $content["filters"] = json_decode($data["filters"]);
        $content["orderby"] = json_decode($data["orderby"]);
        $content["tooltip"] = $data["tooltip"];
        $content["cache_tag"] = $data["cache_tag"];

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setContent(json_encode($content));
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);

    }

}