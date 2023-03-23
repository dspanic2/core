<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\DiscountCouponEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use ScommerceBusinessBundle\Abstracts\AbstractScommerceManager;
use ScommerceBusinessBundle\Entity\BlockedIpsEntity;
use ScommerceBusinessBundle\Entity\SProductAttributeConfigurationEntity;
use ScommerceBusinessBundle\Entity\SProductAttributesLinkEntity;

class ScommerceHelperManager extends AbstractScommerceManager
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var DefaultScommerceManager $scommerceManager */
    protected $scommerceManager;
    /** @var AttributeSet $productProductGroupLinkAs */
    protected $productProductGroupLinkAs;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $subgroupsRaw
     * @param string $attributeCode
     * @return array
     */
    public function prepareKeyLetterTwigOutput($subgroupsRaw, $attributeCode = "name")
    {
        $getter = EntityHelper::makeGetter($attributeCode);
        $session = $this->getContainer()->get("session");
        $storeId = ($session->get("current_store_id"));

        $subgroups = [];
        foreach ($subgroupsRaw as $subgroup) {

//            $isJsonAttribute = json_decode($subgroup->$getter(), true);
            if (in_array(gettype($subgroup->$getter()), ["object", "array"])) {
                $string = ucfirst($subgroup->$getter()[$storeId]);
                $key = mb_substr($string, 0, 1, "UTF-8");
                if (!isset($subgroups[$key])) {
                    $subgroups[$key] = [];
                }
                $subgroups[$key][] = $subgroup;

            } else {

                $key = mb_substr($subgroup->$getter(), 0, 1, "UTF-8");
                if (!isset($subgroups[$key])) {
                    $subgroups[$key] = [];
                }
                $subgroups[$key][] = $subgroup;
            }


//            $key = mb_substr($subgroup->$getter(), 0, 1, "UTF-8");
//            if (!isset($subgroups[$key])) {
//                $subgroups[$key] = [];
//            }
//            $subgroups[$key][] = $subgroup;
        }
        return $subgroups;
    }

    /**
     * @param ProductEntity $product
     * @param $attributeCode
     * @return bool
     */
    public function updateProductAttributeValue(ProductEntity $product, $attributeCode, $newAttributeValue)
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $entityTypeSProductAttributeConfiguration = $this->entityManager->getEntityTypeByCode("s_product_attribute_configuration");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->addFilter(new SearchFilter("filterKey", "eq", $attributeCode));
        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var SProductAttributeConfigurationEntity $sProductAttributeConfiguration */
        $sProductAttributeConfiguration = $this->entityManager->getEntityByEntityTypeAndFilter($entityTypeSProductAttributeConfiguration, $compositeFilters);

        if (empty($sProductAttributeConfiguration)) {
            return true;
        }

        $q = "SELECT * FROM s_product_attributes_link_entity WHERE s_product_attribute_configuration_id = {$sProductAttributeConfiguration->getId()} and product_id = {$product->getId()};";
        $attrValue = $this->databaseContext->getAll($q);

        if (!empty($attrValue)) {
            $attrValue = $attrValue[0];
        }

        if (empty($attrValue) && !empty($newAttributeValue)) {
            /** @var SProductAttributesLinkEntity $attrValue */
            $attrValue = $this->entityManager->getNewEntityByAttributSetName("s_product_attributes_link");

            $attrValue->setSProductAttributeConfiguration($sProductAttributeConfiguration);
            $attrValue->setProduct($product);
            $attrValue->setAttributeValue($newAttributeValue);

            $this->entityManager->saveEntityWithoutLog($attrValue);
        } elseif (!empty($attrValue) && empty($brandValue)) {
            $q = "DELETE FROM s_product_attributes_link_entity WHERE id = {$attrValue["id"]}";
            $this->databaseContext->executeNonQuery($q);
        } elseif ($attrValue["attribute_value"] != $newAttributeValue) {
            $q = "UPDATE s_product_attributes_link_entity SET attribute_value = '{$newAttributeValue}' WHERE id = {$attrValue["id"]}";
            $this->databaseContext->executeNonQuery($q);
        }

        return true;
    }

    /**
     * @return false
     */
    public function getSfrontBlockArray()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT * FROM s_front_block_entity;";

        $data = $this->databaseContext->getAll($q);

        return $data;
    }

    /**
     * @return false
     */
    public function getStemplateTypeArray()
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->getContainer()->get("database_context");
        }

        $q = "SELECT * FROM s_template_type_entity;";

        $data = $this->databaseContext->getAll($q);

        return $data;
    }

    /**
     * @param $code
     * @return null
     */
    public function getStemplateByCode($code)
    {
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $code));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($this->entityManager->getEntityTypeByCode("s_template_type"), $compositeFilters);
    }

    /**
     * @return bool
     */
    public function cleanUnusedFrontBlocks()
    {
        $sFrontBlockArray = $this->getSfrontBlockArray();
        $sTemplateTypeArray = $this->getStemplateTypeArray();

        $q = NULL;
        $deleteArray = array();

        if (!empty($sFrontBlockArray)) {
            foreach ($sFrontBlockArray as $sFrontBlock) {
                $found = false;
                foreach ($sFrontBlockArray as $sFrontBlockCmp) {
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

                foreach ($sTemplateTypeArray as $sTemplateType) {
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

                if (!$found) {
                    $deleteArray["s_front_block"][$sFrontBlock["id"]] = $sFrontBlock;
                }
            }
        }

        if (!empty($deleteArray)) {
            foreach ($deleteArray as $key => $tableData) {
                foreach ($tableData as $id => $entityData) {
                    if (isset($updateArray[$key]) && isset($updateArray[$key][$id])) {
                        unset($updateArray[$key][$id]);
                    }
                    $q .= "DELETE FROM {$key} WHERE id = '{$id}';\n";
                }
            }
        }

        echo $q;

        return true;
    }

    /**
     * @param $ipAddress
     * @param int $level
     * @param string $reason
     * @return bool
     * @throws \Exception
     */
    public function addIpToBlockedIps($ipAddress, $level = 10, $reason = "")
    {

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        /** @var BlockedIpsEntity $blockedIp */
        $blockedIp = $this->entityManager->getNewEntityByAttributSetName("blocked_ips");

        $blockedIp->setIpAddress($ipAddress);
        $blockedIp->setDateBlocked(new \DateTime());
        $blockedIp->setReason($reason);
        $blockedIp->setLevel($level);

        $this->entityManager->saveEntityWithoutLog($blockedIp);

        $this->reloadBlockedIpsCache();

        return true;
    }

    /**
     * @param $ipAddress
     * @return bool
     */
    public function checkIfIpInBlockedIps($ipAddress)
    {

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manger");
        }

        $blockedIps = $this->cacheManager->getCacheItem("blocked_ips");
        $blockedIpsLoaded = $this->cacheManager->getCacheItem("blocked_ips_loaded");
        if (empty($blockedIpsLoaded)) {
            $this->cacheManager->setCacheItem("blocked_ips_loaded", true);
            $blockedIps = $this->reloadBlockedIpsCache();
        }

        if (!empty($blockedIps)) {
            if (in_array($ipAddress, $blockedIps)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array|bool|mixed[]
     */
    public function reloadBlockedIpsCache()
    {

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM blocked_ips_entity WHERE entity_state_id = 1;";
        $blockedIps = $this->databaseContext->getAll($q);

        if (!empty($blockedIps)) {
            $blockedIps = array_column($blockedIps, "ip_address");

            $this->cacheManager->setCacheItem("blocked_ips", $blockedIps);
        }

        return $blockedIps;
    }

    /**
     * @return bool
     */
    public function assignParentProductGroups($ids = [])
    {
        if (empty($this->scommerceManager)) {
            $this->scommerceManager = $this->container->get("scommerce_manager");
        }

        $products = $this->scommerceManager->assignParentGroupsForProducts($ids);

        if (EntityHelper::isCountable($products) && count($products) > 0) {
            $inserts = "";
            /** @var ProductEntity $product */
            foreach ($products as $product) {
                $groups = $product->getProductGroups();
                if (EntityHelper::isCountable($groups) && count($groups) > 0) {
                    /** @var ProductGroupEntity $group */
                    foreach ($groups as $group) {
                        $inserts .= $this->productAssignParentGroup($product, $group);
                    }
                }
            }

            if (!empty($inserts)) {
                if (empty($this->databaseContext)) {
                    $this->databaseContext = $this->container->get("database_context");
                }
                $this->databaseContext->executeNonQuery($inserts);
            }
        }

        return true;
    }

    /**
     * @param ProductEntity $product
     * @param ProductGroupEntity $group
     * @return string
     */
    private function productAssignParentGroup(ProductEntity $product, ProductGroupEntity $group)
    {
        if (empty($this->productProductGroupLinkAs)) {
            $this->productProductGroupLinkAs = $this->entityManager->getAttributeSetByCode("product_product_group_link");
        }

        $insert = "";
        /** @var ProductGroupEntity $parentGroup */
        $parentGroup = $group->getProductGroup();
        if (!empty($parentGroup)) {

            $values = [
                "entity_type_id" => $this->productProductGroupLinkAs->getEntityTypeId(),
                "attribute_set_id" => $this->productProductGroupLinkAs->getId(),
                "created" => "now()",
                "modified" => "now()",
                "entity_state_id" => "1",
                "product_id" => "{$product->getId()}",
                "product_group_id" => "{$parentGroup->getId()}",
                "ord" => "100",
            ];
            $insert .= "INSERT IGNORE INTO product_product_group_link_entity (" . implode(",", array_keys($values)) . ") VALUES (" . implode(",", $values) . ");";

            $insert .= $this->productAssignParentGroup($product, $parentGroup);
        }

        return $insert;
    }

    /**
     * @param $data
     * @return array|mixed
     */
    public function getDashboardFilteredOrders($data)
    {

        if (!isset($data["account"]) || empty($data["account"])) {
            return array();
        }

        $session = $this->getContainer()->get("session");
        $storeId = ($session->get("current_store_id"));

        $entityType = $this->entityManager->getEntityTypeByCode("order");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("store", "eq", $storeId));
        $compositeFilter->addFilter(new SearchFilter("account", "eq", $data["account"]));

        if (isset($data["increment_id"]) && !empty($data["increment_id"])) {
            $compositeFilter->addFilter(new SearchFilter("incrementId", "ew", $data["increment_id"]));
        }
        if (isset($data["order_state"]) && !empty($data["order_state"]) && $data["order_state"] !== 0) {
            $compositeFilter->addFilter(new SearchFilter("orderState.id", "eq", $data["order_state"]));
        }
        if (isset($data["created_from"]) && !empty($data["created_from"])) {
            $from_date = new \DateTime($data["created_from"]);
            $compositeFilter->addFilter(new SearchFilter("created", "gt", $from_date->format("Y-m-d H:i:s")));
        }
        if (isset($data["created_to"]) && !empty($data["created_to"])) {
            $to_date = new \DateTime($data["created_to"]);
            $compositeFilter->addFilter(new SearchFilter("created", "lt", $to_date->format("Y-m-d H:i:s")));
        }
        if (isset($data["contact"]) && !empty($data["contact"])) {
            $compositeFilter->addFilter(new SearchFilter("contact.fullName", "bw", $data["contact"]));
        }

        if (isset($data["shipping_address"]) && !empty($data["shipping_address"])) {
            $compositeFilter->addFilter(new SearchFilter("deliveryType.isDelivery", "eq", 1));
            $compositeSubFilter = new CompositeFilter();
            $compositeSubFilter->setConnector("or");
            $compositeSubFilter->addFilter(new SearchFilter("accountShippingAddress.street", "bw", $data["shipping_address"]));
            $compositeSubFilter->addFilter(new SearchFilter("accountShippingAddress.city.name", "bw", $data["shipping_address"]));
            $compositeSubFilter->addFilter(new SearchFilter("accountShippingAddress.city.postalCode", "bw", $data["shipping_address"]));
            $compositeSubFilter->addFilter(new SearchFilter("accountShippingAddress.name", "bw", $data["shipping_address"]));
            $compositeFilter->addFilter($compositeSubFilter);
        }

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);



        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("created", "desc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters, $sortFilters);
    }

    /**
     * @param ProductEntity $product
     * @param $hours
     * @return false|mixed
     */
    public function getProductNumberOfOrdersInHours(ProductEntity $product, $hours)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $query = "SELECT COUNT( o.id ) AS number_of_orders FROM order_entity AS o JOIN order_item_entity AS oi ON o.id = oi.order_id WHERE o.entity_state_id = 1 AND oi.entity_state_id = 1 AND oi.product_id = {$product->getId()} AND o.created > DATE_SUB(NOW(), INTERVAL {$hours} HOUR );";
        $res = $this->databaseContext->executeQuery($query);

        return $res[0]["number_of_orders"] ?? 0;
    }

    /**
     * @param $entity
     * @param $minutes
     * @return false|mixed
     */
    public function getNumberOfUsersOnPageInLastMinutes($entity, $minutes)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $query = "SELECT COUNT(*) AS number_of_users FROM shape_track WHERE event_name = 'page_viewed' AND page_type = '{$entity->getEntityType()->getEntityTypeCode()}' AND page_id = {$entity->getId()} AND event_time > DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE ) GROUP BY session_id;";
        $res = $this->databaseContext->executeQuery($query);

        return $res[0]["number_of_users"] ?? 0;
    }

    /**
     * @param $entityTypeCode
     * @param $attributeCode
     * @param $storeId
     */
    public function populateEntityStoreAttributeValue($entityTypeCode, $attributeCode, $storeId)
    {
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $entityType = $this->entityManager->getEntityTypeByCode($entityTypeCode);
        $entities = $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);

        if (!empty($entities)) {
            $getter = EntityHelper::makeGetter($attributeCode);
            $setter = EntityHelper::makeSetter($attributeCode);
            foreach ($entities as $entity) {
                if (method_exists($entity, $getter) && method_exists($entity, $setter)) {
                    $value = $entity->{$getter}();
                    if (!isset($value[$storeId]) && isset($value[$_ENV["DEFAULT_STORE_ID"]])) {
                        $value[$storeId] = $value[$_ENV["DEFAULT_STORE_ID"]];
                        $entity->{$setter}($value);
                        $this->entityManager->saveEntityWithoutLog($entity);
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getBankAccounts()
    {
        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();

        /** @var ContactEntity $contact */
        $contact = $coreUser->getDefaultContact();
        if (empty($contact)) {
            return [];
        }

        /** @var AccountEntity $account */
        $account = $contact->getAccount();
        if (empty($account)) {
            return [];
        }

        return $account->getBankAccounts();
    }

    /**
     * @return array
     */
    public function getAddresses()
    {
        /** @var CoreUserEntity $coreUser */
        $coreUser = $this->helperManager->getCurrentCoreUser();

        /** @var ContactEntity $contact */
        $contact = $coreUser->getDefaultContact();
        if (empty($contact)) {
            return [];
        }

        /** @var AccountEntity $account */
        $account = $contact->getAccount();
        if (empty($account)) {
            return [];
        }

        return $account->getAddresses();
    }

    /**
     * @param $date
     * @param int[] $nonWorkingDays
     */
    public function getNextWorkDay($date, $nonWorkingDays = array(6, 7))
    {

        if (in_array($date->format("w"), $nonWorkingDays)) {
            $date->add(new \DateInterval("P1D"));

            return $this->getNextWorkDay($date, $nonWorkingDays);
        } else {
            $et = $this->entityManager->getEntityTypeByCode("holidays");

            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
            $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
            $compositeFilter->addFilter(new SearchFilter("date", "eq", $date->format("Y-m-d")));

            $compositeFilters = new CompositeFilterCollection();
            $compositeFilters->addCompositeFilter($compositeFilter);

            $exists = $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters);

            if (!empty($exists)) {
                $date->add(new \DateInterval("P1D"));

                return $this->getNextWorkDay($date, $nonWorkingDays);
            }
        }

        return $date;
    }

    /**
     * @param CompositeFilter $preparedCompositeFilter
     * @return bool
     */
    public function cacheWarmupListItems(CompositeFilter $preparedCompositeFilter = null)
    {
        $et = $this->entityManager->getEntityTypeByCode("product");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);
        if (!empty($preparedCompositeFilter)) {
            $compositeFilters->addCompositeFilter($compositeFilter);
        }

        $products = $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters);

        if (empty($products)) {
            return false;
        }

        $session = $this->getContainer()->get("session");

        if (empty($this->templateManager)) {
            $this->templateManager = $this->container->get("template_manager");
        }
        if (empty($this->twig)) {
            $this->twig = $this->container->get("templating");
        }

        /** @var ProductEntity $product */
        foreach ($products as $product) {
            echo "Warming cache for product {$product->getId()}\n";
            $this->twig->render($this->templateManager->getTemplatePathByBundle("Components/Product:product_list_item.html.twig", $session->get("current_website_id")), array('product' => $product, 'force_rebuild' => true));
        }

        return true;
    }
}
