<?php

namespace ToursBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use ToursBusinessBundle\Entity\TourEntity;
use ToursBusinessBundle\Entity\TourTipEntity;
use ToursBusinessBundle\Managers\ToursManager;
use Symfony\Component\HttpFoundation\Request;

class TourController extends AbstractController
{
    /**@var ToursManager $toursManager */
    protected $toursManager;

    protected function initialize()
    {
        parent::initialize();
        $this->toursManager = $this->container->get('tours_manager');
    }

    /**
     * @Route("/tour/get_tips", name="tour_get_tips")
     * @Method("POST")
     */
    public function tourGetTipsAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if ((!isset($p["id"]) || empty($p["id"])) && $p["id"] != 0) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing tour ID")));
        }
        if (!isset($p["url"]) || empty($p["url"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing tour url")));
        }

        $tips = $this->toursManager->getTipsByTourAndUrl($p["id"], $p["url"]);

        if (empty($tips)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("No tips found")));
        }

        /** @var TourTipEntity $next */
        $next = $this->toursManager->getNextTip(end($tips));

        $ret = [
            "next_page" => empty($next) ? null : $next->getUrl(),
            "tips" => [],
        ];

        /** @var TourTipEntity $tip */
        foreach ($tips as $tip) {
            $ret["tips"][] = [
                "id" => $tip->getId(),
                "name" => $tip->getName(),
                "body" => $tip->getBody(),
                "selector" => $tip->getSelector(),
                "next_label" => $this->translator->trans("Next"),
                "next_page_label" => $this->translator->trans("Continue to next page"),
                "close_label" => $this->translator->trans("End tour"),
            ];
        }

        $this->setStartTourSession($request, $p["id"]);

        return new JsonResponse(array('error' => false, 'data' => $ret));
    }

    /**
     * @Route("/tour/start", name="tour_start")
     * @Method("POST")
     */
    public function tourStartAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Missing tour ID")));
        }

        /** @var TourEntity $tour */
        $tour = $this->toursManager->getTourById($p["id"]);

        if (empty($tour)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("Tour not found")));
        }

        $tips = $tour->getTips();

        if (empty($tips)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans("No tour tips")));
        }

        /** @var TourTipEntity $firstTip */
        $firstTip = $tips[0];

        $this->setStartTourSession($request, $p["id"]);

        return new JsonResponse(array('error' => false, 'redirect_url' => $firstTip->getUrl()));
    }

    /**
     * @Route("/tour/stop", name="tour_stop")
     * @Method("POST")
     */
    public function tourStopAction(Request $request)
    {
        $this->initialize();

        $session = $request->getSession();
        $session->remove("tour_running");

        return new JsonResponse(array('error' => false));
    }

    /**
     * @param $request
     * @param $tourId
     */
    private function setStartTourSession($request, $tourId)
    {
        $session = $request->getSession();
        $session->set("tour_running", $tourId);
    }
}
