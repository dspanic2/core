<?php


function getConfig()
{

    $parameters_loc = "../../.env";
    $delimiter = "=";
    $conf = array();

    if (!file_exists($parameters_loc)) {
        $parameters_loc = "app/config/parameters.yml";
        $delimiter = ":";
    }

    $parameters = file($parameters_loc);

    foreach ($parameters as $p) {
        if (stripos($p, "database_name") !== false) {
            $p = explode($delimiter, $p);
            $conf["database_name"] = trim($p[1]);
            continue;
        } elseif (stripos($p, "database_user") !== false) {
            $p = explode($delimiter, $p);
            $conf["database_user"] = trim($p[1]);
            continue;
        } elseif (stripos($p, "database_password") !== false) {
            $p = explode($delimiter, $p);
            $conf["database_password"] = trim(trim($p[1]), "'");
            continue;
        } elseif (stripos($p, "database_host") !== false) {
            $p = explode($delimiter, $p);
            $conf["database_host"] = trim($p[1]);
            continue;
        }
    }

    return $conf;
}

/**
 * @param $config
 * @return mysqli
 */
function dbConnect()
{

    $config = getConfig();

    $db = new mysqli($config['database_host'], $config['database_user'], $config['database_password'], $config['database_name']);
    $db->set_charset("utf8");

    return $db;
}

function dbClose($db)
{

    $db->close();

    return false;
}

