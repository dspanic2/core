<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class BlogPostGridBlock extends AbstractBaseFrontBlock
{
    const CACHE_BLOCK_HTML = true;
    const CACHE_BLOCK_HTML_TAGS = ['blog_category', 'blog_post'];

    public function GetBlockData()
    {
        $session = $this->getContainer()->get("session");
        $this->blockData["model"]["subtitle"] = $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $this->blockData["block"], "subtitle");

        return $this->blockData;
    }
}
