<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Interfaces\Fields\FieldInterface;
use Doctrine\Common\Inflector\Inflector;

class CheckboxField extends AbstractField implements FieldInterface
{

    public function getBackendType()
    {
        return "bool";
    }

    public function setEntityValueFromArray(array $array)
    {
        $value = $array[$this->attribute->getAttributeCode()];

        if (empty($array[$this->attribute->getAttributeCode()])) {
            $value = false;
        } else {
            $value = boolval($value);
        }
        $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $value);
    }
}
