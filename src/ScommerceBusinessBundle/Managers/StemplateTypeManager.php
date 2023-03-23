<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\FormManager;
use ScommerceBusinessBundle\Entity\SFrontBlockEntity;
use ScommerceBusinessBundle\Entity\STemplateTypeEntity;

class StemplateTypeManager extends FormManager
{
    public function saveFormModel($typeName, $array)
    {
        if (isset($array['id'])) {
            $entity_id = $array['id'];
        } else {
            $entity_id = null;
        }

        /** @var AttributeSet $attributeSet */
        $attributeSet = $this->attributeSetContext->getItemByCode($typeName);

        $isNew = false;

        if (empty($entity_id)) {
            $entity = $this->entityManager->getNewEntityByAttributSetName($attributeSet->getAttributeSetCode());
            $isNew = true;
        } else {
            $entity = $this->entityManager->getEntityByEntityTypeAndId($attributeSet->getEntityType(), $entity_id);
        }

        /** @var STemplateTypeEntity $entity */
        $entity = $this->entityManager->arrayToEntity($entity, $array);

        if (!$isNew) {
            $contentBlocks = json_decode($entity->getContent(), true);

            $newContent = array();

            /** @var TemplateManager $templateManager */
            $templateManager = $this->container->get("template_manager");

            foreach ($contentBlocks as $key => $contentBlock) {

                /** @var SFrontBlockEntity $frontBlock */
                $frontBlock = $templateManager->getFrontBlockById($contentBlock["id"]);

                if (!empty($frontBlock)) {
                    $contentBlock["id"] = $frontBlock->getId();

                    if ($frontBlock->getType() == "container") {
                        $templateManager->saveFrontBlockContent($frontBlock, $contentBlock["children"]);
                    }

                    /*$block = $templateManager->getBlock($frontBlock, null);
                  $blockSettings = $block->GetBlockSetingsData();

                  if(isset($blockSettings["show_content"]) && $blockSettings["show_content"] == 1){
                    $templateManager->saveFrontBlockContent($frontBlock,$contentBlock["children"]);
                    }*/
                    unset($contentBlock["children"]);

                    $newContent[] = $contentBlock;
                }
            }

            $entity->setContent(json_encode($newContent));
        }


        $entity = $this->entityManager->saveEntity($entity);

        return $entity;
    }
}
