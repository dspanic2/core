<?php

namespace ImageOptimizationBusinessBundle\Extensions;

use ImageOptimizationBusinessBundle\Managers\ImageStyleManager;

class ImageStyleExtension extends \Twig_Extension
{
    /** @var ImageStyleManager $imageStyleManager */
    protected $imageStyleManager;

    public function __construct(ImageStyleManager $imageStyleManager)
    {
        $this->imageStyleManager = $imageStyleManager;
    }

    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('image_style', array($this, 'getImageStyle')),
            new \Twig_SimpleFilter('image_width', array($this, 'getImageWidth')),
            new \Twig_SimpleFilter('image_height', array($this, 'getImageHeight')),
            new \Twig_SimpleFilter('image_timestamp', array($this, 'getImageTimestamp')),
        ];
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('responsive_image_style', array($this, 'getResponsiveImageStyle')),
        ];
    }

    public function getImageStyle($imageRelativeUrl, $imageStyleName, $addWatermark = false)
    {
        return $this->imageStyleManager->getImageStyleImageUrl($imageRelativeUrl, $imageStyleName, false, $addWatermark);
    }

    public function getResponsiveImageStyle($imageRelativeUrl, $imageStyleName, $alt, $title, $addWatermark = false)
    {
        return $this->imageStyleManager->generateResponsiveImageStyle($imageRelativeUrl, $imageStyleName, $alt, $title, false, $addWatermark);
    }

    public function getImageWidth($imageUrl)
    {
        return $this->imageStyleManager->getImageWidth($imageUrl);
    }

    public function getImageHeight($imageUrl)
    {
        return $this->imageStyleManager->getImageHeight($imageUrl);
    }

    public function getImageTimestamp($imageUrl)
    {
        return $this->imageStyleManager->getImageTimestamp($imageUrl);
    }
}
