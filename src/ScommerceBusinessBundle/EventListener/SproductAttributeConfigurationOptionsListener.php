<?php

namespace ScommerceBusinessBundle\EventListener;

use AppBundle\Context\DatabaseContext;
use AppBundle\Entity\EntityValidation;
use AppBundle\Events\EntityCreatedEvent;
use AppBundle\Events\EntityDeletedEvent;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreDeletedEvent;
use AppBundle\Events\EntityPreSetUpdatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Events\EntityUpdatedEvent;
use AppBundle\Helpers\EntityHelper;
use CrmBusinessBundle\Managers\ProductManager;
use RulesBusinessBundle\Providers\Events\EntityDeleted;
use ScommerceBusinessBundle\Entity\SProductAttributeConfigurationEntity;
use ScommerceBusinessBundle\Entity\SProductAttributeConfigurationOptionsEntity;
use ScommerceBusinessBundle\Managers\BrandsManager;
use ScommerceBusinessBundle\Managers\SproductManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SproductAttributeConfigurationOptionsListener implements ContainerAwareInterface
{
    /** @var SproductManager $sProductManager */
    protected $sProductManager;
    /** @var BrandsManager $brandsManager */
    protected $brandsManager;
    /** @var DatabaseContext $databaseContext */
    protected $databaseContext;
    /** @var ProductManager $productManager */
    protected $productManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @return bool
     */
    public function onSproductAttributeConfigurationOptionsPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var SProductAttributeConfigurationOptionsEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_product_attribute_configuration_options") {

            $entityValidation = new EntityValidation();

            if (empty($entity->getConfigurationAttribute())) {
                $entityValidation->setTitle("Error");
                $entityValidation->setMessage("Missing configuration");
                $entity->addEntityValidation($entityValidation);

                return $entity;
            } elseif (empty($entity->getConfigurationValue()) && $entity->getConfigurationValue() !== 0 && $entity->getConfigurationValue() !== false) {

                $entityValidation->setTitle("Error");
                $entityValidation->setMessage("Missing attribute value");
                $entity->addEntityValidation($entityValidation);

                return $entity;
            }
        }
    }

    /**
     * @param EntityCreatedEvent $event
     * @return bool
     */
    public function onSproductAttributeConfigurationOptionsCreated(EntityCreatedEvent $event)
    {
        /** @var SProductAttributeConfigurationOptionsEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_product_attribute_configuration_options") {

            /** @var SProductAttributeConfigurationEntity $attribute */
            $attribute = $entity->getConfigurationAttribute();

            if ($attribute->getFilterKey() == "brand") {

                if (empty($this->brandsManager)) {
                    $this->brandsManager = $this->container->get("brands_manager");
                }

                $this->brandsManager->syncBrandsWithSProductAttributeConfigurationOptions();
            }


            return true;
        }
    }

    /**
     * @param EntityPreSetUpdatedEvent $event
     */
    public function onSproductAttributeConfigurationOptionsPreSetUpdated(EntityPreSetUpdatedEvent $event)
    {
        /** @var SProductAttributeConfigurationOptionsEntity $entity */
        $entity = $event->getEntity();
        $data = $event->getData();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_product_attribute_configuration_options") {

            unset($data["configuration_attribute_id"]);

            if ($entity->getConfigurationValue() != $data["configuration_value"]) {
                $data["is_changed"] = true;
            }

            $event->setData($data);
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @return bool
     */
    public function onSproductAttributeConfigurationOptionsPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var SProductAttributeConfigurationOptionsEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_product_attribute_configuration_options") {

            $entityValidation = new EntityValidation();

            if (empty($entity->getConfigurationAttribute())) {
                $entityValidation->setTitle("Error");
                $entityValidation->setMessage("Missing configuration");
                $entity->addEntityValidation($entityValidation);

                return $entity;
            } elseif (empty($entity->getConfigurationValue()) && $entity->getConfigurationValue() !== 0 && $entity->getConfigurationValue() !== false) {

                $entityValidation->setTitle("Error");
                $entityValidation->setMessage("Missing attribute value");
                $entity->addEntityValidation($entityValidation);

                return $entity;
            }
        }
    }

    /**
     * @param EntityUpdatedEvent $event
     * @return bool
     */
    public function onSproductAttributeConfigurationOptionsUpdated(EntityUpdatedEvent $event)
    {
        /** @var SProductAttributeConfigurationOptionsEntity $entity */
        $entity = $event->getEntity();
        $data = $event->getData();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_product_attribute_configuration_options") {

            if (isset($data["is_changed"]) && $data["is_changed"] == true) {
                if (empty($this->sProductManager)) {
                    $this->sProductManager = $this->container->get("s_product_manager");
                }

                $this->sProductManager->updateAllsProductAttributeLinkValues();

                /** @var SProductAttributeConfigurationEntity $attribute */
                $attribute = $entity->getConfigurationAttribute();

                if ($attribute->getFilterKey() == "brand") {

                    if (empty($this->brandsManager)) {
                        $this->brandsManager = $this->container->get("brands_manager");
                    }

                    $this->brandsManager->syncBrandsWithSProductAttributeConfigurationOptions();
                }

                /**
                 * Configurable product options
                 */
                $configurableAttributes = $entity->getConfigurationAttribute()->getConfigurableAttributes();
                if (EntityHelper::isCountable($configurableAttributes) && count($configurableAttributes)) {
                    if (empty($this->productManager)) {
                        $this->productManager = $this->container->get("product_manager");
                    }

                    $this->productManager->rebuildConfigurableProductConfigurationsForAttributeOption($entity);
                }
            }

            return true;
        }
    }

    /**
     * @param EntityPreDeletedEvent $event
     * @return bool
     */
    public function onSproductAttributeConfigurationOptionsPreDeleted(EntityPreDeletedEvent $event)
    {
        /** @var SProductAttributeConfigurationOptionsEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_product_attribute_configuration_options") {

            $disableDelete = false;

            $configurableAttributes = $entity->getConfigurationAttribute()->getConfigurableAttributes();
            if (EntityHelper::isCountable($configurableAttributes) && count($configurableAttributes)) {
                $disableDelete = true;
            }

            if ($_ENV["DISABLE_DELETE_ATTRIBUTE_OPTIONS_IN_USE"] || $disableDelete) {

                if (empty($this->databaseContext)) {
                    $this->databaseContext = $this->container->get("database_context");
                }

                $q = "SELECT * FROM s_product_attributes_link_entity as spale LEFT JOIN product_entity as p ON spale.product_id = p.id WHERE configuration_option = {$entity->getId()} AND p.entity_state_id = 1;";
                $exists = $this->databaseContext->getAll($q);

                if (!empty($exists)) {
                    throw new \Exception("Cannot delete attribute value in use");
                }
            }

            return true;
        }
    }

    /**
     * @param EntityDeletedEvent $event
     * @return bool
     */
    public function onSproductAttributeConfigurationOptionsDeleted(EntityDeletedEvent $event)
    {
        /** @var SProductAttributeConfigurationOptionsEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_product_attribute_configuration_options") {

            $filterKey = $entity->getConfigurationAttribute()->getFilterKey();

            if ($_ENV["DISABLE_DELETE_ATTRIBUTE_OPTIONS_IN_USE"] == 0) {

                if (empty($this->databaseContext)) {
                    $this->databaseContext = $this->container->get("database_context");
                }

                $q = "DELETE FROM s_product_attribute_configuration_options_entity WHERE entity_state_id = 2;";
                $this->databaseContext->executeNonQuery($q);

                $q = "DELETE FROM s_product_attributes_link_entity WHERE configuration_option is not null and configuration_option != '' and configuration_option not in (SELECT id FROM s_product_attribute_configuration_options_entity WHERE entity_state_id = 1);";
                $this->databaseContext->executeNonQuery($q);
            }

            if ($filterKey == "brand") {

                if (empty($this->brandsManager)) {
                    $this->brandsManager = $this->container->get("brands_manager");
                }

                $this->brandsManager->syncBrandsWithSProductAttributeConfigurationOptions();
            }

            return true;
        }
    }
}
