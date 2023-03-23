<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Entity\CoreUserEntity;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Managers\AccountManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\ProductGroupManager;

class DashboardMostSoldProductsBlock extends AbstractBaseFrontBlock
{
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;

    public function GetBlockData()
    {
        $this->blockData["model"]["products"] = array();

        /*$this->blockData["model"]["go_to_all"] = false;

        if($this->blockData["block"]->getClass() == "dash_all_favorites"){

        }
        else{
            $this->blockData["model"]["go_to_all"] = true;
        }*/

        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();

        /** @var ContactEntity $contact */
        $contact = $user->getDefaultContact();

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $productIds = $this->accountManager->getMostSoldProductsByAccount($contact->getAccount());

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
