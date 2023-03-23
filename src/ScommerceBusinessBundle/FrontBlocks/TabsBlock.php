<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class TabsBlock extends AbstractBaseFrontBlock
{
    public function isVisible()
    {
        return true;
    }

    public function GetBlockAdminTemplate()
    {
        return 'ScommerceBusinessBundle:FrontBlockSettings:admin_container.html.twig';
    }
}
