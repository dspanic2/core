<?php

namespace AppBundle\DAL;

use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\EntityType;
use AppBundle\Helpers\UUIDHelper;

class AttributeDataAccess extends CoreDataAccess
{
    const ALIAS = 'attribute';

    public function save($item)
    {
        if ($item->getUid() == null) {
            $item->setUid(UUIDHelper::generateUUID());
        }
        return parent::save($item);
    }

    public function getAttributesByEntityType(EntityType $entityType)
    {
        return $this->findBy(self::ALIAS,array('entityType' => $entityType));
    }

    public function getAttributeByCode($code, $entityType)
    {
        return $this->findBy(self::ALIAS,array('attributeCode' => $code,'entityType' => $entityType),null,true);
    }

    public function getAttributeByName($code)
    {
        return $this->findBy(self::ALIAS,array('attributeCode' => $code),null,true);
    }

    public function getAttributesByFilter($code, $value)
    {
        $qb = $this->repository->createQueryBuilder('a');
        $qb->where(("a.{$code} like :term"));
        $qb->setParameter('term', $value);

        if(isset($_ENV["USE_BACKEND_CACHE"]) && !empty($_ENV["USE_BACKEND_CACHE"]) && $_ENV["USE_BACKEND_CACHE"] == "redis"){
            $query = $qb->getQuery()->setCacheable(true);
        }
        else{
            $query = $qb->getQuery();
        }

        return $query->getResult();
    }

    function getItemByUid($uid)
    {
        return $this->findBy(self::ALIAS,array('uid' => $uid),null,true);
    }


}
