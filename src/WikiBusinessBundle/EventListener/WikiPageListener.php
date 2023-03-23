<?php

namespace WikiBusinessBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Managers\EntityManager;
use WikiBusinessBundle\Managers\WikiManager;
use WikiBusinessBundle\Managers\WikiRouteManager;
use WikiBusinessBundle\Entity\WikiPageEntity;
use WikiBusinessBundle\Entity\WikiRouteEntity;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class WikiPageListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var WikiRouteManager $wikiRouteManager */
    protected $wikiRouteManager;
    /** @var WikiManager $wikiManager */
    protected $wikiManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityCreatedEvent $event
     */
    public function onWikiPageCreated(EntityCreatedEvent $event)
    {
        /** @var WikiPageEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "wiki_page") {

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            if (empty($this->wikiRouteManager)) {
                $this->wikiRouteManager = $this->container->get("wiki_route_manager");
            }

            /** @var WikiRouteEntity $route */
            $route = $this->wikiRouteManager->createNewRoute($entity);

            $entity->setUrl($route->getRequestUrl());
            $entity->setKeepUrl(1);
            $entity->setAutoGenerateUrl(1);

            $this->entityManager->saveEntityWithoutLog($entity);

            //$this->wikiManager->generateWikiPage($entity);
        }
    }

    /**
     * @param EntityUpdatedEvent $event
     * @return bool
     */
    public function onWikiPageUpdated(EntityUpdatedEvent $event)
    {
        /** @var WikiPageEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "wiki_page") {

            if (!$entity->getKeepUrl()) {

                if (empty($this->entityManager)) {
                    $this->entityManager = $this->container->get("entity_manager");
                }

                if (empty($this->wikiRouteManager)) {
                    $this->wikiRouteManager = $this->container->get("wiki_route_manager");
                }

                /** @var WikiRouteEntity $oldRoute */
                $oldRoute = $this->wikiRouteManager->getRouteByDestination($entity->getId(), $entity->getEntityType()->getEntityTypeCode());
                $newUrl = null;

                if (!$entity->getAutoGenerateUrl()) {
                    $newUrl = $this->wikiRouteManager->prepareUrl($entity->getUrl());
                } else {
                    if (empty($this->wikiManager)) {
                        $this->wikiManager = $this->container->get("wiki_manager");
                    }

                    $parents = $this->wikiManager->getWikiPath($entity->getId(), $entity->getEntityType());
                    foreach ($parents as $parent) {
                        if ($newUrl) {
                            $newUrl .= '/';
                        }
                        $newUrl .= $this->wikiRouteManager->prepareUrl($parent->getName());
                    }
                }

                $newRoute = null;

                if (empty($oldRoute) || $newUrl != $oldRoute->getRequestUrl()) {

                    /**
                     * Use manually generate route
                     */
                    if (!$entity->getAutoGenerateUrl()) {
                        if (strlen($entity->getUrl()) < 3) {
                            return false;
                        }
                        /** @var WikiRouteEntity $newRoute */
                        $newRoute = $this->wikiRouteManager->createNewRoute($entity, $entity->getUrl());
                    } else {
                        /** @var WikiRouteEntity $newRoute */
                        $newRoute = $this->wikiRouteManager->createNewRoute($entity);
                    }

                    $entity->setUrl($newRoute->getRequestUrl());

                    /**
                     * Trigger route update on children
                     */
                    /** @var WikiPageEntity $child */
                    foreach ($entity->getChildPages() as $child) {
                        if ($child->getEntityType()->getEntityTypeCode() == "wiki_page") {
                            $child->setKeepUrl(false);
                            $this->entityManager->saveEntity($child);
                        }
                    }
                }

                if (!empty($oldRoute)) {
                    $redirectType = $this->wikiRouteManager->getRedirectTypeByName("301");
                    $this->wikiRouteManager->setRedirectRoute($oldRoute, $newRoute, $redirectType);
                }

                $entity->setKeepUrl(1);
                $entity->setAutoGenerateUrl(1);

                $this->entityManager->saveEntityWithoutLog($entity);
            }
        }
    }

    /**
     * @param EntityDeletedEvent $event
     */
    public function onWikiPageDeleted(EntityDeletedEvent $event)
    {
        /** @var WikiPageEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "wiki_page") {

            if (empty($this->wikiManager)) {
                $this->wikiManager = $this->container->get("wiki_manager");
            }
            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }
            if (empty($this->wikiRouteManager)) {
                $this->wikiRouteManager = $this->container->get("wiki_route_manager");
            }

            /**
             * initialize array with deleted page as first entity (to delete route)
             */
            $pages = Array($entity);
            $descendants = $this->wikiManager->getDescendantsTree($entity);
            if (!empty($descendants)) {
                $pages[] = $descendants;
            }

            // delete all child pages and associated routes
            foreach ($pages as $page) {

                if ($page != $entity) { // skip first entity as it has already been deleted

                    // delete tags for this page
                    $tags = $this->wikiManager->getTagsForPage($page->getId());
                    if (!empty($tags)) {
                        foreach ($tags as $t) {
                            $this->entityManager->deleteEntity($t);
                        }
                    }

                    // delete page
                    $this->entityManager->deleteEntity($page);
                }

                // delete all routes
                $routes = $this->wikiRouteManager->getRoutesByDestination($page->getId(), $page->getEntityType()->getEntityTypeCode());
                foreach ($routes as $r) {
                    $this->wikiRouteManager->deleteRoute($r);
                }
            }
        }
    }
}