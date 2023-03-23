<?php

namespace ScommerceBusinessBundle\Enumerations;

class FrontBlockTypesEnum
{
    static function values()
    {
        return array(
            "header" => array(
                "html_content" => false,
                "content" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
            "html" => array(
                "html_content" => true,
                "content" => false,
                "is_available_in_block" => 1,
                "is_available_in_page" => 1
            ),
        );
    }
}
