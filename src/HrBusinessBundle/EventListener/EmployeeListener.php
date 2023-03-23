<?php

namespace HrBusinessBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use HrBusinessBundle\Managers\HrManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EmployeeListener implements ContainerAwareInterface
{
    /** @var HrManager $hrManager */
    protected $hrManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function onEmployeePreCreated(EntityPreCreatedEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "employee") {
            $entity->setFullName($entity->getFirstName() . " " . $entity->getLastName());
        }
    }

    public function onEmployeePreUpdated(EntityPreUpdatedEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "employee") {
            $entity->setFullName($entity->getFirstName() . " " . $entity->getLastName());
        }
    }

    public function onEmployeeCreated(EntityCreatedEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "employee") {

            if (empty($this->hrManager)) {
                $this->hrManager = $this->container->get("hr_manager");
            }

            $year = date("Y", time());
            $this->hrManager->generateYearlyAbsenceForEmployee($entity, $year);

            $year = intval($year) + 1;
            $this->hrManager->generateYearlyAbsenceForEmployee($entity, $year);
        }
    }
}