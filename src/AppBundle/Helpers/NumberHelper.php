<?php

namespace AppBundle\Helpers;

class NumberHelper
{
    /**
     * @param $hex
     * @return float
     */
    static function hexToDecimal($hex)
    {
        $dec = 0;
        $len = strlen($hex);
        for ($i = 1; $i <= $len; $i++) {
            $dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
        }

        return ($dec);
    }

    /**
     * Format for display
     * @param $value
     * @param $format
     * @return string
     */
    static function formatDecimal($value, $format = null)
    {
        if (!empty($format)) {
            $format = json_decode($format, true);
        } else {
            $defaultFormat = $_ENV["DECIMAL_FORMAT"] ?? null;
            if (!empty($defaultFormat)) {
                $format = json_decode($defaultFormat, true);
            }
        }

        return !empty($format) ?
            number_format($value, $format["decimals"], $format["dec_point"], $format["thousands_sep"]) :
            number_format($value, "2", ".", "");
    }

    /**
     * Format for database
     * @param $value
     * @param null $format
     * @return string|string[]
     */
    static function cleanDecimal($value, $format = null)
    {
        if (!empty($format)) {
            $format = json_decode($format, true);
        } else {
            $defaultFormat = $_ENV["DECIMAL_FORMAT"] ?? null;
            if (!empty($defaultFormat)) {
                $format = json_decode($defaultFormat, true);
            }
        }

        if (!empty($format)) {
            if (strpos($value, $format["thousands_sep"]) !== false) {
                $value = str_replace($format["thousands_sep"], "", $value);
            }
            if (strpos($value, $format["dec_point"]) !== false) {
                $value = str_replace($format["dec_point"], ".", $value);
            }
        } else {
            if (strpos($value, ",") !== false) {
                $value = str_replace(",", ".", $value);
            }
        }

        return $value;
    }

    static function modulo($n,$b) {
        return $n-$b*floor($n/$b);
    }
}