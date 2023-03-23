<?php

namespace AppBundle\Twig\Extension;

use Symfony\Bundle\TwigBundle\Loader\FilesystemLoader;

class AppExtension extends \Twig_Extension
{

    /**
     * AppExtension constructor.
     * @param FilesystemLoader $loader
     *
     * Davor
     * Overrides base template folder
     */
    public function __construct(FilesystemLoader $loader)
    {
        $this->loader = $loader;
        $this->loader->addPath(__DIR__.'/../../Resources/views');
    }

    public function getName()
    {
        return 'app_extension';
    }
}
