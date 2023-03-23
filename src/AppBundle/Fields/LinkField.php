<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use Doctrine\Common\Inflector\Inflector;

class LinkField extends AbstractField implements FieldInterface
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

    public function setEntityValueFromArray(array $array)
    {
        $url = "";
        $title = "";

        if (isset($array[$this->attribute->getAttributeCode()])) {
            $url = $array[$this->attribute->getAttributeCode()];
        }

        if (isset($array[$this->attribute->getAttributeCode()."_title"])) {
            $title = $array[$this->attribute->getAttributeCode()."_title"];
        } else {
            $title = $array[$$this->attribute->getAttributeCode()];
        }

        $val = array("url" => $url, "title" => $title);
        $val = json_encode($val);

        $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $val);
    }
}
