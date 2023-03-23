<?php

namespace AppBundle\Models;

class LookupModel
{
    protected $column;
    protected $sortValue;
    protected $lookupTable;

    /**
     * @param $column
     * @param $sortValue
     * @param $lookupTable
     */
    public function __construct($column, $sortValue, $lookupTable)
    {
        $this->column = $column;
        $this->sortValue = $sortValue;
        $this->lookupTable = $lookupTable;
    }

    /**
     * @return mixed
     */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * @return mixed
     */
    public function getSortValue()
    {
        return $this->sortValue;
    }

    /**
     * @return mixed
     */
    public function getLookupTable()
    {
        return $this->lookupTable;
    }
}
