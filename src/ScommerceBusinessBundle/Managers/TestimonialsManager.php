<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;

class TestimonialsManager extends AbstractScommerceManager
{

    /**
     * @param $id
     * @return |null
     */
    public function getTestimonialById($id)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("testimonials");
        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @return mixed
     */
    public function getTestimonials()
    {

        $entityType = $this->entityManager->getEntityTypeByCode("testimonials");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("active", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("ord", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }
}
