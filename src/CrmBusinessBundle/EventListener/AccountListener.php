<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Entity\EntityType;
use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\AccountTypeLinkEntity;
use CrmBusinessBundle\Entity\QuoteEntity;
use CrmBusinessBundle\Events\QuoteSentEvent;
use CrmBusinessBundle\Managers\AccountManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AccountListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var EntityType $etAccountType */
    protected $etAccountType;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     */
    public function onAccountPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var AccountEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "account") {
            if (empty($entity->getIsLegalEntity())) {
                $entity->setName($entity->getFirstName()." ".$entity->getLastName());
            }
            $entity->setLastContactDate(new \DateTime());
        }
    }

    /**
     * @param EntityCreatedEvent $event
     */
    public function onAccountCreated(EntityCreatedEvent $event)
    {
        /** @var AccountEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "account") {
            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            if (empty($entity->getAccountTypes())) {
                if (empty($this->etAccountType)) {
                    $this->etAccountType = $this->entityManager->getEntityTypeByCode("account_type");
                }

                if ($entity->getAttributeSet()->getAttributeSetCode() == "supplier") {
                    $accountType = $this->entityManager->getEntityByEntityTypeAndId($this->etAccountType, CrmConstants::ACCOUNT_TYPE_SUPPLIER);

                    /** @var AccountTypeLinkEntity $accountTypeLink */
                    $accountTypeLink = $this->entityManager->getNewEntityByAttributSetName("account_type_link");
                    $accountTypeLink->setAccountType($accountType);
                    $accountTypeLink->setAccount($entity);
                    $this->entityManager->saveEntityWithoutLog($accountTypeLink);
                } else {
                    $accountType = $this->entityManager->getEntityByEntityTypeAndId($this->etAccountType, CrmConstants::ACCOUNT_TYPE_CUSTOMER);

                    /** @var AccountTypeLinkEntity $accountTypeLink */
                    $accountTypeLink = $this->entityManager->getNewEntityByAttributSetName("account_type_link");
                    $accountTypeLink->setAccountType($accountType);
                    $accountTypeLink->setAccount($entity);
                    $this->entityManager->saveEntityWithoutLog($accountTypeLink);
                }
            }

            if (empty($entity->getIsLegalEntity())) {
                $data = array();
                $data["email"] = $entity->getEmail();
                $data["first_name"] = $entity->getFirstName();
                $data["last_name"] = $entity->getLastName();
                $data["phone"] = $entity->getPhone();
                $data["phone_2"] = $entity->getPhone2();
                $data["fax"] = $entity->getFax();
                $data["account"] = $entity;
                $data["active"] = true;

                if (empty($this->accountManager)) {
                    $this->accountManager = $this->container->get("account_manager");
                }

                $this->accountManager->insertContact($data);
            }
        }
    }

    /**
     * @param QuoteSentEvent $event
     * @throws \Exception
     */
    public function onQuoteSent(QuoteSentEvent $event)
    {
        /** @var QuoteEntity $entity */
        $entity = $event->getQuote();

        /** @var AccountEntity $account */
        $account = $entity->getAccount();

        $account->setLastOfferSent(new \DateTime());

        if (empty($this->entityManager)) {
            $this->entityManager = $this->container->get("entity_manager");
        }

        $this->entityManager->saveEntityWithoutLog($account);
    }
}
