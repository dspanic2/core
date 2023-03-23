<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;

class GoogleCaptchaValidateManager extends AbstractBaseManager
{
    protected $session;

    public function initialize()
    {
        parent::initialize();
    }

    public function shouldValidateGoogleRecaptchaV3()
    {
        if ($_ENV["VALIDATE_RECAPTCHA"] != 1 || isset($_ENV["UNIT_TEST_RUNNING"])) {
            return false;
        }

        if (empty($this->session)) {
            $this->session = $this->container->get('session');
        }
        $recaptchaValidated = $this->session->get("recaptcha_validate");
        if (!empty($recaptchaValidated) && $recaptchaValidated == 1) {
            return false;
        }

        if ($recaptcha_secret = $_ENV["GOOGLE_RECAPTCHA_V3_KEY"]) {
            return true;
        }
        return false;
    }

    public function validateGoogleRecaptchaV3($recaptcha_response)
    {
        if ($recaptcha_secret = $_ENV["GOOGLE_RECAPTCHA_V3_KEY"]) {
            // Build POST request:
            $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';

            // Make and decode POST request:
            $recaptcha = file_get_contents($recaptcha_url . '?secret=' . $recaptcha_secret . '&response=' . $recaptcha_response);
            $recaptcha = json_decode($recaptcha);

            $score = 0.5;
            if (isset($_ENV["RECAPTCHA_SCORE"]) && !empty($_ENV["RECAPTCHA_SCORE"])) {
                $score = $_ENV["RECAPTCHA_SCORE"];
            }
            if ($_ENV["IS_PRODUCTION"] != 1) {
                $score = 0.1;
            }

            // Take action based on the score returned:
            if ($recaptcha->success == true && $recaptcha->score >= $score) {
                if (empty($this->session)) {
                    $this->session = $this->container->get('session');
                }
                $this->session->set("recaptcha_validate", 1);
                return true;
            }

            // Override errors.
            if ($recaptcha->success == false) {
                // Override timeout-or-duplicate error.
                if (in_array('timeout-or-duplicate', $recaptcha->{"error-codes"})) {
                    return true;
                }
            }
        }
        return false;
    }
}
