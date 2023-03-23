<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use Doctrine\Common\Inflector\Inflector;

class RepeatEventField extends AbstractField implements FieldInterface
{

    public function GetFormFormattedValue()
    {
        $getter = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());

        $entity = $this->GetEntity();

        $value = $entity->{$getter}();
        if (!empty($value)) {
            $value = json_decode($value, true);
        }


        return $value;
    }

    public function GetListViewFormattedValue()
    {
        $getter = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());

        $entity = $this->GetEntity();

        $value = $entity->{$getter}();
        if (!empty($value)) {
            $value = json_decode($value, true);
        }
        return $value;
    }

    public function getBackendType()
    {
        return "varchar";
    }

    public function setEntityValueFromArray(array $array)
    {

        if (isset($array[$this->attribute->getAttributeCode()])) {
            if ($array[$this->attribute->getAttributeCode()] === 0) {
                if ($this->entity->getId() == null) {
                    $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), null);
                }
            } else {
                if ($this->entity->getId() == null && isset($array["repeat_event"]) && $array["repeat_event"] == 1) {
                    $repeat_event = isset($array["repeat_event"]) ? $array["repeat_event"] : 0;
                    $repeat_type = isset($array["repeat_type"]) ? $array["repeat_type"] : "";
                    $repeat_interval = isset($array["repeat_interval"]) ? $array["repeat_interval"] : "";
                    $ending_condition = isset($array["ending_condition"]) ? $array["ending_condition"] : "";
                    $repeat_end_date = isset($array["repeat_end_date"]) ? $array["repeat_end_date"] : "";
                    $repeat_number_of_occurances = isset($array["repeat_number_of_occurances"]) ? $array["repeat_number_of_occurances"] : "";
                    $repeat_by = isset($array["repeat_by"]) ? $array["repeat_by"] : "";
                    $repeat_on_day = isset($array["repeat_on_day"]) ? $array["repeat_on_day"] : "";

                    $val = array("repeat_type" => $repeat_type,
                        "repeat_event" => $repeat_event,
                        "repeat_interval" => $repeat_interval,
                        "ending_condition" => $ending_condition,
                        "repeat_end_date" => $repeat_end_date,
                        "repeat_number_of_occurances" => $repeat_number_of_occurances,
                        "repeat_by" => $repeat_by,
                        "repeat_on_day" => $repeat_on_day
                    );
                    $val = json_encode($val);
                    $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $val);
                }
            }
        } else {
            $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), null);
        }
    }
}
