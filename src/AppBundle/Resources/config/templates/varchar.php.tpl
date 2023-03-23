<?php

namespace %1$s\Entity;

/**
 * %2$sVarchar
 */
class %2$sVarchar
{
    /**
     * @var integer
     */
    private $attributeId = '0';

    /**
     * @var string
     */
    private $value;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \AppBundle\Entity\EntityType
     */
    private $entityType;
    /**
     * @var \AppBundle\Entity\Attribute
     */
    private $attribute;
    /**
     * @var \%1$s\Entity\%2$s
     */
    private $entity;

    /**
     * @return mixed
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * @param mixed $attribute
     */
    public function setAttribute($attribute)
    {
        $this->attribute = $attribute;
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
     * Set attributeId
     *
     * @param integer $attributeId
     *
     * @return %2$sVarchar
     */
    public function setAttributeId($attributeId)
    {
        $this->attributeId = $attributeId;

        return $this;
    }

    /**
     * Get value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set value
     *
     * @param string $value
     *
     * @return %2$sVarchar
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
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
     * Get entityType
     *
     * @return \AppBundle\Entity\EntityType
     */
    public function getEntityType()
    {
        return $this->entityType;
    }

    /**
     * Set entityType
     *
     * @param \AppBundle\Entity\EntityType $entityType
     *
     * @return %2$sVarchar
     */
    public function setEntityType(\AppBundle\Entity\EntityType $entityType = null)
    {
        $this->entityType = $entityType;

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

    /**
     * Set entity
     *
     * @param \%1$s\Entity\%2$s $entity
     *
     * @return %2$sVarchar
     */
    public function setEntity(\%1$s\Entity\%2$s $entity = null)
    {
        $this->entity = $entity;

        return $this;
    }
}

