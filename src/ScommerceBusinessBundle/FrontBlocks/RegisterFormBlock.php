<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use CrmBusinessBundle\Managers\AccountManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class RegisterFormBlock extends AbstractBaseFrontBlock
{
    /** @var AccountManager $accountManager */
    protected $accountManager;

    public function GetBlockData()
    {
        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        $defaultCountryId = 1;
        if (isset($_ENV["DEFAULT_COUNTRY"]) && !empty($_ENV["DEFAULT_COUNTRY"])) {
            $defaultCountryId = $_ENV["DEFAULT_COUNTRY"];
        }

        $this->blockData["model"]["default_country"] = $this->accountManager->getCountryById($defaultCountryId);

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
