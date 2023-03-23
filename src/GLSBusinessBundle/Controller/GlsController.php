<?php

namespace GLSBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use GLSBusinessBundle\Entity\GlsParcelEntity;
use GLSBusinessBundle\Managers\GLSManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Config\Definition\Exception\Exception;


class GlsController extends AbstractController
{
    /** @var GLSManager $glsManager */
    protected $glsManager;

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * @Route("/request_gls", name="request_gls")
     * @param Request $request
     * @return JsonResponse
     */
    public function requestGlsAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("Missing id")));
        }

        if (empty($this->glsManager)) {
            $this->glsManager = $this->container->get("gls_manager");
        }

        /** @var GlsParcelEntity $glsParcel */
        $glsParcel = $this->glsManager->getGlsParcelById($p["id"]);

        $glsParcels = Array(
            $glsParcel->getClientReference() => $glsParcel
        );

        $res = $this->glsManager->printGLSLabels($glsParcels);
        if (isset($res["error"]) && $res["error"] == true) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $res["message"]));
        }

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Success"), "message" => $this->translator->trans("Parcel pickup requested")));
    }

    /**
     * @Route("/mass_request_gls", name="mass_request_gls")
     * @param Request $request
     * @return JsonResponse
     */
    public function massRequestGlsAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("No items selected")));
        }
        if (!isset($p["items"]["gls_parcel"]) || empty($p["items"]["gls_parcel"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("No items selected")));
        }

        if (empty($this->glsManager)) {
            $this->glsManager = $this->container->get("gls_manager");
        }

        $glsParcels = Array();

        foreach ($p["items"]["gls_parcel"] as $item) {
            /** @var GlsParcelEntity $glsParcel */
            $glsParcel = $this->glsManager->getGlsParcelById($item);
            if (!empty($glsParcel) && empty($glsParcel->getParcelNumber())) {
                $glsParcels[$glsParcel->getClientReference()] = $glsParcel;
            }
        }

        if (empty($glsParcels)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Please select new parcels only")));
        }

        $res = $this->glsManager->printGLSLabels($glsParcels);
        if (isset($res["error"]) && $res["error"] == true) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $res["message"]));
        }

        return new JsonResponse(array(
            "error" => false,
            "title" => $this->translator->trans("Success"),
            "message" => $res["message"],
            "filepath" => $res["filepath"]
        ));
    }
}
