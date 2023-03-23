<?php

// php bin/console algolia:helper reindex 3
// php bin/console algolia:helper search husqvarna

namespace ScommerceBusinessBundle\Command;

use AppBundle\Managers\HelperManager;
use ScommerceBusinessBundle\Managers\AlgoliaManager;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class AlgoliaCommand extends ContainerAwareCommand
{
    /** @var AlgoliaManager $algoliaManager */
    protected $algoliaManager;

    protected function configure()
    {
        $this->setName("algolia:helper")
            ->SetDescription("Algolia search")
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
        if ($func == "reindex") {

            $arg1 = $input->getArgument("arg1");
            if (empty($arg1)) {
                $arg1 = $_ENV["DEFAULT_STORE_ID"];
            }

            /** @var AlgoliaManager $algoliaManager */
            $algoliaManager = $this->getContainer()->get("algolia_manager");

            $data = $algoliaManager->getAlgoliaRecords($arg1);

            if (!empty($data)) {
                $algoliaManager->createUpdateIndex($_ENV["ALGOLIA_INDEX_NAME"], $data);
            }
        } elseif ($func == "search") {

            $arg1 = $input->getArgument("arg1");
            if (empty($arg1)) {
                echo "Missing term\r\n";
            }

            /** @var AlgoliaManager $algoliaManager */
            $algoliaManager = $this->getContainer()->get("algolia_manager");

            $res = $algoliaManager->getSearchResults($arg1, 3);

            dump($res);
        } else {
            throw new \Exception("Command type missing: " . json_encode($input->getArguments()));
        }
        return false;
    }
}