<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;
use ScommerceBusinessBundle\Managers\ThirdPartyManager;

class LoginFormBlock extends AbstractBaseFrontBlock
{
    /** @var ThirdPartyManager $thirdPartyManager */
    protected $thirdPartyManager;

    public function GetBlockData()
    {
        if (empty($this->thirdPartyManager)) {
            $this->thirdPartyManager = $this->container->get("third_party_manager");
        }

        $this->blockData["model"]["google_login_url"] = $this->thirdPartyManager->getGoogleLoginButton("login_customer_google");
        $this->blockData["model"]["facebook_login_url"] = $this->thirdPartyManager->getFacebookLoginButton("login_customer_facebook");

        return $this->blockData;
    }

    public function isVisible()
    {
        if (empty($this->blockData["id"])) {
            return false;
        }

        //Check permission
        return true;
    }

}
