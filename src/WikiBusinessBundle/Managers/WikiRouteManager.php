<?php

namespace WikiBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use WikiBusinessBundle\Entity\WikiRedirectTypeEntity;
use WikiBusinessBundle\Entity\WikiRouteEntity;

class WikiRouteManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var WikiManager $wikiManager */
    protected $wikiManager;
    protected $etWikiRoute;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->helperManager = $this->getContainer()->get("helper_manager");
        $this->wikiManager = $this->getContainer()->get("wiki_manager");
    }

    /**
     * @param $url
     * @param null $avoidDestinationId
     * @param null $avoidDestinationType
     * @return |null
     */
    public function getRouteByUrl($url, $avoidDestinationId = null, $avoidDestinationType = null)
    {
        if (empty($this->etWikiRoute)) {
            $this->etWikiRoute = $this->entityManager->getEntityTypeByCode("wiki_route");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("requestUrl", "eq", $url));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $routes = $this->entityManager->getEntitiesByEntityTypeAndFilter($this->etWikiRoute, $compositeFilters);
        if (empty($routes)) {
            return null;
        }

        if (!empty($avoidDestinationId)) {
            /** @var WikiRouteEntity $route */
            foreach ($routes as $key => $route) {
                if ($route->getDestinationId() == $avoidDestinationId && $route->getDestinationType() == $avoidDestinationType) {
                    unset($routes[$key]);
                    break;
                }
            }

            if (empty($routes)) {
                return null;
            }
        }

        return $routes[0];
    }

    /**
     * @param WikiRouteEntity $routeEntity
     * @return |null
     */
    public function getDestinationByRoute(WikiRouteEntity $routeEntity)
    {
        $entityType = $this->entityManager->getEntityTypeByCode($routeEntity->getDestinationType());

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("id", "eq", $routeEntity->getDestinationId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $destinationId
     * @param $destinationType
     * @return |null
     */
    public function getRoutesByDestination($destinationId, $destinationType)
    {
        if (empty($this->etWikiRoute)) {
            $this->etWikiRoute = $this->entityManager->getEntityTypeByCode("wiki_route");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("destinationId", "eq", $destinationId));
        $compositeFilter->addFilter(new SearchFilter("destinationType", "eq", $destinationType));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($this->etWikiRoute, $compositeFilters);
    }

    /**
     * @param $destinationId
     * @param $destinationType
     * @return |null
     */
    public function getRouteByDestination($destinationId, $destinationType)
    {
        if (empty($this->etWikiRoute)) {
            $this->etWikiRoute = $this->entityManager->getEntityTypeByCode("wiki_route");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("destinationId", "eq", $destinationId));
        $compositeFilter->addFilter(new SearchFilter("destinationType", "eq", $destinationType));
        $compositeFilter->addFilter(new SearchFilter("redirectType", "nu", null));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($this->etWikiRoute, $compositeFilters);
    }

    /**
     * @param $name
     * @return false|mixed|string|string[]|null
     */
    public function prepareUrl($name)
    {
        $url = $this->helperManager->sanitizeFileName($name);
        return $this->createUrlKey($url);
    }

    /**
     * @param $entity
     * @param null $manualUrl
     * @return WikiRouteEntity
     */
    public function createNewRoute($entity, $manualUrl = null)
    {
        $name = null;

        if (!empty($manualUrl)) {
            $name = $manualUrl;
        } else {
            $parents = $this->wikiManager->getWikiPath($entity->getId(), $entity->getEntityType());
            foreach ($parents as $parent) {
                if ($name) {
                    $name .= '/';
                }
                $name .= $this->prepareUrl($parent->getName());
            }
        }

        $urlTmp = $url = $name;

        $i = 1;
        $existingRoute = $this->getRouteByUrl($urlTmp, $entity->getId(), $entity->getEntityType()->getEntityTypeCode());

        while (!empty($existingRoute)) {
            $urlTmp = $url . "-" . $i;
            $existingRoute = $this->getRouteByUrl($urlTmp, $entity->getId(), $entity->getEntityType()->getEntityTypeCode());
            $i++;
        }

        /** @var WikiRouteEntity $route */
        $route = $this->entityManager->getNewEntityByAttributSetName("wiki_route");

        $route->setRequestUrl($urlTmp);
        $route->setDestinationType($entity->getEntityType()->getEntityTypeCode());
        $route->setDestinationId($entity->getId());

        $this->entityManager->saveEntity($route);

        return $route;
    }

    /**
     * @param WikiRouteEntity $route
     * @return bool
     * Use only if necessary
     */
    public function deleteRoute(WikiRouteEntity $route)
    {
        $this->entityManager->deleteEntity($route);
        return true;
    }

    /**
     * @param $name
     * @return |null
     */
    public function getRedirectTypeByName($name)
    {
        $etWikiRedirectType = $this->entityManager->getEntityTypeByCode("wiki_redirect_type");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("name", "eq", $name));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($etWikiRedirectType, $compositeFilters);
    }

    /**
     * @param WikiRouteEntity $oldRoute
     * @param WikiRouteEntity $newRoute
     * @param WikiRedirectTypeEntity $redirectType
     * @return WikiRouteEntity
     */
    public function setRedirectRoute(WikiRouteEntity $oldRoute, WikiRouteEntity $newRoute, WikiRedirectTypeEntity $redirectType)
    {
        $oldRoute->setRedirectTo($newRoute->getRequestUrl());
        $oldRoute->setRedirectType($redirectType);

        $this->entityManager->saveEntityWithoutLog($oldRoute);

        return $oldRoute;
    }

    /**
     * @param $url
     * @return false|mixed|string|string[]|null
     */
    public function createUrlKey($url)
    {
        $url = trim($url);
        $url = mb_strtolower($url, mb_detect_encoding($url));
        $url = preg_replace('/[^A-Za-z0-9-\s]/', ' ', $url);
        $url = preg_replace('/[\s]+/', '-', $url);
        $url = preg_replace('/[-][-]+/', '-', $url);
        $url = trim($url, '-');

        return $url;
    }
}