<?php

namespace AppBundle\Helpers;

define("ENCRYPTION_KEY", 'Qv3$s#%#dtEEC91SJ*RuIxOc9');
define("ENCRYPTION_IV", '@%wcrSU6b2i*SJ16D5EJPE46z');

class StringHelper
{
    static function removeAllSpecialCharacters($string)
    {
        return preg_replace('/[^A-Za-z0-9\-]/', '', $string);
    }

    static function format()
    {

        $args = func_get_args();
        $format = array_shift($args);

        preg_match_all('/(?=\{)\{(\d+)\}(?!\})/', $format, $matches, PREG_OFFSET_CAPTURE);
        $offset = 0;
        foreach ($matches[1] as $data) {
            $i = $data[0];
            $format = substr_replace($format, @$args[$i], $offset + $data[1] - 1, 2 + strlen($i));
            $offset += strlen(@$args[$i]) - 2 - strlen($i);
        }

        return $format;
    }

    /**
     * @param $string
     * @return string
     */
    static function encrypt($string)
    {
        $encrypt_method = "AES-256-CBC";

        $key = hash('sha256', ENCRYPTION_KEY);

        $iv = substr(hash('sha256', ENCRYPTION_IV), 0, 16);

        $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
        $output = base64_encode($output);
        return $output;
    }

    /**
     * @param $string
     * @return string
     */
    static function decrypt($string)
    {
        $encrypt_method = "AES-256-CBC";

        $key = hash('sha256', ENCRYPTION_KEY);

        $iv = substr(hash('sha256', ENCRYPTION_IV), 0, 16);

        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        return $output;
    }

    /**
     * @param $string
     * @return string
     */
    static function guidv4()
    {
        if (function_exists('com_create_guid') === true) {
            return trim(com_create_guid(), '{}');
        }

        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * @param $string
     * @return string|string[]|null
     */
    static function removeNonAsciiCharacters($string)
    {
        $string = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $string);

        // Match Enclosed Alphanumeric Supplement
        $regex_alphanumeric = '/[\x{1F100}-\x{1F1FF}]/u';
        $string = preg_replace($regex_alphanumeric, '', $string);

        // Match Miscellaneous Symbols and Pictographs
        $regex_symbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $string = preg_replace($regex_symbols, '', $string);

        // Match Emoticons
        $regex_emoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $string = preg_replace($regex_emoticons, '', $string);

        // Match Transport And Map Symbols
        $regex_transport = '/[\x{1F680}-\x{1F6FF}]/u';
        $string = preg_replace($regex_transport, '', $string);

        // Match Supplemental Symbols and Pictographs
        $regex_supplemental = '/[\x{1F900}-\x{1F9FF}]/u';
        $string = preg_replace($regex_supplemental, '', $string);

        // Match Miscellaneous Symbols
        $regex_misc = '/[\x{2600}-\x{26FF}]/u';
        $string = preg_replace($regex_misc, '', $string);

        // Match Dingbats
        $regex_dingbats = '/[\x{2700}-\x{27BF}]/u';
        $string = preg_replace($regex_dingbats, '', $string);

        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');

        return $string;
        //return preg_replace('/[^(x20-x7E)|(čćšđžČĆŠĐŽ)]/', '', $string);
    }

