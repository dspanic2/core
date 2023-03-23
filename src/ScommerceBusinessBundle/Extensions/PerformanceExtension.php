<?php

namespace ScommerceBusinessBundle\Extensions;

use AppBundle\Managers\CacheManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\ProductConfigurationProductLinkEntity;
use CrmBusinessBundle\Entity\ProductEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PerformanceExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /** @var CacheManager $cacheManager */
    protected $cacheManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFilters()
    {
        return [
        ];
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_product_list_cache_item', array($this, 'getProductListCacheItem')),
            new \Twig_SimpleFunction('set_product_list_cache_item', array($this, 'setProductListCacheItem')),

            // General cache setter and getter
            new \Twig_SimpleFunction('get_cache_item', array($this, 'getCacheItem')),
            new \Twig_SimpleFunction('set_cache_item', array($this, 'setCacheItem')),
        ];
    }

    /**
     * @param $productId
     * @param $storeId
     */
    public function getProductListCacheItem($productId, $storeId)
    {
        if (isset($_GET["rebuild_cache"]) || (isset($_ENV["DISABLE_FRONT_BLOCK_HTML_CACHE"]) && $_ENV["DISABLE_FRONT_BLOCK_HTML_CACHE"] == 1)) {
            return "";
        }
        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }
        $cacheItem = $this->cacheManager->getCacheGetItem("product_list_item_{$productId}_{$storeId}");
        if (empty($cacheItem)) {
            return "";
        }
        return $cacheItem->get();
    }

    /**
     * @param ProductEntity $product
     * @param $storeId
     * @param $html
     */
    public function setProductListCacheItem(ProductEntity $product, $storeId, $html, $additionalTags = [])
    {
        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }
        $tags = [
            "product",
            "product_{$product->getId()}",
        ];

        if ($product->getProductTypeId() == CrmConstants::PRODUCT_TYPE_CONFIGURABLE) {
            /** @var ProductConfigurationProductLinkEntity $productConfiguration */
            foreach ($product->getProductConfigurations() as $productConfiguration) {
                $tags[] = "product_{$productConfiguration->getChildProductId()}";
            }
        }

        if (!empty($additionalTags)) {
            $tags = array_merge($tags, $additionalTags);
        }

        $this->cacheManager->setCacheItem("product_list_item_{$product->getId()}_{$storeId}", $html, $tags, "30 days");
    }

    /**
     * @param $cacheKey
     * @return string
     */
    public function getCacheItem($cacheKey)
    {
        if (isset($_GET["rebuild_cache"]) || (isset($_ENV["DISABLE_FRONT_BLOCK_HTML_CACHE"]) && $_ENV["DISABLE_FRONT_BLOCK_HTML_CACHE"] == 1)) {
            return "";
        }
        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }
        $cacheItem = $this->cacheManager->getCacheGetItem($cacheKey);
        if (empty($cacheItem)) {
            return "";
        }
        return $cacheItem->get();
    }

    /**
     * @param $cacheKey
     * @param $html
     * @param array $cacheTags
     * @return void
     */
    public function setCacheItem($cacheKey, $html, $cacheTags)
    {
        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }
        $this->cacheManager->setCacheItem($cacheKey, $html, $cacheTags, "30 days");
    }

}
