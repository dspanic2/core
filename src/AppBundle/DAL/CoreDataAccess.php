<?php

namespace AppBundle\DAL;

use AppBundle\Entity\CompositeFilter;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\PagingFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SearchFilterHelper;
use AppBundle\Entity\EntityLevelPermission;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Entity\UserEntity;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use AppBundle\ORM\OrmEntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Validator\Constraints\IsFalse;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Doctrine\DBAL\Query\Expression\CompositeExpression;

class CoreDataAccess extends BaseDataAccess
{
    protected $repository;
    protected $entityManager;
    protected $validator;
    /**@var EntityType $entityType */
    protected $entityType;
    /** @var UserEntity $user */
    protected $user;

    /**@var Registry $doctrine */
    protected $doctrine;


    public function setEntityRepository(EntityRepository $repository)
    {
        $this->repository = $repository;
    }

    public function setDoctrine(Registry $doctrine)
    {
        $this->doctrine = $doctrine;
    }


    public function setEntityType(EntityType $entityType)
    {
        $this->entityType = $entityType;
    }

    public function setUser(UserEntity $user)
    {
        $this->user = $user;
    }

    public function overrideRepository($bundle, $entityModel)
    {
        $this->setEntityRepository($this->getEntityManager()->getRepository($bundle . ":" . $entityModel));
    }

    /*public function setEntityManager(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }*/

    /*public function getEntityManager()
    {
        return $this->entityManager;
    }*/

    public function setValidator($validator)
    {
        $this->validator = $validator;
    }

    public function refresh($entity)
    {
        $this->entityManager->refresh($entity);
    }

    public function getAll()
    {
        $items = $this->repository->findAll();
        return $items;
    }

    public function getById($id)
    {
        $item = $this->repository->findOneById($id);
        return $item;
    }

    public function getEntityById($id)
    {

        $queryBuilder = $this->repository->createQueryBuilder('i');
        $queryBuilder->where(('i.id = :iid'));
        $queryBuilder->andWhere(('i.entityStateId = 1'));
        $queryBuilder->setParameter('iid', $id);


        if ($this->entityType->getHasUniquePermissions()) {
            $roles = array();
            foreach ($this->user->getUserRoles() as $role) {
                $roles[] = $role->getRoleId();
            }

            $roleFilter = implode(",", $roles);

            $queryBuilder->andWhere(
                $queryBuilder->expr()->in(
                    'i.id',
                    $this->getEntityManager()->createQueryBuilder()
                        ->select('d.entityId')
                        ->from('AppBundle\Entity\EntityLevelPermission', 'd')
                        ->andWhere('d.roleId  in (' . $roleFilter . ')')
                        ->andWhere('d.entityTypeId = ' . $this->entityType->getId())
                        ->getDQL()
                )
            );
        }
        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    /**
     * @param $ids
     * @return mixed
     * Accepts an array of ids
     */
    public function findByIds($ids)
    {
        $items = $this->repository->findById($ids);
        return $items;
    }

    public function getBy($filter, $order)
    {
        $items = $this->repository->findBy($filter, $order);
        return $items;
    }

    public function whereFieldContains($field, $term)
    {

        $queryBuilder = $this->repository->createQueryBuilder('i');
        $queryBuilder->where(('i.' . $field . ' like :term'));
        $queryBuilder->andWhere(('i.entityStateId = 1'));
        $queryBuilder->setParameter('term', '%' . $term . '%');


        if ($this->entityType->getHasUniquePermissions()) {
            $roles = array();
            foreach ($this->user->getUserRoles() as $role) {
                $roles[] = $role->getRoleId();
            }

            $roleFilter = implode(",", $roles);

            $queryBuilder->andWhere(
                $queryBuilder->expr()->in(
                    'i.id',
                    $this->getEntityManager()->createQueryBuilder()
                        ->select('d.entityId')
                        ->from('AppBundle\Entity\EntityLevelPermission', 'd')
                        ->andWhere('d.roleId  in (' . $roleFilter . ')')
                        ->andWhere('d.entityTypeId = ' . $this->entityType->getId())
                        ->getDQL()
                )
            );
        }


        return $queryBuilder->getQuery()->getResult();
    }

    public function getItemsWithFilter(CompositeFilterCollection $compositeFilters, SortFilterCollection $sortFilters = null, PagingFilter $pagingFilter = null, $customJoins = null)
    {
        $queryBuilder = $this->repository->createQueryBuilder('i');
        $primaryExpression = new CompositeExpression(CompositeExpression::TYPE_AND);
        $primaryExpression = $this->fillExpressionFromCompositeFilters($primaryExpression, $compositeFilters);
        $this->setUniquePermissionsFilter($primaryExpression, $queryBuilder);

        if ($sortFilters != null) {
            foreach ($sortFilters->getCollection() as $sortFilter) {
                $queryBuilder->addOrderBy($sortFilter->getField(), $sortFilter->getDirection());
            }
        }

        $joins = $this->getFilterJoins($compositeFilters);

        if ($sortFilters != null) {
            $joins = array_unique(array_merge($joins, $this->getSortJoins($sortFilters)));
        }

        $dump = false;

        foreach ($joins as $join) {


            /** sp_ fix for doctrine keywords */
            if (strpos($join, '-') == false) {
                if (strpos($join, 'sp_') === false) {
                    $queryBuilder->leftJoin('i.' . $join, 'sp_' . $join);
                } else {
                    $num = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9);
                    $joinItem = str_replace($num, "", $join);
                    $joinItem = str_replace('sp_', "", $joinItem);

                    $queryBuilder->leftJoin('i.' . $joinItem, $join);
                }
            } else {
                $childs = explode('-', $join);
                if (strpos('sp_', $childs[0]) === false) {
                    $queryBuilder->leftJoin('sp_' . $childs[0] . '.' . $childs[1], 'sp_' . $childs[1]);
                } else {
                    $queryBuilder->leftJoin(preg_replace('/\d+/', '', $childs[0]) . '.' . $childs[1], 'sp_' . $childs[1]);
                }
            }
        }

        /*       if($dump)
               {
                   dump($joins);die;
               }*/


        $queryBuilder->add('where', $primaryExpression);


        if ($pagingFilter != null) {
            $queryBuilder->setFirstResult($pagingFilter->getPageNumber() * $pagingFilter->getPageSize());
            $queryBuilder->setMaxResults($pagingFilter->getPageSize());
        }

        $query = $queryBuilder->getQuery();

        /*if($dump){
            dump($query);
            die;
        }*/

        $items = $query->getResult();
        return $items;
    }

