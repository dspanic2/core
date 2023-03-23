<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Helpers\EntityHelper;
use Doctrine\Common\Inflector\Inflector;

class TimepickerField extends AbstractField
{
    public function GetFormFormattedValue()
    {
        $getter = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());

        $entity = $this->GetEntity();

        $value = $entity->{$getter}();

        /**
         * If new check for default
         */
        if (empty($this->GetEntity()->getId()) && !empty($this->GetAttribute()->getDefaultValue())) {
            $value = $this->GetAttribute()->getDefaultValue();
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

        return $this->twig->render($this->GetFormTemplate(), array("attribute" => $this->GetAttribute(), "value" => $this->GetFormFormattedValue(), "formType" => $this->GetFormType(), "entity_id" => $this->entity->getId()));
    }

    public function getBackendType()
    {
        return "time";
    }

    public function setEntityValueFromArray(array $array)
    {
        $value = $array[$this->attribute->getAttributeCode()];
        if ($value != "") {
            $getter = "";
            if (!$value instanceof \DateTime) {

                $date = new \DateTime();

                $meridianConversion = 0;

                if (strpos($value, "PM")) {
                    $meridianConversion = 12;
                }

                $value = str_replace("PM", "", $value);
                $value = str_replace("AM", "", $value);

                $splitHourMinute = explode(":", trim($value));

                $date->setTime($splitHourMinute[0] + $meridianConversion, $splitHourMinute[1], 0);

                $getter = EntityHelper::makeGetter($this->attribute->getAttributeCode());
                $old = $this->entity->$getter();
                if ($old != $date) {
                    $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $date);
                }
            } else {
                $old = $this->entity->$getter();
                if ($old != $value) {
                    $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $value);
                }
            }
        } else {
            $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), null);
        }
    }
}