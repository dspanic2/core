<?php
$env = file_get_contents("../.env");

if (stripos($env, "MAINTENANCE=1") !== false && !isset($_COOKIE["override_maintenance"])) {
    if (isset($_GET["override_maintenance"])) {
        setcookie("override_maintenance", 1, time() + 60 * 60 * 24 * 365);
        $_COOKIE['override_maintenance'] = 1;
    }
    echo file_get_contents($_ENV["WEB_PATH"] . "../src/ScommerceBusinessBundle/Resources/views/Components/maintenance.html.twig");
} else {
    if (stripos($env, "IS_PRODUCTION=0") !== false || stripos($env, "MAINTENANCE=1") !== false) {
        require __DIR__ . '/app_dev.php';
    } else {
        require __DIR__ . '/app.php';
    }
}
