<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Helpers\EntityHelper;

class DaterangeField extends AbstractField
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

        $value = explode(" ", $value);

        foreach ($value as $key => $v) {
            if (!empty($v)) {
                $value[$key] = date_format($v, $format);
            } else {
                $value[$key] = "";
            }
        }

        $value = implode(" ", $value);

        return trim($value);
    }

    public function GetFormFormattedValue()
    {
        $getter = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());

        $format = "d/m/Y";
        if (!empty($this->attribute->getFrontendDisplayFormat())) {
            $format = $this->attribute->getFrontendDisplayFormat();
        }

        $entity = $this->GetEntity();

        $value = explode(" ", $entity->{$getter}());

        foreach ($value as $key => $v) {
            if (!empty($v)) {
                $value[$key] = date_format($v, $format);
            } else {
                $value[$key] = "";
            }
        }

        $value = implode(" ", $value);

        return trim($value);
    }

    public function getInput()
    {
        return "date";
    }
}
