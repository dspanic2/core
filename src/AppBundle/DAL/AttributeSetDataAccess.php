<?php

namespace AppBundle\DAL;

use AppBundle\Entity\EntityType;
use AppBundle\Helpers\UUIDHelper;

class AttributeSetDataAccess extends CoreDataAccess
{
    const ALIAS = 'attribute_set';

    public function save($item)
    {
        if ($item->getUid() == null) {
            $item->setUid(UUIDHelper::generateUUID());
        }
        return parent::save($item);
    }

    function getItemByCode($code)
    {
        return $this->findBy(self::ALIAS,array('attributeSetCode' => $code),null,true);
    }

    public function getAttributeSetsByEntityType(EntityType $entityType)
    {
        return $this->findBy(self::ALIAS,array('entityType' => $entityType), array('attributeSetCode'=>'ASC'));
    }

    function getItemByUid($uid)
    {
        return $this->findBy(self::ALIAS,array('uid' => $uid),null,true);
    }
}
