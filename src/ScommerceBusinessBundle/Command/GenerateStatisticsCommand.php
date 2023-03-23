<?php

##Jobs and test jobs

// php bin/console statistics:run regenerate_statistics //todo 15min
// php bin/console statistics:run regenerate_statistics 2023-01-01H00:10:20

namespace ScommerceBusinessBundle\Command;

use AppBundle\Managers\HelperManager;
use Monolog\Logger;
use ScommerceBusinessBundle\Managers\StatisticsManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class GenerateStatisticsCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('statistics:run')
            ->SetDescription('Helper functions')
            ->AddArgument('type', InputArgument :: OPTIONAL, ' which function ')
            ->AddArgument('arg1', InputArgument :: OPTIONAL, ' arg1 ')
            ->AddArgument('arg2', InputArgument :: OPTIONAL, ' arg2 ')
            ->AddArgument('arg3', InputArgument :: OPTIONAL, ' arg3 ')
            ->AddArgument('arg4', InputArgument :: OPTIONAL, ' arg4 ');
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

        /**@var Logger $logger */
        $logger = $this->getContainer()->get('logger');

        $func = $input->getArgument('type');
        if ($func == "regenerate_statistics") {

            /** @var StatisticsManager $manager */
            $manager = $this->getContainer()->get("statistics_manager");

            $fromDateTime = $input->getArgument('arg1');
            if (empty($fromDateTime)) {
                $now = new \DateTime();
                $now->modify('-2 hour');

                $fromDateTime = $now->format("Y-m-d H:i:s");
            } else {
                $fromDateTime = str_ireplace("H", " ", $fromDateTime);
            }

            $manager->regenerateStatistics($fromDateTime);
        } else {
            throw new \Exception("Command type missing: " . json_encode($input->getArguments()));
        }

        return false;
    }


}

