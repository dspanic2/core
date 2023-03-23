<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Factory\FactoryContext;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;
use Doctrine\Common\Util\Inflector;

class KanbanAttributeColumnsBlock extends AbstractBaseBlock
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var BlockManager $blockManager */
    protected $blockManager;
    /** @var ListViewContext $listViewContext */
    protected $listViewContext;
    /** @var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /** @var FactoryContext $factoryContext */
    protected $factoryContext;
    /** @var AttributeContext $factoryContext */
    protected $attributeContext;

    public function GetPageBlockTemplate()
    {
        return ('AppBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
    }

    public function GetPageBlockData()
    {
        $this->entityManager = $this->container->get("entity_manager");
        $this->listViewContext = $this->container->get("list_view_context");
        $this->entityTypeContext = $this->container->get("entity_type_context");
        $this->factoryContext = $this->container->get("factory_context");
        $this->attributeContext = $this->container->get("attribute_context");

        $blockContent = json_decode($this->pageBlock->getContent(), true);

        $columnsId = $blockContent["columns"];
        $columnsAttribute = $this->attributeContext->getById($columnsId);
        $columnsEntityType = $columnsAttribute->getLookupEntityType();

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $columns = $this->entityManager->getEntitiesByEntityTypeAndFilter($columnsEntityType, $compositeFilters);

        $columnsPrepared = [];
        $list_view = $this->listViewContext->getListViewById($blockContent["list_view"]);
        $taskEntityType = $this->entityTypeContext->getItemById($list_view[0]->getEntityType());

        $rawChangedAttributeName = $columnsAttribute->getAttributeCode();
        $changedAttributeName = EntityHelper::makeAttributeName($rawChangedAttributeName);

        $title = "Unable to load title";
        $titleAttr = false;
        if (isset($blockContent["title"]) && !empty($blockContent["title"])) {
            $titleAttr = $this->attributeContext->getById($blockContent["title"])->getAttributeCode();
        }

        $description = "Unable to load description";

        /**@var Attribute $descriptionAttr */
        $descriptionAttr = null;
        if (isset($blockContent["description"]) && !empty($blockContent["description"])) {
            $descriptionAttr = $this->attributeContext->getById($blockContent["description"])->getAttributeCode();
        }

        foreach ($columns as $column) {

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
            $compositeFilter->addFilter(new SearchFilter($changedAttributeName, "eq", $column->getId()));

            $compositeFilters = new CompositeFilterCollection();
            $compositeFilters->addCompositeFilter($compositeFilter);

            $sortFilters = new SortFilterCollection();
            $sortFilters->addSortFilter(new SortFilter("kanbanOrder", "asc"));

            $items = $this->entityManager->getEntitiesByEntityTypeAndFilter($taskEntityType, $compositeFilters);

            $tasks = [];
            foreach ($items as $item) {
                if ($titleAttr) $title = $item->{EntityHelper::makeGetter($titleAttr)}();

                /*     if ($descriptionAttr != null) {
                         if ($descriptionAttr->getLookupAttribute() != null) {
                             $description = EntityHelper::makeGetter($descriptionAttr->getLookupAttribute()->getAttributeCode());
                         } else {
                             $description = EntityHelper::makeGetter($descriptionAttr->getAttributeCode());

                         }

                     }
                     $description = $item->{EntityHelper::makeGetter($descriptionAttr)}();*/
                $tasks[] = [
                    'block' => $column->getId(),
                    'id' => $item->getId(),
                    'title' => $title,
                    'description' => $description,
                    'type' => $taskEntityType->getEntityTypeCode(),
                ];
            }

            $columnTmp = [
                "title" => $column->getName(),
                "id" => $column->getId(),
                "items" => $tasks
            ];
            if (method_exists($column, "getColor")) {
                $columnTmp["color"] = $column->getColor();
            }
            $columnsPrepared[] = $columnTmp;
        }

        $raw = json_encode($columnsPrepared);
        $this->pageBlockData["columns"] = $raw;
        $this->pageBlockData["entity_type"] = $taskEntityType;
        $this->pageBlockData["column_changed"] = $rawChangedAttributeName;
        $this->pageBlockData["model"] = $list_view[0];

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:' . $this->pageBlock->getType() . '.html.twig';
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
            $attrObj = $attributeContext->getById($data->columns);
            $columnAttribute = [
                "id" => $data->columns,
                "name" => $attrObj->getFrontendLabel()
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
        if (isset($p["kanbanColumnAttribute"]) && !empty($p["kanbanColumnAttribute"])) {
            $columns_settings["columns"] = $p["kanbanColumnAttribute"];
        }
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