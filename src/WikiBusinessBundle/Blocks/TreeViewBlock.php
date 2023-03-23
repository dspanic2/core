<?php

namespace WikiBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Managers\BlockManager;
use WikiBusinessBundle\Managers\WikiManager;

class TreeViewBlock extends AbstractBaseBlock
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
        /** @var WikiManager $wikiManager */
        $wikiManager = $this->getContainer()->get("wiki_manager");

        $treeView = $wikiManager->getTreeViewData(/*$this->pageBlockData["type"]*/);

        $this->pageBlockData["model"]["tree"] = empty($treeView) ? null : json_encode($treeView);

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