<?php

namespace AppBundle\Context;

use AppBundle\AppBundle;
use AppBundle\DAL\AttributeGroupDataAccess;
use AppBundle\Entity\AttributeSet;

class AttributeGroupContext extends CoreContext
{
    public function __construct(AttributeGroupDataAccess $dataAccess)
    {
        $this->dataAccess = $dataAccess;
    }

    public function getAttributesGroupsBySet(AttributeSet $attributeSet)
    {
        return $this->dataAccess->getAttributesGroupsBySet($attributeSet);
    }


    function getItemByUid($uid)
    {
        return $this->dataAccess->getItemByUid($uid);
    }
}
