<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\BrandsManager;

class SelectedBrandsBlock extends AbstractBaseFrontBlock
{
    /** @var BrandsManager $brandsManager */
    protected $brandsManager;

    public function GetBlockData()
    {
        if (empty($this->brandsManager)) {
            $this->brandsManager = $this->container->get("brands_manager");
        }

        $this->blockData["model"]["brands"] = $this->brandsManager->getHomepageBrands();

        $session = $this->getContainer()->get("session");

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
