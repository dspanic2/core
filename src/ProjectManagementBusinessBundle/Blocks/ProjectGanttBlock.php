<?php

namespace ProjectManagementBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Factory\FactoryContext;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;

class ProjectGanttBlock extends AbstractBaseBlock
{
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /** @var  BlockManager $entityManager */
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
        return ('ProjectManagementBusinessBundle:Block:project_gantt.html.twig');
    }

    public function GetPageBlockData()
    {
        $this->entityManager = $this->container->get("entity_manager");
        $this->listViewContext = $this->container->get("list_view_context");
        $this->entityTypeContext = $this->container->get("entity_type_context");
        $this->factoryContext = $this->container->get("factory_context");
        $this->attributeContext = $this->container->get("attribute_context");

        $project = $this->entityManager->getEntityByEntityTypeAndId($this->entityTypeContext->getItemByCode("project"), $this->pageBlockData["id"]);
        $this->pageBlockData["project"] = $project;

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'ProjectManagementBusinessBundle:BlockSettings:project_gantt.html.twig';
    }

    public function isVisible()
    {
        $id = $this->pageBlockData["id"];

        if ($id == null)
            return false;
        else
            return true;
    }

}
