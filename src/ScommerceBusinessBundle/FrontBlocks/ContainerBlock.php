<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Entity\EntityValidation;
use AppBundle\Managers\EntityManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Entity\SFrontBlockEntity;

class ContainerBlock extends AbstractBaseFrontBlock
{
    /**@var EntityManager $entityManager */
    protected $entityManager;

    /**
     * @return mixed
     */
    public function GetBlockData()
    {
        return $this->blockData;
    }

    public function isVisible()
    {
        return true;
    }

    public function GetBlockAdminTemplate()
    {
        return 'ScommerceBusinessBundle:FrontBlockSettings:admin_container.html.twig';
    }

    /**
     * @param SFrontBlockEntity $frontBlock
     * @return SFrontBlockEntity
     */
    public function getPageBuilderValidation(SFrontBlockEntity $frontBlock)
    {

//        if (empty($frontBlock->getName())) {
//            $entityValidation = new EntityValidation();
//            $entityValidation->setMessage($this->translator->trans("Missing block name"));
//            $frontBlock->addEntityValidation($entityValidation);
//        }

        return $frontBlock;
    }
}
