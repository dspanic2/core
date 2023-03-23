<?php

namespace ToursBusinessBundle\Extensions;

use Symfony\Component\DependencyInjection\ContainerInterface;
use ToursBusinessBundle\Managers\ToursManager;

class ToursExtension extends \Twig_Extension
{

    /**
     * @var ContainerInterface
     */
    protected $container;

    /** @var ToursManager */
    protected $toursManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('page_has_tour_tips', array($this, 'pageHasTourTips')),
        ];
    }

    /**
     * @return bool
     */
    function pageHasTourTips($request)
    {
        if (empty($this->toursManager)) {
            $this->toursManager = $this->container->get("tours_manager");
        }
        return !empty($this->toursManager->getTipsByTourAndUrl(null, $request->getRequestUri()));
    }

}
