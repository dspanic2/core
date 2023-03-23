<?php

namespace AppBundle\DAL;

use AppBundle\Entity\EntityType;
use AppBundle\Entity\Page;
use AppBundle\Helpers\UUIDHelper;

class PageDataAccess extends CoreDataAccess
{
    const ALIAS = 'page';

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

    public function findBlockInContent($blockId)
    {
        $qb = $this->repository->createQueryBuilder('a');
        $qb->where(('a.content like :term'));
        $qb->setParameter('term', '%\"id\":\"'.$blockId.'\"%');

        return $qb->getQuery()->getResult();
    }

    function getItemByUid($uid)
    {
        return $this->findBy(self::ALIAS,array('uid' => $uid),null,true);
    }

    public function getPagesByEntityType(EntityType $entityType)
    {
        return $this->findBy(self::ALIAS,array('entityType' => $entityType), array('id'=>'ASC'));
    }
}
