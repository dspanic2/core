<?php

// php bin/console scommerce:update add_stores_to_entities
// php bin/console scommerce:update add_stores_to_entities brand name text_store 1 #enttiy_type attribute_code frontendt_type is_custom
// php bin/console scommerce:update convert_to_translatable office worktime textarea_store
// php bin/console scommerce:update update_urls product

// composer require "doctrine/orm:^2.6" --update-with-dependencies
// composer require scienta/doctrine-json-functions

namespace AppBundle\Command\Deprecated;

use AppBundle\Context\AttributeGroupContext;
use Symfony\Component\Console\Question\Question;
use AppBundle\Context\AttributeContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\EntityType;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\DatabaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Managers\HelperManager;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Managers\EntityManager;

class ScommerceUpdateCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('scommerce:update')
            ->SetDescription('description of what the command')
            ->AddArgument('type', InputArgument::OPTIONAL, 'which function ')
            ->AddArgument('entity_type_code', InputArgument::OPTIONAL, 'entity type code')
            ->AddArgument('attribute', InputArgument::OPTIONAL, 'attribute code')
            ->AddArgument('store_field_type', InputArgument::OPTIONAL, 'store field type')
            ->AddArgument('is_custom', InputArgument::OPTIONAL, 'is custom');
    }

    public function getStores()
    {

        $databaseContext = $this->getContainer()->get("database_context");

        $q = "SELECT s.id as store_id, s.name as store_name, l.code as language_code, l.id as language_id, w.id as website_id, w.name as website_name FROM s_store_entity as s 
        LEFT JOIN core_language_entity as l ON s.core_language_id = l.id
        LEFT JOIN s_website_entity as w ON s.website_id = w.id
        ORDER BY w.id ASC, s.id ASC";

        return $databaseContext->getAll($q);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Logger $logger */
        $logger = $this->getContainer()->get('logger');

        /** @var HelperManager $helperManager */
        $helperManager = $this->getContainer()->get("helper_manager");

        $request = new Request();
        $helperManager->loginAnonymus($request, "system");

        /** @var EntityManager $entityManager */
        $entityManager = $this->getContainer()->get("entity_manager");
        /**
         * Check which function
         */
        $func = $input->getArgument('type');
        if (empty($func)) {
            throw new \Exception('Function not defined');
        }
        try {
            /** @var DatabaseManager $databaseManager */
            $databaseManager = $this->getContainer()->get("database_manager");
            /** @var AdministrationManager $administrationManager */
            $administrationManager = $this->getContainer()->get("administration_manager");
            /** @var DatabaseContext $databaseContext */
            $databaseContext = $this->getContainer()->get("database_context");
            /** @var AttributeGroupContext $attributeGroupContext */
            $attributeGroupContext = $this->getContainer()->get("attribute_group_context");
            /** @var AttributeContext $attributeContext */
            $attributeContext = $this->getContainer()->get("attribute_context");

            $stores = $this->getStores();
            $baseStore = $stores[0];
            if ($func == "add_stores_to_entities") {
                $arrayToUpdate = array(
//                    "product_group" => array(
//                        "attributes" => array("name", "url", "meta_title", "meta_description", "meta_keywords", "description", "show_on_store"),
//                        "attribute_group" => "Product group details"
//                    ),
//                    "s_page" => array(
//                        "attributes" => array("name", "url", "meta_title", "meta_description", "meta_keywords", "content", "show_on_store"),
//                        "attribute_group" => "Page information"
//                    ),
//                    "product" => array(
//                        "attributes" => array("name", "meta_title", "meta_description", "meta_keywords", "description", "short_description", "show_on_store", "url"),
//                        "attribute_group" => "Basic details"
//                    ),
//                    "blog_category" => array(
//                        "attributes" => array("name", "meta_title", "meta_description", "meta_keywords", "description", "show_on_store", "url"),
//                        "attribute_group" => "Description"
//                    ),
//                    "blog_post" => array(
//                        "attributes" => array("name", "meta_title", "meta_description", "meta_keywords", "content", "show_on_store", "url"),
//                        "attribute_group" => "blog post details"
//                    ),
//                    "s_front_block" => array(
//                        "attributes" => array("editor", "main_title", "subtitle", "url", "show_on_store"),
//                        "attribute_group" => "s front block details"
//                    ),
//                    "faq" => array(
//                        "attributes" => array("name", "description", "show_on_store"),
//                        "attribute_group" => "faq details"
//                    ),
//                    "delivery_type" => array(
//                        "attributes" => array("name", "description"),
//                    ),
//                    "payment_type" => array(
//                        "attributes" => array("name", "description"),
//                    ),
//                    "testimonials" => array(
//                        "attributes" => array("name", "content", "show_on_store"),
//                        "attribute_group" => "testimonials details"
//                    ),
//                    "country" => array(
//                        "attributes" => array("name"),
//                    ),
//                    "office" => array(
//                        "attributes" => array("worktime"),
//                    ),
//                    "office_type" => array(
//                        "attributes" => array("name"),
//                    ),
                );
                if (empty($arrayToUpdate)) {
                    $entityTypeCode = $input->getArgument('entity_type_code');
                    if (empty($entityTypeCode)) {
                        throw new \Exception('Entity type code not defined');
                    }
                    $attribute = $input->getArgument('attribute');
                    if (empty($attribute)) {
                        throw new \Exception('Attribute not defined');
                    }
                    $attrGroup = $input->getArgument('store_field_type');
                    if (empty($attrGroup)) {
                        throw new \Exception('Store field type not defined');
                    }

                    $arrayToUpdate[$entityTypeCode] = [
                        "attributes" => [$attribute],
                        "attribute_group" => $attrGroup,
                    ];
                }

                foreach ($arrayToUpdate as $entityTypeCode => $attributes) {
                    print("Processing {$entityTypeCode}\r\n");

                    $addAttribute = true;
                    $addAttributeUrl = false;
                    $addAttributeTemplateType = false;
                    if ($entityTypeCode == "product" || $entityTypeCode == "blog_category") {
                        $addAttributeUrl = true;
                    }
                    if ($entityTypeCode == "product") {
                        $addAttributeTemplateType = true;
                    }
                    if (in_array($entityTypeCode, array("delivery_type", "payment_type", "country", "office_type", "office"))) {
                        $addAttribute = false;
                    }

                    foreach ($attributes["attributes"] as $attribute) {

                        $attributeFixQuery = "";
                        foreach ($attributes["attributes"] as $attribute) {
                            $attributeFixQuery .= "UPDATE {$entityTypeCode}_entity SET {$attribute} = REPLACE(REPLACE(REPLACE(REPLACE(TRIM({$attribute}), '\\t', ''), '\\t', ''), '\\r', ''), '\\n', '');";
                        }
                        $databaseContext->executeNonQuery($attributeFixQuery);

                        $q = "SELECT * FROM attribute WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code = '{$attribute}';";
                        $attributeData = $databaseContext->getAll($q);

                        if (empty($attributeData)) {
                            if (($key = array_search($attribute, $attributes["attributes"])) !== false) {
                                unset($attributes["attributes"][$key]);
                            }
                            continue;
                        }

                        $attributeData = $attributeData[0];

                        if ($attributeData["attribute_code"] == "show_on_store") {
                            $addAttribute = false;
                        }
                        if (($entityTypeCode == "product" || $entityTypeCode == "blog_category") && $attributeData["attribute_code"] == "url") {
                            $addAttributeUrl = false;
                        }

                        if ($attributeData["backend_type"] == "json") {
                            if (($key = array_search($attribute, $attributes["attributes"])) !== false) {
                                unset($attributes["attributes"][$key]);
                            }
                            continue;
                        }
                    }

                    $q = "SELECT * FROM {$entityTypeCode}_entity LIMIT 1";
                    $dataCheck = $databaseContext->getSingleEntity($q);
                    if ($addAttribute) {
                        if (!array_key_exists("show_on_store", $dataCheck)) {
                            $entityType = $entityManager->getEntityTypeByCode($entityTypeCode);
                            $attributeSet = $entityManager->getAttributeSetByCode($entityTypeCode);
                            if (empty($attributeSet)) {
                                throw new \Exception("Missing attribute set for $entityTypeCode");
                            }

                            $attributeGroup = $attributeGroupContext->getOneBy(array("attributeSet" => $attributeSet->getId(), "attributeGroupName" => $attributes["attribute_group"]));

                            $show_on_store_attribute = array();
                            $show_on_store_attribute["frontendLabel"] = "Show on store";
                            $show_on_store_attribute["frontendInput"] = "checkbox_store";
                            $show_on_store_attribute["frontendType"] = "checkbox_store";
                            $show_on_store_attribute["frontendModel"] = null;
                            $show_on_store_attribute["frontendHidden"] = 0;
                            $show_on_store_attribute["readOnly"] = 0;
                            $show_on_store_attribute["frontendDisplayOnNew"] = 1;
                            $show_on_store_attribute["frontendDisplayOnUpdate"] = 1;
                            $show_on_store_attribute["frontendDisplayOnView"] = 1;
                            $show_on_store_attribute["frontendDisplayOnPreview"] = 1;
                            $show_on_store_attribute["attributeCode"] = "show_on_store";
                            $show_on_store_attribute["backendType"] = "json";
                            $show_on_store_attribute["isCustom"] = 0;

                            $ret = $administrationManager->createAttribute($entityType, $attributeSet, $attributeGroup, $show_on_store_attribute);
                            if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                                dump("error creating attribute");
                            }

                            $databaseManager->createTableIfDoesntExist($entityType, null);
                            $administrationManager->generateDoctrineXML($entityType, true);
                            $administrationManager->generateEntityClasses($entityType, true);

                            $attributes["attributes"][] = "show_on_store";
                        }
                    }
                    if ($addAttributeUrl) {
                        if (!array_key_exists("url", $dataCheck)) {
                            $entityType = $entityManager->getEntityTypeByCode($entityTypeCode);
                            $attributeSet = $entityManager->getAttributeSetByCode($entityTypeCode);

                            $attributeGroup = $attributeGroupContext->getOneBy(array("attributeSet" => $attributeSet->getId(), "attributeGroupName" => $attributes["attribute_group"]));

                            $show_on_store_attribute = array();
                            $show_on_store_attribute["frontendLabel"] = "Url";
                            $show_on_store_attribute["frontendInput"] = "text_store";
                            $show_on_store_attribute["frontendType"] = "text_store";
                            $show_on_store_attribute["frontendModel"] = null;
                            $show_on_store_attribute["frontendHidden"] = 0;
                            $show_on_store_attribute["readOnly"] = 1;
                            $show_on_store_attribute["frontendDisplayOnNew"] = 1;
                            $show_on_store_attribute["frontendDisplayOnUpdate"] = 1;
                            $show_on_store_attribute["frontendDisplayOnView"] = 1;
                            $show_on_store_attribute["frontendDisplayOnPreview"] = 1;
                            $show_on_store_attribute["attributeCode"] = "url";
                            $show_on_store_attribute["backendType"] = "json";
                            $show_on_store_attribute["isCustom"] = 0;

                            $ret = $administrationManager->createAttribute($entityType, $attributeSet, $attributeGroup, $show_on_store_attribute);
                            if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                                dump("error creating attribute");
                            }

                            $databaseManager->createTableIfDoesntExist($entityType, null);
                            $administrationManager->generateDoctrineXML($entityType, true);
                            $administrationManager->generateEntityClasses($entityType, true);

                            $attributes["attributes"][] = "url";
                        }
                    }

                    if ($addAttributeTemplateType) {
                        if (!array_key_exists("template_type_id", $dataCheck)) {
                            $entityType = $entityManager->getEntityTypeByCode($entityTypeCode);
                            $attributeSet = $entityManager->getAttributeSetByCode($entityTypeCode);

                            $attributeGroup = $attributeGroupContext->getOneBy(array("attributeSet" => $attributeSet->getId(), "attributeGroupName" => $attributes["attribute_group"]));

                            $show_on_store_attribute = array();
                            $show_on_store_attribute["frontendLabel"] = "Template type";
                            $show_on_store_attribute["frontendInput"] = "lookup";
                            $show_on_store_attribute["frontendType"] = "autocomplete";
                            $show_on_store_attribute["frontendModel"] = "default";
                            $show_on_store_attribute["frontendHidden"] = 0;
                            $show_on_store_attribute["readOnly"] = 0;
                            $show_on_store_attribute["frontendDisplayOnNew"] = 1;
                            $show_on_store_attribute["frontendDisplayOnUpdate"] = 1;
                            $show_on_store_attribute["frontendDisplayOnView"] = 1;
                            $show_on_store_attribute["frontendDisplayOnPreview"] = 1;
                            $show_on_store_attribute["attributeCode"] = "template_type_id";
                            $show_on_store_attribute["backendType"] = "lookup";
                            $show_on_store_attribute["isCustom"] = 0;

                            /** @var EntityType $templateTypeEntityType */
                            $templateTypeEntityType = $entityManager->getEntityTypeByCode("s_template_type");
                            /** @var AttributeSet $templateTypeAttributeSet */
                            $templateTypeAttributeSet = $administrationManager->getDefaultAttributeSet($templateTypeEntityType);
                            /** @var Attribute $sourceAttribute */
                            $sourceAttribute = $attributeContext->getAttributeByCode("name", $templateTypeEntityType);

                            $show_on_store_attribute["lookupEntityType"] = $templateTypeEntityType;
                            $show_on_store_attribute["lookupAttributeSet"] = $templateTypeAttributeSet;
                            $show_on_store_attribute["lookupAttribute"] = $sourceAttribute;
                            $show_on_store_attribute["enableModalCreate"] = 0;
                            $show_on_store_attribute["useLookupLink"] = 0;
                            $show_on_store_attribute["modalPageBlockId"] = null;

                            $ret = $administrationManager->createAttribute($entityType, $attributeSet, $attributeGroup, $show_on_store_attribute);
                            if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                                dump("error creating attribute");
                            }

                            $databaseManager->createTableIfDoesntExist($entityType, null);
                            $administrationManager->generateDoctrineXML($entityType, true);
                            $administrationManager->generateEntityClasses($entityType, true);

                        }
                    }

                    if (empty($attributes["attributes"])) {
                        continue;
                    }

                    $where = "WHERE show_on_store is null";
                    if (in_array($entityTypeCode, array("delivery_type", "payment_type", "country", "office_type", "office"))) {
                        $where = "";
                    }

                    $q = "SELECT id," . implode(", ", $attributes["attributes"]) . " FROM {$entityTypeCode}_entity {$where}";
                    $data = $databaseContext->getAll($q);

                    if (empty($data)) {
                        continue;
                    }

                    /**
                     * Update data to minimize json errors
                     */
                    /*if($entityTypeCode == "s_front_block_entity"){
                        $q = "UPDATE {$entityTypeCode}_entity SET editor = REPLACE(REPLACE(editor, '\r', ''), '\n', '');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET editor = REPLACE(TRIM(editor), '\t', '');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET editor = REPLACE(editor, '=\"', '=\\\"');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET editor = REPLACE(editor, '\" ', '\\\" ');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET editor = REPLACE(editor, '\">', '\\\">');";
                        $databaseContext->executeNonQuery($q);
                    }
                    elseif($entityTypeCode == "s_page_entity"){
                        $q = "UPDATE {$entityTypeCode}_entity SET content = REPLACE(REPLACE(content, '\r', ''), '\n', '');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET content = REPLACE(TRIM(content), '\t', '');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET content = REPLACE(content, '=\"', '=\\\"');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET content = REPLACE(content, '\" ', '\\\" ');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET content = REPLACE(content, '\">', '\\\">');";
                        $databaseContext->executeNonQuery($q);
                    }
                    elseif($entityTypeCode == "s_front_block_entity"){
                        $q = "UPDATE {$entityTypeCode}_entity SET editor = REPLACE(REPLACE(editor, '\r', ''), '\n', '');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET editor = REPLACE(TRIM(editor), '\t', '');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET editor = REPLACE(editor, '=\"', '=\\\"');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET editor = REPLACE(editor, '\" ', '\\\" ');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET editor = REPLACE(editor, '\">', '\\\">');";
                        $databaseContext->executeNonQuery($q);
                    }
                    elseif($entityTypeCode == "faq_entity"){
                        $q = "UPDATE {$entityTypeCode}_entity SET description = REPLACE(REPLACE(description, '\r', ''), '\n', '');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET description = REPLACE(TRIM(description), '\t', '');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET description = REPLACE(description, '=\"', '=\\\"');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET description = REPLACE(description, '\" ', '\\\" ');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET description = REPLACE(description, '\">', '\\\">');";
                        $databaseContext->executeNonQuery($q);
                    }
                    elseif($entityTypeCode == "blog_post_entity"){
                        $q = "UPDATE {$entityTypeCode}_entity SET content = REPLACE(REPLACE(content, '\r', ''), '\n', '');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET content = REPLACE(TRIM(content), '\t', '');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET content = REPLACE(content, '=\"', '=\\\"');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET content = REPLACE(content, '\" ', '\\\" ');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET content = REPLACE(content, '\">', '\\\">');";
                        $databaseContext->executeNonQuery($q);
                    }
                    elseif($entityTypeCode == "product_entity"){
                        $q = "UPDATE {$entityTypeCode}_entity SET description = REPLACE(REPLACE(description, '\r', ''), '\n', '');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET description = REPLACE(TRIM(description), '\t', '');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET description = REPLACE(description, '=\"', '=\\\"');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET description = REPLACE(description, '\" ', '\\\" ');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE {$entityTypeCode}_entity SET description = REPLACE(description, '\">', '\\\">');";
                        $databaseContext->executeNonQuery($q);
                    }*/

                    //TODO evo svi queriji pa se moze okinut rucno
                    #UPDATE s_page_entity SET content = REPLACE(REPLACE(content, '\r', ''), '\n', '');
                    #UPDATE s_page_entity SET content = REPLACE(TRIM(content), '\t', '');
                    #UPDATE s_page_entity SET content = REPLACE(content, '=\"', '=\\"');
                    #UPDATE s_page_entity SET content = REPLACE(content, '\" ', '\\" ');
                    #UPDATE s_page_entity SET content = REPLACE(content, '\">', '\\">');

                    #UPDATE product_entity SET `meta_title` = REPLACE(name, '{\"3\":\"{\"3\":\"{\"3\":\"{\"3\":\"', '{\"3\":\"');
                    #UPDATE product_entity SET `meta_title` = REPLACE(name, '\"4\":null}\",\"4\":null}\",\"4\":null}\",\"4\":null}', '\"4\":null}');
                    #UPDATE product_entity SET `meta_title` = REPLACE(name, '{\"3\":\"{\"3\":\"', '{\"3\":\"');
                    #UPDATE product_entity SET `meta_title` = REPLACE(name, '\"4\":null}\",\"4\":null}', '\"4\":null}');
                    #UPDATE product_entity SET short_description = '{"3":null,"4":null}';
                    #UPDATE product_entity SET description = '{"3":null,"4":null}';
                    #UPDATE product_entity SET meta_keywords = '{"3":null,"4":null}';

                    #UPDATE product_entity SET name = REPLACE(name, ' \"', '\\"');
                    #UPDATE product_entity SET meta_description = REPLACE(meta_description, ' \"', '\\"');
                    #UPDATE product_entity SET meta_title = REPLACE(meta_title, ' \"', '\\"');
                    #UPDATE product_entity SET name = REPLACE(name, '\" ', '\\" ');
                    #UPDATE product_entity SET meta_description = REPLACE(meta_description, '\" ', '\\" ');
                    #UPDATE product_entity SET meta_title = REPLACE(meta_title, '\" ', '\\" ');

                    #UPDATE product_entity SET name = REPLACE(name, '\"\",', '\\"\",');
                    #UPDATE product_entity SET meta_description = REPLACE(meta_description, '\"\",', '\\"\",');
                    #UPDATE product_entity SET meta_title = REPLACE(meta_title, '\"\",', '\\"\",');

                    #UPDATE product_entity SET name = REPLACE(name, ':\"\"', ':\"\\"');
                    #UPDATE product_entity SET meta_description = REPLACE(meta_description, ':\"\"', ':\"\\"');
                    #UPDATE product_entity SET meta_title = REPLACE(meta_title, ':\"\"', ':\"\\"');

                    #UPDATE product_entity SET name = REPLACE(name, ':\\"', ':\"');
                    #UPDATE product_entity SET meta_description = REPLACE(meta_description, ':\\"', ':\"');
                    #UPDATE product_entity SET meta_title = REPLACE(meta_title, ':\\"', ':\"');

                    #UPDATE product_entity SET name = REPLACE(name, ':\\"', ':\"');
                    #UPDATE product_entity SET meta_description = REPLACE(meta_description, ':\\"', ':\"');
                    #UPDATE product_entity SET meta_title = REPLACE(meta_title, ':\\"', ':\"');

                    ##S front block
                    #UPDATE s_front_block_entity SET editor = REPLACE(REPLACE(editor, '\r', ''), '\n', '');
                    #UPDATE s_front_block_entity SET editor = REPLACE(TRIM(editor), '\t', '');
                    #UPDATE s_front_block_entity SET editor = REPLACE(editor, '=\"', '=\\"');
                    #UPDATE s_front_block_entity SET editor = REPLACE(editor, '\" ', '\\" ');
                    #UPDATE s_front_block_entity SET editor = REPLACE(editor, '\">', '\\">');

                    ##Faq
                    #UPDATE faq_entity SET description = REPLACE(REPLACE(description, '\r', ''), '\n', '');
                    #UPDATE faq_entity SET description = REPLACE(TRIM(description), '\t', '');
                    #UPDATE faq_entity SET description = REPLACE(description, '=\"', '=\\"');
                    #UPDATE faq_entity SET description = REPLACE(description, '\" ', '\\" ');
                    #UPDATE faq_entity SET description = REPLACE(description, '\">', '\\">');

                    //TODO kako provjeriti da li ima krivih
                    #SELECT * FROM product_entity WHERE JSON_VALID(`meta_title`) = 0;

                    $updateQuery = "";
                    $i = 0;

                    foreach ($data as $d) {

                        $updateQueryTmp = "UPDATE {$entityTypeCode}_entity SET ";

                        if ($i == 1) {
                            $databaseContext->executeNonQuery($updateQuery);
                            $updateQuery = "";
                            $i = 0;
                        }

                        foreach ($attributes["attributes"] as $attribute) {
                            $value = array();
                            foreach ($stores as $store) {
                                if ($store["store_id"] == $baseStore["store_id"]) {
                                    $value[$store["store_id"]] = addslashes($d[$attribute]);
                                } else {
                                    $value[$store["store_id"]] = null;
                                }

                                if ($attribute == "show_on_store") {
                                    $value[$store["store_id"]] = 1;
                                }
                            }

                            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                            $updateQueryTmp .= " {$attribute} = '{$value}',";
                        }

                        $updateQueryTmp = substr($updateQueryTmp, 0, -1);
                        $updateQueryTmp .= " WHERE id = {$d["id"]};";

                        $updateQuery .= $updateQueryTmp;

                        $i++;
                    }

                    if (!empty($updateQuery)) {
                        $databaseContext->executeNonQuery($updateQuery);
                        $updateQuery = "";
                    }

                    if ($entityTypeCode == "product_group") {
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'text_store', frontend_input = 'text_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('name','url','meta_title');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'textarea_store', frontend_input = 'textarea_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('meta_description','meta_keywords');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'ckeditor_store', frontend_input = 'ckeditor_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('description');";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "s_page") {
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'text_store', frontend_input = 'text_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('name','url','meta_title');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'textarea_store', frontend_input = 'textarea_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('meta_description','meta_keywords');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'ckeditor_store', frontend_input = 'ckeditor_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('content');";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "blog_category") {
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'text_store', frontend_input = 'text_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('name','url','meta_title');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'textarea_store', frontend_input = 'textarea_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('meta_description','meta_keywords');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'ckeditor_store', frontend_input = 'ckeditor_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('description');";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "blog_post") {
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'text_store', frontend_input = 'text_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('name','url','meta_title');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'textarea_store', frontend_input = 'textarea_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('meta_description','meta_keywords');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'ckeditor_store', frontend_input = 'ckeditor_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('content');";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "product") {
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'text_store', frontend_input = 'text_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('name','url','meta_title');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'textarea_store', frontend_input = 'textarea_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('meta_description','meta_keywords','short_description');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'ckeditor_store', frontend_input = 'ckeditor_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('description');";
                        $databaseContext->executeNonQuery($q);

                        /** COPY product ids to s_route_entity */
                        $q = "UPDATE s_route_entity AS s 
                    LEFT JOIN s_store_product_link_entity AS sp ON s.destination_id = sp.id 
                    SET destination_id = sp.product_id, destination_type = 'product'
                    WHERE s.destination_type = 's_store_product_link';";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "s_front_block") {
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'text_store', frontend_input = 'text_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('subtitle','url','main_title');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'ckeditor_store', frontend_input = 'ckeditor_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('editor');";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "faq") {
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'text_store', frontend_input = 'text_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('name');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'ckeditor_store', frontend_input = 'ckeditor_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('description');";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "testimonials") {
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'text_store', frontend_input = 'text_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('name');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'textarea_store', frontend_input = 'textarea_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('content');";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "payment_type") {
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'text_store', frontend_input = 'text_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('name');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'ckeditor_store', frontend_input = 'ckeditor_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('description');";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "delivery_type") {
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'text_store', frontend_input = 'text_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('name');";
                        $databaseContext->executeNonQuery($q);
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'ckeditor_store', frontend_input = 'ckeditor_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('description');";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "country") {
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'text_store', frontend_input = 'text_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('name');";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "office_type") {
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'text_store', frontend_input = 'text_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('name');";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "office") {
                        $q = "UPDATE attribute SET backend_type = 'json', frontend_type = 'textarea_store', frontend_input = 'textarea_store' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code IN ('worktime');";
                        $databaseContext->executeNonQuery($q);
                    }

                    $attribute = $attributeContext->getOneBy(array("backendTable" => "{$entityTypeCode}_entity", "attributeCode" => "store_id"));
                    if (!empty($attribute)) {
                        $administrationManager->deleteAttribute($attribute);
                        $databaseManager->deleteFieldIfExist("{$entityTypeCode}_entity", $attribute);
                    }

                    $attribute = $attributeContext->getOneBy(array("backendTable" => "{$entityTypeCode}_entity", "attributeCode" => "store_links"));
                    if (!empty($attribute)) {
                        $administrationManager->deleteAttribute($attribute);
                        $databaseManager->deleteFieldIfExist("{$entityTypeCode}_entity", $attribute);
                    }

                    $entityType = $entityManager->getEntityTypeByCode($entityTypeCode);

                    $administrationManager->generateDoctrineXML($entityType, true);
                    $administrationManager->generateEntityClasses($entityType, true);
                }

                /** Delete s_website_product_link_entity */
                $entityType = $entityManager->getEntityTypeByCode("s_website_product_link");
                if (!empty($entityType)) {
                    $administrationManager->deleteEntityType($entityType);
                }

                /** Delete s_store_product_link */
                $entityType = $entityManager->getEntityTypeByCode("s_store_product_link");
                if (!empty($entityType) && $entityTypeCode == "product") {
                    /** COPY product ids to s_route_entity */
                    $q = "UPDATE s_route_entity AS s 
                LEFT JOIN s_store_product_link_entity AS sp ON s.destination_id = sp.id 
                SET destination_id = sp.product_id, destination_type = 'product'
                WHERE s.destination_type = 's_store_product_link';";
                    $databaseContext->executeNonQuery($q);

                    $q = 'UPDATE product_entity as p
                    LEFT JOIN s_route_entity as sr ON p.id = sr.destination_id and destination_type = "product" SET url = CONCAT(\'{\"3\":\"\',sr.request_url,\'\", \"4\":\"\',sr.request_url,\'\"}\')
                    WHERE destination_type = "product";';
                    $databaseContext->executeNonQuery($q);

                    $administrationManager->deleteEntityType($entityType);
                }

                foreach ($arrayToUpdate as $entityTypeCode => $attributes) {
                    if ($entityTypeCode == "product_group") {
                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY url JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY name JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY meta_title JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY meta_description JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY meta_keywords JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY description JSON;";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "s_page") {
                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY url JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY name JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY meta_title JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY meta_description JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY meta_keywords JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY content JSON;";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "blog_category") {
                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY url JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY name JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY meta_title JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY meta_description JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY meta_keywords JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY description JSON;";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "blog_post") {
                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY url JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY name JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY meta_title JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY meta_description JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY meta_keywords JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY content JSON;";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "product") {
                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY url JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY meta_title JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY meta_description JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY meta_keywords JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY short_description JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY description JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY name JSON;";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "s_front_block") {
                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY url JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY subtitle JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY main_title JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY editor JSON;";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "faq") {
                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY name JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY description JSON;";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "testimonials") {
                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY name JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY content JSON;";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "delivery_type") {
                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY name JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY description JSON;";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "payment_type") {
                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY name JSON;";
                        $databaseContext->executeNonQuery($q);

                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY description JSON;";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "country") {
                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY name JSON;";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "office_type") {
                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY name JSON;";
                        $databaseContext->executeNonQuery($q);
                    } elseif ($entityTypeCode == "office") {
                        $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY worktime JSON;";
                        $databaseContext->executeNonQuery($q);
                    }

                    $entityType = $entityManager->getEntityTypeByCode($entityTypeCode);

                    $administrationManager->generateDoctrineXML($entityType, true);
                    $administrationManager->generateEntityClasses($entityType, true);
                }

                try {
                    $sql = "ALTER TABLE quote_item_entity DROP FOREIGN KEY `quote_item_entity_ibfk_3`;";
                    $databaseContext->executeNonQuery($sql);
                    $sql = "SET FOREIGN_KEY_CHECKS=0; ALTER TABLE quote_item_entity DROP COLUMN currency_id; SET FOREIGN_KEY_CHECKS=1;";
                    $databaseContext->executeNonQuery($sql);
                } catch (\Exception $e) {
                    echo $e->getMessage();
                }

            } else if ($func == "convert_to_translatable") {
                $entityTypeCode = $input->getArgument('entity_type_code');
                if (empty($entityTypeCode)) {
                    throw new \Exception('Entity type code not defined');
                }
                $attribute = $input->getArgument('attribute');
                if (empty($attribute)) {
                    throw new \Exception('Attribute not defined');
                }
                $store_field_type = $input->getArgument('store_field_type');
                if (empty($store_field_type)) {
                    throw new \Exception('Store field type not defined');
                }

                $entityType = $entityManager->getEntityTypeByCode($entityTypeCode);
                $attributeSet = $entityManager->getAttributeSetByCode($entityTypeCode);

                /**
                 * Handle JSONs
                 */
                $databaseContext->executeNonQuery("UPDATE {$entityTypeCode}_entity SET {$attribute} = REPLACE(REPLACE(REPLACE(REPLACE(TRIM({$attribute}), '\\t', ''), '\\t', ''), '\\r', ''), '\\n', '');");

                $q = "SELECT * FROM attribute WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code = '{$attribute}';";
                $attributeData = $databaseContext->getSingleEntity($q);

                if (empty($attributeData)) {
                    throw new \Exception('Attribute is empty');
                }

                if ($attributeData["backend_type"] != "json") {
                    $q = "SELECT * FROM {$entityTypeCode}_entity LIMIT 1";
                    $dataCheck = $databaseContext->getSingleEntity($q);

                    if (!array_key_exists("show_on_store", $dataCheck)) {
                        $attributeSet = $entityManager->getAttributeSetByCode($entityTypeCode);

                        $attributeGroup = $attributeGroupContext->getOneBy(array("attributeSet" => $attributeSet->getId(), "attributeGroupName" => "{$entityTypeCode} details"));
                        if (empty($attributeGroup)) {
                            $helper = $this->getHelper('question');
                            $question = new Question("Attribute group \"{$entityTypeCode} details\" was not found! Please enter the id of attribute group to add show_on_store to: ");
                            $id = $helper->ask($input, $output, $question);

                            $attributeGroup = $attributeGroupContext->getById($id);
                            if (empty($attributeGroup)) {
                                throw new \Exception('Failed to load attribute group');
                            }
                        }

                        $show_on_store_attribute = array();
                        $show_on_store_attribute["frontendLabel"] = "Show on store";
                        $show_on_store_attribute["frontendInput"] = "checkbox_store";
                        $show_on_store_attribute["frontendType"] = "checkbox_store";
                        $show_on_store_attribute["frontendModel"] = null;
                        $show_on_store_attribute["frontendHidden"] = 0;
                        $show_on_store_attribute["readOnly"] = 0;
                        $show_on_store_attribute["frontendDisplayOnNew"] = 1;
                        $show_on_store_attribute["frontendDisplayOnUpdate"] = 1;
                        $show_on_store_attribute["frontendDisplayOnView"] = 1;
                        $show_on_store_attribute["frontendDisplayOnPreview"] = 1;
                        $show_on_store_attribute["attributeCode"] = "show_on_store";
                        $show_on_store_attribute["backendType"] = "json";
                        $show_on_store_attribute["isCustom"] = 0;

                        $ret = $administrationManager->createAttribute($entityType, $attributeSet, $attributeGroup, $show_on_store_attribute);
                        if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                            throw new \Exception('Failed to create show on store attribute');
                        }

                        $databaseManager->createTableIfDoesntExist($entityType, null);
                        $administrationManager->generateDoctrineXML($entityType, true);
                        $administrationManager->generateEntityClasses($entityType, true);
                    }
                }

                $q = "SELECT id,{$attribute} FROM {$entityTypeCode}_entity";
                $q = "SELECT * FROM {$entityTypeCode}_entity";
                $data = $databaseContext->getAll($q);

                $updateQuery = [];
                foreach ($data as $d) {
                    if ($this->isJson($d[$attribute])) {
                        continue;
                    }

                    $value = [];
                    $valueShowOnStore = [];

                    $updateQueryTmp = "UPDATE {$entityTypeCode}_entity ";
                    foreach ($stores as $store) {
                        if ($store["store_id"] == $baseStore["store_id"]) {
                            $value[$store["store_id"]] = addslashes($d[$attribute]);
                        } else {
                            $value[$store["store_id"]] = null;
                        }

                        if (isset($d["show_on_store"])) {
                            $valueShowOnStore[$store["store_id"]] = null;
                            $valueShowOnStore[$baseStore["store_id"]] = 1;
                        }
                    }

                    if (isset($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                        $value = str_replace("\\\\'", "\\'", $value);
                        $updateQueryTmp .= "SET {$attribute} = '{$value}' ";
                    }

                    if (isset($valueShowOnStore) && !empty($valueShowOnStore)) {
                        $valueShowOnStore = json_encode($valueShowOnStore, JSON_UNESCAPED_UNICODE);
                        $updateQueryTmp .= ", show_on_store = '{$valueShowOnStore}' ";
                    }
                    $updateQueryTmp .= "WHERE id = {$d["id"]};";
                    $updateQuery[] = $updateQueryTmp;
                }

                if (!empty($updateQuery)) {
                    try {
                        $databaseContext->executeNonQuery(implode("", $updateQuery));
                    } catch (\Exception $e) {
                        dump($e->getMessage());
                        die;
                    }
                }

                $q = "UPDATE attribute SET backend_type = 'json', frontend_type = '{$store_field_type}', frontend_input = '{$store_field_type}' WHERE backend_table = '{$entityTypeCode}_entity' and attribute_code LIKE '{$attribute}';";
                $databaseContext->executeNonQuery($q);

                $q = "ALTER TABLE {$entityTypeCode}_entity MODIFY {$attribute} JSON;";
                $databaseContext->executeNonQuery($q);

                $databaseManager->createTableIfDoesntExist($entityType, null);
                $administrationManager->generateDoctrineXML($entityType, true);
                $administrationManager->generateEntityClasses($entityType, true);
            } elseif ($func = "update_urls") {
                foreach (["product",
                             "product_group",
                             "blog_category",
                             "blog_post",
                             "s_page",
                         ] as $entityTypeCode) {

                    $entityType = $entityManager->getEntityTypeByCode($entityTypeCode);
                    $attributeSet = $entityManager->getAttributeSetByCode($entityTypeCode);

                    $q = "SELECT * FROM {$entityTypeCode}_entity LIMIT 1";
                    $dataCheck = $databaseContext->getSingleEntity($q);
                    if (!array_key_exists("url", $dataCheck)) {

                        $attributeGroup = $attributeGroupContext->getOneBy(array("attributeSet" => $attributeSet->getId(), "attributeGroupName" => "{$entityTypeCode} details"));
                        if (empty($attributeGroup)) {
                            $helper = $this->getHelper('question');
                            $question = new Question("Attribute group \"{$entityTypeCode} details\" was not found! Please enter the id of attribute group to add show_on_store to: ");
                            $id = $helper->ask($input, $output, $question);

                            $attributeGroup = $attributeGroupContext->getById($id);
                            if (empty($attributeGroup)) {
                                throw new \Exception('Failed to load attribute group');
                            }
                        }

                        $attr_data = array();
                        $attr_data["frontendLabel"] = "Url";
                        $attr_data["frontendInput"] = "text_store";
                        $attr_data["frontendType"] = "text_store";
                        $attr_data["frontendModel"] = null;
                        $attr_data["frontendHidden"] = 0;
                        $attr_data["readOnly"] = 1;
                        $attr_data["frontendDisplayOnNew"] = 1;
                        $attr_data["frontendDisplayOnUpdate"] = 1;
                        $attr_data["frontendDisplayOnView"] = 1;
                        $attr_data["frontendDisplayOnPreview"] = 1;
                        $attr_data["attributeCode"] = "url";
                        $attr_data["backendType"] = "json";
                        $attr_data["isCustom"] = 0;

                        $ret = $administrationManager->createAttribute($entityType, $attributeSet, $attributeGroup, $attr_data);
                        if (!isset($ret["attribute"]) || empty($ret["attribute"])) {
                            dump("error creating attribute");
                        }

                        $databaseManager->createTableIfDoesntExist($entityType, null);
                        $administrationManager->generateDoctrineXML($entityType, true);
                        $administrationManager->generateEntityClasses($entityType, true);
                    }

                    $q = 'UPDATE ' . $entityTypeCode . '_entity AS ent LEFT JOIN s_route_entity AS route ON route.destination_id = ent.id AND route.destination_type = "' . $entityTypeCode . '" SET ent.url=CONCAT("{\"' . $baseStore["store_id"] . '\":\"",route.request_url,"\"}");';
                    $databaseContext->executeNonQuery($q);
                }
            }
            else{
                throw new \Exception("Command type missing: ".json_encode($input->getArguments()));
            }
        } catch (\Exception $e) {
            dump($e->getMessage());
        }

        return false;
    }

    private function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}
