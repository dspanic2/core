<?php

// php bin/console admin:update_entity_schema

namespace AppBundle\Command\Deprecated;

use AppBundle\Entity\EntityType;
use AppBundle\Managers\DatabaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Definitions\ColumnDefinitions;
use AppBundle\Helpers\StringHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateEntityTableSchemaCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('admin:update_entity_schema')
            ->SetDescription(' description of what the command ')
            ->AddArgument(' my_argument ', InputArgument :: OPTIONAL, ' We explain the meaning of the argument ');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        /**@var DatabaseManager $databaseManager */
        $databaseManager = $this->getContainer()->get("database_manager");
        $entityTypeConext = $this->getContainer()->get("entity_type_context");
        /**@var DatabaseContext $databaseContext */
        $databaseContext = $this->getContainer()->get("database_context");

        $entityTypes = $entityTypeConext->getAll();

        /**@var EntityType $entityType */
        foreach ($entityTypes as $entityType) {
            $upToDate = true;
            $columnDefinitions = $entityType->getIsDocument() ? ColumnDefinitions::DocumentColumnDefinitions() : ColumnDefinitions::ColumnDefinitions();

            foreach ($columnDefinitions as $definition) {
                $check = $databaseContext->executeQuery("SHOW COLUMNS FROM {$entityType->getEntityTable()} LIKE '{$definition["name"]}'");

                if (!$check) {
                    $upToDate = false;
                    $insert_col = "ALTER TABLE {$entityType->getEntityTable()} ADD {$definition["name"]} {$definition["definition"]}";
                    $databaseContext->executeNonQuery($insert_col);
                    $databaseManager->generateDoctrineXML($entityType, true);
                    $databaseManager->generateEntityClasses($entityType, true);
                }
            }

            if (!$upToDate) {
                echo("{$entityType->getEntityTypeCode()} schema updated! \n");
            } else {
                echo("{$entityType->getEntityTypeCode()} version already latest! \n");
            }
        }
    }
}
