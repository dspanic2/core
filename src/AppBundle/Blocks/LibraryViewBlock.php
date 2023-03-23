<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Entity\ListView;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\ListViewManager;
use Monolog\Logger;

class LibraryViewBlock extends AbstractBaseBlock
{
    /** @var Logger $logger */
    protected $logger;

    public function GetPageBlockTemplate()
    {
        return "AppBundle:Block:" . $this->pageBlock->getType() . ".html.twig";
    }

    public function GetPageBlockData()
    {
        /** @var ListViewManager $listViewManager */
        $listViewManager = $this->factoryManager->loadListViewManager($this->pageBlock->getEntityType()->getEntityTypeCode());
        $this->pageBlockData["model"] = $listViewManager->getListViewModel($this->pageBlock->getRelatedId());

        /** @var AttributeContext $attributeContext */
        $attributeContext = $this->container->get("attribute_context");
        $fileAttribute = $attributeContext->getOneBy(Array("entityType" => $this->pageBlock->getEntityType(), "attributeCode" => "file"));

        if (empty($fileAttribute)) {
            $this->logger = $this->container->get("logger");
            $this->logger->error("Block id " . $this->pageBlock->getId() . " of type " . $this->pageBlock->getType() . " has no file attribute");
            return false;
        }

        $this->pageBlockData["fileAttributeId"] = $fileAttribute->getId();
        $this->pageBlockData["dropzoneSettings"] = json_decode($fileAttribute->getValidator());

        if (empty($this->pageBlockData["model"])) {
            return false;
        }

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return "AppBundle:BlockSettings:" . $this->pageBlock->getType() . ".html.twig";
    }

    public function GetPageBlockSetingsData()
    {
        /** @var ListViewContext $listViewContext */
        $listViewContext = $this->container->get("list_view_context");

        $listViews = $listViewContext->getAll();

        return array(
            "entity" => $this->pageBlock,
            "list_views" => $listViews,
        );
    }

    public function SavePageBlockSettings($data)
    {
        /** @var BlockManager $blockManager */
        $blockManager = $this->container->get("block_manager");

        /** @var ListViewContext $listViewContext */
        $listViewContext = $this->container->get("list_view_context");

        /** @var ListView $listView */
        $listView = $listViewContext->getById($data["relatedId"]);

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setRelatedId($data["relatedId"]);
        $this->pageBlock->setEntityType($listView->getEntityType());
        $this->pageBlock->setAttributeSet($listView->getAttributeSet());
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }

    public function isVisible()
    {
        $id = $this->pageBlockData["id"];
        $type = $this->pageBlockData["type"];
        if ($id == null && $type == "form") {
            return false;
        } else {
            return true;
        }
    }
}
