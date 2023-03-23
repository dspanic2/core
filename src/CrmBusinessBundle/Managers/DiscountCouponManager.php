<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\CacheManager;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\AccountGroupEntity;
use CrmBusinessBundle\Entity\DiscountCouponAccountGroupLinkEntity;
use CrmBusinessBundle\Entity\DiscountCouponEntity;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\ProductEntity;
use Doctrine\Common\Util\Inflector;
use ScommerceBusinessBundle\Managers\FrontProductsRulesManager;
use Symfony\Component\EventDispatcher\EventDispatcher;

class DiscountCouponManager extends AbstractBaseManager
{
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /** @var FrontProductsRulesManager $frontProductsRulesManager */
    protected $frontProductsRulesManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @param $discountCouponName
     * @return DiscountCouponEntity
     * @throws \Exception
     */
    public function getDiscountCouponByCode($discountCouponName)
    {

        $et = $this->entityManager->getEntityTypeByCode("discount_coupon");

        $now = new \DateTime("now");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("dateValidFrom", "le", $now->format("Y-m-d H:i:s")));
        $compositeFilter->addFilter(new SearchFilter("dateValidTo", "gt", $now->format("Y-m-d H:i:s")));
        $compositeFilter->addFilter(new SearchFilter("couponCode", "eq", $discountCouponName));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var DiscountCouponEntity $discountCoupon */
        $discountCoupon = $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters);

        return $discountCoupon;
    }

    /**
     * @param $discountCouponTemplateCode
     * @return DiscountCouponEntity
     */
    public function getDiscountCouponTemplateByCode($discountCouponTemplateCode)
    {

        $et = $this->entityManager->getEntityTypeByCode("discount_coupon");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("templateCode", "eq", $discountCouponTemplateCode));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var DiscountCouponEntity $discountCoupon */
        $discountCoupon = $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters);

        return $discountCoupon;
    }

    /**
     * @param DiscountCouponEntity $discountCoupon
     * @param AccountEntity $account
     * @return int
     */
    public function getNumberOfCouponsUsed(DiscountCouponEntity $discountCoupon, AccountEntity $account)
    {

        $et = $this->entityManager->getEntityTypeByCode("order");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("discountCoupon", "eq", $discountCoupon->getId()));
        $compositeFilter->addFilter(new SearchFilter("account", "eq", $account->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $orders = $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters);

        return count($orders);
    }

    /**
     * @param $templateCode
     * @return bool|DiscountCouponEntity
     * @throws \Exception
     */
    public function generateCouponFromTemplate($templateCode)
    {

        /** @var DiscountCouponEntity $discountCouponTemplate */
        $discountCouponTemplate = $this->getDiscountCouponTemplateByCode($templateCode);

        if (empty($discountCouponTemplate)) {
            $this->errorLogManager->logErrorEvent("Non existing discount coupon template", "System has attempted to generate new discount coupon from non existing template code '{$templateCode}'", true, null);
            return false;
        }

        if (!$discountCouponTemplate->getIsActive()) {
            $this->errorLogManager->logErrorEvent("Discount coupon template is not active", "System has attempted to generate new discount coupon from non existing template code '{$templateCode}'", true, null);
            return false;
        }

        $now = new \DateTime();
        if((!empty($discountCouponTemplate->getDateValidFrom()) && $discountCouponTemplate->getDateValidFrom() > $now) || (!empty($discountCouponTemplate->getDateValidTo()) && $discountCouponTemplate->getDateValidTo() < $now)){
            $this->errorLogManager->logErrorEvent("Discount coupon template expired", "System has attempted to generate new discount coupon from non existing template code '{$templateCode}'", true, null);
            return false;
        }

        return $this->generateCouponFromTemplateEntity($discountCouponTemplate);
    }

    /**
     * @param DiscountCouponEntity $discountCouponTemplate
     * @return DiscountCouponEntity
     * @throws \Exception
     */
    public function generateCouponFromTemplateEntity(DiscountCouponEntity $discountCouponTemplate)
    {

        $interval = $discountCouponTemplate->getDateValidFrom()->diff($discountCouponTemplate->getDateValidTo());

        $from = new \DateTime();
        $to = new \DateTime();
        $to->add($interval);

        /** @var DiscountCouponEntity $discountCoupon */
        $discountCoupon = $this->entityManager->cloneEntity($discountCouponTemplate, "discount_coupon", array(), true);

        $discountCoupon->setIsTemplate(0);
        $discountCoupon->setCouponCode(StringHelper::generateRandomString(10, true, false));
        $discountCoupon->setDateValidFrom($from);
        $discountCoupon->setDateValidTo($to);
        $discountCoupon->setTemplate($discountCouponTemplate);

        $this->entityManager->saveEntity($discountCoupon);
        $this->entityManager->refreshEntity($discountCoupon);

        /**
         * Add related account groups if exist
         */
        if (EntityHelper::isCountable($discountCouponTemplate->getAccountGroups()) && count($discountCouponTemplate->getAccountGroups()) > 0) {

            $saveArray = array();

            /** @var AccountGroupEntity $accountGroup */
            foreach ($discountCouponTemplate->getAccountGroups() as $accountGroup) {
                /** @var DiscountCouponAccountGroupLinkEntity $tmp */
                $tmp = $this->entityManager->getNewEntityByAttributSetName("discount_coupon_account_group_link");

                $tmp->setAccountGroup($accountGroup);
                $tmp->setDiscountCoupon($discountCoupon);

                $saveArray[] = $tmp;
            }

            if (!empty($saveArray)) {
                $this->entityManager->saveArrayEntities($saveArray, $tmp->getEntityType());
            }
        }

        return $discountCoupon;
    }

    /**
     * @param DiscountCouponEntity $discountCoupon
     * @return bool
     */
    public function setCouponUsed(DiscountCouponEntity $discountCoupon)
    {
        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        $this->crmProcessManager->setDiscountCouponUsed($discountCoupon);

        return true;
    }

    /**
     * @param DiscountCouponEntity $discountCoupon
     * @param $data
     * @return DiscountCouponEntity
     */
    public function updateDiscountCoupon(DiscountCouponEntity $discountCoupon, $data)
    {

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($discountCoupon, $setter)) {
                $discountCoupon->$setter($value);
            }
        }

        $this->entityManager->saveEntity($discountCoupon);
        $this->entityManager->refreshEntity($discountCoupon);

        return $discountCoupon;
    }

    /**
     * @param null $additionalCompositeFilter
     * @param null $sortFilters
     * @return mixed
     */
    public function getFilteredDiscountCoupons($additionalCompositeFilter = null, $sortFilters = null)
    {
        $et = $this->entityManager->getEntityTypeByCode("discount_coupon");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalCompositeFilter)) {
            $compositeFilters->addCompositeFilter($additionalCompositeFilter);
        }

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($et, $compositeFilters, $sortFilters);
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getCouponsForProduct(ProductEntity $product, $compositeFilter = null)
    {

        $now = new \DateTime("now");

        if (empty($compositeFilter)) {
            $compositeFilter = new CompositeFilter();
            $compositeFilter->setConnector("and");
        }
        $compositeFilter->addFilter(new SearchFilter("isActive", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("isTemplate", "eq", 0));
        $compositeFilter->addFilter(new SearchFilter("dateValidFrom", "le", $now->format("Y-m-d H:i:s")));
        $compositeFilter->addFilter(new SearchFilter("dateValidTo", "gt", $now->format("Y-m-d H:i:s")));

        $sortFilters = new SortFilterCollection();
        if (!empty($sortFilter)) {
            $sortFilters->addSortFilter($sortFilter);
        } else {
            $sortFilters->addSortFilter(new SortFilter("created", "desc"));
        }

        $coupons = $this->getFilteredDiscountCoupons($compositeFilter, $sortFilters);

        $ret = array();

        if (EntityHelper::isCountable($coupons) && count($coupons)) {

            if (empty($this->crmProcessManager)) {
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            /** @var DiscountCouponEntity $coupon */
            foreach ($coupons as $coupon) {
                $discountPercent = $this->crmProcessManager->getApplicableDiscountCouponPercentForProduct($coupon, $product);
                if (floatval($discountPercent) > 0) {
                    $ret[] = array("coupon" => $coupon, "discount_percent" => $discountPercent);
                }

                if (!empty($ret)) {
                    usort($ret, function ($a, $b) {
                        return $a['discount_percent'] <=> $b['discount_percent'];
                    });
                }
            }
        }

        return $ret;
    }

    /**
     * @param DiscountCouponEntity $coupon
     * @return void
     */
    public function deactivateCoupon(DiscountCouponEntity $coupon)
    {
        if (empty($this->frontProductsRulesManager)) {
            $this->frontProductsRulesManager = $this->container->get("front_product_rules_manager");
        }
        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }
        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        if($coupon->getShowOnProduct()){
            $rules = $coupon->getRules();
            $productIds = $this->frontProductsRulesManager->getProductIdsForRule($rules);
            if (!empty($productIds)) {
                $tags = array_map(function ($value) {
                    return "product_{$value}";
                }, $productIds);
                $this->cacheManager->invalidateCacheByTags($tags);
            }
        }
        $coupon->setIsActive(false);
        $this->entityManager->saveEntity($coupon);
    }
}
