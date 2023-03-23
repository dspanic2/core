<?php

namespace AppBundle\Security;

use AppBundle\Context\CoreContext;
use AppBundle\Entity\UserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

//use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\SimpleFormAuthenticatorInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Authenticator implements SimpleFormAuthenticatorInterface
{
    private $encoder;
    private $usersContext;
    protected $translator;
    protected $container;

    public function __construct(UserPasswordEncoderInterface $encoder, CoreContext $users_context, TranslatorInterface $translator, ContainerInterface $container)
    {
        $this->encoder = $encoder;
        $this->usersContext = $users_context;
        $this->translator = $translator;
        $this->container = $container;
    }

    public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, $data, $username = 'anonymous')
    {
        $user = $this->usersContext->getOneBy(array('username' => $username));

        if (empty($user)) {
            $user = $this->usersContext->getOneBy(array('email' => $username));
            if (empty($user)) {
                return false;
            }
        }

        if (!empty($data["password"])) {
            $passwordValid = $this->checkCredentials($data["password"], $user);
            if (!$passwordValid) {
                return false;
            }
        }

        return new UsernamePasswordToken(
            $user,
            $data["password"],
            $data["providerKey"],
            array("ROLE_USER")
        );
    }

    public function checkCredentials($credentials, UserEntity $user)
    {
        return $this->encoder->isPasswordValid($user, $credentials);
    }

    public function supportsToken(TokenInterface $token, $providerKey)
    {
        return $token instanceof UsernamePasswordToken
            && $token->getProviderKey() === $providerKey;
    }

    public function createToken(Request $request, $username, $password, $providerKey)
    {
        return new UsernamePasswordToken($username, $password, $providerKey);
    }
}
