<?php

namespace AppBundle\Controller;

// Do not remove (otherwise an error will occur).
use AppBundle\Entity\CronJobEntity;
use AppBundle\Managers\CronJobManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;

// Do not remove (otherwise an error will occur).
use AppBundle\Abstracts\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class CronJobController extends AbstractController
{
    /** @var CronJobManager $cronJobManager */
    protected $cronJobManager;

    /**
     * @Route("/cron_job/activate", name="activate_cron_job")
     * @Method("POST")
     * @param Request $request
     */
    public function activateCronJob(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Id is not defined')));
        }

        if(empty($this->cronJobManager)){
            $this->cronJobManager = $this->getContainer()->get("cron_job_manager");
        }

        /** @var CronJobEntity $cronJob */
        $cronJob = $this->cronJobManager->getCronJobById($p["id"]);

        if(empty($cronJob)){
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Cron job does not defined')));
        }

        $data = Array();
        $data["is_active"] = 1;

        $this->cronJobManager->insertUpdateCronJob($data,$cronJob);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Cron job activated')));
    }

}
