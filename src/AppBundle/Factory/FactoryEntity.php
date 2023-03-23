<?php


namespace AppBundle\Factory;

use AppBundle\Entity\ContactEntityVarchar;
use AppBundle\Entity\Entity;

class FactoryEntity
{

    public static function getObject($bundle, $entityType)
    {

        $entityType = ucfirst($entityType);

        if (0 === strpos($entityType, 'Entity')) {
            $class = "AppBundle\\Entity\\".$entityType;
        } else {
            $class = $bundle."\\Entity\\".$entityType;
        }

        return new $class();
    }
}
