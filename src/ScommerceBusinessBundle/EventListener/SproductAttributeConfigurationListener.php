<?php

namespace ScommerceBusinessBundle\EventListener;

use AppBundle\Entity\EntityValidation;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreDeletedEvent;
use AppBundle\Events\EntityPreSetCreatedEvent;
use AppBundle\Events\EntityPreSetUpdatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Helpers\EntityHelper;
use ScommerceBusinessBundle\Entity\SProductAttributeConfigurationEntity;
use ScommerceBusinessBundle\Managers\SproductManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SproductAttributeConfigurationListener implements ContainerAwareInterface
{
    /** @var SproductManager $sProductManager */
    protected $sProductManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreSetCreatedEvent $event
     */
    public function onSproductAttributeConfigurationPreSetCreated(EntityPreSetCreatedEvent $event)
    {
        /** @var SProductAttributeConfigurationEntity $entity */
        $entity = $event->getEntity();
        $data = $event->getData();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_product_attribute_configuration") {

            if (!isset($data["is_active"])) {
                $data["is_active"] = 1;
            }
            if (!isset($data["to_translate"])) {
                $data["to_translate"] = 1;
            }
            if (!isset($data["show_in_filter"])) {
                $data["show_in_filter"] = 1;
            }

            $event->setData($data);
        }
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @return bool
     */
    public function onSproductAttributeConfigurationPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var SProductAttributeConfigurationEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_product_attribute_configuration") {
            if (empty($this->sProductManager)) {
                $this->sProductManager = $this->container->get("s_product_manager");
            }

            $key = $this->sProductManager->generateSProductAttributeConfigurationKey($entity);

            $entity->setFilterKey($key);

            $entityValidation = new EntityValidation();

            if (empty($entity->getName())) {
                $entityValidation->setTitle("Error");
                $entityValidation->setMessage("Missing name");
                $entity->addEntityValidation($entityValidation);

                return $entity;
            } elseif (empty($entity->getSProductAttributeConfigurationType())) {

                $entityValidation->setTitle("Error");
                $entityValidation->setMessage("Missing attribute type");
                $entity->addEntityValidation($entityValidation);

                return $entity;
            }
        }
    }

    /**
     * @param EntityPreSetUpdatedEvent $event
     */
    public function onSproductAttributeConfigurationPreSetUpdated(EntityPreSetUpdatedEvent $event)
    {
        /** @var SProductAttributeConfigurationEntity $entity */
        $entity = $event->getEntity();
        $data = $event->getData();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_product_attribute_configuration") {

            if ($entity->getSProductAttributeConfigurationTypeId() != $data["s_product_attribute_configuration_type_id"]) {
                $data["configuration_type_changed"] = 1;
            }

            unset($data["s_product_attribute_configuration_type_id"]);
            unset($data["filter_key"]);

            $event->setData($data);
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @return bool
     */
    public function onSproductAttributeConfigurationPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var SProductAttributeConfigurationEntity $entity */
        $entity = $event->getEntity();
        $data = $event->getData();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_product_attribute_configuration") {

            $entityValidation = new EntityValidation();

            if (empty($entity->getName())) {
                $entityValidation->setTitle("Error");
                $entityValidation->setMessage("Missing name");
                $entity->addEntityValidation($entityValidation);

                return $entity;
            } elseif (empty($entity->getSProductAttributeConfigurationType())) {

                $entityValidation->setTitle("Error");
                $entityValidation->setMessage("Missing attribute type");
                $entity->addEntityValidation($entityValidation);

                return $entity;
            }

            if (isset($data["configuration_type_changed"]) && $data["configuration_type_changed"] == 1) {

                $configurableAttributes = $entity->getConfigurableAttributes();

                if (EntityHelper::isCountable($configurableAttributes) && count($configurableAttributes)) {
                    $entityValidation->setTitle("Error");
                    $entityValidation->setMessage("Attribute is used in configurations and type cannot be changed");
                    $entity->addEntityValidation($entityValidation);

                    return $entity;
                }
            }
        }
    }

    /**
     * @param EntityPreDeletedEvent $event
     * @return bool
     */
    public function onSproductAttributeConfigurationPreDeleted(EntityPreDeletedEvent $event)
    {
        /** @var SProductAttributeConfigurationEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_product_attribute_configuration") {

            $configurableAttributes = $entity->getConfigurableAttributes();

            if (EntityHelper::isCountable($configurableAttributes) && count($configurableAttributes)) {
                throw new \Exception("Attribute is used in configurations and cannot be deleted");
            }
        }

        return true;
    }


}
