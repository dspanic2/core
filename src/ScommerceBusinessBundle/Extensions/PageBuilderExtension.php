<?php

namespace ScommerceBusinessBundle\Extensions;

use AppBundle\Helpers\EntityHelper;
use ScommerceBusinessBundle\Entity\SFrontBlockEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PageBuilderExtension extends \Twig_Extension
{
    private $globalData = [];

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

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('page_builder_is_active', array($this, 'pageBuilderIsActive')),
            new \Twig_SimpleFunction('page_builder_get_blocks', array($this, 'pageBuilderGetBlocks')),
            new \Twig_SimpleFunction('page_builder_get_block_settings', array($this, 'pageBuilderGetBlockSettings')),
        ];
    }

    /**
     * @param $entity
     * @return false
     */
    public function pageBuilderIsActive($entity)
    {
        if (empty($entity)) {
            return false;
        }
        $cookieKey = 'page_builder_active-' . $entity->getEntityType()->getEntityTypeCode() . "-" . $entity->getId();
        return isset($_COOKIE[$cookieKey]) && $_COOKIE[$cookieKey] == 1;
    }

    /**
     * @return array
     */
    public function pageBuilderGetBlocks()
    {
        $blocks = [];

        $services = $this->container->getServiceIds();

        foreach ($services as $serviceCode) {
            if (strpos($serviceCode, '_front_block') !== false) {
                $service = $this->container->get($serviceCode);
                if (EntityHelper::checkIfMethodExists($service, "getPageBuilderTemplate")) {
                    try {
                        $type = str_ireplace("_front_block", "", $serviceCode);
                        $template = $service->getPageBuilderTemplate($type);
                        if (!empty($template)) {
                            $blocks[] = $type;
                        }
                    } catch (\Exception $e) {
                        // Template not found: Block does not support page builder
                        continue;
                    }
                }
            }
        }

        return $blocks;
    }

    /**
     * @param SFrontBlockEntity|null $block
     * @return array|mixed
     */
    public function pageBuilderGetBlockSettings(SFrontBlockEntity $block = null)
    {
        $settings = [];

        if (!empty($block) && !empty($block->getPageBuilderSettings())) {
            $settings = json_decode($block->getPageBuilderSettings());
        }

        return $settings;
    }
}
