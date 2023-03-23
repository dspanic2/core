<?php

namespace SharedInboxBusinessBundle\Models;

use SharedInboxBusinessBundle\Entity\EmailEntity;

class EmailEntityCollection
{
    /** @var array $emailEntities */
    private $emailEntities = array();

    /**
     * @param EmailEntity $email
     */
    public function addEmailEntity(EmailEntity $email)
    {
        $this->emailEntities[] = $email;
    }

    /**
     * @return array
     */
    public function getEmailEntities()
    {
        return $this->emailEntities;
    }
}