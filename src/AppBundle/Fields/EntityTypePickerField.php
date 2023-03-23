<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Entity\EntityType;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use Doctrine\Common\Inflector\Inflector;

class EntityTypePickerField extends AbstractField implements FieldInterface
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
            'fieldClass' => $this->GetFieldClass(),
            'entity' => $this->GetParentEntity(),
            'showLink' => $this->GetAttribute()->getUseLookupLink()
        ));
    }

    public function GetFormFieldHtml()
    {

        if (empty($this->GetEntity()->getId()) && !$this->GetAttribute()->getFrontendDisplayOnNew()) {
            return "";
        } elseif (!empty($this->GetEntity()->getId()) && !$this->GetAttribute()->getFrontendDisplayOnUpdate()) {
            return "";
        }
        //TODO fali on view

        /** @var EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get("entity_type_context");
        $entityTypes = $entityTypeContext->getBy(array(), array("entityTypeCode" => "asc"));

        return $this->twig->render($this->GetFormTemplate(), array(
            'attribute' => $this->GetAttribute(),
            'entity' => $this->GetEntity(),
            'value' => $this->GetFormFormattedValue(),
            'formType' => $this->GetFormType(),
            'options' => $entityTypes,
        ));
    }

    public function getInput()
    {
        return "select";
    }

    public function getBackendType()
    {
        return "varchar";
    }


    /**
     * @return bool|string
     * @throws \Twig\Error\Error
     */
    public function GetListViewHeaderHtml()
    {
        /** @var EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get("entity_type_context");
        $entityTypes = $entityTypeContext->getBy(array(), array("entityTypeCode" => "asc"));

        /** @var EntityType $entityType */
        foreach ($entityTypes as $entityType) {
            $optionsArray[] = array("value" => $entityType->getEntityTypeCode(), "label" => $entityType->getEntityTypeCode());
        }

        return $this->twig->render($this->GetListViewHeaderTemplate(),
            array(
                'width' => $this->GetListViewAttribute()->getColumnWidth(),
                'field' => Inflector::camelize($this->GetAttribute()->getAttributeCode()),
                'options' => json_encode($optionsArray),
                'show_filter' => $this->GetListViewAttribute()->getListView()->getShowFilter(),
                'label' => $this->GetListViewAttribute()->getLabel()));
    }

    public function GetListViewValue()
    {

        $field = $this->GetListViewAttribute()->getField();

        $entity = $this->GetEntity();

        $getter = EntityHelper::makeGetter($field);

        return $entity->{$getter}();
    }
}
