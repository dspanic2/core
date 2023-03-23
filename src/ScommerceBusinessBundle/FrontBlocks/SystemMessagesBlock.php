<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class SystemMessagesBlock extends AbstractBaseFrontBlock
{
    public function GetBlockData()
    {
        $session = $this->container->get("session");

        $this->blockData["model"]["messages"] = $session->get("system_message");
        $session->set("system_message", array());

        return $this->blockData;
    }

    public function isVisible()
    {
        if (empty($this->blockData["id"])) {
            return false;
        }

        return true;
    }

}
