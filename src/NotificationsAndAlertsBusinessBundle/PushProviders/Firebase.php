<?php

namespace NotificationsAndAlertsBusinessBundle\PushProviders;

use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use NotificationsAndAlertsBusinessBundle\Entity\NotificationEntity;

class Firebase extends AbstractPushProvider
{
    /*
     ADD TO config.yml
    kreait_firebase:
        projects:
            shipshape:
                credentials: "%kernel.root_dir%/../shipshape-f5672-1dfec1cf8b3d.json"
     */


    /** @var Messaging $messaging */
    private $messaging;

    public function initialize()
    {
        parent::initialize();
        $this->messaging = $this->container->get("kreait_firebase.shipshape.messaging");
    }

    public function sendMessage(NotificationEntity $notification, $data = [])
    {
        $message = CloudMessage::withTarget('token', $notification->getDeviceId())
            ->withNotification(Notification::create($notification->getName(), $notification->getContent()))
            ->withData($data);

        try {
            $res = $this->messaging->send($message);
            if (!is_array($res)) {
                throw new \Exception($res);
            }
            $this->onNotificationSent($notification);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

}
