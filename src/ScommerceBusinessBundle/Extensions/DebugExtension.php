<?php

namespace ScommerceBusinessBundle\Extensions;

class DebugExtension extends \Twig_Extension
{

    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('die', array($this, 'killRender')),
        ];
    }

    public function killRender($message = null)
    {
        dump($message);
        die();
    }
}
