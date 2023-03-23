<?php

namespace AppBundle\Context;

use AppBundle\DAL\ActionDataAccess;

class ActionContext extends CoreContext
{
    public function __construct(ActionDataAccess $dataAccess)
    {
        $this->dataAccess = $dataAccess;
    }
}
