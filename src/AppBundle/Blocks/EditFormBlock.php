<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\PageBlockContext;
use AppBundle\Entity\PageBlock;
use AppBundle\Managers\BlockManager;

class EditFormBlock extends AbstractBaseBlock
{

    public function GetPageBlockTemplate()
    {
        return 'AppBundle:Block:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockData()
    {

        return $this->pageBlockData;
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockAdminTemplate()
    {
        return 'AppBundle:Admin/Block:admin_container.html.twig';
    }

    public function GetPageBlockSetingsData()
    {
        if(empty($this->databaseContext)){
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "SELECT * FROM attribute_set ORDER BY attribute_set_code ASC";
        $attributeSets = $this->databaseContext->getAll($q);

        return array(
            'entity' => $this->pageBlock,
            'attribute_sets' => $attributeSets,
            'managed_entity_type' => "page_block",
            'show_add_button' => 1,
            'show_content' => 1
        );
    }

    public function SavePageBlockSettings($data)
    {
        $blockManager = $this->container->get('block_manager');

        if (isset($data["attributeSet"])) {
            $attributeSetContext = $this->container->get('attribute_set_context');
            $attributeSet = $attributeSetContext->getById($data["attributeSet"]);
            $this->pageBlock->setAttributeSet($attributeSet);
            $this->pageBlock->setEntityType($attributeSet->getEntityType());
        }

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);

        if (!empty($data["content"])) {

            /** @var PageBlockContext $pageBlockContext */
            $pageBlockContext = $this->getContainer()->get("page_block_context");

            $contentBlocks = json_decode($data["content"], true);

            $newContent = array();

            foreach ($contentBlocks as $key => $contentBlock) {

                /** @var PageBlock $pageBlock */
                $pageBlock = $pageBlockContext->getById($contentBlock["id"]);

                if (!empty($pageBlock) && isset($contentBlock["children"])) {
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

        /** SAVE NE RADI PA JE ZATO ISKLJUCENO */
        //$this->pageBlock->setContent($data["content"]);

        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }

    public function isVisible()
    {
        //Check permission
        return true;
    }
}
