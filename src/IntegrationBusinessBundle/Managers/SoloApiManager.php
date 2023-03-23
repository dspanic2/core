<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Managers\RestManager;

class SoloApiManager extends AbstractBaseManager
{
    /** @var string $apiUrl */
    private $apiUrl;
    /** @var string $apiKey */
    private $apiKey;

    public function initialize()
    {
        parent::initialize();

        $this->apiUrl = $_ENV["SOLO_API_KEY"];
        $this->apiKey = $_ENV["SOLO_API_URL"];
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

}
