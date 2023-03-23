<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Traits\ProductGridTrait;

class ProductGridBlock extends AbstractBaseFrontBlock
{
    use ProductGridTrait;

    public function GetBlockData()
    {
        $entity = $this->blockData["page"];

        $productGroup = null;
        if ($entity->getEntityType()->getEntityTypeCode() == "product_group") {
            $productGroup = $entity;
        }

        $data = $this->getProductGridHtmlData($_GET, $productGroup, false);

        $this->blockData["model"]["entities"] = $data["entities"] ?? [];
        $this->blockData["model"]["grid_html"] = $data["grid_html"] ?? "";
        $this->blockData["model"]["sort_html"] = $data["sort_html"] ?? "";
        $this->blockData["model"]["pager_html"] = $data["pager_html"] ?? "";

        if (isset($_ENV["SHOW_ACTIVE_FILTERS"]) && !empty($_ENV["SHOW_ACTIVE_FILTERS"])) {
            $this->blockData["model"]["active_filters"] = $data["active_filters"] ?? "";
        }

        return $this->blockData;
    }
}
