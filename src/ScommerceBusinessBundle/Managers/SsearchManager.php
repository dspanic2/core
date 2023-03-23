<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\SProductSearchResultsEntity;
use ScommerceBusinessBundle\Entity\SSearchSynonymsEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;

class SsearchManager extends AbstractScommerceManager
{
    /**@var AttributeSet $sProductSearchResultsSet */
    protected $sProductSearchResultsSet;

    /**@var AttributeSet $sSearchSynonymsSet */
    protected $sSearchSynonymsSet;
    /** @var RouteManager $routeManager */
    protected $routeManager;

    /**
     * @param $usedQuery
     * @param $storeId
     * @return |null
     */
    public function getExistingSearchResult($usedQuery, $storeId)
    {

        if (empty($this->sProductSearchResultsSet)) {
            $this->sProductSearchResultsSet = $this->entityManager->getAttributeSetByCode("s_product_search_results");
        }

        $usedQuery = str_ireplace("'", "", $usedQuery);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("usedQuery", "eq", $usedQuery));
        $compositeFilter->addFilter(new SearchFilter("store.id", "eq", $storeId));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($this->sProductSearchResultsSet->getEntityType(), $compositeFilters);
    }

    /**
     * @param $originalQuery
     * @param $usedQuery
     * @param $storeId
     * @param SProductSearchResultsEntity|null $search
     * @return SProductSearchResultsEntity
     * @throws \Exception
     */
    public function createSearch($originalQuery, $usedQuery, $storeId, SProductSearchResultsEntity $search = null)
    {

        if (empty($search)) {
            if (empty($this->sProductSearchResultsSet)) {
                $this->sProductSearchResultsSet = $this->entityManager->getAttributeSetByCode("s_product_search_results");
            }

            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get("route_manager");
            }

            /** @var SStoreEntity $store */
            $store = $this->routeManager->getStoreById($storeId);

            /** @var SProductSearchResultsEntity $search */
            $search = new SProductSearchResultsEntity();
            $search->setAttributeSet($this->sProductSearchResultsSet);
            $search->setEntityType(($this->sProductSearchResultsSet->getEntityType()));
            $search->setCreated(new \DateTime());
            $search->setModified(new \DateTime());
            $search->setEntityStateId(1);
            $search->setOriginalQuery($originalQuery);
            $search->setUsedQuery($usedQuery);
            $search->setTimesUsed(0);
            $search->setStore($store);
        }

        $search->setLastRegenerateDate(new \DateTime());

        return $search;
    }

    /**
     * @param $entity
     */
    public function save($entity)
    {

        $this->entityManager->saveEntity($entity);
    }

    /**
     * @return mixed
     */
    public function getAllSynonyms()
    {

        $ret = array();

        if (empty($this->sSearchSynonymsSet)) {
            $this->sSearchSynonymsSet = $this->entityManager->getAttributeSetByCode("s_search_synonyms");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $data = $this->entityManager->getEntitiesByEntityTypeAndFilter($this->sSearchSynonymsSet->getEntityType(), $compositeFilters);

        if (!empty($data)) {
            /** @var SSearchSynonymsEntity $d */
            foreach ($data as $d) {
                $ret[strtolower($d->getSynonym())] = $d->getSynonymFor();
            }
        }

        return $ret;
    }

    /**
     * @return mixed
     */
    public function getAllSynonymsArray()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM s_search_synonyms_entity WHERE entity_state_id = 1 ORDER BY LENGTH(synonym) DESC;";

        return $this->databaseContext->getAll($q);
    }

    /**
     * @param $query
     * @return array|bool|string
     */
    public function prepareQuery($query)
    {

        if (empty($query)) {
            return false;
        }

        $synonyms = $this->getAllSynonymsArray();

        if (empty($synonyms)) {
            return $query;
        }

        foreach ($synonyms as $synonym) {
            if (stripos($query, $synonym["synonym"]) !== false) {

                $query = str_ireplace($synonym["synonym"], $synonym["synonym_for"], $query);

                return $query;
            }
        }

        return $query;
    }
}
