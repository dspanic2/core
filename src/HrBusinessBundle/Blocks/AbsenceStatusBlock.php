<?php

namespace HrBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\PageBlockContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Managers\EntityManager;
use HrBusinessBundle\Entity\AbsenceEmployeeYearEntity;
use HrBusinessBundle\Managers\HrManager;

class AbsenceStatusBlock extends  AbstractBaseBlock
{
    public function GetPageBlockTemplate()
    {
        return ('HrBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
    }

    public function GetPageBlockData()
    {
        /**@var EntityManager $entityManager */
        $entityManager = $this->container->get("entity_manager");

        $tokenStorage = $this->container->get("security.token_storage");
        $user = $tokenStorage->getToken()->getUser();

        $employeesEntityType = $entityManager->getEntityTypeByCode("employee");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("user", "eq", $user->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $employee = $entityManager->getEntityByEntityTypeAndFilter($employeesEntityType, $compositeFilters);

        if(empty($employee)){
            return $this->pageBlockData;
        }

        /** @var HrManager $hrManager */
        $hrManager = $this->container->get('hr_manager');

        /** @var AbsenceEmployeeYearEntity $absenceEmployeeYear */
        $absenceEmployeeYear = $hrManager->getAbsenceEmployeeYearByYear($employee,date("Y",time()));

        $this->pageBlockData["model"]["absenceEmployeeYear"] = $absenceEmployeeYear;

        /*$lastYearDays = $absenceEmployeeYear->getLastYearDays();
        $thisYearDays = $absenceEmployeeYear->getThisYearDays();

        $approvedDays = $absenceEmployeeYear->getApprovedDays();

        $lastYearDays = intval($lastYearDays) - intval($approvedDays);
        if($lastYearDays < 0){
            $thisYearDays = intval($thisYearDays) + $lastYearDays;
            $lastYearDays = 0;
        }
        if($thisYearDays < 0){
            $thisYearDays = 0;
        }*/

        $this->pageBlockData["model"]["data"]["lastYearDays"] = $absenceEmployeeYear->getLastYearDaysLeft();
        $this->pageBlockData["model"]["data"]["thisYearDays"] = $absenceEmployeeYear->getThisYearDaysLeft();

        /** @var PageBlockContext $pageBlockContext */
        $pageBlockContext = $this->container->get('page_block_context');
        $absenceFormBlock = $pageBlockContext->getOneBy(Array("type" => "edit_form", "attributeSet" => $this->pageBlock->getAttributeSet()));

        $this->pageBlockData["model"]["url"] = $this->container->get('router')->generate('block_modal_view', array('block_id' => $absenceFormBlock->getId(), 'action' => 'close-modal'));

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'HrBusinessBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
    }


    public function GetPageBlockSetingsData()
    {
        $attributeSetContext = $this->container->get('attribute_set_context');
        $attributeSets = $attributeSetContext->getAll();

        return array(
            'entity' => $this->pageBlock,
            'attribute_sets' => $attributeSets,
        );
    }

    public function SavePageBlockSettings($data){

        $blockManager = $this->container->get('block_manager');

        $attributeSetContext = $this->container->get('attribute_set_context');

        $attributeSet = $attributeSetContext->getById($data["attributeSet"]);
        $this->pageBlock->setAttributeSet($attributeSet);
        $this->pageBlock->setEntityType($attributeSet->getEntityType());

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

       return $blockManager->save($this->pageBlock);
    }

    public function isVisible()
    {
        //Check permission
        return true;
    }

}
