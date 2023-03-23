<?php

namespace DPDBusinessBundle\Events;

use DPDBusinessBundle\Entity\DpdParcelEntity;
use Symfony\Component\EventDispatcher\Event;

class DpdParcelCreatedEvent extends Event
{

    const NAME = 'dpd_parcel.created';

    private $dpdParcel;

    /**
     * DpdParcelCreatedEvent constructor.
     * @param $dpdParcel
     */
    public function __construct($dpdParcel)
    {
        $this->dpdParcel = $dpdParcel;
    }

    /**
     * @return DpdParcelEntity
     */
    public function getDpdParcel()
    {
        return $this->dpdParcel;
    }

    /**
     * @param DpdParcelEntity $dpdParcel
     */
    public function setDpdParcel($dpdParcel)
    {
        $this->dpdParcel = $dpdParcel;
    }
}
