<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Context\PageBlockContext;
use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\PrivilegeManager;

class RoleFormBlock extends AbstractBaseBlock
{

    /**@var PrivilegeManager $privilegeManager */
    protected $privilegeManager;
    /**@var EntityManager $entityManager */
    protected $entityManager;

    public function GetPageBlockTemplate()
    {
        return 'AppBundle:Block:'.$this->pageBlock->getType().'.html.twig';
    }

    public function GetPageBlockData()
    {
        $this->entityManager = $this->container->get('entity_manager');
        $this->privilegeManager = $this->container->get('privilege_manager');

        $entityPrivileges = array();

        if (isset($this->pageBlockData['id'])) {
            $entity_id = $this->pageBlockData['id'];
        } else {
            $entity_id = null;
        }

        if ($entity_id == "") {
            $privilegesList = $this->privilegeManager->getAllPrivileges();
            $actionTypes = $this->privilegeManager->getActionTypes();

            //$entity = $this->entityManager->getNewEntityByAttributSetName("fixture");
        } else {
            /** @var AttributeSet $attributeSet */
            $attributeSet = $this->entityManager->getAttributeSetByCode("role");
            $entity = $this->entityManager->getEntityByEntityTypeAndId($attributeSet->getEntityType(), $entity_id);

            if (!isset($entity) || empty($entity)) {
                return false;
            }

            foreach ($entity->getPrivileges() as $privilege) {
                $entityPrivileges[$privilege->getActionCode()][$privilege->getActionType()->getId()] = 1;
            }

            $privilegesList = $this->privilegeManager->getAllPrivileges();
            $actionTypes = $this->privilegeManager->getActionTypes();
        }

        $this->pageBlockData["privileges_list"] = $privilegesList;
        $this->pageBlockData["action_types"] = $actionTypes;
        $this->pageBlockData["entity_privileges"] = $entityPrivileges;

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
        $attributeSetContext = $this->container->get('attribute_set_context');
        $attributeSets = $attributeSetContext->getBy(array('attributeSetCode' => 'role'));

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

        $this->pageBlock->setDataAttributes($data["dataAttributes"]);

        return $blockManager->save($this->pageBlock);
    }
}
