<?php

namespace %1$s\Entity;

/**
 * %2$sInt
 */
class %2$sInt
{
    /**
     * @var integer
     */
    private $storeId = '0';

    /**
     * @var integer
     */
    private $value = '0';

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \AppBundle\Entity\Attribute
     */
    private $attribute;

    /**
     * @var integer
     */
    private $attributeId;

    /**
     * @var \AppBundle\Entity\EntityType
     */
    private $entityType;

    /**
     * @var \%1$s\Entity\%2$s
     */
    private $entity;



    /**
     * Set attributeId
     *
     * @param integer $attributeId
     *
     * @return %2$sInt
     */
    public function setAttributeId($attributeId)
    {
        $this->attributeId = $attributeId;

        return $this;
    }

    /**
     * Get attributeId
     *
     * @return integer
     */
    public function getAttributeId()
    {
        return $this->attributeId;
    }


    /**
     * Set storeId
     *
     * @param integer $storeId
     *
     * @return %2$sInt
     */
    public function setStoreId($storeId)
    {
        $this->storeId = $storeId;

        return $this;
    }

    /**
     * Get storeId
     *
     * @return integer
     */
    public function getStoreId()
    {
        return $this->storeId;
    }

    /**
     * Set value
     *
     * @param integer $value
     *
     * @return %2$sInt
     */
    public function setValue($value)
    {

        $this->value = $value;
        return $this;
    }

    /**
     * Get value
     *
     * @return integer
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set attribute
     *
     * @param \AppBundle\Entity\Attribute $attribute
     *
     * @return %2$sInt
     */
    public function setAttribute(\AppBundle\Entity\Attribute $attribute = null)
    {
        $this->attribute = $attribute;

        return $this;
    }

    /**
     * Get attribute
     *
     * @return \AppBundle\Entity\Attribute
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * Set entityType
     *
     * @param \AppBundle\Entity\EntityType $entityType
     *
     * @return %2$sInt
     */
    public function setEntityType(\AppBundle\Entity\EntityType $entityType = null)
    {
        $this->entityType = $entityType;

        return $this;
    }

    /**
     * Get entityType
     *
     * @return \AppBundle\Entity\EntityType
     */
    public function getEntityType()
    {
        return $this->entityType;
    }

    /**
     * Set entity
     *
     * @param \%1$s\Entity\%2$s $entity
     *
     * @return %2$sInt
     */
    public function setEntity(\%1$s\Entity\%2$s $entity = null)
    {
        $this->entity = $entity;

        return $this;
    }

    /**
     * Get entity
     *
     * @return \%1$s\Entity\%2$s
     */
    public function getEntity()
    {
        return $this->entity;
    }
}

