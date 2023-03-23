<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\UserEntity;
use AppBundle\Managers\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

// Do not remove (otherwise an error will occur).
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class GoogleAuthenticatorController extends AbstractController
{

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * @Route("/google-authenticator", name="save_google_authenticator")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function saveGoogleAuthenticatorAction(Request $request)
    {

        $p = $_POST;

        $this->initialize();

        if (!isset($p["mfa_google_authenticator_enabled"])) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('mfa_google_authenticator_enabled is not defined')));
        }

        $prev = $this->user->hasGoogleAuthenticator();
        $next = $p["mfa_google_authenticator_enabled"];

        // Process only if we have different values.
        if ($prev != $next) {
            // We first load CoreUserEntity so we can update its data.

            /** @var EntityManager $entityManager */
            $entityManager = $this->getContainer()->get('entity_manager');

            /** @var CoreUserEntity $coreUser */
            $coreUser = $this->helperManager->getCurrentCoreUser();

            if ($next) {
                $newSecret = $this->getContainer()->get("scheb_two_factor.security.google_authenticator")->generateSecret();
                $coreUser->setGoogleAuthenticatorSecret($newSecret);
                $this->user->setGoogleAuthenticatorSecret($newSecret);
            } else {
                $coreUser->unsetGoogleAuthenticatorSecret();
                $this->user->unsetGoogleAuthenticatorSecret();
            }

            $entityManager->saveEntity($coreUser);
        }

        $data = [
            'googleAuthenticator' => [
                'enabled' => $this->user->hasGoogleAuthenticator(),
                'qrCodeUrl' => null,
                'secret' => null
            ]
        ];

        if ($this->user->hasGoogleAuthenticator()) {
            $qrCodeUrl = $this
                ->get("scheb_two_factor.security.google_authenticator")
                ->getUrl($this->user);

            $data['googleAuthenticator']['qrCodeUrl'] = $qrCodeUrl;
            $data['googleAuthenticator']['secret'] = $this->user->getGoogleAuthenticatorSecret();
        }

        return new JsonResponse([
            'error' => false,
            'title' => $this->translator->trans('Success'),
            'message' => $this->translator->trans('Two factor auth has been changed'),
            'data' => $data
        ]);
    }
}