    static function sanitizeFileName($filename)
    {
        $rules = array(
            // Numeric characters
            '¹' => 1,
            '²' => 2,
            '³' => 3,

            // Latin
            'º' => 0,
            '°' => 0,
            'æ' => 'ae',
            'ǽ' => 'ae',
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'Å' => 'A',
            'Ǻ' => 'A',
            'Ă' => 'A',
            'Ǎ' => 'A',
            'Æ' => 'AE',
            'Ǽ' => 'AE',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'å' => 'a',
            'ǻ' => 'a',
            'ă' => 'a',
            'ǎ' => 'a',
            'ª' => 'a',
            '@' => 'at',
            'Ĉ' => 'C',
            'Ċ' => 'C',
            'ĉ' => 'c',
            'ċ' => 'c',
            '©' => 'c',
            'Ð' => 'Dj',
            'Đ' => 'Dj',
            'ð' => 'dj',
            'đ' => 'dj',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Ĕ' => 'E',
            'Ė' => 'E',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ĕ' => 'e',
            'ė' => 'e',
            'ƒ' => 'f',
            'Ĝ' => 'G',
            'Ġ' => 'G',
            'ĝ' => 'g',
            'ġ' => 'g',
            'Ĥ' => 'H',
            'Ħ' => 'H',
            'ĥ' => 'h',
            'ħ' => 'h',
            'Ì' => 'I',
            'Í' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'Ĩ' => 'I',
            'Ĭ' => 'I',
            'Ǐ' => 'I',
            'Į' => 'I',
            'Ĳ' => 'IJ',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ĩ' => 'i',
            'ĭ' => 'i',
            'ǐ' => 'i',
            'į' => 'i',
            'ĳ' => 'ij',
            'Ĵ' => 'J',
            'ĵ' => 'j',
            'Ĺ' => 'L',
            'Ľ' => 'L',
            'Ŀ' => 'L',
            'ĺ' => 'l',
            'ľ' => 'l',
            'ŀ' => 'l',
            'Ñ' => 'N',
            'ñ' => 'n',
            'ŉ' => 'n',
            'Ò' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ō' => 'O',
            'Ŏ' => 'O',
            'Ǒ' => 'O',
            'Ő' => 'O',
            'Ơ' => 'O',
            'Ø' => 'O',
            'Ǿ' => 'O',
            'Œ' => 'OE',
            'ò' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ō' => 'o',
            'ŏ' => 'o',
            'ǒ' => 'o',
            'ő' => 'o',
            'ơ' => 'o',
            'ø' => 'o',
            'ǿ' => 'o',
            'º' => 'o',
            'œ' => 'oe',
            'Ŕ' => 'R',
            'Ŗ' => 'R',
            'ŕ' => 'r',
            'ŗ' => 'r',
            'Ŝ' => 'S',
            'Ș' => 'S',
            'ŝ' => 's',
            'ș' => 's',
            'ſ' => 's',
            'Ţ' => 'T',
            'Ț' => 'T',
            'Ŧ' => 'T',
            'Þ' => 'TH',
            'ţ' => 't',
            'ț' => 't',
            'ŧ' => 't',
            'þ' => 'th',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ũ' => 'U',
            'Ŭ' => 'U',
            'Ű' => 'U',
            'Ų' => 'U',
            'Ư' => 'U',
            'Ǔ' => 'U',
            'Ǖ' => 'U',
            'Ǘ' => 'U',
            'Ǚ' => 'U',
            'Ǜ' => 'U',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ũ' => 'u',
            'ŭ' => 'u',
            'ű' => 'u',
            'ų' => 'u',
            'ư' => 'u',
            'ǔ' => 'u',
            'ǖ' => 'u',
            'ǘ' => 'u',
            'ǚ' => 'u',
            'ǜ' => 'u',
            'Ŵ' => 'W',
            'ŵ' => 'w',
            'Ý' => 'Y',
            'Ÿ' => 'Y',
            'Ŷ' => 'Y',
            'ý' => 'y',
            'ÿ' => 'y',
            'ŷ' => 'y',

            // Russian
            'Ъ' => '',
            'Ь' => '',
            'А' => 'A',
            'Б' => 'B',
            'Ц' => 'C',
            'Ч' => 'Ch',
            'Д' => 'D',
            'Е' => 'E',
            'Ё' => 'E',
            'Э' => 'E',
            'Ф' => 'F',
            'Г' => 'G',
            'Х' => 'H',
            'И' => 'I',
            'Й' => 'J',
            'Я' => 'Ja',
            'Ю' => 'Ju',
            'К' => 'K',
            'Л' => 'L',
            'М' => 'M',
            'Н' => 'N',
            'О' => 'O',
            'П' => 'P',
            'Р' => 'R',
            'С' => 'S',
            'Ш' => 'Sh',
            'Щ' => 'Shch',
            'Т' => 'T',
            'У' => 'U',
            'В' => 'V',
            'Ы' => 'Y',
            'З' => 'Z',
            'Ж' => 'Zh',
            'ъ' => '',
            'ь' => '',
            'а' => 'a',
            'б' => 'b',
            'ц' => 'c',
            'ч' => 'ch',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'e',
            'э' => 'e',
            'ф' => 'f',
            'г' => 'g',
            'х' => 'h',
            'и' => 'i',
            'й' => 'j',
            'я' => 'ja',
            'ю' => 'ju',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'ш' => 'sh',
            'щ' => 'shch',
            'т' => 't',
            'у' => 'u',
            'в' => 'v',
            'ы' => 'y',
            'з' => 'z',
            'ж' => 'zh',

            // German characters
            'Ä' => 'AE',
            'Ö' => 'OE',
            'Ü' => 'UE',
            'ß' => 'ss',
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',

            // Turkish characters
            'Ç' => 'C',
            'Ğ' => 'G',
            'İ' => 'I',
            'Ş' => 'S',
            'ç' => 'c',
            'ğ' => 'g',
            'ı' => 'i',
            'ş' => 's',

            // Latvian
            'Ā' => 'A',
            'Ē' => 'E',
            'Ģ' => 'G',
            'Ī' => 'I',
            'Ķ' => 'K',
            'Ļ' => 'L',
            'Ņ' => 'N',
            'Ū' => 'U',
            'ā' => 'a',
            'ē' => 'e',
            'ģ' => 'g',
            'ī' => 'i',
            'ķ' => 'k',
            'ļ' => 'l',
            'ņ' => 'n',
            'ū' => 'u',

            // Ukrainian
            'Ґ' => 'G',
            'І' => 'I',
            'Ї' => 'Ji',
            'Є' => 'Ye',
            'ґ' => 'g',
            'і' => 'i',
            'ї' => 'ji',
            'є' => 'ye',

            // Czech
            'Č' => 'C',
            'Ď' => 'D',
            'Ě' => 'E',
            'Ň' => 'N',
            'Ř' => 'R',
            'Š' => 'S',
            'Ť' => 'T',
            'Ů' => 'U',
            'Ž' => 'Z',
            'č' => 'c',
            'ď' => 'd',
            'ě' => 'e',
            'ň' => 'n',
            'ř' => 'r',
            'š' => 's',
            'ť' => 't',
            'ů' => 'u',
            'ž' => 'z',

            // Polish
            'Ą' => 'A',
            'Ć' => 'C',
            'Ę' => 'E',
            'Ł' => 'L',
            'Ń' => 'N',
            'Ó' => 'O',
            'Ś' => 'S',
            'Ź' => 'Z',
            'Ż' => 'Z',
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            'ó' => 'o',
            'ś' => 's',
            'ź' => 'z',
            'ż' => 'z',

            // Greek
            'Α' => 'A',
            'Β' => 'B',
            'Γ' => 'G',
            'Δ' => 'D',
            'Ε' => 'E',
            'Ζ' => 'Z',
            'Η' => 'E',
            'Θ' => 'Th',
            'Ι' => 'I',
            'Κ' => 'K',
            'Λ' => 'L',
            'Μ' => 'M',
            'Ν' => 'N',
            'Ξ' => 'X',
            'Ο' => 'O',
            'Π' => 'P',
            'Ρ' => 'R',
            'Σ' => 'S',
            'Τ' => 'T',
            'Υ' => 'Y',
            'Φ' => 'Ph',
            'Χ' => 'Ch',
            'Ψ' => 'Ps',
            'Ω' => 'O',
            'Ϊ' => 'I',
            'Ϋ' => 'Y',
            'ά' => 'a',
            'έ' => 'e',
            'ή' => 'e',
            'ί' => 'i',
            'ΰ' => 'Y',
            'α' => 'a',
            'β' => 'b',
            'γ' => 'g',
            'δ' => 'd',
            'ε' => 'e',
            'ζ' => 'z',
            'η' => 'e',
            'θ' => 'th',
            'ι' => 'i',
            'κ' => 'k',
            'λ' => 'l',
            'μ' => 'm',
            'ν' => 'n',
            'ξ' => 'x',
            'ο' => 'o',
            'π' => 'p',
            'ρ' => 'r',
            'ς' => 's',
            'σ' => 's',
            'τ' => 't',
            'υ' => 'y',
            'φ' => 'ph',
            'χ' => 'ch',
            'ψ' => 'ps',
            'ω' => 'o',
            'ϊ' => 'i',
            'ϋ' => 'y',
            'ό' => 'o',
            'ύ' => 'y',
            'ώ' => 'o',
            'ϐ' => 'b',
            'ϑ' => 'th',
            'ϒ' => 'Y',

            /* Arabic */
            'أ' => 'a',
            'ب' => 'b',
            'ت' => 't',
            'ث' => 'th',
            'ج' => 'g',
            'ح' => 'h',
            'خ' => 'kh',
            'د' => 'd',
            'ذ' => 'th',
            'ر' => 'r',
            'ز' => 'z',
            'س' => 's',
            'ش' => 'sh',
            'ص' => 's',
            'ض' => 'd',
            'ط' => 't',
            'ظ' => 'th',
            'ع' => 'aa',
            'غ' => 'gh',
            'ف' => 'f',
            'ق' => 'k',
            'ك' => 'k',
            'ل' => 'l',
            'م' => 'm',
            'ن' => 'n',
            'ه' => 'h',
            'و' => 'o',
            'ي' => 'y'
        );

        $filename = strtr(html_entity_decode($filename, ENT_QUOTES, 'UTF-8'), $rules);
        return $filename;
    }

