<?php

namespace ScommerceBusinessBundle\Managers;

use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;

class SliderManager extends AbstractScommerceManager
{
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $id
     * @return |null
     */
    public function getSliderById($id)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("slider");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }
}
