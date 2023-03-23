<?php

namespace AppBundle\Events;

use Symfony\Component\EventDispatcher\Event;

class CalendarDragAndDropEvent extends Event
{

    const NAME = 'calendardraganddrop';

    private $entity;
    private $isValid;

    /**
     * @param $entity
     */
    public function __construct($entity)
    {
        $this->entity = $entity;
        $this->isValid = true;
    }

    public function getEntity()
    {
        return $this->entity;
    }

    public function setEntity($entity)
    {
        $this->entity = $entity;
    }

    public function setIsValid($isValid)
    {
        $this->isValid = $isValid;
    }

    public function getIsValid()
    {
        return $this->isValid;
    }
}
