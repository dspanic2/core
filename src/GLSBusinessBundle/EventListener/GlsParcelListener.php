<?php

namespace GLSBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\OrderEntity;
use GLSBusinessBundle\Entity\GlsParcelEntity;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GlsParcelListener implements ContainerAwareInterface
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
    public function onGlsParcelPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var GlsParcelEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "gls_parcel") {

            $clientNumber = $_ENV["GLS_CLIENT_NUMBER"];

            $entity->setClientNumber($clientNumber);
            $entity->setClientReference(StringHelper::guidv4());

            /**
             * If order is defined
             */
            if(!empty($entity->getOrder())){

                /** @var OrderEntity $order */
                $order = $entity->getOrder();

                $entity->setCodReference($order->getIncrementId());
                $entity->setContent($order->getIncrementId());
                $entity->setDeliveryCity($order->getAccountShippingCity()->getName());
                $entity->setDeliveryZipCode($order->getAccountShippingCity()->getPostalCode());
                $entity->setDeliveryStreet($order->getAccountShippingStreet());
                if(isset($_ENV["GLS_DEFAULT_COUNTRY"])){
                    $entity->setDeliveryCountryIsoCode($_ENV["GLS_DEFAULT_COUNTRY"]);
                }
                else{
                    $entity->setDeliveryCountryIsoCode("HR");
                }

                /** @var ContactEntity $contact */
                $contact = $order->getContact();

                $entity->setDeliveryContactName($contact->getFullName());
                $entity->setDeliveryName($order->getAccount()->getName());
                $entity->setDeliveryContactEmail($contact->getEmail());
                $entity->setDeliveryContactPhone($contact->getPhone());

                $crmProcessManager = $this->container->get("crm_process_manager");
                $crmProcessManager->setGlsParcelData($entity,$order);
            }
        }
    }
}