function update()
{

    $db = dbConnect();

    /** UPDATE 10.03.2022 hrvoje@shipshape-solutions.com -> hrvoje.rukavina@shipshape-solutions.com */
    $q = "UPDATE user_entity SET email = 'hrvoje.rukavina@shipshape-solutions.com', email_canonical = 'hrvoje.rukavina@shipshape-solutions.com' WHERE email = 'hrvoje@shipshape-solutions.com'; UPDATE contact_entity SET email = 'hrvoje.rukavina@shipshape-solutions.com' WHERE  email = 'hrvoje@shipshape-solutions.com'; UPDATE account_entity SET email = 'hrvoje.rukavina@shipshape-solutions.com' WHERE email = 'hrvoje@shipshape-solutions.com';";
    if (!empty($q)) {
        $db->multi_query($q);
    }

    dbClose($db);

    $db = dbConnect();

    /** UPDATE 01.01.2021 Add enable_inline_editing to list_view_attribute */
    $q = addColumnQuery("list_view_attribute", "enable_inline_editing", "tinyint(1) default '0'");
    if (!empty($q)) {
        $db->multi_query($q);
    }

    dbClose($db);

    $db = dbConnect();

    /** UPDATE 01.01.2022 Add uid_attribute_code to entity_type */
    $q = addColumnQuery("entity_type", "uid_attribute_code", "varchar(255)");
    if (!empty($q)) {
        $db->multi_query($q);
    }

    dbClose($db);

    $db = dbConnect();

    /** UPDATE 10.02.2022 Add note to list_view */
    $q = addColumnQuery("list_view", "note", "text");
    if (!empty($q)) {
        $db->multi_query($q);
    }

    dbClose($db);

    $db = dbConnect();

    /** UPDATE 10.02.2022 Add note to attribute_group */
    $q = addColumnQuery("attribute_group", "note", "text");
    if (!empty($q)) {
        $db->multi_query($q);
    }

    dbClose($db);

    $db = dbConnect();

    /** UPDATE 01.01.2021 Add column_width to list_view_attribute */
    $q = addColumnQuery("list_view_attribute", "column_width", "varchar(255)");
    if (!empty($q)) {
        $db->multi_query($q);
    }

    dbClose($db);

    /** UPDATE 06.01.2021 Add is_view to entity_type */

    $db = dbConnect();

    $q = addColumnQuery("entity_type", "is_view", "tinyint(1) NOT NULL default '0'");
    if (!empty($q)) {
        $db->multi_query($q);
    }

    dbClose($db);

    /** UPDATE 09.01.2021 Add prefix and suffix to entity_type */

    $db = dbConnect();
    $q = addColumnQuery("attribute", "prefix", "varchar(255)");
    if (!empty($q)) {
        $db->multi_query($q);
    }
    dbClose($db);

    $db = dbConnect();
    $q = addColumnQuery("attribute", "sufix", "varchar(255)");
    if (!empty($q)) {
        $db->multi_query($q);
    }
    dbClose($db);

    /**
     * Increase settings_entity column settings_value
     */
    $db = dbConnect();
    $q = "ALTER TABLE settings_entity MODIFY settings_value json;";
    $db->query($q);
    dbClose($db);

    // Add is_custom columns
    $tables = [
        "attribute",
        "attribute_group",
        "attribute_set",
        "entity_type",
        "list_view",
        "list_view_attribute",
        "navigation_link",
        "page",
        "page_block"
    ];

    if (!empty($tables)) {
        foreach ($tables as $table) {
            $db = dbConnect();
            $q = addColumnQuery($table, "is_custom", "tinyint(1) NOT NULL default '0'");
            if (!empty($q)) {
                $db->multi_query($q);
            }
            dbClose($db);
        }
    }

    $db = dbConnect();
    $q = addColumnQuery("list_view", "inline_editing", "tinyint(1) NOT NULL default '0'");
    if (!empty($q)) {
        $db->multi_query($q);
    }
    dbClose($db);

    // Add sync_content columns
    $tables = [
        "entity_type",
    ];

    if (!empty($tables)) {
        foreach ($tables as $table) {
            $db = dbConnect();
            $q = addColumnQuery($table, "sync_content", "tinyint(1)");
            if (!empty($q)) {
                $db->multi_query($q);
            }
            dbClose($db);
        }
    }
    $db = dbConnect();
    $q = addColumnQuery("role_entity", "uid", "varchar(255)");
    if (!empty($q)) {
        $db->multi_query($q);
    }
    dbClose($db);

    $db = dbConnect();
    $q = addColumnQuery("core_language_entity", "uid", "varchar(255)");
    if (!empty($q)) {
        $db->multi_query($q);
    }
    dbClose($db);

    $db = dbConnect();
    $q = addColumnQuery("region_entity", "uid", "varchar(255)");
    if (!empty($q)) {
        $db->multi_query($q);
    }
    dbClose($db);

    $db = dbConnect();
    $q = addColumnQuery("country_entity", "uid", "varchar(255)");
    if (!empty($q)) {
        $db->multi_query($q);
    }
    dbClose($db);

    transferPrivilegeActionCodeToUID();

    $db = dbConnect();
    $q = "DELETE FROM page_block WHERE bundle = 'WorkflowBusinessBundle';";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DROP TABLE IF EXISTS entity_history;";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DROP TABLE IF EXISTS entity;";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "UPDATE role_entity SET default_for_attribute_set = 0, default_for_list_view = 0, default_for_page = 0, default_for_page_block = 0 WHERE role_code = 'ROLE_CUSTOMER';";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DELETE FROM privilege WHERE role = (SELECT id FROM role_entity WHERE role_code = 'ROLE_CUSTOMER');";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE TABLE IF NOT EXISTS `product_account_price_staging` (
      `product_id` int(10) unsigned NOT NULL,
      `account_id` int(10) unsigned NOT NULL,
      `price_base` decimal(12,4) DEFAULT NULL,
      `rebate` decimal(12,4) DEFAULT NULL,
      `type` int(11) DEFAULT NULL,
      `date_valid_from` datetime DEFAULT NULL,
      `date_valid_to` datetime DEFAULT NULL,
      `to_delete` tinyint(1) NOT NULL default '0',
      PRIMARY KEY (`product_id`,`account_id`),
      KEY `product_account_id` (`product_id`,`account_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE TABLE IF NOT EXISTS `product_account_group_price_staging` (
      `product_id` int(10) unsigned NOT NULL,
      `account_group_id` int(10) unsigned NOT NULL,
      `price_base` decimal(12,4) DEFAULT NULL,
      `rebate` decimal(12,4) DEFAULT NULL,
      `type` int(11) DEFAULT NULL,
      `date_valid_from` datetime DEFAULT NULL,
      `date_valid_to` datetime DEFAULT NULL,
      `to_delete` tinyint(1) NOT NULL default '0',
      PRIMARY KEY (`product_id`,`account_group_id`),
      KEY `product_account_id` (`product_id`,`account_group_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = addColumnQuery("product_account_price_staging", "to_delete", "tinyint(1) NOT NULL default '0'");
    if (!empty($q)) {
        $db->multi_query($q);
    }
    dbClose($db);

    $db = dbConnect();
    $q = addColumnQuery("product_account_group_price_staging", "to_delete", "tinyint(1) NOT NULL default '0'");
    if (!empty($q)) {
        $db->multi_query($q);
    }
    dbClose($db);

    $db = dbConnect();
    $q = "DROP PROCEDURE IF EXISTS sp_import_partner_rabats;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
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
    SELECT 490,334,NOW(),NOW(),'import',1,
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
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DROP PROCEDURE IF EXISTS sp_import_account_groups_rabats;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
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
    SELECT 538,383,NOW(),NOW(),'import',1,
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
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE TABLE IF NOT EXISTS `shape_track` (
      `url` varchar(255) NOT NULL,
      `full_url` varchar(255) NOT NULL,
      `page_id` int(11) DEFAULT NULL,
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
      `user_id` int(11) DEFAULT NULL,
      `session_id` varchar(50) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE TABLE IF NOT EXISTS `shape_track_date_dim` (
      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `fulldate` date DEFAULT NULL,
      `dayofmonth` int(11) DEFAULT NULL,
      `dayofyear` int(11) DEFAULT NULL,
      `dayofweek` int(11) DEFAULT NULL,
      `dayname` varchar(255) DEFAULT NULL,
      `monthnumber` int(11) DEFAULT NULL,
      `monthname` varchar(255) DEFAULT NULL,
      `year` int(11) DEFAULT NULL,
      `quarter` int(11) DEFAULT NULL,
      `quarterid` int(11) DEFAULT NULL,
      `week` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE TABLE IF NOT EXISTS `shape_track_order_item_fact` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `order_item_id` int(11) DEFAULT NULL,
      `order_id` int(11) NOT NULL,
      `date_dim_id` int(11) unsigned NOT NULL,
      `product_id` int(11) DEFAULT NULL,
      `name` varchar(255) DEFAULT NULL,
      `qty` int(11) DEFAULT NULL,
      `base_price_total` decimal(14,2) DEFAULT NULL,
      `base_price_discount_total` decimal(14,2) DEFAULT NULL,
      `base_price_tax` decimal(14,2) DEFAULT NULL,
      `base_price_item_tax` decimal(14,2) DEFAULT NULL,
      `order_state_id` int(11) DEFAULT NULL,
      `store_id` int(11) DEFAULT NULL,
      `website_id` int(11) DEFAULT NULL,
      `account_id` int(11) DEFAULT NULL,
      `contact_id` int(11) DEFAULT NULL,
      `core_user_id` int(11) DEFAULT NULL,
      `account_group_id` int(11) DEFAULT NULL,
      `is_legal_entity` tinyint(1) DEFAULT '0',
      `city_id` int(11) DEFAULT NULL,
      `country_id` int(11) DEFAULT NULL,
      `product_groups` varchar(255) DEFAULT NULL,
      `modified` datetime DEFAULT NULL,
      `base_price_item` decimal(14,2) DEFAULT NULL,
      `payment_type_id` int(11) DEFAULT NULL,
      `delivery_type_id` int(11) DEFAULT NULL,
      `order_base_price_total` decimal(14,2) DEFAULT NULL,
      `order_base_price_tax` decimal(14,2) DEFAULT NULL,
      `order_base_price_items_total` decimal(14,2) DEFAULT NULL,
      `order_base_price_items_tax` decimal(14,2) DEFAULT NULL,
      `order_base_price_delivery_total` decimal(14,2) DEFAULT NULL,
      `order_base_price_delivery_tax` decimal(14,2) DEFAULT NULL,
      `currency_id` int(11) DEFAULT NULL,
      `currency_rate` decimal(14,2) DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `order_item_fact_order_item_id` (`order_item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE TABLE IF NOT EXISTS `shape_track_product_dim` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `product_id` int(11) DEFAULT NULL,
      `date_dim_id` int(11) DEFAULT NULL,
      `quoted` tinyint(5) DEFAULT NULL,
      `ordered` tinyint(5) DEFAULT NULL,
      `canceled` tinyint(5) DEFAULT NULL,
      `returned` tinyint(5) DEFAULT NULL,
      `requests` tinyint(5) DEFAULT NULL,
      `reviewed` tinyint(5) DEFAULT NULL,
      `visited` tinyint(5) DEFAULT NULL,
      `ctr` decimal(5,2) DEFAULT NULL,
      `store_id` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE TABLE IF NOT EXISTS `shape_track_product_group_fact` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `date_dim_id` int(11) unsigned NOT NULL,
      `product_group_id` int(11) DEFAULT NULL,
      `product_group_name` varchar(255) DEFAULT NULL,
      `product_group_level` varchar(255) DEFAULT NULL,
      `product_group_number_of_products` int(11) DEFAULT NULL,
      `visits` int(11) DEFAULT NULL,
      `add_to_cart` int(11) DEFAULT NULL,
      `qty_sold` int(11) DEFAULT NULL,
      `qty_canceled` int(11) DEFAULT NULL,
      `order_total_amount` decimal(14,2) DEFAULT NULL,
      `order_success_amount` decimal(14,2) DEFAULT NULL,
      `order_canceled_amount` decimal(14,2) DEFAULT NULL,
      `order_in_process_amount` decimal(14,2) DEFAULT NULL,
      `store_id` int(11) DEFAULT NULL,
      `website_id` int(11) DEFAULT NULL,
      `performace_rate_visits` decimal(14,2) DEFAULT NULL,
      `modified` datetime DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE TABLE IF NOT EXISTS `shape_track_product_impressions_transaction` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `product_id` int(11) DEFAULT NULL,
      `session_id` varchar(255) DEFAULT NULL,
      `email` varchar(255) DEFAULT NULL,
      `first_name` varchar(255) DEFAULT NULL,
      `last_name` varchar(255) DEFAULT NULL,
      `contact_id` int(11) DEFAULT NULL,
      `event_type` varchar(255) DEFAULT NULL,
      `event_name` varchar(255) DEFAULT NULL,
      `previous` varchar(255) DEFAULT NULL,
      `store_id` int(11) DEFAULT NULL,
      `created` datetime DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=945 DEFAULT CHARSET=utf8mb4;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE TABLE IF NOT EXISTS `shape_track_totals_fact` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `date_dim_id` int(11) unsigned DEFAULT NULL,
      `order_total_count` int(11) DEFAULT '0',
      `order_total_amount` decimal(14,2) DEFAULT '0.00',
      `order_success_count` int(11) DEFAULT '0',
      `order_success_amount` decimal(14,2) DEFAULT '0.00',
      `order_canceled_count` int(11) DEFAULT '0',
      `order_canceled_amount` decimal(14,2) DEFAULT '0.00',
      `order_in_process_count` int(11) DEFAULT '0',
      `order_in_process_amount` decimal(14,2) DEFAULT '0.00',
      `quote_total_count` int(11) DEFAULT '0',
      `quote_total_amount` decimal(14,2) DEFAULT '0.00',
      `quote_to_order_rate_count` decimal(14,2) DEFAULT '0.00',
      `quote_to_order_rate_amount` decimal(14,2) DEFAULT '0.00',
      `store_id` int(11) DEFAULT NULL,
      `website_id` int(11) DEFAULT NULL,
      `modified` datetime DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `shape_track_date_dim_id` (`date_dim_id`),
      KEY `shape_track_store_id` (`store_id`) USING BTREE,
      KEY `shape_track_website_id` (`website_id`) USING BTREE,
      CONSTRAINT `shape_track_totals_fact_ibfk_1` FOREIGN KEY (`date_dim_id`) REFERENCES `shape_track_date_dim` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "UPDATE entity_type 
        SET is_view = 1 
        WHERE entity_type_code = 'account_sales_per_month' 
        OR entity_type_code = 'product_sales_per_month';";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DROP TABLE IF EXISTS account_sales_per_month_entity;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE OR REPLACE VIEW account_sales_per_month_entity AS select i.account_id AS id,a.name AS name,a.entity_type_id AS entity_type_id,a.attribute_set_id AS attribute_set_id,a.modified AS modified,a.created AS created,a.entity_state_id AS entity_state_id,a.industry_type_id AS industry_type_id,a.phone AS phone,a.created_by AS created_by,a.modified_by AS modified_by,a.version AS version,a.min_version AS min_version,a.locked AS locked,a.locked_by AS locked_by,a.is_legal_entity AS is_legal_entity,a.rating AS rating,a.account_group_id AS account_group_id,a.is_active AS is_active,a.owner_id AS owner_id,sum(if((i.ord = 1),i.total,0)) AS month_12,sum(if((i.ord = 1),i.diff,0)) AS month_12_diff,sum(if((i.ord = 2),i.total,0)) AS month_11,sum(if((i.ord = 2),i.diff,0)) AS month_11_diff,sum(if((i.ord = 3),i.total,0)) AS month_10,sum(if((i.ord = 3),i.diff,0)) AS month_10_diff,sum(if((i.ord = 4),i.total,0)) AS month_9,sum(if((i.ord = 4),i.diff,0)) AS month_9_diff,sum(if((i.ord = 5),i.total,0)) AS month_8,sum(if((i.ord = 5),i.diff,0)) AS month_8_diff,sum(if((i.ord = 6),i.total,0)) AS month_7,sum(if((i.ord = 6),i.diff,0)) AS month_7_diff,sum(if((i.ord = 7),i.total,0)) AS month_6,sum(if((i.ord = 7),i.diff,0)) AS month_6_diff,sum(if((i.ord = 8),i.total,0)) AS month_5,sum(if((i.ord = 8),i.diff,0)) AS month_5_diff,sum(if((i.ord = 9),i.total,0)) AS month_4,sum(if((i.ord = 9),i.diff,0)) AS month_4_diff,sum(if((i.ord = 10),i.total,0)) AS month_3,sum(if((i.ord = 10),i.diff,0)) AS month_3_diff,sum(if((i.ord = 11),i.total,0)) AS month_2,sum(if((i.ord = 11),i.diff,0)) AS month_2_diff,sum(if((i.ord = 12),i.total,0)) AS month_1,sum(if((i.ord = 12),i.diff,0)) AS month_1_diff from (((select b.account_id AS account_id,calendar.month_name AS month_name,calendar.month_id AS month_id,b.total AS total,b.dyear AS dyear,(case when (b.last_year_total > 0) then b.last_year_total else 0 end) AS last_year_total,(case when isnull(b.diff) then 200 else b.diff end) AS diff,b.is_legal_entity AS is_legal_entity,if(((calendar.month_id - month(curdate())) <= 0),(12 - abs((calendar.month_id - month(curdate())))),(calendar.month_id - month(curdate()))) AS ord from (((select 1 AS month_id,'Jan' AS month_name) union select 2 AS month_id,'Feb' AS month_name union select 3 AS month_id,'Mar' AS month_name union select 4 AS month_id,'Apr' AS month_name union select 5 AS month_id,'Mays' AS month_name union select 6 AS month_id,'Jun' AS month_name union select 7 AS month_id,'Jul' AS month_name union select 8 AS month_id,'Aug' AS month_name union select 9 AS month_id,'Sep' AS month_name union select 10 AS month_id,'Oct' AS month_name union select 11 AS month_id,'Nov' AS month_name union select 12 AS month_id,'Dec' AS month_name) calendar left join (select (case when (oif.is_legal_entity = 0) then 'B2C' when (oif.is_legal_entity = 1) then 'B2B' end) AS customer_type,oif.account_id AS account_id,oif.is_legal_entity AS is_legal_entity,dd.monthnumber AS month_id,dd.monthname AS month_name,dd.year AS dyear,sum(oif.base_price_total) AS total,last_year.total AS last_year_total,((if((last_year.total = 0),sum(oif.base_price_total),(sum(oif.base_price_total) - last_year.total)) / (if((last_year.total = 0),sum(oif.base_price_total),(sum(oif.base_price_total) + last_year.total)) / 2)) * 100) AS diff from ((shape_track_order_item_fact oif join shape_track_date_dim dd on((oif.date_dim_id = dd.id))) left join (select (case when (oif.is_legal_entity = 0) then 'B2C' when (oif.is_legal_entity = 1) then 'B2B' end) AS customer_type,oif.is_legal_entity AS is_legal_entity,oif.account_id AS account_id,dd.monthnumber AS month_id,dd.monthname AS month_name,dd.year AS dyear,sum(oif.base_price_total) AS total from (shape_track_order_item_fact oif join shape_track_date_dim dd on((oif.date_dim_id = dd.id))) where ((oif.account_id is not null) and (dd.fulldate <= (curdate() - interval 1 year)) and (dd.fulldate > last_day((curdate() - interval 2 year)))) group by dd.year,oif.account_id,dd.monthnumber) last_year on(((dd.monthnumber = last_year.month_id) and (oif.account_id = last_year.account_id)))) where ((oif.account_id is not null) and (dd.fulldate <= curdate()) and (dd.fulldate > last_day((curdate() - interval 1 year)))) group by dd.year,oif.account_id,dd.monthnumber) b on((calendar.month_id = b.month_id))) order by ord,b.total desc)) i left join account_entity a on((i.account_id = a.id))) where (i.account_id is not null) group by i.account_id;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DROP TABLE IF EXISTS product_sales_per_month_entity;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE OR REPLACE VIEW product_sales_per_month_entity AS select i.product_id AS id,a.name AS name,a.entity_type_id AS entity_type_id,a.attribute_set_id AS attribute_set_id,a.modified AS modified,a.created AS created,a.entity_state_id AS entity_state_id,a.created_by AS created_by,a.modified_by AS modified_by,a.version AS version,a.min_version AS min_version,a.locked AS locked,a.locked_by AS locked_by,a.code AS code,a.ean AS ean,a.is_saleable AS is_saleable,a.remote_id AS remote_id,a.remote_source AS remote_source,sum(if((i.ord = 1),i.total,0)) AS month_12,sum(if((i.ord = 1),i.diff,0)) AS month_12_diff,sum(if((i.ord = 2),i.total,0)) AS month_11,sum(if((i.ord = 2),i.diff,0)) AS month_11_diff,sum(if((i.ord = 3),i.total,0)) AS month_10,sum(if((i.ord = 3),i.diff,0)) AS month_10_diff,sum(if((i.ord = 4),i.total,0)) AS month_9,sum(if((i.ord = 4),i.diff,0)) AS month_9_diff,sum(if((i.ord = 5),i.total,0)) AS month_8,sum(if((i.ord = 5),i.diff,0)) AS month_8_diff,sum(if((i.ord = 6),i.total,0)) AS month_7,sum(if((i.ord = 6),i.diff,0)) AS month_7_diff,sum(if((i.ord = 7),i.total,0)) AS month_6,sum(if((i.ord = 7),i.diff,0)) AS month_6_diff,sum(if((i.ord = 8),i.total,0)) AS month_5,sum(if((i.ord = 8),i.diff,0)) AS month_5_diff,sum(if((i.ord = 9),i.total,0)) AS month_4,sum(if((i.ord = 9),i.diff,0)) AS month_4_diff,sum(if((i.ord = 10),i.total,0)) AS month_3,sum(if((i.ord = 10),i.diff,0)) AS month_3_diff,sum(if((i.ord = 11),i.total,0)) AS month_2,sum(if((i.ord = 11),i.diff,0)) AS month_2_diff,sum(if((i.ord = 12),i.total,0)) AS month_1,sum(if((i.ord = 12),i.diff,0)) AS month_1_diff from (((select b.product_id AS product_id,calendar.month_name AS month_name,calendar.month_id AS month_id,b.total AS total,b.dyear AS dyear,(case when (b.last_year_total > 0) then b.last_year_total else 0 end) AS last_year_total,(case when isnull(b.diff) then 200 else b.diff end) AS diff,b.is_legal_entity AS is_legal_entity,if(((calendar.month_id - month(curdate())) <= 0),(12 - abs((calendar.month_id - month(curdate())))),(calendar.month_id - month(curdate()))) AS ord from (((select 1 AS month_id,'Jan' AS month_name) union select 2 AS month_id,'Feb' AS month_name union select 3 AS month_id,'Mar' AS month_name union select 4 AS month_id,'Apr' AS month_name union select 5 AS month_id,'Mays' AS month_name union select 6 AS month_id,'Jun' AS month_name union select 7 AS month_id,'Jul' AS month_name union select 8 AS month_id,'Aug' AS month_name union select 9 AS month_id,'Sep' AS month_name union select 10 AS month_id,'Oct' AS month_name union select 11 AS month_id,'Nov' AS month_name union select 12 AS month_id,'Dec' AS month_name) calendar left join (select (case when (oif.is_legal_entity = 0) then 'B2C' when (oif.is_legal_entity = 1) then 'B2B' end) AS customer_type,oif.product_id AS product_id,oif.is_legal_entity AS is_legal_entity,dd.monthnumber AS month_id,dd.monthname AS month_name,dd.year AS dyear,sum(oif.base_price_total) AS total,last_year.total AS last_year_total,((if((last_year.total = 0),sum(oif.base_price_total),(sum(oif.base_price_total) - last_year.total)) / (if((last_year.total = 0),sum(oif.base_price_total),(sum(oif.base_price_total) + last_year.total)) / 2)) * 100) AS diff from ((shape_track_order_item_fact oif join shape_track_date_dim dd on((oif.date_dim_id = dd.id))) left join (select (case when (oif.is_legal_entity = 0) then 'B2C' when (oif.is_legal_entity = 1) then 'B2B' end) AS customer_type,oif.is_legal_entity AS is_legal_entity,oif.product_id AS product_id,dd.monthnumber AS month_id,dd.monthname AS month_name,dd.year AS dyear,sum(oif.base_price_total) AS total from (shape_track_order_item_fact oif join shape_track_date_dim dd on((oif.date_dim_id = dd.id))) where ((oif.product_id is not null) and (dd.fulldate <= (curdate() - interval 1 year)) and (dd.fulldate > last_day((curdate() - interval 2 year)))) group by dd.year,oif.product_id,dd.monthnumber) last_year on(((dd.monthnumber = last_year.month_id) and (oif.product_id = last_year.product_id)))) where ((oif.product_id is not null) and (dd.fulldate <= curdate()) and (dd.fulldate > last_day((curdate() - interval 1 year)))) group by dd.year,oif.product_id,dd.monthnumber) b on((calendar.month_id = b.month_id))) order by ord,b.total desc)) i left join product_entity a on((i.product_id = a.id))) where (i.product_id is not null) group by i.product_id;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE OR REPLACE VIEW account_company_register_differences_entity AS select (select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'account_company_register_differences')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'account_company_register_differences')) AS `attribute_set_id`,NULL AS `created`,NULL AS `modified`,NULL AS `created_by`,NULL AS `modified_by`,NULL AS `locked`,NULL AS `locked_by`,NULL AS `version`,NULL AS `min_version`,1 AS `entity_state_id`,`address`.`id` AS `id`,`account`.`id` AS `account_id`,`account`.`name` AS `account_name`,`address`.`name` AS `address_name`,`company_register`.`name_short` AS `company_register_name`,`account`.`oib` AS `account_oib`,`company_register`.`identification_number` AS `company_register_identification_number`,`account`.`email` AS `account_email`,`address`.`email` AS `address_email`,`company_register`.`email_address` AS `company_register_email`,`address`.`street` AS `account_street`,`company_register`.`headquarters_street` AS `company_register_street`,`city`.`name` AS `account_city`,`company_register`.`headquarters_city_name` AS `company_register_city`,`city`.`id` AS `city_id`,`address`.`id` AS `address_id` from (((`company_register_entity` `company_register` join `account_entity` `account` on((`company_register`.`identification_number` = `account`.`oib`))) join `address_entity` `address` on((`address`.`account_id` = `account`.`id`))) join `city_entity` `city` on((`address`.`city_id` = `city`.`id`))) where ((`address`.`headquarters` = 1) and ((lower(`account`.`name`) <> lower(`company_register`.`name_short`)) or (lower(`address`.`street`) <> lower(`company_register`.`headquarters_street`)) or (lower(`city`.`name`) <> lower(`company_register`.`headquarters_city_name`))));";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DROP TABLE IF EXISTS available_configurable_products_entity;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE OR REPLACE VIEW available_configurable_products_entity AS select `p`.`id` AS `id`,`p`.`name` AS `name`,`p`.`code` AS `code`,`p`.`ean` AS `ean`,`p`.`active` AS `active`,`p`.`is_saleable` AS `is_saleable`,`p`.`catalog_code` AS `catalog_code`,`p`.`entity_type_id` AS `entity_type_id`,`p`.`attribute_set_id` AS `attribute_set_id`,`p`.`modified` AS `modified`,`p`.`created` AS `created`,`p`.`entity_state_id` AS `entity_state_id`,`p`.`created_by` AS `created_by`,`p`.`modified_by` AS `modified_by`,`p`.`version` AS `version`,`p`.`min_version` AS `min_version`,`p`.`locked` AS `locked`,`p`.`locked_by` AS `locked_by`,`spal`.`configuration_option` AS `configuration_option`,`spal`.`s_product_attribute_configuration_id` AS `s_product_attribute_configuration_id`,`spal`.`attribute_value` AS `attribute_value`,(case when isnull(`scpl`.`id`) then 0 else 1 end) AS `used_in_configuration`,group_concat(concat(`spaco`.`name`,': ',`spal`.`attribute_value`) separator ', ') AS `attributes` from (((`s_product_attributes_link_entity` `spal` left join `product_entity` `p` on((`spal`.`product_id` = `p`.`id`))) left join `s_product_attribute_configuration_entity` `spaco` on((`spal`.`s_product_attribute_configuration_id` = `spaco`.`id`))) left join `product_configuration_product_link_entity` `scpl` on((`p`.`id` = `scpl`.`child_product_id`))) where (`p`.`product_type_id` = 1) group by `p`.`id`;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE OR REPLACE VIEW custom_list_entity AS select `p`.`id` AS `id`,`p`.`attribute_code` AS `code`,'attribute' AS `table_name`,`e`.`entity_type_code` AS `entity_type_code`,'' AS `attribute_set_code`,(select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'product')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'product')) AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,'' AS `bundle`,'' AS `version`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,`p`.`uid` AS `uid` from (`attribute` `p` left join `entity_type` `e` on((`p`.`entity_type_id` = `e`.`id`))) where (`p`.`is_custom` = 1) union select `p`.`id` AS `id`,`p`.`attribute_group_name` AS `code`,'attribute_group' AS `table_name`,'' AS `entity_type_code`,`a`.`attribute_set_code` AS `attribute_set_code`,(select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'product')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'product')) AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,'' AS `version`,'' AS `bundle`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,`p`.`uid` AS `uid` from (`attribute_group` `p` left join `attribute_set` `a` on((`p`.`attribute_set_id` = `a`.`id`))) where (`p`.`is_custom` = 1) union select `p`.`id` AS `id`,`p`.`entity_type_code` AS `code`,'entity_type' AS `table_name`,'' AS `entity_type_code`,'' AS `attribute_set_code`,(select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'product')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'product')) AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,'' AS `version`,`p`.`bundle` AS `bundle`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,`p`.`uid` AS `uid` from `entity_type` `p` where (`p`.`is_custom` = 1) union select `p`.`id` AS `id`,`p`.`attribute_set_code` AS `code`,'attribute_set' AS `table_name`,'' AS `entity_type_code`,'' AS `attribute_set_code`,(select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'product')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'product')) AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,'' AS `version`,'' AS `bundle`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,`p`.`uid` AS `uid` from `attribute_set` `p` where (`p`.`is_custom` = 1) union select `p`.`id` AS `id`,`p`.`title` AS `code`,'page' AS `table_name`,`e`.`entity_type_code` AS `entity_type_code`,`a`.`attribute_set_code` AS `attribute_set_code`,668 AS `entity_type_id`,514 AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,`p`.`bundle` AS `bundle`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,'' AS `version`,`p`.`uid` AS `uid` from ((`page` `p` left join `attribute_set` `a` on((`p`.`attribute_set` = `a`.`id`))) left join `entity_type` `e` on((`p`.`entity_type` = `e`.`id`))) where (`p`.`is_custom` = 1) union select `p`.`id` AS `id`,`p`.`title` AS `code`,'page_block' AS `table_name`,`e`.`entity_type_code` AS `entity_type_code`,`a`.`attribute_set_code` AS `attribute_set_code`,(select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'product')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'product')) AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,`p`.`bundle` AS `bundle`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,'' AS `version`,`p`.`uid` AS `uid` from ((`page_block` `p` left join `attribute_set` `a` on((`p`.`attribute_set` = `a`.`id`))) left join `entity_type` `e` on((`p`.`entity_type` = `e`.`id`))) where (`p`.`is_custom` = 1) union select `p`.`id` AS `id`,`p`.`name` AS `code`,'list_view' AS `table_name`,`e`.`entity_type_code` AS `entity_type_code`,`a`.`attribute_set_code` AS `attribute_set_code`,(select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'product')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'product')) AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,`p`.`bundle` AS `bundle`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,'' AS `version`,`p`.`uid` AS `uid` from ((`list_view` `p` left join `attribute_set` `a` on((`p`.`attribute_set` = `a`.`id`))) left join `entity_type` `e` on((`p`.`entity_type` = `e`.`id`))) where (`p`.`is_custom` = 1) union select `p`.`id` AS `id`,`p`.`display_name` AS `code`,'navigation_link' AS `table_name`,'' AS `entity_type_code`,'' AS `attribute_set_code`,(select `entity_type`.`id` from `entity_type` where (`entity_type`.`entity_type_code` = 'product')) AS `entity_type_id`,(select `attribute_set`.`id` from `attribute_set` where (`attribute_set`.`attribute_set_code` = 'product')) AS `attribute_set_id`,now() AS `modified`,now() AS `created`,1 AS `entity_state_id`,now() AS `created_by`,now() AS `modified_by`,`p`.`bundle` AS `bundle`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,'' AS `version`,`p`.`uid` AS `uid` from `navigation_link` `p` where (`p`.`is_custom` = 1);";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE OR REPLACE VIEW applied_discounts_entity AS select `s`.`id` AS `id`,`s`.`entity_type_id` AS `entity_type_id`,`s`.`attribute_set_id` AS `attribute_set_id`,`s`.`modified` AS `modified`,`s`.`created` AS `created`,`s`.`entity_state_id` AS `entity_state_id`,`s`.`created_by` AS `created_by`,`s`.`modified_by` AS `modified_by`,`s`.`version` AS `version`,`s`.`min_version` AS `min_version`,`s`.`locked` AS `locked`,`s`.`locked_by` AS `locked_by`,`s`.`product_id` AS `product_id`,`s`.`discount_price_base` AS `discount_price_base`,`s`.`discount_price_retail` AS `discount_price_retail`,`s`.`discount_percentage` AS `discount_percentage`,`s`.`type` AS `type`,`s`.`discount_name` AS `discount_name`,`s`.`date_valid_from` AS `date_valid_from`,`s`.`date_valid_to` AS `date_valid_to`,`s`.`applied_to` AS `applied_to`,`s`.`discount_type` AS `discount_type` from (select `pdc`.`id` AS `id`,`pdc`.`entity_type_id` AS `entity_type_id`,`pdc`.`attribute_set_id` AS `attribute_set_id`,`pdc`.`modified` AS `modified`,`pdc`.`created` AS `created`,`pdc`.`entity_state_id` AS `entity_state_id`,`pdc`.`created_by` AS `created_by`,`pdc`.`modified_by` AS `modified_by`,`pdc`.`version` AS `version`,`pdc`.`min_version` AS `min_version`,`pdc`.`locked` AS `locked`,`pdc`.`locked_by` AS `locked_by`,`pdc`.`product_id` AS `product_id`,`pdc`.`discount_price_base` AS `discount_price_base`,`pdc`.`discount_price_retail` AS `discount_price_retail`,`pdc`.`rebate` AS `discount_percentage`,`pdc`.`type` AS `type`,`dc`.`name` AS `discount_name`,`pdc`.`date_valid_from` AS `date_valid_from`,`pdc`.`date_valid_to` AS `date_valid_to`,'' AS `applied_to`,3 AS `discount_type` from (`product_discount_catalog_price_entity` `pdc` left join `discount_catalog_entity` `dc` on((`pdc`.`type` = `dc`.`id`))) union select `pagp`.`id` AS `id`,`pagp`.`entity_type_id` AS `entity_type_id`,`pagp`.`attribute_set_id` AS `attribute_set_id`,`pagp`.`modified` AS `modified`,`pagp`.`created` AS `created`,`pagp`.`entity_state_id` AS `entity_state_id`,`pagp`.`created_by` AS `created_by`,`pagp`.`modified_by` AS `modified_by`,`pagp`.`version` AS `version`,`pagp`.`min_version` AS `min_version`,`pagp`.`locked` AS `locked`,`pagp`.`locked_by` AS `locked_by`,`pagp`.`product_id` AS `product_id`,`pagp`.`discount_price_base` AS `discount_price_base`,`pagp`.`discount_price_retail` AS `discount_price_retail`,`pagp`.`rebate` AS `discount_percentage`,`pagp`.`type` AS `type`,`dc`.`name` AS `discount_name`,`pagp`.`date_valid_from` AS `date_valid_from`,`pagp`.`date_valid_to` AS `date_valid_to`,`ag`.`name` AS `applied_to`,2 AS `discount_type` from ((`product_account_group_price_entity` `pagp` left join `discount_catalog_entity` `dc` on((`pagp`.`type` = `dc`.`id`))) left join `account_group_entity` `ag` on((`pagp`.`account_group_id` = `ag`.`id`))) union select `pap`.`id` AS `id`,`pap`.`entity_type_id` AS `entity_type_id`,`pap`.`attribute_set_id` AS `attribute_set_id`,`pap`.`modified` AS `modified`,`pap`.`created` AS `created`,`pap`.`entity_state_id` AS `entity_state_id`,`pap`.`created_by` AS `created_by`,`pap`.`modified_by` AS `modified_by`,`pap`.`version` AS `version`,`pap`.`min_version` AS `min_version`,`pap`.`locked` AS `locked`,`pap`.`locked_by` AS `locked_by`,`pap`.`product_id` AS `product_id`,`pap`.`discount_price_base` AS `discount_price_base`,`pap`.`discount_price_retail` AS `discount_price_retail`,`pap`.`rebate` AS `discount_percentage`,`pap`.`type` AS `type`,`dc`.`name` AS `discount_name`,`pap`.`date_valid_from` AS `date_valid_from`,`pap`.`date_valid_to` AS `date_valid_to`,`a`.`name` AS `applied_to`,1 AS `discount_type` from ((`product_account_price_entity` `pap` left join `discount_catalog_entity` `dc` on((`pap`.`type` = `dc`.`id`))) left join `account_entity` `a` on((`pap`.`account_id` = `a`.`id`)))) `s` order by `s`.`discount_type` desc;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DELETE FROM user_entity WHERE username = 'bgrgic';";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "delete privilege
           from privilege
          inner join (
             select max(id) as lastId, role, action_type, action_code
               from privilege
              group by role, action_type, action_code
             having count(*) > 1) duplic on duplic.role = privilege.role AND duplic.action_type = privilege.action_type AND duplic.action_code = privilege.action_code
          where privilege.id < duplic.lastId;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE UNIQUE INDEX privilege_uq ON privilege (role, action_type, action_code);";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE employee_entity DROP FOREIGN KEY employee_entity_ibfk_7;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE employee_entity DROP FOREIGN KEY employee_entity_ibfk_5;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE employee_entity ADD CONSTRAINT employee_entity_working_hours_type FOREIGN KEY (working_hours_id) REFERENCES working_hours_type(id);";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE employee_entity DROP INDEX sex;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE employee_entity DROP sex";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE UNIQUE INDEX product_configuration_product_link_uq ON product_configuration_product_link_entity (product_id, configurable_bundle_option_id);";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "CREATE UNIQUE INDEX product_configuration_bundle_option_product_link_uq ON product_configuration_bundle_option_product_link_entity (product_id, configurable_bundle_option_id);";
    $db->query($q);
    dbClose($db);

    /**
     * removing email_attachment page_block
     */
    $db = dbConnect();
    $q = "DELETE FROM page_block WHERE uid = 'a065fe0f4ad197770c5516721ecbaeff';";
    $db->query($q);
    dbClose($db);

    /**
     * Removing invalid page_block
     */
    $db = dbConnect();
    $q = "DELETE FROM page_block WHERE uid = '953a873711db958ce1e4c677a38d3983';";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "DELETE FROM page_block WHERE uid = 'aac0d5b57b4225ecf47a93c4a86c5fba';";
    $db->query($q);
    dbClose($db);

    /**
     * Updating old uid-s with new ones
     */
    $db = dbConnect();
    $q = "UPDATE attribute SET uid = '605b5b96da2f84.88228844' WHERE uid LIKE '6058a69cb47c53.24008673';";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "UPDATE page SET uid = '605b4483469199.09277184' WHERE uid LIKE '6051d0317d4ba7.93743307';";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "UPDATE page SET uid = '605b5dab9f4a97.16756165' WHERE uid LIKE '60585a4765e804.43938707';";
    $db->query($q);
    dbClose($db);

    /**
     * Extending note column in attributes
     */
    $db = dbConnect();
    $q = "ALTER TABLE attribute MODIFY COLUMN note VARCHAR(500);";
    $db->query($q);
    dbClose($db);

    /**
     * Changing task_entity description to task_description
     */
    $db = dbConnect();
    $q = "ALTER TABLE task_entity CHANGE description task_description TEXT;";
    $db->query($q);
    dbClose($db);

    /**
     * Add missing foregin keys on product_configuration_bundle_option_product_link_entity
     */
    $db = dbConnect();
    $q = "ALTER TABLE product_configuration_bundle_option_product_link_entity ADD CONSTRAINT bundle_option_product_link_entity_option_id FOREIGN KEY (configurable_bundle_option_id) REFERENCES product_configuration_bundle_option_entity(id);";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE product_configuration_bundle_option_product_link_entity ADD CONSTRAINT bundle_option_product_link_entity_product_id FOREIGN KEY (product_id) REFERENCES product_entity(id);";
    $db->query($q);
    dbClose($db);

    /**
     * Add missing foregin keys on product_configuration_bundle_option_image_entity
     */
    $db = dbConnect();
    $q = "ALTER TABLE product_configuration_bundle_option_image_entity ADD CONSTRAINT bundle_option_image_entity_option_id FOREIGN KEY (product_configuration_bundle_option_id) REFERENCES product_configuration_bundle_option_entity(id);";
    $db->query($q);
    dbClose($db);

    /**
     * Add missing foregin keys on product_configuration_product_link_entity
     */
    $db = dbConnect();
    $q = "ALTER TABLE product_configuration_product_link_entity ADD CONSTRAINT configuration_product_link_entity_option_id FOREIGN KEY (configurable_bundle_option_id) REFERENCES product_configuration_bundle_option_entity(id);";
    $db->query($q);
    dbClose($db);

    /**
     * Add missing foregin keys on product_configurable_attribute_entity
     */
    $db = dbConnect();
    $q = "ALTER TABLE product_configurable_attribute_entity ADD CONSTRAINT configurable_attribute_entity_attribute_configuration_id FOREIGN KEY (s_product_attribute_configuration_id) REFERENCES s_product_attribute_configuration_entity(id);";
    $db->query($q);
    dbClose($db);

    /**
     * Add missing foregin keys on s_product_attribute_configuration_options_entity
     */
    $db = dbConnect();
    $q = "ALTER TABLE s_product_attribute_configuration_options_entity ADD CONSTRAINT configuration_options_entity_attribute_id FOREIGN KEY (configuration_attribute_id) REFERENCES s_product_attribute_configuration_entity(id);";
    $db->query($q);
    dbClose($db);

    /**
     * Increase product_configuration_product_link_entity column configurable_product_attributes
     */
    $db = dbConnect();
    $q = "ALTER TABLE product_configuration_product_link_entity MODIFY configurable_product_attributes VARCHAR(1000);";
    $db->query($q);
    dbClose($db);

    /**
     * Added for MM
     */
    $db = dbConnect();
    $q = "ALTER TABLE page MODIFY attribute_set SMALLINT(5) unsigned;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE page MODIFY entity_type SMALLINT(5) unsigned;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE page ADD CONSTRAINT page_ibfk_1 FOREIGN KEY (attribute_set) REFERENCES attribute_set(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE page ADD CONSTRAINT page_ibfk_2 FOREIGN KEY (entity_type) REFERENCES entity_type(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE page_block MODIFY attribute_set SMALLINT(5) unsigned;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE page_block MODIFY entity_type SMALLINT(5) unsigned;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE page_block ADD CONSTRAINT page_block_ibfk_1 FOREIGN KEY (attribute_set) REFERENCES attribute_set(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE page_block ADD CONSTRAINT page_block_ibfk_2 FOREIGN KEY (entity_type) REFERENCES entity_type(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE list_view MODIFY attribute_set SMALLINT(5) unsigned;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE list_view MODIFY entity_type SMALLINT(5) unsigned;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE list_view ADD CONSTRAINT list_view_ibfk_1 FOREIGN KEY (attribute_set) REFERENCES attribute_set(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE list_view ADD CONSTRAINT list_view_ibfk_2 FOREIGN KEY (entity_type) REFERENCES entity_type(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE list_view_attribute ADD CONSTRAINT list_view_attribute_ibfk_1 FOREIGN KEY (attribute_id) REFERENCES attribute(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE list_view_attribute ADD CONSTRAINT list_view_attribute_ibfk_2 FOREIGN KEY (list_view_id) REFERENCES list_view(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);


    $db = dbConnect();
    $q = "ALTER TABLE navigation_link ADD CONSTRAINT navigation_link_ibfk_1 FOREIGN KEY (page) REFERENCES page(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE navigation_link ADD CONSTRAINT navigation_link_ibfk_2 FOREIGN KEY (parent_id) REFERENCES navigation_link(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE attribute_group ADD CONSTRAINT attribute_group_ibfk_1 FOREIGN KEY (attribute_set_id) REFERENCES attribute_set(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE entity_attribute ADD CONSTRAINT entity_attribute_ibfk_1 FOREIGN KEY (attribute_id) REFERENCES attribute(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE entity_attribute ADD CONSTRAINT entity_attribute_ibfk_2 FOREIGN KEY (attribute_set_id) REFERENCES attribute_set(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE entity_attribute ADD CONSTRAINT entity_attribute_ibfk_3 FOREIGN KEY (entity_type_id) REFERENCES entity_type(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE entity_attribute ADD CONSTRAINT entity_attribute_ibfk_4 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE privilege ADD CONSTRAINT privilege_ibfk_1 FOREIGN KEY (role) REFERENCES role_entity(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE user_role_entity ADD CONSTRAINT user_role_entity_ibfk_1 FOREIGN KEY (attribute_set_id) REFERENCES attribute_set(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE user_role_entity ADD CONSTRAINT user_role_entity_ibfk_2 FOREIGN KEY (core_user_id) REFERENCES user_entity(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE user_role_entity ADD CONSTRAINT user_role_entity_ibfk_3 FOREIGN KEY (role_id) REFERENCES role_entity(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE user_role_entity ADD CONSTRAINT user_role_entity_ibfk_4 FOREIGN KEY (entity_type_id) REFERENCES entity_type(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE entity_log MODIFY attribute_set_id SMALLINT(5) unsigned;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE entity_log MODIFY entity_type_id SMALLINT(5) unsigned;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = addColumnQuery("entity_log", "previous_values", "longtext");
    if (!empty($q)) {
        $db->multi_query($q);
    }
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE entity_log ADD CONSTRAINT entity_log_ibfk_1 FOREIGN KEY (attribute_set_id) REFERENCES attribute_set(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE entity_log ADD CONSTRAINT entity_log_ibfk_2 FOREIGN KEY (entity_type_id) REFERENCES entity_type(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE entity_level_permission MODIFY entity_type_id SMALLINT(5) unsigned;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE entity_level_permission ADD CONSTRAINT entity_level_permission_ibfk_1 FOREIGN KEY (entity_type_id) REFERENCES entity_type(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE entity_level_permission ADD CONSTRAINT entity_level_permission_ibfk_2 FOREIGN KEY (role_id) REFERENCES role_entity(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE attribute MODIFY entity_type_id SMALLINT(5) unsigned;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE attribute MODIFY lookup_entity_type_id SMALLINT(5) unsigned;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE attribute MODIFY lookup_attribute_set_id SMALLINT(5) unsigned;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE attribute MODIFY lookup_attribute_id INT(11) unsigned;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE attribute ADD CONSTRAINT attribute_ibfk_1 FOREIGN KEY (entity_type_id) REFERENCES entity_type(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE attribute ADD CONSTRAINT attribute_ibfk_2 FOREIGN KEY (lookup_attribute_id) REFERENCES attribute(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE attribute ADD CONSTRAINT attribute_ibfk_3 FOREIGN KEY (lookup_attribute_set_id) REFERENCES attribute_set(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE attribute ADD CONSTRAINT attribute_ibfk_4 FOREIGN KEY (lookup_entity_type_id) REFERENCES entity_type(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    /**
     * Delete bundle_products i configurable_products
     */
    $db = dbConnect();
    $q = "DELETE FROM page_block WHERE type IN ('configurable_products','bundle_products');";
    $db->query($q);
    dbClose($db);

    /**
     * Delete attribute and attribute group
     */
    $db = dbConnect();
    $q = "DELETE FROM attribute WHERE uid = '3cade272a7483f3ab729cedc3679508';";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DELETE FROM attribute_group WHERE uid = '014306871d4cb8397f44db92adb5ee3d';";
    $db->query($q);
    dbClose($db);

    /**
     * Change product groups id to product groups
     */
    $db = dbConnect();
    $q = "UPDATE attribute SET attribute_code = 'product_groups' WHERE attribute_code = 'product_groups_id' AND backend_table = 'product_entity';";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE product_entity RENAME COLUMN product_groups_id TO product_groups;";
    $db->query($q);
    dbClose($db);

    /**
     * Delete sidebar blocks
     */
    $db = dbConnect();
    $q = "DELETE FROM page_block WHERE type = 'sidebar';";
    $db->query($q);
    dbClose($db);

    /**
     * CHANGE clean_entity_log to clean_logs method in cron
     */
    $db = dbConnect();
    $q = "UPDATE cron_job_entity SET method = REPLACE(method, 'clean_entity_log', 'clean_logs') WHERE method LIKE '%clean_entity_log%';";
    $db->query($q);
    dbClose($db);

    /**
     * VIEW FOR ENTITY LOG
     */
    $db = dbConnect();
    $q = "CREATE OR REPLACE VIEW entity_log_view_entity AS select `e`.`id` AS `id`,`e`.`entity_type_id` AS `entity_type_id`,`e`.`attribute_set_id` AS `attribute_set_id`,`e`.`attribute_set_code` AS `entity_attribute_set`,`e`.`entity_id` AS `entity_id`,`e`.`event_time` AS `modified`,`e`.`event_time` AS `created`,`e`.`event_time` AS `event_time`,1 AS `entity_state_id`,`e`.`username` AS `created_by`,`e`.`username` AS `modified_by`,`e`.`username` AS `username`,'' AS `version`,'' AS `min_version`,'' AS `locked`,'' AS `locked_by`,`e`.`previous_values` AS `previous_values`,`e`.`content` AS `current_values`,`e`.`action` AS `entity_action` from `entity_log` `e`;";
    $db->query($q);
    dbClose($db);

    /**
     * Replace wrong attribute set uid
     */
    $db = dbConnect();
    $q = "UPDATE attribute SET uid = '60c891dfb7f379.22672895' WHERE uid = 'be3037554db7c60b817a2713ff648c4f';";
    $db->query($q);
    dbClose($db);

    /**
     * Set qty to decimal
     */
    $db = dbConnect();
    $q = "ALTER TABLE quote_item_entity MODIFY COLUMN qty DECIMAL(12,4);";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE quote_entity MODIFY COLUMN total_qty DECIMAL(12,4);";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE order_entity MODIFY COLUMN total_qty DECIMAL(12,4);";
    $db->query($q);
    dbClose($db);
    $db = dbConnect();
    $q = "ALTER TABLE product_configuration_product_link_entity MODIFY COLUMN min_qty DECIMAL(12,4);";
    $db->query($q);
    dbClose($db);

    /**
     * Drop column deliver_id in delivery_prices_entity
     */
    $db = dbConnect();
    $q = "ALTER TABLE delivery_prices_entity DROP FOREIGN KEY deliver_prices_entity_deliver_id;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE delivery_prices_entity DROP INDEX deliver_prices_entity_deliver_id;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE delivery_prices_entity DROP COLUMN deliver_id;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE delivery_prices_entity DROP FOREIGN KEY deliver_prices_entity_country_id;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE delivery_prices_entity DROP INDEX deliver_prices_entity_country_id;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE delivery_prices_entity DROP COLUMN country_id;";
    $db->query($q);
    dbClose($db);

    /**
     * Drop discount_coupon_entity_ibfk_2
     */
    $db = dbConnect();
    $q = "ALTER TABLE discount_coupon_entity DROP FOREIGN KEY discount_coupon_entity_ibfk_2;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE discount_coupon_entity DROP INDEX discount_coupon_entity_account_group_id;";
    $db->query($q);
    dbClose($db);

    /**
     * Drop coluumn country_id in delivery_prices_entity
     */
    $db = dbConnect();
    $q = "ALTER TABLE delivery_prices_entity DROP FOREIGN KEY delivery_prices_entity_ibfk_2;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE delivery_prices_entity DROP FOREIGN KEY delivery_prices_entity_ibfk_3;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE delivery_prices_entity DROP INDEX deliver_prices_entity_deliver_id;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE delivery_prices_entity DROP FOREIGN KEY deliver_prices_entity_country_id;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE delivery_prices_entity DROP INDEX deliver_prices_entity_country_id;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE delivery_prices_entity DROP COLUMN country_id;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE delivery_prices_entity DROP COLUMN deliver_id;";
    $db->query($q);
    dbClose($db);

    /**
     * DROP column employees FROM notification_type
     */
    $db = dbConnect();
    $q = "ALTER TABLE notification_type_entity DROP FOREIGN KEY notification_type_entity_employees;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE notification_type_entity DROP INDEX notification_type_entity_employees;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE notification_type_entity DROP COLUMN employees;";
    $db->query($q);
    dbClose($db);

    /**
     * Missing FOREIGN KEYs
     */
    $db = dbConnect();
    $q = "ALTER TABLE s_product_attribute_configuration_options_entity ADD CONSTRAINT spaco_configuration_attribute_id FOREIGN KEY (configuration_attribute_id) REFERENCES s_product_attribute_configuration_entity(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE s_product_attribute_configuration_image_entity ADD CONSTRAINT spaco_configuration_id FOREIGN KEY (s_product_attribute_configuration_id) REFERENCES s_product_attribute_configuration_entity(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE s_product_attribute_configuration_entity ADD CONSTRAINT spac_type_id FOREIGN KEY (s_product_attribute_configuration_type_id) REFERENCES s_product_attribute_configuration_type_entity(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE s_product_configuration_product_group_link_entity ADD CONSTRAINT spcpgl_product_group_id FOREIGN KEY (product_group_id) REFERENCES product_group_entity(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE s_product_configuration_product_group_link_entity ADD CONSTRAINT spcpgl_configuration_id FOREIGN KEY (s_product_attribute_configuration_id) REFERENCES s_product_attribute_configuration_entity(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "ALTER TABLE product_configuration_bundle_option_entity ADD CONSTRAINT pcbo_configuration_id FOREIGN KEY (s_product_attribute_configuration_id) REFERENCES s_product_attribute_configuration_entity(id) ON DELETE CASCADE;";
    $db->query($q);
    dbClose($db);

    /**
     * Update cron REMOVE remove_old_discounts_on_products
     */
    $db = dbConnect();
    $q = "DELETE FROM cron_job_entity WHERE method like '%crmhelper:run type:remove_old_discounts_on_products%';";
    $db->query($q);
    dbClose($db);

    /**
     * Create procedure sp_insert_order_item_fact
     */
    $db = dbConnect();
    $q = "DROP PROCEDURE IF EXISTS sp_insert_order_item_fact;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
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
    currency_rate
    )
    
    SELECT sb.order_item_id, sb.order_id, sb.date_dim_id, sb.product_id, sb.name, sb.qty, sb.base_price_total, sb.base_price_tax, sb.base_price_discount_total, sb.base_price_item, sb.base_price_item_tax,
    sb.order_state_id, sb.store_id, sb.website_id, sb.account_id, sb.contact_id, sb.core_user_id, sb.is_legal_entity, sb.account_group_id, 
    sb.account_shipping_city_id, sb.country_id, sb.modified, sb.payment_type_id, sb.delivery_type_id, sb.product_groups,
    sb.order_base_price_total, sb.order_base_price_tax, sb.order_base_price_items_total, sb.order_base_price_items_tax, sb.order_base_price_delivery_total, sb.order_base_price_delivery_tax, sb.currency_id, sb.currency_rate
    FROM
    (SELECT 
    oi.id as order_item_id, 
    o.id as order_id, 
    dd.id as date_dim_id, 
    oi.product_id, 
    oi.name, 
    oi.qty, 
    oi.base_price_total, oi.base_price_tax, oi.base_price_discount_total, oi.base_price_item, oi.base_price_item_tax,
    o.order_state_id, o.store_id, st.website_id, o.account_id, oc.contact_id, con.core_user_id, a.is_legal_entity, a.account_group_id, 
    o.account_shipping_city_id, c.country_id, o.modified, o.created, o.payment_type_id, o.delivery_type_id, CONCAT('#',GROUP_CONCAT(ppl.product_group_id SEPARATOR '#'),'#') AS product_groups,
    o.base_price_total as order_base_price_total, o.base_price_tax as order_base_price_tax, o.base_price_items_total as order_base_price_items_total, o.base_price_items_tax as order_base_price_items_tax, o.base_price_delivery_total as order_base_price_delivery_total, o.base_price_delivery_tax as order_base_price_delivery_tax, o.currency_id, o.currency_rate
    FROM order_item_entity as oi
    LEFT JOIN order_entity as o ON oi.order_id = o.id
    LEFT JOIN account_entity AS a ON o.account_id = a.id
    LEFT JOIN order_customer_entity AS oc ON o.id = oc.order_id
    LEFT JOIN contact_entity as con ON oc.contact_id = con.id
    LEFT JOIN city_entity AS c ON o.account_shipping_city_id = c.id
    LEFT JOIN s_store_entity AS st ON o.store_id = st.id
    LEFT JOIN shape_track_date_dim as dd ON DATE_FORMAT(o.created,\"%Y-%m-%d\") = dd.fulldate
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
    website_id = sb.website_id;
    
    END";
    $db->query($q);
    dbClose($db);

    /**
     * Create procedure sp_insert_product_group_fact
     */
    $db = dbConnect();
    $q = "DROP PROCEDURE IF EXISTS sp_insert_product_group_fact;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
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
    $db->query($q);
    dbClose($db);

    /**
     * Create procedure sp_insert_totals_fact
     */
    $db = dbConnect();
    $q = "DROP PROCEDURE IF EXISTS sp_insert_totals_fact;";
    $db->query($q);
    dbClose($db);

    $db = dbConnect();
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
    $db->query($q);
    dbClose($db);

    /**
     * Set slider_image_entity active
     */
    $db = dbConnect();
    $q = "UPDATE slider_image_entity SET active=1 WHERE active IS NULL;";
    $db->query($q);
    dbClose($db);

    /**
     * Set s_product_search_results_entity active
     */
    $db = dbConnect();
    $q = "UPDATE s_product_search_results_entity SET store_id=3 WHERE store_id IS NULL;";
    $db->query($q);
    dbClose($db);

    /**
     * Create table shape_track_search_transaction
     */
    $db = dbConnect();
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
      `contact_id` int(11) DEFAULT NULL,
      `store_id` int(11) DEFAULT NULL,
      `list_of_results` longtext,
      `from_cache` tinyint(1) unsigned DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->query($q);
    dbClose($db);

    /**
     * Add filenameHash and fileHash
     */
    $db = dbConnect();
    $q = "ALTER table blog_post_image_entity ADD COLUMN file_hash varchar(255),
                                        ADD COLUMN filename_hash varchar(255);";
    $db->query($q);
    dbClose($db);

    /**
     * Add filenameHash and fileHash
     */
    $db = dbConnect();
    $q = "ALTER table blog_category_image_entity ADD COLUMN file_hash varchar(255),
                                        ADD COLUMN filename_hash varchar(255);";
    $db->query($q);
    dbClose($db);

    /**
     * Add filenameHash and fileHash
     */
    $db = dbConnect();
    $q = "ALTER table brand_image_entity ADD COLUMN file_hash varchar(255),
                                        ADD COLUMN filename_hash varchar(255);";
    $db->query($q);
    dbClose($db);

    /**
     * Add filenameHash and fileHash
     */
    $db = dbConnect();
    $q = "ALTER table product_configuration_bundle_option_image_entity ADD COLUMN file_hash varchar(255),
                                        ADD COLUMN filename_hash varchar(255);";
    $db->query($q);
    dbClose($db);

    /**
     * Add filenameHash and fileHash
     */
    $db = dbConnect();
    $q = "ALTER table product_group_images_entity ADD COLUMN file_hash varchar(255),
                                        ADD COLUMN filename_hash varchar(255);";
    $db->query($q);
    dbClose($db);

    /**
     * Add filenameHash and fileHash
     */
    $db = dbConnect();
    $q = "ALTER table s_front_block_images_entity ADD COLUMN file_hash varchar(255),
                                        ADD COLUMN filename_hash varchar(255);";
    $db->query($q);
    dbClose($db);

    /**
     * Add filenameHash and fileHash
     */
    $db = dbConnect();
    $q = "ALTER table s_product_attribute_configuration_image_entity ADD COLUMN file_hash varchar(255),
                                        ADD COLUMN filename_hash varchar(255);";
    $db->query($q);
    dbClose($db);

    /**
     * Add filenameHash and fileHash
     */
    $db = dbConnect();
    $q = "ALTER table slider_image_entity ADD COLUMN file_hash varchar(255),
                                        ADD COLUMN filename_hash varchar(255);";
    $db->query($q);
    dbClose($db);

    /**
     * Drop table settings
     */
    $db = dbConnect();
    $q = "DROP TABLE IF EXISTS settings;";
    $db->multi_query($q);
    dbClose($db);

    /**
     * Drop table bon_api_company_entity
     */
    $db = dbConnect();
    $q = "DROP TABLE IF EXISTS bon_api_company_entity;";
    $db->multi_query($q);
    dbClose($db);

    /**
     * UPDATE remove_old_discounts_on_products
     */
    $db = dbConnect();
    $q = "UPDATE cron_job_entity SET method = 'crmhelper:run type:remove_old_discounts_on_products' WHERE method = 'crmhelper:run remove_old_discounts_on_products';";
    $db->multi_query($q);
    dbClose($db);

    /**
     * UPDATE schedule
     */
    $db = dbConnect();
    $q = "UPDATE cron_job_entity SET schedule = '* * * * *' WHERE schedule = '* */1 * * *';";
    $db->multi_query($q);
    dbClose($db);

    /**
     * UPDATE cron import_files
     */
    $db = dbConnect();
    $q = "UPDATE cron_job_entity SET method = 'crmhelper:run type:import_files arg1:product_images arg2:product arg3:code' WHERE method = 'crmhelper:run import_files product_images product ean';";
    $db->multi_query($q);
    dbClose($db);

    /**
     * UPDATE cron import_files 2
     */
    $db = dbConnect();
    $q = "UPDATE cron_job_entity SET method = 'crmhelper:run type:import_files arg1:product_images arg2:product arg3:code' WHERE method = 'crmhelper:run type:import_images arg1:product_images arg2:product arg3:code';";
    $db->multi_query($q);
    dbClose($db);

    /**
     * UPDATE cron remind_me
     */
    $db = dbConnect();
    $q = "UPDATE cron_job_entity SET method = 'automationsEmail:run function:remind_me', schedule = '10 * * * *'  WHERE method = 'automationsEmail:run type:remind_me';";
    $db->multi_query($q);
    dbClose($db);
}

function addColumnQuery($tablename, $columnName, $columnAttributes)
{
    $query = "SET @dbname = DATABASE();
            SET @tablename = '{$tablename}';
            SET @columnname = '{$columnName}';
            SET @preparedStatement = (SELECT IF(
                (
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
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

function transferPrivilegeActionCodeToUID()
{

    $db = dbConnect();
    $query = "SET @preparedStatement = (SELECT IF(
                        (
                        SELECT count(*) FROM privilege WHERE LENGTH(action_code) < 3 and action_type IN (1,2,3,4)
                    ) > 0,
                    'UPDATE privilege as p LEFT JOIN attribute_set as a ON p.action_code = a.id SET p.action_code = a.uid WHERE action_type IN (1,2,3,4);',
                    'SELECT 1'
                ));
                PREPARE stmt FROM @preparedStatement;
                EXECUTE stmt;
                DEALLOCATE PREPARE stmt;";
    $db->multi_query($query);
    dbClose($db);

    $db = dbConnect();
    $query = "SET @preparedStatement = (SELECT IF(
                        (
                        SELECT count(*) FROM privilege WHERE LENGTH(action_code) < 3 and action_type IN (5)
                    ) > 0,
                    'UPDATE privilege as p LEFT JOIN page as a ON p.action_code = a.id SET p.action_code = a.uid WHERE action_type IN (5);',
                    'SELECT 1'
                ));
                PREPARE stmt FROM @preparedStatement;
                EXECUTE stmt;
                DEALLOCATE PREPARE stmt;";
    $db->multi_query($query);
    dbClose($db);

    $db = dbConnect();
    $query = "SET @preparedStatement = (SELECT IF(
                        (
                        SELECT count(*) FROM privilege WHERE LENGTH(action_code) < 3 and action_type IN (6)
                    ) > 0,
                    'UPDATE privilege as p LEFT JOIN list_view as a ON p.action_code = a.id SET p.action_code = a.uid WHERE action_type IN (6);',
                    'SELECT 1'
                ));
                PREPARE stmt FROM @preparedStatement;
                EXECUTE stmt;
                DEALLOCATE PREPARE stmt;";
    $db->multi_query($query);
    dbClose($db);

    $db = dbConnect();
    $query = "SET @preparedStatement = (SELECT IF(
                        (
                        SELECT count(*) FROM privilege WHERE LENGTH(action_code) < 3 and action_type IN (7)
                    ) > 0,
                    'UPDATE privilege as p LEFT JOIN page_block as a ON p.action_code = a.id SET p.action_code = a.uid WHERE action_type IN (7);',
                    'SELECT 1'
                ));
                PREPARE stmt FROM @preparedStatement;
                EXECUTE stmt;
                DEALLOCATE PREPARE stmt;";
    $db->multi_query($query);
    dbClose($db);

    $db = dbConnect();
    $query = "SET @preparedStatement = (SELECT IF(
                        (
                        SELECT count(*) FROM privilege WHERE action_code IS NULL
                    ) > 0,
                    'DELETE FROM privilege WHERE action_code is null;',
                    'SELECT 1'
                ));
                PREPARE stmt FROM @preparedStatement;
                EXECUTE stmt;
                DEALLOCATE PREPARE stmt;";
    $db->multi_query($query);
    dbClose($db);
}

update();

?>

