<?php

namespace AppBundle\Extensions;

use AppBundle\Helpers\StringHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StringExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('string_encrypt', array($this, 'stringEncrypt')),
            new \Twig_SimpleFunction('string_to_code', array($this, 'stringToCode')),
            new \Twig_SimpleFunction('string_replace', array($this, 'stringReplace')),
        ];
    }

    /**
     * @param $string
     * @return bool|string|null
     */
    public function stringEncrypt($string)
    {
        return StringHelper::encrypt($string);
    }

    /**
     * @param $string
     * @return bool|string|null
     */
    public function stringToCode($string)
    {
        return StringHelper::convertStringToCode($string);
    }

    /**
     * @param $search
     * @param $replace
     * @param $string
     * @return array|string|string[]
     */
    public function stringReplace($search, $replace, $string)
    {
        return str_ireplace($search, $replace, $string);
    }
}
