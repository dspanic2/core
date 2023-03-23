<?php

namespace TaskBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Managers\ListViewManager;

class TaskListViewBlock extends AbstractBaseBlock
{

    /**@var ListViewManager $listViewManager */
    protected $listViewManager;


    public function GetPageBlockTemplate()
    {
        if (isset($this->pageBlockData["model"]) && !empty($this->pageBlockData["model"])) {
            return ('TaskBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
        } else {
            return ('AppBundle:Block:block_error.html.twig');
        }
    }

    public function GetAdvancedSearchTemplate()
    {
        $content = $this->pageBlock->getContent();

        if (!empty($content) && isset($content["advanced_search"])) {
            return $content["advanced_search"];
        }

        return ('AppBundle:Includes:advanced_search_form.html.twig');
    }

    public function GetPageBlockData()
    {

        if (empty($this->pageBlock->getEntityType()) || empty($this->pageBlock->getRelatedId())) {
            return $this->pageBlockData;
        }

        $this->listViewManager = $this->factoryManager->loadListViewManager($this->pageBlock->getEntityType()->getEntityTypeCode());
        $this->pageBlockData["model"] = $this->listViewManager->getListViewModel($this->pageBlock->getRelatedId());

        if (empty($this->pageBlockData["model"])) {
            return $this->pageBlockData;
        }

        return $this->pageBlockData;

    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'TaskBusinessBundle:BlockSettings:' . $this->pageBlock->getType() . '.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        $listViewContext = $this->container->get('list_view_context');
        $listViews = $listViewContext->getBy(array(), array("attributeSet" => "asc"));

        return array(
            'entity' => $this->pageBlock,
            'list_views' => $listViews,
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        $listViewContext = $this->container->get('list_view_context');
        $listView = $listViewContext->getById($data["relatedId"]);

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setRelatedId($data["relatedId"]);
        $this->pageBlock->setEntityType($listView->getAttributeSet()->getEntityType());
        $this->pageBlock->setAttributeSet($listView->getAttributeSet());
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);

    }


}