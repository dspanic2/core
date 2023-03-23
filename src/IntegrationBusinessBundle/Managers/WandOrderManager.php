<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Managers\RestManager;
use CrmBusinessBundle\Abstracts\AbstractImportManager;
use CrmBusinessBundle\Entity\OrderEntity;

class WandOrderManager extends AbstractImportManager
{
    /** @var string $apiUrl */
    protected $apiUrl;

    public function initialize()
    {
        parent::initialize();
        $this->apiUrl = $_ENV["WAND_URL"];
    }

    /**
     * @param $post
     * @param null $apiUrl
     * @return array
     */
    public function sendOrder($post, $apiUrl = null)
    {
        $ret = array();
        $ret["error"] = true;
        $ret["data"] = null;
        $ret["message"] = null;

        $this->restManager = new RestManager();
        $this->restManager->CURLOPT_PORT = 8282;

        $ret["data"] = $post;

        $this->restManager->CURLOPT_POST = 1;
        $this->restManager->CURLOPT_POSTFIELDS = $post;
        $this->restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $this->restManager->CURLOPT_HTTPHEADER = array("Content-Type: application/json");

        if(empty($apiUrl)){
            $apiUrl = $this->apiUrl;
        }

        try{
            $res = $this->restManager->get($apiUrl."Narudzba");

            $error = false;

            if(empty($res)){
                $error = true;
            }
            else{
                $errorKey = "IsError";
                if(!isset($res[$errorKey])){
                    $errorKey = "isError";
                }
                if(!isset($res[$errorKey]) || $res[$errorKey] == true){
                    $error = true;
                }
            }

            if($error){
                $ret["message"] = json_encode($res);
            }
            else{
                $ret["error"] = false;
            }
        }
        catch (\Exception $e){
            $ret["message"] = $e->getMessage();
        }

        if($ret["error"]){
            $this->errorLogManager->logErrorEvent("Wand sendOrder error","{$ret["message"]} - ".json_encode($post),true);
        }

        return $ret;
    }

}