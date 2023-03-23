<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\EntityManager;

class ProductExportRuleTypeManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }


}