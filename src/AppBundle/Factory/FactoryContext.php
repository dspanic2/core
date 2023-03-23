<?php

namespace AppBundle\Factory;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FactoryContext implements ContainerAwareInterface
{
    protected $container;
    protected $entityTypeContext;
    protected $user;
    protected $tokenStorage;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container=$container;
    }
    public function getContainer()
    {
        return $this->container;
    }

    public function getContext(\AppBundle\Entity\EntityType $entityType)
    {

        $this->tokenStorage = $this->container->get("security.token_storage");
        if ($this->tokenStorage->getToken() != null) {
            $this->user = $this->tokenStorage->getToken()->getUser();
        }

        $context = $this->container->get("entity_context");
        $context->setRepository($entityType->getBundle(), $entityType->getEntityModel());
        $context->setEntityType($entityType);
        if (is_object($this->user)) {
            $context->setUser($this->user);
        }

        return $context;
    }
}
