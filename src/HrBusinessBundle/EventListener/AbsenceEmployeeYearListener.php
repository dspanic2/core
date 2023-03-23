<?php

namespace HrBusinessBundle\EventListener;

use AppBundle\Events\EntityUpdatedEvent;
use HrBusinessBundle\Entity\AbsenceEmployeeYearEntity;
use HrBusinessBundle\Managers\HrManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AbsenceEmployeeYearListener implements ContainerAwareInterface
{
    /** @var HrManager $hrManager */
    protected $hrManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityUpdatedEvent $event
     */
    public function onAbsenceEmployeeYearUpdated(EntityUpdatedEvent $event)
    {
        /** @var AbsenceEmployeeYearEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "absence_employee_year") {

            if (empty($this->hrManager)) {
                $this->hrManager = $this->container->get("hr_manager");
            }

            $this->hrManager->calculateVacationDaysLeftForEmployee($entity);
        }
    }
}