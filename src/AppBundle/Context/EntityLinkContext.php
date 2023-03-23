<?php

namespace AppBundle\Context;

use AppBundle\DAL\EntityLinkDAL;

class EntityLinkContext extends CoreContext
{
    public function __construct(EntityLinkDAL $dataAccess)
    {
        $this->dataAccess = $dataAccess;
    }

    public function getByEntityTypeAndId($entityTypeId, $entityId)
    {
        return $this->dataAccess->getByEntityTypeAndId($entityTypeId, $entityId);
    }


    public function getAllByEntityTypeAndId($entityTypeId, $entityId)
    {
        return $this->dataAccess->getAllByEntityTypeAndId($entityTypeId, $entityId);
    }

    public function deleteAllForEntity($entity)
    {
        $links= $this->getAllByEntityTypeAndId($entity->getEntityType()->getId(), $entity->getId());

        foreach ($links as $link) {
            $this->delete($link);
        }
    }
}
