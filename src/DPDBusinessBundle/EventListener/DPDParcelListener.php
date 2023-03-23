<?php

namespace DPDBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use DPDBusinessBundle\Entity\DpdParcelEntity;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DPDParcelListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     */
    public function onDpdParcelPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var DpdParcelEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "dpd_parcel") {

            /**
             * If order is defined
             */
            if (!empty($entity->getOrder())) {

                /** @var OrderEntity $order */
                $order = $entity->getOrder();

                /** @var ContactEntity $contact */
                $contact = $order->getContact();

                $entity->setOrderNumber($order->getIncrementId());

                $entity->setRecipientName($order->getAccount()->getName());
                $entity->setRecipientEmail($contact->getEmail());
                $entity->setRecipientPhone($contact->getPhone());

                /** @var AddressEntity $shippingAddress */
                $shippingAddress = $order->getAccountShippingAddress();
                if (!empty($shippingAddress->getFirstName()) && !empty($shippingAddress->getLastName())) {
                    $entity->setRecipientName($shippingAddress->getFirstName() . " " . $shippingAddress->getLastName());
                }
                if (!empty($shippingAddress->getPhone())) {
                    $entity->setRecipientPhone($shippingAddress->getPhone());
                }

                $entity->setCountry($shippingAddress->getCity()->getCountry()->getCode());
                $entity->setRecipientStreet($shippingAddress->getStreet());
                $entity->setRecipientCity($shippingAddress->getCity()->getName());
                $entity->setPostalCode($shippingAddress->getCity()->getPostalCode());
                $entity->setRequested(0);
                $entity->setNumberOfParcels(1);

                if (isset($_ENV["DPD_PARCEL_SENDER_NAME"]) && !empty($_ENV["DPD_PARCEL_SENDER_NAME"])){
                    $entity->setSenderName($_ENV["DPD_PARCEL_SENDER_NAME"]);
                }

                if (isset($_ENV["DPD_PARCEL_SENDER_PHONE"]) && !empty($_ENV["DPD_PARCEL_SENDER_PHONE"])){
                    $entity->setSenderPhone($_ENV["DPD_PARCEL_SENDER_PHONE"]);
                }

                if (isset($_ENV["DPD_PARCEL_SENDER_COUNTRY"]) && !empty($_ENV["DPD_PARCEL_SENDER_COUNTRY"])){
                    $entity->setSenderCountry($_ENV["DPD_PARCEL_SENDER_COUNTRY"]);
                }

                if (isset($_ENV["DPD_PARCEL_SENDER_CITY"]) && !empty($_ENV["DPD_PARCEL_SENDER_CITY"])){
                    $entity->setSenderCity($_ENV["DPD_PARCEL_SENDER_CITY"]);
                }

                if (isset($_ENV["DPD_PARCEL_SENDER_STREET"]) && !empty($_ENV["DPD_PARCEL_SENDER_STREET"])){
                    $entity->setSenderStreet($_ENV["DPD_PARCEL_SENDER_STREET"]);
                }

                if (isset($_ENV["DPD_PARCEL_SENDER_POSTAL_CODE"]) && !empty($_ENV["DPD_PARCEL_SENDER_POSTAL_CODE"])){
                    $entity->setSenderPostalCode($_ENV["DPD_PARCEL_SENDER_POSTAL_CODE"]);
                }

                $crmProcessManager = $this->container->get("crm_process_manager");
                $crmProcessManager->setDpdParcelData($entity,$order);
            }
        }
    }
}
