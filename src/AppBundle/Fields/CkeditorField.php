<?php

namespace AppBundle\Fields;

use AppBundle\Abstracts\AbstractField;

class CkeditorField extends AbstractField
{

    public function getBackendType()
    {
        return "ckeditor";
    }
}
