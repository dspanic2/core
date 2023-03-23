<?php

namespace AppBundle\Managers;

use AppBundle\Entity\Attribute;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\ListView;
use AppBundle\Helpers\FileHelper;
use CrmBusinessBundle\Abstracts\AbstractImportManager;
use MongoDB\Driver\Exception\ServerException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class ShapeCleanerManager extends AbstractImportManager
{
    /** @var DatabaseManager $databaseManager */
    protected $databaseManager;

    public function initialize()
    {
        parent::initialize();
        $this->databaseManager = $this->container->get("database_manager");
    }

    /**
     * @param $path
     * @param $days
     */
    public function cleanFilesFromFolderOlderThan($path,$days){

        $files = glob($path."*");
        $now   = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * 1) {
                    unlink($file);
                }
            }
        }

        return true;
    }

    public function getAllListViewAttributes()
    {
        $q = "SELECT * FROM list_view_attribute;";
        return $this->databaseContext->executeQuery($q);
    }

    public function getBlockByRelatedId($type, $id)
    {
        $q = "SELECT * FROM page_block 
            WHERE type = '{$type}' 
            AND related_id = '{$id}';";
        return $this->databaseContext->executeQuery($q);
    }

    public function findBlockUidInContent($uid)
    {
        $q = "SELECT * FROM page_block 
            WHERE content LIKE '%{$uid}%';";
        return $this->databaseContext->executeQuery($q);
    }

    public function getBlockByUid($uid)
    {
        $q = "SELECT * FROM page_block 
            WHERE uid = '{$uid}' OR id = '{$uid}';";
        return $this->databaseContext->executeQuery($q);
    }

    public function getNavigationLinkForPage($id)
    {
        $q = "SELECT * FROM navigation_link 
            WHERE page = '{$id}';";
        return $this->databaseContext->getSingleEntity($q);
    }

    public function getAllNavigationLinks()
    {
        $q = "SELECT * FROM navigation_link;";
        return $this->databaseContext->executeQuery($q);
    }

    public function getAllAttributes()
    {
        $q = "SELECT * FROM attribute;";
        return $this->databaseContext->executeQuery($q);
    }

    public function clearUserRoles()
    {
        $q = "DELETE FROM user_role_entity 
            WHERE core_user_id NOT IN (
                SELECT id FROM user_entity
            );";
        return $this->databaseContext->executeQuery($q);
    }

    public function updateNavigationLinks()
    {
        $q = "UPDATE navigation_link 
            SET is_parent = 0
            WHERE parent_id IS NOT NULL;
            UPDATE navigation_link 
            SET is_parent = 1
            WHERE parent_id IS NULL;";
        return $this->databaseContext->executeQuery($q);
    }

    public function getAllListViews()
    {
        $ret = array();

        $q = "SELECT * FROM list_view;";

        $data = $this->databaseContext->executeQuery($q);
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d["id"]] = $d;
            }
        }

        return $ret;
    }

    public function getAllBlocks()
    {
        $ret = array();

        $q = "SELECT * FROM page_block;";

        $data = $this->databaseContext->executeQuery($q);
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d["id"]] = $d;
            }
        }

        return $ret;
    }

    public function getAllPages()
    {
        $ret = array();

        $q = "SELECT * FROM page;";

        $data = $this->databaseContext->executeQuery($q);
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d["id"]] = $d;
            }
        }

        return $ret;
    }

    public function getAllEntityTypes()
    {
        $ret = array();

        $q = "SELECT * FROM entity_type;";

        $data = $this->databaseContext->executeQuery($q);
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d["id"]] = $d;
            }
        }

        return $ret;
    }

    public function getAllPrivileges()
    {
        $ret = array();

        $q = "SELECT
                id, 
                CONCAT(role, '_', action_type, '_', action_code) AS hash
            FROM
                privilege";
        $data = $this->databaseContext->executeQuery($q);
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d["hash"]][] = $d["id"];
            }
        }

        return $ret;
    }

    public function getAllSfrontBlocks()
    {
        $q = "SELECT * FROM s_front_block_entity;";
        return $this->databaseContext->getAll($q);
    }

    public function getAllStemplateTypes()
    {
        $q = "SELECT * FROM s_template_type_entity;";
        return $this->databaseContext->getAll($q);
    }

    public function getAllSpages()
    {
        $q = "SELECT * FROM s_page_entity WHERE layout is not null AND layout != '';";
        return $this->databaseContext->getAll($q);
    }

    /**
     * @param $entity
     * @param $attribute
     * @return array
     */
    private function getEntityBySpecificAttribute($entity, $attribute)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $query = "SELECT * FROM {$entity}_entity;";
        $queryData = $this->databaseContext->getAll($query);

        $res = [];
        if (!empty($queryData)) {
            foreach ($queryData as $data) {
                $res[$data[$attribute]] = $data;
            }
        }

        return $res;
    }

    /**
     * @return array
     */
    public function getJsonStoreAttributes()
    {
        $ret = array();

        $q = "SELECT 
                attribute_code, 
                backend_table
            FROM attribute
            WHERE backend_type = 'json' 
            AND backend_table = 'product_entity'
            AND frontend_type IN ('text_store', 'textarea_store');";

        $data = $this->databaseContext->getAll($q);
        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d["backend_table"]][] = $d["attribute_code"];
            }
        }

        return $ret;
    }

    public function scanDataContent($data, $block)
    {
        // $data = blocks / pages
        foreach ($data as $d) {
            $content = json_decode($d["content"], true);
            if (!empty($content)) {
                foreach ($content as $key1 => $c) {
                    if (isset($c["type"]) && isset($c["id"]) && $c["type"] == $block["type"] &&
                        ($c["id"] == $block["uid"] || $c["id"] == $block["id"])) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function getAll($table)
    {
        $q = "SELECT * FROM {$table};";
        return $this->databaseContext->executeQuery($q);
    }

    /**
     * @return bool
     */
    public function executeCleaner($allowedTablesString = null, $delete = 0)
    {
        $q = NULL;

        $allowedTables = Array();
        if(!empty($allowedTablesString)){
            $allowedTables = explode(",",$allowedTablesString);
        }

        $deleteArray = array();
        $updateArray = array();

        $blocks = $this->getAllBlocks();
        $listViews = $this->getAllListViews();
        $pages = $this->getAllPages();
        $entityTypes = $this->getAllEntityTypes();
        $listViewAttributes = $this->getAllListViewAttributes();
        $navigationLinks = $this->getAllNavigationLinks();
        $attributes = $this->getAllAttributes();
        $privileges = $this->getAllPrivileges();

        $sFrontBlocks = $this->getAllSfrontBlocks();
        $sTemplateTypes = $this->getAllStemplateTypes();
        $sPages = $this->getAllSpages();

        echo "Looking for listViewAttributes without listView...\n";
        foreach ($listViewAttributes as $listViewAttribute) {
            if (!empty($listViewAttribute["list_view_id"])) {
                if (!isset($listViews[$listViewAttribute["list_view_id"]])) {
                    echo "listViewAttribute exists for missing listView (id: " . $listViewAttribute["list_view_id"] . ")\n";

                    $deleteArray["list_view_attribute"][$listViewAttribute["id"]] = $listViewAttribute;
                }
            }
        }

        echo "Looking for invalid pageBlocks...\n";
        foreach ($blocks as $block) {
            /*if (strlen($block["uid"]) != 32) {
                echo "Block with old/empty uid (id: " . $block["id"] . ")\n";

                $newUid = md5($block["parent"] . $block["title"]);
                $updateArray["page_block"][$block["id"]]["uid"] = $newUid;
            }*/
            if (empty($block["parent"])) {
                echo "Block without parent (id: " . $block["id"] . ")\n";
                // ovo ostavljamo
            }
            if (empty($block["bundle"])) {
                echo "Block without bundle (id: " . $block["id"] . ")\n";

                // find bundle using entity_type
                if (isset($entityTypes[$block["entity_type"]]) && !empty($entityTypes[$block["entity_type"]]["bundle"])) {
                    $updateArray["page_block"][$block["id"]]["bundle"] = $entityTypes[$block["entity_type"]]["bundle"];
                }
            }

            // block type specific logic here, checking:
            // list_view
            // library_view
            // attribute_group
            // edit_form
            // tabs

            // list_view, library_view blocks need valid related_id and entity_types
            if ($block["type"] == "list_view" || $block["type"] == "library_view") {
                if (empty($block["related_id"]) || empty($block["entity_type"])) {
                    echo "Invalid " . $block["type"] . " block (id: " . $block["id"] . ")\n";

                    $deleteArray["page_block"][$block["id"]] = $block;
                }
            } // attribute_group blocks are found inside other blocks content
            else if ($block["type"] == "attribute_group") {
                if (!empty($block["uid"])) {
                    if (empty($this->scanDataContent($blocks, $block)) &&
                        empty($this->scanDataContent($pages, $block))) {
                        echo "Unused " . $block["type"] . " block (id: " . $block["id"] . ")\n";

                        $deleteArray["page_block"][$block["id"]] = $block;
                    }
                }
                if (empty($block["related_id"]) || empty($block["entity_type"])) {
                    echo "Invalid " . $block["type"] . " block (id: " . $block["id"] . ")\n";

                    $deleteArray["page_block"][$block["id"]] = $block;
                }
            } // edit_form and tabs blocks need to have content
            else if ($block["type"] == "edit_form" || $block["type"] == "tabs") {
                if (empty($this->scanDataContent($pages, $block)) && empty($this->scanDataContent($blocks, $block))) {
                    echo "Unused " . $block["type"] . " block (id: " . $block["id"] . ")\n";

                    $deleteArray["page_block"][$block["id"]] = $block;
                } else {
                    $contentCopy = $content = json_decode($block["content"], true);
                    if (!empty($content)) {
                        foreach ($content as $key1 => $c) {
                            foreach ($c as $key2 => $value) {
                                if ($key2 == "id") {
                                    if (empty($value)) {
                                        echo "Invalid value in block content (id: " . $block["id"] . ")\n";

                                        // this element should be removed from block content
                                        unset($contentCopy[$key1]);
                                    } else {
                                        $result = $this->getBlockByUid($value);
                                        if (empty($result)) {
                                            echo "Invalid block " . $value . " referenced in block content (id: " . $block["id"] . ")\n";
                                            //unset($contentCopy[$key1]);
                                        }

                                        // check if another edit_form block uses the same block inside their content (e.g. attribute_group)
                                        /*if ($block["type"] == "edit_form") {
                                            $clonedBlocks = $this->findBlockUidInContent($value);
                                            if (!empty($clonedBlocks)) {
                                                foreach ($clonedBlocks as $clonedBlock) {
                                                    // dont compare to itself and dont compare two same blocks twice
                                                    if ($block["id"] == $clonedBlock["id"] || $clonedBlock["id"] < $block["id"]) {
                                                        continue;
                                                    }
                                                    echo "Multiple blocks share " . $value . " block in their content (id: " . $block["id"] . " and " . $clonedBlock["id"] . ")\n";
                                                }
                                            }
                                        }*/
                                    }
                                }
                            }
                        }
                    }

                    if (empty($contentCopy)) {
                        echo "Empty content for " . $block["type"] . " block (id: " . $block["id"] . ")\n";

                        $deleteArray["page_block"][$block["id"]] = $block;
                        // consider deleting page and navigation_link here
                    } else if ($contentCopy != $content) {
                        $updateArray["page_block"][$block["id"]]["content"] = json_encode(array_values($contentCopy));
                    }
                }
            }

            // scan for blocks with duplicate uid
            if (!empty($block["uid"])) {
                foreach ($blocks as $blockCopy) {
                    // dont compare to itself and dont compare two same blocks twice
                    if ($block["id"] == $blockCopy["id"] || $blockCopy["id"] < $block["id"]) {
                        continue;
                    }
                    /*if ($block["uid"] == $blockCopy["uid"]) {
                        echo "Blocks with duplicate uid (id: " . $block["id"] . " and " . $blockCopy["id"] . ")\n";

                        // update other block's uid
                        $newUid = md5($blockCopy["parent"] . $blockCopy["title"]);
                        $updateArray["page_block"][$blockCopy["id"]]["uid"] = $newUid;
                    }*/
                }
            }
        }

        $multiViewListViews = array();

        echo "Looking for invalid listViews...\n";
        /** @var ListView $listView */
        foreach ($listViews as $listView) {
            $listViewBlock = $this->getBlockByRelatedId("list_view", $listView["id"]);
            if (empty($listViewBlock)) {
                if ($listView["public_view"] && in_array($listView["entity_type"] . "-" . $listView["attribute_set"], $multiViewListViews)) {
                    continue;
                }
                $libraryViewBlock = $this->getBlockByRelatedId("library_view", $listView["id"]);
                if (empty($libraryViewBlock)) {
                    echo "Unused listView (id: " . $listView["id"] . ")\n";

                    $deleteArray["list_view"][$listView["id"]] = $listView;
                }
            } else {
                if ($listView["public_view"]) {
                    $multiViewListViews[] = $listView["entity_type"] . "-" . $listView["attribute_set"];
                }
            }
        }

        echo "Looking for invalid pages...\n";
        foreach ($pages as $page) {
            // check if this page is used anywhere
            // TODO: mozda restrictat samo na form/list/dashboard
            $navigationLink = $this->getNavigationLinkForPage($page["id"]);
            if (empty($navigationLink)) {
                echo "Page does not have a navigation link (id: " . $page["id"] . ")\n";
            }

            if ($page["type"] == "list" || $page["type"] == "form") {
                $contentCopy = $content = json_decode($page["content"], true);
                if (!empty($content)) {
                    foreach ($content as $key1 => $c) {
                        foreach ($c as $key2 => $value) {
                            if ($key2 == "id") {
                                if (empty($value)) {
                                    echo "Invalid value in page content (id: " . $page["id"] . ")\n";

                                    // this element should be removed from page content
                                    unset($contentCopy[$key1]);
                                } else {
                                    $result = $this->getBlockByUid($value);
                                    if (empty($result)) {
                                        echo "Invalid block " . $value . " referenced in page content (id: " . $page["id"] . ")\n";
                                        //unset($contentCopy[$key1]);
                                    }
                                }
                            }
                        }
                    }
                }

                if (empty($contentCopy)) {
                    echo "Empty content for " . $page["type"] . " page (id: " . $page["id"] . ")\n";

                    $deleteArray["page"][$page["id"]] = $page;
                    if (!empty($navigationLink)) {
                        $deleteArray["navigation_link"][$navigationLink["id"]] = $page;
                    }
                } else if ($contentCopy != $content) {
                    $updateArray["page"][$page["id"]]["content"] = json_encode(array_values($contentCopy));
                }
            }
        }

        echo "Looking for invalid navigationLinks...\n";
        foreach ($navigationLinks as $navigationLink) {
            if (!empty($navigationLink["page"])) {
                if (!isset($pages[$navigationLink["page"]])) {
                    echo "Page does not exist for navigation link (id: " . $navigationLink["id"] . ")\n";

                    $deleteArray["navigation_link"][$navigationLink["id"]] = $navigationLink;
                }
            } else {
                // page id can be empty for parent nav links or admin nav link children
                if ($navigationLink["is_parent"] != 1 && $navigationLink["parent_id"] != 8) {
                    echo "Page id missing for navigation link (id: " . $navigationLink["id"] . ")\n";

                    $deleteArray["navigation_link"][$navigationLink["id"]] = $navigationLink;
                }
            }
        }

        echo "Checking attributes...\n";
        foreach ($attributes as $attribute) {
            if (!empty($attribute["modal_page_block_id"])) {
                if (!isset($blocks[$attribute["modal_page_block_id"]])) {
                    echo "Modal page block does not exist for attribute (id: " . $attribute["id"] . ")\n";

                    $updateArray["attribute"][$attribute["id"]]["modal_page_block_id"] = 'NULL';
                }
            }
        }

        // TODO: ovo se događa jer fali unique key na role, action_type, action_code
        echo "Checking privileges...\n";
        /*foreach ($privileges as $hash => $privilege) {
            if (count($privilege) > 1) {
                foreach ($privilege as $key => $id) {
                    if ($key > 0) {
                        $deleteArray["privilege"][$id] = $id;
                    }
                }
            }
        }*/

        echo "Checking sFrontBlocks...\n";
        if (!empty($sFrontBlocks)) {
            foreach ($sFrontBlocks as $sFrontBlock) {



                $found = false;
                foreach ($sFrontBlocks as $sFrontBlockCmp) {
                    if ($sFrontBlock["id"] == $sFrontBlockCmp["id"]) {
                        continue;
                    }
                    $content = json_decode($sFrontBlockCmp["content"], true);
                    if (!empty($content)) {
                        foreach ($content as $key => $c) {
                            if ($c["id"] == $sFrontBlock["id"]) {
                                $found = true;
                                break 2;
                            }
                        }
                    }
                }
                if(!$found){
                    foreach ($sTemplateTypes as $sTemplateType) {
                        $content = json_decode($sTemplateType["content"], true);
                        if (!empty($content)) {
                            foreach ($content as $key => $c) {
                                if ($c["id"] == $sFrontBlock["id"]) {
                                    $found = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
                if(!$found){
                    foreach ($sPages as $sPage) {
                        if(!empty(trim($sPage["layout"]))){
                            $content = json_decode($sPage["layout"], true);
                            if(!empty($content)){
                                $found = $this->findBlockInContentRecursive($content,$sFrontBlock["id"]);
                                if($found){
                                    break 1;
                                }
                            }
                        }
                    }
                }

                if (!$found) {
                    $deleteArray["s_front_block_entity"][$sFrontBlock["id"]] = $sFrontBlock;
                }
            }
        }

        // TODO: foreign key + cascade
        //echo "Clearing userRoles...\n";
        //$shapeCleanerManager->clearUserRoles();

        // TODO: nakon ovoga u navigaciji "isplivaju" neki linkovi koji ne bi trebali biti parent (njih rucno pobrisat)
        //echo "Fixing navigationLinks...\n";
        //$shapeCleanerManager->updateNavigationLinks();

        // TODO: prilagodba na getDeleteQuery, getUpdateQuery

        if (!empty($deleteArray)) {
            foreach ($deleteArray as $key => $tableData) {

                /**
                 * Ako se posalje lista entiteta kojoj dozvoljavamo brisanje treba provjeriti
                 */
                if(!empty($allowedTables) && !in_array($key,$allowedTables)){
                    continue;
                }

                foreach ($tableData as $id => $entityData) {
                    if (isset($updateArray[$key]) && isset($updateArray[$key][$id])) {
                        unset($updateArray[$key][$id]);
                    }
                    $q .= "DELETE FROM {$key} WHERE id = '{$id}';\n";
                }
            }
        }

        if (!empty($updateArray)) {
            foreach ($updateArray as $key => $tableData) {

                /**
                 * Ako se posalje lista entiteta kojoj dozvoljavamo brisanje treba provjeriti
                 */
                if(!empty($allowedTables) && !in_array($key,$allowedTables)){
                    continue;
                }

                foreach ($tableData as $id => $entityData) {
                    if (!empty($entityData)) {
                        $t = "UPDATE {$key} SET ";
                        foreach ($entityData as $field => $value) {
                            $t .= "{$field} = '{$value}', ";
                        }
                        $q .= substr($t, 0, -2) . " WHERE id = '{$id}';\n";
                    }
                }
            }
        }

        if($delete && !empty($q)){
            if(empty($this->databaseContext)){
                $this->databaseContext = $this->container->get("database_context");
            }
            $this->databaseContext->executeNonQuery($q);
        }

        return $q;
    }

    /**
     * @param $content
     * @param $blockId
     * @return bool
     */
    public function findBlockInContentRecursive($content,$blockId){

        foreach ($content as $key => $c) {
            if ($c["id"] == $blockId) {
                return true;
            }
            if(isset($c["children"]) && !empty($c["children"])){
                if($this->findBlockInContentRecursive($c["children"],$blockId)){
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function fixJsonFields()
    {
        echo "Fixing JSON fields...\n";

        $defaultStoreId = $_ENV["DEFAULT_STORE_ID"];
        $tableAttributes = $this->getJsonStoreAttributes();

        $ignoreColumns = array(
            "id",
            "url"
        );

        $updateArray = array();

        if (!empty($tableAttributes)) {
            foreach ($tableAttributes as $tableName => $attributes) {
                if (!empty($attributes)) {

                    // TODO: parametri entity_type i id

                    $select = "id";
                    $where = "";
                    foreach ($attributes as $attribute) {
                        $select .= "," . $attribute;
                        $where .= "OR JSON_UNQUOTE(JSON_EXTRACT({$attribute},'$.\"{$defaultStoreId}\"')) IS NULL 
                            OR JSON_UNQUOTE(JSON_EXTRACT({$attribute},'$.\"{$defaultStoreId}\"')) = 'null'
                            OR JSON_UNQUOTE(JSON_EXTRACT({$attribute},'$.\"{$defaultStoreId}\"')) = ''";
                    }
                    $q = "SELECT " . $select . " FROM " . $tableName . " WHERE " . substr($where, 3) . ";";

                    dump($q);
                    die;

                    $data = $this->databaseContext->getAll($q);
                    if (!empty($data)) {
                        foreach ($data as $row) {
                            foreach ($row as $column => $cell) {
                                if (in_array($column, $ignoreColumns)) {
                                    continue;
                                }
                                if (!empty($cell)) {
                                    $value = json_decode($cell, true);
                                    if (!empty($value)) {
                                        if (!isset($value[$defaultStoreId]) || empty($value[$defaultStoreId])) {
                                            foreach ($value as $key => $v) {
                                                if (!empty($v)) {
                                                    $value[$defaultStoreId] = $v;
                                                    ksort($value);
                                                    $updateArray[$tableName][$row["id"]][$column] = json_encode($value);
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->executeUpdateQuery($updateArray);

        echo "Finished\n";

        return true;
    }

    /**
     * @param bool $deleteFiles
     * @return bool
     * Removes files (rows) from the database that have entity_state_id != 1 and deletes files that exist on disk but not in the database
     */
    public function removeDeletedDatabaseAndUnusedDiskFiles($deleteFiles = 0, $includedTables = array())
    {
        if ($deleteFiles == 0) {
            $deleteFiles = false;
        } else if ($deleteFiles == 1) {
            $deleteFiles = true;
        } else {
            print("Invalid argument passed!\nEnter \"0\" if you want to see how much space will be freed per folder OR \"1\" if you want removal of the said files!\n");
            return false;
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $DocumentFolderSizes = ["step1" => array(), "step2" => array(), "step3" => array()];
        $databaseFilesToBeDeletedArray = array();
        $webPath = $_ENV["WEB_PATH"];
        $excluededTables = array("ckeditor_entity", "import_manual_entity");
        $outputFileLocation = $webPath . "../var/logs/remove_deleted_database_and_unused_uisk_files.txt";
        $outputFile = (($deleteFiles) ? "Deletion" : "Scan") . " started on " . date("Y-m-d h:i:sa") . "\nFiles to be deleted from the database:";


        // If variable exists in the .env file, append it's content to exlude array
        if (isset($_ENV["EXCLUDE_ENTITIES_FROM_FILE_DELETION"]) && !empty($_ENV["EXCLUDE_ENTITIES_FROM_FILE_DELETION"])) {
            if ($additionalExcludeEntities = array_map('trim', explode(',', $_ENV["EXCLUDE_ENTITIES_FROM_FILE_DELETION"]))) {
                if (!empty($additionalExcludeEntities) && is_array($additionalExcludeEntities)) {
                    $excluededTables = array_merge($excluededTables, $additionalExcludeEntities);
                }
            } else {
                print("'EXCLUDE_ENTITIES_FROM_FILE_DELETION' string-array from the env file is not formated as an array correctly!\n");
                return false;
            }
        }

        // List of all entities that have filled "folder" attribute
//        $q = "SELECT attribute_code, backend_model, backend_table, folder, frontend_label FROM attribute WHERE folder IS NOT NULL AND folder != '' AND attribute_code NOT IN ('file_source') AND attribute_code IN ('file', 'profile_image', 'icon') GROUP BY backend_model;";
        $getAllFoldersQuery = "SELECT attribute_code, backend_model, backend_table, folder, frontend_label FROM attribute WHERE folder IS NOT NULL AND folder != '' AND attribute_code NOT IN (\"file_source\");";
        $allDocumentFolders = $this->databaseContext->getAll($getAllFoldersQuery);

        if ($deleteFiles) {
            print("Starting deletion of all the files that have entity_state_id != 1...\n");
        } else {
            print("Scanning all files that have entity_state_id != 1!\n");
        }

        if (!empty($allDocumentFolders)) {

            foreach ($allDocumentFolders as $documentFolder) {

                if (in_array($documentFolder["backend_table"], $excluededTables)) {
                    continue;
                }

                if (!empty($includedTables) && !in_array($documentFolder["backend_table"], $includedTables)) {
                    continue;
                }

                //Trim {id}/ from the file path if it exists
                $documentFolderPath = str_replace("{id}/", "", ($webPath . substr($documentFolder["folder"], 1, strlen($documentFolder["folder"]))));
                $DocumentFolderSizes["step1"][$documentFolder["folder"]] = $this->getFolderSize($documentFolderPath);

                $q = "SELECT {$documentFolder['attribute_code']}, id FROM {$documentFolder['backend_table']} WHERE entity_state_id != 1;";
                $tableData = $this->databaseContext->getAll($q);

                $totalDeletedFileSize = 0;
                $deletionItemCounter = 0;

                // If some rows are found in the database
                if (!empty($tableData)) {

                    // Iterate rows with entity_state_id != 1
                    foreach ($tableData as $data) {

                        $filePath = $documentFolderPath . $data[key($data)];

                        if ($deleteFiles) {
                            $uniqueKey = "{$documentFolder["backend_table"]}_{$data['id']}";
                            $databaseFilesToBeDeletedArray[$uniqueKey]["query"] = "DELETE FROM {$documentFolder["backend_table"]} WHERE id = {$data['id']};";

                            if (file_exists($filePath) && !empty($data[$documentFolder['attribute_code']])) {
                                $totalDeletedFileSize += filesize($filePath);
                                $outputFile .= "\n\t{$filePath}";
                                $databaseFilesToBeDeletedArray[$uniqueKey]["file"] = $filePath;
                            }
                            $deletionItemCounter += 1;
                        } else {
                            if (file_exists($filePath) && !empty($data[$documentFolder['attribute_code']])) {
                                $outputFile .= "\n\t{$filePath}";
                                $totalDeletedFileSize += filesize($filePath);
                            }
                        }

                        // If there are 100 or more rows in the deletionArray, delete them and continue
                        if ($deletionItemCounter >= 100) {

                            foreach ($databaseFilesToBeDeletedArray as $deletionItem) {

                                if (isset($deletionItem["query"]) && !empty($deletionItem["query"])) {
                                    $this->databaseContext->executeNonQuery($deletionItem["query"]);
                                }

                                if (isset($deletionItem["file"]) && !empty($deletionItem["file"])) {
                                    unlink($deletionItem["file"]);
                                }
                            }

                            $deletionItemCounter = 0;
                            $databaseFilesToBeDeletedArray = array();
                        }
                    }

                    $DocumentFolderSizes["step2"][$documentFolder["folder"]] = $totalDeletedFileSize;
                }
            }

            // Delete any remaining files in the array
            foreach ($databaseFilesToBeDeletedArray as $deletionItem) {

                if (isset($deletionItem["query"]) && !empty($deletionItem["query"])) {
                    $this->databaseContext->executeNonQuery($deletionItem["query"]);
                }

                if (isset($deletionItem["file"]) && !empty($deletionItem["file"])) {
                    unlink($deletionItem["file"]);
                }
            }

            $deletionItemCounter = 0;
            $databaseFilesToBeDeletedArray = array();

            if ($deleteFiles) {
                print("Finished deleting all files that have entity_state_id != 1!\n");
            } else {
                print("Finished scanning all files that have entity_state_id != 1!\n");
            }

            if ($deleteFiles) {
                print("Starting deletion of all the files that are not in the database...\n");
            } else {
                print("Scanning all the files that are not in the database...\n");
            }

            $outputFile .= "\n\nFiles that exist on disk but not within the database:";
            $groupedDocumentFoldersBySaveFolder = array();

            foreach ($allDocumentFolders as $documentFolder) {
                if (!isset($groupedDocumentFoldersBySaveFolder[$documentFolder["folder"]])) {
                    $groupedDocumentFoldersBySaveFolder[$documentFolder["folder"]][] = $documentFolder;
                } else {
                    $groupedDocumentFoldersBySaveFolder[$documentFolder["folder"]][] = $documentFolder;
                }
            }

            foreach ($groupedDocumentFoldersBySaveFolder as $entityDocumentName => $groupFolderDocument) {

                $skipDocument = false;
                foreach ($groupFolderDocument as $attribute) {
                    if (in_array($attribute["backend_table"], $excluededTables)) {
                        $skipDocument = true;
                        break;
                    }

                    if (!empty($includedTables) && !in_array($attribute["backend_table"], $includedTables)) {
                        $skipDocument = true;
                        break;
                    }
                }

                if ($skipDocument) {
                    unset($groupedDocumentFoldersBySaveFolder[$entityDocumentName]);
                    continue;
                }

                $existingFilesToBeDeletedArray = [];
                $totalDeletedFileSize = 0;
                $documentFolderPath = substr($entityDocumentName, 1, strlen($entityDocumentName));
                $fullDocumentFolderPath = $webPath . $documentFolderPath;

                /**
                 * Delete '{id}/' from the folder path if it exists
                 */
                if (strpos($documentFolderPath, "{id}/")) {
                    $documentFolderPath = preg_replace("#\{id\}\/#", "", $documentFolderPath);
                    $fullDocumentFolderPath = preg_replace("#\{id\}\/#", "", $fullDocumentFolderPath);
                }

                /**
                 * Get list of all existing files for the given entity from the folder
                 */
                $dirContents = $this->getDirContents($webPath, $documentFolderPath);
                $existingFilesToBeDeletedArray = $dirContents;

                /**
                 * Iterate through all documents from the database
                 */
                foreach ($groupFolderDocument as $documentFolder) {

                    /**
                     * Get list of all existing files for the given entity from the database
                     */
                    $databaseFilesArray = $this->getListOfAllFilesForGivenEntity($documentFolder["backend_table"], $documentFolder["attribute_code"]);

                    /**
                     * If list of found folder files is not empty
                     */
                    if (!empty($existingFilesToBeDeletedArray)) {

                        /**
                         * Iterate through all database files and search for it in the list of files from the database. If the file is found, unset it from the deletion array
                         */
                        foreach ($databaseFilesArray as $databaseFile => $databaseFileId) {

                            /**
                             * Get full path for the DB file attribute
                             */
                            $databaseFileWithDocumentPath = $documentFolderPath . $databaseFile;

                            if (isset($existingFilesToBeDeletedArray[$databaseFileWithDocumentPath])) {
                                unset($existingFilesToBeDeletedArray[$databaseFileWithDocumentPath]);
                            }
                        }
                    }
                }

                /**
                 * If some files are left in the deletion array, iterate through them and delete them
                 */

                if (!empty($existingFilesToBeDeletedArray)) {

                    foreach ($existingFilesToBeDeletedArray as $fileToBeDeletedPath => $value) {
                        $fullFilePath = $webPath . $fileToBeDeletedPath;

                        if (file_exists($fullFilePath)) {
                            $totalDeletedFileSize += filesize($fullFilePath);
                            $outputFile .= "\n\t->{$fullFilePath}";
                            if ($deleteFiles) {
                                unlink($fullFilePath);
                                print("Deleting {$fullFilePath} file.\n");
                            }
                        }
                    }
                }

                $DocumentFolderSizes["step3"][$documentFolder["folder"]] = $totalDeletedFileSize;
            }

            if ($deleteFiles) {
                print("Finished deleting all the files that are not in the database...\n");
            } else {
                print("Finished scanning all the files that are not in the database...\n");
            }

        } else {
            return false;
            // TODO no attributes with filled 'folder' field were found
        }

        $totalSizeOfDeletedFiles = 0;
        // Block below is used for terminal output
        $mask = "|%-39.39s |%-20.20s |%-42.42s |%-37.37s |%-19.19s |\n";
        printf($mask, 'Document', 'Start size', "Size of files with entity_state_id != 1", "Size of files that are not in the DB", "Total to be deleted");
        foreach ($DocumentFolderSizes["step1"] as $documentKey => $documentFolder) {
            $step1Size = floatval($documentFolder);
            $step1SizeMb = FileHelper::formatSizeUnits($step1Size);
            $step2SizeBeforeMb = FileHelper::formatSizeUnits(0);
            $step3SizeBeforeMb = FileHelper::formatSizeUnits(0);
            $step4TotalDeleted = 0;
            $step4TotalDeletedMb = FileHelper::formatSizeUnits(0);

            if (isset($DocumentFolderSizes["step2"][$documentKey])) {

                $step2SizeBefore = floatval($DocumentFolderSizes["step2"][$documentKey]);
                $step2SizeBeforeMb = FileHelper::formatSizeUnits($step2SizeBefore);
                $step2SizeAfter = $step1Size - (floatval($DocumentFolderSizes["step2"][$documentKey]));
                $step2SizeAfterMb = FileHelper::formatSizeUnits($step2SizeAfter);
                $step4TotalDeleted += $step2SizeBefore;

                $documentFolder = str_replace("/Documents/", "", str_replace("{id}/", "", $documentKey));
            }

            if (isset($DocumentFolderSizes["step3"][$documentKey])) {

                $step3SizeBefore = floatval($DocumentFolderSizes["step3"][$documentKey]);
                $step3SizeBeforeMb = FileHelper::formatSizeUnits($step3SizeBefore);
                $step3SizeAfter = $step1Size - ($step3SizeBefore);
                $step3SizeAfterMb = FileHelper::formatSizeUnits($step3SizeAfter);
                $step4TotalDeleted += $step3SizeBefore;

                $documentFolder = str_replace("/Documents/", "", str_replace("{id}/", "", $documentKey));
            }

            $totalSizeOfDeletedFiles += $step4TotalDeleted;
            $step4TotalDeletedMb = FileHelper::formatSizeUnits($step4TotalDeleted);

            printf($mask, $documentFolder, "{$step1SizeMb}", "{$step2SizeBeforeMb}", "{$step3SizeBeforeMb}", $step4TotalDeletedMb);
        }

        print("------------------------------------------------------------------------------------------------------------------------------------------------------------------------\n");
        printf($mask, "TOTAL", "", "", "", FileHelper::formatSizeUnits($totalSizeOfDeletedFiles));

        $this->saveFile($outputFileLocation, $outputFile);
        print("List of all the files to be deleted saved at '{$outputFileLocation}'!\n");

        return true;
    }

    /**
     * @param $dir
     * @return false|int|mixed
     */
    private function getFolderSize($dir)
    {
        $size = 0;

        foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $each) {
            $size += is_file($each) ? filesize($each) : $this->getFolderSize($each);
        }

        return $size;
    }

    /**
     * @param $webPath
     * @param $dir
     * @param array $results
     * @return array|mixed
     */
    private function getDirContents($webPath, $dir, &$results = array())
    {
        if (!is_dir($webPath . $dir)) {
            return [];
        }
        $files = scandir($webPath . $dir);
        foreach ($files as $key => $value) {
            if ($value == "." || $value == "..") {
                continue;
            }

            $path = realpath($webPath . $dir . $value);
            if (!is_dir($path) && is_file($path)) {
                $results[$dir . $value] = true;
            } else {
                $this->getDirContents($webPath, $dir . $value . "/", $results);
            }
        }

        return $results;
    }

    /**
     * @param $entityType
     * @param $attribute
     * @return array
     */
    private function getListOfAllFilesForGivenEntity($entityType, $attribute)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $res = [];

        $q = "SELECT {$attribute}, id FROM {$entityType};";
        $files = $this->databaseContext->getAll($q);

        if (!empty($files)) {

            foreach ($files as $file) {
                $res[$file[$attribute]] = $file["id"];
            }

        }

        return $res;
    }

    /**
     * @param $entity
     * @param $attribute
     * @param $tag
     * @param $lookupAttribute
     * @param string $remoteBaseDownloadUrl
     */
    public function downloadAndReplaceTagFromAttribute($entity, $attribute, $tag, $lookupAttribute, $remoteBaseDownloadUrl = "")
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (empty($this->helperManager)) {
            $this->helperManager = $this->getContainer()->get("helper_manager");
        }

        $downloadFolder = "Documents/Ckeditor";
        $webFolder = $_ENV["WEB_PATH"];

        if (!file_exists($webFolder . $downloadFolder)) {
            mkdir($webFolder . $downloadFolder, 0777, true);
        }

        $return = ["downloaded_images" => 0, "found_images" => 0, "replaced_images" => 0, "already_on_disk" => 0, "invalid_images" => array()];

        $existingShapeEntities = $this->getEntitiesArray(array("entity_type_code"), "entity_type", array("entity_type_code"));
        if (!isset($existingShapeEntities[$entity])) {
            throw new \Exception("No entity found for '{$entity}'!\n");
        }

        $existingShapeAttributes = $this->getEntitiesArray(array("a.attribute_code"), "entity_type", array("attribute_code"), "INNER JOIN attribute AS a ON a.entity_type_id = a1.id");
        if (!isset($existingShapeAttributes[$attribute])) {
         throw new \Exception("Entity '{$entity}' doesn't contain attribute '$attribute'!\n");
            return false;
        }

        $existingShapeEntity = $this->getEntitiesArray(array("a1.{$attribute}"), "{$entity}_entity", array("id"));

        foreach ($existingShapeEntity as $entityItem) {

            $value = $entityItem[$attribute];
            $newValue = $value;

            preg_match_all('$<' . $tag . '[^<>]*' . $lookupAttribute . '\=[\\\\]{0,1}\"[^\"]*\".*?[>]$i', $value, $matches);

            if (!isset($matches[0])) {
                continue;
            }

            foreach ($matches[0] as $match) {

                if (empty($match)) {
                    continue;
                }

                $return["found_images"] += 1;

                $contentUrl = preg_replace('$^.*?' . $lookupAttribute . '\=[\\\\]{0,1}\"|\".*$i', "", $match);
                $downloadUrl = $remoteBaseDownloadUrl . substr_replace($contentUrl, "", -1);

                if (strpos($downloadUrl, "{$downloadFolder}")) {
                    continue;
                }

                $fileName = basename($downloadUrl);
                $targetFile = $webFolder . $downloadFolder . "/" . $fileName;

                if (!file_exists($targetFile)) {
                    if (!@getimagesize($downloadUrl)) {
                        $return["invalid_images"][] = $entityItem["id"];
                        continue;
                    }
                    print("Downloading image '{$downloadUrl}'!\n");
                    $filesize = $this->helperManager->saveRemoteFileToDisk($downloadUrl, $targetFile);
                    $return["downloaded_images"] += 1;
                    if (empty($filesize)) {
                        continue;
                    }

                } else {
                    $return["already_on_disk"] += 1;
                    print("found image '{$targetFile}' on disk!\n");
                }
                $replacementShapeUrl = "/" . $downloadFolder . "/" . $fileName . "\\";
                $newValue = str_replace($contentUrl, $replacementShapeUrl, $newValue);
                $return["replaced_images"] += 1;
            }

            $updateArray = array();
            if ($newValue != $value) {

                $itemUpdateArray[$attribute] = $newValue;

                $updateArray["{$entity}_entity"][$entityItem["id"]] = $itemUpdateArray;

                $updateQuery = $this->getUpdateQuery($updateArray);
                if (!empty($updateQuery)) {
                    $this->databaseContext->executeNonQuery($updateQuery);
                    print("Updated {$entity} entity with an id of '{$entityItem["id"]}'\n");
                }
            }
        }

        print("\nFound {$tag}s = " . $return["found_images"] . "\n");
        print("Downloaded {$tag}s = " . $return["downloaded_images"] . "\n");
        print("Images already on the disk = " . $return["already_on_disk"] . "\n");
        print("Replaced {$tag}s = " . $return["replaced_images"] . "\n");
        if(!empty($invalidTags = implode(", " ,$return["invalid_images"]))){
            print("Invalid {$tag} tags left in {$attribute} attribute of {$entity}_entity with an ids of {$invalidTags}\n");
        }

        return true;
    }
}