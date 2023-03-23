<?php

// php bin/console import_manual:run run_queue
// php bin/console import_manual:run run_watch_dog
// php bin/console import_manual:run run_import_by_id 64 --debug=1

namespace AppBundle\Command;

use AppBundle\Managers\HelperManager;
use AppBundle\Managers\ImportManualManager;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Console\Input\InputOption;

class ImportManualCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName("import_manual:run")
            ->setDescription("helper functions")
            ->addArgument("type", InputArgument::REQUIRED, "function")
            ->addArgument("arg1", InputArgument::OPTIONAL, "arg1")
            ->addOption("debug", NULL, InputOption::VALUE_REQUIRED, "debug", 0);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return false
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Logger $logger */
        $logger = $this->getContainer()->get("logger");

        /** @var HelperManager $helperManager */
        $helperManager = $this->getContainer()->get("helper_manager");

        $request = new Request();
        $helperManager->loginAnonymus($request, "system");

        /**
         * Check which function
         */
        $func = $input->getArgument("type");
        $arg1 = $input->getArgument("arg1");

        if ($func == "run_queue") {

            /** @var ImportManualManager $importManager */
            $importManager = $this->getContainer()->get("import_manual_manager");
            $importManager->runQueue();

        } else if ($func == "run_import_by_id") {

            if (empty($arg1)) {
                throw new \Exception("Argument missing");
            }

            /** @var ImportManualManager $importManager */
            $importManager = $this->getContainer()->get("import_manual_manager");
            $importManager->runQueue($arg1, (bool)$input->getOption("debug"));

        } else if ($func == "run_watch_dog") {

            /** @var ImportManualManager $importManager */
            $importManager = $this->getContainer()->get("import_manual_manager");
            $importManager->runWatchDog();

        } else {
            throw new \Exception("Command type missing: " . json_encode($input->getArguments()));
        }

        return false;
    }
}
