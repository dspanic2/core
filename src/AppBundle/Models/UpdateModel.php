<?php

namespace AppBundle\Models;

use Closure;

class UpdateModel
{
    protected $updateAttributes;
    protected $entityArray;
    protected $updateArray;

    protected $lookups;
    protected $functions;

    /**
     * @param $entityArray
     * @param null $updateAttributes
     */
    public function __construct($entityArray, $updateAttributes = null)
    {
        $this->entityArray = $entityArray;
        $this->updateAttributes = $updateAttributes;

        $this->lookups = [];
        $this->functions = [];
    }

    /**
     * @param $column
     * @param $value
     * @param false $compare
     * @return $this
     */
    public function add($column, $value, $compare = true)
    {
        if (!empty($this->updateAttributes) && !isset($this->updateAttributes[$column])) {
            return $this;
        }

        if ($compare && $value == $this->entityArray[$column]) {
            return $this;
        }
        
        $this->updateArray[$column] = $value;

        return $this;
    }

    /**
     * @param $column
     * @param $value
     * @param false $compare
     * @return $this
     */
    public function addFloat($column, $value, $compare = true, $scale = 4)
    {
        if (!empty($this->updateAttributes) && !isset($this->updateAttributes[$column])) {
            return $this;
        }

        if ($compare && bccomp($value, $this->entityArray[$column], $scale) == 0) {
            return $this;
        }

        $this->updateArray[$column] = $value;

        return $this;
    }

    /**
     * @param Closure $function
     * @return $this
     */
    public function addFunction(Closure $function)
    {
        $this->functions[] = $function;

        return $this;
    }

    /**
     * @param $column
     * @param $sortValue
     * @param $lookupTable
     * @return $this
     */
    public function addLookup($column, $sortValue, $lookupTable)
    {
        $this->lookups[] = new LookupModel($column, $sortValue, $lookupTable);

        return $this;
    }

    /**
     * @return mixed
     */
    public function getArray()
    {
        if (!empty($this->updateArray)) {
            $this->updateArray["modified"] = "NOW()";
            $this->updateArray["modified_by"] = "system";
        }

        return $this->updateArray;
    }

    /**
     * @param $updateArray
     * @return $this
     */
    public function setArray($updateArray)
    {
        $this->updateArray = $updateArray;

        return $this;
    }

    /**
     * @return array
     */
    public function getLookups()
    {
        return $this->lookups;
    }

    /**
     * @return array
     */
    public function getFunctions()
    {
        return $this->functions;
    }

    /**
     * @return mixed
     */
    public function getEntityId()
    {
        return $this->entityArray["id"];
    }
}
