<?php

// php bin/console transactionEmail:send

namespace AppBundle\Command;

use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\MailManager;
use AppBundle\Entity\TransactionEmailEntity;
use AppBundle\Managers\TransactionEmailManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class TransactionEmailSendCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('transactionEmail:send')
            ->SetDescription('Email functions');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        /**
         * Start new session for import
         */
        $request = new Request();
        if (!empty($request->getSession())) {
            $request->getSession()->invalidate();
        }

        /** @var HelperManager $helperManager */
        $helperManager = $container->get("helper_manager");

        $request = new Request();
        $helperManager->loginAnonymus($request, "system");
        /**
         * End start new session for import
         */

        /** @var MailManager $mailManager */
        $mailManager = $container->get('mail_manager');
        /** @var TransactionEmailManager $transactionEmailManager */
        $transactionEmailManager = $container->get('transaction_email_manager');

        $transactionEmails = $transactionEmailManager->getTransactionEmailsToSend();

        if(EntityHelper::isCountable($transactionEmails) && count($transactionEmails)){
            /** @var TransactionEmailEntity $email */
            foreach ($transactionEmails as $email) {
                $mailManager->sendTransactionEmail($email);
            }

            $transactionEmailManager->transferMailToSentEmails(20);
        }

        return false;
    }
}
