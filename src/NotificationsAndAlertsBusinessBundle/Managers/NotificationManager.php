<?php

namespace NotificationsAndAlertsBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\MailManager;
use CrmBusinessBundle\Managers\EmailTemplateManager;
use NotificationsAndAlertsBusinessBundle\Entity\NotificationEntity;
use NotificationsAndAlertsBusinessBundle\Entity\NotificationTypeEntity;
use NotificationsAndAlertsBusinessBundle\PushProviders\Firebase;

class NotificationManager extends AbstractBaseManager
{
    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var EmailTemplateManager $emailTemplateManager */
    protected $emailTemplateManager;
    /** @var  EntityManager $entityManager */
    protected $entityManager;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @param $user
     * @return mixed
     */
    public function getAllNotificationsForUser($user)
    {

        $notificationEntityType = $this->entityManager->getEntityTypeByCode("notification");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("user", "eq", $user->getId()));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("created", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter(
            $notificationEntityType,
            $compositeFilters,
            $sortFilters
        );
    }

    /**
     * @param $user
     * @return mixed
     */
    public function getAllUnreadNotificationsForUser($user)
    {
        $notificationEntityType = $this->entityManager->getEntityTypeByCode("notification");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("user", "eq", $user->getId()));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("or");
        $compositeFilter->addFilter(new SearchFilter("isRead", "nu", null));
        $compositeFilter->addFilter(new SearchFilter("isRead", "eq", 0));

        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("created", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter(
            $notificationEntityType,
            $compositeFilters,
            $sortFilters
        );
    }

