<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class CacheManager extends AbstractBaseManager
{
    protected $cache;

    public function initialize()
    {
        parent::initialize();
//        $cacheDir = $this->container->getParameter('web_path')."/Documents/cache";
        $cacheDir = $this->container->getParameter('web_path') . "../var/cache";

        try {
            $this->cache = new TagAwareAdapter(
                new PhpFilesAdapter('sp', 0, $cacheDir)
            );
        } catch (CacheException $e) {
            $this->logger->addError($e->getMessage());
        }
    }

    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @param $key
     * @param $value
     * @param array $tags
     * @param null $expiresAfter
     * @return |null
     */
    public function setCacheItem($key, $value, $tags = [], $expiresAfter = null)
    {
        $cache = $this->getCache();

        if (empty($cache)) {
            return null;
        }

        /** @var CacheItem $cacheItem */
        $cacheItem = $cache->getItem($key);
        $cacheItem->set($value);

        $tags = array_filter($tags);
        if (!empty($tags)) {
            $cacheItem->tag($tags);
        }

        if(!empty($expiresAfter)){
            $cacheItem->expiresAfter(\DateInterval::createFromDateString($expiresAfter));
        }

        $cache->save($cacheItem);

        return $cacheItem->get();
    }

    /**
     * @param $key
     * @return bool
     */
    public function getCacheItem($key)
    {

        $cache = $this->getCache();

        if (empty($cache)) {
            return null;
        }

        $cacheItem = $cache->getItem($key);

        if (!$cacheItem->isHit()) {
            return false;
        }

        return $cacheItem->get();
    }

    /**
     * @param $key
     * @return bool
     */
    public function getCacheGetItem($key)
    {

        $cache = $this->getCache();

        if (empty($cache)) {
            return null;
        }

        $cacheItem = $cache->getItem($key);

        if (!$cacheItem->isHit()) {
            return false;
        }

        return $cacheItem;
    }

    /**
     * @param $tag
     */
    public function invalidateCacheByTag($tag)
    {
        $cache = $this->getCache();

        if (!empty($cache)) {
            $cache->invalidateTags([$tag]);
        }
    }

    /**
     * @param $tags
     */
    public function invalidateCacheByTags($tags)
    {
        $cache = $this->getCache();

        if (!empty($cache)) {
            $cache->invalidateTags($tags);
        }
    }
}
