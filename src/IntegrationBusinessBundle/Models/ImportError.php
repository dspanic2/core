<?php

namespace IntegrationBusinessBundle\Models;

class ImportError
{
    private $function;
    private $code;
    private $message;
    private $data;

    /**
     * @param $function
     * @param $code
     * @param $message
     * @param array $data
     */
    public function __construct($function, $code, $message, $data = [])
    {
        $this->function = $function;
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getFunction()
    {
        return $this->function;
    }

    /**
     * @param mixed $function
     * @return ImportError
     */
    public function setFunction($function)
    {
        $this->function = $function;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param mixed $code
     * @return ImportError
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param mixed $message
     * @return ImportError
     */
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     * @return ImportError
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }
}