    /**
     * @param $user
     * @return mixed
     * @throws \Exception
     */
    public function getNotificationsForUser($user)
    {

        //default get all unread or last 24h limit 20
        //SETTING_KEY notification_header_days
        $defaultNumberOfDays = 1;

        $from_date = new \DateTime();
        $from_date->modify("-{$defaultNumberOfDays} day");

        $notificationEntityType = $this->entityManager->getEntityTypeByCode("notification");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("user", "eq", $user->getId()));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("created", "gt", $from_date->format("Y-m-d H:i:s")));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("created", "desc"));

        $notifications1 = $this->entityManager->getEntitiesByEntityTypeAndFilter(
            $notificationEntityType,
            $compositeFilters,
            $sortFilters
        );

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("user", "eq", $user->getId()));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("or");
        $compositeFilter->addFilter(new SearchFilter("isRead", "nu", null));
        $compositeFilter->addFilter(new SearchFilter("isRead", "eq", 0));

        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("created", "desc"));

        $notifications2 = $this->entityManager->getEntitiesByEntityTypeAndFilter(
            $notificationEntityType,
            $compositeFilters,
            $sortFilters
        );

        $notificationsTmp = array();

        if (!empty($notifications1)) {
            /** @var NotificationEntity $notification1 */
            foreach ($notifications1 as $notification1) {
                $notificationsTmp[$notification1->getId()] = $notification1;
            }
        }

        if (!empty($notifications2)) {
            /** @var NotificationEntity $notification2 */
            foreach ($notifications2 as $notification2) {
                $notificationsTmp[$notification2->getId()] = $notification2;
            }
        }

        if (!empty($notificationsTmp)) {
            usort($notificationsTmp, array($this, 'sortNotifications'));
        }

        return $notificationsTmp;
    }

    private static function sortNotifications($a, $b)
    {
        return $a->getCreated()->getTimestamp() > $b->getCreated()->getTimestamp();
    }

    /**
     * @param $user
     * @param int $limit
     * @return array|bool
     * @throws \Exception
     */
    public function getHeaderNotifications($user, $limit = 20)
    {

        $notifications = $this->getNotificationsForUser($user);
        $total_unread = 0;

        if (empty($notifications)) {
            return true;
        }

        $notifications = array_slice($notifications, 0, $limit);

        $now = new \DateTime();

        foreach ($notifications as $key => $notification) {
            $interval = $now->diff($notification->getCreated());

            $total_seconds = $interval->days * 24 * 60 * 60;
            $total_seconds += $interval->h * 60 * 60;
            $total_seconds += $interval->i * 60;
            $total_seconds += $interval->s;

            if ($total_seconds < 60) {
                $notifications[$key]->setSufixText($total_seconds . " " . $this->translator->trans("sec"));
            } elseif ($total_seconds / 60 < 60) {
                $notifications[$key]->setSufixText(ceil($total_seconds / 60) . " " . $this->translator->trans("min"));
            } elseif ($total_seconds / 3600 < 60) {
                $notifications[$key]->setSufixText(ceil($total_seconds / 3600) . " " . $this->translator->trans("hours"));
            } else {
                $notifications[$key]->setSufixText(ceil($total_seconds / 216000) . " " . $this->translator->trans("days"));
            }

            if ($notification->getIsRead() == 0) {
                $total_unread++;
            }
        }

        return array("notifications" => $notifications, "total_unread" => $total_unread);
    }

    /**
     * @param $id
     * @param $user
     * @return |null
     */
    public function getNotificationByIdAndUser($id, $user)
    {

        $notificationEntityType = $this->entityManager->getEntityTypeByCode("notification");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("id", "eq", $id));
        $compositeFilter->addFilter(new SearchFilter("user", "eq", $user->getId()));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($notificationEntityType, $compositeFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getNotificationById($id)
    {

        $notificationEntityType = $this->entityManager->getEntityTypeByCode("notification");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("id", "eq", $id));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($notificationEntityType, $compositeFilters);
    }

    /**
     * @param $entity
     * @param NotificationTypeEntity $notificationTypeEntity
     * @param $user
     * @return |null
     */
    public function getNotificationByEntityNotificationTypeAndUser(
        $entity,
        NotificationTypeEntity $notificationTypeEntity,
        $user
    )
    {

        $notificationEntityType = $this->entityManager->getEntityTypeByCode("notification");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("relatedId", "eq", $entity->getId()));
        $compositeFilter->addFilter(
            new SearchFilter("relatedEntityType", "eq", $entity->getEntityType()->getEntityTypeCode())
        );
        $compositeFilter->addFilter(new SearchFilter("typeId", "eq", $notificationTypeEntity->getId()));
        $compositeFilter->addFilter(new SearchFilter("user", "eq", $user->getId()));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("created", "desc"));

        return $this->entityManager->getEntityByEntityTypeAndFilter(
            $notificationEntityType,
            $compositeFilters,
            $sortFilters
        );
    }

    /**
     * @param NotificationEntity $notificationEntity
     * @return bool
     */
    public function markAsRead(NotificationEntity $notificationEntity)
    {

        $notificationEntity->setIsRead(1);

        $this->entityManager->saveEntityWithoutLog($notificationEntity);

        return true;
    }

    /**
     * @param $user
     * @return bool
     * @throws \Exception
     */
    public function markAllAsRead($user)
    {

        $notifications = $this->getNotificationsForUser($user);

        if (empty($notifications)) {
            return true;
        }

        foreach ($notifications as $notification) {
            if ($notification->getIsRead() == 0) {
                $this->markAsRead($notification);
            }
        }

        return true;
    }

    /**
     * @param NotificationEntity $notificationEntity
     * @return mixed
     */
    public function generateUrlForNotification(NotificationEntity $notificationEntity)
    {

        return $this->container->get('router')->generate(
            'page_view',
            array(
                "url" => $notificationEntity->getRelatedEntityType(),
                "type" => "view",
                "id" => $notificationEntity->getRelatedId(),
            )
        );
    }

    /**
     * @param $relatedEntity
     * @param NotificationTypeEntity $notificationTypeEntity
     * @param $name
     * @param $user
     * @param bool $avoidCheckExists
     * @return bool
     */
    public function createNotification(
        $relatedEntity,
        NotificationTypeEntity $notificationTypeEntity,
        $name,
        $user,
        $avoidCheckExists = false,
        $onlyNotification = false
    )
    {

        if (empty($user)) {
            return false;
        }

        if (!$avoidCheckExists) {
            $notification = $this->getNotificationByEntityNotificationTypeAndUser(
                $relatedEntity,
                $notificationTypeEntity,
                $user
            );
            if (!empty($notification)) {
                return true;
            }
        }

        /** @var NotificationEntity $notification */
        $notification = $this->entityManager->getNewEntityByAttributSetName("notification");

        $notification->setRelatedEntityType($relatedEntity->getEntityType()->getEntityTypeCode());
        $notification->setRelatedId($relatedEntity->getId());
        $notification->setType($notificationTypeEntity);
        $notification->setName($name);
        $notification->setUser($user);
        $notification->setOnlyNotification($onlyNotification);

        $this->entityManager->saveEntity($notification);

        return false;
    }

    /**
     * @param NotificationEntity $notificationEntity
     * @return bool
     */
    public function createNotificationEmail(NotificationEntity $notificationEntity)
    {
        if (empty($notificationEntity->getType()->getEmailTemplate())) {
            return false;
        }

        if (empty($this->emailTemplateManager)) {
            $this->emailTemplateManager = $this->container->get("email_template_manager");
        }

        if (empty($this->mailManager)) {
            $this->mailManager = $this->container->get("mail_manager");
        }

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $relatedEntityType = $this->entityManager->getEntityTypeByCode($notificationEntity->getRelatedEntityType());
        $relatedEntity = $this->entityManager->getEntityByEntityTypeAndId($relatedEntityType, $notificationEntity->getRelatedId());

        $email = $this->emailTemplateManager->renderEmailTemplate($relatedEntity, $notificationEntity->getType()->getEmailTemplate());

        $transactionEmail = $this->mailManager->sendEmail(array("email" => $notificationEntity->getUser()->getEmail(), "name" => $notificationEntity->getUser()->getFullName()), null, null, null, $email["subject"], "", null, array(), $email["content"], array());

        //TODO zasto se ovdje sejvao email na notification

        return true;
    }

    /**
     * @param $notificationTypeCode
     * @return |null
     */
    public function getNotificationTypeByCode($notificationTypeCode)
    {
        $notificationEmailEntityType = $this->entityManager->getEntityTypeByCode("notification_type");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $notificationTypeCode));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($notificationEmailEntityType, $compositeFilters);
    }

    /**
     * is_read != 1 - Not sent
     * device_id != 1 - Users device ID
     * @return array
     */
    public function getAllUnsentPushNotifications()
    {
        $notificationEntityType = $this->entityManager->getEntityTypeByCode("notification");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("deviceId", "nn"));
        $compositeFilter->addFilter(new SearchFilter("type.code", "eq", "push"));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("or");
        $compositeFilter->addFilter(new SearchFilter("isRead", "nu", null));
        $compositeFilter->addFilter(new SearchFilter("isRead", "ne", 1));

        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("created", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter(
            $notificationEntityType,
            $compositeFilters,
            $sortFilters
        );
    }

    /**
     * @return void
     */
    public function sendPushNotifications()
    {
        $notifications = $this->getAllUnsentPushNotifications();

        /** @var Firebase $provider */
        $provider = $this->container->get("push_provider");

        /** @var NotificationEntity $notification */
        foreach ($notifications as $notification) {
            $provider->sendMessage($notification);
        }
    }

    /**
     * @return NotificationEntity
     */
    public function generatePushNotification($title, $body, $deviceId)
    {
        /** @var NotificationEntity $notification */
        $notification = $this->entityManager->getNewEntityByAttributSetName("notification");

        $notification->setType($this->getNotificationTypeByCode("push"));
        $notification->setName($title);
        $notification->setContent($body);
        $notification->setDeviceId($deviceId);
        $notification->setIsRead(0);

        $this->entityManager->saveEntity($notification);

        return $notification;
    }
}
