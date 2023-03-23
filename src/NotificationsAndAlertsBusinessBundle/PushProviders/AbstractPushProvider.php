<?php

namespace NotificationsAndAlertsBusinessBundle\PushProviders;

use Monolog\Logger;
use NotificationsAndAlertsBusinessBundle\Entity\NotificationEntity;
use NotificationsAndAlertsBusinessBundle\Managers\NotificationManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use AppBundle\Entity\UserEntity;
use AppBundle\Managers\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AbstractPushProvider implements ContainerAwareInterface
{
    protected $container;
    protected $user;

    /** @var  EntityManager $entityManager */
    protected $entityManager;
    /** @var NotificationManager $notificationManager */
    protected $notificationManager;
    /**@var Logger $logger */
    protected $logger;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function setUser(UserEntity $user)
    {
        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function initialize()
    {
        $this->entityManager = $this->container->get("entity_manager");
        $this->notificationManager = $this->container->get("notification_manager");
        $this->logger = $this->container->get('logger');
    }

    /**
     * @param NotificationEntity $notification
     * @return void
     */
    public function onNotificationSent(NotificationEntity $notification)
    {
        $notification->setIsRead(1);
        $this->entityManager->saveEntityWithoutLog($notification);
    }
}
