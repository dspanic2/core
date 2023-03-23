<?php

// php bin/console taskhelper:function generate_notifications

namespace TaskBusinessBundle\Command;

use AppBundle\Managers\HelperManager;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use TaskBusinessBundle\Managers\TaskManager;

class TaskHelperCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName("taskhelper:function")
            ->SetDescription("Helper functions")
            ->AddArgument("type", InputArgument::OPTIONAL, " which function ");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * Start new session for import
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
         * End start new session for import
         */

        /** @var Logger $logger */
        $logger = $this->getContainer()->get("logger");

        /**
         * Check which function
         */
        $func = $input->getArgument("type");
        if (empty($func)) {
            throw new \Exception("Function not defined");
        }

        /** @var TaskManager $taskManager */
        $taskManager = $this->getContainer()->get("task_manager");

        $func = $input->getArgument("type");
        if ($func == "generate_notifications") {
            $taskManager->generateNotificationsForTasks();
        } else {
            throw new \Exception("Command type missing: " . json_encode($input->getArguments()));
        }

        return false;
    }
}