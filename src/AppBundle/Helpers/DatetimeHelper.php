<?php


namespace AppBundle\Helpers;

class DatetimeHelper
{
    /**
     * @param $string
     * @return \DateTime|false
     */
    public static function createDateFromString($string)
    {
        $format = null;

        /**
         * Remove dot if last character (date)
         */
        if (substr($string, -1) == ".") {
            $string = substr($string, 0, -1);
        }

        /**
         * Check if date time
         */
        if (strpos($string, " ") !== false) {
            $tmp = explode(" ", $string);

            $date_part = DatetimeHelper::getDateFormatFromString($tmp[0]);
            if (empty($date_part)) {
                return false;
            }

            $time_part = DatetimeHelper::getTimeFormatFromString($tmp[1]);
            if (!empty($time_part)) {
                $date_part = $date_part . " " . $time_part;
            }

            $format = $date_part;

        } else {
            $format = DatetimeHelper::getDateFormatFromString($string);
        }

        if (empty($format)) {
            return false;
        }

        return \DateTime::createFromFormat($format, $string);
    }

    /**
     * @param $string
     * @return false|string
     */
    public static function getDateFormatFromString($string)
    {
        if (strlen($string) == 4) {
            return "Y";
        }

        $delims = array(".", "/", "-");

        foreach ($delims as $delim) {

            if (strpos($string, $delim) !== false) {

                $parts = explode($delim, $string);
                if (count($parts) != 3) {
                    continue;
                }

                $f = array();

                foreach ($parts as $key => $s) {
                    if (strlen($s) == 4) {
                        $f[$key] = "Y";
                    } else if (intval($s) > 12) {
                        $f[$key] = "d";
                    }
                }

                if (count($f) == 1) {
                    if (isset($f[0])) {
                        $f[1] = "m";
                        $f[2] = "d";
                    } else {
                        $f[0] = "d";
                        $f[1] = "m";
                        $f[2] = "Y";
                    }
                } else {
                    if (isset($f[0]) && $f[0] == "Y") {
                        if (isset($f[1])) {
                            $f[2] = "m";
                        } else {
                            $f[1] = "m";
                        }
                    }
                    if (isset($f[0]) && $f[0] == "d") {
                        $f[1] = "m";
                        $f[2] = "Y";
                    }
                }

                if (count($f) != 3) {
                    continue;
                }

                ksort($f);

                return implode($delim, $f);
            }
        }

        return false;
    }

    /**
     * @param $string
     * @return false|string
     */
    public static function getTimeFormatFromString($string)
    {
        if (strpos($string, ":") !== false) {

            $string = explode(":", $string);
            if (count($string) == 3) {
                return "H:i:s";
            } else if (count($string) == 2) {
                return "H:i";
            }
        }

        return false;
    }

    /**
     * @param $date1
     * @param $date2
     * @return false
     */
    static function calculateNumberOfDaysBetweenDates($date1, $date2)
    {
        if (empty($date1) || empty($date2)) {
            return false;
        }

        $interval = $date1->diff($date2);

        return $interval->format('%a');
    }
}
