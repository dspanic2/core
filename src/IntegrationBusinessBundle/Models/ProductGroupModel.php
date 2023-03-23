<?php

namespace IntegrationBusinessBundle\Models;

class ProductGroupModel
{
    private $name;
    private $code;
    private $parent;
    private $remoteId;

    /**
     * @param $name
     * @param $code
     * @param $parent
     * @param null $remoteId
     */
    public function __construct($name, $code, $parent, $remoteId = null)
    {
        $this->name = $name;
        $this->code = $code;
        $this->parent = $parent;
        $this->remoteId = $remoteId;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
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
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @return mixed
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param mixed $parent
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
    }

    /**
     * @return mixed
     */
    public function getRemoteId()
    {
        return $this->remoteId;
    }

    /**
     * @param mixed $remoteId
     */
    public function setRemoteId($remoteId)
    {
        $this->remoteId = $remoteId;
    }
}