<?php

namespace AppBundle\Extensions;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class SecurityExtension extends \Symfony\Bridge\Twig\Extension\SecurityExtension
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(AuthorizationCheckerInterface $securityChecker = null, ContainerInterface $container)
    {
        parent::__construct($securityChecker);
        $this->container = $container;
    }

    public function isGranted($role, $object = null, $field = null)
    {
        $isGranted = parent::isGranted($role, $object = null, $field = null);

        if ($role == "ROLE_ADMIN") {
            /** @var Session $session */
            $session = $this->container->get('session');

            $superadminDisabled = $session->get("disable_superadmin") ?? false;

            return $isGranted && !$superadminDisabled;
        }

        return $isGranted;
    }
}
