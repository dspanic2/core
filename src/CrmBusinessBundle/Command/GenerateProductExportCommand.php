<?php

// php bin/console productExport:generate generate_all_exports
// php bin/console productExport:generate generate_export 1

namespace CrmBusinessBundle\Command;

use AppBundle\Managers\HelperManager;
use CrmBusinessBundle\Entity\ProductExportEntity;
use CrmBusinessBundle\Managers\ExportManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class GenerateProductExportCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('productExport:generate')
            ->SetDescription('Helper functions')
            ->AddArgument('type', InputArgument :: OPTIONAL, ' which function ')
            ->AddArgument('export_id', InputArgument :: OPTIONAL, ' which export ');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        /**
         * Start new session for import
         */
        $request = new Request();
        if (!empty($request->getSession())) {
            $request->getSession()->invalidate();
        }

        /** @var HelperManager $helperManager */
        $helperManager = $this->getContainer()->get("helper_manager");

        $request = new Request();
        $helperManager->loginAnonymus($request, "system");
        /**
         * End start new session for import
         */

        $func = $input->getArgument('type');
        if ($func == "generate_all_exports") {

            /** @var ExportManager $manager */
            $manager = $this->getContainer()->get("export_manager");

            $productExports = $manager->getProductExports();

            if (empty($productExports)) {
                return true;
            }

            /** @var ProductExportEntity $productExport */
            foreach ($productExports as $productExport) {
                $manager->generateProductExport($productExport);
            }
        } elseif ($func == "generate_export") {

            /** @var ExportManager $manager */
            $manager = $this->getContainer()->get("export_manager");

            $exportId = $input->getArgument('export_id');

            /** @var ProductExportEntity $productExport */
            $productExport = $manager->getProductExportById($exportId);

            if (empty($productExport)) {
                return false;
            }

            $manager->generateProductExport($productExport);
        } else {
            throw new \Exception("Command type missing: ".json_encode($input->getArguments()));
        }

        return false;
    }
}
