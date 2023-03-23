<?php


namespace ImageOptimizationBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\SearchFilter;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\ErrorLogManager;
use ImageOptimizationBusinessBundle\Entity\ImageStyleEntity;
use ImageOptimizationBusinessBundle\Entity\ResponsiveImageStyleEntity;
use ImageOptimizationBusinessBundle\Entity\ResponsiveImageStyleLinkEntity;
use Monolog\Logger;

class ImageStyleManager extends AbstractBaseManager
{
    private $skipExtensions = [
        "gif",
        "jfif",
        "svg",
    ];

    const IMAGE_STYLE_URL_PREFIX = 'image_style';

    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var ErrorLogManager */
    protected $errorLogManager;
    private $basePath;

    public function initialize()
    {
        $this->basePath = $this->container->getParameter('web_path');
    }

    public function generateResponsiveImageStyle($imgRelativeUrl, $responsiveImageStyleCode, $alt, $title, $forceGenerete = false, $addWatermark = false)
    {
        if (empty($this->logger)) {
            $this->logger = $this->container->get('logger');
        }

        $preparedStyles = [
            "styles" => [],
            "alt" => $alt,
            "title" => $title,
        ];

        /** @var ResponsiveImageStyleEntity $responsiveImageStyle */
        $responsiveImageStyle = $this->loadResponsiveImageStyle($responsiveImageStyleCode);
        if (!empty($responsiveImageStyle)) {
            $styles = $responsiveImageStyle->getImageStyles();
            if (!empty($styles)) {
                /** @var ResponsiveImageStyleLinkEntity $styleLink */
                foreach ($styles as $styleLink) {
                    /** @var ImageStyleEntity $imageStyle */
                    $imageStyle = $styleLink->getImageStyle();
                    $imageStyleUrl = $this->getImageStyleImageUrl($imgRelativeUrl, $imageStyle->getCode(), $forceGenerete, $addWatermark);
                    $preparedStyles["styles"][$styleLink->getMaxWidthBreakpoint()] = $imageStyleUrl;
                }
                ksort($preparedStyles["styles"]);
            } else {
                $preparedStyles["styles"]["default"] = $imgRelativeUrl;
            }
        } else {
            $preparedStyles["styles"]["default"] = $imgRelativeUrl;
        }

        if (empty($this->twig)) {
            $this->twig = $this->container->get("templating");
        }
        return $this->twig->render("ImageOptimizationBusinessBundle::responsive_image.html.twig", ["data" => $preparedStyles]);
    }

    /**
     * @param $imgRelativeUrl
     * @param $imageStyleCode
     * @param false $forceGenerete
     * @param false $addWatermark
     * @return string|null
     *   URL of image style.
     */
    public function getImageStyleImageUrl($imgRelativeUrl, $imageStyleCode, $forceGenerete = false, $addWatermark = false)
    {
        if (empty($this->logger)) {
            $this->logger = $this->container->get('logger');
        }

        $imagePath = $this->basePath . $imgRelativeUrl;

        if (file_exists($imagePath)) {
            $extension = pathinfo($imagePath)["extension"] ?? "";
            if (in_array($extension, $this->skipExtensions)) {
                return $imgRelativeUrl;
            }

            $styleRelativeUrl = '/' . self::IMAGE_STYLE_URL_PREFIX . '/' . $imageStyleCode . $imgRelativeUrl;
            $stylePath = $this->basePath . $styleRelativeUrl;

            /** @var ImageStyleEntity $imageStyle */
            $imageStyle = $this->loadImageStyle($imageStyleCode);

            if (empty($imageStyle)) {
                return $imgRelativeUrl;
            }

            if ($imageStyle->getConvertToWebp() && (!isset($_SERVER['HTTP_ACCEPT']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false))) {
                // Leave original extension as part of filename so same filenames don't override each other if came from different extensions.
                $stylePath .= ".webp";
                $styleRelativeUrl .= ".webp";
            }

            if (!$forceGenerete && file_exists($stylePath)) {
                return $styleRelativeUrl;
            }

            if (!extension_loaded('imagick')) {
                if (empty($this->errorLogManager)) {
                    $this->errorLogManager = $this->getContainer()->get("error_log_manager");
                }
                $this->errorLogManager->logErrorEvent("Imagemagick missing!", null, true, array(), 1440);

                return false;
            }

            if ($this->generateImageStyle($imagePath, $imageStyle, $stylePath, $addWatermark)) {
                return $styleRelativeUrl;
            }

            $this->logger->error("Error generating style " . $imageStyleCode . " for " . $imagePath);

            return false;
        }

        /** Privremeno gasim ovaj error jer skace ko nenormalan u dev env */
        //$this->logger->error("Missing original image file ".$imagePath);

        return false;
    }

