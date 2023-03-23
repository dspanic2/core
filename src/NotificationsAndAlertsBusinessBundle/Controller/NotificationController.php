<?php

namespace NotificationsAndAlertsBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\EntityManager;
use NotificationsAndAlertsBusinessBundle\Managers\NotificationManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends AbstractController
{
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /** @var NotificationManager $notificationManager */
    protected $notificationManager;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->notificationManager = $this->getContainer()->get("notification_manager");
        $this->user = $this->helperManager->getCurrentCoreUser();
    }

    /**
     * @Route("/notifications/get_for_user", name="get_notifications_for_user")
     * @Method("POST")
     */
    public function getHeaderNotificationsForUserAction(Request $request)
    {

        $this->initialize();

        $data = $this->notificationManager->getHeaderNotifications($this->user);

        if (empty($data)) {
            return new Response();
        }

        $html = $this->renderView('NotificationsAndAlertsBusinessBundle:Includes:notification_header.html.twig', array("data" => $data));

        return new Response($html);
    }

    /**
     * @Route("/notifications/mark_all_read", name="mark_all_read")
     * @Method("POST")
     */
    public function markAllReadAction(Request $request)
    {

        $this->initialize();

        if ($this->notificationManager->markAllAsRead($this->user)) {
            return new JsonResponse(array('error' => false));
        }

        return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Notification does not exist')));
    }

    /**
     * @Route("/notifications/mark_as_read", name="mark_as_read")
     * @Method("POST")
     */
    public function markAsReadAction(Request $request)
    {

        $p = $_POST;

        $this->initialize();

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Please select notification')));
        }

        $notification = $this->notificationManager->getNotificationByIdAndUser($p["id"], $this->user);

        if (empty($notification)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Notification does not exist')));
        }

        if ($this->notificationManager->markAsRead($notification)) {
            return new JsonResponse(array('error' => false));
        }

        return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Error saving notification')));
    }
}