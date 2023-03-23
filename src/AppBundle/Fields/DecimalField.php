<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Entity\Attribute;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\NumberHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use Doctrine\Common\Inflector\Inflector;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class DecimalField extends AbstractField implements FieldInterface
{
    public function GetListViewFormattedValue()
    {
        $value = $this->GetListViewValue();
        if (empty($value)) {
            return false;
        }

        return NumberHelper::formatDecimal($value, $this->attribute->getFrontendDisplayFormat());
    }

    public function GetFormFormattedValue()
    {
        $getter = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());

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

        return NumberHelper::formatDecimal($value, $this->attribute->getFrontendDisplayFormat());
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
            $defaultValidator = $_ENV["DECIMAL_VALIDATOR"] ?? null;

            if (!empty($defaultValidator)) {
                $attribute->setValidator($defaultValidator);
                $this->SetAttribute($attribute);
            }
        }

        return $this->twig->render($this->GetFormTemplate(), array('attribute' => $this->GetAttribute(), 'value' => $this->GetFormFormattedValue(), 'formType' => $this->GetFormType(), 'entity_id' => $this->entity->getId()));
    }

    public function getBackendType()
    {
        return "decimal";
    }

    public function setEntityValueFromArray(array $array)
    {
        $value = $array[$this->attribute->getAttributeCode()];

        if (empty($array[$this->attribute->getAttributeCode()])) {
            $value = 0;
        } else {
            $value = NumberHelper::cleanDecimal($value, $this->attribute->getFrontendDisplayFormat());
        }

        $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $value);
    }
}
