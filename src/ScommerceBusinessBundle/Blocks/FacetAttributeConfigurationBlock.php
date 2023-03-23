<?php

namespace ScommerceBusinessBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\DatabaseContext;
use AppBundle\Managers\ApplicationSettingsManager;

class FacetAttributeConfigurationBlock extends AbstractBaseBlock
{
    const BRAND_ID = 1;

    public function GetPageBlockTemplate()
    {
        return ('ScommerceBusinessBundle:Block:' . $this->pageBlock->getType() . '.html.twig');
    }

    public function GetPageBlockData()
    {
        /** @var DatabaseContext $databaseContext */
        $databaseContext = $this->container->get("database_context");
        $preparedValues = [];
        $preparedSAttributes = [];

        /** @var ApplicationSettingsManager $applicationSettingsManager */
        $applicationSettingsManager = $this->container->get("application_settings_manager");

        $setting = $applicationSettingsManager->getApplicationSettingByCode("facet_attribute_configuration");
        if (is_array($setting)) {
            $savedConfig = $setting;
        } else {
            $savedConfig = json_decode($setting, true);
        }

        // Attr configs
        $query = "SELECT id,name,filter_key FROM s_product_attribute_configuration_entity WHERE is_active = 1 AND show_in_filter = 1 AND filter_key IS NOT NULL;";
        $attrConfigDbValues = $databaseContext->executeQuery($query);

        foreach ($attrConfigDbValues as $attrConfigDbValue) {
            if (!isset($preparedValues[$attrConfigDbValue["id"]])) {
                $preparedSAttributes[$attrConfigDbValue["id"]] = $attrConfigDbValue["name"];
                $preparedValues[$attrConfigDbValue["id"]] = [
                    "attr_conf_id" => $attrConfigDbValue["id"],
                    "attr_conf_name" => $attrConfigDbValue["name"],
                    "attr_conf_key" => str_ireplace("-", "_", $attrConfigDbValue["filter_key"]),
                    "attr_conf_value" => $savedConfig["sacid-" . $attrConfigDbValue["id"]] ?? $attrConfigDbValue["name"],
                    "attr_values" => [],
                ];
            }
        }

        $query = "SELECT DISTINCT pac.id as attr_conf_id, pac.filter_key as attr_filter_key, pal.attribute_value as attr_val_value, pal.sufix as attr_val_suffix,MD5(CONCAT(pal.s_product_attribute_configuration_id, pal.attribute_value)) as attribute_value_key
FROM s_product_attributes_link_entity AS pal
JOIN s_product_attribute_configuration_entity AS pac ON pal.s_product_attribute_configuration_id=pac.id
WHERE pal.attribute_value IS NOT NULL
ORDER BY pal.attribute_value ASC;";
        $dbValues = $databaseContext->executeQuery($query);

        foreach ($dbValues as $dbValue) {
            if (!isset($preparedValues[$dbValue["attr_conf_id"]]["attr_values"][$dbValue["attribute_value_key"]])) {
                $preparedValues[$dbValue["attr_conf_id"]]["attr_values"][$dbValue["attribute_value_key"]] = [
                    "name" => $dbValue["attr_val_value"] . $dbValue["attr_val_suffix"],
                    "value" => $savedConfig["savk-" . str_ireplace("-", "_", $dbValue["attr_filter_key"]) . "-" . $dbValue["attribute_value_key"]] ?? $dbValue["attr_val_value"] . $dbValue["attr_val_suffix"]
                ];
            }
        }

        asort($preparedSAttributes);
        $this->pageBlockData["model"]["prepared_s_attribute_configuration"] = $preparedValues;
        $this->pageBlockData["model"]["prepared_s_attributes"] = $preparedSAttributes;

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'ScommerceBusinessBundle:BlockSettings:' . $this->pageBlock->getType() . '.html.twig';
    }


    public function GetPageBlockSetingsData()
    {
        $attributeSetContext = $this->container->get('attribute_set_context');
        $attributeSets = $attributeSetContext->getAll();

        return array(
            'entity' => $this->pageBlock,
            'attribute_sets' => $attributeSets,
        );
    }

    public function SavePageBlockSettings($data)
    {

        $blockManager = $this->container->get('block_manager');
        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }

    public function isVisible()
    {
        return true;
    }

}
