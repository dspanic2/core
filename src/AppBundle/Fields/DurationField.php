<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Interfaces\Fields\FieldInterface;
use Doctrine\Common\Inflector\Inflector;

class DurationField extends AbstractField implements FieldInterface
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

        $format = "H:i:s";
        if (!empty($this->attribute->getFrontendDisplayFormat())) {
            $format = $this->attribute->getFrontendDisplayFormat();
        }

        $duration = \DateTime::createFromFormat("U", $value);

        return $duration->format($format);
    }

    /**
     * @return false|string
     */
    public function GetFormFormattedValue()
    {
        $getter = EntityHelper::makeGetter($this->GetAttribute()->getAttributeCode());

        $format = "H:i:s";
        if (!empty($this->attribute->getFrontendDisplayFormat())) {
            $format = $this->attribute->getFrontendDisplayFormat();
        }

        $entity = $this->GetEntity();

        /**
         * If new check for default
         */
        if (empty($this->GetEntity()->getId()) && !empty($this->GetAttribute()->getDefaultValue())) {
            $value = \DateTime::createFromFormat("U", $this->GetAttribute()->getDefaultValue());
        } else {
            $value = $entity->{$getter}();
        }

        if (empty($value)) {
            return false;
        }

        $duration = \DateTime::createFromFormat("U", $value);

        return $duration->format($format);
    }

    /**
     * @return string
     */
    public function getInput()
    {
        return "time";
    }

    /**
     * @return string
     */
    public function getBackendType()
    {
        return "integer";
    }

    /**
     * @param array $array
     */
    public function setEntityValueFromArray(array $array)
    {
        $value = $array[$this->attribute->getAttributeCode()];
        if (!empty($value)) {
            $format = "Y-m-d H:i:s";
            if (!empty($this->attribute->getFrontendDisplayFormat())) {
                $format = $this->attribute->getFrontendDisplayFormat();
            }

            $dateTime = \DateTime::createFromFormat($format, $value);
            if (!empty($dateTime)) {
                $this->entity->setAttribute(Inflector::camelize($this->attribute->getAttributeCode()), $dateTime->format("U"));
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
