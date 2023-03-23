<?php

namespace DPDBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\CountryEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use DPDBusinessBundle\Entity\DpdParcelEntity;
use DPDBusinessBundle\Entity\DpdParcelNumbersEntity;
use DPDBusinessBundle\Entity\DpdParcelTypeEntity;
use DPDBusinessBundle\Managers\DPDManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class DPDController extends AbstractController
{
    /** @var DPDManager $dpdManager */
    protected $dpdManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
        $this->dpdManager = $this->container->get('dpd_manager');
    }

    /**
     * @Route("/dpd_parcel/generate_new_dpd_parcel", name="generate_new_dpd_parcel")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function generateNewDpdParcelAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("Missing id")));
        }

        if (empty($this->entityManager)){
            $this->entityManager = $this->container->get("entity_manager");
        }

        if (empty($this->dpdManager)){
            $this->dpdManager = $this->container->get("dpd_manager");
        }

        /** @var DpdParcelEntity $existingDpdParcel */
        $existingDpdParcel = $this->dpdManager->getParcelEntityByAttribute("", "order", $p["id"]);

        $dpdParcelId = null;
        if ($existingDpdParcel === null){
            /** @var OrderEntity $order */
            $order = $this->entityManager->getEntityByEntityTypeCodeAndId("order", $p["id"]);

            /** @var DpdParcelEntity $dpdParcel */
            $dpdParcel = $this->entityManager->getNewEntityByAttributSetName("dpd_parcel");

            $dpdParcel->setOrder($order);
            $dpdParcel->setOrderNumber($order->getIncrementId());

            $dpdParcel = $this->entityManager->saveEntity($dpdParcel);
            $this->entityManager->refreshEntity($dpdParcel);
            $dpdParcelId = $dpdParcel->getId();
        } else {
            $dpdParcelId = $existingDpdParcel->getId();
        }

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Success"), "url" => "/page/dpd_parcel/form/" . $dpdParcelId));
    }

    /**
     * @Route("/dpd_parcel/request_dpd", name="request_dpd")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function requestDpdAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("Missing id")));
        }

        if (!isset($p["parcel_type_id"]) || empty($p["parcel_type_id"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("Missing parcel type id")));
        }

        if (empty($this->dpdManager)) {
            $this->dpdManager = $this->container->get("dpd_manager");
        }

        /** @var DpdParcelEntity $dpdParcel */
        $dpdParcel = $this->dpdManager->getDPDEntityById($p["id"], 'dpd_parcel');

        if ($dpdParcel->getRequested()) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => 'Already requested'));
        }

        $res = $this->dpdManager->requestDPDParcel($dpdParcel);

        if ($res['error'] === true) {
            $errorArray[$dpdParcel->getId()] = $res['message'];
            $this->logger->error("DPD: " . json_encode($errorArray));

            $dpdParcel->setErrorDescription($res['message']);
            $this->dpdManager->saveDPDEntity($dpdParcel);

            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $res['message']));
        }

        if (!empty($dpdParcel->getErrorDescription())){
            $dpdParcel->setErrorDescription(null);
            $this->dpdManager->saveDPDEntity($dpdParcel);
        }

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Success"), "message" => $this->translator->trans("Parcel pickup requested")));
    }

    /**
     * @Route("/dpd_parcel/refresh_dpd_status", name="refresh_dpd_status")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function refreshDpdStatusAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("Missing id")));
        }

        if (empty($this->dpdManager)) {
            $this->dpdManager = $this->container->get("dpd_manager");
        }

        /** @var DpdParcelEntity $dpdParcel */
        $dpdParcel = $this->dpdManager->getDPDEntityById($p["id"], 'dpd_parcel');

        if (!$dpdParcel->getRequested()) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => 'Not requested'));
        }

        $res = $this->dpdManager->getDPDParcelStatus($dpdParcel);

        if ($res['error'] === true) {
            $errorArray[$dpdParcel->getId()] = $res['message'];
            $this->logger->error("DPD: " . json_encode($errorArray));

            $dpdParcel->setErrorDescription($res['message']);
            $this->dpdManager->saveDPDEntity($dpdParcel);

            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $res['message']));
        }

        if (!empty($dpdParcel->getErrorDescription())){
            $dpdParcel->setErrorDescription(null);
            $this->dpdManager->saveDPDEntity($dpdParcel);
        }

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Success"), "message" => $this->translator->trans("Refreshed successfully")));
    }

    /**
     * @Route("/dpd_parcel/delete_or_cancel_dpd", name="delete_or_cancel_dpd")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteOrCancelDpdAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("Missing id")));
        }

        if (empty($this->dpdManager)) {
            $this->dpdManager = $this->container->get("dpd_manager");
        }

        /** @var DpdParcelEntity $dpdParcel */
        $dpdParcel = $this->dpdManager->getDPDEntityById($p["id"], 'dpd_parcel');

        if (!$dpdParcel->getRequested()) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => 'Not requested'));
        }

        $res = $this->dpdManager->deleteOrCancelDPDParcel($dpdParcel, $p['operation']);

        if ($res['error'] === true) {
            $errorArray[$dpdParcel->getId()] = $res['message'];
            $this->logger->error("DPD: " . json_encode($errorArray));

            $dpdParcel->setErrorDescription($res['message']);
            $this->dpdManager->saveDPDEntity($dpdParcel);

            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $res['message']));
        }

        if (!empty($dpdParcel->getErrorDescription())){
            $dpdParcel->setErrorDescription(null);
            $this->dpdManager->saveDPDEntity($dpdParcel);
        }

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Success"), "message" => $this->translator->trans(ucfirst($p['operation']) . " successfully")));
    }

    /**
     * @Route("/dpd_parcel/print_dpd_labels", name="print_dpd_labels")
     * @Method("POST")
     */
    public function printDpdLabelAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("Missing id")));
        }

        if (empty($this->dpdManager)) {
            $this->dpdManager = $this->container->get("dpd_manager");
        }

        /** @var DpdParcelEntity $dpdParcel */
        $dpdParcel = $this->dpdManager->getDPDEntityById($p["id"], 'dpd_parcel');

        if (!$dpdParcel->getRequested()) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => 'Not requested'));
        }

        $res = $this->dpdManager->printDPDLabels($dpdParcel);
        if ($res['error'] === true) {
            $errorArray[$dpdParcel->getId()] = $res['message'];
            $this->logger->error("DPD: " . json_encode($errorArray));

            $dpdParcel->setErrorDescription($res['message']);
            $this->dpdManager->saveDPDEntity($dpdParcel);

            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $res['message']));
        }

        if (!empty($dpdParcel->getErrorDescription())){
            $dpdParcel->setErrorDescription(null);
            $this->dpdManager->saveDPDEntity($dpdParcel);
        }

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Success"), "message" => $this->translator->trans("Labels printed successfully")));
    }

    /**
     * @Route("/dpd_parcel/mass_dpd_commit_shipment", name="mass_dpd_commit_shipment")
     * @Method("POST")
     */
    public function massDpdCommitShipmentAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]["dpd_parcel"]) || empty($p["items"]["dpd_parcel"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("Missing ids")));
        }

        $ids = $p["items"]["dpd_parcel"];

        if (empty($this->dpdManager)) {
            $this->dpdManager = $this->container->get("dpd_manager");
        }

        foreach ($ids as $id){
            /** @var DpdParcelEntity $dpdParcel */
            $dpdParcel = $this->dpdManager->getDPDEntityById($id, 'dpd_parcel');

            if ($dpdParcel->getRequested()) {
                continue;
            }

            $res = $this->dpdManager->requestDPDParcel($dpdParcel);

            if ($res['error'] === true) {
                $errorArray[$dpdParcel->getId()] = $res['message'];
                $this->logger->error("DPD: " . json_encode($errorArray));

                $dpdParcel->setErrorDescription($res['message']);
                $this->dpdManager->saveDPDEntity($dpdParcel);

                continue;
            }

            if (!empty($dpdParcel->getErrorDescription())){
                $dpdParcel->setErrorDescription(null);
                $this->dpdManager->saveDPDEntity($dpdParcel);
            }
        }

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Success"), "message" => $this->translator->trans("Parcel pickup requested")));
    }

    /**
     * @Route("/dpd_parcel/mass_dpd_print_labels", name="mass_dpd_print_labels")
     * @Method("POST")
     */
    public function massDpdPrintLabelsAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]["dpd_parcel"]) || empty($p["items"]["dpd_parcel"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("Missing ids")));
        }

        $ids = $p["items"]["dpd_parcel"];

        if (empty($this->dpdManager)) {
            $this->dpdManager = $this->container->get("dpd_manager");
        }

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("dpdParcel.id", "in", implode(",",$ids)));

        $dpdParcelNumbers = $this->dpdManager->getFilteredDPDParcelNumbers($compositeFilter);

        if(!EntityHelper::isCountable($dpdParcelNumbers) || count($dpdParcelNumbers) < 1){
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("Missing parcel numbers")));
        }

        $dpdNumbers = Array();
        /** @var DpdParcelNumbersEntity $dpdParcelNumber */
        foreach ($dpdParcelNumbers as $dpdParcelNumber){
            if(!$dpdParcelNumber->getDpdParcel()->getRequested()){
                continue;
            }

            $dpdNumbers[] = $dpdParcelNumber->getDpdParcelNumber();
        }

        if(empty($dpdNumbers)){
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => 'Not requested'));
        }

        $res = $this->dpdManager->printDPDLabels(null,$dpdNumbers);
        if ($res['error'] === true) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $res['message']));
        }

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Success"), "message" => $this->translator->trans("Labels printed successfully")));
    }

    /**
     * @Route("/dpd_parcel/print_dpd_manifest", name="print_dpd_manifest")
     * @Method("POST")
     */
    public function printDpdManifestAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["type"]) || empty($p["type"]) || $p['type'] == '0') {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("List type field is empty")));
        }

        if (!isset($p["date"]) || empty($p["date"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("Date is empty")));
        }

        if (empty($this->dpdManager)) {
            $this->dpdManager = $this->container->get("dpd_manager");
        }

        $null = null;
        $res = $this->dpdManager->getDPDPdf($p, $null, 'manifest');

        if ($res['error'] === true) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans($res['message'])));
        }

       return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Success"), "pdf" => base64_encode($res["result"]), "type" => $p['type']));
    }

    /**
     * @Route("/dpd_parcel/redirect_dpd_tracking_status", name="redirect_dpd_tracking_status")
     * @Method ("POST")
     */
    public
    function redirectDpdTrackingStatus(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error"), "message" => $this->translator->trans("Missing id")));
        }

        if (empty($this->dpdManager)) {
            $this->dpdManager = $this->container->get("dpd_manager");
        }

        /** @var DpdParcelNumbersEntity $dpdParcelNumber */
        $dpdParcelNumber = $this->dpdManager->getDPDEntityById($p["id"], 'dpd_parcel_numbers');

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Success"), "url" => 'https://tracking.dpd.de/status/hr_HR/parcel/' . $dpdParcelNumber->getDpdParcelNumber()));
    }
}
