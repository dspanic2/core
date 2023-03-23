<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Entity\CoreUserEntity;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\ContactEntity;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class DashboardLoyaltyBlock extends AbstractBaseFrontBlock
{
    /** @var HelperManager */
    protected $helperManager;

    public function GetBlockData()
    {
        $this->blockData["model"]["loyalty_card"] = null;

        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();

        if (!empty($user)) {
            /** @var ContactEntity $contact */
            $contact = $user->getDefaultContact();

            if (!empty($contact)) {
                $this->blockData["model"]["loyalty_card"] = $contact->getLoyaltyCard();
            }
        }

        return $this->blockData;
    }
}
