<?php

namespace AppBundle\Interfaces\Providers;

use AppBundle\Entity\Email;
use AppBundle\Entity\ImapConnection;

interface IEmailProvider
{
    public function initialize(ImapConnection $connection = null);
    public function sendSingleEmail(Email $email);
    public function getContainer();
    public function setContainer();
}