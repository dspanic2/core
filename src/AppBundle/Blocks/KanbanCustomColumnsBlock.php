<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\ListView;
use AppBundle\Entity\SearchFilter;
use AppBundle\Factory\FactoryContext;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;
use Doctrine\Common\Util\Inflector;
use Symfony\Component\Config\Definition\Exception\Exception;

class KanbanCustomColumnsBlock extends AbstractBaseBlock
{
    /**@var EntityManager $entityManager*/
    protected $entityManager;
    /** @var  BlockManager $entityManager*/
    protected $blockManager;
    /**@var ListViewContext $listViewContext */
    protected $listViewContext;
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
        $this->factoryContext = $this->container->get("factory_context");
        $this->attributeContext = $this->container->get("attribute_context");

        $blockContent = $this->pageBlock->getContent();

        $columnsPrepared = [];
        /** @var ListView $list_view */
        $list_view = $this->listViewContext->getListViewById($blockContent["list_view"]);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $allItems = $this->entityManager->getEntitiesByEntityTypeAndFilter($list_view->getEntityType(), $compositeFilters);

        $title = "Unable to load title";
        $titleAttr = false;
        if (isset($blockContent["title"]) && !empty($blockContent["title"])) {
            $titleAttr = $this->attributeContext->getById($blockContent["title"])->getAttributeCode();
        }

        $description = "Unable to load description";
        $descriptionAttr = false;
        if (isset($blockContent["description"]) && !empty($blockContent["description"])) {
            $descriptionAttr = $this->attributeContext->getById($blockContent["description"])->getAttributeCode();
        }

        $unassignedItems = [];
        foreach ($allItems as $item) {
            if ($titleAttr) {
                $title = $item->{EntityHelper::makeGetter($titleAttr)}();
            }
            if ($descriptionAttr) {
                $description = $item->{EntityHelper::makeGetter($descriptionAttr)}();
            }
            $unassignedItems[$item->getId()] = [
                'block' => 0,
                'id' => $item->getId(),
                'title' => $title,
                'description' => $description,
            ];
        }

        $kanbanColumnEntityType = $this->entityManager->getEntityTypeByCode("kanban_column");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("blockId", "eq", $this->pageBlock->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $columns = $this->entityManager->getEntitiesByEntityTypeAndFilter($kanbanColumnEntityType, $compositeFilters);

        foreach ($columns as $column) {
            $column_settings = json_decode($column->getColumnSettings());
            if (!empty($column_settings)) {
                $items = array_unique($column_settings);

                $tasks = [];
                foreach ($items as $item_id) {
                    $item = $this->entityManager->getEntityByEntityTypeAndId($list_view->getEntityType(), $item_id);

                    if ($titleAttr) {
                        $title = $item->{EntityHelper::makeGetter($titleAttr)}();
                    }
                    if ($descriptionAttr) {
                        $description = $item->{EntityHelper::makeGetter($descriptionAttr)}();
                    }

                    $tasks[] = [
                        'block' => $column->getId(),
                        'id' => $item->getId(),
                        'title' => $title,
                        'description' => $description,
                        'type' => $list_view->getEntityType(),
                    ];

                    unset($unassignedItems[$item->getId()]);
                }
            }

            $columnTmp = [
                "title" => $column->getName(),
                "id" => $column->getId(),
                "items" => isset($tasks) ? $tasks : []
            ];
            if (method_exists($column, "getColor")) {
                $columnTmp["color"] = $column->getColor();
            }
            $columnsPrepared[] = $columnTmp;
        }

        if (!empty($unassignedItems)) {
            $unassignedColumn = [
                "title" => "Unassigned",
                "id" => 0,
                "color" => "#343A40",
                "items" => $unassignedItems
            ];
            array_unshift($columnsPrepared, $unassignedColumn);
        }

        $raw = json_encode($columnsPrepared);
        $this->pageBlockData["columns"] = $raw;
        $this->pageBlockData["unassigned"] = json_encode($unassignedItems);

        $this->pageBlockData["block_id"] = $this->pageBlock->getId();
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

        $columnAttribute = [];
        if (isset($data->columns) && is_numeric($data->columns)) {
            $columnAttribute = [
                "id" => $data->columns,
                "name" => "Custom"
            ];
        }

        $titleAttribute = [];
        if (isset($data->title) && is_numeric($data->title)) {
            $attrObj = $attributeContext->getById($data->title);
            $titleAttribute = [
                "id" => $data->title,
                "name" => $attrObj->getFrontendLabel()
            ];
        }

        $descriptionAttribute = [];
        if (isset($data->description) && is_numeric($data->description)) {
            $attrObj = $attributeContext->getById($data->description);
            $descriptionAttribute = [
                "id" => $data->columns,
                "name" => $attrObj->getFrontendLabel()
            ];
        }

        return array(
            'entity' => $this->pageBlock,
            'content' => $data,
            'column_attribute' => $columnAttribute,
            'title_attribute' => $titleAttribute,
            'description_attribute' => $descriptionAttribute,
            'attribute_sets' => $attributeSets,
            'list_views' => $listViews
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        $p = $_POST;

        $columns_settings = [];

        if (isset($p["listView"]) && !empty($p["listView"])) {
            $columns_settings["list_view"] = $p["listView"];
        }

        $columns_settings["columns"] = "custom";

        if (isset($p["kanbanTitleAttribute"]) && !empty($p["kanbanTitleAttribute"])) {
            $columns_settings["title"] = $p["kanbanTitleAttribute"];
        }

        if (isset($p["kanbanDescriptionAttribute"]) && !empty($p["kanbanDescriptionAttribute"])) {
            $columns_settings["description"] = $p["kanbanDescriptionAttribute"];
        }

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);
        $this->pageBlock->setContent(json_encode($columns_settings));

        return $blockManager->save($this->pageBlock);
    }
}
