<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use Doctrine\Common\Inflector\Inflector;

class CheckboxWebsiteField extends AbstractField implements FieldInterface
{

    protected $databaseContext;

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
            if(!empty($value)){
                $value = json_decode($value,true);
            }
        }

        return $value;
    }

    public function GetFormFieldHtml()
    {

        if (empty($this->GetEntity()->getId()) && !$this->GetAttribute()->getFrontendDisplayOnNew()) {
            return "";
        } elseif (!empty($this->GetEntity()->getId()) && !$this->GetAttribute()->getFrontendDisplayOnUpdate()) {
            return "";
        }

        $websites = $this->getWebsites();

        return $this->twig->render($this->GetFormTemplate(), array('attribute' => $this->GetAttribute(), 'value' => $this->GetFormFormattedValue(), 'formType' => $this->GetFormType(), 'entity_id' => $this->entity->getId(), 'websites' => $websites));
    }

    public function GetListViewFormattedValue()
    {
        $getter = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());

        $entity = $this->GetEntity();

        $value = $entity->{$getter}();
        if (!empty($value)) {
            $value = array_values($value);
        }

        return $value;
    }

    public function getBackendType()
    {
        return "json";
    }

    public function setEntityValueFromArray(array $array)
    {
        $attributeCodeArray = $this->attribute->getAttributeCode()."_checkbox";
        
        if (isset($array[$this->attribute->getAttributeCode()])) {
            foreach($array[$this->attribute->getAttributeCode()] as $key => $val){
                if(!isset($array[$attributeCodeArray][$key])){
                    $array[$this->attribute->getAttributeCode()][$key] = 0;
                }
                else{
                    $array[$this->attribute->getAttributeCode()][$key] = 1;
                }
            }
            if (empty($array[$this->attribute->getAttributeCode()])) {
                $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), null);
            } else {
                $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $array[$this->attribute->getAttributeCode()]);
            }
        } else {
            $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), null);
        }
    }
}
