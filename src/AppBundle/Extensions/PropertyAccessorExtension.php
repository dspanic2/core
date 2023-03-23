<?php

namespace AppBundle\Extensions;

use Symfony\Component\PropertyAccess\PropertyAccess;
use AppBundle\Twig;

class PropertyAccessorExtension extends \Twig_Extension
{
    /** @var  PropertyAccess */
    protected $accessor;


    public function __construct()
    {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('getAttribute', array($this, 'getAttribute'))
        );
    }

    public function getAttribute($entity, $property)
    {
        try {
            return $this->accessor->getValue($entity, $property);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     *
     */
    public function getName()
    {
        return 'property_accessor_extension';
    }
}
