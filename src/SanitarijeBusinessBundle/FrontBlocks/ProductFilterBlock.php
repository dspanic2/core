<?php

namespace SanitarijeBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Traits\ProductGridTrait;

class ProductFilterBlock extends AbstractBaseFrontBlock
{
    use ProductGridTrait;

    /**
     * @throws \Exception
     */
    public function GetBlockData()
    {
        $entity = $this->blockData["page"];

        $productGroup = null;
        if ($entity->getEntityType()->getEntityTypeCode() == "product_group") {
            $productGroup = $entity;
        }
        $params = $_GET;

        $this->blockData["model"]["productFilterData"] = $this->blockData["block"]->getProductFilterData();
        if (!empty($this->blockData["model"]["productFilterData"])) {
            $params["pre_filter"] = json_decode($this->blockData["model"]["productFilterData"], true)["pre_filter"] ?? [];
        }

        $data = $this->getProductGridHtmlData($params, $productGroup);

        $this->blockData["model"]["entities"] = $data["entities"] ?? [];
        $this->blockData["model"]["filter_html"] = $data["filter_html"] ?? "";
        $this->blockData["model"]["sort_html"] = $data["sort_html"] ?? "";

        return $this->blockData;
    }
}
