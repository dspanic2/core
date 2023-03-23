<?php

namespace AppBundle\Interfaces\Fields;

use AppBundle\Entity\Attribute;
use AppBundle\Interfaces\Entity\IFormEntityInterface;

/**
 * An interface that every field should implement
 */
interface FieldInterface
{
    /** Return field template location for list view */
    public function GetListViewTemplate();

    /** Return field template location for form */
    public function GetFormTemplate();

    /** Return field template location for list view header */
    public function GetListViewHeaderTemplate();

    /** Return field formatted value for list view */
    public function GetListViewFormattedValue();

    /** Return field un formatted value for list view */
    public function GetListViewValue();

    /** Return field formatted value for form */
    public function GetFormFormattedValue();

    /** Return field html for form list view */
    public function GetListViewHtml();

    /** Return field html for form form */
    public function GetFormFieldHtml();

    /** Return field html for form list view header */
    public function GetListViewHeaderHtml();

    /** Set value of entity attribute that is of this field type from array of values*/
    public function setEntityValueFromArray(array $array);

    public function getInput();

    public function getType();

    public function getBackendType();

    public function getCustomAdmin(Attribute $attribute = null);
}
