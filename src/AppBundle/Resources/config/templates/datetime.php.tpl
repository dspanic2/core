<?php

namespace %1$s\Entity;

/**
 * %2$sDatetime
 */
class %2$sDatetime
{
    /**
     * @var integer
     */
    private $attributeId = '0';

    /**
     * @var \DateTime
     */
    private $value = '0000-00-00 00:00:00';

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \AppBundle\Entity\EntityType
     */
    private $entityType;

    /**
     * @var \%1$s\Entity\%2$s
     */
    private $entity;

    /**
     * @var \AppBundle\Entity\Attribute
     */
    private $attribute;

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
     * @return %2$sDatetime
     */
    public function setAttributeId($attributeId)
    {
        $this->attributeId = $attributeId;

        return $this;
    }

    /**
     * Get value
     *
     * @return \DateTime
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set value
     *
     * @param \DateTime $value
     *
     * @return %2$sDatetime
     */
    public function setValue($value)
    {
        $format = $this->attribute->getBackendType() == "date" ? "d/m/Y" : "d/m/Y H:m";
        if (is_string($value)) {
            $this->value = \DateTime::createFromFormat($format, $value);
        } else
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
     * @return %2$sDatetime
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
     * @return %2$sDatetime
     */
    public function setEntity(\%1$s\Entity\%2$s $entity = null)
    {
        $this->entity = $entity;

        return $this;
    }

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
}