    public static function mb_ucfirst($string)
    {
        $string = mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
        return $string;
    }

    public static function mb_ucwords($string)
    {
        $string = explode(" ", $string);

        foreach ($string as $key => $s) {
            $string[$key] = self::mb_ucfirst($s);
        }

        return implode(" ", $string);
    }

    static function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * @param $limit
     * @param bool $only_uppercase
     * @return string
     */
    static function generateRandomString($limit, $only_uppercase = false, $only_numbers = false)
    {
        if ($only_uppercase) {
            $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        } else {
            $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        }

        if ($only_numbers) {
            $alphabet = '123456789';
        }

        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < $limit; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
    }

    /**
     * @param $entityId
     * @param $id
     * @return string
     */
    static function generateHash($entityId, $id)
    {
        return md5($entityId . "#-#" . $id . "#-#" . $id);
    }

    /**
     * @param $string
     * @return string
     */
    static function convertStringToCode($string)
    {
        $search = array("ć", "č", "ž", "š", "đ", " ", "-");
        $replacement = array("c", "c", "z", "s", "dj", "_", "_");
        return strtolower(str_replace($search, $replacement, $string));
    }

    static function binaryToString($binary)
    {
        $binaries = explode(' ', $binary);

        $string = null;
        foreach ($binaries as $binary) {
            $string .= pack('H*', dechex(bindec($binary)));
        }

        return $string;
    }

