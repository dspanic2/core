<?php

namespace CrmBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use CrmBusinessBundle\Entity\InvoiceDeviceIdentifierEntity;
use CrmBusinessBundle\Entity\InvoiceEntity;
use CrmBusinessBundle\Entity\InvoiceItemEntity;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Events\InvoiceCreatedEvent;
use CrmBusinessBundle\Events\InvoiceRefundedEvent;
use JMS\Serializer\Tests\Fixtures\Order;
use Monolog\Logger;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\EventDispatcher\EventDispatcher;
use function Symfony\Component\VarDumper\Tests\Fixtures\bar;

class InvoiceManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var AttributeContext $attributeContext */
    protected $attributeContext;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getInvoiceDeviceIdentifierById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(InvoiceDeviceIdentifierEntity::class);
        return $repository->find($id);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getInvoiceById($id)
    {
        $repository = $this->entityManager->getDoctrineEntityManager()->getRepository(InvoiceEntity::class);
        return $repository->find($id);
    }

    /**
     * @param null $additionalFilter
     * @return mixed
     */
    public function getFilteredInvoice($additionalFilter = null)
    {
        $et = $this->entityManager->getEntityTypeByCode("invoice");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalFilter)) {
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters);
    }

    /**
     * @param $data
     * @param InvoiceEntity|null $invoice
     */
    public function createUpdateInvoice($data, InvoiceEntity $invoice = null, $skipLog = false){

        if (empty($invoice)) {
            /** @var InvoiceEntity $invoice */
            $invoice = $this->entityManager->getNewEntityByAttributSetName("invoice");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($invoice, $setter)) {
                $invoice->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($invoice);
        } else {
            $this->entityManager->saveEntity($invoice);
        }
        $this->entityManager->refreshEntity($invoice);

        return $invoice;
    }

    /**
     * @param null $additionalFilter
     * @return mixed
     */
    public function getFilteredInvoiceItem($additionalFilter = null)
    {
        $et = $this->entityManager->getEntityTypeByCode("invoice_item");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalFilter)) {
            $compositeFilters->addCompositeFilter($additionalFilter);
        }

        return $this->entityManager->getEntityByEntityTypeAndFilter($et, $compositeFilters);
    }

    /**
     * @param $data
     * @param InvoiceItemEntity|null $invoiceItem
     * @param false $skipLog
     * @return InvoiceItemEntity|null
     */
    public function createUpdateInvoiceItem($data, InvoiceItemEntity $invoiceItem = null, $skipLog = false){

        if (empty($invoiceItem)) {
            /** @var InvoiceItemEntity $invoiceItem */
            $invoiceItem = $this->entityManager->getNewEntityByAttributSetName("invoice_item");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($invoiceItem, $setter)) {
                $invoiceItem->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($invoiceItem);
        } else {
            $this->entityManager->saveEntity($invoiceItem);
        }
        $this->entityManager->refreshEntity($invoiceItem);

        return $invoiceItem;
    }

    /**
     * TODO SAMO OVO DOLJE TREBA SREDIT
     */

    /**
     * @param InvoiceEntity $invoice
     * @return InvoiceEntity
     * @throws \Exception
     */
    public function generateRefundFromInvoice(InvoiceEntity $invoice)
    {
        /** @var InvoiceEntity $refundInvoice */
        $refundInvoice = $this->entityManager->cloneEntity($invoice, "invoice", array(), true);

        $refundInvoice->setFileType(null);
        $refundInvoice->setFile(null);
        $refundInvoice->setFilename(null);
        $refundInvoice->setSize(null);
        $refundInvoice->setRefundFor($invoice);
        $refundInvoice->setIssueDate(new \DateTime());
        $refundInvoice->setDueDate(new \DateTime());
        $refundInvoice->setBasePriceTotal(-$refundInvoice->getBasePriceTotal());
        $refundInvoice->setBasePriceTax(-$refundInvoice->getBasePriceTax());
        $refundInvoice->setBasePriceWithoutTax(-$refundInvoice->getBasePriceWithoutTax());
        $refundInvoice->setPriceTotal(-$refundInvoice->getPriceTotal());
        $refundInvoice->setPriceTax(-$refundInvoice->getPriceTax());
        $refundInvoice->setPriceWithoutTax(-$refundInvoice->getPriceWithoutTax());
        $refundInvoice->setBasePriceDiscount(-$refundInvoice->getBasePriceDiscount());

        $this->entityManager->saveEntityWithoutLog($refundInvoice);
        $this->entityManager->refreshEntity($refundInvoice);

        $invoiceItems = $invoice->getInvoiceItems();
        if (EntityHelper::isCountable($invoiceItems) && count($invoiceItems)) {
            /** @var InvoiceItemEntity $invoiceItem */
            foreach ($invoiceItems as $invoiceItem) {
                /** @var InvoiceItemEntity $refundInvoiceItem */
                $refundInvoiceItem = $this->entityManager->cloneEntity($invoiceItem, "invoice_item");

                $refundInvoiceItem->setInvoice($refundInvoice);
                $refundInvoiceItem->setBasePriceTotal(-$refundInvoiceItem->getBasePriceTotal());
                $refundInvoiceItem->setBasePriceTax(-$refundInvoiceItem->getBasePriceTax());
                $refundInvoiceItem->setBasePriceWithoutTax(-$refundInvoiceItem->getBasePriceWithoutTax());
                $refundInvoiceItem->setBasePriceItem(-$refundInvoiceItem->getBasePriceItem());
                $refundInvoiceItem->setBasePriceItemTax(-$refundInvoiceItem->getBasePriceItemTax());
                $refundInvoiceItem->setBasePriceItemWithoutTax(-$refundInvoiceItem->getBasePriceItemWithoutTax());
                $refundInvoiceItem->setPriceTotal(-$refundInvoiceItem->getPriceTotal());
                $refundInvoiceItem->setPriceTax(-$refundInvoiceItem->getPriceTax());
                $refundInvoiceItem->setPriceWithoutTax(-$refundInvoiceItem->getPriceWithoutTax());
                $refundInvoiceItem->setPriceItem(-$refundInvoiceItem->getPriceItem());
                $refundInvoiceItem->setPriceItemTax(-$refundInvoiceItem->getPriceItemTax());
                $refundInvoiceItem->setPriceItemWithoutTax(-$refundInvoiceItem->getPriceItemWithoutTax());
                $refundInvoiceItem->setOriginalPriceItem(-$refundInvoiceItem->getOriginalPriceItem());
                $refundInvoiceItem->setOriginalPriceItemTax(-$refundInvoiceItem->getOriginalPriceItemTax());
                $refundInvoiceItem->setOriginalPriceItemWithoutTax(-$refundInvoiceItem->getOriginalPriceItemWithoutTax());
                $refundInvoiceItem->setOriginalBasePriceItem(-$refundInvoiceItem->getOriginalBasePriceItem());
                $refundInvoiceItem->setOriginalBasePriceItemTax(-$refundInvoiceItem->getOriginalBasePriceItemTax());
                $refundInvoiceItem->setOriginalBasePriceItemWithoutTax(-$refundInvoiceItem->getOriginalBasePriceItemWithoutTax());
                $refundInvoiceItem->setPriceFixedDiscount(-$refundInvoiceItem->getPriceFixedDiscount());
                $refundInvoiceItem->setBasePriceFixedDiscount(-$refundInvoiceItem->getBasePriceFixedDiscount());
                $refundInvoiceItem->setPriceDiscountTotal(-$refundInvoiceItem->getPriceDiscountTotal());
                $refundInvoiceItem->setBasePriceDiscountTotal(-$refundInvoiceItem->getBasePriceDiscountTotal());

                $this->entityManager->saveEntityWithoutLog($refundInvoiceItem);
                $this->entityManager->refreshEntity($refundInvoiceItem);
            }
        }

        $this->entityManager->refreshEntity($refundInvoice);
    }
}
