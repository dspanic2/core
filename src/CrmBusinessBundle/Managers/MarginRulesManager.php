<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\MarginRuleEntity;
use Doctrine\Common\Util\Inflector;

class MarginRulesManager extends ProductAttributeFilterRulesManager
{
    public function initialize()
    {
        parent::initialize();
        $this->setAvoidAttributes(Array("brand_id","supplier_id","product_groups","supplier.id","brand.id","productGroups.id"));
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getMarginRuleById($id){
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(MarginRuleEntity::class);
        return $repository->find($id);
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function getAllAppliedMarginRules($data = Array()){

        $et = $this->entityManager->getEntityTypeByCode("margin_rule");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isApplied", "eq", 1));
        if(isset($data["supplier_ids"]) && !empty($data["supplier_ids"])){
            $compositeFilter->addFilter(new SearchFilter("suppliers.id", "in", implode(",",$data["supplier_ids"])));
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("priority", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @return bool
     */
    public function checkIfMarginRulesNeedRecalculating(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM margin_rule_entity WHERE recalculate = 1;";
        $exists = $this->databaseContext->getAll($q);

        if(!empty($exists) || count($exists) > 0){
            return true;
        }

        return false;
    }

    /**
     * @param array $data
     * @param bool $force
     * @return bool
     */
    public function recalculateMarginRules($data = Array()){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        if(empty($this->attributeContext)){
            $this->attributeContext = $this->container->get("attribute_context");
        }

        /**
         * Reset data if product ids are empty and supplier is empty
         */
        if(!isset($data["product_ids"]) || empty($data["product_ids"]) || !isset($data["supplier_ids"]) || empty($data["supplier_ids"])){
            $data = Array();
        }

        $marginRules = $this->getAllAppliedMarginRules($data);

        $usedProductIds = Array(0);
        $marginRulesData = Array();

        /** @var MarginRuleEntity $marginRule */
        foreach ($marginRules as $key => $marginRule){

            $rules = $marginRule->getRules();
            $where = "";
            $join = "";

            $marginRulesData[$key]["margin_id"] = $marginRule->getId();
            $marginRulesData[$key]["product_ids"] = Array();
            $marginRulesData[$key]["percent"] = $marginRule->getMarginPercent();

            $supplierIds = $marginRule->getSupplierIds();
            if(!empty($supplierIds)){
                $where.= " AND p.supplier_id IN (".implode(",",$supplierIds).") ";
            }

            $brandIds = $marginRule->getBrandIds();
            if(!empty($brandIds)){
                $where.= " AND p.brand_id IN (".implode(",",$brandIds).") ";
            }

            /**
             * Used only if margins are regenerated for subset of products
             */
            if(isset($data["product_ids"]) && !empty($data["product_ids"])){
                $where.= " AND p.id IN (".implode(",",$data["product_ids"]).") ";
            }

            $productGroupIds = $marginRule->getProductGroupIds();
            if(!empty($productGroupIds)){
                $join.= " JOIN product_product_group_link_entity AS p_product_product_group_link_entity ON p_product_product_group_link_entity.product_id = p.id AND product_group_id IN (".implode(",",$productGroupIds).") ";
            }

            $rules = json_decode($rules,true);

            if(!empty($rules)){

                $additionaFilter = $this->parseRuleToFilter($rules,$join,$where);
                if(isset($additionaFilter["join"])){
                    $join = $additionaFilter["join"];
                }
                if(isset($additionaFilter["where"])){
                    $where = $additionaFilter["where"];
                }
            }

            $products = $this->getProductsByRule($join,$where);

            if(empty($products)){
                unset($marginRulesData[$key]);
                continue;
            }

            $marginRulesData[$key]["product_ids"] = array_column($products,"id");


        }

        if(!empty($marginRulesData)){

            $updateQuery = "";

            foreach ($marginRulesData as $key => $marginRuleData){

                if(!empty($usedProductIds)){
                    $marginRuleData["product_ids"] = array_diff($marginRuleData["product_ids"], $usedProductIds);
                }

                if(empty($marginRuleData["product_ids"])){
                    unset($marginRulesData[$key]);
                    continue;
                }

                $updateQuery.= "UPDATE product_entity as p LEFT JOIN tax_type_entity as t ON p.tax_type_id = t.id SET p.price_base = p.price_purchase + (p.price_purchase * {$marginRuleData["percent"]} / 100.0), p.price_retail = (p.price_purchase + (p.price_purchase * {$marginRuleData["percent"]} / 100.0)) * (1+(t.percent/100)), p.margin_rule_id = {$marginRuleData["margin_id"]} WHERE p.id in (".implode(",",$marginRuleData["product_ids"]).");";
                $usedProductIds = array_merge($usedProductIds, $marginRuleData["product_ids"]);
            }

            if(!empty($updateQuery)){
                $this->databaseContext->executeNonQuery($updateQuery);
            }
        }

        /**
         * Only if all margin rules are regenerated
         */
        if(!isset($data["product_ids"])){
            /**
             * Delete unused margin_rule_ids
             */
            $q = "UPDATE product_entity SET margin_rule_id = null WHERE margin_rule_id is not null AND id not in (".implode(",",$usedProductIds).");";
            $this->databaseContext->executeNonQuery($q);
        }

        /**
         * Failsafe da se proizvodi koji imaju prazan price_purchase stave na 0 samo kada imamo suppliera
         */
        /**
         * OVO TREBA RIJESITI DRUGACIJE
         */
        /*if(isset($data["supplier_ids"]) && !empty($data["supplier_ids"])){
            $q = "UPDATE product_entity SET active = 0 WHERE active = 1 and (price_purchase = 0 OR price_purchase is null) and supplier_id IN (".implode(",",$data["supplier_ids"]).") AND product_type_id NOT IN (".CrmConstants::PRODUCT_TYPE_CONFIGURABLE.");";
            $this->databaseContext->executeNonQuery($q);
        }*/


        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->entityManager->getAttributeSetByCode("margin_rule_product_history");

        $historyHash = md5(time());

        /**
         * INSERT HISTORY
         */
        $q = "INSERT INTO  margin_rule_product_history_entity (entity_type_id, attribute_set_id, created, modified, entity_state_id, created_by, margin_rule_id, product_id, date_from, is_current, margin_percent, history_hash) 
            SELECT {$attributeSet->getEntityTypeId()}, {$attributeSet->getId()}, NOW(), NOW(), 1, '".$this->user->getUsername()."', p.margin_rule_id, p.id, NOW(), 1, m.margin_percent, '{$historyHash}' FROM product_entity AS p
            LEFT JOIN margin_rule_product_history_entity as mr ON p.id = mr.product_id AND mr.is_current = 1
            LEFT JOIN margin_rule_entity AS m ON p.margin_rule_id = m.id
            WHERE p.margin_rule_id is not null and (mr.margin_rule_id is null OR mr.margin_percent is null OR mr.margin_percent != m.margin_percent);";
        $this->databaseContext->executeNonQuery($q);

        $q = "INSERT INTO  margin_rule_product_history_entity (entity_type_id, attribute_set_id, created, modified, entity_state_id, created_by, margin_rule_id, product_id, date_from, is_current, margin_percent, history_hash) 
            SELECT {$attributeSet->getEntityTypeId()}, {$attributeSet->getId()}, NOW(), NOW(), 1, '".$this->user->getUsername()."', p.margin_rule_id, p.id, NOW(), 1, 0, '{$historyHash}' FROM product_entity AS p
            LEFT JOIN margin_rule_product_history_entity as mr ON p.id = mr.product_id AND mr.is_current = 1
            WHERE p.margin_rule_id is null and mr.margin_rule_id is not null;";
        $this->databaseContext->executeNonQuery($q);

        /**
         * Sets last entry for each product as is_current
         */
        $q = "update margin_rule_product_history_entity lt join
           (select product_id, max(date_from) as max
            from margin_rule_product_history_entity
            where is_current = 1
            group by product_id
           ) lt2
           on lt.product_id = lt2.product_id
        set lt.is_current = 0
        where lt.is_current = 1 and lt.date_from < lt2.max;";
        $this->databaseContext->executeNonQuery($q);

        /**
         * Only if all margin rules are regenerated trigger regeneration of all prices
         */
        if(!isset($data["product_ids"])) {
            /**
             * Get changed product ids
             */
            $q = "SELECT product_id FROM margin_rule_product_history_entity WHERE history_hash = '{$historyHash}'; ";
            $changedProductIds = $this->databaseContext->getAll($q);
            if(!empty($changedProductIds)){
                $changedProductIds = array_column($changedProductIds,"product_id");
            }

            if(empty($this->crmProcessManager)){
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $data["product_ids"] = $changedProductIds;
            $this->crmProcessManager->afterMarginRulesApplied($data);


            $q = "UPDATE margin_rule_entity SET recalculate = 0;";
            $this->databaseContext->executeNonQuery($q);
        }

        return true;
    }

    /**
     * @param $join
     * @param $where
     * @return mixed[]
     */
    public function getProductsByRule($join,$where){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT p.id FROM product_entity as p {$join} WHERE p.entity_state_id = 1 {$where} AND p.product_type_id NOT IN (".CrmConstants::PRODUCT_TYPE_CONFIGURABLE.");";

        return $this->databaseContext->getAll($q);
    }
}