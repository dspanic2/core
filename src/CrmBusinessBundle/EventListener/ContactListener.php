<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Entity\NewsletterEntity;
use CrmBusinessBundle\Managers\AccountManager;
use CrmBusinessBundle\Managers\NewsletterManager;
use CrmBusinessBundle\Managers\QuoteManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContactListener implements ContainerAwareInterface
{
    protected $container;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var NewsletterManager $newsletterManager */
    protected $newsletterManager;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var QuoteManager $quoteManager */
    protected $quoteManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     */
    public function onContactPreCreated(EntityPreCreatedEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "contact") {
            $entity->setFullName($entity->getFirstName()." ".$entity->getLastName());
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     */
    public function onContactPreUpdated(EntityPreUpdatedEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "contact") {
            $entity->setFullName($entity->getFirstName()." ".$entity->getLastName());
        }
    }


    /**
     * @param EntityUpdatedEvent $event
     * @return bool
     * @throws \Exception
     */
    public function onContactUpdated(EntityUpdatedEvent $event)
    {
        /** @var ContactEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "contact") {

            if (empty($this->newsletterManager)) {
                $this->newsletterManager = $this->container->get("newsletter_manager");
            }

            $session = $this->container->get("session");

            $data = array();
            $data["email"] = $entity->getEmail();
            $data["active"] = $entity->getNewsletterSignup();
            $data["contact"] = $entity;
            $data["store"] = $session->get("current_store_id");

            $this->newsletterManager->insertUpdateNewsletterSubscriber($data,true);

            /** @var AccountEntity $account */
            $account = $entity->getAccount();

            /**
             * Update account data if non legal
             */
            if (!$account->getIsLegalEntity()) {
                $account->setFirstName($entity->getFirstName());
                $account->setLastName($entity->getLastName());
                $account->setPhone($entity->getPhone());
                $account->setPhone2($entity->getPhone2());
                $account->setFax($entity->getFax());
                $account->setName($entity->getFirstName()." ".$entity->getLastName());

                if (empty($this->accountManager)) {
                    $this->accountManager = $this->container->get("account_manager");
                }

                $this->accountManager->save($account);

                if (empty($this->quoteManager)) {
                    $this->quoteManager = $this->container->get("quote_manager");
                }

                $this->quoteManager->updateContactOnQuotes($entity);
            }

            return true;
        }
    }



    /**
     * @param EntityDeletedEvent $event
     * @return bool
     */
    public function onContactDeleted(EntityDeletedEvent $event)
    {
        /** @var ContactEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "contact") {
            $entity->setEmail(null);

            if(empty($this->entityManager)){
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->saveEntityWithoutLog($entity);
        }

        return true;
    }
}
