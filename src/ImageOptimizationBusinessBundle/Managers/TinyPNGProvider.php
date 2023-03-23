<?php

namespace ImageOptimizationBusinessBundle\Managers;

use ImageOptimizationBusinessBundle\Interfaces\IOptimizeImage;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TinyPNGProvider implements IOptimizeImage, ContainerAwareInterface
{
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function initialize()
    {
    }

    private $curl;
    private $url;
    private $key;

    /**
     * Set default curl parameters that can be used for all actions
     */
    private function setDefaultParams()
    {
        $this->url = $_ENV["TINYPNG_URL"];
        $this->key = $_ENV["TINYPNG_KEY"];

        $this->curl = curl_init();

        $curlDefaults = array(
            CURLOPT_URL => $this->url,
            CURLOPT_USERPWD => 'api:'.$this->key,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST"
        );

        curl_setopt_array($this->curl, $curlDefaults);
    }

    /**
     * Optimize image using original image URL
     * Returns optimized image URL on success, null on failure
     * @param $url
     * @return |null
     */
    private function optimizeImgUsingURL($url)
    {
        $this->setDefaultParams();

        // set parameters for uploading from url
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

        $requestBody = array(
            "source" => array(
                "url" => $url
            )
        );
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($requestBody));

        $result = json_decode(curl_exec($this->curl), true);
        if (curl_getinfo($this->curl, CURLINFO_HTTP_CODE) === 201) {
            //curl_close($this->curl);
            return $result["output"]["url"];
        } else {
            // invalid status code
            // 2xx: success
            // 4xx: invalid request
            // 5xx: temporary API problem
        }

        //curl_close($this->curl);
        return null;
    }

    /**
     * Optimize image using local image file path
     * Returns optimized image URL on success, null on failure
     * @param $file
     * @return |null
     */
    private function optimizeImgUsingLocalPath($file)
    {
        $this->setDefaultParams();

        if (file_exists($file)) {
            $buffer = file_get_contents($file);
            if ($buffer !== false) {
                curl_setopt($this->curl, CURLOPT_POSTFIELDS, $buffer);
                $result = json_decode(curl_exec($this->curl), true);
                if (curl_getinfo($this->curl, CURLINFO_HTTP_CODE) === 201) {
                    //curl_close($this->curl);
                    return $result["output"]["url"];
                } else {
                    // invalid status code
                    // 2xx: success
                    // 4xx: invalid request
                    // 5xx: temporary API problem
                }
            } else {
                // cannot read file/file is empty
            }
        } else {
            // file does not exist
        }

        //curl_close($this->curl);
        return null;
    }

    /**
     * @param $imgLocation
     * @return |null
     */
    public function optimizeImage($imgLocation)
    {
        // check where the image is located
        if (strpos($imgLocation, "http") === 0) {
            return $this->optimizeImgUsingURL($imgLocation); // image location is an url
        } else {
            return $this->optimizeImgUsingLocalPath($imgLocation); // image is stored locally
        }
    }

    /**
     * Before resizing, image must be optimized using optimizeImage function,
     * the optimized image TinyPNG URL must then be used as a parameter for this function
     * Returns resized image buffer on success, null on failure
     * @param $imgLocation
     * @param $method
     * scale: Scales the image down proportionally. You must provide either a target width or a target height, but not both.
     * The scaled image will have exactly the provided width or height.
     * fit: Scales the image down proportionally so that it fits within the given dimensions.
     * You must provide both a width and a height. The scaled image will not exceed either of these dimensions.
     * @param $width
     * @param $height
     * @return bool|string|null |null
     */
    function resizeImage($imgLocation, $method, $width, $height)
    {
        if (!$width && !$height) {
            return null;
        }

        $this->setDefaultParams();

        $url = $this->optimizeImage($imgLocation);
        if (!$url) {
            return null;
        }

        // important: replace the default URL with an URL of a previously optimized image
        curl_setopt($this->curl, CURLOPT_URL, $url);

        // resize parameters are organized in a JSON array
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

        $resizeParams = array(
            "resize" => array(
                "method" => $method
            )
        );

        if ($width) {
            $resizeParams["resize"]["width"] = $width;
        }
        if ($height) {
            $resizeParams["resize"]["height"] = $height;
        }

        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($resizeParams));
        //curl_setopt($this->curl, CURLINFO_HEADER_OUT, true);

        $result = curl_exec($this->curl);
        if (curl_getinfo($this->curl, CURLINFO_HTTP_CODE) === 200) {
            //curl_close($this->curl);
            return $result;
        } else {
            // invalid status code
            // 2xx: success
            // 4xx: invalid request
            // 5xx: temporary API problem
        }

        //curl_close($this->curl);
        return null;
    }
}
