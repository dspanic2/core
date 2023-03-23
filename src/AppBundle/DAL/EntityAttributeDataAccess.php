<?php

namespace AppBundle\DAL;

use AppBundle\Entity\AttributeGroup;
use AppBundle\Entity\AttributeSet;

class EntityAttributeDataAccess extends CoreDataAccess
{
    const ALIAS = 'entity_attribute';

    function getItemByName($name)
    {
        return $this->findBy(self::ALIAS,array('attributeSetName' => $name),null,true);
    }

    public function getByAttributeSet(AttributeSet $attributeSet)
    {
        return $this->findBy(
            self::ALIAS,
            array('attributeSet' => $attributeSet),
            array('sortOrder' => 'ASC')
        );
    }

    public function getByAttributeGroup(AttributeGroup $attributeGroup)
    {
        return $this->findBy(
            self::ALIAS,
            array('attributeGroup' => $attributeGroup),
            array('sortOrder' => 'ASC')
        );
    }
}
