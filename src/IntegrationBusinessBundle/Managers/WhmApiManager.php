<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Managers\RestManager;

class WhmApiManager extends AbstractBaseManager
{
    /** @var string $whmHost */
    private $whmHost;
    /** @var string $whmPort */
    private $whmPort;
    /** @var string $whmUser */
    private $whmUser;
    /** @var string $apiToken */
    private $apiToken;

    public function initialize()
    {
        parent::initialize();

        $this->whmHost = $_ENV["WHM_HOST"];
        $this->whmPort = $_ENV["WHM_PORT"];
        $this->whmUser = $_ENV["WHM_USER"];
        $this->apiToken = $_ENV["WHM_API_TOKEN"];
    }

    /**
     * @param $endpoint
     * @param $method
     * @param array $query
     * @return mixed
     * @throws \Exception
     */
    private function getApiResponse($endpoint, $method, $query = [])
    {
        $query = array_merge(["api.version" => 1], $query);

        $restManager = new RestManager();
        $restManager->CURLOPT_CUSTOMREQUEST = $method;
        $restManager->CURLOPT_HTTPHEADER = [
            "Authorization: whm " . $this->whmUser . ":" . $this->apiToken
        ];

        $url = sprintf("https://%s:%s%s?%s", $this->whmHost, $this->whmPort, $endpoint, http_build_query($query));

        $data = $restManager->get($url, false);
        $code = $restManager->code;

        if ($code != 200) {
            throw new \Exception(sprintf("%s request error: %u, %s", $endpoint, $code, $data));
        }
        if (empty($data)) {
            throw new \Exception("Response is empty");
        }

        $data = json_decode($data, true);

        return $data;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getAvailableHostingPlans()
    {
        $params = [
            "want" => "all"
        ];

        return $this->getApiResponse("/json-api/listpkgs", "GET", $params);
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getDiskUsage()
    {
        $params = [
            "cache_mode" => "on"
        ];

        return $this->getApiResponse("/json-api/get_disk_usage", "GET", $params);
    }
}
