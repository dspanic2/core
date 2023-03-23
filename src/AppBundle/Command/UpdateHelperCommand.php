<?php

// php bin/console update:helper notification_regenerate
// php bin/console update:helper transfer_addresses_to_address_entity
// php bin/console update:helper fix_document_attributes
// php bin/console update:helper attribute_to_json product_entity video_title
// php bin/console update:helper validate_env _with_default
// php bin/console update:helper update_active_on_blog_category_s_page
// php bin/console update:helper remove_custom_files page 765aa15e5471684099130084ea75197e
// php bin/console update:helper generate_account_and_contact_for_admin 147
// php bin/console update:helper generate_account_and_contact_for_all_admin
// php bin/console update:helper change_custom_files table uid 1
// php bin/console update:helper change_custom_files attribute 99d3f0cb34365d288a845c1ca28e0dc6 1
// php bin/console update:helper delete_attribute attribute 99d3f0cb34365d288a845c1ca28e0dc6
// php bin/console update:helper delete_entity_type entity_type 604883143b9ff2.62367708
// php bin/console update:helper delete_navigation_link 617fe43ebc6472.97324866


// php bin/console update:helper fix_entity_type_and_attribute_set_id product
// php bin/console update:helper update_activity_attributes
// php bin/console update:helper validate_installation
// php bin/console update:helper remove_bon_hr_business_bundle
// php bin/console update:helper google_images_business_bundle
// php bin/console update:helper remove_sendinblue_business_bundle
// php bin/console update:helper data_transfer_business_bundle
// php bin/console update:helper remove_old_update_php
// php bin/console update:helper fix_s_product_configuration_product_group_link_entity
// php bin/console update:helper update_delivery_payment_options
// php bin/console update:helper update_discount_coupons
// php bin/console update:helper update_reset_password_form
// php bin/console update:helper fix_id_data_type 1
// php bin/console update:helper fix_id_autoincrement 1
// php bin/console update:helper change_collation
// php bin/console update:helper update_contact_on_quote_and_order
// php bin/console update:helper fk_to_md5
// php bin/console update:helper product_account_price_set_uq
// php bin/console update:helper update_s_front_block_show_on_store
// php bin/console update:helper update_missing_uid
// php bin/console update:helper transfer_from_env_to_settings FILTERS_SALEABLE
// php bin/console update:helper transfer_from_env_to_settings FILTERS_IS_ON_DISCOUNT
// php bin/console update:helper transfer_from_env_to_settings FILTERS_PRICE
// php bin/console update:helper transfer_from_env_to_settings FILTERS_CATEGORIES
// php bin/console update:helper transfer_from_env_to_settings FILTERS_CATEGORIES_LEVEL
// php bin/console update:helper transfer_from_env_to_settings FILTERS_CATEGORIES_LEVEL_SEARCH
// php bin/console update:helper transfer_from_env_to_settings FILTERS_CATEGORIES_SHOW_NEXT_CHILDREN_ONLY
// php bin/console update:helper transfer_from_env_to_settings FILTERS_ONLY_IMAGES
// php bin/console update:helper remove_from_env ORDER_RETURN_DEFAULT_PARCEL_SOURCE
// php bin/console update:helper remove_from_env STORE_NOTIFICATION_OF_PRODUCT_INQUITY
// php bin/console update:helper update_routing_yml
// php bin/console update:helper update_default_settings
// php bin/console update:helper update_1_8
// php bin/console update:helper update_1_9
// php bin/console update:helper update_2_1
// php bin/console update:helper update_2_3
// php bin/console update:helper update_2_5
// php bin/console update:helper update_2_6
// php bin/console update:helper update_2_7
// php bin/console update:helper update_3_3
// php bin/console update:helper update_3_4
// php bin/console update:helper update_3_5
// php bin/console update:helper update_4_1
// php bin/console update:helper update_4_3
// php bin/console update:helper update_4_4
// php bin/console update:helper update_4_5
// php bin/console update:helper update_4_6
// php bin/console update:helper update_4_8
// php bin/console update:helper update_4_9
// php bin/console update:helper update_4_91
// php bin/console update:helper update_4_92
// php bin/console update:helper update_4_93
// php bin/console update:helper update_4_94
// php bin/console update:helper update_4_96
// php bin/console update:helper update_4_97
// php bin/console update:helper update_5_0
// php bin/console update:helper update_5_1
// php bin/console update:helper update_5_11
// php bin/console update:helper update_5_13
// php bin/console update:helper update_5_15
// php bin/console update:helper update_5_16
// php bin/console update:helper update_5_17
// php bin/console update:helper update_5_18
// php bin/console update:helper update_5_19
// php bin/console update:helper update_5_22
// php bin/console update:helper update_5_32

namespace AppBundle\Command;

use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\ListView;
use AppBundle\Entity\NavigationLink;
use AppBundle\Entity\PageBlock;
use AppBundle\Entity\SettingsEntity;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\ApplicationSettingsManager;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\DatabaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ErrorLogManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\ListViewManager;
use AppBundle\Managers\NavigationLinkManager;
use AppBundle\Managers\RestManager;
use AppBundle\Managers\SyncManager;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Managers\AccountManager;
use HrBusinessBundle\Entity\CityEntity;
use Monolog\Logger;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Entity\WebformFieldTypeEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Managers\WebformManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\HttpFoundation\Request;

