<?php

namespace AppBundle\Context;

use AppBundle\DAL\ListViewAttributeDataAccess;

class ListViewAttributeContext extends CoreContext
{

    public function __construct(ListViewAttributeDataAccess $dataAccess)
    {
        $this->dataAccess = $dataAccess;
    }
}
