<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\DatabaseContext;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\AnalyticsManager;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\CacheManager;
use Doctrine\ORM\Mapping\Cache;
use ScommerceBusinessBundle\Managers\TemplateManager;
use Symfony\Component\Cache\CacheItem;

class ReportViewBlock extends AbstractBaseBlock
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var TemplateManager $templateManager */
    protected $templateManager;
    /** @var AnalyticsManager $analyticsManager */
    protected $analyticsManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    public function GetPageBlockTemplate()
    {
        return "AppBundle:Block:report_view.html.twig";
    }

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

        if (!empty($settings)) {
            $query = $settings["datasource"];

            $data = Array();
            $data["page_id"] = $page_id;
            if(empty($this->analyticsManager)){
                $this->analyticsManager = $this->container->get("analytics_manager");
            }
            $query = $this->analyticsManager->prepareAnalyticsQuery($query,$data);

            $group_concat_max = "";
            if(stripos($query,"GROUP_CONCAT") !== false){
                $group_concat_max = "SET SESSION group_concat_max_len = 1000000;";
            }
            $query = StringHelper::format("{$group_concat_max}SELECT * FROM ({0})s", $query);

            $queryHash = md5($query);
            if (empty($this->cacheManager)) {
                $this->cacheManager = $this->container->get("cache_manager");
            }

            $cacheItem = $this->cacheManager->getCacheGetItem("report_view_{$queryHash}");

            if (empty($cacheItem) || isset($_GET["rebuild_analytics"])) {
                $rawArray = $this->databaseContext->executeMultiResultSetQuery($query);
                $rawArray = $rawArray[0];

                $cacheTag = Array();
                if (isset($settings["cache_tag"])){
                    $cacheTag = explode(",",$settings["cache_tag"]);
                }

                $this->cacheManager->setCacheItem("report_view_{$queryHash}", $rawArray, $cacheTag, "30 days");
            } else {
                $rawArray = $cacheItem->get();
            }
        }

        $this->pageBlockData["items"] = $rawArray;

        if (isset($settings["template"]) && !empty($settings["template"])) {
            $this->templateManager = $this->container->get("template_manager");
            $template = $this->templateManager->getTemplatePathByBundle("Includes:" . $settings["template"]);

            $this->pageBlockData["html"] = $this->container->get("templating")->render($template, array("data" => $this->pageBlockData));
        } else {
            $html = $this->container->get("twig")->createTemplate($settings["definition"]);
            $this->pageBlockData["html"] = $html->render(array("data" => $this->pageBlockData));
        }

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

        $datasource = "";
        $definition = "";
        $template = "";
        $tooltip = "";
        $cache_tag = "";

        if (!empty($content)) {
            if (isset($content["datasource"])) {
                $datasource = $content["datasource"];
            }
            if (isset($content["definition"])) {
                $definition = $content["definition"];
            }
            if (isset($content["template"])) {
                $template = $content["template"];
            }
            if (isset($content["tooltip"])) {
                $tooltip = $content["tooltip"];
            }
            if (isset($content["cache_tag"]))
                $cache_tag = $content["cache_tag"];
        }

        return array(
            "entity" => $this->pageBlock,
            "datasource" => $datasource,
            "definition" => $definition,
            "template" => $template,
            "tooltip" => $tooltip,
            'cache_tag' => $cache_tag
        );
    }

    public function SavePageBlockSettings($data)
    {
        /** @var BlockManager $blockManager */
        $blockManager = $this->container->get("block_manager");

        $content = [];
        $content["datasource"] = preg_replace('/(\v|\s)+/', " ", $data["datasource"]);
        $content["definition"] = preg_replace('/(\v|\s)+/', " ", $data["definition"]);
        $content["template"] = $data["template"];
        $content["tooltip"] = $data["tooltip"];
        $content["cache_tag"] = $data["cache_tag"];

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setContent(json_encode($content));
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }
}
