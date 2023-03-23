<?php

namespace ScommerceBusinessBundle\Extensions;

use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StoresExtension extends \Twig_Extension
{
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
            new \Twig_SimpleFunction('get_store_by_id', array($this, 'getStoreById')),
        ];
    }

    public function getStoreById($storeId)
    {
        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }
        return $this->routeManager->getStoreById($storeId);
    }

}
