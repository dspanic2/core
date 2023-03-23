<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\AccountGroupEntity;
use CrmBusinessBundle\Entity\DiscountCatalogEntity;
use Doctrine\Common\Util\Inflector;

class DiscountRulesManager extends ProductAttributeFilterRulesManager
{
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getDiscountCatalogById($id){
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(DiscountCatalogEntity::class);
        return $repository->find($id);
    }

    /**
     * @return bool
     */
    public function applyDiscountRules(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE discount_catalog_entity SET is_applied = 1, recalculate = 1 WHERE date_from <= NOW() AND date_to >= NOW() AND is_active = 1 AND entity_state_id = 1 AND is_applied = 0 AND recalculate = 0;";
        $this->databaseContext->executeNonQuery($q);

        $q = "UPDATE discount_catalog_entity SET is_applied = 0, recalculate = 1 WHERE (date_from > NOW() OR date_to < NOW() OR is_active = 0 OR entity_state_id = 2) AND is_applied = 1 AND recalculate = 0;";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @return array
     */
    public function getProductDiscountCatalogPrices($ids = Array()){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $where = "";
        if(!empty($ids)){
            $where = " WHERE product_id IN (".implode(",",$ids).") ";
        }

        $q="SELECT * FROM product_discount_catalog_price_entity {$where};";
        $data = $this->databaseContext->getAll($q);

        $ret = Array();

        if(!empty($data)){
            foreach ($data as $d){
                $ret[$d["product_id"]] = $d;
            }
        }

        return $ret;
    }

    /**
     * @param array $ids
     * @return array
     */
    public function getProductAccountPrices($ids = Array()){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $where = "";
        if(!empty($ids)){
            $where=" WHERE CONCAT(product_id,'_',account_id) IN ('".implode("','",$ids)."')";
        }

        $q="SELECT * FROM product_account_price_entity {$where};";
        $data = $this->databaseContext->getAll($q);

        $ret = Array();

        if(!empty($data)){
            foreach ($data as $d) {
                $ret["{$d["product_id"]}_{$d["account_id"]}"] = $d;
            }
        }

        return $ret;
    }

    /**
     * @param array $ids
     * @return array
     */
    public function getProductAccountGroupPrices($ids = Array()){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $where = "";
        if(!empty($ids)){
            $where=" WHERE CONCAT(product_id,'_',account_group_id) IN ('".implode("','",$ids)."')";
        }

        $q="SELECT * FROM product_account_group_price_entity {$where};";
        $data = $this->databaseContext->getAll($q);

        $ret = Array();

        if(!empty($data)){
            foreach ($data as $d) {
                $ret["{$d["product_id"]}_{$d["account_group_id"]}"] = $d;
            }
        }

        return $ret;
    }

    /**
     * @param string $type
     * @return mixed
     */
    public function getAllAppliedDiscountRules($type = "product"){

        $et = $this->entityManager->getEntityTypeByCode("discount_catalog");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isApplied", "eq", 1));
        if($type == "product"){
            $compositeFilter->addFilter(new SearchFilter("accounts.id", "nu", null));
            $compositeFilter->addFilter(new SearchFilter("accountGroups.id", "nu", null));
        }
        elseif($type == "account"){
            $compositeFilter->addFilter(new SearchFilter("accounts.id", "nn", null));
            $compositeFilter->addFilter(new SearchFilter("accountGroups.id", "nu", null));
        }
        elseif($type == "account_group"){
            $compositeFilter->addFilter(new SearchFilter("accounts.id", "nu", null));
            $compositeFilter->addFilter(new SearchFilter("accountGroups.id", "nn", null));
        }

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
    public function recalculateDiscountRules($data = Array()){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        if(empty($this->attributeContext)){
            $this->attributeContext = $this->container->get("attribute_context");
        }

        /**
         * Reset data if product ids are empty
         */
        if(!isset($data["product_ids"]) || empty($data["product_ids"])){
            $data = Array();
        }

        // Check wether to invalidate cache at the end
        $hadDiscountRules = false;

        /**
         * Delete discount catalog prices if all rules are inactive
         */
        $discountRules = $this->getAllAppliedDiscountRules(null);
        if (empty($discountRules)) {
            $q = "DELETE FROM product_discount_catalog_price_entity;";
            $this->databaseContext->executeNonQuery($q);
            $q = "DELETE FROM product_account_price_entity;";
            $this->databaseContext->executeNonQuery($q);
            $q = "DELETE FROM product_account_group_price_entity;";
            $this->databaseContext->executeNonQuery($q);

            $hadDiscountRules = true;
        }

        /**
         * Recalculate direct product discounts
         */
        $discountRules = $this->getAllAppliedDiscountRules();

        if(!empty($discountRules)){

            $usedProductIds = Array(0);
            $discountRulesData = Array();

            $insertProductDiscountCatalogPrices = "INSERT IGNORE INTO product_discount_catalog_price_entity (entity_type_id, attribute_set_id, created, modified, created_by, modified_by, entity_state_id, product_id, discount_price_base, discount_price_retail, rebate, type, date_valid_from, date_valid_to) VALUES ";
            /** @var AttributeSet $productDiscountCatalogPricesAttributeSet */
            $productDiscountCatalogPricesAttributeSet = $this->entityManager->getAttributeSetByCode("product_discount_catalog_price");

            /** @var DiscountCatalogEntity $discountRule */
            foreach ($discountRules as $key => $discountRule){

                $rules = $discountRule->getRules();
                $where = "";
                $join = "";

                $discountRulesData[$key]["discount_rule"] = $discountRule;
                $discountRulesData[$key]["product_ids"] = Array();
                $discountRulesData[$key]["products"] = Array();

                /**
                 * Used only if rules are regenerated for subset of products
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
                    unset($discountRulesData[$key]);
                    continue;
                }

                $discountRulesData[$key]["product_ids"] = array_column($products,"id");
                $discountRulesData[$key]["products"] = $products;
            }

            if(!empty($discountRulesData)){

                foreach ($discountRulesData as $key => $discountRuleData){

                    if(!empty($usedProductIds)){
                        $discountRuleData["product_ids"] = array_diff($discountRuleData["product_ids"], $usedProductIds);
                    }

                    if(empty($discountRuleData["product_ids"])){
                        unset($discountRuleData[$key]);
                        continue;
                    }

                    $productDiscountCatalogPrices = $this->getProductDiscountCatalogPrices($discountRuleData["product_ids"]);
                    $updateQuery = "";
                    $insertProductDiscountCatalogPricesData = "";
                    $count = 0;

                    foreach ($discountRuleData["products"] as $product){

                        /*if(!in_array($product["id"],$discountRuleData["product_ids"])){
                            continue;
                        }*/

                        $discountPriceBase = floatval($product["price_base"]) - round($product["price_base"]*floatval($discountRuleData["discount_rule"]->getDiscountPercent())/100,2);
                        $discountPriceRetail = floatval($product["price_retail"]) - round($product["price_retail"]*floatval($discountRuleData["discount_rule"]->getDiscountPercent())/100,2);

                        /**
                         * Update
                         */
                        if(isset($productDiscountCatalogPrices[$product["id"]])){

                            if($productDiscountCatalogPrices[$product["id"]]["type"] != $discountRuleData["discount_rule"]->getId() || floatval($productDiscountCatalogPrices[$product["id"]]["rebate"]) != floatval($discountRuleData["discount_rule"]->getDiscountPercent()) || abs(floatval($discountPriceBase) - floatval($productDiscountCatalogPrices[$product["id"]]["discount_price_base"])) > 0.00001 || abs(floatval($discountPriceRetail) - floatval($productDiscountCatalogPrices[$product["id"]]["discount_price_retail"])) > 0.00001){
                                $updateQuery.= "UPDATE product_discount_catalog_price_entity SET modified = NOW(), discount_price_base = {$discountPriceBase}, discount_price_retail = {$discountPriceRetail}, type = {$discountRuleData["discount_rule"]->getId()}, date_valid_from = '{$discountRuleData["discount_rule"]->getDateFrom()->format("Y-m-d H:i:d")}', rebate = {$discountRuleData["discount_rule"]->getDiscountPercent()}, date_valid_to = '{$discountRuleData["discount_rule"]->getDateTo()->format("Y-m-d H:i:d")}' WHERE id = {$productDiscountCatalogPrices[$product["id"]]["id"]};";
                                $count++;
                            }
                        }
                        /**
                         * Insert
                         */
                        else{
                            $insertProductDiscountCatalogPricesData .= "('{$productDiscountCatalogPricesAttributeSet->getEntityTypeId()}', '{$productDiscountCatalogPricesAttributeSet->getId()}', NOW(), NOW(), 'system', 'system', '1', {$product["id"]}, {$discountPriceBase}, {$discountPriceRetail}, {$discountRuleData["discount_rule"]->getDiscountPercent()}, {$discountRuleData["discount_rule"]->getId()}, '{$discountRuleData["discount_rule"]->getDateFrom()->format("Y-m-d H:i:d")}', '{$discountRuleData["discount_rule"]->getDateTo()->format("Y-m-d H:i:d")}'),";
                            $count++;
                        }

                        if($count > 300){
                            $count = 0;
                            if(!empty($updateQuery)){
                                $this->databaseContext->executeNonQuery($updateQuery);
                                $updateQuery = "";
                            }
                            if(!empty($insertProductDiscountCatalogPricesData)){
                                $insertProductDiscountCatalogPricesData = substr($insertProductDiscountCatalogPricesData, 0, -1);
                                $this->databaseContext->executeNonQuery($insertProductDiscountCatalogPrices.$insertProductDiscountCatalogPricesData);
                                $insertProductDiscountCatalogPricesData = "";
                            }
                        }
                    }

                    if(!empty($updateQuery)){
                        $this->databaseContext->executeNonQuery($updateQuery);
                    }
                    if(!empty($insertProductDiscountCatalogPricesData)){
                        $insertProductDiscountCatalogPricesData = substr($insertProductDiscountCatalogPricesData, 0, -1);
                        $this->databaseContext->executeNonQuery($insertProductDiscountCatalogPrices.$insertProductDiscountCatalogPricesData);
                    }

                    $usedProductIds = array_merge($usedProductIds, $discountRuleData["product_ids"]);
                }
            }

            /**
             * Only if all discount rules are regenerated trigger regeneration of all prices
             */
            if(!isset($data["product_ids"])) {
                if(!empty($usedProductIds)){
                    $q = "DELETE FROM product_discount_catalog_price_entity WHERE product_id NOT IN (".implode(",",$usedProductIds).");";
                    $this->databaseContext->executeNonQuery($q);
                }
            }
            else{
                $data["product_ids"] = array_diff($data["product_ids"], $usedProductIds);
                if(!empty($data["product_ids"])){
                    $q = "DELETE FROM product_discount_catalog_price_entity WHERE product_id IN (".implode(",",$data["product_ids"]).");";
                    $this->databaseContext->executeNonQuery($q);
                }
            }

            $hadDiscountRules = true;

            //$this->databaseContext->executeNonQuery("CALL sp_product_active_discount_catalog_check()");
        }

        /**
         * Recalculate discount rules for accounts
         */
        $discountRules = $this->getAllAppliedDiscountRules("account");

        if(!empty($discountRules)){

            $usedProductIds = Array(0);
            $discountRulesData = Array();

            $insertProductAccountPrices = "INSERT IGNORE INTO product_account_price_entity (entity_type_id, attribute_set_id, created, modified, created_by, modified_by, entity_state_id, product_id, account_id, price_base, price_retail, discount_price_base, discount_price_retail, rebate, type, date_valid_from, date_valid_to) VALUES ";
            /** @var AttributeSet $productAccountPriceAttributeSet */
            $productAccountPriceAttributeSet = $this->entityManager->getAttributeSetByCode("product_account_price");

            /** @var DiscountCatalogEntity $discountRule */
            foreach ($discountRules as $key => $discountRule){

                $st1 = microtime(true);

                $rules = $discountRule->getRules();
                $where = "";
                $join = "";

                $discountRulesData[$key]["discount_rule"] = $discountRule;
                $discountRulesData[$key]["product_ids"] = Array();
                $discountRulesData[$key]["products"] = Array();

                /**
                 * Used only if rules are regenerated for subset of products
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
                    unset($discountRulesData[$key]);
                    continue;
                }

                $tmpProductIds = array_column($products,"id");

                /** @var AccountEntity $account */
                foreach ($discountRule->getAccounts() as $account){
                    foreach ($tmpProductIds as $tmpProductId){
                        $discountRulesData[$key]["product_ids"][] = $tmpProductId."_".$account->getId();
                    }
                }
                $discountRulesData[$key]["products"] = $products;
            }

            if(!empty($discountRulesData)){

                foreach ($discountRulesData as $key => $discountRuleData){

                    if(!empty($usedProductIds)){
                        $discountRuleData["product_ids"] = array_diff($discountRuleData["product_ids"], $usedProductIds);
                    }

                    if(empty($discountRuleData["product_ids"])){
                        unset($discountRuleData[$key]);
                        continue;
                    }

                    $productAccountPrices = $this->getProductAccountPrices($discountRuleData["product_ids"]);

                    /** @var AccountEntity $account */
                    foreach ($discountRuleData["discount_rule"]->getAccounts() as $account){

                        $count = 0;

                        $updateQuery = "";
                        $insertProductAccountPricesData = "";

                        foreach ($discountRuleData["products"] as $product){

                            /*if(!in_array($product["id"]."_".$account->getId(),$discountRuleData["product_ids"])){
                                continue;
                            }*/

                            $discountPriceBase = floatval($product["price_base"]) - round($product["price_base"]*floatval($discountRuleData["discount_rule"]->getDiscountPercent())/100,2);
                            $discountPriceRetail = floatval($product["price_retail"]) - round($product["price_retail"]*floatval($discountRuleData["discount_rule"]->getDiscountPercent())/100,2);

                            /**
                             * Update
                             */
                            if(isset($productAccountPrices[$product["id"]."_".$account->getId()])){
                                if($productAccountPrices[$product["id"]."_".$account->getId()]["type"] != $discountRuleData["discount_rule"]->getId() || floatval($productAccountPrices[$product["id"]."_".$account->getId()]["rebate"]) != floatval($discountRuleData["discount_rule"]->getDiscountPercent()) || abs(floatval($discountPriceBase) - floatval($productAccountPrices[$product["id"]."_".$account->getId()]["discount_price_base"])) > 0.00001 || abs(floatval($discountPriceRetail) - floatval($productAccountPrices[$product["id"]."_".$account->getId()]["discount_price_retail"])) > 0.00001){
                                    $updateQuery.= "UPDATE product_account_price_entity SET modified = NOW(), discount_price_base = {$discountPriceBase}, discount_price_retail = {$discountPriceRetail}, type = {$discountRuleData["discount_rule"]->getId()}, date_valid_from = '{$discountRuleData["discount_rule"]->getDateFrom()->format("Y-m-d H:i:d")}', rebate = {$discountRuleData["discount_rule"]->getDiscountPercent()}, date_valid_to = '{$discountRuleData["discount_rule"]->getDateTo()->format("Y-m-d H:i:d")}' WHERE id = {$productAccountPrices[$product["id"].'_'.$account->getId()]["id"]};";
                                    $count++;
                                }
                            }
                            /**
                             * Insert
                             */
                            else{
                                $insertProductAccountPricesData .= "('{$productAccountPriceAttributeSet->getEntityTypeId()}', '{$productAccountPriceAttributeSet->getId()}', NOW(), NOW(), 'system', 'system', '1', {$product["id"]}, {$account->getId()}, NULL, NULL, {$discountPriceBase}, {$discountPriceRetail}, {$discountRuleData["discount_rule"]->getDiscountPercent()}, {$discountRuleData["discount_rule"]->getId()}, '{$discountRuleData["discount_rule"]->getDateFrom()->format("Y-m-d H:i:d")}', '{$discountRuleData["discount_rule"]->getDateTo()->format("Y-m-d H:i:d")}'),";
                                $count++;
                            }

                            if($count > 300){
                                $count = 0;
                                if(!empty($updateQuery)){
                                    $this->databaseContext->executeNonQuery($updateQuery);
                                    $updateQuery = "";
                                }
                                if(!empty($insertProductAccountPricesData)){
                                    $insertProductAccountPricesData = substr($insertProductAccountPricesData, 0, -1);
                                    $this->databaseContext->executeNonQuery($insertProductAccountPrices.$insertProductAccountPricesData);
                                    $insertProductAccountPricesData = "";
                                }
                            }
                        }

                        if(!empty($updateQuery)){
                            $this->databaseContext->executeNonQuery($updateQuery);
                            $updateQuery = "";
                        }
                        if(!empty($insertProductAccountPricesData)){
                            $insertProductAccountPricesData = substr($insertProductAccountPricesData, 0, -1);
                            $this->databaseContext->executeNonQuery($insertProductAccountPrices.$insertProductAccountPricesData);
                            $insertProductAccountPricesData = "";
                        }
                    }

                    $usedProductIds = array_merge($usedProductIds, $discountRuleData["product_ids"]);

                }
            }

            /**
             * Only if all discount rules are regenerated trigger regeneration of all prices
             */
            if(!isset($data["product_ids"])) {
                if(!empty($usedProductIds)){
                    $where = " WHERE CONCAT(product_id,'_',account_id) IN ('".implode("','",$usedProductIds)."')";

                    $q = "SELECT id FROM product_account_price_entity {$where};";
                    $existingIds = $this->databaseContext->getAll($q);
                    if(!empty($existingIds)){
                        $existingIds = array_column($existingIds,"id");
                    }
                    else{
                        $existingIds = Array(0);
                    }

                    $q = "DELETE FROM product_account_price_entity WHERE id NOT IN (".implode(",",$existingIds).");";
                    $this->databaseContext->executeNonQuery($q);
                }
            }
            else{
                $data["product_ids"] = array_diff($data["product_ids"], $usedProductIds);
                if(!empty($data["product_ids"])){
                    $where = "";
                    if(!empty($data["product_ids"])){
                        $where = " WHERE CONCAT(product_id,'_',account_id) IN ('".implode("','",$data["product_ids"])."')";
                    }

                    $q = "DELETE FROM product_account_price_entity {$where};";
                    $this->databaseContext->executeNonQuery($q);
                }
            }

            $hadDiscountRules = true;
        }

        /**
         * Recalculate discount rules for account groups
         */
        $discountRules = $this->getAllAppliedDiscountRules("account_group");
        if(!empty($discountRules)){

            $usedProductIds = Array(0);
            $discountRulesData = Array();

            $insertProductAccountGroupPrices = "INSERT IGNORE INTO product_account_group_price_entity (entity_type_id, attribute_set_id, created, modified, created_by, modified_by, entity_state_id, product_id, account_group_id, price_base, price_retail, discount_price_base, discount_price_retail, rebate, type, date_valid_from, date_valid_to) VALUES ";
            /** @var AttributeSet $productAccountPriceAttributeSet */
            $productAccountGroupPriceAttributeSet = $this->entityManager->getAttributeSetByCode("product_account_group_price");

            /** @var DiscountCatalogEntity $discountRule */
            foreach ($discountRules as $key => $discountRule){

                $rules = $discountRule->getRules();
                $where = "";
                $join = "";

                $discountRulesData[$key]["discount_rule"] = $discountRule;
                $discountRulesData[$key]["product_ids"] = Array();
                $discountRulesData[$key]["products"] = Array();

                /**
                 * Used only if rules are regenerated for subset of products
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
                    unset($discountRulesData[$key]);
                    continue;
                }

                $tmpProductIds = array_column($products,"id");

                /** @var AccountGroupEntity $accountGroup */
                foreach ($discountRule->getAccountGroups() as $accountGroup){
                    foreach ($tmpProductIds as $tmpProductId){
                        $discountRulesData[$key]["product_ids"][] = $tmpProductId."_".$accountGroup->getId();
                    }
                }
                $discountRulesData[$key]["products"] = $products;
            }

            if(!empty($discountRulesData)){

                foreach ($discountRulesData as $key => $discountRuleData){

                    if(!empty($usedProductIds)){
                        $discountRuleData["product_ids"] = array_diff($discountRuleData["product_ids"], $usedProductIds);
                    }

                    if(empty($discountRuleData["product_ids"])){
                        unset($discountRuleData[$key]);
                        continue;
                    }

                    $productAccountGroupPrices = $this->getProductAccountGroupPrices($discountRuleData["product_ids"]);

                    /** @var AccountGroupEntity $accountGroup */
                    foreach ($discountRuleData["discount_rule"]->getAccountGroups() as $accountGroup){

                        $count = 0;

                        $updateQuery = "";
                        $insertProductAccountGroupPricesData = "";

                        foreach ($discountRuleData["products"] as $product){

                            /*if(!in_array($product["id"]."_".$accountGroup->getId(),$discountRuleData["product_ids"])){
                                continue;
                            }*/

                            $discountPriceBase = floatval($product["price_base"]) - round($product["price_base"]*floatval($discountRuleData["discount_rule"]->getDiscountPercent())/100,2);
                            $discountPriceRetail = floatval($product["price_retail"]) - round($product["price_retail"]*floatval($discountRuleData["discount_rule"]->getDiscountPercent())/100,2);

                            /**
                             * Update
                             */
                            if(isset($productAccountGroupPrices[$product["id"]."_".$accountGroup->getId()])){

                                if($productAccountGroupPrices[$product["id"]."_".$accountGroup->getId()]["type"] != $discountRuleData["discount_rule"]->getId() || floatval($productAccountGroupPrices[$product["id"]."_".$accountGroup->getId()]["rebate"]) != floatval($discountRuleData["discount_rule"]->getDiscountPercent()) || abs(floatval($discountPriceBase) - floatval($productAccountGroupPrices[$product["id"]."_".$accountGroup->getId()]["discount_price_base"])) > 0.00001 || abs(floatval($discountPriceRetail) - floatval($productAccountGroupPrices[$product["id"]."_".$accountGroup->getId()]["discount_price_retail"])) > 0.00001){
                                    $updateQuery.= "UPDATE product_account_group_price_entity SET modified = NOW(), discount_price_base = {$discountPriceBase}, discount_price_retail = {$discountPriceRetail}, type = {$discountRuleData["discount_rule"]->getId()}, date_valid_from = '{$discountRuleData["discount_rule"]->getDateFrom()->format("Y-m-d H:i:d")}', rebate = {$discountRuleData["discount_rule"]->getDiscountPercent()}, date_valid_to = '{$discountRuleData["discount_rule"]->getDateTo()->format("Y-m-d H:i:d")}' WHERE id = {$productAccountGroupPrices[$product["id"].'_'.$accountGroup->getId()]["id"]};";
                                    $count++;
                                }
                            }
                            /**
                             * Insert
                             */
                            else{
                                $insertProductAccountGroupPricesData .= "('{$productAccountGroupPriceAttributeSet->getEntityTypeId()}', '{$productAccountGroupPriceAttributeSet->getId()}', NOW(), NOW(), 'system', 'system', '1', {$product["id"]}, {$accountGroup->getId()}, NULL, NULL, {$discountPriceBase}, {$discountPriceRetail}, {$discountRuleData["discount_rule"]->getDiscountPercent()}, {$discountRuleData["discount_rule"]->getId()}, '{$discountRuleData["discount_rule"]->getDateFrom()->format("Y-m-d H:i:d")}', '{$discountRuleData["discount_rule"]->getDateTo()->format("Y-m-d H:i:d")}'),";
                                $count++;
                            }

                            if($count > 300){
                                $count = 0;
                                if(!empty($updateQuery)){
                                    $this->databaseContext->executeNonQuery($updateQuery);
                                    $updateQuery = "";
                                }
                                if(!empty($insertProductAccountGroupPricesData)){
                                    $insertProductAccountGroupPricesData = substr($insertProductAccountGroupPricesData, 0, -1);
                                    $this->databaseContext->executeNonQuery($insertProductAccountGroupPrices.$insertProductAccountGroupPricesData);
                                    $insertProductAccountGroupPricesData = "";
                                }
                            }
                        }

                        if(!empty($updateQuery)){
                            $this->databaseContext->executeNonQuery($updateQuery);
                            $updateQuery = "";
                        }
                        if(!empty($insertProductAccountGroupPricesData)){
                            $insertProductAccountGroupPricesData = substr($insertProductAccountGroupPricesData, 0, -1);
                            $this->databaseContext->executeNonQuery($insertProductAccountGroupPrices.$insertProductAccountGroupPricesData);
                            $insertProductAccountGroupPricesData = "";
                        }
                    }

                    $usedProductIds = array_merge($usedProductIds, $discountRuleData["product_ids"]);
                }
            }

            /**
             * Only if all discount rules are regenerated trigger regeneration of all prices
             */
            if(!isset($data["product_ids"])) {
                if(!empty($usedProductIds)){
                    $where = " WHERE CONCAT(product_id,'_',account_group_id) IN ('".implode("','",$usedProductIds)."')";

                    $q = "SELECT id FROM product_account_group_price_entity {$where};";
                    $existingIds = $this->databaseContext->getAll($q);
                    if(!empty($existingIds)){
                        $existingIds = array_column($existingIds,"id");
                    }
                    else{
                        $existingIds = Array(0);
                    }

                    $q = "DELETE FROM product_account_group_price_entity WHERE id NOT IN (".implode(",",$existingIds).");";
                    $this->databaseContext->executeNonQuery($q);
                }
            }
            else{
                $data["product_ids"] = array_diff($data["product_ids"], $usedProductIds);
                if(!empty($data["product_ids"])){
                    $where = "";
                    if(!empty($data["product_ids"])){
                        $where = " WHERE CONCAT(product_id,'_',account_group_id) IN ('".implode("','",$data["product_ids"])."')";
                    }

                    $q = "DELETE FROM product_account_group_price_entity {$where};";
                    $this->databaseContext->executeNonQuery($q);
                }
            }

            $hadDiscountRules = true;
        }

        if ($hadDiscountRules) {
            if (empty($this->cacheManager)) {
                $this->cacheManager = $this->container->get("cache_manager");
            }
            $this->cacheManager->invalidateCacheByTag("product");
        }


        /**
         * Only if all discount rules are regenerated mark discount rules as recalculated
         */
        if(!isset($data["product_ids"])) {
            if(empty($this->crmProcessManager)){
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $this->crmProcessManager->afterDiscountsApplied();

            $q = "UPDATE discount_catalog_entity SET recalculate = 0;";
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

        $q = "SELECT p.id, p.price_base, p.price_retail FROM product_entity as p {$join} WHERE p.entity_state_id = 1 {$where} AND (p.exclude_from_discounts is null or p.exclude_from_discounts = 0) AND p.product_type_id NOT IN (".CrmConstants::PRODUCT_TYPE_CONFIGURABLE.");";
        return $this->databaseContext->getAll($q);
    }

    /**
     * @return bool
     */
    public function checkIfDiscountRulesNeedRecalculating(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM discount_catalog_entity WHERE recalculate = 1;";
        $exists = $this->databaseContext->getAll($q);

        if(!empty($exists) || count($exists) > 0){
            return true;
        }

        return false;
    }
}