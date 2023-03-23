<?php

namespace AppBundle\DAL;

class EntityLinkDAL extends CoreDataAccess
{

    public function getByEntityTypeAndId($entityTypeId, $entityId)
    {
        return $this->repository->findOneBy(array('entityTypeId' => $entityTypeId, "entityId" => $entityId));
    }

    public function getAllByEntityTypeAndId($entityTypeId, $entityId)
    {
        return $this->repository->findBy(array('entityTypeId' => $entityTypeId, "entityId" => $entityId));
    }
}
