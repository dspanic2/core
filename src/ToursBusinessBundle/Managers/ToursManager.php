<?php

namespace ToursBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Entity\UserEntity;
use AppBundle\Managers\EntityManager;
use ToursBusinessBundle\Entity\TourTipEntity;

class ToursManager extends AbstractBaseManager
{
    /**@var Logger $logger */
    protected $logger;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /**@var UserEntity $user */
    protected $user;
    protected $translator;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->getContainer()->get("entity_manager");
    }

    /**
     * @param $id
     * @return null
     */
    public function getTourById($id)
    {
        $tourEntityType = $this->entityManager->getEntityTypeByCode("tour");
        return $this->entityManager->getEntityByEntityTypeAndId($tourEntityType, $id);
    }

    /**
     * @param $id
     * @param $url
     * @return null
     */
    public function getTipsByTourAndUrl($id, $url)
    {
        $tourEntityType = $this->entityManager->getEntityTypeByCode("tour_tip");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        if (!empty($id) && $id != 0) {
            $compositeFilter->addFilter(new SearchFilter("tour.id", "eq", $id));
        }
        if (!empty($url)) {
            $compositeFilter->addFilter(new SearchFilter("url", "eq", $url));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("ord", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($tourEntityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param TourTipEntity $tip
     * @return null
     */
    public function getNextTip($tip)
    {
        $tourEntityType = $this->entityManager->getEntityTypeByCode("tour_tip");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $ord = $tip->getOrd();
        if (!empty($ord)) {
            $compositeFilter->addFilter(new SearchFilter("ord", "gt", $tip->getOrd()));
        } else {
            $compositeFilter->addFilter(new SearchFilter("id", "gt", $tip->getId()));
        }
        $compositeFilter->addFilter(new SearchFilter("tourId", "eq", $tip->getTourId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("ord", "asc"));

        return $this->entityManager->getEntityByEntityTypeAndFilter($tourEntityType, $compositeFilters, $sortFilters);
    }
}
