<?php

namespace HrBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use HrBusinessBundle\Constants\HrConstants;
use HrBusinessBundle\Entity\AbsenceEmployeeYearEntity;
use HrBusinessBundle\Entity\AbsenceEntity;
use HrBusinessBundle\Entity\EmployeeEntity;
use Monolog\Logger;
use Symfony\Component\Config\Definition\Exception\Exception;

class HrManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;

    public function initialize()
    {
        parent::initialize();

        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @param EmployeeEntity|null $entity
     * @param $data
     * @return EmployeeEntity|mixed|null
     */
    public function createUpdateEmployee(EmployeeEntity $entity = null, $data){

        if(empty($entity)){
            $entity = $this->entityManager->getNewEntityByAttributSetName("employee");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($entity, $setter)) {
                $entity->$setter($value);
            }
        }

        $this->entityManager->saveEntity($entity);
        $this->entityManager->refreshEntity($entity);

        return $entity;
    }

    public function getEmployeeByUserId(){

        $employeeEntityType = $this->entityManager->getEntityTypeByCode("employee");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("user", "eq", $this->user->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($employeeEntityType, $compositeFilters);
    }

    /**
     * @param null $additionalFilter
     * @return null
     */
    public function getFilteredEmployees($additionalFilter = null, $sortFilters = null){

        $employeeEntityType = $this->entityManager->getEntityTypeByCode("employee");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if(!empty($additionalFilter)){
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        if(empty($sortFilters)){
            $sortFilters = new SortFilterCollection();
            $sortFilters->addSortFilter(new SortFilter("id","asc"));
        }

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($employeeEntityType, $compositeFilters, $sortFilters);

    }

    /**
     * @param $id
     * @return |null
     */
    public function getEmployeeById($id){

        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get('entity_manager');

        $employeesEntityType = $entityManager->getEntityTypeByCode("employee");

        return $entityManager->getEntityByEntityTypeAndId($employeesEntityType, $id);
    }

    /**
     * @param CompositeFilter|null $additionalFilter
     * @return mixed
     */
    public function getEmployees(CompositeFilter $additionalFilter = null){

        $employeesEntityType = $this->entityManager->getEntityTypeByCode("employee");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if(!empty($additionalFilter)){
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($employeesEntityType, $compositeFilters);
    }

    /**
     * @param $employee
     * @param $year
     */
    public function generateYearlyAbsenceForEmployee($employee,$year){

        $absenceEmployeeYearEntityType = $this->entityManager->getEntityTypeByCode("absence_employee_year");

        $lastYear = intval($year) - 1;

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("employee", "eq", $employee->getId()));
        $compositeFilter->addFilter(new SearchFilter("year", "eq", $lastYear));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var AbsenceEmployeeYearEntity $absenceEmployeeLastYear */
        $absenceEmployeeLastYear = $this->entityManager->getEntityByEntityTypeAndFilter($absenceEmployeeYearEntityType, $compositeFilters);

        /** @var AbsenceEmployeeYearEntity $absenceEmployeeYear */
        $absenceEmployeeYear = $this->entityManager->getNewEntityByAttributSetName("absence_employee_year");

        $absenceEmployeeYear->setYear($year);
        $absenceEmployeeYear->setEmployee($employee);
        $absenceEmployeeYear->setRequestedDays(0);
        $absenceEmployeeYear->setApprovedDays(0);
        $absenceEmployeeYear->setLastYearDays(0);
        $absenceEmployeeYear->setLastYearDaysLeft(0);
        $absenceEmployeeYear->setThisYearDays(0);
        $absenceEmployeeYear->setThisYearDaysLeft(0);

        if(!empty($absenceEmployeeLastYear)){
            $absenceEmployeeYear->setThisYearDays($absenceEmployeeLastYear->getThisYearDays());
            $absenceEmployeeYear->setThisYearDaysLeft($absenceEmployeeLastYear->getThisYearDays());
        }

        $this->entityManager->saveEntity($absenceEmployeeYear);

    }

    /**
     * @param $year
     * @return bool
     * @throws \Exception
     */
    public function generateYearlyAbsence($year){

        $additionalFilter = new CompositeFilter();
        $additionalFilter->setConnector("and");
        $additionalFilter->addFilter(new SearchFilter("employmentStatusId", "ne", HrConstants::EMPLOYMENT_STATUS_TERMINATED));

        $employees = $this->getEmployees($additionalFilter);

        $date = new \DateTime();

        if(!empty($employees)){
            foreach ($employees as $employee){

                $absenceEmployeeYear = $this->getAbsenceEmployeeYearByYear($employee,$year);

                if(empty($absenceEmployeeYear)){

                    $this->generateYearlyAbsenceForEmployee($employee,$year);
                }
            }
        }

        return true;
    }

    /**
     * @param $year
     * @return bool
     */
    public function removeAbsenceFromLastYear($year){

        $employees = $this->getEmployees();

        if(!empty($employees)){
            foreach ($employees as $employee){

                /** @var AbsenceEmployeeYearEntity $absenceEmployeeYear */
                $absenceEmployeeYear = $this->getAbsenceEmployeeYearByYear($employee,$year);

                if(!empty($absenceEmployeeYear)){

                    if($absenceEmployeeYear->getLastYearDaysLeft() > 0){

                        $lastYearDays = $absenceEmployeeYear->getLastYearDays() - $absenceEmployeeYear->getLastYearDaysLeft();
                        if($lastYearDays < 0){
                            $lastYearDays = 0;
                        }

                        $absenceEmployeeYear->setLastYearDays($lastYearDays);
                        $this->entityManager->saveEntity($absenceEmployeeYear);
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param $year
     * @return bool
     * @deprecated
     */
    public function transferLeftDaysFromLastYear($year){

        return false;

        $employees = $this->getEmployees();
        $lastYear = intval($year) - 1;

        if(!empty($employees)){
            foreach ($employees as $employee){

                /** @var AbsenceEmployeeYearEntity $absenceEmployeeYear */
                $absenceEmployeeYear = $this->getAbsenceEmployeeYearByYear($employee,$year);
                /** @var AbsenceEmployeeYearEntity $absenceEmployeeLastYear */
                $absenceEmployeeLastYear = $this->getAbsenceEmployeeYearByYear($employee,$lastYear);

                if(!empty($absenceEmployeeYear) && !empty($absenceEmployeeLastYear)){

                    if($absenceEmployeeYear->getLastYearDaysLeft() != $absenceEmployeeLastYear->getThisYearDaysLeft()){
                        $absenceEmployeeYear->setLastYearDays($absenceEmployeeLastYear->getThisYearDaysLeft());
                        $this->entityManager->saveEntity($absenceEmployeeYear);
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param $employee
     * @param $day
     * @param $month
     * @param $year
     * @return |null
     */
    public function getAbsenceEmployeeYearByYear($employee,$year){

        $absenceEmployeeYearEntityType = $this->entityManager->getEntityTypeByCode("absence_employee_year");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("year", "eq", $year));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("employee", "eq", $employee->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $absenceEmployeeYear = $this->entityManager->getEntityByEntityTypeAndFilter($absenceEmployeeYearEntityType, $compositeFilters);

        /** Generate absence if does not exist */
        if(empty($absenceEmployeeYear)){
            $absenceEmployeeYear = $this->generateYearlyAbsenceForEmployee($employee,$year);
        }

        return $absenceEmployeeYear;
    }

    /**
     * @param $absence
     * @return \DateTime
     * @throws \Exception
     */
    public function checkIfAbsenceStartAndEndIsSameYear($absence){

        $yearDelimiterDate = \DateTime::createFromFormat('Y-m-d H:i:s', $absence->getToDate()->format("Y")."-07-01 00:00:00");

        $returnToDate = $absence->getToDate();

        if($absence->getFromDate() < $yearDelimiterDate && $absence->getToDate() > $yearDelimiterDate){
            $returnToDate = new \DateTime($absence->getFromDate()->format("Y")."/06/30");
            $nextYearBeginDate = new \DateTime($absence->getToDate()->format("Y")."/07/01");

            /** @var AbsenceEntity $nextYearAbsence */
            $nextYearAbsence = $this->entityManager->getNewEntityByAttributSetName("absence");

            $nextYearAbsence->setEmployee($absence->getEmployee());
            $nextYearAbsence->setAbsenceType($absence->getAbsenceType());
            $nextYearAbsence->setRemark($absence->getRemark());
            $nextYearAbsence->setFromDate($nextYearBeginDate);
            $nextYearAbsence->setToDate($absence->getToDate());

            $this->entityManager->saveEntity($nextYearAbsence);
        }

        return $returnToDate;
    }

    public function calculateVacationDaysForEmployee($absence){

        /** @var AbsenceEmployeeYearEntity $absenceEmployeeYear */
        $absenceEmployeeYear = $absence->getAbsenceEmployeeYear();

        $absenceEntityType = $this->entityManager->getEntityTypeByCode("absence");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("absenceType.deductFromYear", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("absenceEmployeeYear", "eq", $absence->getAbsenceEmployeeYear()->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $absences = $this->entityManager->getEntitiesByEntityTypeAndFilter($absenceEntityType, $compositeFilters);

        $totalRequested = 0;
        $totalApproved = 0;

        if(!empty($absences)){
            foreach ($absences as $absence){
                $totalRequested = $totalRequested + intval($absence->getNumberOfDays());

                if($absence->getApproved() == 1){
                    $totalApproved = $totalApproved + intval($absence->getNumberOfDays());
                }
            }
        }

        if($absenceEmployeeYear->getApprovedDays() != $totalApproved || $absenceEmployeeYear->getRequestedDays() != $totalRequested){
            $absenceEmployeeYear->setRequestedDays($totalRequested);
            $absenceEmployeeYear->setApprovedDays($totalApproved);

            $this->entityManager->saveEntity($absenceEmployeeYear);
        }

        return true;
    }

    /**
     * @param AbsenceEmployeeYearEntity $entity
     * @return bool
     */
    public function calculateVacationDaysLeftForEmployee(AbsenceEmployeeYearEntity $entity){

        $approvedDays = $entity->getApprovedDays();

        $leftDaysFromLastYear = $entity->getLastYearDays() - $approvedDays;
        if($leftDaysFromLastYear < 0){
            $leftDaysFromLastYear = 0;
            $approvedDays = $approvedDays - $entity->getLastYearDays();
        }
        else{
            $approvedDays = 0;
        }

        $leftDaysFromThisYear = $entity->getThisYearDays() - $approvedDays;

        $entity->setLastYearDaysLeft($leftDaysFromLastYear);
        $entity->setThisYearDaysLeft($leftDaysFromThisYear);

        $this->entityManager->saveEntityWithoutLog($entity);

        return true;
    }

    /**
     * @param $absenceId
     * @return |null
     */
    public function getAbsenceById($absenceId){

        $absenceEntityType = $this->entityManager->getEntityTypeByCode("absence");
        $absence = $this->entityManager->getEntityByEntityTypeAndId($absenceEntityType,$absenceId);

        return $absence;
    }

    /**
     * @param $absenceTypeId
     * @return |null
     */
    public function getAbsenceTypeById($absenceTypeId){

        $absenceTypeEntityType = $this->entityManager->getEntityTypeByCode("absence_type");
        $absence = $this->entityManager->getEntityByEntityTypeAndId($absenceTypeEntityType,$absenceTypeId);

        return $absence;
    }

    /**
     * @param EmployeeEntity $employee
     * @return |null
     */
    public function getAbsencesForEmployee(EmployeeEntity $employee){

        $absenceEntityType = $this->entityManager->getEntityTypeByCode("absence");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("employee", "eq", $employee->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("fromDate","desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($absenceEntityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param $date1O
     * @param $date2
     * @return int
     */
    public function calculateNumberOfWorkingDaysBetweenDates($date1O,$date2){

        /**
         * Clone object because it holds reference
         */
        $date1 = clone $date1O;

        $holidayEntityType = $this->entityManager->getEntityTypeByCode("holidays");

        $numberOfWorkingDays = 0;
        $avoidSatudray = true;
        $avoidSunday = true;

        while ($date1 <= $date2){

            $avoid = false;

            if($avoidSatudray && $date1->format("N") == 6){
                $avoid = true;
            }
            elseif($avoidSunday && $date1->format("N") == 7){
                $avoid = true;
            }
            else{

                $compositeFilter = new CompositeFilter();
                $compositeFilter->setConnector("and");
                $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
                $compositeFilter->addFilter(new SearchFilter("date", "eq", $date1->format("Y-m-d")));

                $compositeFilters = new CompositeFilterCollection();
                $compositeFilters->addCompositeFilter($compositeFilter);

                $exists = $this->entityManager->getEntityByEntityTypeAndFilter($holidayEntityType, $compositeFilters);

                if(!empty($exists)){
                    $avoid = true;
                }
            }

            if(!$avoid){
                $numberOfWorkingDays++;
            }

            $date1->modify('+1 day');
        }

        return $numberOfWorkingDays;
    }

    /**
     * @return bool
     */
    public function fixMissingAbsenceEmployeeYear(){

        $absenceEntityType = $this->entityManager->getEntityTypeByCode("absence");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $absences = $this->entityManager->getEntitiesByEntityTypeAndFilter($absenceEntityType, $compositeFilters);

        /** @var AbsenceEntity $absence */
        foreach ($absences as $absence){

            if(empty($absence->getAbsenceEmployeeYear())){
                /** @var AbsenceEmployeeYearEntity $absenceEmployeeYear */
                $absenceEmployeeYear = $this->getAbsenceEmployeeYearByYear($absence->getEmployee(),$absence->getFromDate()->format("Y"));

                $absence->setAbsenceEmployeeYear($absenceEmployeeYear);
                $this->entityManager->saveEntity($absence);
            }

        }

        return true;
    }

    /**
     * @return bool
     */
    public function recalculateAllAbsences(){

        $absenceEntityType = $this->entityManager->getEntityTypeByCode("absence");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $absences = $this->entityManager->getEntitiesByEntityTypeAndFilter($absenceEntityType, $compositeFilters);

        /** @var AbsenceEntity $absence */
        foreach ($absences as $absence){
            $this->entityManager->saveEntity($absence);
        }

        return false;
    }

    /**
     * @return bool
     */
    public function recalculateAll(){

        $absenceEmployeeYearEntityType = $this->entityManager->getEntityTypeByCode("absence_employee_year");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $absenceEmployeeYears = $this->entityManager->getEntitiesByEntityTypeAndFilter($absenceEmployeeYearEntityType, $compositeFilters);

        /** @var AbsenceEmployeeYearEntity $absenceEmployeeYear */
        foreach ($absenceEmployeeYears as $absenceEmployeeYear){
            $this->entityManager->saveEntity($absenceEmployeeYear);
        }

        return false;
    }
}
