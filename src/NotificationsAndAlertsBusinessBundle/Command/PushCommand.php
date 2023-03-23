<?php

// php bin/console notifications:push send

namespace NotificationsAndAlertsBusinessBundle\Command;

use AppBundle\Managers\HelperManager;
use NotificationsAndAlertsBusinessBundle\Managers\NotificationManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class PushCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName("notifications:push")
            ->AddArgument("type", InputArgument::REQUIRED, " function ");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * Start new session
         */
        $request = new Request();
        if (!empty($request->getSession())) {
            $request->getSession()->invalidate();
        }

        /** @var HelperManager $helperManager */
        $helperManager = $this->getContainer()->get("helper_manager");

        $request = new Request();
        $helperManager->loginAnonymus($request, "system");
        /**
         * End start new session
         */

        $func = $input->getArgument('type');
        if ($func == "send") {
            // Send all push notifications

            /** @var NotificationManager $notificationManager */
            $notificationManager = $this->getContainer()->get("notification_manager");

            $notificationManager->sendPushNotifications();
        }

        return false;
    }
}

