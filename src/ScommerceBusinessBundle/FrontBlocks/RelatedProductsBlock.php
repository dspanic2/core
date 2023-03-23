<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\ProductEntity;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\ProductGroupManager;

class RelatedProductsBlock extends AbstractBaseFrontBlock
{
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;

    public function GetBlockData()
    {
        $this->blockData["model"]["products"] = array();

        if (!empty($this->blockData["id"])) {

            $session = $this->getContainer()->get("session");

            if (empty($this->productGroupManager)) {
                $this->productGroupManager = $this->container->get("product_group_manager");
            }

            /** @var ProductEntity $entity */
            $entity = $this->blockData["page"];

            $linkTypeId = CrmConstants::PRODUCT_RELATION_TYPE_RELATED;

            $linkId = $this->blockData["block"]->getUrl();
            if (isset($linkId[$session->get("current_store_id")]) && !empty($linkId[$session->get("current_store_id")])) {
                // URL is used to pass link type ID!!!
                $linkTypeId = $linkId[$session->get("current_store_id")];
            }

            $ids = $this->productGroupManager->getRelatedProducts($entity, $linkTypeId);

            $showRelatedProductsFromSameProductGroup = $_ENV["RELATED_PRODUCTS_AUTOMATICLY_FROM_SAME_PRODUCT_GROUP"] ?? 0;

            if (!empty($ids)) {

                $ids = array_column($ids, "child_product_id");

                $data = array();
                $data["ids"] = $ids;
                $data["page_number"] = 1;
                $data["page_size"] = $this->blockData["block"]->getProductLimit();
                if (empty($data["page_size"])) {
                    $data["page_size"] = 10;
                }

                $sortData = $this->blockData["block"]->getProductSortData() ?? null;
                if (!empty($sortData)) {
                    $data["sort"] = '[{"sort_by": "custom", "sort_dir": "ids" }]';
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
            } else if ($showRelatedProductsFromSameProductGroup) {

                $ids = $this->productGroupManager->getProductIdsFromProductsProductGroup($entity);
                $ids = array_column($ids, "product_id");

                $data = array();
                $data["ids"] = $ids;
                $data["page_number"] = 1;
                $data["page_size"] = $this->blockData["block"]->getProductLimit();
                if (empty($data["page_size"])) {
                    $data["page_size"] = 10;
                }

                $sortData = $this->blockData["block"]->getProductSortData() ?? null;
                if (!empty($sortData)) {
                    $data["sort"] = '[{"sort_by": "custom", "sort_dir": "ids" }]';
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

        return $this->blockData;
    }

    public function GetBlockSetingsTemplate()
    {
        return 'ScommerceBusinessBundle:FrontBlockSettings:related_products_block.html.twig';
    }
}
