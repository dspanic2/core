<?php

// php bin/console admin:update_to_uid

namespace AppBundle\Command\Deprecated;

use AppBundle\Blocks\CalendarBlock;
use AppBundle\Context\PageBlockContext;
use AppBundle\Context\PageContext;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\Page;
use AppBundle\Entity\PageBlock;
use AppBundle\Managers\DatabaseManager;
use AppBundle\Context\DatabaseContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Definitions\ColumnDefinitions;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\PageManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateToUidCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('admin:update_to_uid')
            ->SetDescription(' description of what the command ')
            ->AddArgument(' my_argument ', InputArgument :: OPTIONAL, ' We explain the meaning of the argument ');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var DatabaseContext $databaseContext */
        $databaseContext = $this->getContainer()->get("database_context");

        $query = "SELECT * FROM page_block WHERE type = 'calendar';";
        $blocks = $databaseContext->getAll($query);

        if (!empty($blocks)) {
            /** @var PageBlock $block */
            foreach ($blocks as $block) {
                $updateBlock = false;
                $content = json_decode($block["content"], true);
                if (isset($content["list_view"])) {
                    foreach ($content["list_view"] as $key => $list) {
                        if (is_numeric($list)) {
                            $query = "SELECT uid FROM list_view WHERE id='{$list}';";
                            $listView = $databaseContext->getSingleEntity($query);
                            if (!empty($listView)) {
                                $updateBlock = true;
                                $content["list_view"][$key] = $listView["uid"];
                            }
                        }
                    }
                }
                if (isset($content["list_view_attributes"])) {
                    foreach ($content["list_view_attributes"] as $key => $value) {
                        if (is_numeric($value)) {
                            $query = "SELECT uid FROM attribute WHERE id='{$value}';";
                            $attribute = $databaseContext->getSingleEntity($query);
                            if (!empty($attribute)) {
                                $updateBlock = true;
                                $content["list_view_attributes"][$key] = $attribute["uid"];
                            }
                        }

                        $keyValues = explode("ListView", $key);
                        $attributeValues = explode("Attribute", $keyValues[1]) ?? null;
                        $listId = $attributeValues[0] ?? null;
                        if (!empty($listId)) {
                            $query = "SELECT uid FROM list_view WHERE id='{$listId}';";
                            $listView = $databaseContext->getSingleEntity($query);
                            if (!empty($listView)) {
                                $updateBlock = true;
                                $content["list_view_attributes"]["{$keyValues[0]}ListView{$listView["uid"]}Attribute"] = $content["list_view_attributes"][$key];
                                unset($content["list_view_attributes"][$key]);
                            }
                        }
                    }
                }
                if ($updateBlock) {
                    $newContent = json_encode($content);
                    $query = "UPDATE page_block SET content='{$newContent}' WHERE id='{$block["id"]}';";
//                    dump($query);
//                    die;
                    $databaseContext->executeNonQuery($query);
                }
            }
        }
    }
}