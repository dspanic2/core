<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Traits\CartTrait;

class CartBlock extends AbstractBaseFrontBlock
{
    use CartTrait;

    /**
     * @throws \Exception
     */
    public function GetBlockData()
    {
        $this->blockData = array_merge($this->blockData ?? [], $this->prepareCartData());

        return $this->blockData;
    }
}
