<?php

namespace AppBundle\Context;

use AppBundle\AppBundle;
use AppBundle\DAL\AttributeSetDataAccess;
use AppBundle\Entity\EntityType;

class AttributeSetContext extends CoreContext
{
    function getItemByCode($code)
    {

        return $this->dataAccess->getItemByCode($code);
    }

    public function getEntitiesOfTypeByIds($filter)
    {
        return $this->dataAccess->findByIds($filter);
    }

    public function getAttributeSetsByEntityType(EntityType $entityType)
    {
        return $this->dataAccess->getAttributeSetsByEntityType($entityType);
    }

    function getItemByUid($uid)
    {
        return $this->dataAccess->getItemByUid($uid);
    }
}
