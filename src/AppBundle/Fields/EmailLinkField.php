<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Entity\Attribute;

class EmailLinkField extends AbstractField
{

    public function GetFormFieldHtml()
    {
        if ((empty($this->GetEntity()) || empty($this->GetEntity()->getId())) && !$this->GetAttribute()->getFrontendDisplayOnNew()) {
            return "";
        } elseif ($this->GetFormType() == "form" && !empty($this->GetEntity()) && !empty($this->GetEntity()->getId()) && !$this->GetAttribute()->getFrontendDisplayOnUpdate()) {
            return "";
        } elseif ($this->GetFormType() == "view" && !empty($this->GetEntity()) && !empty($this->GetEntity()->getId()) && !$this->GetAttribute()->getFrontendDisplayOnView()) {
            return "";
        }

        /** @var Attribute $attribute */
        $attribute = $this->GetAttribute();

        if (empty($attribute->getValidator())) {
            $defaultValidator = $_ENV["EMAIL_VALIDATOR"] ?? null;

            if (!empty($defaultValidator)) {
                $attribute->setValidator($defaultValidator);
                $this->SetAttribute($attribute);
            }
        }

        return $this->twig->render($this->GetFormTemplate(), array('attribute' => $this->GetAttribute(), 'value' => $this->GetFormFormattedValue(), 'formType' => $this->GetFormType(),'entity_id'=>$this->entity->getId()));
    }

    public function getBackendType()
    {
        return "varchar";
    }
}
