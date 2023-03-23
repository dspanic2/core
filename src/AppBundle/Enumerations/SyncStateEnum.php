<?php

namespace AppBundle\Enumerations;

class SyncStateEnum
{
    const Unmodified = "unmodified";
    const Created = "new";
    const Modified = "modified";
    const Deleted = "deleted";
}
