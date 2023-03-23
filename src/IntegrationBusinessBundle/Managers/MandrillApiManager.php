<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Managers\RestManager;

class MandrillApiManager extends AbstractBaseManager
{
    protected $apiUrl;
    protected $apiKey;

    public function initialize()
    {
        parent::initialize();

        $this->apiUrl = $_ENV["MANDRILL_API_URL"];
        $this->apiKey = $_ENV["MANDRILL_API_KEY"];
    }

    /**
     * @param $endpoint
     * @param null $email
     * @return mixed
     * @throws \Exception
     */
    private function getApiResponse($endpoint, $email = null)
    {
        $body = [
            "key" => $this->apiKey
        ];

        if (!empty($email)) {
            $body["email"] = $email;
        }

        $restManager = new RestManager();
        $restManager->CURLOPT_POST = 1;
        $restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $restManager->CURLOPT_POSTFIELDS = json_encode($body);

        $data = $restManager->get($this->apiUrl . $endpoint);
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
     * @param $email
     * @return bool
     * @throws \Exception
     */
    public function addSubscriber($email)
    {
        $data = $this->getApiResponse("/allowlists/add", $email);
        if (!isset($res["added"]) || !$res["added"]) {
            throw new \Exception(sprintf("Error adding user to allow list: %s, %s", $email, json_encode($data)));
        }

        return true;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getSubscribers()
    {
        return $this->getApiResponse("/allowlists/list");
    }

    /**
     * @param $email
     * @return bool
     * @throws \Exception
     */
    public function removeSubscriber($email)
    {
        $data = $this->getApiResponse("/allowlists/delete", $email);
        if (!isset($res["deleted"]) || !$res["deleted"]) {
            throw new \Exception(sprintf("Error removing user from allow list: %s, %s", $email, json_encode($data)));
        }

        return true;
    }
}
