<?php


namespace ImageOptimizationBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use ImageOptimizationBusinessBundle\Interfaces\IOptimizeImage;

class OptimizeImageManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;

    /** @var IOptimizeImage $imageOptimizeProvider */
    private $imageOptimizeProvider;

    public function initialize()
    {
        $this->entityManager = $this->container->get("entity_manager");
        $this->helperManager = $this->container->get("helper_manager");
        $this->imageOptimizeProvider = $this->container->get("image_optimize_provider");
    }

    /**
     * @param $imgLocation
     * @param $rawData
     * @param $suffix
     * @return string|null
     */
    public function saveProcessedImageFromRawData($imgLocation, $rawData, $suffix)
    {
        if ($rawData) { // raw data buffer containing processed image
            $pathParts = pathinfo($imgLocation);
            $imgDirectory = $pathParts["dirname"];

            // get optimized image file path
            $imgPath = $pathParts["dirname"]."/".$pathParts["filename"].$suffix.".".$pathParts["extension"];

            if (!file_exists($imgDirectory)) {
                // directory for this entity type doesn't exist
                if (!mkdir($imgDirectory, 0777, true)) {
                    // failed to create directory
                    return null;
                }
            }

            if (file_exists($imgPath)) {
                // file already exists
                return null;
            }

            if ($this->helperManager->saveRawDataToFile($rawData, $imgPath) > 0) {
                return $imgPath;
            }
        }

        return null;
    }

    /**
     * @param $imgLocation
     * @param $imgUrl
     * @param $suffix
     * @return string|null
     */
    public function saveProcessedImageFromURL($imgLocation, $imgUrl, $suffix)
    {
        if ($imgUrl) { // remote URL containing processed image
            $pathParts = pathinfo($imgLocation);
            $imgDirectory = $pathParts["dirname"];

            // get optimized image file path
            $imgPath = $pathParts["dirname"]."/".$pathParts["filename"].$suffix.".".$pathParts["extension"];

            if (!file_exists($imgDirectory)) {
                // directory for this entity type doesn't exist
                if (!mkdir($imgDirectory, 0777, true)) {
                    // failed to create directory
                    return null;
                }
            }

            if (file_exists($imgPath)) {
                // file already exists
                return null;
            }

            // save file and return status
            if ($this->helperManager->saveRemoteFileToDisk($imgUrl, $imgPath)) {
                return $imgPath;
            }
        }

        return null;
    }

    /**
     * @param $imgLocation
     * @return bool
     */
    public function optimizeImage($imgLocation)
    {
        $imgUrl = $this->imageOptimizeProvider->optimizeImage($imgLocation);
        return $this->saveProcessedImageFromURL($imgLocation, $imgUrl, "_optimized");
    }

    /**
     * @param $imgLocation
     * @param $method
     * @param $width
     * @param $height
     * @return bool
     */
    public function resizeImage($imgLocation, $method, $width, $height)
    {
        $imgRawData = $this->imageOptimizeProvider->resizeImage($imgLocation, $method, $width, $height);

        $suffix = "_".$method."_";
        if ($width) {
            $suffix .= "w".$width;
        }
        if ($height) {
            $suffix .= "h".$height;
        }

        return $this->saveProcessedImageFromRawData($imgLocation, $imgRawData, $suffix);
    }
}