    /**
     * @param $imageStyleCode
     * @return []
     */
    public function getImagesForStyle($imageStyleCode)
    {
        $imageStyle = $this->loadImageStyle($imageStyleCode);
        if (empty($imageStyle)) {
            print "Image style not found";

            return false;
        }

        $stylePath = $this->basePath . '/' . self::IMAGE_STYLE_URL_PREFIX . '/' . $imageStyleCode;

        if (!file_exists($stylePath)) {
            print "Style directory does not exist for " . $imageStyleCode;

            return [];
        }

        return $this->listFilesInFolder($stylePath);
    }

    public function getImageWidth($imagePath)
    {
        if (empty($this->logger)) {
            $this->logger = $this->container->get('logger');
        }

        if (strpos($imagePath, $this->basePath) === false) {
            $imagePath = $this->basePath . $imagePath;
        }
        if (file_exists($imagePath)) {
            try {
                $size = getimagesize($imagePath);
                if (isset($size[0])) {
                    return $size[0];
                }
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }

        return "";
    }

    public function getImageHeight($imagePath)
    {
        if (empty($this->logger)) {
            $this->logger = $this->container->get('logger');
        }

        if (strpos($imagePath, $this->basePath) === false) {
            $imagePath = $this->basePath . $imagePath;
        }
        if (file_exists($imagePath)) {
            try {
                $size = getimagesize($imagePath);
                if (isset($size[1])) {
                    return $size[1];
                }
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }

        return "";
    }

    public function getImageTimestamp($imagePath)
    {
        if (empty($this->logger)) {
            $this->logger = $this->container->get('logger');
        }

        if (strpos($imagePath, $this->basePath) === false) {
            $imagePath = $this->basePath . $imagePath;
        }
        if (file_exists($imagePath)) {
            try {
                return filemtime($imagePath);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }

        return "";
    }

    /**
     * @param $imagePath
     * @param $imageStyle
     * @param $stylePath
     * @param false $addWatermark
     * @return bool
     */
    private function generateImageStyle($imagePath, $imageStyle, $stylePath, $addWatermark = false)
    {
        if (empty($this->logger)) {
            $this->logger = $this->container->get('logger');
        }
        // Prepare images style directory
        if ($this->prepareDirectories($stylePath)) {
            try {
                if (!empty($imageStyle)) {
                    $imagick = new \Imagick($imagePath);
                    $imagick->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
                    $width = getimagesize($imagePath)[0] ?? 0;
                    $height = getimagesize($imagePath)[1] ?? 0;

                    $styleWidth = $imageStyle->getWidth();
                    $styleHeight = $imageStyle->getHeight();

                    if (empty($styleWidth) && empty($styleHeight)) {
                        return false;
                    }

                    // Prevent stretching image
                    if (!empty($styleWidth)) {
                        if ($this->getImageWidth($imagePath) > $styleWidth) {
                            $imagick->resizeImage($styleWidth, 0, \Imagick::FILTER_LANCZOS, 1);
                            $width = $styleWidth;
                            $height = $height * ($styleWidth / $this->getImageWidth($imagePath));
                        }
                    } else {
                        if ($this->getImageHeight($imagePath) > $styleHeight) {
                            $calcualtedStyleWidth = $this->getImageWidth($imagePath) * ($styleHeight / $this->getImageHeight($imagePath));
                            $imagick->resizeImage($calcualtedStyleWidth, 0, \Imagick::FILTER_LANCZOS, 1);
                            $width = $calcualtedStyleWidth;
                            $height = $styleHeight;
                        }
                    }

                    if (!empty($styleWidth) && !empty($styleHeight)) {
                        $canvas = new \Imagick();
                        $canvas->setBackgroundColor(new \ImagickPixel('#ffffff'));
                        $canvas->newImage($styleWidth, $styleHeight, '#ffffff', 'png');
                        $canvas->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
                        $canvas->compositeImageGravity($imagick, \Imagick::COMPOSITE_OVER, \Imagick::GRAVITY_CENTER);
                        $imagick = $canvas;
                    }

                    $imagick->stripImage();
                    $this->autoRotateImage($imagick);

                    /**
                     * Add watermark.
                     */
                    if ($addWatermark && isset($_ENV["WATERMARK_PATH_$addWatermark"]) && $_ENV["WATERMARK_PATH_$addWatermark"] && file_exists($_ENV["WEB_PATH"] . $_ENV["WATERMARK_PATH_$addWatermark"])) {
                        $watermarkPath = $_ENV["WEB_PATH"] . $_ENV["WATERMARK_PATH_$addWatermark"];

                        // Calculate height by using width ratio
                        $watermarkWidth = $width / 3;
                        $watermarkHeight = $watermarkWidth / getimagesize($watermarkPath)[0] * getimagesize($watermarkPath)[1];

                        // Create instance of the Watermark image
                        $watermark = new \Imagick();
                        $watermark->readImage($watermarkPath);
                        $watermark->resizeImage($watermarkWidth, 0, \Imagick::FILTER_LANCZOS, 1);
                        $watermark->evaluateImage(\Imagick::EVALUATE_MULTIPLY, 0.5, \Imagick::CHANNEL_ALPHA);

                        // The start coordinates where the file should be printed
                        $x = $width - ($watermarkWidth + $watermarkHeight / 2);
                        $y = $height - ($watermarkHeight + $watermarkHeight / 2);

                        // Draw watermark on the image file with the given coordinates
                        $imagick->compositeImage($watermark, \Imagick::COMPO0SITE_OVER, $x, $y);
                    }

//                    if ($imageStyle->getConvertToWebp() && !$this->isSafari() && !$this->isInternetExplorer()) {
//                        $stylePath = "webp:{$stylePath}";
//                        try {
//                            $imagick->setImageCompressionQuality(50);
//                            $imagick->setOption('webp:lossless', 'true'); // POVECA FILESIZE
//                        } catch (\Exception $e) {
//                        }
//                    }

                    $imagick->writeImage($stylePath);
                    $imagick->destroy();

                    return true;
                }
                $this->logger->error("No image style");
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }

        return false;
    }

    private function loadImageStyle($imageStyleCode)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->getContainer()->get("entity_manager");
        }

        $imageStyleEntityType = $this->entityManager->getEntityTypeByCode("image_style");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $imageStyleCode));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($imageStyleEntityType, $compositeFilters);
    }

