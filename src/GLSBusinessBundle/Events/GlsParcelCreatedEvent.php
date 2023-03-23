<?php

namespace GLSBusinessBundle\Events;

use GLSBusinessBundle\Entity\GlsParcelEntity;
use Symfony\Component\EventDispatcher\Event;

class GlsParcelCreatedEvent extends Event
{

    const NAME = 'gls_parcel.created';

    private $glsParcel;

    /**
     * GlsParcelCreatedEvent constructor.
     * @param $glsParcel
     */
    public function __construct($glsParcel)
    {
        $this->glsParcel = $glsParcel;
    }

    /**
     * @return GlsParcelEntity
     */
    public function getGlsParcel()
    {
        return $this->glsParcel;
    }

    /**
     * @param GlsParcelEntity $glsParcel
     */
    public function setGlsParcel($glsParcel)
    {
        $this->glsParcel = $glsParcel;
    }
}
