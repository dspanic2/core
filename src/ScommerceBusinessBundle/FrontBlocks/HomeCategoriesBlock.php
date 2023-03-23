<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\ProductGroupManager;

class HomeCategoriesBlock extends AbstractBaseFrontBlock
{
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;

    public function GetBlockData()
    {
        $this->blockData = parent::GetBlockData();

        if (empty($this->productGroupManager)) {
            $this->productGroupManager = $this->container->get("product_group_manager");
        }

        $this->blockData["model"]["categories"] = $this->productGroupManager->getHomepageProductGroups($this->blockData["block"]->getProductLimit());

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