    private function loadResponsiveImageStyle($imageStyleCode)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->getContainer()->get("entity_manager");
        }

        $imageStyleEntityType = $this->entityManager->getEntityTypeByCode("responsive_image_style");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $imageStyleCode));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntityByEntityTypeAndFilter($imageStyleEntityType, $compositeFilters);
    }

    private function prepareDirectories($path)
    {
        if (empty($this->logger)) {
            $this->logger = $this->container->get('logger');
        }

        $dirname = dirname($path);
        if (!file_exists($dirname)) {
            if (!mkdir($dirname, 0777, true)) {
                $this->logger->error("Failed to create folders...");
                return false;
            }
        }

        return true;
    }

    private function listFilesInFolder($path)
    {
        $images = [];
        $files = scandir($path);
        unset($files[array_search('.', $files, true)]);
        unset($files[array_search('..', $files, true)]);

        foreach ($files as $file) {
            if (is_dir($path . '/' . $file)) {
                $images = array_merge($images, $this->listFilesInFolder($path . '/' . $file));
            } else {
                $parts = explode('/', str_replace($this->basePath, "", $path . '/' . $file));
                unset($parts[1]); // Remove image_style directory
                unset($parts[2]); // Remove directory of style itself
                $recreate_path = implode('/', $parts);
                $images[] = $recreate_path;
            }
        }

        return $images;
    }

    public function getRemoteImage($remoteUrl, $filePath)
    {
        if ($this->remoteFileExists($remoteUrl . $filePath)) {
            $this->prepareDirectories($this->basePath . $filePath);
            copy($remoteUrl . $filePath, $this->basePath . $filePath);
        }
    }

    private function remoteFileExists($url)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        $result = curl_exec($curl);
        $ret = false;
        if ($result !== false) {
            $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($statusCode == 200) {
                $ret = true;
            }
        }
        curl_close($curl);

        return $ret;
    }

    private function autoRotateImage($image)
    {
        $orientation = $image->getImageOrientation();

        switch ($orientation) {
            case \Imagick::ORIENTATION_BOTTOMRIGHT:
                $image->rotateimage("#000", 180); // rotate 180 degrees
                break;

            case \Imagick::ORIENTATION_RIGHTTOP:
                $image->rotateimage("#000", 90); // rotate 90 degrees CW
                break;

            case \Imagick::ORIENTATION_LEFTBOTTOM:
                $image->rotateimage("#000", -90); // rotate 90 degrees CCW
                break;
        }

        // Now that it's auto-rotated, make sure the EXIF data is correct in case the EXIF gets saved with the image!
        $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }

    /**
     * @return bool
     */
    private function isSafari()
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? "";
        if (stripos($user_agent, 'Safari') && !stripos($user_agent, 'Chrome')) {
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    private function isInternetExplorer()
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? "";
        if (stripos($user_agent, 'MSIE') !== false || stripos($user_agent, 'Trident') !== false) {
            return true;
        }
        return false;
    }

    /**
     * @param $imgRelativeUrl
     * @return void
     */
    public function deleteStyles($imgRelativeUrl)
    {
        if (empty($this->entityManager)) {
            $this->entityManager = $this->getContainer()->get("entity_manager");
        }

        $imageStyleEntityType = $this->entityManager->getEntityTypeByCode("image_style");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        $imageStyles = $this->entityManager->getEntitiesByEntityTypeAndFilter($imageStyleEntityType, $compositeFilters);

        /** @var ImageStyleEntity $imageStyle */
        foreach ($imageStyles as $imageStyle) {
            $styleRelativeUrl = '/' . self::IMAGE_STYLE_URL_PREFIX . '/' . $imageStyle->getCode() . $imgRelativeUrl . ($imageStyle->getConvertToWebp() ? ".webp" : "");
            $stylePath = $this->basePath . $styleRelativeUrl;
            if (file_exists($stylePath)) {
                unlink($stylePath);
            }
        }
    }
}
