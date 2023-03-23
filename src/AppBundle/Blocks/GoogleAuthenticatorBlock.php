<?php

namespace AppBundle\Blocks;

use AppBundle\Abstracts\AbstractBaseBlock;
use AppBundle\Entity\UserEntity;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class GoogleAuthenticatorBlock extends AbstractBaseBlock
{

    public function GetPageBlockTemplate()
    {
        return 'AppBundle:Block:google_authenticator.html.twig';
    }

    public function GetPageBlockSetingsTemplate()
    {
        return 'AppBundle:BlockSettings:google_authenticator.html.twig';
    }

    /**
     * @return mixed
     */
    public function GetPageBlockData()
    {
        /** @var TokenStorage $tokenStorage */
        $tokenStorage = $this->container->get("security.token_storage");

        $this->pageBlockData['mfaGoogleAuthenticatorQRCodeURL'] = null;
        $this->pageBlockData['entity'] = null;

        if ($tokenStorage->getToken() !== null) {
            /** @var UserEntity $currentUser */
            $currentUser = $tokenStorage->getToken()->getUser();
            $this->pageBlockData['entity'] = $currentUser;
            if ($currentUser->getGoogleAuthenticatorSecret()) {
                $qrCodeUrl = $this->container
                    ->get("scheb_two_factor.security.google_authenticator")
                    ->getUrl($currentUser);

                $this->pageBlockData['mfaGoogleAuthenticatorQRCodeURL'] = $qrCodeUrl;
            }
        }

        return $this->pageBlockData;
    }

    /**
     * Do not show if MFA is not enabled via settings.
     * @return bool|int
     */
    public function isVisible()
    {
        $enabled = $_ENV["MFA_ENABLED"] ?? 0;

        return !!(int) $enabled;
    }
}
