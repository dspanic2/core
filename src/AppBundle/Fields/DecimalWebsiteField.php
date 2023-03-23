<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Entity\Attribute;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Helpers\NumberHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use Doctrine\Common\Inflector\Inflector;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class DecimalWebsiteField extends AbstractField implements FieldInterface
{
    public function GetListViewFormattedValue()
    {
        $value = $this->GetListViewValue();
        if (empty($value)) {
            return false;
        }

        return NumberHelper::formatDecimal($value, $this->attribute->getFrontendDisplayFormat());
    }

    /**
     * @return mixed|string
     */
    public function GetFormFormattedValue()
    {
        $getter = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());
        $value = $this->GetEntity()->{$getter}();

        /**
         * If new check for default
         */
        if (empty($this->GetEntity()->getId()) && !empty($this->GetAttribute()->getDefaultValue())) {
            $value = $this->GetAttribute()->getDefaultValue();
            if (!empty($value)) {
                $value = json_decode($value, true);
            }
        }

        if (!empty($value)){
            foreach ($value as $k => $val){
                if(empty($val)){
                    $val = 0;
                }
                $val = floatval($val);
                $value[$k] = NumberHelper::formatDecimal($val, $this->attribute->getFrontendDisplayFormat());
            }
        }

        return $value;

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

        $websites = $this->getWebsites();

        /** @var Attribute $attribute */
        $attribute = $this->GetAttribute();

        if (empty($attribute->getValidator())) {
            $defaultValidator = $_ENV["DECIMAL_VALIDATOR"] ?? null;

            if (!empty($defaultValidator)) {
                $attribute->setValidator($defaultValidator);
                $this->SetAttribute($attribute);
            }
        }

        return $this->twig->render($this->GetFormTemplate(), array('attribute' => $this->GetAttribute(), 'value' => $this->GetFormFormattedValue(), 'formType' => $this->GetFormType(), 'entity_id' => $this->entity->getId(), 'websites' => $websites));
    }

    public function getBackendType()
    {
        return "json";
    }

    public function setEntityValueFromArray(array $array)
    {
        if (isset($array[$this->attribute->getAttributeCode()])) {
            if ($array[$this->attribute->getAttributeCode()] === 0) {
                if ($this->entity->getId() == null) {
                    $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), null);
                }
            } else {
                foreach ($array[$this->attribute->getAttributeCode()] as $key => $value){
                    $array[$this->attribute->getAttributeCode()][$key] = NumberHelper::cleanDecimal($value, $this->attribute->getFrontendDisplayFormat());
                }
                $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $array[$this->attribute->getAttributeCode()]);
            }
        } else {
            $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), null);
        }
    }
}
