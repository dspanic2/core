<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class GoogleAnalyticsBlock extends AbstractBaseBlock
{
    public function GetPageBlockTemplate()
    {
        return "AppBundle:Block:google_analytics.html.twig";
    }

    public function GetPageBlockSetingsTemplate()
    {
        return "AppBundle:BlockSettings:google_analytics.html.twig";
    }

    public function GetPageBlockData()
    {
        return $this->pageBlockData;
    }
}
