<?php

namespace AppBundle\DAL;

use AppBundle\Entity\EntityType;
use AppBundle\Helpers\StringHelper;
use AppBundle\Helpers\UUIDHelper;

class ListViewDataAccess extends CoreDataAccess
{
    const ALIAS = 'list_view';

    public function save($item)
    {
        if ($item->getUid() == null) {
            $item->setUid(UUIDHelper::generateUUID());
        }

        if ($item->getBundle() == null) {
            $item->setBundle($item->getEntityType()->getBundle());
        }

        return parent::save($item);
    }

    public function getByName(EntityType $entityType, $name)
    {
        return $this->findBy(self::ALIAS,array('entityType' => $entityType, 'name' => $name),null,true);
    }

    public function getListViewsByEntityType(EntityType $entityType)
    {
        return $this->findBy(self::ALIAS,array('entityType' => $entityType), array('id'=>'ASC'));
    }

    public function getListViewsById($id)
    {
        return $this->findBy(self::ALIAS,array('id' => $id), array('id'=>'ASC'));
    }

    function getItemByUid($uid)
    {
        return $this->findBy(self::ALIAS,array('uid' => $uid),null,true);
    }
}
