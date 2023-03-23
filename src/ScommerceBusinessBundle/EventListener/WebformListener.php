<?php

namespace ScommerceBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Events\EntityPreUpdatedEvent;
use AppBundle\Helpers\StringHelper;
use ScommerceBusinessBundle\Entity\WebformFieldTypeEntity;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class WebformListener implements ContainerAwareInterface
{
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     * @throws \Exception
     */
    public function onWebformEntityPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var WebformFieldTypeEntity $entity */
        $entity = $event->getEntity();
        if ($entity->getEntityType()->getEntityTypeCode() == "webform_field_type") {

            $code = StringHelper::convertStringToCode($entity->getName());

            $entity->setFieldTypeCode($code);
        }
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onWebformEntityPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var WebformFieldTypeEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "webform_field_type") {

            if (empty($entity->getFieldTypeCode())) {
                $code = StringHelper::convertStringToCode($entity->getName());

                $entity->setFieldTypeCode($code);
            }
        }
    }
}
