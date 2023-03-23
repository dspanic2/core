<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\FormManager;
use ScommerceBusinessBundle\Entity\SMenuEntity;

class SmenuManager extends FormManager
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

        if (empty($attributeSet)) {
            throw new \InvalidArgumentException("Missing attribute_set");
        }

        $isNew = false;

        if ($entity_id == "") {
            $entity = $this->entityManager->getNewEntityByAttributSetName($attributeSet->getAttributeSetCode());
            $isNew = true;
        } else {
            $entity = $this->entityManager->getEntityByEntityTypeAndId($attributeSet->getEntityType(), $entity_id);
        }

        //$entity = $this->entityManager->arrayToEntity($entity, $array);

        /** @var SMenuEntity $entity */
        //$entity = $this->entityManager->saveEntity($entity);

        $entity = $this->entityManager->saveFormEntity($attributeSet, $array);

        if (!$isNew) {
            $menuItemJson = $entity->getTmpContent();

            if (!empty($menuItemJson)) {

                /** @var MenuManager $menuManager */
                $menuManager = $this->container->get("menu_manager");

                $menuManager->saveMenuItemJson($entity, $menuItemJson);
            }
        }

        return $entity;
    }
}
