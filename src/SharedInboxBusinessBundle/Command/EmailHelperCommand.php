<?php

// php bin/console emailhelper:function import
// php bin/console emailhelper:function test_reply 4579 "lorem ipsum"

namespace SharedInboxBusinessBundle\Command;

use AppBundle\Managers\HelperManager;
use SharedInboxBusinessBundle\Entity\EmailEntity;
use SharedInboxBusinessBundle\Managers\EmailManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class EmailHelperCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName("emailhelper:function")
            ->SetDescription("Helper functions")
            ->AddArgument("type", InputArgument::OPTIONAL, " which function ")
            ->AddArgument("arg1", InputArgument::OPTIONAL, " which argument ")
            ->AddArgument("arg2", InputArgument::OPTIONAL, " which argument ");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $request = new Request();
        if (!empty($request->getSession())) {
            $request->getSession()->invalidate();
        }

        /** @var HelperManager $helperManager */
        $helperManager = $this->getContainer()->get("helper_manager");
        $helperManager->loginAnonymus($request, "system");

        /** @var EmailManager $emailManager */
        $emailManager = $this->getContainer()->get("email_manager");

        /**
         * Check which function
         */
        $func = $input->getArgument("type");
        if (empty($func)) {
            throw new \Exception("Function not defined");
        }

        $arg1 = $input->getArgument("arg1");
        $arg2 = $input->getArgument("arg2");

        if ($func == "import") {

            echo $emailManager->importEmails() . " emails imported\n";

        }
        else if ($func == "test_reply") {

            /** @var EmailEntity $email */
            $email = $emailManager->getEmailById($arg1);

            $emailManager->sendReplyEmail($email, "test");

            echo "Reply sent\n";

        }
    }
}