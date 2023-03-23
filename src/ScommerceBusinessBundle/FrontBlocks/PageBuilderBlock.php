<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class PageBuilderBlock extends AbstractBaseFrontBlock
{
    public function GetBlockData()
    {
        // ovo je samo placeholder block koji wrepa blokove
        return $this->blockData;
    }
}
