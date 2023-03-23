<?php

namespace AppBundle\Abstracts;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\PageBlock;
use AppBundle\Enumerations\BlockTypesEnum;
use AppBundle\Enumerations\php;
use AppBundle\Factory\FactoryManager;
use AppBundle\Interfaces\Blocks\BlockInterface;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base properties and methods that can be used when implementing new block definitions
 *
 */
abstract class AbstractBaseBlock implements BlockInterface, ContainerAwareInterface
{
    /**@var PageBlock $pageBlock */
    protected $pageBlock;

    /**@var FactoryManager $factoryManager */
    protected $factoryManager;

    /**array of data*/
    protected $pageBlockData;

    protected $container;

    protected $isVisible;

    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Ovveride this method to initialize all services you will reqquire
     */
    public function initialize()
    {
        $this->factoryManager = $this->container->get("factory_manager");
        $this->isVisible = true;
    }

    public function setPageBlock(PageBlock $pageBlock)
    {
        $this->pageBlock = $pageBlock;
    }

    public function setPageBlockData($pageBlockData)
    {
        $this->pageBlockData = $pageBlockData;
    }

    public function isVisible()
    {
        return $this->isVisible;
    }

    public function setIsVisible($isVisible)
    {
        $this->isVisible = $isVisible;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:default.html.twig';
    }

    public function GetPageBlockAdminTemplate()
    {
        return 'AppBundle:Admin/Block:admin_default_block.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        $attributeSetContext = $this->container->get('attribute_set_context');
        $listViewContext = $this->container->get('list_view_context');

        $attributeSets = $attributeSetContext->getAll();
        $listViews = $listViewContext->getAll();

        $blockTypes = BlockTypesEnum::values();

        $listViewsOutput = [];
        foreach ($listViews as $listView) {
            $attributes = [
                'listView' => $listView,
                'listAttributes' => [],
            ];
            foreach ($listView->getListViewAttributes() as $attribute) {
                $attributes['listAttributes'][] = $attribute;
            }
            $listViewsOutput[] = $attributes;
        }

        return array(
            'entity' => $this->pageBlock,
            'attribute_sets' => $attributeSets,
            'list_views' => $listViewsOutput,
            'block_types' => $blockTypes
        );
    }

    /**
     * @param $data
     * @param int $isCustom
     * @return JsonResponse
     */
    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        if (!isset($data["title"]) || empty($data["title"])) {
            return new JsonResponse(array('error' => true, 'message' => 'title is not correct'));
        }

        if (!isset($data["class"]) || empty($data["class"])) {
            $data["class"] = null;
        }
        if (!isset($data["content"]) || empty($data["content"])) {
            $data["content"] = null;
        }
        if (!isset($data["dataAttributes"]) || empty($data["dataAttributes"])) {
            $data["dataAttributes"] = null;
        }
        if (!isset($data["relatedId"]) || empty($data["relatedId"])) {
            $data["relatedId"] = null;
        }

        $columns_settings = [];

        if (isset($data["kanbanListView"]) && !empty($data["kanbanListView"])) {
            $columns_settings["list_view"] = $data["kanbanListView"];
        }

        if (isset($data["kanbanColumnAttribute"]) && !empty($data["kanbanColumnAttribute"])) {
            $columns_settings["columns"] = $data["kanbanColumnAttribute"];
        } else {
            $columns_settings["columns"] = "custom";
        }

        if (isset($data["kanbanTitleAttribute"]) && !empty($data["kanbanTitleAttribute"])) {
            $columns_settings["title"] = $data["kanbanTitleAttribute"];
        }

        if (isset($data["kanbanDescriptionAttribute"]) && !empty($data["kanbanDescriptionAttribute"])) {
            $columns_settings["description"] = $data["kanbanDescriptionAttribute"];
        }

        if (isset($data["prepopulateLookupAttributes"]) && !empty($data["prepopulateLookupAttributes"])) {
            $prepopulateLookupAttributes = [
                "attribute" => $data["prepopulateLookupAttributes"]
            ];
        }

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        //$entity->setRelatedId($p["relatedId"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);
        //if ($this->pageBlock->getType() == "calendar") $data["content"] = json_encode($listViews);
        if ($this->pageBlock->getType() == "kanban_attribute_columns" || $this->pageBlock->getType() == "kanban_custom_columns") {
            $data["content"] = json_encode($columns_settings);
        }
        $this->pageBlock->setContent($data["content"]);

        return $blockManager->save($this->pageBlock);
    }

    public function GetAdminPageBlockData()
    {
        return $this->pageBlockData;
    }
}
