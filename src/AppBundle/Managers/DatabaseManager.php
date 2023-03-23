<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityAttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Definitions\ColumnDefinitions;
use AppBundle\Entity;
use AppBundle\Entity\EntityType;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\VarDumper\VarDumper;

class DatabaseManager extends AbstractBaseManager
{
    /** @var AttributeContext $attributeContext */
    protected $attributeContext;
    /** @var EntityAttributeContext $entityAttributeContext */
    protected $entityAttributeContext;
    /** @var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    protected $attributeDefinition;

    public function initialize()
    {
        parent::initialize();
        $this->attributeContext = $this->container->get("attribute_context");
        $this->entityAttributeContext = $this->container->get("entity_attribute_context");
        $this->entityTypeContext = $this->container->get("entity_type_context");
        $this->databaseContext = $this->container->get("database_context");
        $this->attributeDefinition = new \AppBundle\Definitions\AttributeDefinition();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @return mixed
     */
    public function getTables()
    {
        $tables = $this->databaseContext->getListTables();
        return $tables;
    }

    /**
     * @param $table
     * @return bool
     */
    public function checkIfTableExists($table)
    {
        $tables = $this->getTables();
        if (in_array($table, $tables)) {
            return true;
        }
        return false;
    }

    /**
     * @param $table
     * @param $attribute_code
     * @return bool
     */
    public function checkIfFieldExists($table, $attribute_code)
    {

        $db_name = $this->databaseContext->getDatabase();
        $field = null;
        $sql = "SELECT * 
                FROM ssinformation.COLUMNS 
                WHERE 
                    TABLE_SCHEMA = '{$db_name}' 
                AND TABLE_NAME = '{$table}' 
                AND COLUMN_NAME = '{$attribute_code}'";
        try {
            $field = $this->databaseContext->executeQuery($sql);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        if (!empty($field)) {
            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public function getEntityTypesBackendModel()
    {
        $entityTypes = $this->entityTypeContext->getAll();
        $backendModelOptions = $this->attributeDefinition->getBackendModelOptions();

        $entityTypesTables = Array();

        foreach ($entityTypes as $entityType) {
            $table = $entityType->getEntityTable();
            $table = explode("_", $table);
            $table = array_map('ucfirst', $table);


            foreach ($backendModelOptions as $type => $options) {
                foreach ($options as $option) {
                    $entityTypesTables[$entityType->getId()][$type][1][] = implode("", $table) . $option;
                }

                $entityTypesTables[$entityType->getId()][$type][0][] = implode("", $table);
            }
        }

        //VarDumper::dump($entityTypesTables);die;
        return $entityTypesTables;
    }

    /**
     * @param Entity\EntityType $entityType
     * @param string $note
     * @return bool
     * @throws \Exception
     */
    public function createTableIfDoesntExist(Entity\EntityType $entityType, $note = "")
    {
        if($entityType->getIsView()){
            return true;
        }

        $table = $entityType->getEntityTable();

        $columnDefinitions = $entityType->getIsDocument() ? ColumnDefinitions::DocumentColumnDefinitions() : ColumnDefinitions::ColumnDefinitions();

        $createColumnsSql = "";
        foreach ($columnDefinitions as $definition) {
            $createColumnsSql = $createColumnsSql . " {$definition["name"]} {$definition["definition"]},";
        }
        $createColumnsSql = trim($createColumnsSql, ',');

        if (!$this->checkIfTableExists($table)) {
            $sql = "CREATE TABLE IF NOT EXISTS {$table} ({$createColumnsSql})";


            try {
                $this->databaseContext->executeNonQuery($sql);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                throw new \Exception($e);
            }
        }

        $attributes = $this->entityManager->getAttributesOfEntityType($entityType->getEntityTypeCode(), false);

        if (!empty($attributes)) {
            /** @var Entity\Attribute $attribute */
            foreach ($attributes as $attribute) {
                $this->addFieldIfDoesntExist($attribute);
            }
        }

        return true;
    }

    /**
     * @param $table
     * @return bool
     * @throws \Exception
     */
    public function deleteTableIfExist($table)
    {
        if (!$this->checkIfTableExists($table)) {
            return false;
        }

        $sql = "SET FOREIGN_KEY_CHECKS=0; DROP TABLE {$table}; SET FOREIGN_KEY_CHECKS=1;";

        try {
            $this->databaseContext->executeNonQuery($sql);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new \Exception($e);
        }


        return true;
    }

    /**
     * @param $table
     * @param Entity\Attribute $attribute
     * @return bool
     * @throws \Exception
     */
    public function deleteFieldIfExist($table, Entity\Attribute $attribute)
    {
        if (!$this->checkIfTableExists($table)) {
            return false;
        }

        if (!$this->checkIfFieldExists($table, $attribute->getAttributeCode())) {
            return false;
        }

        try{
            if ($attribute->getBackendType() == "lookup") {

                $fk_name = md5("{$table}_{$attribute->getLookupEntityType()->getEntityTable()}_{$attribute->getAttributeCode()}");


                $sql = "ALTER TABLE {$table} DROP FOREIGN KEY `{$fk_name}`;";
                try {
                    $this->databaseContext->executeNonQuery($sql);
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                    //throw new \Exception($e);
                }
            }
        }
        catch (\Exception $e){
            //DO NOTHING
        }

        $sql = "SET FOREIGN_KEY_CHECKS=0; ALTER TABLE {$table} DROP COLUMN {$attribute->getAttributeCode()}; SET FOREIGN_KEY_CHECKS=1;";

        try {
            $this->databaseContext->executeNonQuery($sql);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new \Exception($e);
        }

        return true;
    }

    /**
     * @param Entity\Attribute $attribute
     * @return bool
     * @throws \Exception
     */
    public function addFieldIfDoesntExist(Entity\Attribute $attribute)
    {
        if($attribute->getEntityType()->getIsView()){
            return true;
        }

        $table = $attribute->getEntityType()->getEntityTable();
        $attribute_code = $attribute->getAttributeCode();
        $backend_type = $attribute->getBackendType();
        $note = $attribute->getNote();

        $databaseBackendTypeOptions = $this->attributeDefinition->getDatabaseBackendTypeOptions();
        if (!isset($databaseBackendTypeOptions[$backend_type])) {
            $this->logger->error("Database backend type option missing: " . $backend_type . " - " . $attribute_code);
            return false;
        }

        if (empty($databaseBackendTypeOptions[$backend_type])) {
            $this->logger->error("Database backend type option empty: " . $backend_type . " - " . $attribute_code);
            return true;
        }

        if (!$this->checkIfTableExists($table)) {
            return false;
        }

        if (!$this->checkIfFieldExists($table, $attribute_code)) {
            $sql = "SET FOREIGN_KEY_CHECKS=0; ALTER TABLE {$table}
                ADD {$attribute_code} {$databaseBackendTypeOptions[$backend_type]} COMMENT '{$note}'; SET FOREIGN_KEY_CHECKS=1;";

            if ($backend_type == "lookup") {
                $fk_name = md5("{$table}_{$attribute->getLookupEntityType()->getEntityTable()}_{$attribute_code}");
                $sql = $sql . "SET FOREIGN_KEY_CHECKS=0;  ALTER TABLE {$table}
                ADD CONSTRAINT {$fk_name} FOREIGN KEY ({$attribute_code}) REFERENCES {$attribute->getLookupEntityType()->getEntityTable()}(id) ON DELETE CASCADE ON UPDATE CASCADE; SET FOREIGN_KEY_CHECKS=1;";
            }

            try {
                $this->databaseContext->executeNonQuery($sql);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                throw new \Exception($e);
            }
        }

        return true;
    }

    /**
     * @param EntityType $entityType
     * @return bool
     * @throws \Exception
     */
    public function addDocumentColumnsToTable($entityType)
    {
        $table = $entityType->getEntityTable();

        $columnDefinitions = ColumnDefinitions::DocumentColumnExtractedDefinitions();

        foreach ($columnDefinitions as $columnDefinition) {
            $sql = "SET FOREIGN_KEY_CHECKS=0; ALTER TABLE {$table}
                ADD {$columnDefinition["name"]} {$columnDefinition["definition"]} COMMENT ''; SET FOREIGN_KEY_CHECKS=1;";

            try {
                $this->databaseContext->executeNonQuery($sql);
            } catch (\Exception $e) {
                throw new \Exception($e);
            }
        }

        return true;
    }

    /**
     * @param $numberOfDays
     * @return bool
     */
    public function cleanEntityLog($numberOfDays = 30){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $additionalWhere = "";

        $adminAccountRoles = $_ENV["ADMIN_ACCOUNT_ROLES"] ?? 0;
        if(!empty($adminAccountRoles)){
            $adminAccountRoles = json_decode($adminAccountRoles,true);
            if(!empty($adminAccountRoles)){
                $q = "SELECT DISTINCT(u.username) FROM user_role_entity as ure LEFT JOIN role_entity as r ON ure.role_id = r.id LEFT JOIN user_entity as u ON ure.core_user_id = u.id WHERE r.role_code IN ('".implode("','",$adminAccountRoles)."')";
                $adminUsers = $this->databaseContext->getAll($q);
                if(!empty($adminUsers)){
                    $adminUsers = array_column($adminUsers,"username");
                    $additionalWhere = " AND username NOT IN ('".implode("','",$adminUsers)."') ";
                }
            }
        }

        $q="DELETE FROM entity_log WHERE event_time < DATE_SUB(CURRENT_DATE(), INTERVAL {$numberOfDays} DAY) {$additionalWhere};";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @param $tablename
     * @param $columnName
     * @param $columnAttributes
     * @return string
     */
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
}