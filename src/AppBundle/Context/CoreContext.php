<?php

namespace AppBundle\Context;

use AppBundle\DAL\CoreDataAccess;
use AppBundle\DAL\EntityTypeDataAccess;
use AppBundle\DataTable\CompositeFilter;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\PagingFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Entity\UserEntity;
use Doctrine\ORM\EntityRepository;

class CoreContext
{
    protected $dataAccess;
    protected $user;

    public function __construct(CoreDataAccess $dataAccess)
    {
        $this->dataAccess = $dataAccess;
    }

    public function setRepository($bundle, $entityModel)
    {
        $this->dataAccess->overrideRepository($bundle, $entityModel);
    }

    public function setEntityType(EntityType $entityType)
    {
        $this->dataAccess->setEntityType($entityType);
    }

    public function setUser(UserEntity $user)
    {
        $this->user = $user;
        $this->dataAccess->setUser($user);
    }

    public function getAll()
    {
        return $this->dataAccess->getAll();
    }

    public function getById($id)
    {
        return $this->dataAccess->getById($id);
    }

    public function getEntityById($id)
    {
        return $this->dataAccess->getEntityById($id);
    }

    public function getBy($filter, $order = array())
    {
        return $this->dataAccess->getBy($filter, $order);
    }

    public function whereFieldContains($field, $term)
    {
        return $this->dataAccess->whereFieldContains($field, $term);
    }

    public function getOneBy($filter, $order = array())
    {
        return $this->dataAccess->getOneBy($filter, $order);
    }

    public function refresh($entity)
    {
        $this->dataAccess->refresh($entity);
    }

    public function save($item)
    {
        $dateTime = new \DateTime();

        if (property_exists(get_class($item), "created") and $item->getId() == "") {
            $item->setCreated($dateTime);
        }
        if (property_exists(get_class($item), "modified")) {
            $item->setModified($dateTime);
        }

        return $this->dataAccess->save($item);
    }

    public function saveArray($items)
    {
        $dateTime = new \DateTime();

        foreach ($items as $item) {
            if (property_exists(get_class($item), "created") and $item->getId() == "") {
                $item->setCreated($dateTime);
            }
            if (property_exists(get_class($item), "modified")) {
                $item->setModified($dateTime);
            }
        }

        return $this->dataAccess->saveArray($items);
    }

    public function validate($item)
    {
        return $this->dataAccess->validate($item);
    }

    public function deleteArray($items)
    {
        return $this->dataAccess->deleteArray($items);
    }

    public function delete($item)
    {
        $this->dataAccess->delete($item);
    }

    public function getItemsWithPaging(DataTablePager $pager)
    {
        return $this->dataAccess->getItemsWithPaging($pager);
    }

    public function getItemsWithFilter(CompositeFilterCollection $filters, SortFilterCollection $sortFilters = null, PagingFilter $pagingFilter = null, $cusotmJoins = null)
    {
        return $this->dataAccess->getItemsWithFilter($filters, $sortFilters, $pagingFilter, $cusotmJoins);
    }

    public function getAllItems()
    {
        return $this->dataAccess->getAllItems();
    }

    public function groupByCount($groupBy)
    {
        return $this->dataAccess->groupByCount($groupBy);
    }

    public function countFilteredItems(DataTablePager $pager)
    {
        return $this->dataAccess->countFilteredItems($pager);
    }

    public function countAllItems()
    {
        return $this->dataAccess->countAllItems();
    }

    public function clearManager()
    {
        return $this->dataAccess->clearManager();
    }

    public function detach($entity)
    {
        $this->dataAccess->detach($entity);
    }

    public function remove($entity)
    {
        $this->dataAccess->remove($entity);
    }

    public function resetEntityManager()
    {
        $this->dataAccess->resetEntityManager();
    }

}
