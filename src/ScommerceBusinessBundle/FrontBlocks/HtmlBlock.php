<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Entity\EntityValidation;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Entity\SFrontBlockEntity;

class HtmlBlock extends AbstractBaseFrontBlock
{
    public function GetBlockData()
    {
        $this->blockData = parent::GetBlockData();

        $session = $this->getContainer()->get("session");
        $this->blockData["model"]["subtitle"] = $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $this->blockData["block"], "subtitle");
        return $this->blockData;
    }

    public function GetBlockAdminTemplate()
    {
        return 'ScommerceBusinessBundle:FrontBlockSettings:' . $this->block->getType() . '.html.twig';
    }

//    /**
//     * @param SFrontBlockEntity $frontBlock
//     * @return SFrontBlockEntity
//     */
//    public function getPageBuilderValidation(SFrontBlockEntity $frontBlock)
//    {
//
//        if (empty($frontBlock->getName())) {
//            $entityValidation = new EntityValidation();
//            $entityValidation->setMessage($this->translator->trans("Missing block name"));
//            $frontBlock->addEntityValidation($entityValidation);
//        }
//
//        return $frontBlock;
//    }
}
