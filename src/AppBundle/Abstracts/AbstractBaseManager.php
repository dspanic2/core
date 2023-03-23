<?php

namespace AppBundle\Abstracts;

use AppBundle\Entity\UserEntity;
use AppBundle\Managers\ErrorLogManager;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * Base abstract class for any manager that will have to be container aware.
 */
abstract class AbstractBaseManager implements ContainerAwareInterface
{
    protected $container;
    protected $user;
    protected $twig;
    /**@var Logger $logger */
    protected $logger;
    protected $translator;
    protected $twigBase;
    /** @var ErrorLogManager $errorLogManager */
    protected $errorLogManager;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function setUser(UserEntity $user)
    {
        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }

    /**
     * Override this method to initialize all services you will require.
     */
    public function initialize()
    {
        /** @var TokenStorage $tokenStorage */
        $tokenStorage = $this->container->get("security.token_storage");
        if (empty($tokenStorage->getToken())) {
            $this->user = null;
        } else {
            $this->user = $tokenStorage->getToken()->getUser();
        }

        $this->logger = $this->container->get('logger');
        $this->twig = $this->container->get("templating");
        $this->twigBase = $this->container->get('twig');
        $this->translator = $this->container->get("translator");
        $this->errorLogManager = $this->container->get("error_log_manager");
    }
}
