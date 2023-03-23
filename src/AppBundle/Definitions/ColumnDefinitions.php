<?php

namespace AppBundle\Definitions;

class ColumnDefinitions
{
    public static function ColumnDefinitions()
    {
        return Array(
            Array("name" => "id", "definition" => "INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY"),
            Array("name" => "entity_type_id", "definition" => "SMALLINT(5) UNSIGNED  NOT NULL"),
            Array("name" => "attribute_set_id", "definition" => "SMALLINT(5) UNSIGNED  NOT NULL"),
            Array("name" => "created", "definition" => "DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL"),
            Array("name" => "modified", "definition" => "DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL"),
            Array("name" => "locked", "definition" => "DATETIME"),
            Array("name" => "locked_by", "definition" => "VARCHAR(255)"),
            Array("name" => "modified_by", "definition" => "VARCHAR(255)"),
            Array("name" => "created_by", "definition" => "VARCHAR(255)"),
            Array("name" => "version", "definition" => "INT(11) DEFAULT 1"),
            Array("name" => "min_version", "definition" => "INT(11) DEFAULT 0"),
            Array("name" => "entity_state_id", "definition" => "SMALLINT(5) UNSIGNED"),
        );
    }

    public static function DocumentColumnDefinitions()
    {
        return Array(
            Array("name" => "id", "definition" => "INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY"),
            Array("name" => "entity_type_id", "definition" => "SMALLINT(5) UNSIGNED  NOT NULL"),
            Array("name" => "attribute_set_id", "definition" => "SMALLINT(5) UNSIGNED  NOT NULL"),
            Array("name" => "created", "definition" => "DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL"),
            Array("name" => "modified", "definition" => "DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL"),
            Array("name" => "locked", "definition" => "DATETIME"),
            Array("name" => "locked_by", "definition" => "VARCHAR(255)"),
            Array("name" => "modified_by", "definition" => "VARCHAR(255)"),
            Array("name" => "created_by", "definition" => "VARCHAR(255)"),
            Array("name" => "version", "definition" => "INT(11) DEFAULT 1"),
            Array("name" => "min_version", "definition" => "INT(11) DEFAULT 0"),
            Array("name" => "entity_state_id", "definition" => "SMALLINT(5) UNSIGNED"),
            Array("name" => "file", "definition" => "VARCHAR(255)"),
            Array("name" => "filename", "definition" => "VARCHAR(255)"),
            Array("name" => "file_type", "definition" => "VARCHAR(5)"),
            Array("name" => "size", "definition" => "VARCHAR(255)"),
            Array("name" => "file_source", "definition" => "VARCHAR(255)"),
        );
    }

    public static function DocumentColumnExtractedDefinitions()
    {
        return Array(
            Array("name" => "file", "definition" => "VARCHAR(255)"),
            Array("name" => "filename", "definition" => "VARCHAR(255)"),
            Array("name" => "file_type", "definition" => "VARCHAR(5)"),
            Array("name" => "size", "definition" => "VARCHAR(255)"),
            Array("name" => "file_source", "definition" => "VARCHAR(255)"),
        );
    }
}