<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

// Do not remove (otherwise an error will occur).
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class GoogleOAuthController extends AbstractController
{
    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * @Route("/get_google_access_token", name="get_google_access_token")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function getGoogleAccessTokenAction(Request $request)
    {
        $this->initialize();

        $jsonPath = $_ENV["WEB_PATH"] . $_ENV["GOOGLE_APPLICATION_CREDENTIALS"];

        try {
            $client = new \Google_Client();
            $client->setAuthConfig($jsonPath);
            $client->addScope(\Google_Service_Analytics::ANALYTICS_READONLY);
            //$client->useApplicationDefaultCredentials();
            $client->refreshTokenWithAssertion();

            $response = $client->getAccessToken();
        } catch (Exception $e) {
            dump($e->getMessage());die;
        }

        if (!isset($response["access_token"]) || empty($response["access_token"])) {
            return new JsonResponse(array("error" => true, "message" => $this->translator->trans("Could not get access token")));
        }

        return new JsonResponse(array("error" => false, "access_token" => $response["access_token"]));
    }
}
