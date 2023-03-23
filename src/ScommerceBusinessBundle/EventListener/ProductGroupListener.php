<?php

namespace ScommerceBusinessBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use RulesBusinessBundle\Providers\Events\EntityCreated;
use ScommerceBusinessBundle\Constants\ScommerceConstants;
use ScommerceBusinessBundle\Entity\SRouteEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\ElasticSearchManager;
use ScommerceBusinessBundle\Managers\MenuManager;
use ScommerceBusinessBundle\Managers\ProductGroupManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductGroupListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var MenuManager $menuManager */
    protected $menuManager;
    /** @var ProductGroupManager $productGroupManager */
    protected $productGroupManager;
    /** @var ElasticSearchManager $elasticSearchManager */
    protected $elasticSearchManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     */
    public function onProductGroupPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var ProductGroupEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product_group") {
            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            if (empty($this->productGroupManager)) {
                $this->productGroupManager = $this->container->get("product_group_manager");
            }

            $level = $this->productGroupManager->getProductGroupLevel($entity);

            $entity->setLevel($level);
        }
    }

    /**
     * @param EntityCreatedEvent $event
     */
    public function onProductGroupCreated(EntityCreatedEvent $event)
    {
        /** @var ProductGroupEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product_group") {

            if ($_ENV["USE_ELASTIC"] ?? 0) {

                if(empty($this->elasticSearchManager)){
                    $this->elasticSearchManager = $this->container->get("elastic_search_manager");
                }

                if (empty($this->routeManager)) {
                    $this->routeManager = $this->container->get("route_manager");
                }

                $stores = $this->routeManager->getStores();
                $additionalFilter = " id = {$entity->getId()} ";

                /** @var SStoreEntity $store */
                foreach ($stores as $store){
                    $this->elasticSearchManager->reindex("product_group",$store->getId(),$additionalFilter);
                }
            }
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     */
    public function onProductGroupPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var ProductGroupEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product_group") {
            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            if (empty($this->productGroupManager)) {
                $this->productGroupManager = $this->container->get("product_group_manager");
            }

            $level = $this->productGroupManager->getProductGroupLevel($entity);

            $entity->setLevel($level);
        }
    }

    /**
     * @param EntityUpdatedEvent $event
     */
    public function onProductGroupUpdated(EntityUpdatedEvent $event)
    {
        /** @var ProductGroupEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product_group") {

            if ($_ENV["USE_ELASTIC"] ?? 0) {

                if(empty($this->elasticSearchManager)){
                    $this->elasticSearchManager = $this->container->get("elastic_search_manager");
                }

                if (empty($this->routeManager)) {
                    $this->routeManager = $this->container->get("route_manager");
                }

                $stores = $this->routeManager->getStores();
                $additionalFilter = " id = {$entity->getId()} ";

                /** @var SStoreEntity $store */
                foreach ($stores as $store){
                    $this->elasticSearchManager->reindex("product_group",$store->getId(),$additionalFilter);
                }
            }
        }
    }

    /**
     * @param EntityDeletedEvent $event
     */
    public function onProductGroupDeleted(EntityDeletedEvent $event)
    {
        /** @var ProductGroupEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product_group") {
            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get("route_manager");
            }

            if (empty($this->menuManager)) {
                $this->menuManager = $this->container->get("menu_manager");
            }

            $url = $entity->getUrl();

            foreach ($url as $key => $value) {

                /** @var SStoreEntity $store */
                $store = $this->routeManager->getStoreById($key);

                /** @var SRouteEntity $route */
                $route = $this->routeManager->getRouteByDestination($entity->getId(), $entity->getEntityType()->getEntityTypeCode(), $store);
                if (!empty($route)) {
                    $notFoundRoute = $this->routeManager->getNotFoundRouteForStore($store);

                    if (!empty($notFoundRoute)) {
                        $redirectType = $this->routeManager->getRedirectTypeById(ScommerceConstants::S_REDIRECT_TYPE_404);
                        $this->routeManager->setRedirectRoute($route, $notFoundRoute, $redirectType);
                    } else {
                        $this->routeManager->deleteRoute($route);
                    }
                }

                $this->menuManager->checkMenuItem($entity);
            }

            if ($_ENV["USE_ELASTIC"] ?? 0) {

                if(empty($this->elasticSearchManager)){
                    $this->elasticSearchManager = $this->container->get("elastic_search_manager");
                }

                if (empty($this->routeManager)) {
                    $this->routeManager = $this->container->get("route_manager");
                }

                $stores = $this->routeManager->getStores();
                $additionalFilter = " id = {$entity->getId()} ";

                /** @var SStoreEntity $store */
                foreach ($stores as $store){
                    $this->elasticSearchManager->reindex("product_group",$store->getId(),$additionalFilter);
                }
            }

            // TODO: sto ako se obrise gornja kategorija?
        }
    }
}
