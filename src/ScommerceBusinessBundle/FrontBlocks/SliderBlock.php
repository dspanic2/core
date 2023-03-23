<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\EntityManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\TemplateManager;

class SliderBlock extends AbstractBaseFrontBlock
{
    public function GetBlockData()
    {
        $this->blockData["model"]["slider"] = null;

        if (!empty($this->blockData["block"]->getSlider())) {
            $this->blockData["model"]["slider"] = $this->blockData["block"]->getSlider();
        }

        return $this->blockData;
    }

    public function SaveBlockSettings($data)
    {

        /** @var TemplateManager $templateManager */
        $templateManager = $this->container->get('template_manager');

        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get('entity_manager');
        $sliderEntityType = $entityManager->getEntityTypeByCode("slider");
        $sliderEntity = $entityManager->getEntityByEntityTypeAndId($sliderEntityType, $data["slider"]);

        $this->block->setName($data["name"]);
        $this->block->setClass($data["class"]);
        $this->block->setDataAttributes($data["data_attributes"]);
        $this->block->setSlider($sliderEntity);

        return $templateManager->save($this->block);
    }

    public function GetBlockSetingsTemplate()
    {
        return 'ScommerceBusinessBundle:FrontBlockSettings:slider.html.twig';
    }

    public function GetBlockSetingsData()
    {
        $data = parent::GetBlockSetingsData();

        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get('entity_manager');

        $sliderEntityType = $entityManager->getEntityTypeByCode("slider");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $data["sliders"] = $entityManager->getEntitiesByEntityTypeAndFilter($sliderEntityType, $compositeFilters);

        return $data;
    }
}
