<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\EntityAttribute;
use AppBundle\Entity\EntityType;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductImagesEntity;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\PersistentCollection;

class MultiselectField extends AbstractField
{

    private $parentEntityList;
    private $getter;

    public function GetListViewValue()
    {
        $manager = "autocomplete_manager";

        if ($this->GetAttribute()->getFrontendModel() != "default") {
            $manager = $this->GetAttribute()->getFrontendModel() . "_" . $manager;
        }

        $autocompleteManager = $this->container->get($manager);

        $field = $this->GetListViewAttribute()->getField();

        $entity = $this->GetEntity();

        $getters = EntityHelper::getPropertyAccessor($field);

        $value = $entity->{$getters[0]}();

        $count = count($getters);
        $count = $count - 2;

        $lookupAttribute = ($this->GetAttribute()->getLookupAttribute());
        $filterAttribute = $lookupAttribute->getLookupAttribute();

        if ($count == 0) {
            $this->SetParentEntity($value);
        }
        $valueArray = array();

        foreach ($getters as $key => $getter) {
            if ($key == 0) {
                continue;
            }
            if ($value instanceof PersistentCollection) {
                foreach ($value as $item) {
                    if (empty($filterAttribute)) {
                        continue;
                    }
//                    dump($item);die;
                    $ret["id"] = $item->getId();
                    $ret["lookup_value"] = $this->twig->render(
                        $autocompleteManager->getTemplate(),
                        array('field_data' => $item, 'attribute' => Inflector::camelize($filterAttribute->getAttributeCode()))
                    );
                    $valueArray[] = $ret;
                }
            } else {
                if (!empty($value) && is_object($value)) {
                    $value = $value->{$getter}();
                    if ($key == $count) {
                        //TODO ovdje mozda treba prethodni getter
                        $this->SetParentEntityList($value);
                        $this->SetGetter($getters[$key + 1]);
                    }
                } else {
                    $value = "";
                }
            }
        }

        $value = $valueArray;

        return $value;
    }

    public function SetGetter($getter)
    {
        $this->getter = $getter;
    }

    public function GetGetter()
    {
        return $this->getter;
    }

    public function SetParentEntityList($parentEntityList)
    {
        $this->parentEntityList = $parentEntityList;
    }

    public function GetParentEntityList()
    {

        return $this->parentEntityList;
    }

