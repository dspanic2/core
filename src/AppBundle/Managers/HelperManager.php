<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\CoreContext;
use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\ApiAccessEntity;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Entity\SortFilter;
use AppBundle\Entity\SortFilterCollection;
use AppBundle\Entity\UserEntity;
use AppBundle\Entity\UserRoleEntity;
use AppBundle\Helpers\StringHelper;
use AppBundle\Security\Authenticator;
use CrmBusinessBundle\Entity\AccountEntity;
use CrmBusinessBundle\Entity\AddressEntity;
use CrmBusinessBundle\Entity\ContactEntity;
use CrmBusinessBundle\Managers\AccountManager;
use HrBusinessBundle\Entity\CityEntity;
use HrBusinessBundle\Entity\EmployeeEntity;
use HrBusinessBundle\Managers\HrManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class HelperManager extends AbstractBaseManager
{
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var AccountManager $accountManager */
    protected $accountManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @return mixed
     */
    public function getCurrentUser()
    {
        if (empty($this->user)) {
            return null;
        }

        return $this->user;
    }

    /**
     * @return mixed|null
     */
    public function reloadCurrentUser()
    {
        $this->user = $this->getCurrentUser();
        if (empty($this->user) || !is_object($this->user)) {
            /** @var TokenStorage $tokenStorage */
            $tokenStorage = $this->container->get("security.token_storage");
            if (!empty($tokenStorage->getToken())) {
                $this->user = $tokenStorage->getToken()->getUser();
                //$this->setUser($this->user);
                $this->entityManager->setUser($this->user);
            } else {
                $this->logger->error("Helper manager - token is empty");
            }
        }

        return $this->user;
    }

    /**
     * @return bool|null
     */
    public function getCurrentCoreUser()
    {
        $user = $this->getCurrentUser();
        if (empty($user) || !is_object($user)) {
            return false;
        }

        $coreUserEntityType = $this->entityManager->getEntityTypeByCode("core_user");

        return $this->entityManager->getEntityByEntityTypeAndId($coreUserEntityType, $user->getId());
    }

    /**
     * @param CoreUserEntity $coreUserEntity
     * @return array
     */
    public function getRolesForCoreUser(CoreUserEntity $coreUserEntity)
    {
        $roles = array();

        $userRoleEntityType = $this->entityManager->getEntityTypeByCode("core_user_role_link");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("coreUser", "eq", $coreUserEntity->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $userRoles = $this->entityManager->getEntitiesByEntityTypeAndFilter($userRoleEntityType, $compositeFilters);

        if (!empty($userRoles)) {
            /** @var UserRoleEntity $userRole */
            foreach ($userRoles as $userRole) {
                $roles[] = $userRole->getRole();
            }
        }

        return $roles;
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function loginAnonymus(Request $request, $username = 'anonymous', $password = null)
    {
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->container->get("security.token_storage");

        /** @var Authenticator $authenticator */
        $authenticator = $this->container->get("authenticator");

        $data["providerKey"] = "#1!BSi*5#b^XyBo5DgpUg1Z";

        if (empty($tokenStorage->getToken())) {
            $token = $authenticator->createToken($request, $username, $password, $data["providerKey"]); //"57744292"
            $tokenStorage->setToken($token);
        }

        $userProvider = $this->container->get('shape_user_provider');

        $data["username"] = $username;
        $data["password"] = $password;

        $token = $authenticator->authenticateToken($tokenStorage->getToken(), $userProvider, $data, $username);

        if (empty($token)) {
            return false;
        }

        $tokenStorage->setToken($token);

        // Fire the login event
        // Logging the user in above the way we do it doesn't do this automatically
        $event = new InteractiveLoginEvent($request, $token);
        $this->container->get("event_dispatcher")->dispatch("security.interactive_login", $event);

        $this->reloadCurrentUser();

        return true;
    }

    /**
     * @param $username
     * @param string $mailTemplate
     * @return bool
     */
    public function resetUserPassword($username, $mailTemplate = "reset_password_fos")
    {
        if (empty($username)) {
            return false;
        }

        /** @var UserEntity $user */
        $user = $this->getUserByUsername($username);

        if (empty($user)) {

            /** @var UserEntity $user */
            $user = $this->getUserByEmail($username);
        }

        if (empty($user)) {
            return false;
        }

        if (null === $user->getConfirmationToken()) {
            /** @var $tokenGenerator \FOS\UserBundle\Util\TokenGeneratorInterface */
            $tokenGenerator = $this->container->get('fos_user.util.token_generator');
            $user->setConfirmationToken($tokenGenerator->generateToken());
        }

        //$confirmationUrl = $this->container->get('router')->generate('fos_user_resetting_reset', array('token' => $user->getConfirmationToken()), UrlGeneratorInterface::ABSOLUTE_URL);
        $confirmationUrl = "https://copyelectronic.page.link/?amv=1&apn=com.shipshape_solutions.copyelectronic&link=https://copy-electronic.hr?token={$user->getConfirmationToken()}";

        /** @var MailManager $mailManager */
        $mailManager = $this->container->get("mail_manager");
        $mailManager->sendEmail(array('email' => $user->getEmail(), 'name' => $user->getEmail()), null, null, null, $this->translator->trans("Reset password"), "", $mailTemplate, array("user" => $user, "confirmationUrl" => $confirmationUrl));

        $user->setPasswordRequestedAt(new \DateTime());

        $this->container->get('fos_user.user_manager')->updateUser($user);

        return true;
    }

    /**
     * @param $params
     * @return array|bool
     */
    public function getDistanceBetweenTwoPointsGoogleApi($params)
    {
        $query = array();
        foreach ($params as $key => $param) {
            $query[] = $key . "=" . $param;
        }

        $query = implode("&", $query);

        $gmaps_key = $_ENV["GMAPS_KEY"];
        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?{$query}&key={$gmaps_key}";
        $resp = json_decode(file_get_contents($url), true);

        if ($resp['status'] === 'OK') {
            if (!isset($resp['rows'][0]['elements'][0]["distance"])) {
                return false;
            }

            return array('distance' => $resp['rows'][0]['elements'][0]["distance"]["value"]);
        }

        return false;
    }

    /**
     * @param $string
     * @param $fromLangCode
     * @param $toLangCode
     * @return string
     */
    public function translateString($string, $fromLangCode, $toLangCode)
    {
        $translated_text = "No google translation";

        $string = preg_replace('/_/', ' ', $string);

        $gmaps_key = $_ENV["GMAPS_KEY"];
        $url = 'https://www.googleapis.com/language/translate/v2?key=' . $gmaps_key . '&q=' . rawurlencode($string) . '&source=' . $fromLangCode . '&target=' . $toLangCode;
        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($handle);
        $responseDecoded = json_decode($response, true);

        curl_close($handle);

        if (isset($responseDecoded['data']['translations'][0]['translatedText'])) {
            $translated_text = $responseDecoded['data']['translations'][0]['translatedText'];
        }

        return $translated_text;
    }

    /**
     * @param $query
     * @return array|bool
     */
    public function getCoordinatesFromGoogleApi($query)
    {
        $query = urlencode($query);

        $gmaps_key = $_ENV["GMAPS_KEY"];
        $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$query}&key={$gmaps_key}";
        $response = null;
        try {
            $response = file_get_contents($url);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        if (empty($response)) {
            return false;
        }
        $resp = json_decode($response, true);

        if ($resp['status'] === 'OK') {
            if (!isset($resp['results'][0]['formatted_address']) || empty($resp['results'][0]['formatted_address']) ||
                !isset($resp['results'][0]['geometry']['location']['lat']) || empty($resp['results'][0]['geometry']['location']['lat']) ||
                !isset($resp['results'][0]['geometry']['location']['lng']) || empty($resp['results'][0]['geometry']['location']['lng'])) {
                return false;
            }
            return array('address' => $resp['results'][0]['formatted_address'], 'lat' => $resp['results'][0]['geometry']['location']['lat'], 'lng' => $resp['results'][0]['geometry']['location']['lng']);
        }

        return false;
    }

    /**
     * @param $date
     * @return mixed
     * TODO ovo prebaciti u HNB manager
     */
    public function getHNBCurrencyForDate($date, $currencyCode = null)
    {
        $url = "http://api.hnb.hr/tecajn/v1?datum={$date}";
        if (!empty($currencyCode)) {
            $url .= "&valuta=" . $currencyCode;
        }
        $resp = json_decode(file_get_contents($url), true);

        return $resp;
    }

    public function getCoordinatesFromOpenStreet($query)
    {
        $query = urlencode($query);

        $ch = curl_init();
        /* Simulate Chrome 41.0.2228.0 on OSX */
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36");

        /* Port is typically 3128 or 13129 for HTTP/HTTPS and 21 for SOCKS5. Check your account info at https://getfoxyproxy.org/panel */
        //curl_setopt($ch, CURLOPT_PROXY, "45.58.62.54:13129");

        /* Can also use CURLPROXY_SOCKS5 or CURLPROXY_SOCKS5_HOSTNAME for CURLOPT_PROXYTYPE */
        //curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);

        /* Your FoxyProxy account username and password */
        //curl_setopt($ch, CURLOPT_PROXYUSERPWD, "silkypure:prigudder");

        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_URL, "https://nominatim.openstreetmap.org/search?q={$query}&addressdetails=1&format=json");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_REFERER, "");
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $result = curl_exec($ch);
        curl_close($ch);

        if (empty($result)) {
            return false;
        }

        $result = json_decode($result, true);
        if (empty($result)) {
            return false;
        }

        foreach ($result as $r) {
            /*if ($r["type"] != "administrative" &&
                $r["type"] != "city" &&
                $r["type"] != "town" &&
                $r["type"] != "island" &&
                $r["type"] != "neighbourhood" &&
                $r["type"] != "village"
            ) {
                continue;
            }*/

            if (!isset($r["lat"]) || empty($r["lat"])) {
                continue;
            }

            $location = $r["address"];
            $location["lat"] = $r["lat"];
            $location["lng"] = $r["lon"];
            $location["name"] = $r["display_name"];

            return $location;
        }

        return false;
    }

    /**
     * Save raw data to a file on disk
     * @param $localPath
     * @param $rawData
     * @return false|int
     */
    public function saveRawDataToFile($rawData, $localPath)
    {
        $bytesWritten = 0;
        if (file_exists($localPath)) {
            unlink($localPath);
        }
        $fp = fopen($localPath, 'x');
        if ($fp) {
            $bytesWritten = fwrite($fp, $rawData);
            fclose($fp);
        }
        return $bytesWritten;
    }

    /**
     * @param $dir
     */
    function rmdir_recursive($dir)
    {
        foreach (scandir($dir) as $file) {
            if ('.' === $file || '..' === $file) continue;
            if (is_dir("$dir/$file")) $this->rmdir_recursive("$dir/$file");
            else unlink("$dir/$file");
        }
        rmdir($dir);
    }

    /**
     * Download and save remote file to disk using CURL GET
     * @param $url
     * @param $localPath
     * @return bool|false|int
     */
    public function saveRemoteFileToDisk($url, $localPath)
    {
        if (empty($url) || empty($localPath)) {
            return false;
        }

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $rawData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (empty($rawData) || $httpCode !== 200) {
            return false;
        }

        return $this->saveRawDataToFile($rawData, $localPath);
    }

    public function prepareBlockGrid($content)
    {
        $blocksArray = array();
        foreach ($content ?? [] as $key => $block) {
            $blocksArray[] = array(
                "id" => $block["id"],
                "key" => $key,
                "width" => $block["width"] ?? 12,
                "x" => $block["x"] ?? 0,
                "y" => $block["y"] ?? 100
            );
        }

        $preparedContent = array();

        $currentWidthSum = 0;
        $currentLine = 0;
        foreach ($blocksArray as $key => $block) {
            $currentWidthSum = $currentWidthSum + intval($block["width"]);

            if ($currentWidthSum < 13) {
                $preparedContent[$currentLine]["blocks"][] = $content[$block["key"]];
            } else {
                $currentLine++;
                $preparedContent[$currentLine]["blocks"][] = $content[$block["key"]];
                $currentWidthSum = intval($block["width"]);
            }
        }

        return $preparedContent;
    }

    public function unsetFromArrayByNestedKeyAndValue($array, $nestedKey, $nestedValue)
    {
        foreach ($array as $key => $values) {
            foreach ($values as $name => $value) {
                if ($name == $nestedKey && in_array($value, $nestedValue)) {
                    unset($array[$key]);
                }
            }
        }
        return $array;
    }

    public function prepareQueryForQuickSearch($query)
    {
        $queryParts = explode("+", trim($query));

        foreach ($queryParts as $key => $part) {
            if (strlen(trim($part)) < 3) {
                unset($queryParts[$key]);
            }
        }

        return $queryParts;
    }

    /**
     * @param $filename
     * @return string
     * @deprecated  prebaceno u StringHelper
     */
    public function sanitizeFileName($filename)
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

    /**
     * @param $name
     * @return false|string|string[]|null
     */
    public function nameToFilename($name)
    {
        // Replace all special characters
        $name = StringHelper::sanitizeFileName(trim($name));

        $name = strtolower($name);

        // Remove all characters except:
        // Word: alphanumeric, underscore
        // Whitespace: spaces, tabs, line breaks
        // Digit: 0-9
        $name = mb_ereg_replace("([^\w\s\d\-])", "", $name);

        // Replace whitespace with underscore
        $name = preg_replace("/[\s]+/", "_", $name);

        return $name;
    }

    /**
     * @param $s
     * @return false|string
     */
    public function getFileExtension($s)
    {
        $n = strrpos($s, ".");
        return ($n === false) ? "" : substr($s, $n + 1);
    }

    /**
     * @param $s
     * @return false|string
     */
    public function getFilenameWithoutExtension($s)
    {
        $n = strrpos($s, ".");
        return ($n === false) ? $s : substr($s, 0, $n);
    }

    /**
     * @param $s
     * @param bool $decode
     * @return false|string
     */
    public function getFilenameFromUrl($s, $decode = false)
    {
        if ($decode) {
            $s = urldecode($s);
        }
        $n = strrpos($s, "/");
        return ($n === false) ? $s : substr($s, $n + 1);
    }

    /**
     * @param $path
     * @param $filename
     * @return string
     */
    public function incrementFileName($path, $filename)
    {
        $ext = "";
        $ret = $filename;

        $n = strrpos($filename, ".");
        if ($n !== false) {
            $ext = substr($filename, $n);
            $filename = substr($filename, 0, $n);
        }

        $count = count(glob($path . $filename . "*" . $ext));
        if ($count > 0) {
            do {
                $ret = $filename . "-" . $count++ . $ext;
            } while (file_exists($path . $ret));
        }

        return $ret;
    }

    /**
     * @param $salt
     * @return |null
     */
    public function getUserBySalt($salt)
    {
        /** @var CoreContext $userContext */
        $userContext = $this->container->get("user_entity_context");

        return $userContext->getOneBy(array("salt" => $salt));
    }

    /**
     * @param $username
     * @return |null
     */
    public function getUserByUsername($username)
    {
        /** @var CoreContext $userContext */
        $userContext = $this->container->get("user_entity_context");

        return $userContext->getOneBy(array("username" => $username));
    }

    /**
     * @param $email
     * @return |null
     */
    public function getUserByEmail($email)
    {
        /** @var CoreContext $userContext */
        $userContext = $this->container->get("user_entity_context");

        return $userContext->getOneBy(array("email" => $email));
    }

    /**
     * @param $user
     * @return ApiAccessEntity|bool
     * @throws \Exception
     */
    public function getTokenByUser($user)
    {
        if (empty($user)) {
            return false;
        }

        $apiAccessEntityType = $this->entityManager->getEntityTypeByCode("api_access");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("coreUser", "eq", $user->getId()));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var ApiAccessEntity $token */
        $token = $this->entityManager->getEntityByEntityTypeAndFilter($apiAccessEntityType, $compositeFilters);

        if (empty($token)) {
            $token = $this->createToken($user);
            if (empty($token)) {
                return false;
            }
        } else {
            $token = $this->refreshToken($token);
        }

        return $token;
    }

    /**
     * @param ApiAccessEntity $token
     * @return ApiAccessEntity
     * @throws \Exception
     */
    public function refreshToken(ApiAccessEntity $token)
    {
        $now = new \DateTime("now");
        $validTo = new \DateTime('now +1 day');

        $token->setDateLastAccess($now);
        $token->setDateValid($validTo);

        $this->entityManager->saveEntityWithoutLog($token);

        return $token;
    }

    /**
     * @param $user
     * @return ApiAccessEntity|bool
     * @throws \Exception
     */
    public function createToken($user)
    {
        if (empty($user)) {
            return false;
        }

        /** @var ApiAccessEntity $token */
        $token = $this->entityManager->getNewEntityByAttributSetName("api_access");

        $now = new \DateTime("now");

        $validTo = new \DateTime('now +1 day');

        $token->setCoreUser($user);
        $token->setToken(StringHelper::generateRandomString(20, false));
        $token->setRefreshToken(StringHelper::generateRandomString(20, false));
        $token->setDateLastAccess($now);
        $token->setDateValid($validTo);

        $this->entityManager->saveEntityWithoutLog($token);

        return $token;
    }

    /**
     * @param $refreshToken
     * @return ApiAccessEntity|bool
     * @throws \Exception
     */
    public function regenerateToken($refreshToken)
    {
        if (empty($refreshToken)) {
            return false;
        }

        $apiAccessEntityType = $this->entityManager->getEntityTypeByCode("api_access");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("refreshToken", "eq", $refreshToken));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var ApiAccessEntity $token */
        $token = $this->entityManager->getEntityByEntityTypeAndFilter($apiAccessEntityType, $compositeFilters);

        if (empty($token)) {
            return false;
        }

        $token->setToken(StringHelper::generateRandomString(20, false));
        $token->setRefreshToken(StringHelper::generateRandomString(20, false));

        $this->entityManager->saveEntityWithoutLog($token);

        $token = $this->refreshToken($token);

        return $token;
    }

    /**
     * @param $request
     * @param $data
     * @return array
     * @throws \Exception
     */
    public function getUserByTokenAndRefreshToken($request, $data)
    {

        $ret = ["error" => true];

        /** @var CoreUserEntity $coreUser */
        $ret["core_user"] = $this->getUserByToken($request, $data["token"]);
        if (empty($ret["core_user"])) {
            if (isset($data["refresh_token"]) && !empty($data["refresh_token"])) {
                /** @var ApiAccessEntity $token */
                $token = $this->regenerateToken($data["refresh_token"]);
                if (empty($token)) {
                    $ret["message"] = $this->translator->trans('Token does not exist');
                    $ret["login_required"] = $this->translator->trans('Please login');
                    return $ret;
                }
                $ret["core_user"] = $token->getCoreUser();
                $ret["token"] = $token->getToken();
                $ret["refresh_token"] = $token->getRefreshToken();
            } else {
                $ret["title"] = $this->translator->trans('Token not valid');
                return $ret;
            }
        }

        return $ret;
    }


    /**
     * @param $request
     * @param $userToken
     * @return \AppBundle\Entity\CoreUser|bool
     * @throws \Exception
     */
    public function getUserByToken($request, $userToken)
    {
        if (empty($userToken)) {
            return false;
        }

        $now = new \DateTime("now");

        $apiAccessEntityType = $this->entityManager->getEntityTypeByCode("api_access");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("token", "eq", $userToken));
        $compositeFilter->addFilter(new SearchFilter("dateValid", "ge", $now->format("Y-m-d H:i:s")));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        /** @var ApiAccessEntity $token */
        $token = $this->entityManager->getEntityByEntityTypeAndFilter($apiAccessEntityType, $compositeFilters);
        if (empty($token)) {
            return false;
        }

        $this->loginAnonymus($request, $token->getCoreUser()->getUsername());

        $token = $this->refreshToken($token);

        return $token->getCoreUser();
    }

    /**
     * @param $id
     * @return |null
     */
    public function getCoreUserById($id)
    {
        $coreUserEntityType = $this->entityManager->getEntityTypeByCode("core_user");

        return $this->entityManager->getEntityByEntityTypeAndId($coreUserEntityType, $id);
    }

    /**
     * @param $table
     * @param $attribute
     * @return boolean
     */
    public function isAttributeJsonType($table, $attribute)
    {
        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $dbDataQuery = "DESCRIBE $table $attribute";
        $dbData = $this->databaseContext->getAll($dbDataQuery);

        if ($dbData[0]["Type"] == "json") {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $filepath
     * @param $line
     * @return bool|void
     */
    public function addLineToEndOfFile($filepath, $line){

        if (empty($filepath)) {
            return true;
        }

        $f = fopen($filepath, "a") or die("Unable to open file!");
        fwrite($f, "\n". $line);
        fclose($f);

        return true;
    }

    /**
     * @param $filepath
     * @param $textToFind
     * @return bool
     */
    public function removeLineFromFile($filepath, $textToFind)
    {

        if (empty($filepath)) {
            return true;
        }

        $rows = file($filepath);

        foreach ($rows as $key => $row) {
            if (preg_match("/($textToFind)/i", $row)) {
                unset($rows[$key]);
            }
        }

        file_put_contents($filepath, implode("", $rows));

        return true;
    }

    /**
     * @param $filepath
     * @param $textToFind
     * @param $testToSet
     * @param bool $useRegex
     * @return bool
     */
    public function updateLineFromFile($filepath, $textToFind, $testToSet, $useRegex = true)
    {

        if (empty($filepath)) {
            return true;
        }

        if ($useRegex) {
            $rows = file($filepath);

            foreach ($rows as $key => $row) {
                if (preg_match("/($textToFind)/i", $row)) {
                    $rows[$key] = str_replace($textToFind, $testToSet, $row);
                }
            }

            file_put_contents($filepath, implode("", $rows));
        } else {
            $fileContent = file_get_contents($filepath);
            if (strpos($fileContent, $textToFind) !== false) {
                $fileContent = str_replace($textToFind, $testToSet, $fileContent);
                file_put_contents($filepath, $fileContent);
            }
        }

        return true;
    }

    /**
     * @return array|mixed|void
     */
    public function getAllEnvFiles()
    {

        $envFiles = scandir($_ENV["WEB_PATH"] . "/..");
        if (empty($envFiles)) {
            $ret["error"][] = "WEB_PATH is not falid";
            return $ret;
        }

        $ret = array();

        foreach ($envFiles as $envFile) {
            if (stripos($envFile, ".env") !== false && stripos($envFile, "example") === false) {
                $ret[] = $envFile;
            }
        }

        return $ret;
    }

    /**
     * @param null $type
     * @return array
     */
    public function validateEnv($type = "")
    {

        $ret = array();

        $envFiles = scandir($_ENV["WEB_PATH"] . "/..");
        if (empty($envFiles)) {
            $ret["error"][] = "WEB_PATH is not falid";
            return $ret;
        }

        $envFound = 0;
        $configurationEnvFound = 0;

        foreach ($envFiles as $envFile) {
            if (stripos($envFile, ".env") !== false && stripos($envFile, "example") === false) {
                if ($envFile == ".env") {
                    $envFound = 1;
                } elseif ($envFile == "configuration.env") {
                    $configurationEnvFound = 1;
                }

                $filepath = null;

                /**
                 * configuration.env etc
                 */
                if ($envFile != ".env") {
                    if (!$_ENV["IS_PRODUCTION"]) {
                        $filepath = $_ENV["WEB_PATH"] . "/.." . "/{$envFile}";
                    }
                    $envFile = str_ireplace(".", "_", $envFile);
                } /**
                 * .env
                 */
                else {
                    $filepath = $_ENV["WEB_PATH"] . "/.." . "/{$envFile}";
                    $envFile = str_ireplace(".", "", $envFile);
                }

                $arrContextOptions = array(
                    "ssl" => array(
                        "verify_peer" => false,
                        "verify_peer_name" => false,
                    ),
                );

                try {
                    $envData = file_get_contents("https://crm.shipshape-solutions.com/default_{$envFile}{$type}.txt", false, stream_context_create($arrContextOptions));
                } catch (\Exception $e) {
                    $ret["error"][] = "Missing default_{$envFile}{$type}.txt on https://crm.shipshape-solutions.com/default_{$envFile}{$type}.txt";
                    continue;
                }
                $envData = preg_replace("/\r|\n|\t+/", "", $envData);
                $envData = json_decode($envData, true);

                if (!empty($type)) {
                    foreach ($envData as $d => $values) {

                        $mandatory = true;
                        if (isset($values["mandatory"])) {
                            $mandatory = $values["mandatory"];
                        }

                        $auto_add = false;
                        if (isset($values["auto_add"])) {
                            $auto_add = $values["auto_add"];
                        }

                        $default = null;
                        if (isset($values["default"])) {
                            $default = $values["default"];
                        }

                        $comment = null;
                        if (isset($values["comment"])) {
                            $comment = $values["comment"];
                        }

                        $remove = false;
                        if (isset($values["remove"])) {
                            $remove = $values["remove"];
                        }

                        if ($remove && isset($_ENV[$d])) {
                            $this->removeLineFromFile($filepath, "{$d}=");
                            if (!empty($comment)) {
                                $this->removeLineFromFile($filepath, "#{$comment}");
                            }
                            continue;
                        }

                        if (!isset($_ENV[$d])) {
                            if ($mandatory && !$auto_add && empty($remove)) {
                                $ret["missing"][$envFile][] = array($d => $values);
                                continue;
                            }

                            if ($auto_add && !empty($filepath) && empty($remove)) {
                                $fp = fopen($filepath, 'a');
                                if (!empty($comment)) {
                                    fwrite($fp, PHP_EOL . "#{$comment}");
                                }
                                fwrite($fp, PHP_EOL . "{$d}={$default}" . PHP_EOL);
                                fclose($fp);
                            }
                        }
                    }
                } /**
                 * fall back with no defaults
                 */
                else {
                    foreach ($envData as $d) {
                        if (!isset($_ENV[$d])) {
                            $ret["missing"][$envFile][] = $d;
                        }
                    }
                }
            }
        }

        if (!$envFound) {
            $ret["error"][] = "Missing .env file";
        }
        if (!$configurationEnvFound) {
            $ret["error"][] = "Missing configuration.env file";
        }

        return $ret;
    }

    /**
     * @param CoreUserEntity $user
     * @return ContactEntity
     * @throws \Exception
     */
    public function generateAccountAndContactForAdmin(CoreUserEntity $user)
    {

        if (!empty($user->getDefaultContact()) || $user->getUsername() == "system") {
            return false;
        }

        $frontendAdminAccountRoles = $_ENV["FRONTEND_ADMIN_ACCOUNT_ROLES"] ?? 0;
        if (empty($frontendAdminAccountRoles)) {
            return false;
        }
        $frontendAdminAccountRoles = json_decode($frontendAdminAccountRoles, true);

        if (empty($frontendAdminAccountRoles)) {
            return false;
        }

        if (count(array_intersect($frontendAdminAccountRoles, $user->getUserRoleCodes())) === 0) {
            return false;
        }

        if (empty($this->accountManager)) {
            $this->accountManager = $this->container->get("account_manager");
        }

        if (empty($this->databaseContext)) {
            $this->databaseContext = $this->container->get("database_context");
        }

        $q = "DELETE FROM contact_entity WHERE email = '{$user->getEmail()}';";
        $this->databaseContext->executeNonQuery($q);

        $q = "DELETE FROM account_entity WHERE email = '{$user->getEmail()}';";
        $this->databaseContext->executeNonQuery($q);

        $accountData = array();
        $accountData["first_name"] = $user->getFirstName();
        $accountData["last_name"] = $user->getLastName();
        $accountData["email"] = $user->getEmail();
        $accountData["is_active"] = 1;
        $accountData["is_legal_entity"] = 0;

        /** @var AccountEntity $account */
        $account = $this->accountManager->insertAccount("account", $accountData);

        $contactData = array();
        $contactData["first_name"] = $user->getFirstName();
        $contactData["last_name"] = $user->getLastName();
        $contactData["email"] = $user->getEmail();
        $contactData["is_active"] = 1;
        $contactData["account"] = $account;
        $contactData["core_user"] = $user;

        /** @var ContactEntity $contact */
        $contact = $this->accountManager->insertContact($contactData);

        /** @var CityEntity $city */
        $city = $this->accountManager->getCityByPostalCode(10000);

        $addressData = array();
        $addressData["account"] = $account;
        $addressData["city"] = $city;
        $addressData["street"] = "Neka adresa";
        $addressData["first_name"] = $user->getFirstName();
        $addressData["last_name"] = $user->getLastName();
        $addressData["billing"] = true;

        /** @var AddressEntity $address */
        $address = $this->accountManager->insertAddress("address", $addressData);

        return $contact;
    }

    /**
     * @param $session
     * @param $message
     * @param string $type
     * @return bool
     */
    public function setSystemMessage($session, $message, $type = "error")
    {

        $systemMessages = $session->get("system_message");

        $systemMessages[$type][] = $message;

        $session->set("system_message", $systemMessages);

        return true;
    }

    /**
     * @param $entityTypeCode
     * @return mixed
     */
    public function getCodebook($entityTypeCode, $additionalCompositeFilter = null)
    {
        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        if (!empty($additionalCompositeFilter)) {
            $compositeFilters->addCompositeFilter($additionalCompositeFilter);
        }

        $sortFilters = new SortFilterCollection();
        $sortFilters->addSortFilter(new SortFilter("id", "asc"));

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($this->entityManager->getEntityTypeByCode($entityTypeCode), $compositeFilters);
    }
}