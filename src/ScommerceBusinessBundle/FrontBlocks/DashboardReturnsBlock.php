<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Entity\CoreUserEntity;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Managers\OrderManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class DashboardReturnsBlock extends AbstractBaseFrontBlock
{
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;

    public function GetBlockData()
    {
        if(empty($this->helperManager)){
            $this->helperManager = $this->container->get("helper_manager");
        }

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();

        /** @var ContactEntity $contact */
        $contact = $user->getDefaultContact();

        /** @var AccountEntity $account */
        $account = $contact->getAccount();

        if(empty($this->orderReturnManager)){
            $this->orderReturnManager = $this->container->get("order_return_manager");
        }

        $this->blockData["model"]["return_orders"] = $this->orderReturnManager->getReturnOrdersByAccount($account);

        return $this->blockData;
    }
}
