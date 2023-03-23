<?php

namespace AppBundle\Extensions;

use Symfony\Component\DependencyInjection\ContainerInterface;

class SystemExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('file_upload_max_size', array($this, 'fileUploadMaxSize')),
        ];
    }

    /**
     * @return int
     */
    public function fileUploadMaxSize()
    {
        return (int)(ini_get('upload_max_filesize'));
    }
}
