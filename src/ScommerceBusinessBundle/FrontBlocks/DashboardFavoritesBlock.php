<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use CrmBusinessBundle\Entity\FavoriteEntity;
use CrmBusinessBundle\Entity\TrackingEntity;
use CrmBusinessBundle\Managers\AccountManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\ProductGroupManager;

class DashboardFavoritesBlock extends AbstractBaseFrontBlock
{
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;

    public function GetBlockData()
    {
        $this->blockData["model"]["products"] = array();

        $limit = 100;

        if (stripos($this->blockData["block"]->getClass(), "dash_all_favorites") !== false) {
            $this->blockData["model"]["go_to_all"] = false;
        } else {
            $this->blockData["model"]["go_to_all"] = true;
            $limit = 3;
        }

        $productIds = array();

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $session = $this->container->get('session');
        $sessionId = $session->getId();

        /** @var TrackingEntity $tracking */
        $tracking = $this->accountManager->getTracking($sessionId);

        if (!empty($tracking) && !empty($tracking->getEmail())) {

            $favorites = $this->accountManager->getFavoritesByEmail($tracking->getEmail());

            if (!empty($favorites)) {
                /** @var FavoriteEntity $favorite */
                foreach ($favorites as $key => $favorite) {
                    if ($key >= $limit) {
                        break;
                    }
                    $productIds[] = $favorite->getProductId();
                }
            }
        }

        if (!empty($productIds)) {

            if (empty($this->productGroupManager)) {
                $this->productGroupManager = $this->container->get("product_group_manager");
            }

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
            $data["ids"] = $productIds;

            $this->blockData["model"]["products"] = $this->productGroupManager->getFilteredProducts($data);
        }

        if (!empty($this->blockData["model"]["products"]) && $this->blockData["block"]->getClass() != "dash_all_favorites") {
            $this->blockData["model"]["products"] = array_slice($this->blockData["model"]["products"], 0, 5, true);
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
}
