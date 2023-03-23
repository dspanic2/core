<?php

namespace %1$s\Entity;

%5$s
use AppBundle\Entity;

/**
 * %2$sEntity
 */
class %2$s extends %6$s
{

    protected $entityTypeId;


/**
* @return mixed
*/
public function getEntityTypeId()
{
return $this->entityTypeId;
}

/**
* @param mixed $entityTypeId
*/
public function setEntityTypeId($entityTypeId)
{
$this->entityTypeId = $entityTypeId;
}

%3$s

%4$s

}



