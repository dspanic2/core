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
        if (stripos($p, "database_name") !== false && stripos($p, "magento") === false) {
            $p = explode($delimiter, $p);
            $conf["database_name"] = trim($p[1]);
            continue;
        } elseif (stripos($p, "database_user") !== false && stripos($p, "magento") === false) {
            $p = explode($delimiter, $p);
            $conf["database_user"] = trim($p[1]);
            continue;
        } elseif (stripos($p, "database_password") !== false && stripos($p, "magento") === false) {
            $p = explode($delimiter, $p);
            $conf["database_password"] = trim(trim($p[1]), "'");
            continue;
        } elseif (stripos($p, "database_host") !== false && stripos($p, "magento") === false) {
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
    $q = "DELETE FROM entity_type WHERE entity_type_code IN ('stock_manager_product','stock_manager');";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DELETE FROM s_route_entity WHERE destination_type = 's_store_product_link' AND destination_id not in (SELECT id FROM s_store_product_link_entity);";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DELETE FROM s_store_product_link_entity WHERE id NOT IN (SELECT destination_id FROM s_route_entity WHERE destination_type = 's_store_product_link');";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "UPDATE s_route_entity as sr LEFT JOIN s_store_product_link_entity as spl ON sr.destination_id = spl.id AND destination_type = 's_store_product_link' SET sr.destination_id = spl.product_id, sr.destination_type = 'product' WHERE spl.product_id is not null;";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DELETE FROM entity_type WHERE is_custom = 0;";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DELETE FROM page_block WHERE is_custom = 0;";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "UPDATE navigation_link SET parent_id = 999 WHERE is_custom = 1;";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DELETE FROM navigation_link WHERE is_custom = 0 AND (id != 8  AND id != 999) and (parent_id != 8 or parent_id is null);";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DROP INDEX brand_name ON brand_entity;";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DROP TABLE shape_track;";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "DELETE FROM settings WHERE name IN ('wand_images_folder_big','wand_images_folder','wand_api','recro_key','senso_api','gls_wsdl','gls_password','gls_username','gls_client_number','gmaps_key','product_grid_available_page_sizes','product_grid_default_page_size','google_recaptcha_v3_key','mfa_enabled','ckeditor_entity_use_absolute_path','invoice_price_per_items','quote_pdf_template','quote_preview_template','email_validator','decimal_validator','integer_validator','decimal_format','site_base_data','commerce_template_bundles','quote_preview_url','cacheable_entities','money_transfer_payment_slip','environment','is_production','crm_process_manager','tinypng_optimization_current','tinypng_optimization_limit','tinypng_key','tinypng_url');";
    $db->multi_query($q);
    dbClose($db);

    $db = dbConnect();
    $q = "UPDATE product_entity SET template_type_id=5;";
    $db->multi_query($q);
    dbClose($db);
}


update();

?>
