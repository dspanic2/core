<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\BrandsManager;
use ScommerceBusinessBundle\Managers\ScommerceHelperManager;

class QuickOrderBlock extends AbstractBaseFrontBlock
{
    public function GetBlockData()
    {
        /** @var BrandsManager $brandsManager */
        $brandsManager = $this->container->get("brands_manager");

        /** @var ScommerceHelperManager $scommerceHelperManager */
        $scommerceHelperManager = $this->container->get("scommerce_helper_manager");

        $session = $this->getContainer()->get("session");

        $this->blockData["model"]["brands"] = $scommerceHelperManager->prepareKeyLetterTwigOutput(
            $brandsManager->getAllBrands()
        );

        $this->blockData["model"]["subtitle"] = $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $this->blockData["block"], "subtitle");

        return $this->blockData;
    }

    /** Nije potrebno dok se ne uvedu zasebni admin template za pojedine blokove */
    /*public function GetBlockSetingsTemplate()
    {
        return 'ScommerceBusinessBundle:BlockSettings:'.$this->block->getType().'.html.twig';
    }*/

    /*public function GetBlockSetingsData()
    {
        return array(
            'entity' => $this->block,
        );
    }*/
}