    public function getOneBy($filter, $order)
    {
        $items = $this->repository->findBy($filter, $order);

        if (!empty($items)) {
            return $items[0];
        }
        return null;
    }

    public function save($item)
    {
        $this->getEntityManager()->persist($item);
        $this->getEntityManager()->flush();


        return $item;
    }

    public function saveArray($items)
    {
        foreach ($items as $item) {
            $this->getEntityManager()->persist($item);
        }
        $this->getEntityManager()->flush();

        return true;
    }

    public function detach($item)
    {
        $this->getEntityManager()->detach($item);
    }

    public function remove($item)
    {
        $this->getEntityManager()->remove($item);
    }

    public function clearManager()
    {
        $class = $this->entityType->getBundle() . '\\Entity\\' . $this->entityType->getEntityModel();
        $this->getEntityManager()->clear($class);
    }

    public function validate($item)
    {
        return $this->validator->validate($item);
    }

    public function delete($item)
    {
        $this->getEntityManager()->remove($item);
        $this->getEntityManager()->flush();
    }

    public function deleteArray($items)
    {
        foreach ($items as $item) {
            $this->getEntityManager()->remove($item);
        }
        $this->getEntityManager()->flush();

        return true;
    }

    public function countAllItems()
    {
        $query = $this->repository->createQueryBuilder('i')
            ->select('count(i.id)')
            ->getQuery();

        $count = $query->getSingleScalarResult();
        return $count;
    }

    public function getAllItems()
    {
        $query = $this->repository->createQueryBuilder('i')
            ->getQuery();

        $count = $query->getResult();
        return $count;
    }

    public function getItemsWithPaging(DataTablePager $pager)
    {
        $queryBuilder = $this->repository->createQueryBuilder('i');
        $queryBuilder = $this->setQueryBuilderPage($queryBuilder, $pager);
        $queryBuilder = $this->mapPagerToQueryBuilder($queryBuilder, $pager);

        $query = $queryBuilder->getQuery();

        //dump($query);die;
        $items = $query->getResult();
        return $items;
    }

    public function countFilteredItems(DataTablePager $pager)
    {
        $queryBuilder = $this->repository->createQueryBuilder('i');
        $queryBuilder = $this->mapPagerToQueryBuilder($queryBuilder, $pager);
        $query = $queryBuilder->select('count(i.id)')->getQuery();
        $count = $query->getSingleScalarResult();
        return $count;
    }

    public function groupByCount($groupBy)
    {
        $queryBuilder = $this->repository->createQueryBuilder('i');
        $query = $queryBuilder->select('count(i.id),i.' . $groupBy)->groupBy('i.' . $groupBy)->getQuery();
        $count = $query->getResult();
        return $count;
    }

    public function setQueryBuilderPage(\Doctrine\ORM\QueryBuilder $queryBuilder, DataTablePager $pager)
    {
        $queryBuilder->setFirstResult($pager->getStart());
        $queryBuilder->setMaxResults($pager->getLenght());
        return $queryBuilder;
    }

