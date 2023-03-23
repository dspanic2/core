<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use CrmBusinessBundle\Entity\FavoriteEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\TrackingEntity;
use CrmBusinessBundle\Extensions\CrmHelperExtension;
use CrmBusinessBundle\Managers\AccountManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\ProductGroupManager;

class FavoritesBlock extends AbstractBaseFrontBlock
{
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;
    /** @var CrmHelperExtension $crmHelperExtenson */
    protected $crmHelperExtenson;

    public function GetBlockData()
    {
        $this->blockData["model"]["store_products"] = array();

        if (!empty($this->blockData["id"])) {

            $productIds = array();
            $this->blockData["model"]["products"] = null;
            $email = null;

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
                    foreach ($favorites as $favorite) {

                        /** @var ProductEntity $product */
                        $product = $favorite->getProduct();

                        if ($product->getIsVisible() == 0) {
                            if (empty($this->crmHelperExtenson)) {
                                $this->crmHelperExtenson = $this->container->get("crm_helper_extension");
                            }

                            $product = $this->crmHelperExtenson->getMasterProduct($product);
                        }

                        $productIds[] = $product->getId();
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

            if (!empty($this->blockData["model"]["products"])) {
                $this->accountManager->initializeFavorites(true);
            }
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
