<?php

// php bin/console admin:sync delete
// php bin/console admin:sync export-default
// php bin/console admin:sync import-default
// php bin/console admin:sync export
// php bin/console admin:sync import

namespace AppBundle\Command;

use AppBundle\Context\EntityTypeContext;
use AppBundle\Managers\AdministrationManager;
use AppBundle\Managers\DatabaseManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\SyncManager;
use InvalidArgumentException;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class SyncCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('admin:sync')
            ->SetDescription(' description of what the command ')
            ->AddArgument('type', InputArgument :: OPTIONAL, ' which function ')
            ->AddArgument('arg_1', InputArgument :: OPTIONAL, 'Argument 1');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var SyncManager $syncManager */
        $syncManager = $this->getContainer()->get("sync_manager");

        /**
         * Check which function
         */
        $func = $input->getArgument('type');
        if (empty($func)) {
            throw new \Exception('Function not defined');
        }

        if ($func == "delete") {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Do you want delete all config files? [y] ', true);

            if (!$helper->ask($input, $output, $question)) {
                return;
            }

            // Remove old configuration
            foreach ($this->getContainer()->getParameter('kernel.bundles') as $bundle => $namespace) {
                if (file_exists($_ENV["WEB_PATH"] . "/../src/{$bundle}/Resources/config/db")) {
                    $this->deleteDir($_ENV["WEB_PATH"] . "/../src/{$bundle}/Resources/config/db");
                }
            }
        } elseif ($func == "export-default") {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Do you want to export default config files? [y] ', true);

            if (!$helper->ask($input, $output, $question)) {
                return;
            }

            // Export database configuration
            #$syncManager->exportMainTablesStructure();
            #$syncManager->exportDefaultEntityTablesStructure();
            $syncManager->exportMainTablesContent();
            #$syncManager->exportDefaultEntityTablesContent();
//            $syncManager->exportCustomEntities();
        } elseif ($func == "import-default") {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Do you want to import default config files? [y] ', true);

            if (!$helper->ask($input, $output, $question)) {
                return;
            }

            // Import database configuration
            for($i=0;$i<2;$i++){
                $syncManager->importMainTablesStructure();
                $syncManager->importMainTablesContent();
                $syncManager->importDefaultEntityTablesStructure();
                $this->rebuildEntities();
                $syncManager->importDefaultEntitiesTablesContent();
            }
        } elseif ($func == "export") {
            // Performed on save!
            $syncManager->exportCustomEntities();
        } elseif ($func == "import") {
            // Import json configuration
            $syncManager->importConfiguration();
        } else{
            throw new \Exception("Command type missing: ".json_encode($input->getArguments()));
        }

        return false;
    }

    private function deleteDir($dirPath)
    {
        if (file_exists($dirPath)) {
            if (!is_dir($dirPath)) {
                throw new InvalidArgumentException("$dirPath must be a directory");
            }
            if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
                $dirPath .= '/';
            }
            $files = glob($dirPath . '*', GLOB_MARK);
            foreach ($files as $file) {
                if (is_dir($file)) {
                    self::deleteDir($file);
                } else {
                    unlink($file);
                }
            }
            rmdir($dirPath);
        }
    }

    private function rebuildEntities()
    {
        /**@var EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->getContainer()->get("entity_type_context");

        /**@var \AppBundle\Entity\EntityType $entityType */
        $entityTypes = $entityTypeContext->getAllItems();

        /**@var AdministrationManager $administrationManager */
        $administrationManager = $this->getContainer()->get("administration_manager");
        /**@var DatabaseManager $databaseManager */
        $databaseManager = $this->getContainer()->get("database_manager");

        echo "\nRebuilding entity_types...\n";

        foreach ($entityTypes as $entityType) {
            echo "\t" . $entityType->getEntityTypeCode() . "\n";
            $databaseManager->createTableIfDoesntExist($entityType, null);
            $administrationManager->generateDoctrineXML($entityType, true);
            $administrationManager->generateEntityClasses($entityType, true);
        }
    }
}
