<?php

namespace ScommerceBusinessBundle\FrontBlocks;

use AppBundle\Entity\CoreUserEntity;
use AppBundle\Managers\HelperManager;
use NotificationsAndAlertsBusinessBundle\Managers\NotificationManager;
use ScommerceBusinessBundle\Abstracts\AbstractBaseFrontBlock;

class DashboardRecentNoticesBlock extends AbstractBaseFrontBlock
{
    /** @var HelperManager $helperManager */
    protected $helperManager;
    /** @var NotificationManager $notificationManager */
    protected $notificationManager;

    public function GetBlockData()
    {
        if (stripos($this->blockData["block"]->getClass(), "dash_all_notices") !== false) {
            $this->blockData["model"]["go_to_all"] = false;
        } else {
            $this->blockData["model"]["go_to_all"] = true;
        }

        if (empty($this->helperManager)) {
            $this->helperManager = $this->container->get("helper_manager");
        }

        /** @var CoreUserEntity $user */
        $user = $this->helperManager->getCurrentCoreUser();

        if (empty($this->notificationManager)) {
            $this->notificationManager = $this->container->get("notification_manager");
        }

        $this->blockData["model"]["notifications"] = $this->notificationManager->getAllNotificationsForUser($user);

        if (!empty($this->blockData["model"]["notifications"]) && $this->blockData["block"]->getClass() != "dash_all_notices") {
            $this->blockData["model"]["notifications"] = array_slice(
                $this->blockData["model"]["notifications"],
                0,
                10,
                true
            );
        }

        return $this->blockData;
    }

    /** Nije potrebno dok se ne uvedu zasebni admin template za pojedine blokove */
    /*public function GetBlockSetingsTemplate()
    {
        return 'ScommerceBusinessBundle:BlockSettings:'.$this->block->getType().'.html.twig';
    }*/

    /*public function GetBlockSetingsData()
    {
        return array(
            'entity' => $this->block,
        );
    }*/
}
