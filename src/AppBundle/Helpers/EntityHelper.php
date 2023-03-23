<?php

namespace AppBundle\Helpers;

use Doctrine\Common\Util\Inflector;

class EntityHelper
{
    static function makeSetter($name)
    {
        return StringHelper::format("set{0}", ucfirst(Inflector::camelize($name)));
    }

    static function makeGetter($name)
    {

        return StringHelper::format("get{0}", ucfirst(Inflector::camelize($name)));
    }

    static function makeAttributeName($name)
    {
        return StringHelper::format("{0}", Inflector::camelize($name));
    }

    static function makeAttributeCode($name)
    {
        $pieces = preg_split('/(?=[A-Z])/',$name);

        return strtolower(implode("_",$pieces));
    }

    static function getPropertyAccessor($name)
    {

        $tmp = explode(".", $name);
        $getters = array();

        foreach ($tmp as $t) {
            $getters[] = EntityHelper::makeGetter($t);
        }

        return $getters;
    }

    static function checkIfPropertyExists($entity, $property)
    {

        return property_exists($entity, $property);
    }

    static function checkIfMethodExists($entity, $property)
    {

        return method_exists($entity, $property);
    }

    static function isCountable($c)
    {
        if (!function_exists('is_countable')) {
            return is_array($c) || $c instanceof \Countable;
        }
        return is_countable($c);
    }
}
