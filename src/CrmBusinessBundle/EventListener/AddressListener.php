<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Managers\AccountManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AddressListener implements ContainerAwareInterface
{
    /** @var AccountManager $accountManager */
    protected $accountManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     */
    public function onAddressPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var AddressEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "address") {
            $entity->setIsActive(1);
        }
    }

    /**
     * @param EntityCreatedEvent $event
     */
    public function onAddressCreated(EntityCreatedEvent $event)
    {
        /** @var AddressEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "address") {
            if (!empty($entity->getAccount())) {
                if(empty($this->accountManager)){
                    $this->accountManager = $this->container->get("account_manager");
                }

                $this->accountManager->setHeadquartersAndBillingFlag($entity);
            }
        }
    }

    /**
     * @param EntityUpdatedEvent $event
     */
    public function onAddressUpdated(EntityUpdatedEvent $event)
    {
        /** @var AddressEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "address") {
            if (!empty($entity->getAccount())) {
                if(empty($this->accountManager)){
                    $this->accountManager = $this->container->get("account_manager");
                }

                $this->accountManager->setHeadquartersAndBillingFlag($entity);
            }
        }
    }

    /**
     * @param EntityDeletedEvent $event
     */
    public function onAddressDeleted(EntityDeletedEvent $event)
    {
        /** @var AddressEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "address") {
            if (!empty($entity->getAccount())) {
                if(empty($this->accountManager)){
                    $this->accountManager = $this->container->get("account_manager");
                }

                $this->accountManager->setHeadquartersAndBillingFlag($entity);
            }
        }
    }
}
