<?php

namespace AppBundle\Managers;

class RestManager
{
    public $CURLOPT_RETURNTRANSFER = true;
    public $CURLOPT_ENCODING = "";
    public $CURLOPT_MAXREDIRS = 10;
    public $CURLOPT_TIMEOUT = 30;
    public $CURLOPT_FOLLOWLOCATION = true;
    public $CURLOPT_HTTP_VERSION = CURL_HTTP_VERSION_1_1;
    public $CURLOPT_CUSTOMREQUEST = "GET";
    public $CURLOPT_POST = false;
    public $CURLOPT_POSTFIELDS = "";
    public $CURLOPT_HTTPHEADER = [];
    public $CURLOPT_SSL_VERIFYPEER = 1;
    public $CURLOPT_SSL_VERIFYHOST = 2;

    /** @var \CurlHandle $curl */
    private $curl;
    /** @var array $headers */
    public $headers;
    /** @var int $code */
    public $code;

    public function __construct()
    {
        $this->curl = curl_init();
    }

    public function __destruct()
    {
        curl_close($this->curl);
    }

    public function get($url, $decodeJson = true, $returnHeaders = false, $options = [])
    {
        $options = array_replace([
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => $this->CURLOPT_RETURNTRANSFER,
            CURLOPT_ENCODING => $this->CURLOPT_ENCODING,
            CURLOPT_MAXREDIRS => $this->CURLOPT_MAXREDIRS,
            CURLOPT_TIMEOUT => $this->CURLOPT_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => $this->CURLOPT_FOLLOWLOCATION,
            CURLOPT_HTTP_VERSION => $this->CURLOPT_HTTP_VERSION,
            CURLOPT_CUSTOMREQUEST => $this->CURLOPT_CUSTOMREQUEST,
            CURLOPT_POSTFIELDS => $this->CURLOPT_POSTFIELDS,
            CURLOPT_HTTPHEADER => $this->CURLOPT_HTTPHEADER,
            CURLOPT_SSL_VERIFYPEER => $this->CURLOPT_SSL_VERIFYPEER,
            CURLOPT_SSL_VERIFYHOST => $this->CURLOPT_SSL_VERIFYHOST
        ], $options);

        if (empty($options[CURLOPT_POSTFIELDS])) {
            unset($options[CURLOPT_POSTFIELDS]);
        }
        if (empty($options[CURLOPT_HTTPHEADER])) {
            unset($options[CURLOPT_HTTPHEADER]);
        }

        curl_setopt_array($this->curl, $options);

        if ($returnHeaders) {
            $this->headers = [];
            curl_setopt($this->curl, CURLOPT_HEADER, true);
            curl_setopt($this->curl, CURLOPT_HEADERFUNCTION, function ($curl, $header) {
                $length = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) {
                    return $length;
                }
                $this->headers[strtolower(trim($header[0]))][] = trim($header[1]);
                return $length;
            });
        }

        $response = curl_exec($this->curl);
        $error = curl_error($this->curl);
        $this->code = (int)curl_getinfo($this->curl, CURLINFO_HTTP_CODE);

        if (!empty($error)) {
            throw new \Exception($error, curl_errno($this->curl));
        }
        if ($decodeJson) {
            $response = json_decode($response, true);
        }

        return $response;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getHeaders()
    {
        return $this->headers;
    }
}