<?php

namespace AppBundle\Helpers;

use Doctrine\Common\Util\Inflector;

class ArrayHelper
{
    /**
     * Check if multiple keys exist
     * @param array $keys
     * @param array $arr
     * @return bool
     */
    static function array_keys_exists(array $keys, array $arr)
    {
        return !array_diff_key(array_flip($keys), $arr);
    }

}