<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\EntityAttribute;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SearchFilterHelper;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\ProductEntity;
use Doctrine\Common\Util\Inflector;
use ScommerceBusinessBundle\Entity\SProductAttributeConfigurationEntity;
use ScommerceBusinessBundle\Managers\SproductManager;

class ProductAttributeFilterRulesManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var SproductManager $sProductManager */
    protected $sProductManager;
    protected $avoidAttributes;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->getContainer()->get("entity_manager");
    }

    /**
     * @param $avoidAttributes
     */
    public function setAvoidAttributes($avoidAttributes)
    {
        $this->avoidAttributes = $avoidAttributes;
    }

    /**
     * @return mixed
     */
    public function getAvoidAttributes()
    {
        return $this->avoidAttributes;
    }

    /**
     * @return bool
     */
    public function applyRuleEntities($entityTypeCode)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE {$entityTypeCode}_entity SET is_applied = 1 WHERE date_valid_from <= NOW() AND date_valid_to >= NOW() AND is_active = 1 AND entity_state_id = 1 AND (is_applied = 0 or is_applied is null);";
        $this->databaseContext->executeNonQuery($q);

        $q = "UPDATE {$entityTypeCode}_entity SET is_applied = 0 WHERE (date_valid_from > NOW() OR date_valid_to < NOW() OR is_active = 0 OR entity_state_id = 2) AND (is_applied = 1 or is_applied is null);";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @return array
     */
    public function getFilteredAttributes()
    {
        $allowedTypes = array(
            "lookup",
            "text",
            "decimal",
            "integer",
            "checkbox",
            "checkbox_store",
            //"datetimesingle",
            "text_store"
        );

        $allowedTypes = array_flip($allowedTypes);

        $disallowedAttributeKeys = array(
            "canonical_id",
            "currency_id",
            "synced",
            "old_url",
            "synced",
            "meta_title",
            "sort_price_retail",
            "sort_price_base",
            "ord",
            "image",
            "discount_type_base",
            "ctr",
            "discount_diff_base",
            "discount_diff",
            "url",
            "template_type_id",
            "specs_title",
            "description_title",
            "number_of_installments",
            "cash_price_retail",
            "cash_price_base",
            "cash_percentage",
            "video_title",
            "keep_url",
            "auto_generate_url",
            "exclude_from_statistics",
            "manufacturer_remote_id",
            "discount_type",
            "content_changed",
            "margin_rule_id",
            "exclude_from_discounts",
            "bulk_price_rule_id",
            "loyalty_earning_rule_id",
            "price_per_unit",
            "price_return",
            "warning_mail_qty",
            "wand_name_2",
            "name_by_supplier",
            "description_by_supplier",
            "seo_score",
            "description_score",
        );

        $disallowedAttributeKeys = array_flip($disallowedAttributeKeys);
        $filteredAttributes = array();

        $attributes = $this->entityManager->getAttributesOfEntityType("product", false);
        if (!empty($attributes)) {
            /** @var Attribute $attribute */
            foreach ($attributes as $attribute) {
                if (isset($allowedTypes[$attribute->getFrontendInput()]) && !isset($disallowedAttributeKeys[$attribute->getAttributeCode()])) {

                    if (!empty($this->avoidAttributes) && in_array($attribute->getAttributeCode(), $this->avoidAttributes)) {
                        continue;
                    }

                    $filteredAttributes[] = array("id" => $attribute->getId(), "name" => $attribute->getFrontendLabel());
                }
            }
        }

        /**
         * Add product attributes
         */
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT id, name FROM s_product_attribute_configuration_entity WHERE entity_state_id = 1 and is_active = 1;";
        $data = $this->databaseContext->getAll($q);

        if (!empty($data)) {
            foreach ($data as $d) {
                $filteredAttributes[] = array("id" => "s_" . $d["id"], "name" => "Attr - " . $d["name"]);
            }
        }

        if (!empty($filteredAttributes)) {
            usort($filteredAttributes, array($this, 'cmp'));
        }

        return $filteredAttributes;
    }

    public function cmp($a, $b)
    {
        return strcmp($this->translator->trans($a["name"]), $this->translator->trans($b["name"]));
    }

    /**
     * @param $attributeId
     * @param $formType
     * @param $value
     * @param null $searchType
     * @return mixed
     */
    public function getRenderedAttributeField($attributeId, $formType, $value, $searchType = null)
    {
        if (StringHelper::startsWith($attributeId, "s_")) {

            if (empty($this->sProductManager)) {
                $this->sProductManager = $this->container->get("s_product_manager");
            }

            $attributeId = explode("_", $attributeId)[1];

            /** @var SProductAttributeConfigurationEntity $attribute */
            $attribute = $this->sProductManager->getSproductAttributeConfigurationById($attributeId);

            $data = array();
            $data["id"] = "s_" . $attribute->getId();
            $data["code"] = $attribute->getFilterKey();
            $data["attribute"] = $attribute;
            $data["type"] = "s_attribute";
        } else {
            if (empty($this->attributeContext)) {
                $this->attributeContext = $this->container->get("attribute_context");
            }

            /** @var Attribute $attribute */
            $attribute = $this->attributeContext->getById($attributeId);

            $data = array();
            $data["id"] = $attribute->getId();
            $data["code"] = $attribute->getAttributeCode();
            $data["attribute"] = $attribute;
            $data["type"] = "attribute";
        }

        return $this->twig->render(
            "CrmBusinessBundle:Includes:product_attribute_filter_rule.html.twig",
            array(
                "attribute_data" => $data,
                "value" => $value,
                "search_type" => $searchType,
                "formType" => $formType,
                "entity_id" => null
            )
        );
    }

    public function parseRuleToFilter($rules, $join, $where)
    {

        $ret = array();
        if (empty($rules)) {
            return $ret;
        }

        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->container->get("attribute_context");
        }
        if (empty($this->sProductManager)) {
            $this->sProductManager = $this->container->get("s_product_manager");
        }

        foreach ($rules as $rule) {
            if (!empty($rule["search"]["value"]) || $rule["search"]["value"] == "0") {

                if (StringHelper::startsWith($rule["attributeId"], "s_")) {

                    /** @var SProductAttributeConfigurationEntity $sProductAttributeConfiguration */
                    $sProductAttributeConfiguration = $this->sProductManager->getSproductAttributeConfigurationById(substr($rule["attributeId"], 2));

                    $field = "configuration_option";
                    if ($sProductAttributeConfiguration->getSProductAttributeConfigurationTypeId() == 3) {
                        $field = "attribute_value";
                    }

                    $preparedFilter = SearchFilterHelper::prepareFilter($rule["search_type"], "{$rule["attributeId"]}.", $field, $rule["search"]["value"]);

                    $join .= " LEFT JOIN s_product_attributes_link_entity AS {$rule["attributeId"]} ON p.id = {$rule["attributeId"]}.product_id ";
                    $where .= " AND {$preparedFilter} ";
                } else {
                    /** @var Attribute $attribute */
                    $attribute = $this->attributeContext->getById($rule["attributeId"]);

                    if ($attribute->getFrontendType() == "multiselect") {
                        $relatedTable = $attribute->getLookupAttribute()->getBackendTable();

                        $preparedFilter = SearchFilterHelper::prepareFilter($rule["search_type"], "p_{$relatedTable}.", $attribute->getLookupAttribute()->getAttributeCode(), $rule["search"]["value"]);

                        if (stripos($join, $relatedTable) === false) {
                            $join .= " JOIN {$relatedTable} AS p_{$relatedTable} ON p_{$relatedTable}.product_id = p.id AND {$preparedFilter} ";
                        } else {
                            $where .= " AND {$preparedFilter} ";
                        }
                    } else {
                        $preparedFilter = SearchFilterHelper::prepareFilter($rule["search_type"], "p.", $attribute->getAttributeCode(), $rule["search"]["value"]);

                        $where .= " AND {$preparedFilter} ";
                    }
                }
            }
        }

        $ret["join"] = $join;
        $ret["where"] = $where;

        return $ret;
    }

    /**
     * @param $join
     * @param $where
     * @return mixed[]
     */
    public function getProductsByRule($join, $where)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT DISTINCT(p.id) FROM product_entity as p {$join} WHERE p.entity_state_id = 1 {$where};";
        return $this->databaseContext->getAll($q);
    }

    /**
     * @param $rules
     * @return array
     */
    public function getRenderedExistingAttributeFields($rules)
    {
        $fields = array();
        $tmpRules = array();

        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->container->get("attribute_context");
        }
        if (empty($this->sProductManager)) {
            $this->sProductManager = $this->container->get("s_product_manager");
        }

        $rules = json_decode($rules, true);
        if (!empty($rules)) {
            foreach ($rules as $key => $rule) {

                if (StringHelper::startsWith($rule["attributeId"], "s_")) {

                    /** @var SProductAttributeConfigurationEntity $sProductAttributeConfiguration */
                    //$sProductAttributeConfiguration = $this->sProductManager->getSproductAttributeConfigurationById($rule["attributeId"]);
                } else {
                    /** @var Attribute $attribute */
                    $attribute = $this->attributeContext->getById($rule["attributeId"]);

                    if (!empty($this->avoidAttributes) && in_array($attribute->getAttributeCode(), $this->avoidAttributes)) {
                        continue;
                    }

                    if (in_array($attribute->getFrontendInput(), array("integer", "decimal"))) {
                        $rules[$key]["search"]["value"] = array($rule["search_type"] => $rule["search"]["value"]);
                    }
                }

                if (!isset($tmpRules[$rule["attributeId"]])) {
                    $tmpRules[$rule["attributeId"]] = $rules[$key];
                } else {
                    $tmpVal = $tmpRules[$rule["attributeId"]]["search"]["value"];
                    $tmpVal = array_merge($tmpVal, $rules[$key]["search"]["value"]);

                    $tmpRules[$rule["attributeId"]]["search"]["value"] = $tmpVal;
                }
            }

            foreach ($tmpRules as $rule) {
                $fields[] = $this->getRenderedAttributeField($rule["attributeId"], "form", $rule["search"]["value"], $rule["search_type"]);
            }
        }

        return $fields;
    }

    /**
     * @return false|string|null
     */
    public function validateRule($entity)
    {

        $validatedRules = null;

        $rules = json_decode($entity->getRules(), true);
        if (!empty($rules)) {
            foreach ($rules as $key => $rule) {

                if (StringHelper::startsWith($rule["attributeId"], "s_")) {

                    if (empty($this->sProductManager)) {
                        $this->sProductManager = $this->container->get("s_product_manager");
                    }

                    $frontendInput = "autocomplete";
                    /** @var SProductAttributeConfigurationEntity $sProductAttributeConfiguration */
                    $sProductAttributeConfiguration = $this->sProductManager->getSproductAttributeConfigurationById(substr($rule["attributeId"], 2));
                    if ($sProductAttributeConfiguration->getSProductAttributeConfigurationTypeId() == 3) {
                        $frontendInput = "text";
                    }
                } else {

                    if (empty($this->attributeContext)) {
                        $this->attributeContext = $this->container->get("attribute_context");
                    }

                    /** @var Attribute $attribute */
                    $attribute = $this->attributeContext->getById($rule["attributeId"]);
                    $frontendInput = $attribute->getFrontendInput();
                }

                if (in_array($frontendInput, array("text"))) {
                    if (stripos($rules[$key]["search"]["value"], ",") !== false) {
                        $parts = explode(",", $rules[$key]["search"]["value"]);
                        $parts = array_map('trim', $parts);
                        $parts = array_filter($parts);
                        $rules[$key]["search"]["value"] = implode(",", $parts);
                    }
                }

                if (in_array($frontendInput, array("text")) && in_array($rule["search_type"], array("in", "ni"))) {

                    $rules[$key]["search"]["value"] = str_ireplace("'", "", $rule["search"]["value"]);

                    if (stripos($rules[$key]["search"]["value"], ",") !== false) {
                        $parts = explode(",", $rules[$key]["search"]["value"]);
                        $parts = array_map('trim', $parts);
                        $parts = array_filter($parts);

                        $rules[$key]["search"]["value"] = implode("','", $parts);
                    }

                    $rules[$key]["search"]["value"] = "'" . $rules[$key]["search"]["value"] . "'";
                } elseif (in_array($frontendInput, array("text")) && !in_array($rule["search_type"], array("in", "ni"))) {
                    $rules[$key]["search"]["value"] = str_ireplace("'", "", $rule["search"]["value"]);
                }
            }
            $validatedRules = json_encode($rules);
        }

        $entity->setRules($validatedRules);

        return $entity;
    }

    /**
     * @param $rules
     * @return mixed[]
     */
    public function getProductIdsForRule($rules)
    {

        $ret = array();

        $where = "";
        $join = "";

        $rules = json_decode($rules, true);

        if (!empty($rules)) {
            $additionaFilter = $this->parseRuleToFilter($rules, $join, $where);
            if (isset($additionaFilter["join"])) {
                $join = $additionaFilter["join"];
            }
            if (isset($additionaFilter["where"])) {
                $where = $additionaFilter["where"];
            }
        }

        $data = $this->getProductsByRule($join, $where);

        if (!empty($data)) {
            $ret = array_column($data, "id");
        }

        return $ret;
    }

    /**
     * @param $entityTypeCode
     * @return mixed
     */
    public function getRulesByEntityTypeCode($entityTypeCode, $additionalFilter = null, $sortFilters = null)
    {
        $et = $this->entityManager->getEntityTypeByCode($entityTypeCode);

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalFilter)) {
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        $attributes = $et->getEntityAttributes();
        if (!empty($attributes)) {
            $hasOrd = false;
            /** @var EntityAttribute $attribute */
            foreach ($attributes as $attribute) {
                if ($attribute->getAttribute()->getAttributeCode() == "ord") {
                    $hasOrd = true;
                    break;
                }
            }
            if ($hasOrd) {
                if (empty($sortFilters)) {
                    $sortFilters = new SortFilterCollection();
                    $sortFilters->addSortFilter(new SortFilter("ord", "desc"));
                }
            }
        }

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param ProductEntity $product
     * @param $rules
     * @param $rulesEntity
     * @return bool
     */
    public function productMatchesRules(ProductEntity $product, $rules, $rulesEntity)
    {
        if (empty($rulesEntity)) {
            return false;
        }

        $key = md5(json_encode($rules) . "_" . $product->getId());

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $cachedResults = $this->cacheManager->getCacheGetItem($key);
        if (empty($cachedResults) || isset($_GET["rebuild_cache"])) {
            $ids = $this->getProductIdsForRule($rules);
            if (empty($ids)) {
                $ret = 0;
            } else {
                $ids = array_flip($ids);
                $ret = isset($ids[$product->getId()]) ? 1 : 0;
            }

            $this->cacheManager->setCacheItem($key, $ret, ["rule_cache_item", "product", $rulesEntity->getEntityType()->getEntityTypeCode()]);

        } else {
            $ret = $cachedResults->get();
        }

        return $ret == 1;
    }

    /**
     * @param $rule
     * @param array $productIds
     * @return mixed[]
     */
    public function checkIfProductInRule($rule, $productIds = array())
    {

        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->container->get("attribute_context");
        }

        $rules = $rule->getRules();
        $where = "";
        $join = "";

        /**
         * Used only if rules are regenerated for subset of products
         */
        if (!empty($productIds)) {
            $where .= " AND p.id IN (" . implode(",", $productIds) . ") ";
        }

        $rules = json_decode($rules, true);

        if (!empty($rules)) {

            $additionaFilter = $this->parseRuleToFilter($rules, $join, $where);
            if (isset($additionaFilter["join"])) {
                $join = $additionaFilter["join"];
            }
            if (isset($additionaFilter["where"])) {
                $where = $additionaFilter["where"];
            }
        }

        return $this->getProductsByRule($join, $where);
    }
}
