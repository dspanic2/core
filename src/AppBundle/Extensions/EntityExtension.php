<?php

namespace AppBundle\Extensions;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /** @var EntityManager */
    protected $entityManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_entity_by_type_and_id', array($this, 'getEntityByTypeAndId')),
            new \Twig_SimpleFunction('get_entities_by_type', array($this, 'getEntitiesByType')),
            new \Twig_SimpleFunction('get_entity_notes', array($this, 'getEntityNotes')),
        ];
    }

    /**
     * @param $entityTypeCode
     * @param $id
     * @return bool|string|null
     */
    public function getEntityByTypeAndId($entityTypeCode, $id)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $et = $this->entityManager->getEntityTypeByCode($entityTypeCode);

        return $this->entityManager->getEntityByEntityTypeAndId($et, $id);
    }

    /**
     * @param $entityTypeCode
     * @return array
     */
    public function getEntitiesByType($entityTypeCode)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $et = $this->entityManager->getEntityTypeByCode($entityTypeCode);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));



        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters);
    }

    /**
     * @param $entityTypeCode
     * @param $entityId
     * @return bool
     */
    public function getEntityNotes($entityTypeCode, $entityId)
    {
        if (empty($entityTypeCode) || empty($entityId)) {
            return true;
        }

        if (empty($this->noteManager)) {
            $this->noteManager = $this->container->get("note_manager");
        }

        return $this->noteManager->getNotesForEntity($entityTypeCode, $entityId);
    }
}
