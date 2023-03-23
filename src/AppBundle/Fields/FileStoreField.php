<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Entity\Attribute;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use Doctrine\Common\Inflector\Inflector;

class FileStoreField extends AbstractField implements FieldInterface
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
            'stores' => $stores,
            "getter" => EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode()),
            "entity" => $this->GetEntity()
        ));

        /**
         * Hm, zasto je ovo tu? Ovo je doslo kod prijenosa iz borinog editable table
         */
        /*return $this->twig->render($this->GetFormTemplate(), array(
            'attribute' => $this->GetAttribute(),
            'value' => $this->GetFormFormattedValue(),
            'editableValue' => $this->GetFormEditableValue(),
            'formType' => $this->GetFormType(),
            'entity_id' => $this->entity->getId(),
            'stores' => $stores,
        ));*/
    }

    public function GetListViewFormattedValue()
    {
        $getter = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());

        $entity = $this->GetEntity();

        $value = $entity->{$getter}();
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

    public function getCustomAdmin(Attribute $attribute = null)
    {
        return $this->twig->render("AppBundle:Admin/Fields:file.html.twig", array(
            "entity" => $attribute
        ));
    }

    public function getBackendType()
    {
        return "json";
    }

    public function setEntityValueFromArray(array $array)
    {
        if (isset($array[$this->attribute->getAttributeCode()])) {
            $targetDir = $this->attribute->getFolder();
            if (empty($targetDir)) {
                return;
            }

            $filePath = $_ENV["WEB_PATH"] . $targetDir;
            $filePath = str_ireplace("//", "/", $filePath);

            if (!file_exists($filePath)) {
                if (!mkdir($filePath, 0777, true)) {
                    return;
                }
            }

            $setter = EntityHelper::makeSetter($this->attribute->getAttributeCode());

            $files = $array[$this->attribute->getAttributeCode()];

            $savedFiles = [];
            foreach ($files as $store => $filename) {
                if (!empty($filename)) {
                    if (strpos($filename, "tmp") !== false) {
                        // Example: /home/madev/project/web/tmp/file.abc
                        $tmp = $_ENV["WEB_PATH"] . $filename;

                        // Example: /home/madev/project/web/Documents/Entity/Type/file.abc
                        $preparedFolder = str_ireplace("STORE_ID", $store, $filePath);
                        if (!file_exists($preparedFolder)) {
                            if (!mkdir($preparedFolder, 0777, true)) {
                                return;
                            }
                        }
                        dump($preparedFolder);die;
                        $filename = $preparedFolder . str_replace("tmp/", "", $filename);

                        rename($tmp, $filename);
                    }
                    $savedFiles[$store] = basename($filename);
                }
            }
            $this->entity->$setter($savedFiles);
        } else {
            $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), null);
        }
    }
}
