<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Traits\ProductGridTrait;

class ProductFilterBlock extends AbstractBaseFrontBlock
{
    use ProductGridTrait;

    public function GetBlockData()
    {
        $entity = $this->blockData["page"];

        $productGroup = null;
        if ($entity->getEntityType()->getEntityTypeCode() == "product_group") {
            $productGroup = $entity;
        }

        $data = $this->getProductGridHtmlData($_GET, $productGroup, true);

        $this->blockData["model"]["entities"] = $data["entities"] ?? [];
        $this->blockData["model"]["filter_html"] = $data["filter_html"] ?? "";

        return $this->blockData;
    }
}
