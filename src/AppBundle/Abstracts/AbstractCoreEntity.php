<?php

namespace AppBundle\Abstracts;

abstract class AbstractCoreEntity
{


    public function convertToArray()
    {
        return array();
    }

    protected $changeSet;
    /**
     * @return mixed
     */
    public function getChangeSet()
    {
        return $this->changeSet;
    }

    /**
     * @param mixed $changeSet
     */
    public function setChangeSet($changeSet)
    {
        $this->changeSet = $changeSet;
    }
}
