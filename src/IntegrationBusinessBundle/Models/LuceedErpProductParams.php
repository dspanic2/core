<?php

namespace IntegrationBusinessBundle\Models;

class LuceedErpProductParams
{
    private $brandFieldName;
    private $useProductGroups;
    private $useProductDocuments;
    private $deleteUnusedAttributeLinks;
    private $deleteUnusedProductProductGroupLinks;
    private $taxTypesForPriceReturn;
    private $attributes;
    private $skipProductGroupLevels;

    public function __construct()
    {
        $this->brandFieldName = "robna_marka_naziv";
        $this->useProductGroups = false;
        $this->useProductDocuments = false;
        $this->deleteUnusedAttributeLinks = false;
        $this->deleteUnusedProductProductGroupLinks = false;
        $this->taxTypesForPriceReturn = [];
        $this->attributes = [];
        $this->skipProductGroupLevels = [];
    }

    /**
     * @return mixed
     */
    public function getBrandFieldName()
    {
        return $this->brandFieldName;
    }

    /**
     * @param mixed $brandFieldName
     * @return LuceedErpProductParams
     */
    public function setBrandFieldName($brandFieldName)
    {
        $this->brandFieldName = $brandFieldName;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getUseProductGroups()
    {
        return $this->useProductGroups;
    }

    /**
     * @param $useProductGroups
     * @return LuceedErpProductParams
     */
    public function setUseProductGroups($useProductGroups)
    {
        $this->useProductGroups = $useProductGroups;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getUseProductDocuments()
    {
        return $this->useProductDocuments;
    }

    /**
     * @param $useProductDocuments
     * @return LuceedErpProductParams
     */
    public function setUseProductDocuments($useProductDocuments)
    {
        $this->useProductDocuments = $useProductDocuments;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDeleteUnusedAttributeLinks()
    {
        return $this->deleteUnusedAttributeLinks;
    }

    /**
     * @param $deleteUnusedAttributeLinks
     * @return LuceedErpProductParams
     */
    public function setDeleteUnusedAttributeLinks($deleteUnusedAttributeLinks)
    {
        $this->deleteUnusedAttributeLinks = $deleteUnusedAttributeLinks;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDeleteUnusedProductProductGroupLinks()
    {
        return $this->deleteUnusedProductProductGroupLinks;
    }

    /**
     * @param $deleteUnusedProductProductGroupLinks
     * @return LuceedErpProductParams
     */
    public function setDeleteUnusedProductProductGroupLinks($deleteUnusedProductProductGroupLinks)
    {
        $this->deleteUnusedProductProductGroupLinks = $deleteUnusedProductProductGroupLinks;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTaxTypesForPriceReturn()
    {
        return $this->taxTypesForPriceReturn;
    }

    /**
     * @param mixed $taxTypesForPriceReturn
     * @return LuceedErpProductParams
     */
    public function setTaxTypesForPriceReturn($taxTypesForPriceReturn)
    {
        $this->taxTypesForPriceReturn = $taxTypesForPriceReturn;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param mixed $attributes
     * @return LuceedErpProductParams
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSkipProductGroupLevels()
    {
        return $this->skipProductGroupLevels;
    }

    /**
     * @param mixed $skipProductGroupLevels
     * @return LuceedErpProductParams
     */
    public function setSkipProductGroupLevels($skipProductGroupLevels)
    {
        $this->skipProductGroupLevels = $skipProductGroupLevels;

        return $this;
    }
}
