<?php

namespace ScommerceBusinessBundle\Extensions;

use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\EntityManager;
use ScommerceBusinessBundle\Entity\SPageEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GetPageUrlExtension extends \Twig_Extension
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

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_current_uri', array($this, 'getCurrentUri')),

            new \Twig_SimpleFunction('get_page_url', array($this, 'getPageUrl')),
            new \Twig_SimpleFunction('get_page_title', array($this, 'getPageTitle')),
            new \Twig_SimpleFunction('get_entity_store_attribute', array($this, 'getEntityStoreAttribute')),
            new \Twig_SimpleFunction('get_array_store_attribute', array($this, 'getArrayStoreAttribute')),
        ];
    }

    public function getCurrentUri()
    {
        $requestStack = $this->container->get('request_stack');
        $request = $requestStack->getCurrentRequest();

        $routes = $this->container->get('router')->getRouteCollection()->all();
        $routePaths = [];
        foreach ($routes as $route) {
            $routePaths[$route->getPath()] = $route->getPath();
        }
        if (isset($routePaths[$request->getRequestUri()])) {
            return str_ireplace($requestStack->getMasterRequest()->getSchemeAndHttpHost(), "", $request->headers->get('referer'));
        }
        return str_ireplace($requestStack->getMasterRequest()->getSchemeAndHttpHost(), "", $requestStack->getMasterRequest()->getUri());
    }

    public function getPageUrl($storeId, $entityId, $entityTypeCode)
    {
        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $cacheItem = $this->cacheManager->getCacheGetItem("url_{$entityTypeCode}_{$storeId}_{$entityId}");

        if (empty($cacheItem) || isset($_GET["rebuild_cache"])) {
            /** @var EntityManager $entityManager */
            $entityManager = $this->container->get('entity_manager');
            $entityType = $entityManager->getEntityTypeByCode(trim($entityTypeCode));

            /** @var SPageEntity $entity */
            $entity = $entityManager->getEntityByEntityTypeAndId($entityType, $entityId);

            return $this->getEntityStoreAttribute($storeId, $entity, "url");
        }
        return $cacheItem->get();
    }

    public function getPageTitle($storeId, $entityId, $entityTypeCode)
    {
        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        $cacheItem = $this->cacheManager->getCacheGetItem("name_{$entityTypeCode}_{$storeId}_{$entityId}");

        if (empty($cacheItem) || isset($_GET["rebuild_cache"])) {
            /** @var EntityManager $entityManager */
            $entityManager = $this->container->get('entity_manager');
            $entityType = $entityManager->getEntityTypeByCode(trim($entityTypeCode));

            /** @var SPageEntity $entity */
            $entity = $entityManager->getEntityByEntityTypeAndId($entityType, $entityId);

            return $this->getEntityStoreAttribute($storeId, $entity, "name");
        }
        return $cacheItem->get();
    }

    /**
     * @param null $storeId
     * @param $entity
     * @param $attribute_code
     * @return |null
     */
    public function getEntityStoreAttribute($storeId = null, $entity, $attribute_code)
    {

        if (empty($entity)) {
            return null;
        }

        if (!is_object($entity)) {
            return $this->getArrayStoreAttribute($storeId, $entity);
        }

        if (empty($this->cacheManager)) {
            $this->cacheManager = $this->container->get("cache_manager");
        }

        if (empty($storeId)) {
            $session = $this->container->get('session');
            $storeId = $session->get("current_store_id");
        }

        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        if (empty($storeId)) {
            throw new \Exception("Missing store ID in getEntityStoreAttribute!");
        }

        $entityTypeCode = $entity->getEntityType()->getEntityTypeCode();
        $entityId = $entity->getId();

        $cacheItem = $this->cacheManager->getCacheGetItem("{$attribute_code}_" . $entityTypeCode . "_{$storeId}_" . $entityId);

        if (empty($cacheItem) || isset($_GET["rebuild_cache"])) {
            /** @var EntityManager $entityManager */
            $entityManager = $this->container->get('entity_manager');
            $entityType = $entityManager->getEntityTypeByCode(trim($entityTypeCode));

            /** @var SPageEntity $entity */
            $entity = $entityManager->getEntityByEntityTypeAndId($entityType, $entityId);

            $getter = EntityHelper::makeGetter($attribute_code);

            if (!EntityHelper::checkIfMethodExists($entity, $getter)) {
                return null;
            }

            $data = $entity->{$getter}();

            if (!is_array($data)) {
                $this->cacheManager->setCacheItem("{$attribute_code}_" . $entityTypeCode . "_{$storeId}_" . $entityId, $data, [$entityTypeCode . "_" . $entityId]);
                return $data;
            }

            if (!isset($data[$storeId])) {
                return null;
            }

            $value = $data[$storeId];

            $this->cacheManager->setCacheItem("{$attribute_code}_" . $entityTypeCode . "_{$storeId}_" . $entityId, $value, [$entityTypeCode . "_" . $entityId]);
            return $value;
        }
        return $cacheItem->get();
    }

    /**
     * @param null $storeId
     * @param $array
     * @return |null
     */
    public function getArrayStoreAttribute($storeId = null, $array)
    {

        if (empty($array)) {
            return null;
        }

        if (empty($storeId)) {
            $session = $this->container->get('session');
            $storeId = $session->get("current_store_id");
        }

        if (empty($storeId)) {
            $storeId = $_ENV["DEFAULT_STORE_ID"];
        }

        if (empty($storeId)) {
            throw new \Exception("Missing store ID in getEntityStoreAttribute!");
        }

        if (isset($array[$storeId])) {
            return $array[$storeId];
        }

        return null;
    }

}
