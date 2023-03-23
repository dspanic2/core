<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\CoreUserEntity;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class LocaleListener implements EventSubscriberInterface
{
    protected $defaultLocale;
    protected $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage, $defaultLocale = 'en')
    {
        $this->tokenStorage = $tokenStorage;
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $token = $this->tokenStorage->getToken();

        if ($request->getSession() != null) {

            /**
             * Listener for backend
             */
            if ($request->getSession()->get('_locale_type') == "backend") {
                if (isset($token) && !empty($token) && is_object($token->getUser())) {
                    $user = $token->getUser();
                    $request->setLocale($user->getCoreLanguage()->getCode());
                    $request->getSession()->set('_locale', $user->getCoreLanguage()->getCode());
                }
            } /**
             * Frontend and default listener
             */
            /*else {
                if (!empty($request->getSession()->get('_locale'))) {
                    $request->setLocale($request->getSession()->get('_locale'));
                } else {
                    $request->setLocale($this->defaultLocale);
                    $request->getSession()->set('_locale', $this->defaultLocale);
                }
            }*/
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            // must be registered before the default Locale listener
            KernelEvents::REQUEST => array(array('onKernelRequest', 7)),
        );
    }
}
