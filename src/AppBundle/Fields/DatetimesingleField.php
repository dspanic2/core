<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use Doctrine\Common\Inflector\Inflector;

class DatetimesingleField extends AbstractField implements FieldInterface
{
    /**
     * @return false|string
     */
    public function GetListViewFormattedValue()
    {
        $value = $this->GetListViewValue();
        if (empty($value)) {
            return false;
        }

        $format = "d/m/Y H:i:s";
        if (!empty($this->attribute->getFrontendDisplayFormat())) {
            $format = $this->attribute->getFrontendDisplayFormat();
        }

        return date_format($value, $format);
    }

    /**
     * @return false|string
     */
    public function GetFormFormattedValue()
    {
        $getter = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());

        $format = "d/m/Y H:i:s";
        if (!empty($this->attribute->getFrontendDisplayFormat())) {
            $format = $this->attribute->getFrontendDisplayFormat();
        }

        $entity = $this->GetEntity();

        /**
         * If new check for default
         */
        if (empty($this->GetEntity()->getId()) && !empty($this->GetAttribute()->getDefaultValue())) {
            if ($this->GetAttribute()->getDefaultValue() == "now") {
                $value = new \DateTime("now");
            } else {
                $value = \DateTime::createFromFormat($format, $this->GetAttribute()->getDefaultValue());
            }
        } else {
            $value = $entity->{$getter}();
        }

        if (empty($value)) {
            return false;
        }

        return date_format($value, $format);
    }

    /**
     * @return string
     */
    public function getInput()
    {
        return "datetime";
    }

    /**
     * @return string
     */
    public function getBackendType()
    {
        return "datetime";
    }

    /**
     * @param array $array
     */
    public function setEntityValueFromArray(array $array)
    {
        $value = $array[$this->attribute->getAttributeCode()];
        if (!empty($value)) {
            $format = "d/m/Y H:i:s";
            if (!empty($this->attribute->getFrontendDisplayFormat())) {
                $format = $this->attribute->getFrontendDisplayFormat();
            }

            $dateTime = \DateTime::createFromFormat($format, $value);
            if (!empty($dateTime)) {
                $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $dateTime);
            }
        } else {
            $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), null);
        }
    }

    /**
     * @return string
     */
    public function GetListViewTemplate()
    {
        $attribute = $this->GetAttribute();

        return "AppBundle:Fields/ListView:" . $attribute->getFrontendType() . ".html.twig";
    }

    /**
     * @return string
     */
    public function GetFormTemplate()
    {
        $attribute = $this->GetAttribute();

        return "AppBundle:Fields/Form:" . $attribute->getFrontendType() . ".html.twig";
    }

    /**
     * @return string
     */
    public function GetAdvancedSearchTemplate()
    {
        $attribute = $this->GetAttribute();

        return "AppBundle:Fields/AdvancedSearch:" . $attribute->getFrontendType() . ".html.twig";
    }

    /**
     * @return string
     */
    public function GetQuickSearchTemplate()
    {
        $attribute = $this->GetAttribute();

        return "AppBundle:Fields/QuickSearch:" . $attribute->getFrontendType() . ".html.twig";
    }

    /**
     * @return string
     */
    public function GetListViewHeaderTemplate()
    {
        $attribute = $this->GetAttribute();

        return "AppBundle:Fields/ListView/Header:" . $attribute->getFrontendType() . ".html.twig";
    }
}
