<?php

namespace AppBundle\DAL;

use AppBundle\Entity\Entity;
use AppBundle\Entity\EntityLevelPermission;

class EntityLevelPermissionDataAccess extends CoreDataAccess
{
    public function removeEntityPermissions($entity)
    {

        $q = "delete from entity_level_permission where entity_id={$entity->getId()} AND entity_type_id={$entity->getEntityType()->getId()}";

        $query = $this->getConnection()->prepare($q);
        $query->execute();
    }
}
