<?php

namespace ScommerceBusinessBundle\Extensions;

use Mobile_Detect;
use ScommerceBusinessBundle\Entity\SWebsiteEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TwigHelperExtension extends \Twig_Extension
{
    private $globalData = [];
    private $cssDefinitions = [];
    private $jsDefinitions = [];

    /**
     * @var ContainerInterface
     */
    protected $container;

    /** @var RouteManager */
    protected $routeManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('die', array($this, 'killRender')),
            new \Twig_SimpleFilter('is_array', array($this, 'isArray')),
            new \Twig_SimpleFilter('is_json', array($this, 'isJson')),
            new \Twig_SimpleFilter('is_numeric', array($this, 'isNumeric')),
            new \Twig_SimpleFilter('shuffle', array($this, 'shuffleArray')),
        ];
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('is_internet_explorer', array($this, 'isInternetExplorer')),
            new \Twig_SimpleFunction('is_mobile', array($this, 'isMobile')),
            new \Twig_SimpleFunction('get_base_url', array($this, 'getBaseUrl')),

            // Used to pass global data to base template from anywhere else
            new \Twig_SimpleFunction('set_global_tracking_data', array($this, 'setGlobalTrackingData')),
            new \Twig_SimpleFunction('get_global_tracking_data', array($this, 'getGlobalTrackingData')),

            // Pass CSS
            new \Twig_SimpleFunction('set_css_definitions', array($this, 'setCssDefinitions')),
            new \Twig_SimpleFunction('get_css_definitions', array($this, 'getCssDefinitions')),

            // Pass JS
            new \Twig_SimpleFunction('set_js_definitions', array($this, 'setJsDefinitions')),
            new \Twig_SimpleFunction('get_js_definitions', array($this, 'getJsDefinitions')),

            new \Twig_SimpleFunction('base_64', array($this, 'getBase64String')),

            new \Twig_SimpleFunction('render_nested_twig', array($this, 'renderNestedTwig')),
        ];
    }

    /**
     * @param $string
     * @return string
     */
    public function isInternetExplorer()
    {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }

        $ua = htmlentities($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, 'UTF-8');
        if (preg_match('~MSIE|Internet Explorer~i', $ua) || (strpos($ua, 'Trident/7.0') !== false && strpos($ua, 'rv:11.0') !== false)) {
            return true;
        }
        return false;
    }

    /**
     * @param $string
     * @return string
     */
    public function getBase64String($string)
    {
        $im = file_get_contents($_ENV["WEB_PATH"] . $string);
        return base64_encode($im);
    }

    /**
     * @param $string
     */
    public function setGlobalTrackingData($string)
    {
        $this->globalData[] = $string;
    }

    /**
     * @return array
     */
    public function getGlobalTrackingData()
    {
        return $this->globalData;
    }

    /**
     * @param $string
     */
    public function setCssDefinitions($string)
    {
        $this->cssDefinitions[] = $string;
    }

    /**
     * @return array
     */
    public function getCssDefinitions()
    {
        return $this->cssDefinitions;
    }

    /**
     * @param $string
     */
    public function setJsDefinitions($string)
    {
        $this->jsDefinitions[] = $string;
    }

    /**
     * @return array
     */
    public function getJsDefinitions()
    {
        return $this->jsDefinitions;
    }

    /**
     * @param null $message
     */
    public function killRender($message = null)
    {
        dump($message);
        die();
    }

    /**
     * @param $data
     * @return bool
     */
    public function isArray($data)
    {
        return is_array($data);
    }

    /**
     * @param $string
     * @return bool
     */
    function isJson($string)
    {
        if (gettype($string) != "array") {
            json_decode($string);
            return (json_last_error() == JSON_ERROR_NONE);
        }
    }

    /**
     * @param $data
     * @return bool
     */
    function isNumeric($data)
    {
        return is_numeric($data);
    }

    /**
     * Check if the device is mobile.
     * Returns true if any type of mobile device detected, including special ones
     * @return bool
     */
    public function isMobile()
    {
        $detect = new Mobile_Detect;

        return $detect->isMobile();
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $session = $request->getSession();

        $websiteId = $session->get("current_website_id");
        if (empty($websiteId)) {
            $websiteId = $_ENV["DEFAULT_WEBSITE_ID"];
        }

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        /** @var SWebsiteEntity $website */
        $website = $this->routeManager->getWebsiteById($websiteId);

        if (empty($website)) {
            return "";
        }

        return $_ENV["SSL"] . "://" . $website->getBaseUrl();
    }

    /**
     * @param array $array
     * @return array
     */
    public function shuffleArray($array)
    {
        shuffle($array);
        return $array;
    }

    /**
     * @param array $array
     * @return array
     */
    public function renderNestedTwig($string)
    {
        return twig_template_from_string($this->container->get('twig'), $string)->render();
    }
}
