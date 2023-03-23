<?php

namespace ScommerceBusinessBundle\Controller;

use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use ScommerceBusinessBundle\Entity\WebformEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use ScommerceBusinessBundle\Managers\WebformManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class WebformController extends AbstractScommerceController
{
    /** @var WebformManager */
    protected $webformManager;
    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->initializeTwigVariables($request);
        $this->webformManager = $this->container->get("webform_manager");
        if (empty($this->getPageUrlExtension)) {
            $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
        }
    }

    /**
     * @Route("/webform_submission", name="webform_submission")
     * @Method("POST")
     */
    public function saveWebformSubmissionAction(Request $request)
    {
        $this->initialize($request);

        $p = $_POST;
        if (!empty($_FILES)) {
            $p["files"] = $_FILES;
        }

        try {
            $webformData = $this->webformManager->saveWebformSubmissionFromPost($p);
            /** @var WebformEntity $webform */
            $webform = $webformData["webform"];
        } catch (\Exception $e) {
            return new JsonResponse(array("error" => true, "message" => $e->getMessage()));
        }

        if (empty($webform)) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Error saving submission")));
        }

        $session = $this->container->get("session");
        $storeId = $session->get("current_store_id");

        $ret = [];
        $ret["error"] = $webformData["data"]["error"] ?? false;
        $ret["message"] = $webformData["data"]["message"] ?? $this->translator->trans("Submission saved");

        if (!empty($webformData["data"]["content"])) {
            $success = $webformData["data"]["content"];
        } else {
            $success = $this->getPageUrlExtension->getEntityStoreAttribute($storeId, $webform, "success");
        }
        $ret["content"] = $success;

        return new JsonResponse($ret);
    }
}
