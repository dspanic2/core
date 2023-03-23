<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\ListViewManager;
use Symfony\Component\Translation\Translator;

class ListViewBlock extends AbstractBaseBlock
{
    /**@var ListViewManager $listViewManager */
    protected $listViewManager;
    /**@var HelperManager $helperManager */
    protected $helperManager;

    public function GetPageBlockTemplate()
    {
        if (isset($this->pageBlockData["model"]) && !empty($this->pageBlockData["model"])) {
            return ('AppBundle:Block:'.$this->pageBlock->getType().'.html.twig');
        } else {
            return ('AppBundle:Block:list_view_block_error.html.twig');
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

    public function GetQuickSearchTemplate()
    {
        $content = $this->pageBlock->getContent();
        if (!empty($content) && isset($content["quick_search"])) {
            return $content["quick_search"];
        }
        return ('AppBundle:Includes:quick_search_form.html.twig');
    }

    public function GetPageBlockData()
    {

        $request = $this->container->get('request_stack')->getCurrentRequest();
        $session = $request->getSession();
        $this->pageBlockData["list_view_id"] = $session->get($this->pageBlock->getId());

        if (empty($this->pageBlock->getEntityType()) || empty($this->pageBlock->getRelatedId())) {
            return $this->pageBlockData;
        }

        $blockContent = $this->pageBlock->getContent();

        $data = json_decode($this->pageBlock->getContent(),true);

        $allowViewSelect = false;
        if (isset($data["allowViewSelect"])) {
            $allowViewSelect = $data["allowViewSelect"];
        }

        $this->pageBlockData["allowViewSelect"] = $allowViewSelect;

        $this->listViewManager = $this->factoryManager->loadListViewManager($this->pageBlock->getEntityType()->getEntityTypeCode());
        $this->helperManager = $this->container->get('helper_manager');
        $currentUser = $this->helperManager->getCurrentUser();

        /**@var Translator $translator */
        $translator = $this->container->get('translator');
        $translator->setLocale($this->pageBlockData["locale"]);

        $listViewIds = Array();
        $listViews = $this->listViewManager->getListViewsForUserByAttributeSet($currentUser, $this->pageBlock->getAttributeSet());

        foreach ($listViews as $k => $listView) {
            $listViewIds[] = $listView->getId();
            $listViews[$k]->setDisplayName($translator->trans($listView->getDisplayName()));
        }

        if($allowViewSelect && !empty($listViewIds) && !in_array($this->pageBlock->getRelatedId(),$listViewIds)){
            $this->pageBlockData["list_view_id"] = $listViewIds[0];
        }

        $this->pageBlockData["listViews"] = $listViews;
        if (isset($this->pageBlockData["list_view_id"])) {
            $this->pageBlockData["model"] = $this->listViewManager->getListViewModel($this->pageBlockData["list_view_id"]);
        } else {
            $this->pageBlockData["model"] = $this->listViewManager->getListViewModel($this->pageBlock->getRelatedId());
            $this->pageBlockData["list_view_id"] = $this->pageBlock->getRelatedId();
        }

        if (empty($this->pageBlockData["model"])) {
            return $this->pageBlockData;
        }

        if (isset($data["tooltip"])) {
            $this->pageBlockData["tooltip"] = $data["tooltip"];
        }

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        $listViewContext = $this->container->get('list_view_context');
        $listViews = $listViewContext->getBy(array(), array("attributeSet" => "asc"));
        $data = json_decode($this->pageBlock->getContent(),true);


        $allowViewSelect = false;
        if (isset($data["allowViewSelect"])) {
            $allowViewSelect = $data["allowViewSelect"];
        }

        $tooltip = "";
        if (!empty($data)) {
            if (isset($data["tooltip"])) {
                $tooltip = $data["tooltip"];
            }
        }

        return array(
            'entity' => $this->pageBlock,
            'allowViewSelect' => $allowViewSelect,
            'list_views' => $listViews,
            "tooltip" => $tooltip,
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        $listViewContext = $this->container->get('list_view_context');
        $listView = $listViewContext->getById($data["relatedId"]);

        $settings = [];
        $p = $_POST;

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setRelatedId($data["relatedId"]);
        $this->pageBlock->setEntityType($listView->getAttributeSet()->getEntityType());
        $this->pageBlock->setAttributeSet($listView->getAttributeSet());
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        $settings["allowViewSelect"] = false;
        if (isset($p["allowViewSelect"]) && !empty($p["allowViewSelect"])) {
            $settings["allowViewSelect"] = $p["allowViewSelect"];
        }
        if (isset($p["allowViewSelect"]) && !empty($p["allowViewSelect"])) {
            $settings["allowViewSelect"] = $p["allowViewSelect"];
        }
        $settings["tooltip"] = $data["tooltip"];

        $this->pageBlock->setContent(json_encode($settings));

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
