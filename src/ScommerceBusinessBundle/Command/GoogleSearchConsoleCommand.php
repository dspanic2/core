<?php

// php bin/console google_search_console:cmd run_s_route_not_found_indexed
// php bin/console google_search_console:cmd run_s_route_indexed
// php bin/console google_search_console:cmd refresh_google_token
// php bin/console google_search_console:cmd reset_google_api_limit
// php bin/console google_search_console:cmd list_sitemaps 1

namespace ScommerceBusinessBundle\Command;

use AppBundle\Managers\HelperManager;
use IntegrationBusinessBundle\Managers\GoogleApiManager;
use Monolog\Logger;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Managers\TrackingManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class GoogleSearchConsoleCommand extends ContainerAwareCommand
{
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var GoogleApiManager $googleApiManager */
    protected $googleApiManager;

    protected function configure()
    {
        $this->setName('google_search_console:cmd')
            ->SetDescription(' description of what the command ')
            ->AddArgument('type', InputArgument :: OPTIONAL, ' which function ')
            ->AddArgument('arg1', InputArgument :: OPTIONAL, ' which arg1 ')
            ->AddArgument('arg2', InputArgument :: OPTIONAL, ' which arg2 ');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
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

        if ($func == "run_s_route_not_found_indexed") {

            $arg1 = explode(",",$arg1);

            if(empty($this->routeManager)){
                $this->routeManager = $this->getContainer()->get("route_manager");
            }

            $this->routeManager->processSRoute404Redirect($arg1);

        }
        elseif ($func == "run_s_route_indexed") {

            $arg1 = explode(",",$arg1);

            if(empty($this->routeManager)){
                $this->routeManager = $this->getContainer()->get("route_manager");
            }

            $this->routeManager->processSRoute($arg1);

        }
        elseif ($func == "refresh_google_token") {

            if(empty($this->googleApiManager)){
                $this->googleApiManager = $this->getContainer()->get("google_api_manager");
            }

            $this->googleApiManager->initializeConnection();

            $this->googleApiManager->refreshGoogleToken();
        }
        elseif ($func == "reset_google_api_limit") {

            if(empty($this->googleApiManager)){
                $this->googleApiManager = $this->getContainer()->get("google_api_manager");
            }

            $this->googleApiManager->resetGoogleApiLimit();
        }
        elseif ($func == "list_sitemaps") {

            if(empty($this->googleApiManager)){
                $this->googleApiManager = $this->getContainer()->get("google_api_manager");
            }

            if(empty($this->routeManager)){
                $this->routeManager = $this->getContainer()->get("route_manager");
            }

            $website = $this->routeManager->getWebsiteById($arg1);

            $this->googleApiManager->initializeConnection();

            $ret = $this->googleApiManager->listSitemaps($website);
            dump($ret->getSitemap());
            die;
        }
        else {
            throw new \Exception("Command type missing: " . json_encode($input->getArguments()));
        }

        return false;
    }
}
