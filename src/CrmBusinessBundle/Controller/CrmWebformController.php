<?php

namespace CrmBusinessBundle\Controller;

use CrmBusinessBundle\Managers\CrmWebformManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class CrmWebformController extends AbstractScommerceController
{
    /** @var CrmWebformManager */
    protected $crmWebformManager;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->initializeTwigVariables($request);
        $this->crmWebformManager = $this->container->get("crm_webform_manager");
    }

    /**
     * @Route("/webform/export_submissions", name="webform_export_submissions")
     * @Method("POST")
     */
    public function webformExportSubmissionsAction(Request $request)
    {
        $this->initialize($request);
        $p = $_POST;

        if (!isset($p["parent_entity_id"]) || empty($p["parent_entity_id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Parent entity is not defined')));
        }

        $fileUrl = $this->crmWebformManager->exportWebformSubmissions($p["parent_entity_id"]);

        return new JsonResponse(array("error" => false, "file" => $fileUrl, "message" => $this->translator->trans("Export generated")));
    }
}
