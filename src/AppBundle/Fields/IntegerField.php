<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Entity\Attribute;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use Doctrine\Common\Inflector\Inflector;

class IntegerField extends AbstractField implements FieldInterface
{

    public function GetListViewFormattedValue()
    {
        $value = $this->GetListViewValue();

        if (empty($value) && $value != 0) {
            return false;
        }

        $format = "0";

        return number_format($value, $format, ".", "");
    }

    public function GetFormFormattedValue()
    {
        $getter = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());

        $format = "0";

        $entity = $this->GetEntity();

        $value = $entity->{$getter}();
        if (empty($value)) {
            $value = 0;
        }

        /**
         * If new check for default
         */
        if (empty($this->GetEntity()->getId()) && !empty($this->GetAttribute()->getDefaultValue())) {
            $value = $this->GetAttribute()->getDefaultValue();
        }

        return number_format($value, $format, ".", "");
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

        /** @var Attribute $attribute */
        $attribute = $this->GetAttribute();

        if (empty($attribute->getValidator())) {
            $defaultValidator = $_ENV["INTEGER_VALIDATOR"] ?? null;

            if (!empty($defaultValidator)) {
                $attribute->setValidator($defaultValidator);
                $this->SetAttribute($attribute);
            }
        }

        return $this->twig->render($this->GetFormTemplate(), array('attribute' => $this->GetAttribute(), 'value' => $this->GetFormFormattedValue(), 'formType' => $this->GetFormType(),'entity_id'=>$this->entity->getId()));
    }

    public function getBackendType()
    {
        return "integer";
    }

    public function setEntityValueFromArray(array $array)
    {
        $value = $array[$this->attribute->getAttributeCode()];

        if (empty($array[$this->attribute->getAttributeCode()])) {
            $value = 0;
        } else {
            $value = intval($value);
        }
        $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $value);
    }
}