class UpdateHelperCommand extends ContainerAwareCommand
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;

    protected function configure()
    {
        $this->setName('update:helper')
            ->SetDescription(' description of what the command ')
            ->AddArgument('type', InputArgument :: OPTIONAL, ' which function ')
            ->AddArgument('arg1', InputArgument :: OPTIONAL, ' arg1 ')
            ->AddArgument('arg2', InputArgument :: OPTIONAL, ' arg2 ')
            ->AddArgument('arg3', InputArgument :: OPTIONAL, ' arg3 ')
            ->AddArgument('arg4', InputArgument :: OPTIONAL, ' arg4 ')
            ->AddArgument('arg5', InputArgument :: OPTIONAL, ' arg5 ')
            ->AddArgument('arg6', InputArgument :: OPTIONAL, ' arg6 ')
            ->AddArgument('arg7', InputArgument :: OPTIONAL, ' arg7 ');
    }

    public function addColumnQuery($tablename, $columnName, $columnAttributes)
    {
        $query = "SET @dbname = DATABASE();
                SET @tablename = '{$tablename}';
                SET @columnname = '{$columnName}';
                SET @preparedStatement = (SELECT IF(
                    (
                    SELECT COUNT(*) FROM ssinformation.COLUMNS
                    WHERE
                    (table_name = @tablename)
                    AND (table_schema = @dbname)
                    AND (column_name = @columnname)
                  ) > 0,
                  'SELECT 1',
                  CONCAT(\"ALTER TABLE \", @tablename, \" ADD \", @columnname, \" {$columnAttributes};\")
                ));
                PREPARE alterIfNotExists FROM @preparedStatement;
                EXECUTE alterIfNotExists;
                DEALLOCATE PREPARE alterIfNotExists;";
        return $query;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**@var Logger $logger */
        $logger = $this->getContainer()->get('logger');

        /** @var HelperManager $helperManager */
        $helperManager = $this->getContainer()->get("helper_manager");

        $request = new Request();
        $helperManager->loginAnonymus($request, "system");

        /** @var EntityManager $entityManager */
        $entityManager = $this->getContainer()->get("entity_manager");

        /**
         * Check which function
         */
        $func = $input->getArgument('type');
        if (empty($func)) {
            throw new \Exception('Function not defined');
        }

        if ($func == "attribute_to_json") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $table = $input->getArgument('arg1');
            if (empty($table)) {
                return false;
            }
            $attribute_code = $input->getArgument('arg2');
            if (empty($attribute_code)) {
                return false;
            }

            $q = "SELECT DISTINCT(TABLE_NAME) FROM ssinformation.key_column_usage WHERE table_name = '{$table}' AND table_schema = '{$_ENV["DATABASE_NAME"]}';";
            $exists = $databaseContext->executeQuery($q);

            if (empty($exists)) {
                return false;
            }

            $q = "SELECT * FROM ssinformation.COLUMNS WHERE column_name = '{$attribute_code}' AND table_name = '{$table}' AND table_schema = '{$_ENV["DATABASE_NAME"]}';";
            $exists = $databaseContext->executeQuery($q);

            if (empty($exists)) {
                return false;
            }

            /**
             * Check if allready json
             */
            if ($exists[0]["DATA_TYPE"] == "json") {
                return false;
            }

            $q = "SELECT * FROM attribute WHERE backend_table = '{$table}' and attribute_code = '{$attribute_code}';";
            $exists = $databaseContext->executeQuery($q);

            if (empty($exists) || stripos($exists[0]["frontend_type"], "json") !== false) {
                return false;
            }

            $q = "UPDATE {$table} SET {$attribute_code} = CONCAT('{\"3\":\"',{$attribute_code},'\"}') WHERE {$attribute_code} NOT LIKE '%{%';";
            $databaseContext->executeNonQuery($q);

            $q = "ALTER TABLE {$table} MODIFY {$attribute_code} JSON;";
            $databaseContext->executeNonQuery($q);

            return true;
        } elseif ($func == "remove_old_update_php") {
            // Remove old update_php
            $contentExists = false;
            if (file_exists($_ENV["WEB_PATH"] . "../update.php")) {
                unlink($_ENV["WEB_PATH"] . "../update.php");
            }
        } elseif ($func == "update_discount_coupons") {

            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->getContainer()->get("database_context");
            }

            $q = "UPDATE discount_coupon_entity SET allow_on_discount_products = 0 WHERE allow_on_discount_products is null;";
            $this->databaseContext->executeNonQuery($q);
        } elseif ($func == "update_delivery_payment_options") {

            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->getContainer()->get("database_context");
            }

            /**
             * Fix colors
             */
            $q = "UPDATE delivery_type_entity SET color = '#1e90ff' WHERE color is null or color = '';";
            $this->databaseContext->executeNonQuery($q);

            $q = "UPDATE order_state_entity SET color = '#1e90ff' WHERE color is null  or color = '';";
            $this->databaseContext->executeNonQuery($q);

            $q = "UPDATE quote_status_entity SET color = '#1e90ff' WHERE color is null  or color = '';";
            $this->databaseContext->executeNonQuery($q);

            $q = "UPDATE payment_type_entity SET color = '#1e90ff' WHERE color is null  or color = '';";
            $this->databaseContext->executeNonQuery($q);

            $q = "SELECT is_delivery, active FROM delivery_type_entity WHERE id = 1;";
            $data = $this->databaseContext->getAll($q);

            /**
             * Error or is set
             */
            if (empty($data) || $data[0]["is_delivery"] == 1) {
                return true;
            }

            $q = "UPDATE delivery_type_entity SET is_delivery = 1 WHERE id = 1;";
            $this->databaseContext->executeNonQuery($q);

            if ($data[0]["active"] === null) {
                $q = "UPDATE delivery_type_entity SET active = 1;";
                $this->databaseContext->executeNonQuery($q);
            }

            $q = "SELECT id FROM s_store_entity";
            $stores = $this->databaseContext->getAll($q);

            if (!empty($stores)) {

                $showOnStore = array();

                foreach ($stores as $storeId) {
                    $showOnStore[$storeId["id"]] = 1;
                }

                $showOnStore = json_encode($showOnStore, JSON_UNESCAPED_UNICODE);

                $q = "UPDATE delivery_type_entity SET show_on_store = '{$showOnStore}';";
                $this->databaseContext->executeNonQuery($q);

                $q = "UPDATE payment_type_entity SET show_on_store = '{$showOnStore}';";
                $this->databaseContext->executeNonQuery($q);
            }

            echo "Postavljeni su show_on_store na delivery_type_entity i payment_type_entity\r\n";
            echo "Postavljen je is_delivery = 1 na delivery_type_entity id 1, potrebno je provjeriti da li na ovom projektu jos koji delivery type je dostava i na njega staviti is_delivery = 1\r\n";

            return true;
        } elseif ($func == "fix_s_product_configuration_product_group_link_entity") {

            if (isset($_ENV["DISABLE_PRODUCT_GROUP_CONFIGURATION_DELETE"]) && $_ENV["DISABLE_PRODUCT_GROUP_CONFIGURATION_DELETE"] != 1) {
                return false;
            }

            if (empty($this->databaseContext)) {
                $this->databaseContext = $this->getContainer()->get("database_context");
            }

            $q = "SELECT count(*) AS total FROM s_product_configuration_product_group_link_entity as spc WHERE s_product_attribute_configuration_id NOT IN (SELECT id FROM s_product_attribute_configuration_entity);";
            $data = $this->databaseContext->getSingleEntity($q);

            if (empty($data["total"])) {
                return true;
            }

            $q = "DELETE FROM s_product_configuration_product_group_link_entity";
            $this->databaseContext->executeNonQuery($q);

            echo "Obrisane su sve veze u s_product_configuration_product_group_link_entity\r\n";

            return true;
        } elseif ($func == "remove_settings_from_app_base") {
            // Remove old settings
            $contentExists = false;
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../app/Resources/views/base.html.twig");
            if (stripos($contents, "get_setting(") !== false) {
                $contentExists = true;
            }

            if ($contentExists) {
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/Resources/views/base.html.twig", "{{ get_setting(\"default_path\") }}", "/page/home/dashboard");
            }

            // Add per store view on front
            $contentExists = false;
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../app/Resources/views/base.html.twig");
            if (stripos($contents, '<a href="{{ frontendUrl }}" target="_blank" title="{% trans %}Show on frontend{% endtrans %}"><i class="fas fa-solar-panel"></i></a>') !== false) {
                $contentExists = true;
            }

            if ($contentExists) {
                $update = <<<UPDATE
{% for key,url in frontendUrl %}
    {% if key == "default" %}
        <a href="{{ url }}" target="_blank" title="{% trans %}Show on frontend{% endtrans %}"><i class="fas fa-solar-panel"></i></a>
    {% else %}
        <a href="{{ url }}" target="_blank" title="{{ key }}">{{ key }}</a>
    {% endif %}
{% endfor %}
UPDATE;
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/Resources/views/base.html.twig", '<a href="{{ frontendUrl }}" target="_blank" title="{% trans %}Show on frontend{% endtrans %}"><i class="fas fa-solar-panel"></i></a>', $update, false);
            }
        } elseif ($func == "data_transfer_business_bundle") {

            $bundleExists = false;
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../app/AppKernel.php");
            if (stripos($contents, "DataTransferBusinessBundle") !== false) {
                $bundleExists = true;
            }

            if ($bundleExists) {
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/config/config.yml", "DataTransferBusinessBundle");
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/AppKernel.php", "DataTransferBusinessBundle");
            }

            $helperManager->rmdir_recursive($_ENV["WEB_PATH"] . "../src/DataTransferBusinessBundle");
        } elseif ($func == "remove_shapebehat") {

            $bundleExists = false;
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../app/AppKernel.php");
            if (stripos($contents, "shapebehat") !== false) {
                $bundleExists = true;
            }

            if ($bundleExists) {
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/config/config.yml", "shapebehat");
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/AppKernel.php", "shapebehat");
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/AppKernel.php", "ShapeBehat");
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/AppKernel.php", 'new ShapeBehat(),');
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/AppKernel.php", 'use ShapeBehat\ShapeBehat;');
            }

            $helperManager->rmdir_recursive($_ENV["WEB_PATH"] . "../src/ShapeBehat");
        } elseif ($func == "google_images_business_bundle") {

            $bundleExists = false;
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../app/AppKernel.php");
            if (stripos($contents, "GoogleImagesDownloaderBundle") !== false) {
                $bundleExists = true;
            }

            if ($bundleExists) {
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/AppKernel.php", "GoogleImagesDownloaderBundle");
                $helperManager->rmdir_recursive($_ENV["WEB_PATH"] . "../src/GoogleImagesDownloaderBundle");
            }
        } elseif ($func == "remove_sendinblue_business_bundle") {
            $bundleExists = false;
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../app/AppKernel.php");
            if (stripos($contents, "SendinBlue") !== false) {
                $bundleExists = true;
            }

            if ($bundleExists) {

                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/config/config.yml", "sendin");
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/config/config.yml", "Our library supports");
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/config/config.yml", "timeout: 5000");
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/config/parameters.yml", "sendinblue");
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/config/parameters.yml.dist", "sendinblue");
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../.env", "sendinblue");
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/AppKernel.php", "SendinBlueApiBundle");
            }
        } elseif ($func == "update_routing_yml") {

            if ($_ENV["IS_PRODUCTION"]) {
                return false;
            }

            $contents = file_get_contents($_ENV["WEB_PATH"] . "../app/config/routing.yml");

            if (stripos($contents, 'crm:') !== false) {
                $contentExists = true;
            }

            if ($contentExists) {
                $update = <<<UPDATE
crm:
  resource: "@CrmBusinessBundle/Controller/"
  type: annotation
  host: "%backend_url%"
UPDATE;
                $textToFind = <<<UPDATE
crm:
  resource: "@CrmBusinessBundle/Controller/"
  type: annotation
UPDATE;
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/routing.yml", $textToFind, $update, false);

            }

            if (stripos($contents, 'app:') !== false) {
                $contentExists = true;
            }

            if ($contentExists) {
                $update = <<<UPDATE
app:
  resource: "@AppBundle/Controller/"
  type: annotation
  host: "%backend_url%"
UPDATE;
                $textToFind = <<<UPDATE
app:
  resource: "@AppBundle/Controller/"
  type: annotation
UPDATE;
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/routing.yml", $textToFind, $update, false);

            }

            if (stripos($contents, 'wiki:') !== false) {
                $contentExists = true;
            }

            if ($contentExists) {
                $update = <<<UPDATE
wiki:
  resource: "@WikiBusinessBundle/Controller/"
  type: annotation
  host: "%backend_url%"
UPDATE;
                $textToFind = <<<UPDATE
wiki:
  resource: "@WikiBusinessBundle/Controller/"
  type: annotation
UPDATE;
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/routing.yml", $textToFind, $update, false);

            }

            if (stripos($contents, 'notification:') !== false) {
                $contentExists = true;
            }

            if ($contentExists) {
                $update = <<<UPDATE
notification:
  resource: "@NotificationsAndAlertsBusinessBundle/Controller/"
  type: annotation
  host: "%backend_url%"
UPDATE;
                $textToFind = <<<UPDATE
notification:
  resource: "@NotificationsAndAlertsBusinessBundle/Controller/"
  type: annotation
UPDATE;
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/routing.yml", $textToFind, $update, false);

            }

            if (stripos($contents, 'gls:') !== false) {
                $contentExists = true;
            }

            if ($contentExists) {
                $update = <<<UPDATE
gls:
  resource: "@GLSBusinessBundle/Controller/"
  type: annotation
  host: "%backend_url%"
UPDATE;
                $textToFind = <<<UPDATE
gls:
  resource: "@GLSBusinessBundle/Controller/"
  type: annotation
UPDATE;
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/routing.yml", $textToFind, $update, false);

            }

            if (stripos($contents, 'task:') !== false) {
                $contentExists = true;
            }

            if ($contentExists) {
                $update = <<<UPDATE
task:
  resource: "@TaskBusinessBundle/Controller/"
  type: annotation
  host: "%backend_url%"
UPDATE;
                $textToFind = <<<UPDATE
task:
  resource: "@TaskBusinessBundle/Controller/"
  type: annotation
UPDATE;
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/routing.yml", $textToFind, $update, false);

            }

            if (stripos($contents, 'core_api:') !== false) {
                $contentExists = true;
            }

            if ($contentExists) {
                $update = <<<UPDATE
core_api:
  resource: "@AppBundle/Controller/CoreApiController/"
  type: annotation
  host: "%backend_url%"
UPDATE;
                $textToFind = <<<UPDATE
core_api:
  resource: "@AppBundle/Controller/CoreApiController/"
  type: annotation
UPDATE;
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/routing.yml", $textToFind, $update, false);

            }

            if (stripos($contents, 'administrator:') !== false) {
                $contentExists = true;
            }

            if ($contentExists) {
                $update = <<<UPDATE
administrator:
  resource: "@AppBundle/Controller/AdministratorController/"
  type: annotation
  host: "%backend_url%"
UPDATE;
                $textToFind = <<<UPDATE
administrator:
  resource: "@AppBundle/Controller/AdministratorController/"
  type: annotation
UPDATE;
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/routing.yml", $textToFind, $update, false);

            }


            return true;
        } elseif ($func == "remove_from_env") {

            $arg1 = $input->getArgument('arg1');
            $arg1 .= "=";

            $arg2 = $input->getArgument('arg2');

            $ret = $helperManager->getAllEnvFiles();

            foreach ($ret as $env) {

                if (!$arg2 && !$_ENV["IS_PRODUCTION"] && $env != ".env") {
                    continue;
                }

                $bundleExists = false;
                $contents = file_get_contents($_ENV["WEB_PATH"] . "../{$env}");
                if (stripos($contents, $arg1) !== false) {
                    $bundleExists = true;
                }

                if ($bundleExists) {
                    $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../{$env}", $arg1);
                    break;
                }
            }

            return true;
        } elseif ($func == "remove_bon_hr_business_bundle") {

            $bundleExists = false;
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../app/config/config.yml");
            if (stripos($contents, "BonHrBusinessBundle") !== false) {
                $bundleExists = true;
            }

            if ($bundleExists) {
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/config/config.yml", "BonHrBusinessBundle");
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/AppKernel.php", "BonHrBusinessBundle");
                $helperManager->rmdir_recursive($_ENV["WEB_PATH"] . "../src/BonHrBusinessBundle");
            }

            return true;
        } elseif ($func == "remove_asset_bundle") {

            $bundleExists = false;
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../app/AppKernel.php");
            if (stripos($contents, "AssetBundle") !== false) {
                $bundleExists = true;
            }

            if ($bundleExists) {
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/config/config.yml", "AssetBundle");
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/AppKernel.php", "AssetBundle");
                $helperManager->rmdir_recursive($_ENV["WEB_PATH"] . "../src/AssetBundle");
            }

            return true;
        } elseif ($func == "update_reset_password_form") {
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../src/FOS/UserBundle/Controller/ResettingController.php");
            if (stripos($contents, "isPasswordValid") === false) {
                unlink($_ENV["WEB_PATH"] . "../src/FOS/UserBundle/Controller/ResettingController.php");
                copy($_ENV["WEB_PATH"] . "../src/AppBundle/Resources/scripts/files_to_copy/ResettingController.php", $_ENV["WEB_PATH"] . "../src/FOS/UserBundle/Controller/ResettingController.php");

                unlink($_ENV["WEB_PATH"] . "../app/Resources/FOSUserBundle/views/Resetting/reset.html.twig");
                copy($_ENV["WEB_PATH"] . "../src/AppBundle/Resources/scripts/files_to_copy/reset.html.twig", $_ENV["WEB_PATH"] . "../app/Resources/FOSUserBundle/views/Resetting/reset.html.twig");
            }
            return true;
        } elseif ($func == "notification_regenerate") {

            /** @var DatabaseManager $databaseManager */
            $databaseManager = $this->getContainer()->get("database_manager");

            /** @var AdministrationManager $administrationManager */
            $administrationManager = $this->getContainer()->get("administration_manager");

            $entityType = $entityManager->getEntityTypeByCode("note");
            if (!empty($entityType)) {
                $databaseManager->createTableIfDoesntExist($entityType, null);
                $administrationManager->generateDoctrineXML($entityType, true);
                $administrationManager->generateEntityClasses($entityType, true);
            }

            $entityType = $entityManager->getEntityTypeByCode("note_user_like");
            if (!empty($entityType)) {
                $databaseManager->createTableIfDoesntExist($entityType, null);
                $administrationManager->generateDoctrineXML($entityType, true);
                $administrationManager->generateEntityClasses($entityType, true);
            }

            $basePath = $this->getContainer()->get('kernel')->locateResource('@AppBundle');

            if (file_exists($basePath . '/Resources/config/doctrine/NoteEntity.orm.xml')) {
                unlink($basePath . '/Resources/config/doctrine/NoteEntity.orm.xml');
                unlink($basePath . '/Entity/NoteEntity.php');
            }
            if (file_exists($basePath . '/Resources/config/doctrine/NoteUserLikeEntity.orm.xml')) {
                unlink($basePath . '/Resources/config/doctrine/NoteUserLikeEntity.orm.xml');
                unlink($basePath . '/Entity/NoteUserLikeEntity.php');
            }
        } elseif ($func == "entity_type_check_privileges") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "UPDATE entity_type SET check_privileges = 1;";

            $databaseContext->executeNonQuery($q);
        } elseif ($func == "transfer_addresses_to_address_entity") {

            /** @var AttributeContext $attributeContext */
            $attributeContext = $this->getContainer()->get("attribute_context");

            $accountEntityType = $entityManager->getEntityTypeByCode("account");

            $billingStreet = $attributeContext->getAttributeByCode("billing_street", $accountEntityType);
            if (empty($billingStreet)) {
                echo "No billing_street found on account. Assumed that is allready transfered";
                return false;
            }

            $cityType = "text";
            $billingCityId = $attributeContext->getAttributeByCode("billing_city_id", $accountEntityType);
            if (!empty($billingCityId)) {
                $cityType = "object";
            }

            /** @var AccountManager $accountManager */
            $accountManager = $this->getContainer()->get("account_manager");

            $accounts = $accountManager->getAllAccounts();

            if (!empty($accounts)) {

                /** @var AttributeSet $accountAddressAttributeSet */
                $accountAddressAttributeSet = $entityManager->getAttributeSetByCode("address");

                $accountAddresses = array();

                /** @var AccountEntity $account */
                foreach ($accounts as $account) {
                    $preparedAddresses = array();
                    if (!empty($account->getBillingStreet())) {
                        $preparedAddress = array();

                        $preparedAddress["street"] = $account->getBillingStreet();
                        $preparedAddress["billing"] = 1;
                        $preparedAddress["headquarters"] = 1;
                        if ($cityType == "object") {
                            $preparedAddress["city"] = $account->getBillingCity();
                        } else {
                            /** @var CityEntity $city */
                            $city = $accountManager->getCityByPostalCode($account->getBillingCode());
                            if (empty($city)) {

                                /** @var CityEntity $city */
                                $city = $entityManager->getNewEntityByAttributSetName("city");

                                $city->setName($account->getBillingCity());
                                $city->setPostalCode($account->getBillingCode());

                                $entityManager->saveEntityWithoutLog($city);
                            }
                            $preparedAddress["city"] = $city;
                        }

                        $preparedAddresses[] = $preparedAddress;
                    }
                    if (!empty($account->getShippingStreet())) {
                        $preparedAddress = array();

                        $preparedAddress["street"] = $account->getShippingStreet();
                        if (empty($account->getBillingStreet())) {
                            $preparedAddress["billing"] = 1;
                            $preparedAddress["headquarters"] = 1;
                        } else {
                            $preparedAddress["billing"] = 0;
                            $preparedAddress["headquarters"] = 0;
                        }
                        if ($cityType == "object") {
                            $preparedAddress["city"] = $account->getShippingCity();
                        } else {
                            /** @var CityEntity $city */
                            $city = $accountManager->getCityByPostalCode($account->getShippingCode());
                            if (empty($city)) {

                                /** @var CityEntity $city */
                                $city = $entityManager->getNewEntityByAttributSetName("city");

                                $city->setName($account->getShippingCity());
                                $city->setPostalCode($account->getShippingCode());

                                $entityManager->saveEntityWithoutLog($city);
                            }
                            $preparedAddress["city"] = $city;
                        }

                        $preparedAddresses[] = $preparedAddress;
                    }

                    if (empty($preparedAddresses)) {
                        continue;
                    }

                    foreach ($preparedAddresses as $preparedAddress) {

                        /** @var AddressEntity $address */
                        $address = $accountManager->getAddressByAccountAndStreet($account, $preparedAddress["street"]);

                        if (!empty($address)) {
                            continue;
                        }

                        $address = new AddressEntity();
                        $address->setAttributeSet($accountAddressAttributeSet);
                        $address->setEntityType($accountAddressAttributeSet->getEntityType());
                        $address->setCreated(new \DateTime());
                        $address->setModified(new \DateTime());
                        $address->setEntityStateId($account->getEntityStateId());
                        $address->setAccount($account);
                        $address->setBilling($preparedAddress["billing"]);
                        $address->setHeadquarters($preparedAddress["headquarters"]);
                        $address->setCity($preparedAddress["city"]);
                        $address->setStreet($preparedAddress["street"]);

                        $accountAddresses[] = $address;
                    }
                }

                if (!empty($accountAddresses)) {
                    $entityManager->saveArrayEntities($accountAddresses, $accountAddressAttributeSet->getEntityType());
                }
                $entityManager->clearManagerByEntityType($accountAddressAttributeSet->getEntityType());
                $entityManager->clearManagerByEntityType($accountEntityType);
            }
        } elseif ($func == "fix_document_attributes") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            /** @var EntityTypeContext $entityTypeContext */
            $entityTypeContext = $this->getContainer()->get("entity_type_context");

            /** @var AdministrationManager $administrationManager */
            $administrationManager = $this->getContainer()->get("administration_manager");

            /** @var DatabaseManager $databaseManager */
            $databaseManager = $this->getContainer()->get("database_manager");

            $documentEntityTypes = $entityTypeContext->getBy(array("isDocument" => "1"));
            if (!empty($documentEntityTypes)) {
                /** @var EntityType $documentEntityType */
                foreach ($documentEntityTypes as $documentEntityType) {
                    echo "Fixing " . $documentEntityType->getEntityTypeCode() . " document attributes\n";

                    $md5File = md5($documentEntityType->getEntityTypeCode() . "file");
                    $md5Source = md5($documentEntityType->getEntityTypeCode() . "file_source");
                    $md5Filename = md5($documentEntityType->getEntityTypeCode() . "filename");
                    $md5FileType = md5($documentEntityType->getEntityTypeCode() . "file_type");
                    $md5Size = md5($documentEntityType->getEntityTypeCode() . "size");

                    $documentsFolder = "/Documents/" . $documentEntityType->getEntityTypeCode() . "/";

                    $insertQuery = "INSERT IGNORE INTO attribute (
                        frontend_label,
                        frontend_input,
                        frontend_type,
                        frontend_hidden,
                        read_only,
                        frontend_display_on_new,
                        frontend_display_on_update,
                        frontend_display_on_view,
                        frontend_display_on_preview,
                        attribute_code,
                        entity_type_id,
                        backend_model,
                        backend_type,
                        backend_table,
                        folder,
                        uid
                    ) VALUES (
                        'File',
                        'file',
                        'file',
                        0,
                        1,
                        1,
                        1,
                        1,
                        1,
                        'file',
                        '{$documentEntityType->getId()}',
                        '{$documentEntityType->getEntityModel()}',
                        'varchar',
                        '{$documentEntityType->getEntityTable()}',
                        '{$documentsFolder}',
                        '{$md5File}'
                    ), (
                        'Source',
                        'text',
                        'text',
                        0,
                        1,
                        1,
                        1,
                        1,
                        1,
                        'file_source',
                        '{$documentEntityType->getId()}',
                        '{$documentEntityType->getEntityModel()}',
                        'varchar',
                        '{$documentEntityType->getEntityTable()}',
                        '{$documentsFolder}',
                        '{$md5Source}'
                    ), (
                        'Filename',
                        'text',
                        'text',
                        0,
                        1,
                        1,
                        1,
                        1,
                        1,
                        'filename',
                        '{$documentEntityType->getId()}',
                        '{$documentEntityType->getEntityModel()}',
                        'varchar',
                        '{$documentEntityType->getEntityTable()}',
                        NULL,
                        '{$md5Filename}'
                    ), (
                        'File type',
                        'text',
                        'text',
                        0,
                        1,
                        1,
                        1,
                        1,
                        1,
                        'file_type',
                        '{$documentEntityType->getId()}',
                        '{$documentEntityType->getEntityModel()}',
                        'varchar',
                        '{$documentEntityType->getEntityTable()}',
                        NULL,
                        '{$md5FileType}'
                    ), (
                        'Size',
                        'text',
                        'text',
                        0,
                        1,
                        1,
                        1,
                        1,
                        1,
                        'size',
                        '{$documentEntityType->getId()}',
                        '{$documentEntityType->getEntityModel()}',
                        'varchar',
                        '{$documentEntityType->getEntityTable()}',
                        NULL,
                        '{$md5Size}'
                    );";

                    $databaseContext->executeNonQuery($insertQuery);

                    if (strpos($documentEntityType->getEntityTypeCode(), "image") !== false /*&& $documentEntityType->getBundle()*/) {
                        echo "Fixing image alt and title for " . $documentEntityType->getEntityTypeCode() . " \n";

                        $md5Alt = md5($documentEntityType->getEntityTypeCode() . "alt");
                        $md5Title = md5($documentEntityType->getEntityTypeCode() . "title");
                        $md5Selected = md5($documentEntityType->getEntityTypeCode() . "selected");
                        $md5Order = md5($documentEntityType->getEntityTypeCode() . "ord");

                        $insertQuery = "INSERT IGNORE INTO attribute (
                            frontend_label,
                            frontend_input,
                            frontend_type,
                            frontend_hidden,
                            read_only,
                            frontend_display_on_new,
                            frontend_display_on_update,
                            frontend_display_on_view,
                            frontend_display_on_preview,
                            attribute_code,
                            entity_type_id,
                            backend_model,
                            backend_type,
                            backend_table,
                            folder,
                            uid
                        ) VALUES (
                            'Alt',
                            'text',
                            'text',
                            0,
                            1,
                            1,
                            1,
                            1,
                            1,
                            'alt',
                            '{$documentEntityType->getId()}',
                            '{$documentEntityType->getEntityModel()}',
                            'varchar',
                            '{$documentEntityType->getEntityTable()}',
                            NULL,
                            '{$md5Alt}'
                        ), (
                            'Title',
                            'text',
                            'text',
                            0,
                            1,
                            1,
                            1,
                            1,
                            1,
                            'title',
                            '{$documentEntityType->getId()}',
                            '{$documentEntityType->getEntityModel()}',
                            'varchar',
                            '{$documentEntityType->getEntityTable()}',
                            NULL,
                            '{$md5Title}'
                        ), (
                            'Selected',
                            'checkbox',
                            'checkbox',
                            0,
                            1,
                            1,
                            1,
                            1,
                            1,
                            'selected',
                            '{$documentEntityType->getId()}',
                            '{$documentEntityType->getEntityModel()}',
                            'bool',
                            '{$documentEntityType->getEntityTable()}',
                            NULL,
                            '{$md5Selected}'
                        ), (
                            'Order',
                            'integer',
                            'integer',
                            0,
                            1,
                            1,
                            1,
                            1,
                            1,
                            'ord',
                            '{$documentEntityType->getId()}',
                            '{$documentEntityType->getEntityModel()}',
                            'integer',
                            '{$documentEntityType->getEntityTable()}',
                            NULL,
                            '{$md5Order}'
                        );";

                        $databaseContext->executeNonQuery($insertQuery);
                    }

                    $databaseManager->addDocumentColumnsToTable($documentEntityType);
                    $administrationManager->generateDoctrineXML($documentEntityType, true);
                    $administrationManager->generateEntityClasses($documentEntityType, true);
                }
            }
        } elseif ($func == "validate_env") {

            $type = $input->getArgument('arg1');

            if (empty($type)) {
                return false;
            }

            $ret = $helperManager->validateEnv($type);
            if (empty($ret)) {
                print "All env valid\r\n";
            } else {
                print_r($ret);
                die;
            }
        } elseif ($func == "update_active_on_blog_category_s_page") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "SELECT id FROM attribute WHERE attribute_code = 'active' AND backend_table = 's_page_entity';";
            $data = $databaseContext->getAll($q);

            if (!empty($data)) {
                $q = "SELECT * FROM s_page_entity WHERE active IS NULL;";
                $data = $databaseContext->getAll($q);

                if (!empty($data)) {
                    $q = "UPDATE s_page_entity SET active = 1 WHERE entity_state_id = 1;";
                    $databaseContext->executeNonQuery($q);
                    $q = "UPDATE s_page_entity SET active = 0 WHERE entity_state_id = 2;";
                    $databaseContext->executeNonQuery($q);
                }
            }

            $q = "SELECT id FROM attribute WHERE attribute_code = 'active' AND backend_table = 'blog_category_entity';";
            $data = $databaseContext->getAll($q);

            if (!empty($data)) {
                $q = "SELECT * FROM blog_category_entity WHERE active IS NULL;";
                $data = $databaseContext->getAll($q);

                if (!empty($data)) {
                    $q = "UPDATE blog_category_entity SET active = 1 WHERE entity_state_id = 1;";
                    $databaseContext->executeNonQuery($q);
                    $q = "UPDATE blog_category_entity SET active = 0 WHERE entity_state_id = 2;";
                    $databaseContext->executeNonQuery($q);
                }
            }
        } elseif ($func == "remove_custom_files") {

            $table = $input->getArgument('arg1');
            if (empty($table)) {
                return false;
            }
            $uid = $input->getArgument('arg2');
            if (empty($uid)) {
                return false;
            }

            /** @var SyncManager $syncManager */
            $syncManager = $this->getContainer()->get("sync_manager");

            $syncManager->resetToDefault($table, $uid);
        } elseif ($func == "change_custom_files") {

            $table = $input->getArgument('arg1');
            if (empty($table)) {
                return false;
            }
            $uid = $input->getArgument('arg2');
            if (empty($uid)) {
                return false;
            }
            $isCustom = $input->getArgument('arg3');
            if (empty($isCustom)) {
                $isCustom = 0;
            }

            /** @var AdministrationManager $administrationManager */
            $administrationManager = $this->getContainer()->get("administration_manager");

            $entity = $administrationManager->getEntityByTableAndId($table, $uid);

            if (empty($entity)) {
                print_r("Missing entity");
                return false;
            }

            if ($table == "entity_type") {
                if (!$administrationManager->changeIsCustom($entity, 1)) {
                    print_r("Error changing entity_type custom");
                    return false;
                }
            } else {
                if (!$administrationManager->changeIsCustomByTable($table, $entity, $isCustom)) {
                    print_r("Error changing custom");
                    return false;
                }
            }


            return true;
        } elseif ($func == "delete_listview") {

            $table = $input->getArgument('arg1');
            if (empty($table)) {
                return false;
            }
            $uid = $input->getArgument('arg2');
            if (empty($uid)) {
                return false;
            }

            /** @var ListViewManager $listViewManager */
            $listViewManager = $this->getContainer()->get("list_view_manager");

            /** @var ListView $entity */
            $entity = $listViewManager->getListViewByUid($uid);

            if (empty($entity)) {
                return true;
            }

            $listViewManager->deleteListView($entity);

            return true;
        } elseif ($func == "delete_navigation_link") {

            $uid = $input->getArgument('arg1');
            if (empty($uid)) {
                return false;
            }

            /** @var NavigationLinkManager $navigationLinkManager */
            $navigationLinkManager = $this->getContainer()->get("navigation_link_manager");

            /** @var NavigationLink $entity */
            $entity = $navigationLinkManager->getNavigationLinkByUid($uid);

            if (empty($entity)) {
                return true;
            }

            $navigationLinkManager->delete($entity);

            return true;
        } elseif ($func == "delete_attribute") {

            $table = $input->getArgument('arg1');
            if (empty($table)) {
                return false;
            }
            $uid = $input->getArgument('arg2');
            if (empty($uid)) {
                return false;
            }

            /** @var AdministrationManager $administrationManager */
            $administrationManager = $this->getContainer()->get("administration_manager");

            /** @var Attribute $entity */
            $entity = $administrationManager->getEntityByTableAndId($table, $uid);

            if (empty($entity)) {
                return true;
            }

            /** @var DatabaseManager $databaseManager */
            $databaseManager = $this->getContainer()->get("database_manager");

            $databaseManager->deleteFieldIfExist($entity->getBackendTable(), $entity);

            $administrationManager->deleteAttribute($entity);

            return true;
        } elseif ($func == "delete_page_block") {

            $table = $input->getArgument('arg1');
            if (empty($table)) {
                return false;
            }
            $uid = $input->getArgument('arg2');
            if (empty($uid)) {
                return false;
            }

            /** @var BlockManager $blockManager */
            $blockManager = $this->getContainer()->get("block_manager");

            /** @var PageBlock $entity */
            $entity = $blockManager->getBlockByUid($uid);

            if (empty($entity)) {
                return true;
            }

            $blockManager->delete($entity);

            return true;
        } elseif ($func == "delete_entity_type") {

            $table = $input->getArgument('arg1');
            if (empty($table)) {
                return false;
            }
            $uid = $input->getArgument('arg2');
            if (empty($uid)) {
                return false;
            }

            /** @var AdministrationManager $administrationManager */
            $administrationManager = $this->getContainer()->get("administration_manager");

            /** @var EntityType $entity */
            $entity = $administrationManager->getEntityByTableAndId($table, $uid);

            if (empty($entity)) {
                return true;
            }

            $administrationManager->deleteEntityType($entity);

            return true;
        }
        elseif ($func == "delete_attribute_group") {

            $uid = $input->getArgument('arg1');
            if (empty($uid)) {
                return false;
            }

            /** @var AttributeGroupContext $attributeGroupContext */
            $attributeGroupContext = $this->getContainer()->get("attribute_group_context");

            /** @var EntityType $entity */
            $entity = $attributeGroupContext->getItemByUid($uid);

            if (empty($entity)) {
                return true;
            }

            /** @var AdministrationManager $administrationManager */
            $administrationManager = $this->getContainer()->get("administration_manager");

            $administrationManager->deleteAttributeGroup($entity);

            return true;
        }elseif ($func == "fix_entity_type_and_attribute_set_id") {

            $attribute_set_code = $input->getArgument('arg1');
            if (empty($attribute_set_code)) {
                $attribute_set_code = null;
            }

            /** @var AdministrationManager $administrationManager */
            $administrationManager = $this->getContainer()->get("administration_manager");

            $administrationManager->fixEntityTypeAndAttributeSetIdInEntities($attribute_set_code);

            return true;
        } elseif ($func == "generate_account_and_contact_for_admin") {

            $userId = $input->getArgument('arg1');
            if (empty($userId)) {
                return false;
            }

            /** @var CoreUserEntity $coreUser */
            $coreUser = $helperManager->getCoreUserById($userId);

            if (empty($coreUser)) {
                return false;
            }

            $helperManager->generateAccountAndContactForAdmin($coreUser);

            return true;
        } elseif ($func == "generate_account_and_contact_for_all_admin") {

            if (!isset($_ENV["FRONTEND_ADMIN_ACCOUNT_CREATE"]) || !$_ENV["FRONTEND_ADMIN_ACCOUNT_CREATE"]) {
                return false;
            }

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $frontendAdminAccountRoles = $_ENV["FRONTEND_ADMIN_ACCOUNT_ROLES"] ?? 0;
            if (empty($frontendAdminAccountRoles)) {
                return false;
            }
            $frontendAdminAccountRoles = json_decode($frontendAdminAccountRoles, true);
            if (empty($frontendAdminAccountRoles)) {
                return false;
            }

            $frontendAdminAccountRoles = implode("','", $frontendAdminAccountRoles);

            $q = "SELECT DISTINCT(ure.core_user_id) FROM user_role_entity as ure JOIN role_entity as r ON ure.role_id = r.id AND r.role_code IN ('{$frontendAdminAccountRoles}') WHERE ure.core_user_id NOT IN (SELECT core_user_id FROM contact_entity WHERE core_user_id IS NOT NULL);";
            $userIds = $databaseContext->getAll($q);

            if (empty($userIds)) {
                return false;
            }

            $userIds = array_column($userIds, "core_user_id");

            foreach ($userIds as $userId) {
                /** @var CoreUserEntity $coreUser */
                $coreUser = $helperManager->getCoreUserById($userId);

                if (empty($coreUser)) {
                    return false;
                }
                $helperManager->generateAccountAndContactForAdmin($coreUser);
            }

            return true;
        } elseif ($func == "validate_installation") {

            /**
             * Here we will do different checks if installation needs to be updated
             */
            /** @var AdministrationManager $administrationManager */
            $administrationManager = $this->getContainer()->get("administration_manager");

            if (!$administrationManager->checkIfBundleExists("SncRedisBundle")) {
                dump("Please install snc_redis as stated here: https://crm.shipshape-solutions.com/page/wiki_page/view/26");
                exit (0);
            }

            exit (1);
        } elseif ($func == "update_activity_attributes") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $test = "SELECT DATA_TYPE
                FROM ssinformation.COLUMNS
                WHERE TABLE_NAME = 'activity_entity' AND
                     COLUMN_NAME = 'date_start';";

            $check = $databaseContext->getSingleEntity($test);
            if (empty($check)) {
                return false;
            }
            if (!isset($check["DATA_TYPE"])) {
                return false;
            }
            if ($check["DATA_TYPE"] == "datetime") {
                return false;
            }

            $queries = array(
                "ALTER TABLE activity_entity MODIFY COLUMN date_start DATETIME(0) NULL DEFAULT NULL;",
                "ALTER TABLE activity_entity MODIFY COLUMN date_end DATETIME(0) NULL DEFAULT NULL;",
                "UPDATE activity_entity
                    SET date_start = DATE_ADD(date_start, INTERVAL TIME_TO_SEC(time_start) SECOND),
                        date_end = DATE_ADD(date_end, INTERVAL TIME_TO_SEC(time_end) SECOND),
                        time_start = NULL,
                        time_end = NULL;",
                "UPDATE activity_entity
                    SET entity_state_id = entity_state_id + 1
                    WHERE date_end IS NULL;",
                "UPDATE activity_entity SET duration = NULL;",
                "ALTER TABLE activity_entity MODIFY COLUMN duration INT NULL DEFAULT NULL;",
                "UPDATE activity_entity SET entity_state_id = entity_state_id + 1 WHERE date_start > date_end;",
                "UPDATE activity_entity SET duration = TIMESTAMPDIFF(SECOND, date_start, date_end) WHERE entity_state_id = 1;",
                "ALTER TABLE activity_entity DROP FOREIGN KEY `activity_entity_ibfk_1`;",
                "ALTER TABLE activity_entity DROP FOREIGN KEY `activity_entity_ibfk_2`;",
                "ALTER TABLE activity_entity DROP FOREIGN KEY `activity_entity_ibfk_3`;",
                "ALTER TABLE activity_entity DROP FOREIGN KEY `activity_entity_ibfk_4`;",
                "ALTER TABLE activity_entity DROP FOREIGN KEY `activity_entity_ibfk_5`;",
                "ALTER TABLE activity_entity DROP FOREIGN KEY `activity_entity_ibfk_6`;",
                "ALTER TABLE activity_entity DROP FOREIGN KEY `activity_entity_ibfk_7`;",
                "ALTER TABLE activity_contact_link_entity DROP FOREIGN KEY `activity_contact_link_entity_ibfk_1`;",
                "ALTER TABLE activity_contact_link_entity DROP FOREIGN KEY `activity_contact_link_entity_ibfk_2`;"
            );

            foreach ($queries as $query) {
                $databaseContext->executeNonQuery($query);
            }

            return true;
        } elseif ($func == "fix_id_data_type") {

            $dryRun = $input->getArgument('arg1');

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "SELECT CONCAT('ALTER TABLE ',c.TABLE_NAME,' MODIFY ',c.COLUMN_NAME,' INT(11) UNSIGNED') as q, c.TABLE_NAME, c.COLUMN_NAME FROM ssinformation.`COLUMNS` as c
                LEFT JOIN ssinformation.`TABLES` as t ON c.TABLE_NAME = t.TABLE_NAME
                WHERE c.table_schema in ('{$_ENV["DATABASE_NAME"]}') and t.TABLE_SCHEMA = '{$_ENV["DATABASE_NAME"]}' and DATA_TYPE = 'int' and COLUMN_TYPE != 'int(11) unsigned'
                AND COLUMN_NAME not in ('number_of_usage_per_customer','project_delivery_days_left') and t.TABLE_TYPE = 'BASE TABLE'
                ORDER BY LENGTH(COLUMN_NAME) DESC";
            $data = $databaseContext->getAll($q);

            //AUTO_INCREMENT NOT NULL

            if (empty($data)) {
                return true;
            }

            $dropFkQuery = array();
            $recreateFkQuery = array();

            foreach ($data as $d) {


                if ($d["COLUMN_NAME"] == "id") {
                    $d["q"] .= " AUTO_INCREMENT NOT NULL";
                }
                $d["q"] .= ";";
                $alterTableQuery[] = $d["q"];

                $q = "SELECT
                      TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME, REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
                    FROM
                      ssinformation.KEY_COLUMN_USAGE
                    WHERE
                      REFERENCED_TABLE_SCHEMA = '{$_ENV["DATABASE_NAME"]}' AND
                      REFERENCED_TABLE_NAME = '{$d["TABLE_NAME"]}' AND
                      REFERENCED_COLUMN_NAME = '{$d["COLUMN_NAME"]}';";
                $ref = $databaseContext->getAll($q);
                if (!empty($ref)) {
                    foreach ($ref as $r) {
                        $dropFkQuery[] = "ALTER TABLE {$r["TABLE_NAME"]} DROP FOREIGN KEY `{$r["CONSTRAINT_NAME"]}`;";
                        $recreateFkQuery[] = "ALTER TABLE {$r["TABLE_NAME"]} ADD CONSTRAINT `{$r["CONSTRAINT_NAME"]}` FOREIGN KEY ({$r["COLUMN_NAME"]}) REFERENCES {$r["REFERENCED_TABLE_NAME"]}({$r["REFERENCED_COLUMN_NAME"]}) ON DELETE CASCADE ON UPDATE CASCADE;";
                    }
                }
            }

            /** First drop FK */
            foreach ($dropFkQuery as $drop) {
                try {
                    if ($dryRun) {
                        dump($drop);
                    } else {
                        $databaseContext->executeNonQuery($drop);
                    }
                } catch (\Exception $e) {
                    dump($e->getMessage());
                }
            }

            /** Change all */
            foreach ($alterTableQuery as $alter) {
                try {
                    if ($dryRun) {
                        dump($alter);
                    } else {
                        $databaseContext->executeNonQuery($alter);
                    }
                } catch (\Exception $e) {
                    dump($e->getMessage());
                }
            }

            /** Add FK */
            foreach ($recreateFkQuery as $recreate) {
                try {
                    if ($dryRun) {
                        dump($recreate);
                    } else {
                        $databaseContext->executeNonQuery($recreate);
                    }
                } catch (\Exception $e) {
                    dump($e->getMessage());
                }
            }

            return true;
        } elseif ($func == "fix_id_autoincrement") {

            $dryRun = $input->getArgument('arg1');

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "
                SELECT CONCAT('ALTER TABLE ',c.TABLE_NAME,' MODIFY ',c.COLUMN_NAME,' INT(11) UNSIGNED AUTO_INCREMENT NOT NULL;') as q, c.TABLE_NAME, c.COLUMN_NAME FROM ssinformation.`COLUMNS` as c
                LEFT JOIN ssinformation.`TABLES` as t ON c.TABLE_NAME = t.TABLE_NAME
                WHERE c.table_schema in ('{$_ENV["DATABASE_NAME"]}') and t.TABLE_SCHEMA = '{$_ENV["DATABASE_NAME"]}' and DATA_TYPE = 'int'
                AND COLUMN_NAME not in ('number_of_usage_per_customer','project_delivery_days_left') and t.TABLE_TYPE = 'BASE TABLE' AND COLUMN_NAME = 'id' and EXTRA != 'auto_increment'
                ORDER BY LENGTH(COLUMN_NAME) DESC;";
            $data = $databaseContext->getAll($q);

            if (empty($data)) {
                return true;
            }

            $dropFkQuery = array();
            $recreateFkQuery = array();
            $alterTableQuery = array_column($data, "q");

            foreach ($data as $d) {

                $q = "SELECT
                      TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME, REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
                    FROM
                      ssinformation.KEY_COLUMN_USAGE
                    WHERE
                      REFERENCED_TABLE_SCHEMA = '{$_ENV["DATABASE_NAME"]}' AND
                      REFERENCED_TABLE_NAME = '{$d["TABLE_NAME"]}' AND
                      REFERENCED_COLUMN_NAME = '{$d["COLUMN_NAME"]}';";
                $ref = $databaseContext->getAll($q);
                if (!empty($ref)) {
                    foreach ($ref as $r) {
                        $dropFkQuery[] = "ALTER TABLE {$r["TABLE_NAME"]} DROP FOREIGN KEY `{$r["CONSTRAINT_NAME"]}`;";
                        $recreateFkQuery[] = "ALTER TABLE {$r["TABLE_NAME"]} ADD CONSTRAINT {$r["CONSTRAINT_NAME"]} FOREIGN KEY ({$r["COLUMN_NAME"]}) REFERENCES {$r["REFERENCED_TABLE_NAME"]}({$r["REFERENCED_COLUMN_NAME"]}) ON DELETE CASCADE ON UPDATE CASCADE;";
                    }
                }
            }

            /** First drop FK */
            foreach ($dropFkQuery as $drop) {
                try {
                    if ($dryRun) {
                        dump($drop);
                    } else {
                        $databaseContext->executeNonQuery($drop);
                    }
                } catch (\Exception $e) {
                    dump($e->getMessage());
                }
            }

            /** Change all */
            foreach ($alterTableQuery as $alter) {
                try {
                    if ($dryRun) {
                        dump($alter);
                    } else {
                        $databaseContext->executeNonQuery($alter);
                    }
                } catch (\Exception $e) {
                    dump($e->getMessage());
                }
            }

            /** Add FK */
            foreach ($recreateFkQuery as $recreate) {
                try {
                    if ($dryRun) {
                        dump($recreate);
                    } else {
                        $databaseContext->executeNonQuery($recreate);
                    }
                } catch (\Exception $e) {
                    dump($e->getMessage());
                }
            }

            return true;
        } elseif ($func == "change_collation") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "SELECT DISTINCT concat('ALTER DATABASE `', TABLE_SCHEMA, '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;') AS queries
                from INFORMATION_SCHEMA.TABLES
                where TABLE_SCHEMA = '{$_ENV["DATABASE_NAME"]}' and TABLE_COLLATION != 'utf8mb4_unicode_ci'
                UNION
                SELECT CONCAT('ALTER TABLE ', TABLE_SCHEMA, '.', TABLE_NAME,' COLLATE utf8mb4_unicode_ci;') AS queries
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA='{$_ENV["DATABASE_NAME"]}' and TABLE_COLLATION != 'utf8mb4_unicode_ci'
                AND TABLE_TYPE='BASE TABLE'
                UNION
                SELECT DISTINCT
                    CONCAT('ALTER TABLE ', C.TABLE_NAME, ' CHANGE ', C.COLUMN_NAME, ' ', C.COLUMN_NAME, ' ', C.COLUMN_TYPE, ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;') as queries
                FROM INFORMATION_SCHEMA.COLUMNS as C
                    LEFT JOIN INFORMATION_SCHEMA.TABLES as T
                        ON C.TABLE_NAME = T.TABLE_NAME
                WHERE C.COLLATION_NAME is not null and C.COLLATION_NAME != 'utf8mb4_unicode_ci'
                    AND C.TABLE_SCHEMA='{$_ENV["DATABASE_NAME"]}'
                    AND T.TABLE_TYPE='BASE TABLE'
                ;";
            $res = $databaseContext->getAll($q);

            if (!empty($res)) {
                $res = array_column($res, "queries");
                foreach ($res as $r) {
                    $databaseContext->executeNonQuery($r);
                }
            }

            return true;
        } elseif ($func == "update_contact_on_quote_and_order") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "UPDATE quote_entity as q LEFT JOIN quote_customer_entity as qc ON q.id = qc.quote_id SET q.contact_id = qc.contact_id;";
            try {
                $databaseContext->executeNonQuery($q);
            } catch (\Exception $e) {

            }

            $q = "UPDATE order_entity as o LEFT JOIN order_customer_entity as oc ON o.id = oc.order_id SET o.contact_id = oc.contact_id;";
            try {
                $databaseContext->executeNonQuery($q);
            } catch (\Exception $e) {

            }

            $command = $this->getApplication()->find('update:helper');

            /**
             * Delete invoice installment
             */
            $arguments = [
                'type' => 'delete_attribute',
                'arg1' => 'attribute',
                'arg2' => 'dbb817ba8d470d67599a3706c9d45203'

            ];
            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            /**
             * Delete quote installment
             */
            $arguments = [
                'type' => 'delete_entity_type',
                'arg1' => 'entity_type',
                'arg2' => '33550533d26202cf69859b30f708acc0'

            ];
            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            /**
             * Delete quote customer
             */
            $arguments = [
                'type' => 'delete_entity_type',
                'arg1' => 'entity_type',
                'arg2' => '4eb0affce85e3076503b12e8dacba661'

            ];
            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            /**
             * Delete order installment
             */
            $arguments = [
                'type' => 'delete_entity_type',
                'arg1' => 'entity_type',
                'arg2' => 'c133b37ed5dca6d733bd8b63fbf8abbf'

            ];
            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            /**
             * Delete order customer
             */
            $arguments = [
                'type' => 'delete_entity_type',
                'arg1' => 'entity_type',
                'arg2' => 'ba0f05c6f3b9baa8945b8b93b5c4261a'

            ];
            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            return true;
        } elseif ($func == "fk_to_md5") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "select kcu.referenced_column_name, fks.table_name as foreign_table,
                   '->' as rel,
                   fks.referenced_table_name
                          as primary_table,
                   fks.constraint_name,
                   group_concat(kcu.column_name
                        order by position_in_unique_constraint separator ', ')
                         as fk_columns
            from ssinformation.referential_constraints fks
            join ssinformation.key_column_usage kcu
                 on fks.constraint_schema = kcu.table_schema
                 and fks.table_name = kcu.table_name
                 and fks.constraint_name = kcu.constraint_name
             where fks.constraint_schema = '{$_ENV["DATABASE_NAME"]}' and fks.constraint_name REGEXP '_'
            group by fks.constraint_schema,
                     fks.table_name,
                     fks.unique_constraint_schema,
                     fks.referenced_table_name,
                     fks.constraint_name
            order by fks.constraint_schema,
                     fks.table_name;";
            $foreginKeys = $databaseContext->getAll($q);

            if (empty($foreginKeys)) {
                return false;
            }

            foreach ($foreginKeys as $fk) {

                $fk_name = md5("{$fk["foreign_table"]}_{$fk["primary_table"]}_{$fk["fk_columns"]}");

                $q = "ALTER TABLE {$fk["foreign_table"]}
                    DROP FOREIGN KEY `{$fk["constraint_name"]}`,
                    ADD CONSTRAINT `{$fk_name}` FOREIGN KEY (`{$fk["fk_columns"]}`) REFERENCES `{$fk["primary_table"]}` (`{$fk["referenced_column_name"]}`) ON DELETE CASCADE ON UPDATE CASCADE;";
                echo $q . "\r\n";

                try {
                    $databaseContext->executeNonQuery($q);
                } catch (\Exception $e) {
                    $q = "ALTER TABLE {$fk["foreign_table"]}
                    DROP FOREIGN KEY `{$fk["constraint_name"]}`";
                    echo $q . "\r\n";

                    $databaseContext->executeNonQuery($q);
                }
            }

            return true;
        } elseif ($func == "product_account_price_set_uq") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            try {
                $q = "DROP INDEX product_account_price ON product_account_price_entity;";
                $databaseContext->executeNonQuery($q);
            } catch (\Exception $e) {

            }

            try {
                $q = "ALTER TABLE product_account_price_entity ADD CONSTRAINT product_account_price UNIQUE KEY(product_id,account_id);";
                $databaseContext->executeNonQuery($q);
            } catch (\Exception $e) {

            }

            return true;
        } elseif ($func == "update_s_front_block_show_on_store") {

            /** @var RouteManager $routeManager */
            $routeManager = $this->getContainer()->get("route_manager");

            $throwError = array(
                "labtex",
                "superknjizara",
                "grama",
                "dječja",
                "grama",
                "infoam",
                "encian",
                "MakromikroBusiness"
            );

            foreach ($throwError as $t) {
                if (stripos($_ENV["FRONTEND_BUNDLE"], $t) !== false) {
                    throw new \Exception("Please check s_front_block show_on_store mannualy!!!");
                }
            }

            $showOnStoreArray = array();
            $stores = $routeManager->getStores();

            /** @var SStoreEntity $store */
            foreach ($stores as $store) {
                $showOnStoreArray[$store->getId()] = 1;
            }

            $showOnStoreJson = json_encode($showOnStoreArray, JSON_UNESCAPED_UNICODE);

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "UPDATE s_front_block_entity SET show_on_store = '{$showOnStoreJson}';";
            $databaseContext->executeNonQuery($q);

            return true;
        } elseif ($func == "update_missing_uid") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "SELECT * FROM entity_type WHERE sync_content = 1;";
            $entityTypes = $databaseContext->getAll($q);

            foreach ($entityTypes as $et) {
                try {
                    $q = "UPDATE {$et["entity_table"]} SET uid = MD5(id) WHERE uid is null;";
                    $databaseContext->executeNonQuery($q);
                } catch (\Exception $e) {
                    dump($q);
                }
            }

            return true;
        } elseif ($func == "transfer_from_env_to_settings") {

            $arg1 = $input->getArgument('arg1');
            if (empty($arg1)) {
                return false;
            }

            if (!isset($_ENV[$arg1])) {
                print("{$arg1} allready transfered\r\n");
                return true;
            }

            $arg1l = strtolower($arg1);

            /** @var ApplicationSettingsManager $applicationSettingsManager */
            $applicationSettingsManager = $this->getContainer()->get("application_settings_manager");

            /** @var SettingsEntity $setting */
            $setting = $applicationSettingsManager->getRawApplicationSettingEntityByCode($arg1l);
            if (empty($setting)) {

                /** @var RouteManager $routeManager */
                $routeManager = $this->getContainer()->get("route_manager");

                $stores = $routeManager->getStores();

                $nameArray = $settingsValueArray = $showOnStoreArray = array();

                /** @var SStoreEntity $store */
                foreach ($stores as $store) {
                    $nameArray[$store->getId()] = $arg1l;
                    $settingsValueArray[$store->getId()] = $_ENV[$arg1];
                    $showOnStoreArray[$store->getId()] = 1;
                }

                $data = array();
                $data["name"] = $nameArray;
                $data["code"] = $arg1l;
                $data["settings_value"] = $settingsValueArray;
                $data["show_on_store"] = $showOnStoreArray;

                $applicationSettingsManager->createUpdateSettings($setting, $data);
            }

            $command = $this->getApplication()->find('update:helper');

            /**
             * Remove from env
             */
            $arguments = [
                'type' => 'remove_from_env',
                'arg1' => $arg1

            ];
            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);

            return true;
        } elseif ($func == "update_default_settings") {

            $arg1 = $input->getArgument('arg1');
            if (empty($arg1)) {
                $arg1 = false;
            }

            $restManager = new RestManager();

            $restManager->CURLOPT_POST = 1;
            $restManager->CURLOPT_CUSTOMREQUEST = "POST";
            $restManager->CURLOPT_HTTPHEADER = array("Content-Type: application/json");

            $res = $restManager->get("https://crm.shipshape-solutions.com/api/get_default_settings");

            if (empty($res) || !isset($res["error"]) || $res["error"] == true || empty($res["data"])) {
                dump("Error getting default settings");
                return false;
            }

            /** @var ApplicationSettingsManager $applicationSettingsManager */
            $applicationSettingsManager = $this->getContainer()->get("application_settings_manager");

            /** @var RouteManager $routeManager */
            $routeManager = $this->getContainer()->get("route_manager");

            $stores = $routeManager->getStores();

            $command = $this->getApplication()->find('update:helper');

            foreach ($res["data"] as $d) {
                /** @var SettingsEntity $setting */
                $setting = $applicationSettingsManager->getRawApplicationSettingEntityByCode($d["code"]);

                $value = null;
                if (isset($_ENV[strtoupper($d["code"])])) {
                    $value = $_ENV[strtoupper($d["code"])];
                }

                $nameArray = $settingsValueArray = $showOnStoreArray = array();

                /** @var SStoreEntity $store */
                foreach ($stores as $store) {
                    $nameArray[$store->getId()] = $d["name"];
                    //todo descriptino
                    $showOnStoreArray[$store->getId()] = 1;
                }

                $data = array();
                $data["name"] = $nameArray;
                $data["code"] = $d["code"];

                if (empty($setting)) {

                    if ($arg1 || !isset($_ENV[strtoupper($d["code"])])) {
                        $value = $d["default_value"];
                    }

                    /** @var SStoreEntity $store */
                    foreach ($stores as $store) {
                        $settingsValueArray[$store->getId()] = $value;
                    }

                    $data["settings_value"] = $settingsValueArray;
                    $data["show_on_store"] = $showOnStoreArray;
                } else {

                    if ($arg1) {
                        $value = $d["default_value"];

                        /** @var SStoreEntity $store */
                        foreach ($stores as $store) {
                            $settingsValueArray[$store->getId()] = $value;
                        }

                        $data["show_on_store"] = $showOnStoreArray;
                        $data["settings_value"] = $settingsValueArray;
                    }
                }

                if (isset($data["show_on_store"])) {
                    $applicationSettingsManager->createUpdateSettings($setting, $data);
                }

                if (isset($_ENV[strtoupper($d["code"])])) {


                    /**
                     * Remove from env
                     */
                    $arguments = [
                        'type' => 'remove_from_env',
                        'arg1' => strtoupper($d["code"])
                    ];
                    $greetInput = new ArrayInput($arguments);
                    $command->run($greetInput, $output);
                }
            }

            return true;
        } elseif ($func == "update_1_8") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "ALTER TABLE order_entity MODIFY COLUMN additional_data LONGTEXT;";
            $databaseContext->executeNonQuery($q);

            $q = "ALTER TABLE quote_entity MODIFY COLUMN additional_data LONGTEXT;";
            $databaseContext->executeNonQuery($q);

            return true;
        } elseif ($func == "update_1_9") {

            if (!$_ENV["IS_PRODUCTION"]) {
                $contentExists = false;
                $contents = file_get_contents($_ENV["WEB_PATH"] . "../.gitignore");
                if (stripos($contents, "/web/backend") !== false && stripos($contents, "!/web/backend/style") === false) {
                    $contentExists = true;
                }

                $updateText = <<<UPDATE
/web/backend/*
!/web/backend/style.css
UPDATE;

                if ($contentExists) {
                    $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../.gitignore", "/web/backend", $updateText, false);
                }

                $contents = file_get_contents($_ENV["WEB_PATH"] . "../.gitignore");
                if (stripos($contents, "/web/documents.tar.gz") === false) {
                    file_put_contents($_ENV["WEB_PATH"] . "../.gitignore", "/web/documents.tar.gz\n", FILE_APPEND);
                }

            }

            return true;
        } elseif ($func == "update_2_1") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "UPDATE contact_entity SET password = null WHERE password is not null;";
            $databaseContext->executeNonQuery($q);

            /**
             * Remove custom attr
             */
            if (stripos($_ENV["FRONTEND_BUNDLE"], "MakromikroSkupinaBusinessBundle") === false) {
                $command = $this->getApplication()->find('update:helper');

                $arguments = [
                    'type' => 'delete_attribute',
                    'arg1' => 'attribute',
                    'arg2' => '627d2084cf2551.07704998'
                ];

                $greetInput = new ArrayInput($arguments);
                $command->run($greetInput, $output);
            }

            $q = "ALTER TABLE product_price_history_entity ADD INDEX `history_get_index` (product_id,price_change_date);";
            $databaseContext->executeNonQuery($q);

            return true;
        } elseif ($func == "update_2_3") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "UPDATE user_entity SET roles = 'a:1:{i:0;s:10:\"ROLE_ADMIN\";}' WHERE username = 'vjurkovic';";
            $databaseContext->executeNonQuery($q);

            return true;
        } elseif ($func == "update_2_5") {

            $bundleExists = false;
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../app/AppKernel.php");
            if (stripos($contents, "SentryBundle") !== false) {
                $bundleExists = true;
            }

            if ($bundleExists) {

                $textToFind = <<<UPDATE
#SENTRY
sentry:
  dsn: "%env(SENTRY_KEY)%"
  error_types: E_ALL & ~E_DEPRECATED & ~E_NOTICE
  environment: "%kernel.environment%"
UPDATE;
                $update = "";
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/config.yml", $textToFind, $update, false);

                $textToFind = <<<UPDATE
    sentry:
      type: raven
      dsn: "%env(SENTRY_KEY)%"
      level: error
UPDATE;
                $update = "";
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/config.yml", $textToFind, $update, false);


                $textToFind = <<<UPDATE
      members: [ sentry, streamed_main ]
UPDATE;
                $update = <<<UPDATE
      members: [ streamed_main ]
UPDATE;
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/config.yml", $textToFind, $update, false);

                $textToFind = <<<UPDATE
      members: [sentry, streamed_main]
UPDATE;
                $update = <<<UPDATE
      members: [ streamed_main ]
UPDATE;
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/config/config.yml", $textToFind, $update, false);

                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/AppKernel.php", "SentryBundle");
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/config/parameters.yml", "sentry");
                $helperManager->removeLineFromFile($_ENV["WEB_PATH"] . "../app/config/parameters.yml.dist", "sentry");
            }

            return true;
        } elseif ($func == "update_2_6") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "ALTER TABLE transaction_email_sent_entity MODIFY content LONGTEXT;";
            $databaseContext->executeNonQuery($q);

            $q = "ALTER TABLE error_log_entity MODIFY description LONGTEXT;";
            $databaseContext->executeNonQuery($q);

            return true;
        } elseif ($func == "update_2_7") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "ALTER TABLE import_log_entity MODIFY error_log LONGTEXT;";
            $databaseContext->executeNonQuery($q);

            return true;
        } elseif ($func == "update_2_8") {

            if (!$_ENV["IS_PRODUCTION"]) {
                unlink($_ENV["WEB_PATH"] . "../src/FOS/UserBundle/Controller/ResettingController.php");
                copy($_ENV["WEB_PATH"] . "../src/AppBundle/Resources/scripts/files_to_copy/ResettingController.php", $_ENV["WEB_PATH"] . "../src/FOS/UserBundle/Controller/ResettingController.php");

                unlink($_ENV["WEB_PATH"] . "../app/Resources/FOSUserBundle/views/Resetting/reset.html.twig");
                copy($_ENV["WEB_PATH"] . "../src/AppBundle/Resources/scripts/files_to_copy/reset.html.twig", $_ENV["WEB_PATH"] . "../app/Resources/FOSUserBundle/views/Resetting/reset.html.twig");
            }

            return true;
        } elseif ($func == "update_2_9") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "ALTER TABLE wiki_page_entity MODIFY content LONGTEXT;";
            $databaseContext->executeNonQuery($q);

            $q = "ALTER TABLE import_log_entity MODIFY params LONGTEXT;";
            $databaseContext->executeNonQuery($q);

            return true;
        } elseif ($func == "update_3_2") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = $this->addColumnQuery("shape_track_order_item_fact", "discount_coupon_id", "int(11)");
            if (!empty($q)) {
                $databaseContext->executeNonQuery($q);
            }
            $q = $this->addColumnQuery("shape_track_order_item_fact", "discount_coupon_price_total", "decimal(14,2)");
            if (!empty($q)) {
                $databaseContext->executeNonQuery($q);
            }

        } elseif ($func == "update_3_3") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "ALTER TABLE payment_transaction_log_entity MODIFY response_data LONGTEXT;";
            $databaseContext->executeNonQuery($q);

            $q = "ALTER TABLE payment_transaction_log_entity MODIFY request_data LONGTEXT;";
            $databaseContext->executeNonQuery($q);

        } elseif ($func == "update_3_4") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            try {
                $q = "ALTER TABLE product_document_entity DROP FOREIGN KEY `30fe957123489fe9b7c4dc42f361aabb`;";
                $databaseContext->executeNonQuery($q);
            } catch (\Exception $e) {

            }

            try {
                $q = "ALTER TABLE product_document_entity
                    ADD CONSTRAINT `30fe957123489fe9b7c4dc42f361aabb` FOREIGN KEY `(`product_document_type_id`)` REFERENCES `product_document_type_entity` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;";
                $databaseContext->executeNonQuery($q);
            } catch (\Exception $e) {

            }
        } elseif ($func == "update_3_5") {
        } elseif ($func == "update_3_6") {
            /** @var WebformManager $webformManager */
            $webformManager = $this->getContainer()->get("webform_manager");

            $fieldTypeCodes = [];
            $fieldTypes = $webformManager->getWebformFieldTypes();

            /** @var WebformFieldTypeEntity $fieldType */
            foreach ($fieldTypes as $fieldType) {
                $fieldTypeCodes[] = $fieldType->getFieldTypeCode();
            }

            if (!in_array("checkbox", $fieldTypeCodes)) {
                /** @var WebformFieldTypeEntity $fieldType */
                $fieldType = $entityManager->getNewEntityByAttributSetName("webform_field_type");
                $fieldType->setName("Checkbox");
                $fieldType->setFieldTypeCode("checkbox");
                $entityManager->saveEntityWithoutLog($fieldType);
            }
            if (!in_array("checkbox", $fieldTypeCodes)) {
                /** @var WebformFieldTypeEntity $fieldType */
                $fieldType = $entityManager->getNewEntityByAttributSetName("webform_field_type");
                $fieldType->setName("Text field");
                $fieldType->setFieldTypeCode("text_field");
                $entityManager->saveEntityWithoutLog($fieldType);
            }
            if (!in_array("checkbox", $fieldTypeCodes)) {
                /** @var WebformFieldTypeEntity $fieldType */
                $fieldType = $entityManager->getNewEntityByAttributSetName("webform_field_type");
                $fieldType->setName("Radio");
                $fieldType->setFieldTypeCode("radio");
                $entityManager->saveEntityWithoutLog($fieldType);
            }
            if (!in_array("checkbox", $fieldTypeCodes)) {
                /** @var WebformFieldTypeEntity $fieldType */
                $fieldType = $entityManager->getNewEntityByAttributSetName("webform_field_type");
                $fieldType->setName("E-mail");
                $fieldType->setFieldTypeCode("e_mail");
                $entityManager->saveEntityWithoutLog($fieldType);
            }
            if (!in_array("checkbox", $fieldTypeCodes)) {
                /** @var WebformFieldTypeEntity $fieldType */
                $fieldType = $entityManager->getNewEntityByAttributSetName("webform_field_type");
                $fieldType->setName("Select");
                $fieldType->setFieldTypeCode("select");
                $entityManager->saveEntityWithoutLog($fieldType);
            }
            if (!in_array("checkbox", $fieldTypeCodes)) {
                /** @var WebformFieldTypeEntity $fieldType */
                $fieldType = $entityManager->getNewEntityByAttributSetName("webform_field_type");
                $fieldType->setName("file");
                $fieldType->setFieldTypeCode("file");
                $entityManager->saveEntityWithoutLog($fieldType);
            }
            if (!in_array("datetime", $fieldTypeCodes)) {
                /** @var WebformFieldTypeEntity $fieldType */
                $fieldType = $entityManager->getNewEntityByAttributSetName("webform_field_type");
                $fieldType->setName("Date time");
                $fieldType->setFieldTypeCode("datetime");
                $entityManager->saveEntityWithoutLog($fieldType);
            }
            if (!in_array("autocomplete", $fieldTypeCodes)) {
                /** @var WebformFieldTypeEntity $fieldType */
                $fieldType = $entityManager->getNewEntityByAttributSetName("webform_field_type");
                $fieldType->setName("Autocomplete");
                $fieldType->setFieldTypeCode("autocomplete");
                $entityManager->saveEntityWithoutLog($fieldType);
            }
            if (!in_array("html", $fieldTypeCodes)) {
                /** @var WebformFieldTypeEntity $fieldType */
                $fieldType = $entityManager->getNewEntityByAttributSetName("webform_field_type");
                $fieldType->setName("HTML");
                $fieldType->setFieldTypeCode("html");
                $entityManager->saveEntityWithoutLog($fieldType);
            }
        } elseif ($func == "update_3_7") {
            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "SELECT product_id FROM product_price_history_entity WHERE price_change_date <= '2022-07-01';";
            $lowestPrices = $databaseContext->getAll($q);

            $usedIds = array();
            if (!empty($lowestPrices)) {
                $usedIds = array_column($lowestPrices, "product_id");
            }

            $additionalWhere = "";
            if (!empty($usedIds)) {
                $additionalWhere = " AND id NOT IN (" . implode(",", $usedIds) . ") ";
            }

            $q = "SELECT id, price_retail,entity_type_id,currency_id FROM product_entity WHERE entity_state_id = 1 AND price_retail > 0 {$additionalWhere};";
            $data = $databaseContext->getAll($q);

            if (empty($data)) {
                return true;
            }

            /** @var EntityManager $entityManager */
            $entityManager = $this->getContainer()->get("entity_manager");

            /** @var AttributeSet $at */
            $at = $entityManager->getAttributeSetByCode("product_price_history");

            $insertQuery = "INSERT INTO product_price_history_entity (entity_type_id,attribute_set_id,created,modified,modified_by,created_by,entity_state_id,product_id,price_change_date,price,price_type,related_entity_type,related_entity_id,product_currency_id) VALUES (";
            $insertQueryValues = array();

            $i = 0;

            foreach ($data as $d) {
                $insertQueryValues[] = "{$at->getEntityTypeId()},{$at->getId()},'2022-07-01','2022-07-01','system','system',1,{$d["id"]},'2022-07-01',{$d["price_retail"]},'price_retail','product_entity','{$d["entity_type_id"]}','{$d["currency_id"]}' ";
                $i++;

                if ($i >= 1000) {
                    $q = $insertQuery . implode("),(", $insertQueryValues) . ")";
                    $databaseContext->executeNonQuery($q);
                    $insertQueryValues = array();
                    $i = 0;
                }
            }

            if (!empty($insertQueryValues)) {
                $q = $insertQuery . implode("),(", $insertQueryValues) . ")";
                $databaseContext->executeNonQuery($q);
            }

            return true;
        } elseif ($func == "update_3_8") {
            /** @var WebformManager $webformManager */
            $webformManager = $this->getContainer()->get("webform_manager");

            $fieldTypeCodes = [];
            $fieldTypes = $webformManager->getWebformFieldTypes();

            /** @var WebformFieldTypeEntity $fieldType */
            foreach ($fieldTypes as $fieldType) {
                $fieldTypeCodes[] = $fieldType->getFieldTypeCode();
            }

            if (!in_array("textarea", $fieldTypeCodes)) {
                /** @var WebformFieldTypeEntity $fieldType */
                $fieldType = $entityManager->getNewEntityByAttributSetName("webform_field_type");
                $fieldType->setName("Textarea");
                $fieldType->setFieldTypeCode("textarea");
                $entityManager->saveEntityWithoutLog($fieldType);
            }
        } elseif ($func == "update_3_9") {
            /** @var WebformManager $webformManager */
            $webformManager = $this->getContainer()->get("webform_manager");

            $fieldTypeCodes = [];
            $fieldTypes = $webformManager->getWebformFieldTypes();

            /** @var WebformFieldTypeEntity $fieldType */
            foreach ($fieldTypes as $fieldType) {
                $fieldTypeCodes[] = $fieldType->getFieldTypeCode();
            }

            if (!in_array("date", $fieldTypeCodes)) {
                /** @var WebformFieldTypeEntity $fieldType */
                $fieldType = $entityManager->getNewEntityByAttributSetName("webform_field_type");
                $fieldType->setName("Date");
                $fieldType->setFieldTypeCode("date");
                $entityManager->saveEntityWithoutLog($fieldType);
            }
        } elseif ($func == "update_4_0") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "SELECT id,filter_key FROM s_product_attribute_configuration_entity WHERE filter_key LIKE '%-%';";
            $data = $databaseContext->getAll($q);

            if(!empty($data)){
                $update = Array();
                foreach ($data as $d){
                    $filterKey = $d["filter_key"];
                    $filterKey = str_ireplace("-","_",$filterKey);
                    $filterKey = preg_replace("/_+/", "_", $filterKey);

                    $update[] = "UPDATE s_product_attribute_configuration_entity SET filter_key = '{$filterKey}' WHERE id = {$d["id"]};";
                }
                if(!empty($update)){
                    $databaseContext->executeNonQuery(implode(" ",$update));
                }
            }
        }
        elseif ($func == "update_4_1") {

            if($_ENV["IS_PRODUCTION"]){
                return true;
            }

            // Remove old settings
            $contentExists = false;
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../app/Resources/views/base.html.twig");
            if (stripos($contents, "{% if app.user.id == 1 %}") !== false) {
                $contentExists = true;
            }

            if ($contentExists) {
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../app/Resources/views/base.html.twig", "{% if app.user.id == 1 %}", "{% if 'ROLE_ADMIN' in app.user.roles %}");
            }
        }
        elseif ($func == "update_4_3") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            /**
             * Check if event time exists in shape_track table
             */
            $q = "SHOW COLUMNS FROM shape_track LIKE 'event_time';";
            $data = $databaseContext->getAll($q);

            if(empty($data)){
                $q = "ALTER TABLE shape_track CHANGE time event_time DATETIME(0) NULL DEFAULT NULL;";
                $databaseContext->executeNonQuery($q);
            }

            /**
             * Set prava za Viki
             */
            $q = "UPDATE user_entity SET entity_state_id = 2 WHERE email = 'viktorija@shipshape-solutions.com';";
            $databaseContext->executeNonQuery($q);

            $q = "SELECT * FROM user_entity WHERE email = 'viktorija@shipshape-solutions.com'";
            $users = $databaseContext->getAll($q);

            if(!empty($users)){

                if(empty($this->entityManager)){
                    $this->entityManager = $this->getContainer()->get("entity_manager");
                }

                /** @var AttributeSet $attributeSet */
                $attributeSet = $this->entityManager->getAttributeSetByCode("core_user_role_link");

                $q = "INSERT IGNORE INTO user_role_entity (core_user_id,role_id,created,modified,entity_state_id,attribute_set_id,entity_type_id,created_by,modified_by) VALUES ({$users[0]['id']},1,NOW(),NOW(),1,{$attributeSet->getId()},{$attributeSet->getEntityTypeId()},'system','system');";
                $databaseContext->executeNonQuery($q);
            }

            /**
             * Set prava za Ivana
             */
            $q = "UPDATE user_entity SET entity_state_id = 2 WHERE email = 'ivan@shipshape-solutions.com';";
            $databaseContext->executeNonQuery($q);

            $q = "SELECT * FROM user_entity WHERE email = 'ivan@shipshape-solutions.com'";
            $users = $databaseContext->getAll($q);

            if(!empty($users)){

                if(empty($this->entityManager)){
                    $this->entityManager = $this->getContainer()->get("entity_manager");
                }

                /** @var AttributeSet $attributeSet */
                $attributeSet = $this->entityManager->getAttributeSetByCode("core_user_role_link");

                $q = "INSERT IGNORE INTO user_role_entity (core_user_id,role_id,created,modified,entity_state_id,attribute_set_id,entity_type_id,created_by,modified_by) VALUES ({$users[0]['id']},1,NOW(),NOW(),1,{$attributeSet->getId()},{$attributeSet->getEntityTypeId()},'system','system');";
                $databaseContext->executeNonQuery($q);
            }
        }
        elseif ($func == "update_4_4") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            try{
                $q = "ALTER TABLE import_log_entity MODIFY response_data LONGTEXT;";
                $databaseContext->executeNonQuery($q);
            }
            catch (\Exception $e){

            }
        }
        elseif ($func == "update_4_5") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "ALTER TABLE campaign_entity MODIFY COLUMN start_date DATETIME;";
            $databaseContext->executeNonQuery($q);

            $q = "ALTER TABLE campaign_entity MODIFY COLUMN end_date DATETIME;";
            $databaseContext->executeNonQuery($q);
        }
        elseif ($func == "update_4_6") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "UPDATE brand_entity SET is_active=1 WHERE entity_state_id=1 AND is_active IS NULL;";
            $databaseContext->executeNonQuery($q);

            $q = "UPDATE brand_entity SET is_active=0 WHERE entity_state_id=2 AND is_active IS NULL;";
            $databaseContext->executeNonQuery($q);
        }
        elseif ($func == "update_4_8") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            try{
                $q = "ALTER TABLE shape_track_order_item_fact MODIFY product_groups VARCHAR(1000);";
                $databaseContext->executeNonQuery($q);
            }
            catch (\Exception $e){

            }

            try{
                $q = "ALTER TABLE error_log_entity MODIFY trace LONGTEXT;";
                $databaseContext->executeNonQuery($q);
            }
            catch (\Exception $e){

            }

            try{
                $q = "ALTER TABLE cron_job_history_entity MODIFY error_log LONGTEXT;";
                $databaseContext->executeNonQuery($q);
            }
            catch (\Exception $e){

            }
        }
        elseif ($func == "update_4_9") {

            $contentExists = false;
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../configuration.env");
            if (stripos($contents, "SUPPORT_EMAIL=support@shipshape-solutions.com") !== false) {
                $contentExists = true;
            }

            if ($contentExists) {
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../configuration.env", "SUPPORT_EMAIL=support@shipshape-solutions.com", "SUPPORT_EMAIL=service@shipshape.hr");
            }
        }
        elseif ($func == "update_4_91") {

            $line1 = "TRANSFER_TO_CURRENCY_SIGN=€";
            $line2 = "TRANSFER_TO_CURRENCY_CODE=EUR";
            $line3 = "CURRENT_CURRENCY_CODE=HRK";

            $contentExists = false;
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../.env");
            if (stripos($contents, "TRANSFER_TO_CURRENCY_SIGN") !== false) {
                $contentExists = true;
            }
            if(!$contentExists){
                $helperManager->addLineToEndOfFile($_ENV["WEB_PATH"] . "../.env", $line1);
            }

            $contentExists = false;
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../.env");
            if (stripos($contents, "TRANSFER_TO_CURRENCY_CODE") !== false) {
                $contentExists = true;
            }
            if(!$contentExists){
                $helperManager->addLineToEndOfFile($_ENV["WEB_PATH"] . "../.env", $line2);
            }

            $contentExists = false;
            $contents = file_get_contents($_ENV["WEB_PATH"] . "../.env");
            if (stripos($contents, "CURRENT_CURRENCY_CODE") !== false) {
                $contentExists = true;
            }
            if(!$contentExists){
                $helperManager->addLineToEndOfFile($_ENV["WEB_PATH"] . "../.env", $line3);
            }

            if(!$_ENV["IS_PRODUCTION"]){

                $files = [];
                $allFiles = ( new \RecursiveTreeIterator(new \RecursiveDirectoryIterator($_ENV["WEB_PATH"] . "../src/{$_ENV["FRONTEND_BUNDLE"]}/", \RecursiveDirectoryIterator::SKIP_DOTS)));
                foreach ($allFiles as $file) {
                    if(stripos($file,".twig") !== false){
                        $files[] = trim(str_replace(['|', ' ', '~', '\\'], '', $file), '-');
                    }
                }

                $replacementArray = Array(
                    ">€" => '>{{ get_env("TRANSFER_TO_CURRENCY_SIGN") }}',
                    "€<" => '{{ get_env("TRANSFER_TO_CURRENCY_SIGN") }}<',
                    " €" => ' {{ get_env("TRANSFER_TO_CURRENCY_SIGN") }}',
                    "}€" => '}{{ get_env("TRANSFER_TO_CURRENCY_SIGN") }}',
                    "€{" => '{{ get_env("TRANSFER_TO_CURRENCY_SIGN") }}{',
                    '"EUR"' => 'get_env("TRANSFER_TO_CURRENCY_CODE")',
                    '"HRK' => '"{{ get_env("CURRENT_CURRENCY_CODE") }}',
                    '\'HRK' => '\'{{ get_env("CURRENT_CURRENCY_CODE") }}',
                    'HRK' => 'get_env("CURRENT_CURRENCY_CODE")'
                );

                if(!empty($files)){
                    foreach ($files as $file){
                        $contents = file_get_contents($file);
                        foreach ($replacementArray as $from => $to){
                            if (stripos($contents, $from) !== false) {
                                $helperManager->updateLineFromFile($file, $from, $to);
                            }
                        }
                    }
                }
            }

            return true;
        }
        elseif ($func == "update_4_92") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = 'DELETE FROM privilege WHERE action_code IN (
                "c524862e279c55d25516be8ad405e109",
                "603cf957585680.04432334",
                "6047937875b217.29586854",
                "604b4fd42dc929.50934094",
                "605375f593d468.57960916",
                "60585a4715bc10.59732150",
                "6059b8b6865a24.07342595",
                "605b4483469199.09277184") and role != 1;';

            $databaseContext->executeNonQuery($q);
        }
        elseif ($func == "update_4_93") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            try{
                $q = "ALTER TABLE s_route_not_found_entity MODIFY request_uri VARCHAR(500);";
                $databaseContext->executeNonQuery($q);
            }
            catch (\Exception $e){

            }

            try {
                $q = "ALTER TABLE s_route_not_found_entity ADD CONSTRAINT s_route_not_found_uq UNIQUE KEY(request_uri,store_id);";
                $databaseContext->executeNonQuery($q);
            } catch (\Exception $e) {

            }

            return true;
        }
        elseif ($func == "update_4_94") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            try{
                $q = "DROP TABLE IF EXISTS entity_state;";
                $databaseContext->executeNonQuery($q);
            }
            catch (\Exception $e){

            }

            return true;
        }
        elseif ($func == "update_4_96") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            try{
                $q = "ALTER TABLE currency_entity MODIFY COLUMN rate decimal(16,9);";
                $databaseContext->executeNonQuery($q);

                $q = "UPDATE currency_entity SET rate = 0.132722808 WHERE code = 'HRK';";
                $databaseContext->executeNonQuery($q);

                $q = "UPDATE exchange_rate_entity SET buying_rate = 7.5345, median_rate = 7.5345, selling_rate = 7.5345 WHERE currency_from_code = '978';";
                $databaseContext->executeNonQuery($q);
            }
            catch (\Exception $e){

            }

            return true;
        }
        elseif ($func == "update_4_97") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "SELECT id FROM account_entity WHERE id in (SELECT account_id FROM contact_entity WHERE LENGTH(first_name) = 32) and LENGTH(name) < 32 AND id not in (SELECT account_id FROM contact_entity WHERE LENGTH(first_name) <> 32);";
            $accountsToAnonymize = $databaseContext->getAll($q);

            if(empty($accountsToAnonymize)){
                return true;
            }

            $numberOfAccounts = count($accountsToAnonymize);
            $accountIds = array_column($accountsToAnonymize,"id");

            if(empty($this->accountManager)){
                $this->accountManager = $this->getContainer()->get("account_manager");
            }

            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->getContainer()->get("error_log_manager");
            }

            try{
                foreach ($accountsToAnonymize as $accountId){
                    $account = $this->accountManager->getAccountById($accountId);

                    $this->accountManager->anonymizeAccount($account);
                }

                $this->errorLogManager->logErrorEvent("Anonymized accounts","Total: {$numberOfAccounts}<br>List of account ids: ".json_encode($accountIds),true);
            }
            catch (\Exception $e){
                $this->errorLogManager->logErrorEvent("Please update CrmBusinessBundle","Please update CrmBusinessBundle and run manually: php bin/console update:helper update_4_97",true);
            }

            return true;
        } elseif ($func == "update_5_0") {

            // Automatically add unit tests repo
            if(stripos(file_get_contents($_ENV["WEB_PATH"] . "../composer.json"), '"run-tests"') === false){
                $update = <<<UPDATE
"scripts": {
    "run-tests": "./vendor/bin/phpunit --configuration src/ShapeUnitTestingBundle/phpunit.xml",
UPDATE;
                $textToFind = '"scripts": {';
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../composer.json", $textToFind, $update, false);

                $update = <<<UPDATE
"repositories": [
    {
        "type": "vcs",
        "url": "git@bitbucket.org:shipshapesolutions/shapeunittestingbundle.git"
    },
UPDATE;
                $textToFind = '"repositories": [';
                $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../composer.json", $textToFind, $update, false);

                copy($_ENV["WEB_PATH"] . "../src/AppBundle/Resources/scripts/files_to_copy/pre-commit.sh", $_ENV["WEB_PATH"] . "../.git/hooks/pre-commit");

                if (!$_ENV["IS_PRODUCTION"]) {
                    $contents = file_get_contents($_ENV["WEB_PATH"] . "../.gitignore");
                    if (stripos($contents, "/src/ShapeUnitTestingBundle") === false) {
                        file_put_contents($_ENV["WEB_PATH"] . "../.gitignore", "/src/ShapeUnitTestingBundle\n", FILE_APPEND);
                    }

                }
            }

            return true;
        } elseif ($func == "update_5_1") {

            if (!$_ENV["IS_PRODUCTION"]) {
                $contents = file_get_contents($_ENV["WEB_PATH"] . "../.gitignore");
                if (stripos($contents, "/src/ShapeUnitTestingBundle") === false) {
                    file_put_contents($_ENV["WEB_PATH"] . "../.gitignore", "/src/ShapeUnitTestingBundle\n", FILE_APPEND);
                }

            }

            return true;
        } elseif ($func == "update_5_11") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "UPDATE cron_job_history_entity SET cron_batch_id = 1 WHERE cron_batch_id IS NULL and date_started IS NOT NULL;";
            $databaseContext->executeNonQuery($q);

            $q = "UPDATE cron_job_history_entity SET cron_batch_id = 2 WHERE cron_batch_id IS NULL and date_started IS NULL;";
            $databaseContext->executeNonQuery($q);

            return true;
        } elseif ($func == "update_5_13") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "DELETE cron_job_history_entity
            FROM cron_job_history_entity
            INNER JOIN (
                 SELECT MAX(id) AS lastId, cron_job_id, cron_batch_id
                 FROM cron_job_history_entity
                 GROUP BY cron_job_id,cron_batch_id
                 HAVING count(*) > 1) duplic on duplic.cron_job_id = cron_job_history_entity.cron_job_id and duplic.cron_batch_id = cron_job_history_entity.cron_batch_id
            WHERE cron_job_history_entity.id < duplic.lastId;";
            $databaseContext->executeNonQuery($q);

            $q = "CREATE UNIQUE INDEX cron_job_history_entity_uq  
            ON cron_job_history_entity (cron_job_id, cron_batch_id);";
            $databaseContext->executeNonQuery($q);

            return true;
        }  elseif ($func == "update_5_15") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "UPDATE cron_job_entity SET run_time = 30 WHERE method = 'admin:entity clean_logs 15';";
            $databaseContext->executeNonQuery($q);

        }
        elseif ($func == "update_5_16") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "ALTER TABLE payment_transaction_log_entity MODIFY response_data LONGTEXT;";
            $databaseContext->executeNonQuery($q);

            $q = "ALTER TABLE payment_transaction_log_entity MODIFY request_data LONGTEXT;";
            $databaseContext->executeNonQuery($q);

        }
        elseif ($func == "update_5_17") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "UPDATE account_entity SET oib = MD5(oib) WHERE LENGTH(name) = 32 and name not like '% %' and oib is not null and oib != '' and LENGTH(oib) != 32;";
            $databaseContext->executeNonQuery($q);

            $q = "UPDATE account_entity SET is_anonymized = 1 WHERE LENGTH(name) = 32 and name not like '% %';";
            $databaseContext->executeNonQuery($q);

            $q = "UPDATE contact_entity SET is_anonymized = 1 WHERE LENGTH(first_name) = 32 and first_name not like '% %';";
            $databaseContext->executeNonQuery($q);

            $q = "UPDATE favorite_entity SET is_anonymized = 1 WHERE LENGTH(first_name) = 32 and first_name not like '% %';";
            $databaseContext->executeNonQuery($q);

            $q = "UPDATE general_question_entity SET is_anonymized = 1 WHERE LENGTH(first_name) = 32 and first_name not like '% %';";
            $databaseContext->executeNonQuery($q);

            $q = "UPDATE address_entity SET is_anonymized = 1 WHERE LENGTH(street) = 32 and street not like '% %';";
            $databaseContext->executeNonQuery($q);
        }
        elseif ($func == "update_5_18") {

            if($_ENV["IS_PRODUCTION"] == 0 && !isset($_ENV["EMAIL_ERROR_NUMBER_OF_TRIES"])){
                $helperManager->addLineToEndOfFile($_ENV["WEB_PATH"] . "../configuration.env", "EMAIL_ERROR_NUMBER_OF_TRIES=1");
            }
        } elseif ($func == "update_5_19") {
            if($_ENV["IS_PRODUCTION"] == 0){
                if(stripos(file_get_contents($_ENV["WEB_PATH"] . "../composer.json"), 'vendor/bin/phpunit') !== false){
                    $helperManager->updateLineFromFile($_ENV["WEB_PATH"] . "../composer.json", "vendor/bin/phpunit", "vendor/bin/paratest", false);
                }
            }
        }
        elseif ($func == "update_5_22") {
            if($_ENV["IS_PRODUCTION"] == 0 && !isset($_ENV["SUPPORT_OPEN_TICKET"])){
                $helperManager->addLineToEndOfFile($_ENV["WEB_PATH"] . "../configuration.env", "SUPPORT_OPEN_TICKET=1");
            }
        }
        elseif ($func == "update_5_23") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            try{
                $q = "ALTER TABLE s_route_not_found_entity MODIFY request_uri VARCHAR(2000);";
                $databaseContext->executeNonQuery($q);
            }
            catch (\Exception $e){

            }

            return true;
        }
        elseif ($func == "update_5_24") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            try{
                $q = "UPDATE entity_type SET entity_type_code = 'error_logs' WHERE uid = '6173dd98ae2b68.42168239';";
                $databaseContext->executeNonQuery($q);
            }
            catch (\Exception $e){

            }

            return true;
        }
        elseif ($func == "update_5_29") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            try{
                $q = "DELETE FROM cron_job_entity WHERE method like 'scommercehelper:function generate_facebook_export%';";
                $databaseContext->executeNonQuery($q);
            }
            catch (\Exception $e){

            }

            try{
                $q = "DELETE FROM cron_job_entity WHERE method like 'scommercehelper:function generate_google_export%';";
                $databaseContext->executeNonQuery($q);
            }
            catch (\Exception $e){

            }

            try{
                $q = "UPDATE cron_job_entity SET is_active = 1 WHERE method like 'admin:entity clean_logs%';";
                $databaseContext->executeNonQuery($q);
            }
            catch (\Exception $e){

            }

            return true;
        }
        elseif ($func == "update_5_31") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            try{
                $q = "UPDATE cron_job_entity SET is_active = 1, method = 'crmhelper:run type:trigger_erp_to_pull_orders' WHERE method like 'integrationhelper:run type:trigger_erp_to_pull_orders';";
                $databaseContext->executeNonQuery($q);
            }
            catch (\Exception $e){

            }

            return true;
        }
        elseif ($func == "update_5_32") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            try{
                $q = "UPDATE s_route_not_found_entity SET google_search_console_processed = 0 WHERE google_search_console_processed is null;";
                $databaseContext->executeNonQuery($q);
            }
            catch (\Exception $e){

            }

            return true;
        }
        else {
            throw new \Exception("Command type missing: " . json_encode($input->getArguments()));
        }

        return false;
    }
}
