<?php

namespace AppBundle\Blocks;

use AppBundle\Entity\EntityType;
use AppBundle\Entity\Page;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\NoteManager;
use Symfony\Component\VarDumper\VarDumper;
use AppBundle\Abstracts\AbstractBaseBlock;

class NotesBlock extends AbstractBaseBlock
{
    public function GetPageBlockTemplate()
    {
        return "AppBundle:Block:notes.html.twig";
    }

    public function GetPageBlockData()
    {
        $id = $this->pageBlockData["id"];

        /** @var Page $page */
        $page = $this->pageBlockData["page"];

        if (is_array($page)) {
            /** @var EntityManager $entityManager */
            $entityManager = $this->container->get("entity_manager");
            $page = $entityManager->getEntityByEntityTypeCodeAndId($this->pageBlockData["block"]->getAttributeSet()->getAttributeSetCode(), $this->pageBlockData["id"]);
        }

        $this->pageBlockData["model"]["page"] = $page;

        /** @var EntityType $etPageBlock */
        $etPageBlock = $page->getEntityType();
        /** @var NoteManager $noteManager */
        $noteManager = $this->container->get("note_manager");

        $notes = $noteManager->getNotesForEntity($etPageBlock->getEntityTypeCode(), $id);
        $this->pageBlockData["model"]["notes"] = $notes;

        return $this->pageBlockData;
    }

    public function isVisible()
    {
        return !empty($this->pageBlockData["id"]);
    }

    public function GetPageBlockSetingsTemplate()
    {
        return "AppBundle:BlockSettings:notes.html.twig";
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