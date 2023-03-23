<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\BlogManager;

class ProductRelatedArticlesBlock extends AbstractBaseFrontBlock
{
    /** @var BlogManager $blogManager */
    protected $blogManager;

    public function GetBlockData()
    {
        $this->blockData = parent::GetBlockData();

        $this->blockData["model"]["articles"] = array();

        if (!empty($this->blockData["page"]) && $this->blockData["page"]->getEntityType()->getEntityTypeCode() == "product") {
            if (empty($this->blogManager)) {
                $this->blogManager = $this->container->get("blog_manager");
            }
            $this->blockData["model"]["articles"] = $this->blogManager->getProductRelatedBlogPosts($this->blockData["page"]);
        }

        return $this->blockData;
    }
}
