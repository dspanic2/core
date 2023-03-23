<?php

namespace CrmBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;

class DealProgressBlock extends AbstractBaseBlock
{
    /** @var EntityManager $entityManager */
    protected $entityManager;

    public function GetPageBlockTemplate()
    {
        return ('CrmBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
    }

    public function GetPageBlockData()
    {
        $this->entityManager = $this->container->get("entity_manager");

        $etDeal = $this->entityManager->getEntityTypeByCode("deal");
        $etDealStage = $this->entityManager->getEntityTypeByCode("deal_stage");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $this->pageBlockData["model"]["deal"] = $this->entityManager->getEntityByEntityTypeAndId($etDeal, $this->pageBlockData["id"]);
        $this->pageBlockData["model"]["deal_stages"] = $this->entityManager->getEntitiesByEntityTypeAndFilter($etDealStage, $compositeFilters);

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'CrmBusinessBundle:BlockSettings:' . $this->pageBlock->getType() . '.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        /** @var AttributeContext $attributeContext */
        $attributeContext = $this->container->get("attribute_context");

        /** @var EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get("entity_type_context");
        $entityTypes = $entityTypeContext->getAll();

        $data = json_decode($this->pageBlock->getContent());

        $attr = [];
        if (isset($data->title1)) {
            /** @var Attribute $attrObj */
            $attrObj = $attributeContext->getBy(array('attributeCode' => $data->attribute, 'entityType' => $this->pageBlock->getAttributeSet()->getEntityType()));
            if (!empty($attrObj)) {
                $attrObj = $attrObj[0];
                $attr = [
                    "id" => $attrObj->getAttributeCode(),
                    "name" => $attrObj->getFrontendLabel()
                ];
            }
        }

        return array(
            'entity' => $this->pageBlock,
            'entity_types' => $entityTypes,
            'attribute' => $attr,
        );
    }

    public function SavePageBlockSettings($data)
    {
        /** @var BlockManager $blockManager */
        $blockManager = $this->container->get("block_manager");

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }
}
