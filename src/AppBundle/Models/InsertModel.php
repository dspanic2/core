<?php

namespace AppBundle\Models;

class InsertModel
{
    protected $attributeSet;
    protected $insertAttributes;
    protected $customAttributes;
    protected $entityArray;

    protected $lookups;
    protected $functions;

    /**
     * @param $attributeSet
     * @param null $insertAttributes
     * @param null $customAttributes
     */
    public function __construct($attributeSet, $insertAttributes = null, $customAttributes = null)
    {
        $this->attributeSet = $attributeSet;
        $this->insertAttributes = $insertAttributes;
        $this->customAttributes = $customAttributes;

        $this->lookups = [];
        $this->functions = [];
    }

    /**
     * @param $column
     * @param $value
     * @return $this
     */
    public function add($column, $value)
    {
        if (!empty($this->insertAttributes) && !isset($this->insertAttributes[$column])) {
            return $this;
        }

        $this->entityArray[$column] = $value;

        return $this;
    }

    /**
     * @param $function
     * @return $this
     */
    public function addFunction($function)
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
        $this->entityArray["entity_type_id"] = $this->attributeSet->getEntityTypeId();
        $this->entityArray["attribute_set_id"] = $this->attributeSet->getId();
        $this->entityArray["created"] = $this->entityArray["modified"] = "NOW()";
        $this->entityArray["created_by"] = $this->entityArray["modified_by"] = "system";
        $this->entityArray["entity_state_id"] = 1;

        if (!empty($this->customAttributes)) {
            foreach ($this->customAttributes as $column => $value) {
                $this->entityArray[$column] = $value;
            }
        }

        return $this->entityArray;
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
}
