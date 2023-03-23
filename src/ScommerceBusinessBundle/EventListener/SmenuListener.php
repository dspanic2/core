<?php

namespace ScommerceBusinessBundle\EventListener;

use AppBundle\Events\EntityPreCreatedEvent;
use ScommerceBusinessBundle\Entity\SMenuEntity;
use ScommerceBusinessBundle\Managers\MenuManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SmenuListener implements ContainerAwareInterface
{
    /** @var MenuManager $menuManager */
    protected $menuManager;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param EntityPreCreatedEvent $event
     */
    public function onSmenuPreCreated(EntityPreCreatedEvent $event)
    {
        /** @var SMenuEntity $entity */
        $entity = $event->getEntity();

        if ($entity->getEntityType()->getEntityTypeCode() == "s_menu") {
            if (empty($this->menuManager)) {
                $this->menuManager = $this->container->get("menu_manager");
            }

            $code = $this->menuManager->createCode($entity->getName(), $entity->getStore());

            $entity->setMenuCode($code);
        }
    }
}
