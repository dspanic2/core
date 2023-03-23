<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class CommentsBlock extends AbstractBaseFrontBlock
{
    public function GetBlockData()
    {
        return $this->blockData;
    }
}
