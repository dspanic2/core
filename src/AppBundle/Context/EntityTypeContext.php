<?php

namespace AppBundle\Context;

class EntityTypeContext extends CoreContext
{

    function getItemByCode($code)
    {

        return $this->dataAccess->getItemByCode($code);
    }

    function getItemById($id)
    {

        return $this->dataAccess->getItemById($id);
    }

    function getItemByUid($uid)
    {
        return $this->dataAccess->getItemByUid($uid);
    }

    public function getEntityTypesByBundle($bundle)
    {
        return $this->dataAccess->getEntityTypesByBundle($bundle);
    }
}