    public function GetListViewHtml()
    {
        //TODO check if page exists
        $documentData = [];
        if (!empty($this->GetAttribute()->getLookupEntityType()) && $this->GetAttribute()->getLookupEntityType()->getIsDocument()) {
            $documentData["documents"] = [];

            if (empty($this->attributeContext)) {
                $this->attributeContext = $this->container->get("attribute_context");
            }

            if (empty($this->fileManager)) {
                $this->fileManager = $this->container->get("file_manager");
            }

            /** @var ProductEntity $entity */
            $entity = $this->GetEntity();

            /** @var Attribute $fileAttribute */
            $fileAttribute = $this->attributeContext->getAttributeByCode("file", $this->GetAttribute()->getLookupEntityType());

            $documentData["folder"] = $this->fileManager->getTargetPath($fileAttribute->getFolder(), $entity->getId());
            $documentData["file_attribute"] = $fileAttribute;
            $documentData["related_entity"] = $entity;
            $documentData["lookup_entity_type"] = $this->GetAttribute()->getLookupEntityType();
            $documentData["lookup_attribute_set"] = $this->GetAttribute()->getLookupAttributeSet();
            $documentData["document_parent_attribute_id"] = null;

            $attributes = $this->GetAttribute()->getLookupEntityType()->getEntityAttributes();
            /** @var EntityAttribute $entityAttribute */
            foreach ($attributes as $entityAttribute) {
                $attribute = $entityAttribute->getAttribute();
                if (!empty($attribute->getLookupEntityType()) && $attribute->getLookupEntityType()->getId() == $entity->getEntityType()->getId()) {
                    $documentData["document_parent_attribute_id"] = $attribute->getId();
                    break;
                }
            }

            $getters = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());

            // Ovo dvoje se razlikuje
            $relatedEntities = $entity->{$getters}();
            $relatedEntities = $entity->getImages();
//            dump(count($entity->getImages()));
//            dump(count($entity->getProductImages()));
//            die;

            if (!empty($relatedEntities)) {
                $fileGetter = EntityHelper::makeGetter($fileAttribute->getAttributeCode());
                foreach ($relatedEntities as $document) {
                    $file = $document->{$fileGetter}();
                    if (stripos($documentData["folder"], strval($entity->getId())) !== false && stripos($file, strval($entity->getId())) !== false) {
                        $file = str_ireplace("{$entity->getId()}/", "", $file);
                    }
                    $documentData["documents"][] = [
                        "file" => $file,
                        "id" => $document->getId(),
                        "selected" => $document->getSelected(),
                    ];
                }
            }
        }
        return $this->twig->render($this->GetListViewTemplate(), array(
            'width' => $this->GetListViewAttribute()->getColumnWidth(),
            'value' => $this->GetListViewFormattedValue(),
            'fieldClass' => $this->GetFieldClass(),
            'entities' => $this->GetParentEntityList(),
            'getter' => $this->GetGetter(),
            'showLink' => $this->GetAttribute()->getUseLookupLink(),
            'document' => $documentData,
        ));
    }

    public function GetListViewHeaderHtml()
    {

        if (!$this->GetListViewAttribute()->getDisplay()) {
            return false;
        }

        return $this->twig->render($this->GetListViewHeaderTemplate(), array(
            'width' => $this->GetListViewAttribute()->getColumnWidth(),
            'field' => $this->GetListViewAttribute()->getField(),
            'show_filter' => $this->GetListViewAttribute()->getListView()->getShowFilter(),
            'label' => $this->GetListViewAttribute()->getLabel(),
            'is_document' => (!empty($this->GetAttribute()->getLookupEntityType()) && $this->GetAttribute()->getLookupEntityType()->getIsDocument()),
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
        $valueArray = [];
        // if ($this->GetAttribute()->getFrontendModel() == "default") {
        $getters = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());

        $entity = $this->GetEntity();

        $relatedEntities = $entity->{$getters}();

        $lookupAttribute = ($this->GetAttribute()->getLookupAttribute());
        $filterAttribute = $lookupAttribute->getLookupAttribute();


        if ($relatedEntities != null && !empty($relatedEntities)) {
            $manager = "autocomplete_manager";

            if ($this->GetAttribute()->getFrontendModel() != "default") {
                $manager = $this->GetAttribute()->getFrontendModel() . "_" . $manager;
            }

            $autocompleteManager = $this->container->get($manager);

            foreach ($relatedEntities as $relatedEntity) {
                $ret["id"] = $relatedEntity->getId();
                $ret["lookup_value"] = $this->twig->render(
                    $autocompleteManager->getTemplateForAddedEntities(),
                    array('field_data' => $relatedEntity, 'attribute' => Inflector::camelize($filterAttribute->getAttributeCode()), 'showLink' => $this->GetAttribute()->getUseLookupLink())
                );
                $valueArray[] = $ret;
            }
        }
        //}

        return $valueArray;
    }

    public function GetAdvancedSearchValue()
    {
        $valueArray = array();

        $entityIds = $this->advancedSearchValue;
        $value = array();

        if (!empty($entityIds)) {
            $entityIds = explode(",", $entityIds);

            /** @var EntityManager $entityManager */
            $entityManager = $this->container->get("entity_manager");

            foreach ($entityIds as $entityId) {
                $value[] = $entityManager->getEntityByEntityTypeAndId($this->GetAttribute()->getLookupAttribute()->getLookupEntityType(), $entityId);
            }
        }

        if (!empty($value)) {
            $manager = "autocomplete_manager";

            if ($this->GetAttribute()->getFrontendModel() != "default") {
                $manager = $this->GetAttribute()->getFrontendModel() . "_" . $manager;
            }

            $autocompleteManager = $this->container->get($manager);

            foreach ($value as $val) {
                $ret = array();
                $ret["id"] = $val->getId();
                $ret["lookup_value"] = $this->twig->render(
                    $autocompleteManager->getTemplate(),
                    array('field_data' => $val, 'attribute' => Inflector::camelize($this->GetAttribute()->getLookupAttribute()->getLookupAttribute()->getAttributeCode()))
                );
                $valueArray[] = $ret;
            }
        }

        return $valueArray;
    }

    public function GetAdvancedSearchTemplate()
    {

        return parent::GetAdvancedSearchTemplate(); // TODO: Change the autogenerated stub
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
        /** @var AttributeSetContext $attributeSetContext */
        $attributeSetContext = $this->container->get("attribute_set_context");
        /** @var PageBlockContext $blockContext */
        $blockContext = $this->container->get("page_block_context");
        /** @var EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get("entity_type_context");
        $entityAttributeContext = $this->container->get('entity_attribute_context');
        $entityTypes = $entityTypeContext->getBy(array(), array("entityTypeCode" => "asc"));

        $entity_types = array();
        foreach ($entityTypes as $entityType) {
            $attributeSets = $attributeSetContext->getBy(array("entityType" => $entityType), array("id" => "asc"));
            if (empty($attributeSets)) {
                continue;
            }
            $entity_attribute_types = $entityAttributeContext->getBy(array("entityType" => $entityType, "attributeSet" => $attributeSets[0]));

            foreach ($attributeSets as $attributeSet) {
                $blocks = $blockContext->getBy(array("type" => "edit_form", "attributeSet" => $attributeSet));

                foreach ($entity_attribute_types as $entity_attribute_type) {
                    $entity_types[$entity_attribute_type->getEntityType()->getId()]["id"] = $entity_attribute_type->getEntityType()->getId();
                    $entity_types[$entity_attribute_type->getEntityType()->getId()]["name"] = $entity_attribute_type->getEntityType()->getEntityTypeCode();
                    $entity_types[$entity_attribute_type->getEntityType()->getId()]["attribute_sets"][$attributeSet->getId()]["name"] = $attributeSet->getAttributeSetCode();
                    $entity_types[$entity_attribute_type->getEntityType()->getId()]["attribute_sets"][$attributeSet->getId()]["attributes"][$entity_attribute_type->getAttribute()->getId()]["name"] = $entity_attribute_type->getAttribute()->getFrontendLabel();
                    if (!empty($blocks)) {
                        foreach ($blocks as $block) {
                            $entity_types[$entity_attribute_type->getEntityType()->getId()]["attribute_sets"][$attributeSet->getId()]["blocks"][$block->getId()]["name"] = $block->getTitle();
                        }
                    }
                }
            }
        }

        return $this->twig->render("AppBundle:Admin/Fields:multiselect.html.twig", array(
            'entity' => $attribute,
            'entity_types' => $entity_types
        ));
    }

    public function setEntityValueFromArray(array $array)
    {
        //do nothing, just dont delete existing multiselects
    }

    public function getType()
    {
        return "multiselect";
    }
}
