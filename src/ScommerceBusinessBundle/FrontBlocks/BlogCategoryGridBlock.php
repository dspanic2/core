<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\BlogManager;
use ScommerceBusinessBundle\Managers\RouteManager;

class BlogCategoryGridBlock extends AbstractBaseFrontBlock
{
    const CACHE_BLOCK_HTML = true;
    const CACHE_BLOCK_HTML_TAGS = ['blog_category', 'blog_post'];

    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var BlogManager $blogManager */
    protected $blogManager;

    public function GetBlockData()
    {
        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        $session = $this->getContainer()->get("session");

        /** @var SStoreEntity $store */
        $store = $this->routeManager->getStoreById($session->get("current_store_id"));

        if (empty($this->blogManager)) {
            $this->blogManager = $this->container->get("blog_manager");
        }
        $this->blockData["model"]["categories"] = $this->blogManager->getBlogCategoriesByStore($store);
        $this->blockData["model"]["subtitle"] = $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $this->blockData["block"], "subtitle");

        return $this->blockData;
    }
}
