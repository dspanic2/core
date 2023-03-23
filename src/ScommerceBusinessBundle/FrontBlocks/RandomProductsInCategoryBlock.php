<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use CrmBusinessBundle\Entity\ProductEntity;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\ProductGroupManager;

class RandomProductsInCategoryBlock extends AbstractBaseFrontBlock
{
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;

    public function GetBlockData()
    {
        $this->blockData["model"]["products"] = array();

        if (!empty($this->blockData["id"])) {

            /** @var ProductEntity $product */
            $product = $this->blockData["page"];

            $selectedProductGroupId = null;
            $max = 0;

            if (!empty($selectedProductGroupId)) {

                if (empty($this->productGroupManager)) {
                    $this->productGroupManager = $this->container->get("product_group_manager");
                }

                $ids = $this->productGroupManager->getRandomProductsIdsInCategory(
                    $product,
                    $selectedProductGroupId
                );

                if (!empty($ids)) {

                    $ids = array_column($ids, "product_id");

                    $data = array();
                    $data["ids"] = $ids;
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
                }
            }

            $session = $this->getContainer()->get("session");

            $this->blockData["model"]["subtitle"] = $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $this->blockData["block"], "subtitle");
        }

        return $this->blockData;
    }
}
