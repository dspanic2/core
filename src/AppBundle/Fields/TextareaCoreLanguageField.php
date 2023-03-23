<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use Doctrine\Common\Inflector\Inflector;

class TextareaCoreLanguageField extends AbstractField implements FieldInterface
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

        if ((empty($this->GetEntity()) || empty($this->GetEntity()->getId())) && !$this->GetAttribute()->getFrontendDisplayOnNew()) {
            return "";
        } elseif ($this->GetFormType() == "form" && !empty($this->GetEntity()) && !empty($this->GetEntity()->getId()) && !$this->GetAttribute()->getFrontendDisplayOnUpdate()) {
            return "";
        } elseif ($this->GetFormType() == "view" && !empty($this->GetEntity()) && !empty($this->GetEntity()->getId()) && !$this->GetAttribute()->getFrontendDisplayOnView()) {
            return "";
        }

        $jsonKeys = $this->getCoreLanguages();

        return $this->twig->render($this->GetFormTemplate(), array('attribute' => $this->GetAttribute(), 'value' => $this->GetFormFormattedValue(), 'formType' => $this->GetFormType(), 'entity_id' => $this->entity->getId(), 'json_keys' => $jsonKeys));
    }

    public function GetListViewFormattedValue()
    {
        $getter = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());

        $entity = $this->GetEntity();

        $value = $entity->{$getter}();
        if (!empty($value)) {
            $value = array_values($value);
            $value = array_map('trim', $value);
            $value = array_filter($value);
            if(!empty($value)){
                $value = implode("; ", $value);
            }
            else{
                $value = null;
            }
        }

        return $value;
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
                $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $array[$this->attribute->getAttributeCode()]);
            }
        } else {
            $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), null);
        }
    }
}
