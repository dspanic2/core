<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Controller\ListViewController;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\ListView;
use AppBundle\Factory\FactoryContext;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ListViewManager;
use Doctrine\Common\Util\Inflector;
use Symfony\Component\Config\Definition\Exception\Exception;

class TimelineBlock extends AbstractBaseBlock
{
    /**@var EntityManager $entityManager*/
    protected $entityManager;
    /** @var  BlockManager $entityManager*/
    protected $blockManager;
    /**@var ListViewContext $listViewContext */
    protected $listViewContext;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /**@var FactoryContext $factoryContext */
    protected $factoryContext;
    /**@var AttributeContext $factoryContext */
    protected $attributeContext;

    public function GetPageBlockTemplate()
    {
        return ('AppBundle:Block:'.$this->pageBlock->getType().'.html.twig');
    }

    public function GetPageBlockData()
    {
        $this->entityManager = $this->container->get("entity_manager");
        $this->listViewContext = $this->container->get("list_view_context");
        $this->entityTypeContext = $this->container->get("entity_type_context");
        $this->factoryContext = $this->container->get("factory_context");
        $this->attributeContext = $this->container->get("attribute_context");

        $blockContent = $this->pageBlock->getContent();

        $list_view = $this->listViewContext->getListViewById($blockContent["list_view"]);

        /** @var ListViewManager $listviewManager */
        $listviewManager = $this->container->get('list_view_manager');

        $return = [];
        $height = "initial";

        if (!empty($this->pageBlockData["id"])) {
            if (count($list_view)>0) {
                /** @var ListView $list_view */
                $list_view = $list_view[0];

                $date = "Unable to load title";
                $dateAttr = false;
                if (isset($blockContent["date"]) && !empty($blockContent["date"])) {
                    $dateAttr = $this->attributeContext->getById($blockContent["date"])->getAttributeCode();
                }

                $description = "Unable to load description";
                $descriptionAttr = false;
                if (isset($blockContent["description"]) && !empty($blockContent["description"])) {
                    $descriptionAttr = $this->attributeContext->getById($blockContent["description"])->getAttributeCode();
                }

                $pager = new DataTablePager();
                if ($dateAttr) {
                    $pager->setColumnOrder(EntityHelper::makeAttributeName($dateAttr));
                    $pager->setSortOrder('desc');
                }
                $pager->setRequestId($this->pageBlockData["id"]);

                $allItems = $listviewManager->getListViewDataModel($list_view, $pager);

                foreach ($allItems as $item) {
                    if ($dateAttr) {
                        $date = $item->{EntityHelper::makeGetter($dateAttr)}();
                    }
                    if ($descriptionAttr) {
                        $description = $item->{EntityHelper::makeGetter($descriptionAttr)}();
                    }
                    $return[] = [
                        'date' => $date,
                        'description' => $description,
                        'path' => "/page/".$list_view->getEntityType()->getEntityTypeCode()."/form/".$item->getId(),
                        'show_link' => $blockContent["showLink"],
                        'date_format' => $blockContent["dateFormat"],
                    ];
                }

                $height = $blockContent["height"];
            }
        } else {
            $this->setIsVisible(false);
        }

        $this->pageBlockData["items"] = $return;
        $this->pageBlockData["height"] = $height;

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        $attributeSetContext = $this->container->get('attribute_set_context');

        /** @var AttributeContext $attributeContext */
        $attributeContext = $this->container->get('attribute_context');
        $listViewContext = $this->container->get('list_view_context');
        $attributeSets = $attributeSetContext->getAll();
        $listViews = $listViewContext->getAll();

        $data = json_decode($this->pageBlock->getContent());

        $date = [];
        if (isset($data->date) && is_numeric($data->date)) {
            $attrObj = $attributeContext->getById($data->date);
            if (!empty($attrObj)) {
                $date = [
                    "id" => $data->date,
                    "name" => $attrObj->getFrontendLabel()
                ];
            }
        }

        $description = [];
        if (isset($data->description) && is_numeric($data->description)) {
            $attrObj = $attributeContext->getById($data->description);
            if (!empty($attrObj)) {
                $description = [
                    "id" => $data->description,
                    "name" => $attrObj->getFrontendLabel()
                ];
            }
        }

        $showLink = false;
        if (isset($data->showLink)) {
            $showLink = $data->showLink;
        }

        $dateFormat = "Y-m-d";
        if (isset($data->dateFormat)) {
            $dateFormat = $data->dateFormat;
        }

        $height = "initial";
        if (isset($data->height)) {
            $height = $data->height;
        }

        return array(
            'entity' => $this->pageBlock,
            'content' => $data,
            'date' => $date,
            'description' => $description,
            'attribute_sets' => $attributeSets,
            'list_views' => $listViews,
            'show_link' => $showLink,
            'date_format' => $dateFormat,
            'height' => $height,
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        $p = $_POST;

        $settings = [];

        if (isset($p["listView"]) && !empty($p["listView"])) {
            $settings["list_view"] = $p["listView"];
        }

        if (isset($p["date"]) && !empty($p["date"])) {
            $settings["date"] = $p["date"];
        }

        $settings["dateFormat"] = false;
        if (isset($p["dateFormat"]) && !empty($p["dateFormat"])) {
            $settings["dateFormat"] = $p["dateFormat"];
        }

        if (isset($p["description"]) && !empty($p["description"])) {
            $settings["description"] = $p["description"];
        }

        $settings["showLink"] = false;
        if (isset($p["showLink"]) && !empty($p["showLink"])) {
            $settings["showLink"] = $p["showLink"];
        }

        $settings["height"] = "inital";
        if (isset($p["height"]) && !empty($p["height"])) {
            $settings["height"] = $p["height"];
        }

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);
        $this->pageBlock->setContent(json_encode($settings));

        return $blockManager->save($this->pageBlock);
    }
}
