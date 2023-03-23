<?php

namespace AppBundle\DAL;

use AppBundle\Abstracts\AbstractEntity;

class FileEntityDAL extends CoreDataAccess
{
    public function getFolderItems($folder)
    {
        return $this->repository->findBy(array('parent' => $folder));
    }
}
