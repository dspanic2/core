<?php

// php bin/console admin:entity clone_entity_type ARG1 ARG2
// php bin/console admin:entity rebuild CrmBusinessBundle
// php bin/console admin:entity clean_entity_log 15
// php bin/console admin:entity test_excel
// php bin/console admin:entity change_bundle entity_type_code new_bundle
// php bin/console admin:entity change_bundle youtube_embed CrmBusinessBundle
// php bin/console admin:entity rebuild_indexes
// php bin/console admin:entity add_uid core_user
// php bin/console admin:entity add_uid role
// php bin/console admin:entity add_uid core_language
// php bin/console admin:entity add_uid country
// php bin/console admin:entity add_uid region
// php bin/console admin:entity add_uid shared_inbox_connection_type 1
// php bin/console admin:entity > ./var/logs/smetlar.sql
// php bin/console admin:entity clear_given_tables product_entity,product_group_entity,product_product_group_link_entity,product_product_link_entity,brand_entity,s_product_attribute_configuration_entity,s_product_attribute_configuration_options_entity,s_product_attributes_link_entity,blog_category_entity,blog_post_entity,account_entity,user_entity,address_entity,contact_entity,order_entity,order_item_entity,quote_entity,facets_entity,facet_attribute_configuration_link_entity
// php bin/console admin:entity fix_json_fields
// php bin/console admin:entity delete_redis_cache_keys
// php bin/console admin:entity clear_backend_cache
// php bin/console admin:entity clean_logs 10
// php bin/console admin:entity transfer_mail_to_sent_emails
// php bin/console admin:entity transfer_all_mail_to_sent_emails
// php bin/console admin:entity add_url_option_to_entity test



namespace AppBundle\Command;

