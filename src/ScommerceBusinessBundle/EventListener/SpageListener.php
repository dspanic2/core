<?php

namespace ScommerceBusinessBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Managers\EntityManager;
use ScommerceBusinessBundle\Entity\SPageEntity;
use ScommerceBusinessBundle\Entity\SRouteEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\MenuManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SpageListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var MenuManager $menuManager */
    protected $menuManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityDeletedEvent $event
     */
    public function onSpageDeleted(EntityDeletedEvent $event)
    {
        /** @var SPageEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_page") {
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
                        $redirectType = $this->routeManager->getRedirectTypeByName("404");
                        $this->routeManager->setRedirectRoute($route, $notFoundRoute, $redirectType);
                    } else {
                        $this->routeManager->deleteRoute($route);
                    }
                }

                $this->menuManager->checkMenuItem($entity);
            }
            // TODO: sto ako se obrise gornja kategorija?
        }
    }
}
