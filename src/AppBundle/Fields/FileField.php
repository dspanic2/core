<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Constants\FileSources;
use AppBundle\Entity\Attribute;
use AppBundle\Helpers\EntityHelper;

class FileField extends AbstractField
{
    public function GetListViewFormattedValue()
    {
        $value = $this->GetListViewValue();
        if (empty($value)) {
            return false;
        }

        if (method_exists($this->GetEntity(),"getFileSource") && $this->GetEntity()->getFileSource() == FileSources::DROPBOX) {
            return '<a class="sp-document-download" href="' . $this->GetEntity()->getFile() . '" target="_blank">' . $this->GetEntity()->getFilename() . '</a>';
        }

        $baseUrl = $this->getContainer()->get("router")->generate("homepage");
        if (empty($baseUrl)) {
            $baseUrl = $_ENV["SSL"]."://" . $_ENV["BACKEND_URL"] . $_ENV["FRONTEND_URL_PORT"];
        }

        $folder = ltrim($this->GetAttribute()->getFolder(), "/");

        $documentUrl = $baseUrl . $folder . $value;

        return '<a class="sp-document-download" href="' . $documentUrl . '" target="_blank">' . $value . '</a>';
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

        return $this->twig->render($this->GetFormTemplate(), array("attribute" => $this->GetAttribute(), "entity" => $this->GetEntity(), "value" => $this->GetFormFormattedValue(), "formType" => $this->GetFormType(), "getter" => EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode())));
    }

    public function getCustomAdmin(Attribute $attribute = null)
    {
        return $this->twig->render("AppBundle:Admin/Fields:file.html.twig", array(
            "entity" => $attribute
        ));
    }

    public function setEntityValueFromArray(array $array)
    {
        $targetDir = $this->attribute->getFolder();
        if (empty($targetDir)) {
            return;
        }

        $filePath = $_ENV["WEB_PATH"] . $targetDir;
        $filePath = str_ireplace("//","/",$filePath);

        if (!file_exists($filePath)) {
            if (!mkdir($filePath, 0777, true)) {
                return;
            }
        }

        $filename = $array[$this->attribute->getAttributeCode()];

        $setter = EntityHelper::makeSetter($this->attribute->getAttributeCode());

        if (!empty($filename)) {
            if (strpos($filename, "tmp") !== false) {
                // Example: /home/madev/project/web/tmp/file.abc
                $tmp = $_ENV["WEB_PATH"] . $filename;

                // Example: /home/madev/project/web/Documents/Entity/Type/file.abc
                $filename = $filePath . str_replace("tmp/", "", $filename);

                rename($tmp, $filename);
            }
            $this->entity->$setter(basename($filename));
        }else{
            $this->entity->$setter(null);
        }

    }
}
