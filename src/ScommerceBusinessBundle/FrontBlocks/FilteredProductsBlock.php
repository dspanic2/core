<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use CrmBusinessBundle\Managers\ProductManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Entity\SortOptionEntity;
use ScommerceBusinessBundle\Managers\FrontProductsRulesManager;
use ScommerceBusinessBundle\Managers\ProductGroupManager;

class FilteredProductsBlock extends AbstractBaseFrontBlock
{
    const CACHE_BLOCK_HTML = true;
    const CACHE_BLOCK_HTML_TAGS = ['product', 's_front_block'];

    /** @var ProductManager $productManager */
    protected $productManager;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;
    /** @var FrontProductsRulesManager $frontProductRulesManager */
    protected $frontProductRulesManager;

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

            $productRules = $this->blockData["block"]->getRules();

            $productCodes = $this->blockData["block"]->getProductCodes();
            if (!empty($productCodes)) {
                $productCodes = explode(",", $productCodes);
                $productCodes = array_map('trim', $productCodes);
                $productCodes = array_filter($productCodes);
                foreach ($productCodes as $key => $value) {
                    if (!intval($value)) {
                        unset($productCodes[$key]);
                    }
                }
                $productCodes = "'".implode("','", $productCodes)."'";
            }

            if (!empty($productCodes)) {
                if (empty($this->productManager)) {
                    $this->productManager = $this->container->get("product_manager");
                }

                $data = array();
                $data["page_number"] = 1;
                $data["ids"] = $this->productManager->getProductIdsFromCodes($productCodes);

                $limit = $this->blockData["block"]->getProductLimit();
                if (empty($limit) || $limit == 0) {
                    $limit = count($data["ids"]);
                }
                $data["page_size"] = $limit;

                /** @var SortOptionEntity $defaultSort */
                $defaultSort = $this->blockData["block"]->getDefaultSort();
                if (!empty($defaultSort)) {
                    $data["sort"] = $defaultSort->getSortByValue();
                }

                $this->blockData["model"]["products"] = $this->productGroupManager->getFilteredProducts($data);
            } elseif (!empty($productRules)) {
                if (empty($this->frontProductRulesManager)) {
                    $this->frontProductRulesManager = $this->container->get("front_product_rules_manager");
                }

                $ids = $this->frontProductRulesManager->getProductIdsForRule($productRules);

                if (empty($ids)) {
                    return $this->blockData;
                }

                $data = array();
                $data["page_number"] = 1;
                $data["ids"] = $ids;

                $limit = $this->blockData["block"]->getProductLimit();
                if (empty($limit) || $limit == 0) {
                    $limit = count($data["ids"]);
                }
                $data["page_size"] = $limit;

                /** @var SortOptionEntity $defaultSort */
                $defaultSort = $this->blockData["block"]->getDefaultSort();
                if (!empty($defaultSort)) {
                    $data["sort"] = $defaultSort->getSortByValue();
                }

                $this->blockData["model"]["products"] = $this->productGroupManager->getFilteredProducts($data);
            } else {
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

                /** @var SortOptionEntity $defaultSort */
                $defaultSort = $this->blockData["block"]->getDefaultSort();
                if (!empty($defaultSort)) {
                    $data["sort"] = $defaultSort->getSortByValue();
                }

                $this->blockData["model"]["products"] = $this->productGroupManager->getFilteredProducts($data);
            }

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
