<?php

namespace ProjectManagementBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Managers\EntityManager;
use Exception;

class ProjectManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /**@var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
        $this->attributeSetContext = $this->container->get("attribute_set_context");
    }

    /**
     * @param $id
     * @return |null
     */
    public function getProjectById($id)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("project");
        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param $additionalCompositeFilter
     * @return mixed
     */
    public function getFilteredProjects($additionalCompositeFilter = null)
    {
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalCompositeFilter)) {
            $compositeFilters->addCompositeFilter($additionalCompositeFilter);
        }

        $sortFilter = new SortFilter();
        $sortFilter->setField("name");
        $sortFilter->setDirection("asc");

        $sortFilterCollection = new SortFilterCollection();
        $sortFilterCollection->addSortFilter($sortFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($this->entityManager->getEntityTypeByCode('project'), $compositeFilters, $sortFilterCollection);
    }

    /**
     * Returns tasks for project by project id
     *
     * @param $project_id
     * @return array
     * @throws Exception
     */
    public function getProjectTasks($project_id): array
    {
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("project.id", "eq", $project_id));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilter = new SortFilter();
        $sortFilter->setField("id");
        $sortFilter->setDirection("desc");
        $sortFilterCollection = new SortFilterCollection();
        $sortFilterCollection->addSortFilter($sortFilter);

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $tasks = $this->entityManager->getEntitiesByEntityTypeAndFilter(
            $this->entityManager->getEntityTypeByCode('task'),
            $compositeFilters, $sortFilterCollection
        );

        if (!$tasks) {
            return [];
        } else
            return $tasks;
    }

    /**
     * @return mixed
     */
    public function getProjectStatuses(){

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter(
            $this->entityManager->getEntityTypeByCode('project_status'),
            $compositeFilters
        );
    }

    /**
     * Returns  project stages by project id
     *
     * @param $project_id
     * @return array
     * @throws Exception
     */
    public function getProjectStages($project_id): array
    {
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("project.id", "eq", $project_id));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilter = new SortFilter();
        $sortFilter->setField("id");
        $sortFilter->setDirection("desc");
        $sortFilterCollection = new SortFilterCollection();
        $sortFilterCollection->addSortFilter($sortFilter);


        $dependencies = $this->entityManager->getEntitiesByEntityTypeAndFilter(
            $this->entityManager->getEntityTypeByCode('project_stage'),
            $compositeFilters, $sortFilterCollection
        );

        if (!$dependencies) {
            return [];
        } else
            return $dependencies;
    }


    /**
     * Returns dependencies for project by project id
     *
     * @param $project_id
     * @return array
     * @throws Exception
     */
    public function getProjectDependencies($project_id): array
    {
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("stage.project.id", "eq", $project_id));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilter = new SortFilter();
        $sortFilter->setField("id");
        $sortFilter->setDirection("desc");
        $sortFilterCollection = new SortFilterCollection();
        $sortFilterCollection->addSortFilter($sortFilter);


        $dependencies = $this->entityManager->getEntitiesByEntityTypeAndFilter(
            $this->entityManager->getEntityTypeByCode('project_stage_dependency'),
            $compositeFilters, $sortFilterCollection
        );

        if (!$dependencies) {
            return [];
        } else
            return $dependencies;
    }

    /**
     * Returns workpackages for project by project id
     *
     * @param $project_id
     * @return array
     * @throws Exception
     */
    public function getWorkpackages($project_id): array
    {
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("project.id", "eq", $project_id));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilter = new SortFilter();
        $sortFilter->setField("id");
        $sortFilter->setDirection("desc");
        $sortFilterCollection = new SortFilterCollection();
        $sortFilterCollection->addSortFilter($sortFilter);


        $packages = $this->entityManager->getEntitiesByEntityTypeAndFilter(
            $this->entityManager->getEntityTypeByCode('workpackage'),
            $compositeFilters, $sortFilterCollection
        );

        if (!$packages) {
            return [];
        } else
            return $packages;
    }

    /**
     * Toggles completed status for project activity
     */
    public function changeCompletedById($id)
    {
        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->attributeSetContext->getItemByCode("project_activity");

        /** @var ProjectActivityEntity $entity */
        $entity = $this->entityManager->getEntityByEntityTypeAndId($attributeSet->getEntityType(), $id);
        $entity->setIsCompleted(!$entity->getIsCompleted());

        if ($entity->getIsCompleted())
            $entity->setCompletionPercent(100);
        else
            $entity->setCompletionPercent(0);

        $this->entityManager->saveEntity($entity);

        return $entity;
    }


}
