<?php

namespace AppBundle\Twig\Extension;

use AppBundle\Helpers\StringHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \Twig_Extension;
use Doctrine\Common\Util\Inflector;

class VarsExtension extends Twig_Extension
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getName()
    {
        return 'some.extension';
    }

    // Note: If you want to use it as {{ json_decode(var) }} instead of
    // {{ var|json_decode }} please use getFunctions() and
    // new \Twig_SimpleFunction('json_decode', 'json_decode')
    public function getFilters()
    {
        return [
            // Note that we map php json_decode function to
            // extension filter of the same name
            new \Twig_SimpleFilter('json_decode', 'json_decode'),
            new \Twig_SimpleFilter('json_decode_array', array($this, 'json_decode_array')),
            new \Twig_SimpleFilter('camelize', array($this, 'camelize')),
            new \Twig_SimpleFilter('stringEncrypt', array($this, 'stringEncrypt')),
        ];
    }

    public function json_decode_array($object)
    {
        return json_decode($object, true);
    }

    public function camelize($object)
    {
        return Inflector::camelize($object);
    }

    public function stringEncrypt($string)
    {
        return StringHelper::encrypt($string);
    }
}
