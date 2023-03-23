<?php

namespace AppBundle\Interfaces\Managers;

use AppBundle\Entity\AttributeSet;

interface FormManagerInterface
{
    public function getFormAttributes($typeName);

    public function getFormModel(AttributeSet $attributeSet, $id, $formType, $attributeGroupId = null);

    public function saveFormModel($typeName, $post);

    public function deleteFormModel($typeName, $id);

    //public function getEntityType($typeName);

    public function getTemplate($typeName, $viewName);

    /**Occures after the entity is saved in database override for post processing */
/*    public function postSave($entity);*/
}
