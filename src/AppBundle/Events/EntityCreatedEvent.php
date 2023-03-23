<?php

namespace AppBundle\Events;

use AppBundle\Abstracts\AbstractEntity;
use Symfony\Component\EventDispatcher\Event;

class EntityCreatedEvent extends Event
{

    const NAME='entity.created';

    private $entity;

    /**
     * EntityCreatedEvent constructor.
     * @param $entity
     */
    public function __construct($entity)
    {
        $this->entity = $entity;
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
}
