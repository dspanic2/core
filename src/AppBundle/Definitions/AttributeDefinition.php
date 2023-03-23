<?php

namespace AppBundle\Definitions;

class AttributeDefinition
{

    private $databaseBackendTypeOptions;


    public function __construct()
    {
        $this->databaseBackendTypeOptions = array(
            "varchar" => "VARCHAR(255)",
            "integer" => "INT(11) UNSIGNED",
            "decimal" => "DECIMAL(12,4)",
            "static" => "INT(11) UNSIGNED NOT NULL",
            "option" => "INT(11) DEFAULT NULL",
            "lookup" => "INT(11) UNSIGNED DEFAULT NULL",
            "date" => "DATE DEFAULT NULL",
            "datetime" => "DATETIME DEFAULT NULL",
            "text" => "TEXT",
            "time"=>"TIME(0)",
            "ckeditor" => "TEXT",
            "bool" => "INT(11)",
            "json" => "JSON",
            //"reverse_lookup" => Array(),
            "file" => array(),
        );

        $this->frontendClassOptions = array(
            "",
            "sp-bold",
        );
    }

    public function getFrontendClassOptions()
    {
        return $this->frontendClassOptions;
    }

    public function getDatabaseBackendTypeOptions()
    {
        return $this->databaseBackendTypeOptions;
    }
}
