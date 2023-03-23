<?php

namespace AppBundle\Blocks;

use AppBundle\Context\AttributeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Managers\HelperManager;
use Symfony\Component\Console\Helper\Helper;

class IcalExportBlock extends AbstractBaseBlock
{
    public function GetPageBlockTemplate()
    {
        return ('AppBundle:Block:'.$this->pageBlock->getType().'.html.twig');
    }

    public function GetPageBlockData()
    {
        /** @var HelperManager $helperManager */
        $helperManager = $this->container->get("helper_manager");

        /** @var CoreUserEntity $user */
        $user = $helperManager->getCurrentUser();

        $baseUrl = $_ENV["SSL"]."://" . $_ENV["BACKEND_URL"] . $_ENV["FRONTEND_URL_PORT"];;

        $this->pageBlockData["export_url"] = $baseUrl.$this->container->get('router')->generate('ical_export', array('u' => $user->getSalt(), 'b' => $this->pageBlock->getUid()));

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
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
        ];
        if (!isset($data["listView"]) || empty($data["listView"])) {
            $data["listView"] = null;
        } else {
            foreach ($data["listView"] as $listView) {
                $listViews['list_view'][] = $listView;
                if (isset($data["titleListView".$listView."Attribute"]) && !empty($data["titleListView".$listView."Attribute"])) {
                    $listViews['list_view_attributes']["titleListView".$listView."Attribute"] = $data["titleListView".$listView."Attribute"];
                }
                if (isset($data["title3ListView".$listView."Attribute"]) && !empty($data["title2ListView".$listView."Attribute"])) {
                    $listViews['list_view_attributes']["title2ListView".$listView."Attribute"] = $data["title2ListView".$listView."Attribute"];
                }
                if (isset($data["title3ListView".$listView."Attribute"]) && !empty($data["title3ListView".$listView."Attribute"])) {
                    $listViews['list_view_attributes']["title3ListView".$listView."Attribute"] = $data["title3ListView".$listView."Attribute"];
                }
                if (isset($data["descriptionListView".$listView."Attribute"]) && !empty($data["descriptionListView".$listView."Attribute"])) {
                    $listViews['list_view_attributes']["descriptionListView".$listView."Attribute"] = $data["descriptionListView".$listView."Attribute"];
                }
                if (isset($data["startListView".$listView."Attribute"]) && !empty($data["startListView".$listView."Attribute"])) {
                    $listViews['list_view_attributes']["startListView".$listView."Attribute"] = $data["startListView".$listView."Attribute"];
                }
                if (isset($data["endListView".$listView."Attribute"]) && !empty($data["endListView".$listView."Attribute"])) {
                    $listViews['list_view_attributes']["endListView".$listView."Attribute"] = $data["endListView".$listView."Attribute"];
                }
                if (isset($data["colorListView".$listView."Attribute"]) && !empty($data["colorListView".$listView."Attribute"])) {
                    $listViews['list_view_attributes']["colorListView".$listView."Attribute"] = $data["colorListView".$listView."Attribute"];
                }
            }
        }

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);
        $this->pageBlock->setContent(json_encode($listViews));

        return $blockManager->save($this->pageBlock);
    }
}
