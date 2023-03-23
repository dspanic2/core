<?php

namespace AppBundle\Context;

use AppBundle\DAL\EntityAttributeDataAccess;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeGroup;
use AppBundle\Entity\AttributeSet;

class EntityAttributeContext extends CoreContext
{

    public function __construct(EntityAttributeDataAccess $dataAccess)
    {
        $this->dataAccess = $dataAccess;
    }

    public function getByAttributeSet(AttributeSet $attributeSet)
    {
        return $this->dataAccess->getByAttributeSet($attributeSet);
    }

    public function getByAttributeGroup(AttributeGroup $attributeGroup)
    {
        return $this->dataAccess->getByAttributeGroup($attributeGroup);
    }

    public function getByAttribute(Attribute $attribute)
    {
        return $this->dataAccess->getBy(array('attribute' => $attribute), array());
    }

    public function getEntitiesOfTypeByIds($filter)
    {
        return $this->dataAccess->findByIds($filter);
    }
}
