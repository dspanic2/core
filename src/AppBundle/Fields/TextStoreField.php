<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use Doctrine\Common\Inflector\Inflector;

class TextStoreField extends AbstractField implements FieldInterface
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
            if (!empty($value)) {
                $value = json_decode($value, true);
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

        $stores = $this->getStores();

        return $this->twig->render($this->GetFormTemplate(), array(
            'attribute' => $this->GetAttribute(),
            'value' => $this->GetFormFormattedValue(),
            'formType' => $this->GetFormType(),
            'entity_id' => $this->entity->getId(),
            'stores' => $stores
        ));
    }

    public function GetListViewFormattedValue()
    {
        $field = $this->GetListViewAttribute()->getField();

        $entity = $this->GetEntity();

        if (strpos($field, ".")) {
            $getters = EntityHelper::getPropertyAccessor($field);

            $value = $entity->{$getters[0]}();

            $count = count($getters);
            $count = $count - 2;

            if ($count == 0) {
                $this->SetParentEntity($value);
            }

            foreach ($getters as $key => $getter) {
                if ($key == 0) {
                    continue;
                }
                if (!empty($value) && is_object($value)) {
                    $value = $value->{$getter}();

                    if ($key == $count) {
                        $this->SetParentEntity($value);
                    }
                } else {
                    $value = "";
                }
            }
        } else {
            $getter = EntityHelper::makeGetter($field);

            $value = $entity->{$getter}();
        }

        $nonEditableValue = $value;
        if (!empty($value)) {
            if (!is_string($nonEditableValue)) {
                $nonEditableValue = array_values($nonEditableValue);
                $nonEditableValue = array_map('trim', $nonEditableValue);
                $nonEditableValue = array_filter($nonEditableValue);
                if (!empty($nonEditableValue)) {
                    $nonEditableValue = implode("; ", $nonEditableValue);
                } else {
                    $nonEditableValue = null;
                }
            }
        } else {
            $value = null;
            $nonEditableValue = "";
        }
        return [
            "raw" => $value,
            "non_editable" => $nonEditableValue
        ];
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
