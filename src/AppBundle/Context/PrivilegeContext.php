<?php

namespace AppBundle\Context;

use AppBundle\DAL\PrivilegeDataAccess;

class PrivilegeContext extends CoreContext
{
    public function __construct(PrivilegeDataAccess $dataAccess)
    {
        $this->dataAccess = $dataAccess;
    }

    public function getPrivilegesForUserByEntityType($user, $entityTypeId)
    {
        $this->dataAccess->getPrivilegesForUserByEntityType($user, $entityTypeId);
    }
}
