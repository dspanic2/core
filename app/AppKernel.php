<?php

use AppBundle\AppBundle;
use CrmBusinessBundle\CrmBusinessBundle;
use HrBusinessBundle\HrBusinessBundle;
use NotificationsAndAlertsBusinessBundle\NotificationsAndAlertsBusinessBundle;
use SanitarijeBusinessBundle\SanitarijeBusinessBundle;
use SanitarijeAdminBusinessBundle\SanitarijeAdminBusinessBundle;
use SharedInboxBusinessBundle\SharedInboxBusinessBundle;
use TaskBusinessBundle\TaskBusinessBundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use ProjectManagementBusinessBundle\ProjectManagementBusinessBundle;
use ScommerceBusinessBundle\ScommerceBusinessBundle;
use ImageOptimizationBusinessBundle\ImageOptimizationBusinessBundle;
use WikiBusinessBundle\WikiBusinessBundle;
use PaymentProvidersBusinessBundle\PaymentProvidersBusinessBundle;
use IntegrationBusinessBundle\IntegrationBusinessBundle;
use ToursBusinessBundle\ToursBusinessBundle;
use GLSBusinessBundle\GLSBusinessBundle;
use DPDBusinessBundle\DPDBusinessBundle;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new AppBundle(),
            new Symfony\Bundle\AsseticBundle\AsseticBundle(),
            new FOS\UserBundle\FOSUserBundle(),
            new Knp\Bundle\SnappyBundle\KnpSnappyBundle(),
            new Liuggio\ExcelBundle\LiuggioExcelBundle(),
            new JMS\SerializerBundle\JMSSerializerBundle(),
            new Nelmio\ApiDocBundle\NelmioApiDocBundle(),
            new CrmBusinessBundle(),
            new HrBusinessBundle(),
            new TaskBusinessBundle(),
            new SharedInboxBusinessBundle(),
            new NotificationsAndAlertsBusinessBundle(),
            new ProjectManagementBusinessBundle(),
            new Scheb\TwoFactorBundle\SchebTwoFactorBundle(),
            new ScommerceBusinessBundle(),
            new Skies\QRcodeBundle\SkiesQRcodeBundle(),
            new ImageOptimizationBusinessBundle(),
            new WikiBusinessBundle(),
            new PaymentProvidersBusinessBundle(),
            new GLSBusinessBundle(),
            new DPDBusinessBundle(),
            new IntegrationBusinessBundle(),
            new ToursBusinessBundle(),
            new Snc\RedisBundle\SncRedisBundle(),
            new SanitarijeBusinessBundle(),
            new SanitarijeAdminBusinessBundle()
        ];

        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            $bundles[] = new Symfony\Bundle\DebugBundle\DebugBundle();
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
            $bundles[] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
        }

        return $bundles;
    }

    public function getRootDir()
    {
        return __DIR__;
    }

    public function getCacheDir()
    {
        return dirname(__DIR__).'/var/cache/'.$this->getEnvironment();
    }

    public function getLogDir()
    {
        return dirname(__DIR__).'/var/logs';
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->getRootDir().'/config/config_'.$this->getEnvironment().'.yml');
    }
}
