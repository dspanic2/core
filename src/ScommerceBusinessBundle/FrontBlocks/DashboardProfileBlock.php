<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Entity\CoreUserEntity;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class DashboardProfileBlock extends AbstractBaseFrontBlock
{

    /** @var HelperManager $helperManager */
    protected $helperManager;

    public function GetBlockData()
    {
        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();

        /** @var ContactEntity $contact */
        $contact = $user->getDefaultContact();

        /** @var AccountEntity $account */
        $account = $contact->getAccount();

        $this->blockData["model"]["user"] = $user;
        $this->blockData["model"]["contact"] = $contact;
        $this->blockData["model"]["account"] = $account;

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
