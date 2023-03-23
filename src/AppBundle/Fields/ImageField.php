<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Entity\Attribute;
use Doctrine\Common\Inflector\Inflector;

class ImageField extends AbstractField
{
    public function GetListViewHtml()
    {
        return $this->twig->render($this->GetListViewTemplate(), array('width' => $this->GetListViewAttribute()->getColumnWidth(), "value" => $this->GetListViewValue(), "fieldClass" => $this->GetFieldClass(), "entity" => $this->GetParentEntity(), "attribute" => $this->GetAttribute()));
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

    public function getCustomAdmin(Attribute $attribute = null)
    {
        return $this->twig->render("AppBundle:Admin/Fields:image.html.twig", array(
            "entity" => $attribute
        ));
    }

    public function setEntityValueFromArray(array $array)
    {
        $targetDir = $this->attribute->getFolder();
        if (empty($targetDir)) {
            return;
        }

        $webDir = rtrim($_ENV["WEB_PATH"],"/");
        if (!file_exists($webDir . $targetDir)) {
            if (!mkdir($webDir . $targetDir, 0777, true)) {
                return;
            }
        }

        $filename = $array[$this->attribute->getAttributeCode()];
        if (!empty($filename)) {
            if (strpos($filename, "tmp") !== false) {
                /**
                 * Example: /home/madev/serena_crm.madev.eu/web/tmp/DSC_0001.jpg -> /home/madev/serena_crm.madev.eu/web/Documents/Extra/Images/DSC_0001.jpg
                 */
                rename($webDir . "/" . $filename, $webDir . $targetDir . str_replace("tmp/", "", $filename));
            }

            $filename = pathinfo($filename, PATHINFO_BASENAME);
        }

        $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $filename);
    }
}