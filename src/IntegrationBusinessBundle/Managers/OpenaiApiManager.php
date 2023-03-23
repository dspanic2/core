<?php

namespace IntegrationBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Managers\RestManager;

class OpenaiApiManager extends AbstractBaseManager
{
    protected $chatGPTApiKey;

    public function initialize()
    {
        parent::initialize();

        $this->setChatGPTApiKey($_ENV["CHATGPT_API_KEY"] ?? null);
    }

    /**
     * @param $chatGPTApiKey
     * @return void
     */
    public function setChatGPTApiKey($chatGPTApiKey){
        $this->chatGPTApiKey = $chatGPTApiKey;
    }

    /**
     * @return mixed
     */
    public function getChatGPTApiKey(){
        return $this->chatGPTApiKey;
    }

    /**
     * @param RestManager|null $restManager
     * @param $endpoint
     * @param $type
     * @param $data
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function getApiData(RestManager $restManager = null, $endpoint, $type = "POST", $data = Array()){

        if(empty($this->getChatGPTApiKey())){
            throw new \Exception("Missing ChatGpt API KEY");
        }

        if($type == "POST" && empty($data)){
            throw new \Exception("Empty data for ChatGPT");
        }

        if(empty($restManager)){
            $restManager = new RestManager();
        }

        /*$data = Array();
        $data["model"] = "text-davinci-003";
        $data["prompt"] = "text-davinci-003";
        $data["temperature"] = 0;
        $data["max_tokens"] = 1608;
        $data["top_p"] = 1;
        $data["frequency_penalty"] = 0;
        $data["presence_penalty"] = 0;*/

        $restManager->CURLOPT_HTTPHEADER[] = "Authorization: Bearer ".$this->getChatGPTApiKey();
        $restManager->CURLOPT_SSL_VERIFYHOST = 0;
        $restManager->CURLOPT_SSL_VERIFYPEER = 0;
        $restManager->CURLOPT_TIMEOUT = 300;
        if($type == "POST"){
            #$restManager->CURLOPT_POST = 1;
            #$restManager->CURLOPT_CUSTOMREQUEST = "POST";
            #$restManager->CURLOPT_POSTFIELDS = $data;
        }

        $url = "https://api.openai.com/v1/".$endpoint;

        $res = $restManager->get($url, true);

        if(empty($res)){
            throw new \Exception("Empty ChatGPT response");
        }

        if(isset($res["error"]) && !empty($res["error"])){
            throw new \Exception($res["error"]["message"]);
        }

        return $res;
    }

    /**
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function getFiles(){

        if(empty($restManager)){
            $restManager = new RestManager();
        }

        $restManager->CURLOPT_HTTPHEADER[] = "Content-Type: application/json";

        return $this->getApiData($restManager,"files","GET");
    }


    /**
     * @param $data
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function uploadFile($data){

        if(empty($restManager)){
            $restManager = new RestManager();
        }

        $restManager->CURLOPT_HTTPHEADER[] = "Content-Type: multipart/form-data";
        $restManager->CURLOPT_POST = 1;
        $restManager->CURLOPT_CUSTOMREQUEST = "POST";
        $restManager->CURLOPT_POSTFIELDS = $data;

        return $this->getApiData($restManager,"files","POST", $data);
    }

    /**
     * @param $id
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function deleteFile($id){

        if(empty($restManager)){
            $restManager = new RestManager();
        }

        $restManager->CURLOPT_CUSTOMREQUEST = "DELETE";

        return $this->getApiData($restManager,"files/{$id}","DEL");
    }

    /**
     * @param $id
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function retrieveFile($id){

        if(empty($restManager)){
            $restManager = new RestManager();
        }

        return $this->getApiData($restManager,"files/{$id}","GET");
    }

    /**
     * @param $id
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function retrieveFileContent($id){

        if(empty($restManager)){
            $restManager = new RestManager();
        }

        return $this->getApiData($restManager,"files/{$id}/content","GET");
    }


}
