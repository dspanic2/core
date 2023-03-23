<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\FaqEntity;

class FaqManager extends AbstractScommerceManager
{
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $id
     * @return |null
     */
    public function getFaqById($id)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("faq");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param $storeId
     * @param $entity
     * @return mixed|null
     */
    public function getFaqByRelatedEntityTypeAndId($storeId, $entity)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("faq");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("showOnStore", "json_contains", json_encode(array(1, '$."' . $storeId . '"'))));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if ($entity->getEntityType()->getEntityTypeCode() == "s_page") {
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("pagesId.id", "in", $entity->getId()));
            $compositeFilters->addCompositeFilter($compositeFilter);
        } elseif ($entity->getEntityType()->getEntityTypeCode() == "product") {
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("or");
            //$compositeFilter->addFilter(new SearchFilter("products.id", "in", $entity->getId()));
            $compositeFilter->addFilter(new SearchFilter("showOnAllProducts", "eq", 1));
            $compositeFilters->addCompositeFilter($compositeFilter);
        } else {
            return null;
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("ord", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param FaqEntity $entity
     * @param bool $isUpdate
     * @return FaqEntity
     */
    public function insertUpdateFaqLanguages(FaqEntity $entity, $isUpdate = false)
    {

        $stores = $entity->getShowOnStore();
        $hasChanges = false;

        $name = $entity->getName();

        $baseName = null;
        foreach ($name as $n) {
            if (!empty($n)) {
                $baseName = $n;
                break;
            }
        }

        foreach ($stores as $key => $value) {
            if ($value && (!isset($name[$key]) || empty($name[$key]))) {
                $name[$key] = $baseName;
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $entity->setName($name);

            $this->entityManager->saveEntityWithoutLog($entity);
            $this->entityManager->refreshEntity($entity);
        }

        return $entity;
    }
}
