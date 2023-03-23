<?php

namespace ScommerceBusinessBundle\Extensions;

use AppBundle\Managers\CacheManager;

class GetEnvVariableExtension extends \Twig_Extension
{
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_env', array($this, 'getEnv')),
        ];
    }

    public function getEnv($envVariableName)
    {
        if (isset($_ENV[$envVariableName]) && !empty($_ENV[$envVariableName])) {
            return $_ENV[$envVariableName];
        }

        return null;
    }
}
