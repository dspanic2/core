<?php

namespace AppBundle\Events;

use AppBundle\Abstracts\AbstractEntity;
use Symfony\Component\EventDispatcher\Event;

class EntityPreSetUpdatedEvent extends Event
{

    const NAME='entity.presetupdated';

    private $entity;
    private $data;

    /**
     * EntityPreSetUpdatedEvent constructor.
     * @param $entity
     * @param $data
     */
    public function __construct($entity, $data)
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
