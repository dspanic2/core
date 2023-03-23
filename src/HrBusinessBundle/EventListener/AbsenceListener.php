<?php

namespace HrBusinessBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use HrBusinessBundle\Entity\AbsenceEntity;
use HrBusinessBundle\Managers\HrManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AbsenceListener implements ContainerAwareInterface
{
    /** @var HrManager $hrManager */
    protected $hrManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @throws \Exception
     */
    public function onAbsencePreCreated(EntityPreCreatedEvent $event)
    {
        /** @var AbsenceEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "absence") {

            if (empty($this->hrManager)) {
                $this->hrManager = $this->container->get("hr_manager");
            }

            if (empty($entity->getEmployee())) {
                $entity->setEmployee($this->hrManager->getEmployeeByUserId());
            }
            if (empty($entity->getApproved())) {
                $entity->setApproved(0);
            }
            $entity->setColor($entity->getAbsenceType()->getColorUnaprooved());

            $toDate = $entity->getToDate();
            if ($toDate->format("H:i") == "00:00") {
                $toDate = \DateTime::createFromFormat('Y-m-d H:i:s', $toDate->format("Y-m-d") . " 23:30:00");
                $entity->setToDate($toDate);
            }

            $toDate = $this->hrManager->checkIfAbsenceStartAndEndIsSameYear($entity);
            $entity->setToDate($toDate);

            $absenceEmployeeYear = $this->hrManager->getAbsenceEmployeeYearByYear($entity->getEmployee(), $entity->getFromDate()->format("Y"));
            if (!empty($absenceEmployeeYear)) {
                $entity->setAbsenceEmployeeYear($absenceEmployeeYear);
            }

            $numberOfDays = $this->hrManager->calculateNumberOfWorkingDaysBetweenDates($entity->getFromDate(), $entity->getToDate());

            $entity->setNumberOfDays($numberOfDays);
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onAbsencePreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var AbsenceEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "absence") {

            if (empty($this->hrManager)) {
                $this->hrManager = $this->container->get("hr_manager");
            }

            $toDate = $entity->getToDate();
            if ($toDate->format("H:i") == "00:00") {
                $toDate = \DateTime::createFromFormat('Y-m-d H:i:s', $toDate->format("Y-m-d") . " 23:30:00");
                $entity->setToDate($toDate);
            }

            $toDate = $this->hrManager->checkIfAbsenceStartAndEndIsSameYear($entity);
            $entity->setToDate($toDate);

            $absenceEmployeeYear = $this->hrManager->getAbsenceEmployeeYearByYear($entity->getEmployee(), $entity->getFromDate()->format("Y"));
            if (!empty($absenceEmployeeYear) && $entity->getAbsenceEmployeeYear()->getId() != $absenceEmployeeYear->getId()) {
                $entity->setAbsenceEmployeeYear($absenceEmployeeYear);
            }

            $numberOfDays = $this->hrManager->calculateNumberOfWorkingDaysBetweenDates($entity->getFromDate(), $entity->getToDate());
            $entity->setNumberOfDays($numberOfDays);

            if ($entity->getApproved() == 0) {
                $entity->setColor($entity->getAbsenceType()->getColorUnaprooved());
            } else {
                $entity->setColor($entity->getAbsenceType()->getColor());
            }
        }
    }

    /**
     * @param EntityUpdatedEvent $event
     */
    public function onAbsenceUpdated(EntityUpdatedEvent $event)
    {
        /** @var AbsenceEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "absence") {

            if (empty($this->hrManager)) {
                $this->hrManager = $this->container->get("hr_manager");
            }

            if (!empty($entity->getAbsenceEmployeeYear())) {
                $this->hrManager->calculateVacationDaysForEmployee($entity);
            }
        }
    }

    /**
     * @param EntityCreatedEvent $event
     */
    public function onAbsenceCreated(EntityCreatedEvent $event)
    {
        /** @var AbsenceEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "absence") {

            if (empty($this->hrManager)) {
                $this->hrManager = $this->container->get("hr_manager");
            }

            if (!empty($entity->getAbsenceEmployeeYear())) {
                $this->hrManager->calculateVacationDaysForEmployee($entity);
            }
        }
    }

    /**
     * @param EntityDeletedEvent $event
     */
    public function onAbsenceDeleted(EntityDeletedEvent $event)
    {
        /** @var AbsenceEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "absence") {

            if (empty($this->hrManager)) {
                $this->hrManager = $this->container->get("hr_manager");
            }

            if (!empty($entity->getAbsenceEmployeeYear())) {
                $this->hrManager->calculateVacationDaysForEmployee($entity);
            }
        }
    }
}
