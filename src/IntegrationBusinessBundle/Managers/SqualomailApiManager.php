<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Managers\RestManager;

class SqualomailApiManager extends AbstractBaseManager
{
    private $apiUrl;
    private $apiKey;
    private $apiUser;
    private $apiAuth;

    public function initialize()
    {
        parent::initialize();

        $this->apiUrl = $_ENV["SQUALOMAIL_API_URL"];
        $this->apiKey = $_ENV["SQUALOMAIL_API_KEY"];
        $this->apiUser = $_ENV["SQUALOMAIL_API_USER"];
        $this->apiAuth = ["apiKey" => $this->apiKey, "apiUser" => $this->apiUser];
    }

    public function getLists()
    {
        $restManager = new RestManager();
        $restManager->CURLOPT_POST = 1;
        $restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $restManager->CURLOPT_HTTPHEADER = ["Content-Type: application/json"];
        $restManager->CURLOPT_POSTFIELDS = json_encode($this->apiAuth);

        $data = $restManager->get("https://api.squalomail.com/v1/get-lists");

        dump($restManager->code);
        dump($data);die;
    }

    public function getSubscriberByEmail($email)
    {

    }

    public function subscribeMember($email)
    {

    }

    public function getRecentlyUnsubscribedMembers()
    {

    }
}
