<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Entity\CoreUserEntity;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Managers\OrderManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class DashboardRecentOrdersBlock extends AbstractBaseFrontBlock
{
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;

    public function GetBlockData()
    {
        $limit = 10000;

        if (stripos($this->blockData["block"]->getClass(), "dash_all_orders") !== false) {
            $this->blockData["model"]["go_to_all"] = false;
        } else {
            $this->blockData["model"]["go_to_all"] = true;;
            $limit = 5;
        }

        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();

        /** @var ContactEntity $contact */
        $contact = $user->getDefaultContact();

        /** @var AccountEntity $account */
        $account = $contact->getAccount();

        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        $this->blockData["model"]["orders"] = $this->orderManager->getOrdersByAccount($account);
        $this->blockData["model"]["order_states"] = $this->orderManager->getOrderStatuses();

        if (!empty($this->blockData["model"]["orders"]) && stripos($this->blockData["block"]->getClass(), "dash_all_orders") === false) {
            $this->blockData["model"]["orders"] = array_slice($this->blockData["model"]["orders"], 0, $limit, true);
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
