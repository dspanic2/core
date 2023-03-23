<?php

namespace PaymentProvidersBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use CrmBusinessBundle\Constants\CrmConstants;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\PaymentTransactionEntity;
use CrmBusinessBundle\Entity\PaymentTransactionStatusEntity;
use JMS\Serializer\Tests\Fixtures\Order;
use Monolog\Logger;
use PaymentProvidersBusinessBundle\Entity\PaymentTransactionLogEntity;
use PaymentProvidersBusinessBundle\Entity\PaymentTransactionLogTypeEntity;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\EventDispatcher\EventDispatcher;

class PaymentTransactionManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @param $id
     * @return |null
     */
    public function getPaymentTransactionStatusById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(PaymentTransactionStatusEntity::class);
        return $repository->find($id);
    }

    /**
     * @param $data
     * @param PaymentTransactionEntity|null $paymentTransaction
     * @return PaymentTransactionEntity
     */
    public function createUpdatePaymentTransaction($data, PaymentTransactionEntity $paymentTransaction = null)
    {
        if (empty($paymentTransaction)) {
            /** @var PaymentTransactionEntity $paymentTransaction */
            $paymentTransaction = $this->entityManager->getNewEntityByAttributSetName("payment_transaction");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($paymentTransaction, $setter)) {
                $paymentTransaction->$setter($value);
            }
        }

        $this->entityManager->saveEntity($paymentTransaction);
        $this->entityManager->refreshEntity($paymentTransaction);

        return $paymentTransaction;
    }

    /**
     * @param null $additionalFilter
     * @return mixed
     */
    public function getPaymentTransactionsByFilter($additionalFilter = null)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("payment_transaction");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalFilter)) {
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getPaymentTransactionById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(PaymentTransactionEntity::class);
        return $repository->find($id);
    }

    /**
     * @param $orderId
     * @return |null
     */
    public function getPaymentTransactionByOrderId($orderId)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("payment_transaction");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("orderId", "eq", $orderId));
        $compositeFilter->addFilter(new SearchFilter("transactionStatusId", "ne", CrmConstants::PAYMENT_TRANSACTION_STATUS_REVERSAL));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $id
     * @return |null
     */
    public function getPaymentTransactionLogTypeById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(PaymentTransactionLogTypeEntity::class);
        return $repository->find($id);
    }

    /**
     * @param $data
     * @param PaymentTransactionLogEntity|null $paymentTransactionLog
     * @return PaymentTransactionLogEntity|null
     */
    public function createUpdatePaymentTransactionLog($data, PaymentTransactionLogEntity $paymentTransactionLog = null)
    {
        if (empty($paymentTransactionLog)) {
            /** @var PaymentTransactionLogEntity $paymentTransactionLog */
            $paymentTransactionLog = $this->entityManager->getNewEntityByAttributSetName("payment_transaction_log");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($paymentTransactionLog, $setter)) {
                $paymentTransactionLog->$setter($value);
            }
        }

        $this->entityManager->saveEntity($paymentTransactionLog);
        $this->entityManager->refreshEntity($paymentTransactionLog);

        return $paymentTransactionLog;
    }

    /**
     * @param $paymentProviderCode
     * @return null
     */
    public function getPaymentTypeByProviderCode($paymentProviderCode){

        $entityType = $this->entityManager->getEntityTypeByCode("payment_type");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("provider", "eq", $paymentProviderCode));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }

    /**
     * @param $id
     * @return string
     */
    public function getPaymentTransactonLogByOrderId($id){

        $ret = Array();

        if(empty($this->orderManager)){
            $this->orderManager = $this->container->get("order_manager");
        }

        $order = $this->orderManager->getOrderById($id);

        if(empty($order)){
            return implode(",",Array(0));
        }

        $ret[] = $order->getQuoteId();

        return implode(",",$ret);
    }

    /**
     * @param $quoteId
     * @return mixed
     */
    public function getPaymentTransactionByQuoteId($quoteId)
    {
        $entityType = $this->entityManager->getEntityTypeByCode("payment_transaction");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("quoteId", "eq", $quoteId));
        $compositeFilter->addFilter(new SearchFilter("transactionStatusId", "ne", CrmConstants::PAYMENT_TRANSACTION_STATUS_REVERSAL));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($entityType, $compositeFilters);
    }
}
