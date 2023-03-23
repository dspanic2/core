<?php


namespace ImageOptimizationBusinessBundle\Interfaces;

interface IOptimizeImage
{
    function optimizeImage($imgLocation);
    function resizeImage($imgLocation, $method, $width, $height);
}
