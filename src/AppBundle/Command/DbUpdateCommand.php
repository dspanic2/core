<?php

// php bin/console db_update:helper rebuild_procedures_views
// php bin/console db_update:helper rebuild_fk
// php bin/console db_update:helper insert_default_codebooks

namespace AppBundle\Command;

use AppBundle\Context\AttributeContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\SettingsEntity;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\ApplicationSettingsManager;
use AppBundle\Managers\DatabaseManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\MailManager;
use Monolog\Logger;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class DbUpdateCommand extends ContainerAwareCommand
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var MailManager $mailManager */
    protected $mailManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var DatabaseManager $databaseManager */
    protected $databaseManager;
    /** @var ApplicationSettingsManager $settingsManager */
    protected $settingsManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;

    protected function configure()
    {
        $this->setName('db_update:helper')
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

        if ($func == "rebuild_procedures_views") {

            if(empty($this->databaseContext)){
                $this->databaseContext = $this->getContainer()->get("database_context");
            }

            $q = "SELECT VERSION() as count;";
            $ver = $this->databaseContext->getSingleResult($q);

            $mysqlType = "MySQL";
            if(stripos($ver,"mariadb") !== false){
                $mysqlType = "MariaDB";
            }

            /** CREATE table product_account_price_staging */
            $q = "CREATE TABLE IF NOT EXISTS `product_account_price_staging` (
              `product_id` int(11) unsigned NOT NULL,
              `account_id` int(11) unsigned NOT NULL,
              `price_base` decimal(12,4) DEFAULT NULL,
              `rebate` decimal(12,4) DEFAULT NULL,
              `type` int(11) DEFAULT NULL,
              `date_valid_from` datetime DEFAULT NULL,
              `date_valid_to` datetime DEFAULT NULL,
              `to_delete` tinyint(1) NOT NULL default '0',
              PRIMARY KEY (`product_id`,`account_id`),
              KEY `product_account_id` (`product_id`,`account_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $this->databaseContext->executeNonQuery($q);

            /** CREATE table product_account_group_price_staging */
            $q = "CREATE TABLE IF NOT EXISTS `product_account_group_price_staging` (
              `product_id` int(11) unsigned NOT NULL,
              `account_group_id` int(11) unsigned NOT NULL,
              `price_base` decimal(12,4) DEFAULT NULL,
              `rebate` decimal(12,4) DEFAULT NULL,
              `type` int(11) DEFAULT NULL,
              `date_valid_from` datetime DEFAULT NULL,
              `date_valid_to` datetime DEFAULT NULL,
              `to_delete` tinyint(1) NOT NULL default '0',
              PRIMARY KEY (`product_id`,`account_group_id`),
              KEY `product_account_id` (`product_id`,`account_group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $this->databaseContext->executeNonQuery($q);

            /** CREATE table shape_track */
            $q = "CREATE TABLE IF NOT EXISTS `shape_track` (
              `url` varchar(255) NOT NULL,
              `full_url` varchar(255) NOT NULL,
              `page_id` int(11) unsigned DEFAULT NULL,
              `page_type` varchar(100) DEFAULT NULL,
              `previous` varchar(255) DEFAULT NULL,
              `event_type` varchar(20) DEFAULT NULL,
              `event_name` varchar(255) DEFAULT NULL,
              `origin` varchar(255) DEFAULT NULL,
              `source` varchar(255) DEFAULT NULL,
              `useragent` varchar(255) DEFAULT NULL,
              `http_status` varchar(3) DEFAULT NULL,
              `ip_address` varchar(50) DEFAULT NULL,
              `store_id` tinyint(2) NOT NULL DEFAULT '0',
              `event_time` datetime NOT NULL,
              `user_id` int(11) unsigned DEFAULT NULL,
              `session_id` varchar(50) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $this->databaseContext->executeNonQuery($q);

            /** CREATE table shape_track_date_dim */
            $q = "CREATE TABLE IF NOT EXISTS `shape_track_date_dim` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `fulldate` date DEFAULT NULL,
              `dayofmonth` int(11) unsigned DEFAULT NULL,
              `dayofyear` int(11) unsigned DEFAULT NULL,
              `dayofweek` int(11) unsigned DEFAULT NULL,
              `dayname` varchar(255) DEFAULT NULL,
              `monthnumber` int(11) unsigned DEFAULT NULL,
              `monthname` varchar(255) DEFAULT NULL,
              `year` int(11) unsigned DEFAULT NULL,
              `quarter` int(11) unsigned DEFAULT NULL,
              `quarterid` int(11) unsigned DEFAULT NULL,
              `week` int(11) unsigned DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $this->databaseContext->executeNonQuery($q);

            /** CREATE table shape_track_order_item_fact */
            $q = "CREATE TABLE IF NOT EXISTS `shape_track_order_item_fact` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `order_item_id` int(11) unsigned DEFAULT NULL,
              `order_id` int(11) unsigned NOT NULL,
              `date_dim_id` int(11) unsigned NOT NULL,
              `product_id` int(11) unsigned DEFAULT NULL,
              `name` varchar(255) DEFAULT NULL,
              `qty` decimal(14,4) DEFAULT NULL,
              `base_price_total` decimal(14,2) DEFAULT NULL,
              `base_price_discount_total` decimal(14,2) DEFAULT NULL,
              `base_price_tax` decimal(14,2) DEFAULT NULL,
              `base_price_item_tax` decimal(14,2) DEFAULT NULL,
              `order_state_id` int(11) unsigned DEFAULT NULL,
              `store_id` int(11) unsigned DEFAULT NULL,
              `website_id` int(11) unsigned DEFAULT NULL,
              `account_id` int(11) unsigned DEFAULT NULL,
              `contact_id` int(11) unsigned DEFAULT NULL,
              `core_user_id` int(11) unsigned DEFAULT NULL,
              `account_group_id` int(11) unsigned DEFAULT NULL,
              `is_legal_entity` tinyint(1) DEFAULT '0',
              `city_id` int(11) unsigned DEFAULT NULL,
              `country_id` int(11) unsigned DEFAULT NULL,
              `product_groups` varchar(1000) DEFAULT NULL,
              `modified` datetime DEFAULT NULL,
              `base_price_item` decimal(14,2) DEFAULT NULL,
              `payment_type_id` int(11) unsigned DEFAULT NULL,
              `delivery_type_id` int(11) unsigned DEFAULT NULL,
              `order_base_price_total` decimal(14,2) DEFAULT NULL,
              `order_base_price_tax` decimal(14,2) DEFAULT NULL,
              `order_base_price_items_total` decimal(14,2) DEFAULT NULL,
              `order_base_price_items_tax` decimal(14,2) DEFAULT NULL,
              `order_base_price_delivery_total` decimal(14,2) DEFAULT NULL,
              `order_base_price_delivery_tax` decimal(14,2) DEFAULT NULL,
              `currency_id` int(11) unsigned DEFAULT NULL,
              `currency_rate` decimal(14,2) DEFAULT NULL,
              `discount_coupon_id` int(11) unsigned DEFAULT NULL,
              `discount_coupon_price_total` decimal(14,2) DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `order_item_fact_order_item_id` (`order_item_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $this->databaseContext->executeNonQuery($q);

            /** CREATE table shape_track_product_dim */
            $q = "CREATE TABLE IF NOT EXISTS `shape_track_product_dim` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `product_id` int(11) unsigned DEFAULT NULL,
              `date_dim_id` int(11) unsigned DEFAULT NULL,
              `quoted` tinyint(5) DEFAULT NULL,
              `ordered` tinyint(5) DEFAULT NULL,
              `canceled` tinyint(5) DEFAULT NULL,
              `returned` tinyint(5) DEFAULT NULL,
              `requests` tinyint(5) DEFAULT NULL,
              `reviewed` tinyint(5) DEFAULT NULL,
              `visited` tinyint(5) DEFAULT NULL,
              `ctr` decimal(5,2) DEFAULT NULL,
              `store_id` int(11) unsigned DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $this->databaseContext->executeNonQuery($q);

            /** CREATE table shape_track_product_group_fact */
            $q = "CREATE TABLE IF NOT EXISTS `shape_track_product_group_fact` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `date_dim_id` int(11) unsigned NOT NULL,
              `product_group_id` int(11) unsigned DEFAULT NULL,
              `product_group_name` varchar(255) DEFAULT NULL,
              `product_group_level` varchar(255) DEFAULT NULL,
              `product_group_number_of_products` int(11) unsigned DEFAULT NULL,
              `visits` int(11) unsigned DEFAULT NULL,
              `add_to_cart` int(11) unsigned DEFAULT NULL,
              `qty_sold` int(11) unsigned DEFAULT NULL,
              `qty_canceled` int(11) unsigned DEFAULT NULL,
              `order_total_amount` decimal(14,2) DEFAULT NULL,
              `order_success_amount` decimal(14,2) DEFAULT NULL,
              `order_canceled_amount` decimal(14,2) DEFAULT NULL,
              `order_in_process_amount` decimal(14,2) DEFAULT NULL,
              `store_id` int(11) unsigned DEFAULT NULL,
              `website_id` int(11) unsigned DEFAULT NULL,
              `performace_rate_visits` decimal(14,2) DEFAULT NULL,
              `modified` datetime DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $this->databaseContext->executeNonQuery($q);

            /** CREATE table shape_track_product_impressions_transaction */
            $q = "CREATE TABLE IF NOT EXISTS `shape_track_product_impressions_transaction` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `product_id` int(11) unsigned DEFAULT NULL,
              `session_id` varchar(255) DEFAULT NULL,
              `email` varchar(255) DEFAULT NULL,
              `first_name` varchar(255) DEFAULT NULL,
              `last_name` varchar(255) DEFAULT NULL,
              `contact_id` int(11) unsigned DEFAULT NULL,
              `event_type` varchar(255) DEFAULT NULL,
              `event_name` varchar(255) DEFAULT NULL,
              `previous` varchar(255) DEFAULT NULL,
              `store_id` int(11) unsigned DEFAULT NULL,
              `created` datetime DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB AUTO_INCREMENT=945 DEFAULT CHARSET=utf8mb4;";
            $this->databaseContext->executeNonQuery($q);

            /** CREATE table shape_track_totals_fact */
            $q = "CREATE TABLE IF NOT EXISTS `shape_track_totals_fact` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `date_dim_id` int(11) unsigned DEFAULT NULL,
              `order_total_count` int(11) unsigned DEFAULT '0',
              `order_total_amount` decimal(14,2) DEFAULT '0.00',
              `order_success_count` int(11) unsigned DEFAULT '0',
              `order_success_amount` decimal(14,2) DEFAULT '0.00',
              `order_canceled_count` int(11) unsigned DEFAULT '0',
              `order_canceled_amount` decimal(14,2) DEFAULT '0.00',
              `order_in_process_count` int(11) unsigned DEFAULT '0',
              `order_in_process_amount` decimal(14,2) DEFAULT '0.00',
              `quote_total_count` int(11) unsigned DEFAULT '0',
              `quote_total_amount` decimal(14,2) DEFAULT '0.00',
              `quote_to_order_rate_count` decimal(14,2) DEFAULT '0.00',
              `quote_to_order_rate_amount` decimal(14,2) DEFAULT '0.00',
              `store_id` int(11) unsigned DEFAULT NULL,
              `website_id` int(11) unsigned DEFAULT NULL,
              `modified` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `shape_track_date_dim_id` (`date_dim_id`),
              KEY `shape_track_store_id` (`store_id`) USING BTREE,
              KEY `shape_track_website_id` (`website_id`) USING BTREE,
              CONSTRAINT `shape_track_totals_fact_ibfk_1` FOREIGN KEY (`date_dim_id`) REFERENCES `shape_track_date_dim` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $this->databaseContext->executeNonQuery($q);

            /** CREATE table shape_track_search_transaction */
            $q = "CREATE TABLE IF NOT EXISTS `shape_track_search_transaction` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `created` datetime NOT NULL,
              `original_query` varchar(255) DEFAULT NULL,
              `used_query` varchar(255) DEFAULT NULL,
              `number_of_results` int(11) unsigned DEFAULT NULL,
              `session_id` varchar(255) DEFAULT NULL,
              `email` varchar(255) DEFAULT NULL,
              `first_name` varchar(255) DEFAULT NULL,
              `last_name` varchar(255) DEFAULT NULL,
              `contact_id` int(11) unsigned DEFAULT NULL,
              `store_id` int(11) unsigned DEFAULT NULL,
              `list_of_results` longtext,
              `from_cache` tinyint(1) unsigned DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $this->databaseContext->executeNonQuery($q);

            /** view account_sales_per_month_entity */
            $q = "CREATE OR REPLACE VIEW account_sales_per_month_entity AS select i.account_id AS id,a.name AS name,a.entity_type_id AS entity_type_id,a.attribute_set_id AS attribute_set_id,a.modified AS modified,a.created AS created,a.entity_state_id AS entity_state_id,a.industry_type_id AS industry_type_id,a.phone AS phone,a.created_by AS created_by,a.modified_by AS modified_by,a.version AS version,a.min_version AS min_version,a.locked AS locked,a.locked_by AS locked_by,a.is_legal_entity AS is_legal_entity,a.rating AS rating,a.account_group_id AS account_group_id,a.is_active AS is_active,a.owner_id AS owner_id,sum(if((i.ord = 1),i.total,0)) AS month_12,sum(if((i.ord = 1),i.diff,0)) AS month_12_diff,sum(if((i.ord = 2),i.total,0)) AS month_11,sum(if((i.ord = 2),i.diff,0)) AS month_11_diff,sum(if((i.ord = 3),i.total,0)) AS month_10,sum(if((i.ord = 3),i.diff,0)) AS month_10_diff,sum(if((i.ord = 4),i.total,0)) AS month_9,sum(if((i.ord = 4),i.diff,0)) AS month_9_diff,sum(if((i.ord = 5),i.total,0)) AS month_8,sum(if((i.ord = 5),i.diff,0)) AS month_8_diff,sum(if((i.ord = 6),i.total,0)) AS month_7,sum(if((i.ord = 6),i.diff,0)) AS month_7_diff,sum(if((i.ord = 7),i.total,0)) AS month_6,sum(if((i.ord = 7),i.diff,0)) AS month_6_diff,sum(if((i.ord = 8),i.total,0)) AS month_5,sum(if((i.ord = 8),i.diff,0)) AS month_5_diff,sum(if((i.ord = 9),i.total,0)) AS month_4,sum(if((i.ord = 9),i.diff,0)) AS month_4_diff,sum(if((i.ord = 10),i.total,0)) AS month_3,sum(if((i.ord = 10),i.diff,0)) AS month_3_diff,sum(if((i.ord = 11),i.total,0)) AS month_2,sum(if((i.ord = 11),i.diff,0)) AS month_2_diff,sum(if((i.ord = 12),i.total,0)) AS month_1,sum(if((i.ord = 12),i.diff,0)) AS month_1_diff from (((select b.account_id AS account_id,calendar.month_name AS month_name,calendar.month_id AS month_id,b.total AS total,b.dyear AS dyear,(case when (b.last_year_total > 0) then b.last_year_total else 0 end) AS last_year_total,(case when isnull(b.diff) then 200 else b.diff end) AS diff,b.is_legal_entity AS is_legal_entity,if(((calendar.month_id - month(curdate())) <= 0),(12 - abs((calendar.month_id - month(curdate())))),(calendar.month_id - month(curdate()))) AS ord from (((select 1 AS month_id,'Jan' AS month_name) union select 2 AS month_id,'Feb' AS month_name union select 3 AS month_id,'Mar' AS month_name union select 4 AS month_id,'Apr' AS month_name union select 5 AS month_id,'Mays' AS month_name union select 6 AS month_id,'Jun' AS month_name union select 7 AS month_id,'Jul' AS month_name union select 8 AS month_id,'Aug' AS month_name union select 9 AS month_id,'Sep' AS month_name union select 10 AS month_id,'Oct' AS month_name union select 11 AS month_id,'Nov' AS month_name union select 12 AS month_id,'Dec' AS month_name) calendar left join (select (case when (oif.is_legal_entity = 0) then 'B2C' when (oif.is_legal_entity = 1) then 'B2B' end) AS customer_type,oif.account_id AS account_id,oif.is_legal_entity AS is_legal_entity,dd.monthnumber AS month_id,dd.monthname AS month_name,dd.year AS dyear,sum(oif.base_price_total) AS total,last_year.total AS last_year_total,((if((last_year.total = 0),sum(oif.base_price_total),(sum(oif.base_price_total) - last_year.total)) / (if((last_year.total = 0),sum(oif.base_price_total),(sum(oif.base_price_total) + last_year.total)) / 2)) * 100) AS diff from ((shape_track_order_item_fact oif join shape_track_date_dim dd on((oif.date_dim_id = dd.id))) left join (select (case when (oif.is_legal_entity = 0) then 'B2C' when (oif.is_legal_entity = 1) then 'B2B' end) AS customer_type,oif.is_legal_entity AS is_legal_entity,oif.account_id AS account_id,dd.monthnumber AS month_id,dd.monthname AS month_name,dd.year AS dyear,sum(oif.base_price_total) AS total from (shape_track_order_item_fact oif join shape_track_date_dim dd on((oif.date_dim_id = dd.id))) where ((oif.account_id is not null) and (dd.fulldate <= (curdate() - interval 1 year)) and (dd.fulldate > last_day((curdate() - interval 2 year)))) group by dd.year,oif.account_id,dd.monthnumber) last_year on(((dd.monthnumber = last_year.month_id) and (oif.account_id = last_year.account_id)))) where ((oif.account_id is not null) and (dd.fulldate <= curdate()) and (dd.fulldate > last_day((curdate() - interval 1 year)))) group by dd.year,oif.account_id,dd.monthnumber) b on((calendar.month_id = b.month_id))) order by ord,b.total desc)) i left join account_entity a on((i.account_id = a.id))) where (i.account_id is not null) group by i.account_id;";
            $this->databaseContext->executeNonQuery($q);

            /** product_sales_per_month_entity */
            $q = "CREATE OR REPLACE VIEW product_sales_per_month_entity AS select i.product_id AS id,a.name AS name,a.entity_type_id AS entity_type_id,a.attribute_set_id AS attribute_set_id,a.modified AS modified,a.created AS created,a.entity_state_id AS entity_state_id,a.created_by AS created_by,a.modified_by AS modified_by,a.version AS version,a.min_version AS min_version,a.locked AS locked,a.locked_by AS locked_by,a.code AS code,a.ean AS ean,a.is_saleable AS is_saleable,a.remote_id AS remote_id,a.remote_source AS remote_source,sum(if((i.ord = 1),i.total,0)) AS month_12,sum(if((i.ord = 1),i.diff,0)) AS month_12_diff,sum(if((i.ord = 2),i.total,0)) AS month_11,sum(if((i.ord = 2),i.diff,0)) AS month_11_diff,sum(if((i.ord = 3),i.total,0)) AS month_10,sum(if((i.ord = 3),i.diff,0)) AS month_10_diff,sum(if((i.ord = 4),i.total,0)) AS month_9,sum(if((i.ord = 4),i.diff,0)) AS month_9_diff,sum(if((i.ord = 5),i.total,0)) AS month_8,sum(if((i.ord = 5),i.diff,0)) AS month_8_diff,sum(if((i.ord = 6),i.total,0)) AS month_7,sum(if((i.ord = 6),i.diff,0)) AS month_7_diff,sum(if((i.ord = 7),i.total,0)) AS month_6,sum(if((i.ord = 7),i.diff,0)) AS month_6_diff,sum(if((i.ord = 8),i.total,0)) AS month_5,sum(if((i.ord = 8),i.diff,0)) AS month_5_diff,sum(if((i.ord = 9),i.total,0)) AS month_4,sum(if((i.ord = 9),i.diff,0)) AS month_4_diff,sum(if((i.ord = 10),i.total,0)) AS month_3,sum(if((i.ord = 10),i.diff,0)) AS month_3_diff,sum(if((i.ord = 11),i.total,0)) AS month_2,sum(if((i.ord = 11),i.diff,0)) AS month_2_diff,sum(if((i.ord = 12),i.total,0)) AS month_1,sum(if((i.ord = 12),i.diff,0)) AS month_1_diff from (((select b.product_id AS product_id,calendar.month_name AS month_name,calendar.month_id AS month_id,b.total AS total,b.dyear AS dyear,(case when (b.last_year_total > 0) then b.last_year_total else 0 end) AS last_year_total,(case when isnull(b.diff) then 200 else b.diff end) AS diff,b.is_legal_entity AS is_legal_entity,if(((calendar.month_id - month(curdate())) <= 0),(12 - abs((calendar.month_id - month(curdate())))),(calendar.month_id - month(curdate()))) AS ord from (((select 1 AS month_id,'Jan' AS month_name) union select 2 AS month_id,'Feb' AS month_name union select 3 AS month_id,'Mar' AS month_name union select 4 AS month_id,'Apr' AS month_name union select 5 AS month_id,'Mays' AS month_name union select 6 AS month_id,'Jun' AS month_name union select 7 AS month_id,'Jul' AS month_name union select 8 AS month_id,'Aug' AS month_name union select 9 AS month_id,'Sep' AS month_name union select 10 AS month_id,'Oct' AS month_name union select 11 AS month_id,'Nov' AS month_name union select 12 AS month_id,'Dec' AS month_name) calendar left join (select (case when (oif.is_legal_entity = 0) then 'B2C' when (oif.is_legal_entity = 1) then 'B2B' end) AS customer_type,oif.product_id AS product_id,oif.is_legal_entity AS is_legal_entity,dd.monthnumber AS month_id,dd.monthname AS month_name,dd.year AS dyear,sum(oif.base_price_total) AS total,last_year.total AS last_year_total,((if((last_year.total = 0),sum(oif.base_price_total),(sum(oif.base_price_total) - last_year.total)) / (if((last_year.total = 0),sum(oif.base_price_total),(sum(oif.base_price_total) + last_year.total)) / 2)) * 100) AS diff from ((shape_track_order_item_fact oif join shape_track_date_dim dd on((oif.date_dim_id = dd.id))) left join (select (case when (oif.is_legal_entity = 0) then 'B2C' when (oif.is_legal_entity = 1) then 'B2B' end) AS customer_type,oif.is_legal_entity AS is_legal_entity,oif.product_id AS product_id,dd.monthnumber AS month_id,dd.monthname AS month_name,dd.year AS dyear,sum(oif.base_price_total) AS total from (shape_track_order_item_fact oif join shape_track_date_dim dd on((oif.date_dim_id = dd.id))) where ((oif.product_id is not null) and (dd.fulldate <= (curdate() - interval 1 year)) and (dd.fulldate > last_day((curdate() - interval 2 year)))) group by dd.year,oif.product_id,dd.monthnumber) last_year on(((dd.monthnumber = last_year.month_id) and (oif.product_id = last_year.product_id)))) where ((oif.product_id is not null) and (dd.fulldate <= curdate()) and (dd.fulldate > last_day((curdate() - interval 1 year)))) group by dd.year,oif.product_id,dd.monthnumber) b on((calendar.month_id = b.month_id))) order by ord,b.total desc)) i left join product_entity a on((i.product_id = a.id))) where (i.product_id is not null) group by i.product_id;";
            $this->databaseContext->executeNonQuery($q);

            if(empty($this->databaseManager)){
                $this->databaseManager = $this->getContainer()->get("database_manager");
            }

            if($this->databaseManager->checkIfTableExists("company_register_entity")){
                /** account_company_register_differences_entity */
                $q = "CREATE OR REPLACE VIEW account_company_register_differences_entity AS select (select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'account_company_register_differences')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'account_company_register_differences')) AS `attribute_set_id`,NULL AS `created`,NULL AS `modified`,NULL AS `created_by`,NULL AS `modified_by`,NULL AS `locked`,NULL AS `locked_by`,NULL AS `version`,NULL AS `min_version`,1 AS `entity_state_id`,`address`.`id` AS `id`,`account`.`id` AS `account_id`,`account`.`name` AS `account_name`,`address`.`name` AS `address_name`,`company_register`.`name_short` AS `company_register_name`,`account`.`oib` AS `account_oib`,`company_register`.`identification_number` AS `company_register_identification_number`,`account`.`email` AS `account_email`,`address`.`email` AS `address_email`,`company_register`.`email_address` AS `company_register_email`,`address`.`street` AS `account_street`,`company_register`.`headquarters_street` AS `company_register_street`,`city`.`name` AS `account_city`,`company_register`.`headquarters_city_name` AS `company_register_city`,`city`.`id` AS `city_id`,`address`.`id` AS `address_id` from (((`company_register_entity` `company_register` join `account_entity` `account` on((`company_register`.`identification_number` = `account`.`oib`))) join `address_entity` `address` on((`address`.`account_id` = `account`.`id`))) join `city_entity` `city` on((`address`.`city_id` = `city`.`id`))) where ((`address`.`headquarters` = 1) and ((lower(`account`.`name`) <> lower(`company_register`.`name_short`)) or (lower(`address`.`street`) <> lower(`company_register`.`headquarters_street`)) or (lower(`city`.`name`) <> lower(`company_register`.`headquarters_city_name`))));";
                $this->databaseContext->executeNonQuery($q);
            }

            /** available_configurable_products_entity */
            $q = "SELECT * FROM ssinformation.TABLES WHERE TABLE_SCHEMA = '{$_ENV["DATABASE_NAME"]}' AND TABLE_NAME = 'available_configurable_products_entity'";
            $exists = $this->databaseContext->getAll($q);

            if(!empty($exists) && $exists[0]["TABLE_TYPE"] != "VIEW"){
                $q = "DROP TABLE IF EXISTS available_configurable_products_entity;";
                $this->databaseContext->executeNonQuery($q);
            }

            $q = "CREATE OR REPLACE VIEW available_configurable_products_entity AS select `p`.`id` AS `id`,`p`.`name` AS `name`,`p`.`code` AS `code`,`p`.`ean` AS `ean`,`p`.`active` AS `active`,`p`.`is_saleable` AS `is_saleable`,`p`.`catalog_code` AS `catalog_code`,`p`.`entity_type_id` AS `entity_type_id`,`p`.`attribute_set_id` AS `attribute_set_id`,`p`.`modified` AS `modified`,`p`.`created` AS `created`,`p`.`entity_state_id` AS `entity_state_id`,`p`.`created_by` AS `created_by`,`p`.`modified_by` AS `modified_by`,`p`.`version` AS `version`,`p`.`min_version` AS `min_version`,`p`.`locked` AS `locked`,`p`.`locked_by` AS `locked_by`,`spal`.`configuration_option` AS `configuration_option`,`spal`.`s_product_attribute_configuration_id` AS `s_product_attribute_configuration_id`,`spal`.`attribute_value` AS `attribute_value`,(case when isnull(`scpl`.`id`) then 0 else 1 end) AS `used_in_configuration`,group_concat(concat(`spaco`.`name`,': ',`spal`.`attribute_value`) separator ', ') AS `attributes` from (((`s_product_attributes_link_entity` `spal` left join `product_entity` `p` on((`spal`.`product_id` = `p`.`id`))) left join `s_product_attribute_configuration_entity` `spaco` on((`spal`.`s_product_attribute_configuration_id` = `spaco`.`id`))) left join `product_configuration_product_link_entity` `scpl` on((`p`.`id` = `scpl`.`child_product_id`))) where (`p`.`product_type_id` = 1) group by `p`.`id`;";
            $this->databaseContext->executeNonQuery($q);

            /** custom_list_entity */
            $q = "CREATE OR REPLACE VIEW custom_list_entity AS select `p`.`id` AS `id`,`p`.`attribute_code` AS `code`,'attribute' AS `table_name`,`e`.`entity_type_code` AS `entity_type_code`,'' AS `attribute_set_code`,(select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'product')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'product')) AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,'' AS `bundle`,'' AS `version`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,`p`.`uid` AS `uid` from (`attribute` `p` left join `entity_type` `e` on((`p`.`entity_type_id` = `e`.`id`))) where (`p`.`is_custom` = 1) union select `p`.`id` AS `id`,`p`.`attribute_group_name` AS `code`,'attribute_group' AS `table_name`,'' AS `entity_type_code`,`a`.`attribute_set_code` AS `attribute_set_code`,(select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'product')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'product')) AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,'' AS `version`,'' AS `bundle`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,`p`.`uid` AS `uid` from (`attribute_group` `p` left join `attribute_set` `a` on((`p`.`attribute_set_id` = `a`.`id`))) where (`p`.`is_custom` = 1) union select `p`.`id` AS `id`,`p`.`entity_type_code` AS `code`,'entity_type' AS `table_name`,'' AS `entity_type_code`,'' AS `attribute_set_code`,(select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'product')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'product')) AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,'' AS `version`,`p`.`bundle` AS `bundle`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,`p`.`uid` AS `uid` from `entity_type` `p` where (`p`.`is_custom` = 1) union select `p`.`id` AS `id`,`p`.`attribute_set_code` AS `code`,'attribute_set' AS `table_name`,'' AS `entity_type_code`,'' AS `attribute_set_code`,(select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'product')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'product')) AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,'' AS `version`,'' AS `bundle`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,`p`.`uid` AS `uid` from `attribute_set` `p` where (`p`.`is_custom` = 1) union select `p`.`id` AS `id`,`p`.`title` AS `code`,'page' AS `table_name`,`e`.`entity_type_code` AS `entity_type_code`,`a`.`attribute_set_code` AS `attribute_set_code`,668 AS `entity_type_id`,514 AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,`p`.`bundle` AS `bundle`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,'' AS `version`,`p`.`uid` AS `uid` from ((`page` `p` left join `attribute_set` `a` on((`p`.`attribute_set` = `a`.`id`))) left join `entity_type` `e` on((`p`.`entity_type` = `e`.`id`))) where (`p`.`is_custom` = 1) union select `p`.`id` AS `id`,`p`.`title` AS `code`,'page_block' AS `table_name`,`e`.`entity_type_code` AS `entity_type_code`,`a`.`attribute_set_code` AS `attribute_set_code`,(select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'product')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'product')) AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,`p`.`bundle` AS `bundle`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,'' AS `version`,`p`.`uid` AS `uid` from ((`page_block` `p` left join `attribute_set` `a` on((`p`.`attribute_set` = `a`.`id`))) left join `entity_type` `e` on((`p`.`entity_type` = `e`.`id`))) where (`p`.`is_custom` = 1) union select `p`.`id` AS `id`,`p`.`name` AS `code`,'list_view' AS `table_name`,`e`.`entity_type_code` AS `entity_type_code`,`a`.`attribute_set_code` AS `attribute_set_code`,(select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'product')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'product')) AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,`p`.`bundle` AS `bundle`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,'' AS `version`,`p`.`uid` AS `uid` from ((`list_view` `p` left join `attribute_set` `a` on((`p`.`attribute_set` = `a`.`id`))) left join `entity_type` `e` on((`p`.`entity_type` = `e`.`id`))) where (`p`.`is_custom` = 1) union select `p`.`id` AS `id`,`p`.`display_name` AS `code`,'navigation_link' AS `table_name`,'' AS `entity_type_code`,'' AS `attribute_set_code`,(select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'product')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'product')) AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,`p`.`bundle` AS `bundle`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,'' AS `version`,`p`.`uid` AS `uid` from `navigation_link` `p` where (`p`.`is_custom` = 1);";
            $this->databaseContext->executeNonQuery($q);

            /** applied_discounts_entity */
            $q = "CREATE OR REPLACE VIEW applied_discounts_entity AS select `s`.`id` AS `id`,`s`.`entity_type_id` AS `entity_type_id`,`s`.`attribute_set_id` AS `attribute_set_id`,`s`.`modified` AS `modified`,`s`.`created` AS `created`,`s`.`entity_state_id` AS `entity_state_id`,`s`.`created_by` AS `created_by`,`s`.`modified_by` AS `modified_by`,`s`.`version` AS `version`,`s`.`min_version` AS `min_version`,`s`.`locked` AS `locked`,`s`.`locked_by` AS `locked_by`,`s`.`product_id` AS `product_id`,`s`.`discount_price_base` AS `discount_price_base`,`s`.`discount_price_retail` AS `discount_price_retail`,`s`.`discount_percentage` AS `discount_percentage`,`s`.`type` AS `type`,`s`.`discount_name` AS `discount_name`,`s`.`date_valid_from` AS `date_valid_from`,`s`.`date_valid_to` AS `date_valid_to`,`s`.`applied_to` AS `applied_to`,`s`.`discount_type` AS `discount_type` from (select `pdc`.`id` AS `id`,`pdc`.`entity_type_id` AS `entity_type_id`,`pdc`.`attribute_set_id` AS `attribute_set_id`,`pdc`.`modified` AS `modified`,`pdc`.`created` AS `created`,`pdc`.`entity_state_id` AS `entity_state_id`,`pdc`.`created_by` AS `created_by`,`pdc`.`modified_by` AS `modified_by`,`pdc`.`version` AS `version`,`pdc`.`min_version` AS `min_version`,`pdc`.`locked` AS `locked`,`pdc`.`locked_by` AS `locked_by`,`pdc`.`product_id` AS `product_id`,`pdc`.`discount_price_base` AS `discount_price_base`,`pdc`.`discount_price_retail` AS `discount_price_retail`,`pdc`.`rebate` AS `discount_percentage`,`pdc`.`type` AS `type`,`dc`.`name` AS `discount_name`,`pdc`.`date_valid_from` AS `date_valid_from`,`pdc`.`date_valid_to` AS `date_valid_to`,'' AS `applied_to`,3 AS `discount_type` from (`product_discount_catalog_price_entity` `pdc` left join `discount_catalog_entity` `dc` on((`pdc`.`type` = `dc`.`id`))) union select `pagp`.`id` AS `id`,`pagp`.`entity_type_id` AS `entity_type_id`,`pagp`.`attribute_set_id` AS `attribute_set_id`,`pagp`.`modified` AS `modified`,`pagp`.`created` AS `created`,`pagp`.`entity_state_id` AS `entity_state_id`,`pagp`.`created_by` AS `created_by`,`pagp`.`modified_by` AS `modified_by`,`pagp`.`version` AS `version`,`pagp`.`min_version` AS `min_version`,`pagp`.`locked` AS `locked`,`pagp`.`locked_by` AS `locked_by`,`pagp`.`product_id` AS `product_id`,`pagp`.`discount_price_base` AS `discount_price_base`,`pagp`.`discount_price_retail` AS `discount_price_retail`,`pagp`.`rebate` AS `discount_percentage`,`pagp`.`type` AS `type`,`dc`.`name` AS `discount_name`,`pagp`.`date_valid_from` AS `date_valid_from`,`pagp`.`date_valid_to` AS `date_valid_to`,`ag`.`name` AS `applied_to`,2 AS `discount_type` from ((`product_account_group_price_entity` `pagp` left join `discount_catalog_entity` `dc` on((`pagp`.`type` = `dc`.`id`))) left join `account_group_entity` `ag` on((`pagp`.`account_group_id` = `ag`.`id`))) union select `pap`.`id` AS `id`,`pap`.`entity_type_id` AS `entity_type_id`,`pap`.`attribute_set_id` AS `attribute_set_id`,`pap`.`modified` AS `modified`,`pap`.`created` AS `created`,`pap`.`entity_state_id` AS `entity_state_id`,`pap`.`created_by` AS `created_by`,`pap`.`modified_by` AS `modified_by`,`pap`.`version` AS `version`,`pap`.`min_version` AS `min_version`,`pap`.`locked` AS `locked`,`pap`.`locked_by` AS `locked_by`,`pap`.`product_id` AS `product_id`,`pap`.`discount_price_base` AS `discount_price_base`,`pap`.`discount_price_retail` AS `discount_price_retail`,`pap`.`rebate` AS `discount_percentage`,`pap`.`type` AS `type`,`dc`.`name` AS `discount_name`,`pap`.`date_valid_from` AS `date_valid_from`,`pap`.`date_valid_to` AS `date_valid_to`,`a`.`name` AS `applied_to`,1 AS `discount_type` from ((`product_account_price_entity` `pap` left join `discount_catalog_entity` `dc` on((`pap`.`type` = `dc`.`id`))) left join `account_entity` `a` on((`pap`.`account_id` = `a`.`id`)))) `s` order by `s`.`discount_type` desc;";
            $this->databaseContext->executeNonQuery($q);

            /** entity_log_view_entity */
            $q = "CREATE OR REPLACE VIEW entity_log_view_entity AS select `e`.`id` AS `id`,`e`.`entity_type_id` AS `entity_type_id`,`e`.`attribute_set_id` AS `attribute_set_id`,`e`.`attribute_set_code` AS `entity_attribute_set`,`e`.`entity_id` AS `entity_id`,`e`.`event_time` AS `modified`,`e`.`event_time` AS `created`,`e`.`event_time` AS `event_time`,1 AS `entity_state_id`,`e`.`username` AS `created_by`,`e`.`username` AS `modified_by`,`e`.`username` AS `username`,'' AS `version`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,`e`.`previous_values` AS `previous_values`,`e`.`content` AS `current_values`,`e`.`action` AS `entity_action` from `entity_log` `e`;";
            $this->databaseContext->executeNonQuery($q);

            if(empty($this->entityManager)){
                $this->entityManager = $this->getContainer()->get("entity_manager");
            }

            /** @var AttributeSet $atProductAccountPrice */
            $atProductAccountPrice = $this->entityManager->getAttributeSetByCode("product_account_price");

            /** @var AttributeSet $atProductAccountGroupPrice */
            $atProductAccountGroupPrice = $this->entityManager->getAttributeSetByCode("product_account_group_price");

            /**
             * CREATE PROCEDURES
             */
            $q = "DROP PROCEDURE IF EXISTS sp_import_partner_rabats;";
            $this->databaseContext->executeNonQuery($q);

            $q = "CREATE PROCEDURE sp_import_partner_rabats()
            BEGIN
        
            /**BRIŠEMO VIŠAK*/
            
            DELETE FROM product_account_price_staging WHERE to_delete = 1;
            
            DELETE pp
            FROM product_account_price_entity pp
            LEFT JOIN product_account_price_staging ps ON ps.account_id = pp.account_id
            AND ps.product_id = pp.product_id
            WHERE ps.product_id IS NULL;
        
            /**UPDATE POSTOJEĆIH*/
            UPDATE product_account_price_entity pp
            JOIN product_account_price_staging ps ON ps.product_id = pp.product_id AND ps.account_id = pp.account_id
            SET
                pp.price_base=ps.price_base,
                pp.date_valid_from=ps.date_valid_from,
                pp.date_valid_to=ps.date_valid_to,
                pp.type=ps.type,
                pp.rebate=ps.rebate
            WHERE
                pp.price_base<>ps.price_base OR
                pp.date_valid_from<>ps.date_valid_from OR
                pp.date_valid_to<>ps.date_valid_to OR
                pp.type<>ps.type OR
                pp.rebate<>ps.rebate;
        
            /**INSERT RAZLIKE*/
            INSERT INTO product_account_price_entity (
                entity_type_id,
                attribute_set_id,
                created,modified,
                created_by,
                entity_state_id,
                product_id,
                account_id,
                price_base,
                rebate,
                type,
                date_valid_from,
                date_valid_to
            )
            SELECT {$atProductAccountPrice->getEntityTypeId()},{$atProductAccountPrice->getId()},NOW(),NOW(),'import',1,
                ps.product_id,
                ps.account_id,
                ps.price_base,
                ps.rebate,
                ps.type,
                ps.date_valid_from,
                ps.date_valid_to
            FROM product_account_price_staging ps
            LEFT JOIN product_account_price_entity pp ON ps.account_id = pp.account_id
            AND ps.product_id = pp.product_id
            WHERE pp.product_id IS NULL;
            END";
            $this->databaseContext->executeNonQuery($q);

            $q = "DROP PROCEDURE IF EXISTS sp_import_account_groups_rabats;";
            $this->databaseContext->executeNonQuery($q);

            $q = "CREATE PROCEDURE sp_import_account_groups_rabats()
            BEGIN
            
            DELETE FROM product_account_group_price_staging WHERE to_delete = 1;
        
            /**BRIŠEMO VIŠAK*/
            DELETE pp
            FROM product_account_group_price_entity pp
            LEFT JOIN product_account_group_price_staging ps ON ps.account_group_id = pp.account_group_id
            AND ps.product_id = pp.product_id
            WHERE ps.product_id IS NULL;
        
            /**UPDATE POSTOJEĆIH*/
            UPDATE product_account_group_price_entity pp
            JOIN product_account_group_price_staging ps ON ps.product_id = pp.product_id AND ps.account_group_id = pp.account_group_id
            SET
                pp.price_base=ps.price_base,
                pp.date_valid_from=ps.date_valid_from,
                pp.date_valid_to=ps.date_valid_to,
                pp.type=ps.type,
                pp.rebate=ps.rebate
            WHERE
                pp.price_base<>ps.price_base OR
                pp.date_valid_from<>ps.date_valid_from OR
                pp.date_valid_to<>ps.date_valid_to OR
                pp.type<>ps.type OR
                pp.rebate<>ps.rebate;
        
            /**INSERT RAZLIKE*/
            INSERT INTO product_account_group_price_entity (
                entity_type_id,
                attribute_set_id,
                created,modified,
                created_by,
                entity_state_id,
                product_id,
                account_group_id,
                price_base,
                rebate,
                type,
                date_valid_from,
                date_valid_to
            )
            SELECT {$atProductAccountGroupPrice->getEntityTypeId()},{$atProductAccountGroupPrice->getId()},NOW(),NOW(),'import',1,
                ps.product_id,
                ps.account_group_id,
                ps.price_base,
                ps.rebate,
                ps.type,
                ps.date_valid_from,
                ps.date_valid_to
            FROM product_account_group_price_staging ps
            LEFT JOIN product_account_group_price_entity pp ON ps.account_group_id = pp.account_group_id
            AND ps.product_id = pp.product_id
            WHERE pp.product_id IS NULL;
        
            END";
            $this->databaseContext->executeNonQuery($q);

            $q = "DROP PROCEDURE IF EXISTS sp_insert_order_item_fact;";
            $this->databaseContext->executeNonQuery($q);

            $q = "CREATE PROCEDURE sp_insert_order_item_fact(in_date_time date)
            BEGIN
        
            DELETE FROM shape_track_order_item_fact WHERE date_dim_id >= (SELECT id FROM shape_track_date_dim WHERE DATE_FORMAT(fulldate,\"%Y-%m-%d\") = in_date_time);
            
            INSERT INTO shape_track_order_item_fact (
            order_item_id,
            order_id,
            date_dim_id,
            product_id,
            name,
            qty,
            base_price_total,
            base_price_tax,
            base_price_discount_total,
            base_price_item,
            base_price_item_tax,
            order_state_id,
            store_id,
            website_id,
            account_id,
            contact_id,
            core_user_id,
            is_legal_entity,
            account_group_id,
            city_id,
            country_id,
            modified,
            payment_type_id,
            delivery_type_id,
            product_groups,
            order_base_price_total,
            order_base_price_tax,
            order_base_price_items_total,
            order_base_price_items_tax,
            order_base_price_delivery_total,
            order_base_price_delivery_tax,
            currency_id,
            currency_rate,
            discount_coupon_id,
            discount_coupon_price_total
            )
            
            SELECT sb.order_item_id, sb.order_id, sb.date_dim_id, sb.product_id, sb.name, sb.qty, sb.base_price_total, sb.base_price_tax, sb.base_price_discount_total, sb.base_price_item, sb.base_price_item_tax,
            sb.order_state_id, sb.store_id, sb.website_id, sb.account_id, sb.contact_id, sb.core_user_id, sb.is_legal_entity, sb.account_group_id, 
            sb.account_shipping_city_id, sb.country_id, sb.modified, sb.payment_type_id, sb.delivery_type_id, sb.product_groups,
            sb.order_base_price_total, sb.order_base_price_tax, sb.order_base_price_items_total, sb.order_base_price_items_tax, sb.order_base_price_delivery_total, sb.order_base_price_delivery_tax, sb.currency_id, sb.currency_rate, sb.discount_coupon_id, sb.base_price_discount_coupon_total
            FROM
            (SELECT 
            oi.id as order_item_id, 
            o.id as order_id, 
            dd.id as date_dim_id, 
            oi.product_id, 
            oi.name, 
            oi.qty, 
            oi.base_price_total, oi.base_price_tax, oi.base_price_discount_total, oi.base_price_item, oi.base_price_item_tax,
            o.order_state_id, o.store_id, st.website_id, o.account_id, o.contact_id, con.core_user_id, a.is_legal_entity, a.account_group_id, 
            o.account_shipping_city_id, c.country_id, o.modified, o.created, o.payment_type_id, o.delivery_type_id, CONCAT('#',GROUP_CONCAT(ppl.product_group_id SEPARATOR '#'),'#') AS product_groups,
            o.base_price_total as order_base_price_total, o.base_price_tax as order_base_price_tax, o.base_price_items_total as order_base_price_items_total, o.base_price_items_tax as order_base_price_items_tax, o.base_price_delivery_total as order_base_price_delivery_total, o.base_price_delivery_tax as order_base_price_delivery_tax, o.currency_id, o.currency_rate, 
            (CASE 
                WHEN dce.template_id is not null THEN dce.template_id
                ELSE o.discount_coupon_id
            END) AS discount_coupon_id, oi.base_price_discount_coupon_total
            FROM order_item_entity as oi
            LEFT JOIN order_entity as o ON oi.order_id = o.id
            LEFT JOIN account_entity AS a ON o.account_id = a.id
            LEFT JOIN contact_entity as con ON o.contact_id = con.id
            LEFT JOIN city_entity AS c ON o.account_shipping_city_id = c.id
            LEFT JOIN s_store_entity AS st ON o.store_id = st.id
            LEFT JOIN shape_track_date_dim as dd ON DATE_FORMAT(o.created,\"%Y-%m-%d\") = dd.fulldate
            LEFT JOIN discount_coupon_entity as dce ON o.discount_coupon_id = dce.id
            JOIN product_product_group_link_entity AS ppl ON oi.product_id = ppl.product_id
            WHERE DATE_FORMAT(o.modified,\"%Y-%m-%d\") >= in_date_time AND o.entity_state_id = 1
            GROUP BY oi.id) as sb
            ON DUPLICATE KEY UPDATE 
            order_state_id = sb.order_state_id,
            qty = sb.qty,
            base_price_total = sb.base_price_total,
            base_price_tax = sb.base_price_tax,
            base_price_discount_total = sb.base_price_discount_total,
            base_price_item = sb.base_price_item,
            base_price_item_tax = sb.base_price_item_tax,
            is_legal_entity = sb.is_legal_entity,
            account_group_id = sb.account_group_id,
            account_id = sb.account_id,
            contact_id = sb.contact_id,
            core_user_id = sb.core_user_id,
            modified = sb.modified,
            payment_type_id = sb.payment_type_id,
            delivery_type_id = sb.delivery_type_id,
            product_groups = sb.product_groups,
            order_base_price_total = sb.order_base_price_total,
            order_base_price_tax = sb.order_base_price_tax,
            order_base_price_items_total = sb.order_base_price_items_total,
            order_base_price_items_tax = sb.order_base_price_items_tax,
            order_base_price_delivery_total = sb.order_base_price_delivery_total,
            order_base_price_delivery_tax = sb.order_base_price_delivery_tax,
            currency_id = sb.currency_id,
            currency_rate = sb.currency_rate,
            store_id = sb.store_id,
            website_id = sb.website_id,
            discount_coupon_price_total = sb.base_price_discount_coupon_total,
            discount_coupon_id = sb.discount_coupon_id;
            END";
            $this->databaseContext->executeNonQuery($q);

            $q = "DROP PROCEDURE IF EXISTS sp_insert_product_group_fact;";
            $this->databaseContext->executeNonQuery($q);

            if($mysqlType == "MariaDB"){
                $q = 'CREATE PROCEDURE sp_insert_product_group_fact(in_store_id int, in_date_dim_id int, in_product_group_id int, in_success_order_state_ids varchar(20), in_canceled_order_state_ids varchar(20), in_quote_state_ids varchar(20))
                sp_insert_product_group_fact:BEGIN
            
                DECLARE in_success_order_state_ids_local VARCHAR(20);
                DECLARE in_canceled_order_state_ids_local VARCHAR(20);
                DECLARE show_on_store INT(1);
                #DECLARE quote_total_count INT(11);
                #DECLARE quote_total_amount DECIMAL(14,2);
                #DECLARE in_quote_state_ids_local VARCHAR(20);
                
                SET in_success_order_state_ids_local = REPLACE(in_success_order_state_ids, ";", ",");
                SET in_canceled_order_state_ids_local = REPLACE(in_canceled_order_state_ids, ";", ",");
                #SET in_quote_state_ids_local = REPLACE(in_quote_state_ids, ";", ",");
                
                SELECT JSON_CONTAINS(pg.show_on_store, \'1\', CONCAT(\'$."\', in_store_id , \'"\')) into show_on_store FROM product_group_entity as pg WHERE id = in_product_group_id;
                
                IF show_on_store IS NULL OR show_on_store = 0 THEN
                   LEAVE sp_insert_product_group_fact;
                END IF;
                
                ## TODO moze se dodati
                ## broj ordera sa reganim userima
                ## broj ordera sa nereganim userima
                ## broj ordera sa b2c
                ## broj ordera sa b2b
                ## amount ordera sa b2c
                ## amount ordera sa b2b
                ## broj quote sa reganim
                ## broj quote sa nereganim
                ## amount quote sa b2c
                ## amount quote sa b2b
                
                DELETE f FROM shape_track_product_group_fact f
                WHERE f.date_dim_id=in_date_dim_id AND f.store_id=in_store_id AND f.product_group_id=in_product_group_id;
                
                INSERT INTO shape_track_product_group_fact 
                (date_dim_id,
                product_group_id,
                product_group_name,
                product_group_level,
                product_group_number_of_products,
                visits,
                add_to_cart,
                qty_sold,
                qty_canceled,
                order_total_amount,
                order_success_amount,
                order_canceled_amount,
                order_in_process_amount,
                store_id,
                website_id,
                performace_rate_visits,
                modified
                )
                SELECT 		
                f.date_dim_id,
                pg.id,
                REPLACE(JSON_EXTRACT(pg.name,CONCAT(\'$."\', in_store_id , \'"\')) COLLATE utf8mb4_general_ci,\'"\',\'\'),
                pg.level,
                pg.products_in_group,
                0,
                0,
                SUM(f.qty),
                SUM(CASE WHEN f.order_state_id IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_canceled_order_state_ids_local)) THEN f.qty ELSE 0 END),
                SUM(f.base_price_total) as order_total_amount,
                SUM(CASE WHEN f.order_state_id IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_success_order_state_ids_local)) THEN f.base_price_total ELSE 0 END),
                SUM(CASE WHEN f.order_state_id IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_canceled_order_state_ids_local)) THEN f.base_price_total ELSE 0 END),
                SUM(CASE WHEN f.order_state_id NOT IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_success_order_state_ids_local)) AND f.order_state_id NOT IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_canceled_order_state_ids_local)) THEN f.base_price_total ELSE 0 END),
                in_store_id,
                f.website_id,
                0,
                f.modified
                FROM shape_track_order_item_fact f
                LEFT JOIN product_group_entity AS pg ON pg.id = in_product_group_id
                WHERE f.date_dim_id=in_date_dim_id AND f.store_id = in_store_id AND f.product_groups LIKE CONCAT(\'%#\', in_product_group_id , \'#%\')
                GROUP BY f.date_dim_id;
                
                END';

            }
            else{
                $q = 'CREATE PROCEDURE sp_insert_product_group_fact(in_store_id int, in_date_dim_id int, in_product_group_id int, in_success_order_state_ids varchar(20), in_canceled_order_state_ids varchar(20), in_quote_state_ids varchar(20))
                sp_insert_product_group_fact:BEGIN
            
                DECLARE in_success_order_state_ids_local VARCHAR(20);
                DECLARE in_canceled_order_state_ids_local VARCHAR(20);
                DECLARE show_on_store INT(1);
                #DECLARE quote_total_count INT(11);
                #DECLARE quote_total_amount DECIMAL(14,2);
                #DECLARE in_quote_state_ids_local VARCHAR(20);
                
                SET in_success_order_state_ids_local = REPLACE(in_success_order_state_ids, ";", ",");
                SET in_canceled_order_state_ids_local = REPLACE(in_canceled_order_state_ids, ";", ",");
                #SET in_quote_state_ids_local = REPLACE(in_quote_state_ids, ";", ",");
                
                SELECT JSON_CONTAINS(pg.show_on_store, \'1\', CONCAT(\'$."\', in_store_id , \'"\')) into show_on_store FROM product_group_entity as pg WHERE id = in_product_group_id;
                
                IF show_on_store IS NULL OR show_on_store = 0 THEN
                   LEAVE sp_insert_product_group_fact;
                END IF;
                
                ## TODO moze se dodati
                ## broj ordera sa reganim userima
                ## broj ordera sa nereganim userima
                ## broj ordera sa b2c
                ## broj ordera sa b2b
                ## amount ordera sa b2c
                ## amount ordera sa b2b
                ## broj quote sa reganim
                ## broj quote sa nereganim
                ## amount quote sa b2c
                ## amount quote sa b2b
                
                DELETE f FROM shape_track_product_group_fact f
                WHERE f.date_dim_id=in_date_dim_id AND f.store_id=in_store_id AND f.product_group_id=in_product_group_id;
                
                INSERT INTO shape_track_product_group_fact 
                (date_dim_id,
                product_group_id,
                product_group_name,
                product_group_level,
                product_group_number_of_products,
                visits,
                add_to_cart,
                qty_sold,
                qty_canceled,
                order_total_amount,
                order_success_amount,
                order_canceled_amount,
                order_in_process_amount,
                store_id,
                website_id,
                performace_rate_visits,
                modified
                )
                SELECT 		
                f.date_dim_id,
                pg.id,
                REPLACE(JSON_EXTRACT(pg.name,CONCAT(\'$."\', in_store_id , \'"\')) COLLATE utf8mb4_general_ci,\'"\',\'\'),
                pg.level,
                pg.products_in_group,
                0,
                0,
                SUM(f.qty),
                SUM(CASE WHEN f.order_state_id IN (in_canceled_order_state_ids_local) THEN f.qty ELSE 0 END),
                SUM(f.base_price_total) as order_total_amount,
                SUM(CASE WHEN f.order_state_id IN (in_success_order_state_ids_local) THEN f.base_price_total ELSE 0 END),
                SUM(CASE WHEN f.order_state_id IN (in_canceled_order_state_ids_local) THEN f.base_price_total ELSE 0 END),
                SUM(CASE WHEN f.order_state_id NOT IN (in_success_order_state_ids_local) AND f.order_state_id NOT IN (in_canceled_order_state_ids_local) THEN f.base_price_total ELSE 0 END),
                in_store_id,
                f.website_id,
                0,
                f.modified
                FROM shape_track_order_item_fact f
                LEFT JOIN product_group_entity AS pg ON pg.id = in_product_group_id
                WHERE f.date_dim_id=in_date_dim_id AND f.store_id = in_store_id AND f.product_groups LIKE CONCAT(\'%#\', in_product_group_id , \'#%\')
                GROUP BY f.date_dim_id;
                
                END';
            }
            $this->databaseContext->executeNonQuery($q);

            $q = "DROP PROCEDURE IF EXISTS sp_insert_totals_fact;";
            $this->databaseContext->executeNonQuery($q);

            if($mysqlType == "MariaDB") {
                $q = 'CREATE PROCEDURE sp_insert_totals_fact(in_store_id int, in_date_dim_id int, in_success_order_state_ids varchar(20), in_canceled_order_state_ids varchar(20), in_quote_state_ids varchar(20))
                BEGIN
            
                    DECLARE in_success_order_state_ids_local VARCHAR(20);
                    DECLARE in_canceled_order_state_ids_local VARCHAR(20);
                    DECLARE fulldate DATE;
                    DECLARE quote_total_count INT(11);
                    DECLARE quote_total_amount DECIMAL(14,2);
                    DECLARE in_quote_state_ids_local VARCHAR(20);
                    
                    SELECT dd.fulldate into fulldate FROM shape_track_date_dim as dd WHERE id = in_date_dim_id;
                    
                    SET in_success_order_state_ids_local = REPLACE(in_success_order_state_ids, ";", ",");
                    SET in_canceled_order_state_ids_local = REPLACE(in_canceled_order_state_ids, ";", ",");
                    SET in_quote_state_ids_local = REPLACE(in_quote_state_ids, ";", ",");
                    
                    ## TODO moze se dodati
                    ## broj ordera sa reganim userima
                    ## broj ordera sa nereganim userima
                    ## broj ordera sa b2c
                    ## broj ordera sa b2b
                    ## amount ordera sa b2c
                    ## amount ordera sa b2b
                    ## broj quote sa reganim
                    ## broj quote sa nereganim
                    ## amount quote sa b2c
                    ## amount quote sa b2b
                    
                    SELECT count(*), SUM(q.base_price_total) into quote_total_count, quote_total_amount FROM quote_entity as q WHERE DATE_FORMAT(q.created,"%Y-%m-%d") = fulldate AND q.quote_status_id IN (SELECT id FROM quote_status_entity WHERE FIND_IN_SET(id,in_quote_state_ids_local)) AND q.store_id = in_store_id;
                    
                    DELETE f FROM shape_track_totals_fact f
                    WHERE f.date_dim_id=in_date_dim_id AND f.store_id=in_store_id;
                    
                    INSERT INTO shape_track_totals_fact 
                    (date_dim_id,
                    order_total_count,
                    order_total_amount,
                    order_success_count,
                    order_success_amount,
                    order_canceled_count,
                    order_canceled_amount,
                    order_in_process_count,
                    order_in_process_amount,
                    quote_total_count,
                    quote_total_amount,
                    quote_to_order_rate_count,
                    quote_to_order_rate_amount,
                    store_id,
                    website_id,
                    modified
                    )
                    SELECT 		
                    f.date_dim_id,
                    COUNT(DISTINCT(f.order_id)) as order_total_count,
                    SUM(f.base_price_total) as order_total_amount,
                    (SELECT COUNT(DISTINCT(f.order_id)) FROM shape_track_order_item_fact f WHERE f.date_dim_id=in_date_dim_id AND store_id = in_store_id AND f.order_state_id IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_success_order_state_ids_local)) GROUP BY f.date_dim_id) as successuful,
                    #SUM(CASE WHEN f.order_state_id IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_success_order_state_ids_local)) THEN 1 ELSE 0 END),
                    SUM(CASE WHEN f.order_state_id IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_success_order_state_ids_local)) THEN f.base_price_total ELSE 0 END),
                    (SELECT COUNT(DISTINCT(f.order_id)) FROM shape_track_order_item_fact f WHERE f.date_dim_id=in_date_dim_id AND store_id = in_store_id AND f.order_state_id IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_canceled_order_state_ids_local)) GROUP BY f.date_dim_id) as canceled,
                    #SUM(CASE WHEN f.order_state_id IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_canceled_order_state_ids_local)) THEN 1 ELSE 0 END),
                    SUM(CASE WHEN f.order_state_id IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_canceled_order_state_ids_local)) THEN f.base_price_total ELSE 0 END),
                    (SELECT COUNT(DISTINCT(f.order_id)) FROM shape_track_order_item_fact f WHERE f.date_dim_id=in_date_dim_id AND store_id = in_store_id AND f.order_state_id NOT IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_success_order_state_ids_local)) AND f.order_state_id NOT IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_canceled_order_state_ids_local)) GROUP BY f.date_dim_id) as in_process,
                    #SUM(CASE WHEN f.order_state_id NOT IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_success_order_state_ids_local)) AND f.order_state_id NOT IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_canceled_order_state_ids_local)) THEN 1 ELSE 0 END),
                    SUM(CASE WHEN f.order_state_id NOT IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_success_order_state_ids_local)) AND f.order_state_id NOT IN (SELECT id FROM order_state_entity WHERE FIND_IN_SET(id,in_canceled_order_state_ids_local)) THEN f.base_price_total ELSE 0 END),
                    CASE WHEN quote_total_count > 0 THEN quote_total_count ELSE 0 END,
                    CASE WHEN quote_total_amount > 0 THEN quote_total_amount ELSE 0 END,
                    COUNT(DISTINCT(f.order_id)) / (quote_total_count+COUNT(DISTINCT(f.order_id))),
                    SUM(f.base_price_total) / (quote_total_amount+SUM(f.base_price_total)),
                    in_store_id,
                    f.website_id,
                    f.modified
                    FROM shape_track_order_item_fact f
                    WHERE f.date_dim_id=in_date_dim_id AND store_id = in_store_id
                    GROUP BY f.date_dim_id;
                    
                    END';
            }
            else{
                $q = 'CREATE PROCEDURE sp_insert_totals_fact(in_store_id int, in_date_dim_id int, in_success_order_state_ids varchar(20), in_canceled_order_state_ids varchar(20), in_quote_state_ids varchar(20))
                BEGIN
            
                    DECLARE in_success_order_state_ids_local VARCHAR(20);
                    DECLARE in_canceled_order_state_ids_local VARCHAR(20);
                    DECLARE fulldate DATE;
                    DECLARE quote_total_count INT(11);
                    DECLARE quote_total_amount DECIMAL(14,2);
                    DECLARE in_quote_state_ids_local VARCHAR(20);
                    
                    SELECT dd.fulldate into fulldate FROM shape_track_date_dim as dd WHERE id = in_date_dim_id;
                    
                    SET in_success_order_state_ids_local = REPLACE(in_success_order_state_ids, ";", ",");
                    SET in_canceled_order_state_ids_local = REPLACE(in_canceled_order_state_ids, ";", ",");
                    SET in_quote_state_ids_local = REPLACE(in_quote_state_ids, ";", ",");
                    
                    ## TODO moze se dodati
                    ## broj ordera sa reganim userima
                    ## broj ordera sa nereganim userima
                    ## broj ordera sa b2c
                    ## broj ordera sa b2b
                    ## amount ordera sa b2c
                    ## amount ordera sa b2b
                    ## broj quote sa reganim
                    ## broj quote sa nereganim
                    ## amount quote sa b2c
                    ## amount quote sa b2b
                    
                    SELECT count(*), SUM(q.base_price_total) into quote_total_count, quote_total_amount FROM quote_entity as q WHERE DATE_FORMAT(q.created,"%Y-%m-%d") = fulldate AND q.quote_status_id IN (in_quote_state_ids_local) AND q.store_id = in_store_id;
                    
                    DELETE f FROM shape_track_totals_fact f
                    WHERE f.date_dim_id=in_date_dim_id AND f.store_id=in_store_id;
                    
                    INSERT INTO shape_track_totals_fact 
                    (date_dim_id,
                    order_total_count,
                    order_total_amount,
                    order_success_count,
                    order_success_amount,
                    order_canceled_count,
                    order_canceled_amount,
                    order_in_process_count,
                    order_in_process_amount,
                    quote_total_count,
                    quote_total_amount,
                    quote_to_order_rate_count,
                    quote_to_order_rate_amount,
                    store_id,
                    website_id,
                    modified
                    )
                    SELECT 		
                    f.date_dim_id,
                    COUNT(DISTINCT(f.order_id)) as order_total_count,
                    SUM(f.base_price_total) as order_total_amount,
                    (SELECT COUNT(DISTINCT(f.order_id)) FROM shape_track_order_item_fact f WHERE f.date_dim_id=in_date_dim_id AND store_id = in_store_id AND f.order_state_id IN (in_success_order_state_ids_local) GROUP BY f.date_dim_id) as successuful,
                    #SUM(CASE WHEN f.order_state_id IN (in_success_order_state_ids_local) THEN 1 ELSE 0 END),
                    SUM(CASE WHEN f.order_state_id IN (in_success_order_state_ids_local) THEN f.base_price_total ELSE 0 END),
                    (SELECT COUNT(DISTINCT(f.order_id)) FROM shape_track_order_item_fact f WHERE f.date_dim_id=in_date_dim_id AND store_id = in_store_id AND f.order_state_id IN (in_canceled_order_state_ids_local) GROUP BY f.date_dim_id) as canceled,
                    #SUM(CASE WHEN f.order_state_id IN (in_canceled_order_state_ids_local) THEN 1 ELSE 0 END),
                    SUM(CASE WHEN f.order_state_id IN (in_canceled_order_state_ids_local) THEN f.base_price_total ELSE 0 END),
                    (SELECT COUNT(DISTINCT(f.order_id)) FROM shape_track_order_item_fact f WHERE f.date_dim_id=in_date_dim_id AND store_id = in_store_id AND f.order_state_id NOT IN (in_success_order_state_ids_local) AND f.order_state_id NOT IN (in_canceled_order_state_ids_local) GROUP BY f.date_dim_id) as in_process,
                    #SUM(CASE WHEN f.order_state_id NOT IN (in_success_order_state_ids_local) AND f.order_state_id NOT IN (in_canceled_order_state_ids_local) THEN 1 ELSE 0 END),
                    SUM(CASE WHEN f.order_state_id NOT IN (in_success_order_state_ids_local) AND f.order_state_id NOT IN (in_canceled_order_state_ids_local) THEN f.base_price_total ELSE 0 END),
                    CASE WHEN quote_total_count > 0 THEN quote_total_count ELSE 0 END,
                    CASE WHEN quote_total_amount > 0 THEN quote_total_amount ELSE 0 END,
                    COUNT(DISTINCT(f.order_id)) / (quote_total_count+COUNT(DISTINCT(f.order_id))),
                    SUM(f.base_price_total) / (quote_total_amount+SUM(f.base_price_total)),
                    in_store_id,
                    f.website_id,
                    f.modified
                    FROM shape_track_order_item_fact f
                    WHERE f.date_dim_id=in_date_dim_id AND store_id = in_store_id
                    GROUP BY f.date_dim_id;
                    
                    END';
            }
            $this->databaseContext->executeNonQuery($q);

            /**
             * TRIGGER FROM PRICE HISTORY
             */
            $q = "DROP TRIGGER IF EXISTS product_discount_catalog_price_history_insert;";
            $this->databaseContext->executeNonQuery($q);

            $q = "DROP TRIGGER IF EXISTS product_price_history;";
            $this->databaseContext->executeNonQuery($q);

            $q = "DROP TRIGGER IF EXISTS product_discount_catalog_price_history;";
            $this->databaseContext->executeNonQuery($q);

            $q = "DROP TRIGGER IF EXISTS product_price_history_insert;";
            $this->databaseContext->executeNonQuery($q);

            if(!isset($_ENV["DISABLE_PRODUCT_PRICE_HISTORY"]) || !$_ENV["DISABLE_PRODUCT_PRICE_HISTORY"]) {

                /** @var AttributeSet $productPriceHistoryAS */
                $productPriceHistoryAS = $this->entityManager->getAttributeSetByCode("product_price_history");

                /** @var AttributeSet $productAS */
                $productAS = $this->entityManager->getAttributeSetByCode("product");

                /** @var AttributeSet $productDiscountCatalogAS */
                $productDiscountCatalogAS = $this->entityManager->getAttributeSetByCode("product_discount_catalog_price");

                if (!empty($productPriceHistoryAS)) {
                    $q = "CREATE TRIGGER product_discount_catalog_price_history_insert
                    AFTER insert ON product_discount_catalog_price_entity 
                    FOR EACH ROW
                    BEGIN
                        IF
                            NEW.discount_price_retail is not null and NEW.discount_price_retail > 0
                            and now() BETWEEN IFNULL(NEW.date_valid_from,DATE_SUB(NOW(), INTERVAL 1 SECOND)) and IFNULL(NEW.date_valid_to,DATE_ADD(NOW(), INTERVAL 1 SECOND))
                        THEN
                            INSERT INTO product_price_history_entity 
                                (entity_type_id
                                ,attribute_set_id
                                ,created
                                ,modified
                                ,entity_state_id
                                ,price_change_date
                                ,product_id
                                ,price
                                ,price_type
                                ,related_entity_type
                                ,related_entity_id
                                ,product_currency_id)
                            VALUES
                                (" . $productPriceHistoryAS->getEntityTypeId() . "
                                ," . $productPriceHistoryAS->getId() . "
                                ,now()
                                ,now()
                                ,'1'
                                ,now()
                                ,NEW.product_id
                                ,NEW.discount_price_retail
                                ,'discount_price_retail'
                                ,'product_discount_catalog_price_entity'
                                ," . $productDiscountCatalogAS->getEntityTypeId() . "
                                ,(select currency_id from product_entity WHERE id = NEW.product_id ));
                            end if;	
                    END;";
                    $this->databaseContext->executeNonQuery($q);

                    $q = "CREATE TRIGGER product_discount_catalog_price_history 
                            AFTER UPDATE ON product_discount_catalog_price_entity 
                            FOR EACH ROW
                            BEGIN
                            IF
                                (NEW.discount_price_retail <> OLD.discount_price_retail or OLD.discount_price_retail is null)
                                and concat(NEW.id,left(NOW(),10),NEW.discount_price_retail) not in (select concat(product_id,left(price_change_date,10),price) from product_price_history_entity) 
                                and now() BETWEEN IFNULL(NEW.date_valid_from,DATE_SUB(NOW(), INTERVAL 1 SECOND)) and IFNULL(NEW.date_valid_to,DATE_ADD(NOW(), INTERVAL 1 SECOND))
                            THEN
                                INSERT INTO product_price_history_entity 
                                    (entity_type_id
                                    ,attribute_set_id
                                    ,created
                                    ,modified
                                    ,entity_state_id
                                    ,price_change_date
                                    ,product_id
                                    ,price
                                    ,price_type
                                    ,related_entity_type
                                    ,related_entity_id
                                    ,product_currency_id)
                                VALUES
                                    (" . $productPriceHistoryAS->getEntityTypeId() . "
                                    ," . $productPriceHistoryAS->getId() . "
                                    ,now()
                                    ,now()
                                    ,'1'
                                    ,now()
                                    ,NEW.product_id
                                    ,NEW.discount_price_retail
                                    ,'discount_price_retail'
                                    ,'product_discount_catalog_price_entity'
                                    ," . $productDiscountCatalogAS->getEntityTypeId() . "
                                    ,(select currency_id from product_entity WHERE id = NEW.product_id ));
                                end if;	
                        END;";
                    $this->databaseContext->executeNonQuery($q);

                    $q = "CREATE TRIGGER product_price_history_insert
                    AFTER insert ON product_entity
                    FOR EACH ROW
                    BEGIN
                        IF
                            NEW.price_retail is not null and NEW.price_retail > 0
                        THEN
                            INSERT INTO product_price_history_entity 
                                (entity_type_id
                                ,attribute_set_id
                                ,created
                                ,modified
                                ,entity_state_id
                                ,price_change_date
                                ,product_id
                                ,price
                                ,price_type
                                ,related_entity_type
                                ,related_entity_id
                                ,product_currency_id)
                            VALUES
                                (" . $productPriceHistoryAS->getEntityTypeId() . "
                                ," . $productPriceHistoryAS->getId() . "
                                ,now()
                                ,now()
                                ,'1'
                                ,now()
                                ,NEW.id
                                ,NEW.price_retail
                                ,'price_retail'
                                ,'product_entity'
                                ," . $productAS->getEntityTypeId() . "
                                ,NEW.currency_id);
                            end if;
                            
                        IF
                            NEW.discount_price_retail is not null and NEW.discount_price_retail > 0
                            and now() BETWEEN IFNULL(NEW.date_discount_from,DATE_SUB(NOW(), INTERVAL 1 SECOND)) and IFNULL(NEW.date_discount_to,DATE_ADD(NOW(), INTERVAL 1 SECOND))
                        THEN
                            INSERT INTO product_price_history_entity 
                                (entity_type_id
                                ,attribute_set_id
                                ,created
                                ,modified
                                ,entity_state_id
                                ,price_change_date
                                ,product_id
                                ,price
                                ,price_type
                                ,related_entity_type
                                ,related_entity_id
                                ,product_currency_id)
                            VALUES
                                (" . $productPriceHistoryAS->getEntityTypeId() . "
                                ," . $productPriceHistoryAS->getId() . "
                                ,now()
                                ,now()
                                ,'1'
                                ,now()
                                ,NEW.id
                                ,NEW.discount_price_retail
                                ,'discount_price_retail'
                                ,'product_entity'
                                ," . $productAS->getEntityTypeId() . "
                                ,NEW.currency_id);
                            end if;	
                    END;";
                    $this->databaseContext->executeNonQuery($q);

                    $q = "CREATE TRIGGER product_price_history 
                            AFTER UPDATE ON product_entity 
                            FOR EACH ROW
                            BEGIN
                                IF
                                    (NEW.price_retail <> OLD.price_retail or OLD.price_retail is null) and NEW.price_retail is not null and NEW.price_retail > 0
                                AND concat( NEW.id, LEFT ( NOW(), 10 ), NEW.price_retail ) NOT IN ( SELECT concat( product_id, LEFT ( price_change_date, 10 ), price ) FROM product_price_history_entity )
                                THEN
                                    INSERT INTO product_price_history_entity 
                                        (entity_type_id
                                        ,attribute_set_id
                                        ,created
                                        ,modified
                                        ,entity_state_id
                                        ,price_change_date
                                        ,product_id
                                        ,price
                                        ,price_type
                                        ,related_entity_type
                                        ,related_entity_id
                                        ,product_currency_id)
                                    VALUES
                                    (" . $productPriceHistoryAS->getEntityTypeId() . "
                                    ," . $productPriceHistoryAS->getId() . "
                                    ,now()
                                    ,now()
                                    ,'1'
                                    ,now()
                                    ,NEW.id
                                    ,NEW.price_retail
                                    ,'price_retail'
                                    ,'product_entity'
                                    ," . $productAS->getEntityTypeId() . "
                                    ,NEW.currency_id
                                    );
                                end if;
                                
                            IF
                                (NEW.discount_price_retail <> OLD.discount_price_retail or OLD.discount_price_retail is null) and NEW.discount_price_retail is not null and NEW.discount_price_retail > 0
                                and concat(NEW.id,left(NOW(),10),NEW.discount_price_retail) not in (select concat(product_id,left(price_change_date,10),price) from product_price_history_entity) 
                                and now() BETWEEN IFNULL(NEW.date_discount_from,DATE_SUB(NOW(), INTERVAL 1 SECOND)) and IFNULL(NEW.date_discount_to,DATE_ADD(NOW(), INTERVAL 1 SECOND))
                            THEN
                                INSERT INTO product_price_history_entity 
                                    (entity_type_id
                                    ,attribute_set_id
                                    ,created
                                    ,modified
                                    ,entity_state_id
                                                
                                    ,price_change_date
                                    ,product_id
                                    ,price
                                    ,price_type
                                    ,related_entity_type
                                    ,related_entity_id
                                    ,product_currency_id)
                                VALUES
                                    (" . $productPriceHistoryAS->getEntityTypeId() . "
                                    ," . $productPriceHistoryAS->getId() . "
                                    ,now()
                                    ,now()
                                    ,'1'
                                    ,now()
                                    ,NEW.id
                                    ,NEW.discount_price_retail
                                    ,'discount_price_retail'
                                    ,'product_entity'
                                    ," . $productAS->getEntityTypeId() . "
                                    ,NEW.currency_id
                                    );
                                end if;	
                        END;";
                    $this->databaseContext->executeNonQuery($q);

                    $q = "DROP PROCEDURE IF EXISTS sp_product_active_discount_check;";
                    $this->databaseContext->executeNonQuery($q);

                    $q = "CREATE PROCEDURE sp_product_active_discount_check()
                    BEGIN
                        INSERT INTO product_price_history_entity 
                                (entity_type_id
                                ,attribute_set_id
                                ,created
                                ,modified
                                ,entity_state_id
                                ,price_change_date
                                ,product_id
                                ,price
                                ,price_type
                                ,related_entity_type
                                ,related_entity_id
                                ,product_currency_id)
                      SELECT
                                " . $productPriceHistoryAS->getEntityTypeId() . "
                                ," . $productPriceHistoryAS->getId() . "
                                ,now()
                                ,now()
                                ,'1'
                                ,now()
                                ,p.id
                                ,p.discount_price_retail
                                ,'discount_price_retail'
                                ,'product_entity'
                                ," . $productAS->getEntityTypeId() . "
                                ,p.currency_id
                        from product_entity p
                        left join product_price_history_entity h on p.id = h.product_id 
                        and h.price = p.discount_price_retail
                        and price_change_date BETWEEN date_discount_from and IFNULL(date_discount_to,DATE_ADD(NOW(), INTERVAL 1 SECOND))
                        where NOW() BETWEEN date_discount_from and IFNULL(date_discount_to,DATE_ADD(NOW(), INTERVAL 1 SECOND))
                        and h.price is null
                        and p.discount_price_retail is not null and p.discount_price_retail > 0;
                    END";
                    $this->databaseContext->executeNonQuery($q);

                    $q = "DROP PROCEDURE IF EXISTS sp_product_active_discount_catalog_check;";
                    $this->databaseContext->executeNonQuery($q);

                    $q = "CREATE PROCEDURE sp_product_active_discount_catalog_check()
                    BEGIN
                        INSERT INTO product_price_history_entity
                                (entity_type_id
                                ,attribute_set_id
                                ,created
                                ,modified
                                ,entity_state_id
                                ,price_change_date
                                ,product_id
                                ,price
                                ,price_type
                                ,related_entity_type
                                ,related_entity_id
                                ,product_currency_id)
                        SELECT
                                " . $productPriceHistoryAS->getEntityTypeId() . "
                                ," . $productPriceHistoryAS->getId() . "
                                ,now()
                                ,now()
                                ,'1'
                                ,now()
                                ,p.product_id
                                ,p.discount_price_retail
                                ,'discount_price_retail'
                                ,'product_discount_catalog_price_entity'
                                ," . $productAS->getEntityTypeId() . "
                                ,a.currency_id
                        from product_discount_catalog_price_entity p
                        left join product_entity a on p.product_id = a.id
                        left join product_price_history_entity h on p.product_id = h.product_id 
                        and h.price = p.discount_price_retail
                        and price_change_date BETWEEN date_valid_from and IFNULL(date_valid_to,DATE_ADD(NOW(), INTERVAL 1 SECOND))
                        where NOW() BETWEEN date_valid_from and IFNULL(date_valid_to,DATE_ADD(NOW(), INTERVAL 1 SECOND))
                        and h.price is null
                        and p.discount_price_retail is not null and p.discount_price_retail > 0;
                    END";
                    $this->databaseContext->executeNonQuery($q);
                }
            }

            /**
             * TRIGGER QUOTE,ORDER,ORDER_RETURN INCREMENT ID
             */
            if(empty($this->routeManager)){
                $this->routeManager = $this->getContainer()->get("route_manager");
            }

            $stores = $this->routeManager->getStores();

            if(EntityHelper::isCountable($stores) && count($stores)){

                /**
                 * Set increment for quote
                 */
                $q = "DROP TRIGGER IF EXISTS quote_increment_id;";
                $this->databaseContext->executeNonQuery($q);

                $q = "CREATE TRIGGER quote_increment_id
                BEFORE insert ON quote_entity
                FOR EACH ROW
                BEGIN
                    DECLARE store INTEGER;
                    DECLARE start_1 INTEGER;
                    set @start_1 = 100;
                    set new.increment_id =
                    IFNULL((select max(increment_id)
                        from quote_entity) + 1, @start_1 );
                END;";
                $this->databaseContext->executeNonQuery($q);

                if(empty($this->settingsManager)){
                    $this->settingsManager = $this->getContainer()->get("application_settings_manager");
                }

                /**
                 * Set increment for order
                 */
                /** @var SettingsEntity $setting */
                $setting = $this->settingsManager->getRawApplicationSettingEntityByCode("order_increment_start_from");

                $nameArray = $showOnStoreArray = $settingsValueArray = Array();
                $code = "";
                if(!empty($setting)){
                    $nameArray = $setting->getName();
                    $code = $setting->getCode();
                    $settingsValueArray = $setting->getSettingsValue();
                    $showOnStoreArray = $setting->getShowOnStore();
                }

                $update = false;
                $start = 1000001;
                /** @var SStoreEntity $store */
                foreach ($stores as $store){
                    if(!isset($settingsValueArray[$store->getId()])){
                        $update = true;
                        $nameArray[$store->getId()] = "order_increment_start_from";
                        $code = "order_increment_start_from";
                        $settingsValueArray[$store->getId()] = ($store->getId()-2)*$start;
                        $showOnStoreArray[$store->getId()] = 1;
                    }
                }
                if($update){
                    $data = array();
                    $data["name"] = $nameArray;
                    $data["code"] = $code;
                    $data["settings_value"] = $settingsValueArray;
                    $data["show_on_store"] = $showOnStoreArray;
                    /** @var SettingsEntity $setting */
                    $setting = $this->settingsManager->createUpdateSettings($setting, $data);
                }

                $settingsValueArray = $setting->getSettingsValue();

                $settingsValueArrayByWebsite = Array();

                /** @var SStoreEntity $store */
                foreach ($stores as $store){
                    $settingsValueArrayByWebsite[$store->getWebsiteId()]["store_ids"][] = $store->getId();
                    $settingsValueArrayByWebsite[$store->getWebsiteId()]["increment_id"] = $settingsValueArray[$store->getId()];
                }

                $q = "DROP TRIGGER IF EXISTS order_increment_id;";
                $this->databaseContext->executeNonQuery($q);

                $q = "CREATE TRIGGER order_increment_id
                    BEFORE insert ON order_entity
                    FOR EACH ROW
                    BEGIN
                        DECLARE store INTEGER;";
                        foreach ($settingsValueArrayByWebsite as $key => $value){
                            $q.="DECLARE start_{$key} INTEGER;";
                        }
                        foreach ($settingsValueArrayByWebsite as $key => $value){
                            $q.="set @start_{$key} = {$value["increment_id"]};";
                        }
                        /*$q.="set @store := new.store_id;
                        set new.increment_id =
                        IFNULL((select max(increment_id)
                            from order_entity
                            where store_id IN (@store)) + 1,";*/
                        $q.="set @store := (SELECT website_id FROM s_store_entity WHERE id = new.store_id);
                        set new.increment_id =
                        IFNULL((select max(increment_id)
                            from order_entity as o LEFT JOIN s_store_entity as ss ON o.store_id = ss.id
                            where ss.website_id = @store) + 1,";
                                $tmpQA = Array();
                                $settingsValueArray = array_reverse($settingsValueArrayByWebsite, true);
                                $isFirst = 1;
                                foreach ($settingsValueArray as $key => $value){
                                    $tmpQ = "if (@store = {$key}, @start_{$key},";
                                    if($isFirst){
                                        $tmpQ.="{$start})";
                                        $isFirst=0;
                                    }
                                    $tmpQA[] = $tmpQ;
                                }
                                $tmpQ = "";
                                foreach ($tmpQA as $t){
                                    $tmpQ = $t.$tmpQ.")";
                                }
                                $q.=$tmpQ;
                                //if (@store = 3, @start_1,if (@store = 4, @start_2,1))
                    $q.=";END;";
                $this->databaseContext->executeNonQuery($q);

                /**
                 * Set increment for order return
                 */
                /** @var SettingsEntity $setting */
                $setting = $this->settingsManager->getRawApplicationSettingEntityByCode("order_return_increment_start_from");

                $nameArray = $showOnStoreArray = $settingsValueArray = Array();
                $code = "";
                if(!empty($setting)){
                    $nameArray = $setting->getName();
                    $code = $setting->getCode();
                    $settingsValueArray = $setting->getSettingsValue();
                    $showOnStoreArray = $setting->getShowOnStore();
                }

                $update = false;
                $start = 1000001;
                /** @var SStoreEntity $store */
                foreach ($stores as $store){
                    if(!isset($settingsValueArray[$store->getId()])){
                        $update = true;
                        $nameArray[$store->getId()] = "order_return_increment_start_from";
                        $code = "order_return_increment_start_from";
                        $settingsValueArray[$store->getId()] = ($store->getId()-2)*$start;
                        $showOnStoreArray[$store->getId()] = 1;
                    }
                }
                if($update){
                    $data = array();
                    $data["name"] = $nameArray;
                    $data["code"] = $code;
                    $data["settings_value"] = $settingsValueArray;
                    $data["show_on_store"] = $showOnStoreArray;
                    /** @var SettingsEntity $setting */
                    $setting = $this->settingsManager->createUpdateSettings($setting, $data);
                }

                $settingsValueArray = $setting->getSettingsValue();

                $settingsValueArrayByWebsite = Array();

                /** @var SStoreEntity $store */
                foreach ($stores as $store){
                    $settingsValueArrayByWebsite[$store->getWebsiteId()]["store_ids"][] = $store->getId();
                    $settingsValueArrayByWebsite[$store->getWebsiteId()]["increment_id"] = $settingsValueArray[$store->getId()];
                }

                $q = "DROP TRIGGER IF EXISTS order_return_increment_id;";
                $this->databaseContext->executeNonQuery($q);

                $q = "CREATE TRIGGER order_return_increment_id
                    BEFORE insert ON order_return_entity
                    FOR EACH ROW
                    BEGIN
                        DECLARE store INTEGER;";
                        foreach ($settingsValueArrayByWebsite as $key => $value){
                            $q.="DECLARE start_{$key} INTEGER;";
                        }
                        foreach ($settingsValueArrayByWebsite as $key => $value){
                            $q.="set @start_{$key} = {$value["increment_id"]};";
                        }
                        /*$q.="set @store := new.store_id;
                        set new.increment_id =
                        IFNULL((select max(increment_id)
                            from order_return_entity
                            where store_id = @store) + 1,";*/
                        $q.="set @store := (SELECT website_id FROM s_store_entity WHERE id = new.store_id);
                        set new.increment_id =
                        IFNULL((select max(increment_id)
                            from order_return_entity as o LEFT JOIN s_store_entity as ss ON o.store_id = ss.id
                            where ss.website_id = @store) + 1,";
                                $tmpQA = Array();
                                $settingsValueArray = array_reverse($settingsValueArrayByWebsite, true);
                                $isFirst = 1;
                                foreach ($settingsValueArray as $key => $value){
                                    $tmpQ = "if (@store = {$key}, @start_{$key},";
                                    if($isFirst){
                                        $tmpQ.="{$start})";
                                        $isFirst=0;
                                    }
                                    $tmpQA[] = $tmpQ;
                                }
                                $tmpQ = "";
                                foreach ($tmpQA as $t){
                                    $tmpQ = $t.$tmpQ.")";
                                }
                                $q.=$tmpQ;
                                //if (@store = 3, @start_1,if (@store = 4, @start_2,1))
                    $q.=";END;";
                $this->databaseContext->executeNonQuery($q);
            }

            return true;

        }
        elseif ($func == "rebuild_fk") {

            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");

            $q = "select kcu.referenced_column_name, fks.table_name as foreign_table,
                   '->' as rel,
                   fks.referenced_table_name
                          as primary_table,
                   fks.constraint_name AS constraint_name,
                   group_concat(kcu.column_name
                        order by position_in_unique_constraint separator ', ') 
                         as fk_columns
            from ssinformation.referential_constraints fks
            join ssinformation.key_column_usage kcu
                 on fks.constraint_schema = kcu.table_schema
                 and fks.table_name = kcu.table_name
                 and fks.constraint_name = kcu.constraint_name
             where fks.constraint_schema = '{$_ENV["DATABASE_NAME"]}'
            group by fks.constraint_schema,
                     fks.table_name,
                     fks.unique_constraint_schema,
                     fks.referenced_table_name,
                     fks.constraint_name
            order by fks.constraint_schema,
                     fks.table_name;";
            $foreginKeysTmp = $databaseContext->getAll($q);

            if(!empty($foreginKeysTmp)){
                foreach ($foreginKeysTmp as $fk){
                    if(!isset($fk["constraint_name"]) && isset($fk["CONSTRAINT_NAME"])){
                        $fk["constraint_name"] = $fk["CONSTRAINT_NAME"];
                    }
                    $q = "ALTER TABLE {$fk["foreign_table"]}
                    DROP FOREIGN KEY `{$fk["constraint_name"]}`";
                    echo $q."\r\n";

                    $databaseContext->executeNonQuery($q);
                }
            }

            /** @var AttributeContext $attributeContext */
            $attributeContext = $this->getContainer()->get("attribute_context");

            $lookups = $attributeContext->getBy(Array("backendType" => "lookup"));

            $tablesToRestrict = Array(
                "product_entity",
                "project_entity",
                "account_entity",
                "order_entity",
            );
            $tablesToExclude = Array(
                "order_state_history_entity",
                "order_return_entity",
                "quote_entity",
                "user_role_entity"
            );
            $relatedTablesToRestrict = Array(
                "user_entity"
            );

            /** @var Attribute $lookup */
            foreach ($lookups as $lookup){

                if($lookup->getEntityType()->getIsView()){
                    continue;
                }

                /** @var DatabaseManager $databaseManager */
                $databaseManager = $this->getContainer()->get("database_manager");

                if(!$databaseManager->checkIfTableExists($lookup->getEntityType()->getEntityTable())){
                    continue;
                }
                if(!$databaseManager->checkIfTableExists($lookup->getLookupEntityType()->getEntityTable())){
                    continue;
                }

                if(empty($lookup->getLookupAttribute()) || empty($lookup->getLookupEntityType())){
                    dump("Malformed attribute id: ".$lookup->getId());
                    continue;
                }

                $fk_name = md5("{$lookup->getEntityType()->getEntityTable()}_{$lookup->getLookupEntityType()->getEntityTable()}_{$lookup->getAttributeCode()}");

                dump("Building FK {$lookup->getEntityType()->getEntityTable()}.{$lookup->getAttributeCode()} -> {$lookup->getLookupEntityType()->getEntityTable()}.id");

                $fk_type = "CASCADE";
                if(in_array($lookup->getEntityType()->getEntityTable(),$tablesToRestrict) && !in_array($lookup->getLookupEntityType()->getEntityTable(),$tablesToExclude)){
                    $fk_type = "RESTRICT";
                }
                elseif(in_array($lookup->getLookupEntityType()->getEntityTable(),$relatedTablesToRestrict) && !in_array($lookup->getEntityType()->getEntityTable(),$tablesToExclude)){
                    $fk_type = "RESTRICT";
                }
                elseif(in_array($lookup->getLookupEntityType()->getSyncContent(),$relatedTablesToRestrict) && !in_array($lookup->getEntityType()->getEntityTable(),$tablesToExclude)){
                    $fk_type = "RESTRICT";
                }

                $q = "ALTER TABLE {$lookup->getEntityType()->getEntityTable()}
                ADD CONSTRAINT `{$fk_name}` FOREIGN KEY (`{$lookup->getAttributeCode()}`) REFERENCES `{$lookup->getLookupEntityType()->getEntityTable()}` (`id`) ON DELETE {$fk_type} ON UPDATE {$fk_type};";
                try{
                    $databaseContext->executeNonQuery($q);
                }
                catch (\Exception $e){
                    $q = "UPDATE {$lookup->getEntityType()->getEntityTable()} SET {$lookup->getAttributeCode()} = null WHERE {$lookup->getAttributeCode()} NOT IN (SELECT id FROM {$lookup->getLookupEntityType()->getEntityTable()});";
                    echo $q."\r\n";
                    $databaseContext->executeNonQuery($q);

                    $q = "ALTER TABLE {$lookup->getEntityType()->getEntityTable()}
                    ADD CONSTRAINT `{$fk_name}` FOREIGN KEY (`{$lookup->getAttributeCode()}`) REFERENCES `{$lookup->getLookupEntityType()->getEntityTable()}` (`id`) ON DELETE {$fk_type} ON UPDATE {$fk_type};";
                    $databaseContext->executeNonQuery($q);
                }
            }

            $baseFkList = Array();
            $baseFkList[] = Array("table" => "attribute", "fk_table" => "entity_type", "column" => "entity_type_id", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "attribute", "fk_table" => "attribute", "column" => "lookup_attribute_id", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "attribute", "fk_table" => "attribute_set", "column" => "lookup_attribute_set_id", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "attribute", "fk_table" => "entity_type", "column" => "lookup_entity_type_id", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "attribute_group", "fk_table" => "attribute_set", "column" => "attribute_set_id", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "attribute_set", "fk_table" => "entity_type", "column" => "entity_type_id", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "entity_attribute", "fk_table" => "attribute", "column" => "attribute_id", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "entity_attribute", "fk_table" => "attribute_set", "column" => "attribute_set_id", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "entity_attribute", "fk_table" => "entity_type", "column" => "entity_type_id", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "entity_attribute", "fk_table" => "attribute_group", "column" => "attribute_group_id", "fk_attribute" => "id");

            $baseFkList[] = Array("table" => "entity_level_permission", "fk_table" => "entity_type", "column" => "entity_type_id", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "entity_level_permission", "fk_table" => "role_entity", "column" => "role_id", "fk_attribute" => "id");

            $baseFkList[] = Array("table" => "entity_log", "fk_table" => "attribute_set", "column" => "attribute_set_id", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "entity_log", "fk_table" => "entity_type", "column" => "entity_type_id", "fk_attribute" => "id");

            $baseFkList[] = Array("table" => "list_view", "fk_table" => "attribute_set", "column" => "attribute_set", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "list_view", "fk_table" => "entity_type", "column" => "entity_type", "fk_attribute" => "id");

            $baseFkList[] = Array("table" => "list_view_attribute", "fk_table" => "attribute", "column" => "attribute_id", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "list_view_attribute", "fk_table" => "list_view", "column" => "list_view_id", "fk_attribute" => "id");

            $baseFkList[] = Array("table" => "page", "fk_table" => "attribute_set", "column" => "attribute_set", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "page", "fk_table" => "entity_type", "column" => "entity_type", "fk_attribute" => "id");

            $baseFkList[] = Array("table" => "page_block", "fk_table" => "attribute_set", "column" => "attribute_set", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "page_block", "fk_table" => "entity_type", "column" => "entity_type", "fk_attribute" => "id");

            $baseFkList[] = Array("table" => "privilege", "fk_table" => "role_entity", "column" => "role", "fk_attribute" => "id");

            $baseFkList[] = Array("table" => "navigation_link", "fk_table" => "page", "column" => "page", "fk_attribute" => "id");
            $baseFkList[] = Array("table" => "navigation_link", "fk_table" => "navigation_link", "column" => "parent_id", "fk_attribute" => "id");



            foreach ($baseFkList as $baseFk){

                $fk_name = md5("{$baseFk["table"]}_{$baseFk["fk_table"]}_{$baseFk["column"]}");

                dump("Building FK {$baseFk["table"]}.{$baseFk["column"]} -> {$baseFk["fk_table"]}.id");

                $q = "ALTER TABLE {$baseFk["table"]} ADD CONSTRAINT `{$fk_name}` FOREIGN KEY (`{$baseFk["column"]}`) REFERENCES `{$baseFk["fk_table"]}` (`{$baseFk["fk_attribute"]}`) ON DELETE CASCADE ON UPDATE CASCADE;";

                try{
                    $databaseContext->executeNonQuery($q);
                }
                catch (\Exception $e){
                    if($baseFk["table"] == "entity_log"){
                        $q = "TRUNCATE TABLE entity_log";
                        $databaseContext->executeNonQuery($q);
                    }
                    dump($q);
                    dump($e->getMessage());
                }
            }

            return true;
        }
        elseif ($func == "insert_default_codebooks") {

            if(empty($this->entityManager)){
                $this->entityManager = $this->getContainer()->get("entity_manager");
            }

            if(empty($this->databaseContext)){
                $this->databaseContext = $this->getContainer()->get("database_context");
            }

            /**
             * Codebook array
             */
            $codebookArray = Array(
                "order_return_state" => Array(
                    1 => Array("name" => "Novo"),
                    2 => Array("name" => "U obradi"),
                    3 => Array("name" => "Potvrđeno"),
                    4 => Array("name" => "Odbijeno"),
                    5 => Array("name" => "Povrat izvršen"),
                    6 => Array("name" => "Otkazan")
                ),
                "payment_transaction_log_type" => Array(
                    1 => Array("name" => "Prepare"),
                    2 => Array("name" => "Request"),
                    3 => Array("name" => "Response")
                ),
                "wiki_redirect_type" => Array(
                    1 => Array("name" => "301", "uid" => "c4ca4238a0b923820dcc509a6f75849b"),
                    2 => Array("name" => "404", "uid" => "c81e728d9d4c2f636f067f89cc14862c")
                ),
                "webform_field_type" => Array(
                    1 => Array("name" => "Checkbox", "field_type_code" => "checkbox"),
                    2 => Array("name" => "Text field", "field_type_code" => "text_field"),
                    3 => Array("name" => "Radio", "field_type_code" => "radio"),
                    4 => Array("name" => "E-mail", "field_type_code" => "e_mail"),
                    5 => Array("name" => "Select", "field_type_code" => "select"),
                    6 => Array("name" => "File", "field_type_code" => "file"),
                    7 => Array("name" => "Date time", "field_type_code" => "datetime"),
                    8 => Array("name" => "Autocomplete", "field_type_code" => "autocomplete"),
                    9 => Array("name" => "HTML", "field_type_code" => "html"),
                    10 => Array("name" => "Textarea", "field_type_code" => "textarea"),
                    11 => Array("name" => "Date", "field_type_code" => "date")
                ),
                "s_redirect_type" => Array(
                    1 => Array("name" => "301", "uid" => "c4ca4238a0b923820dcc509a6f75849b"),
                    2 => Array("name" => "404", "uid" => "c81e728d9d4c2f636f067f89cc14862c")
                ),
                "s_menu_item_type" => Array(
                    1 => Array("name" => "No link", "uid" => "c4ca4238a0b923820dcc509a6f75849b"),
                    2 => Array("name" => "Page", "uid" => "c81e728d9d4c2f636f067f89cc14862c"),
                    3 => Array("name" => "Category", "uid" => "eccbc87e4b5ce2fe28308fd9f2a7baf3"),
                    4 => Array("name" => "Custom url", "uid" => "a87ff679a2f3e71d9181a67b7542122c"),
                    5 => Array("name" => "Blog category", "uid" => "e4da3b7fbbce2345d7772b0674a318d5"),
                    6 => Array("name" => "Brand", "uid" => "e0f1455552534e7c8a5a1c15628cfb1f"),
                    7 => Array("name" => "Warehouse", "uid" => "e483fbf291404ace86a9ab4fd9826967"),
                ),
                "product_type" => Array(
                    1 => Array("name" => "Simple", "uid" => "c4ca4238a0b923820dcc509a6f75849b"),
                    2 => Array("name" => "Configurable", "uid" => "c81e728d9d4c2f636f067f89cc14862c"),
                    3 => Array("name" => "Bundle", "uid" => "eccbc87e4b5ce2fe28308fd9f2a7baf3"),
                    4 => Array("name" => "Bundle wand", "uid" => "a87ff679a2f3e71d9181a67b7542122c"),
                    6 => Array("name" => "Configurable bundle", "uid" => "1679091c5a880faf6fb5e6087eb1b2dc"),
                ),
                "marketing_rule_type" => Array(
                    1 => Array("name" => "Marketing rule per product", "manager_code" => "automations_manager", "method" => "marketingDefaultAutomation"),
                    2 => Array("name" => "Marketing rule per cart", "manager_code" => "automations_manager", "method" => "marketingDefaultAutomation")
                ),
                "marketing_rule_group" => Array(
                    1 => Array("name" => "Group1"),
                    2 => Array("name" => "Group2")
                ),
                "import_manual_type" => Array(
                    1 => Array("name" => "Import simple proizvoda", "manager_code" => "default_import_manager", "method" => "importSimpleProducts","estimated_duration" => 5),
                    2 => Array("name" => "Import konfigurabilnih proizvoda", "manager_code" => "default_import_manager", "method" => "importConfigurableProducts","estimated_duration" => 5),
                    3 => Array("name" => "Import računa i kontakata", "manager_code" => "default_import_manager", "method" => "importAccountsContactsUsers","estimated_duration" => 5)
                ),
                "import_manual_status" => Array(
                    1 => Array("name" => "Waiting in queue"),
                    2 => Array("name" => "In progress"),
                    3 => Array("name" => "Success"),
                    4 => Array("name" => "Failed")
                ),
                "discount_coupon_application_type" => Array(
                    1 => Array("name" => "Primjeni veći popust", "uid" => "c4ca4238a0b923820dcc509a6f75849b"),
                    2 => Array("name" => "Primjeni na cijenu sa popustom", "uid" => "c81e728d9d4c2f636f067f89cc14862c"),
                    3 => Array("name" => "Primjeni na osnovnu cijenu", "uid" => "eccbc87e4b5ce2fe28308fd9f2a7baf3"),
                ),
                "bulk_price_display_type" => Array(
                    1 => Array("name" => "Popust na količinu"),
                    2 => Array("name" => "Kupi X dobiješ 1 besplatno")
                ),
            );

            foreach ($codebookArray as $entityType => $values){

                try{
                    $q = "SELECT * FROM {$entityType}_entity";
                    $data = $this->databaseContext->getAll($q);

                    if(!empty($data)){
                        foreach ($data as $d){
                            if(isset($values[$d["id"]])){
                                unset($values[$d["id"]]);
                            }
                        }
                    }

                    $insertQueryValues = Array();

                    if(!empty($values)){

                        /** @var AttributeSet $attributeSet */
                        $attributeSet = $this->entityManager->getAttributeSetByCode($entityType);

                        /**
                         * This entity does not exist
                         */
                        if(empty($attributeSet)){
                            continue;
                        }

                        $keys = array_keys(end($values));
                        $insertQuery = "INSERT IGNORE INTO {$entityType}_entity (id, entity_type_id, attribute_set_id, created, modified,created_by, modified_by, entity_state_id, ".implode(",",$keys).") VALUES ";

                        foreach ($values as $id => $value){
                            $insertQueryValues[] = "({$id},'{$attributeSet->getEntityTypeId()}','{$attributeSet->getId()}',NOW(),NOW(),'system','system','1','".implode("','",$value)."')";
                        }
                    }

                    if(!empty($insertQueryValues)){
                        $this->databaseContext->executeNonQuery($insertQuery.implode(",",$insertQueryValues));
                        print "Codebook values added: {$entityType}\r\n";
                    }
                }
                catch (\Exception $e){
                    //TODO nothing, samo da ne puca kod entiteta ako ne postoje
                }
            }

            return true;
        }
        else{
            throw new \Exception("Command type missing: ".json_encode($input->getArguments()));
        }

        return false;
    }
}
