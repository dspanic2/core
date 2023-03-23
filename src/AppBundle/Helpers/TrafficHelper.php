<?php

namespace AppBundle\Helpers;

class TrafficHelper
{
    /**
     * @param null $string
     * @return bool|false|int
     */
    static function detectBot($string = null)
    {
        if(empty($string) && isset($_SERVER['HTTP_USER_AGENT'])){
            $string = $_SERVER['HTTP_USER_AGENT'];
        }

        if(empty($string)){
            return false;
        }

        return (preg_match('/bot|crawl|slurp|spider|mediapartners|Qwantify|Python-|AppEngine-Google|YandexAccessibility|Barkrowler|GoogleImageProxy/i', $string));
    }
    /**
     * @return bool|false|int
     */
    static function detectPageSpeed()
    {
        if(isset($_SERVER['HTTP_USER_AGENT'])){
            return (preg_match('/Chrome-Lighthouse|Google Page Speed Insight/i', $_SERVER['HTTP_USER_AGENT']));
        }
        return false;
    }

    /**
     * @param $ip
     * @param $range
     * @return bool
     */
    static function ipInRange($ip, $range)
    {
        if (strpos($range, '/') == false) {
            $range .= '/32';
        }

        // $range is in IP/CIDR format eg 127.0.0.1/24
        list($range, $netmask) = explode('/', $range, 2);
        $range_decimal = ip2long($range);
        $ip_decimal = ip2long($ip);
        $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
        $netmask_decimal = ~$wildcard_decimal;

        return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
    }

    /**
     * @param null $string
     * @param string $type
     * @return bool|false|int
     */
    static function detectSqlInjection($string = null, $type = "url"){

        if(empty($string)){
            return false;
        }

        if($type == "url"){
            return (preg_match('/information_schema|\'\+n|CAST\(/i', $string));
        }
        elseif ($type == "user_agent"){
            return (preg_match('/EXTRACTVALUE|CONCAT|HAVING|information_schema|FROM DUAL| WHERE |;SELECT|CAST\(/i', $string));
        }

        return false;
    }

    static function getIpInfo($ip = NULL, $purpose = "location", $deep_detect = TRUE) {
        $output = NULL;
        if (filter_var($ip, FILTER_VALIDATE_IP) === FALSE) {
            $ip = $_SERVER["REMOTE_ADDR"];
            if ($deep_detect) {
                if (filter_var(@$_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP))
                    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                if (filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP))
                    $ip = $_SERVER['HTTP_CLIENT_IP'];
            }
        }
        $purpose    = str_replace(array("name", "\n", "\t", " ", "-", "_"), NULL, strtolower(trim($purpose)));
        $support    = array("country", "countrycode", "state", "region", "city", "location", "address");
        $continents = array(
            "AF" => "Africa",
            "AN" => "Antarctica",
            "AS" => "Asia",
            "EU" => "Europe",
            "OC" => "Australia (Oceania)",
            "NA" => "North America",
            "SA" => "South America"
        );
        if (filter_var($ip, FILTER_VALIDATE_IP) && in_array($purpose, $support)) {
            $ipdat = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ip));
            if (@strlen(trim($ipdat->geoplugin_countryCode)) == 2) {
                switch ($purpose) {
                    case "location":
                        $output = array(
                            "city"           => @$ipdat->geoplugin_city,
                            "state"          => @$ipdat->geoplugin_regionName,
                            "country"        => @$ipdat->geoplugin_countryName,
                            "country_code"   => @$ipdat->geoplugin_countryCode,
                            "continent"      => @$continents[strtoupper($ipdat->geoplugin_continentCode)],
                            "continent_code" => @$ipdat->geoplugin_continentCode
                        );
                        break;
                    case "address":
                        $address = array($ipdat->geoplugin_countryName);
                        if (@strlen($ipdat->geoplugin_regionName) >= 1)
                            $address[] = $ipdat->geoplugin_regionName;
                        if (@strlen($ipdat->geoplugin_city) >= 1)
                            $address[] = $ipdat->geoplugin_city;
                        $output = implode(", ", array_reverse($address));
                        break;
                    case "city":
                        $output = @$ipdat->geoplugin_city;
                        break;
                    case "state":
                        $output = @$ipdat->geoplugin_regionName;
                        break;
                    case "region":
                        $output = @$ipdat->geoplugin_regionName;
                        break;
                    case "country":
                        $output = @$ipdat->geoplugin_countryName;
                        break;
                    case "countrycode":
                        $output = @$ipdat->geoplugin_countryCode;
                        break;
                }
            }
        }
        return $output;
    }

    /**
     * @param $requestUri
     * @return false|mixed|string
     */
    static function getUrlFileExtension($requestUri){

        $ret = "url";

        $parts = explode(".",$requestUri);

        if(count($parts) < 2){
            return $ret;
        }

        $ext = end($parts);

        if($ext == "js"){
            return $ext;
        }
        elseif ($ext == "css"){
            return $ext;
        }
        elseif ($ext == "html"){
            return $ret;
        }
        elseif (in_array($ext,Array("pdf","doc","xml","docx"))){
            $ret = "file";
        }
        elseif (in_array($ext,Array("png","jpg","webp","svg","jpeg"))){
            $ret = "image";
        }
        elseif (in_array($ext,Array("mpeg"))){
            $ret = "video";
        }

        return $ret;
    }
}
