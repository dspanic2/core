<?php

namespace ScommerceBusinessBundle\EventListener;

use AppBundle\Events\EntityPreUpdatedEvent;
use ScommerceBusinessBundle\Entity\SRouteNotFoundEntity;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SRouteNotFoundListener implements ContainerAwareInterface
{
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreUpdatedEvent $event
     * @throws \Exception
     */
    public function onSRouteNotFoundPreUpdated(EntityPreUpdatedEvent $event)
    {
        /** @var SRouteNotFoundEntity $entity */
        $entity = $event->getEntity();
        if ($entity->getEntityType()->getEntityTypeCode() == "s_route_not_found") {

            if(empty($entity->getRedirectTo())){
                $entity->setIsRedirected(0);
            }
            else{
                $entity->setIsRedirected(1);
            }
        }
    }
}
