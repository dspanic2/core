<?php

// php bin/console initialize:helper initialize_new
// php bin/console initialize:helper initialize_production

namespace AppBundle\Command;

use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\ApplicationSettingsManager;
use Symfony\Component\Console\Question\Question;
use AppBundle\Managers\DatabaseManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\MailManager;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class InitializeCommand extends ContainerAwareCommand
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var DatabaseManager $databaseManager */
    protected $databaseManager;
    /** @var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /** @var AdministrationManager $administrationManager */
    protected $administrationManager;
    /** @var ApplicationSettingsManager $applicationSettingsManager */
    protected $applicationSettingsManager;

    protected function configure()
    {
        $this->setName('initialize:helper')
            ->SetDescription(' description of what the command ')
            ->AddArgument('type', InputArgument :: OPTIONAL, ' which function ')
            ->AddArgument('arg1', InputArgument :: OPTIONAL, ' which arg1 ')
            ->AddArgument('arg2', InputArgument :: OPTIONAL, ' which arg2 ')
            ->AddArgument('arg3', InputArgument :: OPTIONAL, ' which arg3 ')
            ->AddArgument('arg4', InputArgument :: OPTIONAL, ' which arg4 ')
            ->AddArgument('arg5', InputArgument :: OPTIONAL, ' which arg5 ')
            ->AddArgument('arg6', InputArgument :: OPTIONAL, ' which arg6 ')
            ->AddArgument('arg7', InputArgument :: OPTIONAL, ' which arg7 ');
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
        $arg3 = $input->getArgument('arg3');
        $arg4 = $input->getArgument('arg4');
        $arg5 = $input->getArgument('arg5');
        $arg6 = $input->getArgument('arg6');
        $arg7 = $input->getArgument('arg7');

        if ($func == "initialize_new") {

            $helper = $this->getHelper('question');
            $askForConfirmation = new Question('Warning! This will delete all data from database. Write YES to confirm initialization: ', '');

            $confirmation = $helper->ask($input, $output, $askForConfirmation);

            if($confirmation != "YES" || $_ENV["IS_PRODUCTION"]){
                dump("Initialization canceled");
                return true;
            }

            /**
             * Remove env files
             */
            $envFiles = scandir($_ENV["WEB_PATH"]."/..");
            if(empty($envFiles)){
                dump("WEB_PATH is not valid");
                return false;
            }

            foreach ($envFiles as $envFile) {
                if(stripos($envFile, "example") !== false || $envFile == '.env' || $envFile == 'configuration.env' || $envFile == '.' || $envFile == '..'){
                    continue;
                }
                elseif(stripos($envFile, ".env") !== false){
                    unlink($_ENV["WEB_PATH"]."/../".$envFile);
                }
            }
            /**
             * End remove env files
             */

            $bundlesToDelete = Array();

            /**
             * Find custom bundles
             */
            $allowedBundles = Array(
                "FOS",
                "CrmBusinessBundle",
                "TaskBusinessBundle",
                "AppBundle",
                "HrBusinessBundle",
                "SharedInboxBusinessBundle",
                "NotificationsAndAlertsBusinessBundle",
                "ProjectManagementBusinessBundle",
                "ScommerceBusinessBundle",
                "WikiBusinessBundle",
                "ImageOptimizationBusinessBundle",
                "GLSBusinessBundle",
                "DPDBusinessBundle",
                "IntegrationBusinessBundle",
                "ToursBusinessBundle",
                "RulesBusinessBundle",
                "PaymentProvidersBusinessBundle",
                "FinanceBusinessBundle",
                "ShapeBehat"
            );

            $bundles = $this->getContainer()->getParameter('kernel.bundles');

            foreach ($bundles as $key => $value) {

                if (stripos($key, "business") !== false) {
                    if(!in_array($key,$allowedBundles)){
                        $bundlesToDelete[] = $key;
                    }
                }
            }

            $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/routing.yml", "%frontend_url%|%frontend_url_2%", "%frontend_url%", false);
            $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/routing.yml", "type:     annotation", "type: annotation", false);

            if(!empty($bundlesToDelete)){
                foreach ($bundlesToDelete as $bundleToDelete){

                    $contents = file_get_contents($_ENV["WEB_PATH"]."../app/AppKernel.php");
                    if(stripos($contents,$bundleToDelete) !== false){
                        $helperManager->removeLineFromFile($_ENV["WEB_PATH"]."../app/config/config.yml",$bundleToDelete);
                        $helperManager->removeLineFromFile($_ENV["WEB_PATH"]."../app/AppKernel.php",$bundleToDelete);
                    }
                    $contents = file_get_contents($_ENV["WEB_PATH"]."../app/config/routing.yml");
                    if(stripos($contents,$bundleToDelete) !== false){

                        $startsWith = strtolower(str_ireplace("businessbundle","",$bundleToDelete));
                        if(stripos($bundleToDelete,"admin") !== false){

                            $update=<<<UPDATE
UPDATE;
                $textToFind = <<<UPDATE
{$startsWith}:
  resource: "@{$bundleToDelete}/Controller/"
  type: annotation
  host: "%backend_url%"
UPDATE;
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/routing.yml", $textToFind, $update, false);
                $textToFind = <<<UPDATE
{$startsWith}:
  resource: "@{$bundleToDelete}/Controller/"
  type: annotation
UPDATE;
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/routing.yml", $textToFind, $update, false);
                $textToFind = <<<UPDATE
{$startsWith}:
  resource: "@{$bundleToDelete}/Controller/"
  type:     annotation
UPDATE;
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/routing.yml", $textToFind, $update, false);
                        }
                        else{
                            $update=<<<UPDATE
UPDATE;
                $textToFind = <<<UPDATE
{$startsWith}:
  resource: "@{$bundleToDelete}/Controller/"
  type: annotation
  host: "{domain}"
  requirements:
    domain: "%frontend_url%"
UPDATE;
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/routing.yml", $textToFind, $update, false);
                        }
                    }
                }
            }

            /**
             * Rebuild procedures
             */
            print "Started rebuild procedures\r\n";
            $command = $this->getApplication()->find('db_update:helper');

            $arguments = [
                'type'    => 'rebuild_procedures_views',
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);
            print "Finished rebuild procedures\r\n";

            /**
             * Check id data type
             */
            print "Started cleaning data type\r\n";
            $command = $this->getApplication()->find('update:helper');

            /*$arguments = [
                'type'    => 'fix_id_data_type',
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);*/

            $arguments = [
                'type'    => 'change_collation',
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);
            print "Data type cleaned\r\n";

            /**
             * Rebuild FK
             */
            print "Started rebuilding FK\r\n";
            $command = $this->getApplication()->find('db_update:helper');

            $arguments = [
                'type'    => 'rebuild_fk',
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);
            print "End rebuilding FK\r\n";

            if(empty($this->databaseContext)){
                $this->databaseContext = $this->getContainer()->get("database_context");
            }

            /**
             * NE KOPIRATI OVAJ POPIS U FUNKCIJU NIZE
             */
            $tablesToTruncate = Array(
                "shape_track",
                "shape_track_order_item_fact",
                "shape_track_product_dim",
                "shape_track_product_impressions_transaction",
                "shape_track_search_transaction",
                "shape_track_product_group_fact",
                "shape_track_totals_fact",
                "s_search_synonyms_entity",
                "s_product_search_results_entity",
                "tracking_entity",
                "transaction_email_entity",
                "transaction_email_sent_entity",
                "product_account_group_price_staging",
                "product_account_price_staging",
                "product_contact_remind_me_entity",
                "notification_entity",
                "marketing_rules_result_entity",
                "import_manual_entity",
                "import_log_entity",
                "general_question_entity",
                "gdpr_entity",
                "favorite_entity",
                "error_log_entity",
                "entity_log",
                "ckeditor_entity",
                "s_route_not_found_entity"
            );

            foreach ($tablesToTruncate as $table){
                $q = "TRUNCATE TABLE {$table};";
                $this->databaseContext->executeNonQuery($q);
                print "TRUNCATED TABLE {$table}\r\n";
            }

            $tablesToDelete = Array(
                "facet_attribute_configuration_link_entity",
                "facets_entity",
                "email_entity",
                "dpd_parcel_entity",
                "s_product_attribute_configuration_options_entity",
		        "product_configurable_attribute_entity",
                "s_product_attribute_configuration_entity",
                "payment_transaction_entity",
                "loyalty_card_entity",
                "product_entity",
                "product_group_entity",
                "note_entity",
                "brand_entity",
                "blog_post_entity",
                "blog_category_entity",
                "blocked_ips_entity",
                "api_access_entity",
                "absence_entity",
		        "bulk_price_option_entity",
                "bulk_price_entity",
		        "warehouse_entity",
                "testimonials_entity",
                "task_entity",
                "static_content_entity",
                "slider_entity",
                "s_menu_entity",
                "s_entity_comment_entity",
                "order_entity",
		        "quote_entity",
                "project_entity",
                "discount_coupon_entity",
                "deal_entity",
                "contract_entity",
		        "account_entity",
                "employee_entity",
                "contact_entity",
		        "faq_spage_link_entity",
                "account_group_entity",
                "newsletter_entity",
                "s_entity_comment_entity",
                "s_entity_rating_entity"
            );

            foreach ($tablesToDelete as $table){
                $q = "DELETE FROM {$table};";
                $this->databaseContext->executeNonQuery($q);
                print "DELETE FROM {$table}\r\n";
            }

            /**
             * Custom ciscenje
             */

            /**
             * S PAGE SETUP
             */
            $q = "DELETE FROM `s_menu_item_entity`;";
            $this->databaseContext->executeNonQuery($q);
            $q = "DELETE FROM s_page_entity;";
            $this->databaseContext->executeNonQuery($q);
            print "DELETE FROM s_page_entity\r\n";

            if(empty($this->entityManager)){
                $this->entityManager = $this->getContainer()->get("entity_manager");
            }

            /** @var AttributeSet $at */
            $at = $this->entityManager->getAttributeSetByCode("s_page");

            $sPageArray = Array(
                0 => Array("id" => 29, "name" => "{\"3\": \"Naslovnica\"}", "url" => "{\"3\": \"/\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 3, "meta_title" => "{\"3\": \"Naslovnica\"}", "meta_description" => "{\"3\": \"Naslovnica\"}", "enable_edit" => 0, "do_not_index" => 0),
                1 => Array("id" => 30, "name" => "{\"3\": \"404\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 1, "meta_title" => "{\"3\": \"404\"}", "meta_description" => "{\"3\": \"404\"}", "enable_edit" => 0, "do_not_index" => 0),
                2 => Array("id" => 31, "name" => "{\"3\": \"O nama\"}", "content" => "{\"3\": \"<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc vitae ligula eu massa condimentum lacinia. Morbi finibus ultricies imperdiet. Pellentesque quis elementum nulla. Nunc sed quam eu est posuere posuere. Donec erat metus, tempor ut sollicitudin et, varius eu nulla. Proin ex purus, varius sed tincidunt non, condimentum non nisi. Duis luctus facilisis bibendum. Nunc et viverra massa. Etiam dictum urna ac enim vehicula volutpat. Mauris nec sapien ac risus aliquam elementum. Donec vitae fringilla nulla. Ut ut quam fermentum, ornare elit et, cursus magna. Sed nec dolor a dui placerat lobortis.</p><p>Praesent tempor at tellus a rhoncus. Nunc sapien odio, semper at iaculis non, feugiat eu enim. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Sed a diam euismod sem auctor sagittis. Etiam et pretium libero. Curabitur justo metus, vestibulum eu lacus in, lacinia blandit sem. Aliquam dapibus pellentesque convallis. Suspendisse risus risus, elementum sit amet aliquam euismod, efficitur at nulla. Pellentesque bibendum risus aliquam nunc iaculis, nec eleifend erat mollis.</p>\"}", "template_type_id" => 2, "meta_title" => "{\"3\": \"O nama\"}", "meta_description" => "{\"3\": \"\"}", "enable_edit" => 1, "do_not_index" => 0),
                3 => Array("id" => 32, "name" => "{\"3\": \"Pomoć\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 24, "meta_title" => "{\"3\": \"Često postavljana pitanja\"}", "meta_description" => "{\"3\": \"Često postavljana pitanja\"}", "enable_edit" => 1, "do_not_index" => 0),
                4 => Array("id" => 34, "name" => "{\"3\": \"Poslovnice\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 18, "meta_title" => "{\"3\": \"Poslovnice\"}", "meta_description" => "{\"3\": \"Poslovnice\"}", "enable_edit" => 1, "do_not_index" => 0),
                5 => Array("id" => 35, "name" => "{\"3\": \"Kontakt\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 32, "meta_title" => "{\"3\": \"Kontakt\"}", "meta_description" => "{\"3\": \"Kontakt\"}", "enable_edit" => 1, "do_not_index" => 0),
                6 => Array("id" => 38, "name" => "{\"3\": \"Uvjeti poslovanja\"}", "content" => "{\"3\": \"<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc vitae ligula eu massa condimentum lacinia. Morbi finibus ultricies imperdiet. Pellentesque quis elementum nulla. Nunc sed quam eu est posuere posuere. Donec erat metus, tempor ut sollicitudin et, varius eu nulla. Proin ex purus, varius sed tincidunt non, condimentum non nisi. Duis luctus facilisis bibendum. Nunc et viverra massa. Etiam dictum urna ac enim vehicula volutpat. Mauris nec sapien ac risus aliquam elementum. Donec vitae fringilla nulla. Ut ut quam fermentum, ornare elit et, cursus magna. Sed nec dolor a dui placerat lobortis.</p><p>Praesent tempor at tellus a rhoncus. Nunc sapien odio, semper at iaculis non, feugiat eu enim. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Sed a diam euismod sem auctor sagittis. Etiam et pretium libero. Curabitur justo metus, vestibulum eu lacus in, lacinia blandit sem. Aliquam dapibus pellentesque convallis. Suspendisse risus risus, elementum sit amet aliquam euismod, efficitur at nulla. Pellentesque bibendum risus aliquam nunc iaculis, nec eleifend erat mollis.</p>\"}", "template_type_id" => 32, "meta_title" => "{\"3\": \"Uvjeti poslovanja\"}", "meta_description" => "{\"3\": \"Uvjeti poslovanja\"}", "enable_edit" => 1, "do_not_index" => 0),
                7 => Array("id" => 39, "name" => "{\"3\": \"Načini plaćanja\"}", "content" => "{\"3\": \"<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc vitae ligula eu massa condimentum lacinia. Morbi finibus ultricies imperdiet. Pellentesque quis elementum nulla. Nunc sed quam eu est posuere posuere. Donec erat metus, tempor ut sollicitudin et, varius eu nulla. Proin ex purus, varius sed tincidunt non, condimentum non nisi. Duis luctus facilisis bibendum. Nunc et viverra massa. Etiam dictum urna ac enim vehicula volutpat. Mauris nec sapien ac risus aliquam elementum. Donec vitae fringilla nulla. Ut ut quam fermentum, ornare elit et, cursus magna. Sed nec dolor a dui placerat lobortis.</p><p>Praesent tempor at tellus a rhoncus. Nunc sapien odio, semper at iaculis non, feugiat eu enim. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Sed a diam euismod sem auctor sagittis. Etiam et pretium libero. Curabitur justo metus, vestibulum eu lacus in, lacinia blandit sem. Aliquam dapibus pellentesque convallis. Suspendisse risus risus, elementum sit amet aliquam euismod, efficitur at nulla. Pellentesque bibendum risus aliquam nunc iaculis, nec eleifend erat mollis.</p>\"}", "template_type_id" => 2, "meta_title" => "{\"3\": \"Načini plaćanja\"}", "meta_description" => "{\"3\": \"Načini plaćanja\"}", "enable_edit" => 1, "do_not_index" => 0),
                8 => Array("id" => 40, "name" => "{\"3\": \"Načini dostave\"}", "content" => "{\"3\": \"<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc vitae ligula eu massa condimentum lacinia. Morbi finibus ultricies imperdiet. Pellentesque quis elementum nulla. Nunc sed quam eu est posuere posuere. Donec erat metus, tempor ut sollicitudin et, varius eu nulla. Proin ex purus, varius sed tincidunt non, condimentum non nisi. Duis luctus facilisis bibendum. Nunc et viverra massa. Etiam dictum urna ac enim vehicula volutpat. Mauris nec sapien ac risus aliquam elementum. Donec vitae fringilla nulla. Ut ut quam fermentum, ornare elit et, cursus magna. Sed nec dolor a dui placerat lobortis.</p><p>Praesent tempor at tellus a rhoncus. Nunc sapien odio, semper at iaculis non, feugiat eu enim. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Sed a diam euismod sem auctor sagittis. Etiam et pretium libero. Curabitur justo metus, vestibulum eu lacus in, lacinia blandit sem. Aliquam dapibus pellentesque convallis. Suspendisse risus risus, elementum sit amet aliquam euismod, efficitur at nulla. Pellentesque bibendum risus aliquam nunc iaculis, nec eleifend erat mollis.</p>\"}", "template_type_id" => 2, "meta_title" => "{\"3\": \"Načini dostave\"}", "meta_description" => "{\"3\": \"Načini dostave\"}", "enable_edit" => 1, "do_not_index" => 0),
                9 => Array("id" => 41, "name" => "{\"3\": \"Prigovori kupaca\"}", "content" => "{\"3\": \"<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc vitae ligula eu massa condimentum lacinia. Morbi finibus ultricies imperdiet. Pellentesque quis elementum nulla. Nunc sed quam eu est posuere posuere. Donec erat metus, tempor ut sollicitudin et, varius eu nulla. Proin ex purus, varius sed tincidunt non, condimentum non nisi. Duis luctus facilisis bibendum. Nunc et viverra massa. Etiam dictum urna ac enim vehicula volutpat. Mauris nec sapien ac risus aliquam elementum. Donec vitae fringilla nulla. Ut ut quam fermentum, ornare elit et, cursus magna. Sed nec dolor a dui placerat lobortis.</p><p>Praesent tempor at tellus a rhoncus. Nunc sapien odio, semper at iaculis non, feugiat eu enim. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Sed a diam euismod sem auctor sagittis. Etiam et pretium libero. Curabitur justo metus, vestibulum eu lacus in, lacinia blandit sem. Aliquam dapibus pellentesque convallis. Suspendisse risus risus, elementum sit amet aliquam euismod, efficitur at nulla. Pellentesque bibendum risus aliquam nunc iaculis, nec eleifend erat mollis.</p>\"}", "template_type_id" => 2, "meta_title" => "{\"3\": \"Prigovori kupaca\"}", "meta_description" => "{\"3\": \"Prigovori kupaca\"}", "enable_edit" => 1, "do_not_index" => 0),
                10 => Array("id" => 51, "name" => "{\"3\": \"Rezultati pretrage\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 11, "meta_title" => "{\"3\": \"Rezultati pretrage\"}", "meta_description" => "{\"3\": \"Rezultati pretrage\"}", "enable_edit" => 0, "do_not_index" => 0),
                11 => Array("id" => 52, "name" => "{\"3\": \"Registracija\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 12, "meta_title" => "{\"3\": \"Registracija\"}", "meta_description" => "{\"3\": \"Registracija\"}", "enable_edit" => 0, "do_not_index" => 0),
                12 => Array("id" => 53, "name" => "{\"3\": \"Usporedba\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 13, "meta_title" => "{\"3\": \"Usporedba\"}", "meta_description" => "{\"3\": \"Usporedba\"}", "enable_edit" => 0, "do_not_index" => 1),
                13 => Array("id" => 54, "name" => "{\"3\": \"Uspješna narudžba\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 8, "meta_title" => "{\"3\": \"Uspješna narudžba\"}", "meta_description" => "{\"3\": \"Uspješna narudžba\"}", "enable_edit" => 0, "do_not_index" => 1),
                14 => Array("id" => 55, "name" => "{\"3\": \"Greška prilikom narudžbe\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 14, "meta_title" => "{\"3\": \"Greška prilikom narudžbe\"}", "meta_description" => "{\"3\": \"Greška prilikom narudžbe\"}", "enable_edit" => 0, "do_not_index" => 1),
                15 => Array("id" => 56, "name" => "{\"3\": \"Narudžba\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 7, "meta_title" => "{\"3\": \"Narudžba\"}", "meta_description" => "{\"3\": \"Narudžba\"}", "enable_edit" => 0, "do_not_index" => 1),
                16 => Array("id" => 58, "name" => "{\"3\": \"Prijava\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 17, "meta_title" => "{\"3\": \"Prijava\"}", "meta_description" => "{\"3\": \"Prijava\"}", "enable_edit" => 0, "do_not_index" => 1),
                17 => Array("id" => 59, "name" => "{\"3\": \"Favoriti\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 25, "meta_title" => "{\"3\": \"Favoriti\"}", "meta_description" => "{\"3\": \"Favoriti\"}", "enable_edit" => 0, "do_not_index" => 1),
                18 => Array("id" => 60, "name" => "{\"3\": \"Moj račun\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 27, "meta_title" => "{\"3\": \"Moj račun\"}", "meta_description" => "{\"3\": \"Moj račun\"}", "enable_edit" => 0, "do_not_index" => 1),
                19 => Array("id" => 61, "name" => "{\"3\": \"Moje narudžbe\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 28, "meta_title" => "{\"3\": \"Moje narudžbe\"}", "meta_description" => "{\"3\": \"Moje narudžbe\"}", "enable_edit" => 0, "do_not_index" => 1),
                20 => Array("id" => 62, "name" => "{\"3\": \"Moji favoriti\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 29, "meta_title" => "{\"3\": \"Moji favoriti\"}", "meta_description" => "{\"3\": \"Moji favoriti\"}", "enable_edit" => 0, "do_not_index" => 1),
                21 => Array("id" => 63, "name" => "{\"3\": \"Obavijesti\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 30, "meta_title" => "{\"3\": \"Obavijesti\"}", "meta_description" => "{\"3\": \"Obavijesti\"}", "enable_edit" => 0, "do_not_index" => 1),
                22 => Array("id" => 64, "name" => "{\"3\": \"Moj profil\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 31, "meta_title" => "{\"3\": \"Moj profil\"}", "meta_description" => "{\"3\": \"Moj profil\"}", "enable_edit" => 0, "do_not_index" => 1),
                23 => Array("id" => 68, "name" => "{\"3\": \"Reset lozinke\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 36, "meta_title" => "{\"3\": \"Reset lozinke\"}", "meta_description" => "{\"3\": \"Reset lozinke\"}", "enable_edit" => 0, "do_not_index" => 1),
                24 => Array("id" => 70, "name" => "{\"3\": \"Narudžba pregled\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 37, "meta_title" => "{\"3\": \"Narudžba pregled\"}", "meta_description" => "{\"3\": \"Narudžba pregled\"}", "enable_edit" => 0, "do_not_index" => 1),
                25 => Array("id" => 71, "name" => "{\"3\": \"Nova lozinka\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 38, "meta_title" => "{\"3\": \"Nova lozinka\"}", "meta_description" => "{\"3\": \"Nova lozinka\"}", "enable_edit" => 0, "do_not_index" => 1),
                26 => Array("id" => 93, "name" => "{\"3\": \"Dostava i povrat\"}", "content" => "{\"3\": \"<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc vitae ligula eu massa condimentum lacinia. Morbi finibus ultricies imperdiet. Pellentesque quis elementum nulla. Nunc sed quam eu est posuere posuere. Donec erat metus, tempor ut sollicitudin et, varius eu nulla. Proin ex purus, varius sed tincidunt non, condimentum non nisi. Duis luctus facilisis bibendum. Nunc et viverra massa. Etiam dictum urna ac enim vehicula volutpat. Mauris nec sapien ac risus aliquam elementum. Donec vitae fringilla nulla. Ut ut quam fermentum, ornare elit et, cursus magna. Sed nec dolor a dui placerat lobortis.</p><p>Praesent tempor at tellus a rhoncus. Nunc sapien odio, semper at iaculis non, feugiat eu enim. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Sed a diam euismod sem auctor sagittis. Etiam et pretium libero. Curabitur justo metus, vestibulum eu lacus in, lacinia blandit sem. Aliquam dapibus pellentesque convallis. Suspendisse risus risus, elementum sit amet aliquam euismod, efficitur at nulla. Pellentesque bibendum risus aliquam nunc iaculis, nec eleifend erat mollis.</p>\"}", "template_type_id" => 2, "meta_title" => "{\"3\": \"Dostava i povrat\"}", "meta_description" => "{\"3\": \"Dostava i povrat\"}", "enable_edit" => 1, "do_not_index" => 0),
                27 => Array("id" => 94, "name" => "{\"3\": \"Pravila o privatnosti\"}", "content" => "{\"3\": \"<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc vitae ligula eu massa condimentum lacinia. Morbi finibus ultricies imperdiet. Pellentesque quis elementum nulla. Nunc sed quam eu est posuere posuere. Donec erat metus, tempor ut sollicitudin et, varius eu nulla. Proin ex purus, varius sed tincidunt non, condimentum non nisi. Duis luctus facilisis bibendum. Nunc et viverra massa. Etiam dictum urna ac enim vehicula volutpat. Mauris nec sapien ac risus aliquam elementum. Donec vitae fringilla nulla. Ut ut quam fermentum, ornare elit et, cursus magna. Sed nec dolor a dui placerat lobortis.</p><p>Praesent tempor at tellus a rhoncus. Nunc sapien odio, semper at iaculis non, feugiat eu enim. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Sed a diam euismod sem auctor sagittis. Etiam et pretium libero. Curabitur justo metus, vestibulum eu lacus in, lacinia blandit sem. Aliquam dapibus pellentesque convallis. Suspendisse risus risus, elementum sit amet aliquam euismod, efficitur at nulla. Pellentesque bibendum risus aliquam nunc iaculis, nec eleifend erat mollis.</p>\"}", "template_type_id" => 2, "meta_title" => "{\"3\": \"Pravila o privatnosti\"}", "meta_description" => "{\"3\": \"Pravila o privatnosti\"}", "enable_edit" => 1, "do_not_index" => 0),
                28 => Array("id" => 146, "name" => "{\"3\": \"Odjava\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 54, "meta_title" => "{\"3\": \"Odjava\"}", "meta_description" => "{\"3\": \"Odjava\"}", "enable_edit" => 0, "do_not_index" => 1),
                29 => Array("id" => 74, "name" => "{\"3\": \"Prikaz ponude\"}", "content" => "{\"3\": \"\"}", "template_type_id" => 40, "meta_title" => "{\"3\": \"Prikaz ponude\"}", "meta_description" => "{\"3\": \"Prikaz ponude\"}", "enable_edit" => 0, "do_not_index" => 1),
            );

            $q = Array();

            $sPageDefault = Array(
                "entity_type_id" => $at->getEntityTypeId(),
                "attribute_set_id" => $at->getId(),
                "created" => "NOW()",
                "modified" => "NOW()",
                "modified_by" => "system",
                "created_by" => "system",
                "entity_state_id" => 1,
                "keep_url" => 1,
                "auto_generate_url" => 1,
                "meta_keywords" => "{\"3\": \"\"}",
                "show_on_store" => "{\"3\": 1}",
                "active" => 1
            );

            foreach ($sPageArray as $sPage){

                $sPage = array_merge($sPage,$sPageDefault);

                $keys = implode(",",array_keys($sPage));
                $values = array_values($sPage);

                $tmp = "INSERT INTO s_page_entity ({$keys}) VALUES ('".implode("','",$values)."');";
                $tmp = str_ireplace("'NOW()'","NOW()",$tmp);

                $q[] = $tmp;
            }
            $q = implode(" ",$q);
            $this->databaseContext->executeNonQuery($q);

            /**
             * S TEMPLATE TYPE SETUP
             */
            $allowedTemplateTypes = Array(
                "404_page",
                "static_page",
                "home_page",
                "default_category",
                "default_product",
                "checkout_page",
                "success_page",
                "search_results",
                "register_page",
                "compare_page",
                "failure_page",
                "login_page",
                "offices",
                "blog_post",
                "blog_category",
                "faq",
                "favorites",
                "dashboard",
                "dashboard_orders",
                "dashboard_favorites",
                "dashboard_notices",
                "dashboard_profile",
                "contact_page",
                "reset_password_page",
                "single_order",
                "set_new_password_page",
                "newsletter_unsubscribe",
                "blog_categories",
                "quote_preview"
            );
            $q = "DELETE FROM s_template_type_entity WHERE code not in ('".implode("','",$allowedTemplateTypes)."');";
            $this->databaseContext->executeNonQuery($q);
            print "DELETE FROM s_template_type_entity WHERE code not in ('".implode("','",$allowedTemplateTypes)."')\r\n";

            $q = "UPDATE s_template_type_entity SET entity_state_id = 1, javascripts = '[]';";
            $this->databaseContext->executeNonQuery($q);
            print "UPDATE s_template_type_entity SET entity_state_id = 1, javascripts = '[]'\r\n";

            $q = "DELETE FROM faq_entity WHERE id > 13 OR entity_state_id = 2;";
            $this->databaseContext->executeNonQuery($q);
            print "DELETE FROM faq_entity WHERE id > 13 OR entity_state_id = 2\r\n";

            $q = "UPDATE faq_entity SET store_id = 3, description = '{\"3\": \"<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc vitae ligula eu massa condimentum lacinia. Morbi finibus ultricies imperdiet. Pellentesque quis elementum nulla. Nunc sed quam eu est posuere posuere. Donec erat metus, tempor ut sollicitudin et, varius eu nulla. Proin ex purus, varius sed tincidunt non, condimentum non nisi. Duis luctus facilisis bibendum. Nunc et viverra massa. Etiam dictum urna ac enim vehicula volutpat. Mauris nec sapien ac risus aliquam elementum. Donec vitae fringilla nulla. Ut ut quam fermentum, ornare elit et, cursus magna. Sed nec dolor a dui placerat lobortis.</p><p>Praesent tempor at tellus a rhoncus. Nunc sapien odio, semper at iaculis non, feugiat eu enim. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Sed a diam euismod sem auctor sagittis. Etiam et pretium libero. Curabitur justo metus, vestibulum eu lacus in, lacinia blandit sem. Aliquam dapibus pellentesque convallis. Suspendisse risus risus, elementum sit amet aliquam euismod, efficitur at nulla. Pellentesque bibendum risus aliquam nunc iaculis, nec eleifend erat mollis.</p>\"}';";
            $this->databaseContext->executeNonQuery($q);

	        $q = "DELETE FROM user_role_entity WHERE core_user_id NOT IN (SELECT id FROM user_entity WHERE username = 'system');";
            $this->databaseContext->executeNonQuery($q);
            print "DELETE FROM user_role_entity WHERE core_user_id NOT IN (SELECT id FROM user_entity WHERE username = 'system')\r\n";

            $q = "DELETE FROM user_entity WHERE username != 'system';";
            $this->databaseContext->executeNonQuery($q);
            print "DELETE FROM user_entity WHERE username != 'system'\r\n";

            $q = "DELETE FROM role_entity WHERE role_code NOT IN ('ROLE_ADMIN','ROLE_COMMERCE_ADMIN','ROLE_CUSTOMER');";
            $this->databaseContext->executeNonQuery($q);
            print "DELETE FROM role_entity WHERE role_code NOT IN ('ROLE_ADMIN','ROLE_COMMERCE_ADMIN','ROLE_CUSTOMER')\r\n";

            $q = "UPDATE cron_job_entity SET is_active = 0;";
            $this->databaseContext->executeNonQuery($q);

            $q = "UPDATE cron_job_entity SET is_active = 1 WHERE method = 'transactionEmail:send';";
            $this->databaseContext->executeNonQuery($q);

	        $q = "DELETE FROM s_route_entity WHERE store_id != 3 OR request_url != '/';";
            $this->databaseContext->executeNonQuery($q);
            print "DELETE FROM s_route_entity WHERE store_id != 3 OR request_url != '/'\r\n";

	        $q = "DELETE FROM s_store_entity WHERE id != 3;";
            $this->databaseContext->executeNonQuery($q);
            print "DELETE FROM s_store_entity WHERE id != 3\r\n";

            $q = "DELETE FROM s_website_entity WHERE id != 1;";
            $this->databaseContext->executeNonQuery($q);
            print "DELETE FROM s_website_entity WHERE id != 1\r\n";

            $q = "UPDATE s_website_entity SET name = 'base', base_url = '{$_ENV["FRONTEND_URL"]}', commerce_template_bundles = 'ScommerceBusinessBundle,CrmBusinessBundle,AppBundle' WHERE id = 1;";
            $this->databaseContext->executeNonQuery($q);
            print "Default website data set\r\n";

            $q = "UPDATE s_store_entity SET name = 'hr', core_language_id = 1, website_id = 1, display_currency_id = 1 WHERE id = 3;";
            $this->databaseContext->executeNonQuery($q);
            print "Default store data set\r\n";

            $q = "DELETE FROM settings_entity;";
            $this->databaseContext->executeNonQuery($q);
            print "DELETE FROM settings_entity\r\n";

            $q = "DELETE FROM s_front_block_entity WHERE entity_state_id = 2;";
            $this->databaseContext->executeNonQuery($q);
            print "DELETE FROM s_front_block WHERE entity_state_id = 2\r\n";

            $q = "UPDATE s_front_block_entity SET active = 1, show_on_store = '{\"3\":1}';";
            $this->databaseContext->executeNonQuery($q);
            print "UPDATE s_front_block_entity SET active = 1, show_on_store = '{\"3\":1}'\r\n";

            $q = "UPDATE s_front_block_entity SET editor = '{\"3\":\"Lorem ipsum\"}' WHERE type = 'html';";
            $this->databaseContext->executeNonQuery($q);
            print "UPDATE s_front_block_entity SET '{\"3\": \"Lorem ipsum\"}' WHERE type = 'html'\r\n";

            $q = "UPDATE payment_type_entity SET description = '{\"3\": \"Lorem ipsum\"}', show_on_store = '{\"3\":1}';";
            $this->databaseContext->executeNonQuery($q);
            print "UPDATE payment_type_entity SET description = '{\"3\": \"Lorem ipsum\"}', show_on_store = '{\"3\":1}'\r\n";

            $q = "UPDATE delivery_type_entity SET description = '{\"3\": \"Lorem ipsum\"}', show_on_store = '{\"3\":1}';";
            $this->databaseContext->executeNonQuery($q);
            print "UPDATE payment_type_entity SET description = '{\"3\": \"Lorem ipsum\"}', show_on_store = '{\"3\":1}'\r\n";

            /**
             * Delete s_front_block
             */
            $command = $this->getApplication()->find('helper:smetlar');
            $arguments = [
                'type'    => 'clear_blocks',
                'arg_1'   => 's_front_block_entity',
                'arg_2'   => '1'
            ];

            $greetInput = new ArrayInput($arguments);

            for ($x = 0; $x <= 10; $x++) {
                $command->run($greetInput, $output);
            }
            /**
             * End delete s_front_block
             */

            /**
             * Clear routes
             */
            print "Started cleaning routes\r\n";
            $command = $this->getApplication()->find('scommercehelper:function');

            $arguments = [
                'type'    => 'automatically_fix_rutes',
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);
            print "Routes cleaned\r\n";

            /**
             * Delete backup tables
             */
            $q = "SHOW TABLES LIKE '\_%';";
            $tables = $this->databaseContext->getAll($q);

            if(!empty($tables)){
                foreach ($tables as $table){
                    foreach ($table as $t){
                        $q = "DROP TABLE {$t};";
                        print "DROP TABLE {$t}\r\n";
                        $this->databaseContext->executeNonQuery($q);
                    }
                }
            }

            /**
             * Delete custom bundles
             */
            print "Started deleting custom entity types\r\n";

            if(empty($this->entityTypeContext)){
                $this->entityTypeContext = $this->getContainer()->get("entity_type_context");
            }

            /**@var \AppBundle\Entity\EntityType $entityType */
            $entityTypes = $this->entityTypeContext->getAllItems();

            if(empty($this->administrationManager)){
                $this->administrationManager = $this->getContainer()->get("administration_manager");
            }

            if(!empty($bundlesToDelete)){
                foreach ($entityTypes as $entityType) {
                    if (in_array($entityType->getBundle(),$bundlesToDelete)) {
                        print "{$entityType->getEntityTypeCode()}\r\n";
                        $this->administrationManager->deleteEntityType($entityType);
                    }
                }
            }
            print "Finished deleting custom entity types\r\n";

            print "Started deleting custom bundles\r\n";

            $dir = $_ENV["WEB_PATH"]."../src";

            foreach( glob("$dir/*") as $file ) {
                if( !in_array(basename($file), $allowedBundles) ){
                    shell_exec("rm -rf {$file}");
                }
            }
            print "Finished deleting custom bundles\r\n";

            print "Ciscenje web foldera\r\n";

            $dir = $_ENV["WEB_PATH"]."xml";
            if(file_exists($dir)){
                shell_exec("rm -rf {$dir}");
            }

            $dir = $_ENV["WEB_PATH"]."Documents";
            if(file_exists($dir)){
                shell_exec("rm -rf {$dir}");
            }
            if(!file_exists($dir)){
                mkdir($dir,0777,true);
            }

            $dir = $_ENV["WEB_PATH"]."images";
            if(file_exists($dir)){
                shell_exec("rm -rf {$dir}");
            }
            if(!file_exists($dir)){
                mkdir($dir,0777,true);
            }

            print "End ciscenje web foldera\r\n";

            shell_exec("sh ".$_ENV["WEB_PATH"]."../src/AppBundle/Resources/scripts/clear_cache.sh");

            print "Sync import\r\n";
            $command = $this->getApplication()->find('admin:sync');

            $arguments = [
                'type'    => 'import',
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);
            $command->run($greetInput, $output);
            print "Finished sync import\r\n";

            //TODO HRCO napisati komandu za popunjavanje date dim

            //TODO HRCO php bin/console helper:smetlar > ./var/logs/smetlar.sql

            print "Set default settings\r\n";
            $command = $this->getApplication()->find('update:helper');

            $arguments = [
                'type'    => 'update_default_settings',
                'arg1'   => '1'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);
            print "End set default settings\r\n";

            print "Clean files\r\n";
            $command = $this->getApplication()->find('helper:smetlar');
            $arguments = [
                'type'    => 'remove_deleted_database_and_unused_disk_files',
                'arg_1'   => '1'
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);
            print "Finished clean files\r\n";

            print "Rebuild FK\r\n";
            $command = $this->getApplication()->find('db_update:helper');

            $arguments = [
                'type'    => 'rebuild_fk',
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);
            print "End rebuild FK\r\n";

            print "Generate admin accounts\r\n";
            $command = $this->getApplication()->find('user:helper');

            $arguments = [
                'type'    => 'set_default_admins',
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);
            print "Finished generate admin accounts\r\n";

            //custom ciscenje

            //TODO popunjavanje holidays
            //TODO popunjavanje codebooka

            return true;

        }
        elseif ($func == "initialize_production") {

            $helper = $this->getHelper('question');
            $askForConfirmation = new Question('Warning! This will clear orders, quotes, transaction_email, newsletter, statisticks data from database. Write YES to confirm initialization: ', '');

            $confirmation = $helper->ask($input, $output, $askForConfirmation);

            if($confirmation != "YES" || $_ENV["IS_PRODUCTION"]){
                dump("Initialization canceled");
                return true;
            }

            if(empty($this->databaseContext)){
                $this->databaseContext = $this->getContainer()->get("database_context");
            }

            $testingEmails = Array(
                "davor.spanic+",
                "vmrazovic",
                "alen.pagac+",
                "viki.shipshape+",
                "igor.drausnik+",
                "Viktorija.jurkovic@",
                "zaba@shipshape-",
                "ividovi91@"
            );

            $tablesToDelete = Array(
                "order_entity",
                "quote_entity",
                "s_entity_comment_entity",
                "s_entity_rating_entity",
                "loyalty_earnings_entity",
                "loyalty_card_entity",
                "task_entity",
                "payment_transaction_entity",
                "s_route_not_found_entity"
            );

            foreach ($tablesToDelete as $table){
                $q = "DELETE FROM {$table};";
                $this->databaseContext->executeNonQuery($q);
                print "DELETE FROM {$table}\r\n";
            }

            foreach ($testingEmails as $testingEmail){
                $q = "DELETE FROM account_entity WHERE email like '{$testingEmail}%';";
                $this->databaseContext->executeNonQuery($q);
            }

            $q = "DELETE FROM user_entity WHERE id not in (SELECT core_user_id FROM contact_entity WHERE core_user_id is not null) and username != 'system';";
            $this->databaseContext->executeNonQuery($q);

            $tablesToTruncate = Array(
                "shape_track",
                "shape_track_order_item_fact",
                "shape_track_product_dim",
                "shape_track_product_impressions_transaction",
                "shape_track_search_transaction",
                "shape_track_product_group_fact",
                "shape_track_totals_fact",
                "s_product_search_results_entity",
                "tracking_entity",
                "transaction_email_entity",
                "transaction_email_sent_entity",
                "product_contact_remind_me_entity",
                "notification_entity",
                "marketing_rules_result_entity",
                "import_manual_entity",
                "import_log_entity",
                "general_question_entity",
                "gdpr_entity",
                "favorite_entity",
                "error_log_entity",
                "entity_log",
                "newsletter_entity",
                "blocked_ips_entity",

            );

            foreach ($tablesToTruncate as $table){
                $q = "TRUNCATE TABLE {$table};";
                $this->databaseContext->executeNonQuery($q);
                print "TRUNCATED TABLE {$table}\r\n";
            }

            if (empty($this->applicationSettingsManager)) {
                $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
            }

            /**
             * Order return
             */
            $settings = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode("order_return_increment_start_from");
            if(!empty($settings)){
                $settingsValue = $settings->getSettingsValue();
                foreach ($settingsValue as $key => $value){
                    $settingsValue[$key] = intval($value)+20000;
                }

                $data = Array();
                $data["settings_value"] = $settingsValue;
                $this->applicationSettingsManager->createUpdateSettings($settings,$data);
            }

            /**
             * Order
             */
            $settings = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode("order_increment_start_from");
            if(!empty($settings)){
                $settingsValue = $settings->getSettingsValue();
                foreach ($settingsValue as $key => $value){
                    $settingsValue[$key] = intval($value)+20000;
                }

                $data = Array();
                $data["settings_value"] = $settingsValue;
                $this->applicationSettingsManager->createUpdateSettings($settings,$data);
            }

            print "Generate admin accounts\r\n";
            $command = $this->getApplication()->find('user:helper');

            $arguments = [
                'type'    => 'set_default_admins',
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);
            print "Finished generate admin accounts\r\n";

            print "Generate account for all admins\r\n";
            $command = $this->getApplication()->find('update:helper');

            $arguments = [
                'type'    => 'generate_account_and_contact_for_all_admin',
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);
            print "Finished generate account for all admins\r\n";

            return true;
        }
        else{
            throw new \Exception("Command type missing: ".json_encode($input->getArguments()));
        }

        return false;
    }
}
