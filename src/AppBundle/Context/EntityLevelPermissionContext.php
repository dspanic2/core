<?php

namespace AppBundle\Context;

use AppBundle\DAL\EntityLevelPermissionDataAccess;
use AppBundle\Entity\UserEntity;

class EntityLevelPermissionContext extends CoreContext
{
    public function __construct(EntityLevelPermissionDataAccess $dataAccess)
    {
        $this->dataAccess = $dataAccess;
    }

    public function removeEntityPermissions($entity)
    {
        $this->dataAccess->removeEntityPermissions($entity);
    }
}
