<?php

namespace SharedInboxBusinessBundle\Interfaces;

use SharedInboxBusinessBundle\Entity\SharedInboxConnectionEntity;

interface IEmailProvider
{
    public function initialize();
    public function setConnection(SharedInboxConnectionEntity $connection);
    public function getEmails($folder, $filter = null);
    public function saveEmail($sentMimeMessage);
}