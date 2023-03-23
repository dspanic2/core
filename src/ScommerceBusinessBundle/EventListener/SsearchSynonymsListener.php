<?php

namespace ScommerceBusinessBundle\EventListener;

use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Managers\EntityManager;
use ScommerceBusinessBundle\Entity\SSearchSynonymsEntity;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SsearchSynonymsListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityDeletedEvent $event
     */
    public function onSsearchSynonymsDeleted(EntityDeletedEvent $event)
    {
        /** @var SSearchSynonymsEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_search_synonyms") {

            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->deleteEntityFromDatabase($entity);
        }
    }
}