<?php

// php bin/console helper:smetlar clear_blocks 1|0 delete or dump > ./var/logs/smetlar.sql
// php bin/console helper:smetlar clear_blocks s_front_block_entity 0 -> za test
// php bin/console helper:smetlar test
// php bin/console helper:smetlar clear_given_tables product_entity,product_group_entity,product_product_group_link_entity,product_product_link_entity,brand_entity,s_product_attribute_configuration_options_entity,s_product_attributes_link_entity,s_product_attribute_configuration_entity,blog_category_entity,blog_post_entity,account_entity,user_entity,address_entity,contact_entity,order_entity,order_item_entity,quote_entity,facets_entity,facet_attribute_configuration_link_entity,int_also_attribute_entity,int_also_attribute_values_entity,int_also_category_entity,int_greencell_category_entity,int_lost_category_entity,int_luceed_attribute_entity,int_luceed_attribute_values_entity,int_luceed_category_entity,int_microline_attribute_entity,int_microline_attribute_values_entity,int_microline_category_entity,margin_rule_entity
// php bin/console helper:smetlar fix_json_fields
// php bin/console helper:smetlar remove_deleted_database_and_unused_disk_files | [1/0], 0 -> default | list of entity types
// php bin/console helper:smetlar download_and_replace_tags_from_attribute blog_post content img src https://www.oluk.hr/wp-content/uploads/

namespace AppBundle\Command;

use Monolog\Logger;
use AppBundle\Managers\ShapeCleanerManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class ShapeCleanerCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('helper:smetlar')
            ->SetDescription('Helper functions')
            ->AddArgument('type', InputArgument :: OPTIONAL, ' We explain the meaning of the argument ')
            ->AddArgument('arg_1', InputArgument :: OPTIONAL, 'Argument 1')
            ->AddArgument('arg_2', InputArgument :: OPTIONAL, 'Argument 2')
            ->AddArgument('arg_3', InputArgument :: OPTIONAL, 'Argument 3')
            ->AddArgument('arg_4', InputArgument :: OPTIONAL, 'Argument 4')
            ->AddArgument('arg_5', InputArgument :: OPTIONAL, 'Argument 5');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ShapeCleanerManager $shapeCleanerManager */
        $shapeCleanerManager = $this->getContainer()->get("shape_cleaner_manager");

        $func = $input->getArgument('type');
        if ($func == "fix_json_fields") {
            $shapeCleanerManager->fixJsonFields();

            return true;
        } else if ($func == "clear_blocks") {

            $allowedTables = $input->getArgument("arg_1");
            $delete = $input->getArgument("arg_2");

            $query = $shapeCleanerManager->executeCleaner($allowedTables,$delete);

            if(!$delete){
                dump($query);
            }

            return true;
        } else if ($func == "remove_deleted_database_and_unused_disk_files") {
            $deleteFiles = $input->getArgument('arg_1');
            $listOfEntityTypes = $input->getArgument('arg_2');
            if(!empty($listOfEntityTypes)){
                $listOfEntityTypes = explode(",",$listOfEntityTypes);
            }
            return $shapeCleanerManager->removeDeletedDatabaseAndUnusedDiskFiles($deleteFiles,$listOfEntityTypes);
        } elseif ($func == "download_and_replace_tags_from_attribute") {

            // blog_post, product...
            $entity = $input->getArgument("arg_1");
            // content, description...
            $attribute = $input->getArgument("arg_2");
            // img...
            $tag = $input->getArgument("arg_3");
            // src, href...
            $lookupAttribute = $input->getArgument("arg_4");

            if(empty($entity) || empty($attribute) || empty($tag) || empty($lookupAttribute)){
                throw new \Exception("Missing mandatory input!");
            }

            // https://encian.hr, https://oluk.hr/wp-content/uploads/... *-> Optional
            $remoteBaseDownloadUrl = $input->getArgument("arg_5");

            $shapeCleanerManager->downloadAndReplaceTagFromAttribute($entity, $attribute, $tag, $lookupAttribute, $remoteBaseDownloadUrl);
        }
        else{
            throw new \Exception("Command type missing: ".json_encode($input->getArguments()));
        }

        return true;
    }
}