    public function mapPagerToQueryBuilder(\Doctrine\ORM\QueryBuilder $queryBuilder, DataTablePager $pager)
    {
        $joins = array();

        $primaryExpression = new CompositeExpression(CompositeExpression::TYPE_AND);
        $primaryExpression = $this->fillExpressionFromCompositeFilters($primaryExpression, $pager->getCompositeFilterCollection());

        $sortFilterCollection = $pager->getSortFilterCollection();

        if ($sortFilterCollection != null) {
            foreach ($sortFilterCollection->getCollection() as $sortFilter) {
                $queryBuilder->addOrderBy($sortFilter->getField(), $sortFilter->getDirection());
            }
        }

        if ($primaryExpression->count() > 0) {
            $queryBuilder->add('where', $primaryExpression);
        }

        $this->setUniquePermissionsFilter($primaryExpression, $queryBuilder);

        $joins = array_unique(array_merge($joins, $this->getFilterJoins($pager->getCompositeFilterCollection())));
        $joins = array_unique(array_merge($joins, $this->getSortJoins($pager->getSortFilterCollection())));


        foreach ($joins as $join) {
            /** sp_ fix for doctrine keywords */
            if (strpos($join, '-') == false) {
                $queryBuilder->leftJoin('i.' . $join, 'sp_' . $join);
            } else {
                $childs = explode('-', $join);
                $queryBuilder->leftJoin('sp_' . $childs[0] . '.' . $childs[1], 'sp_' . $childs[1]);
            }
        }

        //dump($queryBuilder->getQuery());die;

        return $queryBuilder;
    }

    protected function fillExpressionFromCompositeFilters(CompositeExpression $primaryExpression, CompositeFilterCollection $compositeFilters)
    {
        if ($compositeFilters->getCollection() != null) {
            foreach ($compositeFilters->getCollection() as $filter) {
                if ($filter->getFilters() != null) {
                    $primaryExpression->add($filter->getExpression());
                }
            }
        }

        return $primaryExpression;
    }

    protected function setUniquePermissionsFilter(CompositeExpression $expression, \Doctrine\ORM\QueryBuilder $queryBuilder)
    {
        if ($this->entityType != null) {
            if ($this->entityType->getHasUniquePermissions()) {
                $roles = array();
                foreach ($this->user->getUserRoles() as $role) {
                    /**
                     * Override for admin
                     */
                    if ($role->getRoleId() == 1) {
                        return true;
                    }
                    $roles[] = $role->getRoleId();
                }

                $roleFilter = implode(",", $roles);

                $expression->add(
                    $queryBuilder->expr()->in(
                        'i.id',
                        $this->getEntityManager()->createQueryBuilder()
                            ->select('d.entityId')
                            ->from('AppBundle\Entity\EntityLevelPermission', 'd')
                            ->andWhere('d.roleId  in (' . $roleFilter . ')')
                            ->andWhere('d.entityTypeId = ' . $this->entityType->getId())
                            ->getDQL()
                    )
                );
            }
        }

        return true;
    }

    protected function getFilterJoins(CompositeFilterCollection $compositeFilters)
    {
        $joins = array();
        if ($compositeFilters->getCollection() != null) {
            foreach ($compositeFilters->getCollection() as $filter) {
                if ($filter instanceof CompositeFilter) {
                    $joins = array_unique(array_merge($joins, $filter->getJoinTables()));
                }
            }
        }
        return $joins;
    }

    protected function getSortJoins(SortFilterCollection $sortFilters)
    {
        $joins = array();
        foreach ($sortFilters->getCollection() as $sort) {
            $joins = array_unique(array_merge($joins, $sort->getJoinTables()));
        }
        return $joins;
    }

    public function executeQuery($query)
    {
        $query = $this->getConnection()->prepare($query);
        $query->execute();
        $results = $query->fetchAll();
        return $results;
    }

    public function resetEntityManager()
    {
        $this->setEntityManager($this->doctrine->resetManager());
        //= $this->doctrine->resetManager();
    }

    /**
     * @param $alias
     * @param $criteria
     * @param null $orderBy
     * @param bool $singleResult
     * @return mixed
     */
    function findBy($alias, $criteria, $orderBy = null, $singleResult = false){

        $queryBuilder = $this->repository->createQueryBuilder($alias);
        foreach($criteria as $field => $value) {
            if($value == null){
                $queryBuilder->andWhere($alias . ".{$field} is null");
            }
            else{
                $queryBuilder->andWhere($alias . ".{$field} = :{$field}")->setParameter($field, $value);
            }
        }
        if (is_array($orderBy)) {
            foreach ($orderBy as $field => $dir) {
                $queryBuilder->addOrderBy($alias .".".$field, $dir);
            }
        }
        if($singleResult){
            $queryBuilder->setMaxResults(1);
        }

        if(isset($_ENV["USE_BACKEND_CACHE"]) && !empty($_ENV["USE_BACKEND_CACHE"]) && $_ENV["USE_BACKEND_CACHE"] == "redis"){
            $query = $queryBuilder->getQuery()->setCacheable(true);
        }
        else{
            $query = $queryBuilder->getQuery();
        }

        //$query->useResultCache(self::CACHE_KEY);

        $result = $query->getResult();

        if ($result && $singleResult) return reset($result);

        return $result;
    }
}
