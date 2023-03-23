<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\PageBlockContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeSet;
use AppBundle\Managers\HelperManager;
use AppBundle\Entity\EntityType;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use AppBundle\Managers\EntityManager;
use Doctrine\Common\Inflector\Inflector;

class AutocompleteField extends AbstractField implements FieldInterface
{
    protected $helperManager;

    public function GetListViewFormattedValue()
    {
        return $this->GetListViewValue();
    }

    public function GetListViewHtml()
    {
        return $this->twig->render($this->GetListViewTemplate(), array(
            'width' => $this->GetListViewAttribute()->getColumnWidth(),
            'value' => $this->GetListViewFormattedValue(),
            'valueId' => $this->GetEntity()->getId() ?? 0,
            'fieldClass' => $this->GetFieldClass(),
            'entity' => $this->GetParentEntity(),
            'showLink' => $this->GetAttribute()->getUseLookupLink(),
            "attribute" => $this->GetListViewAttribute()->getAttribute(),
            'field' => $this,
            'listMode' => $this->getListMode(),
        ));
    }

    public function GetFormFieldHtml()
    {
        if ((empty($this->GetEntity()) || empty($this->GetEntity()->getId())) && !$this->GetAttribute()->getFrontendDisplayOnNew()) {
            return "";
        } elseif ($this->GetFormType() == "form" && !empty($this->GetEntity()) && !empty($this->GetEntity()->getId()) && !$this->GetAttribute()->getFrontendDisplayOnUpdate()) {
            return "";
        } elseif ($this->GetFormType() == "view" && !empty($this->GetEntity()) && !empty($this->GetEntity()->getId()) && !$this->GetAttribute()->getFrontendDisplayOnView()) {
            return "";
        }

        return $this->twig->render($this->GetFormTemplate(), array('attribute' => $this->GetAttribute(), 'entity' => $this->GetEntity(), 'value' => $this->GetFormFormattedValue(), 'formType' => $this->GetFormType()));
    }

    public function GetFormFormattedValue()
    {
        $ret = array();


        $manager = "autocomplete_manager";

        if ($this->GetAttribute()->getFrontendModel() != "default") {
            $manager = $this->GetAttribute()->getFrontendModel()."_".$manager;
        }


        $autocompleteManager = $this->container->get($manager);

        $entity = $this->GetEntity();

        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get("entity_manager");

        /**
         * Get parent entity if entity is empty and parent exists
         */
        $parent = $this->GetParent();
        $parentAttributeSet = null;
        $parentEntityType = null;
        if (!empty($parent["attributeSetCode"])) {
            /** @var AttributeSet $parentAttributeSet */
            $parentAttributeSet = $entityManager->getAttributeSetByCode($parent["attributeSetCode"]);
            $parentEntityType = $parentAttributeSet->getEntityType();
        }

        if (empty($entity->getId()) && !empty($parent) && !empty($parentAttributeSet) && !empty($parentEntityType) && ($this->GetAttribute()->getLookupAttributeSet()->getAttributeSetCode() == $parentAttributeSet->getAttributeSetCode() || $this->GetAttribute()->getLookupEntityType()->getEntityTypeCode() == $parentEntityType->getEntityTypeCode())) {
            $value = $entityManager->getEntityByEntityTypeAndId($parentEntityType, $parent["id"]);
        } elseif (empty($entity->getId()) && !empty($this->GetAttribute()->getDefaultValue())) {
            $defaultValue = $this->GetAttribute()->getDefaultValue();
            if ($defaultValue == "user_id") {
                if(empty($this->helperManager)){
                    $this->helperManager = $this->container->get("helper_manager");
                }
                $defaultValue = $this->helperManager->getCurrentUser();
            }

            $value = $entityManager->getEntityByEntityTypeAndId($this->GetAttribute()->getLookupEntityType(), $defaultValue);
        } else {
            $getters = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());
            $getters = substr($getters, 0, -2);
            $value = $entity->{$getters}();
        }

        $this->SetParentEntity($value);

        if ($value != null) {
            // dump($value,$this->GetAttribute());die;
            $ret["id"] = $value->getId();
            $ret["lookup_value"] = $this->twig->render(
                $autocompleteManager->getTemplateForAddedEntities(),
                array('field_data' => $value, 'attribute' => Inflector::camelize($this->GetAttribute()->getLookupAttribute()->getAttributeCode()), 'showLink' => $this->GetAttribute()->getUseLookupLink())
            );
        }


