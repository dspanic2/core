<?php

namespace ScommerceBusinessBundle\Abstracts;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;

abstract class AbstractScommerceManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;

    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    /** @var HelperManager $helperManager */
    protected $helperManager;

    /**
     * Override this method to initialize all services you will require
     */
    public function initialize()
    {
        parent::initialize();
        $this->helperManager = $this->container->get("helper_manager");
        $this->entityManager = $this->container->get("entity_manager");
        $this->cacheManager = $this->container->get("cache_manager");
    }
}
