<?php

namespace CrmBusinessBundle\EventListener;

use AppBundle\Entity\SettingsEntity;
use AppBundle\Events\EntityPreCreatedEvent;
use AppBundle\Helpers\StringHelper;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductLabelPositionListener implements ContainerAwareInterface
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
    public function onProductLabelPositionPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var SettingsEntity $entity */
        $entity = $event->getEntity();
        if ($entity->getEntityType()->getEntityTypeCode() == "product_label_position") {

            $code = StringHelper::convertStringToCode($entity->getName());
            $entity->setCode($code);
        }
    }
}
