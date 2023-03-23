<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;

class ThirdPartyManager extends AbstractBaseManager
{
    /** @var RouteManager $routeManager */
    protected $routeManager;

    /**
     * @param $destinationSufix
     * @return |null
     */
    public function getGoogleLoginButton($destinationSufix)
    {

        $enableLogin = $_ENV["GOOGLE_CLIENT_USE"] ?? 0;

        if (!$enableLogin) {
            return null;
        }

        $session = $this->container->get("session");

        $destinationUrl = $this->getGoogleDestinationUrl($session, $destinationSufix);

        $client = $this->getGoogleClient($destinationUrl);

        return $client->createAuthUrl();
    }

    /**
     * @param $session
     * @param $destinationSufix
     * @return string
     */
    public function getGoogleDestinationUrl($session, $destinationSufix)
    {

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        $websiteData = $this->routeManager->getWebsiteDataById($session->get("current_website_id"));

        $currentLanguage = "";
        if ($websiteData["is_multilang"]) {
            $currentLanguage = $session->get("current_language") . "/";
        }

        return $_ENV["SSL"] . "://" . $websiteData["base_url"] . $_ENV["FRONTEND_URL_PORT"] . "/" . $currentLanguage . $destinationSufix;
    }

    /**
     * @param $destinationUrl
     * @return \Google_Client
     */
    public function getGoogleClient($destinationUrl)
    {

        $client = new \Google_Client();
        $client->setClientId($_ENV["GOOGLE_CLIENT_ID"]);
        $client->setClientSecret($_ENV["GOOGLE_CLIENT_SECRET"]);
        $client->setRedirectUri($destinationUrl);
        $client->addScope("email");
        $client->addScope("profile");

        return $client;
    }

    /**
     * @param $client
     * @param $code
     * @return |null
     */
    public function getGoogleAccount($client, $code)
    {

        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (empty($token) || isset($token["error"])) {
            return null;
        }

        $client->setAccessToken($token['access_token']);

        // get profile info
        $google_oauth = new \Google_Service_Oauth2($client);
        return $google_oauth->userinfo->get();
    }

    /**
     * @param $destinationSufix
     * @return |null
     */
    public function getFacebookLoginButton($destinationSufix)
    {

        $enableLogin = $_ENV["FACEBOOK_CLIENT_USE"] ?? 0;

        if (!$enableLogin) {
            return null;
        }

        $session = $this->container->get("session");

        $destinationUrl = $this->getFacebookDestinationUrl($session, $destinationSufix);

        $client = $this->getFacebookClient();

        $helper = $client->getRedirectLoginHelper();
        $permissions = ['email']; // optional

        return $helper->getLoginUrl($destinationUrl, $permissions);
    }

    /**
     * @return \Facebook\Facebook
     */
    public function getFacebookClient()
    {

        $fb = new \Facebook\Facebook([
            'app_id' => $_ENV["FACEBOOK_CLIENT_ID"],
            'app_secret' => $_ENV["FACEBOOK_CLIENT_SECRET"],
            'default_graph_version' => $_ENV["FACEBOOK_GRAPH_VERSION"],
        ]);

        return $fb;
    }

    /**
     * @param $session
     * @param $destinationSufix
     * @return string
     */
    public function getFacebookDestinationUrl($session, $destinationSufix)
    {

        if (empty($this->routeManager)) {
            $this->routeManager = $this->container->get("route_manager");
        }

        $websiteData = $this->routeManager->getWebsiteDataById($session->get("current_website_id"));

        $currentLanguage = "";
        if ($websiteData["is_multilang"]) {
            $currentLanguage = $session->get("current_language") . "/";
        }

        return $_ENV["SSL"] . "://" . $websiteData["base_url"] . $_ENV["FRONTEND_URL_PORT"] . "/" . $currentLanguage . $destinationSufix;
    }

    /**
     * @param $client
     * @return |null
     */
    public function getFacebookAccount($client)
    {

        $helper = $client->getRedirectLoginHelper();
        try {
            $accessToken = $helper->getAccessToken();
        } catch (\Exception $e) {
            return null;
        }

        if (empty($accessToken)) {
            return null;
        }

        // OAuth 2.0 client handler
        $oAuth2Client = $client->getOAuth2Client();
        // Exchanges a short-lived access token for a long-lived one
        $longLivedAccessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
        $accessToken = (string)$longLivedAccessToken;
        // setting default access token to be used in script
        $client->setDefaultAccessToken($accessToken);

        $profile = null;

        try {
            $profile_request = $client->get('/me?fields=name,first_name,last_name,email');
            $profile = $profile_request->getGraphUser();
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            return null;
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            return null;
        }

        return $profile;
    }
}
