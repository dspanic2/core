<?php

namespace AppBundle\Context;

use AppBundle\DAL\PageDataAccess;
use AppBundle\Entity\EntityType;

class PageContext extends CoreContext
{
    public function __construct(PageDataAccess $dataAccess)
    {
        $this->dataAccess = $dataAccess;
    }

    public function findBlockInContent($blockId)
    {
        return $this->dataAccess->findBlockInContent($blockId);
    }

    function getItemByUid($uid)
    {
        return $this->dataAccess->getItemByUid($uid);
    }

    public function getPagesByEntityType(EntityType $entityType)
    {
        return $this->dataAccess->getPagesByEntityType($entityType);
    }
}
