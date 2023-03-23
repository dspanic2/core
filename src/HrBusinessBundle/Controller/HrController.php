<?php

namespace HrBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\PageManager;
use HrBusinessBundle\Entity\AbsenceEntity;
use HrBusinessBundle\Managers\HrManager;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;


class HrController extends AbstractController
{
    /**@var HrManager $hrManager */
    protected $hrManager;

    protected function initialize()
    {
        parent::initialize();
        $this->hrManager = $this->container->get('hr_manager');
    }

    /**
     * @Route("/absence_approve", name="absence_approve")
     */
    public function absenceApproveAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Id is not defined')));
        }

        /** @var AbsenceEntity $absence */
        $absence = $this->hrManager->getAbsenceById($p["id"]);

        if(empty($absence)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Entity does not exist')));
        }

        if($absence->getApproved() == 1){
            return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Approve'), 'message' => $this->translator->trans('Absence is already approveed')));
        }

        $absence->setApproved(1);

        $this->hrManager->saveEntity($absence);

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Approved'), 'message' => $this->translator->trans('Absence is approved')));
    }

    /**
     * @Route("/absence_mass_approve", name="absence_mass_approve")
     */
    public function absenceMassApproveAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Id is not defined')));
        }

        if (!isset($p["items"]["absence"]) || empty($p["items"]["absence"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Id is not defined')));
        }

        foreach ($p["items"]["absence"] as $id){

            /** @var AbsenceEntity $absence */
            $absence = $this->hrManager->getAbsenceById($id);

            if($absence->getApproved() == 1){
                continue;
            }

            $absence->setApproved(1);

            $this->hrManager->saveEntity($absence);
        }

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Approved'), 'message' => $this->translator->trans('Report item is approved')));
    }

    /**
     * @Route("/absence_disapprove", name="absence_disapprove")
     */
    public function absenceDisapproveAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"]) || !preg_match('/^[0-9]*$/', $p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Id is not defined')));
        }

        /** @var AbsenceEntity $absence */
        $absence = $this->hrManager->getAbsenceById($p["id"]);

        if(empty($absence)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Entity does not exist')));
        }

        if($absence->getApproved() == 0){
            return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Disapprove'), 'message' => $this->translator->trans('Absence is already disapproved')));
        }

        $absence->setApproved(0);

        $this->hrManager->saveEntity($absence);

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Disapproved'), 'message' => $this->translator->trans('Absence is disapproved')));
    }

    /**
     * @Route("/absence_mass_disapprove", name="absence_mass_disapprove")
     */
    public function absenceMassDisapproveAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Id is not defined')));
        }

        if (!isset($p["items"]["absence"]) || empty($p["items"]["absence"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Id is not defined')));
        }

        foreach ($p["items"]["absence"] as $id){

            /** @var AbsenceEntity $absence */
            $absence = $this->hrManager->getAbsenceById($id);

            if($absence->getApproved() == 0){
                continue;
            }

            $absence->setApproved(0);

            $this->hrManager->saveEntity($absence);
        }

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Disapproved'), 'message' => $this->translator->trans('Report item is disapproved')));
    }
}
