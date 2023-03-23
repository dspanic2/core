<?php

namespace AppBundle\Context;

use AppBundle\AppBundle;
use AppBundle\DAL\AttributeDataAccess;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\ListView;

class AttributeContext extends CoreContext
{
    public function __construct(AttributeDataAccess $dataAccess)
    {
        $this->dataAccess = $dataAccess;
    }

    public function getAttributesByEntityType(EntityType $entityType)
    {
        return $this->dataAccess->getAttributesByEntityType($entityType);
    }

    public function getAttributeByCode($code, EntityType $entityType)
    {
        return $this->dataAccess->getAttributeByCode($code, $entityType);
    }

    public function getAttributeByName($code)
    {
        return $this->dataAccess->getAttributeByName($code);
    }

    public function findByIds($filter)
    {
        return $this->dataAccess->findByIds($filter);
    }

    public function getAttributesByFilter($code, $value)
    {
        return $this->dataAccess->getAttributesByFilter($code, $value);
    }

    function getItemByUid($uid)
    {
        return $this->dataAccess->getItemByUid($uid);
    }
}
