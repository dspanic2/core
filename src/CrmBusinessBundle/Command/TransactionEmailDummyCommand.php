<?php

// php bin/console transactionEmail:dummy send_test alen.pagac@gmail.com
// php bin/console transactionEmail:dummy send_test_template alen.pagac@gmail.com order_created ID

namespace CrmBusinessBundle\Command;

use AppBundle\Managers\EntityManager;
use AppBundle\Managers\MailManager;
use CrmBusinessBundle\Entity\EmailTemplateEntity;
use CrmBusinessBundle\Managers\EmailTemplateManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TransactionEmailDummyCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('transactionEmail:dummy')
            ->SetDescription('Email functions')
            ->AddArgument('function', InputArgument::OPTIONAL, 'function')
            ->AddArgument('arg1', InputArgument :: OPTIONAL)
            ->AddArgument('arg2', InputArgument :: OPTIONAL)
            ->AddArgument('arg3', InputArgument :: OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('arg1');
        if (empty($email)) {
            echo "Email param missing\n";
            return false;
        }

        /** @var MailManager $mailManager */
        $mailManager = $this->getContainer()->get('mail_manager');

        $func = $input->getArgument("function");
        if ($func == "send_test") {
            $to = [
                "email" => $email,
                "name" => "AP"
            ];
            $mailManager->prepareTransactionEmail($to, null, null, null, 'Test email', "", null, array(), "SOME TEXT");

        } elseif ($func == "send_test_template") {
            $templateCode = $input->getArgument("arg2");
            $entityId = $input->getArgument("arg3");
            if (empty($templateCode) || empty($entityId)) {
                throw new \Exception("Missing code or entity id");
            }
            $to = [
                "email" => $email,
                "name" => "AP"
            ];

            /** @var EmailTemplateManager $emailTemplateManager */
            $emailTemplateManager = $this->getContainer()->get('email_template_manager');
            /** @var EntityManager $entityManager */
            $entityManager = $this->getContainer()->get('entity_manager');
            /** @var EmailTemplateEntity $template */
            $template = $emailTemplateManager->getEmailTemplateByCode($templateCode);

            $templateAttachments = $template->getAttachments();
            if (!empty($templateAttachments)) {
                $attachments = $template->getPreparedAttachments();
            }

            $entity = $entityManager->getEntityByEntityTypeAndId($entityManager->getEntityTypeByCode($template->getProvidedEntityTypeId()), $entityId);

            $templateData = $emailTemplateManager->renderEmailTemplate($entity, $template);

            $mailManager->prepareTransactionEmail($to, null, null, null, $templateData["subject"], "", null, $attachments ?? [], $templateData["content"]);
        }

        return false;
    }
}
