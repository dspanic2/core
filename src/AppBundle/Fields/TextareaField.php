<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;

class TextareaField extends AbstractField
{


    public function getBackendType()
    {
        return "text";
    }
}
