<?php

namespace AppBundle\DAL;

use AppBundle\Abstracts\AbstractEntity;

class TaskEntityDAL extends CoreDataAccess
{
    public function getTasksByEntity(AbstractEntity $entity)
    {
        return $this->entityManager->getRepository('AppBundle:EntityTaskLink')->findBy(
            array('entity'=> $entity, 'entityType' => $entity->getEntityType())
        );
    }
}
