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
        $config = getConfig();

        $db = dbConnect();
        $q = "SELECT DISTINCT concat('ALTER DATABASE `', TABLE_SCHEMA, '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;') AS queries
            from INFORMATION_SCHEMA.TABLES
            where TABLE_SCHEMA = '{$config['database_name']}' and TABLE_COLLATION != 'utf8mb4_unicode_ci'
            UNION
            SELECT CONCAT('ALTER TABLE ', TABLE_SCHEMA, '.', TABLE_NAME,' COLLATE utf8mb4_unicode_ci;') AS queries
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA='{$config['database_name']}' and TABLE_COLLATION != 'utf8mb4_unicode_ci'
            AND TABLE_TYPE='BASE TABLE'
            UNION
            SELECT DISTINCT
                CONCAT('ALTER TABLE ', C.TABLE_NAME, ' CHANGE ', C.COLUMN_NAME, ' ', C.COLUMN_NAME, ' ', C.COLUMN_TYPE, ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;') as queries
            FROM INFORMATION_SCHEMA.COLUMNS as C
                LEFT JOIN INFORMATION_SCHEMA.TABLES as T
                    ON C.TABLE_NAME = T.TABLE_NAME
            WHERE C.COLLATION_NAME is not null and C.COLLATION_NAME != 'utf8mb4_unicode_ci'
                AND C.TABLE_SCHEMA='{$config['database_name']}'
                AND T.TABLE_TYPE='BASE TABLE'
            ;";
        $result = $db->query($q);
        $res = $result->fetch_all(MYSQLI_ASSOC);
        $result->free_result();
        dbClose($db);

        if(!empty($res)){
            $res = array_column($res,"queries");
            foreach ($res as $r){
                $db = dbConnect();
                $db->query($r);
                dbClose($db);
            }
        }

        return true;
    }

    update();

?>