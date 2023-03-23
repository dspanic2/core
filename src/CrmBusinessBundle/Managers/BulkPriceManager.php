<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\BulkPriceEntity;
use Doctrine\Common\Util\Inflector;

class BulkPriceManager extends ProductAttributeFilterRulesManager
{
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getBulkPriceById($id){
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(BulkPriceEntity::class);
        return $repository->find($id);
    }

    /**
     * @return bool
     */
    public function applyBulkPrices(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE bulk_price_entity SET is_applied = 1, recalculate = 1 WHERE is_active = 1 AND entity_state_id = 1 AND is_applied = 0 AND recalculate = 0;";
        $this->databaseContext->executeNonQuery($q);

        $q = "UPDATE bulk_price_entity SET is_applied = 0, recalculate = 1 WHERE is_active = 0 OR entity_state_id = 2) AND is_applied = 1 AND recalculate = 0;";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @return mixed
     */
    public function getAllAppliedBulkPriceRules(){

        $et = $this->entityManager->getEntityTypeByCode("bulk_price");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isApplied", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("priority", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param array $data
     * @param bool $force
     * @return bool
     */
    public function recalculateBulkPriceRules($data = Array()){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        if(empty($this->attributeContext)){
            $this->attributeContext = $this->container->get("attribute_context");
        }

        /**
         * Reset data if product ids are empty and supplier is empty
         */
        if(!isset($data["product_ids"]) || empty($data["product_ids"])){
            $data = Array();
        }

        $bulkPriceRules = $this->getAllAppliedBulkPriceRules();

        $usedProductIds = Array(0);
        $bulkPriceRulesData = Array();

        /** @var BulkPriceEntity $bulkPriceRule */
        foreach ($bulkPriceRules as $key => $bulkPriceRule){

            $rules = $bulkPriceRule->getRules();
            $where = "";
            $join = "";

            $bulkPriceRulesData[$key]["bulk_price_rule_id"] = $bulkPriceRule->getId();
            $bulkPriceRulesData[$key]["product_ids"] = Array();
            //$marginRulesData[$key]["percent"] = $marginRule->getMarginPercent();

            /**
             * Used only if bulk price rules are regenerated for subset of products
             */
            if(isset($data["product_ids"]) && !empty($data["product_ids"])){
                $where.= " AND p.id IN (".implode(",",$data["product_ids"]).") ";
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
                unset($bulkPriceRulesData[$key]);
                continue;
            }

            $bulkPriceRulesData[$key]["product_ids"] = array_column($products,"id");


        }

        if(!empty($bulkPriceRulesData)){

            $updateQuery = "";

            foreach ($bulkPriceRulesData as $key => $bulkPriceRuleData){

                if(!empty($usedProductIds)){
                    $bulkPriceRuleData["product_ids"] = array_diff($bulkPriceRuleData["product_ids"], $usedProductIds);
                }

                if(empty($bulkPriceRuleData["product_ids"])){
                    unset($bulkPriceRuleData[$key]);
                    continue;
                }

                $updateQuery.= "UPDATE product_entity as p SET p.bulk_price_rule_id = {$bulkPriceRuleData["bulk_price_rule_id"]} WHERE p.id in (".implode(",",$bulkPriceRuleData["product_ids"]).");";
                $usedProductIds = array_merge($usedProductIds, $bulkPriceRuleData["product_ids"]);
            }

            if(!empty($updateQuery)){
                $this->databaseContext->executeNonQuery($updateQuery);
            }
        }

        /**
         * Only if all bulk price rules are regenerated
         */
        if(!isset($data["product_ids"])){
            /**
             * Delete unused bulk_price_rule_id
             */
            $q = "UPDATE product_entity SET bulk_price_rule_id = null WHERE bulk_price_rule_id is not null AND id not in (".implode(",",$usedProductIds).");";
            $this->databaseContext->executeNonQuery($q);
        }


        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->entityManager->getAttributeSetByCode("bulk_price_rule_product_history");

        $historyHash = md5(time());

        /**
         * INSERT HISTORY
         */
        $q = "INSERT INTO  bulk_price_rule_product_history_entity (entity_type_id, attribute_set_id, created, modified, entity_state_id, created_by, bulk_price_rule_id, product_id, date_from, is_current, history_hash) 
            SELECT {$attributeSet->getEntityTypeId()}, {$attributeSet->getId()}, NOW(), NOW(), 1, '".$this->user->getUsername()."', p.bulk_price_rule_id, p.id, NOW(), 1, '{$historyHash}' FROM product_entity AS p
            LEFT JOIN bulk_price_rule_product_history_entity as mr ON p.id = mr.product_id AND mr.is_current = 1
            WHERE p.bulk_price_rule_id is not null and (mr.bulk_price_rule_id != p.bulk_price_rule_id OR mr.bulk_price_rule_id is null) ;";
        $this->databaseContext->executeNonQuery($q);

        $q = "INSERT INTO  bulk_price_rule_product_history_entity (entity_type_id, attribute_set_id, created, modified, entity_state_id, created_by, bulk_price_rule_id, product_id, date_from, is_current, history_hash) 
            SELECT {$attributeSet->getEntityTypeId()}, {$attributeSet->getId()}, NOW(), NOW(), 1, '".$this->user->getUsername()."', p.bulk_price_rule_id, p.id, NOW(), 1, '{$historyHash}' FROM product_entity AS p
            LEFT JOIN bulk_price_rule_product_history_entity as mr ON p.id = mr.product_id AND mr.is_current = 1
            WHERE p.bulk_price_rule_id is null and mr.bulk_price_rule_id is not null;";
        $this->databaseContext->executeNonQuery($q);

        /**
         * Sets last entry for each product as is_current
         */
        $q = "update bulk_price_rule_product_history_entity lt join
           (select product_id, max(date_from) as max
            from bulk_price_rule_product_history_entity
            where is_current = 1
            group by product_id
           ) lt2
           on lt.product_id = lt2.product_id
        set lt.is_current = 0
        where lt.is_current = 1 and lt.date_from < lt2.max;";
        $this->databaseContext->executeNonQuery($q);

        /**
         * Only if all bulk price rules are regenerated trigger regeneration after changes
         */
        if(!isset($data["product_ids"])) {
            /**
             * Get changed product ids
             */
            $q = "SELECT product_id FROM bulk_price_rule_product_history_entity WHERE history_hash = '{$historyHash}'; ";
            $changedProductIds = $this->databaseContext->getAll($q);
            if(!empty($changedProductIds)){
                $changedProductIds = array_column($changedProductIds,"product_id");
            }

            if(empty($this->crmProcessManager)){
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $data["product_ids"] = $changedProductIds;
            $this->crmProcessManager->afterBulkPriceRulesApplied($data);


            $q = "UPDATE bulk_price_entity SET recalculate = 0;";
            $this->databaseContext->executeNonQuery($q);
        }

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }
        $this->cacheManager->invalidateCacheByTag("product");

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

        $q = "SELECT p.id FROM product_entity as p {$join} WHERE p.entity_state_id = 1 {$where} AND p.product_type_id IN (".CrmConstants::PRODUCT_TYPE_SIMPLE.");";
        return $this->databaseContext->getAll($q);
    }
}