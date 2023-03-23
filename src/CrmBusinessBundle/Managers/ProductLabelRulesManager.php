<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use CrmBusinessBundle\Entity\ProductLabelEntity;
use Doctrine\Common\Util\Inflector;

class ProductLabelRulesManager extends ProductAttributeFilterRulesManager
{
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getProductLabelById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(ProductLabelEntity::class);
        return $repository->find($id);
    }

    /**
     * @return bool
     */
    public function applyProductLabels()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE product_label_entity SET is_applied = 1, recalculate = 1 WHERE date_from <= NOW() AND date_to >= NOW() AND is_active = 1 AND entity_state_id = 1 AND is_applied = 0 AND recalculate = 0;";
        $this->databaseContext->executeNonQuery($q);

        $q = "UPDATE product_label_entity SET is_applied = 0, recalculate = 1 WHERE (date_from > NOW() OR date_to < NOW() OR is_active = 0 OR entity_state_id = 2) AND is_applied = 1 AND recalculate = 0;";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @return array
     */
    public function getProductLabelProductLinks($ids = array())
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $where = "";
        if (!empty($ids)) {
            $where = " WHERE product_id IN (" . implode(",", $ids) . ") ";
        }

        $q = "SELECT * FROM product_label_product_link_entity {$where};";
        $data = $this->databaseContext->getAll($q);

        $ret = array();

        if (!empty($data)) {
            foreach ($data as $d) {
                $ret[$d["product_id"] . "_" . $d["product_label_id"]] = $d;
            }
        }

        return $ret;
    }

    /**
     * @param string $type
     * @return mixed
     */
    public function getAllAppliedProductLabels($type = "product")
    {

        $et = $this->entityManager->getEntityTypeByCode("product_label");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isApplied", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("priority", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param array $data
     * @return bool
     */
    public function recalculateProductLabelRules($data = array())
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (empty($this->attributeContext)) {
            $this->attributeContext = $this->container->get("attribute_context");
        }

        /**
         * Reset data if product ids are empty
         */
        $productIds = Array();
        if (!isset($data["product_ids"]) || empty($data["product_ids"])) {
            $data = array();
        }
        else{
            $productIds = $data["product_ids"];
        }

        // Check wether to invalidate cache at the end
        $idsToInvalidate = array();

        /**
         * Delete product labels if all rules are inactive
         */
        $productLabels = $this->getAllAppliedProductLabels(null);
        if (empty($productLabels)) {
            $q = "SELECT DISTINCT(product_id) FROM product_label_product_link_entity";
            $tmp = $this->databaseContext->getAll($q);

            if (!empty($tmp)) {
                $idsToInvalidate = array_column($tmp, "product_id");
            }

            $q = "DELETE FROM product_label_product_link_entity;";
            $this->databaseContext->executeNonQuery($q);

        }

        /**
         * Recalculate direct product labels
         */
        $productLabels = $this->getAllAppliedProductLabels();

        if (!empty($productLabels)) {

            $productLabelRulesData = array();

            $insertProductLabelProductLinks = "INSERT IGNORE INTO product_label_product_link_entity (entity_type_id, attribute_set_id, created, modified, created_by, modified_by, entity_state_id, product_id, product_label_id) VALUES ";
            /** @var AttributeSet $productLabelProductLinksAttributeSet */
            $productLabelProductLinksAttributeSet = $this->entityManager->getAttributeSetByCode("product_label_product_link");

            /** @var ProductLabelEntity $productLabel */
            foreach ($productLabels as $key => $productLabel) {

                $q = "SELECT DISTINCT(product_id) FROM product_label_product_link_entity WHERE product_label_id = {$productLabel->getId()};";
                $tmp = $this->databaseContext->getAll($q);

                $productLabelRulesData[$key]["current_product_ids"] = array();
                if (!empty($tmp)) {
                    $productLabelRulesData[$key]["current_product_ids"] = array_column($tmp, "product_id");
                    $idsToInvalidate = array_merge($idsToInvalidate, array_column($tmp, "product_id"));
                }

                $rules = $productLabel->getRules();
                $where = "";
                $join = "";

                $productLabelRulesData[$key]["product_label"] = $productLabel;
                $productLabelRulesData[$key]["product_ids"] = array();
                $productLabelRulesData[$key]["products"] = array();


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

                $products = $this->getProductsByRule($join, $where);


                /**
                 * Ovo se brise samo kada se radi full rekalkulacija, a ne kada se rekalkulira samo jedan proizvod
                 */
                if (empty($products) && empty($productIds)) {

                    /**
                     * Remove existing ids
                     */
                    if (!empty($productLabelRulesData[$key]["current_product_ids"])) {
                        $q = "DELETE FROM product_label_product_link_entity WHERE product_label_id = {$productLabelRulesData[$key]["product_label"]->getId()} AND product_id IN (" . implode(",", $productLabelRulesData[$key]["current_product_ids"]) . ");";
                        $this->databaseContext->executeNonQuery($q);
                    }

                    unset($productLabelRulesData[$key]);
                    continue;
                }

                $productLabelRulesData[$key]["product_ids"] = array_column($products, "id");
                $productLabelRulesData[$key]["products"] = $products;
            }

            $totalLab = 0;
            foreach ($productLabelRulesData as $p){
                $totalLab = $totalLab + count($p["product_ids"]);
            }

            $productLabelProductLinks = $this->getProductLabelProductLinks($productIds);
            if (!empty($productLabelRulesData)) {

                foreach ($productLabelRulesData as $key => $productLabelRuleData) {

                    if (empty($productLabelRuleData["product_ids"])) {
                        unset($productLabelRulesData[$key]);
                        continue;
                    }


                    $insertProductLabelProductLinksData = "";
                    $count = 0;

                    foreach ($productLabelRuleData["products"] as $product) {

                        $productLabelLinkKey = $product["id"] . "_" . $productLabelRuleData["product_label"]->getId();

                        /**
                         * Insert
                         */
                        if (!isset($productLabelProductLinks[$productLabelLinkKey])) {

                            $insertProductLabelProductLinksData .= "('{$productLabelProductLinksAttributeSet->getEntityTypeId()}', '{$productLabelProductLinksAttributeSet->getId()}', NOW(), NOW(), 'system', 'system', '1', {$product["id"]},{$productLabelRuleData["product_label"]->getId()}),";
                            $count++;
                        } else {
                            unset($productLabelProductLinks[$productLabelLinkKey]);
                        }

                        if ($count > 300) {
                            $count = 0;
                            if (!empty($insertProductLabelProductLinksData)) {
                                $insertProductLabelProductLinksData = substr($insertProductLabelProductLinksData, 0, -1);
                                $this->databaseContext->executeNonQuery($insertProductLabelProductLinks . $insertProductLabelProductLinksData);
                                $insertProductLabelProductLinksData = "";
                            }
                        }
                    }

                    if (!empty($insertProductLabelProductLinksData)) {
                        $insertProductLabelProductLinksData = substr($insertProductLabelProductLinksData, 0, -1);
                        $this->databaseContext->executeNonQuery($insertProductLabelProductLinks . $insertProductLabelProductLinksData);
                    }

                    $idsToInvalidate = array_merge($idsToInvalidate, $productLabelRuleData["product_ids"]);
                }
            }

            if (!empty($productLabelProductLinks)) {
                $productLabelLinkIds = array_column($productLabelProductLinks, "id");
                $q = "DELETE FROM product_label_product_link_entity WHERE id IN (".implode(",",$productLabelLinkIds).");";
                $this->databaseContext->executeNonQuery($q);
            }
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        if(!empty($productIds)){
            $idsToInvalidate = $productIds;
        }
        if ((!isset($_ENV["DISABLE_FRONT_BLOCK_HTML_CACHE"]) || $_ENV["DISABLE_FRONT_BLOCK_HTML_CACHE"] != 1) && !empty($idsToInvalidate)) {
            $idsToInvalidate = array_unique($idsToInvalidate);
            $this->crmProcessManager->invalidateProductCacheForRecentlyModifiedProducts($idsToInvalidate);
        }


        /**
         * Only if all product label rules are regenerated mark product label rules as recalculated
         */
        if (!isset($data["product_ids"])) {
            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $this->crmProcessManager->afterProductLabelsApplied();

            $q = "UPDATE product_label_entity SET recalculate = 0;";
            $this->databaseContext->executeNonQuery($q);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function checkIfProductLabelRulesNeedRecalculating()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM product_label_entity WHERE recalculate = 1;";
        $exists = $this->databaseContext->getAll($q);

        if (!empty($exists) || count($exists) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @param $product
     * @param $storeId
     * @param $lablePositionCodes
     * @param $isProductPage
     * @return array
     */
    public function getLabelsForProduct($product, $storeId, $lablePositionCodes = array(), $isProductPage = false)
    {
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $additionalWhere = "";
        if (!empty($lablePositionCodes)) {
            $additionalWhere = " AND pls.code IN ('" . implode("','", $lablePositionCodes) . "') ";
        }

        $limitColumn = "label_limit";
        if ($isProductPage) {
            $limitColumn = "product_label_limit";
        }

        $q = "SELECT pl.`id`, pl.`name`, pls.`code`, pl.priority, pl.label_image, pl.product_label_image, pls.{$limitColumn} AS label_limit, pl.url FROM product_label_product_link_entity as plpl LEFT JOIN product_label_entity AS pl ON plpl.product_label_id = pl.id and pl.is_applied = 1
        LEFT JOIN product_label_position_entity as pls ON pl.label_position_id = pls.id
        WHERE plpl.product_id = {$product->getId()} {$additionalWhere}
        GROUP BY pl.id
        ORDER BY pls.id ASC, pl.priority ASC";
        $data = $this->databaseContext->getAll($q);

        $ret = array();

        if (!empty($data)) {
            foreach ($data as $d) {

                if (!isset($ret[$d["code"]])) {
                    $ret[$d["code"]] = array();
                }

                if (intval($d["label_limit"]) > 0 && count($ret[$d["code"]]) >= intval($d["label_limit"])) {
                    continue;
                }

                if (empty($d["label_image"]) && empty($d["product_label_image"])) {
                    continue;
                }

                $d["label_image"] = json_decode($d["label_image"], true);

                if (!empty($d["product_label_image"]) && $isProductPage) {
                    $d["product_label_image"] = json_decode($d["product_label_image"], true);
                    if (!empty($d["product_label_image"])) {
                        $d["label_image"] = $d["product_label_image"];
                        unset($d["product_label_image"]);
                    }
                }

                if (!isset($d["label_image"][$storeId])) {
                    continue;
                } else {
                    $d["label_image"] = $d["label_image"][$storeId];
                }

                $ret[$d["code"]][] = $d;
            }
        }

        return $ret;
    }
}