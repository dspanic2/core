<?php

namespace AppBundle\DAL;

use AppBundle\Entity\EntityType;
use AppBundle\Entity\PageBlock;
use AppBundle\Helpers\StringHelper;
use AppBundle\Helpers\UUIDHelper;

class PageBlockDataAccess extends CoreDataAccess
{
    const ALIAS = 'page_block';

    public function save($item)
    {
        if ($item->getUid() == null) {
            $item->setUid(UUIDHelper::generateUUID());
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

    public function getById($id)
    {
        $entity = null;

        if (!preg_match('/^[0-9]*$/', $id)) {
            $entity = $this->getBlockByUid($id);
        } else {
            $entity = $this->findBy(self::ALIAS,array('id' => $id),null,true);
        }

        return $entity;
    }

    public function getBlockByUid($uid)
    {
        return $this->findBy(self::ALIAS,array('uid' => $uid),null,true);
    }

    public function getBlocksByParent($parent)
    {
        return $this->findBy(self::ALIAS,array('parent' => $parent));
    }


    function getItemByUid($uid)
    {
        return $this->findBy(self::ALIAS,array('uid' => $uid),null,true);
    }
    public function getPageBlocksByEntityType(EntityType $entityType)
    {
        return $this->findBy(self::ALIAS,array('entityType' => $entityType), array('id'=>'ASC'));
    }
}
