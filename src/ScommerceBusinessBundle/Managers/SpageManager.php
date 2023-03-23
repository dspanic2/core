<?php

namespace ScommerceBusinessBundle\Managers;

use CrmBusinessBundle\Entity\ProductEntity;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\SPageEntity;
use ScommerceBusinessBundle\Entity\SRouteEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;

class SpageManager extends AbstractScommerceManager
{
    /** @var RouteManager $routeManager */
    protected $routeManager;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $id
     * @return |null
     */
    public function getSpageById($id)
    {

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $entityType = $this->entityManager->getEntityTypeByCode("s_page");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }
}
