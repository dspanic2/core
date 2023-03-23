<?php

namespace CrmBusinessBundle\Controller;

use ScommerceBusinessBundle\Abstracts\AbstractScommerceController;
use ScommerceBusinessBundle\Managers\CommentsManager;
use ScommerceBusinessBundle\Managers\RouteManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


class CommentsRatingController extends AbstractScommerceController
{
    /**@var RouteManager $routeManager */
    protected $routeManager;
    /** @var CommentsManager */
    protected $commentsManager;

    protected function initialize($request = null)
    {
        parent::initialize();
        $this->routeManager = $this->getContainer()->get('route_manager');
    }


    /**
     * @Route("/comment/mass_approve", name="mass_approve_comment")
     * @Method("POST")
     */
    public function massApproveCommentAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"]) || !isset($p["items"]["s_entity_comment"]) || empty($p["items"]["s_entity_comment"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Comment ids are missing')));
        }

        if (empty($this->commentsManager)) {
            $this->commentsManager = $this->getContainer()->get("comments_manager");
        }

        $this->commentsManager->approveComments($p["items"]["s_entity_comment"]);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Comments approved')));
    }

    /**
     * @Route("/rating/mass_approve", name="mass_approve_rating")
     * @Method("POST")
     */
    public function massApproveRatingAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["items"]) || empty($p["items"]) || !isset($p["items"]["s_entity_rating"]) || empty($p["items"]["s_entity_rating"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Rating ids are missing')));
        }

        if (empty($this->commentsManager)) {
            $this->commentsManager = $this->getContainer()->get("comments_manager");
        }

        $this->commentsManager->approveRatings($p["items"]["s_entity_rating"]);

        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Ratings approved')));
    }
}
