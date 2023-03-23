<?php

namespace ScommerceBusinessBundle\EventListener;

use AppBundle\Entity\EntityValidation;
use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreSetCreatedEvent;
use AppBundle\Events\EntityPreSetUpdatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Managers\EntityManager;
use ScommerceBusinessBundle\Constants\ScommerceConstants;
use ScommerceBusinessBundle\Entity\BrandEntity;
use ScommerceBusinessBundle\Entity\SRouteEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Entity\STemplateTypeEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use ScommerceBusinessBundle\Managers\TemplateManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BrandListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var TemplateManager $templateManager */
    protected $templateManager;
    protected $translator;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreSetCreatedEvent $event
     * @return mixed
     */
    public function onBrandPreSetCreated(EntityPreSetCreatedEvent $event)
    {
        $entity = $event->getEntity();
        $data = $event->getData();

        if ($entity->getEntityType()->getEntityTypeCode() == "brand") {

            if (empty($this->translator)) {
                $this->translator = $this->container->get("translator");
            }

            /**
             * Fallback na starije verzije gdje nema multilang
             */
            $hasMultilang = false;

            if (isset($data["show_on_store"])) {
                $hasMultilang = true;
            }

            $entityValidation = new EntityValidation();

            if (!isset($data["name"]) || empty($data["name"])) {
                $entityValidation->setTitle("Error");
                $entityValidation->setMessage($this->translator->trans('Name cannot be empty'));
                $entity->addEntityValidation($entityValidation);

                return false;
            }

            if ($hasMultilang) {
                $data["name"] = array_map('trim', $data["name"]);
                $data["name"] = array_filter($data["name"]);
                if (empty($data["name"])) {
                    $entityValidation->setTitle("Error");
                    $entityValidation->setMessage($this->translator->trans('Name cannot be empty'));
                    $entity->addEntityValidation($entityValidation);

                    return false;
                }
                if (empty($data["show_on_store_checkbox"])) {
                    $entityValidation->setTitle("Error");
                    $entityValidation->setMessage($this->translator->trans('Please add at least one store'));
                    $entity->addEntityValidation($entityValidation);

                    return false;
                }
            }

            return true;
        }
    }

    /**
     * @param EntityPreCreatedEvent $event
     */
    public function onBrandPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var BrandEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "brand") {

            if (empty($entity->getTemplateType())) {
                if (empty($this->templateManager)) {
                    $this->templateManager = $this->container->get("template_manager");
                }

                /** @var STemplateTypeEntity $templateType */
                $templateType = $this->templateManager->getTemplateTypeByCode("brand");

                $entity->setTemplateType($templateType);
            }
        }
    }

    /**
     * @param EntityCreatedEvent $event
     */
    public function onBrandCreated(EntityCreatedEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "brand") {
            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->refreshEntity($entity);

            if(empty($this->routeManager)){
                $this->routeManager = $this->container->get("route_manager");
            }

            $this->routeManager->insertUpdateDefaultLanguages($entity,$entity->getId());
        }
    }

    /**
     * @param EntityPreSetUpdatedEvent $event
     * @return mixed
     */
    public function onBrandPreSetUpdated(EntityPreSetUpdatedEvent $event)
    {
        $entity = $event->getEntity();
        $data = $event->getData();

        if ($entity->getEntityType()->getEntityTypeCode() == "brand") {

            if (empty($this->translator)) {
                $this->translator = $this->container->get("translator");
            }

            /**
             * Fallback na starije verzije gdje nema multilang
             */
            $hasMultilang = false;

            if (isset($data["show_on_store"])) {
                $hasMultilang = true;
            }

            $entityValidation = new EntityValidation();

            if (!isset($data["name"]) || empty($data["name"])) {
                $entityValidation->setTitle("Error");
                $entityValidation->setMessage($this->translator->trans('Name cannot be empty'));
                $entity->addEntityValidation($entityValidation);

                return false;
            }

            if ($hasMultilang) {
                $data["name"] = array_map('trim', $data["name"]);
                $data["name"] = array_filter($data["name"]);
                if (empty($data["name"])) {
                    $entityValidation->setTitle("Error");
                    $entityValidation->setMessage($this->translator->trans('Name cannot be empty'));
                    $entity->addEntityValidation($entityValidation);

                    return false;
                }
                if (empty($data["show_on_store_checkbox"])) {
                    $entityValidation->setTitle("Error");
                    $entityValidation->setMessage($this->translator->trans('Please add at least one store'));
                    $entity->addEntityValidation($entityValidation);

                    return false;
                }
            }

            return true;
        }
    }

    /**
     * @param EntityCreatedEvent $event
     */
    public function onBrandUpdated(EntityUpdatedEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "brand") {
            if (empty($this->entityManager)) {
                $this->entityManager = $this->container->get("entity_manager");
            }

            $this->entityManager->refreshEntity($entity);

            if(empty($this->routeManager)){
                $this->routeManager = $this->container->get("route_manager");
            }

            $this->routeManager->insertUpdateDefaultLanguages($entity,$entity->getId());
        }
    }

    /**
     * @param EntityDeletedEvent $event
     */
    public function onBrandDeleted(EntityDeletedEvent $event)
    {
        /** @var BrandEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "brand") {
            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get("route_manager");
            }

            $url = $entity->getUrl();

            foreach ($url as $key => $value) {

                /** @var SStoreEntity $store */
                $store = $this->routeManager->getStoreById($key);

                /** @var SRouteEntity $route */
                $route = $this->routeManager->getRouteByDestination($entity->getId(), $entity->getEntityType()->getEntityTypeCode(), $store);
                if (!empty($route)) {
                    $notFoundRoute = $this->routeManager->getNotFoundRouteForStore($store);

                    if (!empty($notFoundRoute)) {
                        $redirectType = $this->routeManager->getRedirectTypeById(ScommerceConstants::S_REDIRECT_TYPE_404);
                        $this->routeManager->setRedirectRoute($route, $notFoundRoute, $redirectType);
                    } else {
                        $this->routeManager->deleteRoute($route);
                    }
                }
            }
        }
    }
}
