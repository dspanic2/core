<?php

namespace SharedInboxBusinessBundle\EventListener;

use AppBundle\Entity\TransactionEmailEntity;
use AppBundle\Events\TransactionEmailSentEvent;
use AppBundle\Managers\EntityManager;
use SharedInboxBusinessBundle\Entity\EmailEntity;
use SharedInboxBusinessBundle\Entity\SharedInboxConnectionEntity;
use SharedInboxBusinessBundle\Interfaces\IEmailProvider;
use SharedInboxBusinessBundle\Managers\EmailManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TransactionEmailListener implements ContainerAwareInterface
{
    /** @var EmailManager $emailManager */
    protected $emailManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param TransactionEmailSentEvent $event
     */
    public function onTransactionEmailSent(TransactionEmailSentEvent $event)
    {
        /** @var TransactionEmailEntity $transactionEmail */
        $transactionEmail = $event->getTransactionEmail();

        $content = json_decode($transactionEmail->getContent(), true);
        if (!empty($content) && isset($content["headers"]["In-Reply-To"])) {

            if (empty($this->emailManager)) {
                $this->emailManager = $this->container->get("email_manager");
            }

            /** @var EmailEntity $email */
            $email = $this->emailManager->getEmailByMessageId($content["headers"]["In-Reply-To"]);
            if (!empty($email)) {
                /** @var SharedInboxConnectionEntity $connection */
                $connection = $email->getSharedInboxConnection();

                /** @var IEmailProvider $provider */
                $provider = $this->container->get(strtolower($connection->getType()->getName()) . "_provider");
                $provider->setConnection($connection);

                $provider->saveEmail($event->getSentMimeMessage());
            }
        }
    }
}
