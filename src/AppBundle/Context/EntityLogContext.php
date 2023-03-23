<?php

namespace AppBundle\Context;

use AppBundle\DAL\EntityLogDAL;

class EntityLogContext extends CoreContext
{
    public function __construct(EntityLogDAL  $dataAccess)
    {
        $this->dataAccess = $dataAccess;
    }
}
