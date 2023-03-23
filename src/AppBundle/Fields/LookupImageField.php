<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Context\AttributeContext;
use AppBundle\Entity\Attribute;
use AppBundle\Helpers\EntityHelper;
use Doctrine\Common\Inflector\Inflector;

class LookupImageField extends AbstractField
{
    public function GetListViewHtml()
    {
        /** @var AttributeContext $attributeContext */
        $attributeContext = $this->getContainer()->get("attribute_context");

        /** @var Attribute $lookupFileAttribute */
        $lookupFileAttribute = $attributeContext->getOneBy(Array("entityType" => $this->GetAttribute()->getLookupEntityType(), "attributeCode" => "file"));
        $value = $this->GetListViewValue();

        return $this->twig->render($this->GetListViewTemplate(), array('width' => $this->GetListViewAttribute()->getColumnWidth(), "value" => $value, "fieldClass" => $this->GetFieldClass(), "entity" => $this->GetEntity(), "attribute" => $lookupFileAttribute));
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

        return $this->twig->render($this->GetFormTemplate(), array("attribute" => $this->GetAttribute(), "entity" => $this->GetEntity(), "value" => $this->GetFormFormattedValue(), "formType" => $this->GetFormType()));
    }

    public function GetFormFormattedValue()
    {
        $ret = Array();
        if ($this->GetAttribute()->getFrontendModel() == "default") {
            $getters = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());
            $getters = substr($getters, 0, -2);

            $entity = $this->GetEntity();

            $value = $entity->{$getters}();

            $this->SetParentEntity($value);

            if ($value != null) {
                // dump($value,$this->GetAttribute());die;
                $ret["id"] = $value->getId();
                $ret["lookup_value"] = $this->twig->render("AppBundle:Form/AutocompleteTemplates:default.html.twig",
                    array(
                        "field_data" => $value,
                        "attribute" => Inflector::camelize($this->GetAttribute()->getLookupAttribute()->getAttributeCode()))
                    );
            }
        }

        return $ret;
    }

    public function getInput()
    {
        return "lookup";
    }
}