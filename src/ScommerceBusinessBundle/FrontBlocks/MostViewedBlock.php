<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\PagingFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\EntityManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Managers\TrackingManager;

class MostViewedBlock extends AbstractBaseFrontBlock
{
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;
    /** @var TrackingManager $trackingManager */
    protected $trackingManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;

    public function GetBlockData()
    {
        if (!empty($this->blockData["block"]->getProductFilterData())) {
            $tmpFilterData = json_decode($this->blockData["block"]->getProductFilterData(), true);

            if (!isset($tmpFilterData["page_type"])) {
                return $this->blockData;
            }

            if (empty($this->trackingManager)) {
                $this->trackingManager = $this->container->get("tracking_manager");
            }

            $filter = " WHERE event_type = 'page_view' AND event_name = 'page_viewed' AND page_type='{$tmpFilterData["page_type"]}' AND MONTH(event_time) = MONTH(CURRENT_DATE()) AND YEAR(event_time) = YEAR(CURRENT_DATE()) ";
            $ids = $this->trackingManager->getPageImpressions($filter);
            if (empty($ids)) {
                return $this->blockData;
            }

            $ids = array_column($ids, "id");

            $this->blockData["model"]["ids"] = $ids;

            if ($tmpFilterData["page_type"] == "product") {
                unset($tmpFilterData["page_type"]);

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
                $data["ids"] = $ids;
                if (!empty($this->blockData["block"]->getProductSortData())) {
                    $data["sort"] = $this->blockData["block"]->getProductSortData();
                }
                if (isset($tmpFilterData["pre_filter"])) {
                    $data["pre_filter"] = $tmpFilterData["pre_filter"];
                }
                if (isset($tmpFilterData["filter"])) {
                    $data["filter"] = $tmpFilterData["filter"];
                    $data["get_filter"] = true;
                }

                $this->blockData["model"]["products"] = $this->productGroupManager->getFilteredProducts($data);
            } else {
                if (empty($this->entityManager)) {
                    $this->entityManager = $this->container->get("entity_manager");
                }

                $et = $this->entityManager->getEntityTypeByCode($tmpFilterData["page_type"]);

                if (empty($et)) {
                    return $this->blockData;
                }

                $compositeFilter = new CompositeFilter();
                $compositeFilter->setConnector("and");
                $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
                $compositeFilter->addFilter(new SearchFilter("id", "in", implode(",", $ids)));

                $compositeFilters = new CompositeFilterCollection();
                $compositeFilters->addCompositeFilter($compositeFilter);

                $pagingFilter = new PagingFilter();
                $pagingFilter->setPageNumber(0);
                $pagingFilter->setPageSize($this->blockData["block"]->getProductLimit() ?? 10);

                $this->blockData["model"][$tmpFilterData["page_type"]] = $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, null, $pagingFilter);

            }
        }

        return $this->blockData;
    }
}
