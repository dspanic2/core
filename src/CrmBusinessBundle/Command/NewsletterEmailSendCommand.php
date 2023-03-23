<?php

// php bin/console newsletterEmail:run generate_newsletter //todo 1 dnevno
// php bin/console newsletterEmail:run send 1000 //todo svaki sat

namespace CrmBusinessBundle\Command;

use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Managers\NewsletterManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class NewsletterEmailSendCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('newsletterEmail:run')
            ->SetDescription('Email functions')
            ->AddArgument('function', InputArgument::OPTIONAL, 'function')
            ->AddArgument('arg1', InputArgument::OPTIONAL, "arg1", null);
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

        $func = $input->getArgument("function");
        if ($func == "generate_newsletter") {

            //todo sloziti defaultnu funckciju

        }
        elseif ($func == "send"){

            /** @var NewsletterManager $newsletterEmailManager */
            $newsletterEmailManager = $container->get('newsletter_manager');

            $limit = $input->getArgument("arg1");

            if(empty($limit)){
                echo "Missing limit";
            }

            $newsletterEmailManager->sendBulkNewsletterEmail($limit);

            return true;
        }
        elseif ($func == "delete_sent_emails"){

            /** @var NewsletterManager $newsletterEmailManager */
            $newsletterEmailManager = $container->get('newsletter_manager');

            $newsletterEmailManager->deleteSentEmails();

            return true;
        }
        else {
            throw new \Exception("Command type missing: ".json_encode($input->getArguments()));
        }

        return false;
    }
}
