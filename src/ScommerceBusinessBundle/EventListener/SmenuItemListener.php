<?php

namespace ScommerceBusinessBundle\EventListener;

use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use ScommerceBusinessBundle\Entity\SMenuItemEntity;
use ScommerceBusinessBundle\Entity\SPageEntity;
use ScommerceBusinessBundle\Entity\SRouteEntity;
use ScommerceBusinessBundle\Managers\MenuManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SmenuItemListener implements ContainerAwareInterface
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
     * @return bool
     */
    public function onSmenuItemDeleted(EntityDeletedEvent $event)
    {
        /** @var SMenuItemEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_menu_item") {
            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            if (empty($this->menuManager)) {
                $this->menuManager = $this->container->get("menu_manager");
            }

            $entityId = $entity->getId();

            /**
             * Remove menu from page
             */
            if (!empty($entity->getPage())) {
                /** @var SPageEntity $page */
                $page = $entity->getPage();
                if ($page->getMenu()) {
                    $page->setMenu(null);
                    $this->entityManager->saveEntityWithoutLog($page);
                }
            } /**
             * Remove menu from product group
             */
            elseif (!empty($entity->getProductGroup())) {
                /** @var ProductGroupEntity $productGroup */
                $productGroup = $entity->getProductGroup();
                if ($productGroup->getProductGroup()) {
                    $productGroup->setMenu(null);
                    $this->entityManager->saveEntityWithoutLog($productGroup);
                }
            } /**
             * Delete menu from routes
             */
            elseif ($entity->getMenuItemType()->getId() == 4) {
                if (empty($this->routeManager)) {
                    $this->routeManager = $this->container->get("route_manager");
                }

                /** @var SRouteEntity $route */
                $route = $this->routeManager->getRouteByDestination($entity->getId(), $entity->getEntityType()->getEntityTypeCode(), $entity->getMenu()->getStore());

                if (!empty($route)) {

                    $notFoundRoute = $this->routeManager->getNotFoundRouteForStore($entity->getMenu()->getStore());

                    if (!empty($notFoundRoute)) {
                        $redirectType = $this->routeManager->getRedirectTypeByName("404");
                        $this->routeManager->setRedirectRoute($route, $notFoundRoute, $redirectType);
                    } else {
                        $this->routeManager->deleteRoute($route);
                    }
                }
            }

            if (!empty($entity->getChildMenuItems())) {
                $menuItems = $this->menuManager->getMenuItemsArray($entity->getMenu(), $entity->getLevel());

                /** @var SMenuItemEntity $newParent */
                $newParent = null;
                if (!empty($entity->getMenuItem())) {
                    $newParent = $entity->getMenuItem();
                }

                foreach ($menuItems as $key => $menuItem) {
                    if ($menuItem["id"] == $entity->getId()) {
                        if (isset($menuItem["children"]) && !empty($menuItem["children"])) {
                            foreach ($menuItem["children"] as $key => $child) {
                                $this->menuManager->updateMenuItem($child, $entity->getMenu(), $key, $newParent);
                            }
                        }

                        break;
                    }
                }
            }

            $this->entityManager->clearManagerByEntityType($entity->getEntityType());
            $entity = $this->menuManager->getMenuItemById($entityId);
            $this->entityManager->deleteEntityFromDatabase($entity);

            return true;
        }
    }
}
