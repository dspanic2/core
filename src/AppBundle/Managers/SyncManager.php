<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Entity\EntityType;
use AppBundle\Helpers\ArrayHelper;
use AppBundle\Helpers\EntityHelper;
use Symfony\Component\Yaml\Yaml;

class SyncManager extends AbstractBaseManager
{
    private $isDefaultImport = false;
    private $debug = 0;
    private $importingMerged = false;
    private $errors = [];
    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    /** @var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;

    protected $yamlFiles = [
        "entity_type" => "entity_type_old.yaml",
        "attribute" => "attribute_old.yaml",
        "attribute_group" => "attribute_group_old.yaml",
        "list_view" => "list_view_old.yaml",
        "page_block" => "page_block_old.yaml",
        "page" => "page_old.yaml",
        "navigation_link" => "navigation_link_old.yaml",
    ];
    protected $entityTypeListByUid;
    protected $attributeListByUid;
    protected $entityTypeListByAttributeSetUid;
    protected $attributeListById;
    protected $listViewListByUid;
    protected $attributeGroupListByUid;
    protected $pageListByUid;


    /**
     * Limit delete to tables.
     *
     * @var array
     */
    private $additionalSwapIdToUid = [
        "page_block" => [ // table to swap in
            [
                "column_with_id" => "related_id", // column with id
                "column_with_table" => "type", // column with id
                "type" => [ // column that holds table name
                    "list_view" => "list_view",
                    "library_view" => "list_view",
                    "attribute_group" => "attribute_group",
                    "related_attribute_group" => "attribute_group",
                    "custom_html" => "attribute_set",
                    // ...
                ],
            ],
        ]
    ];

    /**
     * List of tables to merge.
     */
    private $mergeTables = [
        "list_view" => [
            "list_view_attribute" => [
                "column" => "list_view_id",
                "order_by" => "ord",
            ]
        ],
        "attribute_group" => [
            "entity_attribute" => [
                "column" => "attribute_group_id",
                "order_by" => "sort_order",
            ]
        ]
    ];

    /**
     * Exclude from export.
     * @var string[]
     */
    private $excludeMainTablesContent = [
        "entity_log",
        "shape_track",
        "shape_track_product_dim",
        "shape_track_totals_fact",
        "product_account_price_staging",
        "shape_track_date_dim",
        "shape_track_product_impressions_transaction",
        "shape_track_order_item_fact",
        "shape_track_search_transaction",
        "shape_track_product_group_fact",
        "product_account_group_price_staging",
    ];

    /**
     * Foreign keys not set in database.
     *
     * @var \string[][]
     */
    private $additionalForeignKeys = [
        [
            "table" => "entity_type",
            "column" => "entity_type_id",
        ],
        [
            "table" => "attribute_set",
            "column" => "attribute_set_id",
        ]
    ];

    /**
     * Replace IDs .
     *
     * @var \string[][]
     */
    private $jsonColumnsByTable = [
        "page_block" => [
            "json_column" => "content",
            "columns" => [
                [
                    "json_column_name" => "attribute_id",
                    "related_table" => "attribute",
                ],
            ]
        ],
    ];

    /**
     * Check bundle from parent table.
     *
     * @var [][]
     */
    private $checkParentBundle = [
        "attribute_set" => [
            "table" => "entity_type",
            "column" => "entity_type_id",
        ],
        "attribute_group" => [
            "table" => "attribute_set",
            "column" => "attribute_set_id",
        ],
        "attribute" => [
            "table" => "entity_type",
            "column" => "entity_type_id",
        ],
        "entity_attribute" => [
            "table" => "attribute_group",
            "column" => "attribute_group_id",
        ],
        "list_view" => [
            "table" => "entity_type",
            "column" => "entity_type",
        ],
        "list_view_attribute" => [
            "table" => "list_view",
            "column" => "list_view_id",
        ],
        "page_block" => [
            "table" => "entity_type",
            "column" => "entity_type",
        ],
        "page" => [
            "table" => "entity_type",
            "column" => "entity_type",
        ],
        "navigation_link" => [
            "table" => "page",
            "column" => "page",
        ],
    ];

    private $keepIds = [
        "user_entity",
    ];

    /**
     * @var string[]
     */
    private $skipImport = [
        "privilege",
        "settings",
        "bon_api_company_entity",
        "entity",
        "entity_level_permission",
        "product_account_price_staging",
        "shape_track_date_dim",
        "shape_track_product_impressions_transaction",
        "shape_track_order_item_fact",
        "entity_log",
        "shape_track_totals_fact",
        "shape_track_search_transaction",
        "shape_track_product_group_fact",
        "product_account_group_price_staging",
        "shape_track_product_dim",
    ];

    /**
     * Delay import after default entity types are imported.
     *
     * @var string[]
     */
    private $afterDefaultEntityTypes = [
        "entity_level_permission",
        "privilege",
    ];

    /**
     * Add column value to filename suffix.
     * @var string[]
     */
    private $fileSuffixColumn = [
        "privilege" => [
            "action_type",
            "action_code",
        ],
        "entity_attribute" => [
            "sort_order",
        ],
        "user_role_entity" => [
            "core_user_id",
            "role_id",
        ],
    ];

    /**
     * Limit delete to tables.
     *
     * @var string[]
     */
    private $limitDelete = [
        "entity_attribute",
        "list_view_attribute",
    ];

    /**
     * Holds json data during import.
     *
     * @var array
     */
    private $exportArray = [];

    /**
     * Loaded data.
     *
     * @var string
     */
    private $data = [];

    /**
     * Holds indexes to be added.
     *
     * @var array
     */
    private $toAddIndexes = [];

    /**
     * Holds constraints to be added.
     *
     * @var array
     */
    private $toAddConstraints = [];

    /**
     * Holds data to rerun during import.
     *
     * @var array
     */
    private $importRerun = [];

    /**
     * Save array of merged records.
     *
     * @var array
     */
    private $importMerged = [];

    /**
     * Import order of tables.
     * @var array[]
     */
    private $importOrder = [];

    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;

    public function initialize()
    {
        parent::initialize();
        $this->databaseContext = $this->container->get("database_context");
    }

    /**
     * @return bool
     */
    public function exportMainTablesStructure()
    {
        print "\nEXPORTING MAIN TABLES STRUCTURE:\n";

        foreach ($this->getMainTables() as $table) {
            $this->exportTableStructure($table);
        }

        return true;
    }

    /**
     * @param false $saveAsArray
     * @return bool
     * @throws \Exception
     */
    public function exportMainTablesContent($saveToFile = true)
    {
        print "\nEXPORTING MAIN TABLES CONTENT:\n";

        foreach ($this->getMainTables() as $table) {
            if (in_array($table, $this->excludeMainTablesContent)) {
                continue;
            }

            if (!$saveToFile && !$this->isDefaultImport) {
                if (in_array($table, $this->skipImport)) {
                    continue;
                }
            }

            $this->exportTable($saveToFile, $table, "AppBundle");
        }

        return true;
    }

