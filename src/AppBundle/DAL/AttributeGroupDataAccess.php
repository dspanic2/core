<?php

namespace AppBundle\DAL;

use AppBundle\Entity\AttributeSet;
use AppBundle\Helpers\StringHelper;
use AppBundle\Helpers\UUIDHelper;

class AttributeGroupDataAccess extends CoreDataAccess
{
    const ALIAS = 'attribute_group';

    public function save($item)
    {
        if ($item->getUid() == null) {
            $item->setUid(UUIDHelper::generateUUID());
        }
        return parent::save($item);
    }


    public function getAttributesGroupsBySet(AttributeSet $attributeSet)
    {
        return $this->findBy(self::ALIAS,array('attributeSet' => $attributeSet), array('sortOrder' => 'ASC'));
    }

    function getItemByUid($uid)
    {
        return $this->findBy(self::ALIAS,array('uid' => $uid),null,true);
    }
}
