<?php

namespace AppBundle\DAL;

use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\SearchFilter;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\SearchFilterHelper;
use Doctrine\ORM\Query\Expr\Join;

class EntityDataAccess extends CoreDataAccess
{

    public function getEntitiesWithPaging(EntityType $entityType, $attributes, DataTablePager $pager)
    {

        $queryBuilder = $this->repository->createQueryBuilder('e')
            ->join('e.entityType', 'et')
            ->setFirstResult($pager->getStart())
            ->setMaxResults($pager->getLenght());

        /**@var DataTableFilter $filter*/
        if ($pager->getFilters()) {
            $conditions = array();

            /**@var SearchFilter $filter */
            foreach ($pager->getFilters() as $filter) {

                /**@var Attribute $attribute*/
                foreach ($attributes as $attribute) {
                    if ($attribute->getBackendModel()==$filter->getField()) {
                        $queryBuilder->join(
                            'AppBundle\Entity\Entity'.$attribute->getBackendType(),
                            $attribute->getBackendModel(),
                            Join::WITH,
                            $attribute->getBackendModel().'.entityType = et.id and '.$attribute->getBackendModel().'.entity = e.id '
                        );
                        array_push($conditions, SearchFilterHelper::mapOperations($filter, $attribute->getBackendModel()));
                    }
                }

                $orX = $queryBuilder->expr()->andX();
                foreach ($conditions as $condition) {
                    $orX->add($condition);
                }
                $queryBuilder->add('where', $orX);
            }
        }

        $query = $queryBuilder->getQuery();
        $entities = $query->getResult();
        return $entities;
    }
}
