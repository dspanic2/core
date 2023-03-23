<?php

namespace AppBundle\DAL;

use AppBundle\Helpers\UUIDHelper;

class EntityTypeDataAccess extends CoreDataAccess
{
    const ALIAS = 'entity_type';

    public function save($item)
    {
        if ($item->getUid()==null) {
            $item->setUid(UUIDHelper::generateUUID());
        }
        return parent::save($item);
    }

    function getItemByCode($code)
    {
        return $this->findBy(self::ALIAS,array('entityTypeCode' => $code),null,true);
    }

    function getItemById($id)
    {
        return $this->findBy(self::ALIAS,array('id' => $id),null,true);
    }


    function getItemByUid($uid)
    {
        return $this->findBy(self::ALIAS,array('uid' => $uid),null,true);
    }

    public function getEntityTypesByBundle($bundle)
    {
        return $this->findBy(self::ALIAS,array('bundle' => $bundle));
    }
}
