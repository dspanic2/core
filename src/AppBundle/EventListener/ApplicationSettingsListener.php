<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\SettingsEntity;
use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\CacheManager;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ApplicationSettingsListener implements ContainerAwareInterface
{
    protected $container;

    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @throws \Exception
     */
    public function onEntityPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var SettingsEntity $entity */
        $entity = $event->getEntity();
        if ($entity->getEntityType()->getEntityTypeCode() == "settings") {

            if (empty($this->getPageUrlExtension)) {
                $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
            }

            $session = $this->container->get("session");
            $storeId = $session->get("current_store_id") ?? $_ENV["DEFAULT_STORE_ID"];

            $code = StringHelper::convertStringToCode($entity->getName()[$storeId]);

            $entity->setCode($code);

            /** @var CacheManager $cacheManager */
            $cacheManager = $this->container->get("cache_manager");
            $cacheManager->setCacheItem("app_settings.{$code}", $entity->getSettingsValue());
        }
    }

    /**
     * @param EntityUpdatedEvent $event
     * @throws \Exception
     */
    public function onEntityUpdated(EntityUpdatedEvent $event)
    {
        /** @var SettingsEntity $entity */
        $entity = $event->getEntity();
        if ($entity->getEntityType()->getEntityTypeCode() == "settings") {

            $code = $entity->getCode();

            /** @var CacheManager $cacheManager */
            $cacheManager = $this->container->get("cache_manager");
            $cacheManager->setCacheItem("app_settings.{$code}", $entity->getSettingsValue());
        }
    }
}
