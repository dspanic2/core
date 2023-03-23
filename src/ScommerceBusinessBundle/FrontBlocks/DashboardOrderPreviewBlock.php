<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Entity\CoreUserEntity;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Managers\OrderManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class DashboardOrderPreviewBlock extends AbstractBaseFrontBlock
{
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;

    public function GetBlockData()
    {
        $this->blockData["model"]["missing_order"] = false;

        if (isset($_GET['order']) && $order_hash = $_GET['order']) {
            $order_id = StringHelper::decrypt($_GET['order']);

            if (empty($this->helperManager)) {
                $this->helperManager = $this->container->get("helper_manager");
            }

            /** @var CoreUserEntity $user */
            $user = $this->helperManager->getCurrentCoreUser();

            /** @var ContactEntity $contact */
            $contact = $user->getDefaultContact();
            $this->blockData["model"]["contact"] = $contact;

            /** @var AccountEntity $account */
            $account = $contact->getAccount();
            $this->blockData["model"]["account"] = $account;

            if (empty($this->orderManager)) {
                $this->orderManager = $this->container->get("order_manager");
            }

            $orders = $this->orderManager->getOrdersByAccountAndId($account, $order_id);

            if (!empty($orders)) {
                /** @var OrderEntity $order */
                $order = $orders[0];
                $this->blockData["model"]["order"] = $order;
                $this->blockData["model"]["quote"] = $order->getQuote();
            }
        } else {
            $this->blockData["model"]["missing_order"] = true;
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
