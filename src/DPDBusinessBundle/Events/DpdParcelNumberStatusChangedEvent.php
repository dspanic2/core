<?php

namespace DPDBusinessBundle\Events;

use DPDBusinessBundle\Entity\DpdParcelNumbersEntity;
use Symfony\Component\EventDispatcher\Event;

class DpdParcelNumberStatusChangedEvent extends Event
{

    const NAME = 'dpd_parcel_number.status_changed';

    private $dpdParcelNumber;

    /**
     * @param $dpdParcelNumber
     */
    public function __construct($dpdParcelNumber)
    {
        $this->dpdParcelNumber = $dpdParcelNumber;
    }

    /**
     * @return DpdParcelNumbersEntity
     */
    public function getDpdParcelNumber()
    {
        return $this->dpdParcelNumber;
    }

    /**
     * @param DpdParcelNumbersEntity $dpdParcelNumber
     */
    public function setDpdParcelNumber($dpdParcelNumber)
    {
        $this->dpdParcelNumber = $dpdParcelNumber;
    }
}
