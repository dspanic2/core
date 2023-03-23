<?php

namespace AppBundle\Models;

class MailAttachmentCollection
{
    /** @var array $addresses */
    private $attachments = array();

    /**
     * @param MailAttachment $attachment
     */
    public function addAttachment(MailAttachment $attachment)
    {
        $this->attachments[] = $attachment;
    }

    /**
     * @return array
     */
    public function getAttachments()
    {
        return $this->attachments;
    }
}