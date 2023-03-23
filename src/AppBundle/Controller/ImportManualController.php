<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Constants\ImportManualConstants;
use AppBundle\Entity\ImportManualEntity;
use AppBundle\Managers\ImportManualManager;
use Monolog\Logger;
use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ImportManualController extends AbstractController
{
    /** @var ImportManualManager $importManualManager */
    private $importManualManager;

    protected function initialize()
    {
        parent::initialize();
        $this->importManualManager = $this->getContainer()->get("import_manual_manager");
    }

    /**
     * @Route("/import_manual/run", name="import_manual_run")
     * @Method("POST")
     */
    public function importManualRunAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Id is missing')));
        }

        /** @var ImportManualEntity $importManual */
        $importManual = $this->importManualManager->getImportManualById($p["id"]);

        if (empty($importManual)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('This import does not exist')));
        }

        if ($importManual->getImportManualStatusId() != ImportManualConstants::STATUS_WAITING_IN_QUEUE) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('This import is running or is already run')));
        }

        $this->importManualManager->runQueue($p["id"]);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Import is finished')));
    }
}