    static function isBinary($str)
    {
        return preg_match('/~[^\x20-\x7E\t\r\n]~/', $str) > 0;
    }

    static function bin2text($bin)
    {
        if (is_binarystring($bin)) {
            # valid binary string, split, explode and other magic
            # prepare string for conversion
            $chars = explode("\n", chunk_split(str_replace("\n", '', $bin), 8));
            $char_count = count($chars);

            # converting the characters one by one
            for ($i = 0; $i < $char_count; $text .= chr(bindec($chars[$i])), $i++) ;

            # let's return the result
            return "Result: " . $text;
        } else {
            # not valid binary to text string
            return "Input problems! Are we missing some ones and zeros?";
        }
    }

    /**
     * @param $string
     * @param $startString
     * @return bool
     */
    static function startsWith($string, $startString)
    {
        $len = strlen($startString);
        return (substr($string, 0, $len) === $startString);
    }

    /**
     * @param $string
     * @param $endString
     * @return bool
     */
    static function endsWith($string, $endString)
    {
        $len = strlen($endString);
        if ($len == 0) {
            return true;
        }
        return (substr($string, -$len) === $endString);
    }

    /**
     * @param $term
     * @param string $language
     * @return mixed|string
     */
    static function removeStopwords($term, $language = "hr")
    {

        $ret = "";

        if (empty(trim($term))) {
            return $ret;
        }

        $ret = $term;

        $stopWords = array();

        switch ($language) {
            case "hr":
                $stopWords = ["a", "ako", "ali", "bi", "bih", "bila", "bili", "bilo", "bio", "bismo", "biste", "biti", "bumo", "da", "do", "duž", "ga", "hoće", "hoćemo", "hoćete", "hoćeš", "hoću", "i", "iako", "ih", "ili", "iz", "ja", "je", "jedna", "jedne", "jedno", "jer", "jesam", "jesi", "jesmo", "jest", "jeste", "jesu", "jim", "joj", "još", "ju", "kada", "kako", "kao", "koja", "koje", "koji", "kojima", "koju", "kroz", "li", "me", "mene", "meni", "mi", "mimo", "moj", "moja", "moje", "mu", "na", "nad", "nakon", "nam", "nama", "nas", "naš", "naša", "naše", "našeg", "ne", "nego", "neka", "neki", "nekog", "neku", "nema", "netko", "neće", "nećemo", "nećete", "nećeš", "neću", "nešto", "ni", "nije", "nikoga", "nikoje", "nikoju", "nisam", "nisi", "nismo", "niste", "nisu", "njega", "njegov", "njegova", "njegovo", "njemu", "njezin", "njezina", "njezino", "njih", "njihov", "njihova", "njihovo", "njim", "njima", "njoj", "nju", "no", "o", "od", "odmah", "on", "ona", "oni", "ono", "ova", "pa", "pak", "po", "pod", "pored", "prije", "s", "sa", "sam", "samo", "se", "sebe", "sebi", "si", "smo", "ste", "su", "sve", "svi", "svog", "svoj", "svoja", "svoje", "svom", "ta", "tada", "taj", "tako", "te", "tebe", "tebi", "ti", "to", "toj", "tome", "tu", "tvoj", "tvoja", "tvoje", "u", "uz", "vam", "vama", "vas", "vaš", "vaša", "vaše", "već", "vi", "vrlo", "za", "zar", "će", "ćemo", "ćete", "ćeš", "ću", "što"];
                break;
        }

        if (empty($stopWords)) {
            return $term;
        }

        foreach ($stopWords as &$word) {
            $word = '/\b' . preg_quote($word, '/') . '\b/i';
        }

        $ret = preg_replace($stopWords, '', $ret);
        $ret = trim(preg_replace('/\s+/', ' ', $ret));

        return $ret;
    }

