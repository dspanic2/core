<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\ErrorLogManager;
use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\LoyaltyCardEntity;
use CrmBusinessBundle\Entity\LoyaltyEarningsConfigurationEntity;
use CrmBusinessBundle\Entity\LoyaltyEarningsEntity;
use CrmBusinessBundle\Entity\LoyaltyEarningsOrderItemLinkEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderItemEntity;
use Doctrine\Common\Util\Inflector;
use Exception;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Entity\LoyaltySpendingsEntity;

class LoyaltyManager extends ProductAttributeFilterRulesManager
{
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $id
     * @return |null
     */
    public function getLoyaltyEarningConfigurationById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(LoyaltyEarningsConfigurationEntity::class);
        return $repository->find($id);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getLoyaltyEarningById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(LoyaltyEarningsEntity::class);
        return $repository->find($id);
    }

    /**
     * @return bool
     */
    public function applyLoyaltyEarningsConfiguration(){

        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "UPDATE loyalty_earnings_configuration_entity SET is_applied = 1, recalculate = 1 WHERE is_active = 1 AND entity_state_id = 1 AND is_applied = 0 AND recalculate = 0;";
        $this->databaseContext->executeNonQuery($q);

        $q = "UPDATE loyalty_earnings_configuration_entity SET is_applied = 0, recalculate = 1 WHERE is_active = 0 OR entity_state_id = 2) AND is_applied = 1 AND recalculate = 0;";
        $this->databaseContext->executeNonQuery($q);

        return true;
    }

    /**
     * @return mixed
     */
    public function getAllAppliedRules(){

        $et = $this->entityManager->getEntityTypeByCode("loyalty_earnings_configuration");

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
    public function recalculateLoyaltyEarningsConfiguration($data = Array()){

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

        $loyaltyEarningsConfiguration = $this->getAllAppliedRules();

        $usedProductIds = Array(0);
        $loyaltyEarningsConfigurationData = Array();

        /** @var LoyaltyEarningsConfigurationEntity $loyaltyEarningConfiguration */
        foreach ($loyaltyEarningsConfiguration as $key => $loyaltyEarningConfiguration){

            $rules = $loyaltyEarningConfiguration->getRules();
            $where = "";
            $join = "";

            $loyaltyEarningsConfigurationData[$key]["loyalty_earning_rule_id"] = $loyaltyEarningConfiguration->getId();
            $loyaltyEarningsConfigurationData[$key]["product_ids"] = Array();

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
                unset($loyaltyEarningsConfigurationData[$key]);
                continue;
            }

            $loyaltyEarningsConfigurationData[$key]["product_ids"] = array_column($products,"id");


        }

        if(!empty($loyaltyEarningsConfigurationData)){

            $updateQuery = "";

            foreach ($loyaltyEarningsConfigurationData as $key => $loyaltyEarningConfigurationData){

                if(!empty($usedProductIds)){
                    $loyaltyEarningConfigurationData["product_ids"] = array_diff($loyaltyEarningConfigurationData["product_ids"], $usedProductIds);
                }

                if(empty($loyaltyEarningConfigurationData["product_ids"])){
                    unset($loyaltyEarningConfigurationData[$key]);
                    continue;
                }

                $updateQuery.= "UPDATE product_entity as p SET p.loyalty_earning_rule_id = {$loyaltyEarningConfigurationData["loyalty_earning_rule_id"]} WHERE p.id in (".implode(",",$loyaltyEarningConfigurationData["product_ids"]).");";
                $usedProductIds = array_merge($usedProductIds, $loyaltyEarningConfigurationData["product_ids"]);
            }

            if(!empty($updateQuery)){
                $this->databaseContext->executeNonQuery($updateQuery);
            }
        }

        /**
         * Only if all rules are regenerated
         */
        if(!isset($data["product_ids"])){
            /**
             * Delete unused loyalty_earning_rule_id

             */
            $q = "UPDATE product_entity SET loyalty_earning_rule_id = null WHERE loyalty_earning_rule_id is not null AND id not in (".implode(",",$usedProductIds).");";
            $this->databaseContext->executeNonQuery($q);
        }


        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->entityManager->getAttributeSetByCode("loyalty_earning_configuration_product_history");

        $historyHash = md5(time());

        /**
         * INSERT HISTORY
         */
        $q = "INSERT INTO  loyalty_earning_configuration_product_history_entity (entity_type_id, attribute_set_id, created, modified, entity_state_id, created_by, loyalty_earnings_configuration_id, product_id, date_from, is_current, points_multiplier, history_hash)
            SELECT {$attributeSet->getEntityTypeId()}, {$attributeSet->getId()}, NOW(), NOW(), 1, '".$this->user->getUsername()."', p.loyalty_earning_rule_id, p.id, NOW(), 1, m.points_multiplier, '{$historyHash}' FROM product_entity AS p
            LEFT JOIN loyalty_earning_configuration_product_history_entity as mr ON p.id = mr.product_id AND mr.is_current = 1
            LEFT JOIN loyalty_earnings_configuration_entity AS m ON p.loyalty_earning_rule_id = m.id
            WHERE p.loyalty_earning_rule_id is not null and (mr.loyalty_earnings_configuration_id is null OR mr.points_multiplier is null OR mr.points_multiplier != m.points_multiplier);";
        $this->databaseContext->executeNonQuery($q);

        $q = "INSERT INTO  loyalty_earning_configuration_product_history_entity (entity_type_id, attribute_set_id, created, modified, entity_state_id, created_by, loyalty_earnings_configuration_id, product_id, date_from, is_current, points_multiplier, history_hash)
            SELECT {$attributeSet->getEntityTypeId()}, {$attributeSet->getId()}, NOW(), NOW(), 1, '".$this->user->getUsername()."', p.loyalty_earning_rule_id, p.id, NOW(), 1, 0, '{$historyHash}' FROM product_entity AS p
            LEFT JOIN loyalty_earning_configuration_product_history_entity as mr ON p.id = mr.product_id AND mr.is_current = 1
            WHERE p.loyalty_earning_rule_id is null and mr.loyalty_earnings_configuration_id is not null;";
        $this->databaseContext->executeNonQuery($q);

        /**
         * Sets last entry for each product as is_current
         */
        $q = "update loyalty_earning_configuration_product_history_entity lt join
           (select product_id, max(date_from) as max
            from loyalty_earning_configuration_product_history_entity
            where is_current = 1
            group by product_id
           ) lt2
           on lt.product_id = lt2.product_id
        set lt.is_current = 0
        where lt.is_current = 1 and lt.date_from < lt2.max;";
        $this->databaseContext->executeNonQuery($q);

        /**
         * Only if all rules are regenerated trigger regeneration after changes
         */
        if(!isset($data["product_ids"])) {
            /**
             * Get changed product ids
             */
            $q = "SELECT product_id FROM loyalty_earning_configuration_product_history_entity WHERE history_hash = '{$historyHash}'; ";
            $changedProductIds = $this->databaseContext->getAll($q);
            if(!empty($changedProductIds)){
                $changedProductIds = array_column($changedProductIds,"product_id");
            }

            if(empty($this->crmProcessManager)){
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $data["product_ids"] = $changedProductIds;
            $this->crmProcessManager->afterLoyaltyEarningsConfigurationApplied($data);


            $q = "UPDATE loyalty_earnings_configuration_entity SET recalculate = 0;";
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

    /**
     * @param LoyaltyCardEntity|null $loyatyCard
     * @param $data
     * @return LoyaltyCardEntity|mixed|null
     */
    public function insertUpdateLoyaltyCard(LoyaltyCardEntity $loyatyCard = null, $data)
    {
        if (empty($loyatyCard)) {
            $loyatyCard = $this->entityManager->getNewEntityByAttributSetName("loyalty_card");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($loyatyCard, $setter)) {
                $loyatyCard->$setter($value);
            }
        }

        $this->entityManager->saveEntityWithoutLog($loyatyCard);
        $this->entityManager->refreshEntity($loyatyCard);

        return $loyatyCard;
    }

    /**
     * @param $marketingRuleCode
     * @return |null
     */
    public function getLoyaltyRuleByCode($marketingRuleCode)
    {

        $et = $this->entityManager->getEntityTypeByCode("loyalty_earnings_configuration");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("ruleCode", "eq", $marketingRuleCode));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters);
    }

    /**
     * @param $loyaltyRuleCode
     * @param OrderEntity $order
     * @return mixed
     * @throws Exception
     */
    public function runLoyaltyRuleByCode($loyaltyRuleCode, OrderEntity $order)
    {
        $ret = array();

        if(empty($order->getLoyaltyCard())){
            throw new Exception("Missing loyalty card on order: {$order->getId()}");
        }

        /** @var LoyaltyEarningsConfigurationEntity $loyaltyRule */
        $loyaltyRule = $this->getLoyaltyRuleByCode($loyaltyRuleCode);

        if (empty($loyaltyRule)) {
            throw new Exception("Missing loyalty rule: {$loyaltyRuleCode}");
        }

        if (!$loyaltyRule->getIsActive()) {
            throw new Exception("Loyalty rule not active: {$loyaltyRuleCode}");
        }

        if (empty($loyaltyRule->getManagerCode())) {
            throw new Exception("Manager is empty for {$loyaltyRule->getName()}");
        } else {
            $manager = $this->getContainer()->get($loyaltyRule->getManagerCode());
            if (empty($manager)) {
                throw new Exception("Manager {$loyaltyRule->getManagerCode()} does not exist");
            } else {
                if (empty($loyaltyRule->getRuleMethod())) {
                    throw new Exception("Method is empty for {$loyaltyRule->getRuleMethod()}");
                } elseif (!EntityHelper::checkIfMethodExists($manager, $loyaltyRule->getRuleMethod())) {
                    throw new Exception("Manager {$loyaltyRule->getManagerCode()} does not have method {$loyaltyRule->getRuleMethod()}");
                }
            }
        }

        return $manager->{$loyaltyRule->getRuleMethod()}($loyaltyRule, $order);
    }

    /**
     * @param LoyaltyEarningsEntity|null $entity
     * @param $data
     * @return LoyaltyEarningsEntity|mixed|null
     */
    public function createUpdateLoyaltyEarnings(LoyaltyEarningsEntity $entity = null, $data){

        if(empty($entity)){
            $entity = $this->entityManager->getNewEntityByAttributSetName("loyalty_earnings");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($entity, $setter)) {
                $entity->$setter($value);
            }
        }

        $this->entityManager->saveEntity($entity);
        $this->entityManager->refreshEntity($entity);

        return $entity;
    }

    /**
     * @param LoyaltyEarningsOrderItemLinkEntity|null $loyaltyEarningsOrderItemLink
     * @param $data
     * @return mixed
     */
    public function createUpdateLoyaltyEarningsOrderItemLink(LoyaltyEarningsOrderItemLinkEntity $loyaltyEarningsOrderItemLink = null, $data){

        if(empty($entity)){
            $entity = $this->entityManager->getNewEntityByAttributSetName("loyalty_earnings_order_item_link");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($entity, $setter)) {
                $entity->$setter($value);
            }
        }

        $this->entityManager->saveEntity($entity);
        $this->entityManager->refreshEntity($entity);

        return $entity;
    }

    /**
     * @param SStoreEntity $store
     * @return false|int|mixed
     */
    public function getNextLoyaltyCardNumber(SStoreEntity $store)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->container->get("application_settings_manager");
        }

        $q = "SELECT MAX(CAST(card_number AS SIGNED)) as count FROM loyalty_card_entity;";
        $incrementId = $this->databaseContext->getSingleResult($q);

        if (empty($incrementId)) {
            $incrementId = $this->applicationSettingsManager->getApplicationSettingByCodeAndStore("loyalty_card_increment_start_from", $store);
            if (empty($incrementId)) {
                $incrementId = 1;
            }
        }

        $incrementId++;

        return $incrementId;
    }

    /**
     * @param LoyaltyCardEntity|null $entity
     * @param $data
     * @return LoyaltyCardEntity|mixed|null
     */
    public function createUpdateLoyaltyCard(LoyaltyCardEntity $entity = null, $data){

        if(empty($entity)){
            $entity = $this->entityManager->getNewEntityByAttributSetName("loyalty_card");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($entity, $setter)) {
                $entity->$setter($value);
            }
        }

        $this->entityManager->saveEntity($entity);
        $this->entityManager->refreshEntity($entity);

        return $entity;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getLoyaltyCardById($id){

        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(LoyaltyCardEntity::class);
        return $repository->find($id);
    }

    /**
     * @param $cardNumber
     * @return null
     */
    public function getLoyaltyCardByCardNumber($cardNumber){

        $et = $this->entityManager->getEntityTypeByCode("loyalty_card");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("cardNumber", "eq", $cardNumber));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters);
    }

    /**
     * @param null $additionalFilter
     * @return mixed
     */
    public function getFilteredLoyaltyCards($additionalFilter = null){

        $et = $this->entityManager->getEntityTypeByCode("loyalty_card");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if(!empty($additionalFilter)){
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters);
    }

    /**
     * @param OrderEntity $order
     * @return bool
     */
    public function runLoyaltyRules(OrderEntity $order)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM loyalty_earnings_configuration_entity WHERE entity_state_id = 1 and is_active = 1 and order_state_id = {$order->getOrderState()->getId()};";
        $data = $this->databaseContext->getAll($q);

        if (!empty($data)) {
            foreach ($data as $d) {
                try {
                    $this->runLoyaltyRuleByCode($d["rule_code"], $order);
                } catch (\Exception $e) {
                    /** @var ErrorLogManager $errorLogManager */
                    $errorLogManager = $this->getContainer()->get("error_log_manager");
                    $errorLogManager->logExceptionEvent(sprintf("Error running loyalty rule %s on order %s", $d["rule_code"], $order->getId()), $e, true);
                }
            }
        }

        return true;
    }

    /**
     * @param LoyaltySpendingsEntity|null $entity
     * @param $data
     * @return LoyaltySpendingsEntity|mixed|null
     */
    public function createUpdateLoyaltySpending(LoyaltySpendingsEntity $entity = null, $data){

        if(empty($entity)){
            $entity = $this->entityManager->getNewEntityByAttributSetName("loyalty_spendings");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($entity, $setter)) {
                $entity->$setter($value);
            }
        }

        $this->entityManager->saveEntity($entity);
        $this->entityManager->refreshEntity($entity);

        return $entity;
    }

    /**
     * @param ContactEntity $contact
     * @return ContactEntity
     */
    public function addLoyaltyCardToContact(ContactEntity $contact)
    {
        if (!empty($contact->getLoyaltyCard())) {
            return $contact;
        }

        $session = $this->getContainer()->get("session");

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        /** @var SStoreEntity $store */
        $store = $this->routeManager->getStoreById($storeId);

        $cardNumber = $this->getNextLoyaltyCardNumber($store);

        $data = array();
        $data["card_number"] = $cardNumber;
        $data["percent_discount"] = 0;
        $data["points"] = 0;
        $data["contact"] = $contact;

        /** @var LoyaltyCardEntity $loyaltyCard */
        $loyaltyCard = $this->createUpdateLoyaltyCard(null, $data);

        $contact->setLoyaltyCard($loyaltyCard);

        return $contact;
    }

    /**
     * @param LoyaltyEarningsConfigurationEntity $loyaltyRule
     * @param OrderEntity|null $order
     * @return bool
     * @throws Exception
     */
    public function loyaltyCalculation(LoyaltyEarningsConfigurationEntity $loyaltyRule, OrderEntity $order = null)
    {
        if (empty($order)) {
            throw new \Exception("Missing order for loyalty rule code {$loyaltyRule->getRuleCode()}");
        }

        if (empty($order->getLoyaltyCard())) {
            throw new \Exception("Missing loyalty card for order id {$order->getId()}");
        }

        $orderItems = $order->getOrderItems();
        if (!EntityHelper::isCountable($orderItems) || count($orderItems) == 0) {
            throw new \Exception("Empty order_items for loyalty rule code {$loyaltyRule->getRuleCode()} and order id {$order->getId()}");
        }

        $loyaltyEarningOrderItems = array();
        $points = ceil(floatval($order->getBasePriceItemsTotal())*floatval($loyaltyRule->getPointsMultiplier()));

        /** @var OrderItemEntity $orderItem */
        foreach ($orderItems as $orderItem) {
            $loyaltyEarningOrderItems[] = $orderItem;
        }

        /**
         * Add loyalty earning
         */
        if ($points > 0) {

            $data = array();
            $data["loyalty_card"] = $order->getLoyaltyCard();
            $data["date_received"] = new \DateTime();
            $data["points"] = intval($points);
            $data["based_on_rule"] = $loyaltyRule;
            $data["order"] = $order;

            /** @var LoyaltyEarningsEntity $loyaltyEarnings */
            $loyaltyEarnings = $this->createUpdateLoyaltyEarnings(null, $data);

            //TODO ovdje ce trebati sloziti filter proizvoda po rule
            //TODO izbjeci ako je vec dobiveno na loyalty order item link

            if (empty($loyaltyEarnings)) {
                throw new \Exception("Unable to create loyalty earnings for rule code {$loyaltyRule->getRuleCode()} and order id {$order->getId()}");
            }

            if (!empty($loyaltyEarningOrderItems)) {
                /** @var OrderItemEntity $orderItem */
                foreach ($loyaltyEarningOrderItems as $orderItem) {
                    $datal = array();
                    $datal["order_item"] = $orderItem;
                    $datal["loyalty_earning"] = $loyaltyEarnings;
                    $this->createUpdateLoyaltyEarningsOrderItemLink(null, $datal);
                }
            }
        }

        return true;
    }

    /**
     * @return \CrmBusinessBundle\Entity\LoyaltyCard|null
     */
    public function getCurrentLoyaltyCard()
    {
        if(empty($this->helperManager)){
            $this->helperManager = $this->container->get("helper_manager");
        }

        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();
        if(empty($coreUser)){
            return null;
        }

        /** @var ContactEntity $contact */
        $contact = $coreUser->getDefaultContact();
        if(empty($contact)){
            return null;
        }

        return $contact->getLoyaltyCard();
    }

    /**
     * @param LoyaltyCardEntity $loyaltyCard
     * @return integer|void
     */
    public function getAvailableLoyaltyPoints(LoyaltyCardEntity $loyaltyCard)
    {
        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM loyalty_earnings_entity WHERE loyalty_card_id = {$loyaltyCard->getId()} AND entity_state_id = 1 AND (points - IFNULL(points_spent, 0) > 0) AND (date_received BETWEEN (NOW() - INTERVAL 1 YEAR) AND NOW()) ORDER BY created ASC;";
        $data = $this->databaseContext->getAll($q);

        $ret = 0;

        if(empty($data)){
            return $ret;
        }

        foreach ($data as $d){
            $ret = $ret + ($d["points"] - $d["points_spent"]);
        }

        return $ret;
    }

    /**
     * @param $points
     * @return array
     */
    public function getAvailableLoyaltyDiscountLevels($points)
    {
        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT id, name, percent_discount, points FROM loyalty_discount_level_entity WHERE entity_state_id = 1;";
        $data = $this->databaseContext->getAll($q);

        if(empty($data)){
            return [];
        }

        $ret = [];

        foreach ($data as $d) {
            if ($points >= $d["points"]) {
                $ret[] = $d;
            }
        }

        return $ret;
    }

    /**
     * @param OrderEntity $order
     * @return bool
     * @throws Exception
     */
    public function subtractPointsOnOrder(OrderEntity $order)
    {
        // if customer is anonymous
        if (empty($order->getLoyaltyCard())) {
            return false;
        }

        /** @var LoyaltyCardEntity $loyaltyCard */
        $loyaltyCard = $order->getLoyaltyCard();

        $cardPoints = $loyaltyCard->getPoints();

        if ($cardPoints == 0) {
            return false;
        }

        // get available loyalty earnings. Exclude current order which is already added to db
        $q = "SELECT * FROM loyalty_earnings_entity WHERE loyalty_card_id = {$loyaltyCard->getId()} AND entity_state_id = 1 AND (points - IFNULL(points_spent, 0) > 0) AND (date_received BETWEEN (NOW() - INTERVAL 1 YEAR) AND NOW()) AND (order_id IS NULL  OR order_id <> {$order->getId()}) ORDER BY created ASC;";
        $loyaltyEarnings = $this->databaseContext->getAll($q);

        if(empty($loyaltyEarnings)){
            return false;
        }

        // check if card points are bigger than available points
        $availablePoints = 0;
        foreach ($loyaltyEarnings as $d){
            $availablePoints = $availablePoints + ($d["points"] - $d["points_spent"]);
        }

        if ($availablePoints < $cardPoints) {
            return false;
        }

        $this->calculateSubtraction($loyaltyCard, $loyaltyEarnings, $cardPoints, $order);

        // remove points and percentage from loyalty card
        $data = array();
        $data["percent_discount"] = 0;
        $data["points"] = 0;

        $this->createUpdateLoyaltyCard($loyaltyCard, $data);

        return true;
    }

    /**
     * @param int $subtractPoints
     * @return bool
     * @throws Exception
     */
    public function subtractPointsManually(LoyaltyCardEntity $loyaltyCard, int $subtractPoints)
    {
        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM loyalty_earnings_entity WHERE loyalty_card_id = {$loyaltyCard->getId()} AND entity_state_id = 1 AND (points - IFNULL(points_spent, 0) > 0) AND (date_received BETWEEN (NOW() - INTERVAL 1 YEAR) AND NOW()) ORDER BY created ASC;";
        $loyaltyEarnings = $this->databaseContext->getAll($q);

        if(empty($loyaltyEarnings)){
            return false;
        }

        // check if card points are bigger than available points
        $availablePoints = 0;
        foreach ($loyaltyEarnings as $d){
            $availablePoints = $availablePoints + ($d["points"] - $d["points_spent"]);
        }

        $subtractPoints = abs($subtractPoints);

        if ($availablePoints < $subtractPoints) {
            return false;
        }

        $this->calculateSubtraction($loyaltyCard, $loyaltyEarnings, $subtractPoints);

        return true;
    }

    /**
     * @param LoyaltyCardEntity $loyaltyCard
     * @param int $addPoints
     * @return bool
     * @throws Exception
     */
    public function addPointsManually(LoyaltyCardEntity $loyaltyCard, int $addPoints)
    {
        $configurationRule = $this->getLoyaltyEarningConfigurationById(1);

        $data = array();
        $data["loyalty_card"] = $loyaltyCard;
        $data["date_received"] = new \DateTime();
        $data["points"] = $addPoints;
        $data["based_on_rule"] = $configurationRule;

        /** @var LoyaltyEarningsEntity $loyaltyEarnings */
        $loyaltyEarnings = $this->createUpdateLoyaltyEarnings(null, $data);

        if (empty($loyaltyEarnings)) {
            throw new \Exception("Unable to create loyalty earnings");
        }

        return true;
    }

    /**
     * @param LoyaltyCardEntity $loyaltyCard
     * @param array $loyaltyEarnings
     * @param int $points
     * @param OrderEntity|null $order
     * @return void
     * @throws Exception
     */
    public function calculateSubtraction(LoyaltyCardEntity $loyaltyCard, array $loyaltyEarnings, int $points, OrderEntity $order = null)
    {
        // calculate spendings for each loyaltyEarningEntity and update in database
        foreach ($loyaltyEarnings as $loyaltyEarning) {
            $loyaltyEarningAvailablePoints = $loyaltyEarning["points"] - $loyaltyEarning["points_spent"];

            $earningData = array();
            $spendingData = array();

            // calculate
            if ($loyaltyEarningAvailablePoints <= $points) {
                $earningData["points_spent"] = $loyaltyEarning["points"];
                $points = $points - $loyaltyEarningAvailablePoints;
                $spendingData["points_spent"] = $loyaltyEarningAvailablePoints;
            } else {
                $earningData["points_spent"] = $loyaltyEarning["points_spent"] + $points;
                $spendingData["points_spent"] = $points;
                $points = 0;
            }

            // update loyaltyEarnings db
            $loyaltyEarningEntity = $this->getLoyaltyEarningById($loyaltyEarning["id"]);
            $updatedLoyaltyEarning = $this->createUpdateLoyaltyEarnings($loyaltyEarningEntity, $earningData);

            if (empty($updatedLoyaltyEarning)) {
                throw new \Exception("Unable to update loyalty earning entity for loyalty card id: {$loyaltyCard->getId()}");
            }

            // add new loyaltySpending to db
            $spendingData["loyalty_card"] = $loyaltyCard;
            $spendingData["loyalty_earning"] = $updatedLoyaltyEarning;
            if ($order) {
                $spendingData["order"] = $order;
            }
            $createdLoyaltySpending = $this->createUpdateLoyaltySpending(null, $spendingData);

            if (empty($createdLoyaltySpending)) {
                throw new \Exception("Unable to create loyalty spending entity for loyalty card id: {$loyaltyCard->getId()}");
            }

            if ($points <= 0) {
                break;
            }
        }
    }
}
