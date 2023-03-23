<?php

namespace TaskBusinessBundle\Fields;

use AppBundle\Abstracts\AbstractField;

class TaskCompletedField extends AbstractField
{
    /**
     * @return string
     */
    public function GetListViewTemplate()
    {
        $attribute = $this->GetAttribute();

        return 'TaskBusinessBundle:Fields/ListView:' . $attribute->getFrontendType() . '.html.twig';
    }

    /**
     * @return string
     */
    public function GetFormTemplate()
    {
        $attribute = $this->GetAttribute();

        return 'TaskBusinessBundle:Fields/Form:' . $attribute->getFrontendType() . '.html.twig';
    }

    /**
     * @return string
     */
    public function GetAdvancedSearchTemplate()
    {
        $attribute = $this->GetAttribute();

        return 'TaskBusinessBundle:Fields/AdvancedSearch:' . $attribute->getFrontendType() . '.html.twig';
    }

    /**
     * @return string
     */
    public function GetQuickSearchTemplate()
    {
        $attribute = $this->GetAttribute();

        return 'TaskBusinessBundle:Fields/QuickSearch:' . $attribute->getFrontendType() . '.html.twig';
    }

    /**
     * @return string
     */
    public function GetListViewHeaderTemplate()
    {
        $attribute = $this->GetAttribute();

        return 'TaskBusinessBundle:Fields/ListView/Header:' . $attribute->getFrontendType() . '.html.twig';
    }
}