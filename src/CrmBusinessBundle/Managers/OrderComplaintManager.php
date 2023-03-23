<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Entity\OrderComplaintEntity;
use CrmBusinessBundle\Entity\OrderComplaintItemEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use CrmBusinessBundle\Entity\OrderItemEntity;
use CrmBusinessBundle\Entity\OrderStateEntity;

class OrderComplaintManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var OrderManager $orderManager */
    protected $orderManager;
    protected $databaseContext;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @param OrderEntity $order
     * @param $data
     * @return void
     * @throws \Exception
     */
    public function createOrderComplaint(OrderEntity $order, $data)
    {
        /** @var OrderComplaintEntity $orderComplaint */
        $orderComplaint = $this->entityManager->getNewEntityByAttributSetName("order_complaint");
        $orderComplaint->setOrder($order);
        $this->entityManager->saveEntity($orderComplaint);

        if (empty($this->orderManager)) {
            $this->orderManager = $this->container->get("order_manager");
        }

        foreach ($data as $orderItemId => $value) {
            $orderItem = $this->orderManager->getOrderItemById($orderItemId);
            if (!empty($orderItem)) {
                /** @var OrderComplaintItemEntity $orderComplaintItem */
                $orderComplaintItem = $this->entityManager->getNewEntityByAttributSetName("order_complaint_item");
                $orderComplaintItem->setOrderItem($orderItem);
                $orderComplaintItem->setOrderComplaint($orderComplaint);
                $orderComplaintItem->setNote($value);
                $this->entityManager->saveEntity($orderComplaintItem);
            }
        }

        $this->entityManager->refreshEntity($orderComplaint);

        if ($_ENV["ENABLE_OUTGOING_EMAIL"]) {
            /** @var EmailTemplateManager $emailTemplateManager */
            $emailTemplateManager = $this->container->get('email_template_manager');
            /** @var EmailTemplateEntity $template */
            $template = $emailTemplateManager->getEmailTemplateByCode("order_complaint");

            if (empty($template)) {
                throw new \Exception("Missing template order_complaint");
            }

            $emailTemplateManager->sendEmail(
                "order_complaint",
                $orderComplaint,
                $order->getStore(),
                array(
                    'email' => $_ENV["ORDER_COMPLAINT_RECIPIENT"],
                    'name' => $_ENV["ORDER_COMPLAINT_RECIPIENT"],
                ),
            );
        }
    }

    /**
     * @param OrderEntity $order
     * @return null
     */
    public function getOrderComplaintByOrder(OrderEntity $order)
    {
        $et = $this->entityManager->getEntityTypeByCode("order_complaint");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("order", "eq", $order->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters);
    }

    /**
     * @param OrderItemEntity $orderItem
     * @return null
     */
    public function getOrderComplaintItemByOrderItem(OrderItemEntity $orderItem)
    {
        $et = $this->entityManager->getEntityTypeByCode("order_complaint_item");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("orderItem", "eq", $orderItem->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters);
    }
}