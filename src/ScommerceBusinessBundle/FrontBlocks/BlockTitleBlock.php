<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class BlockTitleBlock extends AbstractBaseFrontBlock
{
    public function GetBlockData()
    {
        return $this->blockData;
    }
}
