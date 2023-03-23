<?php

namespace AppBundle\EventListener;

use AppBundle\Constants\ImportManualConstants;
use AppBundle\Entity\ImportManualEntity;
use AppBundle\Entity\ImportManualStatusEntity;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Managers\ImportManualManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ImportManualListener implements ContainerAwareInterface
{
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     */
    public function onImportManualPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var ImportManualEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "import_manual") {

            /** @var ImportManualManager $importManager */
            $importManager = $this->container->get("import_manual_manager");

            /** @var ImportManualStatusEntity $status */
            $status = $importManager->getImportManualStatusById(ImportManualConstants::STATUS_WAITING_IN_QUEUE);

            $entity->setImportManualStatus($status);
        }
    }
}
