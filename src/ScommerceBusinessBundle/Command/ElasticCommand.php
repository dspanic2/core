<?php

// php bin/console elastic:helper create_index product
// php bin/console elastic:helper create_index product_group
// php bin/console elastic:helper create_index brand
// php bin/console elastic:helper create_index blog_post

// php bin/console elastic:helper reindex product 3
// php bin/console elastic:helper reindex product_group 3
// php bin/console elastic:helper reindex brand 3
// php bin/console elastic:helper reindex blog_post 3


// php bin/console elastic:helper search "smasung led" 3 product
// php bin/console elastic:helper search "smasung" 3 brand
// php bin/console elastic:helper search "susilo za ves" 3 product_group

// php bin/console elastic:helper search_products "smasung led" 3
// php bin/console elastic:helper search_blog_posts "slatka voda" 3

namespace ScommerceBusinessBundle\Command;

use AppBundle\Managers\HelperManager;
use ScommerceBusinessBundle\Managers\AlgoliaManager;
use Monolog\Logger;
use ScommerceBusinessBundle\Managers\DefaultScommerceManager;
use ScommerceBusinessBundle\Managers\ElasticSearchManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class ElasticCommand extends ContainerAwareCommand
{
    /** @var ElasticSearchManager $elasticSearchManager */
    protected $elasticSearchManager;
    /** @var DefaultScommerceManager $scommerceManager */
    protected $scommerceManager;

    protected function configure()
    {
        $this->setName("elastic:helper")
            ->SetDescription("Elastic search")
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

        $func = $input->getArgument('type');
        if($func == "create_index"){

            $arg1 = $input->getArgument("arg1");
            if (empty($arg1)) {
                throw new \Exception("Missing index name to regenerate");
            }

            if(!isset($_ENV["INDEX_PREFIX"])){
                throw new \Exception("Missing index prefix");
            }

            if(!isset($_ENV[strtoupper($arg1)."_COLUMNS"])){
                throw new \Exception("Missing columns to index");
            }

            if(empty($this->elasticSearchManager)){
                $this->elasticSearchManager = $this->getContainer()->get("elastic_search_manager");
            }

            $this->elasticSearchManager->createIndex($arg1);

            return true;
        }
        elseif ($func == "reindex") {

            $arg1 = $input->getArgument("arg1");
            if (empty($arg1)) {
                throw new \Exception("Missing entity type code");
            }
            if(!isset($_ENV[strtoupper($arg1)."_COLUMNS"])){
                throw new \Exception("Missing columns to index");
            }

            $arg2 = $input->getArgument("arg2");
            if (empty($arg1)) {
                $arg2 = $_ENV["DEFAULT_STORE_ID"];
            }

            if(empty($this->elasticSearchManager)){
                $this->elasticSearchManager = $this->getContainer()->get("elastic_search_manager");
            }

            $this->elasticSearchManager->reindex($arg1,$arg2);

        }
        elseif ($func == "search") {

            $arg1 = $input->getArgument("arg1");
            if (empty($arg1)) {
                throw new \Exception("Missing term");
            }

            $arg2 = $input->getArgument("arg2");
            if (empty($arg1)) {
                $arg2 = $_ENV["DEFAULT_STORE_ID"];
            }

            $arg3 = $input->getArgument("arg3");
            if (empty($arg3)) {
                throw new \Exception("Missing entity type code");
            }

            if(empty($this->elasticSearchManager)){
                $this->elasticSearchManager = $this->getContainer()->get("elastic_search_manager");
            }

            $res = $this->elasticSearchManager->getSearchResults($arg1, $arg2, $arg3);

            dump($res);
        }
        elseif ($func == "search_products") {

            $arg1 = $input->getArgument("arg1");
            if (empty($arg1)) {
                throw new \Exception("Missing term");
            }

            $arg2 = $input->getArgument("arg2");
            if (empty($arg1)) {
                $arg2 = $_ENV["DEFAULT_STORE_ID"];
            }

            if(empty($this->scommerceManager)){
                $this->scommerceManager = $this->getContainer()->get("scommerce_manager");
            }

            $res = $this->scommerceManager->elasticSearchProducts($arg1, $arg2);

            dump($res);
        }
        elseif ($func == "search_blog_posts") {

            $arg1 = $input->getArgument("arg1");
            if (empty($arg1)) {
                throw new \Exception("Missing term");
            }

            $arg2 = $input->getArgument("arg2");
            if (empty($arg1)) {
                $arg2 = $_ENV["DEFAULT_STORE_ID"];
            }

            if(empty($this->scommerceManager)){
                $this->scommerceManager = $this->getContainer()->get("scommerce_manager");
            }

            $res = $this->scommerceManager->elasticSearchBlogPosts($arg1, $arg2);

            dump($res);
        }
        else {
            throw new \Exception("Command type missing: " . json_encode($input->getArguments()));
        }
        return false;
    }
}