<?php

namespace CrmBusinessBundle\Model;

class InvoiceFiscalResponse
{

    /**
     * @var bool
     */
    private $isFiscalized;

    /**
     * @var string
     */
    private $responseBody;

    /**
     * @var string
     */
    private $responseCode;

    /**
     * @var string
     */
    private $jir;

    /**
     * @return bool
     */
    public function isFiscalized(): bool
    {
        return $this->isFiscalized;
    }

    /**
     * @param bool $isFiscalized
     */
    public function setIsFiscalized(bool $isFiscalized)
    {
        $this->isFiscalized = $isFiscalized;
    }

    /**
     * @return string
     */
    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

    /**
     * @param string $responseBody
     */
    public function setResponseBody(string $responseBody)
    {
        $this->responseBody = $responseBody;
    }

    /**
     * @return string
     */
    public function getResponseCode(): string
    {
        return $this->responseCode;
    }

    /**
     * @param string $responseCode
     */
    public function setResponseCode(string $responseCode)
    {
        $this->responseCode = $responseCode;
    }

    /**
     * @return string
     */
    public function getJir(): string
    {
        return $this->jir;
    }

    /**
     * @param string $jir
     */
    public function setJir(string $jir)
    {
        $this->jir = $jir;
    }
}
