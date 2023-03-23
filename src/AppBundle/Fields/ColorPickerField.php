<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;

class ColorPickerField extends AbstractField
{
    public function getBackendType()
    {
        return "varchar";
    }
}
