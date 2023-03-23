<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\SettingsEntity;
use AppBundle\Managers\ApplicationSettingsManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\RestManager;

class InstagramApiManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    private $entityManager;
    /** @var RestManager $restManager */
    private $restManager;
    /** @var string $apiUrl */
    private $apiUrl;
    /** @var SettingsEntity $accessToken */
    private $accessToken;
    /** @var SettingsEntity $clientSecret */
    private $clientSecret;
    /** @var SettingsEntity $lastRefreshed */
    private $lastRefreshed;

    public function initialize()
    {
        parent::initialize();

        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->restManager = $this->getContainer()->get("rest_manager");

        /** @var ApplicationSettingsManager $settingsManager */
        $settingsManager = $this->getContainer()->get("settings_manager");

        $this->apiUrl = "https://graph.instagram.com/";
        $this->accessToken = $settingsManager->getApplicationSettingByCode("instagram_access_token");
        $this->clientSecret = $settingsManager->getApplicationSettingByCode("instagram_client_secret");
        $this->lastRefreshed = $settingsManager->getApplicationSettingByCode("instagram_last_refreshed");
    }

    /**
     * @param $accessToken
     * @param $clientSecret
     * @return false|mixed
     * @throws \Exception
     */
    public function getRefreshToken($accessToken, $clientSecret)
    {
        $params = [
            "grant_type" => "ig_refresh_token",
            "client_secret" => $clientSecret,
            "access_token" => $accessToken
        ];

        $result = $this->restManager->get($this->apiUrl . "refresh_access_token?" . http_build_query($params));
        if (empty($result) || !isset($result["access_token"])) {
            throw new \Exception("Could not refresh access token: " . json_encode($result));
        }

        return $result["access_token"];
    }

    /**
     * @param $accessToken
     * @return string
     */
    public function getMediaUrl($accessToken)
    {
        $params = [
            "fields" => "id,media_url,thumbnail_url,permalink,caption",
            "access_token" => $accessToken
        ];

        return $this->apiUrl . "me/media?" . http_build_query($params);
    }

    /**
     * @return array|false
     * @throws \Exception
     */
    public function getInstagramPosts()
    {
        if (empty($this->accessToken)) {
            throw new \Exception("Access token is not set");
        }
        if (empty($this->clientSecret)) {
            throw new \Exception("Client secret is not set");
        }

        $accessToken = $this->accessToken->getSettingsValue()[3];
        $clientSecret = $this->clientSecret->getSettingsValue()[3];
        $lastRefreshed = (int)$this->lastRefreshed->getSettingsValue()[3];

        $timeNow = time();
        if ($timeNow > ($lastRefreshed + 604800)) { /* 7 days */

            $accessToken = $this->getRefreshToken($accessToken, $clientSecret);
            $lastRefreshed = $timeNow;

            $this->accessToken->setSettingsValue(json_encode([3 => $accessToken]));
            $this->entityManager->saveEntityWithoutLog($this->accessToken);
            $this->lastRefreshed->setSettingsValue(json_encode([3 => $lastRefreshed]));
            $this->entityManager->saveEntityWithoutLog($this->lastRefreshed);
        }

        $data = [];

        $url = $this->getMediaUrl($accessToken);
        while ($url) {
            $media = $this->restManager->get($url);
            if (isset($media["data"])) {
                $data = array_merge($data, $media["data"]);
            }
            $url = null;
            if (isset($media["paging"]["next"])) {
                $url = $media["paging"]["next"];
            }
        }

        return $data;
    }
}
