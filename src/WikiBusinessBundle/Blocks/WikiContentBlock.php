<?php

namespace WikiBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\Page;
use AppBundle\Managers\BlockManager;
use WikiBusinessBundle\Managers\WikiManager;

class WikiContentBlock extends AbstractBaseBlock
{
    public function GetPageBlockTemplate()
    {
        if (isset($this->pageBlockData["model"]) && !empty($this->pageBlockData["model"])) {
            return ("WikiBusinessBundle:Block:" . $this->pageBlock->getType() . ".html.twig");
        } else {
            return ("AppBundle:Block:block_error.html.twig");
        }
    }

    public function GetPageBlockData()
    {
        $pageId = $this->pageBlockData["id"];

        /** @var Page $page */
        $page = $this->pageBlockData["page"];

        /** @var EntityType $etPage */
        $etPage = $page->getEntityType();

        /** @var WikiManager $wikiManager */
        $wikiManager = $this->getContainer()->get("wiki_manager");

        $pagesPath = $wikiManager->getWikiPath($pageId, $etPage);

        $this->pageBlockData["model"]["path"] = $pagesPath;

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return "WikiBusinessBundle:BlockSettings:" . $this->pageBlock->getType() . ".html.twig";
    }

    public function GetPageBlockSetingsData()
    {
        return array(
            "entity" => $this->pageBlock
        );
    }

    public function SavePageBlockSettings($data)
    {
        /** @var BlockManager $blockManager */
        $blockManager = $this->container->get("block_manager");

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);

        return $blockManager->save($this->pageBlock);
    }
}