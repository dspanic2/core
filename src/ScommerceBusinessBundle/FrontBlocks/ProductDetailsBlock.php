<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use CrmBusinessBundle\Managers\ProductManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class ProductDetailsBlock extends AbstractBaseFrontBlock
{
    /** @var ProductManager $productManager */
    protected $productManager;

    public function GetBlockData()
    {
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
