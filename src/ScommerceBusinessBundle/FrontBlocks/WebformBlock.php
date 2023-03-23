<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\EntityManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Entity\SFrontBlockEntity;
use ScommerceBusinessBundle\Managers\TemplateManager;

class WebformBlock extends AbstractBaseFrontBlock
{
    public function GetBlockData()
    {
        $this->blockData = parent::GetBlockData();
        $this->blockData["model"]["webform"] = null;

        /** @var SFrontBlockEntity $block */
        $block = $this->blockData["block"];

        if ($block->getEnableEdit()) {
            $entity = $this->blockData["page"];
            if (method_exists($entity, "getWebform")) {
                $this->blockData["model"]["webform"] = $entity->getWebform();
            }
        }
        if (!empty($block->getEntityId())) {
            /** @var EntityManager $entityManager */
            $entityManager = $this->container->get('entity_manager');
            $webformEntityType = $entityManager->getEntityTypeByCode("webform");
            $this->blockData["model"]["webform"] = $entityManager->getEntityByEntityTypeAndId($webformEntityType, $block->getEntityId());
        }

        return $this->blockData;
    }

    public function SaveBlockSettings($data)
    {

        /** @var TemplateManager $templateManager */
        $templateManager = $this->container->get('template_manager');

        $this->block->setName($data["name"]);
        $this->block->setClass($data["class"]);
        $this->block->setDataAttributes($data["data_attributes"]);
        $this->block->setEntityId($data["webform"]);
        $this->block->setEnableEdit($data["useContextEntity"] ?? 0);

        return $templateManager->save($this->block);
    }

    public function GetBlockSetingsTemplate()
    {
        return 'ScommerceBusinessBundle:FrontBlockSettings:webform.html.twig';
    }

    public function GetBlockSetingsData()
    {
        $data = parent::GetBlockSetingsData();

        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get('entity_manager');

        $sliderEntityType = $entityManager->getEntityTypeByCode("webform");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $data["webforms"] = $entityManager->getEntitiesByEntityTypeAndFilter($sliderEntityType, $compositeFilters);

        return $data;
    }
}
