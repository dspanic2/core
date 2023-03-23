<?php

namespace ScommerceBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\CacheManager;
use AppBundle\Managers\EntityManager;
use ScommerceBusinessBundle\Entity\StaticContentEntity;
use ScommerceBusinessBundle\Extensions\GetPageUrlExtension;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StaticContentListener implements ContainerAwareInterface
{
    protected $container;

    /** @var GetPageUrlExtension $getPageUrlExtension */
    protected $getPageUrlExtension;

    /** @var EntityManager */
    protected $entityManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @throws \Exception
     */
    public function onStaticContentEntityPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var StaticContentEntity $entity */
        $entity = $event->getEntity();
        if ($entity->getEntityType()->getEntityTypeCode() == "static_content") {

            if (empty($this->getPageUrlExtension)) {
                $this->getPageUrlExtension = $this->container->get("get_page_url_extension");
            }

            $code = StringHelper::convertStringToCode($entity->getName());

            $entity->setCode($code);

            /** @var CacheManager $cacheManager */
            $cacheManager = $this->container->get("cache_manager");
            $cacheManager->setCacheItem("static_content.{$code}", $entity->getValue());
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onStaticContentEntityPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var StaticContentEntity $entity */
        $entity = $event->getEntity();
        if ($entity->getEntityType()->getEntityTypeCode() == "static_content") {

            $code = $entity->getCode();

            /** @var CacheManager $cacheManager */
            $cacheManager = $this->container->get("cache_manager");
            $cacheManager->setCacheItem("static_content.{$code}", $entity->getValue());
        }
    }
}