    /**
     * @return bool
     */
    public function exportDefaultEntityTablesStructure()
    {
        print "\nEXPORTING NON ENTITY TABLES STRUCTURE:\n";

        try {
            $tables = $this->getDefaultEntityTypesTables();
            foreach ($tables as $table) {
                $this->exportTableStructure($table["table"], $table["bundle"]);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * @param bool $saveToFile
     * @return bool
     */
    public function exportDefaultEntityTablesContent($saveToFile = true)
    {
        $this->insertIds = true;
        print "\nEXPORTING DEFAULT ENTITY CONTENT:\n";

        foreach ($this->getDefaultEntityTypesTables() as $tableData) {
            $table = $tableData["table"];
            $bundle = $tableData["bundle"];

            $this->exportTable($saveToFile, $table, $bundle);
        }
        return true;
    }

    /**
     * Export custom entities.
     */
    public function exportCustomEntities($uid = null)
    {
        $this->insertIds = false;

        $additional = "";
        if (!empty($uid)) {
            $additional = " OR uid = '{$uid}' ";
        }

        $query = "SELECT entity_table,bundle FROM entity_type WHERE sync_content=1 AND (is_custom=1 OR bundle='{$_ENV["PROJECT_BUNDLE"]}' {$additional});";
        $tables = $this->databaseContext->executeQuery($query);
        if (!empty($tables)) {
            foreach ($tables as $table) {
                $this->exportTable(true, $table["entity_table"], $table["bundle"] ?? $_ENV["PROJECT_BUNDLE"]);
            }
        }

        foreach ($this->getMainTables() as $table) {
            $isValid = true;
            foreach ($this->mergeTables as $mainTable => $mergeTables) {
                if (isset($mergeTables[$table])) {
                    $isValid = false;
                    break;
                }
            }
            if (!$isValid) {
                continue;
            }
            $query = "SHOW COLUMNS FROM {$table} LIKE 'is_custom';";
            $isCustom = !empty($this->databaseContext->executeQuery($query));

            if (!$isCustom) {
                continue;
            }

            $preparedConstraints = $this->getTablesForeignKeysConstraintsForUid($table);

            $query = "SELECT * FROM {$table}";
            $hasBundleColumnQuery = "SHOW COLUMNS FROM {$table} LIKE 'bundle';";
            $hasBundleColumn = !empty($this->databaseContext->executeQuery($hasBundleColumnQuery));
            if ($hasBundleColumn) {
                $query .= " WHERE bundle='{$_ENV["PROJECT_BUNDLE"]}' OR is_custom=1";
            }
            $query .= ";";
            $tableContent = $this->databaseContext->executeQuery($query);

            foreach ($tableContent as $row) {
                $export = false;
                if (isset($row["is_custom"]) && $row["is_custom"] == 1) {
                    $export = true;
                    $path = $this->getConfigDirectoryPath("content/{$table}", $_ENV["PROJECT_BUNDLE"]);
                } elseif (isset($this->checkParentBundle[$table]) && !empty($row[$this->checkParentBundle[$table]["column"]])) {
                    $parentBundle = $this->getBundleByParent($table, $row);
                    $path = $this->getConfigDirectoryPath("content/{$table}", $parentBundle);
                    if ($parentBundle == $_ENV["PROJECT_BUNDLE"]) {
                        $export = true;
                    }
                }
                if ($export) {
                    $this->exportTableRow(true, $path, $table, $preparedConstraints, $row);
                }
            }
        }
    }

    /**
     * Export single entity.
     *
     * @param $table
     * @param $id
     * @param bool $resetData
     * @return bool
     * @throws \Exception
     */
    public function exportEntityByTableAndId($table, $id, $resetData = false)
    {
        if (isset($_ENV["IS_PRODUCTION"]) && $_ENV["IS_PRODUCTION"] == 1) {
            return true;
        }
        $preparedConstraints = $this->getTablesForeignKeysConstraintsForUid($table);

        $row = $this->getEntityRecordById($table, $id);

        if (isset($row["is_custom"]) && $row["is_custom"] == 1) {
            $path = $this->getConfigDirectoryPath("content/{$table}", $_ENV["PROJECT_BUNDLE"]);
        } else {
            $projectPath = $this->getConfigDirectoryPath("content/{$table}", $_ENV["PROJECT_BUNDLE"]);
            $md5 = $this->generateFileHash($table, $row);
            if (file_exists("{$projectPath}/{$table}-$md5.json")) {
                //todo sredit
                unlink("{$projectPath}/{$table}-$md5.json");
            }

            if (isset($this->checkParentBundle[$table]) && !empty($row[$this->checkParentBundle[$table]["column"]])) {
                $parentBundle = $this->getBundleByParent($table, $row);
                $path = $this->getConfigDirectoryPath("content/{$table}", $parentBundle);
            } elseif (isset($row["bundle"])) {
                $path = $this->getConfigDirectoryPath("content/{$table}", $row["bundle"]);
            } else {
                $path = $this->getConfigDirectoryPath("content/{$table}", "AppBundle");
            }
        }

        $this->exportTableRow(true, $path, $table, $preparedConstraints, $row, $resetData);

        return true;
        /*if (!empty($row)) {
            try {
                $md5 = $this->generateFileHash($table, $row);

                $isCustom = 1;
                if (isset($row["is_custom"])) {
                    $isCustom = $row["is_custom"];
                }
                $otherTableData = $this->getIsCustomByMapping($table, $row);
                $otherTableBundle = null;
                if (!empty($otherTableData)) {
                    if (isset($otherTableData["bundle"]) && !empty($otherTableData["bundle"])) {
                        $otherTableBundle = $otherTableData["bundle"];
                    }
                    if (isset($otherTableData["is_custom"]) && !empty($otherTableData["is_custom"])) {
                        $isCustom = $otherTableData["is_custom"];
                        //TODO ako je gore custom a ovdje nije, hoce li ga to pregaziti
                    }
                }


                if ($isCustom) {
                    $path = $this->getConfigDirectoryPath("content/{$table}", $_ENV["PROJECT_BUNDLE"]);
                } else {
                    // Check if custom exists and remove to to prevent override
                    $path = $this->getConfigDirectoryPath("content/{$table}", $_ENV["PROJECT_BUNDLE"]);

                    if (isset($this->fileSuffixColumn[$table]) && isset($row[$this->fileSuffixColumn[$table]])) {
                        $md5 .= "-" . $row[$this->fileSuffixColumn[$table]];
                    }

                    if (file_exists("{$path}/{$table}-$md5.json")) {
                        unlink("{$path}/{$table}-$md5.json");
                    }

                    // Save to bundle
                    if (isset($row["bundle"])) {
                        $path = $this->getConfigDirectoryPath("content/{$table}", $row["bundle"]);
                    } else {
                        if (!empty($otherTableBundle)) {
                            $path = $this->getConfigDirectoryPath("content/{$table}", $otherTableBundle);
                        } else {
                            $query = "SELECT bundle FROM entity_type WHERE entity_table='{$table}'";
                            $bundle = $this->databaseContext->getSingleEntity($query);
                            if (!empty($bundle)) {
                                $path = $this->getConfigDirectoryPath("content/{$table}", $bundle["bundle"]);
                            } else {
                                $path = $this->getConfigDirectoryPath("content/{$table}");
                            }
                        }
                    }
                }

                $this->convertForeignKeysIdToUid($preparedConstraints, $row);

                $this->saveJson($path, "{$table}-$md5.json", $row);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                dump($e->getMessage());
                die;
            }
        }
        return true;*/

    }

    /**
     * Executes import of main tables.
     *
     * @return bool
     */
    public function importMainTablesStructure()
    {
        $this->importRerun = [];
        $this->debug = 0;

        print "\nIMPORTING MAIN TABLES STRUCTURE:\n";

        $path = $this->getConfigDirectoryPath("structure");
        $files = $this->listConfigFiles($path);
        if (!empty($files)) {
            foreach ($files as $path) {
                $this->importTable($path);
            }
        }

        if (!empty($this->importRerun)) {
            $toImport = array_sum(array_map("count", $this->importRerun));
            $this->importReruns($toImport, true);
        }

        $this->addTableIndexes();
        $this->addTableConstraints();

        return true;
    }

    /**
     * Execute main tables content import.
     *
     * @return bool
     * @throws \Exception
     */
    public function importMainTablesContent()
    {
        $this->importRerun = [];
        $this->importingMerged = false;
        $this->isDefaultImport = true;
        $this->debug = 0;

//        $this->exportMainTablesContent(false);

        print "\nIMPORTING MAIN TABLES CONTENT:\n";

        foreach ($this->getMainTables() as $table) {
            if (in_array($table, $this->excludeMainTablesContent)) {
                continue;
            }
            foreach ($this->getContainer()->getParameter('kernel.bundles') as $bundle => $namespace) {
                if ($bundle == $_ENV["PROJECT_BUNDLE"]) {
                    continue;
                }
                $files = $this->listConfigFiles($this->getConfigDirectoryPath("content/{$table}", $bundle));
                if (!empty($files)) {
                    foreach ($files as $path) {
                        $this->importFile($table, $path);
                    }
                }
            }
        }

        if (!empty($this->importRerun)) {
            $toImport = array_sum(array_map("count", $this->importRerun));
            $this->importReruns($toImport);
        }

        if (!empty($this->importMerged)) {
            $this->importingMerged = true;
            $toImport = array_sum(array_map("count", $this->importMerged));
            $this->importMerged($toImport);
        }
        return true;
    }

    /**
     * Execute import of files that were not successful on initial run.
     *
     * @param $toImportPrev
     */
    public function importReruns($toImportPrev, $isTable = false)
    {
        if (!empty($this->importRerun)) {
            foreach ($this->importRerun as $table => $files) {
                $this->importRerun[$table] = [];
                unset($this->importRerun[$table]);

                foreach ($files as $path) {
                    if ($isTable) {
                        $this->importTable($path);
                    } else {
                        $this->importFile($table, $path);
                    }
                }
            }
        }
        if (!empty($this->importRerun)) {
            $toImport = array_sum(array_map("count", $this->importRerun));
            if ($toImport == $toImportPrev) {
                $this->debug = 1;
            }
            $this->importReruns($toImport, $isTable);
        }
    }

    /**
     * Execute import of files that were not successful on initial run.
     *
     * @param $toImportPrev
     */
    public function importMerged($toImportPrev)
    {
        if (!empty($this->importMerged)) {
            foreach ($this->importMerged as $table => $files) {
                $this->importMerged[$table] = [];
                unset($this->importMerged[$table]);

                foreach ($files as $path) {
                    $this->importFile($table, $path);
                }
            }
        }
        if (!empty($this->importMerged)) {
            $toImport = array_sum(array_map("count", $this->importMerged));
            if ($toImport == $toImportPrev) {
                $this->debug = 1;
            }
            $this->importMerged($toImport);
        }
    }

    /**
     * Executes import of main tables.
     *
     * @return bool
     */
    public function importDefaultEntityTablesStructure()
    {
        $this->importRerun = [];
        $this->debug = 0;

        print "\nIMPORTING DEFAULT ENTITY TABLES STRUCTURE:\n";

        $tables = $this->getDefaultEntityTypesTables();
        foreach ($tables as $table) {
            $path = $this->getConfigDirectoryPath("structure", $table["bundle"]);
            $files = $this->listConfigFiles($path);
            if (!empty($files)) {
                foreach ($files as $path) {
                    $this->importTable($path);
                }
            }
        }

        if (!empty($this->importRerun)) {
            $toImport = array_sum(array_map("count", $this->importRerun));
            $this->importReruns($toImport, true);
        }

        $this->addTableIndexes();
        $this->addTableConstraints();

        return true;
    }

    /**
     * Execute default entities content import.
     *
     * @return bool
     * @throws \Exception
     */
    public function importDefaultEntitiesTablesContent()
    {
        $this->importRerun = [];
        $this->isDefaultImport = true;
        $this->debug = 0;

//        $this->exportMainTablesContent(false);

        print "\nIMPORTING DEFAULT ENTITIES CONTENT:\n";
        foreach ($this->getDefaultEntityTypesTables() as $tableData) {
            $table = $tableData["table"];
            $bundle = $tableData["bundle"];
            if (in_array($table, $this->afterDefaultEntityTypes)) {
                continue;
            }

            $path = $this->getConfigDirectoryPath("content/{$table}", $bundle);
            $files = $this->listConfigFiles($path);
            if (!empty($files)) {
                foreach ($files as $path) {
                    $this->importFile($table, $path);
                }
            }
        }

        if (!empty($this->importRerun)) {
            $toImport = array_sum(array_map("count", $this->importRerun));
            $this->importReruns($toImport);
        }

        $tables = $this->afterDefaultEntityTypes;
        foreach ($tables as $table) {
            $path = $this->getConfigDirectoryPath("content/{$table}");
            $files = $this->listConfigFiles($path);
            if (!empty($files)) {
                foreach ($files as $path) {
                    $this->importFile($table, $path);
                }
            }
        }
        return true;
    }

    /**
     * Imports single table.
     *
     * @param $path
     */
    private function importTable($path)
    {
        $data = json_decode(file_get_contents($path), true);

        $fileName = basename($path);
        $parts = explode(".", $fileName);
        $table = $parts[0];
        print "\t{$table}\n";

        $query = "SHOW TABLES LIKE '{$table}';";
        $res = $this->databaseContext->executeQuery($query);

        if (empty($res)) {
            // Create table
            $primary = null;

            $createQuery = "CREATE TABLE {$table} (";
            foreach ($data["table_structure"] as $key => $column) {
                $null = "NOT NULL";
                if ($column["Null"] == "YES") {
                    $null = "NULL";
                }

                $createQuery .= "{$column["Field"]} {$column["Type"]} {$null} {$column["Extra"]}";
                if (!empty($column["Default"])) {
                    $createQuery .= " DEFAULT '{$column["Default"]}'";
                }
                $createQuery .= ",";

                if ($column["Key"] == "PRI") {
                    $primary = $column;
                }
            }
            if (!empty($primary)) {
                $createQuery .= "PRIMARY KEY (`{$primary["Field"]}`) USING BTREE,";
            }
            $createQuery .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8";
            if (isset($data["table_status"]) && isset($data["table_status"]["Auto_increment"]) && !empty($data["table_status"]["Auto_increment"])) {
                $createQuery .= " AUTO_INCREMENT={$data["table_status"]["Auto_increment"]}";
            }
            $createQuery .= ";";
            $createQuery = str_replace(",) ENGINE", ") ENGINE", $createQuery);

            try {
                $this->databaseContext->executeNonQuery($createQuery);

                $this->toAddIndexes[] = [
                    "table_name" => $table,
                    "indexes" => $data["indexes"],
                ];
                $this->toAddConstraints[] = [
                    "table_name" => $table,
                    "constraints" => $data["constraints"],
                ];
            } catch (\Exception $e) {
                if ($this->debug) {
                    dump($e->getMessage());
                    die;
                }
                if (!isset($this->importRerun[$table])) {
                    $this->importRerun[$table] = [];
                }
                $this->importRerun[$table][] = $path;
            }
        } else {
            //Update table
        }
    }

    /**
     * Add indexes after tables are created.
     */
    private function addTableIndexes()
    {
        // Add indexes
        if (!empty($this->toAddIndexes)) {
            foreach ($this->toAddIndexes as $key => $toIndex) {
                $table = $toIndex["table_name"];
                $indexes = $toIndex["indexes"];

                $preparedIndexes = [];

                foreach ($indexes as $index) {
                    $type = "";
                    if ($index["Key_name"] == "PRIMARY") {
                        continue;
                    } elseif ($index["Non_unique"] == "0") {
                        $type = "UNIQUE";
                    }

                    if (!isset($preparedIndexes[$index["Key_name"]])) {
                        $preparedIndexes[$index["Key_name"]] = [
                            "type" => $type,
                            "Index_type" => $index["Index_type"],
                            "columns" => [],
                        ];
                    }

                    $preparedIndexes[$index["Key_name"]]["columns"][] = $index["Column_name"];
                }

                // Add indexes
                if (!empty($preparedIndexes)) {
                    foreach ($preparedIndexes as $name => $data) {
                        $columns = implode(",", $data["columns"]);
                        $query = "ALTER TABLE {$table} ADD {$data["type"]} KEY ({$columns}) USING {$data["Index_type"]};";
                        try {
                            $this->databaseContext->executeNonQuery($query);
                            unset($this->toAddIndexes[$key]);
                        } catch (\Exception $e) {

                        }
                    }
                }
            }
        }
    }

    /**
     * Add constraints after tables are created.
     */
    private function addTableConstraints()
    {
        // Add constraints
        if (!empty($this->toAddConstraints)) {
            foreach ($this->toAddConstraints as $key => $toConstraint) {
                $table = $toConstraint["table_name"];
                $constraints = $toConstraint["constraints"];

                foreach ($constraints as $constraint) {
                    $query = "ALTER TABLE {$table} ADD CONSTRAINT {$constraint["CONSTRAINT_NAME"]} FOREIGN KEY ({$constraint["COLUMN_NAME"]}) REFERENCES {$constraint["REFERENCED_TABLE_NAME"]}(id) ON DELETE CASCADE ON UPDATE CASCADE";
                    try {
                        $this->databaseContext->executeNonQuery($query);
                        unset($this->toAddConstraints[$key]);
                    } catch (\Exception $e) {

                    }
                }
            }
        }
    }

    /**
     * Import single file.
     *
     * @param $table
     * @param $path
     * @return bool
     */
    public function importFile($table, $path)
    {
        if (!file_exists($path)) {
            return false;
        }
        print "\n\t{$path}" . "\t" . memory_get_usage();

        $jsonContent = file_get_contents($path);

        $row = json_decode($jsonContent, true);
        if (empty($row)) {
            var_dump($jsonContent);
            die;
        }
        if (isset($this->exportArray[basename($path)])) {
            $isSameMain = true;
            $isSameMerged = true;
            $mergeData = [];
            if (isset($row["merged_data"])) {
                $mergeData = $row["merged_data"];
                unset($row["merged_data"]);
                unset($row["merged_data"]);
            }
            $mergeDataExisting = [];
            $mergeDataExistingByKey = [];
            if (isset($this->exportArray[basename($path)]["merged_data"])) {
                $mergeDataExisting = $this->exportArray[basename($path)]["merged_data"];

                /**
                 * Small fix for entity_attribute
                 */
                if (!empty($mergeDataExisting) && isset($mergeDataExisting["entity_attribute"])) {
                    foreach ($mergeDataExisting["entity_attribute"] as $mergeDataExistingItem)
                        $mergeDataExistingByKey["entity_attribute"]["{$mergeDataExistingItem["attribute_set_id"]}{$mergeDataExistingItem["attribute_group_id"]}{$mergeDataExistingItem["attribute_id"]}"] = $mergeDataExistingItem;
                }

                unset($this->exportArray[basename($path)]["merged_data"]);
                unset($this->exportArray[basename($path)]["merged_data"]);
            }
            if (!empty(array_diff_assoc($row, $this->exportArray[basename($path)]))) {
                $isSameMain = false;
            } else {
                if (!empty($mergeData) && !empty($mergeDataExisting)) {
                    foreach ($mergeData as $mergeTable => $mergeRows) {
                        if (!isset($mergeDataExisting[$mergeTable])) {
                            $isSameMerged = false;
                        }
                        if (count($mergeData[$mergeTable]) != count($mergeDataExisting[$mergeTable])) {
                            $isSameMerged = false;
                        }
                        if ($isSameMerged) {
                            foreach ($mergeRows as $key => $mergeRowData) {
                                if (!isset($mergeDataExisting[$mergeTable][$key])) {
                                    $isSameMerged = false;
                                } elseif (!empty(array_diff_assoc($mergeRowData, $mergeDataExisting[$mergeTable][$key]))) {

                                    if (!empty($mergeDataExistingByKey) && $mergeTable == "entity_attribute") {
                                        if (!isset($mergeDataExistingByKey[$mergeTable]["{$mergeRowData["attribute_set_id"]}{$mergeRowData["attribute_group_id"]}{$mergeRowData["attribute_id"]}"])) {
                                            $isSameMerged = false;
                                        }
                                    } else {
                                        $isSameMerged = false;
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $isSameMerged = false;
                }
            }

            // Is regular import
            if (!$isSameMain || !$isSameMerged) {
                // Do UPDATE
                $existingRowId = $this->loadExistingRowId($table, $this->exportArray[basename($path)]);
                $this->updateRecord($table, $existingRowId, $row, $isSameMain, $isSameMerged, $mergeData, $path);
            } else {
                // Nothing to update
            }
        } else {

            $inserts = [];

            // Insert
            if (isset($row["id"]) && isset($row["uid"])) {
                unset($row["id"]);
            }

            $mergeData = [];
            if (isset($row["merged_data"])) {
                $mergeData = $row["merged_data"];
                unset($row["merged_data"]);
                unset($row["merged_data"]);
            }

            if ($this->importingMerged) {
                if (!empty($mergeData)) {
                    foreach ($mergeData as $mergeTable => $mergeRows) {
                        foreach ($mergeRows as $mergeRowData) {
                            $columns = [];
                            $values = [];
                            $this->convertForeignKeysUidToId($mergeTable, $this->getTablesForeignKeysConstraintsForUid($mergeTable), $mergeRowData);
                            foreach ($mergeRowData as $column => $value) {
                                $columns[] = $column;
                                $values[] = $value;
                            }
                            $columns = implode(",", $columns);
                            $values = implode(",", array_map(function ($value) {

                                if (is_numeric($value)) {
                                    return $value;
                                } elseif (is_string($value)) {
                                    $value = addslashes($value);
                                    return "'{$value}'";
                                } elseif (empty($value)) {
                                    return "null";
                                } else {
                                    return "'" . (str_replace("'", "\'", trim($value))) . "'";
                                }
                            }, $values));
                            $inserts[] = str_replace("'null'", "null", "REPLACE INTO {$mergeTable} ({$columns}) VALUES ({$values});");
                        }
                    }
                }
            } else {
                if (empty($this->loadExistingRowId($table, $row))) {

                    $this->convertForeignKeysUidToId($table, $this->getTablesForeignKeysConstraintsForUid($table), $row);

                    $columns = [];
                    $values = [];
                    foreach ($row as $column => $value) {
                        $columns[] = $column;
                        $values[] = $value;
                    }
                    $columns = implode(",", $columns);
                    $values = implode(",", array_map(function ($value) {
                        if (is_numeric($value)) {
                            return $value;
                        } elseif (is_string($value)) {
                            $value = addslashes($value);
                            return "'{$value}'";
                        } elseif (empty($value)) {
                            return "null";
                        } else {
                            return "'" . (str_replace("'", "\'", trim($value))) . "'";
                        }
                    }, $values));

                    if (isset($row["id"])) {
                        $inserts[] = str_replace("'null'", "null", "INSERT INTO {$table} ({$columns}) VALUES ({$values}) ON DUPLICATE KEY UPDATE id='{$row["id"]}';");
                    } else {
                        $ignore = "";
//                        if($table == "navigation_link"){
//                            $ignore = " IGNORE ";
//                        }
                        $inserts[] = str_replace("'null'", "null", "INSERT {$ignore} INTO {$table} ({$columns}) VALUES ({$values});");
                    }

                    if (!empty($mergeData) && !in_array($path, $this->importMerged)) {
                        if (!isset($this->importMerged[$table])) {
                            $this->importMerged[$table] = [];
                        }
                        if (!in_array($path, $this->importMerged[$table])) {
                            $this->importMerged[$table][] = $path;
                        }
                    }
                }
            }
        }

        if (!empty($inserts)) {
            try {
                $this->databaseContext->executeNonQuery(implode("", $inserts));
            } catch (\Exception $e) {
                if ($this->debug) {
                    dump($table);
                    dump($path);
                    dump($e->getMessage());
                    die;
                }
                if ($this->importingMerged) {
                    if (!isset($this->importMerged[$table])) {
                        $this->importMerged[$table] = [];
                    }
                    $this->importMerged[$table][] = $path;
                } else {
                    if (!isset($this->importRerun[$table])) {
                        $this->importRerun[$table] = [];
                    }
                    $this->importRerun[$table][] = $path;
                }

                return false;
            }
        }

        return true;
    }

    private function loadExistingRowId($table, $row)
    {
        if (isset($row["uid"])) {
            $query = "SELECT id FROM {$table} WHERE uid='{$row["uid"]}';";
        } else {
            $this->convertForeignKeysUidToId($table, $this->getTablesForeignKeysConstraintsForUid($table), $row);

            $conditions = [];
            foreach ($row as $column => $value) {
                if (is_string($value) && !is_numeric($value)) {
                    $value = addslashes($value);
                } elseif (empty($value)) {
                    $value = "null";
                }
                $conditions[] = " {$column}='{$value}' ";
            }
            $conditions = implode("AND", $conditions);
            $query = "SELECT id FROM {$table} WHERE {$conditions};";
            $query = str_replace("'null'", "null", $query);
        }

        $existingRow = $this->databaseContext->executeQuery($query);

        if (empty($existingRow)) {
            return null;
        }
        if (count($existingRow) > 1) {
            throw new \Exception("Multiple rows loaded");
        }
        if (!isset($existingRow[0])) {
            dump($row);
            dump($query);
            die;
        }
        return $existingRow[0]["id"];
    }

    private function updateRecord($table, $id, $row, $isSameMain, $isSameMerged, $mergeData, $path)
    {
        try {
            if (!$isSameMain) {
                $this->convertForeignKeysUidToId($table, $this->getTablesForeignKeysConstraintsForUid($table), $row);

                $sets = [];
                foreach ($row as $column => $value) {
                    if ($value == null) {
                        $value = "null";
                    } elseif (is_string($value) && !is_numeric($value)) {
                        $value = addslashes($value);
                        $value = "'{$value}'";
                    }
                    $sets[] = " {$column}={$value} ";
                }
                $sets = implode(",", $sets);

                $query = "UPDATE {$table} SET {$sets} WHERE id='{$id}'";
                $query = str_replace("'null'", "null", $query);
                $this->databaseContext->executeNonQuery($query);
            }
            if (!empty($mergeData) && !$isSameMerged) {
                $existingId = $this->loadExistingRowId($table, $row);
                $inserts = [];
                foreach ($mergeData as $mergeTable => $mergeRows) {
                    $column = $this->mergeTables[$table][$mergeTable]["column"];
                    $query = "DELETE FROM {$mergeTable} WHERE {$column}='$existingId';";
                    $this->databaseContext->executeNonQuery($query);

                    foreach ($mergeRows as $mergeRowData) {
                        $columns = [];
                        $values = [];
                        $this->convertForeignKeysUidToId($mergeTable, $this->getTablesForeignKeysConstraintsForUid($mergeTable), $mergeRowData);
                        foreach ($mergeRowData as $column => $value) {
                            $columns[] = $column;
                            $values[] = $value;
                        }
                        $columns = implode(",", $columns);
                        $values = implode(",", array_map(function ($value) {

                            if (is_numeric($value)) {
                                return $value;
                            } elseif (is_string($value)) {
                                $value = addslashes($value);
                                return "'{$value}'";
                            } elseif (empty($value)) {
                                return "null";
                            } else {
                                return "'" . (str_replace("'", "\'", trim($value))) . "'";
                            }
                        }, $values));
                        $inserts[] = str_replace("'null'", "null", "INSERT INTO {$mergeTable} ({$columns}) VALUES ({$values});");
                    }
                }
                if (!empty($inserts)) {
                    try {
//                        $this->databaseContext->executeNonQuery(implode("",$inserts));
                        foreach ($inserts as $insert) {
                            $this->databaseContext->executeNonQuery($insert);
                        }
                        $this->errors["changes"]["$table"][] = count($inserts);
                    } catch (\Exception $e) {
                        $this->errors[$path] = $e->getMessage();
                    }
                }
            }
        } catch (\Exception $e) {
//            dump($e->getMessage());
//            dump($row);
//            die;
        }
    }

    /**
     * Collect import files to import.
     */
    public function importConfiguration()
    {
        print "\nIMPORTING CONFIGURATION:\n";

        foreach ($this->getMainTables() as $table) {
            if (in_array($table, $this->skipImport)) {
                continue;
            }
            $this->importOrder[$table] = [];
        }

        $this->exportMainTablesContent(false);

        $files = [];
        foreach ($this->container->getParameter('kernel.bundles') as $bundle => $namespace) {
            if ($bundle == $_ENV["PROJECT_BUNDLE"]) {
                continue;
            }
            $files = array_merge($files, $this->listConfigFiles($this->getConfigDirectoryPath("content", $bundle), $bundle));
        }
        $files = array_merge($files, $this->listConfigFiles($this->getConfigDirectoryPath("content", $_ENV["PROJECT_BUNDLE"]), $_ENV["PROJECT_BUNDLE"]));

        foreach ($files as $key => $path) {
            foreach ($this->importOrder as $table => $data) {
                if (strpos($path, "/{$table}-") !== false) {
                    $basename = basename($path);
                    if (!isset($this->importOrder[$table][$basename])) {
                        $this->importOrder[$table][$basename] = "";
                    }
                    $this->importOrder[$table][$basename] = $path;
                }
            }
            $files[$key] = null;
            unset($files[$key]);
        }

        $this->runImport();

        if (!empty($this->errors)) {
            dump($this->errors);
        }

        /**
         * Clean caches
         */
        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $this->cacheManager->invalidateCacheByTag("navigation_link");
    }

    /**
     * Run main import.
     *
     * @return bool
     * @throws \Exception
     */
    public function runImport()
    {
        foreach ($this->importOrder as $configurationTable => $data) {
            foreach ($data as $path) {
                $this->importFile($configurationTable, $path);
            }
        }

        if (!empty($this->importRerun)) {
            $toImport = array_sum(array_map("count", $this->importRerun));
            $this->importReruns($toImport);
        }

        if (!empty($this->importMerged)) {
            $this->importingMerged = true;
            $toImport = array_sum(array_map("count", $this->importMerged));
            $this->importMerged($toImport);
        }
        return true;
    }

    /**
     * Lists files in path.
     *
     * @param $path
     * @param string $bundle
     * @return array|mixed
     */
    private function listConfigFiles($path, $bundle = "AppBundle")
    {
        if (!file_exists($path)) {
            return [];
        }

        // Separate method cause of recursion.
        return $this->getDirContents($path, $bundle);
    }

    /**
     * Gets all files in directory with subdirectories.
     *
     * @param $path
     * @param $bundle
     * @param array $results
     * @return array|mixed
     */
    private function getDirContents($path, $bundle, &$results = array())
    {
        $files = scandir($path);
        foreach ($files as $key => $value) {
            if ($value == "." || $value == "..") {
                continue;
            }

            $filePath = str_replace("//", "/", $path . DIRECTORY_SEPARATOR . $value);
            if (!is_dir($filePath) && is_file($filePath)) {
                // Check if custom exists
                $customFilePath = str_replace($bundle, $_ENV["PROJECT_BUNDLE"], $filePath);
                if (file_exists($customFilePath)) {
                    $results[] = $customFilePath;
                } else {
                    $results[] = $filePath;
                }
            } else {
                $this->getDirContents($filePath, $bundle, $results);
            }
        }

        return $results;
    }

    /**
     * Get list of non entity tables.
     *
     * @return array
     */
    private function getMainTables()
    {
        $preparedTables = [];

        $query = "SELECT DISTINCT(TABLE_NAME) FROM ssinformation.key_column_usage WHERE table_name NOT IN (SELECT entity_table FROM entity_type) AND table_schema = '{$_ENV["DATABASE_NAME"]}' AND substring(TABLE_NAME, 1, 1) NOT IN ('_');";
        $tables = $this->databaseContext->executeQuery($query);

        if (!empty($tables)) {
            foreach ($tables as $table) {
                //if(in_array($table["TABLE_NAME"],Array("entity_type","attribute_set","attribute","page","page_block","list_view","list_view_attribute","attribute_group","entity_attribute","navigation_link"))){
                $preparedTables[] = $table["TABLE_NAME"];
                //}
            }
        }

        return $preparedTables;
    }

    /**
     * Gets entity tables to sync.
     *
     * @return array
     */
    private function getDefaultEntityTypesTables()
    {
        $preparedTables = [];

        $query = "SELECT entity_table,bundle FROM entity_type WHERE sync_content=1;";
        $tables = $this->databaseContext->getAll($query);

        if (!empty($tables)) {
            foreach ($tables as $table) {
                $preparedTables[] = [
                    "table" => $table["entity_table"],
                    "bundle" => $table["bundle"],
                ];
            }
        }

        return $preparedTables;
    }

    /**
     * Export single table structure.
     *
     * @param $table
     * @param string $bundle
     */
    private function exportTableStructure($table, $bundle = "AppBundle")
    {
        $path = $this->getConfigDirectoryPath("structure", $bundle);

        print "\t{$path}/{$table}.json\n";

        $query = "DESCRIBE {$table};";
        $tableStructure = $this->databaseContext->executeQuery($query);

        $query = "SHOW INDEX FROM {$table};";
        $indexes = $this->databaseContext->executeQuery($query);

        $query = "SELECT * FROM ssinformation.key_column_usage WHERE referenced_table_name is not null AND table_name = '{$table}' AND table_schema = '{$_ENV["DATABASE_NAME"]}';";
        $constraints = $this->databaseContext->executeQuery($query);

        $query = "SHOW CREATE TABLE {$table};";
        $createTable = $this->databaseContext->executeQuery($query);

        $query = "SHOW TABLE STATUS FROM {$_ENV["DATABASE_NAME"]} WHERE `name` LIKE '{$table}';";
        $tableStatus = $this->databaseContext->executeQuery($query);

        $structure = [
            "create_table_code" => $createTable[0]["Create Table"],
            "table_status" => $tableStatus[0],
            "table_name" => $table,
            "table_structure" => $tableStructure,
            "indexes" => $indexes,
            "constraints" => $constraints,
        ];

        $this->saveJson($path, "{$table}.json", $structure);
    }

    /**
     * Build full path to config files.
     *
     * @param $dir
     * @param string $bundle
     * @return string|string[]
     */
    public function getConfigDirectoryPath($dir, $bundle = "AppBundle")
    {
        $path = $_ENV["WEB_PATH"] . "/../src/{$bundle}/Resources/config/db/{$dir}";
        if (file_exists($_ENV["WEB_PATH"] . "/../src/{$bundle}") && !file_exists($path)) {
            mkdir($path, 0755, true);
        }
        return str_replace("//", "/", $_ENV["WEB_PATH"] . "/../src/{$bundle}/Resources/config/db/{$dir}");
    }

    /**
     * @param $path
     * @param $filename
     * @param $array
     */
    private function saveJson($path, $filename, $array)
    {
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
        file_put_contents("{$path}/{$filename}", json_encode($array));

        return true;
    }

    /**
     * Export single table.
     *
     * @param $saveToFile
     * @param $table
     * @param string $bundle
     */
    private function exportTable($saveToFile, $table, $bundle = "AppBundle")
    {
        if (!empty($this->mergeTables)) {
            foreach ($this->mergeTables as $mainTable => $mergeTables) {
                if (isset($mergeTables[$table])) {
                    return false;
                }
            }
        }

        print "\nExporting content from table {$table}...\n";

        $preparedConstraints = $this->getTablesForeignKeysConstraintsForUid($table);

        $query = "SELECT * FROM {$table};";
        $tableContent = $this->databaseContext->executeQuery($query);
        foreach ($tableContent as $row) {
            if (isset($row["is_custom"]) && $row["is_custom"] == 1) {
                $path = $this->getConfigDirectoryPath("content/{$table}", $_ENV["PROJECT_BUNDLE"]);
            } else {
                if (isset($this->checkParentBundle[$table]) && !empty($row[$this->checkParentBundle[$table]["column"]])) {
                    $parentBundle = $this->getBundleByParent($table, $row);
                    $path = $this->getConfigDirectoryPath("content/{$table}", $parentBundle);
                } elseif (isset($row["bundle"])) {
                    $path = $this->getConfigDirectoryPath("content/{$table}", $row["bundle"]);
                } else {
                    $path = $this->getConfigDirectoryPath("content/{$table}", $bundle);
                }
            }
            $this->exportTableRow($saveToFile, $path, $table, $preparedConstraints, $row);
        }
    }

    /**
     * Get parent bundle.
     *
     * @param $table
     * @param $row
     * @return mixed
     */
    private function getBundleByParent($table, $row)
    {
        $bundle = null;
        if (isset($this->checkParentBundle[$table]) && isset($row[$this->checkParentBundle[$table]["column"]])) {
            $query = "SELECT * FROM {$this->checkParentBundle[$table]["table"]} WHERE id='{$row[$this->checkParentBundle[$table]["column"]]}';";
            $newRow = $this->databaseContext->getSingleEntity($query);
            if (isset($newRow["bundle"])) {
                $bundle = $newRow["bundle"];
            }
            if (isset($this->checkParentBundle[$this->checkParentBundle[$table]["table"]]) && isset($this->checkParentBundle[$this->checkParentBundle[$table]["table"]])) {
                $bundle = $this->getBundleByParent($this->checkParentBundle[$table]["table"], $newRow);
            }
        }
        return $bundle;
    }

    /**
     * Export single table row.
     *
     * @param $saveToFile
     * @param $path
     * @param $table
     * @param $preparedConstraints
     * @param $row
     * @param bool $resetData
     * @return bool
     * @throws \Exception
     */
    private function  exportTableRow($saveToFile, $path, $table, $preparedConstraints, $row, $resetData = false)
    {
        $md5 = $this->generateFileHash($table, $row);

        $id = $row["id"];

        if (isset($row["uid"])) {
            unset($row["id"]);
        }
        $this->convertForeignKeysIdToUid($table, $preparedConstraints, $row, $resetData);
        foreach ($row as $column => $value) {
            if (empty($value) && $value != "0") {
                $row[$column] = null;
            }
        }

        // Copy created datetime to modified
        if (isset($row["created"]) && isset($row["modified"])) {
            $row["modified"] = $row["created"];
        }

        // Add merged data
        if (isset($this->mergeTables[$table])) {
            foreach ($this->mergeTables[$table] as $mergeTable => $data) {
                $query = "SELECT * FROM {$mergeTable} WHERE {$data["column"]}='{$id}' ORDER BY {$data["order_by"]} ASC;";
                $data = $this->databaseContext->getAll($query);
                if (!empty($data)) {
                    if (!isset($row["merged_data"])) {
                        $row["merged_data"] = [];
                    }
                    if (!isset($row["merged_data"][$mergeTable])) {
                        $row["merged_data"][$mergeTable] = [];
                    }

                    $preparedConstraints = $this->getTablesForeignKeysConstraintsForUid($mergeTable);

                    foreach ($data as $mergeRow) {
                        $this->convertForeignKeysIdToUid($mergeTable, $preparedConstraints, $mergeRow, $resetData);
                        if (isset($mergeRow["id"])) {
                            unset($mergeRow["id"]);
                        }
                        $row["merged_data"][$mergeTable][] = $mergeRow;
                    }
                }
            }
        }

        if ($saveToFile) {
            if(isset($this->yamlFiles[$table])){
                $data = $this->prepareYaml($table,$row);
                if(!empty($data)){
                    $this->saveYaml($path, $this->yamlFiles[$table], $data);
                }
            }
            $this->saveJson($path, "{$table}-$md5.json", $row);
        } else {
            $this->exportArray["{$table}-{$md5}.json"] = $row;
        }

        return true;
    }



    /**
     * Gets table foreign keys constraints.
     *
     * @param $table
     * @return array
     */
    private function getTablesForeignKeysConstraintsForUid($table)
    {
        // Get foreign keys
        $query = "SELECT * FROM ssinformation.key_column_usage WHERE referenced_table_name is not null AND table_name = '{$table}' AND table_schema = '{$_ENV["DATABASE_NAME"]}';";
        $constraints = $this->databaseContext->executeQuery($query);
        $preparedConstraints = [];
        if (!empty($constraints)) {
            foreach ($constraints as $constraint) {
                if (isset($constraint["REFERENCED_TABLE_NAME"]) && !empty($constraint["REFERENCED_TABLE_NAME"])) {
                    $referencedTable = $constraint["REFERENCED_TABLE_NAME"];
                    $query = "SHOW COLUMNS FROM {$referencedTable} LIKE 'uid';";
                    $referencedTableUid = $this->databaseContext->executeQuery($query);
                    if (!empty($referencedTableUid)) {
                        $preparedConstraints[] = [
                            "table" => $referencedTable,
                            "column" => $constraint["COLUMN_NAME"],
                        ];
                    }
                }
            }
        }

        return $preparedConstraints;
    }

    /**
     * @param $table
     * @param $row
     * @return mixed|string
     */
    private function generateFileHash($table, $row)
    {
        if (isset($row["uid"])) {
            $md5 = $row["uid"];
        } elseif (!in_array($table, $this->keepIds) && $uid = $this->getUidByMapping($table, $row)) {
            $md5 = $uid;
        } elseif (isset($row["id"])) {
            $md5 = $row["id"];
        } else {
            dump($table);
            dump($row);
            die;
            throw new \Exception("Missing column for md5!");
        }
        if (isset($this->fileSuffixColumn[$table])) {
            foreach ($this->fileSuffixColumn[$table] as $suffixColumnName) {
                $md5 .= "-{$row[$suffixColumnName]}";
            }
        }
        return $md5;
    }

    /**
     * Check if is custom on linked tables too.
     *
     * @param $table
     * @param $row
     * @return array
     */
    private function getIsCustomByMapping($table, $row)
    {
        // Get foreign keys
        $query = "SELECT * FROM ssinformation.key_column_usage WHERE referenced_table_name is not null AND table_name = '{$table}' AND table_schema = '{$_ENV["DATABASE_NAME"]}';";
        $constraints = $this->databaseContext->executeQuery($query);

        if (!empty($constraints)) {
            foreach ($constraints as $constraint) {
                if (isset($constraint["REFERENCED_TABLE_NAME"]) && !empty($constraint["REFERENCED_TABLE_NAME"])) {
                    $referencedTable = $constraint["REFERENCED_TABLE_NAME"];
                    $query = "SHOW COLUMNS FROM {$referencedTable} LIKE 'is_custom';";
                    $referencedTableIsCustom = !empty($this->databaseContext->executeQuery($query));
                    if ($referencedTableIsCustom && isset($row[$constraint["COLUMN_NAME"]]) && !empty($row[$constraint["COLUMN_NAME"]])) {
                        $query = "SELECT * FROM {$referencedTable} WHERE id={$row[$constraint["COLUMN_NAME"]]};";
                        $referencedTableData = $this->databaseContext->getSingleEntity($query);
                        return [
                            "is_custom" => $referencedTableData["is_custom"],
                            "bundle" => $referencedTableData["bundle"] ?? null,
                        ];
                    }
                }
            }
        }

        return [];
    }

    /**
     * Get UID from paretn table
     *
     * @param $table
     * @param $row
     * @return mixed|string
     */
    private function getUidByMapping($table, $row)
    {
        // Get foreign keys
        $query = "SELECT * FROM ssinformation.key_column_usage WHERE referenced_table_name is not null AND table_name = '{$table}' AND table_schema = '{$_ENV["DATABASE_NAME"]}';";
        $constraints = $this->databaseContext->executeQuery($query);

        $uidCombination = "";
        if (!empty($constraints)) {
            foreach ($constraints as $key => $constraint) {
                if (isset($constraint["REFERENCED_TABLE_NAME"]) && !empty($constraint["REFERENCED_TABLE_NAME"])) {
                    $referencedTable = $constraint["REFERENCED_TABLE_NAME"];
                    $query = "SHOW COLUMNS FROM {$referencedTable} LIKE 'uid';";
                    $referencedTableHasUid = !empty($this->databaseContext->executeQuery($query));
                    if ($referencedTableHasUid) {
                        if (empty($row[$constraint["COLUMN_NAME"]])) {
                            continue;
                        }
                        $query = "SELECT uid FROM {$referencedTable} WHERE id={$row[$constraint["COLUMN_NAME"]]};";
                        $referencedTableData = $this->databaseContext->getSingleEntity($query);
                        $uidCombination .= "{$referencedTableData["uid"]}-";
                    }
                }
            }
            return rtrim($uidCombination, "-");
        }

        return "";
    }

    /**
     * Converts IDs to UIDs
     *
     * @param $table
     * @param $preparedConstraints
     * @param $row
     * @param bool $resetData
     */
    private function convertForeignKeysIdToUid($table, $preparedConstraints, &$row, $resetData = false)
    {
        if ($resetData) {
            $this->data = array();
        }

        if (!empty($preparedConstraints)) {
            foreach ($preparedConstraints as $constraint) {
                if (!isset($this->data[$constraint["table"]])) {
                    $query = "SELECT id,uid FROM {$constraint["table"]};";
                    $data = $this->databaseContext->executeQuery($query);
                    $this->data[$constraint["table"]] = $data;
                }
                if (!empty($this->data[$constraint["table"]])) {
                    foreach ($this->data[$constraint["table"]] as $referencedData) {
                        if ($row[$constraint["column"]] == $referencedData["id"]) {
                            $row[$constraint["column"]] = $referencedData["uid"];
                        }
                    }
                }
            }
        }
        foreach ($this->additionalForeignKeys as $constraint) {
            if (!isset($this->data[$constraint["table"]])) {
                $query = "SELECT id,uid FROM {$constraint["table"]};";
                $data = $this->databaseContext->executeQuery($query);
                $this->data[$constraint["table"]] = $data;
            }
            if (!empty($this->data[$constraint["table"]])) {
                foreach ($this->data[$constraint["table"]] as $referencedData) {
                    if (isset($row[$constraint["column"]]) && $row[$constraint["column"]] == $referencedData["id"]) {
                        $row[$constraint["column"]] = $referencedData["uid"];
                    }
                }
            }
        }

        if (isset($this->additionalSwapIdToUid[$table])) {
            foreach ($this->additionalSwapIdToUid[$table] as $mapping) {
                if (empty($row[$mapping["column_with_id"]])) {
                    continue;
                }
                foreach ($mapping[$mapping["column_with_table"]] as $oldTable => $newTable) {
                    if (isset($row[$mapping["column_with_table"]]) && $row[$mapping["column_with_table"]] == $oldTable) {
                        if (!isset($this->data[$newTable])) {
                            $query = "SELECT id,uid FROM {$newTable};";
                            $data = $this->databaseContext->executeQuery($query);
                            $this->data[$newTable] = $data;
                        }
                        if (!empty($this->data[$newTable])) {
                            foreach ($this->data[$newTable] as $referencedData) {
                                if (isset($row[$mapping["column_with_id"]]) && $row[$mapping["column_with_id"]] == $referencedData["id"]) {
                                    $row[$mapping["column_with_id"]] = $referencedData["uid"];
                                }
                            }
                        }
                    }
                }
            }
        }

        if (isset($this->jsonColumnsByTable[$table])) {
            if (isset($row[$this->jsonColumnsByTable[$table]["json_column"]]) && $this->isJson($row[$this->jsonColumnsByTable[$table]["json_column"]])) {
                $jsonValues = json_decode($row[$this->jsonColumnsByTable[$table]["json_column"]], true);
                if (!empty($jsonValues) && !empty($this->jsonColumnsByTable[$table]["columns"])) {
                    foreach ($this->jsonColumnsByTable[$table]["columns"] as $jsonColumn) {
                        if (isset($jsonValues[$jsonColumn["json_column_name"]])) {
                            if (!isset($this->data[$jsonColumn["related_table"]])) {
                                $query = "SELECT id,uid FROM {$jsonColumn["related_table"]};";
                                $data = $this->databaseContext->executeQuery($query);
                                $this->data[$jsonColumn["related_table"]] = $data;
                            }

                            if (!empty($this->data[$jsonColumn["related_table"]])) {
                                foreach ($this->data[$jsonColumn["related_table"]] as $referencedData) {
                                    if (isset($jsonValues[$jsonColumn["json_column_name"]]) && $jsonValues[$jsonColumn["json_column_name"]] == $referencedData["id"]) {
                                        $jsonValues[$jsonColumn["json_column_name"]] = $referencedData["uid"];
                                        break;
                                    }
                                }
                                $row[$this->jsonColumnsByTable[$table]["json_column"]] = json_encode($jsonValues);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Converts UIDs to IDs
     *
     * @param $preparedConstraints
     * @param $row
     */
    private function convertForeignKeysUidToId($table, $preparedConstraints, &$row)
    {
        if (!empty($preparedConstraints)) {
            foreach ($preparedConstraints as $constraint) {
                // Rebuild array with fresh data.
                $query = "SELECT id FROM {$constraint["table"]} WHERE uid='{$row[$constraint["column"]]}';";
                $data = $this->databaseContext->getSingleEntity($query);
                if (!empty($data)) {
                    $row[$constraint["column"]] = $data["id"];
                }
            }
        }
        foreach ($this->additionalForeignKeys as $constraint) {
            if (isset($row[$constraint["column"]])) {
                $query = "SELECT id,uid FROM {$constraint["table"]} WHERE uid='{$row[$constraint["column"]]}';";
                $data = $this->databaseContext->getSingleEntity($query);
                if (!empty($data)) {
                    $row[$constraint["column"]] = $data["id"];
                }
            }
        }
        if (isset($this->additionalSwapIdToUid[$table])) {
            foreach ($this->additionalSwapIdToUid[$table] as $mapping) {
                if (empty($row[$mapping["column_with_id"]])) {
                    continue;
                }
                foreach ($mapping[$mapping["column_with_table"]] as $oldTable => $newTable) {
                    if (isset($row[$mapping["column_with_table"]]) && $row[$mapping["column_with_table"]] == $oldTable) {
                        $query = "SELECT id,uid FROM {$newTable} WHERE uid='{$row[$mapping["column_with_id"]]}';";
                        $data = $this->databaseContext->getSingleEntity($query);
                        if (!empty($data)) {
                            $row[$mapping["column_with_id"]] = $data["id"];
                        }
                    }
                }
            }
        }

        if (isset($this->jsonColumnsByTable[$table])) {
            if (isset($row[$this->jsonColumnsByTable[$table]["json_column"]]) && $this->isJson($row[$this->jsonColumnsByTable[$table]["json_column"]])) {
                $jsonValues = json_decode($row[$this->jsonColumnsByTable[$table]["json_column"]], true);
                if (!empty($jsonValues) && !empty($this->jsonColumnsByTable[$table]["columns"])) {
                    foreach ($this->jsonColumnsByTable[$table]["columns"] as $jsonColumn) {
                        if (isset($jsonValues[$jsonColumn["json_column_name"]])) {
                            $query = "SELECT id,uid FROM {$jsonColumn["related_table"]} WHERE uid='{$jsonValues[$jsonColumn["json_column_name"]]}';";
                            $data = $this->databaseContext->getSingleEntity($query);
                            if (!empty($data)) {
                                $jsonValues[$jsonColumn["json_column_name"]] = $data["id"];
                                $row[$this->jsonColumnsByTable[$table]["json_column"]] = json_encode($jsonValues);
                                continue;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Check if string is json.
     *
     * @param $string
     * @return bool
     */
    private function isJson($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
        if (preg_match("#{[\'\"][0-9]+[\'\"][ ]*:#i", $string)) {
            return true;
        }
        return false;
    }

    /**
     * @param $table
     * @param $id
     * @return bool
     */
    public function getEntityRecordById($table, $id)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $query = "SELECT * FROM {$table} WHERE id={$id};";
        $row = $this->databaseContext->getSingleEntity($query);

        return $row;
    }

    /**
     * Removes single config record.
     *
     * @param $table
     * @param $row
     */
    public function deleteEntityRecord($table, $row, $forceDelete = false)
    {
        //if (in_array($table, $this->limitDelete)) {
        $md5 = $this->generateFileHash($table, $row);

        $oldUid = $row["uid"];

        $isCustom = false;
        $customPath = $this->getConfigDirectoryPath("content/{$table}", $_ENV["PROJECT_BUNDLE"]);
        if (file_exists("$customPath/{$table}-$md5.json")) {
            $isCustom = true;
        }

        $defaultPath = null;
        $hasDefault = false;
        foreach ($this->getContainer()->getParameter('kernel.bundles') as $bundle => $namespace) {
            if ($bundle == $_ENV["PROJECT_BUNDLE"]) {
                continue;
            }
            $defaultPath = $this->getConfigDirectoryPath("content/{$table}", $bundle);
            if (file_exists("$defaultPath/{$table}-$md5.json")) {
                $hasDefault = true;
                break;
            }
        }
        if ($isCustom) {
            if ($hasDefault) {
                if ($forceDelete) {
                    if(isset($this->yamlFiles[$table])){
                        $this->removeFromYaml($customPath, $this->yamlFiles[$table], $oldUid);
                        $this->removeFromYaml($defaultPath, $this->yamlFiles[$table], $oldUid);
                    }
                    unlink("$customPath/{$table}-$md5.json");
                    unlink("$defaultPath/{$table}-$md5.json");
                } else {
                    if(isset($this->yamlFiles[$table])){
                        $this->removeFromYaml($customPath, $this->yamlFiles[$table], $oldUid);
                        $this->removeFromYaml($defaultPath, $this->yamlFiles[$table], $oldUid);
                    }
                    unlink("$customPath/{$table}-$md5.json");
                    rename("$defaultPath/{$table}-$md5.json", "$defaultPath/_deleted-{$table}-$md5.json");
                }
            } else {
                if ($forceDelete) {
                    if(isset($this->yamlFiles[$table])){
                        $this->removeFromYaml($customPath, $this->yamlFiles[$table], $oldUid);
                    }
                    unlink("$customPath/{$table}-$md5.json");
                } else {
                    if(isset($this->yamlFiles[$table])){
                        $this->removeFromYaml($customPath, $this->yamlFiles[$table], $oldUid);
                    }
                    rename("$customPath/{$table}-$md5.json", "$customPath/_deleted-{$table}-$md5.json");
                }
            }
        } elseif ($hasDefault) {
            if ($forceDelete) {
                if(isset($this->yamlFiles[$table])){
                    $this->removeFromYaml($defaultPath, $this->yamlFiles[$table], $oldUid);
                }
                unlink("$defaultPath/{$table}-$md5.json");
            } else {
                if(isset($this->yamlFiles[$table])){
                    $this->removeFromYaml($defaultPath, $this->yamlFiles[$table], $oldUid);
                }
                rename("$defaultPath/{$table}-$md5.json", "$defaultPath/_deleted-{$table}-$md5.json");
            }

        }
        //}

        return true;
    }

    /**
     * @param $table
     * @param $uid
     * @return bool
     */
    public function resetToDefault($table, $uid)
    {

        $path = $this->getConfigDirectoryPath("content/{$table}", $_ENV["PROJECT_BUNDLE"]) . "/{$table}-{$uid}.json";

        if (file_exists($path)) {
            if(isset($this->yamlFiles[$table])){
                $this->removeFromYaml($path, $this->yamlFiles[$table], $uid);
            }
            unlink($path);
        }

        return true;
    }

    /**
     * NEW
     */

    /**
     * @param $path
     * @param $filename
     * @param $oldUid
     * @return true
     */
    public function removeFromYaml($path, $filename, $oldUid){

        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        if(!file_exists("{$path}/{$filename}")){
            return true;
        }

        $dataArray = Yaml::parseFile("{$path}/{$filename}");

        foreach ($dataArray as $key => $data){
            if($data["uid"] == $oldUid){
                unset($dataArray[$key]);
            }
        }

        $dataYaml = Yaml::dump($dataArray);

        file_put_contents("{$path}/{$filename}", $dataYaml);

        return true;
    }

    /**
     * @param $path
     * @param $filename
     * @param $array
     * @return true
     */
    public function saveYaml($path, $filename, $array){
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        $dataArray = Array();

        if(file_exists("{$path}/{$filename}")){
            $dataArray = Yaml::parseFile("{$path}/{$filename}");
        }

        $dataArray[$array["uid"]] = $array;

        $dataYaml = Yaml::dump($dataArray);

        file_put_contents("{$path}/{$filename}", $dataYaml);

        return true;
    }

    /**
     * @param $table
     * @param $row
     * @return array
     */
    public function prepareYaml($table,$row){

        $ret = Array();

        switch ($table) {
            case "attribute":

                //TODO ovdje idu mapiranja na nove attr codove

                if(empty($this->entityTypeListByUid)){
                    $this->getEntityTypeByUid();
                }

                if(empty($this->attributeListByUid)){
                    $this->getAttributeListByUid();
                }

                $lookupEntityTypeUid = null;
                if(!empty($row["lookup_entity_type_id"])){
                    $lookupEntityTypeUid = $this->entityTypeListByUid[$row["lookup_entity_type_id"]]["new_uid"];
                }

                $lookupAttributeUid = null;
                if(!empty($row["lookup_attribute_id"])){
                    $lookupAttributeUid = $this->attributeListByUid[$row["lookup_attribute_id"]]["new_uid"];
                }

                $tmp = explode("_entity",$row["backend_table"])[0];
                $entityTypeUid = md5($tmp);
                $newUid = md5($tmp.$row["attribute_code"]);
                $ret = Array(
                    "entity_type_id" => $entityTypeUid,
                    "entity_type_code" => $tmp,
                    "lookup_entity_type_id" => $lookupEntityTypeUid,
                    "lookup_attribute_id" => $lookupAttributeUid,
                    "inverse_attribute_id" => null,
                    "name" => $row["frontend_label"],
                    "code" => $row["attribute_code"],
                    "property" => EntityHelper::makeAttributeName($row["attribute_code"]),
                    "type" => $this->newTransferType($row["backend_type"]),
                    "template" => $row["frontend_input"],
                    "default_value" => $row["default_value"],
                    "note" => $row["note"],
                    "show_on_create" => $row["frontend_display_on_new"],
                    "show_on_read" => $row["frontend_display_on_view"],
                    "show_on_update" => $row["frontend_display_on_update"],
                    "read_only" => $row["read_only"],
                    "required" => $row["is_required"],
                    "hidden" => $row["frontend_hidden"],
                    "autocomplete_template" => $row["frontend_model"],
                    "options" => null,
                    "custom" => null,
                    "uid" => $newUid,
                    "old_uid" => $row["uid"],
                    "modal_page_block_id" => $row["modal_page_block_id"],
                    "enable_modal_create" => $row["enable_modal_create"],
                    "use_in_advanced_search" => $row["use_in_advanced_search"],
                    "use_in_quick_search" => $row["use_in_quick_search"],
                    "validator" => $row["validator"]
                );

                break;
            case "entity_type":

                $className = $this->getClassName($row);

                $ret = Array(
                    "name" => str_ireplace("_"," ",ucfirst($row["entity_type_code"])),
                    "code" => $row["entity_type_code"],
                    "bundle" => $this->cleanBundleName($row["bundle"]), //todo pri importu prebacit u id
                    "class_name" => $className,
                    "table_name" => $row["entity_table"],
                    "uid" => md5($row["entity_type_code"]),
                    "old_uid" => $row["entity_type_code"],
                    "is_custom" => intval($row["is_custom"]),
                    "is_view" => intval($row["is_view"]),
                    "sync_content" => intval($row["sync_content"]),
                    "check_privileges" => intval($row["check_privileges"])
                );

                break;
            case "attribute_group":

                if(empty($this->attributeListByUid)){
                    $this->getAttributeListByUid();
                }

                if(empty($this->entityTypeListByAttributeSetUid)){
                    $this->getEntityTypeByAttributeSetUid();
                }

                $attribute_group_attributes = Array();
                if(!empty($row["merged_data"]) && isset($row["merged_data"]["entity_attribute"]) && !empty($row["merged_data"]["entity_attribute"])){
                    foreach ($row["merged_data"]["entity_attribute"] as $attribute){
                        if(!isset($this->attributeListByUid[$attribute["attribute_id"]]["new_uid"])) {
                            continue;
                        }
                        $attribute_group_attributes[] = Array(
                            "attribute_id" => $this->attributeListByUid[$attribute["attribute_id"]]["new_uid"],
                            "ord" => $attribute["sort_order"],
                            "custom" => false
                        );
                    }
                }

                $entityTypeCode = $this->entityTypeListByAttributeSetUid[$row["attribute_set_id"]]["entity_type_code"];
                $attributeGroupCode = $entityTypeCode."_".str_replace(' ', '_', strtolower(trim($row["attribute_group_name"])));
                $newUid = md5($entityTypeCode.$attributeGroupCode);

                //uid = entity_type_code + code

                $ret = Array(
                    "name" => $row["attribute_group_name"],
                    "entity_type_code" => $entityTypeCode,
                    "code" => $attributeGroupCode,
                    "custom" => intval($row["is_custom"]),
                    "note" => $row["note"],
                    "uid" => $newUid,
                    "old_uid" => $row["uid"],
                    "attribute_group_attributes" => json_encode($attribute_group_attributes)
                );

                break;
            case "list_view":

                if(empty($this->attributeListByUid)){
                    $this->getAttributeListByUid();
                }

                if(empty($this->attributeListById)){
                    $this->getAttributeListById();
                }

                if(empty($this->entityTypeListByUid)){
                    $this->getEntityTypeByUid();
                }

                $attribute_group_attributes = Array();
                if(!empty($row["merged_data"]) && isset($row["merged_data"]["list_view_attribute"]) && !empty($row["merged_data"]["list_view_attribute"])){
                    foreach ($row["merged_data"]["list_view_attribute"] as $attribute){
                        if(!isset($this->attributeListByUid[$attribute["attribute_id"]]["new_uid"])) {
                            continue;
                        }
                        $attribute_group_attributes[] = Array(
                            "attribute_id" => $this->attributeListByUid[$attribute["attribute_id"]]["new_uid"],
                            "ord" => $attribute["ord"],
                            "is_sort_attribute" => 1,
                            "property" => null,
                            "custom" => intval($attribute["is_custom"]),
                            "column_width" => $attribute["column_width"],
                            "enable_inline_editing" => $attribute["enable_inline_editing"],
                        );
                    }
                }

                //uid = entity_type_code + code

                $entityType = $this->entityTypeListByUid[$row["entity_type"]];

                $newUid = md5($entityType["entity_type_code"].$row["name"]);

                $sort_attribute_id = null;
                if(isset($this->attributeListById[$row["default_sort"]]["new_uid"])){
                    $sort_attribute_id = $this->attributeListById[$row["default_sort"]]["new_uid"];
                }

                $ret = Array(
                    "entity_type_id" => $entityType["new_uid"],
                    "name" => $row["display_name"],
                    "entity_type_code" => $entityType["entity_type_code"],
                    "code" => $row["name"],
                    "filter" => $row["filter"],
                    "sort_direction" => $row["default_sort_type"],
                    "row_limit" => $row["show_limit"],
                    "show_search" => intval($row["show_filter"]),
                    "show_import" => intval($row["show_import"]),
                    "show_export" => intval($row["show_export"]),
                    "show_advanced_search" => intval($row["show_advanced_search"]),
                    "main_button" => $row["main_button"],
                    "dropdown_buttons" => $row["dropdown_buttons"],
                    "row_actions" => $row["row_actions"],
                    "mass_actions" => $row["mass_actions"],
                    "show_sort" => 1,
                    "note" => $row["note"],
                    "uid" => $newUid,
                    "old_uid" => $row["uid"],
                    "custom" => intval($row["is_custom"]),
                    "sort_attribute_id" => $sort_attribute_id,
                    "list_view_attributes" => json_encode($attribute_group_attributes),
                    "inline_editing" => intval($row["inline_editing"]),
                    "public_view" => intval($row["public_view"]),
                    "above_list_actions" => $row["above_list_actions"],
                    "modal_add" => intval($row["modal_add"]),
                );

                break;
            case "page":

                if(empty($this->entityTypeListByUid)){
                    $this->getEntityTypeByUid();
                }

                //uid = entity_type_code + code

                $entityType = $this->entityTypeListByUid[$row["entity_type"]];

                $newUid = md5($row["url"].$row["type"]);

                $ret = Array(
                    "title" => $row["title"],
                    "url" => $row["url"],
                    "entity_type_code" => $entityType["entity_type_code"],
                    "entity_type_id" => $entityType["new_uid"],
                    "type" => $row["type"],
                    "content" => $row["content"],
                    "description" => null,
                    "show_breadcrumbs" => false,
                    "class" => $row["class"],
                    "buttons" => $row["buttons"],
                    "custom" => intval($row["is_custom"]),
                    "data_attributes" => $row["data_attributes"],
                    "bundle" => $this->cleanBundleName($row["bundle"]), //todo pri importu prebacit u id
                    "uid" => $newUid,
                    "old_uid" => $row["uid"],
                );

                break;
            case "page_block":

                if(empty($this->entityTypeListByUid)){
                    $this->getEntityTypeByUid();
                }

                if(empty($row["entity_type"])){
                    return $ret;
                }

                $entityType = $this->entityTypeListByUid[$row["entity_type"]];

                //uid = custom

                $relatedUid = null;

                switch ($row["type"]) {
                    case "list_view":
                    case "library_view":

                        if(empty($row["related_id"])){
                            return $ret;
                        }

                        if(empty($this->listViewListByUid)){
                            $this->getListViewByUid();
                        }

                        if(!isset($this->listViewListByUid[$row["related_id"]])){
                            return $ret;
                        }

                        $relatedUid = $this->listViewListByUid[$row["related_id"]]["new_uid"];

                        break;
                    case "attribute_group":
                    case "related_attribute_group":

                        if(empty($this->attributeGroupListByUid)){
                            $this->getAttributeGroupByUid();
                        }

                        $relatedUid = $this->attributeGroupListByUid[$row["related_id"]]["new_uid"];

                        break;
                    default:
                        break;
                }

                $newUid = $row["uid"];

                $ret = Array(
                    "title" => $row["title"],
                    "entity_type_code" => $entityType["entity_type_code"],
                    "entity_type_id" => $entityType["new_uid"],
                    "type" => $row["type"],
                    "content" => $row["content"],
                    "related_id" => $relatedUid,
                    "class" => $row["class"],
                    "custom" => intval($row["is_custom"]),
                    "data_attributes" => $row["data_attributes"],
                    "uid" => $newUid,
                    "old_uid" => $row["uid"],
                );

                //"parent" => "13a4218267f4b83b2ef4ca8433ee9a65"

                break;
            case "navigation_link":

                if(empty($this->pageListByUid)){
                    $this->getPageByUid();
                }

                if(empty($this->entityTypeListByUid)){
                    $this->getEntityTypeByUid();
                }

                $entityTypeNewUid = null;
                $entityTypeCode = null;
                $pageUid = null;
                if(!empty($row["page"])){

                    if(!isset($this->pageListByUid[$row["page"]])){
                        return $ret;
                    }

                    $page = $this->pageListByUid[$row["page"]];
                    $pageUid = $page["new_uid"];
                    $entityType = $this->entityTypeListByUid[$page["entity_type_uid"]];
                    $entityTypeCode = $entityType["entity_type_code"];
                    $entityTypeNewUid = $entityType["new_uid"];
                }

                $newUid = $row["uid"];

                $ret = Array(
                    "display_name" => $row["display_name"],
                    "entity_type_code" => $entityTypeCode,
                    "entity_type_id" => $entityTypeNewUid,
                    "url" => $row["url"],
                    "image" => $row["image"],
                    "is_parent" => intval($row["is_parent"]),
                    "css_class" => $row["css_class"],
                    "custom" => intval($row["is_custom"]),
                    "ord" => $row["ord"],
                    "uid" => $newUid,
                    "old_uid" => $row["uid"],
                    "parent_id" => $row["parent_id"],
                    "shw" => $row["shw"],
                    "target" => $row["target"],
                    "bundle" => $this->cleanBundleName($row["bundle"]), //todo pri importu prebacit u id
                    "page" => $pageUid
                );

                break;
            default:
                break;
        }

        return $ret;
    }

    public function cleanBundleName($bundle){

        $bundle = str_ireplace("business","",$bundle);

        return $bundle;
    }

    /**
     * @param $row
     * @return string
     */
    public function getClassName($row){

        $defaultBundles = Array(
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
            "PaymentProvidersBusinessBundle"
        );

        $shapeBundle = Array(
            "CrmBusinessBundle",
            "TaskBusinessBundle",
            "AppBundle",
            "ScommerceBusinessBundle"
        );

        if(in_array($row["bundle"],$defaultBundles)){
            $classPrefix = "App\\".$this->cleanBundleName($row["bundle"]);
            if(in_array($row["bundle"],$shapeBundle)){
                $classPrefix = "App\Shipshape\\".$this->cleanBundleName($row["bundle"]);
            }
        }
        else{
            $classPrefix = "App\Custom\\".$this->cleanBundleName($row["bundle"]);
        }

        $className = "{$classPrefix}\\".ucfirst(EntityHelper::makeAttributeName($row["entity_type_code"])."Entity");

        return $className;
    }

    /**
     * @return true
     */
    public function getEntityTypeByUid(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT id, uid, entity_type_code, MD5(entity_type_code) as new_uid FROM entity_type;";
        $data = $this->databaseContext->getAll($q);

        foreach ($data as $d){
            $this->entityTypeListByUid[$d["uid"]] = $d;
        }

        return true;
    }

    /**
     * @return true
     */
    public function getAttributeListByUid(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT a.id, a.uid, a.attribute_code, MD5(CONCAT(e.entity_type_code,a.attribute_code)) as new_uid FROM attribute as a LEFT JOIN entity_type as e ON a.entity_type_id = e.id;";
        $data = $this->databaseContext->getAll($q);

        foreach ($data as $d){
            $this->attributeListByUid[$d["uid"]] = $d;
        }

        return true;
    }

    /**
     * @return true
     */
    public function getListViewByUid(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT l.id, l.uid, MD5(CONCAT(e.entity_type_code,l.name)) as new_uid FROM list_view as l LEFT JOIN entity_type as e ON l.entity_type = e.id;";
        $data = $this->databaseContext->getAll($q);

        foreach ($data as $d){
            $this->listViewListByUid[$d["uid"]] = $d;
        }

        return true;
    }

    /**
     * @return true
     */
    public function getAttributeGroupByUid(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT a.id, a.uid, MD5(CONCAT(e.entity_type_code,CONCAT(e.entity_type_code,'_',LOWER(replace(a.attribute_group_name,' ', '_'))))) as new_uid FROM attribute_group as a LEFT JOIN attribute_set as ats ON a.attribute_set_id = ats.id LEFT JOIN entity_type as e ON ats.entity_type_id = e.id;";
        $data = $this->databaseContext->getAll($q);

        foreach ($data as $d){
            $this->attributeGroupListByUid[$d["uid"]] = $d;
        }

        return true;
    }

    public function getPageByUid(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT p.id, p.uid, e.uid as entity_type_uid, MD5(CONCAT(p.url,p.type)) as new_uid FROM page as p LEFT JOIN entity_type as e ON p.entity_type = e.id;";
        $data = $this->databaseContext->getAll($q);

        foreach ($data as $d){
            $this->pageListByUid[$d["uid"]] = $d;
        }

        return true;
    }


    /**
     * @return true
     */
    public function getAttributeListById(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT a.id, a.uid, a.attribute_code, MD5(CONCAT(e.entity_type_code,a.attribute_code)) as new_uid FROM attribute as a LEFT JOIN entity_type as e ON a.entity_type_id = e.id;";
        $data = $this->databaseContext->getAll($q);

        foreach ($data as $d){
            $this->attributeListById[$d["id"]] = $d;
        }

        return true;
    }

    public function getEntityTypeByAttributeSetUid(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT a.id, a.entity_type_id, a.uid, e.entity_type_code, MD5(CONCAT(e.entity_type_code)) as new_uid FROM attribute_set as a LEFT JOIN entity_type as e ON a.entity_type_id = e.id;";
        $data = $this->databaseContext->getAll($q);

        foreach ($data as $d){
            $this->entityTypeListByAttributeSetUid[$d["uid"]] = $d;
        }

        return true;
    }

    /**
     * @param $backendType
     * @return string|void
     */
    public function newTransferType($backendType){

        $mappingArray = Array(
            "static" => "integer",
            "varchar" => "string",
            "lookup" => "ManyToOne",
            "decimal" => "decimal",
            "integer" => "integer",
            "text" => "string",
            "date" => "",
            "datetime" => "datetime",
            "json" => "json",
            "bool" => "boolean",
            "ckeditor" => "",
            "time" => "",
        );

        if(!isset($mappingArray[$backendType])){
            dump($backendType);
            die;
        }

        return $mappingArray[$backendType];
    }

    /**
     * END NEW
     */
}
