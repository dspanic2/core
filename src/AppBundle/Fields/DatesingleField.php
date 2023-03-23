<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Helpers\DatetimeHelper;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use Doctrine\Common\Inflector\Inflector;

class DatesingleField extends AbstractField implements FieldInterface
{

    public function GetListViewFormattedValue()
    {
        $value = $this->GetListViewValue();

        if (empty($value)) {
            return false;
        }

        $format = "d/m/Y";
        if (!empty($this->attribute->getFrontendDisplayFormat())) {
            $format = $this->attribute->getFrontendDisplayFormat();
        }

        return date_format($value, $format);
    }

    public function GetFormFormattedValue()
    {
        $getter = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());

        $format = "d/m/Y";
        if (!empty($this->attribute->getFrontendDisplayFormat())) {
            $format = $this->attribute->getFrontendDisplayFormat();
        }

        $entity = $this->GetEntity();

        /**
         * If new check for default
         */
        if (empty($this->GetEntity()->getId()) && !empty($this->GetAttribute()->getDefaultValue())) {
            if ($this->GetAttribute()->getDefaultValue() == "now") {
                $value = new \DateTime("now");
            } else {
                $value = \DateTime::createFromFormat($format, $this->GetAttribute()->getDefaultValue());
            }
        } else {
            $value = $entity->{$getter}();
        }

        if (empty($value)) {
            return false;
        }

        return date_format($value, $format);
    }

    public function getInput()
    {
        return "date";
    }

    public function getBackendType()
    {
        return "date";
    }

    public function setEntityValueFromArray(array $array)
    {

        $value = $array[$this->attribute->getAttributeCode()];

        if ($value != "") {
            if (!$value instanceof \DateTime) {
                $value = DatetimeHelper::createDateFromString($value);
            }
            if (!empty($value)) {
                $value->setTime(0, 0, 0);

                $getter = EntityHelper::makeGetter($this->attribute->getAttributeCode());
                $old = $this->entity->$getter();
                if ($old != $value) {
                    $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $value);
                }
            } else {
                $this->logger->error("Missing date transformation: ".$this->attribute->getAttributeCode());
            }
        } else {
            $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), null);
        }
    }
}
