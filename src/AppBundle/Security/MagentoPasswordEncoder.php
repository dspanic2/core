<?php

namespace AppBundle\Security;

use Symfony\Component\Security\Core\Encoder\BasePasswordEncoder;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder;

/**
 * @author Elnur Abdurrakhimov <elnur@elnur.pro>
 * @author Terje Bråten <terje@braten.be>
 */
class MagentoPasswordEncoder extends BCryptPasswordEncoder
{
    const MAX_PASSWORD_LENGTH = 72;

    /**
     * @var string
     */
    private $cost;

    /**
     * Constructor.
     *
     * @param int $cost The algorithmic cost that should be used
     *
     * @throws \RuntimeException         When no BCrypt encoder is available
     * @throws \InvalidArgumentException if cost is out of range
     */
    public function __construct($cost = 6)
    {
        $cost = (int) $cost;
        if ($cost < 4 || $cost > 31) {
            throw new \InvalidArgumentException('Cost must be in the range of 4-31.');
        }

        $this->cost = $cost;
    }

    /**
     * Encodes the raw password.
     *
     * It doesn't work with PHP versions lower than 5.3.7, since
     * the password compat library uses CRYPT_BLOWFISH hash type with
     * the "$2y$" salt prefix (which is not available in the early PHP versions).
     *
     * @see https://github.com/ircmaxell/password_compat/issues/10#issuecomment-11203833
     *
     * It is almost best to **not** pass a salt and let PHP generate one for you.
     *
     * @param string $raw  The password to encode
     * @param string $salt The salt
     *
     * @return string The encoded password
     *
     * @throws BadCredentialsException when the given password is too long
     *
     * @link http://lxr.php.net/xref/PHP_5_5/ext/standard/password.c#111
     */
    public function encodePassword($raw, $salt)
    {
        if ($this->isPasswordTooLong($raw)) {
            throw new BadCredentialsException('Invalid password.');
        }

        $options = array('cost' => $this->cost);

        if ($salt) {
            // Ignore $salt, the auto-generated one is always the best
        }

        return password_hash($raw, PASSWORD_BCRYPT, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid($encoded, $raw, $salt)
    {
        /**
         * For magento password
         */
        if(strlen($salt) < 4){

            /**
             * Magento password example
             *  Encoded pass: 5c422aedce7d044ee2894ddf19da7ca3:9I
             *  Salt: 9I
             *  MD5 (salt.raw_pass) = 5c422aedce7d044ee2894ddf19da7ca3
             *  dump(md5("9I57744292").":9I");
             *  raw_pass example = 57744292
             *  How to save in user_entity:
             *  salt: 9I
             *  password: c422aedce7d044ee2894ddf19da7ca3
             *
             */
            if(md5($salt.$raw) === $encoded){
                return true;
            }
            return false;
        }

        return !$this->isPasswordTooLong($raw) && password_verify($raw, $encoded);
    }
}
