<?php

namespace AppBundle\Blocks;

use AppBundle\Context\AttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Entity\ListView;
use AppBundle\Factory\FactoryContext;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\CalendarManager;
use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Managers\EntityManager;

class CalendarBlock extends AbstractBaseBlock
{
    /** @var ListViewContext $listViewContext */
    protected $listViewContext;

    public function GetPageBlockTemplate()
    {
        return ('AppBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
    }

    public function GetPageBlockData()
    {
        $settings = $this->pageBlock->getPreparedContent();

        /** @var ListViewContext $listViewContext */
        $listViewContext = $this->container->get("list_view_context");

        /** @var ListView $list_view */
        $listView = $listViewContext->getItemByUid($settings["list_view"][0]);

        if (!empty($settings["open_modal"])) {
            $pageBlockContext = $this->container->get("page_block_context");
            $blockForm = $pageBlockContext->getOneBy(array("attributeSet" => $listView->getAttributeSet(), "type" => "edit_form"));
            $url = "/block/modal?block_id=" . $blockForm->getId() . "&action=reload";
        } else {
            $url = "/page/" . $listView->getAttributeSet()->getAttributeSetCode() . "/form";
        }

        $this->pageBlockData["create_new_url"] = $url;
        $this->pageBlockData["drag_and_drop"] = 0;
        if (isset($settings["drag_and_drop"])) {
            $this->pageBlockData["drag_and_drop"] = $settings["drag_and_drop"];
        }
        if (isset($settings["form_type"])) {
            $this->pageBlockData["form_type"] = $settings["form_type"];
        }
        $this->pageBlockData["first_day"] = 1;
        if (isset($settings["first_day"])) {
            $this->pageBlockData["first_day"] = $settings["first_day"];
        }
        $this->pageBlockData["enable_print"] = 0;
        if (isset($settings["enable_print"])) {
            $this->pageBlockData["enable_print"] = $settings["enable_print"];
        }

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:' . $this->pageBlock->getType() . '.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        /** @var ListViewContext $listViewContext */
        $listViewContext = $this->container->get('list_view_context');

        /** @var AttributeContext $attributeContext */
        $attributeContext = $this->container->get('attribute_context');

        $fetchedEntityTypes = array();
        $listViews = array();

        $attributes = $attributeContext->getAttributesByFilter("backendType", "date%");
        foreach ($attributes as $attribute) {
            if (!in_array($attribute->getEntityType()->getId(), $fetchedEntityTypes)) {
                $allAttributes = $attributeContext->getBy(array('entityType' => $attribute->getEntityType()));

                $usedEntityTypes = array();
                $usedEntityTypes[] = $attribute->getEntityType()->getEntityTypeCode();

                foreach ($allAttributes as $tmpAttr) {
                    if (!empty($tmpAttr->getLookupEntityType()) && !in_array($tmpAttr->getLookupEntityType()->getEntityTypeCode(), $usedEntityTypes)) {
                        $usedEntityTypes[] = $tmpAttr->getLookupEntityType()->getEntityTypeCode();
                        $allAttributes = array_merge($allAttributes, $attributeContext->getBy(array('entityType' => $tmpAttr->getLookupEntityType())));
                    }
                }

                $listViewsTmp = $listViewContext->getBy(array("entityType" => $attribute->getEntityType()));
                foreach ($listViewsTmp as $key => $listViewTmp) {
                    $listViewsTmp[$key]->allAttributes = $allAttributes;
                }
                $listViews = array_merge($listViews, $listViewsTmp);
                $fetchedEntityTypes[] = $attribute->getEntityType()->getId();
            }
        }

        return array(
            'entity' => $this->pageBlock,
            'list_views' => $listViews
        );
    }

    public function SavePageBlockSettings($data)
    {

        $blockManager = $this->container->get('block_manager');

        $listViews = [
            'list_view' => [],
            'list_view_attributes' => [],
            'enable_print' => 0,
            'open_modal' => 0,
            'drag_and_drop' => 0,
            'first_day' => 0,
            'form_type' => 0
        ];
        if (!isset($data["listView"]) || empty($data["listView"])) {
            $data["listView"] = null;
        } else {
            $this->setListViews($data, $listViews);
        }

        $listViews["open_modal"] = $data["open_modal"];
        $listViews["drag_and_drop"] = $data["drag_and_drop"];
        $listViews["enable_print"] = $data["enable_print"];
        $listViews["first_day"] = $data["first_day"];
        $listViews["form_type"] = $data["form_type"];

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);
        $this->pageBlock->setContent(json_encode($listViews));

        return $blockManager->save($this->pageBlock);
    }

    /**
     * @param $data
     * @param $listViews
     */
    function setListViews($data, &$listViews)
    {
        if (empty($this->listViewContext)) {
            $this->listViewContext = $this->container->get("list_view_context");
        }

        $attributes = ["titleListView", "title2ListView", "title3ListView", "descriptionListView", "startListView", "endListView", "colorListView"];

        $data["listView"] = array_unique($data["listView"]);

        foreach ($data["listView"] as $listView) {

            /**
             * POST iz nekog razloga pretvara . u _ pa je nuzna ova dolje pretvorba!!!!
             */

            $listView = str_ireplace("_", ".", $listView);

            /** @var ListView $listViewEntity */
            $listViewEntity = $this->listViewContext->getById($listView);

            $key = $listViewEntity->getUid();
            /*if(!isset($data["startListView{$key}Attribute"])){
                $key = $listViewEntity->getId();
            }*/

            $listViews['list_view'][] = $listViewEntity->getUid();
            foreach ($attributes as $attribute) {

                if (isset($data[str_ireplace(".", "_", $attribute . "{$key}Attribute")]) && !empty($data[str_ireplace(".", "_", $attribute . "{$key}Attribute")])) {
                    $listViews['list_view_attributes'][$attribute . "{$key}Attribute"] = $data[str_ireplace(".", "_", $attribute . "{$key}Attribute")];
                }
            }
        }
    }
}
