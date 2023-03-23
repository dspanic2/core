<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractChartBlock;
use AppBundle\Context\DatabaseContext;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\CacheManager;

class ChoroplethChartBlock extends AbstractChartBlock
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    public function GetPageBlockTemplate()
    {
        return "AppBundle:Block:" . $this->pageBlock->getType() . ".html.twig";
    }

    public function GetPageBlockData()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $page_id = 0;
        if (isset($this->pageBlockData["id"])) {
            $page_id = $this->pageBlockData["id"];
        }

        $settings = $this->pageBlock->getContent();
        if (!is_array($settings)) {
            $settings = json_decode($settings, true);
        }

        if (!empty($settings)) {

            $query = $settings["datasource"];

            $data = Array();
            $data["page_id"] = $page_id;
            if(empty($this->analyticsManager)){
                $this->analyticsManager = $this->container->get("analytics_manager");
            }
            $query = $this->analyticsManager->prepareAnalyticsQuery($query,$data);
            $query = StringHelper::format("SELECT * FROM ({0})s", $query);

            $queryHash = md5($query);
            if (empty($this->cacheManager)) {
                $this->cacheManager = $this->container->get("cache_manager");
            }

            $cacheItem = $this->cacheManager->getCacheGetItem("choropleth_chart_{$queryHash}");

            if (empty($cacheItem) || isset($_GET["rebuild_analytics"])) {
                $rawArray = $this->databaseContext->executeMultiResultSetQuery($query);

                $cacheTag = Array();
                if (isset($settings["cache_tag"])){
                    $cacheTag = explode(",",$settings["cache_tag"]);
                }

                $this->cacheManager->setCacheItem("choropleth_chart_{$queryHash}", $rawArray, $cacheTag, "30 days");
            } else {
                $rawArray = $cacheItem->get();
            }

            if (!empty($rawArray)) {
                $choroplethArray = Array();
                foreach ($rawArray[0] as $item) {
                    $choroplethArray[] = Array(
                        $item["name"],
                        $item["count"]
                    );
                }
                $this->pageBlockData["items"] = json_encode($choroplethArray);
            }
        }

        $this->pageBlockData["gmaps_key"] = $_ENV["GMAPS_KEY"];
        if (isset($settings["tooltip"]))
            $this->pageBlockData["tooltip"] = $settings["tooltip"];

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return "AppBundle:BlockSettings:" . $this->pageBlock->getType() . ".html.twig";
    }

    public function GetPageBlockSetingsData()
    {
        $contentJson = $this->pageBlock->getContent();

        $content = json_decode($contentJson, true);

        $tooltip = "";
        $cache_tag = "";
        $datasource = "";

        if (!empty($content)) {
            if (isset($content["datasource"])) {
                $datasource = $content["datasource"];
            }
            if (isset($content["tooltip"]))
                $tooltip = $content["tooltip"];
            if (isset($content["cache_tag"]))
                $cache_tag = $content["cache_tag"];
        }

        return array(
            "entity" => $this->pageBlock,
            "datasource" => $datasource,
            'tooltip' => $tooltip,
            'cache_tag' => $cache_tag
        );
    }

    public function SavePageBlockSettings($data)
    {
        /** @var BlockManager $blockManager */
        $blockManager = $this->container->get("block_manager");

        $content = array();
        $content["datasource"] = preg_replace("/(\v|\s)+/", " ", $data["datasource"]);
        $content["tooltip"] = $data["tooltip"];
        $content["cache_tag"] = $data["cache_tag"];

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setContent(json_encode($content));
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }
}
