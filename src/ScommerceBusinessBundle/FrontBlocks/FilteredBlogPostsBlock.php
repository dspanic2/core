<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\BlogManager;

class FilteredBlogPostsBlock extends AbstractBaseFrontBlock
{
    public function GetBlockData()
    {
        $this->blockData = parent::GetBlockData();

        $this->blockData["model"]["subtitle"] = "";
        $this->blockData["model"]["store_products"] = array();

        if (!empty($this->blockData["block"])) {
            $session = $this->container->get('session');
            $storeId = $session->get("current_store_id");

            /** @var BlogManager $blogManager */
            $blogManager = $this->container->get("blog_manager");

            $data = array();
            if (!empty($this->blockData["block"]->getProductFilterData())) {
                $tmpFilterData = json_decode($this->blockData["block"]->getProductFilterData(), true);
                if (isset($tmpFilterData["pre_filter"])) {
                    $data["pre_filter"] = $tmpFilterData["pre_filter"];
                }
                if (isset($tmpFilterData["filter"])) {
                    $data["filter"] = $tmpFilterData["filter"];
                    $data["get_filter"] = true;
                }
            }
            $data["page_size"] = $this->blockData["block"]->getProductLimit();
            if (!empty($this->blockData["block"]->getProductSortData())) {
                $data["sort"] = $this->blockData["block"]->getProductSortData();
            }

            $this->blockData["model"]["blogs"] = $blogManager->getFilteredBlogPosts($data);

            $this->blockData["model"]["subtitle"] = $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $this->blockData["block"], "subtitle");
        }

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
