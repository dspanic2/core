<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Context\PageBlockContext;
use AppBundle\Controller\ListViewController;
use AppBundle\DataTable\DataTablePager;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\ListView;
use AppBundle\Factory\FactoryContext;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use AppBundle\Managers\ListViewManager;
use Doctrine\Common\Util\Inflector;
use Symfony\Component\Config\Definition\Exception\Exception;

class AccountCreationBlock extends AbstractBaseBlock
{
    /**@var EntityManager $entityManager*/
    protected $entityManager;
    /** @var  BlockManager $entityManager*/
    protected $blockManager;
    /**@var ListViewContext $listViewContext */
    protected $listViewContext;
    /**@var EntityTypeContext $entityTypeContext */
    protected $entityTypeContext;
    /**@var FactoryContext $factoryContext */
    protected $factoryContext;
    /**@var AttributeContext $factoryContext */
    protected $attributeContext;

    public function GetPageBlockTemplate()
    {
        return ('AppBundle:Block:'.$this->pageBlock->getType().'.html.twig');
    }

    public function GetPageBlockData()
    {
        $this->entityManager = $this->container->get("entity_manager");
        $this->listViewContext = $this->container->get("list_view_context");
        $this->entityTypeContext = $this->container->get("entity_type_context");
        $this->factoryContext = $this->container->get("factory_context");
        $this->attributeContext = $this->container->get("attribute_context");

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        return array(
            'entity' => $this->pageBlock,
        );
    }

    public function GetPageBlockAdminTemplate()
    {
        return 'AppBundle:Admin/Block:admin_container.html.twig';
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);
        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        if (!empty($data["content"])) {

            /** @var PageBlockContext $pageBlockContext */
            $pageBlockContext = $this->getContainer()->get("page_block_context");
            /** @var BlockManager $blockManager */
            $blockManager = $this->getContainer()->get("block_manager");

            $contentBlocks = json_decode($data["content"], true);

            $newContent = array();

            foreach ($contentBlocks as $key => $contentBlock) {
                $pageBlock = $pageBlockContext->getById($contentBlock["id"]);
                if (!empty($pageBlock)) {
                    $contentBlock["id"] = $pageBlock->getUid();

                    $block = $blockManager->getBlock($pageBlock, null);
                    $blockSettings = $block->GetPageBlockSetingsData();

                    if (isset($blockSettings["show_content"]) && $blockSettings["show_content"] == 1) {
                        $tmp = json_decode($pageBlock->getContent(), true);
                        foreach(array_keys($tmp) as $key) {
                           unset($tmp[$key]["id"]);
                           unset($tmp[$key]["children"]);
                        }
                        $tmp2 = $contentBlock["children"];
                        foreach(array_keys($tmp2) as $key) {
                           unset($tmp2[$key]["id"]);
                           unset($tmp2[$key]["children"]);
                        }

                        if(!empty($tmp) && !empty($tmp2) && (json_encode($tmp) != json_encode($tmp2))){
                            $pageBlock->setIsCustom(1);
                        }
                        $blockManager->savePageBlockContent($pageBlock, $contentBlock["children"]);
                    }
                    unset($contentBlock["children"]);
                    $newContent[] = $contentBlock;
                }
            }

            $data["content"] = json_encode($newContent);
        }

        $this->pageBlock->setContent($data["content"]);

        return $blockManager->save($this->pageBlock);
    }
}
