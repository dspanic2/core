<?php

namespace AppBundle\Events;

use AppBundle\Abstracts\AbstractEntity;
use Symfony\Component\EventDispatcher\Event;

class EntityUpdatedEvent extends Event
{

    const NAME='entity.updated';

    private $entity;
    private $data;
    private $previousValuesArray;

    /**
     * EntityUpdatedEvent constructor.
     * @param $entity
     * @param null $previousValuesArray
     */
    public function __construct($entity,$previousValuesArray = null)
    {
        $this->entity = $entity;
        $this->previousValuesArray = $previousValuesArray;
        $this->data = $previousValuesArray;
    }

    /**
     * @return AbstractEntity
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @param AbstractEntity $entity
     */
    public function setEntity($entity)
    {
        $this->entity = $entity;
    }

    /**
     * @return AbstractEntity
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * @return |null
     */
    public function getPreviousValuesArray()
    {
        return $this->previousValuesArray;
    }

    /**
     * @param $previousValuesArray
     */
    public function setPreviousValuesArray($previousValuesArray)
    {
        $this->previousValuesArray = $previousValuesArray;
    }
}
