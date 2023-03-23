<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use CrmBusinessBundle\Managers\ProductManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Entity\SortOptionEntity;
use ScommerceBusinessBundle\Managers\FrontProductsRulesManager;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Traits\ProductGridTrait;

class CustomProductGridBlock extends AbstractBaseFrontBlock
{
    use ProductGridTrait;

    /** @var ProductManager $productManager */
    protected $productManager;
    /** @var FrontProductsRulesManager $frontProductRulesManager */
    protected $frontProductRulesManager;

    public function GetBlockData()
    {
        $this->blockData["model"]["subtitle"] = "";

        if (!empty($this->blockData["id"])) {

            if (empty($this->productGroupManager)) {
                $this->productGroupManager = $this->container->get("product_group_manager");
            }

            $session = $this->getContainer()->get("session");

            $params = $_GET;

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
                $ids = $this->productManager->getProductIdsFromCodes($productCodes);
                if (empty($ids)) {
                    return $this->blockData;
                }

                $params["ids"] = $ids;

                $this->blockData["model"]["ids"] = implode(",", $ids);

                $this->blockData["model"]["productFilterData"] = '{"pre_filter":[{"connector":"and","filters":[{"field":"p.id","operation":"in","value":"' . $this->blockData["model"]["ids"] . '"}]}]}';
            } elseif (!empty($productRules)) {
                if (empty($this->frontProductRulesManager)) {
                    $this->frontProductRulesManager = $this->container->get("front_product_rules_manager");
                }

                $ids = $this->frontProductRulesManager->getProductIdsForRule($productRules);
                if (empty($ids)) {
                    return $this->blockData;
                }

                $data = array();
                $data["ids"] = $ids;
                $data["page_size"] = $params["page_size"] ?? $_ENV["PRODUCT_GRID_DEFAULT_PAGE_SIZE"];
                $data["page_number"] = $params["page_number"] ?? 1;

                $params["ids"] = $ids;

                $this->blockData["model"]["ids"] = implode(",", $ids);
                $this->blockData["model"]["products"] = $this->productGroupManager->getFilteredProducts($data);
                $this->blockData["model"]["productFilterData"] = '{"pre_filter":[{"connector":"and","filters":[{"field":"p.id","operation":"in","value":"' . $this->blockData["model"]["ids"] . '"}]}]}';
            } else {
                $this->blockData["model"]["productFilterData"] = $this->blockData["block"]->getProductFilterData();
                if (!empty($this->blockData["model"]["productFilterData"])) {
                    $params["pre_filter"] = json_decode($this->blockData["model"]["productFilterData"], true)["pre_filter"] ?? [];
                }
            }

            /** @var SortOptionEntity $defaultSort */
            $defaultSort = $this->blockData["block"]->getDefaultSort();
            if (!empty($defaultSort)) {
                $params["default_sort"] = $defaultSort->getId();
            }

//            $params["first_load"] = 1;

            $data = $this->getProductGridHtmlData($params);

            $this->blockData["model"]["data"] = $data;
            $this->blockData["model"]["entities"] = $data["entities"] ?? [];
            $this->blockData["model"]["grid_html"] = $data["grid_html"] ?? "";
            $this->blockData["model"]["sort_html"] = $data["sort_html"] ?? "";
            $this->blockData["model"]["pager_html"] = $data["pager_html"] ?? "";
            $this->blockData["model"]["filter_html"] = $data["filter_html"] ?? "";

            if (isset($_ENV["SHOW_ACTIVE_FILTERS"]) && !empty($_ENV["SHOW_ACTIVE_FILTERS"])) {
                $this->blockData["model"]["active_filters"] = $data["active_filters"] ?? "";
            }

            $this->blockData["model"]["subtitle"] = $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $this->blockData["block"], "subtitle");

            $this->blockData["model"]["params"] = $params;
        }

        return $this->blockData;
    }

    public function isVisible()
    {
        if (empty($this->blockData["id"])) {
            return false;
        }

        return true;
    }

}
