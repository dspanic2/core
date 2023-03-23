<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Context\AttributeSetContext;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ErrorLogManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Events\OrderCreatedEvent;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\DiscountCouponManager;
use CrmBusinessBundle\Managers\OrderManager;
use JMS\Serializer\Tests\Fixtures\Order;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OrderListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;
    /** @var CacheManager $cacheManager */
    protected $cacheManager;
    /** @var AttributeSetContext $attributeSetContext */
    protected $attributeSetContext;
    /** @var DiscountCouponManager $discountCouponManager */
    protected $discountCouponManager;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;

    protected $container;
    protected $logger;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function onOrderCreated(OrderCreatedEvent $event)
    {
        /** @var OrderEntity $order */
        $order = $event->getOrder();
        $order->setTotalLeftToPay($order->getPriceTotal());
        $order->setCreated(new \DateTime());
        $order->setModified(new \DateTime());
        $order->setQuoteDate(new \DateTime());

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $this->entityManager->saveEntityWithoutLog($order);
        $this->entityManager->refreshEntity($order);

        /**
         * Set order state history
         */
        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        $this->orderManager->setOrderStateHistory($order);

        /**
         * Convert lead to account
         */
        /** @var AccountEntity $account */
        $account = $order->getAccount();
        if (!empty($account) && $account->getAttributeSet()->getId() == CrmConstants::LEAD_ATTRIBUTE_SET_ID) {
            if (empty($this->attributeSetContext)) {
                $this->attributeSetContext = $this->container->get("attribute_set_context");
            }

            $acc_attr_set = $this->attributeSetContext->getById(CrmConstants::ACCOUNT_ATTRIBUTE_SET_ID);

            $account->setAttributeSet($acc_attr_set);
            $this->entityManager->saveEntityWithoutLog($account);
        }

        if (empty($this->crmProcessManager)) {
            $this->crmProcessManager = $this->container->get("crm_process_manager");
        }

        try{
            $this->crmProcessManager->afterOrderCreated($order);
        }
        catch (\Exception $e){
            if (empty($this->errorLogManager)) {
                $this->errorLogManager = $this->container->get("error_log_manager");
            }
            $this->errorLogManager->logExceptionEvent("After order created",$e, true);
        }


        $this->entityManager->refreshEntity($order);

        /**
         * Set coupon used
         */
        if(!empty($order->getDiscountCoupon())){
            if(empty($this->discountCouponManager)){
                $this->discountCouponManager = $this->container->get("discount_coupon_manager");
            }

            $this->discountCouponManager->setCouponUsed($order->getDiscountCoupon());
        }

        return true;
    }

    /**
     * @param EntityUpdatedEvent $event
     */
    public function onOrderUpdated(EntityUpdatedEvent $event)
    {

        /** @var OrderEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "order") {

            if (empty($this->orderManager)) {
                $this->orderManager = $this->container->get("order_manager");
            }

            /**
             * Set order state history
             */
            $this->orderManager->setOrderStateHistory($entity);
        }
    }
}
