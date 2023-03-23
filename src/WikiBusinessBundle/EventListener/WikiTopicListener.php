<?php

namespace WikiBusinessBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Managers\EntityManager;
use WikiBusinessBundle\Entity\WikiPageEntity;
use WikiBusinessBundle\Entity\WikiTopicEntity;
use WikiBusinessBundle\Managers\WikiManager;
use WikiBusinessBundle\Managers\WikiRouteManager;
use WikiBusinessBundle\Entity\WikiRouteEntity;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class WikiTopicListener implements ContainerAwareInterface
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
    public function onWikiTopicCreated(EntityCreatedEvent $event)
    {
        /** @var WikiTopicEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "wiki_topic") {

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
        }
    }

    /**
     * @param EntityUpdatedEvent $event
     * @return bool
     */
    public function onWikiTopicUpdated(EntityUpdatedEvent $event)
    {
        /** @var WikiTopicEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "wiki_topic") {

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
                     * Trigger route update on first level children
                     */
                    /** @var WikiPageEntity $child */
                    foreach ($entity->getChildPages() as $child) {
                        if ($child->getEntityType()->getEntityTypeCode() == "wiki_page" &&
                            $child->getParentPage() == null) {
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
    public function onWikiTopicDeleted(EntityDeletedEvent $event)
    {
        /** @var WikiTopicEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "wiki_topic") {

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }
            if (empty($this->wikiRouteManager)) {
                $this->wikiRouteManager = $this->container->get("wiki_route_manager");
            }
            if (empty($this->wikiManager)) {
                $this->wikiManager = $this->container->get("wiki_manager");
            }

            $entities = array($entity); // initialize array with deleted topic as first entity (to delete route)

            $childPages = $entity->getChildPages();
            foreach ($childPages as $child) {
                $entities[] = $child;
            }

            // delete all child pages and associated routes
            foreach ($entities as $e) {

                if ($e != $entity) { // skip first entity as it has already been deleted

                    // delete tags for this page
                    $tags = $this->wikiManager->getTagsForPage($e->getId());
                    if (!empty($tags)) {
                        foreach ($tags as $t) {
                            $this->entityManager->deleteEntity($t);
                        }
                    }

                    // delete page
                    $this->entityManager->deleteEntity($e);
                }

                // delete all routes
                $routes = $this->wikiRouteManager->getRoutesByDestination($e->getId(), $e->getEntityType()->getEntityTypeCode());
                foreach ($routes as $r) {
                    $this->wikiRouteManager->deleteRoute($r);
                }
            }
        }
    }
}