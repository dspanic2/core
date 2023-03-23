<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\ProductGroupManager;

class HomeProductsBlock extends AbstractBaseFrontBlock
{
    const CACHE_BLOCK_HTML = true;
    const CACHE_BLOCK_HTML_TAGS = ['product', 's_front_block'];

    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;

    public function GetBlockData()
    {
        $this->blockData["model"]["subtitle"] = "";
        $this->blockData["model"]["store_products"] = array();
        $this->blockData["model"]["show_more"] = array();

        if (!empty($this->blockData["id"])) {

            if (empty($this->productGroupManager)) {
                $this->productGroupManager = $this->container->get("product_group_manager");
            }

            $session = $this->container->get('session');
            $storeId = $session->get("current_store_id");

            $url = $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $this->blockData["block"], "url");
            if (!empty($url)) {
                $this->blockData["model"]["show_more"] = array(
                    "title" => $this->translator->trans($this->getPageUrlExtension->getEntityStoreAttribute(null, $this->blockData["block"], "main_title")),
                    "url" => $url,
                );
            }

            $data = array();
            $data["page_number"] = 1;
            $data["page_size"] = $this->blockData["block"]->getProductLimit();
            if (!empty($this->blockData["block"]->getProductSortData())) {
                $data["sort"] = $this->blockData["block"]->getProductSortData();
            }
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

            $this->blockData["model"]["products"] = $this->productGroupManager->getFilteredProducts($data);

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

    public function isVisible()
    {
        if (empty($this->blockData["id"])) {
            return false;
        }

        return true;
    }

}
