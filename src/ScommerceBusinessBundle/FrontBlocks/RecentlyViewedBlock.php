<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Entity\CoreUserEntity;
use AppBundle\Managers\HelperManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Managers\TrackingManager;

class RecentlyViewedBlock extends AbstractBaseFrontBlock
{
    /** @var TrackingManager $trackingManager */
    protected $trackingManager;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;

    public function GetBlockData()
    {
        $this->blockData["model"]["products"] = array();

        if (empty($this->trackingManager)) {
            $this->trackingManager = $this->container->get("tracking_manager");
        }

        $ids = array();
        $contact = null;

        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();

        if (!empty($user)) {
            $contact = $user->getDefaultContact();
        }

        $session = $this->getContainer()->get("session");

        $avoidIds = "";

        $entity = $this->blockData["page"];
        if ($entity->getEntityType()->getEntityTypeCode() == "product") {
            $avoidIds = " AND product_id != {$entity->getId()} ";
        }


        /**
         * If user is logged in search by contact id
         */
//        if(!empty($contact)){
//            $filter = " WHERE event_type = 'page_view' AND event_name = 'page_viewed' AND contact_id = {$contact->getId()} AND store_id = {$session->get("current_store_id")} {$avoidIds} ";
//            $groupBy = " GROUP BY product_id ";
//            $orderBy = " ORDER BY created DESC ";
//            $ids = $this->trackingManager->getProductImpressions($filter,$groupBy,$orderBy);
//        }
//        else{
        $filter = " WHERE event_type = 'page_view' AND event_name = 'page_viewed' AND session_id = '{$session->getId()}' AND store_id = {$session->get("current_store_id")} {$avoidIds} ";
        $groupBy = " GROUP BY product_id ";
        $orderBy = " ORDER BY created DESC ";
        $ids = $this->trackingManager->getProductImpressions($filter, $groupBy, $orderBy);
//        }

        if (!empty($ids)) {

            $ids = array_column($ids, "product_id");

            if (empty($this->productGroupManager)) {
                $this->productGroupManager = $this->container->get("product_group_manager");
            }

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
            $this->blockData["model"]["subtitle"] = $this->getPageUrlExtension->getEntityStoreAttribute($session->get("current_store_id"), $this->blockData["block"], "subtitle");
        }

        return $this->blockData;
    }
}
