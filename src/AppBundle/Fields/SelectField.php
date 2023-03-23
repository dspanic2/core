<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Constants\SearchFilterOperations;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\PageBlockContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\StringHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use Doctrine\Common\Inflector\Inflector;

class SelectField extends AbstractField implements FieldInterface
{
    public function GetListViewFormattedValue()
    {
        return $this->GetListViewValue();
    }

    /**
     * @return bool|string
     * @throws \Twig\Error\Error
     */
    public function GetListViewHtml()
    {
        //TODO check if page exists

        return $this->twig->render($this->GetListViewTemplate(), array('width' => $this->GetListViewAttribute()->getColumnWidth(), 'value' => $this->GetListViewFormattedValue(), 'fieldClass' => $this->GetFieldClass(), 'entity' => $this->GetParentEntity(), 'showLink' => $this->GetAttribute()->getUseLookupLink()));
    }

    /**
     * @return string
     * @throws \Twig\Error\Error
     */
    public function GetFormFieldHtml()
    {


        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get("entity_manager");
        $attributeSet = $this->GetAttribute()->getLookupAttributeSet();

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", SearchFilterOperations::EQUAL, 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $entities = $entityManager->getEntitiesByAttributeSetAndFilter($attributeSet, $compositeFilters);

        if ((empty($this->GetEntity()) || empty($this->GetEntity()->getId())) && !$this->GetAttribute()->getFrontendDisplayOnNew()) {
            return "";
        } elseif ($this->GetFormType() == "form" && !empty($this->GetEntity()) && !empty($this->GetEntity()->getId()) && !$this->GetAttribute()->getFrontendDisplayOnUpdate()) {
            return "";
        } elseif ($this->GetFormType() == "view" && !empty($this->GetEntity()) && !empty($this->GetEntity()->getId()) && !$this->GetAttribute()->getFrontendDisplayOnView()) {
            return "";
        }

        return $this->twig->render($this->GetFormTemplate(),
            array(
                'attribute' => $this->GetAttribute(),
                'lookupAttribute' => Inflector::camelize($this->GetAttribute()->getLookupAttribute()->getAttributeCode()),
                'entity' => $this->GetEntity(),
                'options' => $entities,
                'value' => $this->GetFormFormattedValue(),
                'formType' => $this->GetFormType()));
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
        return "select";
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

            $attributeSets = $attributeSetContext->getBy(array("entityType" => $entityType));
            if (empty($attributeSets)) {
                continue;
            }

            $attributes = $attributeContext->getBy(array("entityType" => $entityType), array("attributeCode" => "asc"));

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

        return $this->twig->render("AppBundle:Admin/Fields:select.html.twig", array(
            'entity' => $attribute,
            'entity_types' => $entity_types
        ));
    }


    /**
     * @return bool|string
     * @throws \Twig\Error\Error
     */
    public function GetListViewHeaderHtml()
    {

        if (!$this->GetListViewAttribute()->getDisplay()) {
            return false;
        }

        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get("entity_manager");


        $attributeSet = $this->GetAttribute()->getLookupAttributeSet();
        $lookupAttributeCode = Inflector::camelize($this->GetAttribute()->getLookupAttribute()->getAttributeCode());
        $getter = EntityHelper::makeGetter($lookupAttributeCode);


        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", SearchFilterOperations::EQUAL, 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $entities = $entityManager->getEntitiesByAttributeSetAndFilter($attributeSet, $compositeFilters);

        $optionsArray = array();

        foreach ($entities as $entity) {
            $optionsArray[] = array("value" => "" . $entity->getId(), "label" => $entity->{$getter}());
        }


        return $this->twig->render($this->GetListViewHeaderTemplate(),
            array(
                'width' => $this->GetListViewAttribute()->getColumnWidth(),
                'field' => Inflector::camelize($this->GetAttribute()->getAttributeCode()),
                'options' => json_encode($optionsArray),
                'show_filter' => $this->GetListViewAttribute()->getListView()->getShowFilter(),
                'label' => $this->GetListViewAttribute()->getLabel()));
    }


    public function setEntityValueFromArray(array $array)
    {
        $value = $array[$this->attribute->getAttributeCode()];

        $doctrineEntityManager = $this->container->get("doctrine.orm.entity_manager");


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
