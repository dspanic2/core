<?php

namespace SharedInboxBusinessBundle\Models;

use SharedInboxBusinessBundle\Entity\EmailAttachmentEntity;

class EmailAttachmentEntityCollection
{
    /** @var array $emailAttachmentEntities */
    private $emailAttachmentEntities = array();

    /**
     * @param EmailAttachmentEntity $emailAttachment
     */
    public function addEmailAttachmentEntity(EmailAttachmentEntity $emailAttachment)
    {
        $this->emailAttachmentEntities[] = $emailAttachment;
    }

    /**
     * @return array
     */
    public function getEmailAttachmentEntities()
    {
        return $this->emailAttachmentEntities;
    }
}