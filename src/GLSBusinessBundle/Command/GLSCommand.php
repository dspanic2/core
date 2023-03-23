<?php

// php bin/console gls:execute createTestParcel
// php bin/console gls:execute getGLSParcelStatus 1

namespace GLSBusinessBundle\Command;

use AppBundle\Managers\HelperManager;
use GLSBusinessBundle\Managers\GLSManager;
use MakromikroBusinessBundle\Managers\MakromikroHelperManager;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use VendorBusinessBundle\Managers\RecroManager;
use VendorBusinessBundle\Managers\WandManager;

class GLSCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('gls:execute')
            ->SetDescription('Helper functions')
            ->AddArgument('type', InputArgument :: OPTIONAL, ' which function ')
            ->AddArgument('arg1', InputArgument :: OPTIONAL, ' which function ');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var HelperManager $helperManager */
        $helperManager = $this->getContainer()->get("helper_manager");

        $request = new Request();
        $helperManager->loginAnonymus($request, "system");

        /** @var GLSManager $glsManager */
        $glsManager = $this->getContainer()->get('gls_manager');

        $func = $input->getArgument('type');
        if ($func == "createTestParcel") {
            $glsParcel = $glsManager->createTestParcel();

            $glsParcels = array(
                $glsParcel->getClientReference() => $glsParcel
            );

            $glsManager->printGLSLabels($glsParcels);

            dump($glsManager->getParcelList(new \DateTime('2020-07-01'), new \DateTime('2020-07-10'), null, null));
        } elseif ($func == "getGLSParcelStatus") {

            $arg1 = $input->getArgument('arg1');

            $glsParcel = $glsManager->getGlsParcelById($arg1);

            $ret = $glsManager->getGLSParcelStatus($glsParcel);
            dump($ret);
            die;
        }
    }
}