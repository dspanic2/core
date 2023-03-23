<?php

namespace NotificationsAndAlertsBusinessBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use NotificationsAndAlertsBusinessBundle\Entity\NotificationEntity;
use NotificationsAndAlertsBusinessBundle\Entity\NotificationTypeEntity;
use NotificationsAndAlertsBusinessBundle\Managers\NotificationManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class NotificationListener implements ContainerAwareInterface
{
    /** @var NotificationManager $notificationManager */
    protected $notificationManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     */
    public function onNotificationPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var NotificationEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "notification") {

            /** @var NotificationTypeEntity $notificationType */
            $notificationType = $entity->getType();

            $entity->setIsRead(0);

            if ($notificationType->getHasUrl() == 1) {

                if (empty($this->notificationManager)) {
                    $this->notificationManager = $this->container->get("notification_manager");
                }

                $url = $this->notificationManager->generateUrlForNotification($entity);

                $entity->setUrl($url);
            }
        }
    }

    /**
     * @param EntityCreatedEvent $event
     */
    public function onNotificationCreated(EntityCreatedEvent $event)
    {
        /** @var NotificationEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "notification") {

            /** @var NotificationTypeEntity $notificationType */
            $notificationType = $entity->getType();

            if (!empty($notificationType->getEmailTemplate()) && !$entity->getOnlyNotification()) {

                if (empty($this->notificationManager)) {
                    $this->notificationManager = $this->container->get("notification_manager");
                }

                $this->notificationManager->createNotificationEmail($entity);
            }
        }
    }
}