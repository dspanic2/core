<?php

namespace AppBundle\Blocks;

use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\PageBlockContext;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;
use Symfony\Component\VarDumper\VarDumper;
use AppBundle\Abstracts\AbstractBaseBlock;

class CollapsibleBlock extends AbstractBaseBlock
{
    /**@var EntityManager $entityManager */
    protected $entityManager;


    public function GetPageBlockTemplate()
    {
        return 'AppBundle:Block:'.$this->pageBlock->getType().'.html.twig';
    }

    /**
     * @return mixed
     */
    public function GetPageBlockData()
    {
        return $this->pageBlockData;
    }

    public function isVisible()
    {
        return true;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        return array(
            'entity' => $this->pageBlock,
            'managed_entity_type' => "page_block",
            'show_add_button' => 1,
            'show_content' => 1
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
                        if(!empty($tmp)){
                            foreach(array_keys($tmp) as $key) {
                               unset($tmp[$key]["id"]);
                               unset($tmp[$key]["children"]);
                            }
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

                    if($pageBlock->getIsCustom()){
                        $this->pageBlock->setIsCustom($pageBlock->getIsCustom());
                    }
                }
            }

            $data["content"] = json_encode($newContent);
        }

        $this->pageBlock->setContent($data["content"]);

        return $blockManager->save($this->pageBlock);
    }
}
