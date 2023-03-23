<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\SearchFilter;
use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreSetUpdatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\EntityManager;
use CrmBusinessBundle\Constants\CrmConstants;
use CrmBusinessBundle\Entity\ProductEntity;
use CrmBusinessBundle\Entity\ProductGroupEntity;
use CrmBusinessBundle\Managers\DefaultCrmProcessManager;
use CrmBusinessBundle\Managers\ProductManager;
use JMS\Serializer\EventDispatcher\EventDispatcher;
use ScommerceBusinessBundle\Constants\ScommerceConstants;
use ScommerceBusinessBundle\Entity\SRouteEntity;
use ScommerceBusinessBundle\Entity\SStoreEntity;
use ScommerceBusinessBundle\Managers\RouteManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductListener implements ContainerAwareInterface
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var RouteManager $routeManager */
    protected $routeManager;
    /** @var ProductManager $productManager */
    protected $productManager;
    /** @var DefaultCrmProcessManager $crmProcessManager */
    protected $crmProcessManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     */
    public function onProductPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var ProductEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product") {

            $entity->setKeepUrl(1);
            $entity->setAutoGenerateUrl(1);
            $entity->setContentChanged(1);

            if(empty($entity->getQtyStep())){
                $entity->setQtyStep(1);
            }

            if($entity->getCashPercentage() < 0 || $entity->getCashPercentage() > 100){
                $entity->setCashPercentage(0);
            }

            if($entity->getDiscountPercentage() < 0 || $entity->getDiscountPercentage() > 100){
                $entity->setDiscountPercentage(0);
            }
        }
    }

    /**
     * @param EntityCreatedEvent $event
     */
    /*public function onProductCreated(EntityCreatedEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product") {

            if(empty($this->crmProcessManager)){
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $this->crmProcessManager->recalculateProductPrices($entity);
        }
    }*/

    /**
     * @param EntityPreSetUpdatedEvent $event
     */
    public function onProductPreSetUpdated(EntityPreSetUpdatedEvent $event)
    {
        /** @var ProductEntity $entity */
        $entity = $event->getEntity();
        $data = $event->getData();

        if ($entity->getEntityType()->getEntityTypeCode() == "product") {

            if ((isset($data["name"]) && $data["name"] != $entity->getName()) ||
                (isset($data["description"]) && $data["description"] ?? "" == $entity->getDescription()) ||
                (isset($data["short_description"]) && $data["short_description"] ?? "" == $entity->getShortDescription()) ||
                (isset($data["meta_title"]) && $data["meta_title"] ?? "" == $entity->getMetaTitle()) ||
                (isset($data["meta_description"]) && $data["meta_description"] ?? "" == $entity->getMetaDescription())
            ) {
                $entity->setContentChanged(1);
            }

            /**
             * If product type is changed, delete all relations
             */
            if(isset($data["product_type_id"]) && !empty($entity->getProductType()) && $entity->getProductTypeId() != $data["product_type_id"] && $entity->getProductTypeId() != CrmConstants::PRODUCT_TYPE_SIMPLE){

                if(empty($this->productManager)){
                    $this->productManager = $this->container->get("product_manager");
                }

                $compositeFilter = new CompositeFilter();
                $compositeFilter->setConnector("and");
                $compositeFilter->addFilter(new SearchFilter("product", "eq", $entity->getId()));

                $relations = $this->productManager->getProductConfigurationProductLinks($compositeFilter);

                if(!empty($relations)){
                    foreach ($relations as $relation){
                        $this->productManager->deleteProductConfigurationProductLink($relation,false);
                    }
                }
            }
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     */
    public function onProductPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var ProductEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product") {
            if (!$entity->getActive() && $entity->getIsSaleable()) {
                $entity->setIsSaleable(0);
            }

            if($entity->getCashPercentage() < 0 || $entity->getCashPercentage() > 100){
                $entity->setCashPercentage(0);
            }

            if($entity->getDiscountPercentage() < 0 || $entity->getDiscountPercentage() > 100){
                $entity->setDiscountPercentage(0);
            }
        }
    }

    /**
     * @param EntityUpdatedEvent $event
     */
    /*public function onProductUpdated(EntityUpdatedEvent $event)
    {
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product") {

            if(empty($this->crmProcessManager)){
                $this->crmProcessManager = $this->container->get("crm_process_manager");
            }

            $this->crmProcessManager->recalculateProductPrices($entity);
        }
    }*/

    /**
     * @param EntityDeletedEvent $event
     * @return bool
     */
    public function onProductDeleted(EntityDeletedEvent $event)
    {
        /** @var ProductEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "product") {

            /**
             * Fallback ako nema routa
             */
            if (!EntityHelper::checkIfMethodExists($entity, "getUrl")) {
                return false;
            }

            if (empty($this->routeManager)) {
                $this->routeManager = $this->container->get("route_manager");
            }

            $url = $entity->getUrl();

            if (!empty($url)) {
                foreach ($url as $key => $value) {

                    /** @var SStoreEntity $store */
                    $store = $this->routeManager->getStoreById($key);

                    /** @var SRouteEntity $route */
                    $route = $this->routeManager->getRouteByDestination($entity->getId(), $entity->getEntityType()->getEntityTypeCode(), $store);
                    if (!empty($route)) {

                        if (empty($this->productManager)) {
                            $this->productManager = $this->container->get("product_manager");
                        }

                        /** @var ProductGroupEntity $productGroup */
                        $productGroup = $this->productManager->getLowestProductGroupOnProduct($entity);

                        if (empty($productGroup)) {
                            $notFoundRoute = $this->routeManager->getNotFoundRouteForStore($store);
                            if (!empty($notFoundRoute)) {
                                $redirectType = $this->routeManager->getRedirectTypeById(ScommerceConstants::S_REDIRECT_TYPE_404);
                                $this->routeManager->setRedirectRoute($route, $notFoundRoute, $redirectType);
                            } else {
                                $this->routeManager->deleteRoute($route);
                            }

                            return true;
                        }

                        /** @var SRouteEntity $redirectToRoute */
                        $redirectToRoute = $this->routeManager->getRouteByDestination($productGroup->getId(), $productGroup->getEntityType()->getEntityTypeCode(), $store);

                        $redirectType = $this->routeManager->getRedirectTypeById(ScommerceConstants::S_REDIRECT_TYPE_404);
                        $this->routeManager->setRedirectRoute($route, $redirectToRoute, $redirectType);
                    }
                }
            }

            return true;
        }
    }
}
