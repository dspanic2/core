<?php

namespace DPDBusinessBundle\Blocks;

use AppBundle\Entity\EntityType;
use AppBundle\Entity\Page;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\NoteManager;
use Symfony\Component\VarDumper\VarDumper;
use AppBundle\Abstracts\AbstractBaseBlock;

class DPDManifestBlock extends AbstractBaseBlock
{
    public function GetPageBlockTemplate()
    {
        return "DPDBusinessBundle:Block:dpd_manifest.html.twig";
    }

    public function GetPageBlockData()
    {
        return $this->pageBlockData;
    }

    public function isVisible()
    {
        return true;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return "DPDBusinessBundle:BlockSettings:dpd_manifest.html.twig";
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