    /**
     * @param $word
     * @param $term
     * @return mixed|string
     */
    static function removeWordFromString($word, $term)
    {

        $ret = $term;

        if (empty(trim($word))) {
            return $ret;
        }

        if (empty(trim($term))) {
            return $ret;
        }

        $word = '/\b' . preg_quote($word, '/') . '\b/i';
        $ret = preg_replace($word, '', $ret);
        $ret = trim(preg_replace('/\s+/', ' ', $ret));

        return $ret;
    }

    /**
     * @param $phone
     * @return array|string|string[]
     */
    static function cleanPhone($phone)
    {

        $phone = trim($phone);

        if (empty($phone)) {
            return $phone;
        }

        $clean = false;
        if (stripos($phone, "/") !== false) {
            $phone = str_ireplace("/", "", $phone);
        }
        if (stripos($phone, "-") !== false) {
            $phone = str_ireplace("-", "", $phone);
        }
        if (stripos($phone, " ") !== false) {
            $phone = str_ireplace(" ", "", $phone);
        }
        if (!StringHelper::startsWith($phone, "00385")) {
            $clean = true;

            $phone = ltrim($phone, "0");
            if (StringHelper::startsWith($phone, "38")) {
                $phone = substr($phone, 3);
            }

            if (!preg_match('/^[9|1|2|3|4|5][0-9]{7,8}$/', $phone)) {
                $phone = null;
            } else {
                $phone = "00385" . $phone;
            }
        }

        return $phone;
    }

    static function substrAtWordBoundary($string, $chars = 100)
    {
        preg_match('/^.{0,' . $chars . '}(?:.*?)\b/iu', $string, $matches);
        $new_string = $matches[0];
        return ($new_string === $string) ? $string : $new_string;
    }

    /**
     * @param $ean
     * @return bool
     */
    public static function isValidEan($ean)
    {
        if (!is_numeric($ean)) {
            return false;
        }

        $ean = strrev($ean);
        // Split number into checksum and number
        $checksum = substr($ean, 0, 1);
        $number = substr($ean, 1);
        $total = 0;
        for ($i = 0, $max = strlen($number); $i < $max; $i++) {
            if (($i % 2) == 0) {
                $total += ($number[$i] * 3);
            } else {
                $total += $number[$i];
            }

        }
        $mod = ($total % 10);
        $calculated_checksum = (10 - $mod) % 10;
        if ($calculated_checksum == $checksum) {
            return true;
        } else {
            return false;
        }
    }
}
