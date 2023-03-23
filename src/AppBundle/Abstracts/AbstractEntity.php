<?php

namespace AppBundle\Abstracts;

use AppBundle\Interfaces\Entity\IEntityValidation;
use AppBundle\Interfaces\Entity\IFormEntityInterface;
use AppBundle\Interfaces\Entity\ITrackChanges;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Inflector\Inflector;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\DateTime;

abstract class AbstractEntity implements IFormEntityInterface, ITrackChanges
{
    /**
     * @var \DateTime
     */
    protected $created;

    /**
     * @var \DateTime
     */
    protected $modified;

    /**
     * @var array
     */
    protected $entityValidationCollection;

    /**
     * @return mixed
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param mixed $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * @return mixed
     */
    public function getMinVersion()
    {
        return $this->minVersion;
    }

    /**
     * @param mixed $minVersion
     */
    public function setMinVersion($minVersion)
    {
        $this->minVersion = $minVersion;
    }


    protected $version;


    protected $minVersion;

    /**
     * @return String
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    /**
     * @param String $createdBy
     */
    public function setCreatedBy($createdBy)
    {
        $this->createdBy = $createdBy;
    }

    /**
     * @return String
     */
    public function getModifiedBy()
    {
        return $this->modifiedBy;
    }

    /**
     * @param String $modifiedBy
     */
    public function setModifiedBy($modifiedBy)
    {
        $this->modifiedBy = $modifiedBy;
    }

    /**
     * @var \String
     */
    protected $createdBy;


    /**
     * @var \String
     */
    protected $modifiedBy;

    /**
     * @var integer
     */
    protected $id;

    /**
     * @var \AppBundle\Entity\EntityType
     */
    protected $entityType;

    /**
     * @var \AppBundle\Entity\AttributeSet
     */
    protected $attributeSet;

    protected $entityStateId;

    protected $attributes;

    /**
     * @return mixed
     */
    public function getLocked()
    {
        if(!empty($this->locked) && $this->locked->format("Y") < 0){
            return null;
        }

        return $this->locked;
    }

    /**
     * @param mixed $locked
     */
    public function setLocked($locked)
    {
        $this->locked = $locked;
    }

    /**
     * @return mixed
     */
    public function getLockedBy()
    {
        return $this->lockedBy;
    }

    /**
     * @param mixed $lockedBy
     */
    public function setLockedBy($lockedBy)
    {
        $this->lockedBy = $lockedBy;
    }

    protected $locked;

    protected $lockedBy;

    /**
     * @return mixed
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param mixed $array
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Get created
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set created
     *
     * @param \DateTime $created
     *
     * @return Entity
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get modified
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * Set modified
     *
     * @param \DateTime $modified
     *
     * @return Entity
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

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
     * Unset id
     */
    public function unsetId()
    {
        $this->id = null;
    }

    /**
     * Get entityType
     *
     * @return \AppBundle\Entity\EntityType
     */
    public function getEntityType():\AppBundle\Entity\EntityType
    {
        return $this->entityType;
    }

    /**
     * Set entityType
     *
     * @param \AppBundle\Entity\EntityType $entityType
     *
     * @return Entity
     */
    public function setEntityType(\AppBundle\Entity\EntityType $entityType = null)
    {
        $this->entityType = $entityType;

        return $this;
    }

    /**
     * Get attributeSet
     *
     * @return \AppBundle\Entity\AttributeSet
     */
    public function getAttributeSet()
    {
        return $this->attributeSet;
    }

    /**
     * Set attributeSet
     *
     * @param \AppBundle\Entity\AttributeSet $attributeSet
     *
     * @return AbstractEntity
     */
    public function setAttributeSet(\AppBundle\Entity\AttributeSet $attributeSet = null)
    {
        $this->attributeSet = $attributeSet;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getEntityLookups()
    {
        return $this->entityLookups;
    }

    /**
     * @param mixed $entityLookups
     */
    public function setEntityLookups($entityLookups)
    {
        $this->entityLookups = $entityLookups;
    }

    /**
     * @return mixed
     */
    public function getEntityStateId()
    {
        return $this->entityStateId;
    }

    /**
     * @param mixed $entityStateId
     */
    public function setEntityStateId($entityStateId)
    {
        $this->entityStateId = $entityStateId;
    }

    public function setAttribute($attribute, $value)
    {
        $this->$attribute = $value;
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

    /**
     * @return array
     */
    public function getEntityValidationCollection()
    {
        return $this->entityValidationCollection;
    }

    /**
     * @param IEntityValidation $entityValidation
     */
    public function addEntityValidation(IEntityValidation $entityValidation)
    {
        if ($this->entityValidationCollection == null) {
            $this->entityValidationCollection = array();
        }
        $this->entityValidationCollection[] = $entityValidation;
    }

    /**
     * @param $c
     * @return bool|mixed
     */
    public function isCountable($c)
    {
        if (!function_exists('is_countable')) {
            return is_array($c) || $c instanceof \Countable;
        }
        return is_countable($c);
    }
}
