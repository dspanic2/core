<?php

namespace AppBundle\Events;

use AppBundle\Abstracts\AbstractEntity;
use Symfony\Component\EventDispatcher\Event;

class EntityPreCreatedEvent extends Event
{

    const NAME='entity.precreated';

    private $entity;
    private $data;

    /**
     * EntityCreatedEvent constructor.
     * @param $entity
     * @param $data
     */
    public function __construct($entity,$data)
    {
        $this->entity = $entity;
        $this->data = $data;
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
     * @return $data
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
}
