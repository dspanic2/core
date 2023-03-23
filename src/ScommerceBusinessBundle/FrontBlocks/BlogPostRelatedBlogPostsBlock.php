<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class BlogPostRelatedBlogPostsBlock extends AbstractBaseFrontBlock
{
    public function GetBlockData()
    {
        return $this->blockData;
    }
}
