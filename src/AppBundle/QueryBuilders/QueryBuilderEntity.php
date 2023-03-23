<?php

namespace AppBundle\QueryBuilders;

class QueryBuilderEntity
{
    private $select;
    private $join;

    /**
     * @return mixed
     */
    public function getSelect()
    {
        return $this->select;
    }

    /**
     * @param mixed $select
     */
    public function setSelect($select)
    {
        $this->select = $select;
    }

    /**
     * @return mixed
     */
    public function getJoin()
    {
        return $this->join;
    }

    /**
     * @param mixed $join
     */
    public function setJoin($join)
    {
        $this->join = $join;
    }
}