use AppBundle\Managers\AnalyticsManager;
use Symfony\Component\Console\Input\ArrayInput;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\CronJobManager;
use AppBundle\Managers\DatabaseManager;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ExcelManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\ImportManualManager;
use AppBundle\Managers\ShapeCleanerManager;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class EntityCommand extends ContainerAwareCommand
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var AdministrationManager $administrationManager */
    protected $administrationManager;

    protected function configure()
    {
        $this->setName('admin:entity')
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

        if ($func == "add_url_option_to_entity") {

            $entityTypeCode = $arg1;
            if(empty($entityTypeCode)){
                throw new \Exception("Empty entity type code");
            }

            if(empty($this->administrationManager)){
                $this->administrationManager = $this->getContainer()->get("administration_manager");
            }

            try{
                $this->administrationManager->addUrlOptionToEntity($entityTypeCode);
            }
            catch (\Exception $e){
                throw $e;
            }

            print "\r\nTo generate url-s add entity type to sCommerceManager:filterCustomDestinationTypes and run 'php bin/console scommercehelper:function automatically_fix_rutes' \r\nAlso, add event listener (check blog_post_listener) and custom validateDestination in sCommerceManager.\r\nDont forget to add Template type\r\n\r\n";
        }
        elseif ($func == "clone_entity_type") {

            /** @var AdministrationManager $administrationManager */
            $administrationManager = $this->getContainer()->get("administration_manager");

            $administrationManager->cloneEntityType($arg1, $arg2);
        } elseif ($func == "rebuild") {
            if ($arg1 !== "all" && !array_key_exists($arg1, $this->getContainer()->getParameter('kernel.bundles'))) {
                echo "Pass bundle name as an argument or 'all' if you want to rebuild all entities.\n";
                return false;
            }

            /**@var EntityTypeContext $entityTypeContext */
            $entityTypeContext = $this->getContainer()->get("entity_type_context");

            /**@var \AppBundle\Entity\EntityType $entityType */
            $entityTypes = $entityTypeContext->getAllItems();

            $filteredEntityTypes = [];
            foreach ($entityTypes as $entityType) {
                if ($entityType->getBundle() == $arg1 || $arg1 === "all") {
                    $filteredEntityTypes[] = $entityType;
                }
            }

            if (empty($filteredEntityTypes)) {
                echo "No entity types found for bundle $arg1!\n";
                return false;
            }

            /**@var AdministrationManager $administrationManager */
            $administrationManager = $this->getContainer()->get("administration_manager");
            /**@var DatabaseManager $databaseManager */
            $databaseManager = $this->getContainer()->get("database_manager");
            foreach ($filteredEntityTypes as $entityType) {
                echo "Rebuilding entity_type ".$entityType->getEntityTypeCode()."\n";
                $databaseManager->createTableIfDoesntExist($entityType, null);
                $administrationManager->generateDoctrineXML($entityType, true);
                $administrationManager->generateEntityClasses($entityType, true);
            }
        } elseif ($func == "clean_entity_log") {

            /**@var DatabaseManager $databaseManager */
            $databaseManager = $this->getContainer()->get("database_manager");

            $databaseManager->cleanEntityLog($arg1);

        } elseif ($func == "transfer_mail_to_sent_emails") {

            if(empty($this->transactionEmailManager)){
                $this->transactionEmailManager = $this->getContainer()->get("transaction_email_manager");
            }

            $this->transactionEmailManager->transferMailToSentEmails();

            return true;
        }
        elseif ($func == "transfer_all_mail_to_sent_emails") {

            if(empty($this->transactionEmailManager)){
                $this->transactionEmailManager = $this->getContainer()->get("transaction_email_manager");
            }

            $ret = $this->transactionEmailManager->transferMailToSentEmails();
            while ($ret){
                $ret = $this->transactionEmailManager->transferMailToSentEmails();
            }

            return true;
        }
        elseif ($func == "delete_redis_cache_keys") {

            /** @var EntityManager $entityManager */
            $entityManager = $this->getContainer()->get("entity_manager");

            $entityManager->deleteRedisCacheKeys($arg1);
        }
        elseif ($func == "clear_backend_cache") {

            $cacheType = $_ENV["USE_BACKEND_CACHE"] ?? null;

            if(empty($cacheType)){
                return true;
            }

            if($cacheType == "redis"){
                /** @var EntityManager $entityManager */
                //$entityManager = $this->getContainer()->get("entity_manager");
                //$entityManager->deleteRedisCacheKeys($arg1);

                //php bin/console redis:flushdb --client=doctrine
                $command = $this->getApplication()->find('redis:flushdb');

                $arguments = [
                    '--client'  => 'doctrine',
                    '--no-interaction' => 'y'
                ];

                $greetInput = new ArrayInput($arguments);
                $command->run($greetInput, $output);
            }

            return true;
        }
        elseif ($func == "clean_logs") {

            /**@var DatabaseManager $databaseManager */
            $databaseManager = $this->getContainer()->get("database_manager");
            $databaseManager->cleanEntityLog($arg1);

            /** @var CronJobManager $cronJobManager */
            $cronJobManager = $this->getContainer()->get("cron_job_manager");
            $cronJobManager->cleanHistoryLog(intval($arg1/4));

            /** @var ImportManualManager $importManualManager */
            $importManualManager = $this->getContainer()->get("import_manual_manager");
            $importManualManager->cleanImportLog(intval($arg1)*10);

            /** @var AnalyticsManager $analyticsManager */
            $analyticsManager = $this->getContainer()->get("analytics_manager");
            $analyticsManager->cleanAnalytics(365);

            /** @var ShapeCleanerManager $shapeCleanerManager */
            $shapeCleanerManager = $this->getContainer()->get("shape_cleaner_manager");
            $shapeCleanerManager->cleanFilesFromFolderOlderThan($_ENV["WEB_PATH"]."../var/sessions/dev/",$arg1);

            return true;

        } elseif ($func == "test_excel") {

            /** @var ExcelManager $excelManager */
            $excelManager = $this->getContainer()->get("excel_manager");

            $test = $excelManager->excelTest();

            echo $test . "\n";
        } elseif ($func == "change_bundle") {

            if(empty($arg1)){
                echo "Missing entity type";
                return false;
            }

            if(empty($arg2)){
                echo "Missing destination bundle";
                return false;
            }

            /**@var AdministrationManager $administrationManager */
            $administrationManager = $this->getContainer()->get("administration_manager");

            $administrationManager->changeBundleOfEntityType($arg1,$arg2);
        }
        elseif ($func == "add_uid") {

            if(empty($arg1)){
                return false;
            }

            $addIsCustom = null;
            if(!empty($arg2)){
                $addIsCustom = 1;
            }

            /**@var AdministrationManager $administrationManager */
            $administrationManager = $this->getContainer()->get("administration_manager");

            $administrationManager->addUidToEntityType($arg1,$addIsCustom);
        }
        elseif ($func == "rebuild_indexes") {

            /** @var AdministrationManager $administrationManager */
            $administrationManager = $this->getContainer()->get("administration_manager");

            // Loop trough all bundles and rebuild indexes
            foreach ($this->getContainer()->getParameter('kernel.bundles') as $bundle => $namespace) {

                if(file_exists($_ENV["WEB_PATH"] . "/../src/{$bundle}/Resources/config/custom/indexes/indexes.json")){
                    $administrationManager->rebuildBundleIndexes($bundle);
                }
            }
        }
        elseif ($func == "clear_given_tables") {
            /** @var ShapeCleanerManager $shapeCleanerManager */
            $shapeCleanerManager = $this->getContainer()->get("shape_cleaner_manager");
            $shapeCleanerManager->clearGivenTables($arg1);
        }
        elseif ($func == "fix_json_fields") {
            /** @var ShapeCleanerManager $shapeCleanerManager */
            $shapeCleanerManager = $this->getContainer()->get("shape_cleaner_manager");
            $shapeCleanerManager->fixJsonFields();
        }
        else{
            throw new \Exception("Command type missing: ".json_encode($input->getArguments()));
        }

        return false;
    }
}
