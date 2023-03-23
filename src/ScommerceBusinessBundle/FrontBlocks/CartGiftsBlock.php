<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\ApplicationSettingsManager;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Entity\QuoteItemEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\QuoteManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\ProductGroupManager;

class CartGiftsBlock extends AbstractBaseFrontBlock
{
    /** @var DefaultCrmProcessManager */
    protected $crmProcessManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;
    /** @var ApplicationSettingsManager $applicationSettingsManager */
    protected $applicationSettingsManager;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;

    public function GetBlockData()
    {
        if (empty($this->blockData["block"])) {
            /** @var EntityManager $entityManager */
            $entityManager = $this->container->get('entity_manager');
            $blockEntityType = $entityManager->getEntityTypeByCode("s_front_block");

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
            $compositeFilter->addFilter(new SearchFilter("active", "eq", 1));

            $compositeFilters = new CompositeFilterCollection();
            $compositeFilters->addCompositeFilter($compositeFilter);

            $block = $entityManager->getEntityByEntityTypeAndFilter($blockEntityType, $compositeFilters);
            if (!empty($block)) {
                $this->blockData["block"] = $block;
            }
        }

        $this->blockData["model"]["show"] = false;
        $this->blockData["model"]["limit"] = null;
        $this->blockData["model"]["max"] = 0;
        $this->blockData["model"]["gift_products"] = null;
        $this->blockData["model"]["total_available_gifts"] = 0;
        $this->blockData["model"]["total_used_gifts"] = 0;
        $this->blockData["model"]["used_gift_ids"] = array();
        $this->blockData["model"]["price_for_next_step"] = 0;
        $this->blockData["model"]["currency"] = "";

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        if (empty($this->quoteManager)) {
            $this->quoteManager = $this->container->get("quote_manager");
        }

        /** @var QuoteEntity $quote */
        $quote = $this->quoteManager->getActiveQuote(false);

        $ret = $this->crmProcessManager->getNumberOfAvailableGifts($quote);

        $this->blockData["model"]["show"] = $ret["show"];
        $this->blockData["model"]["limit"] = $ret["limit"];
        $this->blockData["model"]["max"] = $ret["max"];
        $this->blockData["model"]["total_available_gifts"] = $ret["total_available_gifts"];
        $this->blockData["model"]["price_for_next_step"] = $ret["price_for_next_step"];

        /*if(empty($this->applicationSettingsManager)){
            $this->applicationSettingsManager = $this->container->get("application_settings_manager");
        }

        $session = $this->container->get('session');

        $limitSettings = $this->applicationSettingsManager->getApplicationSettingByCode("gift_limit");
        if(!empty($limitSettings) && isset($limitSettings[$session->get("current_store_id")])){
            $this->blockData["model"]["limit"] = floatval($limitSettings[$session->get("current_store_id")]);
            $this->blockData["model"]["show"] = true;

            $maxSettings = $this->applicationSettingsManager->getApplicationSettingByCode("gift_max");
            if(!empty($maxSettings) && isset($maxSettings[$session->get("current_store_id")])){
                $this->blockData["model"]["max"] = floatval($maxSettings[$session->get("current_store_id")]);
            }
            else{
                $this->blockData["model"]["max"] = 1000;
            }
        }*/

        if (!$this->blockData["model"]["show"]) {
            return $this->blockData;
        }

        if (empty($this->productGroupManager)) {
            $this->productGroupManager = $this->container->get("product_group_manager");
        }

        $data = array();
        $data["page_number"] = 1;
        $data["page_size"] = $this->blockData["block"]->getProductLimit();
        if (empty($data["page_size"])) {
            $data["page_size"] = 8;
        }
        if (!empty($this->blockData["block"]->getProductSortData())) {
            $data["sort"] = $this->blockData["block"]->getProductSortData();
        }
        $data["pre_filter"] = json_decode('[{"connector":"and","filters":[{"field":"p.is_visible","operation":"eq","value":"0"},{"field":"p.is_gift","operation":"eq","value":1}]}]', true);

        $this->blockData["model"]["gift_products"] = $this->productGroupManager->getFilteredProducts($data);

        if (!empty($quote)) {

            $this->blockData["model"]["currency"] = $quote->getCurrency()->getSign();

            $this->blockData["model"]["quote"] = $quote;

            $priceTotal = $quote->getPriceTotal();

            if ($priceTotal > $this->blockData["model"]["limit"]) {

                /*$this->blockData["model"]["total_available_gifts"] = intval(($priceTotal/$this->blockData["model"]["limit"]));
                if($this->blockData["model"]["total_available_gifts"] > $this->blockData["model"]["max"]){
                    $this->blockData["model"]["total_available_gifts"] = $this->blockData["model"]["max"];
                }
                else{
                    $this->blockData["model"]["price_for_next_step"] = ($this->blockData["model"]["total_available_gifts"]+1)*$this->blockData["model"]["limit"] - $priceTotal;
                }*/

                $giftProductIds = array();
                if (!empty($this->blockData["model"]["gift_products"]["entities"])) {
                    /** @var ProductEntity $giftProduct */
                    foreach ($this->blockData["model"]["gift_products"]["entities"] as $giftProduct) {
                        $giftProductIds[] = $giftProduct->getId();
                    }
                }

                $quoteItems = $quote->getQuoteItems();
                if (EntityHelper::isCountable($quoteItems) && count($quoteItems)) {
                    /** @var QuoteItemEntity $quoteItem */
                    foreach ($quoteItems as $quoteItem) {
                        if (in_array($quoteItem->getProductId(), $giftProductIds)) {
                            $this->blockData["model"]["total_used_gifts"] = $this->blockData["model"]["total_used_gifts"] + $quoteItem->getQty();
                            $this->blockData["model"]["used_gift_ids"][] = $quoteItem->getProductId();
                        }
                    }
                }
            }
            /*else{
                $this->blockData["model"]["price_for_next_step"] = $this->blockData["model"]["limit"]-$priceTotal;
            }*/
        }

        return $this->blockData;
    }
}