        return $ret;
    }

    public function GetAdvancedSearchValue()
    {

        $valueArray = array();
        if ($this->GetAttribute()->getFrontendModel() == "default") {
            $entityIds = $this->advancedSearchValue;
            $value = array();

            if (!empty($entityIds)) {
                $entityIds = explode(",", $entityIds);

                /** @var EntityManager $entityManager */
                $entityManager = $this->container->get("entity_manager");

                foreach ($entityIds as $entityId) {
                    $value[] = $entityManager->getEntityByEntityTypeAndId($this->GetAttribute()->getLookupEntityType(), $entityId);
                }
            }

            if (!empty($value)) {
                foreach ($value as $val) {
                    $ret = array();
                    $ret["id"] = $val->getId();
                    $ret["lookup_value"] = $this->twig->render(
                        "AppBundle:Form/AutocompleteTemplates:default.html.twig",
                        array('field_data' => $val, 'attribute' => Inflector::camelize($this->GetAttribute()->getLookupAttribute()->getAttributeCode()))
                    );
                    $valueArray[] = $ret;
                }
            }
        }

        return $valueArray;
    }

    public function getInput()
    {
        return "lookup";
    }

    public function getBackendType()
    {
        return "lookup";
    }

    public function getCustomAdmin(Attribute $attribute = null)
    {
        /** @var AttributeContext $attributeContext */
        $attributeContext = $this->container->get("attribute_context");
        /** @var AttributeSetContext $attributeSetContext */
        $attributeSetContext = $this->container->get("attribute_set_context");
        /** @var PageBlockContext $blockContext */
        $blockContext = $this->container->get("page_block_context");
        /** @var EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get("entity_type_context");
        $entityAttributeContext = $this->container->get('entity_attribute_context');
        $entityTypes = $entityTypeContext->getBy(array(), array("entityTypeCode" => "asc"));

        $entity_types = array();

        /** @var EntityType $entityType */
        foreach ($entityTypes as $entityType) {
            //$attributeSets = $attributeContext->getBy(Array("entityType" => $entityType->getId()), Array("attributeCode" => "asc"));

            $attributeSets = $attributeSetContext->getBy(array("entityType" => $entityType));
            if (empty($attributeSets)) {
                continue;
            }

            $attributes = $attributeContext->getBy(array("entityType" => $entityType), array("attributeCode" => "asc"));

            //$entity_attribute_types = $entityAttributeContext->getBy(Array("entityType" => $entityType, "attributeSet" => $attributeSets[0]));

            foreach ($attributeSets as $attributeSet) {
                $blocks = $blockContext->getBy(array("type" => "edit_form", "attributeSet" => $attributeSet));

                /** @var Attribute $lookupAttribute */
                foreach ($attributes as $lookupAttribute) {
                    $entity_types[$entityType->getId()]["id"] = $entityType->getId();
                    $entity_types[$entityType->getId()]["name"] = $entityType->getEntityTypeCode();
                    $entity_types[$entityType->getId()]["attribute_sets"][$attributeSet->getId()]["name"] = $attributeSet->getAttributeSetCode();
                    $entity_types[$entityType->getId()]["attribute_sets"][$attributeSet->getId()]["attributes"][$lookupAttribute->getId()]["name"] = $lookupAttribute->getFrontendLabel();
                    if (!empty($blocks)) {
                        foreach ($blocks as $block) {
                            $entity_types[$entityType->getId()]["attribute_sets"][$attributeSet->getId()]["blocks"][$block->getId()]["name"] = $block->getTitle();
                        }
                    }
                }
            }
        }

        return $this->twig->render("AppBundle:Admin/Fields:autocomplete.html.twig", array(
            'entity' => $attribute,
            'entity_types' => $entity_types
        ));
    }

    public function setEntityValueFromArray(array $array)
    {
        $value = $array[$this->attribute->getAttributeCode()];

        $doctrineEntityManager = $this->container->get("doctrine.orm.entity_manager");

        if (strpos($this->attribute->getFrontendType(), 'autocomplete') !== false || strpos($$this->attribute->getFrontendType(), 'lookup_image') !== false) {
            $lookupEntityType = $this->attribute->getLookupEntityType();

            $setter = EntityHelper::makeSetter(str_replace("_id", "", $this->attribute->getAttributeCode()));
            if ($value != "") {
                $val = intval($value);
                $lookupClass = StringHelper::format('{0}\Entity\{1}', $lookupEntityType->getBundle(), $lookupEntityType->getEntityModel());
                $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $val);
                $this->entity->{$setter}($doctrineEntityManager->getReference($lookupClass, $val));
            } else {
                $this->entity->{$setter}(null);
            }
        }
    }
}
