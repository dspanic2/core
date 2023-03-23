<?php

use Doctrine\Common\Annotations\AnnotationRegistry;
use Composer\Autoload\ClassLoader;
use Symfony\Component\Dotenv\Dotenv;

/**
 * @var ClassLoader $loader
 */
$loader = require __DIR__.'/../vendor/autoload.php';

AnnotationRegistry::registerLoader([$loader, 'loadClass']);

$dotenv = new Dotenv();
//$dotenv->load(__DIR__.'/../.env', __DIR__.'/../.env.localdev');
$dotenv->load(__DIR__.'/../.env');
$dotenv->load(__DIR__.'/../configuration.env');
$dotenv->load(__DIR__.'/../wand.env');
$dotenv->load(__DIR__.'/../algolia.env');

return $loader;
