<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use CrmBusinessBundle\Managers\AccountManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class ResetPasswordNewPasswordFormBlock extends AbstractBaseFrontBlock
{
    /** @var AccountManager $accountManager */
    protected $accountManager;

    public function GetBlockData()
    {
        $this->blockData["model"]["token"] = false;

        $token = $_GET['token'] ?? null;

        if (!empty($token)) {

            if (empty($this->accountManager)) {
                $this->accountManager = $this->container->get("account_manager");
            }

            $user = $this->accountManager->getUserByPasswordResetToken($token);

            if (!empty($user)) {
                $this->blockData["model"]["token"] = $token;
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

    public function isVisible()
    {
        if (empty($this->blockData["id"])) {
            return false;
        }

        //Check permission
        return true;
    }

}
