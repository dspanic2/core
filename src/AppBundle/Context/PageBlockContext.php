<?php

namespace AppBundle\Context;

use AppBundle\DAL\PageBlockDataAccess;
use AppBundle\Entity\EntityType;

class PageBlockContext extends CoreContext
{
    public function __construct(PageBlockDataAccess $dataAccess)
    {
        $this->dataAccess = $dataAccess;
    }

    public function findBlockInContent($blockId)
    {
        return $this->dataAccess->findBlockInContent($blockId);
    }

    public function getBlockByUid($uid)
    {
        return $this->dataAccess->getBlockByUid($uid);
    }

    public function getBlocksByParent($parent)
    {
        return $this->dataAccess->getBlocksByParent($parent);
    }

    function getByUid($uid)
    {
        return $this->dataAccess->getItemByUid($uid);
    }
    public function getPageBlocksByEntityType(EntityType $entityType)
    {
        return $this->dataAccess->getPageBlocksByEntityType($entityType);
    }
}
