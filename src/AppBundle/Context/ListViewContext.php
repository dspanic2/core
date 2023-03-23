<?php

namespace AppBundle\Context;

use AppBundle\DAL\ListViewDataAccess;
use AppBundle\Entity\EntityType;
use Symfony\Component\Validator\Tests\Fixtures\Entity;

class ListViewContext extends CoreContext
{

    public function __construct(ListViewDataAccess $dataAccess)
    {
        $this->dataAccess = $dataAccess;
    }

    public function getByName(EntityType $entityType, $name)
    {
        return $this->dataAccess->getByName($entityType, $name);
    }

    public function getListViewsByEntityType(EntityType $entityType)
    {
        return $this->dataAccess->getListViewsByEntityType($entityType);
    }
    public function getListViewById($id)
    {
        return $this->dataAccess->getListViewsById($id);
    }

    function getItemByUid($uid)
    {
        return $this->dataAccess->getItemByUid($uid);
    }

    function getItemById($id)
    {
        if(strlen($id) > 10){
            return $this->dataAccess->getItemByUid($id);
        }

        return $this->dataAccess->getById($id);
    }
}
