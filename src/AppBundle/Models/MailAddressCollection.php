<?php

namespace AppBundle\Models;

class MailAddressCollection
{
    /** @var array $addresses */
    private $addresses = array();

    /**
     * @param MailAddress $address
     */
    public function addMailAddress(MailAddress $address)
    {
        $this->addresses[] = $address;
    }

    /**
     * @return array
     */
    public function getAddresses()
    {
        return $this->addresses;
    }
}