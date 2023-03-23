<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreSetUpdatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\CacheManager;
use CrmBusinessBundle\Entity\DiscountCouponEntity;
use ScommerceBusinessBundle\Managers\FrontProductsRulesManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DiscountCouponListener implements ContainerAwareInterface
{
    protected $container;
    /** @var FrontProductsRulesManager $frontProductsRulesManager */
    protected $frontProductsRulesManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @throws \Exception
     */
    public function onDiscountCouponPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var DiscountCouponEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "discount_coupon") {

            if ($entity->getIsTemplate()) {

                $entity->setCouponCode(null);
                $entity->setTemplateCode(StringHelper::convertStringToCode(StringHelper::sanitizeFileName($entity->getName())));
            } else {
                $entity->setTemplateCode(null);
            }

            if ($entity->getIsFixed()) {
                $entity->setDiscountPercent(0);
            } else {
                $entity->setFixedDiscount(0);
            }

            if (!empty($entity->getRules())) {
                if (empty($this->productAttributeFilterRulesManager)) {
                    $this->productAttributeFilterRulesManager = $this->container->get("product_attribute_filter_rules_manager");
                }

                $entity = $this->productAttributeFilterRulesManager->validateRule($entity);
            }
        }
    }

    /**
     * @param EntityPreSetUpdatedEvent $event
     * @throws \Exception
     */
    public function onDiscountCouponPreSetUpdated(EntityPreSetUpdatedEvent $event)
    {
        /** @var DiscountCouponEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "discount_coupon") {

            if (!$entity->getIsTemplate()) {
                $data = $event->getData();
                /**
                 * Ako je kupon trenutno aktivan i ako je se postavi da vise nije aktivan
                 * Dodaj mu flag da treba rebuildat product cache
                 */
                if(isset($data["is_active"]) && $data["is_active"] == 0 && $entity->getIsActive()){
                    $data["rebuild_product_cache"] = 1;

                    $event->setData($data);
                }
            }
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onDiscountCouponPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var DiscountCouponEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "discount_coupon") {
            if ($entity->getIsTemplate()) {

                $entity->setCouponCode(null);
                if (empty($entity->getTemplateCode())) {
                    $entity->setTemplateCode(StringHelper::convertStringToCode(StringHelper::sanitizeFileName($entity->getName())));
                }
            } else {
                $entity->setTemplateCode(null);
            }

            if ($entity->getIsFixed()) {
                $entity->setDiscountPercent(0);
            } else {
                $entity->setFixedDiscount(0);
            }

            if (!empty($entity->getRules())) {
                if (empty($this->productAttributeFilterRulesManager)) {
                    $this->productAttributeFilterRulesManager = $this->container->get("product_attribute_filter_rules_manager");
                }

                $this->productAttributeFilterRulesManager->validateRule($entity);
            }
        }
    }

    /**
     * @param EntityUpdatedEvent $event
     * @throws \Exception
     */
    public function onDiscountCouponUpdated(EntityUpdatedEvent $event)
    {
        /** @var DiscountCouponEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "discount_coupon") {
            $previousValues = $event->getPreviousValuesArray();

            if(isset($previousValues["rebuild_product_cache"]) && $previousValues["rebuild_product_cache"]){
                if (empty($this->cacheManager)) {
                    $this->cacheManager = $this->container->get("cache_manager");
                }
                if (empty($this->frontProductsRulesManager)) {
                    $this->frontProductsRulesManager = $this->container->get("front_product_rules_manager");
                }

                $productIds = $this->frontProductsRulesManager->getProductIdsForRule($entity->getRules());
                if (!empty($productIds)) {
                    $tags = array_map(function ($value) {
                        return "product_{$value}";
                    }, $productIds);
                    $this->cacheManager->invalidateCacheByTags($tags);
                }
            }
        }
    }
}
