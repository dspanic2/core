<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractChartBlock;
use AppBundle\Context\DatabaseContext;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\CacheManager;
use Doctrine\DBAL\Connection;

class BubbleChartBlock extends AbstractChartBlock
{
    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;

    public function GetPageBlockData()
    {
        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $settings = $this->pageBlock->getContent();

        $page_id = 0;
        $rawArray = array();

        if (isset($this->pageBlockData["id"])) {
            $page_id = $this->pageBlockData["id"];
        }

        if (!is_array($settings)) {
            $settings = json_decode($settings, true);
        }

        if (isset($settings["datasource"]) && !empty($settings)) {
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

            $cacheItem = $this->cacheManager->getCacheGetItem("bubble_chart_{$queryHash}");

            if (empty($cacheItem) || isset($_GET["rebuild_analytics"])) {
                $rawArray = $this->databaseContext->getAll($query);

                $cacheTag = Array();
                if (isset($settings["cache_tag"])){
                    $cacheTag = explode(",",$settings["cache_tag"]);
                }

                $this->cacheManager->setCacheItem("bubble_chart_{$queryHash}", $rawArray, $cacheTag, "30 days");
            } else {
                $rawArray = $cacheItem->get();
            }
        }

        $this->pageBlockData["dataset"] = json_encode($rawArray);
        if (isset($settings["tooltip"])) {
            $this->pageBlockData["tooltip"] = $settings["tooltip"];
        }

        return $this->pageBlockData;
    }

    /**
     * EXAMPLE
     */
    /*dataset = {
        "children": [{"Name":"Olives","Count":4319,"group":"low"},
            {"Name":"Tea","Count":4319,"group":"low"},
            {"Name":"Mashed Potatoes","Count":4319,"group":"low"},
            {"Name":"Boiled Potatoes","Count":4319,"group":"low"},
            {"Name":"Milk","Count":4319,"group":"low"},
            {"Name":"Chicken Salad","Count":1809,"group":"med"},
            {"Name":"Vanilla Ice Cream","Count":1713,"group":"med"},
            {"Name":"Cocoa","Count":1636,"group":"med"},
            {"Name":"Lettuce Salad","Count":1566,"group":"med"},
            {"Name":"Lobster Salad","Count":1511,"group":"med"},
            {"Name":"Chocolate","Count":1489,"group":"high"},
            {"Name":"Apple Pie","Count":1487,"group":"high"},
            {"Name":"Orange Juice","Count":1423,"group":"high"},
            {"Name":"American Cheese","Count":1372,"group":"high"},
            {"Name":"Green Peas","Count":1341,"group":"med"},
            {"Name":"Assorted Cakes","Count":1331,"group":"med"},
            {"Name":"French Fried Potatoes","Count":1328,"group":"med"},
            {"Name":"Potato Salad","Count":1306,"group":"med"},
            {"Name":"Baked Potatoes","Count":1293,"group":"med"},
            {"Name":"Roquefort","Count":1273,"group":"med"},
            {"Name":"Stewed Prunes","Count":1268,"group":"med"}]
    };*/

    public function GetPageBlockSetingsTemplate()
    {
        return "AppBundle:BlockSettings:" . $this->pageBlock->getType() . ".html.twig";
    }

    public function GetPageBlockSetingsData()
    {
        $contentJson = $this->pageBlock->getContent();

        $content = json_decode($contentJson, true);

        $datasource = "";
        $tooltip = "";
        $cache_tag = "";

        if (!empty($content)) {
            if (isset($content["datasource"])) {
                $datasource = $content["datasource"];
            }
            if (isset($content["tooltip"])) {
                $tooltip = $content["tooltip"];
            }
            if (isset($content["cache_tag"])) {
                $cache_tag = $content["cache_tag"];
            }
        }

        return array(
            "entity" => $this->pageBlock,
            "datasource" => $datasource,
            "tooltip" => $tooltip,
            "cache_tag" => $cache_tag
        );
    }

    public function SavePageBlockSettings($data)
    {
        /** @var BlockManager $blockManager */
        $blockManager = $this->container->get("block_manager");

        $content = [];
        $content["datasource"] = preg_replace('/(\v|\s)+/', " ", $data["datasource"]);
        $content["tooltip"] = $data["tooltip"];
        $content["cache_tag"] = $data["cache_tag"];

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setContent(json_encode($content));
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }
}
