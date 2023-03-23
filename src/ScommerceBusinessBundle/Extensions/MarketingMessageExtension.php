<?php

namespace ScommerceBusinessBundle\Extensions;

use ScommerceBusinessBundle\Managers\MarketingMessageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MarketingMessageExtension extends \Twig_Extension
{

    /**
     * @var ContainerInterface
     */
    protected $container;

    /** @var MarketingMessageManager $marketingMessageManager */
    protected $marketingMessageManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_active_messages', array($this, 'getActiveMessages')),
        ];
    }

    /**
     * @return false
     */
    function getActiveMessages()
    {
        if (empty($this->marketingMessageManager)) {
            $this->marketingMessageManager = $this->container->get("marketing_message_manager");
        }
        return $this->marketingMessageManager->getActiveMessages();
    }
}
