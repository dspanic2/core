<?php

namespace ScommerceBusinessBundle\Extensions;

use ScommerceBusinessBundle\Managers\StaticContentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StaticContentExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /** @var StaticContentManager */
    protected $staticContentManager;
    /** @var GetPageUrlExtension $pageUrlExtension */
    protected $pageUrlExtension;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_static_content', array($this, 'getStaticContentByKey')),
        ];
    }

    /**
     * @param $key
     * @return string
     * @throws \Exception
     */
    public function getStaticContentByKey($key)
    {
        if (empty($this->staticContentManager)) {
            $this->staticContentManager = $this->container->get("static_content_manager");
        }

        /** @var Session $session */
        $session = $this->container->get("session");
        $storeId = $session->get("current_store_id");
        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        if (empty($this->pageUrlExtension)) {
            $this->pageUrlExtension = $this->container->get("get_page_url_extension");
        }

        $value = $this->pageUrlExtension->getEntityStoreAttribute($storeId, $this->staticContentManager->getRawStaticContentEntityByCode($key), "value");

        return html_entity_decode($value);
    }
}
