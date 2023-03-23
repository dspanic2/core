<?php

namespace IntegrationBusinessBundle\Models;

class LuceedErpWarehouseParams
{
    private $warehouseCode;

    public function __construct()
    {
        $this->warehouseCode = null;
    }

    /**
     * @return mixed
     */
    public function getWarehouseCode()
    {
        return $this->warehouseCode;
    }

    /**
     * @param mixed $warehouseCode
     * @return LuceedErpWarehouseParams
     */
    public function setWarehouseCode($warehouseCode)
    {
        $this->warehouseCode = $warehouseCode;

        return $this;
    }
}
