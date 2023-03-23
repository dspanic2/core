<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\EntityManager;
use ScommerceBusinessBundle\Entity\StaticContentEntity;

class StaticContentManager extends AbstractBaseManager
{

    /**
     * @param $code
     * @return string|null
     */
    public function getRawStaticContentEntityByCode($code)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get("entity_manager");

        /** @var Session $session */
        $session = $this->container->get("session");
        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $staticContentEntityType = $entityManager->getEntityTypeByCode("static_content");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $code));
//        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $entityManager->getEntityByEntityTypeAndFilter($staticContentEntityType, $compositeFilters);
    }

    /**
     * @param $code
     * @return string|null
     */
    private function getRawStaticContentByCode($code)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get("entity_manager");

        /** @var Session $session */
        $session = $this->container->get("session");
        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        $staticContentEntityType = $entityManager->getEntityTypeByCode("static_content");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $code));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var StaticContentEntity $staticContent */
        $staticContent = $entityManager->getEntityByEntityTypeAndFilter($staticContentEntityType, $compositeFilters);

        if (empty($staticContent)) {
            return null;
        }
        return $staticContent->getValue();
    }

    /**
     * @param $code
     * @return string|null
     */
    public function getStaticContentByCode($code)
    {
        /** @var CacheManager $cacheManager */
        $cacheManager = $this->container->get("cache_manager");
        $value = $cacheManager->getCacheItem("static_content.{$code}");

        if (!$value || empty($value)) {
            $value = $this->getRawStaticContentByCode($code);
            $cacheManager->setCacheItem("static_content.{$code}", $value);
            return $value;
        } else {
            return $value;
        }
    }
}
