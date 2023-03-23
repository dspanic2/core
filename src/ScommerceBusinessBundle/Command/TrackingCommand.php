<?php

// php bin/console track:cmd clean_shape_track 1
// php bin/console track:cmd update_shape_track_table

namespace ScommerceBusinessBundle\Command;

use AppBundle\Managers\HelperManager;
use Monolog\Logger;
use ScommerceBusinessBundle\Managers\TrackingManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class TrackingCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('track:cmd')
            ->SetDescription(' description of what the command ')
            ->AddArgument('type', InputArgument :: OPTIONAL, ' which function ')
            ->AddArgument('arg1', InputArgument :: OPTIONAL, ' which arg1 ')
            ->AddArgument('arg2', InputArgument :: OPTIONAL, ' which arg2 ');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**@var Logger $logger */
        $logger = $this->getContainer()->get('logger');

        /** @var HelperManager $helperManager */
        $helperManager = $this->getContainer()->get("helper_manager");

        $request = new Request();
        $helperManager->loginAnonymus($request, "system");

        /**
         * Check which function
         */
        $func = $input->getArgument('type');
        if (empty($func)) {
            throw new \Exception('Function not defined');
        }

        $arg1 = $input->getArgument('arg1');
        $arg2 = $input->getArgument('arg2');

        if ($func == "clean_shape_track") {

            /**@var TrackingManager $trackingManager */
            $trackingManager = $this->getContainer()->get("tracking_manager");

            $trackingManager->cleanShapeTrack($arg1);

        } elseif ($func == "update_shape_track_table") {

            /**@var TrackingManager $trackingManager */
            $trackingManager = $this->getContainer()->get("tracking_manager");

            $trackingManager->updateShapeTrackTable();

        } else {
            throw new \Exception("Command type missing: " . json_encode($input->getArguments()));
        }

        return false;
    }
}
