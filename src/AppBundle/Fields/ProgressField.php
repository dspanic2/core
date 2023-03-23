<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;

class ProgressField extends AbstractField
{

    public function getBackendType()
    {
        return "integer";
    }
}
